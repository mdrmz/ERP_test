<?php
session_start();
include("../baglan.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["oturum"])) {
    echo json_encode(['success' => false, 'message' => 'Oturum kapalı.']);
    exit;
}

if (!isset($_GET['tavlama_2_id'])) {
    echo json_encode(['success' => false, 'message' => 'Tavlama 2 ID gerekli.']);
    exit;
}

$t2_id = (int) $_GET['tavlama_2_id'];

// Get the main tavlama 2 record
$t2_query = $baglanti->query("SELECT id, baslama_tarihi, toplam_tonaj FROM uretim_tavlama_2 WHERE id = $t2_id");
if (!$t2_query || $t2_query->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Tavlama 2 kaydı bulunamadı.']);
    exit;
}
$t2_data = $t2_query->fetch_assoc();

// Get the details
$detay_query = $baglanti->query("
    SELECT * FROM uretim_tavlama_2_detay 
    WHERE tavlama_2_id = $t2_id
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
    'baslama_tarihi' => $t2_data['baslama_tarihi'],
    'toplam_tonaj' => $t2_data['toplam_tonaj'],
    'detaylar' => $detaylar
]);
?>