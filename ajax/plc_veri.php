<?php
/**
 * PLC Veri Servisi
 * Son okunan PLC verilerini JSON olarak döndürür.
 *
 * Kullanım:
 *   /ajax/plc_veri.php                        → Tüm cihazların son okumaları
 *   /ajax/plc_veri.php?tip=randiman           → Sadece randıman kantarları
 *   /ajax/plc_veri.php?cihaz_id=5             → Tek bir cihazın son okuması
 *   /ajax/plc_veri.php?cihaz_id=5&gecmis=60   → Son 60 dakikanın verileri
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include(__DIR__ . "/../baglan.php");

$cihaz_id = isset($_GET['cihaz_id']) ? (int)$_GET['cihaz_id'] : 0;
$tip = isset($_GET['tip']) ? trim($_GET['tip']) : '';
$gecmis_dk = isset($_GET['gecmis']) ? (int)$_GET['gecmis'] : 0;

try {
    // === TEK CİHAZ + GEÇMİŞ ===
    if ($cihaz_id > 0 && $gecmis_dk > 0) {
        $stmt = $baglanti->prepare("
            SELECT o.okuma_zamani, o.veriler
            FROM plc_okumalari o
            WHERE o.cihaz_id = ?
              AND o.okuma_zamani >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY o.okuma_zamani DESC
        ");
        $stmt->bind_param("ii", $cihaz_id, $gecmis_dk);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'zaman' => $row['okuma_zamani'],
                'veriler' => json_decode($row['veriler'], true)
            ];
        }

        echo json_encode([
            'basari' => true,
            'cihaz_id' => $cihaz_id,
            'gecmis_dk' => $gecmis_dk,
            'kayit_sayisi' => count($data),
            'veriler' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === TEK CİHAZ (SON OKUMA) ===
    if ($cihaz_id > 0) {
        $stmt = $baglanti->prepare("
            SELECT c.cihaz_adi, c.cihaz_tipi, c.ip_adresi, c.son_baglanti, c.aktif,
                   o.okuma_zamani, o.veriler
            FROM plc_cihazlari c
            LEFT JOIN plc_okumalari o ON o.id = (
                SELECT o2.id FROM plc_okumalari o2
                WHERE o2.cihaz_id = c.id
                ORDER BY o2.okuma_zamani DESC
                LIMIT 1
            )
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $cihaz_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['basari' => false, 'hata' => 'Cihaz bulunamadı']);
            exit;
        }

        echo json_encode([
            'basari' => true,
            'cihaz' => [
                'id' => $cihaz_id,
                'adi' => $row['cihaz_adi'],
                'tipi' => $row['cihaz_tipi'],
                'ip' => $row['ip_adresi'],
                'aktif' => (bool)$row['aktif'],
                'son_baglanti' => $row['son_baglanti'],
                'son_okuma' => $row['okuma_zamani'],
                'veriler' => $row['veriler'] ? json_decode($row['veriler'], true) : null
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === TÜM CİHAZLAR (veya tip filtreli) ===
    $sql = "
        SELECT c.id, c.cihaz_adi, c.cihaz_tipi, c.ip_adresi, c.son_baglanti, c.aktif,
               o.okuma_zamani, o.veriler
        FROM plc_cihazlari c
        LEFT JOIN plc_okumalari o ON o.id = (
            SELECT o2.id FROM plc_okumalari o2
            WHERE o2.cihaz_id = c.id
            ORDER BY o2.okuma_zamani DESC
            LIMIT 1
        )
    ";

    if ($tip !== '') {
        $sql .= " WHERE c.cihaz_tipi = '" . $baglanti->real_escape_string($tip) . "'";
    }

    $sql .= " ORDER BY c.cihaz_tipi, c.cihaz_adi";
    $result = $baglanti->query($sql);

    $cihazlar = [];
    while ($row = $result->fetch_assoc()) {
        $cihazlar[] = [
            'id' => (int)$row['id'],
            'adi' => $row['cihaz_adi'],
            'tipi' => $row['cihaz_tipi'],
            'ip' => $row['ip_adresi'],
            'aktif' => (bool)$row['aktif'],
            'son_baglanti' => $row['son_baglanti'],
            'son_okuma' => $row['okuma_zamani'],
            'veriler' => $row['veriler'] ? json_decode($row['veriler'], true) : null
        ];
    }

    echo json_encode([
        'basari' => true,
        'toplam' => count($cihazlar),
        'cihazlar' => $cihazlar
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'basari' => false,
        'hata' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
