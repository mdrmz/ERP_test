<?php
// mysqli exception reporting açık (try-catch için gerekli)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$kullanici = "root";
$sifre = "";
$veritabani = "yonetim_paneli";

$baglanti = new mysqli($host, $kullanici, $sifre, $veritabani);
$baglanti->set_charset("utf8mb4");

if ($baglanti->connect_error) {
    die("Bağlantı hatası: " . $baglanti->connect_error);
}
?>
