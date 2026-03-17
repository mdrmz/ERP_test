<?php
include("baglan.php");
$sql = "ALTER TABLE sevkiyatlar ADD COLUMN siparis_id INT(11) AFTER id";
if($baglanti->query($sql)) {
    echo "Successfully added siparis_id to sevkiyatlar table.\n";
} else {
    echo "Error or column already exists: " . $baglanti->error . "\n";
}

// Also check sevkiyat_detaylari just in case
$sql2 = "ALTER TABLE sevkiyat_detaylari ADD COLUMN parti_no VARCHAR(50) AFTER miktar";
$baglanti->query($sql2); // This might fail if table doesn't exist, that's fine for now.

echo "Done.";
?>
