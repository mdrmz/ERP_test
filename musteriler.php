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

// YENİ MÜŞTERİ / TEDARİKÇİ EKLE
if (isset($_POST["musteri_ekle"])) {
    $kod = $baglanti->real_escape_string($_POST["cari_kod"]);
    $tip = (strpos($kod, '120') === 0) ? 'Müşteri' : ((strpos($kod, '320') === 0) ? 'Tedarikçi' : 'Müşteri');
    $ad = $baglanti->real_escape_string($_POST["firma_adi"]);
    $yetkili = $baglanti->real_escape_string($_POST["yetkili_kisi"]);
    $tel = $baglanti->real_escape_string($_POST["telefon"]);
    $eposta = $baglanti->real_escape_string($_POST["eposta"]);
    $vd = $baglanti->real_escape_string($_POST["vergi_dairesi"]);
    $vn = $baglanti->real_escape_string($_POST["vergi_no"]);
    $il = $baglanti->real_escape_string($_POST["il"]);
    $ilce = $baglanti->real_escape_string($_POST["ilce"]);
    $adres = $baglanti->real_escape_string($_POST["adres"]);
    $notlar = $baglanti->real_escape_string($_POST["ozel_notlar"]);

    // Aynı kodda var mı kontrolü
    $kontrol = $baglanti->query("SELECT id FROM musteriler WHERE cari_kod = '$kod'");
    if ($kontrol && $kontrol->num_rows > 0) {
        $hata = "⚠️ Bu cari kod ($kod) zaten sistemde kayıtlı!";
    } else {
        $sql = "INSERT INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres, ozel_notlar) 
                VALUES ('$kod', '$tip', '$ad', '$yetkili', '$tel', '$eposta', '$vd', '$vn', '$il', '$ilce', '$adres', '$notlar')";
        if ($baglanti->query($sql)) {
            $mesaj = "✅ Kayıt Başarılı: $ad ($kod)";
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// FİLTRELEME
$where = " WHERE 1=1 ";
if(isset($_GET['tip']) && $_GET['tip'] == 'tedarikci') {
    $where .= " AND cari_tip = 'Tedarikçi' ";
} elseif(isset($_GET['tip']) && $_GET['tip'] == 'musteri') {
    $where .= " AND cari_tip = 'Müşteri' ";
}

// LİSTELERİ ÇEK
$musteriler = $baglanti->query("SELECT * FROM musteriler $where ORDER BY firma_adi ASC");

// Müşteri detayı seçildiyse:
$secili_musteri_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$secili_musteri = null;
$gecmis_siparisler = null;

if ($secili_musteri_id > 0) {
    // Müşteri bilgisi
    $secili_res = $baglanti->query("SELECT * FROM musteriler WHERE id = $secili_musteri_id");
    if ($secili_res && $secili_res->num_rows > 0) {
        $secili_musteri = $secili_res->fetch_assoc();

        // Sipariş ve sevkiyat (izlenebilirlik) verisi
        // siparisler, siparis_detaylari, sevkiyatlar birleşimi.
        $sql_siparisler = "
        SELECT 
            s.id as siparis_id,
            s.siparis_kodu,
            s.siparis_tarihi,
            s.durum as siparis_durumu,
            sd.urun_adi,
            sd.miktar as istenen_miktar,
            sd.birim,
            sevk.sevk_tarihi,
            sevk.sevk_miktari,
            sevk.parti_no as izlenebilirlik_kodu,
            sevk.arac_plaka
        FROM siparisler s
        LEFT JOIN siparis_detaylari sd ON s.id = sd.siparis_id
        LEFT JOIN sevkiyatlar sevk ON s.id = sevk.siparis_id -- NOT: Tam doğru eşleşme için randevu veya detaylarla bağlanması gerekebilir ama sevkiyatlarda müşteri adı/sipariş id var
        WHERE s.musteri_id = $secili_musteri_id
        ORDER BY s.siparis_tarihi DESC
        ";

        // Düzeltme: Mevcut veritabanı şemasında sevkiyatlar tablosu tam detaylı olmayabilir.
        // En güncel ve doğru veri çekimi:
        $sql_gecmis = "
            SELECT 
                s.id as sid,
                s.siparis_kodu, 
                s.siparis_tarihi, 
                s.durum, 
                s.aciklama,
                s.alici_adi,
                s.odeme_tarihi,
                sd.urun_adi, 
                sd.miktar, 
                sd.birim,
                (SELECT GROUP_CONCAT(parti_no SEPARATOR ', ') FROM sevkiyatlar WHERE siparis_id = s.id) as parti_nolar
            FROM siparisler s
            JOIN siparis_detaylari sd ON s.id = sd.siparis_id
            WHERE s.musteri_id = $secili_musteri_id
            ORDER BY s.siparis_tarihi DESC
        ";
        $gecmis_siparisler = $baglanti->query($sql_gecmis);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Müşteri Yönetimi - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .customer-card {
            cursor: pointer;
            transition: 0.2s;
        }

        .customer-card:hover,
        .customer-card.active {
            border-color: #f5a623;
            background-color: #fffaf0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .list-group-scroll {
            max-height: 70vh;
            overflow-y: auto;
        }

        .track-code {
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: bold;
            color: #d35400;
            background: #fdf2e9;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container-fluid py-4 px-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users text-primary"></i> Müşteri Yönetimi</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yeniMusteriModal">
                <i class="fas fa-user-plus me-1"></i> Yeni Müşteri
            </button>
        </div>



        <div class="row">
            <!-- Sol Panel: Müşteri Listesi -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-dark btn-sm dropdown-toggle fw-bold p-0" type="button" data-bs-toggle="dropdown">
                                <?php 
                                    if(isset($_GET['tip']) && $_GET['tip'] == 'tedarikci') echo "Tedarikçiler (320)";
                                    elseif(isset($_GET['tip']) && $_GET['tip'] == 'musteri') echo "Müşteriler (120)";
                                    else echo "Tüm Kayıtlar";
                                ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark shadow">
                                <li><a class="dropdown-menu-item dropdown-item small" href="musteriler.php">Hepsi</a></li>
                                <li><a class="dropdown-menu-item dropdown-item small" href="musteriler.php?tip=musteri">Sadece Müşteriler (120)</a></li>
                                <li><a class="dropdown-menu-item dropdown-item small" href="musteriler.php?tip=tedarikci">Sadece Tedarikçiler (320)</a></li>
                            </ul>
                        </div>
                        <span class="badge bg-light text-dark">
                            <?php echo $musteriler ? $musteriler->num_rows : 0; ?> Kayit
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-2 bg-light border-bottom">
                            <input type="text" id="musteriAra" class="form-control form-control-sm"
                                placeholder="Müşteri, yetkili veya telefon ara...">
                        </div>
                        <div class="list-group list-group-flush list-group-scroll" id="musteriListesi">
                            <?php
                            if ($musteriler && $musteriler->num_rows > 0) {
                                while ($m = $musteriler->fetch_assoc()) {
                                    $active = ($secili_musteri_id == $m['id']) ? 'active' : '';
                                    echo "
                                    <a href='musteriler.php?id={$m['id']}" . (isset($_GET['tip']) ? '&tip='.$_GET['tip'] : '') . "' class='list-group-item list-group-item-action customer-card {$active}'>
                                        <div class='d-flex w-100 justify-content-between'>
                                            <h6 class='mb-1 text-primary fw-bold'>{$m['firma_adi']}</h6>
                                            <span class='badge " . ($m['cari_tip'] == 'Müşteri' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning') . "' style='font-size:0.6rem;'>{$m['cari_tip']}</span>
                                        </div>
                                        <div class='d-flex justify-content-between align-items-center mb-1'>
                                            <small class='text-muted' style='font-size:0.75rem;'><i class='fas fa-id-card me-1'></i> {$m['cari_kod']}</small>
                                            <small class='text-muted' style='font-size:0.75rem;'><i class='fas fa-map-marker-alt me-1'></i> " . ($m['il'] ? $m['il'] : 'LOKASYON YOK') . "</small>
                                        </div>
                                        <div class='d-flex justify-content-between align-items-center'>
                                            <small class='text-dark small'><i class='fas fa-user text-muted me-1'></i> " . ($m['yetkili_kisi'] ?: '-') . "</small>
                                            <small class='text-muted small'><i class='fas fa-phone me-1'></i> " . ($m['telefon'] ?: '-') . "</small>
                                        </div>
                                    </a>";
                                }
                            } else {
                                echo "<div class='p-4 text-center text-muted'>Kayıtlı müşteri yok.</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Panel: Müşteri Detayı ve Geçmiş -->
            <div class="col-lg-8">
                <?php if ($secili_musteri) { ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 text-dark">
                                    <?php echo $secili_musteri['firma_adi']; ?>
                                </h4>
                                <div class="text-muted small">
                                    <span class="me-3"><i class="fas fa-id-card me-1 text-muted"></i>
                                        <strong><?php echo $secili_musteri['cari_kod']; ?></strong>
                                    </span>
                                    <span class="me-3"><i class="fas fa-user me-1 text-muted"></i>
                                        <?php echo $secili_musteri['yetkili_kisi'] ?: '-'; ?>
                                    </span>
                                    <span class="me-3"><i class="fas fa-phone me-1 text-muted"></i>
                                        <?php echo $secili_musteri['telefon'] ?: '-'; ?>
                                    </span>
                                    <span><i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                        <?php echo $secili_musteri['ilce'] . ' / ' . $secili_musteri['il']; ?>
                                    </span>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <span class="me-3"><i class="fas fa-file-invoice me-1 text-muted"></i>
                                        VD: <?php echo $secili_musteri['vergi_dairesi'] ?: '-'; ?> / No: <?php echo $secili_musteri['vergi_no'] ?: '-'; ?>
                                    </span>
                                    <span class="me-3"><i class="fas fa-envelope me-1 text-muted"></i>
                                        <?php echo $secili_musteri['eposta'] ?: '-'; ?>
                                    </span>
                                </div>
                                <?php if($secili_musteri['ozel_notlar']): ?>
                                <div class="mt-2 p-2 bg-warning-subtle rounded border border-warning-subtle small">
                                    <i class="fas fa-sticky-note text-warning me-2"></i><strong>Özel Not:</strong> <?php echo nl2br(htmlspecialchars($secili_musteri['ozel_notlar'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="pazarlama.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-shopping-cart me-1"></i> Sipariş Gir
                            </a>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-primary"><i class="fas fa-history me-2"></i>Satın Alma Geçmişi &
                                İzlenebilirlik Raporları</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($gecmis_siparisler && $gecmis_siparisler->num_rows > 0) { ?>
                            <div class="table-responsive p-3">
                                <table class="table table-hover align-middle" id="raporTablosu" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Tarih</th>
                                            <th>Müşteri/Alıcı Detayı</th>
                                            <th>Ürün Bilgisi</th>
                                            <th>Durum</th>
                                            <th>İzlenebilirlik</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($s = $gecmis_siparisler->fetch_assoc()): 
                                            $renk = 'secondary';
                                            if ($s['durum'] == 'Bekliyor') $renk = 'warning text-dark';
                                            if ($s['durum'] == 'KismiSevk') $renk = 'primary';
                                            if ($s['durum'] == 'TeslimEdildi') $renk = 'success';
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $s['siparis_kodu']; ?></strong></td>
                                            <td><small><?php echo date("d.m.Y", strtotime($s['siparis_tarihi'])); ?></small></td>
                                            <td>
                                                <?php if (!empty($s['alici_adi'])): ?>
                                                    <div class="small fw-bold text-primary"><i class="fas fa-user-tag me-1"></i><?php echo $s['alici_adi']; ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($s['odeme_tarihi'])): ?>
                                                    <div class="small text-danger"><i class="fas fa-calendar-check me-1"></i>Vade: <?php echo date("d.m.Y", strtotime($s['odeme_tarihi'])); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($s['aciklama'])): ?>
                                                    <div class="small text-muted mt-1 fst-italic"><i class="fas fa-comment-dots me-1"></i><?php echo htmlspecialchars($s['aciklama']); ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($s['alici_adi']) && empty($s['odeme_tarihi']) && empty($s['aciklama'])): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo $s['urun_adi']; ?></div>
                                                <div class="small text-muted"><?php echo $s['miktar'] . ' ' . $s['birim']; ?></div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $renk; ?>"><?php echo $s['durum']; ?></span></td>
                                            <td>
                                                <?php
                                                if ($s['parti_nolar']) {
                                                    $partiler = array_unique(explode(',', $s['parti_nolar']));
                                                    foreach ($partiler as $p) {
                                                        $p = trim($p);
                                                        if (empty($p)) continue;
                                                        echo "<span class='track-code me-1'><i class='fas fa-barcode'></i> {$p}</span> ";
                                                    }
                                                } else {
                                                    echo "<span class='text-muted small'>Henüz sevk edilmedi</span>";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($s['parti_nolar']) { ?>
                                                    <?php $ilk_parti = trim(explode(',', $s['parti_nolar'])[0]); ?>
                                                    <?php if (!empty($ilk_parti)) { ?>
                                                        <a href="izlenebilirlik.php?sorgula=1&parti_no=<?php echo urlencode($ilk_parti); ?>"
                                                            class="btn btn-sm btn-success fw-bold text-white shadow-sm" target="_blank">
                                                            <i class="fas fa-route me-1"></i> Rapor
                                                        </a>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php } else { ?>
                                <div class="p-5 text-center text-muted">
                                    <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                                    <p>Bu müşteriye ait güncel sipariş kaydı bulunamadı.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <!-- Boş Durum Ekranı -->
                    <div class="card shadow-sm border-0 h-100 d-flex flex-column align-items-center justify-content-center text-center p-5"
                        style="min-height: 400px; background-color: #f8f9fa;">
                        <div class="mb-4">
                            <i class="fas fa-user-circle fa-5x text-secondary opacity-50"></i>
                        </div>
                        <h4 class="text-muted">Müşteri Detayları</h4>
                        <p class="text-muted mb-4">Sipariş geçmişini ve izlenebilirlik raporlarını görmek için sol taraftaki
                            listeden bir müşteri seçin.</p>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#yeniMusteriModal">
                            <i class="fas fa-plus me-2"></i>Yeni Müşteri Ekle
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- MODAL: YENİ MÜŞTERİ -->
    <div class="modal fade" id="yeniMusteriModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Yeni Müşteri Kaydı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="row g-2">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Cari Kod *</label>
                                <input type="text" name="cari_kod" class="form-control" placeholder="Örn: 120.01.001" required>
                                <div class="form-text small" style="font-size:0.65rem;">120: Müşteri, 320: Tedarikçi</div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold small">Firma / Kurum Ünvanı *</label>
                                <input type="text" name="firma_adi" class="form-control" placeholder="Örn: Özbal Un ve Yem A.Ş." required>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Yetkili Kişi</label>
                                <input type="text" name="yetkili_kisi" class="form-control" placeholder="Ad Soyad">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Telefon</label>
                                <input type="text" name="telefon" class="form-control" placeholder="05XX XXX XX XX">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">E-Posta</label>
                            <input type="email" name="eposta" class="form-control" placeholder="info@firma.com">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Vergi Dairesi</label>
                                <input type="text" name="vergi_dairesi" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Vergi No</label>
                                <input type="text" name="vergi_no" class="form-control">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">İl</label>
                                <input type="text" name="il" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">İlçe</label>
                                <input type="text" name="ilce" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Açık Adres</label>
                            <textarea name="adres" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold small">Özel CRM Notları</label>
                            <textarea name="ozel_notlar" class="form-control" rows="2" placeholder="Müşteriye özel un talepleri, ambalaj tercihleri vb."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="musteri_ekle" class="btn btn-primary"><i
                                class="fas fa-save me-1"></i> Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        $(document).ready(function () {
            // SweetAlert2 Alerts
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
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
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

            // DataTables: Sadece tablo varsa başlat
            if ($('#raporTablosu').length > 0) {
                var table = $('#raporTablosu').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
                    },
                    "order": [[1, "desc"]],
                    "columnDefs": [
                        { "targets": [5, 6], "orderable": false } // İzlenebilirlik ve İşlem kolonlarını sıralamaya kapat
                    ],
                    "destroy": true, // Sayfa yenilenmeden tekrar yüklenirse hata vermemesi için
                    "responsive": true
                });
            }

            // Müşteri Arama (Sol Menü)
            $("#musteriAra").on("keyup", function () {
                var value = $(this).val().toLowerCase();
                $("#musteriListesi a").filter(function () {
                    // search text within a tag
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
