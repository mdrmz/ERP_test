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

sayfaErisimKontrol($baglanti);

$yazma_yetkisi = yazmaYetkisiVar($baglanti, 'Hammadde Yönetimi');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$yazma_yetkisi) {
    die("Bu işlem için yazma yetkiniz bulunmamaktadır.");
}

$is_patron = (($_SESSION['rol_adi'] ?? '') === 'Patron') || ((int) ($_SESSION['rol_id'] ?? 0) === 1);
$mesaj = "";
$hata  = "";
$gecmis_form_acik = $is_patron && isset($_POST['giris_yap']) && (int)($_POST['gecmis_mod'] ?? 0) === 1;

// ─────────────────────────────────────────────
// YENİ ARAÇ GİRİŞİ
// ─────────────────────────────────────────────
if (isset($_POST["giris_yap"])) {
    $plaka     = strtoupper(trim($baglanti->real_escape_string($_POST["plaka"] ?? '')));
    $tedarikci = trim($baglanti->real_escape_string($_POST["tedarikci"] ?? ''));
    $islem_turu = trim((string)($_POST["islem_turu"] ?? ''));
    $gecmis_mod = $is_patron && ((int)($_POST["gecmis_mod"] ?? 0) === 1);
    $gecmis_tarih_raw = trim((string)($_POST["gecmis_tarih"] ?? ''));
    $gecmis_saat_raw = trim((string)($_POST["gecmis_saat"] ?? ''));
    $gecmis_tarih_sql = null;
    $gecmis_tarih_log = '';

    if ($plaka === '' || $tedarikci === '' || !in_array($islem_turu, ['yukleme', 'bosaltma'], true)) {
        $hata = "Plaka, Tedarikçi ve İşlem Türü alanları zorunludur.";
    } else {
        if ($gecmis_mod) {
            if ($gecmis_tarih_raw === '' || $gecmis_saat_raw === '') {
                $hata = "Geçmiş giriş için tarih ve saat alanları zorunludur.";
            } else {
                $gecmis_birlesik = $gecmis_tarih_raw . ' ' . $gecmis_saat_raw;
                $gecmis_dt = DateTime::createFromFormat('Y-m-d H:i', $gecmis_birlesik);
                $gecmis_hata = DateTime::getLastErrors();
                $gecerli_dt = ($gecmis_dt instanceof DateTime)
                    && ($gecmis_hata === false || ((int)$gecmis_hata['warning_count'] === 0 && (int)$gecmis_hata['error_count'] === 0))
                    && ($gecmis_dt->format('Y-m-d H:i') === $gecmis_birlesik);

                if (!$gecerli_dt) {
                    $hata = "Geçmiş tarih/saat formatı geçersizdir.";
                } else {
                    $simdi = new DateTime('now');
                    if ($gecmis_dt > $simdi) {
                        $hata = "Geçmiş giriş tarihi gelecekte olamaz.";
                    } else {
                        $gecmis_tarih_sql = $baglanti->real_escape_string($gecmis_dt->format('Y-m-d H:i:s'));
                        $gecmis_tarih_log = $gecmis_dt->format('d.m.Y H:i');
                    }
                }
            }
        }
    }

    if ($hata === '') {
        // parti_no: NOT NULL alanı — otomatik üret (YYYYMMDD-PLAKA-random)
        $parti_no = date('Ymd') . '-' . preg_replace('/[^A-Z0-9]/', '', $plaka) . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
        $parti_no = $baglanti->real_escape_string($parti_no);
        $personel = $baglanti->real_escape_string($_SESSION['kadi'] ?? '');
        $islem_turu_sql = $baglanti->real_escape_string($islem_turu);
        $islem_turu_label = ($islem_turu === 'bosaltma') ? 'Boşaltma' : 'Yükleme';

        if ($gecmis_mod && $gecmis_tarih_sql !== null) {
            $sql = "INSERT INTO hammadde_girisleri
                        (tarih, arac_plaka, tedarikci, islem_turu, parti_no, personel, miktar_kg, giris_m3, analiz_yapildi)
                    VALUES
                        ('$gecmis_tarih_sql', '$plaka', '$tedarikci', '$islem_turu_sql', '$parti_no', '$personel', 0, 0, 0)";
        } else {
            $sql = "INSERT INTO hammadde_girisleri
                        (arac_plaka, tedarikci, islem_turu, parti_no, personel, miktar_kg, giris_m3, analiz_yapildi)
                    VALUES
                        ('$plaka', '$tedarikci', '$islem_turu_sql', '$parti_no', '$personel', 0, 0, 0)";
        }

        if ($baglanti->query($sql)) {
            $yeni_giris_id = (int)$baglanti->insert_id;

            if (function_exists('akisOlustur') && $yeni_giris_id > 0 && $islem_turu !== 'yukleme') {
                $tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'hammadde_kabul_akisi'");
                if ($tablo_kontrol && $tablo_kontrol->num_rows > 0) {
                    akisOlustur($baglanti, $yeni_giris_id);
                }
            }

            if (function_exists('systemLogKaydet')) {
                $log_mesaji = "Yeni araç kabul kaydı: $plaka | Tedarikçi: $tedarikci | İşlem Türü: $islem_turu_label";
                if ($gecmis_mod && $gecmis_tarih_log !== '') {
                    $log_mesaji .= " | Geçmiş Tarih: $gecmis_tarih_log";
                }
                systemLogKaydet($baglanti, 'INSERT', 'Araç Kabul', $log_mesaji);
            }

            header("Location: hammadde.php?giris=ok&plaka=" . urlencode($plaka));
            exit;
        } else {
            $hata = "Kayıt hatası: " . $baglanti->error;
        }
    }
}
if (isset($_GET['giris']) && $_GET['giris'] === 'ok') {
    $mesaj = "✅ " . htmlspecialchars($_GET['plaka'] ?? '') . " plakalı araç başarıyla kaydedildi.";
}

