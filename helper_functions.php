<?php
/**
 * =====================================================
 * ÖZBAL UN ERP - YARDIMCI FONKSİYONLAR
 * Versiyon: 2.0
 * Tarih: 26 Ocak 2026
 *
 * İçerik:
 * - Kullanıcı yetkilendirme kontrolleri
 * - Onay sistemi fonksiyonları
 * - Log kayıt fonksiyonları
 * - Hesaplama fonksiyonları (M³, kütle denkliği, vs.)
 * =====================================================
 */

// ==============================================
// KULLANICI YETKİLENDİRME FONKSİYONLARI
// ==============================================

/**
 * Kullanıcının belirli bir modüle erişim yetkisi var mı kontrol eder
 *
 * @param mysqli $baglanti
 * @param string $modul_adi
 * @param string $yetki_tipi "okuma", "yazma", "onaylama"
 * @return bool
 */
function yetkiKontrol($baglanti, $modul_adi, $yetki_tipi = 'okuma')
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = $_SESSION['user_id'];

    // Kullanıcının rolünü bul
    $sql = "SELECT u.rol_id, r.rol_adi
            FROM users u
            JOIN kullanici_rolleri r ON u.rol_id = r.id
            WHERE u.id = $user_id";
    $result = $baglanti->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $rol_id = $user['rol_id'];

        // Patron her şeyi yapabilir
        if ($user['rol_adi'] == 'Patron') {
            return true;
        }

        $yetki_kolonu = $yetki_tipi; // okuma, yazma, onaylama
        $modul_adi_esc = $baglanti->real_escape_string($modul_adi);

        // Bireysel override kontrolü
        $sql_bireysel = "SELECT $yetki_kolonu FROM kullanici_modul_yetkileri WHERE user_id = $user_id AND modul_adi = '$modul_adi_esc'";
        $result_bireysel = $baglanti->query($sql_bireysel);
        if ($result_bireysel && $result_bireysel->num_rows > 0) {
            $yetki = $result_bireysel->fetch_assoc();
            return (bool) $yetki[$yetki_kolonu];
        }

        // Modül yetkilerini kontrol et (Role göre)
        $sql_yetki = "SELECT $yetki_kolonu
                      FROM modul_yetkileri
                      WHERE rol_id = $rol_id AND modul_adi = '$modul_adi_esc'";
        $result_yetki = $baglanti->query($sql_yetki);

        if ($result_yetki && $result_yetki->num_rows > 0) {
            $yetki = $result_yetki->fetch_assoc();
            return (bool) $yetki[$yetki_kolonu];
        }
    }

    return false;
}

/**
 * Sayfa-Modül eşleştirme haritası
 * 
 * @return array
 */
function sayfaModulHaritasi()
{
    return [
        'hammadde.php' => 'Hammadde Yönetimi',
        'planlama.php' => 'Planlama & Takvim',
        'uretim.php' => 'Üretim Paneli',
        'siparisler.php' => 'Satış & Siparişler',
        'siparis_ajax.php' => 'Satış & Siparişler',
        'pazarlama.php' => 'Pazarlama',
        'musteriler.php' => 'Müşteriler',
        'sikayetler.php' => 'Müşteriler',
        'satin_alma.php' => 'Satın Alma',
        'depo_sevkiyat.php' => 'Sevkiyat & Lojistik',
        'malzeme_stok.php' => 'Stok Takibi',
        'izlenebilirlik.php' => 'İzlenebilirlik',
        'lab_analizleri.php' => 'Lab Analizleri',
        'bakim.php' => 'Bakım & Arıza',
        'silo_yonetimi.php' => 'Silo Yönetimi',
        'hammadde_kodlama.php' => 'Hammadde Kodlama',
        'kullanici_yonetimi.php' => 'Sistem Ayarları',
        'islem_gecmisi.php' => 'Sistem Ayarları',
        'onay_merkezi.php' => 'Sistem Ayarları'
    ];
}

/**
 * Modül yetkisi kontrolü (yeni sistem)
 * 
 * @param mysqli $baglanti
 * @param string|null $modul_adi Belirtilmezse mevcut sayfadan alınır
 * @param string $yetki_tipi "okuma", "yazma", "onaylama"
 * @return bool
 */
function modulYetkisiVar($baglanti, $modul_adi = null, $yetki_tipi = 'okuma')
{
    // Session kontrolü
    if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] === null || !isset($_SESSION['user_id'])) {
        return false;
    }

    $rol_id = (int) $_SESSION['rol_id'];
    $user_id = (int) $_SESSION['user_id'];
    $rol_adi = $_SESSION['rol_adi'] ?? '';

    // Patron her şeyi görebilir
    if ($rol_adi === 'Patron') {
        return true;
    }

    // Modül adı parametre olarak verilmediyse, mevcut sayfadan al
    if ($modul_adi === null) {
        $sayfa = basename($_SERVER['PHP_SELF']);
        $harita = sayfaModulHaritasi();
        $modul_adi = $harita[$sayfa] ?? null;

        // Haritada yoksa erişim izni ver (panel.php gibi genel sayfalar)
        if ($modul_adi === null) {
            return true;
        }
    }

    // SQL güvenliği için escape
    $modul_adi_esc = $baglanti->real_escape_string($modul_adi);

    // İlk olarak bireysel override kontrolü
    $sql_bireysel = "SELECT $yetki_tipi FROM kullanici_modul_yetkileri WHERE user_id = $user_id AND modul_adi = '$modul_adi_esc'";
    $result_bireysel = $baglanti->query($sql_bireysel);
    if ($result_bireysel && $result_bireysel->num_rows > 0) {
        $yetki = $result_bireysel->fetch_assoc();
        return (bool) $yetki[$yetki_tipi];
    }

    // Override yoksa, rolden gelen yetkiyi kontrol et
    $sql = "SELECT $yetki_tipi FROM modul_yetkileri 
            WHERE rol_id = $rol_id AND modul_adi = '$modul_adi_esc'";
    $result = $baglanti->query($sql);

    if ($result && $result->num_rows > 0) {
        $yetki = $result->fetch_assoc();
        return (bool) $yetki[$yetki_tipi];
    }

    return false;
}

/**
 * Sayfa erişim kontrolü - yetkisizleri bloklar ve çıkış yapar
 * 
 * @param mysqli $baglanti
 */
