<?php
// Hata Raporlama (Geliştirme aşamasında açık, canlıda loglanmalı)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

// Oturum Kontrolü
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

// --- YARDIMCI FONKSİYONLAR ---

if (!function_exists('sayiFormat')) {
    function sayiFormat($sayi, $ondalik = 2)
    {
        return number_format((float) $sayi, $ondalik, ',', '.');
    }
}

if (!function_exists('siloDolulukRenk')) {
    function siloDolulukRenk($yuzde)
    {
        if ($yuzde > 90)
            return 'bg-danger';
        if ($yuzde > 75)
            return 'bg-warning';
        if ($yuzde > 50)
            return 'bg-primary';
        return 'bg-success';
    }
}

if (!function_exists('alertMesaj')) {
    function alertMesaj($mesaj, $tip = 'info')
    {
        return "<div class='alert alert-$tip alert-dismissible fade show' role='alert'>
                    $mesaj
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
}

if (!function_exists('normalizeKodList')) {
    function normalizeKodList($kodlar)
    {
        if (!is_array($kodlar)) {
            return [];
        }

        $temiz = [];
        foreach ($kodlar as $kod) {
            $kod = trim((string) $kod);
            if ($kod !== '') {
                $temiz[] = $kod;
            }
        }

        return array_values(array_unique($temiz));
    }
}

if (!function_exists('silolariDogalSirala')) {
    function silolariDogalSirala($sonuc)
    {
        $satirlar = [];
        if ($sonuc instanceof mysqli_result) {
            while ($satir = $sonuc->fetch_assoc()) {
                $satirlar[] = $satir;
            }
        }

        usort($satirlar, static function ($a, $b) {
            return strnatcasecmp((string) ($a['silo_adi'] ?? ''), (string) ($b['silo_adi'] ?? ''));
        });

        return $satirlar;
    }
}

// --- ANA İŞLEMLER ---

$mesaj = "";
$hata = "";

// 1. SİLO EKLEME
if (isset($_POST['silo_ekle'])) {
    $adi = $baglanti->real_escape_string($_POST['silo_adi'] ?? '');
    $tip = $baglanti->real_escape_string($_POST['tip'] ?? 'bugday');
    $kapasite = (float) ($_POST['kapasite_m3'] ?? 0);
    $plc_ip = $baglanti->real_escape_string($_POST['plc_ip_adresi'] ?? '');
    $izinli_kodlar = normalizeKodList($_POST['yeni_izinli_kodlar'] ?? []);

    if ($kapasite <= 0)
        $kapasite = 10;

    $tip_izinli = ['bugday', 'un', 'tav', 'kepek'];
    if (!in_array($tip, $tip_izinli, true)) {
        $tip = 'bugday';
    }

    if ($adi) {
        $izinli_sql = "NULL";
        if (!empty($izinli_kodlar)) {
            $izinli_json = $baglanti->real_escape_string(json_encode($izinli_kodlar, JSON_UNESCAPED_UNICODE));
            $izinli_sql = "'$izinli_json'";
        }

        $sql = "INSERT INTO silolar (silo_adi, tip, kapasite_m3, durum, doluluk_m3, aktif_hammadde_kodu, izin_verilen_hammadde_kodlari, plc_ip_adresi) 
                VALUES ('$adi', '$tip', $kapasite, 'aktif', 0, NULL, $izinli_sql, " . ($plc_ip === "" ? "NULL" : "'$plc_ip'") . ")";

        if ($baglanti->query($sql)) {
            $mesaj = "✅ Silo eklendi: $adi";
            $yeni_silo_id = (int) $baglanti->insert_id;
            $izinli_log = !empty($izinli_kodlar) ? implode(',', $izinli_kodlar) : '-';
            $plc_ip_log = ($plc_ip !== '') ? $plc_ip : '-';
            $aciklama = "Silo eklendi: $adi (ID: $yeni_silo_id) | Tip: $tip | Kapasite: " . sayiFormat($kapasite, 2) . " m3 | PLC IP: $plc_ip_log | İzinli Kodlar: $izinli_log";
            systemLogKaydet($baglanti, 'INSERT', 'Silo Yönetimi', $aciklama);
        } else {
            $hata = "Ekleme hatası: " . $baglanti->error;
        }
    } else {
        $hata = "Silo adı boş olamaz.";
    }
}

// 2. SİLO SİLME
if (isset($_POST['silo_sil'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    $silo_adi_log = 'Bilinmeyen Silo';
    $silo_tip_log = '-';

    if ($id > 0) {
        $silo_bilgi_res = $baglanti->query("SELECT silo_adi, tip FROM silolar WHERE id=$id LIMIT 1");
        if ($silo_bilgi_res && $silo_bilgi_res->num_rows > 0) {
            $silo_bilgi = $silo_bilgi_res->fetch_assoc();
            $silo_adi_log = $silo_bilgi['silo_adi'] ?? $silo_adi_log;
            $silo_tip_log = $silo_bilgi['tip'] ?? $silo_tip_log;
        }
    }

    if ($id > 0 && $baglanti->query("DELETE FROM silolar WHERE id=$id")) {
        $mesaj = "🗑️ Silo silindi.";
        $aciklama = "Silo silindi: $silo_adi_log (ID: $id) | Tip: $silo_tip_log";
        systemLogKaydet($baglanti, 'DELETE', 'Silo Yönetimi', $aciklama);
    } else {
        $hata = "Silinirken hata oluştu.";
    }
}

// 3. SİLO GÜNCELLEME
if (isset($_POST['silo_guncelle'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    $adi = $baglanti->real_escape_string($_POST['silo_adi'] ?? '');
    $kapasite = (float) ($_POST['kapasite_m3'] ?? 0);
    $plc_ip = $baglanti->real_escape_string($_POST['plc_ip_adresi'] ?? '');
    $durum = $baglanti->real_escape_string($_POST['durum'] ?? 'aktif');
    $plc_ip = $baglanti->real_escape_string($_POST['plc_ip_adresi'] ?? '');
    $durum_izinli = ['aktif', 'bakim', 'temizlik'];
    if (!in_array($durum, $durum_izinli, true)) {
        $durum = 'aktif';
    }

    // Warning Hatası Çözümü: Null coalescing operator (??) kullanarak boş gelirse boş string ata
    $raw_aktif = trim((string) ($_POST['aktif_hammadde_kodu'] ?? ''));
    $aktif_kod = $baglanti->real_escape_string($raw_aktif);

    // Eğer 'Boş' seçildiyse (value="") veritabanına NULL veya boş string kaydet
    if ($aktif_kod === '')
        $aktif_kod_sql = "NULL";
    else
        $aktif_kod_sql = "'$aktif_kod'";

    // JSON Verisi (Checkboxlar gelmemişse null)
    $izinli_kodlar = normalizeKodList($_POST['izinli_kodlar'] ?? []);
    $izinli_sql = "NULL";
    if (!empty($izinli_kodlar)) {
        $izinli_json = $baglanti->real_escape_string(json_encode($izinli_kodlar, JSON_UNESCAPED_UNICODE));
        $izinli_sql = "'$izinli_json'";
    }

    if ($id > 0) {
        if ($kapasite <= 0) {
            $hata = "Kapasite 0'dan buyuk olmali.";
        } elseif ($adi === '') {
            $hata = "Silo adi bos olamaz.";
        } elseif ($raw_aktif !== '' && !empty($izinli_kodlar) && !in_array($raw_aktif, $izinli_kodlar, true)) {
            $hata = "Aktif urun, izin verilen hammadde listesinde olmalidir.";
        }
    }

    if ($id > 0 && $hata === "") {
        // aktif_hammadde_kodu için özel SQL formatı (NULL desteği)
        $sql = "UPDATE silolar SET 
                silo_adi='$adi', 
                kapasite_m3=$kapasite, 
                durum='$durum',
                aktif_hammadde_kodu=$aktif_kod_sql, 
                izin_verilen_hammadde_kodlari=$izinli_sql,
                plc_ip_adresi=" . ($plc_ip === "" ? "NULL" : "'$plc_ip'") . "
                WHERE id=$id";

        if ($baglanti->query($sql)) {
            $mesaj = "✅ Güncellendi.";
            $izinli_log = !empty($izinli_kodlar) ? implode(',', $izinli_kodlar) : '-';
            $aktif_kod_log = ($raw_aktif !== '') ? $raw_aktif : '-';
            $plc_ip_log = ($plc_ip !== '') ? $plc_ip : '-';
            $aciklama = "Silo güncellendi: $adi (ID: $id) | Kapasite: " . sayiFormat($kapasite, 2) . " m3 | Durum: $durum | Aktif Kod: $aktif_kod_log | PLC IP: $plc_ip_log | İzinli Kodlar: $izinli_log";
            systemLogKaydet($baglanti, 'UPDATE', 'Silo Yönetimi', $aciklama);
        } else {
            $hata = "Güncelleme hatası: " . $baglanti->error;
        }
    }
}

// 4. SİLO SIFIRLAMA
if (isset($_POST['silo_sifirla'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    if ($id > 0) {
        $silo_adi_log = "Bilinmeyen Silo";
        $onceki_doluluk_log = 0;
        $onceki_aktif_kod_log = '-';

        $silo_bilgi_res = $baglanti->query("SELECT silo_adi, doluluk_m3, aktif_hammadde_kodu FROM silolar WHERE id=$id LIMIT 1");
        if ($silo_bilgi_res && $silo_bilgi_res->num_rows > 0) {
            $silo_bilgi = $silo_bilgi_res->fetch_assoc();
            $silo_adi_log = $silo_bilgi['silo_adi'] ?? $silo_adi_log;
            $onceki_doluluk_log = (float) ($silo_bilgi['doluluk_m3'] ?? 0);
            $onceki_aktif_kod_log = trim((string) ($silo_bilgi['aktif_hammadde_kodu'] ?? ''));
            if ($onceki_aktif_kod_log === '') {
                $onceki_aktif_kod_log = '-';
            }
        }

        $baglanti->begin_transaction();
        try {
            $fifo_sql = "UPDATE silo_stok_detay 
                         SET kalan_miktar_kg = 0 
                         WHERE silo_id = $id AND kalan_miktar_kg > 0";
            if (!$baglanti->query($fifo_sql)) {
                throw new Exception($baglanti->error);
            }

            $silo_sql = "UPDATE silolar 
                         SET doluluk_m3=0, aktif_hammadde_kodu=NULL, durum='temizlik' 
                         WHERE id=$id";
            if (!$baglanti->query($silo_sql)) {
                throw new Exception($baglanti->error);
            }

            $baglanti->commit();
            $mesaj = "Silo bosaltildi.";
            $aciklama = "Silo boşaltıldı: $silo_adi_log (ID: $id) | Önceki Doluluk: " . sayiFormat($onceki_doluluk_log, 3) . " m3 | Önceki Aktif Kod: $onceki_aktif_kod_log";
            systemLogKaydet($baglanti, 'UPDATE', 'Silo Yönetimi', $aciklama);
        } catch (Throwable $e) {
            $baglanti->rollback();
            $hata = "Silo bosaltma hatasi: " . $e->getMessage();
        }
    }
}

// 5. MANUEL STOK VE HEKTOLİTRE BELİRLEME
if (isset($_POST['silo_manuel_belirle'])) {
    $id        = (int)($_POST['silo_id'] ?? 0);
    $yeni_kg   = (float)($_POST['yeni_kg'] ?? 0);
    $yeni_hek  = (float)($_POST['yeni_hek'] ?? 0);

    if ($id > 0 && $yeni_kg > 0 && $yeni_hek > 0) {
        $silo_adi_log = "Bilinmeyen Silo";
        $onceki_doluluk_log = 0;
        $onceki_aktif_kod_log = '-';

        $silo_bilgi_res = $baglanti->query("SELECT silo_adi, doluluk_m3, aktif_hammadde_kodu FROM silolar WHERE id=$id LIMIT 1");
        if ($silo_bilgi_res && $silo_bilgi_res->num_rows > 0) {
            $silo_bilgi = $silo_bilgi_res->fetch_assoc();
            $silo_adi_log = $silo_bilgi['silo_adi'] ?? $silo_adi_log;
            $onceki_doluluk_log = (float) ($silo_bilgi['doluluk_m3'] ?? 0);
            $onceki_aktif_kod_log = trim((string) ($silo_bilgi['aktif_hammadde_kodu'] ?? ''));
            if ($onceki_aktif_kod_log === '') {
                $onceki_aktif_kod_log = '-';
            }
        }

        $baglanti->begin_transaction();
        try {
            // Hangi ürün var siloda? (Eğer POST ile gönderildiyse onu kullan, yoksa silonun aktif kodunu kullan)
            if (!empty($_POST['aktif_hammadde_kodu'])) {
                $aktif_hammadde_kodu = $baglanti->real_escape_string($_POST['aktif_hammadde_kodu']);
                // Silonun aktif kodunu da doğrudan güncelle ki ileride sorun çıkmasın
                $baglanti->query("UPDATE silolar SET aktif_hammadde_kodu='$aktif_hammadde_kodu' WHERE id=$id");
            } else {
                $s_q = $baglanti->query("SELECT aktif_hammadde_kodu FROM silolar WHERE id=$id");
                $aktif_hammadde_kodu = $s_q->fetch_assoc()['aktif_hammadde_kodu'] ?? 'Bilinmeyen';
            }

            // 1) Mevcut stokları "tükendi" ve miktar 0 yap. (Siloyu sıfırla)
            $baglanti->query("UPDATE silo_stok_detay SET kalan_miktar_kg=0, durum='tükendi' WHERE silo_id=$id AND kalan_miktar_kg>0");

            // 2) Yeni özel parti kodu oluştur
            $custom_parti_no = 'MAN-S' . $id . '-' . date('YmdHi');

            // 3) Yeni Stok kaydı gir
            $ins_stok = "INSERT INTO silo_stok_detay (silo_id, parti_kodu, hammadde_turu, giren_miktar_kg, kalan_miktar_kg, giris_tarihi, durum)
                         VALUES ($id, '$custom_parti_no', '$aktif_hammadde_kodu', $yeni_kg, $yeni_kg, NOW(), 'aktif')";
            if (!$baglanti->query($ins_stok)) throw new Exception($baglanti->error);

            // 4) Hektolitre için fake lab analizi kaydı gir (sadece bu siloyu etkiler)
            $ins_lab = "INSERT INTO lab_analizleri (parti_no, hektolitre, tarih) VALUES ('$custom_parti_no', $yeni_hek, NOW())";
            if (!$baglanti->query($ins_lab)) throw new Exception($baglanti->error);

            // 5) doluluk_m3 yeniden hesapla (Girilen hektolitre oradan m3 hesabı)
            $yeni_yogunluk = $yeni_hek * 10;
            if ($yeni_yogunluk <= 0) {
                $yogunluk_sql = "SELECT COALESCE(yogunluk_kg_m3, 780) AS yog FROM hammaddeler WHERE hammadde_kodu = '$aktif_hammadde_kodu'";
                $yog_res = $baglanti->query($yogunluk_sql);
                $yeni_yogunluk = ($yog_res && $yog_res->num_rows > 0) ? (float)($yog_res->fetch_assoc()['yog']) : 780;
            }
            if ($yeni_yogunluk <= 0) $yeni_yogunluk = 780;

            $yeni_doluluk_m3 = $yeni_kg / $yeni_yogunluk;
            $upd_silo = "UPDATE silolar SET doluluk_m3=$yeni_doluluk_m3 WHERE id=$id";
            if (!$baglanti->query($upd_silo)) throw new Exception($baglanti->error);

            $baglanti->commit();
            $mesaj = "✅ Silo değeri manuel olarak " . number_format($yeni_kg, 0, ',', '.') . " kg ve $yeni_hek Hektolitre yapıldı.";
            $aktif_kod_log = trim((string) ($aktif_hammadde_kodu ?? ''));
            if ($aktif_kod_log === '') {
                $aktif_kod_log = '-';
            }
            $aciklama = "Silo seviyesi manuel belirlendi: $silo_adi_log (ID: $id) | Yeni Miktar: " . sayiFormat($yeni_kg, 0) . " kg | Yeni Hektolitre: " . sayiFormat($yeni_hek, 2) . " | Yeni Doluluk: " . sayiFormat($yeni_doluluk_m3, 3) . " m3 | Aktif Kod: $aktif_kod_log | Önceki Doluluk: " . sayiFormat($onceki_doluluk_log, 3) . " m3 | Önceki Aktif Kod: $onceki_aktif_kod_log | Parti: $custom_parti_no";
            systemLogKaydet($baglanti, 'UPDATE', 'Silo Yönetimi', $aciklama);
        } catch (Throwable $e) {
            $baglanti->rollback();
            $hata = "Manuel belirleme hatası: " . $e->getMessage();
        }
    } else {
        $hata = "Lütfen geçerli silo, kg ve hektolitre değeri giriniz.";
    }
}

// SİLO VERİLERİNİ ÇEKME
$silolar_bugday = silolariDogalSirala($baglanti->query("SELECT * FROM silolar WHERE tip='bugday'"));
$silolar_un = silolariDogalSirala($baglanti->query("SELECT * FROM silolar WHERE tip='un'"));
$silolar_tav = silolariDogalSirala($baglanti->query("SELECT * FROM silolar WHERE tip='tav'"));
$silolar_kepek = silolariDogalSirala($baglanti->query("SELECT * FROM silolar WHERE tip='kepek'"));

// HAMMADDELER
$hammadde_listesi = [];
$hammadde_yogunluk_map = [];
$hm_sql = "SELECT hammadde_kodu, yogunluk_kg_m3 FROM hammaddeler ORDER BY hammadde_kodu";
$h_result = $baglanti->query($hm_sql);

if ($h_result) {
    while ($r = $h_result->fetch_assoc()) {
        $r['hammadde_adi'] = ''; // UI hatası önlemi
        $hammadde_listesi[] = $r;
        $kod = trim((string) ($r['hammadde_kodu'] ?? ''));
        $yogunluk_kg_m3 = (float) ($r['yogunluk_kg_m3'] ?? 0);
        if ($kod !== '' && $yogunluk_kg_m3 > 0) {
            $hammadde_yogunluk_map[$kod] = $yogunluk_kg_m3;
        }
    }
}

$silo_karisim_map = [];
$silo_toplam_kalan_kg = [];
$karisim_sql = "SELECT 
                    ssd.silo_id,
                    ssd.parti_kodu,
                    COALESCE(h.hammadde_kodu, NULLIF(TRIM(ssd.hammadde_turu), ''), 'Bilinmeyen') AS hammadde_kodu,
                    SUM(ssd.kalan_miktar_kg) AS kalan_kg,
                    MAX(COALESCE(la_hg.hektolitre, la_pk.hektolitre)) AS lab_hektolitre,
                    MAX(COALESCE(la_hg.nem, la_pk.nem)) AS lab_nem,
                    MAX(COALESCE(la_hg.protein, la_pk.protein)) AS lab_protein,
                    MAX(COALESCE(la_hg.nisasta, la_pk.nisasta)) AS lab_nisasta,
                    MAX(COALESCE(la_hg.sertlik, la_pk.sertlik)) AS lab_sertlik,
                    MAX(COALESCE(la_hg.gluten, la_pk.gluten)) AS lab_gluten,
                    MAX(COALESCE(la_hg.index_degeri, la_pk.index_degeri)) AS lab_index_degeri,
                    MAX(COALESCE(la_hg.sedimantasyon, la_pk.sedimantasyon)) AS lab_sedimantasyon,
                    MAX(COALESCE(la_hg.gecikmeli_sedimantasyon, la_pk.gecikmeli_sedimantasyon)) AS lab_gecikmeli_sedimantasyon,
                    MAX(COALESCE(la_hg.fn, la_pk.fn)) AS lab_fn,
                    MAX(COALESCE(la_hg.doker_orani, la_pk.doker_orani)) AS lab_doker_orani,
                    MAX(COALESCE(la_hg.laborant, la_pk.laborant)) AS lab_laborant,
                    MAX(COALESCE(la_hg.tarih, la_pk.tarih)) AS lab_tarih
                FROM silo_stok_detay ssd
                LEFT JOIN hammadde_girisleri hg ON hg.parti_no = ssd.parti_kodu
                LEFT JOIN hammaddeler h ON h.id = hg.hammadde_id
                LEFT JOIN lab_analizleri la_hg ON la_hg.id = (
                    SELECT la1.id
                    FROM lab_analizleri la1
                    WHERE la1.hammadde_giris_id = hg.id
                    ORDER BY la1.id DESC
                    LIMIT 1
                )
                LEFT JOIN lab_analizleri la_pk ON la_pk.id = (
                    SELECT la2.id
                    FROM lab_analizleri la2
                    WHERE la2.parti_no = ssd.parti_kodu
                    ORDER BY la2.id DESC
                    LIMIT 1
                )
                WHERE ssd.kalan_miktar_kg > 0
                GROUP BY ssd.silo_id, ssd.parti_kodu, COALESCE(h.hammadde_kodu, NULLIF(TRIM(ssd.hammadde_turu), ''), 'Bilinmeyen')
                ORDER BY ssd.silo_id, MIN(ssd.giris_tarihi) DESC";
$karisim_result = $baglanti->query($karisim_sql);

if ($karisim_result) {
    while ($k = $karisim_result->fetch_assoc()) {
        $silo_id = (int) ($k['silo_id'] ?? 0);
        $hammadde_kodu = trim((string) ($k['hammadde_kodu'] ?? 'Bilinmeyen'));
        $parti_kodu = trim((string) ($k['parti_kodu'] ?? ''));
        $kalan_kg = (float) ($k['kalan_kg'] ?? 0);

        if ($silo_id <= 0 || $kalan_kg <= 0) continue;
        if ($hammadde_kodu === '') $hammadde_kodu = 'Bilinmeyen';

        if (!isset($silo_karisim_map[$silo_id])) $silo_karisim_map[$silo_id] = [];

        $silo_karisim_map[$silo_id][] = [
            'hammadde_kodu' => $hammadde_kodu,
            'parti_kodu' => $parti_kodu,
            'kalan_kg' => $kalan_kg,
            'lab' => [
                'hektolitre' => $k['lab_hektolitre'] ?? null,
                'nem' => $k['lab_nem'] ?? null,
                'protein' => $k['lab_protein'] ?? null,
                'nisasta' => $k['lab_nisasta'] ?? null,
                'sertlik' => $k['lab_sertlik'] ?? null,
                'gluten' => $k['lab_gluten'] ?? null,
                'index_degeri' => $k['lab_index_degeri'] ?? null,
                'sedimantasyon' => $k['lab_sedimantasyon'] ?? null,
                'gecikmeli_sedimantasyon' => $k['lab_gecikmeli_sedimantasyon'] ?? null,
                'fn' => $k['lab_fn'] ?? null,
                'doker_orani' => $k['lab_doker_orani'] ?? null,
                'laborant' => $k['lab_laborant'] ?? null,
                'tarih' => $k['lab_tarih'] ?? null
            ]
        ];
        $silo_toplam_kalan_kg[$silo_id] = ($silo_toplam_kalan_kg[$silo_id] ?? 0) + $kalan_kg;
    }
}

// --- SİLO ÖZET İSTATİSTİKLERİ ---
$silo_ozet_res = $baglanti->query("SELECT 
    COUNT(*) as toplam,
    SUM(CASE WHEN doluluk_m3 = 0 THEN 1 ELSE 0 END) as bos,
    SUM(CASE WHEN doluluk_m3 > 0 THEN 1 ELSE 0 END) as dolu,
    SUM(kapasite_m3) as toplam_kapasite,
    SUM(doluluk_m3) as mevcut_doluluk
FROM silolar");
$silo_ozet = $silo_ozet_res ? $silo_ozet_res->fetch_assoc() : [
    'toplam' => 0, 
    'bos' => 0, 
    'dolu' => 0, 
    'toplam_kapasite' => 0, 
    'mevcut_doluluk' => 0
];
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Silo Yönetimi</title>
    <!-- CSS Kütüphaneleri -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .silo-metrics {
            margin: 0;
        }

        .silo-metric-row {
            display: grid;
            grid-template-columns: minmax(92px, max-content) minmax(0, 1fr);
            gap: 0.5rem;
            align-items: center;
            padding: 0.35rem 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.12);
            min-width: 0;
        }

        .silo-metric-row:last-child {
            border-bottom: 0;
        }

        .silo-metric-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #334155;
            font-weight: 700;
            white-space: nowrap;
        }

        .silo-metric-value {
            text-align: right;
            white-space: normal;
            overflow-wrap: anywhere;
            font-weight: 700;
            color: #0f172a;
            min-width: 0;
            line-height: 1.25;
        }

        .silo-metric-row.is-capacity .silo-metric-label,
        .silo-metric-row.is-capacity .silo-metric-value {
            color: #0d6efd;
        }

        .silo-metric-row.is-fill .silo-metric-label,
        .silo-metric-row.is-fill .silo-metric-value {
            color: #1f2937;
        }

        .silo-metric-row.is-muted .silo-metric-label,
        .silo-metric-row.is-muted .silo-metric-value {
            color: #198754;
            font-weight: 600;
        }

        @media (max-width: 576px) {
            .silo-metric-row {
                grid-template-columns: 1fr;
                gap: 0.2rem;
            }

            .silo-metric-value {
                text-align: left;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-warehouse text-primary"></i> Silo Yönetimi</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniSiloModal">
                <i class="fas fa-plus"></i> Yeni Silo Ekle
            </button>
        </div>

        <!-- SİLO ÖZET KARTLARI -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 opacity-75 small">Toplam Silo</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $silo_ozet['toplam']; ?></h3>
                            </div>
                            <i class="fas fa-warehouse fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 opacity-75 small">Müsait (Boş) Silo</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $silo_ozet['bos']; ?></h3>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 opacity-75 small">Dolu / Aktif Silo</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $silo_ozet['dolu']; ?></h3>
                            </div>
                            <i class="fas fa-fill-drip fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1 opacity-75 small">Toplam Kapasite</h6>
                                <h3 class="mb-0 fw-bold"><?php echo sayiFormat($silo_ozet['toplam_kapasite'], 0); ?> <span class="small fw-normal">m&sup3;</span></h3>
                            </div>
                            <i class="fas fa-database fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- SEKMELER -->
        <ul class="nav nav-tabs mb-4" id="siloTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="bugday-tab" data-bs-toggle="tab"
                    data-bs-target="#bugday-pane" type="button">
                    <i class="fas fa-seedling text-warning"></i> Buğday
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="un-tab" data-bs-toggle="tab" data-bs-target="#un-pane"
                    type="button">
                    <i class="fas fa-bread-slice text-secondary"></i> Un
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="tav-tab" data-bs-toggle="tab" data-bs-target="#tav-pane"
                    type="button">
                    <i class="fas fa-tint text-info"></i> Tav
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="kepek-tab" data-bs-toggle="tab" data-bs-target="#kepek-pane"
                    type="button">
                    <i class="fas fa-leaf text-success"></i> Kepek
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <?php
            $tipler = [
                'bugday' => ['data' => $silolar_bugday, 'id' => 'bugday-pane', 'active' => true],
                'un' => ['data' => $silolar_un, 'id' => 'un-pane', 'active' => false],
                'tav' => ['data' => $silolar_tav, 'id' => 'tav-pane', 'active' => false],
                'kepek' => ['data' => $silolar_kepek, 'id' => 'kepek-pane', 'active' => false]
            ];

            foreach ($tipler as $key => $val) {
                $activeClass = $val['active'] ? 'show active' : '';
                echo "<div class='tab-pane fade $activeClass' id='{$val['id']}'><div class='row'>";

                if (!empty($val['data'])) {
                    foreach ($val['data'] as $row) {
                        // Veri temizliği (null check)
                        $cap_m3 = (float) ($row['kapasite_m3'] ?? 0);
                        $dol_m3 = (float) ($row['doluluk_m3'] ?? 0);
                        $bos_m3 = max(0, $cap_m3 - $dol_m3);
                        $durum = htmlspecialchars($row['durum'] ?? 'aktif');
                        $silo_adi = htmlspecialchars($row['silo_adi'] ?? '');
                        $aktif_kod_raw = trim((string) ($row['aktif_hammadde_kodu'] ?? ''));
                        $aktif_kod = htmlspecialchars($aktif_kod_raw);
                        $silo_id = (int) ($row['id'] ?? 0);

                        $izinli_kodlar = [];
                        if (!empty($row['izin_verilen_hammadde_kodlari'])) {
                            $tmp = json_decode((string) $row['izin_verilen_hammadde_kodlari'], true);
                            if (is_array($tmp)) {
                                $izinli_kodlar = normalizeKodList($tmp);
                            }
                        }
                        $izinli_metin = !empty($izinli_kodlar) ? htmlspecialchars(implode(', ', $izinli_kodlar)) : 'Kisit yok';
                        $izinli_alert = !empty($izinli_kodlar) ? 'alert-warning' : 'alert-light';
                        $karisimlar = $silo_karisim_map[$silo_id] ?? [];
                        $toplam_kalan_kg = (float) ($silo_toplam_kalan_kg[$silo_id] ?? 0);
                        $toplam_kalan_ton = $toplam_kalan_kg / 1000;

                        $varsayilan_yogunluk_kg_m3 = 780.0;
                        $aktif_yogunluk_kg_m3 = (float) ($hammadde_yogunluk_map[$aktif_kod_raw] ?? 0);
                        if ($dol_m3 > 0 && $toplam_kalan_kg > 0) {
                            $tahmini_yogunluk_kg_m3 = $toplam_kalan_kg / $dol_m3;
                        } elseif ($aktif_yogunluk_kg_m3 > 0) {
                            $tahmini_yogunluk_kg_m3 = $aktif_yogunluk_kg_m3;
                        } else {
                            $tahmini_yogunluk_kg_m3 = $varsayilan_yogunluk_kg_m3;
                        }
                        $kapasite_tahmini_ton = ($cap_m3 * $tahmini_yogunluk_kg_m3) / 1000;
                        $bos_alan_tahmini_ton = ($bos_m3 * $tahmini_yogunluk_kg_m3) / 1000;

                        $yuzde = ($cap_m3 > 0) ? ($dol_m3 / $cap_m3) * 100 : 0;
                        $renk = siloDolulukRenk($yuzde);

                        // İkon Seçimi
                        $ikon = 'fa-warehouse';
                        if ($key == 'un')
                            $ikon = 'fa-bread-slice';
                        if ($key == 'bugday')
                            $ikon = 'fa-seedling';
                        if ($key == 'tav')
                            $ikon = 'fa-tint';
                        if ($key == 'kepek')
                            $ikon = 'fa-leaf';

                        // Durum Badge Rengi
                        $durum_badge = ($durum == 'aktif') ? 'bg-success' : (($durum == 'bakim') ? 'bg-warning' : 'bg-secondary');

                        $karisim_html = "<small class='text-muted'>Aktif stok kaydi yok</small>";
                        if (!empty($karisimlar) && $toplam_kalan_kg > 0) {
                            $karisim_html = "<ul class='list-group list-group-flush small'>";
                            foreach ($karisimlar as $k) {
                                $kodu_raw = trim((string) ($k['hammadde_kodu'] ?? 'Bilinmeyen'));
                                $kodu = htmlspecialchars($kodu_raw, ENT_QUOTES, 'UTF-8');
                                $parti_raw = trim((string) ($k['parti_kodu'] ?? ''));
                                $parti = $parti_raw !== '' ? $parti_raw : '-';
                                $parti_esc = htmlspecialchars($parti, ENT_QUOTES, 'UTF-8');
                                $kalan = (float) ($k['kalan_kg'] ?? 0);
                                $oran = ($toplam_kalan_kg > 0) ? ($kalan / $toplam_kalan_kg) * 100 : 0;
                                $lab_payload = htmlspecialchars(json_encode($k['lab'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                                $karisim_html .= "<li class='list-group-item d-flex justify-content-between px-0 py-1'>
                                                    <span>
                                                        <button type='button' class='btn btn-link p-0 text-start text-decoration-none karisim-lab-btn'
                                                            data-hammadde-kodu='$kodu'
                                                            data-parti-kodu='$parti_esc'
                                                            data-lab='$lab_payload'>
                                                            <strong>$kodu</strong> / <span class='text-muted'>$parti_esc</span> %" . sayiFormat($oran, 1) . "
                                                        </button>
                                                    </span>
                                                    <span>" . sayiFormat($kalan, 0) . " kg</span>
                                                  </li>";
                            }
                            $karisim_html .= "</ul>";
                        }

                        $plc_ip_attr = '';
                        if (!empty($row['plc_ip_adresi'])) {
                            $plc_ip_attr = "data-plc-ip='" . htmlspecialchars($row['plc_ip_adresi'], ENT_QUOTES, 'UTF-8') . "'";
                        }
                        echo "
                    <div class='col-md-6 col-lg-4 mb-4 silo-card-wrapper' data-kategori='$key' $plc_ip_attr>
                        <div class='card shadow-sm h-100 border-start border-4 border-primary'>
                            <div class='card-header bg-white d-flex justify-content-between align-items-center'>
                                <h5 class='mb-0 text-primary'><i class='fas $ikon'></i> $silo_adi</h5>
                                <span class='badge $durum_badge'>" . strtoupper($durum) . "</span>
                            </div>
                            <div class='card-body'>
                                <div class='row align-items-center mb-3'>
                                    <div class='col-4 text-center'>
                                        <div class='silo-visual border rounded position-relative bg-light' style='height:120px; width:60px; margin:0 auto; overflow:hidden; border:2px solid #555;'>
                                            <div class='position-absolute bottom-0 w-100 $renk' style='height: {$yuzde}%; transition: height 1s;'></div>
                                            <div class='position-absolute top-0 w-100 h-100' style='background: repeating-linear-gradient(transparent, transparent 19px, rgba(0,0,0,0.1) 20px);'></div>
                                        </div>
                                        <small class='d-block mt-1 fw-bold'>%" . round($yuzde) . "</small>
                                    </div>
                                    <div class='col-8'>
                                        <div class='silo-metrics small'>
                                            <div class='silo-metric-row is-capacity'>
                                                <span class='silo-metric-label'><i class='fas fa-database'></i> Kapasite:</span>
                                                <span class='silo-metric-value'>" . sayiFormat($cap_m3, 1) . " m&sup3; / " . sayiFormat($kapasite_tahmini_ton, 2) . " ton (tah.)</span>
                                            </div>
                                            <div class='silo-metric-row is-fill'>
                                                <span class='silo-metric-label'><i class='fas fa-cube'></i> Doluluk:</span>
                                                <span class='silo-metric-value'>" . sayiFormat($dol_m3, 1) . " m&sup3; / " . sayiFormat($toplam_kalan_ton, 2) . " ton</span>
                                            </div>
                                            <div class='silo-metric-row is-muted'>
                                                <span class='silo-metric-label'><i class='fas fa-box-open'></i> Bos Alan:</span>
                                                <span class='silo-metric-value'>" . sayiFormat($bos_m3, 1) . " m&sup3; / " . sayiFormat($bos_alan_tahmini_ton, 2) . " ton (tah.)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class='alert alert-light py-1 px-2 mb-2 text-center border'>
                                    <small>" . ($aktif_kod ? "<strong>$aktif_kod</strong>" : "Boş") . "</small>
                                </div>

                                <div class='alert $izinli_alert py-1 px-2 mb-2 border'>
                                    <small><strong>Sadece Bunlar Girebilir:</strong> $izinli_metin</small>
                                </div>

                                " . (!empty($row['plc_ip_adresi']) ? "
                                <div class='border rounded px-2 py-1 mb-2 bg-light plc-status-container d-flex flex-column'>
                                    <div class='d-flex justify-content-between align-items-center mb-1'>
                                        <small class='text-muted' style='font-size:11px;'><i class='fas fa-network-wired'></i> {$row['plc_ip_adresi']}</small>
                                        <span class='badge bg-secondary plc-durum-badge' style='font-size:10px;'>Bağlantı Bekleniyor</span>
                                    </div>
                                    <div class='d-flex justify-content-between align-items-center plc-flow-row' style='display:none !important;'>
                                        <span class='fw-bold text-success plc-akis-val' style='font-size:1.1rem;'>0 <small class='text-muted' style='font-size:10px;'>kg/h</small></span>
                                        <span class='badge bg-info plc-oran-badge' title='Paçal Oranı (Aktifler Arasında)'>%0</span>
                                    </div>
                                </div>" : "") . "

                                <div class='border rounded p-2 mb-2 bg-light-subtle'>
                                    <div class='small fw-semibold text-muted mb-1'>Silo Karisimi (kalan kg / oran)</div>
                                    $karisim_html
                                </div>

                                <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                    <button class='btn btn-sm btn-outline-secondary w-100' onclick='duzenleModal(" . json_encode($row) . ")'>
                                        <i class='fas fa-cog'></i> Düzenle
                                    </button>
                                    <button class='btn btn-sm btn-outline-warning w-100' onclick='manuelSeviyeModalAc($silo_id, \"$silo_adi\", $toplam_kalan_kg, \"$aktif_kod_raw\", \"$key\")' title='Manuel KG/Hektolitre Belirle'><i class='fas fa-edit'></i> Seviye Belirle</button>
                                    ";

                        if ($dol_m3 > 0) {
                            echo "<button class='btn btn-sm btn-outline-danger w-100' onclick='sifirlaModal({$row['id']}, \"$silo_adi\")'>
                                <i class='fas fa-trash-alt'></i> Boşalt
                              </button>";
                        } else {
                            echo "<button class='btn btn-sm btn-outline-danger w-100' onclick='silModal({$row['id']}, \"$silo_adi\")'>
                                <i class='fas fa-times'></i> Sil
                              </button>";
                        }

                        echo "      </div>
                            </div>
                        </div>
                    </div>";
                    }
                } else {
                    echo "<div class='col-12 p-3 text-muted'>Bu kategoride silo bulunmuyor.</div>";
                }
                echo "</div></div>";
            }
            ?>
        </div>
    </div>

    <!-- MODAL: YENİ SİLO -->
    <div class="modal fade" id="yeniSiloModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Yeni Silo Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label>Adı</label><input type="text" name="silo_adi" class="form-control"
                            required></div>
                    <div class="mb-3"><label>Tipi</label>
                        <select name="tip" id="new_silo_tip" class="form-select" onchange="filterHammaddeList('new')">
                            <option value="bugday">Buğday Silosu</option>
                            <option value="un">Un Silosu</option>
                            <option value="tav">Tav Silosu</option>
                            <option value="kepek">Kepek Silosu</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-12"><label>Kapasite (m&sup3;)</label><input type="number" step="0.1"
                                name="kapasite_m3" class="form-control" required></div>
                    </div>
                    <div class="row mb-3 mt-2">
                        <div class="col-12"><label>PLC IP Adresi (Opsiyonel)</label><input type="text"
                                name="plc_ip_adresi" class="form-control" placeholder="Örn: 192.168.20.51"></div>
                    </div>
                    <div class="mb-2">
                        <label>Sadece Bunlar Girebilir (istege bagli):</label>
                        <div class="border rounded p-2 bg-light" style="height:140px; overflow-y:auto;">
                            <?php foreach ($hammadde_listesi as $h) { ?>
                                <div class="form-check hammadde-item" data-kod="<?php echo $h['hammadde_kodu']; ?>">
                                    <input type="checkbox" name="yeni_izinli_kodlar[]"
                                        value="<?php echo $h['hammadde_kodu']; ?>"
                                        id="new_chk_<?php echo $h['hammadde_kodu']; ?>" class="form-check-input">
                                    <label class="form-check-label small"
                                        for="new_chk_<?php echo $h['hammadde_kodu']; ?>"><?php echo $h['hammadde_kodu']; ?></label>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="silo_ekle" class="btn btn-success">Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: DÜZENLE -->
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Silo Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="silo_id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary border-bottom pb-2">Ayarlar</h6>
                            <div class="mb-3"><label>Adı</label><input type="text" name="silo_adi" id="edit_adi"
                                    class="form-control" required></div>
                            <div class="row mb-3">
                                <div class="col-12"><label>m&sup3;</label><input type="number" step="0.1" name="kapasite_m3"
                                        id="edit_kapasite" class="form-control"></div>
                            </div>
                            <div class="mb-3"><label>Durum</label>
                                <select name="durum" id="edit_durum" class="form-select">
                                    <option value="aktif">Aktif</option>
                                    <option value="bakim">Bakım</option>
                                    <option value="temizlik">Temizlik</option>
                                </select>
                            </div>
                            <div class="mb-3"><label>PLC IP Adresi</label><input type="text" name="plc_ip_adresi" id="edit_plc_ip"
                                    class="form-control" placeholder="192.168.20.x">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2">İçerik & Kısıtlama</h6>
                            <div class="mb-3">
                                <label>İçindeki Ürün</label>
                                <select name="aktif_hammadde_kodu" id="edit_aktif_kod" class="form-select hammadde-select">
                                    <option value="">-- Boş --</option>
                                    <?php foreach ($hammadde_listesi as $h)
                                        echo "<option value='{$h['hammadde_kodu']}' class='hammadde-opt' data-kod='{$h['hammadde_kodu']}'>{$h['hammadde_kodu']}</option>"; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label>Sadece Bunlar Girebilir:</label>
                                <div class="border rounded p-2 bg-light" style="height:150px; overflow-y:auto;">
                                    <?php foreach ($hammadde_listesi as $h) { ?>
                                        <div class="form-check hammadde-item" data-kod="<?php echo $h['hammadde_kodu']; ?>">
                                            <input type="checkbox" name="izinli_kodlar[]"
                                                value="<?php echo $h['hammadde_kodu']; ?>"
                                                id="chk_<?php echo $h['hammadde_kodu']; ?>"
                                                class="form-check-input izinli-check">
                                            <label class="form-check-label small"
                                                for="chk_<?php echo $h['hammadde_kodu']; ?>"><?php echo $h['hammadde_kodu']; ?></label>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="silo_guncelle"
                        class="btn btn-primary">Kaydet</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL: MANUEL STOK/HEKTOLİTRE BELİRLEME -->
    <div class="modal fade" id="manuelSeviyeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Sıfırlama (KG / Hektolitre) Ayarla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="silo_id" id="manuel_silo_id">
                    <div class="alert alert-warning py-2 small">
                        <i class="fas fa-info-circle me-1"></i>
                        Bu işlem, <strong>silonun mevcut içeriğini (kg) ve hektolitre değerini tamamen ezer</strong>. Yeni girilen değerler üzerinden siloyu doldurur ve sadece bu siloyu etkiler. Lab kayıtlarına doğrudan dokunmaz.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Silo</label>
                        <input type="text" id="manuel_silo_adi" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mevcut Stok (Eski)</label>
                        <input type="text" id="manuel_mevcut_kg" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Girecek Ürün Cinsi <span class="text-danger">*</span></label>
                        <select name="aktif_hammadde_kodu" id="manuel_aktif_kodu" class="form-select" required>
                            <option value="">-- Ürün Seçin --</option>
                            <?php foreach ($hammadde_listesi as $h) { ?>
                                <option value="<?php echo $h['hammadde_kodu']; ?>"><?php echo $h['hammadde_kodu']; ?></option>
                            <?php } ?>
                        </select>
                        <small class="text-muted">Lütfen siloya atanacak ürünü seçin.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Yeni Miktar (Sıfırlama KG) <span class="text-danger">*</span></label>
                        <input type="number" name="yeni_kg" id="manuel_kg_input" class="form-control" step="0.01" min="1" required placeholder="Örn: 20000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Yeni Hektolitre (Sıfırlama Hek) <span class="text-danger">*</span></label>
                        <input type="number" name="yeni_hek" id="manuel_hek_input" class="form-control" step="0.1" min="1" required placeholder="Örn: 78.5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="silo_manuel_belirle" class="btn btn-warning"><i class="fas fa-check me-1"></i>Uygula</button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" id="sifirlaForm" class="d-none">
        <input type="hidden" name="silo_id" id="sifirla_id">
        <input type="hidden" name="silo_sifirla" value="1">
    </form>

    <form method="post" id="silForm" class="d-none">
        <input type="hidden" name="silo_id" id="sil_id">
        <input type="hidden" name="silo_sil" value="1">
    </form>

    <div class="modal fade" id="labAnalizModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-flask me-2"></i>Parti Lab Analizi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div><strong>Hammadde Kodu:</strong> <span id="lab_modal_hammadde_kodu">-</span></div>
                        <div><strong>Parti Kodu:</strong> <span id="lab_modal_parti_kodu">-</span></div>
                    </div>
                    <div id="lab_modal_kayit_yok" class="alert alert-warning d-none mb-3">
                        Bu parti için lab analizi bulunamadı.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 25%;">Hektolitre</th>
                                    <td id="lab_modal_hektolitre">-</td>
                                    <th style="width: 25%;">Nem</th>
                                    <td id="lab_modal_nem">-</td>
                                </tr>
                                <tr>
                                    <th>Protein</th>
                                    <td id="lab_modal_protein">-</td>
                                    <th>Nişasta</th>
                                    <td id="lab_modal_nisasta">-</td>
                                </tr>
                                <tr>
                                    <th>Sertlik</th>
                                    <td id="lab_modal_sertlik">-</td>
                                    <th>Gluten</th>
                                    <td id="lab_modal_gluten">-</td>
                                </tr>
                                <tr>
                                    <th>Index</th>
                                    <td id="lab_modal_index">-</td>
                                    <th>Sedimantasyon</th>
                                    <td id="lab_modal_sedimantasyon">-</td>
                                </tr>
                                <tr>
                                    <th>Gecikmeli Sedimantasyon</th>
                                    <td id="lab_modal_gecikmeli_sedimantasyon">-</td>
                                    <th>FN</th>
                                    <td id="lab_modal_fn">-</td>
                                </tr>
                                <tr>
                                    <th>Döker Oranı</th>
                                    <td id="lab_modal_doker_orani">-</td>
                                    <th>Laborant</th>
                                    <td id="lab_modal_laborant">-</td>
                                </tr>
                                <tr>
                                    <th>Tarih</th>
                                    <td colspan="3" id="lab_modal_tarih">-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // SweetAlert2 Alerts
            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ "], "", $mesaj)); ?>',
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 5000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            <?php endif; ?>

            <?php if (!empty($hata)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", $hata)); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>
        });

        function duzenleModal(data) {
            document.getElementById('edit_id').value = data.id || '';
            document.getElementById('edit_adi').value = data.silo_adi || '';
            document.getElementById('edit_kapasite').value = data.kapasite_m3 || '';
            document.getElementById('edit_durum').value = data.durum || 'aktif';
            document.getElementById('edit_plc_ip').value = data.plc_ip_adresi || '';

            let tip = data.tip || 'bugday';
            let icerikContainer = document.getElementById('edit_aktif_kod').closest('.col-md-6');
            let sel = document.getElementById('edit_aktif_kod');
            
            document.querySelectorAll('.izinli-check').forEach(cb => cb.checked = false);

            if (tip === 'un' || tip === 'tav' || tip === 'kepek') {
                icerikContainer.style.display = 'none';
                let val = tip.toUpperCase() + '_PACAL';
                let found = false;
                for(let i=0; i<sel.options.length; i++) if(sel.options[i].value === val) found = true;
                if(!found) { let o=document.createElement('option'); o.value=val; o.text=val; sel.appendChild(o); }
                sel.value = val;
            } else {
                icerikContainer.style.display = 'block';
                sel.value = data.aktif_hammadde_kodu || '';
                
                if (data.izin_verilen_hammadde_kodlari) {
                    try {
                        JSON.parse(data.izin_verilen_hammadde_kodlari).forEach(k => {
                            let cb = document.getElementById('chk_' + k);
                            if (cb) cb.checked = true;
                        });
                    } catch (e) { }
                }
            }
            
            // Filtreleme uygula
            filterHammaddeList('edit', tip);

            new bootstrap.Modal(document.getElementById('duzenleModal')).show();
        }

        function filterHammaddeList(context, ForcedTip = null) {
            let tip = ForcedTip;
            if(!tip) {
                if(context === 'new') tip = document.getElementById('new_silo_tip').value;
                else tip = 'bugday'; // Default fallback
            }

            const modalId = (context === 'new') ? 'yeniSiloModal' : 'duzenleModal';
            const modalEl = document.getElementById(modalId);
            if(!modalEl) return;

            const items = modalEl.querySelectorAll('.hammadde-item');
            const opts = modalEl.querySelectorAll('.hammadde-opt');

            const isWheatSilo = (tip === 'bugday');

            const filterFn = (kod) => {
                if (!kod) return true;
                const c = kod.trim().charAt(0).toUpperCase();
                const isWheatCode = (c === 'D' || c === 'K' || c === 'B');
                if (!isWheatSilo && isWheatCode) return false; // Buğday değilse ve kod buğdaysa gizle
                return true;
            };

            items.forEach(item => {
                const kod = item.getAttribute('data-kod');
                item.style.display = filterFn(kod) ? 'block' : 'none';
            });

            opts.forEach(opt => {
                const kod = opt.getAttribute('data-kod');
                opt.style.display = filterFn(kod) ? 'block' : 'none';
            });
        }

        function manuelSeviyeModalAc(id, adi, mevcutKg, aktifGirenKod = '', kategori = 'bugday') {
            document.getElementById('manuel_silo_id').value = id;
            document.getElementById('manuel_silo_adi').value = adi;
            document.getElementById('manuel_mevcut_kg').value = mevcutKg.toLocaleString('tr-TR', {maximumFractionDigits: 0}) + ' kg';
            document.getElementById('manuel_kg_input').value = mevcutKg > 0 ? mevcutKg : '';
            document.getElementById('manuel_hek_input').value = '';
            
            let aktifHammaddeKapsayici = document.getElementById('manuel_aktif_kodu').parentElement;
            let sel = document.getElementById('manuel_aktif_kodu');

            if (kategori === 'un' || kategori === 'tav' || kategori === 'kepek') {
                aktifHammaddeKapsayici.style.display = 'none';
                let val = kategori.toUpperCase() + '_PACAL';
                let found = false;
                for(let i=0; i<sel.options.length; i++) if(sel.options[i].value === val) found = true;
                if(!found) { let o=document.createElement('option'); o.value=val; o.text=val; sel.appendChild(o); }
                sel.value = val;
            } else {
                aktifHammaddeKapsayici.style.display = 'block';
                sel.value = aktifGirenKod;
            }

            new bootstrap.Modal(document.getElementById('manuelSeviyeModal')).show();
        }

        function sifirlaModal(id, adi) {
            document.getElementById('sifirla_id').value = id;
            Swal.fire({
                title: 'Silo Boşaltma Onayı',
                html: `<strong>${adi}</strong> içeriği tamamen boşaltılacak.<br>Bu işlem geri alınamaz.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Boşalt',
                cancelButtonText: 'İptal',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('sifirlaForm').submit();
                }
            });
        }

        function silModal(id, adi) {
            document.getElementById('sil_id').value = id;
            Swal.fire({
                title: 'Silo Silme Onayı',
                html: `<strong>${adi}</strong> kalıcı olarak silinecek.<br>Bu işlem geri alınamaz.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('silForm').submit();
                }
            });
        }

        function labDegerFormat(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }
            return String(value);
        }

        function labModalDoldur(labData, hammaddeKodu, partiKodu) {
            document.getElementById('lab_modal_hammadde_kodu').innerText = hammaddeKodu || '-';
            document.getElementById('lab_modal_parti_kodu').innerText = partiKodu || '-';

            const alanlar = {
                hektolitre: 'lab_modal_hektolitre',
                nem: 'lab_modal_nem',
                protein: 'lab_modal_protein',
                nisasta: 'lab_modal_nisasta',
                sertlik: 'lab_modal_sertlik',
                gluten: 'lab_modal_gluten',
                index_degeri: 'lab_modal_index',
                sedimantasyon: 'lab_modal_sedimantasyon',
                gecikmeli_sedimantasyon: 'lab_modal_gecikmeli_sedimantasyon',
                fn: 'lab_modal_fn',
                doker_orani: 'lab_modal_doker_orani',
                laborant: 'lab_modal_laborant',
                tarih: 'lab_modal_tarih'
            };

            let analizVar = false;
            Object.keys(alanlar).forEach(function (key) {
                const rawVal = (labData && Object.prototype.hasOwnProperty.call(labData, key)) ? labData[key] : null;
                if (rawVal !== null && rawVal !== undefined && rawVal !== '') {
                    analizVar = true;
                }
                document.getElementById(alanlar[key]).innerText = labDegerFormat(rawVal);
            });

            const kayitYok = document.getElementById('lab_modal_kayit_yok');
            if (analizVar) {
                kayitYok.classList.add('d-none');
            } else {
                kayitYok.classList.remove('d-none');
            }
        }

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.karisim-lab-btn');
            if (!btn) {
                return;
            }

            let labData = {};
            try {
                labData = JSON.parse(btn.getAttribute('data-lab') || '{}');
            } catch (e) {
                labData = {};
            }

            const hammaddeKodu = btn.getAttribute('data-hammadde-kodu') || '-';
            const partiKodu = btn.getAttribute('data-parti-kodu') || '-';

            labModalDoldur(labData, hammaddeKodu, partiKodu);
            new bootstrap.Modal(document.getElementById('labAnalizModal')).show();
        });

        // Gerçek Zamanlı PLC Akış ve Paçal Oranı Takibi
        function updatePLCFlows() {
            fetch('ajax/plc_veri.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.basari || !data.cihazlar) return;
                    
                    // Cihaz verilerini IP'ye göre haritalayalım
                    const plcMap = {};
                    data.cihazlar.forEach(c => {
                        if (c.ip) plcMap[c.ip] = c;
                    });

                    // Her kategori için toplam aktif flow'u hesaplayacağız (paçal oranı için)
                    const kategoriFlowTotals = { 'bugday': 0, 'un': 0, 'tav': 0, 'kepek': 0 };
                    
                    document.querySelectorAll('.silo-card-wrapper').forEach(card => {
                        const ip = card.getAttribute('data-plc-ip');
                        const kategori = card.getAttribute('data-kategori');
                        if (ip && plcMap[ip]) {
                            const veriler = plcMap[ip].veriler;
                            if (veriler && (veriler.ANLIK_TONAJ || veriler.ANLIK_TONAJ === 0)) {
                                const akis = parseFloat(veriler.ANLIK_TONAJ) || 0;
                                const aktif = parseInt(veriler.AKAR_DURUM) === 2;
                                if (aktif && akis > 0) {
                                    kategoriFlowTotals[kategori] += akis;
                                }
                            }
                        }
                    });

                    // Arayüzü Güncelle
                    document.querySelectorAll('.silo-card-wrapper').forEach(card => {
                        const ip = card.getAttribute('data-plc-ip');
                        const kategori = card.getAttribute('data-kategori');
                        const durumBadge = card.querySelector('.plc-durum-badge');
                        const flowRow = card.querySelector('.plc-flow-row');
                        const akisVal = card.querySelector('.plc-akis-val');
                        const oranBadge = card.querySelector('.plc-oran-badge');

                        if (!ip || !plcMap[ip]) {
                            if (durumBadge) {
                                durumBadge.className = 'badge bg-secondary plc-durum-badge';
                                durumBadge.innerText = 'Çevrimdışı / Pasif';
                            }
                            return;
                        }

                        const plcData = plcMap[ip];
                        const veriler = plcData.veriler;

                        // PLC'nin 5 dakikadan eski verisi varsa pasif sayabiliriz ama
                        // cihazdan gelen 'AKIS' var genelde
                        if (!veriler || (veriler.ANLIK_TONAJ === undefined && veriler.AKAR_DURUM === undefined)) {
                            if (durumBadge) {
                                durumBadge.className = 'badge bg-warning text-dark plc-durum-badge';
                                durumBadge.innerText = 'Akış Verisi Yok';
                            }
                            return;
                        }

                        const akis = parseFloat(veriler.ANLIK_TONAJ) || 0;
                        const akarDurum = parseInt(veriler.AKAR_DURUM) || 0;
                        
                        if (akarDurum === 2 && akis > 0) {
                            // Çalışıyor
                            durumBadge.className = 'badge bg-success plc-durum-badge';
                            durumBadge.innerHTML = '<span class=\"spinner-grow spinner-grow-sm\" role=\"status\" aria-hidden=\"true\" style=\"width:10px;height:10px;margin-right:3px;\"></span> Çalışıyor';
                            
                            if (flowRow) flowRow.style.setProperty('display', 'flex', 'important');
                            if (akisVal) akisVal.innerHTML = Math.round(akis).toLocaleString('tr-TR') + ' <small class=\"text-muted\" style=\"font-size:10px;\">kg/h</small>';
                            
                            // Oran hesapla
                            if (oranBadge) {
                                const toplam = kategoriFlowTotals[kategori] || 0;
                                const oran = (toplam > 0) ? (akis / toplam) * 100 : 0;
                                oranBadge.innerText = '%' + oran.toFixed(1);
                            }
                        } else {
                            // Beklemede
                            durumBadge.className = 'badge bg-light text-secondary border plc-durum-badge';
                            durumBadge.innerText = 'Beklemede';
                            if (flowRow) flowRow.style.setProperty('display', 'none', 'important');
                        }
                    });
                })
                .catch(err => console.error('PLC veri hatası:', err));
        }

        // İlk yükleme ve periyodik güncelleme
        setTimeout(updatePLCFlows, 500); // UI çizildikten hemen sonra
        setInterval(updatePLCFlows, 5000); // 5 saniyede bir güncelle
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
