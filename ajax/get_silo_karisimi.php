<?php
/**
 * Silo Karışımı AJAX Endpoint
 * İş emrine bağlı silo karışımını döndürür
 */
session_start();
include("../baglan.php");

// Güvenlik kontrolü
if (!isset($_SESSION["oturum"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$is_emri_id = (int) ($_GET['is_emri_id'] ?? 0);

if ($is_emri_id <= 0) {
    echo json_encode([]);
    exit;
}

$result = $baglanti->query("
    SELECT s.silo_adi, isk.yuzde, s.doluluk_m3, s.kapasite_m3, s.id as silo_id
    FROM is_emri_silo_karisimlari isk
    JOIN silolar s ON isk.silo_id = s.id
    WHERE isk.is_emri_id = $is_emri_id
    ORDER BY isk.yuzde DESC
");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'silo_id' => $row['silo_id'],
        'silo_adi' => $row['silo_adi'],
        'yuzde' => number_format($row['yuzde'], 1),
        'doluluk_m3' => $row['doluluk_m3'],
        'kapasite_m3' => $row['kapasite_m3'],
        'doluluk_yuzde' => round(($row['doluluk_m3'] / $row['kapasite_m3']) * 100)
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
