-- hammadde_girisleri tablosuna yukleme/bosaltma secimi ekler.
-- Eski kayitlar NULL kalir ve uygulamada "Bilinmiyor" olarak gosterilir.

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hammadde_girisleri'
      AND COLUMN_NAME = 'islem_turu'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `hammadde_girisleri` ADD COLUMN `islem_turu` ENUM(''yukleme'',''bosaltma'') NULL AFTER `arac_plaka`',
    'SELECT ''hammadde_girisleri.islem_turu already exists'' AS migration_info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
