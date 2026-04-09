<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

include(__DIR__ . '/../baglan.php');
include(__DIR__ . '/../services/kantar_service.php');

$baglanti->set_charset('utf8mb4');

$ip = '192.168.1.53';
$port = 1453;
$file = 'tartim.txt';

$istekPlaka = isset($_GET['plaka']) ? trim((string) $_GET['plaka']) : '';

if ($istekPlaka === '') {
    echo json_encode([
        'basari' => false,
        'hata_kodu' => 'PLAKA_BOS',
        'hata' => 'Plaka bilgisi bos olamaz.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) SQL-first: plaka icin en guncel kayit
$liveResult = kantarFetchParseAndStore($baglanti, $ip, $port, $file, $istekPlaka);
if (!empty($liveResult['ok'])) {
    $matchMode = (string) ($liveResult['match_mode'] ?? '');
    echo json_encode(kantarRowToApi($liveResult['row'], 'live', $matchMode), JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Canli veride eslesme olmazsa SQL cache'e dus
$dbResult = kantarGetLatestByPlate($baglanti, $istekPlaka, true);
if (!empty($dbResult['ok']) && !empty($dbResult['found'])) {
    $matchMode = (string) ($dbResult['match_mode'] ?? '');
    echo json_encode(kantarRowToApi($dbResult['row'], 'sql', $matchMode), JSON_UNESCAPED_UNICODE);
    exit;
}

$errorCode = (string) ($liveResult['error_code'] ?? '');
$err = (string) ($liveResult['error'] ?? 'Kantar verisi alinamadi.');

if ($err === '' || $errorCode === 'PLAKA_ESLESMEDI') {
    $err = 'Bu plakaya ait uygun bir kantar kaydi bulunamadi.';
}
if ($errorCode === 'KARARSIZ_VERI') {
    $err = 'Kantar verisi o an kararsiz geldi, lutfen tekrar deneyin.';
}
if ($errorCode === '' && !empty($dbResult['error_code'])) {
    $errorCode = (string) $dbResult['error_code'];
}
if (!empty($dbResult['error']) && $errorCode === '') {
    $err .= ' SQL: ' . (string) $dbResult['error'];
}

echo json_encode([
    'basari' => false,
    'hata_kodu' => $errorCode,
    'hata' => $err
], JSON_UNESCAPED_UNICODE);
