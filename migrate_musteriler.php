<?php
include("baglan.php");

$columns_to_add = [
    "cari_kod VARCHAR(50) AFTER id",
    "cari_tip ENUM('Müşteri', 'Tedarikçi') DEFAULT 'Müşteri' AFTER cari_kod",
    "eposta VARCHAR(100) AFTER telefon",
    "vergi_dairesi VARCHAR(100) AFTER eposta",
    "vergi_no VARCHAR(50) AFTER vergi_dairesi",
    "il VARCHAR(50) AFTER vergi_no",
    "ilce VARCHAR(50) AFTER il",
    "ozel_notlar TEXT AFTER adres",
    "bakiye DECIMAL(18,2) DEFAULT 0.00 AFTER ozel_notlar",
    "para_birimi VARCHAR(10) DEFAULT 'TL' AFTER bakiye"
];

foreach ($columns_to_add as $col) {
    try {
        $baglanti->query("ALTER TABLE musteriler ADD $col");
        echo "Added column: $col\n";
    } catch (Exception $e) {
        echo "Column might already exist or error: " . $e->getMessage() . "\n";
    }
}

// Ensure unique constraint on cari_kod
try {
    $baglanti->query("ALTER TABLE musteriler ADD UNIQUE (cari_kod)");
} catch (Exception $e) {
    echo "Unique index on cari_kod check: " . $e->getMessage() . "\n";
}

echo "Database migration finished.\n";
?>
