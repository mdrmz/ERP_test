<?php

if (defined('KANTAR_SERVICE_LOADED')) {
    return;
}
define('KANTAR_SERVICE_LOADED', true);

function kantarUtf8($text)
{
    if (!is_string($text)) {
        return '';
    }

    $text = str_replace("\0", '', $text);

    if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
        $text = substr($text, 3);
    } elseif (substr($text, 0, 2) === "\xFF\xFE" || substr($text, 0, 2) === "\xFE\xFF") {
        $text = substr($text, 2);
    }

    if (function_exists('mb_detect_encoding')) {
        $enc = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-9', 'Windows-1254', 'ISO-8859-1'], true);
        if ($enc && $enc !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', $enc);
        }
    }

    return trim($text);
}

function kantarNormalizePlate($plate)
{
    $plate = kantarUtf8((string) $plate);

    if (function_exists('mb_strtoupper')) {
        $plate = mb_strtoupper($plate, 'UTF-8');
    } else {
        $plate = strtoupper($plate);
    }

    $plate = preg_replace('/[^A-Z0-9]/u', '', $plate);
    return trim((string) $plate);
}

function kantarBuildSourceUrl($ip, $port, $file)
{
    $ip = trim((string) $ip);
    $port = (int) $port;
    $file = ltrim(trim((string) $file), '/');

    return "http://{$ip}:{$port}/{$file}";
}

function kantarReadEndpoint($ip, $port, $file = 'tartim.txt')
{
    $url = kantarBuildSourceUrl($ip, $port, $file);
    $raw = false;
    $curlError = '';
    $socketError = '';
    $socketNo = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        $curlError = (string) curl_error($ch);
        curl_close($ch);
    }

    if (($raw === false || trim((string) $raw) === '') && function_exists('fsockopen')) {
        $fp = @fsockopen((string) $ip, (int) $port, $socketNo, $socketError, 3);
        if ($fp) {
            stream_set_timeout($fp, 5);
            $tmp = '';
            while (!feof($fp)) {
                $line = fgets($fp, 256);
                if ($line === false) {
                    break;
                }
                $tmp .= $line;
            }
            fclose($fp);
            if (trim($tmp) !== '') {
                $raw = $tmp;
            }
        }
    }

    if ($raw === false || trim((string) $raw) === '') {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5
            ]
        ]);
        $raw = @file_get_contents($url, false, $ctx);
    }

    $raw = (string) $raw;
    if (trim(str_replace("\0", '', $raw)) === '') {
        return [
            'ok' => false,
            'error' => "Kantar verisi alinamadi ({$ip}:{$port}). cURL: {$curlError} Soket: {$socketError}",
            'source_url' => $url
        ];
    }

    return [
        'ok' => true,
        'raw' => kantarUtf8($raw),
        'source_url' => $url
    ];
}

