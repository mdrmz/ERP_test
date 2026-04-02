<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) { header("Location: login.php"); exit; }
sayfaErisimKontrol($baglanti);

$mesaj = "";
$hata = "";
$force_tab = '';

$pacal_tarih_degeri = trim($_POST["pacal_tarih"] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pacal_tarih_degeri)) {
    $pacal_tarih_degeri = date('Y-m-d');
}
$pacal_urun_degeri = trim($_POST["urun_adi"] ?? '');
$pacal_notlar_degeri = trim($_POST["pacal_notlar"] ?? '');

function sonrakiPacalPartiNo(mysqli $baglanti, string $tarihYmd): string {
    if (!preg_match('/^\d{8}$/', $tarihYmd)) {
        $tarihYmd = date('Ymd');
    }

    $prefix = "PCL-" . $tarihYmd . "-";
    $prefixEsc = $baglanti->real_escape_string($prefix);
    $res = $baglanti->query("SELECT MAX(CAST(SUBSTRING_INDEX(parti_no, '-', -1) AS UNSIGNED)) AS max_sira FROM uretim_pacal WHERE parti_no LIKE '{$prefixEsc}%'");
    $maxSira = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $maxSira = (int)($row['max_sira'] ?? 0);
    }

    return $prefix . str_pad((string)($maxSira + 1), 2, '0', STR_PAD_LEFT);
}

// ==================== BACKEND İŞLEMLERİ ====================

// --- PAÇAL KAYDET ---
if (isset($_POST["pacal_kaydet"])) {
    $force_tab = 'pacal';

    $tarih_raw = trim($_POST["pacal_tarih"] ?? '');
    $urun_adi_raw = trim($_POST["urun_adi"] ?? '');
    $parti_no_raw = trim($_POST["pacal_parti_no"] ?? '');
    $notlar_raw = trim($_POST["pacal_notlar"] ?? '');

    $pacal_tarih_degeri = $tarih_raw;
    $pacal_urun_degeri = $urun_adi_raw;
    $pacal_notlar_degeri = $notlar_raw;

    $tarih = $baglanti->real_escape_string($tarih_raw);
    $urun_adi = $baglanti->real_escape_string($urun_adi_raw);
    $parti_no = $baglanti->real_escape_string($parti_no_raw);
    $notlar = $baglanti->real_escape_string($notlar_raw);

    if (empty($tarih_raw) || empty($urun_adi_raw) || empty($parti_no_raw)) {
        $hata = "Tarih, ürün adı ve parti numarası zorunludur.";
    }

    if (empty($hata) && !preg_match('/^PCL-\d{8}-\d{2,}$/', $parti_no_raw)) {
        $hata = "Parti numarası formatı geçersiz. Beklenen format: PCL-YYYYMMDD-01";
    }

    if (empty($hata)) {
        $tarih_parca = substr($parti_no_raw, 4, 8);
        $dt = DateTime::createFromFormat('Ymd', $tarih_parca);
        if (!$dt || $dt->format('Ymd') !== $tarih_parca) {
            $hata = "Parti numarasındaki tarih geçersiz. Beklenen format: PCL-YYYYMMDD-01";
        }
    }

    if (empty($hata)) {
        $dup = $baglanti->query("SELECT id FROM uretim_pacal WHERE parti_no = '$parti_no'");
        if ($dup && $dup->num_rows > 0) $hata = "Bu parti numarası zaten kullanılmış: $parti_no_raw";
    }

    if (empty($hata)) {
        $satirlar = $_POST["satirlar"] ?? [];
        $dolu_satir = 0; $toplam_oran = 0;
        foreach ($satirlar as $s) {
            if (!empty($s["silo_id"]) && $s["silo_id"] > 0) {
                $dolu_satir++;
                $toplam_oran += floatval($s["oran"] ?? 0);
            }
        }
        if ($dolu_satir == 0) $hata = "En az bir silo seçmelisiniz.";
        elseif (abs($toplam_oran - 100) > 0.1) $hata = "Paçal oranları toplamı %100 olmalıdır. (Şu an: " . number_format($toplam_oran,2) . ")";
    }

    if (empty($hata)) {
        $toplam_kg = 0;
        foreach ($satirlar as $s) {
            if (!empty($s["silo_id"]) && $s["silo_id"] > 0)
                $toplam_kg += floatval($s["miktar_kg"] ?? 0);
        }

        $sql = "INSERT INTO uretim_pacal (tarih, urun_adi, parti_no, toplam_miktar_kg, notlar, durum, olusturan)
                VALUES ('$tarih', '$urun_adi', '$parti_no', $toplam_kg, '$notlar', 'hazirlaniyor', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql)) {
            $pacal_id = $baglanti->insert_id;
            foreach ($satirlar as $sira => $s) {
                $silo_id = (int)($s["silo_id"] ?? 0);
                if ($silo_id <= 0) continue;

                // Silo'dan hammadde bilgisini al
                $silo_info = $baglanti->query("SELECT s.aktif_hammadde_kodu, h.id as hammadde_id, h.hammadde_kodu 
                    FROM silolar s LEFT JOIN hammaddeler h ON s.aktif_hammadde_kodu = h.hammadde_kodu 
                    WHERE s.id = $silo_id")->fetch_assoc();
                $h_id = (int)($silo_info['hammadde_id'] ?? 0);
                $kod = $baglanti->real_escape_string($s["kod"] ?? '');
                $miktar = floatval($s["miktar_kg"] ?? 0);
                $oran = floatval($s["oran"] ?? 0);

                $fields = ['gluten','g_index','n_sedim','g_sedim','hektolitre','nem',
                           'alveo_p','alveo_g','alveo_pl','alveo_w','alveo_ie','fn',
                           'perten_protein','perten_sertlik','perten_nisasta'];
                $vals = [];
                foreach ($fields as $f) {
                    $v = $s[$f] ?? '';
                    $vals[] = is_numeric($v) ? floatval($v) : 'NULL';
                }

                $sql_d = "INSERT INTO uretim_pacal_detay 
                    (pacal_id, silo_id, sira_no, hammadde_id, kod, miktar_kg, oran,
                     gluten, g_index, n_sedim, g_sedim, hektolitre, nem,
                     alveo_p, alveo_g, alveo_pl, alveo_w, alveo_ie, fn,
                     perten_protein, perten_sertlik, perten_nisasta)
                    VALUES ($pacal_id, $silo_id, $sira, $h_id, '$kod', $miktar, $oran,
                            " . implode(',', $vals) . ")";
                $baglanti->query($sql_d);
            }
            header("Location: planlama.php?tab=pacal&msg=pacal_kaydedildi&parti_no=" . urlencode($parti_no_raw));
            exit;
        } else {
            $hata = "SQL Hatası: " . $baglanti->error;
        }
    }
}
// --- PAÇAL SİL ---
if (isset($_GET["sil_pacal"])) {
    $sil_id = (int)$_GET["sil_pacal"];
    $baglanti->query("DELETE FROM uretim_pacal_detay WHERE pacal_id = $sil_id");
    $baglanti->query("DELETE FROM uretim_pacal WHERE id = $sil_id");
    header("Location: planlama.php?tab=pacal&msg=pacal_silindi");
    exit;
}

