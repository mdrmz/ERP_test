<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/../baglan.php');

$baglanti->set_charset('utf8mb4');

function kmLog($message)
{
    echo $message . PHP_EOL;
}

function kmRun($baglanti, $sql, $okMsg, $errPrefix)
{
    if ($baglanti->query($sql)) {
        kmLog("OK: " . $okMsg);
        return true;
    }
    kmLog("ERR: " . $errPrefix . " | " . $baglanti->error);
    return false;
}

function kmColumnExists($baglanti, $table, $column)
{
    $table = $baglanti->real_escape_string($table);
    $column = $baglanti->real_escape_string($column);
    $res = $baglanti->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return ($res && $res->num_rows > 0);
}

function kmIndexExists($baglanti, $table, $indexName)
{
    $table = $baglanti->real_escape_string($table);
    $indexName = $baglanti->real_escape_string($indexName);
    $res = $baglanti->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
    return ($res && $res->num_rows > 0);
}

function kmFkExistsOnColumn($baglanti, $table, $column)
{
    $table = $baglanti->real_escape_string($table);
    $column = $baglanti->real_escape_string($column);
    $sql = "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1";
    $res = $baglanti->query($sql);
    return ($res && $res->num_rows > 0);
}

kmLog("Kantar migration basladi...");

$createKantar = "CREATE TABLE IF NOT EXISTS `kantar_okumalari` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `source_url` VARCHAR(255) DEFAULT NULL,
    `plaka_raw` VARCHAR(32) DEFAULT NULL,
    `plaka_norm` VARCHAR(32) DEFAULT NULL,
    `tartim_tarihi` DATE DEFAULT NULL,
    `tartim_saati` TIME DEFAULT NULL,
    `tartim_zamani` DATETIME DEFAULT NULL,
    `brut_kg` DECIMAL(12,2) DEFAULT 0.00,
    `tara_kg` DECIMAL(12,2) DEFAULT 0.00,
    `net_kg` DECIMAL(12,2) DEFAULT 0.00,
    `firma` VARCHAR(150) DEFAULT NULL,
    `urun` VARCHAR(150) DEFAULT NULL,
    `kaynak_il` VARCHAR(100) DEFAULT NULL,
    `hedef_il` VARCHAR(100) DEFAULT NULL,
    `surucu` VARCHAR(150) DEFAULT NULL,
    `ham_veri` MEDIUMTEXT DEFAULT NULL,
    `veri_hash` CHAR(64) NOT NULL,
    `cekim_zamani` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
kmRun($baglanti, $createKantar, 'kantar_okumalari tablo kontrolu tamamlandi.', 'kantar_okumalari olusturma hatasi');

if (!kmIndexExists($baglanti, 'kantar_okumalari', 'uq_kantar_veri_hash')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `kantar_okumalari` ADD UNIQUE KEY `uq_kantar_veri_hash` (`veri_hash`)",
        'uq_kantar_veri_hash eklendi.',
        'uq_kantar_veri_hash eklenemedi'
    );
} else {
    kmLog('SKIP: uq_kantar_veri_hash zaten var.');
}

if (!kmIndexExists($baglanti, 'kantar_okumalari', 'idx_kantar_plaka_zaman')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `kantar_okumalari` ADD KEY `idx_kantar_plaka_zaman` (`plaka_norm`, `tartim_zamani`, `id`)",
        'idx_kantar_plaka_zaman eklendi.',
        'idx_kantar_plaka_zaman eklenemedi'
    );
} else {
    kmLog('SKIP: idx_kantar_plaka_zaman zaten var.');
}

if (!kmIndexExists($baglanti, 'kantar_okumalari', 'idx_kantar_cekim_zamani')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `kantar_okumalari` ADD KEY `idx_kantar_cekim_zamani` (`cekim_zamani`)",
        'idx_kantar_cekim_zamani eklendi.',
        'idx_kantar_cekim_zamani eklenemedi'
    );
} else {
    kmLog('SKIP: idx_kantar_cekim_zamani zaten var.');
}

if (!kmColumnExists($baglanti, 'hammadde_kabul_akisi', 'kantar_okuma_id')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `hammadde_kabul_akisi` ADD COLUMN `kantar_okuma_id` BIGINT NULL AFTER `kantar_tarihi`",
        'hammadde_kabul_akisi.kantar_okuma_id eklendi.',
        'hammadde_kabul_akisi.kantar_okuma_id eklenemedi'
    );
} else {
    kmLog('SKIP: hammadde_kabul_akisi.kantar_okuma_id zaten var.');
}

if (!kmIndexExists($baglanti, 'hammadde_kabul_akisi', 'idx_hka_kantar_okuma')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `hammadde_kabul_akisi` ADD KEY `idx_hka_kantar_okuma` (`kantar_okuma_id`)",
        'idx_hka_kantar_okuma eklendi.',
        'idx_hka_kantar_okuma eklenemedi'
    );
} else {
    kmLog('SKIP: idx_hka_kantar_okuma zaten var.');
}

if (!kmFkExistsOnColumn($baglanti, 'hammadde_kabul_akisi', 'kantar_okuma_id')) {
    kmRun(
        $baglanti,
        "ALTER TABLE `hammadde_kabul_akisi`
         ADD CONSTRAINT `fk_hka_kantar_okuma`
         FOREIGN KEY (`kantar_okuma_id`) REFERENCES `kantar_okumalari`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE",
        'fk_hka_kantar_okuma eklendi.',
        'fk_hka_kantar_okuma eklenemedi'
    );
} else {
    kmLog('SKIP: hammadde_kabul_akisi.kantar_okuma_id icin FK zaten var.');
}

kmLog("Kantar migration tamamlandi.");

