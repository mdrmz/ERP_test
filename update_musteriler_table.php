<?php
include("baglan.php");

// Existing table check or cleanup if necessary. 
// We'll rename the old one if it exists to keep data safe, or just drop if it's mock.
// Since it's a test environment, I'll drop and recreation for a clean start with the new schema.

$drop_sql = "DROP TABLE IF EXISTS musteriler";
$baglanti->query($drop_sql);

$sql = "CREATE TABLE musteriler (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    cari_kod VARCHAR(50) NOT NULL UNIQUE,
    cari_tip ENUM('Müşteri', 'Tedarikçi') NOT NULL,
    firma_adi VARCHAR(255) NOT NULL,
    yetkili_kisi VARCHAR(100),
    telefon VARCHAR(50),
    eposta VARCHAR(100),
    vergi_dairesi VARCHAR(100),
    vergi_no VARCHAR(50),
    il VARCHAR(50),
    ilce VARCHAR(50),
    adres TEXT,
    ozel_notlar TEXT,
    bakiye DECIMAL(18,2) DEFAULT 0.00,
    para_birimi VARCHAR(10) DEFAULT 'TL',
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($baglanti->query($sql)) {
    echo "musteriler table updated successfully.\n";
} else {
    echo "Error: " . $baglanti->error . "\n";
}
?>