// --- HAFTALIK PLAN EKLE ---
if (isset($_POST["plan_ekle"])) {
    $force_tab = 'haftalik';
    $pUrun = $baglanti->real_escape_string(trim($_POST["plan_urun"]));
    $pMiktar = floatval($_POST["plan_miktar"]);
    $pOncelik = $baglanti->real_escape_string($_POST["plan_oncelik"]);
    $pHafta = (int)$_POST["hafta_no"];
    $pYil = (int)$_POST["yil"];
    
    if (!empty($pUrun) && $pMiktar > 0) {
        $baglanti->query("INSERT INTO haftalik_plan (hafta_no, yil, urun_adi, miktar_ton, oncelik, olusturan) 
            VALUES ($pHafta, $pYil, '$pUrun', $pMiktar, '$pOncelik', '{$_SESSION["kadi"]}')");
        $mesaj = "Haftalık plana eklendi: $pUrun";
    }
}

// --- HAFTALIK PLAN SİL ---
if (isset($_POST["plan_sil"])) {
    $force_tab = 'haftalik';
    $sil_id = (int)$_POST["sil_plan_id"];
    $baglanti->query("DELETE FROM haftalik_plan WHERE id = $sil_id");
    $mesaj = "Plan kaydı silindi.";
}

// --- YENİ REÇETE EKLEME ---
if (isset($_POST["recete_ekle"])) {
    $force_tab = 'recete';
    $ad = $baglanti->real_escape_string($_POST["recete_adi"]);
    $tav = $_POST["tav_miktar"] ?: 0;
    $sure = $_POST["sure_saat"] ?: 0;
    $isi = $_POST["sicaklik"] ?: 0;
    $nem = $_POST["hedef_nem"] ?: 0;
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"] ?? '');
    $sql = "INSERT INTO receteler (recete_adi, tav_miktar, sure_saat, sicaklik, hedef_nem, aciklama) 
            VALUES ('$ad', $tav, $sure, $isi, $nem, '$aciklama')";
    if ($baglanti->query($sql)) {
        $mesaj = "Yeni reçete tanımlandı: $ad";
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- İŞ EMRİ OLUŞTURMA ---
if (isset($_POST["is_emri_ver"])) {
    $force_tab = 'recete';
    $recete_id = (int)$_POST["recete_id"];
    $hedef_ton = $_POST["hedef_miktar"] ?: 0;
    $personel = $baglanti->real_escape_string($_POST["atanan_personel"] ?? '');
    $yikama_parti_no = $baglanti->real_escape_string($_POST["yikama_parti_no"] ?? '');
    $silo_ids = $_POST["silo_id"] ?? [];
    $silo_yuzdeleri = $_POST["silo_yuzde"] ?? [];
    $toplam_yuzde = 0; $gecerli_silolar = [];
    for ($i = 0; $i < count($silo_ids); $i++) {
        if (!empty($silo_ids[$i]) && !empty($silo_yuzdeleri[$i])) {
            $toplam_yuzde += (float)$silo_yuzdeleri[$i];
            $gecerli_silolar[] = ['silo_id'=>(int)$silo_ids[$i], 'yuzde'=>(float)$silo_yuzdeleri[$i]];
        }
    }
    if (empty($gecerli_silolar)) { $hata = "En az bir silo seçmelisiniz!"; }
    elseif (abs($toplam_yuzde - 100) > 0.1) { $hata = "Silo yüzdelerinin toplamı %100 olmalı!"; }
    else {
        $kod = "URT-" . rand(1000,9999);
        $sql = "INSERT INTO is_emirleri (is_kodu, recete_id, yikama_parti_no, hedef_miktar_ton, baslangic_tarihi, durum, atanan_personel) 
                VALUES ('$kod', $recete_id, " . ($yikama_parti_no ? "'$yikama_parti_no'" : "NULL") . ", $hedef_ton, NOW(), 'bekliyor', '$personel')";
        if ($baglanti->query($sql)) {
            $yeni_id = $baglanti->insert_id;
            foreach ($gecerli_silolar as $silo) {
                $baglanti->query("INSERT INTO is_emri_silo_karisimlari (is_emri_id, silo_id, yuzde) VALUES ($yeni_id, {$silo['silo_id']}, {$silo['yuzde']})");
            }
            $recete_adi = '';
            $rr = $baglanti->query("SELECT recete_adi FROM receteler WHERE id = $recete_id");
            if ($rr && $r = $rr->fetch_assoc()) $recete_adi = $r['recete_adi'];
            onayOlustur($baglanti, 'is_emri', $yeni_id, "İş Emri: $kod | Reçete: $recete_adi | Hedef: {$hedef_ton} ton");
            bildirimOlustur($baglanti, 'onay_bekleniyor', "Yeni İş Emri Onay Bekliyor: $kod", "Reçete: $recete_adi | Hedef: {$hedef_ton} ton", 1, null, 'is_emirleri', $yeni_id, 'onay_merkezi.php');
            systemLogKaydet($baglanti, 'INSERT', 'Planlama', "Yeni iş emri: $kod | Reçete: $recete_adi | {$hedef_ton} ton");
            $mesaj = "İş emri yayınlandı! Kod: $kod";
        } else { $hata = "Hata: " . $baglanti->error; }
    }
}

// Success msg from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'pacal_silindi') $mesaj = "Paçal kaydı silindi.";
    if ($_GET['msg'] == 'pacal_kaydedildi') {
        $kaydedilen_parti_no = trim($_GET['parti_no'] ?? '');
        $mesaj = "Paçal kaydı başarılı! Parti No: " . ($kaydedilen_parti_no ?: '-');
    }
}

// --- SİLO AKTARMA İŞLEMİ ---
if (isset($_POST["silo_aktarma_kaydet"])) {
    $force_tab = 'silo_aktarma';
    $giris_id = (int)$_POST["giris_id"];
    $dagitim_silo_ids = $_POST['dagitim_silo_id'] ?? [];
    $dagitim_kgs = $_POST['dagitim_kg'] ?? [];
    $hata_silo = "";

    if ($giris_id <= 0) $hata_silo = "Geçersiz giriş ID.";
    
    if(empty($hata_silo)){
        $mevcut = $baglanti->query("
            SELECT hg.id, hg.parti_no, hg.miktar_kg, h.ad as hammadde_adi, h.hammadde_kodu,
                   (SELECT la.hektolitre FROM lab_analizleri la WHERE la.hammadde_giris_id = hg.id ORDER BY la.id DESC LIMIT 1) as lab_hektolitre,
                   h.yogunluk_kg_m3
            FROM hammadde_girisleri hg
            LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
            WHERE hg.id = $giris_id
        ")->fetch_assoc();
        
        if(!$mevcut) $hata_silo = "Kayıt bulunamadı.";
        else {
            $referans_kg = (float)$mevcut['miktar_kg'];
            $hl = (float)$mevcut['lab_hektolitre'];
            $yogunluk = ($hl > 0) ? ($hl * 10) : (float)$mevcut['yogunluk_kg_m3'];
            if($yogunluk <= 0) $yogunluk = 780;
            
            $dagitimlar = [];
            $toplam = 0.0;
            $ilk_silo = 0;
            $max = max(count($dagitim_silo_ids), count($dagitim_kgs));
            for($i=0; $i<$max; $i++) {
                $sid= (int)($dagitim_silo_ids[$i]??0);
                $kg = (float)($dagitim_kgs[$i]??0);
                if($sid<=0 || $kg<=0) { $hata_silo="Geçersiz silo dağıtımı!"; break; }
                if($ilk_silo==0) $ilk_silo=$sid;
                if(!isset($dagitimlar[$sid])) $dagitimlar[$sid]=0.0;
                $dagitimlar[$sid] += $kg;
                $toplam += $kg;
            }
            
            if(empty($hata_silo) && count($dagitimlar)==0) $hata_silo = "Silo dağıtımı girmediniz.";
            if(empty($hata_silo) && abs($toplam - $referans_kg) > 0.01) $hata_silo = "Dağıtım toplamı kantar değerine ($referans_kg KG) eşit olmalıdır.";
            
            // Backend Silo Kapasite ve İzin Kontrolleri
            if(empty($hata_silo)) {
                $silo_ids = array_keys($dagitimlar);
                $id_list = implode(',', $silo_ids);
                $silo_kayitlari = $baglanti->query("SELECT id, silo_adi, kapasite_m3, doluluk_m3, izin_verilen_hammadde_kodlari FROM silolar WHERE id IN ($id_list)");
                $silo_map = [];
                while($s = $silo_kayitlari->fetch_assoc()) $silo_map[(int)$s['id']] = $s;

                foreach($dagitimlar as $sid => $mkg) {
                    if(!isset($silo_map[$sid])) { $hata_silo = "Silo bulunamadı (ID: $sid)."; break; }
                    $s = $silo_map[$sid];
                    $bos_m3 = max(0, (float)$s['kapasite_m3'] - (float)$s['doluluk_m3']);
                    $max_kg = $bos_m3 * $yogunluk;
                    if(($mkg - $max_kg) > 0.01) { $hata_silo = "{$s['silo_adi']} silosunda yeterli boşluk yok (Maks: ".number_format($max_kg,0)." KG)."; break; }
                    
                    $izinli_raw = trim((string)$s['izin_verilen_hammadde_kodlari']);
                    if(!empty($izinli_raw)) {
                        $izinli_list = json_decode($izinli_raw, true);
                        if(is_array($izinli_list) && count($izinli_list) > 0 && !in_array($mevcut['hammadde_kodu'], $izinli_list, true)) {
                            $hata_silo = "{$s['silo_adi']} silosuna {$mevcut['hammadde_kodu']} kodlu hammadde girişi izinli değil."; break;
                        }
                    }
                }
            }

            if(empty($hata_silo)) {
                $baglanti->begin_transaction();
                $islem_ok = true;
                
                $guncel_m3 = $referans_kg / $yogunluk;
                $p_no = $baglanti->real_escape_string($mevcut['parti_no']);
                $h_turu = $baglanti->real_escape_string($mevcut['hammadde_adi']);
                
                if(!$baglanti->query("UPDATE hammadde_girisleri SET giris_m3=$guncel_m3, silo_id=$ilk_silo WHERE id=$giris_id")) {
                    $islem_ok = false; $hata_silo = "Giriş güncellenemedi.";
                }
                
                if ($islem_ok) {
                    foreach($dagitimlar as $sid => $mkg) {
                        $f_sql = "INSERT INTO silo_stok_detay (silo_id, parti_kodu, hammadde_turu, giren_miktar_kg, kalan_miktar_kg, giris_tarihi, durum) 
                                  VALUES ($sid, '$p_no', '$h_turu', $mkg, $mkg, NOW(), 'aktif')";
                        if(!$baglanti->query($f_sql)) { $islem_ok = false; $hata_silo = "FIFO eklenemedi."; break; }
                        
                        $m3_ekle = $mkg / $yogunluk;
                        if(!$baglanti->query("UPDATE silolar SET doluluk_m3 = doluluk_m3 + $m3_ekle WHERE id=$sid")) {
                            $islem_ok = false; $hata_silo = "Doluluk işlenemedi."; break;
                        }
                    }
                }
                
                if($islem_ok) {
                    $baglanti->commit();
                    $mesaj = "Silo aktarımı başarıyla tamamlandı (Parti: {$mevcut['parti_no']})!";
                    systemLogKaydet($baglanti, 'INSERT', 'Silo Aktarma', "Silo dağıtımı. Parti: {$mevcut['parti_no']} Toplam: {$referans_kg} KG");
                } else {
                    $baglanti->rollback();
                    $hata = "Hata: " . $hata_silo;
                }
            } else {
                $hata = $hata_silo;
            }
        }
    }
}
// ==================== VERİ ÇEKİMLERİ ====================
$receteler = $baglanti->query("SELECT * FROM receteler ORDER BY id DESC");
$bugday_silolari = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' AND durum='aktif' ORDER BY silo_adi");
$bugday_silolari_arr = [];
if ($bugday_silolari) while ($s = $bugday_silolari->fetch_assoc()) $bugday_silolari_arr[] = $s;

$yikama_partileri = $baglanti->query("SELECT parti_no, yikama_tarihi, urun_adi FROM yikama_kayitlari WHERE parti_no IS NOT NULL AND parti_no != '' ORDER BY yikama_tarihi DESC LIMIT 50");

$aktif_emirler = $baglanti->query("
    SELECT ie.*, r.recete_adi, GROUP_CONCAT(CONCAT(s.silo_adi, ':', isk.yuzde, '%') SEPARATOR ', ') as silo_karisimi
    FROM is_emirleri ie JOIN receteler r ON ie.recete_id = r.id 
    LEFT JOIN is_emri_silo_karisimlari isk ON ie.id = isk.is_emri_id
    LEFT JOIN silolar s ON isk.silo_id = s.id GROUP BY ie.id ORDER BY ie.id DESC
");

$pacal_tarih_ymd = str_replace('-', '', $pacal_tarih_degeri);
$pacal_onerilen_parti_no = sonrakiPacalPartiNo($baglanti, $pacal_tarih_ymd);
$pacal_parti_no_degeri = trim($_POST["pacal_parti_no"] ?? '');
if ($pacal_parti_no_degeri === '') {
    $pacal_parti_no_degeri = $pacal_onerilen_parti_no;
}

$aktif_tab = $force_tab ?: ($_GET['tab'] ?? 'haftalik');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üretim Planlama - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { font-family:'Inter',system-ui,sans-serif; background:#f1f5f9!important; }
        .page-header { background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); color:#fff; border-radius:1.25rem; margin-top:1.25rem; margin-bottom:1.4rem; padding:1.55rem 1.7rem; box-shadow:0 16px 28px -14px rgba(15,23,42,.55); position:relative; overflow:hidden; }
        .page-header::before { content:""; position:absolute; top:-65%; right:-10%; width:440px; height:440px; border-radius:50%; background:radial-gradient(circle,rgba(245,158,11,.2) 0%,rgba(245,158,11,0) 72%); pointer-events:none; }
        .nav-tabs .nav-link { font-weight:600; color:#64748b; border:none; padding:14px 28px; border-radius:12px 12px 0 0; transition:all .3s; font-size:.95rem; }
        .nav-tabs .nav-link.active { color:#fff; background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); border:none; }
        .nav-tabs .nav-link:hover:not(.active) { background:#e2e8f0; color:#1e293b; }
        .tab-content { background:#fff; border-radius:0 0 15px 15px; padding:0; box-shadow:0 4px 15px rgba(0,0,0,.08); }
        .tab-content .tab-pane { padding:20px; }
        @media(max-width:768px) { .nav-tabs .nav-link { padding:10px 16px; font-size:.82rem; } .tab-content .tab-pane { padding:12px; } }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>
    <div class="container-fluid px-md-4 pb-4" style="max-width:1680px;margin:0 auto">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="fw-bold mb-1"><i class="fas fa-calendar-check me-2"></i>Üretim Planlama</h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.78)">Haftalık plan, paçal hazırlama ve iş emirleri</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#yeniReceteModal">
                        <i class="fas fa-scroll me-1"></i> Yeni Reçete
                    </button>
                </div>
            </div>
        </div>

        <!-- SEKMELER -->
        <ul class="nav nav-tabs" id="planlamaTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="planlama.php?tab=haftalik" class="nav-link <?php echo $aktif_tab=='haftalik'?'active':''; ?>" id="haftalik-tab" role="tab">
                    <i class="fas fa-calendar-week me-1"></i> Haftalık Plan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="planlama.php?tab=silo_aktarma" class="nav-link <?php echo $aktif_tab=='silo_aktarma'?'active':''; ?>" id="silo-aktarma-tab" role="tab">
                    <i class="fas fa-right-left me-1"></i> Silo Aktarma
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="planlama.php?tab=pacal" class="nav-link <?php echo $aktif_tab=='pacal'?'active':''; ?>" id="pacal-tab" role="tab">
                    <i class="fas fa-blender me-1"></i> Paçal Hazırlama
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="planlama.php?tab=recete" class="nav-link <?php echo $aktif_tab=='recete'?'active':''; ?>" id="recete-tab" role="tab">
                    <i class="fas fa-bullhorn me-1"></i> Reçete & İş Emirleri
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="planlama.php?tab=canli_rota" class="nav-link <?php echo $aktif_tab=='canli_rota'?'active':''; ?>" id="canli-rota-tab" role="tab">
                    <i class="fas fa-route text-success me-1"></i> Akış Rotası
                </a>
            </li>
        </ul>

        <div class="tab-content" id="planlamaTabsContent">
            <!-- SEKME 1: HAFTALIK PLAN -->
            <div class="tab-pane <?php echo $aktif_tab=='haftalik'?'show active':'d-none'; ?>" id="haftalikPlan" role="tabpanel" aria-labelledby="haftalik-tab" tabindex="0">
                <?php include("includes/planlama_haftalik_tab.php"); ?>
            </div>

            <!-- SEKME 2: SİLO AKTARMA -->
            <div class="tab-pane <?php echo $aktif_tab=='silo_aktarma'?'show active':'d-none'; ?>" id="siloAktarmaArea" role="tabpanel" aria-labelledby="silo-aktarma-tab" tabindex="0">
                <?php include("includes/planlama_silo_aktarma_tab.php"); ?>
            </div>

            <!-- SEKME 3: PAÇAL HAZIRLAMA -->
            <div class="tab-pane <?php echo $aktif_tab=='pacal'?'show active':'d-none'; ?>" id="pacalHazirla" role="tabpanel" aria-labelledby="pacal-tab" tabindex="0">
                <?php include("includes/planlama_pacal_tab.php"); ?>
            </div>

            <!-- SEKME 4: REÇETE & İŞ EMİRLERİ -->
            <div class="tab-pane <?php echo $aktif_tab=='recete'?'show active':'d-none'; ?>" id="receteIsEmri" role="tabpanel" aria-labelledby="recete-tab" tabindex="0">
                <?php include("includes/planlama_recete_tab.php"); ?>
            </div>

            <!-- SEKME 5: CANLI ROTA -->
            <div class="tab-pane <?php echo $aktif_tab=='canli_rota'?'show active':'d-none'; ?>" id="canliRota" role="tabpanel" aria-labelledby="canli-rota-tab" tabindex="0">
                <?php if(file_exists("includes/planlama_canli_rota_tab.php")) include("includes/planlama_canli_rota_tab.php"); ?>
            </div>
        </div>
    </div>

    <!-- REÇETE MODAL -->
    <div class="modal fade" id="yeniReceteModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Yeni Üretim Reçetesi</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="post">
                    <div class="mb-3"><label>Reçete Adı</label><input type="text" name="recete_adi" class="form-control" placeholder="Örn: Lüks Ekmeklik Un" required></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label>Tav Miktarı (lt)</label><input type="number" step="0.1" name="tav_miktar" class="form-control"></div>
                        <div class="col-6 mb-3"><label>Süre (Saat)</label><input type="number" name="sure_saat" class="form-control"></div>
                        <div class="col-6 mb-3"><label>Sıcaklık (°C)</label><input type="number" step="0.1" name="sicaklik" class="form-control"></div>
                        <div class="col-6 mb-3"><label>Hedef Nem (%)</label><input type="number" step="0.1" name="hedef_nem" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label>Teknik Açıklama</label><textarea name="aciklama" class="form-control" rows="3"></textarea></div>
                    <div class="d-grid"><button type="submit" name="recete_ekle" class="btn btn-success">Kaydet</button></div>
                </form>
            </div>
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($mesaj)): ?>
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'<?php echo addslashes(strip_tags($mesaj)); ?>', showConfirmButton:false, showCloseButton:true, timer:5000, timerProgressBar:true });
        <?php endif; ?>
        <?php if (!empty($hata)): ?>
        Swal.fire({ icon:'error', title:'Hata!', text:'<?php echo addslashes(strip_tags($hata)); ?>', confirmButtonColor:'#0f172a' });
        <?php endif; ?>

        // Silme onayı
        document.querySelectorAll('.sil-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({ title:'Emin misiniz?', text:'Bu kayıt silinecek!', icon:'warning', showCancelButton:true, confirmButtonColor:'#dc3545', cancelButtonText:'Vazgeç', confirmButtonText:'Sil!' }).then(r => { if(r.isConfirmed) window.location.href = this.getAttribute('href'); });
            });
        });
    });

    // ============ PAÇAL JS ============
    let kodSayac = {};

    function siloSecildi(selectEl, row) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const siloId = selectEl.value;
        const hammaddeAdi = opt?.dataset?.hammadde || '';
        const hid = opt?.dataset?.hid || '';

        document.querySelector(`.bugday-cinsi[data-row="${row}"]`).value = hammaddeAdi;
        document.querySelector(`.hammadde-id-hidden[data-row="${row}"]`).value = hid;

        const maxValEl = document.querySelector(`.miktar-field[data-row="${row}"]`);

        if (siloId) {
            // Fetch FIFO code and limits
            fetch(`ajax/silo_fifo_getir.php?silo_id=${siloId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector(`.kod-field[data-row="${row}"]`).value = data.fifo_kodu || '';
                        
                        if(maxValEl) {
                           maxValEl.dataset.max = data.serbest_kg;
                           maxValEl.setAttribute('title', 'Maksimum serbest stok: ' + data.serbest_kg.toLocaleString('tr-TR') + ' KG');
                        }
                    } else {
                        document.querySelector(`.kod-field[data-row="${row}"]`).value = '';
                    }
                }).catch(err => console.error('FIFO verisi hatası:', err));
        } else {
            document.querySelector(`.kod-field[data-row="${row}"]`).value = '';
            if(maxValEl) {
                maxValEl.removeAttribute('data-max');
                maxValEl.removeAttribute('title');
            }
        }

        // Lab verilerini çek
        if (hid) {
            fetch(`ajax/ajax_lab_verileri.php?hammadde_kodu=${encodeURIComponent(hid)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const d = data.data;
                        ['gluten','g_index','n_sedim','g_sedim','hektolitre','nem','fn',
                         'perten_protein','perten_sertlik','perten_nisasta'].forEach(col => {
                            const el = document.querySelector(`.lab-val[data-row="${row}"][data-col="${col}"]`);
                            if (el) el.value = d[col] || '';
                        });
                        hesaplaOrtalama();
                    }
                }).catch(err => console.error('Lab verisi hatası:', err));
        } else {
            document.querySelectorAll(`.lab-val[data-row="${row}"]`).forEach(f => f.value = '');
            hesaplaOrtalama();
        }
    }

    function hesaplaOrtalama() {
        const kolonlar = [
            {col:'gluten',id:'avgGluten',dec:2},{col:'g_index',id:'avgGIndex',dec:0},
            {col:'n_sedim',id:'avgNSedim',dec:0},{col:'g_sedim',id:'avgGSedim',dec:0},
            {col:'hektolitre',id:'avgHektolitre',dec:2},{col:'nem',id:'avgNem',dec:2},
            {col:'alveo_p',id:'avgAlveoP',dec:2},{col:'alveo_g',id:'avgAlveoG',dec:2},
            {col:'alveo_pl',id:'avgAlveoPL',dec:2},{col:'alveo_w',id:'avgAlveoW',dec:0},
            {col:'alveo_ie',id:'avgAlveoIE',dec:2},{col:'fn',id:'avgFN',dec:0},
            {col:'perten_protein',id:'avgProtein',dec:2},{col:'perten_sertlik',id:'avgSertlik',dec:2},
            {col:'perten_nisasta',id:'avgNisasta',dec:2}
        ];
        let toplamMiktar=0, toplamOran=0;
        for(let r=1;r<=7;r++){
            toplamOran += parseFloat(document.querySelector(`.oran-field[data-row="${r}"]`)?.value)||0;
            toplamMiktar += parseFloat(document.querySelector(`.miktar-field[data-row="${r}"]`)?.value)||0;
        }
        const el1 = document.getElementById('avgMiktar');
        const el2 = document.getElementById('avgOranToplam');
        if(el1) el1.innerText = toplamMiktar.toLocaleString('tr-TR');
        if(el2) el2.innerText = toplamOran.toFixed(2);

        kolonlar.forEach(({col,id,dec}) => {
            let toplam=0, hasVal=false;
            for(let r=1;r<=7;r++){
                const oran = parseFloat(document.querySelector(`.oran-field[data-row="${r}"]`)?.value)||0;
                if(oran<=0) continue;
                const valEl = document.querySelector(`.lab-val[data-row="${r}"][data-col="${col}"]`)
                    || document.querySelector(`.alveo-val[data-row="${r}"][data-col="${col}"]`);
                const val = parseFloat(valEl?.value)||0;
                if(val>0){ toplam += val*oran; hasVal=true; }
            }
            const avgEl = document.getElementById(id);
            if(avgEl) avgEl.innerText = (hasVal && toplamOran>0) ? (toplam/toplamOran).toFixed(dec) : '-';
        });
    }

    // Paçal form validasyonu
    const pf = document.getElementById('pacalForm');
    if(pf) pf.addEventListener('submit', function(e) {
        const partiNoEl = document.getElementById('pacalPartiNo');
        const partiNo = (partiNoEl?.value || '').trim();
        if (!/^PCL-\d{8}-\d{2,}$/.test(partiNo)) {
            e.preventDefault();
            Swal.fire({icon:'error',title:'Hata!',text:'Parti no formatı geçersiz. Beklenen: PCL-YYYYMMDD-01',confirmButtonColor:'#0f172a'});
            return;
        }

        let topO=0;
        for(let r=1;r<=7;r++) {
            topO += parseFloat(document.querySelector(`.oran-field[data-row="${r}"]`)?.value)||0;
            
            // Stok validasyonu
            let miktarEl = document.querySelector(`.miktar-field[data-row="${r}"]`);
            if (miktarEl) {
                let miktar = parseFloat(miktarEl.value) || 0;
                let maxVal = parseFloat(miktarEl.dataset.max);
                if (!isNaN(maxVal) && miktar > maxVal) {
                    e.preventDefault();
                    Swal.fire({icon:'error',title:'Yetersiz Stok!',text: r + '. satırdaki giriş ('+miktar+' KG), silodaki serbest stok limitini ('+maxVal.toLocaleString('tr-TR')+' KG) aşıyor!',confirmButtonColor:'#0f172a'});
                    return;
                }
            }
        }
        if(topO>0 && Math.abs(topO-100)>0.05){
            e.preventDefault();
            Swal.fire({icon:'error',title:'Hata!',text:'Paçal oranları toplamı 100 olmalı! (Şu an: '+topO.toFixed(2)+')',confirmButtonColor:'#0f172a'});
        }
    });

    // ============ İŞ EMRİ SILO JS ============
    const siloContainer = document.getElementById('siloKarisimContainer');
    const btnSiloEkle = document.getElementById('btnSiloEkle');
    if (siloContainer && btnSiloEkle) {
        const siloOpts = `<?php
            $opts = '<option value="">Silo Seç...</option>';
            foreach ($bugday_silolari_arr as $s) {
                $dol = ($s['kapasite_m3']>0)?round(($s['doluluk_m3']/$s['kapasite_m3'])*100):0;
                $opts .= '<option value="'.$s['id'].'">'.$s['silo_adi'].' ('.$dol.'% dolu)</option>';
            }
            echo addslashes($opts);
        ?>`;
        btnSiloEkle.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'silo-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = `<select name="silo_id[]" class="form-select" style="width:60%">${siloOpts}</select>
                <input type="number" name="silo_yuzde[]" class="form-control silo-yuzde" placeholder="%" min="0" max="100" step="0.1" style="width:80px">
                <span class="text-muted">%</span>
                <button type="button" class="btn btn-outline-danger btn-sm btn-silo-sil"><i class="fas fa-times"></i></button>`;
            siloContainer.appendChild(row);
        });
        siloContainer.addEventListener('click', function(e) {
            if (e.target.closest('.btn-silo-sil')) {
                const rows = siloContainer.querySelectorAll('.silo-row');
                if (rows.length > 1) e.target.closest('.silo-row').remove();
            }
        });
        siloContainer.addEventListener('input', function(e) {
            if (e.target.classList.contains('silo-yuzde')) {
                let t=0;
                siloContainer.querySelectorAll('.silo-yuzde').forEach(i => t += parseFloat(i.value)||0);
                const sp = document.getElementById('toplamYuzde');
                const uy = document.getElementById('yuzdeUyari');
                if(sp) sp.textContent = t.toFixed(1);
                if(uy) { if(Math.abs(t-100)>0.1){uy.classList.remove('d-none');sp.classList.add('text-danger')}else{uy.classList.add('d-none');sp.classList.remove('text-danger');sp.classList.add('text-success')} }
            }
        });
    }

    // PLC Otomatik Stok Düşüm / Canlı Rota İşleyici
    if (window.location.href.indexOf('tab=canli_rota') !== -1) {
        setInterval(() => window.location.reload(), 15000); // 15s de bir UI guncelle
    }
    // Arka planda transfer veritabanını işlemesi için her 5s'de endpoint tetikle (Lock mantığı DB'de var)
    setInterval(() => fetch('ajax/plc_stok_guncelleme.php?gizli_key=1').catch(()=>{}), 5000);
    
    </script>
    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>
</html>