function kantarParsePayload($rawPayload, $sourceUrl = '')
{
    $payload = kantarUtf8((string) $rawPayload);
    $pos = strpos($payload, '#*');
    if ($pos !== false) {
        $payload = substr($payload, $pos + 2);
    }

    if (substr($payload, -2) === '*#') {
        $payload = substr($payload, 0, -2);
    }

    $payload = trim($payload, " \t\n\r*");
    $parts = explode('*', $payload);

    if (count($parts) < 8) {
        return [
            'ok' => false,
            'error' => 'Kantar verisi gecersiz formatta.'
        ];
    }

    $plakaRaw = trim((string) ($parts[0] ?? ''));
    $plakaNorm = kantarNormalizePlate($plakaRaw);

    $tarihRaw = trim((string) ($parts[1] ?? ''));
    $saatRaw = trim((string) ($parts[2] ?? ''));

    $brutRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[3] ?? '0'));
    $taraRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[6] ?? '0'));
    $netRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[7] ?? '0'));

    $brutKg = (int) ($brutRaw === '' ? 0 : $brutRaw);
    $taraKg = (int) ($taraRaw === '' ? 0 : $taraRaw);
    $netKg = (int) ($netRaw === '' ? 0 : $netRaw);

    $firma = trim((string) ($parts[8] ?? ''));
    $urun = trim((string) ($parts[9] ?? ''));
    $kaynakIl = trim((string) ($parts[11] ?? ''));
    $hedefIl = trim((string) ($parts[12] ?? ''));

    $surucuHam = trim((string) ($parts[13] ?? ''));
    $surucu = trim((string) preg_replace('/-?\s*\d{10,}$/u', '', $surucuHam));

    $tarihMysql = null;
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $tarihRaw, $m)) {
        $tarihMysql = "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    $saatMysql = null;
    if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $saatRaw, $m)) {
        $sec = isset($m[3]) ? $m[3] : '00';
        $saatMysql = "{$m[1]}:{$m[2]}:{$sec}";
    }

    $tartimZamani = null;
    if ($tarihMysql && $saatMysql) {
        $tartimZamani = $tarihMysql . ' ' . $saatMysql;
    }

    $canonical = [
        'plaka_norm' => $plakaNorm,
        'tartim_tarihi' => $tarihMysql,
        'tartim_saati' => $saatMysql,
        'brut_kg' => $brutKg,
        'tara_kg' => $taraKg,
        'net_kg' => $netKg,
        'firma' => $firma,
        'urun' => $urun,
        'kaynak_il' => $kaynakIl,
        'hedef_il' => $hedefIl,
        'surucu' => $surucu
    ];
    $canonicalStr = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $veriHash = hash('sha256', (string) $canonicalStr);

    return [
        'ok' => true,
        'record' => [
            'source_url' => (string) $sourceUrl,
            'plaka_raw' => $plakaRaw,
            'plaka_norm' => $plakaNorm,
            'tartim_tarihi' => $tarihMysql,
            'tartim_saati' => $saatMysql,
            'tartim_zamani' => $tartimZamani,
            'brut_kg' => $brutKg,
            'tara_kg' => $taraKg,
            'net_kg' => $netKg,
            'firma' => $firma,
            'urun' => $urun,
            'kaynak_il' => $kaynakIl,
            'hedef_il' => $hedefIl,
            'surucu' => $surucu,
            'ham_veri' => $payload,
            'veri_hash' => $veriHash
        ]
    ];
}

function kantarTableExists($baglanti)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $result = @$baglanti->query("SHOW TABLES LIKE 'kantar_okumalari'");
    $cached = ($result && $result->num_rows > 0);
    return $cached;
}

function kantarQuoteOrNull($baglanti, $value)
{
    if ($value === null || $value === '') {
        return 'NULL';
    }
    return "'" . $baglanti->real_escape_string((string) $value) . "'";
}

function kantarInsertIfNew($baglanti, array $record)
{
    if (!kantarTableExists($baglanti)) {
        return ['ok' => false, 'error' => 'kantar_okumalari tablosu bulunamadi.'];
    }

    $veriHash = $baglanti->real_escape_string((string) ($record['veri_hash'] ?? ''));
    if ($veriHash === '') {
        return ['ok' => false, 'error' => 'veri_hash bos olamaz.'];
    }

    $existing = $baglanti->query("SELECT id FROM kantar_okumalari WHERE veri_hash = '{$veriHash}' LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $row = $existing->fetch_assoc();
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'yeni' => false
        ];
    }

    $sql = "INSERT INTO kantar_okumalari (
                source_url, plaka_raw, plaka_norm, tartim_tarihi, tartim_saati, tartim_zamani,
                brut_kg, tara_kg, net_kg, firma, urun, kaynak_il, hedef_il, surucu, ham_veri, veri_hash, cekim_zamani
            ) VALUES (
                " . kantarQuoteOrNull($baglanti, $record['source_url'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['plaka_raw'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['plaka_norm'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['tartim_tarihi'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['tartim_saati'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['tartim_zamani'] ?? null) . ",
                " . (int) ($record['brut_kg'] ?? 0) . ",
                " . (int) ($record['tara_kg'] ?? 0) . ",
                " . (int) ($record['net_kg'] ?? 0) . ",
                " . kantarQuoteOrNull($baglanti, $record['firma'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['urun'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['kaynak_il'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['hedef_il'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['surucu'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['ham_veri'] ?? null) . ",
                " . kantarQuoteOrNull($baglanti, $record['veri_hash'] ?? null) . ",
                NOW()
            )";

    if (!$baglanti->query($sql)) {
        return [
            'ok' => false,
            'error' => 'Kantar kaydi yazilamadi: ' . $baglanti->error
        ];
    }

    return [
        'ok' => true,
        'id' => (int) $baglanti->insert_id,
        'yeni' => true
    ];
}

