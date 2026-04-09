<?php
session_start();
include("../baglan.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['silo_id'])) {
    echo json_encode(['error' => 'silo_id belirtilmedi']);
    exit;
}

$silo_id = (int)$_GET['silo_id'];

// 1. Toplam kalan stok
$sql_stok = "SELECT COALESCE(SUM(kalan_miktar_kg), 0) as toplam_kalan FROM silo_stok_detay WHERE silo_id = $silo_id AND durum = 'aktif'";
$res_stok = $baglanti->query($sql_stok);
$toplam_kalan = 0;
if ($res_stok && $row = $res_stok->fetch_assoc()) {
    $toplam_kalan = (float)$row['toplam_kalan'];
}

// 2. Rezerve stok (Henüz Tavlama'ya girmemiş, 'hazirlaniyor' durumundaki paçallar)
$sql_rezerve = "
    SELECT COALESCE(SUM(pd.miktar_kg), 0) as rezerve_kg 
    FROM uretim_pacal_detay pd 
    JOIN uretim_pacal p ON pd.pacal_id = p.id 
    WHERE p.durum = 'hazirlaniyor' AND pd.silo_id = $silo_id
";
$res_rezerve = $baglanti->query($sql_rezerve);
$rezerve_kg = 0;
if ($res_rezerve && $row = $res_rezerve->fetch_assoc()) {
    $rezerve_kg = (float)$row['rezerve_kg'];
}

$serbest_kg = $toplam_kalan - $rezerve_kg;

// 3. FIFO Kod (En eski aktif parti)
$sql_fifo = "SELECT parti_kodu, hammadde_turu FROM silo_stok_detay WHERE silo_id = $silo_id AND durum = 'aktif' ORDER BY giris_tarihi ASC LIMIT 1";
$res_fifo = $baglanti->query($sql_fifo);
$fifo_kodu = '';
$hammadde_turu = '';

if ($res_fifo && $row = $res_fifo->fetch_assoc()) {
    $fifo_kodu = $row['parti_kodu'];
    $hammadde_turu = $row['hammadde_turu'];
}

echo json_encode([
    'success' => true,
    'toplam_kalan_kg' => $toplam_kalan,
    'rezerve_kg' => $rezerve_kg,
    'serbest_kg' => $serbest_kg,
    'fifo_kodu' => $fifo_kodu,
    'hammadde_turu' => $hammadde_turu
]);
?>
