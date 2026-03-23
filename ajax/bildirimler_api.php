<?php
/**
 * Bildirim API - AJAX Endpoint
 * Navbar popup ve bildirim sayacı için
 */

session_start();
include("../baglan.php");
include("../helper_functions.php");

header('Content-Type: application/json');

if (!isset($_SESSION["oturum"])) {
    echo json_encode(['error' => 'Oturum yok', 'count' => 0]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'count':
        // Okunmamış bildirim sayısı
        $sayi = bildirimSayisi($baglanti);
        echo json_encode(['count' => $sayi]);
        break;

    case 'all_counts':
        // Bildirim + Sipariş + Onay sayaçları
        $bildirim_count = (int) bildirimSayisi($baglanti);
        $can_siparis = (bool) modulYetkisiVar($baglanti, 'Satış & Siparişler', 'okuma');
        $can_onay = ((($_SESSION['rol_adi'] ?? '') === 'Patron' || (int) ($_SESSION['rol_id'] ?? 0) === 1) || (bool) onayYetkisiVar($baglanti));

        $siparis_count = 0;
        $onay_count = 0;

        if ($can_siparis) {
            $siparis_result = @$baglanti->query("SELECT COUNT(*) AS cnt FROM siparisler WHERE durum = 'Bekliyor'");
            if ($siparis_result && $siparis_row = $siparis_result->fetch_assoc()) {
                $siparis_count = (int) ($siparis_row['cnt'] ?? 0);
            }
        }

        if ($can_onay) {
            $onay_result = @$baglanti->query("SELECT COUNT(*) AS cnt FROM onay_bekleyenler WHERE onay_durum = 'bekliyor'");
            if ($onay_result && $onay_row = $onay_result->fetch_assoc()) {
                $onay_count += (int) ($onay_row['cnt'] ?? 0);
            }

            // Onay Merkezi'ndeki hammadde akış bekleyenlerini de dahil et
            $hammadde_tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'hammadde_kabul_akisi'");
            if ($hammadde_tablo_kontrol && $hammadde_tablo_kontrol->num_rows > 0) {
                $hammadde_onay_result = @$baglanti->query("SELECT COUNT(*) AS cnt FROM hammadde_kabul_akisi WHERE asama IN ('bekliyor', 'analiz_yapildi', 'onay_bekleniyor')");
                if ($hammadde_onay_result && $hammadde_onay_row = $hammadde_onay_result->fetch_assoc()) {
                    $onay_count += (int) ($hammadde_onay_row['cnt'] ?? 0);
                }
            }
        }

        echo json_encode([
            'bildirim_count' => $bildirim_count,
            'siparis_count' => $siparis_count,
            'onay_count' => $onay_count,
            'can_siparis' => $can_siparis,
            'can_onay' => $can_onay
        ]);
        break;

    case 'list':
        // Son bildirimleri listele
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
        $bildirimler = bildirimleriGetir($baglanti, $limit, false);

        $liste = [];
        if ($bildirimler) {
            while ($row = $bildirimler->fetch_assoc()) {
                $liste[] = [
                    'id' => $row['id'],
                    'tip' => $row['bildirim_tipi'],
                    'baslik' => $row['baslik'],
                    'aciklama' => $row['aciklama'],
                    'link' => $row['link'],
                    'okundu' => (bool) $row['okundu_durum'],
                    'ikon' => bildirimIkon($row['bildirim_tipi']),
                    'tarih' => tarihFormat($row['olusturma_tarihi']),
                    'tarih_ago' => zamanOnce($row['olusturma_tarihi'])
                ];
            }
        }

        $sayi = bildirimSayisi($baglanti);
        echo json_encode(['count' => $sayi, 'bildirimler' => $liste]);
        break;

    case 'read':
        // Tek bildirimi okundu işaretle
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            bildirimOkundu($baglanti, $id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID gerekli']);
        }
        break;

    case 'read_all':
        // Tümünü okundu işaretle
        tumBildirimleriOkundu($baglanti);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz action']);
}

/**
 * Zaman farkını insani formatta döndürür
 */
function zamanOnce($tarih)
{
    $simdi = time();
    $zaman = strtotime($tarih);
    $fark = $simdi - $zaman;

    if ($fark < 60) {
        return 'Az önce';
    } elseif ($fark < 3600) {
        $dakika = floor($fark / 60);
        return $dakika . ' dk önce';
    } elseif ($fark < 86400) {
        $saat = floor($fark / 3600);
        return $saat . ' saat önce';
    } elseif ($fark < 604800) {
        $gun = floor($fark / 86400);
        return $gun . ' gün önce';
    } else {
        return date('d.m.Y', $zaman);
    }
}
?>
