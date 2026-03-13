<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("baglan.php");

// 1. GÜVENLİK KONTROLÜ
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// 2. YETKI BİLGİSİ
$yetki = isset($_SESSION["yetki"]) ? $_SESSION["yetki"] : 'personel';
$kadi = isset($_SESSION["kadi"]) ? $_SESSION["kadi"] : 'Kullanıcı';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$rol_id = isset($_SESSION["rol_id"]) ? (int) $_SESSION["rol_id"] : 0;

// 3. ROLE GÖRE VERİ ÇEKME (Sadece gerekli sorgular çalışır)

// Bekleyen İş Emirleri — uretim & admin
$bekleyen_is = 0;
if ($yetki == 'admin' || $yetki == 'uretim') {
    $bekleyen_is_sorgu = $baglanti->query("SELECT count(*) as total FROM is_emirleri WHERE durum='bekliyor'");
    if ($bekleyen_is_sorgu && $row = $bekleyen_is_sorgu->fetch_assoc()) {
        $bekleyen_is = $row['total'];
    }
}

// Günlük Üretim — uretim, lab & admin
$gunluk_uretim_ton = 0;
$bugun = date("Y-m-d");
if ($yetki == 'admin' || $yetki == 'uretim' || $yetki == 'lab') {
    $uretim_sorgu = $baglanti->query("SELECT SUM(uretilen_miktar_kg) as toplam_kg FROM uretim_hareketleri WHERE DATE(tarih) = '$bugun'");
    $gunluk_uretim_kg = 0;
    if ($uretim_sorgu && $uretim_veri = $uretim_sorgu->fetch_assoc()) {
        $gunluk_uretim_kg = $uretim_veri['toplam_kg'] ?? 0;
    }
    $gunluk_uretim_ton = ($gunluk_uretim_kg > 0) ? number_format($gunluk_uretim_kg / 1000, 1) : 0;
}

// Sevkiyat — depo, satin_alma & admin
$sevkiyat_sorgu = null;
$sevkiyat_count = 0;
if ($yetki == 'admin' || $yetki == 'depo' || $yetki == 'satin_alma') {
    $sevkiyat_sorgu = $baglanti->query("SELECT * FROM sevkiyat_randevulari WHERE DATE(randevu_tarihi) = '$bugun' ORDER BY randevu_tarihi ASC");
    $sevkiyat_count = $sevkiyat_sorgu ? $sevkiyat_sorgu->num_rows : 0;
}

// Silo Durumları — uretim, satin_alma & admin
$silo_sorgu = null;
if ($yetki == 'admin' || $yetki == 'uretim' || $yetki == 'satin_alma') {
    $silo_sorgu = $baglanti->query("SELECT * FROM silolar");
}

// Admin: Bekleyen Onaylar
$bekleyen_onay = 0;
if ($yetki == 'admin') {
    $table_check = @$baglanti->query("SHOW TABLES LIKE 'pending_approvals'");
    if ($table_check && $table_check->num_rows > 0) {
        $onay_result = @$baglanti->query("SELECT COUNT(*) as cnt FROM pending_approvals WHERE status = 'PENDING'");
        if ($onay_result) {
            $bekleyen_onay = $onay_result->fetch_assoc()['cnt'] ?? 0;
        }
    }
    // Aktif kullanıcı sayısı
    $user_count_result = $baglanti->query("SELECT COUNT(*) as cnt FROM users WHERE aktif = 1");
    $aktif_kullanici = $user_count_result ? $user_count_result->fetch_assoc()['cnt'] : 0;
}

// Lab: Bekleyen Analiz
$bekleyen_analiz = 0;
if ($yetki == 'lab' || $yetki == 'admin') {
    $analiz_result = @$baglanti->query("SELECT COUNT(*) as cnt FROM hammadde_girisleri WHERE analiz_yapildi = 0");
    if ($analiz_result) {
        $bekleyen_analiz = $analiz_result->fetch_assoc()['cnt'] ?? 0;
    }
}

// Satın Alma: Bekleyen Talepler
$bekleyen_talep = 0;
if ($yetki == 'satin_alma' || $yetki == 'admin') {
    $talep_result = @$baglanti->query("SELECT COUNT(*) as cnt FROM satin_alma_talepleri WHERE onay_durum = 'bekliyor'");
    if ($talep_result) {
        $bekleyen_talep = $talep_result->fetch_assoc()['cnt'] ?? 0;
    }
}

