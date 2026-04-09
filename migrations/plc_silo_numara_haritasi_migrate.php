<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/../baglan.php');

$baglanti->set_charset('utf8mb4');

function psmLog($message)
{
    echo $message . PHP_EOL;
}

function psmRun($baglanti, $sql, $okMsg, $errPrefix)
{
    if ($baglanti->query($sql)) {
        psmLog('OK: ' . $okMsg);
        return true;
    }
    psmLog('ERR: ' . $errPrefix . ' | ' . $baglanti->error);
    return false;
}

function psmIndexExists($baglanti, $table, $indexName)
{
    $table = $baglanti->real_escape_string($table);
    $indexName = $baglanti->real_escape_string($indexName);
    $res = $baglanti->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
    return ($res && $res->num_rows > 0);
}

function psmFkExists($baglanti, $table, $fkName)
{
    $table = $baglanti->real_escape_string($table);
    $fkName = $baglanti->real_escape_string($fkName);
    $sql = "SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND CONSTRAINT_NAME = '{$fkName}'
            LIMIT 1";
    $res = $baglanti->query($sql);
    return ($res && $res->num_rows > 0);
}

psmLog('PLC silo numara haritasi migration basladi...');

$createTable = "CREATE TABLE IF NOT EXISTS `plc_silo_numara_haritasi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `plc_kod` INT NOT NULL,
    `silo_id` INT NULL,
    `aktif` TINYINT(1) NOT NULL DEFAULT 1,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
psmRun($baglanti, $createTable, 'plc_silo_numara_haritasi tablo kontrolu tamamlandi.', 'Tablo olusturma hatasi');

if (!psmIndexExists($baglanti, 'plc_silo_numara_haritasi', 'uq_plc_silo_numara_haritasi_plc_kod')) {
    psmRun(
        $baglanti,
        "ALTER TABLE `plc_silo_numara_haritasi`
         ADD UNIQUE KEY `uq_plc_silo_numara_haritasi_plc_kod` (`plc_kod`)",
        'uq_plc_silo_numara_haritasi_plc_kod eklendi.',
        'uq_plc_silo_numara_haritasi_plc_kod eklenemedi'
    );
} else {
    psmLog('SKIP: uq_plc_silo_numara_haritasi_plc_kod zaten var.');
}

if (!psmIndexExists($baglanti, 'plc_silo_numara_haritasi', 'idx_plc_silo_numara_haritasi_silo_id')) {
    psmRun(
        $baglanti,
        "ALTER TABLE `plc_silo_numara_haritasi`
         ADD KEY `idx_plc_silo_numara_haritasi_silo_id` (`silo_id`)",
        'idx_plc_silo_numara_haritasi_silo_id eklendi.',
        'idx_plc_silo_numara_haritasi_silo_id eklenemedi'
    );
} else {
    psmLog('SKIP: idx_plc_silo_numara_haritasi_silo_id zaten var.');
}

if (!psmIndexExists($baglanti, 'plc_silo_numara_haritasi', 'idx_plc_silo_numara_haritasi_aktif')) {
    psmRun(
        $baglanti,
        "ALTER TABLE `plc_silo_numara_haritasi`
         ADD KEY `idx_plc_silo_numara_haritasi_aktif` (`aktif`)",
        'idx_plc_silo_numara_haritasi_aktif eklendi.',
        'idx_plc_silo_numara_haritasi_aktif eklenemedi'
    );
} else {
    psmLog('SKIP: idx_plc_silo_numara_haritasi_aktif zaten var.');
}

if (!psmFkExists($baglanti, 'plc_silo_numara_haritasi', 'fk_plc_silo_numara_haritasi_silo')) {
    psmRun(
        $baglanti,
        "ALTER TABLE `plc_silo_numara_haritasi`
         ADD CONSTRAINT `fk_plc_silo_numara_haritasi_silo`
         FOREIGN KEY (`silo_id`) REFERENCES `silolar`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE",
        'fk_plc_silo_numara_haritasi_silo eklendi.',
        'fk_plc_silo_numara_haritasi_silo eklenemedi'
    );
} else {
    psmLog('SKIP: fk_plc_silo_numara_haritasi_silo zaten var.');
}

// Ornek kayitlar (elle mapping icin baslangic verisi). Mevcut kayitlar ezilmez.
$seedRows = [
    [16, 16, 'Ornek esleme: PLC 16 -> Silo 16'],
    [18, 18, 'Ornek esleme: PLC 18 -> Silo 18'],
    [30, 30, 'Ornek esleme: PLC 30 -> Silo 30'],
    [32, 32, 'Ornek esleme: PLC 32 -> Silo 32'],
    [35, 35, 'Ornek esleme: PLC 35 -> Silo 35'],
];

foreach ($seedRows as $row) {
    $plcKod = (int) $row[0];
    $siloId = (int) $row[1];
    $aciklama = $baglanti->real_escape_string($row[2]);

    $sql = "INSERT IGNORE INTO `plc_silo_numara_haritasi` (`plc_kod`, `silo_id`, `aktif`, `aciklama`)
            VALUES ({$plcKod}, {$siloId}, 1, '{$aciklama}')";
    if ($baglanti->query($sql)) {
        if ($baglanti->affected_rows > 0) {
            psmLog("OK: Ornek mapping eklendi (plc_kod={$plcKod}, silo_id={$siloId}).");
        } else {
            psmLog("SKIP: plc_kod={$plcKod} kaydi zaten mevcut.");
        }
    } else {
        psmLog("ERR: plc_kod={$plcKod} ornek mapping eklenemedi | " . $baglanti->error);
    }
}

psmLog('PLC silo numara haritasi migration tamamlandi.');

