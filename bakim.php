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

// Bakım bildirimlerini kontrol et
bakimBildirimleriniKontrolEt($baglanti);

$mesaj = "";
$hata = "";

// --- 1. YENİ MAKİNE EKLEME ---
if (isset($_POST["makine_ekle"])) {
    $kod = strtoupper(trim($_POST["makine_kodu"]));
    $ad = trim($_POST["makine_adi"]);
    $unite = trim($_POST["unite_adi"]);
    $kat = trim($_POST["kat_bilgisi"]);
    $lokasyon = trim($_POST["lokasyon"]);
    $periyot = (int) $_POST["bakim_periyodu"];
    $son_bakim = !empty($_POST["son_bakim_tarihi"]) ? $_POST["son_bakim_tarihi"] : NULL;

    // EDGE CASE: Önce aynı makine kodundan var mı kontrol et (Aktif veya pasif fark etmeksizin)
    $stmt_check = $baglanti->prepare("SELECT id FROM makineler WHERE makine_kodu = ?");
    $stmt_check->bind_param("s", $kod);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $hata = "Hata: Cihaz kayıt reddedildi! '$kod' koduna sahip bir makine zaten sistemde mevcut.";
    } else {
        // Sonraki bakım tarihini hesapla (son bakım varsa)
        $sonraki_bakim = NULL;
        if ($son_bakim) {
            $sonraki_bakim = date('Y-m-d', strtotime($son_bakim . " + $periyot days"));
        }

        $stmt = $baglanti->prepare("INSERT INTO makineler (makine_kodu, makine_adi, unite_adi, kat_bilgisi, lokasyon, bakim_periyodu, son_bakim_tarihi, sonraki_bakim_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiss", $kod, $ad, $unite, $kat, $lokasyon, $periyot, $son_bakim, $sonraki_bakim);

        if ($stmt->execute()) {
            $mesaj = "✅ Yeni makine eklendi: $ad";
            systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "Yeni makine eklendi: $ad ($kod)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 2. MAKİNE GÜNCELLEME ---
if (isset($_POST["makine_guncelle"])) {
    $id = (int) $_POST["makine_id"];
    $kod = strtoupper(trim($_POST["makine_kodu"]));
    $ad = trim($_POST["makine_adi"]);
    $unite = trim($_POST["unite_adi"]);
    $kat = trim($_POST["kat_bilgisi"]);
    $lokasyon = trim($_POST["lokasyon"]);
    $periyot = (int) $_POST["bakim_periyodu"];

    // EDGE CASE: Güncellenmek istenen kod, başka bir makinede (başka ID'de) var mı kontrol et
    $stmt_check = $baglanti->prepare("SELECT id FROM makineler WHERE makine_kodu = ? AND id != ?");
    $stmt_check->bind_param("si", $kod, $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $hata = "Hata: Güncelleme reddedildi! '$kod' kodu zaten başka bir makine tarafından kullanılıyor.";
    } else {
        // Eski periyot bilgisini ve son bakımı alıp yeni sonraki_bakim_tarihini hesapla
        $stmt_get = $baglanti->prepare("SELECT son_bakim_tarihi FROM makineler WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result()->fetch_assoc();

        $sonraki_bakim = NULL;
        if ($res && !empty($res['son_bakim_tarihi'])) {
            $sonraki_bakim = date('Y-m-d', strtotime($res['son_bakim_tarihi'] . " + $periyot days"));
        }

        $stmt = $baglanti->prepare("UPDATE makineler SET makine_kodu = ?, makine_adi = ?, unite_adi = ?, kat_bilgisi = ?, lokasyon = ?, bakim_periyodu = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
        $stmt->bind_param("sssssisi", $kod, $ad, $unite, $kat, $lokasyon, $periyot, $sonraki_bakim, $id);

        if ($stmt->execute()) {
            $mesaj = "✅ Makine bilgileri güncellendi.";
            systemLogKaydet($baglanti, "UPDATE", "Bakım & Arıza", "Makine güncellendi: $ad ($kod)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 3. MAKİNE SİLME ---
if (isset($_GET["sil"])) {
    $id = (int) $_GET["sil"];

    // Önce makineye ait bakım geçmişini (foreing key kısıtlaması nedeniyle) sil
    $stmt_bakimlari_sil = $baglanti->prepare("DELETE FROM bakim_kayitlari WHERE makine_id = ?");
    $stmt_bakimlari_sil->bind_param("i", $id);
    $stmt_bakimlari_sil->execute();

    // Sonra makineyi veritabanından tamamen sil
    $stmt = $baglanti->prepare("DELETE FROM makineler WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mesaj = "✅ Makine ve ona ait tüm bakım geçmişi sistemden silindi.";
        systemLogKaydet($baglanti, "DELETE", "Bakım & Arıza", "Makine silindi (ID: $id)");
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 4. BAKIM KAYDI EKLEME ---
if (isset($_POST["bakim_ekle"])) {
    $makine_id = (int) $_POST["makine_id"];
    $tarih = $_POST["bakim_tarihi"];
    $tur = $_POST["bakim_turu"];
    $islem = $_POST["yapilan_islem"];
    $teknisyen = $_POST["teknisyen"];

    // EDGE CASE: Gelecekteki bir tarih veya çok eski bir tarih (örneğin 2 yıl öncesi) girilmesini engelle
    $bugunun_tarihi = date('Y-m-d');
    $max_gecmis_tarih = date('Y-m-d', strtotime('-1 years'));

    if ($tarih > $bugunun_tarihi) {
        $hata = "Hata: İleri bir tarihe bakım kaydı giremezsiniz.";
    } elseif ($tarih < $max_gecmis_tarih) {
        $hata = "Hata: Çok geçmiş bir tarihe (1 yıldan eski) bakım kaydı girilemez.";
    } else {
        // Makine bilgisini al (periyodu öğrenmek için)
        $stmt_periyot = $baglanti->prepare("SELECT bakim_periyodu, makine_adi FROM makineler WHERE id = ?");
        $stmt_periyot->bind_param("i", $makine_id);
        $stmt_periyot->execute();
        $makine_res = $stmt_periyot->get_result()->fetch_assoc();
        $periyot = $makine_res['bakim_periyodu'];
        $m_adi = $makine_res['makine_adi'];

        // Sonraki bakım tarihini güncelle
        $sonraki_bakim = date('Y-m-d', strtotime($tarih . " + $periyot days"));

        // 1. Bakım kaydını ekle
        $stmt_kayit = $baglanti->prepare("INSERT INTO bakim_kayitlari (makine_id, bakim_tarihi, bakim_turu, yapilan_islem, sonraki_bakim, teknisyen) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_kayit->bind_param("isssss", $makine_id, $tarih, $tur, $islem, $sonraki_bakim, $teknisyen);

        if ($stmt_kayit->execute()) {
            // 2. Makine tablosunu güncelle
            $stmt_upd = $baglanti->prepare("UPDATE makineler SET son_bakim_tarihi = ?, sonraki_bakim_tarihi = ? WHERE id = ?");
            $stmt_upd->bind_param("ssi", $tarih, $sonraki_bakim, $makine_id);
            $stmt_upd->execute();

            $mesaj = "✅ Bakım kaydı eklendi ve sonraki bakım tarihi güncellendi.";
            systemLogKaydet($baglanti, "INSERT", "Bakım & Arıza", "$m_adi için bakım kaydı girildi ($tur)");
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// İSTATİSTİKLER
$stats = [
    'gecikmis' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1 AND sonraki_bakim_tarihi < CURRENT_DATE")->fetch_assoc()['sayi'],
    'yaklasan' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1 AND sonraki_bakim_tarihi BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)")->fetch_assoc()['sayi'],
    'toplam' => $baglanti->query("SELECT COUNT(*) as sayi FROM makineler WHERE aktif = 1")->fetch_assoc()['sayi']
];

// LİSTELERİ ÇEK
$makineler = $baglanti->query("SELECT * FROM makineler WHERE aktif = 1 ORDER BY sonraki_bakim_tarihi ASC");
$gecmis = $baglanti->query("SELECT b.*, m.makine_adi FROM bakim_kayitlari b JOIN makineler m ON b.makine_id = m.id ORDER BY b.bakim_tarihi DESC LIMIT 20");
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Takip - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Select2 CSS for Searchable Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .card-stats {
            border-left: 5px solid;
        }

        .card-stats .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-past {
            background-color: #fff1f2;
        }

        .bg-soon {
            background-color: #fffbeb;
        }

        .bg-ok {
            background-color: #f0fdf4;
        }

        .table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.025em;
            border-top: none;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            color: #1e293b;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            padding-left: 35px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-normal {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-warning {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .status-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* YENİ UI EKLENTİLERİ */
        .filter-btn {
            border: 1px solid #e2e8f0;
            background-color: #fff;
            color: #475569;
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background-color: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .makine-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #fff;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .makine-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .mach-status-line {
            height: 4px;
            width: 100%;
        }

        .mach-status-danger {
            background-color: #ef4444;
        }

        .mach-status-warning {
            background-color: #f59e0b;
        }

        .mach-status-success {
            background-color: #10b981;
        }

        .mach-status-unknown {
            background-color: #94a3b8;
        }

        .card-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }

        /* Dokunmatik cihaz destekli görünüm için focus states dahil eklendi */
        .makine-card:hover .card-actions,
        .makine-card:focus-within .card-actions {
            opacity: 1;
        }
    </style>
</head>

<body>

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fas fa-tools text-primary me-2"></i> Makine Bakım Takip
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="panel.php" class="text-decoration-none">Panel</a></li>
                        <li class="breadcrumb-item active">Bakım & Arıza</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success px-4" data-bs-toggle="modal" data-bs-target="#bakimModal">
                    <i class="fas fa-wrench me-2"></i>Bakım Gir
                </button>
                <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#yeniMakineModal">
                    <i class="fas fa-plus me-2"></i>Makine Ekle
                </button>
            </div>
        </div>

        <!-- STATS -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card card-stats shadow-sm border-danger">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-light-danger text-danger me-3"><i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Geciken Bakımlar</h6>
                            <h3 class="fw-bold mb-0 text-danger"><?php echo $stats['gecikmis']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stats shadow-sm border-warning">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-light-warning text-warning me-3"><i class="fas fa-clock"></i></div>
                        <div>
                            <h6 class="text-muted mb-0">7 Gün İçinde Olacak</h6>
                            <h3 class="fw-bold mb-0 text-warning"><?php echo $stats['yaklasan']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stats shadow-sm border-primary">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-light-primary text-primary me-3"><i class="fas fa-industry"></i></div>
                        <div>
                            <h6 class="text-muted mb-0">Toplam Aktif Makine</h6>
                            <h3 class="fw-bold mb-0 text-primary"><?php echo $stats['toplam']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="row">
            <!-- MAKİNE LİSTESİ (KARTLAR) -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0 fw-bold">Makine Durumları</h5>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="makineArama" class="form-control form-control-sm"
                                    placeholder="Makine ara...">
                            </div>
                        </div>

                        <!-- Ünite Filtreleri -->
                        <div class="d-flex flex-wrap gap-2" id="uniteFiltreleri">
                            <button class="filter-btn active" data-filter="all">Tümü</button>
                            <button class="filter-btn" data-filter="Ön Temizleme Ünitesi">Ön Temz.</button>
                            <button class="filter-btn" data-filter="Temizleme Ünitesi">Temizleme</button>
                            <button class="filter-btn" data-filter="Aktarma Ünitesi">Aktarma</button>
                            <button class="filter-btn" data-filter="Hazırlık Ünitesi">Hazırlık</button>
                            <button class="filter-btn" data-filter="Atık Ünitesi">Atık</button>
                            <button class="filter-btn" data-filter="Öğütme Ünitesi">Öğütme</button>
                            <button class="filter-btn" data-filter="Un Ünitesi">Un</button>
                            <button class="filter-btn" data-filter="Kepek Ünitesi">Kepek</button>
                        </div>
                    </div>

                    <div class="card-body bg-light p-4">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="makineGrid">
                            <?php
                            if ($makineler && $makineler->num_rows > 0) {
                                while ($m = $makineler->fetch_assoc()) {
                                    $sonraki = $m['sonraki_bakim_tarihi'];
                                    $bugun = date('Y-m-d');

                                    $durum_class = '';
                                    $durum_badge = 'status-normal';
                                    $durum_text = 'Normal';
                                    $line_class = 'mach-status-success';

                                    if ($sonraki) {
                                        $diff = (strtotime($sonraki) - strtotime($bugun)) / (60 * 60 * 24);

                                        if ($diff < 0) {
                                            $durum_class = 'border-danger';
                                            $durum_badge = 'status-danger';
                                            $durum_text = 'GECİKMİŞ';
                                            $line_class = 'mach-status-danger';
                                        } elseif ($diff <= 7) {
                                            $durum_class = 'border-warning';
                                            $durum_badge = 'status-warning';
                                            $durum_text = 'YAKLAŞIYOR';
                                            $line_class = 'mach-status-warning';
                                        }
                                    } else {
                                        $durum_text = 'Belirsiz';
                                        $durum_badge = 'bg-light text-muted';
                                        $line_class = 'mach-status-unknown';
                                    }

                                    $unite_veri = htmlspecialchars($m['unite_adi'] ?? '');
                                    $ham_arama = $m['makine_adi'] . ' ' . $m['makine_kodu'] . ' ' . $unite_veri . ' ' . ($m['kat_bilgisi'] ?? '') . ' ' . $m['lokasyon'];
                                    $arama_metni = mb_strtolower($ham_arama, 'UTF-8');
                                    ?>
                                    <div class="col makine-kart" data-unite="<?php echo $unite_veri; ?>"
                                        data-search="<?php echo htmlspecialchars($arama_metni); ?>">
                                        <div class="makine-card shadow-sm <?php echo $durum_class; ?>">
                                            <div class="mach-status-line <?php echo $line_class; ?>"></div>
                                            <div class="p-3 flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-light text-dark border"><i
                                                            class="fas fa-barcode me-1 text-muted"></i><?php echo htmlspecialchars($m['makine_kodu']); ?></span>
                                                    <span
                                                        class="status-pill <?php echo $durum_badge; ?>"><?php echo $durum_text; ?></span>
                                                </div>
                                                <h6 class="fw-bold mb-1 text-truncate"
                                                    title="<?php echo htmlspecialchars($m['makine_adi']); ?>">
                                                    <?php echo htmlspecialchars($m['makine_adi']); ?>
                                                </h6>

                                                <div class="d-flex flex-wrap gap-1 mb-3 mt-2">
                                                    <?php if (!empty($m['unite_adi'])): ?>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary border border-primary-subtle"
                                                            style="font-size:0.7rem; font-weight:500;"><?php echo htmlspecialchars($m['unite_adi']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($m['kat_bilgisi'])): ?>
                                                        <span
                                                            class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"
                                                            style="font-size:0.7rem; font-weight:500;"><?php echo htmlspecialchars($m['kat_bilgisi']); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="small text-muted mb-1 px-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span><i class="far fa-calendar-check me-1"></i>Sonraki:</span>
                                                        <strong
                                                            class="<?php echo ($diff < 0) ? 'text-danger' : (($diff <= 7) ? 'text-warning' : 'text-dark'); ?>">
                                                            <?php echo $sonraki ? date('d.m.Y', strtotime($sonraki)) : '-'; ?>
                                                        </strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><i class="fas fa-sync-alt me-1"></i>Periyot:</span>
                                                        <span><?php echo $m['bakim_periyodu']; ?> Gün</span>
                                                    </div>
                                                    <?php if (!empty($m['lokasyon'])): ?>
                                                        <div class="d-flex justify-content-between mt-1 align-items-center">
                                                            <span><i class="fas fa-map-marker-alt me-1"></i>Detay:</span>
                                                            <span class="text-truncate" style="max-width: 100px;"
                                                                title="<?php echo htmlspecialchars($m['lokasyon']); ?>"><?php echo htmlspecialchars($m['lokasyon']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="bg-white border-top p-2 d-flex justify-content-around card-actions">
                                                <button class="btn btn-sm btn-outline-success border-0 px-2" title="Bakım Gir"
                                                    onclick="bakimModalAc(<?php echo $m['id']; ?>, '<?php echo addslashes($m['makine_adi']); ?>')">
                                                    <i class="fas fa-wrench"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary border-0 px-2" title="Düzenle"
                                                    onclick="makineDuzenleModalAc(<?php echo htmlspecialchars(json_encode($m)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?sil=<?php echo $m['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger border-0 px-2" title="Makineyi Sil"
                                                    onclick="silmeOnay(event, this.href)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                            } else {
                                echo "<div class='col-12'><div class='text-center p-5 text-muted w-100'><i class='fas fa-info-circle me-2 mb-3 fs-3 d-block'></i>Henüz makine eklenmemiş.</div></div>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SON BAKIM KAYITLARI -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold">Son Bakım İşlemleri</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php
                            if ($gecmis && $gecmis->num_rows > 0) {
                                while ($g = $gecmis->fetch_assoc()) {
                                    ?>
                                    <li class="list-group-item py-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <strong class="text-dark"><?php echo htmlspecialchars($g['makine_adi']); ?></strong>
                                            <span class="badge bg-soft-info text-info rounded-pill"
                                                style="font-size: 0.65rem;"><?php echo date('d.m.Y', strtotime($g['bakim_tarihi'])); ?></span>
                                        </div>
                                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($g['yapilan_islem']); ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span class="text-primary font-monospace" style="font-size: 0.7rem;"><i
                                                    class="fas fa-user-cog me-1"></i><?php echo htmlspecialchars($g['teknisyen']); ?></span>
                                            <span
                                                class="badge <?php echo $g['bakim_turu'] == 'Arıza' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> px-2 py-1"><?php echo $g['bakim_turu']; ?></span>
                                        </div>
                                    </li>
                                <?php }
                            } else {
                                echo "<li class='list-group-item text-center p-4 text-muted'>Geçmiş kayıt bulunamadı.</li>";
                            } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- YENİ MAKİNE MODAL -->
    <div class="modal fade" id="yeniMakineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Yeni Makine Ekle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Kodu</label>
                            <input type="text" name="makine_kodu" class="form-control border-0 bg-light"
                                placeholder="Örn: PAK-01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Adı</label>
                            <input type="text" name="makine_adi" class="form-control border-0 bg-light"
                                placeholder="Örn: Paketleme Hattı 1" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Ünite</label>
                                <select name="unite_adi" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Ön Temizleme Ünitesi">Ön Temizleme Ünitesi</option>
                                    <option value="Temizleme Ünitesi">Temizleme Ünitesi</option>
                                    <option value="Aktarma Ünitesi">Aktarma Ünitesi</option>
                                    <option value="Hazırlık Ünitesi">Hazırlık Ünitesi</option>
                                    <option value="Atık Ünitesi">Atık Ünitesi</option>
                                    <option value="Öğütme Ünitesi">Öğütme Ünitesi</option>
                                    <option value="Un Ünitesi">Un Ünitesi</option>
                                    <option value="Kepek Ünitesi">Kepek Ünitesi</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Kat Bilgisi</label>
                                <select name="kat_bilgisi" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Yer Altı">Yer Altı</option>
                                    <option value="Zemin">Zemin Kat</option>
                                    <option value="1. Kat">1. Kat</option>
                                    <option value="2. Kat">2. Kat</option>
                                    <option value="3. Kat">3. Kat</option>
                                    <option value="4. Kat">4. Kat</option>
                                    <option value="5. Kat">5. Kat</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Detay Lokasyon <small
                                        class="text-primary">(Opsiyonel)</small></label>
                                <input type="text" name="lokasyon" class="form-control border-0 bg-light"
                                    placeholder="Örn: Blower Odası, Motor Yanı vb.">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Periyodu (Gün)</label>
                                <input type="number" name="bakim_periyodu" class="form-control border-0 bg-light"
                                    value="30" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Son Bakım Tarihi <small
                                    class="text-primary">(Opsiyonel)</small></label>
                            <input type="date" name="son_bakim_tarihi" class="form-control border-0 bg-light">
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="makine_ekle" class="btn btn-primary px-4">Makineyi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MAKİNE DÜZENLE MODAL -->
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <input type="hidden" name="makine_id" id="edit_id">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold">Makineyi Düzenle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Kodu</label>
                            <input type="text" name="makine_kodu" id="edit_kod" class="form-control border-0 bg-light"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Adı</label>
                            <input type="text" name="makine_adi" id="edit_ad" class="form-control border-0 bg-light"
                                required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Ünite</label>
                                <select name="unite_adi" id="edit_unite" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Ön Temizleme Ünitesi">Ön Temizleme Ünitesi</option>
                                    <option value="Temizleme Ünitesi">Temizleme Ünitesi</option>
                                    <option value="Aktarma Ünitesi">Aktarma Ünitesi</option>
                                    <option value="Hazırlık Ünitesi">Hazırlık Ünitesi</option>
                                    <option value="Atık Ünitesi">Atık Ünitesi</option>
                                    <option value="Öğütme Ünitesi">Öğütme Ünitesi</option>
                                    <option value="Un Ünitesi">Un Ünitesi</option>
                                    <option value="Kepek Ünitesi">Kepek Ünitesi</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Kat Bilgisi</label>
                                <select name="kat_bilgisi" id="edit_kat" class="form-select border-0 bg-light" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Yer Altı">Yer Altı</option>
                                    <option value="Zemin">Zemin Kat</option>
                                    <option value="1. Kat">1. Kat</option>
                                    <option value="2. Kat">2. Kat</option>
                                    <option value="3. Kat">3. Kat</option>
                                    <option value="4. Kat">4. Kat</option>
                                    <option value="5. Kat">5. Kat</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Detay Lokasyon <small
                                        class="text-primary">(Opsiyonel)</small></label>
                                <input type="text" name="lokasyon" id="edit_lokasyon"
                                    class="form-control border-0 bg-light">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Periyodu (Gün)</label>
                                <input type="number" name="bakim_periyodu" id="edit_periyot"
                                    class="form-control border-0 bg-light" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="makine_guncelle" class="btn btn-dark px-4">Değişiklikleri
                            Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BAKIM GİRİŞ MODAL -->
    <div class="modal fade" id="bakimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-wrench me-2"></i>Yeni Bakım Girişi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Makine Seçimi</label>
                            <!-- eklenti Select2 tarafından class="form-select" yerine kullanılacak şekilde tasarlandı -->
                            <select name="makine_id" id="modalMakineSelect" class="form-select border-0 bg-light"
                                required style="width: 100%;">
                                <option value="">Makine ara ve seç...</option>
                                <?php
                                $makineler->data_seek(0);
                                while ($m = $makineler->fetch_assoc()) {
                                    $unite_kat_metni = !empty($m['unite_adi']) ? " - " . $m['unite_adi'] : "";
                                    echo "<option value='{$m['id']}'>{$m['makine_adi']} ({$m['makine_kodu']}){$unite_kat_metni}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Tarihi</label>
                                <input type="date" name="bakim_tarihi" class="form-control border-0 bg-light"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Bakım Türü</label>
                                <select name="bakim_turu" class="form-select border-0 bg-light">
                                    <option value="Periyodik">Periyodik Bakım</option>
                                    <option value="Arıza">Arıza Onarım</option>
                                    <option value="Kontrol">Genel Kontrol</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Yapılan İşlemler</label>
                            <textarea name="yapilan_islem" class="form-control border-0 bg-light" rows="3"
                                placeholder="Parça değişimi, yağlama vb." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Sorumlu Teknisyen</label>
                            <input type="text" name="teknisyen" class="form-control border-0 bg-light"
                                value="<?php echo $_SESSION['kadi']; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="bakim_ekle" class="btn btn-success px-4">Kaydı Tamamla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
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
        });

        function silmeOnay(e, url) {
            e.preventDefault();
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu makine ve ona ait TÜM BAKIM GEÇMİŞİ kalıcı olarak silinecektir. Bu işlem geri alınamaz!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Kalıcı Olarak Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // Makine Arama ve Filtreleme
        const filterBtns = document.querySelectorAll('.filter-btn');
        let aktifUnite = 'all';

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                aktifUnite = this.getAttribute('data-filter');
                filtreleMakineler();
            });
        });

        document.getElementById('makineArama').addEventListener('keyup', filtreleMakineler);

        function filtreleMakineler() {
            let searchVal = document.getElementById('makineArama').value.toLocaleLowerCase('tr-TR');
            let kartlar = document.querySelectorAll('.makine-kart');

            kartlar.forEach(kart => {
                let searchData = kart.getAttribute('data-search');
                // Eğer data-search attribütü javascript ile oluşturulsaydı toLocaleLowerCase uygulardık,
                // ama PHP kodunda mb_strtolower kullandığımız için JS tarafında da Türkçe küçük harf eşleşmesini tam yapıyoruz.
                let uniteData = kart.getAttribute('data-unite');

                let textMatch = searchData.includes(searchVal);
                let uniteMatch = (aktifUnite === 'all') || (uniteData === aktifUnite);

                if (textMatch && uniteMatch) {
                    kart.style.display = '';
                } else {
                    kart.style.display = 'none';
                }
            });
        }

        // Select2 Kurulumu (Modal İçinde Çalışması İçin Özelleştirilmiş)
        $(document).ready(function () {
            $('#modalMakineSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#bakimModal'), // Modalın arkasına düşmemesi veya odağın (focus) kaybolmaması için.
                placeholder: 'Yazarak makine arayın...',
                language: {
                    noResults: function () {
                        return "Makine bulunamadı.";
                    }
                }
            });
        });

        function bakimModalAc(id, ad) {
            // Dropdown değerini güncelle ve select2'nin algılaması için trigger("change") tetikle.
            $('#modalMakineSelect').val(id).trigger('change');
            const modal = new bootstrap.Modal(document.getElementById('bakimModal'));
            modal.show();
        }

        function makineDuzenleModalAc(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_kod').value = data.makine_kodu;
            document.getElementById('edit_ad').value = data.makine_adi;
            document.getElementById('edit_unite').value = data.unite_adi || '';
            document.getElementById('edit_kat').value = data.kat_bilgisi || '';
            document.getElementById('edit_lokasyon').value = data.lokasyon || '';
            document.getElementById('edit_periyot').value = data.bakim_periyodu;
            const modal = new bootstrap.Modal(document.getElementById('duzenleModal'));
            modal.show();
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
