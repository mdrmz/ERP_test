<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

$mesaj = "";
$hata = "";

// --- 1. YENİ MÜŞTERİ EKLE ---
if (isset($_POST["musteri_ekle"])) {
    if (!yazmaYetkisiVar($baglanti)) {
        $hata = "Bu işlem için yazma yetkiniz bulunmuyor.";
    } else {
        $kod = $baglanti->real_escape_string($_POST["cari_kod"]);
        $tip = (strpos($kod, '120') === 0) ? 'Müşteri' : ((strpos($kod, '320') === 0) ? 'Tedarikçi' : 'Müşteri');
        $ad = $baglanti->real_escape_string($_POST["firma_adi"]);
        $yetkili = $baglanti->real_escape_string($_POST["yetkili_kisi"]);
        $tel = $baglanti->real_escape_string($_POST["telefon"]);
        $adres = $baglanti->real_escape_string($_POST["adres"]);

        // Aynı kodda var mı kontrolü
        $kontrol = $baglanti->query("SELECT id FROM musteriler WHERE cari_kod = '$kod'");
        if ($kontrol && $kontrol->num_rows > 0) {
            $hata = "⚠️ Bu cari kod ($kod) zaten kayıtlı!";
        } else {
            $sql = "INSERT INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, adres) 
                    VALUES ('$kod', '$tip', '$ad', '$yetkili', '$tel', '$adres')";
            if ($baglanti->query($sql)) {
                $mesaj = "✅ Müşteri eklendi: $ad ($kod)";
            } else {
                $hata = "Hata: " . $baglanti->error;
            }
        }
    }
}

