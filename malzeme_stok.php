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

// --- 1. YENİ MALZEME EKLEME ---
if (isset($_POST["malzeme_ekle"])) {
    $kod = strtoupper(trim($_POST["malzeme_kodu"]));
    $ad = trim($_POST["malzeme_adi"]);
    $kategori = $_POST["kategori"];
    $kapasite = $_POST["kapasite_kg"] ?: NULL;
    $birim = $_POST["birim"];
    $min_stok = $_POST["min_stok"] ?: 100;

    $sql = "INSERT INTO malzemeler (malzeme_kodu, malzeme_adi, kategori, kapasite_kg, birim, min_stok, mevcut_stok) 
            VALUES ('$kod', '$ad', '$kategori', " . ($kapasite ? $kapasite : "NULL") . ", '$birim', $min_stok, 0)";

    if ($baglanti->query($sql)) {
        $mesaj = "✅ Yeni malzeme eklendi: $ad";
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// --- 2. STOK GİRİŞİ (Satın Alma) ---
if (isset($_POST["stok_giris"])) {
    $malzeme_id = (int) $_POST["malzeme_id"];
    $miktar = (float) $_POST["miktar"];
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"] ?? '');

    // Stok güncelle
    $baglanti->query("UPDATE malzemeler SET mevcut_stok = mevcut_stok + $miktar WHERE id = $malzeme_id");

    // Hareket kaydı
    $baglanti->query("INSERT INTO malzeme_hareketleri (malzeme_id, hareket_tipi, miktar, aciklama, kullanici) 
                      VALUES ($malzeme_id, 'giris', $miktar, '$aciklama', '{$_SESSION["kadi"]}')");

    $mesaj = "✅ Stok girişi yapıldı: $miktar adet";
}

// --- 3. STOK ÇIKIŞI (Üretim) ---
if (isset($_POST["stok_cikis"])) {
    $malzeme_id = (int) $_POST["malzeme_id"];
    $uretim_kg = (float) $_POST["uretim_kg"];
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"] ?? '');

    // Malzeme bilgisini al
    $mlz = $baglanti->query("SELECT * FROM malzemeler WHERE id = $malzeme_id")->fetch_assoc();

    if ($mlz['kapasite_kg'] > 0) {
        // Otomatik hesapla: kg / torba kapasitesi = adet
        $adet = ceil($uretim_kg / $mlz['kapasite_kg']);

        // Stok kontrolü
        if ($adet > $mlz['mevcut_stok']) {
            $hata = "❌ Yetersiz stok! Gereken: $adet adet, Mevcut: {$mlz['mevcut_stok']} adet";
        } else {
            // Stok düşür
            $baglanti->query("UPDATE malzemeler SET mevcut_stok = mevcut_stok - $adet WHERE id = $malzeme_id");

            // Hareket kaydı
            $baglanti->query("INSERT INTO malzeme_hareketleri (malzeme_id, hareket_tipi, miktar, uretim_kg, aciklama, kullanici) 
                              VALUES ($malzeme_id, 'cikis', $adet, $uretim_kg, '$aciklama', '{$_SESSION["kadi"]}')");

            $mesaj = "✅ Üretim için $adet adet {$mlz['malzeme_adi']} stoktan düşüldü ($uretim_kg kg / {$mlz['kapasite_kg']} kg = $adet adet)";
        }
    } else {
        // Kapasite yoksa direkt adet olarak düş
        $adet = (int) $uretim_kg;
        $baglanti->query("UPDATE malzemeler SET mevcut_stok = mevcut_stok - $adet WHERE id = $malzeme_id");
        $baglanti->query("INSERT INTO malzeme_hareketleri (malzeme_id, hareket_tipi, miktar, aciklama, kullanici) 
                          VALUES ($malzeme_id, 'cikis', $adet, '$aciklama', '{$_SESSION["kadi"]}')");
    }
}

// --- 4. SATIN ALMA TALEBİ OLUŞTUR ---
if (isset($_POST["talep_olustur"])) {
    $malzeme_id = (int) $_POST["malzeme_id"];
    $miktar = (int) $_POST["talep_miktar"];

    // Malzeme bilgisini al
    $mlz = $baglanti->query("SELECT * FROM malzemeler WHERE id = $malzeme_id")->fetch_assoc();
    $user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 1;

    // Satın alma talebine ekle - malzeme kodunu da ekle (kolay eşleştirme için)
    $malzeme_adi = $baglanti->real_escape_string($mlz['malzeme_adi']);
    $malzeme_kodu = $baglanti->real_escape_string($mlz['malzeme_kodu']);
    $birim = $baglanti->real_escape_string($mlz['birim']);

    // malzeme_adi alanına "KOD:malzeme_id:isim" formatında kaydet
    $malzeme_bilgi = "ID:$malzeme_id|$malzeme_adi";

    $sql = "INSERT INTO satin_alma_talepleri (talep_eden_user_id, malzeme_adi, miktar, birim, aciliyet) 
            VALUES ($user_id, '$malzeme_bilgi', '$miktar', '$birim', 'Acil')";

    if ($baglanti->query($sql)) {
        $mesaj = "✅ Satın alma talebi oluşturuldu: $miktar $birim $malzeme_adi (ID: $malzeme_id)";
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// LİSTELERİ ÇEK - Dinamik sütun kontrolü
$columns = $baglanti->query("SHOW COLUMNS FROM malzemeler");
$col_names = [];
while ($col = $columns->fetch_assoc()) {
    $col_names[] = $col['Field'];
}

// Varsayılan sorgu
$select_fields = "id, malzeme_kodu, malzeme_adi, kategori, birim";
if (in_array('kapasite_kg', $col_names))
    $select_fields .= ", kapasite_kg";
if (in_array('mevcut_stok', $col_names))
    $select_fields .= ", mevcut_stok";
if (in_array('min_stok', $col_names))
    $select_fields .= ", min_stok";
if (in_array('aktif', $col_names)) {
    $malzemeler = $baglanti->query("SELECT $select_fields FROM malzemeler WHERE aktif = 1 ORDER BY kategori, malzeme_adi");
} else {
    $malzemeler = $baglanti->query("SELECT $select_fields FROM malzemeler ORDER BY kategori, malzeme_adi");
}

// Hareketler - tablo varsa çek
$hareketler = null;
$tables = $baglanti->query("SHOW TABLES LIKE 'malzeme_hareketleri'");
if ($tables->num_rows > 0) {
    $hareketler = $baglanti->query("SELECT mh.*, m.malzeme_adi, m.kapasite_kg FROM malzeme_hareketleri mh 
                                     JOIN malzemeler m ON mh.malzeme_id = m.id 
                                     ORDER BY mh.islem_tarihi DESC LIMIT 20");
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malzeme Stok - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .kritik-stok {
            background: #fee2e2 !important;
        }

        .stok-badge {
            font-size: 1.1rem;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark"><i class="fas fa-cubes text-primary"></i> Malzeme Stok Yönetimi</h2>
                <p class="text-muted mb-0">Torba, çuval, ip ve diğer malzemelerin stok takibi</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stokGirisModal">
                    <i class="fas fa-plus-circle"></i> Stok Girişi
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#stokCikisModal">
                    <i class="fas fa-minus-circle"></i> Üretim Çıkışı
                </button>
                <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#yeniMalzemeModal">
                    <i class="fas fa-box"></i> Yeni Malzeme
                </button>
            </div>
        </div>



        <!-- STOK LİSTESİ -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-boxes text-secondary"></i> Stok Durumu</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kod</th>
                                    <th>Malzeme</th>
                                    <th>Kapasite</th>
                                    <th class="text-center">Mevcut Stok</th>
                                    <th>Min</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($malzemeler && $malzemeler->num_rows > 0) {
                                    while ($m = $malzemeler->fetch_assoc()) {
                                        $mevcut = isset($m['mevcut_stok']) ? $m['mevcut_stok'] : 0;
                                        $min = isset($m['min_stok']) ? $m['min_stok'] : 0;
                                        $kritik = $mevcut < $min;
                                        $renk = $kritik ? 'danger' : 'success';
                                        ?>
                                        <tr class="<?php echo $kritik ? 'kritik-stok' : ''; ?>">
                                            <td><span class="badge bg-secondary"><?php echo $m['malzeme_kodu']; ?></span></td>
                                            <td class="fw-bold"><?php echo $m['malzeme_adi']; ?></td>
                                            <td>
                                                <?php if (isset($m['kapasite_kg']) && $m['kapasite_kg']) { ?>
                                                    <span class="text-primary fw-bold"><?php echo $m['kapasite_kg']; ?> kg</span>
                                                <?php } else { ?>
                                                    <span class="text-muted">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $renk; ?> stok-badge">
                                                    <?php echo number_format($mevcut); ?>         <?php echo $m['birim']; ?>
                                                </span>
                                            </td>
                                            <td class="text-muted"><?php echo $min; ?></td>
                                            <td>
                                                <?php if ($kritik) { ?>
                                                    <span class="text-danger"><i class="fas fa-exclamation-triangle"></i>
                                                        Kritik!</span>
                                                <?php } else { ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if ($kritik) { ?>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                        data-bs-target="#talepModal<?php echo $m['id']; ?>">
                                                        <i class="fas fa-shopping-cart"></i> Talep Oluştur
                                                    </button>

                                                    <!-- Talep Modal -->
                                                    <div class="modal fade" id="talepModal<?php echo $m['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-sm">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h6 class="modal-title"><i class="fas fa-shopping-cart"></i>
                                                                        Satın Alma Talebi</h6>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p class="mb-2">
                                                                        <strong><?php echo $m['malzeme_adi']; ?></strong>
                                                                    </p>
                                                                    <p class="small text-muted mb-3">Mevcut: <?php echo $mevcut; ?>
                                                                        / Min: <?php echo $min; ?></p>
                                                                    <form method="post">
                                                                        <input type="hidden" name="malzeme_id"
                                                                            value="<?php echo $m['id']; ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Talep Miktarı</label>
                                                                            <input type="number" name="talep_miktar"
                                                                                class="form-control"
                                                                                value="<?php echo max(0, $min - $mevcut + 500); ?>"
                                                                                required>
                                                                        </div>
                                                                        <div class="d-grid">
                                                                            <button type="submit" name="talep_olustur"
                                                                                class="btn btn-danger">
                                                                                <i class="fas fa-paper-plane"></i> Talep Gönder
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center p-4 text-muted'>Henüz malzeme tanımlanmamış.</td></tr>";
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SON HAREKETLER -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-history text-secondary"></i> Son Hareketler</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php
                            if ($hareketler && $hareketler->num_rows > 0) {
                                while ($h = $hareketler->fetch_assoc()) {
                                    $ikon = $h['hareket_tipi'] == 'giris' ? 'arrow-down text-success' : 'arrow-up text-danger';
                                    $isaret = $h['hareket_tipi'] == 'giris' ? '+' : '-';
                                    ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-<?php echo $ikon; ?> me-2"></i>
                                                <strong>
                                                    <?php echo $h['malzeme_adi']; ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date("d.m H:i", strtotime($h['islem_tarihi'])); ?>
                                                    <?php if ($h['uretim_kg'])
                                                        echo " • " . $h['uretim_kg'] . " kg üretim"; ?>
                                                </small>
                                            </div>
                                            <span
                                                class="badge bg-<?php echo $h['hareket_tipi'] == 'giris' ? 'success' : 'danger'; ?>">
                                                <?php echo $isaret . $h['miktar']; ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php }
                            } else {
                                echo "<li class='list-group-item text-center text-muted'>Henüz hareket yok.</li>";
                            } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- YENİ MALZEME MODAL -->
    <div class="modal fade" id="yeniMalzemeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-box"></i> Yeni Malzeme Tanımla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Malzeme Kodu</label>
                                <input type="text" name="malzeme_kodu" class="form-control" placeholder="TRB-25"
                                    required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="kategori" class="form-select">
                                    <option value="ambalaj">Ambalaj</option>
                                    <option value="sarf">Sarf Malzeme</option>
                                    <option value="katki">Katkı Maddesi</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Malzeme Adı</label>
                            <input type="text" name="malzeme_adi" class="form-control" placeholder="25 kg Torba"
                                required>
                        </div>
                        <div class="row">
                            <div class="col-4 mb-3">
                                <label class="form-label">Kapasite (kg)</label>
                                <input type="number" step="0.1" name="kapasite_kg" class="form-control"
                                    placeholder="25">
                                <small class="text-muted">Torba için doldurun</small>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label">Birim</label>
                                <select name="birim" class="form-select">
                                    <option value="adet">Adet</option>
                                    <option value="kg">Kg</option>
                                    <option value="rulo">Rulo</option>
                                    <option value="paket">Paket</option>
                                </select>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label">Min Stok</label>
                                <input type="number" name="min_stok" class="form-control" value="100">
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="malzeme_ekle" class="btn btn-success">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- STOK GİRİŞ MODAL -->
    <div class="modal fade" id="stokGirisModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Stok Girişi (Satın Alma)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Malzeme Seçin</label>
                            <select name="malzeme_id" class="form-select" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                $malzemeler->data_seek(0);
                                while ($m = $malzemeler->fetch_assoc()) { ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo $m['malzeme_kodu']; ?> -
                                        <?php echo $m['malzeme_adi']; ?>
                                        (Mevcut:
                                        <?php echo $m['mevcut_stok']; ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Giriş Miktarı</label>
                            <input type="number" step="1" name="miktar" class="form-control" placeholder="1000"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="aciklama" class="form-control"
                                placeholder="Satın alma, tedarikçi vb.">
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="stok_giris" class="btn btn-success">Stok Girişi Yap</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- STOK ÇIKIŞ MODAL (ÜRETİM) -->
    <div class="modal fade" id="stokCikisModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-industry"></i> Üretim İçin Stok Çıkışı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Üretim miktarını kg olarak girin, sistem otomatik
                        hesaplayacak.
                        <br><small>Örn: 1250 kg / 25 kg torba = 50 adet düşer</small>
                    </div>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Torba/Çuval Seçin</label>
                            <select name="malzeme_id" class="form-select" required id="malzemeSecim">
                                <option value="">Seçiniz...</option>
                                <?php
                                $malzemeler->data_seek(0);
                                while ($m = $malzemeler->fetch_assoc()) {
                                    if ($m['kapasite_kg']) { ?>
                                        <option value="<?php echo $m['id']; ?>"
                                            data-kapasite="<?php echo $m['kapasite_kg']; ?>">
                                            <?php echo $m['malzeme_adi']; ?> (
                                            <?php echo $m['kapasite_kg']; ?> kg)
                                            - Stok:
                                            <?php echo $m['mevcut_stok']; ?>
                                        </option>
                                    <?php }
                                } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Üretim Miktarı (KG)</label>
                            <input type="number" step="0.1" name="uretim_kg" class="form-control" placeholder="1250"
                                required id="uretimKg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hesaplama</label>
                            <div class="alert alert-secondary" id="hesapSonuc">
                                Üretim miktarı ve torba seçin...
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="aciklama" class="form-control"
                                placeholder="İş emri no, vardiya vb.">
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="stok_cikis" class="btn btn-warning">Stoktan Düş</button>
                        </div>
                    </form>
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

        // Otomatik hesaplama
        document.getElementById('uretimKg').addEventListener('input', hesapla);
        document.getElementById('malzemeSecim').addEventListener('change', hesapla);

        function hesapla() {
            const kg = parseFloat(document.getElementById('uretimKg').value) || 0;
            const select = document.getElementById('malzemeSecim');
            const option = select.options[select.selectedIndex];
            const kapasite = parseFloat(option?.dataset?.kapasite) || 0;

            if (kg > 0 && kapasite > 0) {
                const adet = Math.ceil(kg / kapasite);
                document.getElementById('hesapSonuc').innerHTML =
                    `<strong>${kg} kg</strong> ÷ <strong>${kapasite} kg</strong> = <span class="text-danger fw-bold">${adet} adet</span> düşecek`;
            } else {
                document.getElementById('hesapSonuc').innerHTML = 'Üretim miktarı ve torba seçin...';
            }
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
