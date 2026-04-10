<?php
/**
 * PLC Entegrasyonu - Veritabanı Migrasyon Scripti
 * 3 tablo oluşturur: plc_cihazlari, plc_etiketleri, plc_okumalari
 * Tek seferlik çalıştırılır.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("baglan.php");

$sqls = [];

// 1. PLC Cihazları
$sqls[] = "CREATE TABLE IF NOT EXISTS `plc_cihazlari` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cihaz_adi` VARCHAR(100) NOT NULL,
  `cihaz_tipi` ENUM('akar_kantar','randiman','oto_tav','flow') NOT NULL,
  `ip_adresi` VARCHAR(15) NOT NULL,
  `port` INT DEFAULT 502,
  `unit_id` INT DEFAULT 1,
  `aktif` TINYINT DEFAULT 1,
  `son_baglanti` DATETIME NULL,
  `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ip` (`ip_adresi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// 2. PLC Etiketleri (Modbus Register Haritası)
$sqls[] = "CREATE TABLE IF NOT EXISTS `plc_etiketleri` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cihaz_id` INT NOT NULL,
  `etiket_adi` VARCHAR(100) NOT NULL,
  `modbus_adres` INT NOT NULL COMMENT 'Register adresi (MW numarası)',
  `veri_tipi` ENUM('INT','FLOAT','DOUBLE','REAL','BIT','BOOL') NOT NULL DEFAULT 'INT',
  `register_sayisi` INT DEFAULT 1 COMMENT 'Kaç register okunacak (Double=2, Float=2)',
  `carpan` DECIMAL(10,4) DEFAULT 1.0000,
  `birim` VARCHAR(20) DEFAULT '',
  `aciklama` VARCHAR(200) DEFAULT '',
  FOREIGN KEY (`cihaz_id`) REFERENCES `plc_cihazlari`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// 3. PLC Okumaları (Zaman Serisi Verileri)
$sqls[] = "CREATE TABLE IF NOT EXISTS `plc_okumalari` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `cihaz_id` INT NOT NULL,
  `okuma_zamani` DATETIME NOT NULL,
  `veriler` JSON NOT NULL COMMENT 'Tüm etiket değerleri JSON olarak',
  INDEX `idx_cihaz_zaman` (`cihaz_id`, `okuma_zamani`),
  FOREIGN KEY (`cihaz_id`) REFERENCES `plc_cihazlari`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// ===== SEED DATA: Cihazlar =====
$sqls[] = "INSERT IGNORE INTO `plc_cihazlari` (`cihaz_adi`, `cihaz_tipi`, `ip_adresi`, `port`) VALUES
  ('AKAR KANTAR 1', 'akar_kantar', '192.168.20.51', 502),
  ('AKAR KANTAR 2', 'akar_kantar', '192.168.20.52', 502),
  ('AKAR KANTAR 3', 'akar_kantar', '192.168.20.53', 502),
  ('AKAR KANTAR 4', 'akar_kantar', '192.168.20.54', 502),
  ('AKAR KANTAR 5', 'akar_kantar', '192.168.20.55', 502),
  ('AKAR KANTAR 6', 'akar_kantar', '192.168.20.56', 502),
  ('AKAR KANTAR 7', 'akar_kantar', '192.168.20.57', 502),
  ('AKAR KANTAR 8', 'akar_kantar', '192.168.20.58', 502),
  ('AKAR KANTAR 9', 'akar_kantar', '192.168.20.59', 502),
  ('AKAR KANTAR 10', 'akar_kantar', '192.168.20.60', 502),
  ('AKAR KANTAR 11', 'akar_kantar', '192.168.20.61', 502),
  ('AKAR KANTAR 12', 'akar_kantar', '192.168.20.62', 502),
  ('AKAR KANTAR 13', 'akar_kantar', '192.168.20.63', 502),
  ('AKAR KANTAR 14', 'akar_kantar', '192.168.20.64', 502),
  ('AKAR KANTAR 15', 'akar_kantar', '192.168.20.65', 502),
  ('AKAR KANTAR 16', 'akar_kantar', '192.168.20.66', 502),
  ('AKAR KANTAR 17', 'akar_kantar', '192.168.20.67', 502),
  ('AKAR KANTAR 18', 'akar_kantar', '192.168.20.68', 502),
  ('AKAR KANTAR 19', 'akar_kantar', '192.168.20.69', 502),
  ('AKAR KANTAR 20', 'akar_kantar', '192.168.20.70', 502),
  ('OTOMATİK TAV', 'oto_tav', '192.168.20.71', 502),
  ('TEMİZLEME RANDIMAN', 'randiman', '192.168.20.101', 502),
  ('B1 RANDIMAN', 'randiman', '192.168.20.102', 502),
  ('UN1 RANDIMAN', 'randiman', '192.168.20.103', 502),
  ('UN2 RANDIMAN', 'randiman', '192.168.20.104', 502),
  ('KEPEK RANDIMAN', 'randiman', '192.168.20.105', 502),
  ('TEMİZLEME 2 RANDIMAN', 'randiman', '192.168.20.106', 502);";

echo "<h2>PLC Migrasyon</h2>";
$basarili = 0;
$hatali = 0;

foreach ($sqls as $i => $sql) {
    if ($baglanti->query($sql)) {
        $basarili++;
        echo "<p>✅ Sorgu #" . ($i+1) . " başarılı</p>";
    } else {
        $hatali++;
        echo "<p>❌ Sorgu #" . ($i+1) . " hatası: " . $baglanti->error . "</p>";
    }
}

// ===== SEED DATA: Etiketler =====
echo "<h3>Etiketler ekleniyor...</h3>";

// Cihaz ID'lerini çek
$cihaz_map = [];
$result = $baglanti->query("SELECT id, cihaz_adi, ip_adresi FROM plc_cihazlari");
while ($row = $result->fetch_assoc()) {
    $cihaz_map[$row['ip_adresi']] = $row['id'];
    $cihaz_map[$row['cihaz_adi']] = $row['id'];
}

// Etiket ekleme fonksiyonu
function etiketEkle($baglanti, $cihaz_id, $etiketler) {
    $eklenen = 0;
    foreach ($etiketler as $e) {
        $adi = $baglanti->real_escape_string($e[0]);
        $adres = (int)$e[1];
        $tip = $baglanti->real_escape_string($e[2]);
        $reg_say = (int)$e[3];
        $birim = $baglanti->real_escape_string($e[4] ?? '');
        $aciklama = $baglanti->real_escape_string($e[5] ?? '');

        $check = $baglanti->query("SELECT id FROM plc_etiketleri WHERE cihaz_id=$cihaz_id AND etiket_adi='$adi'");
        if ($check && $check->num_rows == 0) {
            $sql = "INSERT INTO plc_etiketleri (cihaz_id, etiket_adi, modbus_adres, veri_tipi, register_sayisi, birim, aciklama)
                    VALUES ($cihaz_id, '$adi', $adres, '$tip', $reg_say, '$birim', '$aciklama')";
            if ($baglanti->query($sql)) $eklenen++;
        }
    }
    return $eklenen;
}

// ---- AKAR KANTAR etiketleri (tüm 20 kantar için aynı) ----
$akar_etiketler = [
    ['ANLIK_TONAJ', 102, 'INT', 1, 'kg', 'Anlık tartım değeri'],
    ['REEL_TONAJ', 200, 'INT', 1, 'kg', 'Gerçek tonaj'],
    ['SET_TONAJ', 2, 'INT', 1, 'kg', 'Hedef tonaj'],
    ['SCADA_YUZDE', 1, 'INT', 1, '%', 'Doluluk yüzdesi'],
    ['LOADCELL_YUK', 120, 'INT', 1, 'kg', 'Loadcell yük değeri'],
    ['AKAR_DURUM', 5, 'INT', 1, '', 'Kantar durumu'],
    ['SILO_ISIM', 2002, 'FLOAT', 2, '', 'Silo referansı'],
];

for ($i = 51; $i <= 70; $i++) {
    $ip = "192.168.20.$i";
    if (isset($cihaz_map[$ip])) {
        $n = etiketEkle($baglanti, $cihaz_map[$ip], $akar_etiketler);
        if ($n > 0) echo "<p>✅ AKAR KANTAR ($ip): $n etiket eklendi</p>";
    }
}

// ---- OTOMATİK TAV etiketleri ----
$oto_tav_id = $cihaz_map['192.168.20.71'] ?? null;
if ($oto_tav_id) {
    $n = etiketEkle($baglanti, $oto_tav_id, [
        ['AKIS', 112, 'FLOAT', 2, 'kg/h', 'Su akış hızı'],
        ['REEL_SU', 128, 'FLOAT', 2, 'lt', 'Gerçek su miktarı'],
        ['NEM_OKU', 100, 'FLOAT', 2, '%', 'Nem okuması'],
        ['NEM_SET', 116, 'FLOAT', 2, '%', 'Nem set değeri'],
        ['HESAP_SU', 108, 'INT', 1, 'lt', 'Hesaplanan su'],
        ['MIN_KG', 124, 'INT', 1, 'kg', 'Minimum kg'],
        ['MIN_SU', 132, 'INT', 1, 'lt', 'Minimum su'],
        ['LD_DEGERI', 136, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 120, 'INT', 1, '', 'Yük değeri'],
        ['ISTENEN_AKIS', 182, 'INT', 1, 'kg/h', 'İstenen akış'],
        ['NEM_MAN_GIRIS', 152, 'FLOAT', 2, '%', 'Manuel nem girişi'],
        ['REEL_NEM', 104, 'FLOAT', 2, '%', 'Gerçek nem'],
        ['AKTARMA1_SILO', 404, 'INT', 1, '', 'Aktarma 1 silo no'],
        ['AKTARMA2_SILO', 406, 'INT', 1, '', 'Aktarma 2 silo no'],
        ['TEMIZLEME_SILO', 402, 'INT', 1, '', 'Temizleme silo no'],
        ['ONTEMIZLEME_SILO', 400, 'INT', 1, '', 'Ön temizleme silo no'],
        ['KEPEK_SILO', 412, 'INT', 1, '', 'Kepek silo no'],
        ['UN1_SILO', 408, 'INT', 1, '', 'Un 1 silo no'],
        ['UN2_SILO', 410, 'INT', 1, '', 'Un 2 silo no'],
    ]);
    echo "<p>✅ OTOMATİK TAV: $n etiket eklendi</p>";
}

// ---- TEMİZLEME RANDIMAN ----
$temizleme_id = $cihaz_map['192.168.20.101'] ?? null;
if ($temizleme_id) {
    $n = etiketEkle($baglanti, $temizleme_id, [
        ['AKIS', 202, 'FLOAT', 2, 'kg/h', 'Anlık akış'],
        ['KG', 200, 'FLOAT', 2, 'kg', 'Anlık kg'],
        ['GUNLUK', 204, 'FLOAT', 2, 'kg', 'Günlük toplam'],
        ['AYLIK', 206, 'FLOAT', 2, 'kg', 'Aylık toplam'],
        ['YILLIK', 208, 'FLOAT', 2, 'kg', 'Yıllık toplam'],
        ['TOPLAM_1', 204, 'FLOAT', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 208, 'FLOAT', 2, 'kg', 'Toplam 2'],
        ['TOPLAM_DUN', 150, 'FLOAT', 2, 'kg', 'Dünkü toplam'],
        ['PARTI_KG', 150, 'FLOAT', 2, 'kg', 'Parti kg'],
        ['SET_DEGERI', 210, 'FLOAT', 2, '', 'Set değeri'],
        ['LD_DEGERI', 3840, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 226, 'FLOAT', 2, '', 'Yük değeri'],
    ]);
    echo "<p>✅ TEMİZLEME RANDIMAN: $n etiket eklendi</p>";
}

// ---- B1 RANDIMAN ----
$b1_id = $cihaz_map['192.168.20.102'] ?? null;
if ($b1_id) {
    $n = etiketEkle($baglanti, $b1_id, [
        ['AKIS', 22, 'INT', 1, 'kg/h', 'Anlık akış'],
        ['KG', 0, 'REAL', 2, 'kg', 'Anlık kg'],
        ['KG_BAYKON', 0, 'INT', 1, 'kg', 'Baykon kg'],
        ['GUNLUK', 204, 'FLOAT', 2, 'kg', 'Günlük toplam'],
        ['AYLIK', 206, 'FLOAT', 2, 'kg', 'Aylık toplam'],
        ['YILLIK', 208, 'FLOAT', 2, 'kg', 'Yıllık toplam'],
        ['TOPLAM_1', 62, 'DOUBLE', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 62, 'DOUBLE', 2, 'kg', 'Toplam 2'],
        ['TOPLAM_DUN', 150, 'FLOAT', 2, 'kg', 'Dünkü toplam'],
        ['PARTI_KG', 50, 'DOUBLE', 2, 'kg', 'Parti kg'],
        ['SET_DEGERI', 210, 'FLOAT', 2, '', 'Set değeri'],
        ['SET_DEBI', 572, 'INT', 1, '', 'Set debi'],
        ['LD_DEGERI', 3840, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 226, 'FLOAT', 2, '', 'Yük değeri'],
        ['STABIL_SURESI', 212, 'INT', 1, 'sn', 'Stabil süresi'],
        ['START_SURESI', 216, 'INT', 1, 'sn', 'Start süresi'],
    ]);
    echo "<p>✅ B1 RANDIMAN: $n etiket eklendi</p>";
}

// ---- UN1 RANDIMAN ----
$un1_id = $cihaz_map['192.168.20.103'] ?? null;
if ($un1_id) {
    $n = etiketEkle($baglanti, $un1_id, [
        ['AKIS', 202, 'FLOAT', 2, 'kg/h', 'Anlık akış'],
        ['KG', 200, 'FLOAT', 2, 'kg', 'Anlık kg'],
        ['KG_BAYKON', 0, 'INT', 1, 'kg', 'Baykon kg'],
        ['GUNLUK', 204, 'FLOAT', 2, 'kg', 'Günlük toplam'],
        ['AYLIK', 206, 'FLOAT', 2, 'kg', 'Aylık toplam'],
        ['YILLIK', 208, 'FLOAT', 2, 'kg', 'Yıllık toplam'],
        ['TOPLAM_1', 204, 'FLOAT', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 208, 'FLOAT', 2, 'kg', 'Toplam 2'],
        ['TOPLAM_DUN', 150, 'FLOAT', 2, 'kg', 'Dünkü toplam'],
        ['SET_DEGERI', 210, 'FLOAT', 2, '', 'Set değeri'],
        ['LD_DEGERI', 3840, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 226, 'FLOAT', 2, '', 'Yük değeri'],
        ['STABIL_SURESI', 212, 'INT', 1, 'sn', 'Stabil süresi'],
        ['START_SURESI', 216, 'INT', 1, 'sn', 'Start süresi'],
    ]);
    echo "<p>✅ UN1 RANDIMAN: $n etiket eklendi</p>";
}

// ---- UN2 RANDIMAN ----
$un2_id = $cihaz_map['192.168.20.104'] ?? null;
if ($un2_id) {
    $n = etiketEkle($baglanti, $un2_id, [
        ['AKIS', 202, 'FLOAT', 2, 'kg/h', 'Anlık akış'],
        ['KG', 200, 'FLOAT', 2, 'kg', 'Anlık kg'],
        ['GUNLUK', 204, 'FLOAT', 2, 'kg', 'Günlük toplam'],
        ['AYLIK', 206, 'FLOAT', 2, 'kg', 'Aylık toplam'],
        ['YILLIK', 208, 'FLOAT', 2, 'kg', 'Yıllık toplam'],
        ['TOPLAM_1', 204, 'FLOAT', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 208, 'FLOAT', 2, 'kg', 'Toplam 2'],
        ['TOPLAM_DUN', 150, 'FLOAT', 2, 'kg', 'Dünkü toplam'],
        ['SET_DEGERI', 210, 'FLOAT', 2, '', 'Set değeri'],
        ['LD_DEGERI', 3840, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 226, 'FLOAT', 2, '', 'Yük değeri'],
        ['STABIL_SURESI', 212, 'INT', 1, 'sn', 'Stabil süresi'],
        ['START_SURESI', 216, 'INT', 1, 'sn', 'Start süresi'],
    ]);
    echo "<p>✅ UN2 RANDIMAN: $n etiket eklendi</p>";
}

// ---- KEPEK RANDIMAN ----
$kepek_id = $cihaz_map['192.168.20.105'] ?? null;
if ($kepek_id) {
    $n = etiketEkle($baglanti, $kepek_id, [
        ['AKIS', 202, 'FLOAT', 2, 'kg/h', 'Anlık akış'],
        ['KG', 200, 'FLOAT', 2, 'kg', 'Anlık kg'],
        ['GUNLUK', 204, 'FLOAT', 2, 'kg', 'Günlük toplam'],
        ['AYLIK', 206, 'FLOAT', 2, 'kg', 'Aylık toplam'],
        ['YILLIK', 208, 'FLOAT', 2, 'kg', 'Yıllık toplam'],
        ['TOPLAM_1', 204, 'FLOAT', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 208, 'FLOAT', 2, 'kg', 'Toplam 2'],
        ['TOPLAM_DUN', 150, 'FLOAT', 2, 'kg', 'Dünkü toplam'],
        ['SET_DEGERI', 210, 'FLOAT', 2, '', 'Set değeri'],
        ['LD_DEGERI', 3840, 'DOUBLE', 2, '', 'Load cell değeri'],
        ['YUK_DEGERI', 226, 'FLOAT', 2, '', 'Yük değeri'],
        ['STABIL_SURESI', 212, 'INT', 1, 'sn', 'Stabil süresi'],
        ['START_SURESI', 216, 'INT', 1, 'sn', 'Start süresi'],
    ]);
    echo "<p>✅ KEPEK RANDIMAN: $n etiket eklendi</p>";
}

// ---- TEMİZLEME 2 RANDIMAN ----
$temiz2_id = $cihaz_map['192.168.20.106'] ?? null;
if ($temiz2_id) {
    $n = etiketEkle($baglanti, $temiz2_id, [
        ['AKIS', 22, 'INT', 1, 'kg/h', 'Anlık akış'],
        ['KG', 0, 'REAL', 2, 'kg', 'Anlık kg'],
        ['KG_BAYKON', 0, 'INT', 1, 'kg', 'Baykon kg'],
        ['PARTI_KG', 50, 'DOUBLE', 2, 'kg', 'Parti kg'],
        ['TOPLAM_1', 62, 'DOUBLE', 2, 'kg', 'Toplam 1'],
        ['TOPLAM_2', 62, 'DOUBLE', 2, 'kg', 'Toplam 2'],
    ]);
    echo "<p>✅ TEMİZLEME 2 RANDIMAN: $n etiket eklendi</p>";
}

echo "<hr>";
echo "<h3>Sonuç: $basarili başarılı, $hatali hatalı sorgu</h3>";
echo "<p><a href='panel.php'>Panele Dön</a></p>";

$baglanti->close();
?>
