<?php
session_start();
include("../baglan.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["oturum"])) {
    echo json_encode(['success' => false, 'message' => 'Oturum kapalı.']);
    exit;
}

if (!isset($_GET['tavlama_1_id'])) {
    echo json_encode(['success' => false, 'message' => 'Tavlama 1 ID gerekli.']);
    exit;
}

$t1_id = (int) $_GET['tavlama_1_id'];

// Get the main tavlama 1 record
$t1_query = $baglanti->query("SELECT id, baslama_tarihi, toplam_tonaj FROM uretim_tavlama_1 WHERE id = $t1_id");
if (!$t1_query || $t1_query->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Tavlama 1 kaydı bulunamadı.']);
    exit;
}
$t1_data = $t1_query->fetch_assoc();

// Get the details
$detay_query = $baglanti->query("
    SELECT * FROM uretim_tavlama_1_detay 
    WHERE tavlama_1_id = $t1_id
    ORDER BY id ASC
");

$detaylar = [];
if ($detay_query && $detay_query->num_rows > 0) {
    while ($row = $detay_query->fetch_assoc()) {
        $detaylar[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'baslama_tarihi' => $t1_data['baslama_tarihi'],
    'toplam_tonaj' => $t1_data['toplam_tonaj'],
    'detaylar' => $detaylar
]);
?>