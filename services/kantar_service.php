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

    $replacements = [
        "\x30\x01" => "İ",
        "\x31\x01" => "ı",
        "\x5e\x01" => "Ş",
        "\x5f\x01" => "ş",
        "\x1e\x01" => "Ğ",
        "\x1f\x01" => "ğ"
    ];
    $text = strtr($text, $replacements);

    return trim($text);
}

function kantarNormalizeTextField($text)
{
    $text = kantarUtf8((string) $text);
    if ($text === '') {
        return '';
    }

    $map = [
        'Ä°' => 'İ',
        'Ä±' => 'ı',
        'Ãœ' => 'Ü',
        'Ã¼' => 'ü',
        'Ã–' => 'Ö',
        'Ã¶' => 'ö',
        'Ã‡' => 'Ç',
        'Ã§' => 'ç',
        'Åž' => 'Ş',
        'ÅŸ' => 'ş',
        'Äž' => 'Ğ',
        'ÄŸ' => 'ğ',
        'Ý' => 'İ',
        'ý' => 'ı',
        'Þ' => 'Ş',
        'þ' => 'ş',
        'Ð' => 'Ğ',
        'ð' => 'ğ'
    ];

    $text = strtr($text, $map);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
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

function kantarPlateParts($plate)
{
    $plateUtf8 = kantarUtf8((string) $plate);
    $fullNorm = kantarNormalizePlate($plateUtf8);

    $coreRaw = $plateUtf8;
    if (strpos($plateUtf8, '-') !== false) {
        $tmp = explode('-', $plateUtf8, 2);
        $coreRaw = (string) ($tmp[0] ?? '');
    }
    $coreNorm = kantarNormalizePlate($coreRaw);

    return [
        'full_norm' => $fullNorm,
        'core_norm' => $coreNorm
    ];
}

function kantarPlateMatchMode($expectedPlate, $actualPlate)
{
    $expected = kantarPlateParts($expectedPlate);
    $actual = kantarPlateParts($actualPlate);

    if ($expected['full_norm'] !== '' && $expected['full_norm'] === $actual['full_norm']) {
        return 'exact';
    }

    if ($expected['core_norm'] !== '' && $expected['core_norm'] === $actual['core_norm']) {
        return 'core';
    }

    return '';
}

function kantarBuildSourceUrl($ip, $port, $file)
{
    $ip = trim((string) $ip);
    $port = (int) $port;
    $file = ltrim(trim((string) $file), '/');

    return "http://{$ip}:{$port}/{$file}";
}

function kantarError($code, $message, array $extra = [])
{
    return array_merge([
        'ok' => false,
        'error_code' => (string) $code,
        'error' => (string) $message
    ], $extra);
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
        if (is_resource($ch)) {
            curl_close($ch);
        } else {
            unset($ch);
        }
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
        return kantarError(
            'KANTAR_ERISIM',
            "Kantar verisi alinamadi ({$ip}:{$port}). cURL: {$curlError} Soket: {$socketError}",
            ['source_url' => $url]
        );
    }

    return [
        'ok' => true,
        'raw' => kantarUtf8($raw),
        'source_url' => $url
    ];
}

function kantarParseDateMysql($dateRaw)
{
    $dateRaw = trim((string) $dateRaw);
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateRaw, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

function kantarParseTimeMysql($timeRaw)
{
    $timeRaw = trim((string) $timeRaw);
    if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $timeRaw, $m)) {
        $sec = isset($m[3]) ? $m[3] : '00';
        return "{$m[1]}:{$m[2]}:{$sec}";
    }
    return null;
}

function kantarBuildSeferAnahtar(array $record)
{
    $canonical = [
        'plaka_norm' => (string) ($record['plaka_norm'] ?? ''),
        'giris_tarihi' => (string) ($record['giris_tarihi'] ?? ''),
        'giris_saati' => (string) ($record['giris_saati'] ?? ''),
        'tartim_tarihi' => (string) ($record['tartim_tarihi'] ?? ''),
        'tartim_saati' => (string) ($record['tartim_saati'] ?? '')
    ];

    $canonicalStr = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', (string) $canonicalStr);
}