function sayfaErisimKontrol($baglanti)
{
    if (!modulYetkisiVar($baglanti)) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Yetkisiz Erişim</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
        <div class="text-center">
            <h1 class="display-1 text-danger">🚫</h1>
            <h3 class="text-dark">Yetkisiz Erişim</h3>
            <p class="text-muted">Bu sayfayı görüntüleme yetkiniz bulunmamaktadır.</p>
            <a href="panel.php" class="btn btn-primary mt-3">Ana Panele Dön</a>
        </div></body></html>';
        exit;
    }
}

/**
 * Kullanıcının yazma yetkisi var mı kontrol eder
 * 
 * @param mysqli $baglanti
 * @param string|null $modul_adi Belirtilmezse mevcut sayfadan alınır
 * @return bool
 */
function yazmaYetkisiVar($baglanti, $modul_adi = null)
{
    return modulYetkisiVar($baglanti, $modul_adi, 'yazma');
}

/**
 * Yazma yetkisi yoksa form submit'i engelleyen JavaScript kodu döndürür
 * Bootstrap modal popup ile kullanıcıyı bilgilendirir
 * 
 * @param mysqli $baglanti
 * @param string|null $modul_adi
 * @return string JavaScript kodu (script tag dahil)
 */
function yazmaYetkisiKontrolJS($baglanti, $modul_adi = null)
{
    // Patron her zaman yazabilir
    if (($_SESSION['rol_adi'] ?? '') === 'Patron') {
        return '';
    }

    // Yazma yetkisi varsa script ekleme
    if (yazmaYetkisiVar($baglanti, $modul_adi)) {
        return '';
    }

    // Yazma yetkisi yoksa modal ve form engelleme scripti döndür
    return '
    <!-- Yazma Yetkisi Uyarı Modal -->
    <div class="modal fade" id="yazmaYetkisiModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Yetkisiz İşlem</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-dark">Yazma Yetkiniz Bulunmuyor</h4>
                    <p class="text-muted mb-0">Bu sayfada işlem yapma yetkiniz yok.</p>
                    <p class="text-muted">Yetki almak için sistem yöneticinize başvurun.</p>
                    <div class="alert alert-light border mt-3 mb-0">
                        <small><i class="fas fa-info-circle text-primary me-1"></i> 
                        Mevcut rolünüz: <strong>' . htmlspecialchars($_SESSION['rol_adi'] ?? 'Bilinmiyor') . '</strong></small>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Tüm formların submit olayını yakala
        document.querySelectorAll("form").forEach(function(form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Bootstrap modal göster
                var modal = new bootstrap.Modal(document.getElementById("yazmaYetkisiModal"));
                modal.show();
                
                return false;
            });
        });
        
        // Submit butonlarına da tıklama engeli ekle
        document.querySelectorAll("button[type=submit], input[type=submit]").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var modal = new bootstrap.Modal(document.getElementById("yazmaYetkisiModal"));
                modal.show();
                
                return false;
            });
        });
    });
    </script>';
}

/**
 * Kullanıcının rolünü döndürür
 *
 * @param mysqli $baglanti
 * @return string|null
 */
function kullaniciRolu($baglanti)
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $user_id = $_SESSION['user_id'];
    $sql = "SELECT r.rol_adi
            FROM users u
            JOIN kullanici_rolleri r ON u.rol_id = r.id
            WHERE u.id = $user_id";
    $result = $baglanti->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return $user['rol_adi'];
    }

    return null;
}

/**
 * Kullanıcının onay yetkisi var mı kontrol eder
 *
 * @param mysqli $baglanti
 * @return bool
 */
function onayYetkisiVar($baglanti)
{
    $rol = kullaniciRolu($baglanti);
    return in_array($rol, ['Patron', 'İdari Satın Alma', 'İdari Sevkiyat']);
}

// ==============================================
// ONAY SİSTEMİ FONKSİYONLARI
// ==============================================

/**
 * Yeni onay kaydı oluşturur
 *
 * @param mysqli $baglanti
 * @param string $islem_tipi
 * @param int $islem_id
 * @param string $islem_aciklama
 * @return bool
 */
function onayOlustur($baglanti, $islem_tipi, $islem_id, $islem_aciklama = '')
{
    $user_id = (int) $_SESSION['user_id'];
    $islem_id = (int) $islem_id;
    $islem_tipi = $baglanti->real_escape_string($islem_tipi);
    $islem_aciklama = $baglanti->real_escape_string($islem_aciklama);

    $sql = "INSERT INTO onay_bekleyenler
            (islem_tipi, islem_id, islem_aciklama, olusturan_user_id)
            VALUES ('$islem_tipi', $islem_id, '$islem_aciklama', $user_id)";

    return $baglanti->query($sql);
}

/**
 * Onay verir
 *
 * @param mysqli $baglanti
 * @param int $onay_id
 * @param bool $onayla true: onayla, false: reddet
 * @param string $red_aciklama
 * @return bool
 */
