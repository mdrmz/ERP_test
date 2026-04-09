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
$kullanici_adi = $_SESSION["kadi"] ?? "Bilinmiyor";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header("Location: sikayetler.php");
    exit;
}

// Şikayet Verisini Çek
$sql = "SELECT s.*, m.firma_adi as org_musteri
        FROM sikayetler s 
        LEFT JOIN musteriler m ON s.musteri_id = m.id 
        WHERE s.id = $id";
$res = $baglanti->query($sql);
$sikayet = $res ? $res->fetch_assoc() : null;

if (!$sikayet) {
    echo "Şikayet bulunamadı.";
    exit;
}

$musteriAdiGoster = $sikayet['org_musteri'] ? $sikayet['org_musteri'] : ($sikayet['musteri_adi'] ?: 'Bilinmeyen Müşteri');
$isKapali = ($sikayet['durum'] === 'kapandi');

// Mesaj Gösterimleri
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'guncellendi')
        $mesaj = "✅ Şikayet detayları güncellendi.";
    if ($_GET['msg'] == 'faaliyet_eklendi')
        $mesaj = "✅ Yeni faaliyet başarıyla eklendi.";
    if ($_GET['msg'] == 'faaliyet_guncellendi')
        $mesaj = "✅ Faaliyet durumu güncellendi.";
    if ($_GET['msg'] == 'kapatildi')
        $mesaj = "✅ Şikayet dosyası başarıyla kapatıldı.";
}

// ==========================================
// FORM HANDLE İŞLEMLERİ (Buradaysa direkt POST edilir, değilse sikayetler.php'ye post edilir)
// Yönetimi tek sayfada tutmak daha pratik olabilir.
// ==========================================

