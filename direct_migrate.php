<?php
// Direct connection attempt for CLI
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "yonetim_paneli";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$columns = [
    "cari_kod VARCHAR(50) NOT NULL UNIQUE AFTER id",
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

foreach ($columns as $col) {
    // Check if column exists first to avoid error
    $col_name = explode(' ', $col)[0];
    $check = $conn->query("SHOW COLUMNS FROM musteriler LIKE '$col_name'");
    if ($check->num_rows == 0) {
        if ($conn->query("ALTER TABLE musteriler ADD $col")) {
            echo "Added $col_name\n";
        } else {
            echo "Error adding $col_name: " . $conn->error . "\n";
        }
    } else {
        echo "$col_name already exists.\n";
    }
}

echo "Migration complete.\n";
?>