function onayVer($baglanti, $onay_id, $onayla = true, $red_aciklama = '')
{
    $user_id = (int) $_SESSION['user_id'];
    $onay_id = (int) $onay_id;
    $durum = $onayla ? 'onaylandi' : 'reddedildi';
    $red_aciklama = $baglanti->real_escape_string($red_aciklama);

    $sql = "UPDATE onay_bekleyenler
            SET onay_durum = '$durum',
                onaylayan_user_id = $user_id,
                onay_tarihi = NOW(),
                red_aciklama = '$red_aciklama'
            WHERE id = $onay_id";

    if ($baglanti->query($sql)) {
        // İlgili tablodaki kaydı da güncelle
        $onay_result = $baglanti->query("SELECT * FROM onay_bekleyenler WHERE id = $onay_id");
        if ($onay_result && $onay = $onay_result->fetch_assoc()) {
            $islem_id = (int) $onay['islem_id'];

            if ($onay['islem_tipi'] == 'is_emri') {
                $baglanti->query("UPDATE is_emirleri
                                 SET onay_durum = '$durum',
                                     onaylayan_user_id = $user_id,
                                     onay_tarihi = NOW()
                                 WHERE id = $islem_id");
            }
        }

        return true;
    }

    return false;
}

/**
 * Bekleyen onayları listeler
 *
 * @param mysqli $baglanti
 * @param string|null $islem_tipi
 * @return mysqli_result
 */
function bekleyenOnaylar($baglanti, $islem_tipi = null)
{
    $where = "WHERE onay_durum = 'bekliyor'";
    if ($islem_tipi) {
        $where .= " AND islem_tipi = '$islem_tipi'";
    }

    $sql = "SELECT ob.*, u.kadi as olusturan_kadi
            FROM onay_bekleyenler ob
            JOIN users u ON ob.olusturan_user_id = u.id
            $where
            ORDER BY ob.olusturma_tarihi DESC";

    return $baglanti->query($sql);
}

// ==============================================
// LOG FONKSİYONLARI
// ==============================================

/**
 * İşlem logu kaydeder
 *
 * @param mysqli $baglanti
 * @param string $islem_tipi
 * @param string $islem_tablosu
 * @param int $islem_id
 * @param string $islem_aciklama
 * @param array|null $islem_detay JSON olarak kaydedilecek detaylar
 */
function logKaydet($baglanti, $islem_tipi, $islem_tablosu, $islem_id, $islem_aciklama, $islem_detay = null)
{
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $tarayici = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $detay_json = $islem_detay ? json_encode($islem_detay, JSON_UNESCAPED_UNICODE) : null;
    $detay_json = $baglanti->real_escape_string($detay_json);
    $islem_aciklama = $baglanti->real_escape_string($islem_aciklama);

    $sql = "INSERT INTO islem_loglari
           (user_id, islem_tipi, islem_tablosu, islem_id, islem_aciklama, islem_detay, ip_adresi, tarayici)
            VALUES ($user_id, '$islem_tipi', '$islem_tablosu', $islem_id, '$islem_aciklama', '$detay_json', '$ip_adresi', '$tarayici')";

    $baglanti->query($sql);
}

/**
 * System log kaydeder (islem_gecmisi.php için)
 * LOGIN, LOGOUT dışındaki tüm işlemleri kaydetmek için kullanılır
 *
 * @param mysqli $baglanti
 * @param string $action_type INSERT, UPDATE, DELETE, APPROVAL, REJECT vb.
 * @param string $module Modül/sayfa adı
 * @param string $description İşlem açıklaması
 * @return bool
 */
function systemLogKaydet($baglanti, $action_type, $module, $description)
{
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $description = $baglanti->real_escape_string($description);
    $module = $baglanti->real_escape_string($module);
    $action_type = $baglanti->real_escape_string($action_type);

    $sql = "INSERT INTO system_logs (user_id, action_type, module, description, ip_address)
            VALUES ($user_id, '$action_type', '$module', '$description', '$ip_address')";

    return $baglanti->query($sql);
}

/**
 * Belirli bir işlem için logları getirir
 *
 * @param mysqli $baglanti
 * @param string $islem_tablosu
 * @param int $islem_id
 * @return mysqli_result
 */
function islemLoglariniGetir($baglanti, $islem_tablosu, $islem_id)
{
    $sql = "SELECT l.*, u.kadi
            FROM islem_loglari l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.islem_tablosu = '$islem_tablosu' AND l.islem_id = $islem_id
            ORDER BY l.islem_zamani DESC";

    return $baglanti->query($sql);
}

// ==============================================
// HESAPLAMA FONKSİYONLARI
// ==============================================

/**
 * KG'den M³'e çevirme (buğday için)
 *
 * @param float $kg
 * @param float $yogunluk kg/m³ cinsinden (varsayılan: 780)
 * @return float
 */
function kgToM3($kg, $yogunluk = 780.0)
{
    if ($yogunluk <= 0)
        return 0;
    return round($kg / $yogunluk, 3);
}

/**
 * M³'den KG'ye çevirme
 *
 * @param float $m3
 * @param float $yogunluk kg/m³ cinsinden (varsayılan: 780)
 * @return float
 */
function m3ToKg($m3, $yogunluk = 780.0)
{
    return round($m3 * $yogunluk, 2);
}

/**
 * Randıman hesaplama
 *
 * @param float $giris_kg
 * @param float $cikis_kg
 * @return float Yüzde cinsinden
 */
function randimanHesapla($giris_kg, $cikis_kg)
{
    if ($giris_kg <= 0)
        return 0;
    return round(($cikis_kg / $giris_kg) * 100, 2);
}

/**
 * Fire oranı hesaplama
 *
 * @param float $giris_kg
 * @param float $cikis_kg
 * @return float Yüzde cinsinden
 */
function fireOrani($giris_kg, $cikis_kg)
{
    if ($giris_kg <= 0)
        return 0;
    $fire = $giris_kg - $cikis_kg;
    return round(($fire / $giris_kg) * 100, 2);
}

/**
 * Kütle denkliği kontrol
 *
 * @param mysqli $baglanti
 * @param string $parti_no
 * @return array ['dengeli' => bool, 'mesaj' => string, 'fire_yuzde' => float]
 */
function kuttleDenkligiKontrol($baglanti, $parti_no)
{
    // Hammadde girişi toplamı
    $hammadde_sql = "SELECT SUM(uh.kullanilan_kg) as toplam_girdi
                     FROM uretim_hareketleri uh
                     WHERE uh.parti_no = '$parti_no'";
    $hammadde_result = $baglanti->query($hammadde_sql);
    $toplam_girdi = $hammadde_result ? $hammadde_result->fetch_assoc()['toplam_girdi'] : 0;

    // Üretim çıktıları toplamı (ana + yan ürünler)
    $uretim_sql = "SELECT SUM(uc.miktar_kg) as toplam_cikti
                   FROM uretim_ciktilari uc
                   JOIN uretim_hareketleri uh ON uc.uretim_id = uh.id
                   WHERE uh.parti_no = '$parti_no'";
    $uretim_result = $baglanti->query($uretim_sql);
    $toplam_cikti = $uretim_result ? $uretim_result->fetch_assoc()['toplam_cikti'] : 0;

    $fire_yuzde = fireOrani($toplam_girdi, $toplam_cikti);

    $result = [
        'dengeli' => true,
        'mesaj' => 'Kütle denkliği normal',
        'fire_yuzde' => $fire_yuzde,
        'toplam_girdi' => $toplam_girdi,
        'toplam_cikti' => $toplam_cikti
    ];

    // Fire %5'ten fazlaysa uyarı
    if ($fire_yuzde > 5) {
        $result['dengeli'] = false;
        $result['mesaj'] = "⚠️ UYARI: Fire oranı yüksek! (%{$fire_yuzde})";

    }
    return $result;
}

// ==============================================
// YARDIMCI FONKSİYONLAR
// ==============================================

/**
 * Otomatik parti numarası oluşturur
 *
 * @param string $prefix Ön ek (PRT, HG, AMB, vs.)
 * @return string
 */
function partiNoOlustur($prefix = 'PRT')
{
    return $prefix . '-' . date('ymd-Hi');
}

/**
 * Tarih formatını Türkçe'ye çevirir
 *
 * @param string $tarih
 * @param bool $saat_dahil
 * @return string
 */
function tarihFormat($tarih, $saat_dahil = true)
{
    if (!$tarih)
        return '-';

    $format = $saat_dahil ? 'd.m.Y H:i' : 'd.m.Y';
    return date($format, strtotime($tarih));
}

/**
 * Sayıyı Türk formatında gösterir
 *
 * @param float $sayi
 * @param int $ondalik
 * @return string
 */
function sayiFormat($sayi, $ondalik = 2)
{
    return number_format($sayi, $ondalik, ',', '.');
}

/**
 * Alert mesajı oluşturur
 *
 * @param string $mesaj
 * @param string $tip success, danger, warning, info
 * @return string HTML
 */
function alertMesaj($mesaj, $tip = 'info')
{
    return "<div class='alert alert-$tip alert-dismissible fade show' role='alert'>
                $mesaj
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Silo doluluk rengini döndürür
 *
 * @param float $doluluk_yuzde
 * @return string CSS class
 */
function siloDolulukRenk($doluluk_yuzde)
{
    if ($doluluk_yuzde >= 90)
        return 'bg-danger'; // Kırmızı
    if ($doluluk_yuzde >= 70)
        return 'bg-warning'; // Sarı
    if ($doluluk_yuzde >= 30)
        return 'bg-success'; // Yeşil
    return 'bg-info'; // Mavi (az dolu)
}

/**
 * Silo kısıtlama kontrolü - Yanlış hammadde giremez
 *
 * @param mysqli $baglanti
 * @param int $silo_id
 * @param string $hammadde_kodu
 * @return array ['izinli' => bool, 'mesaj' => string]
 */
function siloKisitlamaKontrol($baglanti, $silo_id, $hammadde_kodu)
{
    $sql = "SELECT izin_verilen_hammadde_kodlari FROM silolar WHERE id = $silo_id";
    $result = $baglanti->query($sql);

    if ($result && $result->num_rows > 0) {
        $silo = $result->fetch_assoc();
        $izinli_kodlar = $silo['izin_verilen_hammadde_kodlari'];

        // JSON decode
        if ($izinli_kodlar) {
            $izinli_array = json_decode($izinli_kodlar, true);

            if (!in_array($hammadde_kodu, $izinli_array)) {
                return [
                    'izinli' => false,
                    'mesaj' => "❌ Bu siloya sadece şu hammaddeler girilebilir: " . implode(', ', $izinli_array)
                ];
            }
        }
    }

    return ['izinli' => true, 'mesaj' => ''];
}

/**
 * E-posta gönderir (PHPMailer kullanarak)
 *
 * @param string $alici_email
 * @param string $konu
 * @param string $mesaj
 * @return bool
 */
function emailGonder($alici_email, $konu, $mesaj)
{
    // TODO: PHPMailer entegrasyonu yapılacak
    // Şimdilik simüle edelim

    // Örnek kullanım:
    // use PHPMailer\PHPMailer\PHPMailer;
    // $mail = new PHPMailer(true);
    // ... SMTP ayarları

    return true; // Geçici olarak true döndür
}

/**
 * QR Kod oluşturur
 *
 * @param string $data
 * @param string $dosya_adi
 * @return string QR kod dosya yolu
 */
function qrKodOlustur($data, $dosya_adi)
{
    // TODO: QR kod kütüphanesi entegrasyonu
    // phpqrcode veya chillerlan/php-qrcode kullanılabilir

    return "qrcodes/$dosya_adi.png"; // Geçici
}

// ==============================================
// SESSION KONTROL
// ==============================================

/**
 * Oturum kontrolü yapar, yoksa login'e yönlendir
 */
function oturumKontrol()
{
    if (!isset($_SESSION["oturum"])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Yetkisiz erişim mesajı
 */
function yetkisizErisim()
{
    return alertMesaj('❌ Bu işlem için yetkiniz yok!', 'danger');
}

// ==============================================
// BİLDİRİM SİSTEMİ FONKSİYONLARI
// ==============================================

/**
 * Yeni bildirim oluşturur
 *
 * @param mysqli $baglanti
 * @param string $bildirim_tipi arac_geldi, numune_alindi, analiz_tamamlandi, onay_bekleniyor, onaylandi, reddedildi
 * @param string $baslik
 * @param string $aciklama
 * @param int|null $hedef_rol_id Belirli bir role gönder (null = herkese)
 * @param int|null $hedef_user_id Belirli bir kullanıcıya gönder
 * @param string|null $referans_tablo İlişkili tablo adı
 * @param int|null $referans_id İlişkili kayıt ID
 * @param string|null $link Tıklandığında gidilecek sayfa
 * @return bool
 */
function bildirimOlustur($baglanti, $bildirim_tipi, $baslik, $aciklama = '', $hedef_rol_id = null, $hedef_user_id = null, $referans_tablo = null, $referans_id = null, $link = null)
{
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $bildirim_tipi = $baglanti->real_escape_string($bildirim_tipi);
    $baslik = $baglanti->real_escape_string($baslik);
    $aciklama = $baglanti->real_escape_string($aciklama);
    $referans_tablo = $referans_tablo ? "'" . $baglanti->real_escape_string($referans_tablo) . "'" : "NULL";
    $referans_id = $referans_id ? (int) $referans_id : "NULL";
    $hedef_rol_id = $hedef_rol_id ? (int) $hedef_rol_id : "NULL";
    $hedef_user_id = $hedef_user_id ? (int) $hedef_user_id : "NULL";
    $link = $link ? "'" . $baglanti->real_escape_string($link) . "'" : "NULL";
    $olusturan = $user_id ? $user_id : "NULL";

    $sql = "INSERT INTO bildirimler 
            (bildirim_tipi, baslik, aciklama, hedef_rol_id, hedef_user_id, referans_tablo, referans_id, link, olusturan_user_id)
            VALUES ('$bildirim_tipi', '$baslik', '$aciklama', $hedef_rol_id, $hedef_user_id, $referans_tablo, $referans_id, $link, $olusturan)";

    return $baglanti->query($sql);
}

/**
 * Kullanıcının okunmamış bildirim sayısını döndürür
 *
 * @param mysqli $baglanti
 * @return int
 */
function bildirimSayisi($baglanti)
{
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }

    $user_id = (int) $_SESSION['user_id'];
    $rol_id = isset($_SESSION['rol_id']) ? (int) $_SESSION['rol_id'] : 0;

    $uye_tarih_sql = "SELECT olusturma_tarihi FROM users WHERE id = $user_id";
    $uye_res = $baglanti->query($uye_tarih_sql);
    $uye_kayit_tarihi = '1970-01-01 00:00:00';
    if ($uye_res && $uye_row = $uye_res->fetch_assoc()) {
        $uye_kayit_tarihi = $uye_row['olusturma_tarihi'] ?: '1970-01-01 00:00:00';
    }

    // Kullanıcıya özel veya rolüne gönderilmiş veya herkese gönderilmiş bildirimleri say
    $sql = "SELECT COUNT(*) as sayi FROM bildirimler b
            LEFT JOIN kullanici_bildirim_durumlari kbd ON b.id = kbd.bildirim_id AND kbd.user_id = $user_id
            WHERE (kbd.okundu IS NULL OR kbd.okundu = 0) 
            AND (kbd.silindi IS NULL OR kbd.silindi = 0)
            AND b.olusturma_tarihi >= '$uye_kayit_tarihi'
            AND (b.hedef_user_id = $user_id 
                 OR b.hedef_rol_id = $rol_id 
                 OR (b.hedef_user_id IS NULL AND b.hedef_rol_id IS NULL))
            AND (b.bildirim_tipi != 'genel' OR b.olusturan_user_id != $user_id OR b.olusturan_user_id IS NULL)";

    $result = $baglanti->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int) $row['sayi'];
    }
    return 0;
}

/**
 * Kullanıcının bildirimlerini listeler
 *
 * @param mysqli $baglanti
 * @param int $limit
 * @param bool $sadece_okunmamis
 * @param string $arama_metni
 * @param string $bildirim_tipi
 * @param string $okundu_filtre
 * @param string $yon 'gelen' veya 'gonderilen'
 * @param string $baslangic_tarihi
 * @param string $bitis_tarihi
 * @return mysqli_result|false
 */
function bildirimleriGetir($baglanti, $limit = 20, $sadece_okunmamis = false, $arama_metni = '', $bildirim_tipi = '', $okundu_filtre = '', $yon = 'gelen', $baslangic_tarihi = '', $bitis_tarihi = '')
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = (int) $_SESSION['user_id'];
    $rol_id = isset($_SESSION['rol_id']) ? (int) $_SESSION['rol_id'] : 0;

    if ($yon === 'gonderilen') {
        $where = ["(b.olusturan_user_id = $user_id)"];
    } else {
        $where = ["(b.hedef_user_id = $user_id OR b.hedef_rol_id = $rol_id OR (b.hedef_user_id IS NULL AND b.hedef_rol_id IS NULL))", "(b.bildirim_tipi != 'genel' OR b.olusturan_user_id != $user_id OR b.olusturan_user_id IS NULL)"];
    }

    $where[] = "(kbd.silindi IS NULL OR kbd.silindi = 0)";

    if ($sadece_okunmamis) {
        $where[] = "(kbd.okundu IS NULL OR kbd.okundu = 0)";
    }

    if ($okundu_filtre === 'okunmamis') {
        $where[] = "(kbd.okundu IS NULL OR kbd.okundu = 0)";
    } elseif ($okundu_filtre === 'okunmus') {
        $where[] = "(kbd.okundu = 1)";
    }

    if (!empty($arama_metni)) {
        $arama_metni = $baglanti->real_escape_string($arama_metni);
        $where[] = "(b.baslik LIKE '%$arama_metni%' OR b.aciklama LIKE '%$arama_metni%' OR u.kadi LIKE '%$arama_metni%')";
    }

    if (!empty($baslangic_tarihi)) {
        $bas_tarih = $baglanti->real_escape_string($baslangic_tarihi) . " 00:00:00";
        $where[] = "b.olusturma_tarihi >= '$bas_tarih'";
    }

    if (!empty($bitis_tarihi)) {
        $bit_tarih = $baglanti->real_escape_string($bitis_tarihi) . " 23:59:59";
        $where[] = "b.olusturma_tarihi <= '$bit_tarih'";
    }

    if (!empty($bildirim_tipi)) {
        $bildirim_tipi = $baglanti->real_escape_string($bildirim_tipi);
        $where[] = "b.bildirim_tipi = '$bildirim_tipi'";
    }

    $where_str = implode(" AND ", $where);

    $uye_tarih_sql = "SELECT olusturma_tarihi FROM users WHERE id = $user_id";
    $uye_res = $baglanti->query($uye_tarih_sql);
    $uye_kayit_tarihi = '1970-01-01 00:00:00';
    if ($uye_res && $uye_row = $uye_res->fetch_assoc()) {
        $uye_kayit_tarihi = $uye_row['olusturma_tarihi'] ?: '1970-01-01 00:00:00';
    }

    $sql = "SELECT b.*, COALESCE(kbd.okundu, 0) as okundu_durum, u.kadi as olusturan_kadi 
            FROM bildirimler b
            LEFT JOIN kullanici_bildirim_durumlari kbd ON b.id = kbd.bildirim_id AND kbd.user_id = $user_id
            LEFT JOIN users u ON b.olusturan_user_id = u.id
            WHERE $where_str
            AND b.olusturma_tarihi >= '$uye_kayit_tarihi'
            ORDER BY b.olusturma_tarihi DESC
            LIMIT $limit";

    return $baglanti->query($sql);
}

