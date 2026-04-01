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
        'hata' => 'Plaka bilgisi bos olamaz.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) SQL-first: plaka icin en guncel kayit
$dbResult = kantarGetLatestByPlate($baglanti, $istekPlaka);
if (!empty($dbResult['ok']) && !empty($dbResult['found'])) {
    echo json_encode(kantarRowToApi($dbResult['row'], 'sql'), JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) SQL'de yoksa canli fallback ile cek, parse et, dedup ile DB'ye yaz
$fallback = kantarFetchParseAndStore($baglanti, $ip, $port, $file, $istekPlaka);
if (!empty($fallback['ok'])) {
    echo json_encode(kantarRowToApi($fallback['row'], 'fallback'), JSON_UNESCAPED_UNICODE);
    exit;
}

$err = (string) ($fallback['error'] ?? 'Kantar verisi alinamadi.');
if (!empty($dbResult['error'])) {
    $err .= ' SQL: ' . (string) $dbResult['error'];
}

echo json_encode([
    'basari' => false,
    'hata' => $err
], JSON_UNESCAPED_UNICODE);
