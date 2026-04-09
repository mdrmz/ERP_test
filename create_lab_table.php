<?php
include("baglan.php");

$sql = "CREATE TABLE IF NOT EXISTS bakim_lab_malzemeler (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    malzeme_adi VARCHAR(255) NOT NULL,
    miktar DECIMAL(10,2) DEFAULT 0,
    birim VARCHAR(50),
    kullanim_alani VARCHAR(255),
    kritik_seviye DECIMAL(10,2) DEFAULT 0,
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($baglanti->query($sql)) {
    echo "bakim_lab_malzemeler table created successfully.\n";
} else {
    echo "Error creating table: " . $baglanti->error . "\n";
}

// Ensure 'Laboratory' is a valid unit in makineler (though it's a varchar, we just need to be consistent)
echo "Done.";
?>
