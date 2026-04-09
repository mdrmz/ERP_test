<?php
include("baglan.php");

echo "<h3>Cari Kod Temizlik ve Index Uygulama</h3>";

// 1. Boşlukları temizle (isteğe bağlı ama önerilir)
echo "Boşluklar temizleniyor...<br>";
$baglanti->query("UPDATE musteriler SET cari_kod = TRIM(cari_kod)");

// 2. Mükerrerleri bul ve temizle (en yüksek ID'yi tut, diğerlerini sil)
echo "Mükerrer veriler temizleniyor...<br>";
$dup_sql = "DELETE t1 FROM musteriler t1
            INNER JOIN musteriler t2 
            WHERE t1.id < t2.id AND t1.cari_kod = t2.cari_kod";

if ($baglanti->query($dup_sql)) {
    echo "Temizlik başarılı (" . $baglanti->affected_rows . " satır silindi).<br>";
} else {
    echo "Hata (Temizlik): " . $baglanti->error . "<br>";
}

// 3. Unique Index Uygula
echo "Unique Index uygulanıyor...<br>";
try {
    // Önce varsa eski indexi sil (Hata verirse sorun değil)
    $baglanti->query("ALTER TABLE musteriler DROP INDEX IF EXISTS cari_kod");
    $baglanti->query("ALTER TABLE musteriler ADD UNIQUE INDEX ui_cari_kod (cari_kod)");
    echo "<span style='color:green'>Success: Unique Index (ui_cari_kod) başarıyla uygulandı!</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>Hata (Index): " . $e->getMessage() . "</span><br>";
}

echo "<br><a href='musteriler.php'>Müşteri Listesine Git</a>";
?>
