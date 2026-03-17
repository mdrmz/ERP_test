<?php
include("baglan.php");
$res = $baglanti->query("SELECT id, firma_adi FROM musteriler");
echo "Musteriler:\n";
while($row = $res->fetch_assoc()) {
    echo $row['id'] . " - " . $row['firma_adi'] . "\n";
}
?>
