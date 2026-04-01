<?php
session_start();
include("baglan.php");
include("helper_functions.php");

oturumKontrol();

// Sadece onay yetkisi olanlar erişebilir
if (!onayYetkisiVar($baglanti)) {
    die(yetkisizErisim());
}

$mesaj = "";
$hata = "";

// Onay verme işlemi (mevcut sistem)
if (isset($_POST['onay_ver'])) {
    $onay_id = $_POST['onay_id'];
    $karar = $_POST['karar']; // 'onayla' veya 'reddet'
    $red_aciklama = $_POST['red_aciklama'] ?? '';

    $onayla = ($karar == 'onayla');

    if (onayVer($baglanti, $onay_id, $onayla, $red_aciklama)) {
        $mesaj = $onayla ? "✅ İşlem onaylandı!" : "❌ İşlem reddedildi!";

        // === SYSTEM LOG KAYDI ===
        $action_type = $onayla ? 'APPROVAL' : 'REJECT';
        systemLogKaydet(
            $baglanti,
            $action_type,
            'Onay Merkezi',
            ($onayla ? "Onay verildi" : "Reddedildi") . " | Onay ID: $onay_id"
        );
    } else {
        $hata = "❌ Onay işlemi sırasında hata oluştu!";
    }
}

// Hammadde onay işlemi
if (isset($_POST['hammadde_onay'])) {
    $akis_id = (int) $_POST['akis_id'];
    $karar = $_POST['karar'];
    $red_aciklama = trim($_POST['red_aciklama'] ?? '');
    $birim_fiyat_raw = trim($_POST['birim_fiyat'] ?? '');
    $odeme_tarihi_raw = trim($_POST['odeme_tarihi'] ?? '');

    // Tablo var mı kontrol et
    $tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'hammadde_kabul_akisi'");
    if ($tablo_kontrol && $tablo_kontrol->num_rows > 0) {
        $mevcut = $baglanti->query("SELECT asama FROM hammadde_kabul_akisi WHERE id = $akis_id")->fetch_assoc();
        $eski_asama = $mevcut ? $mevcut['asama'] : 'bilinmiyor';

        if ($birim_fiyat_raw === '' || $odeme_tarihi_raw === '') {
            $hata = "Birim fiyat ve ödeme tarihi zorunludur!";
        } elseif (!is_numeric($birim_fiyat_raw)) {
            $hata = "Birim fiyat geçerli bir sayı olmalıdır!";
        } else {
            $birim_fiyat = (float) $birim_fiyat_raw;
            $odeme_tarihi = $baglanti->real_escape_string($odeme_tarihi_raw);

        if ($karar === 'onayla') {
            $baglanti->query("UPDATE hammadde_kabul_akisi SET 
                asama = 'satina_bekliyor', 
                onay_durum = 'onaylandi',
                onaylayan_user_id = {$_SESSION['user_id']},
                onay_tarihi = NOW(),
                birim_fiyat = $birim_fiyat,
                odeme_tarihi = '$odeme_tarihi'
                WHERE id = $akis_id");

            if (function_exists('akisGecmisKaydet')) {
                akisGecmisKaydet($baglanti, $akis_id, $eski_asama, 'satina_bekliyor', 'Onay Merkezi üzerinden onaylandi. Satınalma işlemleri bekleniyor.');
            }

            // === SYSTEM LOG KAYDI ===
            systemLogKaydet(
                $baglanti,
                'APPROVAL',
                'Hammadde Kabul',
                "Hammadde onaylandi | Akis ID: $akis_id"
            );

            $mesaj = "✅ Hammadde başarıyla onaylandı!";

        } elseif ($karar === 'reddet') {
            if (empty($red_aciklama)) {
                $hata = "Red için açıklama gereklidir!";
            } else {
                $red_esc = $baglanti->real_escape_string($red_aciklama);
                $baglanti->query("UPDATE hammadde_kabul_akisi SET 
                    asama = 'reddedildi', 
                    onay_durum = 'reddedildi',
                    onaylayan_user_id = {$_SESSION['user_id']},
                    onay_tarihi = NOW(),
                    red_aciklama = '$red_esc',
                    birim_fiyat = $birim_fiyat,
                    odeme_tarihi = '$odeme_tarihi'
                    WHERE id = $akis_id");

                if (function_exists('akisGecmisKaydet')) {
                    akisGecmisKaydet($baglanti, $akis_id, $eski_asama, 'reddedildi', "Red sebebi: $red_aciklama");
                }

                // === SYSTEM LOG KAYDI ===
                systemLogKaydet(
                    $baglanti,
                    'REJECT',
                    'Hammadde Kabul',
                    "Hammadde reddedildi | Akis ID: $akis_id | Sebep: $red_aciklama"
                );

                $mesaj = "❌ Hammadde reddedildi.";
            }
        }
        }
    }
}

// İşlemi tamamla
if (isset($_POST['tamamla'])) {
    $akis_id = (int) $_POST['akis_id'];
    $baglanti->query("UPDATE hammadde_kabul_akisi SET asama = 'satina_bekliyor' WHERE id = $akis_id");
    $mesaj = "✅ İşlem satın alma onayına gönderildi!";
}

// Red nedeni guncelle
if (isset($_POST['red_nedeni_duzenle'])) {
    $akis_id = (int) $_POST['akis_id'];
    $yeni_aciklama = mysqli_real_escape_string($baglanti, trim($_POST['yeni_red_aciklama']));

    $baglanti->query("UPDATE hammadde_kabul_akisi SET 
        red_aciklama = '$yeni_aciklama',
        guncelleme_tarihi = NOW()
        WHERE id = $akis_id");

    if (function_exists('akisGecmisKaydet')) {
        akisGecmisKaydet($baglanti, $akis_id, 'reddedildi', 'reddedildi', "Red nedeni güncellendi: $yeni_aciklama");
    }

    $mesaj = "✅ Red açıklaması başarıyla güncellendi!";
}

// Kantar + Birim Fiyat/Ödeme Tarihi düzenle
if (isset($_POST['kantar_duzenle'])) {
    $akis_id = (int) $_POST['akis_id'];
    $birim_fiyat_raw = trim($_POST['birim_fiyat'] ?? '');
    $odeme_tarihi_raw = trim($_POST['odeme_tarihi'] ?? '');

    if ($birim_fiyat_raw === '' || $odeme_tarihi_raw === '') {
        $hata = "Birim fiyat ve ödeme tarihi zorunludur!";
    } elseif (!is_numeric($birim_fiyat_raw)) {
        $hata = "Birim fiyat geçerli bir sayı olmalıdır!";
    } else {
        $birim_fiyat = (float) $birim_fiyat_raw;
        $odeme_tarihi = mysqli_real_escape_string($baglanti, $odeme_tarihi_raw);

        $baglanti->query("UPDATE hammadde_kabul_akisi SET
            birim_fiyat = $birim_fiyat,
            odeme_tarihi = '$odeme_tarihi',
            guncelleme_tarihi = NOW()
            WHERE id = $akis_id");

        if (function_exists('akisGecmisKaydet')) {
            akisGecmisKaydet($baglanti, $akis_id, 'duzenleme', 'duzenleme', "Fiyat/ödeme tarihi güncellendi. Birim Fiyat: $birim_fiyat, Ödeme Tarihi: $odeme_tarihi");
        }

        systemLogKaydet($baglanti, 'UPDATE', 'Hammadde Kabul', "Fiyat/Ödeme tarihi güncellendi | Akis ID: $akis_id");
        $mesaj = "✅ Ödeme bilgileri güncellendi!";
    }
}

// Reddedilen hammaddeyi tamamla (arşivle)
if (isset($_POST['red_tamamla'])) {
    $akis_id = (int) $_POST['akis_id'];
    $baglanti->query("UPDATE hammadde_kabul_akisi SET asama = 'tamamlandi', guncelleme_tarihi = NOW() WHERE id = $akis_id");

    if (function_exists('akisGecmisKaydet')) {
        akisGecmisKaydet($baglanti, $akis_id, 'reddedildi', 'tamamlandi', "Reddedilen hammadde tamamlandı/arşivlendi.");
    }

    $mesaj = "✅ Reddedilen hammadde tamamlandı!";
}

// Bekleyen onayları çek
$bekleyen_onaylar_result = bekleyenOnaylar($baglanti);

// Onaylanan son 10 kayıt
$onaylanan_sql = "SELECT ob.*, u1.kadi as olusturan, u2.kadi as onaylayan 
                  FROM onay_bekleyenler ob 
                  LEFT JOIN users u1 ON ob.olusturan_user_id = u1.id 
                  LEFT JOIN users u2 ON ob.onaylayan_user_id = u2.id 
                  WHERE ob.onay_durum != 'bekliyor' 
                  ORDER BY ob.onay_tarihi DESC LIMIT 10";
$onaylanan_result = $baglanti->query($onaylanan_sql);

// İstatistikler
$istatistik_sql = "SELECT onay_durum, COUNT(*) as adet FROM onay_bekleyenler GROUP BY onay_durum";
$istatistik_result = $baglanti->query($istatistik_sql);
$istatistikler = [];
while ($row = $istatistik_result->fetch_assoc()) {
    $istatistikler[$row['onay_durum']] = $row['adet'];
}

$bekleyen_adet = $istatistikler['bekliyor'] ?? 0;
$onaylanan_adet = $istatistikler['onaylandi'] ?? 0;
$reddedilen_adet = $istatistikler['reddedildi'] ?? 0;

// Hammadde kabul akışı verileri (tablo varsa)
$hammadde_bekleyenler = null;
$hammadde_tamamlananlar = null;
$hammadde_bekleyen_adet = 0;

$tablo_kontrol = @$baglanti->query("SHOW TABLES LIKE 'hammadde_kabul_akisi'");
if ($tablo_kontrol && $tablo_kontrol->num_rows > 0) {
    // Bekleyen hammadde onayları
    $hammadde_bekleyenler = $baglanti->query("
        SELECT a.*, hg.arac_plaka, hg.tedarikci, hg.miktar_kg, hg.hektolitre, hg.tarih as giris_tarihi, hg.parti_no,
               la.hektolitre as lab_hektolitre, la.nem as lab_nem, la.protein as lab_protein, la.nisasta as lab_nisasta, la.sertlik as lab_sertlik, la.gluten as lab_gluten, la.index_degeri as lab_index,
               h.ad as hammadde_adi, h.hammadde_kodu,
               s.silo_adi
        FROM hammadde_kabul_akisi a
        LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
        LEFT JOIN silolar s ON hg.silo_id = s.id
        LEFT JOIN lab_analizleri la ON la.hammadde_giris_id = hg.id
        WHERE a.asama IN ('bekliyor', 'numune_alindi', 'analiz_yapildi', 'onay_bekleniyor', 'onaylandi', 'kantar', 'reddedildi')
        ORDER BY a.olusturma_tarihi DESC
    ");

    $hammadde_bekleyen_adet = $hammadde_bekleyenler ? $hammadde_bekleyenler->num_rows : 0;

    // Tamamlananlar ve Reddedilenler
    $hammadde_tamamlananlar = $baglanti->query("
        SELECT a.*, hg.arac_plaka, hg.tedarikci, hg.miktar_kg, hg.tarih as giris_tarihi, hg.parti_no,
               h.ad as hammadde_adi, h.hammadde_kodu,
               s.silo_adi,
               u.kadi as onaylayan_kadi
        FROM hammadde_kabul_akisi a
        LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
        LEFT JOIN silolar s ON hg.silo_id = s.id
        LEFT JOIN users u ON a.onaylayan_user_id = u.id
        WHERE a.asama IN ('tamamlandi', 'satina_bekliyor')
        ORDER BY a.guncelleme_tarihi DESC
        LIMIT 200
    ");
}

$aktif_tab = isset($_GET['tab']) ? $_GET['tab'] : 'genel';
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onay Merkezi - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .onay-badge {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .onay-card {
            transition: transform 0.2s;
        }

        .onay-card:hover {
            transform: translateY(-5px);
        }

        .nav-tabs .nav-link {
            color: #64748b;
            font-weight: 500;
            border: none;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #f5a623;
            border-bottom: 3px solid #f5a623;
            background: transparent;
        }

        .nav-tabs .nav-link:hover {
            color: #f5a623;
            border-color: transparent;
        }

        .akis-kart {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .akis-kart:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .akis-header {
            padding: 14px 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .akis-body {
            padding: 18px;
        }

        .plaka-badge {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        .asama-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .asama-bekliyor {
            background: #f1f5f9;
            color: #64748b;
        }

        .asama-numune_alindi {
            background: #dbeafe;
            color: #1e40af;
        }

        .asama-analiz_yapildi {
            background: #e0e7ff;
            color: #4338ca;
        }

        .asama-onay_bekleniyor {
            background: #fef3c7;
            color: #92400e;
        }

        .asama-onaylandi {
            background: #d1fae5;
            color: #065f46;
        }

        .asama-reddedildi {
            background: #fee2e2;
            color: #991b1b;
        }

        .asama-kantar {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .asama-tamamlandi {
            background: #d1fae5;
            color: #065f46;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .info-item {
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .info-label {
            font-size: 0.65rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="mb-4">
            <h2 class="mb-0"><i class="fas fa-check-circle text-success"></i> Onay Merkezi</h2>
            <p class="text-muted mb-0">Bekleyen işlemler ve onay geçmişi</p>
        </div>



        <!-- Tab Navigasyonu -->
        <ul class="nav nav-tabs mb-4" id="onayTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $aktif_tab == 'genel' ? 'active' : ''; ?>" href="?tab=genel">
                    <i class="fas fa-list-check me-1"></i> Genel Onaylar
                    <?php if ($bekleyen_adet > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?php echo $bekleyen_adet; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $aktif_tab == 'hammadde' ? 'active' : ''; ?>" href="?tab=hammadde">
                    <i class="fas fa-truck-loading me-1"></i> Hammadde Kabul
                    <?php if ($hammadde_bekleyen_adet > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?php echo $hammadde_bekleyen_adet; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <?php if ($aktif_tab == 'genel'): ?>
            <!-- ============ GENEL ONAYLAR TAB ============ -->

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm onay-card bg-warning text-dark">
                        <div class="card-body d-flex align-items-center">
                            <div class="onay-badge bg-white bg-opacity-25 me-3">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 opacity-75">Bekleyen Onaylar</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $bekleyen_adet; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm onay-card bg-success text-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="onay-badge bg-white bg-opacity-25 me-3">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 opacity-75">Onaylanan</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $onaylanan_adet; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm onay-card bg-danger text-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="onay-badge bg-white bg-opacity-25 me-3">
                                <i class="fas fa-times"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 opacity-75">Reddedilen</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $reddedilen_adet; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bekleyen Onaylar -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="fas fa-hourglass-half"></i> Bekleyen Onaylar (<?php echo $bekleyen_adet; ?>)
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>İşlem Tipi</th>
                                <th>Açıklama</th>
                                <th>Oluşturan</th>
                                <th>Tarih</th>
                                <th>Öncelik</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($bekleyen_onaylar_result && $bekleyen_onaylar_result->num_rows > 0) {
                                while ($onay = $bekleyen_onaylar_result->fetch_assoc()) {
                                    $oncelik_badge = 'secondary';
                                    if ($onay['oncelik'] == 'yuksek')
                                        $oncelik_badge = 'warning';
                                    if ($onay['oncelik'] == 'acil')
                                        $oncelik_badge = 'danger';
                                    ?>
                                    <tr>
                                        <td><?php echo $onay['id']; ?></td>
                                        <td><span class="badge bg-info"><?php echo $onay['islem_tipi']; ?></span></td>
                                        <td><?php echo $onay['islem_aciklama'] ?? '-'; ?></td>
                                        <td><?php echo $onay['olusturan_kadi']; ?></td>
                                        <td><?php echo tarihFormat($onay['olusturma_tarihi']); ?></td>
                                        <td><span
                                                class="badge bg-<?php echo $oncelik_badge; ?>"><?php echo strtoupper($onay['oncelik']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success"
                                                onclick="onayModalAc(<?php echo $onay['id']; ?>, '<?php echo $onay['islem_tipi']; ?>', 'onayla')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="onayModalAc(<?php echo $onay['id']; ?>, '<?php echo $onay['islem_tipi']; ?>', 'reddet')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo "<tr><td colspan='7' class='text-center text-muted py-4'>
                                <i class='fas fa-check-double fa-3x mb-2 opacity-50'></i><br>
                                Bekleyen onay yok. Harika! 🎉
                              </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Son Onaylanan/Reddedilen -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-history"></i> Son Onay İşlemleri
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>İşlem Tipi</th>
                                <th>Oluşturan</th>
                                <th>Onaylayan</th>
                                <th>Onay Tarihi</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($onaylanan_result && $onaylanan_result->num_rows > 0) {
                                while ($row = $onaylanan_result->fetch_assoc()) {
                                    $durum_class = $row['onay_durum'] == 'onaylandi' ? 'success' : 'danger';
                                    $durum_icon = $row['onay_durum'] == 'onaylandi' ? 'check' : 'times';
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($row['islem_tipi']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['olusturan'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['onaylayan'] ?? '-'); ?></td>
                                        <td><?php echo tarihFormat($row['onay_tarihi']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $durum_class; ?>">
                                                <i class="fas fa-<?php echo $durum_icon; ?>"></i>
                                                <?php echo strtoupper($row['onay_durum']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo "<tr><td colspan='5' class='text-center text-muted py-4'>
                                <i class='fas fa-inbox fa-2x mb-2 opacity-50'></i><br>
                                Henüz onay geçmişi bulunmuyor.
                              </td></tr>";
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- ============ HAMMADDE KABUL TAB ============ -->

            <div class="mb-4">
                <h5 class="fw-bold"><i class="fas fa-hourglass-half text-warning me-2"></i>Bekleyen Hammadde İşlemleri</h5>

                <?php if ($hammadde_bekleyenler && $hammadde_bekleyenler->num_rows > 0): ?>
                    <?php while ($row = $hammadde_bekleyenler->fetch_assoc()): ?>
                        <div class="akis-kart">
                            <div class="akis-header">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="plaka-badge"><?php echo htmlspecialchars($row['arac_plaka'] ?? '-'); ?></span>
                                    <div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars(($row['hammadde_kodu'] ?? '') . ' - ' . ($row['hammadde_adi'] ?? '-')); ?>
                                        </div>
                                        <small
                                            class="text-muted"><?php echo htmlspecialchars($row['tedarikci'] ?? '-'); ?><?php echo !empty($row['parti_no']) ? " (Parti: " . htmlspecialchars($row['parti_no']) . ")" : ""; ?></small>
                                    </div>
                                </div>
                                <span class="asama-badge asama-<?php echo $row['asama']; ?>">
                                    <?php echo function_exists('asamaEtiket') ? asamaEtiket($row['asama']) : $row['asama']; ?>
                                </span>
                            </div>
                            <div class="akis-body">
                                <div class="info-grid mb-3">
                                    <div class="info-item">
                                        <div class="info-label">Miktar</div>
                                        <div class="info-value"><?php echo number_format($row['miktar_kg'] ?? 0, 0, ',', '.'); ?> kg
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Hedef Silo</div>
                                        <div class="info-value"><?php echo htmlspecialchars($row['silo_adi'] ?? '-'); ?></div>
                                    </div>
                                </div>

                                <button class="btn btn-sm btn-outline-secondary w-100 mb-3" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#labDetay<?php echo $row['id']; ?>" aria-expanded="false">
                                    <i class="fas fa-microscope me-1"></i> Analiz Detaylarını Gör
                                </button>

                                <div class="collapse mb-3" id="labDetay<?php echo $row['id']; ?>">
                                    <div class="card card-body bg-light py-2 px-3">
                                        <div class="row text-center g-2 small">
                                            <div class="col-4 col-md-3">
                                                <strong>Protein:</strong><br><?php echo $row['lab_protein'] !== null ? $row['lab_protein'] . '%' : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>Gluten:</strong><br><?php echo $row['lab_gluten'] !== null ? $row['lab_gluten'] . '%' : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>Nem:</strong><br><?php echo $row['lab_nem'] !== null ? $row['lab_nem'] . '%' : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>Hektolitre:</strong><br><?php echo $row['lab_hektolitre'] !== null ? $row['lab_hektolitre'] : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>İndex:</strong><br><?php echo $row['lab_index'] !== null ? $row['lab_index'] : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>Sertlik:</strong><br><?php echo $row['lab_sertlik'] !== null ? $row['lab_sertlik'] : '-'; ?>
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <strong>Nişasta:</strong><br><?php echo $row['lab_nisasta'] !== null ? $row['lab_nisasta'] . '%' : '-'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($row['asama'] === 'bekliyor' || $row['asama'] === 'analiz_yapildi' || $row['asama'] === 'onay_bekleniyor'): ?>
                                    <!-- Onay/Red Butonları -->
                                    <div class="border rounded-3 bg-light p-2 p-md-3">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-12 col-md-5">
                                                <label class="form-label small mb-1 text-muted">Birim Fiyat *</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                    <input form="onayForm<?php echo $row['id']; ?>" type="number" step="0.0001" min="0"
                                                        name="birim_fiyat" class="form-control" placeholder="0,0000" required>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <label class="form-label small mb-1 text-muted">Ödeme Tarihi *</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                                    <input form="onayForm<?php echo $row['id']; ?>" type="date" name="odeme_tarihi"
                                                        class="form-control" required>
                                                </div>
                                            </div>

                                            <!-- Buttons: always equal width on mobile -->
                                            <div class="col-12 col-md-2">
                                                <div class="row g-2">
                                                    <div class="col-6 col-md-12 d-grid">
                                                        <button form="onayForm<?php echo $row['id']; ?>" type="submit" name="hammadde_onay"
                                                            class="btn btn-success btn-sm fw-semibold">
                                                            <i class="fas fa-check me-1"></i> Onayla
                                                        </button>
                                                    </div>
                                                    <div class="col-6 col-md-12 d-grid">
                                                        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#redModal<?php echo $row['id']; ?>">
                                                            <i class="fas fa-times me-1"></i> Reddet
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden form to submit approval -->
                                        <form method="post" id="onayForm<?php echo $row['id']; ?>">
                                            <input type="hidden" name="akis_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="karar" value="onayla">
                                        </form>
                                    </div>

                                    <!-- Red Modal -->
                                    <div class="modal fade" id="redModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Hammadde Reddi
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Bu hammaddeyi reddetme nedenini belirtiniz:</p>
                                                        <input type="hidden" name="akis_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="karar" value="reddet">
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-6">
                                                                <label class="form-label small mb-1">Birim Fiyat *</label>
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                                    <input type="number" step="0.0001" min="0" name="birim_fiyat"
                                                                        class="form-control" placeholder="0,0000" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label small mb-1">Ödeme Tarihi *</label>
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                                                    <input type="date" name="odeme_tarihi" class="form-control"
                                                                        required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <textarea name="red_aciklama" class="form-control" rows="3" required
                                                            placeholder="Red sebebini yazınız..."></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">İptal</button>
                                                        <button type="submit" name="hammadde_onay"
                                                            class="btn btn-danger">Reddet</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($row['asama'] === 'reddedildi'): ?>
                                    <div class="alert alert-danger p-2 mb-3 small">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        <strong>Red Nedeni:</strong>
                                        <?php echo htmlspecialchars($row['red_aciklama'] ?: ($row['notlar'] ?? 'Belirtilmedi')); ?>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-warning btn-sm"
                                            onclick="manuelKabulSwal(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-check-double me-1"></i> Manuel Kabul Et
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="redNedeniDuzenleModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['red_aciklama'] ?: ($row['notlar'] ?? ''))); ?>')">
                                            <i class="fas fa-edit me-1"></i> Red Nedenini Düzenle
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="redTamamlaSwal(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-archive me-1"></i> Tamamla
                                        </button>
                                    </div>

                                <?php elseif ($row['asama'] === 'onaylandi'): ?>
                                    <div class="alert alert-success p-2 mb-3 small">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Hammadde analizi onaylandı. Satınalma panelinden kantar tartımı beleniyor...
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                        <h5>Bekleyen hammadde işlemi yok</h5>
                        <p class="mb-0">Tüm hammadde kabul işlemleri tamamlandı.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tamamlanan Hammadde İşlemleri -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold d-flex align-items-center">
                    <i class="fas fa-history me-2 text-secondary"></i>Son Tamamlanan Hammadde İşlemleri
                </div>
                <div class="table-responsive">
                    <table id="tamamlananlarTablo" class="table table-hover mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Giriş Tarihi</th>
                                <th>Plaka</th>
                                <th>Hammadde</th>
                                <th>Tedarikçi</th>
                                <th>Miktar/Kantar</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hammadde_tamamlananlar && $hammadde_tamamlananlar->num_rows > 0): ?>
                                <?php while ($row = $hammadde_tamamlananlar->fetch_assoc()): ?>
                                    <?php
                                    $isReddedildi = ($row['onay_durum'] === 'reddedildi' || $row['asama'] === 'reddedildi');
                                    $isSatinaBekliyor = ($row['asama'] === 'satina_bekliyor');
                                    $isAktifRed = ($row['asama'] === 'reddedildi');
                                    $isRedTamamlandi = ($row['asama'] === 'tamamlandi' && $row['onay_durum'] === 'reddedildi');
                                    $isSatinalmaRed = ($row['asama'] === 'tamamlandi' && $row['onay_durum'] === 'satinalma_red');
                                    ?>
                                    <tr
                                        class="<?php echo $isReddedildi || $isSatinalmaRed ? 'table-danger' : ($isSatinaBekliyor ? 'table-info' : ''); ?>">
                                        <td data-order="<?php echo strtotime($row['giris_tarihi']); ?>">
                                            <?php echo date('d.m.Y H:i', strtotime($row['giris_tarihi'])); ?>
                                        </td>
                                        <td><span class=" badge bg-secondary">
                                                <?php echo htmlspecialchars($row['arac_plaka'] ?? '-'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars(($row['hammadde_kodu'] ?? '') . ' - ' . ($row['hammadde_adi'] ?? '-')); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['tedarikci'] ?? '-'); ?><?php echo !empty($row['parti_no']) ? "<br><small class='text-muted'>Parti: " . htmlspecialchars($row['parti_no']) . "</small>" : ""; ?>
                                        </td>
                                        <td>
                                            <?php echo number_format($row['kantar_net_kg'] ?? ($row['miktar_kg'] ?? 0), 2, ',', '.'); ?>
                                            kg
                                        </td>
                                        <td>
                                            <?php if ($isReddedildi): ?>
                                                <span class="asama-badge asama-reddedildi"
                                                    title="<?php echo htmlspecialchars($row['red_aciklama'] ?? ''); ?>"
                                                    style="cursor:help;">
                                                    <i class="fas fa-times-circle me-1"></i>Reddedildi
                                                </span>
                                                <?php if (!empty($row['red_aciklama'])): ?>
                                                    <br><small class="text-danger mt-1 d-inline-block"
                                                        style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                                        title="<?php echo htmlspecialchars($row['red_aciklama']); ?>">
                                                        <i
                                                            class="fas fa-comment-dots me-1"></i><?php echo htmlspecialchars($row['red_aciklama']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php elseif ($isSatinaBekliyor): ?>
                                                <span class="asama-badge bg-info text-white">
                                                    <i class="fas fa-shopping-cart me-1"></i>Satın Alma Onayında
                                                </span>
                                            <?php elseif ($isSatinalmaRed): ?>
                                                <span class="asama-badge asama-reddedildi"
                                                    title="<?php echo htmlspecialchars($row['red_aciklama'] ?? ''); ?>"
                                                    style="cursor:help;">
                                                    <i class="fas fa-times-circle me-1"></i>Satın Alma Reddedildi
                                                </span>
                                                <?php if (!empty($row['red_aciklama'])): ?>
                                                    <br><small class="text-danger mt-1 d-inline-block"
                                                        style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                                        title="<?php echo htmlspecialchars($row['red_aciklama']); ?>">
                                                        <i
                                                            class="fas fa-comment-dots me-1"></i><?php echo htmlspecialchars($row['red_aciklama']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="asama-badge asama-tamamlandi">
                                                    <i
                                                        class="fas fa-check-circle me-1"></i><?php echo function_exists('asamaEtiket') ? asamaEtiket($row['asama']) : $row['asama']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($isAktifRed): ?>
                                                <!-- Henüz tamamlanmamış red: düzenle/kabul/tamamla butonları -->
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                        title="Red Nedenini Düzenle"
                                                        onclick="redNedeniDuzenleModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['red_aciklama'] ?? '')); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" title="Manuel Kabul Et"
                                                        onclick="manuelKabulSwal(<?php echo $row['id']; ?>)">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                                        title="Tamamla/Arşivle" onclick="redTamamlaSwal(<?php echo $row['id']; ?>)">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </div>
                                            <?php elseif ($isRedTamamlandi || $isSatinalmaRed): ?>
                                                <!-- Red yiyip tamamlanmış: Sadece neden düzenlenebilir -->
                                                <button type="button" class="btn btn-outline-danger btn-sm" title="Red Nedenini Düzenle"
                                                    onclick="redNedeniDuzenleModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['red_aciklama'] ?? '')); ?>')">
                                                    <i class="fas fa-edit"></i> Nedeni Düzenle
                                                </button>
                                            <?php else: ?>
                                                <!-- Normal kabul+tamamlandi: kantar düzenlenebilir -->
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="kantarDuzenleModal(
                                                        <?php echo (int) $row['id']; ?>,
                                                        '<?php echo htmlspecialchars((string) ($row['birim_fiyat'] ?? ''), ENT_QUOTES); ?>',
                                                        '<?php echo htmlspecialchars((string) ($row['odeme_tarihi'] ?? ''), ENT_QUOTES); ?>'
                                                    )">
                                                    <i class="fas fa-edit"></i> Düzenle
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Onay Modal -->
    <div class="modal fade" id="onayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header" id="modalHeader">
                        <h5 class="modal-title" id="modalTitle">Onay İşlemi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="onay_id" id="onayId">
                        <input type="hidden" name="karar" id="karar">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong id="islemTipi"></strong> işlemi için karar verin.
                        </div>

                        <div id="redAciklamaDiv" style="display:none;">
                            <label class="form-label">Red Açıklaması *</label>
                            <textarea name="red_aciklama" class="form-control" rows="3"
                                placeholder="Neden reddedildiğini açıklayın..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="onay_ver" class="btn" id="onayBtn">Onayla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kantar Düzenle Modal -->
    <div class="modal fade" id="kantarDuzenleModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Kantar / Ödeme Bilgisi Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="akis_id" id="editKantarAkisId">

                        <div class="mb-3">
                            <label class="form-label">Birim Fiyat *</label>
                            <input type="number" step="0.0001" min="0" name="birim_fiyat" id="editBirimFiyat"
                                class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ödeme Tarihi *</label>
                            <input type="date" name="odeme_tarihi" id="editOdemeTarihi" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="kantar_duzenle" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Red Nedeni Düzenle Modal -->
    <div class="modal fade" id="redNedeniDuzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Red Nedenini Düzenle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="akis_id" id="editRedAkisId">

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="yeni_red_aciklama" id="editRedAciklama" class="form-control" rows="4"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="red_nedeni_duzenle" class="btn btn-danger">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Gizli Formlar (SweetAlert2 ile kullanilacak) -->
    <form id="manuelKabulForm" method="post" style="display:none;">
        <input type="hidden" name="akis_id" id="swalManuelKabulAkisId">
        <input type="hidden" name="karar" value="onayla">
        <input type="hidden" name="hammadde_onay" value="1">
    </form>
    <form id="redTamamlaForm" method="post" style="display:none;">
        <input type="hidden" name="akis_id" id="swalRedTamamlaAkisId">
        <input type="hidden" name="red_tamamla" value="1">
    </form>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        function redNedeniDuzenleModal(id, aciklama) {
            document.getElementById('editRedAkisId').value = id;
            document.getElementById('editRedAciklama').value = aciklama;
            var myModal = new bootstrap.Modal(document.getElementById('redNedeniDuzenleModal'));
            myModal.show();
        }
        function kantarDuzenleModal(id, birimFiyat, odemeTarihi) {
            document.getElementById('editKantarAkisId').value = id;
            document.getElementById('editBirimFiyat').value = birimFiyat || '';
            document.getElementById('editOdemeTarihi').value = odemeTarihi || '';
            var myModal = new bootstrap.Modal(document.getElementById('kantarDuzenleModal'));
            myModal.show();
        }

        function manuelKabulSwal(akisId) {
            Swal.fire({
                title: 'Manuel Kabul',
                text: 'Bu ürün reddedilmiş durumda. Yine de manuel olarak kabul etmek istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e67e22',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check-double"></i> Evet, Kabul Et',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('swalManuelKabulAkisId').value = akisId;
                    document.getElementById('manuelKabulForm').submit();
                }
            });
        }

        function redTamamlaSwal(akisId) {
            Swal.fire({
                title: 'İşlemi Tamamla',
                text: 'Bu reddedilen hammadde işlemi tamamlanacak ve geçmiş kayıtlara taşınacak. Devam edilsin mi?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                cancelButtonColor: '#adb5bd',
                confirmButtonText: '<i class="fas fa-archive"></i> Tamamla',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('swalRedTamamlaAkisId').value = akisId;
                    document.getElementById('redTamamlaForm').submit();
                }
            });
        }
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

            // DataTable Init for Tamamlananlar
            if ($('#tamamlananlarTablo').length) {
                $('#tamamlananlarTablo').DataTable({
                    "order": [[0, "desc"]], // Tarihe gore azalan
                    "pageLength": 10,
                    "language": {
                        "emptyTable": "Yeterli veri yok.",
                        "info": "_TOTAL_ kayıttan _START_ - _END_ arasındakiler gös.",
                        "infoEmpty": "Kayıt yok",
                        "infoFiltered": "(_MAX_ kayıt içerisinden)",
                        "search": "Ara:",
                        "lengthMenu": "_MENU_ Kayıt",
                        "paginate": {
                            "first": "İlk",
                            "last": "Son",
                            "next": "Sonraki",
                            "previous": "Önceki"
                        }
                    }
                });
            }
        });
        const onayModal = new bootstrap.Modal(document.getElementById('onayModal'));

        function onayModalAc(onay_id, islem_tipi, karar) {
            document.getElementById('onayId').value = onay_id;
            document.getElementById('karar').value = karar;
            document.getElementById('islemTipi').textContent = islem_tipi;

            if (karar == 'onayla') {
                document.getElementById('modalHeader').className = 'modal-header bg-success text-white';
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check"></i> Onaylama';
                document.getElementById('onayBtn').className = 'btn btn-success';
                document.getElementById('onayBtn').innerHTML = '<i class="fas fa-check"></i> Onayla';
                document.getElementById('redAciklamaDiv').style.display = 'none';
            } else {
                document.getElementById('modalHeader').className = 'modal-header bg-danger text-white';
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-times"></i> Reddetme';
                document.getElementById('onayBtn').className = 'btn btn-danger';
                document.getElementById('onayBtn').innerHTML = '<i class="fas fa-times"></i> Reddet';
                document.getElementById('redAciklamaDiv').style.display = 'block';
            }

            onayModal.show();
        }

        function detayGor(islem_id, islem_tipi) {
            Swal.fire({
                icon: 'info',
                title: 'Bilgi',
                text: 'İşlem #' + islem_id + ' (' + islem_tipi + ') detayı yakında eklenecektir.',
                confirmButtonColor: '#3085d6'
            });
        }
    </script>
</body>

</html>