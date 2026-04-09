<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

// Güvenlik
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

$mesaj = "";
$hata = "";

// Kullanıcı bilgilerini al
$kullanici_adi = $_SESSION["kadi"] ?? "Bilinmiyor";

// ==========================================
// 1. POST İŞLEMLERİ
// ==========================================

// --- YENİ ŞİKAYET EKLEME ---
if (isset($_POST["sikayet_ekle"])) {
    // Verileri al ve temizle
    $musteri_id_raw = $_POST["musteri_id"] ?? "";
    $musteri_id = !empty($musteri_id_raw) ? (int) $musteri_id_raw : "NULL";
    $musteri_adi = mysqli_real_escape_string($baglanti, trim($_POST["musteri_adi"] ?? ""));

    // Eğer müşteri seçiliyse ve ad boşsa veritabanından adını çekmeye çalış (fallback)
    if ($musteri_id !== "NULL" && empty($musteri_adi)) {
        $m_res = $baglanti->query("SELECT firma_adi FROM musteriler WHERE id = $musteri_id");
        if ($m_res && $m = $m_res->fetch_assoc()) {
            $musteri_adi = mysqli_real_escape_string($baglanti, $m['firma_adi']);
        }
    }

    $parti_no = mysqli_real_escape_string($baglanti, trim($_POST["parti_no"] ?? ""));
    $sevkiyat_parti_no = mysqli_real_escape_string($baglanti, trim($_POST["sevkiyat_parti_no"] ?? ""));
    $sikayet_tarihi = mysqli_real_escape_string($baglanti, $_POST["sikayet_tarihi"] ?? date('Y-m-d'));

    $bildirim_kanali = mysqli_real_escape_string($baglanti, $_POST["bildirim_kanali"] ?? "");
    $sikayet_tipi = mysqli_real_escape_string($baglanti, $_POST["sikayet_tipi"] ?? "");
    $oncelik = mysqli_real_escape_string($baglanti, $_POST["oncelik"] ?? "orta");

    $sikayet_konusu = mysqli_real_escape_string($baglanti, trim($_POST["sikayet_konusu"] ?? ""));
    $sikayet_detay = mysqli_real_escape_string($baglanti, trim($_POST["sikayet_detay"] ?? ""));

    // Zorunlu alan kontrolü
    if (empty($sikayet_konusu) || empty($sikayet_tarihi)) {
        $hata = "Şikayet Konusu ve Şikayet Tarihi zorunludur.";
    } else {
        // DÖF Numarası Üretme (DOF-YIL-SIRA)
        $yil = date("Y", strtotime($sikayet_tarihi));
        $prefix = "DOF-" . $yil . "-";

        $son_kayit_res = $baglanti->query("SELECT sikayet_no FROM sikayetler WHERE sikayet_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
        $siradaki = 1;
        if ($son_kayit_res && $son_kayit_res->num_rows > 0) {
            $son_kayit = $son_kayit_res->fetch_assoc();
            $son_no = $son_kayit['sikayet_no'];
            $son_numara = (int) substr($son_no, strrpos($son_no, "-") + 1);
            $siradaki = $son_numara + 1;
        }
        $sikayet_no = $prefix . str_pad($siradaki, 3, "0", STR_PAD_LEFT);

        // SQL Ekleme
        $sql_insert = "INSERT INTO sikayetler (
            sikayet_no, musteri_id, musteri_adi, parti_no, sevkiyat_parti_no, 
            sikayet_tarihi, bildirim_kanali, sikayet_tipi, sikayet_konusu, sikayet_detay, 
            oncelik, durum, olusturan
        ) VALUES (
            '$sikayet_no', $musteri_id, '$musteri_adi', '$parti_no', '$sevkiyat_parti_no',
            '$sikayet_tarihi', '$bildirim_kanali', '$sikayet_tipi', '$sikayet_konusu', '$sikayet_detay',
            '$oncelik', 'acik', '$kullanici_adi'
        )";

        if ($baglanti->query($sql_insert)) {
            $yeni_id = $baglanti->insert_id;

            // Log 
            systemLogKaydet($baglanti, 'INSERT', 'Şikayetler', "Yeni şikayet kaydı eklendi: $sikayet_no ($sikayet_konusu)");

            // Bildirim
            if (function_exists('bildirimOlustur')) {
                // Bildirim gidecek roller (Örn: 1=Patron, 2=Satın Alma/İdari vb.)
                // Gerçek projenizdeki rollere göre uyarlayın. Şu an patron(1)'a atalım.
                bildirimOlustur(
                    $baglanti,
                    'yeni_sikayet',
                    "Yeni Şikayet / DÖF: $sikayet_no",
                    "Müşteri: $musteri_adi | Konu: $sikayet_konusu",
                    1,
                    null,
                    'sikayetler',
                    $yeni_id,
                    'sikayetler.php'
                );
            }

            header("Location: sikayetler.php?msg=eklendi&no=" . urlencode($sikayet_no));
            exit;
        } else {
            $hata = "Veritabanı hatası: " . $baglanti->error;
        }
    }
}

