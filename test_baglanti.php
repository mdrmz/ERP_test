<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Çalışıyor!</h1>";

// Veritabanı testi
$host = "localhost";
$kullanici = "erp";
$sifre = "1234";
$veritabani = "yonetim_paneli";

$baglanti = new mysqli($host, $kullanici, $sifre);

if ($baglanti->connect_error) {
    die("<p style='color:red'>MySQL BAĞLANTI HATASI: " . $baglanti->connect_error . "</p>");
}

echo "<p style='color:green'>✓ MySQL bağlantısı başarılı!</p>";

// Veritabanı var mı kontrol et
$result = $baglanti->query("SHOW DATABASES LIKE '$veritabani'");
if ($result->num_rows == 0) {
    echo "<p style='color:orange'>⚠ '$veritabani' veritabanı YOK! Oluşturuluyor...</p>";
    $baglanti->query("CREATE DATABASE $veritabani");
    echo "<p style='color:green'>✓ Veritabanı oluşturuldu!</p>";
} else {
    echo "<p style='color:green'>✓ '$veritabani' veritabanı mevcut!</p>";
}

// Veritabanını seç
$baglanti->select_db($veritabani);

// Tabloları kontrol et
$tablolar = ['silolar', 'is_emirleri', 'sevkiyat_randevulari', 'uretim_hareketleri', 'users'];
echo "<h3>Tablo Durumları:</h3><ul>";
foreach ($tablolar as $tablo) {
    $check = $baglanti->query("SHOW TABLES LIKE '$tablo'");
    if ($check->num_rows > 0) {
        echo "<li style='color:green'>✓ $tablo</li>";
    } else {
        echo "<li style='color:red'>✗ $tablo (YOK)</li>";
    }
}
echo "</ul>";

echo "<p><b>Sonuç:</b> Eksik tablolar varsa kurulum_komple.sql dosyasını çalıştırmalısın.</p>";
?>
