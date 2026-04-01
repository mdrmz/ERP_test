<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "yonetim_paneli";

$conn = mysqli_connect($host, $user, $pass, $db);

$mock_data = [
    ['120.01.001', 'Müşteri', 'Kardeşler Ekmek Fırını', 'Ahmet Demir', '0532 111 22 33', 'kardesler@mail.com', 'Marmara', '1234567890', 'İstanbul', 'Esenyurt', 'Fatih Mah. 12. Sokak No:5', 'Düzenli un alımı yapar, ödemeler haftalık.'],
    ['320.01.005', 'Tedarikçi', 'Anadolu Tarım Ürünleri', 'Mehmet Yılmaz', '0544 333 44 55', 'anadolu@tarim.com', 'Konya V.D.', '9876543210', 'Konya', 'Meram', 'Sanayi Sitesi B Blok No:12', 'Buğday tedarikçimiz, protein oranı yüksek ürün verir.'],
    ['120.02.045', 'Müşteri', 'Has Unlu Mamülleri', 'Ayşe Kaya', '0555 666 77 88', 'hasunlu@mail.com', 'Bornova', '5554443332', 'İzmir', 'Bornova', 'Merkez Cad. No:45', 'Özel Tip-1 un talep ediyor.'],
    ['320.02.010', 'Tedarikçi', 'Global Lojistik Hizmetleri', 'Serkan Ak', '0505 999 00 11', 'serkan@global.com', 'Pendik', '1112223334', 'İstanbul', 'Pendik', 'Liman Yolu Cad. No:1', 'Sevkıyat ve nakliye partnerimiz.']
];

foreach ($mock_data as $row) {
    $c_kod = $row[0];
    $check = mysqli_query($conn, "SELECT id FROM musteriler WHERE cari_kod = '$c_kod'");
    if (mysqli_num_rows($check) == 0) {
        $sql = "INSERT INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres, ozel_notlar) 
                VALUES ('{$row[0]}', '{$row[1]}', '{$row[2]}', '{$row[3]}', '{$row[4]}', '{$row[5]}', '{$row[6]}', '{$row[7]}', '{$row[8]}', '{$row[9]}', '{$row[10]}', '{$row[11]}')";
        mysqli_query($conn, $sql);
        echo "Inserted: {$row[2]}\n";
    } else {
        echo "Exists: {$row[2]}\n";
    }
}
?>
