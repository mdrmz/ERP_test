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

// Create table if not exists with the new schema
$sql = "CREATE TABLE IF NOT EXISTS musteriler (
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

if ($conn->query($sql)) {
    echo "Table created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Mock Data
$mock_data = [
    ['120.01.001', 'Müşteri', 'Kardeşler Ekmek Fırını', 'Ahmet Demir', '0532 111 22 33', 'kardesler@mail.com', 'Marmara', '1234567890', 'İstanbul', 'Esenyurt', 'Fatih Mah. 12. Sokak No:5', 'Düzenli un alımı yapar, ödemeler haftalık.'],
    ['320.01.005', 'Tedarikçi', 'Anadolu Tarım Ürünleri', 'Mehmet Yılmaz', '0544 333 44 55', 'anadolu@tarim.com', 'Konya V.D.', '9876543210', 'Konya', 'Meram', 'Sanayi Sitesi B Blok No:12', 'Buğday tedarikçimiz, protein oranı yüksek ürün verir.'],
    ['120.02.045', 'Müşteri', 'Has Unlu Mamülleri', 'Ayşe Kaya', '0555 666 77 88', 'hasunlu@mail.com', 'Bornova', '5554443332', 'İzmir', 'Bornova', 'Merkez Cad. No:45', 'Özel Tip-1 un talep ediyor.'],
    ['320.02.010', 'Tedarikçi', 'Global Lojistik Hizmetleri', 'Serkan Ak', '0505 999 00 11', 'serkan@global.com', 'Pendik', '1112223334', 'İstanbul', 'Pendik', 'Liman Yolu Cad. No:1', 'Sevkıyat ve nakliye partnerimiz.']
];

foreach ($mock_data as $row) {
    $stmt = $conn->prepare("INSERT IGNORE INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres, ozel_notlar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11]);
    if ($stmt->execute()) {
        echo "Inserted/Matched: {$row[2]}\n";
    } else {
        echo "Error inserting {$row[2]}: " . $stmt->error . "\n";
    }
}

echo "Finalizing migration...\n";
?>
