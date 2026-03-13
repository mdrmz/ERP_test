<?php
include("baglan.php");

echo "<h2>is_emirleri Tablosuna baslangic_tarihi Ekle</h2>";

$r = $baglanti->query("SHOW COLUMNS FROM is_emirleri LIKE 'baslangic_tarihi'");
if ($r->num_rows == 0) {
    if ($baglanti->query("ALTER TABLE is_emirleri ADD COLUMN baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP")) {
        echo "<p style='color:green'>✅ baslangic_tarihi eklendi!</p>";
    } else {
        echo "<p style='color:red'>❌ Hata: " . $baglanti->error . "</p>";
    }
} else {
    echo "<p>baslangic_tarihi zaten var</p>";
}

echo "<h3>Güncel is_emirleri Tablosu:</h3>";
$r = $baglanti->query("DESCRIBE is_emirleri");
echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
while ($row = $r->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

echo "<p><a href='planlama.php'>Planlama sayfasına git →</a></p>";
?>
