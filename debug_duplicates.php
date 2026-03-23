<?php
include("baglan.php");

echo "<h3>Cari Kod Tekrar Kontrolü</h3>";

// 1. Index Kontrolü
$res = $baglanti->query("SHOW INDEX FROM musteriler WHERE Column_name = 'cari_kod'");
echo "<h4>Index Durumu:</h4>";
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "Index Name: " . $row['Key_name'] . " | Non_unique: " . $row['Non_unique'] . " (0 ise unique demektir)<br>";
    }
} else {
    echo "<span style='color:red'>HATA: cari_kod kolonunda her hangi bir index bulunamadı!</span><br>";
}

// 2. Mükerrer Kayıt Kontrolü (Boşlukları da kontrol ederek)
$res2 = $baglanti->query("SELECT cari_kod, COUNT(*) as adet FROM musteriler GROUP BY cari_kod HAVING adet > 1");
echo "<h4>Mükerrer Kayıtlar:</h4>";
if ($res2 && $res2->num_rows > 0) {
    while($row = $res2->fetch_assoc()) {
        $kod = $row['cari_kod'];
        echo "Kod: <strong style='background:#eee'>[$kod]</strong> | Adet: " . $row['adet'] . " | Uzunluk: " . strlen($kod) . "<br>";
        
        // Bu kodun tüm kayıtlarını detaylı dök
        $kod_esc = $baglanti->real_escape_string($kod);
        $detay = $baglanti->query("SELECT id, firma_adi, LENGTH(cari_kod) as len FROM musteriler WHERE cari_kod = '$kod_esc'");
        while($d = $detay->fetch_assoc()) {
            echo "--- ID: {$d['id']} | Ad: {$d['firma_adi']} | Gerçek Uzunluk: {$d['len']}<br>";
        }
    }
} else {
    echo "<span style='color:green'>Mükerrer (tekrar eden) kayıt bulunamadı.</span><br>";
}

// 3. Örnek Veri Gösterimi (İlk 5)
$res3 = $baglanti->query("SELECT id, cari_kod, firma_adi FROM musteriler ORDER BY id DESC LIMIT 5");
echo "<h4>Son Eklenen 5 Kayıt:</h4>";
while($row = $res3->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Kod: " . $row['cari_kod'] . " | Ad: " . $row['firma_adi'] . "<br>";
}
?>
