<?php
include("baglan.php");

$q1 = $baglanti->query("ALTER TABLE siparisler ADD COLUMN alici_adi VARCHAR(255) NULL AFTER musteri_id");
if($q1) echo "Added alici_adi\n";
else echo "Error adding alici_adi: " . $baglanti->error . "\n";

$q2 = $baglanti->query("ALTER TABLE siparisler ADD COLUMN odeme_tarihi DATE NULL AFTER teslim_tarihi");
if($q2) echo "Added odeme_tarihi\n";
else echo "Error adding odeme_tarihi: " . $baglanti->error . "\n";
?>