function kantarParsePayload($rawPayload, $sourceUrl = '')
{
    $raw = kantarUtf8((string) $rawPayload);
    if ($raw === '') {
        return kantarError('FORMAT_BOS', 'Kantar verisi bos geldi.');
    }

    $start = strpos($raw, '#*');
    if ($start !== false) {
        $afterStart = (string) substr($raw, $start + 2);
        $hashPos = strrpos($afterStart, '#');
        if ($hashPos !== false) {
            $payload = (string) substr($afterStart, 0, $hashPos);
        } else {
            $payload = $afterStart;
        }
    } else {
        $payload = $raw;
    }

    $payload = trim($payload, " \t\n\r*#");
    if ($payload === '') {
        return kantarError('FORMAT_BOS', 'Kantar verisi bos geldi.');
    }

    $parts = explode('*', $payload);
    if (count($parts) < 14) {
        return kantarError('FORMAT_ALAN_EKSIK', 'Kantar verisi gecersiz formatta.');
    }

    $requiredIndexes = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13];
    foreach ($requiredIndexes as $idx) {
        if (!isset($parts[$idx]) || trim((string) $parts[$idx]) === '') {
            return kantarError('FORMAT_ALAN_EKSIK', "Kantar verisinde gerekli alan bos (index: {$idx}).");
        }
    }

    $plakaRaw = trim((string) ($parts[0] ?? ''));
    $plakaNorm = kantarNormalizePlate($plakaRaw);
    if ($plakaNorm === '') {
        return kantarError('FORMAT_PLAKA', 'Kantar plaka bilgisi gecersiz.');
    }

    $cikisTarihRaw = trim((string) ($parts[1] ?? ''));
    $cikisSaatRaw = trim((string) ($parts[2] ?? ''));
    $girisTarihRaw = trim((string) ($parts[4] ?? ''));
    $girisSaatRaw = trim((string) ($parts[5] ?? ''));

    $brutRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[3] ?? '0'));
    $taraRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[6] ?? '0'));
    $netRaw = preg_replace('/[^0-9\-]/', '', (string) ($parts[7] ?? '0'));

    if ($brutRaw === '' || $taraRaw === '' || $netRaw === '') {
        return kantarError('FORMAT_AGIRLIK', 'Kantar agirlik alanlari eksik.');
    }

    $brutKg = (int) $brutRaw;
    $taraKg = (int) $taraRaw;
    $netKg = (int) $netRaw;

    if ($brutKg <= 0 || $netKg <= 0 || $taraKg < 0) {
        return kantarError('FORMAT_AGIRLIK', 'Kantar agirlik degerleri gecersiz.');
    }
    if ($brutKg < $taraKg) {
        return kantarError('FORMAT_AGIRLIK', 'Brut agirlik tara agirligindan kucuk olamaz.');
    }
    if (($brutKg - $taraKg) !== $netKg) {
        return kantarError('ALAN_KAYMASI', 'Tartim alanlari tutarsiz. Veri kaymasi olabilir.');
    }

    $firma = trim((string) ($parts[8] ?? ''));
    $urun = trim((string) ($parts[9] ?? ''));
    $kaynakIl = trim((string) ($parts[11] ?? ''));
    $hedefIl = trim((string) ($parts[12] ?? ''));

    $surucuHam = trim((string) ($parts[13] ?? ''));
    $surucu = trim((string) preg_replace('/-?\s*\d{10,}$/u', '', $surucuHam));

    if ($firma === '' || $urun === '' || $kaynakIl === '' || $hedefIl === '' || $surucu === '') {
        return kantarError('FORMAT_ALAN_EKSIK', 'Kantar metin alanlari eksik.');
    }
    if (preg_match('/^\d+$/u', $firma)) {
        return kantarError('ALAN_KAYMASI', 'Firma alani sayisal geldi. Veri kaymasi olabilir.');
    }

    $tartimTarihi = kantarParseDateMysql($cikisTarihRaw);
    $tartimSaati = kantarParseTimeMysql($cikisSaatRaw);
    $girisTarihi = kantarParseDateMysql($girisTarihRaw);
    $girisSaati = kantarParseTimeMysql($girisSaatRaw);

    if (!$tartimTarihi || !$tartimSaati) {
        return kantarError('FORMAT_TARIH', 'Cikis tarih/saat alani gecersiz.');
    }
    if (!$girisTarihi || !$girisSaati) {
        return kantarError('FORMAT_TARIH', 'Giris tarih/saat alani gecersiz.');
    }

    $tartimZamani = $tartimTarihi . ' ' . $tartimSaati;
    $girisZamani = $girisTarihi . ' ' . $girisSaati;

    $record = [
        'source_url' => (string) $sourceUrl,
        'plaka_raw' => $plakaRaw,
        'plaka_norm' => $plakaNorm,
        'giris_tarihi' => $girisTarihi,
        'giris_saati' => $girisSaati,
        'giris_zamani' => $girisZamani,
        'tartim_tarihi' => $tartimTarihi,
        'tartim_saati' => $tartimSaati,
        'tartim_zamani' => $tartimZamani,
        'brut_kg' => $brutKg,
        'tara_kg' => $taraKg,
        'net_kg' => $netKg,
        'firma' => $firma,
        'urun' => $urun,
        'kaynak_il' => $kaynakIl,
        'hedef_il' => $hedefIl,
        'surucu' => $surucu,
        'ham_veri' => $payload
    ];

    $record['sefer_anahtar'] = kantarBuildSeferAnahtar($record);

    $canonical = [
        'plaka_norm' => $record['plaka_norm'],
        'giris_tarihi' => $record['giris_tarihi'],
        'giris_saati' => $record['giris_saati'],
        'tartim_tarihi' => $record['tartim_tarihi'],
        'tartim_saati' => $record['tartim_saati'],
        'brut_kg' => $record['brut_kg'],
        'tara_kg' => $record['tara_kg'],
        'net_kg' => $record['net_kg'],
        'firma' => $record['firma'],
        'urun' => $record['urun'],
        'kaynak_il' => $record['kaynak_il'],
        'hedef_il' => $record['hedef_il'],
        'surucu' => $record['surucu'],
        'sefer_anahtar' => $record['sefer_anahtar']
    ];
    $canonicalStr = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $record['veri_hash'] = hash('sha256', (string) $canonicalStr);

    return [
        'ok' => true,
        'record' => $record
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

function kantarColumnExists($baglanti, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $tableEsc = $baglanti->real_escape_string((string) $table);
    $columnEsc = $baglanti->real_escape_string((string) $column);
    $res = @$baglanti->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    $cache[$key] = ($res && $res->num_rows > 0);
    return $cache[$key];
}

function kantarHasSeferAnahtarColumn($baglanti)
{
    if (!kantarTableExists($baglanti)) {
        return false;
    }
    return kantarColumnExists($baglanti, 'kantar_okumalari', 'sefer_anahtar');
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
        return kantarError('TABLO_YOK', 'kantar_okumalari tablosu bulunamadi.');
    }

    $veriHash = $baglanti->real_escape_string((string) ($record['veri_hash'] ?? ''));
    if ($veriHash === '') {
        return kantarError('HASH_BOS', 'veri_hash bos olamaz.');
    }

    $existingByHash = $baglanti->query("SELECT id FROM kantar_okumalari WHERE veri_hash = '{$veriHash}' LIMIT 1");
    if ($existingByHash && $existingByHash->num_rows > 0) {
        $row = $existingByHash->fetch_assoc();
        return [
            'ok' => true,
            'id' => (int) $row['id'],
            'yeni' => false,
            'guncellendi' => false
        ];
    }

    $hasSeferAnahtar = kantarHasSeferAnahtarColumn($baglanti);
    $seferAnahtar = trim((string) ($record['sefer_anahtar'] ?? ''));

    if ($hasSeferAnahtar && $seferAnahtar !== '') {
        $seferEsc = $baglanti->real_escape_string($seferAnahtar);
        $existingByTrip = $baglanti->query("SELECT id FROM kantar_okumalari WHERE sefer_anahtar = '{$seferEsc}' LIMIT 1");
        if ($existingByTrip && $existingByTrip->num_rows > 0) {
            $found = $existingByTrip->fetch_assoc();
            $id = (int) $found['id'];

            $updateSql = "UPDATE kantar_okumalari SET
                            source_url = " . kantarQuoteOrNull($baglanti, $record['source_url'] ?? null) . ",
                            plaka_raw = " . kantarQuoteOrNull($baglanti, $record['plaka_raw'] ?? null) . ",
                            plaka_norm = " . kantarQuoteOrNull($baglanti, $record['plaka_norm'] ?? null) . ",
                            tartim_tarihi = " . kantarQuoteOrNull($baglanti, $record['tartim_tarihi'] ?? null) . ",
                            tartim_saati = " . kantarQuoteOrNull($baglanti, $record['tartim_saati'] ?? null) . ",
                            tartim_zamani = " . kantarQuoteOrNull($baglanti, $record['tartim_zamani'] ?? null) . ",
                            brut_kg = " . (int) ($record['brut_kg'] ?? 0) . ",
                            tara_kg = " . (int) ($record['tara_kg'] ?? 0) . ",
                            net_kg = " . (int) ($record['net_kg'] ?? 0) . ",
                            firma = " . kantarQuoteOrNull($baglanti, $record['firma'] ?? null) . ",
                            urun = " . kantarQuoteOrNull($baglanti, $record['urun'] ?? null) . ",
                            kaynak_il = " . kantarQuoteOrNull($baglanti, $record['kaynak_il'] ?? null) . ",
                            hedef_il = " . kantarQuoteOrNull($baglanti, $record['hedef_il'] ?? null) . ",
                            surucu = " . kantarQuoteOrNull($baglanti, $record['surucu'] ?? null) . ",
                            ham_veri = " . kantarQuoteOrNull($baglanti, $record['ham_veri'] ?? null) . ",
                            veri_hash = " . kantarQuoteOrNull($baglanti, $record['veri_hash'] ?? null) . ",
                            sefer_anahtar = " . kantarQuoteOrNull($baglanti, $seferAnahtar) . ",
                            cekim_zamani = NOW()
                          WHERE id = {$id}
                          LIMIT 1";

            if (!$baglanti->query($updateSql)) {
                return kantarError('DB_GUNCELLEME', 'Kantar kaydi guncellenemedi: ' . $baglanti->error);
            }

            return [
                'ok' => true,
                'id' => $id,
                'yeni' => false,
                'guncellendi' => true
            ];
        }
    }

    $columns = [
        'source_url',
        'plaka_raw',
        'plaka_norm',
        'tartim_tarihi',
        'tartim_saati',
        'tartim_zamani',
        'brut_kg',
        'tara_kg',
        'net_kg',
        'firma',
        'urun',
        'kaynak_il',
        'hedef_il',
        'surucu',
        'ham_veri',
        'veri_hash',
        'cekim_zamani'
    ];
    $values = [
        kantarQuoteOrNull($baglanti, $record['source_url'] ?? null),
        kantarQuoteOrNull($baglanti, $record['plaka_raw'] ?? null),
        kantarQuoteOrNull($baglanti, $record['plaka_norm'] ?? null),
        kantarQuoteOrNull($baglanti, $record['tartim_tarihi'] ?? null),
        kantarQuoteOrNull($baglanti, $record['tartim_saati'] ?? null),
        kantarQuoteOrNull($baglanti, $record['tartim_zamani'] ?? null),
        (int) ($record['brut_kg'] ?? 0),
        (int) ($record['tara_kg'] ?? 0),
        (int) ($record['net_kg'] ?? 0),
        kantarQuoteOrNull($baglanti, $record['firma'] ?? null),
        kantarQuoteOrNull($baglanti, $record['urun'] ?? null),
        kantarQuoteOrNull($baglanti, $record['kaynak_il'] ?? null),
        kantarQuoteOrNull($baglanti, $record['hedef_il'] ?? null),
        kantarQuoteOrNull($baglanti, $record['surucu'] ?? null),
        kantarQuoteOrNull($baglanti, $record['ham_veri'] ?? null),
        kantarQuoteOrNull($baglanti, $record['veri_hash'] ?? null),
        'NOW()'
    ];

    if ($hasSeferAnahtar) {
        $columns[] = 'sefer_anahtar';
        $values[] = kantarQuoteOrNull($baglanti, $seferAnahtar);
    }

    $sql = "INSERT INTO kantar_okumalari (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")";

    if (!$baglanti->query($sql)) {
        if ((int) $baglanti->errno === 1062) {
            $raceByHash = $baglanti->query("SELECT id FROM kantar_okumalari WHERE veri_hash = '{$veriHash}' LIMIT 1");
            if ($raceByHash && $raceByHash->num_rows > 0) {
                $row = $raceByHash->fetch_assoc();
                return [
                    'ok' => true,
                    'id' => (int) $row['id'],
                    'yeni' => false,
                    'guncellendi' => false
                ];
            }
            if ($hasSeferAnahtar && $seferAnahtar !== '') {
                $seferEsc = $baglanti->real_escape_string($seferAnahtar);
                $raceByTrip = $baglanti->query("SELECT id FROM kantar_okumalari WHERE sefer_anahtar = '{$seferEsc}' LIMIT 1");
                if ($raceByTrip && $raceByTrip->num_rows > 0) {
                    $row = $raceByTrip->fetch_assoc();
                    return [
                        'ok' => true,
                        'id' => (int) $row['id'],
                        'yeni' => false,
                        'guncellendi' => true
                    ];
                }
            }
        }
        return kantarError('DB_EKLEME', 'Kantar kaydi yazilamadi: ' . $baglanti->error);
    }

    return [
        'ok' => true,
        'id' => (int) $baglanti->insert_id,
        'yeni' => true,
        'guncellendi' => false
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

function kantarGetLatestByPlate($baglanti, $plate, $allowFlexible = true)
{
    if (!kantarTableExists($baglanti)) {
        return kantarError('TABLO_YOK', 'kantar_okumalari tablosu bulunamadi.');
    }

    $plateParts = kantarPlateParts($plate);
    $plateNorm = $plateParts['full_norm'];
    if ($plateNorm === '') {
        return kantarError('PLAKA_BOS', 'Plaka bos olamaz.');
    }

    $plateNormEsc = $baglanti->real_escape_string($plateNorm);
    $exactSql = "SELECT *
                 FROM kantar_okumalari
                 WHERE plaka_norm = '{$plateNormEsc}'
                 ORDER BY COALESCE(tartim_zamani, cekim_zamani) DESC, id DESC
                 LIMIT 1";

    $exactRes = $baglanti->query($exactSql);
    if ($exactRes && $exactRes->num_rows > 0) {
        return [
            'ok' => true,
            'found' => true,
            'match_mode' => 'exact',
            'row' => $exactRes->fetch_assoc()
        ];
    }

    if (!$allowFlexible) {
        return ['ok' => true, 'found' => false];
    }

    $coreNorm = $plateParts['core_norm'];
    if ($coreNorm === '') {
        return ['ok' => true, 'found' => false];
    }

    $coreEsc = $baglanti->real_escape_string($coreNorm);
    $flexSql = "SELECT *
                FROM kantar_okumalari
                WHERE plaka_norm = '{$coreEsc}' OR plaka_norm LIKE '{$coreEsc}%'
                ORDER BY COALESCE(tartim_zamani, cekim_zamani) DESC, id DESC
                LIMIT 50";

    $flexRes = $baglanti->query($flexSql);
    if ($flexRes && $flexRes->num_rows > 0) {
        while ($candidate = $flexRes->fetch_assoc()) {
            $candidatePlate = (string) ($candidate['plaka_raw'] ?? $candidate['plaka_norm'] ?? '');
            $mode = kantarPlateMatchMode($plate, $candidatePlate);
            if ($mode !== '') {
                return [
                    'ok' => true,
                    'found' => true,
                    'match_mode' => $mode,
                    'row' => $candidate
                ];
            }
        }
    }

    return ['ok' => true, 'found' => false];
}

function kantarGetLatestOverall($baglanti)
{
    if (!kantarTableExists($baglanti)) {
        return kantarError('TABLO_YOK', 'kantar_okumalari tablosu bulunamadi.');
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

function kantarFetchStableParsedRecord($ip, $port, $file = 'tartim.txt', $delayMs = 180)
{
    $firstRead = kantarReadEndpoint($ip, $port, $file);
    if (!$firstRead['ok']) {
        return $firstRead;
    }

    $firstParsed = kantarParsePayload($firstRead['raw'], $firstRead['source_url']);
    if (!$firstParsed['ok']) {
        return $firstParsed;
    }

    $delayMs = (int) $delayMs;
    if ($delayMs > 0 && $delayMs <= 2000) {
        usleep($delayMs * 1000);
    }

    $secondRead = kantarReadEndpoint($ip, $port, $file);
    if (!$secondRead['ok']) {
        return $secondRead;
    }

    $secondParsed = kantarParsePayload($secondRead['raw'], $secondRead['source_url']);
    if (!$secondParsed['ok']) {
        return kantarError(
            'KARARSIZ_VERI',
            'Kantar verisi kararsiz gorunuyor. Lutfen tekrar deneyin.',
            ['detail' => (string) ($secondParsed['error'] ?? '')]
        );
    }

    $hashA = (string) ($firstParsed['record']['veri_hash'] ?? '');
    $hashB = (string) ($secondParsed['record']['veri_hash'] ?? '');
    if ($hashA === '' || $hashB === '' || $hashA !== $hashB) {
        return kantarError('KARARSIZ_VERI', 'Kantar verisi iki okumada farkli geldi. Lutfen tekrar deneyin.');
    }

    return [
        'ok' => true,
        'record' => $secondParsed['record']
    ];
}

function kantarFetchParseAndStore($baglanti, $ip, $port, $file = 'tartim.txt', $expectedPlate = '')
{
    $stable = kantarFetchStableParsedRecord($ip, $port, $file);
    if (!$stable['ok']) {
        return $stable;
    }

    $record = $stable['record'];
    $matchMode = '';

    $expectedNorm = kantarNormalizePlate($expectedPlate);
    if ($expectedNorm !== '') {
        $matchMode = kantarPlateMatchMode($expectedPlate, (string) ($record['plaka_raw'] ?? ''));
        if ($matchMode === '') {
            return kantarError('PLAKA_ESLESMEDI', 'Kantar plakasi ile ekran plakasi eslesmiyor.');
        }
    }

    $store = kantarInsertIfNew($baglanti, $record);
    if (!$store['ok']) {
        return $store;
    }

    $row = kantarGetReadingById($baglanti, $store['id']);
    if (!$row) {
        return kantarError('DB_OKUNAMADI', 'Kayit yazildi ama okunamadi.');
    }

    return [
        'ok' => true,
        'record' => $record,
        'store' => $store,
        'row' => $row,
        'match_mode' => $matchMode
    ];
}

function kantarRowToApi(array $row, $kaynak = 'sql', $eslesmeTipi = '')
{
    $tarihMysql = $row['tartim_tarihi'] ?? null;
    $tarih = '';
    if (!empty($tarihMysql) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarihMysql)) {
        $tarih = date('d.m.Y', strtotime($tarihMysql));
    }

    $firma = kantarNormalizeTextField((string) ($row['firma'] ?? ''));
    $urun = kantarNormalizeTextField((string) ($row['urun'] ?? ''));
    $surucu = kantarNormalizeTextField((string) ($row['surucu'] ?? ''));
    $kaynakIl = kantarNormalizeTextField((string) ($row['kaynak_il'] ?? ''));
    $hedefIl = kantarNormalizeTextField((string) ($row['hedef_il'] ?? ''));

    return [
        'basari' => true,
        'kantar_okuma_id' => (int) ($row['id'] ?? 0),
        'kaynak' => $kaynak,
        'eslesme_tipi' => (string) $eslesmeTipi,
        'plaka' => (string) ($row['plaka_raw'] ?? ''),
        'brut_kg' => (int) round((float) ($row['brut_kg'] ?? 0)),
        'tara_kg' => (int) round((float) ($row['tara_kg'] ?? 0)),
        'net_kg' => (int) round((float) ($row['net_kg'] ?? 0)),
        'firma' => $firma,
        'urun' => $urun,
        'surucu' => $surucu,
        'kaynak_il' => $kaynakIl,
        'hedef_il' => $hedefIl,
        'tarih' => $tarih,
        'tarih_mysql' => !empty($row['tartim_tarihi']) ? (string) $row['tartim_tarihi'] : '',
        'saat' => !empty($row['tartim_saati']) ? (string) $row['tartim_saati'] : '',
        'tartim_zamani' => !empty($row['tartim_zamani']) ? (string) $row['tartim_zamani'] : ''
    ];
}