// --- 2. YENİ SİPARİŞ OLUŞTUR ---
if (isset($_POST["siparis_olustur"])) {
    if (!yazmaYetkisiVar($baglanti)) {
        $hata = "Bu işlem için yazma yetkiniz bulunmuyor.";
    } else {
        $musteri_id = (int) $_POST["musteri_id"];
        $tarih = $_POST["siparis_tarihi"];
        $teslim = $_POST["teslim_tarihi"];
        $alici = isset($_POST["alici_adi"]) ? $baglanti->real_escape_string($_POST["alici_adi"]) : "";
        $odeme = isset($_POST["odeme_tarihi"]) ? $_POST["odeme_tarihi"] : null;
        if (empty($odeme)) {
            $odeme = null;
        }
        $odeme_sql = $odeme ? "'$odeme'" : "NULL";
        $aciklama = $baglanti->real_escape_string($_POST["aciklama"]);
        $siparis_kodu = "SIP-" . date("Ymd") . "-" . rand(100, 999);

        // Ana Sipariş Kaydı
        $sql_baslik = "INSERT INTO siparisler (musteri_id, siparis_kodu, siparis_tarihi, teslim_tarihi, alici_adi, odeme_tarihi, aciklama, durum) 
                    VALUES ($musteri_id, '$siparis_kodu', '$tarih', '$teslim', '$alici', $odeme_sql, '$aciklama', 'Bekliyor')";

        if ($baglanti->query($sql_baslik)) {
            $siparis_id = $baglanti->insert_id;

            // Ürünleri Ekle
            if (isset($_POST["urunler"]) && is_array($_POST["urunler"])) {
                foreach ($_POST["urunler"] as $k => $urun_adi) {
                    if (empty($urun_adi))
                        continue;
                    $miktar = (int) $_POST["miktarlar"][$k];
                    $birim = $_POST["birimler"][$k];

                    // Ürün adını temizle
                    $urun_adi = $baglanti->real_escape_string($urun_adi);

                    $baglanti->query("INSERT INTO siparis_detaylari (siparis_id, urun_adi, miktar, birim) 
                                    VALUES ($siparis_id, '$urun_adi', $miktar, '$birim')");
                }
            }
            $mesaj = "✅ Sipariş oluşturuldu: $siparis_kodu";
        } else {
            $hata = "Sipariş oluşturulurken hata: " . $baglanti->error;
        }
    }
}

// --- 3. FİYATLANDIRMA KAYDET ---
if (isset($_POST["fiyatlandirma_kaydet"])) {
    if (!yazmaYetkisiVar($baglanti)) {
        $hata = "Bu işlem için yazma yetkiniz bulunmuyor.";
    } else {
        $siparis_id = isset($_POST["siparis_id"]) ? (int) $_POST["siparis_id"] : 0;
        $fiyatlar = isset($_POST["birim_fiyat"]) && is_array($_POST["birim_fiyat"]) ? $_POST["birim_fiyat"] : [];

        if ($siparis_id <= 0) {
            $hata = "Geçersiz sipariş bilgisi.";
        } else {
            $siparis_durum = '';
            $siparis_durum_res = $baglanti->query("SELECT durum FROM siparisler WHERE id = $siparis_id LIMIT 1");
            if (!$siparis_durum_res || $siparis_durum_res->num_rows === 0) {
                $hata = "Sipariş bulunamadı.";
            } else {
                $siparis_durum = $siparis_durum_res->fetch_assoc()['durum'] ?? '';
            }

            if ($siparis_durum === 'IptalEdildi') {
                $hata = "İptal edilen siparişte fiyatlandırma yapılamaz.";
            } else {
            $satirlar_res = $baglanti->query("SELECT id, miktar FROM siparis_detaylari WHERE siparis_id = $siparis_id");
            if (!$satirlar_res || $satirlar_res->num_rows === 0) {
                $hata = "Fiyatlandırılacak sipariş satırı bulunamadı.";
            } else {
                $baglanti->begin_transaction();
                try {
                    while ($satir = $satirlar_res->fetch_assoc()) {
                        $detay_id = (int) $satir['id'];
                        $miktar = (int) $satir['miktar'];
                        $raw_fiyat = isset($fiyatlar[$detay_id]) ? trim((string) $fiyatlar[$detay_id]) : '';
                        $norm_fiyat = str_replace(',', '.', $raw_fiyat);

                        if ($raw_fiyat === '' || !is_numeric($norm_fiyat) || (float) $norm_fiyat <= 0) {
                            throw new Exception("Tüm satırlar için birim fiyat 0'dan büyük olmalıdır.");
                        }

                        $birim_fiyat = round((float) $norm_fiyat, 2);
                        $toplam_fiyat = round($miktar * $birim_fiyat, 2);

                        $sql_upd = "UPDATE siparis_detaylari
                                    SET birim_fiyat = $birim_fiyat,
                                        toplam_fiyat = $toplam_fiyat
                                    WHERE id = $detay_id AND siparis_id = $siparis_id";
                        if (!$baglanti->query($sql_upd)) {
                            throw new Exception("Satır fiyatı güncellenemedi: " . $baglanti->error);
                        }
                    }

                    $toplam_res = $baglanti->query("SELECT COALESCE(SUM(toplam_fiyat), 0) AS toplam FROM siparis_detaylari WHERE siparis_id = $siparis_id");
                    if (!$toplam_res) {
                        throw new Exception("Sipariş toplamı hesaplanamadı: " . $baglanti->error);
                    }
                    $toplam = (float) $toplam_res->fetch_assoc()['toplam'];

                    if (!$baglanti->query("UPDATE siparisler SET toplam_tutar = $toplam, genel_toplam = $toplam WHERE id = $siparis_id")) {
                        throw new Exception("Sipariş toplamı güncellenemedi: " . $baglanti->error);
                    }

                    $baglanti->commit();
                    $mesaj = "✅ Fiyatlandırma kaydedildi.";
                } catch (Exception $e) {
                    $baglanti->rollback();
                    $hata = $e->getMessage();
                }
            }
        }
    }
}

// --- 4. SEVKİYAT GİRİŞİ (Parçalı Sevkiyat) ---
}
if (isset($_POST["sevkiyat_gir"])) {
    if (!yazmaYetkisiVar($baglanti)) {
        $hata = "Bu işlem için yazma yetkiniz bulunmuyor.";
    } else {
        $siparis_id = (int) $_POST["siparis_id"];
        $plaka = $baglanti->real_escape_string($_POST["plaka"]);
        $sevk_tarihi = $_POST["sevk_tarihi"];

        $siparis_durum = '';
        $siparis_durum_res = $baglanti->query("SELECT durum FROM siparisler WHERE id = $siparis_id LIMIT 1");
        if (!$siparis_durum_res || $siparis_durum_res->num_rows === 0) {
            $hata = "Sipariş bulunamadı.";
        } else {
            $siparis_durum = $siparis_durum_res->fetch_assoc()['durum'] ?? '';
        }

        if ($siparis_durum === 'IptalEdildi') {
            $hata = "İptal edilen sipariş sevk edilemez.";
        } elseif ($siparis_durum === 'TeslimEdildi') {
            $hata = "Teslim edilmiş sipariş için tekrar sevkiyat yapılamaz.";
        } else {

        $eksik_fiyat_res = $baglanti->query("SELECT COUNT(*) AS eksik_sayi
                                             FROM siparis_detaylari
                                             WHERE siparis_id = $siparis_id
                                               AND (birim_fiyat IS NULL OR birim_fiyat <= 0)");
        $eksik_fiyat_sayi = ($eksik_fiyat_res) ? (int) $eksik_fiyat_res->fetch_assoc()['eksik_sayi'] : 0;

        if ($eksik_fiyat_sayi > 0) {
            $hata = "Bu sipariş sevk edilemez. Önce tüm satırlar için birim fiyatı 0'dan büyük şekilde kaydedin.";
        } else {
            $sevk_var = false;
            $hata_log = [];

            if (isset($_POST["sevk_miktar"]) && is_array($_POST["sevk_miktar"])) {
                foreach ($_POST["sevk_miktar"] as $detay_id => $miktar) {
                    $miktar = (int) $miktar;
                    if ($miktar > 0) {
                        $sevk_var = true;

                        // 1. Detay tablosunda sevk edilen miktarı güncelle
                        $sql_upd = "UPDATE siparis_detaylari SET sevk_edilen_miktar = sevk_edilen_miktar + $miktar WHERE id = $detay_id";
                        if (!$baglanti->query($sql_upd)) {
                            $hata_log[] = "Ürün ID $detay_id güncellenemedi: " . $baglanti->error;
                            continue;
                        }

                        // 2. Ürün adını al
                        $detay_res = $baglanti->query("SELECT urun_adi FROM siparis_detaylari WHERE id=$detay_id");
                        if (!$detay_res) {
                            $hata_log[] = "Ürün adı alınamadı: " . $baglanti->error;
                            continue;
                        }

                        $detay = $detay_res->fetch_assoc();
                        $urun_adi = $baglanti->real_escape_string($detay['urun_adi']);

                        // 3. Sevkiyat logu ekle
                        $sql_ins = "INSERT INTO sevkiyat_detaylari (siparis_id, urun_adi, miktar, sevk_tarihi, plaka) 
                                    VALUES ($siparis_id, '$urun_adi', $miktar, '$sevk_tarihi', '$plaka')";
                        if (!$baglanti->query($sql_ins)) {
                            $hata_log[] = "Sevkiyat detay eklenemedi: " . $baglanti->error;
                        }
                    }
                }
            }

            if ($sevk_var && empty($hata_log)) {
                $toplam_sip_res = $baglanti->query("SELECT SUM(miktar) as t FROM siparis_detaylari WHERE siparis_id=$siparis_id");
                $toplam_sevk_res = $baglanti->query("SELECT SUM(sevk_edilen_miktar) as t FROM siparis_detaylari WHERE siparis_id=$siparis_id");

                $toplam_sip = ($toplam_sip_res) ? $toplam_sip_res->fetch_assoc()['t'] : 0;
                $toplam_sevk = ($toplam_sevk_res) ? $toplam_sevk_res->fetch_assoc()['t'] : 0;

                $yeni_durum = ($toplam_sip > 0 && $toplam_sevk >= $toplam_sip) ? 'TeslimEdildi' : 'KismiSevk';
                $baglanti->query("UPDATE siparisler SET durum='$yeni_durum' WHERE id=$siparis_id");

                $mesaj = "✅ Sevkiyat kaydedildi. Sipariş Durumu: $yeni_durum";
            } else {
                if (!empty($hata_log)) {
                    $hata = "Sevkiyat sırasında bazı hatalar oluştu: <br>" . implode("<br>", $hata_log);
                } else {
                    $hata = "Lütfen sevk edilecek miktarları girin.";
                }
            }
        }
        }
    }
}

// VERİLERİ ÇEK
if (isset($_POST["siparis_iptal"])) {
    if (!yazmaYetkisiVar($baglanti)) {
        $hata = "Bu işlem için yazma yetkiniz bulunmuyor.";
    } else {
        $siparis_id = isset($_POST["siparis_id"]) ? (int) $_POST["siparis_id"] : 0;
        if ($siparis_id <= 0) {
            $hata = "Geçersiz sipariş bilgisi.";
        } else {
            $siparis_res = $baglanti->query("SELECT siparis_kodu, durum FROM siparisler WHERE id = $siparis_id LIMIT 1");
            if (!$siparis_res || $siparis_res->num_rows === 0) {
                $hata = "Sipariş bulunamadı.";
            } else {
                $siparis = $siparis_res->fetch_assoc();
                $durum = $siparis['durum'] ?? '';
                $siparis_kodu = $siparis['siparis_kodu'] ?? ("#" . $siparis_id);

                if ($durum === 'IptalEdildi') {
                    $hata = "Sipariş zaten iptal edilmiş.";
                } elseif ($durum === 'TeslimEdildi') {
                    $hata = "Teslim edilmiş sipariş iptal edilemez.";
                } else {
                    if ($baglanti->query("UPDATE siparisler SET durum = 'IptalEdildi' WHERE id = $siparis_id")) {
                        $mesaj = "✅ Sipariş iptal edildi: $siparis_kodu";
                    } else {
                        $hata = "Sipariş iptal edilirken hata oluştu: " . $baglanti->error;
                    }
                }
            }
        }
    }
}

$musteriler = $baglanti->query("SELECT * FROM musteriler ORDER BY firma_adi");
$siralamada_olusturma_var = false;
$kolon_kontrol = $baglanti->query("SHOW COLUMNS FROM siparisler LIKE 'olusturma_tarihi'");
if ($kolon_kontrol && $kolon_kontrol->num_rows > 0) {
    $siralamada_olusturma_var = true;
}
$siralama_kolonu = $siralamada_olusturma_var ? "s.olusturma_tarihi" : "s.siparis_tarihi";

$aktif_siparisler = $baglanti->query("SELECT s.*, m.firma_adi, m.cari_kod
    FROM siparisler s
    JOIN musteriler m ON s.musteri_id = m.id
    WHERE s.durum NOT IN ('TeslimEdildi', 'IptalEdildi')
    ORDER BY $siralama_kolonu DESC, s.id DESC");

$gecmis_siparisler = $baglanti->query("SELECT s.*, m.firma_adi, m.cari_kod
    FROM siparisler s
    JOIN musteriler m ON s.musteri_id = m.id
    WHERE s.durum IN ('TeslimEdildi', 'IptalEdildi')
    ORDER BY $siralama_kolonu DESC, s.id DESC");
$aktif_siparis_sayisi = ($aktif_siparisler && isset($aktif_siparisler->num_rows)) ? (int) $aktif_siparisler->num_rows : 0;
$gecmis_siparis_sayisi = ($gecmis_siparisler && isset($gecmis_siparisler->num_rows)) ? (int) $gecmis_siparisler->num_rows : 0;
$urunler_list = $baglanti->query("SELECT * FROM urunler"); // Ürün listesi (dropdown için)

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Satış & Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <style>
        /* ── Genel ── */
        .progress-bar-striped { transition: width .6s ease; }

        /* ── Sipariş kartları ── */
        .siparis-kart { margin-bottom: 1.25rem; }
        .siparis-kart .card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eef2f7;
        }

        /* ── Filtre satırları ── */
        .filtre-satiri {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            align-items: center;
            padding: 0.6rem 1rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            overflow-x: auto;
        }
        .filtre-satiri .filtre-grup {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex: 0 0 auto;
        }
        .filtre-satiri .filtre-grup label {
            white-space: nowrap;
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }
        .filtre-satiri .filtre-grup select,
        .filtre-satiri .filtre-grup input {
            min-width: 180px;
        }

        /* ── DataTables gizle/göster ── */
        #aktifSiparisTablo_wrapper .dataTables_paginate,
        #aktifSiparisTablo_wrapper .dataTables_length,
        #aktifSiparisTablo_wrapper .dataTables_info { display: none !important; }

        /* ── Sıralama okları ── */
        #aktifSiparisTablo thead th,
        #gecmisSiparisTablo thead th {
            position: relative;
            padding-left: 1.35rem !important;
        }
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting:before,
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting_asc:before,
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting_desc:before,
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting:after,
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting_asc:after,
        #aktifSiparisTablo_wrapper table.dataTable thead .sorting_desc:after,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting:before,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting_asc:before,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting_desc:before,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting:after,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting_asc:after,
        #gecmisSiparisTablo_wrapper table.dataTable thead .sorting_desc:after {
            left: 0.35rem !important;
            right: auto !important;
        }

        /* ── İşlem butonları hücresi ── */
        .siparis-islem-listesi {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
        }
        .siparis-islem-listesi .btn { white-space: nowrap; }
        .siparis-islem-listesi form { margin: 0; display: inline-flex; }

        /* ── Responsive tablo kırılma noktaları ── */
        @media (max-width: 1199px) {
            .siparis-islem-listesi .btn {
                font-size: 0.78rem;
                padding: 0.22rem 0.45rem;
            }
        }
        @media (max-width: 991px) {
            /* İşlem kolonunu daha dar tut; DataTables responsive geri kalanları sarar */
            .filtre-satiri .filtre-grup select,
            .filtre-satiri .filtre-grup input { min-width: 160px; }
        }
        @media (max-width: 575px) {
            .siparis-islem-listesi .btn {
                font-size: 0.72rem;
                padding: 0.18rem 0.4rem;
            }
            .filtre-satiri .filtre-grup select,
            .filtre-satiri .filtre-grup input { min-width: 140px; }
            .siparis-kart .card-header { flex-direction: column; align-items: flex-start !important; }
        }

        /* UI refresh inspired by satin_alma.php */
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --surface: #ffffff;
            --accent: #f59e0b;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --line-soft: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f1f5f9 !important;
            color: var(--text-main);
        }

        .page-wrap {
            max-width: 1680px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-bg) 0%, var(--secondary-bg) 100%);
            color: #fff;
            border-radius: 1.25rem;
            margin-top: 1.25rem;
            margin-bottom: 1.4rem;
            padding: 1.55rem 1.7rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 28px -14px rgba(15, 23, 42, 0.55);
        }

        .page-header::before {
            content: "";
            position: absolute;
            top: -65%;
            right: -10%;
            width: 440px;
            height: 440px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0) 72%);
            pointer-events: none;
        }

        .page-header .header-title {
            font-weight: 700;
            margin: 0 0 0.35rem 0;
        }

        .page-header .header-subtitle {
            color: rgba(255, 255, 255, 0.78);
            margin: 0;
            font-size: 0.92rem;
        }

        .header-stats {
            display: flex;
            gap: 0.65rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .stat-card {
            min-width: 142px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 0.9rem;
            padding: 0.65rem 0.85rem;
            text-align: center;
            backdrop-filter: blur(8px);
        }

        .stat-card .stat-label {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.67rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.2rem;
        }

        .stat-card .stat-value {
            color: #fff;
            font-size: 1.38rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .header-actions {
            display: flex;
            gap: 0.55rem;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 0.95rem;
            position: relative;
            z-index: 1;
        }

        .header-actions .btn {
            border-radius: 999px;
            padding: 0.44rem 1rem;
            font-weight: 600;
            box-shadow: 0 8px 16px -10px rgba(15, 23, 42, 0.8);
        }

        .siparis-kart {
            border-radius: 1.1rem !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.06), 0 12px 26px -18px rgba(15, 23, 42, 0.28) !important;
            overflow: hidden;
        }

        .siparis-kart .card-header {
            background: var(--surface) !important;
            border-bottom: 1px solid var(--line-soft);
            padding: 0.95rem 1.15rem;
        }

        .siparis-kart .card-header h5 {
            color: #0f172a;
            font-weight: 700;
        }

        .filtre-satiri {
            gap: 0.65rem;
            padding: 0.62rem 1.1rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--line-soft);
            overflow-x: auto;
            flex-wrap: nowrap;
        }

        .filtre-satiri .filtre-grup label {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .filtre-satiri .filtre-grup select,
        .filtre-satiri .filtre-grup input {
            border-radius: 0.6rem;
            border-color: #cbd5e1;
            min-width: 190px;
        }

        .filtre-satiri::-webkit-scrollbar {
            height: 6px;
        }

        .filtre-satiri::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .siparis-kart .table thead th {
            background: #f8fafc !important;
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.045em;
            border-bottom: 1px solid var(--line-soft);
            padding-top: 1.05rem;
            padding-bottom: 1.05rem;
            vertical-align: middle;
        }

        .siparis-kart .table tbody td {
            border-color: #edf2f7;
            padding-top: 0.95rem;
            padding-bottom: 0.95rem;
            vertical-align: middle;
        }

        .siparis-kart .table-hover tbody tr:hover {
            background: #f8fafc;
        }

        .siparis-islem-listesi {
            gap: 0.3rem;
        }

        .siparis-islem-listesi .btn {
            border-radius: 0.55rem;
            font-weight: 500;
        }

        #gecmisSiparisTablo_wrapper .dataTables_info {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0;
            padding: 0;
        }

        #gecmisSiparisTablo_wrapper .pagination {
            margin: 0;
            gap: 0.3rem;
        }

        #gecmisSiparisTablo_wrapper .page-link {
            border-radius: 0.55rem !important;
            border-color: #d1d5db;
            color: #334155;
            min-width: 2rem;
            text-align: center;
        }

        #gecmisSiparisTablo_wrapper .page-item.active .page-link {
            background-color: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }

        @media (max-width: 991px) {
            .page-header {
                padding: 1.3rem 1.1rem;
            }

            .header-stats {
                justify-content: flex-start;
                margin-top: 0.95rem;
            }

            .header-actions {
                justify-content: flex-start;
            }

            .filtre-satiri .filtre-grup select,
            .filtre-satiri .filtre-grup input {
                min-width: 165px;
            }
        }

        @media (max-width: 575px) {
            .filtre-satiri .filtre-grup select,
            .filtre-satiri .filtre-grup input {
                min-width: 145px;
            }
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container-fluid px-md-4 pb-4 page-wrap">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 d-none legacy-page-head">
            <h2><i class="fas fa-shopping-bag text-primary"></i> Satış & Sipariş Yönetimi</h2>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#yeniMusteriModal">
                    <i class="fas fa-user-plus"></i> Müşteri Ekle
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniSiparisModal">
                    <i class="fas fa-plus-circle"></i> Sipariş Oluştur
                </button>
            </div>
        </div>



        <!-- SİPARİŞ LİSTESİ -->
        <div class="page-header">
            <div class="row align-items-center g-3">
                <div class="col-lg-6">
                    <h2 class="header-title"><i class="fas fa-shopping-bag me-2"></i>Satış & Sipariş Yönetimi</h2>
                    <p class="header-subtitle">Siparişleri tek ekrandan fiyatlandırın, sevk edin ve geçmiş kayıtları izleyin.</p>
                </div>
                <div class="col-lg-6">
                    <div class="header-stats">
                        <div class="stat-card">
                            <div class="stat-label">Aktif Sipariş</div>
                            <div class="stat-value"><?php echo $aktif_siparis_sayisi; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Geçmiş Sipariş</div>
                            <div class="stat-value"><?php echo $gecmis_siparis_sayisi; ?></div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#yeniMusteriModal">
                            <i class="fas fa-user-plus me-1"></i> Müşteri Ekle
                        </button>
                        <button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#yeniSiparisModal">
                            <i class="fas fa-plus-circle me-1"></i> Sipariş Oluştur
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm siparis-kart">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0"><i class="fas fa-bolt text-warning me-1"></i> Aktif Siparişler</h5>
            </div>
            <div class="filtre-satiri">
                <div class="filtre-grup">
                    <label for="aktifDurumFiltre">Durum</label>
                    <select id="aktifDurumFiltre" class="form-select form-select-sm">
                        <option value="">Tüm durumlar</option>
                        <option value="Bekliyor">Bekliyor</option>
                        <option value="Hazirlaniyor">Hazırlanıyor</option>
                        <option value="KismiSevk">Kısmi Sevk</option>
                    </select>
                </div>
                <div class="filtre-grup">
                    <label for="aktifSearchInput">Ara:</label>
                    <input type="text" id="aktifSearchInput" class="form-control form-control-sm" placeholder="Müşteri, sipariş no...">
                </div>
            </div>
            <div class="table-responsive">
                <table id="aktifSiparisTablo" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="dt-head-left" data-priority="4">Cari Kod</th>
                            <th class="dt-head-left" data-priority="1">Müşteri</th>
                            <th class="dt-head-left" data-priority="3">Sipariş Tarihi</th>
                            <th class="dt-head-left" data-priority="2">Durum</th>
                            <th class="dt-head-left" data-priority="5">İlerleme</th>
                            <th class="dt-head-left" data-priority="6">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($aktif_siparisler && $aktif_siparisler->num_rows > 0) {
                            while ($s = $aktif_siparisler->fetch_assoc()) {
                                // İlerleme Hesabı
                                $detaylar = $baglanti->query("
                                    SELECT 
                                        COUNT(*) as satir_sayisi,
                                        SUM(miktar) as top,
                                        SUM(sevk_edilen_miktar) as sevk,
                                        SUM(CASE WHEN birim_fiyat IS NULL OR birim_fiyat <= 0 THEN 1 ELSE 0 END) as eksik_fiyat_sayisi
                                    FROM siparis_detaylari
                                    WHERE siparis_id={$s['id']}
                                ")->fetch_assoc();
                                $yuzde = ($detaylar['top'] > 0) ? round(($detaylar['sevk'] / $detaylar['top']) * 100) : 0;
                                $fiyat_tamam = ((int) $detaylar['satir_sayisi'] > 0) && ((int) $detaylar['eksik_fiyat_sayisi'] === 0);
                                $iptal_durumu = ($s['durum'] == 'IptalEdildi');

                                $renk = 'secondary';
                                if ($s['durum'] == 'Bekliyor')
                                    $renk = 'warning';
                                if ($s['durum'] == 'Hazirlaniyor')
                                    $renk = 'info';
                                if ($s['durum'] == 'KismiSevk')
                                    $renk = 'primary';
                                if ($s['durum'] == 'TeslimEdildi')
                                    $renk = 'success';
                                if ($s['durum'] == 'IptalEdildi')
                                    $renk = 'danger';
                                ?>
                                <tr>
                                    <td><small class="fw-bold"><?php echo $s['cari_kod']; ?></small></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $s['firma_adi']; ?></div>
                                        <div class="small text-muted">
                                            <?php echo $s['siparis_kodu']; ?>
                                            <?php if ($fiyat_tamam): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Fiyat Tamam</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1">Fiyat Eksik</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php
                                    $liste_tarihi_raw = !empty($s['olusturma_tarihi']) ? $s['olusturma_tarihi'] : (($s['siparis_tarihi'] ?? '') . ' 00:00:00');
                                    $liste_tarihi_ts = strtotime($liste_tarihi_raw);
                                    ?>
                                    <td data-order="<?php echo $liste_tarihi_ts ? $liste_tarihi_ts : 0; ?>">
                                        <?php echo $liste_tarihi_ts ? date("d.m.Y H:i", $liste_tarihi_ts) : "-"; ?>
                                    </td>
                                    <td><span class="badge bg-<?php echo $renk; ?>">
                                            <?php echo $s['durum']; ?>
                                        </span></td>
                                    <td style="width: 150px;">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar progress-bar-striped bg-<?php echo $renk; ?>"
                                                role="progressbar" style="width: <?php echo $yuzde; ?>%"></div>
                                        </div>
                                        <small class="text-muted">%
                                            <?php echo $yuzde; ?> Tamamlandı
                                        </small>
                                    </td>
                                    <td class="siparis-islem-hucre">
                                        <div class="siparis-islem-listesi d-flex flex-wrap gap-1 align-items-center">
                                        <button class="btn btn-sm btn-info text-white"
                                            onclick="detayAc(<?php echo $s['id']; ?>)">
                                            <i class="fas fa-eye"></i> Detay
                                        </button>
                                        <?php if (!$iptal_durumu): ?>
                                        <button class="btn btn-sm btn-warning text-dark"
                                            onclick='fiyatAc(<?php echo (int) $s["id"]; ?>, <?php echo json_encode($s["siparis_kodu"]); ?>)'>
                                            <i class="fas fa-tags"></i> Fiyatlandır
                                        </button>
                                        <?php if ($yuzde < 100) { ?>
                                            <?php if ($fiyat_tamam): ?>
                                                <button class="btn btn-sm btn-dark"
                                                    onclick='sevkAc(<?php echo (int) $s["id"]; ?>, <?php echo json_encode($s["siparis_kodu"]); ?>)'>
                                                    <i class="fas fa-truck"></i> Sevk Et
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-dark" disabled title="Önce fiyatlandırma yapın.">
                                                    <i class="fas fa-truck"></i> Sevk Et
                                                </button>
                                            <?php endif; ?>
                                        <?php } ?>
                                        <?php if ($s['durum'] != 'TeslimEdildi'): ?>
                                            <form method="post" class="d-inline"
                                                onsubmit="return siparisIptalOnay(this, '<?php echo addslashes($s['siparis_kodu']); ?>');">
                                                <input type="hidden" name="siparis_id" value="<?php echo (int) $s['id']; ?>">
                                                <input type="hidden" name="siparis_iptal" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-ban"></i> Siparişi İptal Et
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Sipariş İptal Edildi</span>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm siparis-kart">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0"><i class="fas fa-history text-secondary me-1"></i> Geçmiş Siparişler</h5>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="small text-muted">Sayfada</span>
                    <select id="gecmisPageLength" class="form-select form-select-sm" style="width:auto;">
                        <option value="10" selected>10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="-1">Tümü</option>
                    </select>
                    <span class="small text-muted">kayıt göster</span>
                </div>
            </div>
            <div class="filtre-satiri">
                <div class="filtre-grup">
                    <label for="gecmisDurumFiltre">Durum</label>
                    <select id="gecmisDurumFiltre" class="form-select form-select-sm">
                        <option value="">Tüm durumlar</option>
                        <option value="TeslimEdildi">Teslim Edildi</option>
                        <option value="IptalEdildi">İptal Edildi</option>
                    </select>
                </div>
                <div class="filtre-grup">
                    <label for="gecmisSearchInput">Ara:</label>
                    <input type="text" id="gecmisSearchInput" class="form-control form-control-sm" placeholder="Müşteri, sipariş no...">
                </div>
            </div>
            <div class="table-responsive">
                <table id="gecmisSiparisTablo" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th data-priority="4">Cari Kod</th>
                            <th data-priority="1">Müşteri</th>
                            <th data-priority="3">Sipariş Tarihi</th>
                            <th data-priority="2">Durum</th>
                            <th data-priority="6">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($gecmis_siparisler && $gecmis_siparisler->num_rows > 0) {
                            while ($s = $gecmis_siparisler->fetch_assoc()) {
                                $renk = ($s['durum'] == 'TeslimEdildi') ? 'success' : 'danger';
                                $liste_tarihi_raw = !empty($s['olusturma_tarihi']) ? $s['olusturma_tarihi'] : (($s['siparis_tarihi'] ?? '') . ' 00:00:00');
                                $liste_tarihi_ts = strtotime($liste_tarihi_raw);
                                ?>
                                <tr>
                                    <td><small class="fw-bold"><?php echo $s['cari_kod']; ?></small></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $s['firma_adi']; ?></div>
                                        <div class="small text-muted"><?php echo $s['siparis_kodu']; ?></div>
                                    </td>
                                    <td data-order="<?php echo $liste_tarihi_ts ? $liste_tarihi_ts : 0; ?>">
                                        <?php echo $liste_tarihi_ts ? date("d.m.Y H:i", $liste_tarihi_ts) : "-"; ?>
                                    </td>
                                    <td><span class="badge bg-<?php echo $renk; ?>"><?php echo $s['durum']; ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-info text-white" onclick="detayAc(<?php echo $s['id']; ?>)">
                                            <i class="fas fa-eye"></i> Detay
                                        </button>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL: YENİ SİPARİŞ (BASİT) -->
    <div class="modal fade" id="yeniSiparisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Yeni Sipariş Oluştur</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label>Müşteri Seçin</label>
                                <select name="musteri_id" class="form-select" required>
                                    <option value="">Seçiniz...</option>
                                    <?php
                                    if ($musteriler && $musteriler->num_rows > 0) {
                                        $musteriler->data_seek(0);
                                        while ($m = $musteriler->fetch_assoc()) {
                                            echo "<option value='{$m['id']}'>[{$m['cari_kod']}] {$m['firma_adi']}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-3">
                                <label>Sipariş Tarihi</label>
                                <input type="date" name="siparis_tarihi" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-3">
                                <label>Teslim Tarihi</label>
                                <input type="date" name="teslim_tarihi" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Notlar</label>
                            <input type="text" name="aciklama" class="form-control" placeholder="Örn: Acil sipariş">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label>Alıcı Adı / Soyadı</label>
                                <input type="text" name="alici_adi" class="form-control" placeholder="Teslim alacak kişi/firma">
                            </div>
                            <div class="col-md-6">
                                <label>Planlanan Ödeme Tarihi</label>
                                <input type="date" name="odeme_tarihi" class="form-control">
                            </div>
                        </div>
                        <hr>
                        <h6>Ürünler</h6>
                        <div id="urunListesi">
                            <div class="row mb-2">
                                <div class="col-6">
                                    <select name="urunler[]" class="form-select" required>
                                        <option value="">Ürün Seç...</option>
                                        <?php
                                        $urunler_list->data_seek(0);
                                        while ($u = $urunler_list->fetch_assoc())
                                            echo "<option value='{$u['urun_adi']}'>{$u['urun_adi']}</option>";
                                        ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="miktarlar[]" class="form-control" placeholder="Miktar"
                                        required>
                                </div>
                                <div class="col-3">
                                    <select name="birimler[]" class="form-select">
                                        <option value="Adet">Adet</option>
                                        <option value="Kg">Kg</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="satirEkle()">+ Ürün
                            Ekle</button>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="siparis_olustur" class="btn btn-primary">Siparişi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SEVKİYAT GİRİŞ -->
    <div class="modal fade" id="sevkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">Sevkiyat Çıkışı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="siparis_id" id="sevkSiparisId">
                        <p id="sevkBaslik" class="fw-bold text-primary"></p>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label>Sevk Tarihi</label>
                                <input type="date" name="sevk_tarihi" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label>Araç Plaka</label>
                                <input type="text" name="plaka" class="form-control" placeholder="34 ABC 123" required>
                            </div>
                        </div>

                        <h6>Sevk Edilecek Miktarlar:</h6>
                        <div id="sevkUrunListesi">Yükleniyor...</div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="sevkiyat_gir" class="btn btn-primary">Sevkiyatı Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: FİYATLANDIRMA -->
    <div class="modal fade" id="fiyatModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Sipariş Fiyatlandırma</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="siparis_id" id="fiyatSiparisId">
                        <p id="fiyatBaslik" class="fw-bold text-primary"></p>
                        <div class="alert alert-info py-2 small">
                            Tüm satırlar için birim fiyat <strong>0'dan büyük</strong> olmalıdır. Fiyatlandırma tamamlanmadan sevk işlemi yapılamaz.
                        </div>
                        <div id="fiyatUrunListesi">Yükleniyor...</div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="fiyatlandirma_kaydet" class="btn btn-warning text-dark fw-bold">
                            <i class="fas fa-save me-1"></i> Fiyatları Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SİPARİŞ DETAY -->
    <div class="modal fade" id="detayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sipariş Detayı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detayIcerik">
                    Yükleniyor...
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: YENİ MÜŞTERİ (CRM UYUMLU) -->
    <div class="modal fade" id="yeniMusteriModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Hızlı Müşteri Kaydı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="row g-2">
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-bold small">Cari Kod *</label>
                                <input type="text" name="cari_kod" class="form-control" placeholder="120.XX.XXX" required>
                                <div class="form-text small" style="font-size:0.65rem;">120: Müşteri, 320: Tedarikçi</div>
                            </div>
                            <div class="col-md-7 mb-3">
                                <label class="form-label fw-bold small">Firma Ünvanı *</label>
                                <input type="text" name="firma_adi" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Yetkili Kişi</label>
                                <input type="text" name="yetkili_kisi" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Telefon</label>
                                <input type="text" name="telefon" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Açık Adres</label>
                            <textarea name="adres" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="fas fa-info-circle me-1"></i> Daha detaylı vergi ve iletişim bilgileri için <a href="musteriler.php" class="alert-link">Müşteri Yönetimi</a> sayfasını kullanın.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="musteri_ekle" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // SweetAlert2 Alerts
            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ "], "", strip_tags($mesaj))); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", strip_tags($hata))); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>

            if (window.jQuery && $.fn.DataTable) {
                const ortakDil = { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" };

                const aktifTablo = $('#aktifSiparisTablo').DataTable({
                    language: ortakDil,
                    order: [[2, 'desc']],
                    paging: false,
                    lengthChange: false,
                    info: false,
                    dom: 't',
                    autoWidth: false,
                    responsive: {
                        details: {
                            type: 'inline',
                            target: 'tr'
                        }
                    },
                    columnDefs: [
                        { targets: [5], orderable: false, responsivePriority: 6 },
                        { targets: [0], responsivePriority: 4 },
                        { targets: [1], responsivePriority: 1 },
                        { targets: [2], responsivePriority: 3 },
                        { targets: [3], responsivePriority: 2 },
                        { targets: [4], responsivePriority: 5 }
                    ]
                });

                $('#aktifSearchInput').on('input', function () {
                    aktifTablo.search(this.value).draw();
                });

                $('#aktifDurumFiltre').on('change', function () {
                    const val = this.value;
                    aktifTablo.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
                });

                const gecmisTablo = $('#gecmisSiparisTablo').DataTable({
                    language: ortakDil,
                    order: [[2, 'desc']],
                    pageLength: 10,
                    dom: 't<"d-flex justify-content-between align-items-center flex-wrap gap-2 px-2 py-2 border-top"ip>',
                    autoWidth: false,
                    responsive: {
                        details: {
                            type: 'inline',
                            target: 'tr'
                        }
                    },
                    columnDefs: [
                        { targets: [4], orderable: false, responsivePriority: 6 },
                        { targets: [0], responsivePriority: 4 },
                        { targets: [1], responsivePriority: 1 },
                        { targets: [2], responsivePriority: 3 },
                        { targets: [3], responsivePriority: 2 }
                    ]
                });

                $('#gecmisDurumFiltre').on('change', function () {
                    const val = this.value;
                    gecmisTablo.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
                });

                $('#gecmisSearchInput').on('input', function () {
                    gecmisTablo.search(this.value).draw();
                });

                $('#gecmisPageLength').on('change', function () {
                    gecmisTablo.page.len(parseInt(this.value)).draw();
                });
            }
        });

        function satirEkle() {
            const row = document.querySelector('#urunListesi .row').cloneNode(true);
            row.querySelectorAll('input').forEach(i => i.value = '');
            document.getElementById('urunListesi').appendChild(row);
        }

        function formatTl(value) {
            const fixed = Number(value).toFixed(2);
            const parts = fixed.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return parts.join(',') + ' TL';
        }

        function updateFiyatSatirToplam(inputEl) {
            const row = inputEl.closest('tr');
            if (!row) return;

            const hucre = row.querySelector('.satir-toplam-hucre');
            if (!hucre) return;

            const miktar = parseFloat(inputEl.dataset.miktar || '0');
            const fiyatRaw = (inputEl.value || '').replace(',', '.');
            const fiyat = parseFloat(fiyatRaw);

            if (!Number.isFinite(fiyat) || fiyat <= 0) {
                hucre.textContent = '0,00 TL';
                return;
            }

            const toplam = miktar * fiyat;
            hucre.textContent = formatTl(toplam);
        }

        function siparisIptalOnay(form, siparisKodu) {
            const onayMesaji = siparisKodu + ' nolu sipari\u015fi iptal etmek istedi\u011finize emin misiniz?';

            if (typeof Swal === 'undefined') {
                const onay = confirm(onayMesaji);
                if (onay && form) {
                    form.submit();
                }
                return false;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Sipari\u015fi iptal et',
                text: onayMesaji,
                showCancelButton: true,
                confirmButtonText: 'Evet, iptal et',
                cancelButtonText: 'Vazge\u00e7',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });

            return false;
        }

        function sevkAc(id, kod) {
            document.getElementById('sevkSiparisId').value = id;
            document.getElementById('sevkBaslik').textContent = kod + ' Nolu Sipariş';

            // AJAX ile ürünleri çek
            fetch('siparis_ajax.php?islem=getir_sevk&id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('sevkUrunListesi').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('sevkModal')).show();
                });
        }

        function fiyatAc(id, kod) {
            document.getElementById('fiyatSiparisId').value = id;
            document.getElementById('fiyatBaslik').textContent = kod + ' Nolu Sipariş Fiyatlandırması';

            fetch('siparis_ajax.php?islem=getir_fiyatlandirma&id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('fiyatUrunListesi').innerHTML = html;

                    document.querySelectorAll('#fiyatUrunListesi .birim-fiyat-input').forEach(function (inp) {
                        updateFiyatSatirToplam(inp);
                    });

                    new bootstrap.Modal(document.getElementById('fiyatModal')).show();
                });
        }

        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('birim-fiyat-input')) {
                updateFiyatSatirToplam(e.target);
            }
        });

        function detayAc(id) {
            fetch('siparis_ajax.php?islem=getir_detay&id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('detayIcerik').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detayModal')).show();
                });
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>