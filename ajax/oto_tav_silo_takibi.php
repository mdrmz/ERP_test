<?php
/**
 * OTO TAV (.71) silo takip servisi
 *
 * Cikti:
 * {
 *   basari: bool,
 *   cihaz_ip: string,
 *   okuma_zamani: string|null,
 *   stale: bool,
 *   tagler: {
 *     TAG_ADI: {
 *       ham_deger: mixed,
 *       mapped_silo_id: int|null,
 *       mapped_silo_adi: string|null,
 *       mapped_tip: string|null,
 *       mapping_bulundu: bool
 *     }
 *   },
 *   uyarilar: string[]
 * }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include(__DIR__ . '/../baglan.php');

$otoTavIp = '192.168.20.71';
$staleLimitSeconds = 120;
$tagList = [
    'ONTEMIZLEME_SILO',
    'TEMIZLEME_SILO',
    'AKTARMA1_SILO',
    'AKTARMA2_SILO',
    'UN1_SILO',
    'UN2_SILO',
    'KEPEK_SILO',
];

$response = [
    'basari' => false,
    'cihaz_ip' => $otoTavIp,
    'okuma_zamani' => null,
    'stale' => true,
    'tagler' => [],
    'uyarilar' => [],
];

foreach ($tagList as $tag) {
    $response['tagler'][$tag] = [
        'ham_deger' => null,
        'mapped_silo_id' => null,
        'mapped_silo_adi' => null,
        'mapped_tip' => null,
        'mapping_bulundu' => false,
    ];
}

try {
    $stmtCihaz = $baglanti->prepare("SELECT id FROM plc_cihazlari WHERE ip_adresi = ? LIMIT 1");
    $stmtCihaz->bind_param('s', $otoTavIp);
    $stmtCihaz->execute();
    $cihazRes = $stmtCihaz->get_result();
    $cihaz = $cihazRes ? $cihazRes->fetch_assoc() : null;
    $stmtCihaz->close();

    if (!$cihaz) {
        $response['uyarilar'][] = "PLC cihazi bulunamadi (ip: {$otoTavIp}).";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cihazId = (int) $cihaz['id'];

    $stmtOkuma = $baglanti->prepare("
        SELECT
            o.okuma_zamani,
            o.veriler,
            TIMESTAMPDIFF(SECOND, o.okuma_zamani, NOW()) AS yas_saniye
        FROM plc_okumalari o
        WHERE o.cihaz_id = ?
        ORDER BY o.id DESC
        LIMIT 1
    ");
    $stmtOkuma->bind_param('i', $cihazId);
    $stmtOkuma->execute();
    $okumaRes = $stmtOkuma->get_result();
    $okumaRow = $okumaRes ? $okumaRes->fetch_assoc() : null;
    $stmtOkuma->close();

    if (!$okumaRow) {
        $response['basari'] = true;
        $response['uyarilar'][] = 'Bu cihaz icin hic plc_okumalari kaydi bulunamadi.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response['okuma_zamani'] = $okumaRow['okuma_zamani'];
    $yasSaniye = isset($okumaRow['yas_saniye']) ? (int) $okumaRow['yas_saniye'] : 999999;
    $response['stale'] = ($yasSaniye > $staleLimitSeconds);
    if ($response['stale']) {
        $response['uyarilar'][] = "Son okuma stale: {$yasSaniye} saniye once.";
    }

    $veriler = json_decode((string) $okumaRow['veriler'], true);
    if (!is_array($veriler)) {
        $veriler = [];
        $response['uyarilar'][] = 'Son okuma JSON verisi parse edilemedi.';
    }

    $plcKodlar = [];
    foreach ($tagList as $tag) {
        $hamDeger = array_key_exists($tag, $veriler) ? $veriler[$tag] : null;
        $response['tagler'][$tag]['ham_deger'] = $hamDeger;

        if ($hamDeger === null) {
            $response['uyarilar'][] = "{$tag} etiketi son okumada bulunamadi.";
            continue;
        }

        if (!is_numeric($hamDeger)) {
            $response['uyarilar'][] = "{$tag} sayisal degil (deger: {$hamDeger}).";
            continue;
        }

        $plcKod = (int) $hamDeger;
        if ($plcKod > 0) {
            $plcKodlar[$plcKod] = true;
        }
    }

    $mappingMap = [];
    if (!empty($plcKodlar)) {
        $kodListSql = implode(',', array_map('intval', array_keys($plcKodlar)));
        $sqlMap = "
            SELECT
                m.plc_kod,
                m.silo_id,
                s.silo_adi,
                s.tip
            FROM plc_silo_numara_haritasi m
            LEFT JOIN silolar s ON s.id = m.silo_id
            WHERE m.aktif = 1
              AND m.plc_kod IN ({$kodListSql})
        ";
        $mapRes = $baglanti->query($sqlMap);
        while ($mapRes && ($row = $mapRes->fetch_assoc())) {
            $mappingMap[(int) $row['plc_kod']] = $row;
        }
    }

    foreach ($tagList as $tag) {
        $hamDeger = $response['tagler'][$tag]['ham_deger'];
        if (!is_numeric($hamDeger)) {
            continue;
        }

        $plcKod = (int) $hamDeger;
        if ($plcKod <= 0) {
            continue;
        }

        if (!isset($mappingMap[$plcKod])) {
            $response['uyarilar'][] = "{$tag} icin mapping bulunamadi (plc_kod={$plcKod}).";
            continue;
        }

        $map = $mappingMap[$plcKod];
        $mappedSiloId = isset($map['silo_id']) ? (int) $map['silo_id'] : 0;

        if ($mappedSiloId <= 0 || empty($map['silo_adi'])) {
            $response['uyarilar'][] = "{$tag} mapping kaydi var ama silo baglantisi gecersiz (plc_kod={$plcKod}).";
            continue;
        }

        $response['tagler'][$tag]['mapped_silo_id'] = $mappedSiloId;
        $response['tagler'][$tag]['mapped_silo_adi'] = $map['silo_adi'];
        $response['tagler'][$tag]['mapped_tip'] = $map['tip'];
        $response['tagler'][$tag]['mapping_bulundu'] = true;
    }

    $response['basari'] = true;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $response['basari'] = false;
    $response['uyarilar'][] = 'Servis hatasi: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

