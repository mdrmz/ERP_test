<?php
session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

$mesaj = "";
$hata = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'silindi') {
        $mesaj = "Bildirim başarıyla silindi.";
    } elseif ($_GET['msg'] == 'okundu') {
        $mesaj = "Bildirim okundu olarak işaretlendi.";
    }
}

// Arama ve Filtre Parametreleri
$arama_metni = isset($_GET['arama']) ? trim($_GET['arama']) : '';
$bildirim_tipi = isset($_GET['tip']) ? trim($_GET['tip']) : '';
$okundu_filtre = isset($_GET['durum']) ? trim($_GET['durum']) : '';
$yon = isset($_GET['yon']) && $_GET['yon'] == 'gonderilen' ? 'gonderilen' : 'gelen';
$baslangic_tarihi = isset($_GET['baslangic']) ? trim($_GET['baslangic']) : '';
$bitis_tarihi = isset($_GET['bitis']) ? trim($_GET['bitis']) : '';

// Tümünü okundu yap
if (isset($_POST['tum_okundu'])) {
    tumBildirimleriOkundu($baglanti, $yon);
    $mesaj = "Tüm bildirimler okundu olarak işaretlendi.";
}

// Tümünü temizle
if (isset($_POST['tum_temizle'])) {
    tumBildirimleriTemizle($baglanti, $arama_metni, $bildirim_tipi, $okundu_filtre, $yon, $baslangic_tarihi, $bitis_tarihi);
    $mesaj = "Bildirimler temizlendi.";
}

// Tek bildirim okundu
if (isset($_GET['okundu'])) {
    $id = (int) $_GET['okundu'];
    bildirimOkundu($baglanti, $id);

    $qs = $_GET;
    unset($qs['okundu']);
    unset($qs['sil']);
    $qs['msg'] = 'okundu';
    $q_str = empty($qs) ? "" : "?" . http_build_query($qs);
    header("Location: bildirimler.php" . $q_str);
    exit;
}

// Tek bildirim sil
if (isset($_GET['sil'])) {
    $id = (int) $_GET['sil'];
    bildirimSil($baglanti, $id);

    $qs = $_GET;
    unset($qs['okundu']);
    unset($qs['sil']);
    $qs['msg'] = 'silindi';
    $q_str = empty($qs) ? "" : "?" . http_build_query($qs);
    header("Location: bildirimler.php" . $q_str);
    exit;
}

// Bildirim gönder
if (isset($_POST['bildirim_gonder'])) {
    $baslik = trim($_POST['baslik']);
    $aciklama = trim($_POST['aciklama']);
    $hedef_tip = $_POST['hedef_tip'];
    $hedef_rol_id = null;
    $hedef_user_id = null;

    if ($hedef_tip === 'rol') {
        $hedef_rol_id = (int) $_POST['hedef_rol'];
    } elseif ($hedef_tip === 'kullanici') {
        $hedef_user_id = (int) $_POST['hedef_kullanici'];
    }

    if (empty($baslik)) {
        $hata = "Bildirim başlığı zorunludur!";
    } else {
        $sonuc = bildirimOlustur(
            $baglanti,
            'genel',
            $baslik,
            $aciklama,
            $hedef_rol_id,
            $hedef_user_id,
            null,
            null,
            null
        );

        if ($sonuc) {
            $mesaj = "✅ Bildirim başarıyla gönderildi!";
        } else {
            $hata = "Bildirim gönderilirken bir hata oluştu.";
        }
    }
}

// Bildirimleri getir
$bildirimler = bildirimleriGetir($baglanti, 50, false, $arama_metni, $bildirim_tipi, $okundu_filtre, $yon, $baslangic_tarihi, $bitis_tarihi);

// Roller listesi
$roller = $baglanti->query("SELECT id, rol_adi FROM kullanici_rolleri ORDER BY rol_adi");