function kantarGetReadingById($baglanti, $id)
{
    if (!kantarTableExists($baglanti)) {
        return null;
    }

    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    $res = $baglanti->query("SELECT * FROM kantar_okumalari WHERE id = {$id} LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return null;
}

function kantarGetLatestByPlate($baglanti, $plate)
{
    if (!kantarTableExists($baglanti)) {
        return ['ok' => false, 'error' => 'kantar_okumalari tablosu bulunamadi.'];
    }

    $plateNorm = kantarNormalizePlate($plate);
    if ($plateNorm === '') {
        return ['ok' => false, 'error' => 'Plaka bos olamaz.'];
    }

    $plateNormEsc = $baglanti->real_escape_string($plateNorm);
    $sql = "SELECT *
            FROM kantar_okumalari
            WHERE plaka_norm = '{$plateNormEsc}'
            ORDER BY COALESCE(tartim_zamani, cekim_zamani) DESC, id DESC
            LIMIT 1";

    $res = $baglanti->query($sql);
    if ($res && $res->num_rows > 0) {
        return ['ok' => true, 'found' => true, 'row' => $res->fetch_assoc()];
    }

    return ['ok' => true, 'found' => false];
}

function kantarGetLatestOverall($baglanti)
{
    if (!kantarTableExists($baglanti)) {
        return ['ok' => false, 'error' => 'kantar_okumalari tablosu bulunamadi.'];
    }

    $sql = "SELECT *
            FROM kantar_okumalari
            ORDER BY COALESCE(tartim_zamani, cekim_zamani) DESC, id DESC
            LIMIT 1";

    $res = $baglanti->query($sql);
    if ($res && $res->num_rows > 0) {
        return ['ok' => true, 'found' => true, 'row' => $res->fetch_assoc()];
    }

    return ['ok' => true, 'found' => false];
}

function kantarFetchParseAndStore($baglanti, $ip, $port, $file = 'tartim.txt', $expectedPlate = '')
{
    $read = kantarReadEndpoint($ip, $port, $file);
    if (!$read['ok']) {
        return $read;
    }

    $parsed = kantarParsePayload($read['raw'], $read['source_url']);
    if (!$parsed['ok']) {
        return $parsed;
    }

    $record = $parsed['record'];

    $expectedNorm = kantarNormalizePlate($expectedPlate);
    if ($expectedNorm !== '' && $record['plaka_norm'] !== $expectedNorm) {
        return [
            'ok' => false,
            'error' => 'Kantar plakasi ile ekran plakasi eslesmiyor.'
        ];
    }

    $store = kantarInsertIfNew($baglanti, $record);
    if (!$store['ok']) {
        return $store;
    }

    $row = kantarGetReadingById($baglanti, $store['id']);
    if (!$row) {
        return [
            'ok' => false,
            'error' => 'Kayit yazildi ama okunamadi.'
        ];
    }

    return [
        'ok' => true,
        'record' => $record,
        'store' => $store,
        'row' => $row
    ];
}

function kantarRowToApi(array $row, $kaynak = 'sql')
{
    $tarihMysql = $row['tartim_tarihi'] ?? null;
    $tarih = '';
    if (!empty($tarihMysql) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarihMysql)) {
        $tarih = date('d.m.Y', strtotime($tarihMysql));
    }

    return [
        'basari' => true,
        'kantar_okuma_id' => (int) ($row['id'] ?? 0),
        'kaynak' => $kaynak,
        'plaka' => (string) ($row['plaka_raw'] ?? ''),
        'brut_kg' => (int) round((float) ($row['brut_kg'] ?? 0)),
        'tara_kg' => (int) round((float) ($row['tara_kg'] ?? 0)),
        'net_kg' => (int) round((float) ($row['net_kg'] ?? 0)),
        'firma' => (string) ($row['firma'] ?? ''),
        'urun' => (string) ($row['urun'] ?? ''),
        'surucu' => (string) ($row['surucu'] ?? ''),
        'kaynak_il' => (string) ($row['kaynak_il'] ?? ''),
        'hedef_il' => (string) ($row['hedef_il'] ?? ''),
        'tarih' => $tarih,
        'tarih_mysql' => !empty($row['tartim_tarihi']) ? (string) $row['tartim_tarihi'] : '',
        'saat' => !empty($row['tartim_saati']) ? (string) $row['tartim_saati'] : '',
        'tartim_zamani' => !empty($row['tartim_zamani']) ? (string) $row['tartim_zamani'] : ''
    ];
}