// --- ŞİKAYET KÖK NEDEN / DÖF GÜNCELLEME ---
if (isset($_POST["sikayet_guncelle"])) {
    if (!$isKapali) {
        $kok_neden = mysqli_real_escape_string($baglanti, $_POST["kok_neden"] ?? "");
        $duzeltici_faaliyet = mysqli_real_escape_string($baglanti, $_POST["duzeltici_faaliyet"] ?? "");
        $onleyici_faaliyet = mysqli_real_escape_string($baglanti, $_POST["onleyici_faaliyet"] ?? "");
        $dof_sorumlu = mysqli_real_escape_string($baglanti, $_POST["dof_sorumlu"] ?? "");

        $hedef_kapanma_tarihi = !empty($_POST["hedef_kapanma_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["hedef_kapanma_tarihi"]) . "'" : "NULL";
        $yeni_durum = mysqli_real_escape_string($baglanti, $_POST["durum"] ?? $sikayet['durum']);

        $sql_update = "UPDATE sikayetler SET 
            kok_neden = '$kok_neden',
            duzeltici_faaliyet = '$duzeltici_faaliyet',
            onleyici_faaliyet = '$onleyici_faaliyet',
            dof_sorumlu = '$dof_sorumlu',
            hedef_kapanma_tarihi = $hedef_kapanma_tarihi,
            durum = '$yeni_durum'
            WHERE id = $id";

        if ($baglanti->query($sql_update)) {
            systemLogKaydet($baglanti, 'UPDATE', 'Şikayetler', "Şikayet kök neden/DÖF alanı güncellendi. No: " . $sikayet['sikayet_no']);
            header("Location: sikayet_detay.php?id=$id&msg=guncellendi");
            exit;
        } else {
            $hata = "Güncelleme hatası: " . $baglanti->error;
        }
    } else {
        $hata = "Kapalı şikayetler güncellenemez.";
    }
}

// --- FAALİYET EKLEME ---
if (isset($_POST["faaliyet_ekle"])) {
    if (!$isKapali) {
        $faaliyet_tipi = mysqli_real_escape_string($baglanti, $_POST["faaliyet_tipi"] ?? "duzeltici");
        $aciklama = mysqli_real_escape_string($baglanti, trim($_POST["aciklama"] ?? ""));
        $sorumlu = mysqli_real_escape_string($baglanti, trim($_POST["sorumlu"] ?? ""));
        $hedef_tarih = !empty($_POST["hedef_tarih"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["hedef_tarih"]) . "'" : "NULL";

        if (!empty($aciklama)) {
            $sql_f = "INSERT INTO sikayet_faaliyetleri (sikayet_id, faaliyet_tipi, aciklama, sorumlu, hedef_tarih, olusturan) 
                             VALUES ($id, '$faaliyet_tipi', '$aciklama', '$sorumlu', $hedef_tarih, '$kullanici_adi')";
            if ($baglanti->query($sql_f)) {
                // Şikayet durumu "dof_acildi" olsun
                if ($sikayet['durum'] == 'acik' || $sikayet['durum'] == 'inceleniyor') {
                    $baglanti->query("UPDATE sikayetler SET durum = 'dof_acildi' WHERE id = $id");
                }
                header("Location: sikayet_detay.php?id=$id&msg=faaliyet_eklendi");
                exit;
            } else {
                $hata = "Faaliyet eklenirken hata: " . $baglanti->error;
            }
        }
    }
}

// --- FAALİYET DURUM GÜNCELLEME ---
if (isset($_POST["faaliyet_guncelle"])) {
    $f_id = (int) $_POST["f_id"];
    $yeni_durum = mysqli_real_escape_string($baglanti, $_POST["f_durum"]);

    // Eğer tamamlandı işaretlenirse tarih atsın
    $tamamlanma_sql = ($yeni_durum === 'tamamlandi') ? "tamamlanma_tarihi = CURRENT_DATE()," : "tamamlanma_tarihi = NULL,";

    $baglanti->query("UPDATE sikayet_faaliyetleri SET $tamamlanma_sql durum = '$yeni_durum' WHERE id = $f_id AND sikayet_id = $id");
    header("Location: sikayet_detay.php?id=$id&msg=faaliyet_guncellendi");
    exit;
}

// --- ŞİKAYET KAPATMA ---
if (isset($_POST["sikayet_kapat"])) {
    $sonuc_dogrulama = mysqli_real_escape_string($baglanti, trim($_POST["sonuc_dogrulama"] ?? ""));

    $sql_kapat = "UPDATE sikayetler SET 
                  durum = 'kapandi', 
                  kapanma_tarihi = CURRENT_DATE(), 
                  sonuc_dogrulama = '$sonuc_dogrulama' 
                  WHERE id = $id";

    if ($baglanti->query($sql_kapat)) {
        systemLogKaydet($baglanti, 'UPDATE', 'Şikayetler', "Şikayet KAPATILDI. No: " . $sikayet["sikayet_no"]);
        header("Location: sikayet_detay.php?id=$id&msg=kapatildi");
        exit;
    } else {
        $hata = "Kapatılırken hata: " . $baglanti->error;
    }
}

// Faaliyetleri Çek
$faaliyetler = $baglanti->query("SELECT * FROM sikayet_faaliyetleri WHERE sikayet_id = $id ORDER BY olusturma_tarihi ASC");

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DÖF Detay -
        <?php echo $sikayet['sikayet_no']; ?> - Özbal Un
    </title>
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --ozbal-primary: #0f172a;
            --ozbal-accent: #f59e0b;
            --ozbal-success: #10b981;
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
            font-weight: 600;
        }

        .badge-soft {
            padding: 0.4em 0.7em;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-durum-acik,
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

        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #64748b;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #0f172a;
            margin-bottom: 1rem;
        }

        .timeline-item {
            padding: 1rem;
            border-left: 2px solid #e2e8f0;
            position: relative;
            margin-left: 10px;
        }

        .timeline-item::before {
            content: "";
            width: 12px;
            height: 12px;
            background: #fff;
            border: 2px solid var(--ozbal-accent);
            border-radius: 50%;
            position: absolute;
            left: -7px;
            top: 1.2rem;
        }

        .timeline-item.completed::before {
            border-color: var(--ozbal-success);
            background: var(--ozbal-success);
        }
    </style>
</head>

<body>
    <?php include("navbar.php"); ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="sikayetler.php" class="text-decoration-none text-muted"><i
                        class="fas fa-arrow-left me-2"></i>Şikayetler Listesine Dön</a>
                <h3 class="fw-bold mt-2">DÖF Detay:
                    <?php echo $sikayet['sikayet_no']; ?>
                </h3>
            </div>
            <div>
                <span class="badge badge-soft badge-durum-<?php echo $sikayet['durum']; ?> fs-6 me-2">
                    Durum:
                    <?php echo strtoupper(str_replace('_', ' ', $sikayet['durum'])); ?>
                </span>
                <?php if ($sikayet['oncelik'] == 'kritik'): ?>
                    <span class="badge bg-danger fs-6"><i class="fas fa-exclamation-triangle me-1"></i>KRİTİK</span>
                <?php endif; ?>
            </div>
        </div>



        <div class="row">
            <!-- Sol Sütun: Temel Bilgiler ve Şikayet Detayı -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-info-circle me-2"></i>Temel Bilgiler
                    </div>
                    <div class="card-body">
                        <div class="info-label">Müşteri / Cari</div>
                        <div class="info-value"><?php echo $musteriAdiGoster; ?></div>

                        <div class="info-label">Şikayet Tarihi</div>
                        <div class="info-value">
                            <?php echo date('d.m.Y', strtotime($sikayet['sikayet_tarihi'])); ?>
                        </div>

                        <div class="info-label">Bildirim Kanalı</div>
                        <div class="info-value">
                            <?php echo ucfirst(str_replace('_', ' ', $sikayet['bildirim_kanali'])); ?>
                        </div>

                        <div class="info-label">Şikayet Tipi</div>
                        <div class="info-value">
                            <?php echo ucfirst(str_replace('_', ' ', $sikayet['sikayet_tipi'])); ?>
                        </div>

                        <div class="info-label">İlgili Parti / Üretim No</div>
                        <div class="info-value text-primary font-monospace">
                            <?php echo $sikayet['parti_no'] ?: '-'; ?>
                        </div>

                        <hr>

                        <div class="info-label">Müşteri Şikayeti (Konu)</div>
                        <div class="info-value fw-bold text-danger">
                            <?php echo nl2br(htmlspecialchars($sikayet['sikayet_konusu'] ?? '')); ?>
                        </div>

                        <div class="info-label">Şikayet Detayı / Müşteri Beyanı</div>
                        <div class="p-3 bg-light rounded text-dark fs-6" style="min-height: 100px;">
                            <?php echo nl2br(htmlspecialchars($sikayet['sikayet_detay'] ?? '')); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Sütun: DÖF Formu ve Faaliyetler -->
            <div class="col-lg-8 mb-4">
                <!-- DÖF Analiz ve Planlama (Kök Neden) -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-microscope me-2"></i>Kök Neden ve Aksiyon Planı</div>
                        <?php if ($isKapali): ?>
                            <span class="badge bg-secondary">KAPALI DOSYA</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <fieldset <?php echo $isKapali ? 'disabled' : ''; ?>>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Şikayet Durumu</label>
                                        <select name="durum" class="form-select">
                                            <option value="acik" <?php if ($sikayet['durum'] == 'acik')
                                                echo 'selected'; ?>>Açık / Yeni</option>
                                            <option value="inceleniyor" <?php if ($sikayet['durum'] == 'inceleniyor')
                                                echo 'selected'; ?>>İnceleniyor</option>
                                            <option value="dof_acildi" <?php if ($sikayet['durum'] == 'dof_acildi')
                                                echo 'selected'; ?>>DÖF Açıldı (Aksiyon Aşamasında)</option>
                                            <option value="kapandi" disabled <?php if ($sikayet['durum'] == 'kapandi')
                                                echo 'selected'; ?>>Kapandi (Aşağıdan Kapatın)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DÖF Sorumlusu</label>
                                        <input type="text" name="dof_sorumlu" class="form-control"
                                            value="<?php echo htmlspecialchars($sikayet['dof_sorumlu'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-danger fw-bold"><i
                                                class="fas fa-sitemap me-1"></i> Kök Neden Analizi</label>
                                        <textarea name="kok_neden" class="form-control" rows="3"
                                            placeholder="Problemin asıl kaynağı nedir? (5 Neden Analizi)"><?php echo htmlspecialchars($sikayet['kok_neden'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-warning"><i class="fas fa-wrench me-1"></i>
                                            Düzeltici Faaliyet Tanımı</label>
                                        <textarea name="duzeltici_faaliyet" class="form-control" rows="3"
                                            placeholder="Mevcut sorunu çözmek için ne yapılacak?"><?php echo htmlspecialchars($sikayet['duzeltici_faaliyet'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-success"><i class="fas fa-shield-alt me-1"></i>
                                            Önleyici Faaliyet Tanımı</label>
                                        <textarea name="onleyici_faaliyet" class="form-control" rows="3"
                                            placeholder="Sorunun tekrarlamaması için ne yapılacak?"><?php echo htmlspecialchars($sikayet['onleyici_faaliyet'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hedef Kapanma Tarihi</label>
                                        <input type="date" name="hedef_kapanma_tarihi" class="form-control"
                                            value="<?php echo $sikayet['hedef_kapanma_tarihi']; ?>">
                                    </div>

                                    <?php if (!$isKapali): ?>
                                        <div class="col-12 text-end">
                                            <button type="submit" name="sikayet_guncelle" class="btn btn-warning fw-bold">
                                                <i class="fas fa-save me-2"></i>DÖF Formunu Kaydet
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </div>

                <!-- DÖF Faaliyetleri (Görevler) -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark"><i class="fas fa-tasks text-muted me-2"></i>Gerçekleşen Faaliyetler
                            (Aksiyonlar)</h5>
                        <?php if (!$isKapali): ?>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#faaliyetModal">
                                <i class="fas fa-plus"></i> Faaliyet Ekle
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($faaliyetler && $faaliyetler->num_rows > 0): ?>
                            <div class="mt-2">
                                <?php while ($f = $faaliyetler->fetch_assoc()):
                                    $fkapali = ($f['durum'] == 'tamamlandi');
                                    ?>
                                    <div class="timeline-item <?php echo $fkapali ? 'completed' : ''; ?>">
                                        <div class="d-flex justify-content-between p-3 bg-light rounded border">
                                            <div>
                                                <div class="mb-1">
                                                    <span
                                                        class="badge <?php echo $f['faaliyet_tipi'] == 'duzeltici' ? 'bg-warning text-dark' : ($f['faaliyet_tipi'] == 'onleyici' ? 'bg-success' : 'bg-danger'); ?>">
                                                        <?php echo strtoupper(str_replace('_', ' ', $f['faaliyet_tipi'])); ?>
                                                    </span>
                                                    <strong class="ms-2">
                                                        <?php echo htmlspecialchars($f['sorumlu'] ?: 'Sorumlu atanmadı'); ?>
                                                    </strong>
                                                    <small class="text-muted ms-2">Hedef:
                                                        <?php echo $f['hedef_tarih'] ? date('d.m.Y', strtotime($f['hedef_tarih'])) : '-'; ?>
                                                    </small>
                                                </div>
                                                <div class="fs-6 mt-2">
                                                    <?php echo nl2br(htmlspecialchars($f['aciklama'] ?? '')); ?>
                                                </div>
                                            </div>
                                            <div class="text-end" style="min-width: 150px;">
                                                <?php if ($fkapali): ?>
                                                    <span class="badge bg-success mb-2 d-block"><i
                                                            class="fas fa-check me-1"></i>Tamamlandı</span>
                                                    <small class="text-muted">
                                                        <?php echo date('d.m.Y', strtotime($f['tamamlanma_tarihi'])); ?>
                                                    </small>
                                                <?php elseif (!$isKapali): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="f_id" value="<?php echo $f['id']; ?>">
                                                        <div class="input-group input-group-sm">
                                                            <select name="f_durum" class="form-select form-select-sm">
                                                                <option value="bekliyor" <?php if ($f['durum'] == 'bekliyor')
                                                                    echo 'selected'; ?>>Bekliyor</option>
                                                                <option value="devam_ediyor" <?php if ($f['durum'] == 'devam_ediyor')
                                                                    echo 'selected'; ?>>Devam Ediyor</option>
                                                                <option value="tamamlandi">Bitti / Tamamlandı</option>
                                                            </select>
                                                            <button type="submit" name="faaliyet_guncelle"
                                                                class="btn btn-outline-secondary" title="Güncelle"><i
                                                                    class="fas fa-check"></i></button>
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary mb-2 d-block">Yarım Kaldı</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-tasks fs-1 mb-2 opacity-25"></i>
                                <p>Henüz planlanmış bir faaliyet bulunmuyor.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- DÖF KAPATMA KARTI -->
                    <div class="card-footer bg-light p-4 border-top">
                        <?php if ($isKapali): ?>
                            <div class="alert alert-success mb-0 border-success">
                                <h5 class="fw-bold"><i class="fas fa-lock me-2"></i>DÖF KAPATILDI</h5>
                                <p class="mb-1">Tarih: <strong>
                                        <?php echo date('d.m.Y', strtotime($sikayet['kapanma_tarihi'])); ?>
                                    </strong></p>
                                <strong>Sonuç ve Etkinlik Doğrulaması:</strong>
                                <div class="mt-2 text-dark">
                                    <?php echo nl2br(htmlspecialchars($sikayet['sonuc_dogrulama'] ?? '')); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <h5 class="text-dark fw-bold"><i class="fas fa-flag-checkered me-2"></i>DÖF Kapatma / Etkinlik
                                Doğrulama</h5>
                            <p class="text-muted small mb-3">Tüm aksiyonlar tamamlandıysa veya sorunun çözüldüğü teyit
                                edildiyse, etkinlik doğrulamasını yazarak dosyayı kapatabilirsiniz. Kapalı şikayetler tekrar
                                düzenlenemez.</p>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label required-field">Etkinlik Doğrulaması (Yapılanlar sorunu çözdü
                                        mü? Müşteri memnun mu?)</label>
                                    <textarea name="sonuc_dogrulama" class="form-control" rows="3" required
                                        placeholder="Alınan aksiyonların etkinliği yerinde kontrol edildi ve..."></textarea>
                                </div>
                                <button type="submit" name="sikayet_kapat" class="btn btn-success fw-bold w-100"
                                    onclick="confirmKapat(event)">
                                    <i class="fas fa-lock me-2"></i>Dosyayı Kapat
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div> <!-- /container -->

    <!-- Faaliyet Ekleme Modal -->
    <div class="modal fade" id="faaliyetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Yeni Faaliyet Planla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Faaliyet Tipi</label>
                            <select name="faaliyet_tipi" class="form-select">
                                <option value="duzeltici">Düzeltici (Sorunu çöz)</option>
                                <option value="onleyici">Önleyici (Tekrarını engelle)</option>
                                <option value="acil_onlem">Acil Aksiyon (Geçici çözüm)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required-field">Aksiyon / İş Emri</label>
                            <textarea name="aciklama" class="form-control" rows="3" required
                                placeholder="Ne yapılacak?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sorumlu Personel / Birim</label>
                            <input type="text" name="sorumlu" class="form-control"
                                placeholder="Örn: Üretim Vardiya Amiri">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hedef Tamamlama Tarihi</label>
                            <input type="date" name="hedef_tarih" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="faaliyet_ekle" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        function confirmKapat(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Şikayet (DÖF) dosyası KALICI OLARAK KAPATILACAK!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Kapat!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Butonun bulunduğu formu submit et
                    e.target.closest('form').submit();
                }
            });
        }

        $(document).ready(function () {
            <?php if (function_exists('yazmaYetkisiKontrolJS')) {
                yazmaYetkisiKontrolJS($baglanti, true);
            } ?>

            // Alert Messages with SweetAlert2
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