// ─────────────────────────────────────────────
// SİLME
// ─────────────────────────────────────────────
if (isset($_POST["kayit_sil"])) {
    $sil_id = (int)($_POST["sil_id"] ?? 0);
    if ($sil_id > 0) {
        $kontrol = $baglanti->query("SELECT id FROM hammadde_girisleri WHERE id=$sil_id AND analiz_yapildi=0 LIMIT 1");
        if ($kontrol && $kontrol->num_rows > 0) {
            if ($baglanti->query("DELETE FROM hammadde_girisleri WHERE id=$sil_id")) {
                $mesaj = "✅ Kayıt silindi.";
            } else {
                $hata = "Silme hatası: " . $baglanti->error;
            }
        } else {
            $hata = "Bu kayıt silinemez (işlem görmüş veya bulunamadı).";
        }
    }
}

// ─────────────────────────────────────────────
// DÜZENLEME
// ─────────────────────────────────────────────
if (isset($_POST["kayit_duzenle"])) {
    $duzenle_id  = (int)($_POST["duzenle_id"] ?? 0);
    $yeni_plaka  = strtoupper(trim($baglanti->real_escape_string($_POST["d_plaka"] ?? '')));
    $yeni_ted    = trim($baglanti->real_escape_string($_POST["d_tedarikci"] ?? ''));
    $yeni_islem_turu = trim((string)($_POST["d_islem_turu"] ?? ''));

    if ($duzenle_id <= 0 || $yeni_plaka === '' || $yeni_ted === '' || !in_array($yeni_islem_turu, ['yukleme', 'bosaltma'], true)) {
        $hata = "Plaka, Tedarikçi ve İşlem Türü alanları zorunludur.";
    } else {
        // Sadece analiz_yapildi=0 olan kayıtlar düzenlenebilir
        $kontrol = $baglanti->query("SELECT id FROM hammadde_girisleri WHERE id=$duzenle_id AND analiz_yapildi=0 LIMIT 1");
        if ($kontrol && $kontrol->num_rows > 0) {
            $yeni_islem_turu_sql = $baglanti->real_escape_string($yeni_islem_turu);
            $yeni_islem_turu_label = ($yeni_islem_turu === 'bosaltma') ? 'Boşaltma' : 'Yükleme';
            $sql_upd = "UPDATE hammadde_girisleri SET arac_plaka='$yeni_plaka', tedarikci='$yeni_ted', islem_turu='$yeni_islem_turu_sql' WHERE id=$duzenle_id";
            if ($baglanti->query($sql_upd)) {
                if (function_exists('systemLogKaydet')) {
                    systemLogKaydet($baglanti, 'UPDATE', 'Araç Kabul Düzenleme',
                        "ID:$duzenle_id | Plaka: $yeni_plaka | Tedarikçi: $yeni_ted | İşlem Türü: $yeni_islem_turu_label");
                }
                $mesaj = "✅ Kayıt güncellendi.";
            } else {
                $hata = "Güncelleme hatası: " . $baglanti->error;
            }
        } else {
            $hata = "Bu kayıt düzenlenemez (işlem görmüş veya bulunamadı).";
        }
    }
}

