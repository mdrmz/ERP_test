<?php
include("baglan.php");

$sql = "CREATE TABLE IF NOT EXISTS bakim_dokumanlari (
    id INT(11) NOT NULL AUTO_INCREMENT,
    orijinal_ad VARCHAR(255) NOT NULL,
    saklanan_ad VARCHAR(255) NOT NULL,
    dosya_yolu VARCHAR(500) NOT NULL,
    dosya_boyut INT(11) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    yukleyen_user_id INT(11) DEFAULT NULL,
    yukleme_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bakim_dokumanlari_tarih (yukleme_tarihi),
    KEY idx_bakim_dokumanlari_user (yukleyen_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($baglanti->query($sql)) {
    echo "bakim_dokumanlari table is ready.\n";
} else {
    echo "Error creating table: " . $baglanti->error . "\n";
    exit;
}

$fk_sql = "ALTER TABLE bakim_dokumanlari
           ADD CONSTRAINT fk_bakim_dokumanlari_user
           FOREIGN KEY (yukleyen_user_id) REFERENCES users(id)
           ON DELETE SET NULL ON UPDATE CASCADE";

if (!$baglanti->query($fk_sql)) {
    // FK mevcutsa veya eklenemiyorsa script tamamen fail olmasın.
    echo "Foreign key note: " . $baglanti->error . "\n";
}

echo "Done.";

