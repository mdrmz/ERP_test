<?php
session_start();
include("../baglan.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["oturum"])) {
    echo json_encode(['success' => false, 'message' => 'Oturum kapalı.']);
    exit;
}

if (!isset($_GET['pacal_id'])) {
    echo json_encode(['success' => false, 'message' => 'Paçal ID gerekli.']);
    exit;
}

$pacal_id = (int) $_GET['pacal_id'];

// Ana paçal kaydı
$pacal_query = $baglanti->query("SELECT toplam_miktar_kg FROM uretim_pacal WHERE id = $pacal_id");
if (!$pacal_query || $pacal_query->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Paçal bulunamadı.']);
    exit;
}
$pacal = $pacal_query->fetch_assoc();

// Detay satırları (oran > 0 olanlar)
$detay_query = $baglanti->query("
    SELECT * FROM uretim_pacal_detay 
    WHERE pacal_id = $pacal_id AND oran > 0
    ORDER BY sira_no ASC
");

$cols = ['gluten', 'g_index', 'n_sedim', 'g_sedim', 'hektolitre', 'nem', 'alveo_p', 'alveo_g', 'alveo_pl', 'alveo_w', 'alveo_ie', 'fn', 'perten_protein', 'perten_sertlik', 'perten_nisasta'];

$toplamlar = array_fill_keys($cols, 0);
$oran_toplamlari = array_fill_keys($cols, 0);

// Yaş ambar listesini topla
$yas_ambarlar = [];

if ($detay_query && $detay_query->num_rows > 0) {
    while ($row = $detay_query->fetch_assoc()) {
        $oran = (float) ($row['oran'] ?? 0);

        // Yaş ambar no'ları topla (boş olmayanları)
        $yam = trim($row['yas_ambar_no'] ?? '');
        if ($yam !== '' && !in_array($yam, $yas_ambarlar)) {
            $yas_ambarlar[] = $yam;
        }

        foreach ($cols as $col) {
            if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                $val = (float) $row[$col];
                $toplamlar[$col] += ($val * $oran);
                $oran_toplamlari[$col] += $oran;
            }
        }
    }
}

$averages = [];
foreach ($cols as $col) {
    if ($oran_toplamlari[$col] > 0) {
        $averages[$col] = round($toplamlar[$col] / $oran_toplamlari[$col], 2);
    } else {
        $averages[$col] = '';
    }
}

// Integer sütunları yuvarla
$int_cols = ['g_index', 'n_sedim', 'g_sedim', 'alveo_w', 'fn'];
foreach ($int_cols as $ic) {
    if ($averages[$ic] !== '') {
        $averages[$ic] = round($averages[$ic]);
    }
}

echo json_encode([
    'success' => true,
    'toplam_miktar_kg' => (float) $pacal['toplam_miktar_kg'],
    'data' => $averages,
    'yas_ambarlar' => $yas_ambarlar
]);
?>