// ─────────────────────────────────────────────
// FİLTRELER
// ─────────────────────────────────────────────
$f_baslangic = $_GET['f_baslangic'] ?? '';
$f_bitis     = $_GET['f_bitis']     ?? '';
$f_arama     = $_GET['f_arama']     ?? '';

$where = "WHERE 1=1";
if ($f_baslangic !== '') {
    $where .= " AND hg.tarih >= '" . $baglanti->real_escape_string($f_baslangic) . " 00:00:00'";
}
if ($f_bitis !== '') {
    $where .= " AND hg.tarih <= '" . $baglanti->real_escape_string($f_bitis) . " 23:59:59'";
}
if ($f_arama !== '') {
    $escaped_arama = $baglanti->real_escape_string($f_arama);
    $where .= " AND (hg.tedarikci LIKE '%$escaped_arama%' OR hg.arac_plaka LIKE '%$escaped_arama%')";
}

// ─────────────────────────────────────────────
// LİSTE
// ─────────────────────────────────────────────
$sql_liste = "SELECT hg.id, hg.tarih, hg.arac_plaka, hg.tedarikci, hg.analiz_yapildi,
                     hg.parti_no, hg.hammadde_id, hg.islem_turu, h.ad AS hammadde_adi
              FROM hammadde_girisleri hg
              LEFT JOIN hammaddeler h ON h.id = hg.hammadde_id
              $where
              ORDER BY hg.tarih DESC
              LIMIT 500";

$liste = $baglanti->query($sql_liste);

