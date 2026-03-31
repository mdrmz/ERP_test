<?php
include("baglan.php");

$sql = "ALTER TABLE uretim_pacal_detay ADD COLUMN silo_id INT NULL AFTER pacal_id";

if ($baglanti->query($sql)) {
    echo "Başarıyla eklendi.\n";
} else {
    echo "Hata: " . $baglanti->error . "\n";
}