/**
 * Bildirimi okundu olarak işaretle
 *
 * @param mysqli $baglanti
 * @param int $bildirim_id
 * @return bool
 */
function bildirimOkundu($baglanti, $bildirim_id)
{
    if (!isset($_SESSION['user_id']))
        return false;
    $user_id = (int) $_SESSION['user_id'];
    $bildirim_id = (int) $bildirim_id;

    $sql = "INSERT INTO kullanici_bildirim_durumlari (user_id, bildirim_id, okundu) 
            VALUES ($user_id, $bildirim_id, 1) 
            ON DUPLICATE KEY UPDATE okundu = 1";
    return $baglanti->query($sql);
}

/**
 * Tüm bildirimleri okundu olarak işaretle
 *
 * @param mysqli $baglanti
 * @param string $yon 'gelen' veya 'gonderilen'
 * @return bool
 */
function tumBildirimleriOkundu($baglanti, $yon = 'gelen')
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = (int) $_SESSION['user_id'];
    $rol_id = isset($_SESSION['rol_id']) ? (int) $_SESSION['rol_id'] : 0;

    $uye_tarih_sql = "SELECT olusturma_tarihi FROM users WHERE id = $user_id";
    $uye_res = $baglanti->query($uye_tarih_sql);
    $uye_kayit_tarihi = '1970-01-01 00:00:00';
    if ($uye_res && $uye_row = $uye_res->fetch_assoc()) {
        $uye_kayit_tarihi = $uye_row['olusturma_tarihi'] ?: '1970-01-01 00:00:00';
    }

    if ($yon === 'gonderilen') {
        $hedef_kosul = "(b.olusturan_user_id = $user_id)";
    } else {
        $hedef_kosul = "(b.hedef_user_id = $user_id OR b.hedef_rol_id = $rol_id OR (b.hedef_user_id IS NULL AND b.hedef_rol_id IS NULL)) AND (b.bildirim_tipi != 'genel' OR b.olusturan_user_id != $user_id OR b.olusturan_user_id IS NULL)";
    }

    $sql = "SELECT b.id FROM bildirimler b
            LEFT JOIN kullanici_bildirim_durumlari kbd ON b.id = kbd.bildirim_id AND kbd.user_id = $user_id
            WHERE (kbd.okundu IS NULL OR kbd.okundu = 0) 
            AND (kbd.silindi IS NULL OR kbd.silindi = 0)
            AND b.olusturma_tarihi >= '$uye_kayit_tarihi'
            AND $hedef_kosul";

    $result = $baglanti->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            bildirimOkundu($baglanti, $row['id']);
        }
    }

    return true;
}

