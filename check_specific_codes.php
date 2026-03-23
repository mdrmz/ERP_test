<?php
include("baglan.php");
$res = $baglanti->query("SELECT cari_kod FROM musteriler WHERE cari_kod LIKE '120.01.107%'");
while($row = $res->fetch_assoc()) {
    echo $row['cari_kod'] . "\n";
}
?>
