<?php
include("../baglan.php");
header('Content-Type: application/json');

$b1_id = isset($_GET['b1_id']) ? (int) $_GET['b1_id'] : 0;

if ($b1_id <= 0) {
    echo json_encode(["success" => false, "message" => "Geçersiz B1 ID"]);
    exit;
}

// B1 detaylarını çek
$sql = "SELECT * FROM uretim_b1_detay WHERE b1_id = $b1_id ORDER BY id ASC";
$result = $baglanti->query($sql);

$detaylar = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $detaylar[] = [
            "yas_ambar_no" => $row["yas_ambar_no"],
            "nem" => $row["nem"],
            "gluten" => $row["gluten"],
            "g_index" => $row["g_index"],
            "n_sedim" => $row["n_sedim"],
            "g_sedim" => $row["g_sedim"],
            "hektolitre" => $row["hektolitre"],
            "alveo_p" => $row["alveo_p"],
            "alveo_g" => $row["alveo_g"],
            "alveo_pl" => $row["alveo_pl"],
            "alveo_w" => $row["alveo_w"],
            "alveo_ie" => $row["alveo_ie"],
            "fn" => $row["fn"],
            "perten_protein" => $row["perten_protein"],
            "perten_sertlik" => $row["perten_sertlik"],
            "perten_nisasta" => $row["perten_nisasta"]
        ];
    }
    echo json_encode(["success" => true, "detaylar" => $detaylar]);
} else {
    echo json_encode(["success" => false, "message" => "Detay bulunamadı"]);
}
?>