$filtre_aktif = ($f_baslangic || $f_bitis || $f_arama);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Araç Kabul - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary:  #0f172a;
            --accent:   #f59e0b;
            --success:  #10b981;
            --danger:   #ef4444;
            --bg:       #f1f5f9;
            --card-bg:  #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: #1e293b;
        }

        /* ── Page Header ── */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            border-radius: 1.25rem;
            margin-top: 1.25rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 16px 28px -14px rgba(15,23,42,.55);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: "";
            position: absolute;
            top: -65%; right: -8%;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245,158,11,.22) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ── Cards ── */
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,.06);
            margin-bottom: 1.5rem;
            background: var(--card-bg);
        }
        .card-header {
            border-radius: 14px 14px 0 0 !important;
            padding: 1.1rem 1.4rem;
            font-weight: 600;
        }

        /* ── Form ── */
        .form-label {
            font-weight: 500;
            font-size: .875rem;
            color: #64748b;
            margin-bottom: .4rem;
        }
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .6rem .85rem;
            font-size: .95rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,.12);
        }

        /* ── Buttons ── */
        .btn-save {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: .7rem 1.6rem;
            border-radius: 9px;
            transition: transform .15s, box-shadow .15s;
        }
        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16,185,129,.3);
            color: #fff;
        }

        /* ── Table ── */
        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 2px solid #e2e8f0;
            padding: .9rem 1rem;
            white-space: nowrap;
        }
        .table tbody td {
            padding: .85rem 1rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .table tbody tr:hover {
            background: #f8fafc;
        }

        /* ── Badges ── */
        .badge-plaka {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: .3rem .65rem;
            border-radius: 6px;
            font-weight: 700;
            letter-spacing: .5px;
            font-size: .8rem;
        }
        .badge-analiz-bekliyor {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .badge-analiz-kismi {
            background: #fef08a; /* sarı ton */
            color: #854d0e;
            border: 1px solid #fde047;
        }
        .badge-analiz-tamam {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* ── Filter card ── */
        .filter-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.2rem 1.4rem;
            margin-bottom: 1.25rem;
        }

        /* ── Stats row ── */
        .stat-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: .75rem 1.1rem;
            text-align: center;
        }
        .stat-box .stat-num {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-box .stat-label {
            font-size: .75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* scrollbar */
        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* DataTables custom */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .35rem .7rem;
            font-size: .875rem;
            color: #334155;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,.12);
        }
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            font-size: .875rem;
            color: #64748b;
            font-weight: 500;
        }
        .dataTables_wrapper .dataTables_info {
            font-size: .8rem;
            color: #94a3b8;
            padding-top: .6rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            font-size: .8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }
        table.dataTable thead .sorting::after,
        table.dataTable thead .sorting_asc::after,
        table.dataTable thead .sorting_desc::after {
            top: 50%;
            transform: translateY(-50%);
        }
        .dt-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
<?php include("navbar.php"); ?>

<div class="container-fluid px-md-4 pb-5" style="max-width:1400px;margin:0 auto;">

    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="fw-bold mb-1"><i class="fas fa-truck-loading me-2"></i>Araç Kabul</h2>
                <p class="mb-0" style="color:rgba(255,255,255,.75)">Gelen araçların tedarikçi, plaka ve işlem türü kaydı</p>
            </div>
        </div>
    </div>

    <!-- ─── GİRİŞ FORMU ─── -->
    <div class="card">
        <div class="card-header" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Yeni Araç Kabul Kaydı</h5>
        </div>
        <div class="card-body p-4">
            <form method="post" id="girisForm">
                <input type="hidden" name="gecmis_mod" id="gecmis_mod_input" value="<?php echo $gecmis_form_acik ? '1' : '0'; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Tedarikçi Firma <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-building text-muted"></i></span>
                            <input type="text" name="tedarikci" id="alan_tedarikci" class="form-control"
                                   placeholder="Firma adı girin..."
                                   value="<?php echo htmlspecialchars($_POST['tedarikci'] ?? ''); ?>"
                                   required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Araç Plaka <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-car text-muted"></i></span>
                            <input type="text" name="plaka" id="alan_plaka" class="form-control"
                                   placeholder="27 ABC 123"
                                   value="<?php echo htmlspecialchars($_POST['plaka'] ?? ''); ?>"
                                   required oninput="this.value=this.value.toUpperCase()"
                                   style="text-transform:uppercase;font-weight:700;letter-spacing:.5px;">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">İşlem Türü <span class="text-danger">*</span></label>
                        <select name="islem_turu" id="alan_islem_turu" class="form-select" required>
                            <option value="">Seçin...</option>
                            <option value="yukleme" <?php echo (($_POST['islem_turu'] ?? '') === 'yukleme') ? 'selected' : ''; ?>>Yükleme</option>
                            <option value="bosaltma" <?php echo (($_POST['islem_turu'] ?? '') === 'bosaltma') ? 'selected' : ''; ?>>Boşaltma</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="giris_yap" class="btn btn-save w-100">
                            <i class="fas fa-save me-2"></i>Kaydet
                        </button>
                    </div>
                </div>
                <?php if ($is_patron): ?>
                    <div class="mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gecmisToggleBtn" aria-expanded="<?php echo $gecmis_form_acik ? 'true' : 'false'; ?>">
                            <i class="fas <?php echo $gecmis_form_acik ? 'fa-calendar-minus' : 'fa-calendar-plus'; ?> me-1"></i>
                            <?php echo $gecmis_form_acik ? 'Geçmiş Tarihli Girişi Kapat' : 'Geçmiş Tarihli Giriş'; ?>
                        </button>
                    </div>

                    <div class="row g-3 mt-1" id="gecmisAlan" style="display: <?php echo $gecmis_form_acik ? 'flex' : 'none'; ?>;">
                        <div class="col-md-3">
                            <label class="form-label">Geçmiş Tarih <span class="text-danger">*</span></label>
                            <input type="date" name="gecmis_tarih" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['gecmis_tarih'] ?? ''); ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Geçmiş Saat <span class="text-danger">*</span></label>
                            <input type="time" name="gecmis_saat" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['gecmis_saat'] ?? ''); ?>">
                        </div>
                    </div>

                    <p class="text-muted small mt-2 mb-0" id="gecmisBilgi" style="display: <?php echo $gecmis_form_acik ? 'block' : 'none'; ?>;">
                        <i class="fas fa-history me-1"></i>
                        Geçmiş tarihli girişte tarih-saat alanları zorunludur ve gelecek tarih seçilemez.
                    </p>
                <?php endif; ?>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Hammadde cinsi ve parti numarası daha sonra <strong>Hammadde Analiz</strong> sayfasından girilecektir.
                </p>
            </form>
        </div>
    </div>

    <!-- ─── FİLTRELEME ─── -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="cursor:pointer;"
             data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <span><i class="fas fa-filter me-2"></i>Kayıtları Filtrele
                <?php if ($filtre_aktif): ?>
                    <span class="badge ms-2" style="background:var(--accent);color:#000;">Aktif</span>
                <?php endif; ?>
            </span>
            <i class="fas fa-chevron-down text-white-50"></i>
        </div>
        <div class="collapse <?php echo $filtre_aktif ? 'show' : ''; ?>" id="filterCollapse">
            <div class="card-body bg-light border-bottom">
                <form method="GET" action="hammadde.php" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Başlangıç Tarihi</label>
                        <input type="date" name="f_baslangic" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($f_baslangic); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Bitiş Tarihi</label>
                        <input type="date" name="f_bitis" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($f_bitis); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-bold">Kelime Ara (Plaka, Firma)</label>
                        <input type="text" name="f_arama" class="form-control form-control-sm"
                               placeholder="Kelime yazın..." value="<?php echo htmlspecialchars($f_arama); ?>">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="submit" class="btn btn-sm btn-dark w-100 mb-1">
                            <i class="fas fa-search me-1"></i>Filtrele
                        </button>
                        <a href="hammadde.php" class="btn btn-sm btn-secondary w-100">
                            <i class="fas fa-times me-1"></i>Temizle
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ─── KAYIT LİSTESİ ─── -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Son Araç Kabul Kayıtları</h5>
            <?php if ($liste): ?>
                <span class="badge" style="background:var(--accent);color:#000;font-size:.8rem;">
                    <?php echo $liste->num_rows; ?> kayıt
                </span>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="hammaddeListe">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tarih</th>
                        <th>Plaka</th>
                        <th>Tedarikçi</th>
                        <th>İşlem Türü</th>
                        <th>Hammadde Cinsi</th>
                        <th>Durum</th>
                        <th class="text-center">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($liste && $liste->num_rows > 0):
                        while ($row = $liste->fetch_assoc()):
                            $is_analiz = (int)($row['analiz_yapildi'] ?? 0);
                            $islem_turu_kayit = (string)($row['islem_turu'] ?? '');
                            $is_yukleme = ($islem_turu_kayit === 'yukleme');
                    ?>
                    <tr>
                        <td class="text-muted small"><?php echo $row['id']; ?></td>
                        <td data-order="<?php echo strtotime($row['tarih']); ?>">
                            <div class="fw-500" style="font-size:.875rem;">
                                <?php echo date('d.m.Y', strtotime($row['tarih'])); ?>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?php echo date('H:i', strtotime($row['tarih'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge-plaka"><?php echo htmlspecialchars($row['arac_plaka'] ?? '-'); ?></span>
                        </td>
                        <td style="font-size:.9rem;">
                            <?php echo htmlspecialchars($row['tedarikci'] ?? '-'); ?>
                        </td>
                        <td>
                            <?php if ($islem_turu_kayit === 'yukleme'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Yükleme</span>
                            <?php elseif ($islem_turu_kayit === 'bosaltma'): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Boşaltma</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Bilinmiyor</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['hammadde_adi'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.8rem;">
                                    <?php echo htmlspecialchars($row['hammadde_adi']); ?>
                                </span>
                            <?php elseif ($is_yukleme): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.8rem;">
                                    Yükleme Kaydı
                                </span>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-clock me-1"></i>Lab girişi bekleniyor</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_yukleme): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle py-1 px-2" style="font-size:.75rem;">
                                    <i class="fas fa-truck-loading me-1"></i>Yüklemeye Uygun
                                </span>
                            <?php elseif ($is_analiz === 2): ?>
                                <span class="badge badge-analiz-tamam py-1 px-2" style="font-size:.75rem;">
                                    <i class="fas fa-check-double me-1"></i>Analiz Tamamlandı
                                </span>
                            <?php elseif ($is_analiz === 1): ?>
                                <span class="badge badge-analiz-kismi py-1 px-2" style="font-size:.75rem;">
                                    <i class="fas fa-edit me-1"></i>Kısmi Analiz
                                </span>
                            <?php else: ?>
                                <span class="badge badge-analiz-bekliyor py-1 px-2" style="font-size:.75rem;">
                                    <i class="fas fa-hourglass-half me-1"></i>Analiz Bekliyor
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($is_analiz === 0): ?>
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="duzenleAc(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['arac_plaka'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['tedarikci'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['islem_turu'] ?? '', ENT_QUOTES); ?>')"
                                            title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="silOnay(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['arac_plaka'] ?? '', ENT_QUOTES); ?>')"
                                            title="Kaydı Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-lock"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-30"></i>
                            Henüz kayıt bulunmuyor.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Silme gizli form -->
<form method="post" id="silForm" style="display:none;">
    <input type="hidden" name="sil_id" id="sil_id_input">
    <input type="hidden" name="kayit_sil" value="1">
</form>

<!-- ─── DÜZENLEME MODALİ ─── -->
<div class="modal fade" id="duzenleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2"></i>Kayıt Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <input type="hidden" name="duzenle_id" id="d_id">
                    <div class="mb-3">
                        <label class="form-label">Tedarikçi Firma <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-building text-muted"></i></span>
                            <input type="text" name="d_tedarikci" id="d_tedarikci"
                                   class="form-control" placeholder="Firma adı" required autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Araç Plaka <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-car text-muted"></i></span>
                            <input type="text" name="d_plaka" id="d_plaka"
                                   class="form-control" placeholder="27 ABC 123" required
                                   oninput="this.value=this.value.toUpperCase()"
                                   style="text-transform:uppercase;font-weight:700;letter-spacing:.5px;">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">İşlem Türü <span class="text-danger">*</span></label>
                        <select name="d_islem_turu" id="d_islem_turu" class="form-select" required>
                            <option value="">Seçin...</option>
                            <option value="yukleme">Yükleme</option>
                            <option value="bosaltma">Boşaltma</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>İptal
                    </button>
                    <button type="submit" name="kayit_duzenle" class="btn btn-primary fw-bold">
                        <i class="fas fa-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

<script>
    $(document).ready(function () {
        <?php if (!empty($mesaj)): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '<?php echo addslashes(str_replace(["✅ ", "✓ ", "⚠️ "], "", strip_tags($mesaj))); ?>',
            showConfirmButton: false,
            showCloseButton: true,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        <?php endif; ?>

        <?php if (!empty($hata)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Hata!',
            text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: ", "⚠️ "], "", strip_tags($hata))); ?>',
            confirmButtonColor: '#0f172a'
        });
        <?php endif; ?>
        var table = $('#hammaddeListe').DataTable({
            order: [[1, 'desc'], [0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Tamamı']],
            searching: false, // Kendi arama kutusunu kapat
            language: {
                emptyTable:     'Tabloda veri bulunmuyor',
                info:           '_TOTAL_ kayıttan _START_–_END_ arası gösteriliyor',
                infoEmpty:      'Kayıt yok',
                infoFiltered:   '(_MAX_ kayıt içinden filtrelendi)',
                lengthMenu:     '_MENU_ kayıt göster',
                loadingRecords: 'Yükleniyor...',
                processing:     'İşleniyor...',
                zeroRecords:    'Eşleşen kayıt bulunamadı',
                paginate: {
                    first:    'İlk',
                    last:     'Son',
                    next:     'Sonraki ›',
                    previous: '‹ Önceki'
                }
            },
            columnDefs: [
                { orderable: false, targets: 7 }   // İşlem sütunu sıralanamaz
            ],
            dom: '<"dt-top-row"l>rtip'
        });

    });

    function silOnay(id, plaka) {
        Swal.fire({
            title: 'Emin misiniz?',
            text: plaka + ' plakali arac kaydi silinecek!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Vazgec',
            confirmButtonText: 'Sil!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('sil_id_input').value = id;
                document.getElementById('silForm').submit();
            }
        });
    }

    function duzenleAc(id, plaka, tedarikci, islemTuru) {
        document.getElementById('d_id').value        = id;
        document.getElementById('d_plaka').value     = plaka;
        document.getElementById('d_tedarikci').value = tedarikci;
        document.getElementById('d_islem_turu').value = islemTuru;
        new bootstrap.Modal(document.getElementById('duzenleModal')).show();
    }

    // Plaka alanları otomatik büyük harf
    const gecmisToggleBtn = document.getElementById('gecmisToggleBtn');
    const gecmisAlan = document.getElementById('gecmisAlan');
    const gecmisBilgi = document.getElementById('gecmisBilgi');
    const gecmisModInput = document.getElementById('gecmis_mod_input');
    const gecmisTarihInput = document.querySelector('input[name="gecmis_tarih"]');
    const gecmisSaatInput = document.querySelector('input[name="gecmis_saat"]');

    function gecmisAlaniDurumunuAyarla(acik) {
        if (!gecmisAlan || !gecmisModInput || !gecmisToggleBtn) {
            return;
        }

        gecmisAlan.style.display = acik ? 'flex' : 'none';
        if (gecmisBilgi) {
            gecmisBilgi.style.display = acik ? 'block' : 'none';
        }
        gecmisModInput.value = acik ? '1' : '0';
        gecmisToggleBtn.setAttribute('aria-expanded', acik ? 'true' : 'false');
        gecmisToggleBtn.innerHTML = acik
            ? '<i class="fas fa-calendar-minus me-1"></i>Geçmiş Tarihli Girişi Kapat'
            : '<i class="fas fa-calendar-plus me-1"></i>Geçmiş Tarihli Giriş';

        if (gecmisTarihInput) {
            gecmisTarihInput.required = acik;
        }
        if (gecmisSaatInput) {
            gecmisSaatInput.required = acik;
        }
    }

    if (gecmisToggleBtn) {
        gecmisToggleBtn.addEventListener('click', function () {
            const acikMi = gecmisModInput && gecmisModInput.value === '1';
            gecmisAlaniDurumunuAyarla(!acikMi);
        });
        gecmisAlaniDurumunuAyarla(gecmisModInput && gecmisModInput.value === '1');
    }
    document.getElementById('alan_plaka')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
</script>

<?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>
</html>

