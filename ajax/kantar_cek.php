<?php
/**
 * Kantar Entegrasyonu - Anlık Tartım Okuma
 * Kantar PC (192.168.1.53) paylaşımından tartim.txt okur.
 * 
 * Kullanım: /ajax/kantar_cek.php
 * Döndürür: JSON
 */

// Hata göster - debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// =============================================
// KANTAR BAĞLANTISI (192.168.1.53:1453)
// =============================================
$ip = '192.168.1.53';
$port = 1453;
$url = "http://$ip:$port/tartim.txt";

// İsteği yapan JS'den gelen plaka
$istek_plaka = isset($_GET['plaka']) ? strtoupper(trim($_GET['plaka'])) : '';

$icerik = false;
$curl_error = '';
$errstr = '';
$errno = 0;

// YÖNTEM 1: cURL ile HTTP(S) İsteği
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 saniye bağlanma süresi
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 saniye veri bekleme süresi
    $icerik = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
}

// YÖNTEM 2: Eğer cURL çalışmadıysa (örneğin HTTP değil de saf TCP soketi ise) fsockopen ile dene
if (($icerik === false || trim($icerik) === '') && function_exists('fsockopen')) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, 3); // 3 saniye timeout
    if ($fp) {
        // Raw TCP verisi çekmeyi dene (bazı kantar cihazları direkt veriyi stream eder)
        stream_set_timeout($fp, 5);
        $icerik = '';
        while (!feof($fp)) {
            $icerik .= fgets($fp, 128);
        }
        fclose($fp);
    }
}

// YÖNTEM 3: Standart file_get_contents (http wrapper)
if ($icerik === false || trim($icerik) === '') {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $icerik = @file_get_contents($url, false, $ctx);
}

if ($icerik === false || trim(str_replace("\0", '', $icerik)) === '') {
    echo json_encode([
        'basari' => false,
        'hata' => "Kantar verisine ulaşılamadı. Sunucu ($ip:$port) açık mı kontrol edin. Detay: cURL ($curl_error), Soket ($errstr)",
    ]);
    exit;
}

// BOM ve Null Byte temizliği
$icerik = str_replace("\0", "", $icerik); // UTF-16 null paddinglerini temizle

// Encoding dönüşümü (Kantar Windows-1254 / ISO-8859-9 gönderebilir)
if (function_exists('mb_detect_encoding')) {
    $enc = mb_detect_encoding($icerik, ['UTF-8', 'ISO-8859-9', 'Windows-1254'], true);
    if ($enc && $enc !== 'UTF-8') {
        $icerik = mb_convert_encoding($icerik, 'UTF-8', $enc);
    }
}

// BOM temizliği
if (substr($icerik, 0, 3) === "\xEF\xBB\xBF") {
    $icerik = substr($icerik, 3);
}
elseif (substr($icerik, 0, 2) === "\xFF\xFE" || substr($icerik, 0, 2) === "\xFE\xFF") {
    $icerik = substr($icerik, 2);
}

// #* ile başlayan veri bloğunu bul
$icerik = trim($icerik);
$pos = strpos($icerik, '#*');
if ($pos !== false) {
    $icerik = substr($icerik, $pos + 2); // #* sonrasını al
}
// Sondaki *# varsa kaldır
if (substr($icerik, -2) === '*#') {
    $icerik = substr($icerik, 0, -2);
}
// Baştaki ve sondaki * ve boşlukları temizle
$icerik = trim($icerik, " \t\n\r*");

$parcalar = explode('*', $icerik);

// Minimum alan sayısı kontrolü
if (count($parcalar) < 7) {
    echo json_encode([
        'basari' => false,
        'hata' => 'Kantar verisi geçersiz format (' . count($parcalar) . ' alan). Ham: ' . htmlspecialchars(substr($icerik, 0, 80)) . '...',
    ]);
    exit;
}

// Alanları parse et
$plaka = trim($parcalar[0] ?? '');

// Plaka kontrolü (Eğer JS'den plaka gönderilmişse ve eşleşmiyorsa)
if ($istek_plaka !== '' && strpos(strtoupper($plaka), $istek_plaka) === false && strpos($istek_plaka, strtoupper($plaka)) === false) {
    echo json_encode([
        'basari' => false,
        'hata' => "Kantar verisindeki plaka ($plaka) ile ekrandaki plaka ($istek_plaka) eşleşmiyor! Lütfen doğru aracı tarttığınızdan emin olun.",
    ]);
    exit;
}
$tarih = trim($parcalar[1] ?? ''); // 03.03.2026
$saat = trim($parcalar[2] ?? ''); // 13:46:33
$brut_kg = (int)trim($parcalar[3] ?? 0); // 016720 → 16720
$tara_kg = (int)trim($parcalar[6] ?? 0); // 13940
$net_kg = (int)trim($parcalar[7] ?? 0); // 2780
$firma = trim($parcalar[8] ?? ''); // SBF ÇINAR
$urun = trim($parcalar[9] ?? ''); // UN
$kaynak_il = trim($parcalar[11] ?? ''); // GAZİANTEP
$hedef_il = trim($parcalar[12] ?? ''); // K.MARAŞ

// Sürücü alanı (telefon varsa ayır)
$surucu_ham = trim($parcalar[13] ?? '');
$surucu = preg_replace('/-?\d{10,}$/', '', $surucu_ham); // Sondaki telefon numarasını sil

// Tarihi MySQL formatına çevir (DD.MM.YYYY → YYYY-MM-DD)
$tarih_mysql = '';
if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $tarih, $m)) {
    $tarih_mysql = "{$m[3]}-{$m[2]}-{$m[1]}";
}

echo json_encode([
    'basari' => true,
    'plaka' => strtoupper($plaka),
    'brut_kg' => $brut_kg,
    'tara_kg' => $tara_kg,
    'net_kg' => $net_kg,
    'firma' => $firma,
    'urun' => $urun,
    'surucu' => trim($surucu),
    'kaynak_il' => $kaynak_il,
    'hedef_il' => $hedef_il,
    'tarih' => $tarih,
    'tarih_mysql' => $tarih_mysql,
    'saat' => $saat,
], JSON_UNESCAPED_UNICODE);