// Bakım: Yaklaşan/Geciken Bakımlar
$bakim_uyari = 0;
if ($yetki == 'uretim' || $yetki == 'admin') {
    $bakim_check = @$baglanti->query("SHOW TABLES LIKE 'bakim_planlari'");
    if ($bakim_check && $bakim_check->num_rows > 0) {
        $bakim_result = @$baglanti->query("SELECT COUNT(*) as cnt FROM bakim_planlari WHERE sonraki_bakim_tarihi <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND durum != 'tamamlandi'");
        if ($bakim_result) {
            $bakim_uyari = $bakim_result->fetch_assoc()['cnt'] ?? 0;
        }
    }
}

// 4. ROLE GÖRE SAYFA BAŞLIĞI VE AÇIKLAMA
$panel_basliklar = [
    'admin' => ['başlık' => 'Yönetim Paneli', 'açıklama' => 'Tüm fabrika anlık durum özeti'],
    'uretim' => ['başlık' => 'Üretim Paneli', 'açıklama' => 'Üretim ve planlama durumu'],
    'depo' => ['başlık' => 'Depo & Sevkiyat Paneli', 'açıklama' => 'Sevkiyat ve yükleme programı'],
    'satin_alma' => ['başlık' => 'Satın Alma Paneli', 'açıklama' => 'Tedarik ve stok durumu'],
    'lab' => ['başlık' => 'Laboratuvar Paneli', 'açıklama' => 'Analiz ve kalite kontrol'],
    'personel' => ['başlık' => 'Hoş Geldiniz', 'açıklama' => 'Menüden yetkili modüllere erişebilirsiniz'],
];
$baslik = $panel_basliklar[$yetki]['başlık'] ?? 'Hoş Geldiniz';
$aciklama = $panel_basliklar[$yetki]['açıklama'] ?? '';
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $baslik; ?> - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Navbar'la uyumlu body ayarları */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-left: 0 !important;
        }

        /* Masaüstü: Sidebar'ı yer ver */
        @media (min-width: 992px) {
            body {
                padding-left: 260px !important;
            }
        }

        /* Kart Tasarımları */
        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            background: white;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .card-custom:hover {
            transform: translateY(-5px);
        }

        .stat-card {
            color: white;
        }

        /* Progress Bar (Silo Doluluk) */
        .progress {
            height: 25px;
            border-radius: 15px;
            background-color: #e9ecef;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            font-weight: bold;
            line-height: 25px;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
        }

        /* Hoşgeldin Kartı */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }

        .welcome-card .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        /* Hızlı Erişim */
        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            background: #f8f9fa;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
            border: 1px solid #e9ecef;
        }

        .quick-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
            color: #333;
        }

        .quick-link i {
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        /* Rol Badge */
        .role-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <!-- BAŞLIK -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 text-dark fw-bold"><?php echo $baslik; ?></h2>
                <p class="text-muted mb-0"><?php echo $aciklama; ?></p>
            </div>
            <div class="d-none d-md-flex align-items-center gap-2">
                <span class="role-badge bg-primary text-white"><?php echo $yetki; ?></span>
                <span class="badge bg-white text-dark border p-2 shadow-sm">
                    <i class="far fa-calendar-alt text-primary"></i> <?php echo date("d.m.Y"); ?>
                </span>
            </div>
        </div>

        <!-- ========== STAT KARTLARI (ROLE GÖRE) ========== -->
        <div class="row mb-4">

            <?php if ($yetki == 'admin' || $yetki == 'uretim') { ?>
                <!-- Bekleyen İş Emri -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card bg-primary p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bekleyen İş Emri</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bekleyen_is; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-clipboard-list fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="planlama.php" class="text-white text-decoration-none">Planlamaya Git <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'admin' || $yetki == 'uretim' || $yetki == 'lab') { ?>
                <!-- Günlük Üretim -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card bg-success p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Günlük Üretim</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $gunluk_uretim_ton; ?> Ton</h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-cogs fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">Hatlar aktif çalışıyor</div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'admin' || $yetki == 'depo' || $yetki == 'satin_alma') { ?>
                <!-- Bugünkü Sevkiyat -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card bg-warning text-dark p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bugünkü Sevkiyat</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $sevkiyat_count; ?> Araç</h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-shipping-fast fa-2x text-dark"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="depo_sevkiyat.php" class="text-dark text-decoration-none">Listeyi Gör <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'admin') { ?>
                <!-- Admin: Bekleyen Onay -->
                <div class="col-md-4 col-6 mt-md-3">
                    <div class="card card-custom stat-card p-3 h-100"
                        style="background: linear-gradient(135deg, #7c3aed, #a855f7);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bekleyen Onay</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bekleyen_onay; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-check-double fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="onay_merkezi_v2.php" class="text-white text-decoration-none">Onay Merkezine Git <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Admin: Aktif Kullanıcı -->
                <div class="col-md-4 col-6 mt-md-3">
                    <div class="card card-custom stat-card p-3 h-100"
                        style="background: linear-gradient(135deg, #0891b2, #06b6d4);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Aktif Kullanıcı</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $aktif_kullanici; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-users fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="kullanici_yonetimi.php" class="text-white text-decoration-none">Kullanıcıları Yönet <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'lab') { ?>
                <!-- Lab: Bekleyen Analiz -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card p-3 h-100"
                        style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bekleyen Analiz</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bekleyen_analiz; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-flask fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="lab_analizleri.php" class="text-white text-decoration-none">Analizlere Git <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'satin_alma') { ?>
                <!-- Satın Alma: Bekleyen Talep -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card p-3 h-100"
                        style="background: linear-gradient(135deg, #ea580c, #f97316);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bekleyen Talep</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bekleyen_talep; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-shopping-cart fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="satin_alma.php" class="text-white text-decoration-none">Taleplere Git <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($yetki == 'uretim') { ?>
                <!-- Üretim: Bakım Uyarısı -->
                <div class="col-md-4 col-6">
                    <div class="card card-custom stat-card p-3 h-100"
                        style="background: linear-gradient(135deg, <?php echo $bakim_uyari > 0 ? '#dc2626, #ef4444' : '#16a34a, #22c55e'; ?>);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 opacity-75">Bakım Uyarısı</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bakim_uyari; ?></h2>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                                <i class="fas fa-tools fa-2x text-white"></i>
                            </div>
                        </div>
                        <div class="mt-3 small opacity-75">
                            <a href="bakim.php" class="text-white text-decoration-none">Bakım Planları <i
                                    class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>

        </div>

        <!-- ========== SİLO DOLULUK (uretim, satin_alma, admin) ========== -->
        <?php if ($silo_sorgu && ($yetki == 'admin' || $yetki == 'uretim' || $yetki == 'satin_alma')) { ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0 fw-bold"><i class="fas fa-database text-secondary me-2"></i>Silo
                                Doluluk Oranları</h5>
                            <?php if ($yetki == 'admin' || $yetki == 'uretim' || $yetki == 'satin_alma') { ?>
                                <a href="hammadde.php" class="btn btn-sm btn-outline-primary">Hammadde Girişi Yap</a>
                            <?php } ?>
                        </div>

                        <div class="row">
                            <?php
                            if ($silo_sorgu->num_rows > 0) {
                                while ($silo = $silo_sorgu->fetch_assoc()) {
                                    $yuzde = 0;
                                    if ($silo["kapasite_m3"] > 0) {
                                        $yuzde = ($silo["doluluk_m3"] / $silo["kapasite_m3"]) * 100;
                                    }

                                    $renk = "bg-success";
                                    if ($yuzde > 70)
                                        $renk = "bg-warning";
                                    if ($yuzde > 90)
                                        $renk = "bg-danger";

                                    $ikon = ($silo["tip"] == 'un') ? '<i class="fas fa-bread-slice text-secondary"></i>' : '<i class="fas fa-wheat text-warning"></i>';
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="border rounded p-3 bg-light h-100 position-relative">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong class="text-dark"><?php echo $ikon; ?>
                                                    <?php echo $silo["silo_adi"]; ?></strong>
                                                <span
                                                    class="badge bg-secondary"><?php echo $silo["aktif_hammadde_kodu"] ? $silo["aktif_hammadde_kodu"] : 'BOŞ'; ?></span>
                                            </div>

                                            <div class="progress mb-2">
                                                <div class="progress-bar <?php echo $renk; ?> progress-bar-striped progress-bar-animated"
                                                    role="progressbar" style="width: <?php echo $yuzde; ?>%">
                                                    %<?php echo number_format($yuzde, 1); ?>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between text-muted small">
                                                <span><i class="fas fa-fill-drip"></i>
                                                    <?php echo number_format($silo["doluluk_m3"], 1); ?> m³</span>
                                                <span><i class="fas fa-box"></i> Kap: <?php echo $silo["kapasite_m3"]; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                            } else {
                                echo "<div class='col-12 text-center text-muted'>Sistemde kayıtlı silo yok.</div>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- ========== YÜKLEME PROGRAMI (depo, admin) ========== -->
        <?php if ($sevkiyat_sorgu && ($yetki == 'admin' || $yetki == 'depo')) { ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <h5 class="card-title mb-3 fw-bold"><i class="fas fa-truck text-secondary me-2"></i>Bugünün Yükleme
                            Programı</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Saat</th>
                                        <th>Müşteri</th>
                                        <th>Plaka</th>
                                        <th>Şoför</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($sevkiyat_sorgu->num_rows > 0) {
                                        while ($sevk = $sevkiyat_sorgu->fetch_assoc()) {
                                            $durum_renk = "bg-secondary";
                                            if ($sevk["durum"] == "bekliyor")
                                                $durum_renk = "bg-warning text-dark";
                                            if ($sevk["durum"] == "yukleniyor")
                                                $durum_renk = "bg-info text-dark";
                                            if ($sevk["durum"] == "tamamlandi")
                                                $durum_renk = "bg-success";
                                            ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo date("H:i", strtotime($sevk["randevu_tarihi"])); ?>
                                                </td>
                                                <td><?php echo $sevk["musteri_adi"]; ?></td>
                                                <td><span class="badge bg-dark"><?php echo $sevk["arac_plaka"]; ?></span></td>
                                                <td><?php echo $sevk["sofor_adi"]; ?></td>
                                                <td><span
                                                        class="badge <?php echo $durum_renk; ?>"><?php echo strtoupper($sevk["durum"]); ?></span>
                                                </td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">
                                                <i class="fas fa-coffee fa-2x mb-2 d-block opacity-50"></i>
                                                Bugün için planlanmış sevkiyat yok.
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- ========== HIZLI ERİŞİM - NAVBAR MENÜSÜ (Her rol için dinamik) ========== -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <h5 class="card-title mb-3 fw-bold"><i class="fas fa-bolt text-warning me-2"></i>Hızlı Erişim</h5>
                    
                    <!-- Ana Panel -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <a href="panel.php" class="quick-link text-decoration-none">
                                <i class="fas fa-th-large bg-secondary text-white p-2 rounded"></i>
                                <span class="fw-semibold">Genel Bakış</span>
                                <i class="fas fa-chevron-right ms-auto text-muted"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="bildirimler.php" class="quick-link text-decoration-none">
                                <i class="fas fa-bell bg-warning text-dark p-2 rounded"></i>
                                <span class="fw-semibold">Bildirimler <?php echo $bildirim_sayisi > 0 ? "<span class='badge bg-danger ms-1'>".$bildirim_sayisi."</span>" : ""; ?></span>
                                <i class="fas fa-chevron-right ms-auto text-muted"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Üretim -->
                    <?php if (function_exists('navbarModulGoster') && ($baglanti->query("SELECT 1 FROM modul_yetkileri WHERE rol_id = $rol_id AND modul_adi IN ('Hammadde Yönetimi', 'Planlama & Takvim', 'Üretim Paneli') AND okuma = 1")->num_rows > 0 || $rol_adi === 'Patron')) { ?>
                        <h6 class="text-muted small text-uppercase fw-bold mt-3 mb-2">Üretim</h6>
                        <div class="row g-3 mb-3">
                            <?php if (navbarModulGoster($baglanti, 'Hammadde Yönetimi')) { ?>
                                <div class="col-md-6">
                                    <a href="hammadde.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-truck-loading bg-warning text-dark p-2 rounded"></i>
                                        <span class="fw-semibold">Hammadde</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (navbarModulGoster($baglanti, 'Planlama & Takvim')) { ?>
                                <div class="col-md-6">
                                    <a href="planlama.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-calendar-alt bg-primary text-white p-2 rounded"></i>
                                        <span class="fw-semibold">Planlama</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (navbarModulGoster($baglanti, 'Üretim Paneli')) { ?>
                                <div class="col-md-6">
                                    <a href="uretim.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-industry bg-success text-white p-2 rounded"></i>
                                        <span class="fw-semibold">Üretim</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <!-- Lojistik -->
                    <?php if (function_exists('navbarModulGoster') && ($baglanti->query("SELECT 1 FROM modul_yetkileri WHERE rol_id = $rol_id AND modul_adi IN ('Satın Alma', 'Sevkiyat & Lojistik', 'Stok Takibi') AND okuma = 1")->num_rows > 0 || $rol_adi === 'Patron')) { ?>
                        <h6 class="text-muted small text-uppercase fw-bold mt-3 mb-2">Lojistik</h6>
                        <div class="row g-3 mb-3">
                            <?php if (navbarModulGoster($baglanti, 'Satın Alma')) { ?>
                                <div class="col-md-6">
                                    <a href="satin_alma.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-shopping-cart bg-info text-white p-2 rounded"></i>
                                        <span class="fw-semibold">Satın Alma</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (navbarModulGoster($baglanti, 'Sevkiyat & Lojistik')) { ?>
                                <div class="col-md-6">
                                    <a href="depo_sevkiyat.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-boxes-stacked bg-warning text-dark p-2 rounded"></i>
                                        <span class="fw-semibold">Depo & Sevk</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (navbarModulGoster($baglanti, 'Stok Takibi')) { ?>
                                <div class="col-md-6">
                                    <a href="malzeme_stok.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-cubes bg-success text-white p-2 rounded"></i>
                                        <span class="fw-semibold">Malzeme Stok</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <!-- Kalite -->
                    <?php if (function_exists('navbarModulGoster') && ($baglanti->query("SELECT 1 FROM modul_yetkileri WHERE rol_id = $rol_id AND modul_adi IN ('İzlenebilirlik', 'Lab Analizleri') AND okuma = 1")->num_rows > 0 || $rol_adi === 'Patron')) { ?>
                        <h6 class="text-muted small text-uppercase fw-bold mt-3 mb-2">Kalite</h6>
                        <div class="row g-3 mb-3">
                            <?php if (navbarModulGoster($baglanti, 'İzlenebilirlik')) { ?>
                                <div class="col-md-6">
                                    <a href="izlenebilirlik.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-barcode bg-primary text-white p-2 rounded"></i>
                                        <span class="fw-semibold">İzlenebilirlik</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                            <?php if (navbarModulGoster($baglanti, 'Lab Analizleri')) { ?>
                                <div class="col-md-6">
                                    <a href="lab_analizleri.php" class="quick-link text-decoration-none">
                                        <i class="fas fa-flask bg-danger text-white p-2 rounded"></i>
                                        <span class="fw-semibold">Lab Analiz</span>
                                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <!-- Bakım -->
                    <?php if (navbarModulGoster($baglanti, 'Bakım & Arıza')) { ?>
                        <h6 class="text-muted small text-uppercase fw-bold mt-3 mb-2">Bakım</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <a href="bakim.php" class="quick-link text-decoration-none">
                                    <i class="fas fa-tools bg-secondary text-white p-2 rounded"></i>
                                    <span class="fw-semibold">Makine Bakım</span>
                                    <i class="fas fa-chevron-right ms-auto text-muted"></i>
                                </a>
                            </div>
                        </div>
                    <?php } ?>

                </div>
            </div>
        </div>

        <!-- ========== PERSONEL HOŞGELDİN (sadece personel) ========== -->
        <?php if ($yetki == 'personel') { ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="welcome-card">
                        <div class="icon"><i class="fas fa-wheat-awn"></i></div>
                        <h3 class="fw-bold mb-2">Hoş Geldiniz, <?php echo ucfirst($kadi); ?>!</h3>
                        <p class="opacity-75 mb-0">Menüden yetkili olduğunuz modüllere erişebilirsiniz.</p>
                    </div>
                </div>
            </div>
        <?php } ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
