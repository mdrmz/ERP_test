<?php
mysqli_report(MYSQLI_REPORT_OFF); // Disable exceptions
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "yonetim_paneli";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connect Error: " . mysqli_connect_error());
}

// Ensure table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS musteriler (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");

$cols = [
    "cari_kod VARCHAR(50) NOT NULL",
    "cari_tip ENUM('Müşteri', 'Tedarikçi') DEFAULT 'Müşteri'",
    "firma_adi VARCHAR(255) NOT NULL",
    "yetkili_kisi VARCHAR(100)",
    "telefon VARCHAR(50)",
    "eposta VARCHAR(100)",
    "vergi_dairesi VARCHAR(100)",
    "vergi_no VARCHAR(50)",
    "il VARCHAR(50)",
    "ilce VARCHAR(50)",
    "adres TEXT",
    "ozel_notlar TEXT",
    "bakiye DECIMAL(18,2) DEFAULT 0.00",
    "para_birimi VARCHAR(10) DEFAULT 'TL'"
];

foreach ($cols as $c) {
    $name = explode(' ', $c)[0];
    $res = mysqli_query($conn, "SHOW COLUMNS FROM musteriler LIKE '$name'");
    if (mysqli_num_rows($res) == 0) {
        if (mysqli_query($conn, "ALTER TABLE musteriler ADD $c")) {
            echo "Added $name\n";
        } else {
            echo "Error adding $name: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "$name exists.\n";
    }
}

// Add Unique index to cari_kod if not exists
mysqli_query($conn, "ALTER TABLE musteriler ADD UNIQUE (cari_kod)");

echo "Migration done.\n";
?>
