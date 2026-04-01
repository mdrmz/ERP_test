<?php
/**
 * FCFDFD Tablosundan Musteriler Tablosuna Manuel Veri Gocerici
 * 
 * Bu script, SQL dosyasından içeri aktarılan FCFDFD tablosundaki verileri
 * yonetim_paneli.musteriler tablosuna uygun formatta kopyalar/günceller.
 */

include("baglan.php");

// 1. Tablo Var mı Kontrol Et
$check_table = $baglanti->query("SHOW TABLES LIKE 'FCFDFD'");
if ($check_table->num_rows == 0) {
    die("Hata: FCFDFD tablosu bulunamadı. Lütfen önce .sql dosyasını mysql'e aktarın.");
}

echo "Göç işlemi başlıyor...<br>";

// 2. Verileri Çek
$veriler = $baglanti->query("SELECT * FROM FCFDFD");
$toplam = $veriler->num_rows;
$eklenen = 0;
$guncellenen = 0;

if ($toplam > 0) {
    while ($row = $veriler->fetch_assoc()) {
        $kod = $baglanti->real_escape_string($row['KODU']);
        
        // Cari Tip Belirle (120 Müşteri, 320 Tedarikçi)
        $tip = 'Müşteri';
        if (substr($kod, 0, 3) === '120') {
            $tip = 'Müşteri';
        } elseif (substr($kod, 0, 3) === '320') {
            $tip = 'Tedarikçi';
        }

        // Ünvanları birleştir
        $unvan = trim($row['ÜNVANI_1'] . ' ' . $row['ÜNVANI_2']);
        $unvan = $baglanti->real_escape_string($unvan);

        // Adres birleştir
        $adres = trim($row['CADDE'] . ' ' . $row['SOKAK']);
        $adres = $baglanti->real_escape_string($adres);

        $vd = $baglanti->real_escape_string($row['VD_ADI']);
        $vn = $baglanti->real_escape_string($row['VD']);
        $mail = $baglanti->real_escape_string($row['MAIL']);
        $tel = $baglanti->real_escape_string($row['TEL_NO']);
        $il = $baglanti->real_escape_string($row['Column_9']);
        $ilce = $baglanti->real_escape_string($row['Column_10']);

        // Mükerrer Kontrolü ve Insert/Update
        $sql = "INSERT INTO musteriler 
                (cari_kod, cari_tip, firma_adi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres) 
                VALUES 
                ('$kod', '$tip', '$unvan', '$tel', '$mail', '$vd', '$vn', '$il', '$ilce', '$adres')
                ON DUPLICATE KEY UPDATE 
                firma_adi = VALUES(firma_adi),
                telefon = VALUES(telefon),
                eposta = VALUES(eposta),
                vergi_dairesi = VALUES(vergi_dairesi),
                vergi_no = VALUES(vergi_no),
                il = VALUES(il),
                ilce = VALUES(ilce),
                adres = VALUES(adres)";
        
        if ($baglanti->query($sql)) {
            if ($baglanti->affected_rows == 1) {
                $eklenen++;
            } elseif ($baglanti->affected_rows == 2) {
                $guncellenen++;
            }
        } else {
            echo "Kayıt hatası (Kod: $kod): " . $baglanti->error . "<br>";
        }
    }
}

echo "<br>--- İŞLEM TAMAMLANDI ---<br>";
echo "Okunan Satır: $toplam<br>";
echo "Yeni Eklenen: $eklenen<br>";
echo "Güncellenen: $guncellenen<br>";
echo "<br><a href='musteriler.php'>Müşteri Listesine Git</a>";
?>