/**
 * Bildirimi silindi olarak işaretle
 *
 * @param mysqli $baglanti
 * @param int $bildirim_id
 * @return bool
 */
function bildirimSil($baglanti, $bildirim_id)
{
    if (!isset($_SESSION['user_id']))
        return false;
    $user_id = (int) $_SESSION['user_id'];
    $bildirim_id = (int) $bildirim_id;

    $sql = "INSERT INTO kullanici_bildirim_durumlari (user_id, bildirim_id, silindi) 
            VALUES ($user_id, $bildirim_id, 1) 
            ON DUPLICATE KEY UPDATE silindi = 1";
    return $baglanti->query($sql);
}

/**
 * Filtreye göre tüm bildirimleri temizle (kullanıcı bazlı)
 *
 * @param mysqli $baglanti
 * @param string $arama_metni
 * @param string $bildirim_tipi
 * @param string $okundu_filtre
 * @param string $baslangic_tarihi
 * @param string $bitis_tarihi
 * @return bool
 */
function tumBildirimleriTemizle($baglanti, $arama_metni = '', $bildirim_tipi = '', $okundu_filtre = '', $yon = 'gelen', $baslangic_tarihi = '', $bitis_tarihi = '')
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = (int) $_SESSION['user_id'];
    $rol_id = isset($_SESSION['rol_id']) ? (int) $_SESSION['rol_id'] : 0;

    $uye_tarih_sql = "SELECT olusturma_tarihi FROM users WHERE id = $user_id";
    $uye_res = $baglanti->query($uye_tarih_sql);
    $uye_kayit_tarihi = '1970-01-01 00:00:00';
    if ($uye_res && $uye_row = $uye_res->fetch_assoc()) {
        $uye_kayit_tarihi = $uye_row['olusturma_tarihi'] ?: '1970-01-01 00:00:00';
    }

    if ($yon === 'gonderilen') {
        $where = ["(b.olusturan_user_id = $user_id)"];
    } else {
        $where = ["(b.hedef_user_id = $user_id OR b.hedef_rol_id = $rol_id OR (b.hedef_user_id IS NULL AND b.hedef_rol_id IS NULL))", "(b.bildirim_tipi != 'genel' OR b.olusturan_user_id != $user_id OR b.olusturan_user_id IS NULL)"];
    }

    $where[] = "(kbd.silindi IS NULL OR kbd.silindi = 0)";

    $where[] = "b.olusturma_tarihi >= '$uye_kayit_tarihi'";

    if ($okundu_filtre === 'okunmamis') {
        $where[] = "(kbd.okundu IS NULL OR kbd.okundu = 0)";
    } elseif ($okundu_filtre === 'okunmus') {
        $where[] = "(kbd.okundu = 1)";
    }

    if (!empty($arama_metni)) {
        $arama_metni = $baglanti->real_escape_string($arama_metni);
        $where[] = "(b.baslik LIKE '%$arama_metni%' OR b.aciklama LIKE '%$arama_metni%' OR u.kadi LIKE '%$arama_metni%')";
    }

    if (!empty($baslangic_tarihi)) {
        $bas_tarih = $baglanti->real_escape_string($baslangic_tarihi) . " 00:00:00";
        $where[] = "b.olusturma_tarihi >= '$bas_tarih'";
    }

    if (!empty($bitis_tarihi)) {
        $bit_tarih = $baglanti->real_escape_string($bitis_tarihi) . " 23:59:59";
        $where[] = "b.olusturma_tarihi <= '$bit_tarih'";
    }

    if (!empty($bildirim_tipi)) {
        $bildirim_tipi = $baglanti->real_escape_string($bildirim_tipi);
        $where[] = "b.bildirim_tipi = '$bildirim_tipi'";
    }

    $where_str = implode(" AND ", $where);

    $sql = "SELECT b.id FROM bildirimler b
            LEFT JOIN kullanici_bildirim_durumlari kbd ON b.id = kbd.bildirim_id AND kbd.user_id = $user_id
            LEFT JOIN users u ON b.olusturan_user_id = u.id
            WHERE $where_str";

    $result = $baglanti->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            bildirimSil($baglanti, $row['id']);
        }
    }

    return true;
}