// Kullanıcılar listesi
$kullanicilar = $baglanti->query("SELECT id, kadi, tam_ad FROM users WHERE aktif = 1 ORDER BY tam_ad");

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildirimler - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .bildirim-kart {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            margin-bottom: 10px;
        }

        .bildirim-kart:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #f5a623;
        }

        .bildirim-kart.okunmamis {
            border-left: 4px solid #f5a623;
            background: #fffbeb;
        }

        .bildirim-ikon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .ikon-arac {
            background: #dbeafe;
            color: #2563eb;
        }

        .ikon-lab {
            background: #dcfce7;
            color: #16a34a;
        }

        .ikon-onay {
            background: #fef3c7;
            color: #d97706;
        }

        .ikon-red {
            background: #fee2e2;
            color: #dc2626;
        }

        .ikon-duzeltme {
            background: #ede9fe;
            color: #7c3aed;
        }

        .ikon-duzeltme-onay {
            background: #dcfce7;
            color: #166534;
        }

        .ikon-duzeltme-red {
            background: #fee2e2;
            color: #b91c1c;
        }

        .ikon-genel {
            background: #f1f5f9;
            color: #64748b;
        }

        .bildirim-zaman {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .badge-yeni {
            background: #f5a623;
            color: #000;
            font-size: 0.65rem;
            padding: 4px 8px;
            border-radius: 20px;
        }

        .gonder-kart {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 16px;
            color: #fff;
        }

        .gonder-kart .form-control,
        .gonder-kart .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .gonder-kart .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .gonder-kart .form-control:focus,
        .gonder-kart .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #f5a623;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.2);
        }

        .gonder-kart .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }

        .gonder-kart .form-select option {
            background: #1e293b;
            color: #fff;
        }

        /* RESPONSIVE AYARLAR */
        @media (max-width: 768px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            /* Başlık ve Butonlar */
            .bildirim-header-wrapper {
                flex-direction: column;
                align-items: stretch !important;
                gap: 15px;
            }

            .bildirim-actions {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .bildirim-actions .btn,
            .bildirim-actions form {
                width: 100%;
                display: block;
            }

            /* Bildirim Kartı */
            .bildirim-kart {
                padding: 12px !important;
            }

            /* İkon - İçerik - Link hizalamasını koru ama sıkıştırma */
            .bildirim-kart>.d-flex {
                align-items: flex-start !important;
                gap: 12px !important;
            }

            /* İkon Boyutu */
            .bildirim-ikon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                flex-shrink: 0;
            }

            /* İçerik Alanı (Başlık, Açıklama, Tarih) */
            .bildirim-kart .flex-grow-1>.d-flex {
                flex-direction: column;
                /* Yan yana yerine alt alta */
                align-items: flex-start !important;
            }

            /* Sağ taraftaki (Tarih ve Okundu butonu) alanı sola al */
            .bildirim-kart .text-end {
                text-align: left !important;
                margin-top: 8px;
                width: 100%;
                display: flex;
                justify-content: space-between;
                /* Tarih solda, buton sağda olsun */
                align-items: center;
            }

            .bildirim-zaman {
                font-size: 0.75rem;
                order: 1;
                /* Tarih solda */
            }

            .bildirim-kart .text-end .btn {
                margin-top: 0 !important;
                order: 2;
                /* Buton sağda */
            }

            /* Açıklama metni */
            .bildirim-icerik p {
                font-size: 0.85rem;
                line-height: 1.4;
                margin-top: 2px;
            }

            /* Kartın en sağındaki ok butonu */
            .bildirim-kart>.d-flex>a.btn-outline-primary {
                display: none;
                /* Mobilde ok butonunu gizleyelim, karta tıklanıyor zaten */
            }
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 bildirim-header-wrapper">
            <div>
                <h2 class="fw-bold"><i class="fas fa-bell text-warning"></i> Bildirimler</h2>
                <p class="text-muted mb-0">
                    <?php echo $yon === 'gonderilen' ? 'Gönderdiğiniz tüm bildirimler' : 'Tüm gelen bildirimleriniz'; ?>
                </p>
            </div>
            <div class="d-flex gap-2 bildirim-actions">
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal"
                    data-bs-target="#bildirimGonderModal">
                    <i class="fas fa-paper-plane me-1"></i> Bildirim Gönder
                </button>
                <?php if ($yon !== 'gonderilen'): ?>
                    <form method="post" class="d-inline" action="?yon=<?php echo htmlspecialchars($yon); ?>">
                        <button type="submit" name="tum_okundu" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-check-double me-1"></i> Tümünü Okundu
                        </button>
                    </form>
                <?php endif; ?>
                <form method="post" class="d-inline" id="formTumTemizle"
                    action="?yon=<?php echo htmlspecialchars($yon); ?>">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="tumunuTemizleOnay()">
                        <i class="fas fa-trash-alt me-1"></i> Tümünü Temizle
                    </button>
                    <input type="hidden" name="tum_temizle" value="1">
                </form>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $yon == 'gelen' ? 'active fw-bold text-dark' : 'text-secondary'; ?>"
                    aria-current="page" href="?yon=gelen">Gelen Bildirimler</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $yon == 'gonderilen' ? 'active fw-bold text-dark' : 'text-secondary'; ?>"
                    href="?yon=gonderilen">Gönderilen Bildirimler</a>
            </li>
        </ul>

        <!-- Filtreleme Kartı -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="yon" value="<?php echo htmlspecialchars($yon); ?>">
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label small text-muted">Kelime Arama</label>
                        <input type="text" name="arama" class="form-control" placeholder="Kullanıcı, başlık, içerik..."
                            value="<?php echo htmlspecialchars($arama_metni); ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">Bildirim Türü</label>
                        <select name="tip" class="form-select">
                            <option value="">Tümü</option>
                            <option value="arac_geldi" <?php echo $bildirim_tipi == 'arac_geldi' ? 'selected' : ''; ?>>
                                Araç Geldi</option>
                            <option value="numune_alindi" <?php echo $bildirim_tipi == 'numune_alindi' ? 'selected' : ''; ?>>Numune Alındı</option>
                            <option value="analiz_tamamlandi" <?php echo $bildirim_tipi == 'analiz_tamamlandi' ? 'selected' : ''; ?>>Analiz Tamamlandı</option>
                            <option value="onay_bekleniyor" <?php echo $bildirim_tipi == 'onay_bekleniyor' ? 'selected' : ''; ?>>Onay Bekleniyor</option>
                            <option value="onaylandi" <?php echo $bildirim_tipi == 'onaylandi' ? 'selected' : ''; ?>>
                                Onaylandı</option>
                            <option value="reddedildi" <?php echo $bildirim_tipi == 'reddedildi' ? 'selected' : ''; ?>>
                                Reddedildi</option>
                            <option value="silo_duzeltme_talebi" <?php echo $bildirim_tipi == 'silo_duzeltme_talebi' ? 'selected' : ''; ?>>
                                Silo Düzeltme Talebi</option>
                            <option value="silo_duzeltme_onay" <?php echo $bildirim_tipi == 'silo_duzeltme_onay' ? 'selected' : ''; ?>>
                                Silo Düzeltme Onay</option>
                            <option value="silo_duzeltme_red" <?php echo $bildirim_tipi == 'silo_duzeltme_red' ? 'selected' : ''; ?>>
                                Silo Düzeltme Red</option>
                            <option value="genel" <?php echo $bildirim_tipi == 'genel' ? 'selected' : ''; ?>>Genel
                                Bildirimler</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">Durum</label>
                        <select name="durum" class="form-select">
                            <option value="">Hepsi</option>
                            <option value="okunmamis" <?php echo $okundu_filtre == 'okunmamis' ? 'selected' : ''; ?>>
                                Okunmayanlar</option>
                            <option value="okunmus" <?php echo $okundu_filtre == 'okunmus' ? 'selected' : ''; ?>>Okunanlar
                            </option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small text-muted">Başlangıç</label>
                        <input type="date" name="baslangic" class="form-control"
                            value="<?php echo htmlspecialchars($baslangic_tarihi); ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small text-muted">Bitiş</label>
                        <input type="date" name="bitis" class="form-control"
                            value="<?php echo htmlspecialchars($bitis_tarihi); ?>">
                    </div>
                    <div class="col-lg-1 col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrele">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($arama_metni || $bildirim_tipi || $okundu_filtre || $baslangic_tarihi || $bitis_tarihi): ?>
                            <a href="bildirimler.php" class="btn btn-secondary" title="Sıfırla"><i
                                    class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if ($bildirimler && $bildirimler->num_rows > 0): ?>
                    <?php
                    $qs = $_GET;
                    unset($qs['okundu']);
                    unset($qs['sil']);
                    $q_str = !empty($qs) ? '&' . http_build_query($qs) : '';
                    while ($row = $bildirimler->fetch_assoc()):
                        $okunmamis = (!$row['okundu_durum'] && $yon !== 'gonderilen');

                        // İkon sınıfı
                        switch ($row['bildirim_tipi']) {
                            case 'arac_geldi':
                                $ikon_cls = 'ikon-arac';
                                $ikon = 'fa-truck';
                                break;
                            case 'numune_alindi':
                                $ikon_cls = 'ikon-lab';
                                $ikon = 'fa-vial';
                                break;
                            case 'analiz_tamamlandi':
                                $ikon_cls = 'ikon-lab';
                                $ikon = 'fa-flask';
                                break;
                            case 'onay_bekleniyor':
                                $ikon_cls = 'ikon-onay';
                                $ikon = 'fa-clock';
                                break;
                            case 'onaylandi':
                                $ikon_cls = 'ikon-lab';
                                $ikon = 'fa-check-circle';
                                break;
                            case 'reddedildi':
                                $ikon_cls = 'ikon-red';
                                $ikon = 'fa-times-circle';
                                break;
                            case 'silo_duzeltme_talebi':
                                $ikon_cls = 'ikon-duzeltme';
                                $ikon = 'fa-flag';
                                break;
                            case 'silo_duzeltme_onay':
                                $ikon_cls = 'ikon-duzeltme-onay';
                                $ikon = 'fa-rotate-left';
                                break;
                            case 'silo_duzeltme_red':
                                $ikon_cls = 'ikon-duzeltme-red';
                                $ikon = 'fa-xmark';
                                break;
                            default:
                                $ikon_cls = 'ikon-genel';
                                $ikon = 'fa-bell';
                                break;
                        }
                        ?>
                        <div class="bildirim-kart p-3 <?php echo $okunmamis ? 'okunmamis' : ''; ?>"
                            style="cursor: pointer; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.transform='translateY(0)'" onclick="detayGoster(this)"
                            data-id="<?php echo $row['id']; ?>" data-baslik="<?php echo htmlspecialchars($row['baslik']); ?>"
                            data-aciklama="<?php echo htmlspecialchars($row['aciklama']); ?>"
                            data-tarih="<?php echo tarihFormat($row['olusturma_tarihi']); ?>"
                            data-link="<?php echo htmlspecialchars($row['link'] ?? ''); ?>" data-icon="<?php echo $ikon; ?>"
                            data-icon-cls="<?php echo $ikon_cls; ?>"
                            data-gonderen="<?php echo htmlspecialchars($row['olusturan_kadi'] ?? ''); ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bildirim-ikon <?php echo $ikon_cls; ?>">
                                    <i class="fas <?php echo $ikon; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-semibold">
                                                <?php echo htmlspecialchars($row['baslik']); ?>
                                                <?php if ($okunmamis): ?>
                                                    <span class="badge badge-yeni ms-2">YENİ</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-0 text-muted small text-truncate" style="max-width: 600px;">
                                                <?php echo htmlspecialchars($row['aciklama']); ?>
                                            </p>
                                            <?php if (!empty($row['olusturan_kadi'])): ?>
                                                <small class="text-secondary d-block mt-1">
                                                    <i class="fas fa-user-circle me-1"></i>
                                                    Gönderen: <?php echo htmlspecialchars($row['olusturan_kadi']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <div class="bildirim-zaman">
                                                <?php echo tarihFormat($row['olusturma_tarihi']); ?>
                                            </div>
                                            <?php if ($okunmamis && $yon !== 'gonderilen'): ?>
                                                <a href="?yon=<?php echo htmlspecialchars($yon); ?>&okundu=<?php echo $row['id']; ?><?php echo $q_str ? '&' . ltrim($q_str, '?') : ''; ?>"
                                                    class="btn btn-sm text-success p-0 mt-1" onclick="event.stopPropagation()"
                                                    title="Okundu işaretle">
                                                    <i class="fas fa-check-double fa-lg"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?yon=<?php echo htmlspecialchars($yon); ?>&sil=<?php echo $row['id']; ?><?php echo $q_str ? '&' . ltrim($q_str, '?') : ''; ?>"
                                                class="btn btn-sm text-danger p-0 mt-1 ms-3 shadow-none"
                                                onclick="return confirmDelete(event, this.href);" title="Sil">
                                                <i class="fas fa-trash fa-lg"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($row['link']): ?>
                                    <a href="<?php echo htmlspecialchars($row['link']); ?>" class="btn btn-sm btn-outline-primary"
                                        onclick="event.stopPropagation()">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-bell-slash fa-3x mb-3 opacity-50"></i>
                        <h5>Henüz bildirim yok</h5>
                        <p class="mb-0">Yeni işlemler olduğunda burada görünecek.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bildirim Gönder Modal -->
    <div class="modal fade" id="bildirimGonderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0">
                <div class="modal-header gonder-kart border-0">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Bildirim Gönder</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bildirim Başlığı *</label>
                            <input type="text" name="baslik" class="form-control"
                                placeholder="Örn: Acil - Lab Analizi Gerekiyor" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Açıklama</label>
                            <textarea name="aciklama" class="form-control" rows="3"
                                placeholder="Bildirim detaylarını yazın..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Gönderilecek Hedef</label>
                            <select name="hedef_tip" class="form-select" id="hedefTip" onchange="hedefDegistir()">
                                <option value="herkes">Herkese</option>
                                <option value="rol">Belirli Bir Role</option>
                                <option value="kullanici">Belirli Bir Kullanıcıya</option>
                            </select>
                        </div>

                        <div class="mb-3" id="rolSecim" style="display:none;">
                            <label class="form-label fw-semibold">Rol Seçin</label>
                            <select name="hedef_rol" class="form-select">
                                <option value="">Seçiniz...</option>
                                <?php if ($roller):
                                    $roller->data_seek(0);
                                    while ($r = $roller->fetch_assoc()): ?>
                                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['rol_adi']); ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="kullaniciSecim" style="display:none;">
                            <label class="form-label fw-semibold">Kullanıcı Seçin</label>
                            <select name="hedef_kullanici" class="form-select">
                                <option value="">Seçiniz...</option>
                                <?php if ($kullanicilar):
                                    $kullanicilar->data_seek(0);
                                    while ($k = $kullanicilar->fetch_assoc()): ?>
                                        <option value="<?php echo $k['id']; ?>">
                                            <?php echo htmlspecialchars($k['tam_ad'] ?: $k['kadi']); ?>
                                        </option>

                                    <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="bildirim_gonder" class="btn btn-warning">
                            <i class="fas fa-paper-plane me-1"></i> Gönder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bildirim Detay Modal -->
    <div class="modal fade" id="bildirimDetayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title d-flex align-items-center">
                        <span id="detayIconBox"
                            class="me-2 rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 32px; height: 32px;">
                            <i id="detayIcon" class=""></i>
                        </span>
                        Bildirim Detayı
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <h5 id="detayBaslik" class="fw-bold mb-3"></h5>
                    <p id="detayAciklama" class="text-muted mb-4 fs-6" style="white-space: pre-wrap;"></p>
                    <div class="d-flex justify-content-between align-items-center text-muted small">
                        <span id="detayTarih"><i class="far fa-calendar me-1"></i></span>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <a href="#" id="detayLink" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-external-link-alt me-1"></i> İlgili Sayfaya Git
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
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
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ ", "⚠️ "], "", $mesaj)); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: ", "⚠️ "], "", $hata)); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>
        });

        const detayModal = new bootstrap.Modal(document.getElementById('bildirimDetayModal'));

        function detayGoster(element) {
            const baslik = element.getAttribute('data-baslik');
            const aciklama = element.getAttribute('data-aciklama');
            const tarih = element.getAttribute('data-tarih');
            const link = element.getAttribute('data-link');
            const icon = element.getAttribute('data-icon');
            const iconCls = element.getAttribute('data-icon-cls');
            const gonderen = element.getAttribute('data-gonderen');

            document.getElementById('detayBaslik').textContent = baslik;
            document.getElementById('detayAciklama').textContent = aciklama;

            let tarihHtml = '<i class="far fa-clock me-1"></i> ' + tarih;
            if (gonderen) {
                tarihHtml += '<span class="ms-3"><i class="fas fa-user-circle me-1"></i> Gönderen: ' + gonderen + '</span>';
            }
            document.getElementById('detayTarih').innerHTML = tarihHtml;

            const linkBtn = document.getElementById('detayLink');
            if (link && link !== '') {
                linkBtn.href = link;
                linkBtn.style.display = 'inline-block';
            } else {
                linkBtn.style.display = 'none';
            }

            // İkon ve renkler
            const iconBox = document.getElementById('detayIconBox');
            const iconElem = document.getElementById('detayIcon');

            iconElem.className = 'fas ' + icon;

            let bgColor = '#f8f9fa';
            let color = '#6c757d';

            if (iconCls.includes('ikon-arac')) { bgColor = '#fff3cd'; color = '#ffc107'; }
            else if (iconCls.includes('ikon-lab')) { bgColor = '#cfe2ff'; color = '#0d6efd'; }
            else if (iconCls.includes('ikon-onay')) { bgColor = '#fff3cd'; color = '#ffc107'; }
            else if (iconCls.includes('ikon-red')) { bgColor = '#f8d7da'; color = '#dc3545'; }
            else if (iconCls.includes('ikon-duzeltme-onay')) { bgColor = '#dcfce7'; color = '#166534'; }
            else if (iconCls.includes('ikon-duzeltme-red')) { bgColor = '#fee2e2'; color = '#b91c1c'; }
            else if (iconCls.includes('ikon-duzeltme')) { bgColor = '#ede9fe'; color = '#7c3aed'; }
            else { bgColor = '#e2e3e5'; color = '#383d41'; }

            iconBox.style.backgroundColor = bgColor;
            iconBox.style.color = color;

            // OTOMATİK OKUNDU İŞARETLEME
            const id = element.getAttribute('data-id');

            if (element.classList.contains('okunmamis') && '<?php echo $yon; ?>' !== 'gonderilen') {
                // UI Güncellemeleri - Anında tepki
                element.classList.remove('okunmamis');
                element.style.backgroundColor = '#fff';
                element.style.borderLeftColor = '#e2e8f0';

                // "YENİ" badge'ini bul ve kaldır
                const badge = element.querySelector('.badge-yeni');
                if (badge) badge.remove();

                // "Okundu işaretle" butonunu kaldır
                const okunduBtn = element.querySelector('a[title="Okundu işaretle"]');
                if (okunduBtn) okunduBtn.remove();

                // Backend isteği
                fetch('ajax/bildirimler_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=read&id=' + id
                }).then(() => {
                    // Navbar'daki sayıyı güncelle (varsa)
                    if (typeof bildirimKontrol === 'function') {
                        bildirimKontrol();
                    }
                }).catch(console.error);
            }

            detayModal.show();
        }

        function hedefDegistir() {
            const tip = document.getElementById('hedefTip').value;
            document.getElementById('rolSecim').style.display = tip === 'rol' ? 'block' : 'none';
            document.getElementById('kullaniciSecim').style.display = tip === 'kullanici' ? 'block' : 'none';
        }

        // Tümünü temizle onay
        function tumunuTemizleOnay() {
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Tüm bildirimler kalıcı olarak temizlenecektir. (Sadece sizin listelediğiniz)",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Temizle!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formTumTemizle').submit();
                }
            });
        }

        // Tekli sil onay
        function confirmDelete(e, href) {
            e.preventDefault();
            e.stopPropagation();
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu bildirim silinecek!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
            return false;
        }
    </script>
</body>

</html>
