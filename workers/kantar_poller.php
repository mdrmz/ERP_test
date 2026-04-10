<?php
/**
 * Kantar polling script (Raspberry icin).
 * Varsayilan: sonsuz dongu, 20 saniyede bir okur.
 *
 * Kullanim:
 *   php workers/kantar_poller.php
 *   php workers/kantar_poller.php --once
 *   php workers/kantar_poller.php --interval=20
 *   php workers/kantar_poller.php --ip=192.168.1.53 --port=1453 --file=tartim.txt
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Istanbul');

include(__DIR__ . '/../baglan.php');
include(__DIR__ . '/../services/kantar_service.php');

$baglanti->set_charset('utf8mb4');

$args = $_SERVER['argv'] ?? [];
$runOnce = in_array('--once', $args, true);
$interval = 20;
$ip = '192.168.1.53';
$port = 1453;
$file = 'tartim.txt';

foreach ($args as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $val = (int) substr($arg, strlen('--interval='));
        if ($val > 0) {
            $interval = $val;
        }
    } elseif (strpos($arg, '--ip=') === 0) {
        $tmp = trim((string) substr($arg, strlen('--ip=')));
        if ($tmp !== '') {
            $ip = $tmp;
        }
    } elseif (strpos($arg, '--port=') === 0) {
        $tmp = (int) substr($arg, strlen('--port='));
        if ($tmp > 0) {
            $port = $tmp;
        }
    } elseif (strpos($arg, '--file=') === 0) {
        $tmp = trim((string) substr($arg, strlen('--file=')));
        if ($tmp !== '') {
            $file = $tmp;
        }
    }
}

function kpLog($message)
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$message}" . PHP_EOL;
}

kpLog("Kantar poller basladi. Kaynak: " . kantarBuildSourceUrl($ip, $port, $file) . " | interval: {$interval}s");

while (true) {
    if (!@$baglanti->ping()) {
        kpLog('DB baglantisi aktif degil, cikiliyor.');
        exit(1);
    }

    $result = kantarFetchParseAndStore($baglanti, $ip, $port, $file);

    if (!$result['ok']) {
        $code = (string) ($result['error_code'] ?? 'GENEL');
        kpLog("HATA({$code}): " . ($result['error'] ?? 'Bilinmeyen hata'));
    } else {
        $row = $result['row'];
        $id = (int) ($row['id'] ?? 0);
        $plaka = (string) ($row['plaka_raw'] ?? '-');
        $net = (float) ($row['net_kg'] ?? 0);
        $yeni = !empty($result['store']['yeni']);
        $guncellendi = !empty($result['store']['guncellendi']);

        if ($yeni) {
            kpLog("KAYDEDILDI | id={$id} | plaka={$plaka} | net={$net} kg");
        } elseif ($guncellendi) {
            kpLog("GUNCELLENDI | id={$id} | plaka={$plaka} | net={$net} kg");
        } else {
            kpLog("SKIP(DUP)  | id={$id} | plaka={$plaka} | net={$net} kg");
        }
    }

    if ($runOnce) {
        break;
    }

    sleep($interval);
}

kpLog('Kantar poller tamamlandi.');