// ==============================================
// HAMMADDE KABUL AKIŞ FONKSİYONLARI
// ==============================================

/**
 * Hammadde girişi için akış kaydı oluşturur
 *
 * @param mysqli $baglanti
 * @param int $hammadde_giris_id
 * @return int|false Oluşturulan akış ID veya false
 */
function akisOlustur($baglanti, $hammadde_giris_id)
{
    $hammadde_giris_id = (int) $hammadde_giris_id;

    // Önce var mı kontrol et
    $check = $baglanti->query("SELECT id FROM hammadde_kabul_akisi WHERE hammadde_giris_id = $hammadde_giris_id");
    if ($check && $check->num_rows > 0) {
        return $check->fetch_assoc()['id'];
    }

    $sql = "INSERT INTO hammadde_kabul_akisi (hammadde_giris_id, asama) VALUES ($hammadde_giris_id, 'bekliyor')";

    if ($baglanti->query($sql)) {
        $akis_id = $baglanti->insert_id;

        // Geçmiş kaydı oluştur
        akisGecmisKaydet($baglanti, $akis_id, null, 'bekliyor', 'Araç geldi, kayıt oluşturuldu');

        return $akis_id;
    }
    return false;
}

/**
 * Akış aşamasını günceller
 *
 * @param mysqli $baglanti
 * @param int $akis_id
 * @param string $yeni_asama
 * @param string $aciklama
 * @return bool
 */
