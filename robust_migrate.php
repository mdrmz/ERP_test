<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "yonetim_paneli";

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Ensure Table exists
$conn->query("CREATE TABLE IF NOT EXISTS musteriler (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2. Add columns one by one
$columns = [
    "cari_kod VARCHAR(50) NOT NULL UNIQUE AFTER id",
    "cari_tip ENUM('Müşteri', 'Tedarikçi') DEFAULT 'Müşteri' AFTER cari_kod",
    "firma_adi VARCHAR(255) NOT NULL AFTER cari_tip",
    "yetkili_kisi VARCHAR(100) AFTER firma_adi",
    "telefon VARCHAR(50) AFTER yetkili_kisi",
    "eposta VARCHAR(100) AFTER telefon",
    "vergi_dairesi VARCHAR(100) AFTER eposta",
    "vergi_no VARCHAR(50) AFTER vergi_dairesi",
    "il VARCHAR(50) AFTER vergi_no",
    "ilce VARCHAR(50) AFTER il",
    "adres TEXT AFTER ilce",
    "ozel_notlar TEXT AFTER adres",
    "bakiye DECIMAL(18,2) DEFAULT 0.00 AFTER ozel_notlar",
    "para_birimi VARCHAR(10) DEFAULT 'TL' AFTER bakiye"
];

foreach ($columns as $col) {
    $parts = explode(' ', trim($col));
    $col_name = $parts[0];
    $check = $conn->query("SHOW COLUMNS FROM musteriler LIKE '$col_name'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE musteriler ADD $col");
        echo "Added $col_name\n";
    } else {
        echo "$col_name exists.\n";
    }
}

// 3. Mock Data
$mock_data = [
    ['120.01.001', 'Müşteri', 'Kardeşler Ekmek Fırını', 'Ahmet Demir', '0532 111 22 33', 'kardesler@mail.com', 'Marmara', '1234567890', 'İstanbul', 'Esenyurt', 'Fatih Mah. 12. Sokak No:5', 'Düzenli un alımı yapar, ödemeler haftalık.'],
    ['320.01.005', 'Tedarikçi', 'Anadolu Tarım Ürünleri', 'Mehmet Yılmaz', '0544 333 44 55', 'anadolu@tarim.com', 'Konya V.D.', '9876543210', 'Konya', 'Meram', 'Sanayi Sitesi B Blok No:12', 'Buğday tedarikçimiz, protein oranı yüksek ürün verir.'],
    ['120.02.045', 'Müşteri', 'Has Unlu Mamülleri', 'Ayşe Kaya', '0555 666 77 88', 'hasunlu@mail.com', 'Bornova', '5554443332', 'İzmir', 'Bornova', 'Merkez Cad. No:45', 'Özel Tip-1 un talep ediyor.'],
    ['320.02.010', 'Tedarikçi', 'Global Lojistik Hizmetleri', 'Serkan Ak', '0505 999 00 11', 'serkan@global.com', 'Pendik', '1112223334', 'İstanbul', 'Pendik', 'Liman Yolu Cad. No:1', 'Sevkıyat ve nakliye partnerimiz.']
];

foreach ($mock_data as $row) {
    $c_kod = $row[0];
    $check_dup = $conn->query("SELECT id FROM musteriler WHERE cari_kod = '$c_kod'");
    if ($check_dup->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres, ozel_notlar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssss", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11]);
        $stmt->execute();
        echo "Inserted: {$row[2]}\n";
    } else {
        echo "Skip (exists): {$row[2]}\n";
    }
}

echo "Success.\n";
?>
