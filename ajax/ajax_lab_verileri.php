<?php
/**
 * AJAX Lab Verileri Endpointi
 * Paçal sayfasında buğday cinsi seçildiğinde lab analiz verilerini döner.
 * 
 * Parametreler:
 *   parti_no  → Hammadde parti numarası (hammadde_girisleri.parti_no)
 *   hammadde_id → Hammadde ID (en son analizini getirir)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["oturum"])) {
    http_response_code(401);
    echo json_encode(["error" => "Oturum gerekli"]);
    exit;
}

include("../baglan.php");

$parti_no = isset($_GET["parti_no"]) ? mysqli_real_escape_string($baglanti, trim($_GET["parti_no"])) : "";
$hammadde_id = isset($_GET["hammadde_id"]) ? (int) $_GET["hammadde_id"] : 0;

$result = null;

// Parti numarasıyla arama (öncelikli)
if (!empty($parti_no)) {
    $sql = "SELECT la.gluten, la.index_degeri as g_index, la.sedimantasyon as n_sedim, 
                   la.gecikmeli_sedimantasyon as g_sedim, la.hektolitre, la.nem, la.fn,
                   la.protein as perten_protein, la.sertlik as perten_sertlik, la.nisasta as perten_nisasta,
                   hg.parti_no, h.ad as hammadde_adi, h.hammadde_kodu
            FROM lab_analizleri la
            LEFT JOIN hammadde_girisleri hg ON la.parti_no = hg.parti_no
            LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
            LEFT JOIN hammadde_kabul_akisi hka ON hka.hammadde_giris_id = hg.id
            WHERE la.parti_no = '$parti_no' AND hka.asama = 'tamamlandi'
            ORDER BY la.tarih DESC
            LIMIT 1";
    $result = $baglanti->query($sql);
}

// Hammadde ID ile arama (fallback — son analiz)
if ((!$result || $result->num_rows == 0) && $hammadde_id > 0) {
    $sql = "SELECT la.gluten, la.index_degeri as g_index, la.sedimantasyon as n_sedim, 
                   la.gecikmeli_sedimantasyon as g_sedim, la.hektolitre, la.nem, la.fn,
                   la.protein as perten_protein, la.sertlik as perten_sertlik, la.nisasta as perten_nisasta,
                   hg.parti_no, h.ad as hammadde_adi, h.hammadde_kodu
            FROM lab_analizleri la
            LEFT JOIN hammadde_girisleri hg ON la.parti_no = hg.parti_no
            LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
            LEFT JOIN hammadde_kabul_akisi hka ON hka.hammadde_giris_id = hg.id
            WHERE hg.hammadde_id = $hammadde_id AND hka.asama = 'tamamlandi'
            ORDER BY la.tarih DESC
            LIMIT 1";
    $result = $baglanti->query($sql);
}

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Null'ları temizle, numeric yapıyı koru
    foreach ($row as $key => $val) {
        if ($val === null) {
            $row[$key] = "";
        }
    }

    echo json_encode(["success" => true, "data" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "Bu parti için lab analizi bulunamadı"]);
}

$baglanti->close();
?>