function akisGuncelle($baglanti, $akis_id, $yeni_asama, $aciklama = '')
{
    $akis_id = (int) $akis_id;
    $yeni_asama = $baglanti->real_escape_string($yeni_asama);

    // Mevcut aşamayı al
    $mevcut = $baglanti->query("SELECT asama FROM hammadde_kabul_akisi WHERE id = $akis_id");
    $onceki_asama = null;
    if ($mevcut && $row = $mevcut->fetch_assoc()) {
        $onceki_asama = $row['asama'];
    }

    // Aşamayı güncelle
    $sql = "UPDATE hammadde_kabul_akisi SET asama = '$yeni_asama' WHERE id = $akis_id";

    if ($baglanti->query($sql)) {
        // Geçmiş kaydı oluştur
        akisGecmisKaydet($baglanti, $akis_id, $onceki_asama, $yeni_asama, $aciklama);
        return true;
    }
    return false;
}

/**
 * Akış geçmişi kaydeder
 *
 * @param mysqli $baglanti
 * @param int $akis_id
 * @param string|null $onceki_asama
 * @param string $yeni_asama
 * @param string $aciklama
 * @return bool
 */
function akisGecmisKaydet($baglanti, $akis_id, $onceki_asama, $yeni_asama, $aciklama = '')
{
    $akis_id = (int) $akis_id;
    $onceki_asama = $onceki_asama ? "'" . $baglanti->real_escape_string($onceki_asama) . "'" : "NULL";
    $yeni_asama = $baglanti->real_escape_string($yeni_asama);
    $aciklama = $baglanti->real_escape_string($aciklama);
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : "NULL";

    $sql = "INSERT INTO hammadde_kabul_gecmisi (akis_id, onceki_asama, yeni_asama, islem_yapan_user_id, aciklama)
            VALUES ($akis_id, $onceki_asama, '$yeni_asama', $user_id, '$aciklama')";

    return $baglanti->query($sql);
}

/**
 * Hammadde girişi için akış bilgisini getirir
 *
 * @param mysqli $baglanti
 * @param int $hammadde_giris_id
 * @return array|null
 */