// --- ŞİKAYET DÜZENLEME / KÖK NEDEN - DÖF GÜNCELLEME ---
if (isset($_POST["sikayet_guncelle"])) {
    $sikayet_id = (int) $_POST["sikayet_id"];

    // Yalnızca detay ve yöneticilerin düzenleyebileceği alanlar
    $kok_neden = mysqli_real_escape_string($baglanti, $_POST["kok_neden"] ?? "");
    $duzeltici_faaliyet = mysqli_real_escape_string($baglanti, $_POST["duzeltici_faaliyet"] ?? "");
    $onleyici_faaliyet = mysqli_real_escape_string($baglanti, $_POST["onleyici_faaliyet"] ?? "");
    $dof_sorumlu = mysqli_real_escape_string($baglanti, $_POST["dof_sorumlu"] ?? "");

    $hedef_kapanma_tarihi = !empty($_POST["hedef_kapanma_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["hedef_kapanma_tarihi"]) . "'" : "NULL";
    $durum_yeni = mysqli_real_escape_string($baglanti, $_POST["durum"] ?? "acik");

    // DB'deki mevcut duruma bakalım, eğer kapalı ise işlem yapmayalım. (Güvenlik)
    $mevcut_res = $baglanti->query("SELECT durum, sikayet_no FROM sikayetler WHERE id = $sikayet_id");
    if ($mevcut_res && $m = $mevcut_res->fetch_assoc()) {
        if ($m['durum'] === 'kapandi' && $durum_yeni !== 'kapandi') {
            // Sadece yönetici (rol_id=1) kapalı bir şikayeti geri açabilir. (Bunu projeye göre ekleyebilirsiniz, şimdilik ufak bir kontrol)
            // Biz şimdilik direkt güncellesin diye izin veriyoruz.
        }

        $sql_update = "UPDATE sikayetler SET 
            kok_neden = '$kok_neden',
            duzeltici_faaliyet = '$duzeltici_faaliyet',
            onleyici_faaliyet = '$onleyici_faaliyet',
            dof_sorumlu = '$dof_sorumlu',
            hedef_kapanma_tarihi = $hedef_kapanma_tarihi,
            durum = '$durum_yeni'
            WHERE id = $sikayet_id";

        if ($baglanti->query($sql_update)) {
            systemLogKaydet($baglanti, 'UPDATE', 'Şikayetler', "Şikayet DÖF detayları güncellendi. No: " . $m['sikayet_no']);
            header("Location: sikayetler.php?msg=guncellendi");
            exit;
        } else {
            $hata = "Güncelleme hatası: " . $baglanti->error;
        }
    }
}

// --- YENİ FAALİYET/AKSİYON EKLEME ---
if (isset($_POST["faaliyet_ekle"])) {
    $sikayet_id = (int) $_POST["sikayet_id"];
    $faaliyet_tipi = mysqli_real_escape_string($baglanti, $_POST["faaliyet_tipi"] ?? "duzeltici");
    $aciklama = mysqli_real_escape_string($baglanti, trim($_POST["aciklama"] ?? ""));
    $sorumlu = mysqli_real_escape_string($baglanti, trim($_POST["sorumlu"] ?? ""));
    $hedef_tarih = !empty($_POST["hedef_tarih"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["hedef_tarih"]) . "'" : "NULL";

    if (!empty($aciklama)) {
        $sql_f_insert = "INSERT INTO sikayet_faaliyetleri (sikayet_id, faaliyet_tipi, aciklama, sorumlu, hedef_tarih, olusturan) 
                         VALUES ($sikayet_id, '$faaliyet_tipi', '$aciklama', '$sorumlu', $hedef_tarih, '$kullanici_adi')";
        if ($baglanti->query($sql_f_insert)) {
            header("Location: sikayetler.php?msg=faaliyet_eklendi");
            exit;
        } else {
            $hata = "Faaliyet ekleneirken hata oluştu: " . $baglanti->error;
        }
    } else {
        $hata = "Faaliyet açıklaması zorunludur.";
    }
}

// --- FAALİYET DURUM GÜNCELLEME (Tamamlandı İşaretleme vb.) ---
if (isset($_POST["faaliyet_guncelle"])) {
    $faaliyet_id = (int) $_POST["faaliyet_id"];
    $yeni_durum = mysqli_real_escape_string($baglanti, $_POST["durum"] ?? "bekliyor");
    $tamamlanma_sql = ($yeni_durum === 'tamamlandi') ? "tamamlanma_tarihi = CURRENT_DATE()," : "tamamlanma_tarihi = NULL,";

    $baglanti->query("UPDATE sikayet_faaliyetleri SET $tamamlanma_sql durum = '$yeni_durum' WHERE id = $faaliyet_id");
    header("Location: sikayetler.php?msg=faaliyet_guncellendi");
    exit;
}

// --- ŞİKAYET KAPATMA ---
if (isset($_POST["sikayet_kapat"])) {
    $sikayet_id = (int) $_POST["sikayet_id"];
    $sonuc_dogrulama = mysqli_real_escape_string($baglanti, trim($_POST["sonuc_dogrulama"] ?? ""));

    // DB Check
    $m_res = $baglanti->query("SELECT sikayet_no FROM sikayetler WHERE id = $sikayet_id");
    if ($m_res && $m = $m_res->fetch_assoc()) {
        $sql_kapat = "UPDATE sikayetler SET 
                      durum = 'kapandi', 
                      kapanma_tarihi = CURRENT_DATE(), 
                      sonuc_dogrulama = '$sonuc_dogrulama' 
                      WHERE id = $sikayet_id";

        if ($baglanti->query($sql_kapat)) {
            systemLogKaydet($baglanti, 'UPDATE', 'Şikayetler', "Şikayet KAPATILDI. No: " . $m["sikayet_no"]);
            header("Location: sikayetler.php?msg=kapatildi");
            exit;
        } else {
            $hata = "Kapatılırken hata: " . $baglanti->error;
        }
    }
}


// Mesaj Gösterimleri
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'eklendi')
        $mesaj = "✅ Yeni şikayet kaydı oluşturuldu: " . htmlspecialchars($_GET['no'] ?? '');
    if ($_GET['msg'] == 'guncellendi')
        $mesaj = "✅ Şikayet / DÖF detayları güncellendi.";
    if ($_GET['msg'] == 'faaliyet_eklendi')
        $mesaj = "✅ Yeni aksiyon/faaliyet başarıyla eklendi.";
    if ($_GET['msg'] == 'faaliyet_guncellendi')
        $mesaj = "✅ Faaliyet durumu güncellendi.";
    if ($_GET['msg'] == 'kapatildi')
        $mesaj = "✅ Şikayet / DÖF dosyası kapatıldı.";
}

// ==========================================
// 2. VERİ ÇEKME & LİSTELEME
// ==========================================

// Dashboard Özet Verileri
$istatistikler = [
    'toplam' => 0,
    'acik' => 0,
    'inceleniyor' => 0,
    'kapandi' => 0
];
$ist_res = $baglanti->query("SELECT durum, COUNT(id) as sayi FROM sikayetler GROUP BY durum");
if ($ist_res) {
    while ($row = $ist_res->fetch_assoc()) {
        $istatistikler['toplam'] += $row['sayi'];
        if ($row['durum'] == 'acik' || $row['durum'] == 'dof_acildi')
            $istatistikler['acik'] += $row['sayi'];
        if ($row['durum'] == 'inceleniyor')
            $istatistikler['inceleniyor'] = $row['sayi'];
        if ($row['durum'] == 'kapandi')
            $istatistikler['kapandi'] = $row['sayi'];
    }
}

// Müşterileri Dropdown İçin Al
// Note: Gerçek tabloda müşteri nasıl geçiyorsa uyduruldu. Genelde 'musteriler' ve 'firma_adi' olur.
$musteriler_options = "";
$musteriler_res = @$baglanti->query("SELECT id, firma_adi FROM musteriler ORDER BY firma_adi ASC");
if ($musteriler_res) {
    while ($m = $musteriler_res->fetch_assoc()) {
        $musteriler_options .= "<option value='{$m['id']}'>{$m['firma_adi']}</option>";
    }
}

// Şikayet Kayıtları Ana Listesi
$sikayetler_sql = "
    SELECT 
        s.*,
        (SELECT COUNT(id) FROM sikayet_faaliyetleri sf WHERE sf.sikayet_id = s.id) as toplam_faaliyet,
        (SELECT COUNT(id) FROM sikayet_faaliyetleri sf WHERE sf.sikayet_id = s.id AND sf.durum = 'tamamlandi') as tamamlanan_faaliyet
    FROM sikayetler s
    ORDER BY CASE WHEN s.durum = 'kapandi' THEN 1 ELSE 0 END ASC, s.sikayet_tarihi DESC, s.id DESC
";
$sikayetler_res = $baglanti->query($sikayetler_sql);

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şikayetler ve DÖF Yönetimi - Özbal Un</title>
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 (Müşteri arama için opsiyonel ama önerilir) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --ozbal-primary: #0f172a;
            --ozbal-accent: #f59e0b;
            --ozbal-success: #10b981;
            --ozbal-danger: #ef4444;
            --ozbal-bg: #f8fafc;
            --ozbal-card-bg: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--ozbal-bg);
            color: #1e293b;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        /* Dash karts */
        .dash-card {
            border-radius: 12px;
            padding: 1.5rem;
            color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .dash-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .bg-gradient-total {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .bg-gradient-open {
            background: linear-gradient(135deg, #ef4444, #991b1b);
        }

        .bg-gradient-review {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        .bg-gradient-closed {
            background: linear-gradient(135deg, #10b981, #047857);
        }

        .table thead th {
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.025em;
        }

        .badge-soft {
            padding: 0.4em 0.7em;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-durum-acik {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-durum-dof_acildi {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-durum-inceleniyor {
            background-color: #fef3c7;
            color: #b45309;
        }

        .badge-durum-kapandi {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-oncelik-dusuk {
            background-color: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .badge-oncelik-orta {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-oncelik-yuksek {
            background-color: #fed7aa;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

        .badge-oncelik-kritik {
            background-color: #fecaca;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .required-field::after {
            content: " *";
            color: #ef4444;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container-fluid py-4 px-lg-5">

        <!-- Headers & Alerts -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0 text-dark"><i class="fas fa-comment-dots text-primary me-2"></i>Müşteri Şikayetleri
                ve DÖF</h3>
            <button class="btn btn-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#yeniSikayetModal">
                <i class="fas fa-plus me-2"></i>Yeni Şikayet / DÖF Aç
            </button>
        </div>



        <!-- Dashboard Kartları -->
        <div class="row mb-2">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dash-card bg-gradient-total position-relative h-100">
                    <div class="text-uppercase fw-bold small opacity-75 mb-1">Toplam Kayıt</div>
                    <div class="fs-1 fw-bold"><?php echo $istatistikler['toplam']; ?></div>
                    <i class="fas fa-folder-open dash-icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dash-card bg-gradient-open position-relative h-100">
                    <div class="text-uppercase fw-bold small opacity-75 mb-1">Açık İşlemler</div>
                    <div class="fs-1 fw-bold"><?php echo $istatistikler['acik']; ?></div>
                    <i class="fas fa-fire dash-icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dash-card bg-gradient-review position-relative h-100">
                    <div class="text-uppercase fw-bold small opacity-75 mb-1">İnceleniyor</div>
                    <div class="fs-1 fw-bold"><?php echo $istatistikler['inceleniyor']; ?></div>
                    <i class="fas fa-search dash-icon"></i>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dash-card bg-gradient-closed position-relative h-100">
                    <div class="text-uppercase fw-bold small opacity-75 mb-1">Kapanan (Çözüldü)</div>
                    <div class="fs-1 fw-bold"><?php echo $istatistikler['kapandi']; ?></div>
                    <i class="fas fa-check-double dash-icon"></i>
                </div>
            </div>
        </div>

        <!-- Şikayet Listesi Tablosu -->
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-muted"></i>Tüm Kayıtlar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table id="sikayetlerTablosu" class="table table-hover align-middle w-100 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>DÖF No</th>
                                <th>Tarih</th>
                                <th>Müşteri</th>
                                <th>Parti No</th>
                                <th>Şikayet Tipi</th>
                                <th>Konu</th>
                                <th>Öncelik</th>
                                <th>Durum</th>
                                <th>Aksiyonlar</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sikayetler_res && $sikayetler_res->num_rows > 0) {
                                while ($s = $sikayetler_res->fetch_assoc()) {
                                    // Durum ve Öncelik Etiketleri
                                    $durumL = $s['durum'];
                                    $durumLabel = "Açık";
                                    if ($durumL == 'inceleniyor')
                                        $durumLabel = "İnceleniyor";
                                    if ($durumL == 'dof_acildi')
                                        $durumLabel = "DÖF Açıldı";
                                    if ($durumL == 'kapandi')
                                        $durumLabel = "Kapandı";

                                    $oncelikL = $s['oncelik'];
                                    $oncelikLabel = ucfirst($oncelikL);

                                    // Aksiyon Sayımı Gösterim
                                    $aksiyonOran = $s['toplam_faaliyet'] > 0 ? $s['tamamlanan_faaliyet'] . "/" . $s['toplam_faaliyet'] : "-";
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['sikayet_no']); ?></strong></td>
                                        <td data-order="<?php echo $s['sikayet_tarihi']; ?>">
                                            <?php echo date('d.m.Y', strtotime($s['sikayet_tarihi'])); ?>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;"
                                                title="<?php echo htmlspecialchars($s['musteri_adi']); ?>">
                                                <?php echo htmlspecialchars($s['musteri_adi'] ?: 'Bilinmeyen Müşteri'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['parti_no'] ?? '-'); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $s['sikayet_tipi'])); ?></td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 150px;"
                                                title="<?php echo htmlspecialchars($s['sikayet_konusu']); ?>">
                                                <?php echo htmlspecialchars($s['sikayet_konusu']); ?>
                                            </div>
                                        </td>
                                        <td><span
                                                class="badge-soft badge-oncelik-<?php echo $oncelikL; ?>"><?php echo $oncelikLabel; ?></span>
                                        </td>
                                        <td><span
                                                class="badge-soft badge-durum-<?php echo $durumL; ?>"><?php echo $durumLabel; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($s['toplam_faaliyet'] > 0): ?>
                                                <span
                                                    class="badge <?php echo ($s['tamamlanan_faaliyet'] == $s['toplam_faaliyet']) ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                    <?php echo $aksiyonOran; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="sikayet_detay.php?id=<?php echo $s['id']; ?>"
                                                class="btn btn-sm btn-outline-primary" title="Detayları Gör">
                                                <i class="fas fa-search"></i>
                                            </a>
                                            <!-- Direkt modal için altyapı da yapılabilir ama form karmaşası olmaması için ayrı sayfa veya AJAX modal daha iyidir -->
                                        </td>
                                    </tr>
                                <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div> <!-- container -->

    <!-- ============================================== -->
    <!-- MODALS -->
    <!-- ============================================== -->

    <!-- Yeni Şikayet Modalı -->
    <div class="modal fade" id="yeniSikayetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Yeni Müşteri Şikayeti & DÖF Başlat
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label required-field">Müşteri</label>
                                <select name="musteri_id" id="yeni_musteri_id" class="form-select select2-init">
                                    <option value="">Seçiniz (Rehberden)</option>
                                    <?php echo $musteriler_options; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Manuel Ad (Rehberde Yoksa)</label>
                                <input type="text" name="musteri_adi" class="form-control" placeholder="Örn: X Fırını">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Şikayet Tipi</label>
                                <select name="sikayet_tipi" class="form-select">
                                    <option value="kalite">Ürün Kalitesi (Değerler vb.)</option>
                                    <option value="ambalaj">Ambalaj & Paketleme</option>
                                    <option value="lojistik">Lojistik & Sevkiyat</option>
                                    <option value="yabanci_madde">Yabancı Madde</option>
                                    <option value="miktar">Miktar / Gramaj</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bildirim Kanalı</label>
                                <select name="bildirim_kanali" class="form-select">
                                    <option value="telefon">Telefon</option>
                                    <option value="email">E-Posta</option>
                                    <option value="yuz_yuze">Yüz Yüze Görüşme</option>
                                    <option value="yazili">Yazılı/Dilekçe</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">İlgili Parti No</label>
                                <input type="text" name="parti_no" id="yeni_parti_no" class="form-control"
                                    placeholder="Hammadde / Üretim Partisi">
                                <small class="text-muted">Parti numarasını izlenebilirlik için girin</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required-field">Şikayet Tarihi</label>
                                <input type="date" name="sikayet_tarihi" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-9">
                                <label class="form-label required-field">Konu / Kısa Özet</label>
                                <input type="text" name="sikayet_konusu" class="form-control" required
                                    placeholder="Son partide ekmek kabarmıyor problemi">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Öncelik</label>
                                <select name="oncelik" class="form-select">
                                    <option value="dusuk">Düşük</option>
                                    <option value="orta" selected>Orta</option>
                                    <option value="yuksek">Yüksek (Acil)</option>
                                    <option value="kritik">Kritik (Geri Çağırma)</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Şikayet Detayı / Müşterinin Beyanı</label>
                                <textarea name="sikayet_detay" class="form-control" rows="4"
                                    placeholder="Müşteri tam olarak ne yaşıyor? Ne zaman tespit edildi?"></textarea>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="sikayet_ekle" class="btn btn-primary fw-bold px-4">
                            <i class="fas fa-save me-2"></i>Kaydet ve DÖF Başlat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        $(document).ready(function () {
            // DataTables
            $('#sikayetlerTablosu').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
                },
                "order": [[1, "desc"]], // Tarihe göre azalan
                "pageLength": 25,
                "responsive": true
            });

            // Select2
            $('.select2-init').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#yeniSikayetModal'),
                width: '100%'
            });

            // Eğer yazma yetkisi kontrolü varsa JS kısıtlamaları yapılabilir
            <?php if (function_exists('yazmaYetkisiKontrolJS')) {
                yazmaYetkisiKontrolJS($baglanti, true);
            } ?>

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
        });
    </script>
</body>

</html>
