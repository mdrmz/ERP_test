<?php
include("baglan.php");
$baglanti->query("CREATE TABLE IF NOT EXISTS `haftalik_plan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hafta_no` INT NOT NULL,
  `yil` INT NOT NULL,
  `siparis_id` INT NULL,
  `urun_adi` VARCHAR(150) NOT NULL,
  `miktar_ton` DECIMAL(10,2) NOT NULL,
  `oncelik` ENUM('dusuk','normal','yuksek','acil') DEFAULT 'normal',
  `durum` ENUM('planlanmis','uretimde','tamamlandi') DEFAULT 'planlanmis',
  `notlar` TEXT,
  `olusturan` VARCHAR(100),
  `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_hafta` (`yil`, `hafta_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "haftalik_plan tablosu OK\n";