function akisBilgisiGetir($baglanti, $hammadde_giris_id)
{
    $hammadde_giris_id = (int) $hammadde_giris_id;
    $sql = "SELECT * FROM hammadde_kabul_akisi WHERE hammadde_giris_id = $hammadde_giris_id";
    $result = $baglanti->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Bildirim tipi için ikon döndürür
 *
 * @param string $tip
 * @return string
 */
function bildirimIkon($tip)
{
    $ikonlar = [
        'arac_geldi' => 'fa-truck text-primary',
        'numune_alindi' => 'fa-vial text-info',
        'analiz_tamamlandi' => 'fa-flask text-success',
        'onay_bekleniyor' => 'fa-clock text-warning',
        'onaylandi' => 'fa-check-circle text-success',
        'reddedildi' => 'fa-times-circle text-danger',
        'kantar_bekleniyor' => 'fa-weight text-secondary',
        'silo_duzeltme_talebi' => 'fa-flag text-danger',
        'silo_duzeltme_onay' => 'fa-rotate-left text-success',
        'silo_duzeltme_red' => 'fa-rotate-left text-danger',
        'genel' => 'fa-bell text-primary'
    ];
    return $ikonlar[$tip] ?? $ikonlar['genel'];
}

/**
 * Akış aşaması için Türkçe etiket döndürür
 *
 * @param string $asama
 * @return string
 */
function asamaEtiket($asama)
{
    $etiketler = [
        'bekliyor' => 'Araç Bekliyor',
        'numune_alindi' => 'Numune Alındı',
        'analiz_yapildi' => 'Analiz Yapıldı',
        'onay_bekleniyor' => 'Onay Bekleniyor',
        'onaylandi' => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'kantar' => 'Kantar Tartıldı',
        'satina_bekliyor' => 'Satın Alma Bekliyor',
        'tamamlandi' => 'Tamamlandı'
    ];
    return $etiketler[$asama] ?? $asama;
}

/**
 * Akış aşaması için renk döndürür
 *
 * @param string $asama
 * @return string Bootstrap renk sınıfı
 */
function asamaRenk($asama)
{
    $renkler = [
        'bekliyor' => 'secondary',
        'numune_alindi' => 'info',
        'analiz_yapildi' => 'primary',
        'onay_bekleniyor' => 'warning',
        'onaylandi' => 'success',
        'reddedildi' => 'danger',
        'kantar' => 'dark',
        'satina_bekliyor' => 'info',
        'tamamlandi' => 'success'
    ];
    return $renkler[$asama] ?? 'secondary';
}

/**
 * Makine bakım tarihlerini kontrol eder ve yaklaşan/gecikmiş bakımlar için bildirim oluşturur.
 *
 * @param mysqli $baglanti
 * @return void
 */
function bakimBildirimleriniKontrolEt($baglanti)
{
    $bugun = date('Y-m-d');
    $bir_hafta_sonra = date('Y-m-d', strtotime('+7 days'));

    // Aktif makineleri çek
    $sql = "SELECT id, makine_adi, makine_kodu, sonraki_bakim_tarihi FROM makineler WHERE aktif = 1 AND sonraki_bakim_tarihi IS NOT NULL";
    $result = $baglanti->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($m = $result->fetch_assoc()) {
            $makine_id = $m['id'];
            $makine_adi = $m['makine_adi'];
            $makine_kodu = $m['makine_kodu'];
            $sonraki_bakim = $m['sonraki_bakim_tarihi'];

            $bildirim_tipi = "";
            $baslik = "";
            $aciklama = "";

            if ($sonraki_bakim < $bugun) {
                // Bakım gecikmiş
                $bildirim_tipi = "bakim_gecikti";
                $baslik = "⚠️ Bakım Gecikti: $makine_adi";
                $aciklama = "$makine_kodu kodlu makinenin bakım tarihi " . date('d.m.Y', strtotime($sonraki_bakim)) . " idi. Lütfen kontrol edin.";
            } elseif ($sonraki_bakim == $bugun) {
                // Bakım günü
                $bildirim_tipi = "bakim_gunu";
                $baslik = "🔧 Bakım Günü: $makine_adi";
                $aciklama = "$makine_kodu kodlu makinenin periyodik bakım günü bugün.";
            } elseif ($sonraki_bakim <= $bir_hafta_sonra) {
                // Bakıma 1 hafta kala
                $bildirim_tipi = "bakim_yaklasiyor";
                $baslik = "📅 Bakım Yaklaşıyor: $makine_adi";
                $aciklama = "$makine_kodu kodlu makinenin bakımına 1 haftadan az süre kaldı. " . date('d.m.Y', strtotime($sonraki_bakim));
            }

            if ($bildirim_tipi != "") {
                // Mükerrer bildirim kontrolü: Aynı makine için aynı tipte okunmamış bildirim var mı?
                $check_sql = "SELECT id FROM bildirimler 
                             WHERE okundu = 0 
                             AND bildirim_tipi = '$bildirim_tipi' 
                             AND referans_tablo = 'makineler' 
                             AND referans_id = $makine_id";
                $check_result = $baglanti->query($check_sql);

                if ($check_result && $check_result->num_rows == 0) {
                    // Bildirim oluştur (Patron ve Otomasyon Sorumlusu rollerine)
                    // Rol ID'leri: Patron (1), Otomasyon Sorumlusu (4)
                    bildirimOlustur($baglanti, $bildirim_tipi, $baslik, $aciklama, 1, null, 'makineler', $makine_id, 'bakim.php');
                    bildirimOlustur($baglanti, $bildirim_tipi, $baslik, $aciklama, 4, null, 'makineler', $makine_id, 'bakim.php');
                }
            }
        }
    }
}

/**
 * Silodan belirtilen miktarı FIFO kuralına göre düşer ve fire hesaplayarak veritabanına loglar.
 *
 * @param mysqli $baglanti
 * @param int $silo_id
 * @param float $dusulecek_kg
 * @param string $uretim_parti_no
 * @param float $elek_alti_yuzde
 * @return array
 */
function fifoDusumYap($baglanti, $silo_id, $dusulecek_kg, $uretim_parti_no, $elek_alti_yuzde)
{
    // 1. İlgili silonun 'aktif' olan partilerini FIFO'ya (giris_tarihi ASC) göre al
    $sql = "SELECT * FROM silo_stok_detay WHERE silo_id = $silo_id AND durum = 'aktif' ORDER BY giris_tarihi ASC";
    $result = $baglanti->query($sql);

    $kalan_ihtiyac = $dusulecek_kg;
    $elek_alti_toplam = 0;

    $islem_yapan = isset($_SESSION["kadi"]) ? $baglanti->real_escape_string($_SESSION["kadi"]) : 'Sistem';

    while ($row = $result->fetch_assoc()) {
        if ($kalan_ihtiyac <= 0)
            break;

        $parti_id = $row['id'];
        $mevcut_stok = (float) $row['kalan_miktar_kg'];
        $kaynak_parti_kodu = $baglanti->real_escape_string($row['parti_kodu']);

        if ($mevcut_stok <= $kalan_ihtiyac) {
            // Bu parti tamamen tükeniyor
            $dusulen = $mevcut_stok;
            $kalan_ihtiyac -= $dusulen;

            $baglanti->query("UPDATE silo_stok_detay SET kalan_miktar_kg = 0, durum = 'tükendi' WHERE id = $parti_id");

        } else {
            // Bu parti ihtiyacı tam karşılıyor ve artıyor
            $dusulen = $kalan_ihtiyac;
            $yeni_kalan = $mevcut_stok - $dusulen;
            $kalan_ihtiyac = 0;

            $baglanti->query("UPDATE silo_stok_detay SET kalan_miktar_kg = $yeni_kalan WHERE id = $parti_id");
        }

        // Log Tablosuna Yaz (uretim_silo_cikis_log)
        $elek_alti_fire_kg = ($dusulen * $elek_alti_yuzde) / 100;
        $elek_alti_toplam += $elek_alti_fire_kg;

        $baglanti->query("INSERT INTO uretim_silo_cikis_log 
            (uretim_parti_no, silo_id, kaynak_parti_kodu, cikis_miktari_brut_kg, elek_alti_fire_kg, kullanici) 
            VALUES 
            ('$uretim_parti_no', $silo_id, '$kaynak_parti_kodu', $dusulen, $elek_alti_fire_kg, '$islem_yapan')");
    }

    return [
        'dusulemeyen_kg' => $kalan_ihtiyac,
        'elek_alti_toplam_kg' => $elek_alti_toplam
    ];
}

?>
