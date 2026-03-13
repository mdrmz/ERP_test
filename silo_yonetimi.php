<?php
// Hata Raporlama (Geliştirme aşamasında açık, canlıda loglanmalı)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("baglan.php");
include("helper_functions.php");

// Oturum Kontrolü
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

// --- YARDIMCI FONKSİYONLAR ---

if (!function_exists('sayiFormat')) {
    function sayiFormat($sayi, $ondalik = 2)
    {
        return number_format((float) $sayi, $ondalik, ',', '.');
    }
}

if (!function_exists('siloDolulukRenk')) {
    function siloDolulukRenk($yuzde)
    {
        if ($yuzde > 90)
            return 'bg-danger';
        if ($yuzde > 75)
            return 'bg-warning';
        if ($yuzde > 50)
            return 'bg-primary';
        return 'bg-success';
    }
}

if (!function_exists('alertMesaj')) {
    function alertMesaj($mesaj, $tip = 'info')
    {
        return "<div class='alert alert-$tip alert-dismissible fade show' role='alert'>
                    $mesaj
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
}

// --- ANA İŞLEMLER ---

$mesaj = "";
$hata = "";

// 1. SİLO EKLEME
if (isset($_POST['silo_ekle'])) {
    $adi = $baglanti->real_escape_string($_POST['silo_adi'] ?? '');
    $tip = $baglanti->real_escape_string($_POST['tip'] ?? 'bugday');
    $kapasite = (float) ($_POST['kapasite_m3'] ?? 0);
    $yogunluk = (float) ($_POST['yogunluk'] ?? 0.6);

    if ($kapasite <= 0)
        $kapasite = 10;
    if ($yogunluk <= 0)
        $yogunluk = 0.6;

    if ($adi) {
        $sql = "INSERT INTO silolar (silo_adi, tip, kapasite_m3, yogunluk, durum, doluluk_m3) 
                VALUES ('$adi', '$tip', $kapasite, $yogunluk, 'aktif', 0)";

        if ($baglanti->query($sql)) {
            $mesaj = "✅ Silo eklendi: $adi";
        } else {
            $hata = "Ekleme hatası: " . $baglanti->error;
        }
    } else {
        $hata = "Silo adı boş olamaz.";
    }
}

// 2. SİLO SİLME
if (isset($_POST['silo_sil'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    if ($id > 0 && $baglanti->query("DELETE FROM silolar WHERE id=$id")) {
        $mesaj = "🗑️ Silo silindi.";
    } else {
        $hata = "Silinirken hata oluştu.";
    }
}

// 3. SİLO GÜNCELLEME
if (isset($_POST['silo_guncelle'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    $adi = $baglanti->real_escape_string($_POST['silo_adi'] ?? '');
    $yogunluk = (float) ($_POST['yogunluk'] ?? 0.6);
    $kapasite = (float) ($_POST['kapasite_m3'] ?? 0);
    $durum = $baglanti->real_escape_string($_POST['durum'] ?? 'aktif');

    // Warning Hatası Çözümü: Null coalescing operator (??) kullanarak boş gelirse boş string ata
    $raw_aktif = $_POST['aktif_hammadde_kodu'] ?? '';
    $aktif_kod = $baglanti->real_escape_string($raw_aktif);

    // Eğer 'Boş' seçildiyse (value="") veritabanına NULL veya boş string kaydet
    if ($aktif_kod === '')
        $aktif_kod_sql = "NULL";
    else
        $aktif_kod_sql = "'$aktif_kod'";

    // JSON Verisi (Checkboxlar gelmemişse null)
    $izinli_kodlar = isset($_POST['izinli_kodlar']) ? json_encode($_POST['izinli_kodlar']) : NULL;
    if ($izinli_kodlar)
        $izinli_kodlar = $baglanti->real_escape_string($izinli_kodlar);
    $izinli_sql = $izinli_kodlar ? "'$izinli_kodlar'" : "NULL";

    if ($id > 0) {
        // aktif_hammadde_kodu için özel SQL formatı (NULL desteği)
        $sql = "UPDATE silolar SET 
                silo_adi='$adi', 
                yogunluk=$yogunluk, 
                kapasite_m3=$kapasite, 
                durum='$durum',
                aktif_hammadde_kodu=$aktif_kod_sql, 
                izin_verilen_hammadde_kodlari=$izinli_sql
                WHERE id=$id";

        if ($baglanti->query($sql)) {
            $mesaj = "✅ Güncellendi.";
        } else {
            $hata = "Güncelleme hatası: " . $baglanti->error;
        }
    }
}

// 4. SİLO SIFIRLAMA
if (isset($_POST['silo_sifirla'])) {
    $id = (int) ($_POST['silo_id'] ?? 0);
    if ($id > 0) {
        $baglanti->query("UPDATE silolar SET doluluk_m3=0, aktif_hammadde_kodu=NULL, durum='temizlik' WHERE id=$id");
        $mesaj = "✅ Silo boşaltıldı.";
    }
}

// SİLO VERİLERİNİ ÇEKME
$silolar_bugday = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' ORDER BY silo_adi");
$silolar_un = $baglanti->query("SELECT * FROM silolar WHERE tip='un' ORDER BY silo_adi");
$silolar_tav = $baglanti->query("SELECT * FROM silolar WHERE tip='tav' ORDER BY silo_adi");
$silolar_kepek = $baglanti->query("SELECT * FROM silolar WHERE tip='kepek' ORDER BY silo_adi");

// HAMMADDELER
$hammadde_listesi = [];
$hm_sql = "SELECT hammadde_kodu FROM hammaddeler ORDER BY hammadde_kodu";
$h_result = $baglanti->query($hm_sql);

if ($h_result) {
    while ($r = $h_result->fetch_assoc()) {
        $r['hammadde_adi'] = ''; // UI hatası önlemi
        $hammadde_listesi[] = $r;
    }
}

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Silo Yönetimi</title>
    <!-- CSS Kütüphaneleri -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-warehouse text-primary"></i> Silo Yönetimi</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniSiloModal">
                <i class="fas fa-plus"></i> Yeni Silo Ekle
            </button>
        </div>



        <!-- SEKMELER -->
        <ul class="nav nav-tabs mb-4" id="siloTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="bugday-tab" data-bs-toggle="tab"
                    data-bs-target="#bugday-pane" type="button">
                    <i class="fas fa-seedling text-warning"></i> Buğday
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="un-tab" data-bs-toggle="tab" data-bs-target="#un-pane"
                    type="button">
                    <i class="fas fa-bread-slice text-secondary"></i> Un
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="tav-tab" data-bs-toggle="tab" data-bs-target="#tav-pane"
                    type="button">
                    <i class="fas fa-tint text-info"></i> Tav
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="kepek-tab" data-bs-toggle="tab" data-bs-target="#kepek-pane"
                    type="button">
                    <i class="fas fa-leaf text-success"></i> Kepek
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <?php
            $tipler = [
                'bugday' => ['data' => $silolar_bugday, 'id' => 'bugday-pane', 'active' => true],
                'un' => ['data' => $silolar_un, 'id' => 'un-pane', 'active' => false],
                'tav' => ['data' => $silolar_tav, 'id' => 'tav-pane', 'active' => false],
                'kepek' => ['data' => $silolar_kepek, 'id' => 'kepek-pane', 'active' => false]
            ];

            foreach ($tipler as $key => $val) {
                $activeClass = $val['active'] ? 'show active' : '';
                echo "<div class='tab-pane fade $activeClass' id='{$val['id']}'><div class='row'>";

                if ($val['data'] && $val['data']->num_rows > 0) {
                    while ($row = $val['data']->fetch_assoc()) {
                        // Veri temizliği (null check)
                        $yogunluk = (float) ($row['yogunluk'] ?? 0.6);
                        $cap_m3 = (float) ($row['kapasite_m3'] ?? 0);
                        $dol_m3 = (float) ($row['doluluk_m3'] ?? 0);
                        $durum = htmlspecialchars($row['durum'] ?? 'aktif');
                        $silo_adi = htmlspecialchars($row['silo_adi'] ?? '');
                        $aktif_kod = htmlspecialchars($row['aktif_hammadde_kodu'] ?? '');

                        $yuzde = ($cap_m3 > 0) ? ($dol_m3 / $cap_m3) * 100 : 0;
                        $renk = siloDolulukRenk($yuzde);

                        // İkon Seçimi
                        $ikon = 'fa-warehouse';
                        if ($key == 'un')
                            $ikon = 'fa-bread-slice';
                        if ($key == 'bugday')
                            $ikon = 'fa-seedling';
                        if ($key == 'tav')
                            $ikon = 'fa-tint';
                        if ($key == 'kepek')
                            $ikon = 'fa-leaf';

                        // Durum Badge Rengi
                        $durum_badge = ($durum == 'aktif') ? 'bg-success' : (($durum == 'bakim') ? 'bg-warning' : 'bg-secondary');

                        echo "
                    <div class='col-md-6 col-lg-4 mb-4'>
                        <div class='card shadow-sm h-100 border-start border-4 border-primary'>
                            <div class='card-header bg-white d-flex justify-content-between align-items-center'>
                                <h5 class='mb-0 text-primary'><i class='fas $ikon'></i> $silo_adi</h5>
                                <span class='badge $durum_badge'>" . strtoupper($durum) . "</span>
                            </div>
                            <div class='card-body'>
                                <div class='row align-items-center mb-3'>
                                    <div class='col-4 text-center'>
                                        <div class='silo-visual border rounded position-relative bg-light' style='height:120px; width:60px; margin:0 auto; overflow:hidden; border:2px solid #555;'>
                                            <div class='position-absolute bottom-0 w-100 $renk' style='height: {$yuzde}%; transition: height 1s;'></div>
                                            <div class='position-absolute top-0 w-100 h-100' style='background: repeating-linear-gradient(transparent, transparent 19px, rgba(0,0,0,0.1) 20px);'></div>
                                        </div>
                                        <small class='d-block mt-1 fw-bold'>%" . round($yuzde) . "</small>
                                    </div>
                                    <div class='col-8'>
                                        <ul class='list-group list-group-flush small'>
                                            <li class='list-group-item d-flex justify-content-between px-0'>
                                                <span><i class='fas fa-cube text-muted'></i> Hacim:</span>
                                                <strong>" . sayiFormat($dol_m3, 1) . " m³</strong>
                                            </li>
                                            <li class='list-group-item d-flex justify-content-between px-0 text-primary'>
                                                <span><i class='fas fa-weight-hanging'></i> Tonaj:</span>
                                                <strong>" . sayiFormat($dol_m3 * $yogunluk, 2) . " Ton</strong>
                                            </li>
                                            <li class='list-group-item d-flex justify-content-between px-0 text-muted'>
                                                <span>Mak:</span>
                                                <span>" . sayiFormat($cap_m3 * $yogunluk, 0) . " Ton</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class='alert alert-light py-1 px-2 mb-2 text-center border'>
                                    <small>" . ($aktif_kod ? "<strong>$aktif_kod</strong>" : "Boş") . "</small>
                                </div>

                                <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                    <button class='btn btn-sm btn-outline-secondary w-100' onclick='duzenleModal(" . json_encode($row) . ")'>
                                        <i class='fas fa-cog'></i> Düzenle
                                    </button>
                                    ";

                        if ($dol_m3 > 0) {
                            echo "<button class='btn btn-sm btn-outline-danger w-100' onclick='sifirlaModal({$row['id']}, \"$silo_adi\")'>
                                <i class='fas fa-trash-alt'></i> Boşalt
                              </button>";
                        } else {
                            echo "<button class='btn btn-sm btn-outline-danger w-100' onclick='silModal({$row['id']}, \"$silo_adi\")'>
                                <i class='fas fa-times'></i> Sil
                              </button>";
                        }

                        echo "      </div>
                            </div>
                        </div>
                    </div>";
                    }
                } else {
                    echo "<div class='col-12 p-3 text-muted'>Bu kategoride silo bulunmuyor.</div>";
                }
                echo "</div></div>";
            }
            ?>
        </div>
    </div>

    <!-- MODAL: YENİ SİLO -->
    <div class="modal fade" id="yeniSiloModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Yeni Silo Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label>Adı</label><input type="text" name="silo_adi" class="form-control"
                            required></div>
                    <div class="mb-3"><label>Tipi</label>
                        <select name="tip" class="form-select">
                            <option value="bugday">Buğday Silosu</option>
                            <option value="un">Un Silosu</option>
                            <option value="tav">Tav Silosu</option>
                            <option value="kepek">Kepek Silosu</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6"><label>Kapasite (m³)</label><input type="number" step="0.1"
                                name="kapasite_m3" class="form-control" required></div>
                        <div class="col-6"><label>Yoğunluk</label><input type="number" step="0.001" name="yogunluk"
                                class="form-control" value="0.6"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="silo_ekle" class="btn btn-success">Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: DÜZENLE -->
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Silo Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="silo_id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary border-bottom pb-2">Ayarlar</h6>
                            <div class="mb-3"><label>Adı</label><input type="text" name="silo_adi" id="edit_adi"
                                    class="form-control" required></div>
                            <div class="row mb-3">
                                <div class="col-6"><label>m³</label><input type="number" step="0.1" name="kapasite_m3"
                                        id="edit_kapasite" class="form-control"></div>
                                <div class="col-6"><label>Yoğunluk</label><input type="number" step="0.001"
                                        name="yogunluk" id="edit_yogunluk" class="form-control"></div>
                            </div>
                            <div class="mb-3"><label>Durum</label>
                                <select name="durum" id="edit_durum" class="form-select">
                                    <option value="aktif">Aktif</option>
                                    <option value="bakim">Bakım</option>
                                    <option value="temizlik">Temizlik</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2">İçerik & Kısıtlama</h6>
                            <div class="mb-3">
                                <label>İçindeki Ürün</label>
                                <select name="aktif_hammadde_kodu" id="edit_aktif_kod" class="form-select">
                                    <option value="">-- Boş --</option>
                                    <?php foreach ($hammadde_listesi as $h)
                                        echo "<option value='{$h['hammadde_kodu']}'>{$h['hammadde_kodu']}</option>"; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label>Sadece Bunlar Girebilir:</label>
                                <div class="border rounded p-2 bg-light" style="height:150px; overflow-y:auto;">
                                    <?php foreach ($hammadde_listesi as $h) { ?>
                                        <div class="form-check">
                                            <input type="checkbox" name="izinli_kodlar[]"
                                                value="<?php echo $h['hammadde_kodu']; ?>"
                                                id="chk_<?php echo $h['hammadde_kodu']; ?>"
                                                class="form-check-input izinli-check">
                                            <label class="form-check-label small"
                                                for="chk_<?php echo $h['hammadde_kodu']; ?>"><?php echo $h['hammadde_kodu']; ?></label>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="silo_guncelle"
                        class="btn btn-primary">Kaydet</button></div>
            </form>
        </div>
    </div>

    <!-- Diğer Modallar -->
    <div class="modal fade" id="sifirlaModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-body">
                    <input type="hidden" name="silo_id" id="sifirla_id">
                    <p><strong><span id="sifirla_adi"></span></strong> boşaltılsın mı?</p>
                    <div class="text-end">
                        <button type="submit" name="silo_sifirla" class="btn btn-warning">Boşalt</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="silModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-body">
                    <input type="hidden" name="silo_id" id="sil_id">
                    <p class="text-danger"><strong><span id="sil_adi"></span></strong> silinecek. Emin misin?</p>
                    <div class="text-end">
                        <button type="submit" name="silo_sil" class="btn btn-danger">Sil</button>
                    </div>
                </div>
            </form>
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
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ "], "", $mesaj)); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", $hata)); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>
        });

        function duzenleModal(data) {
            document.getElementById('edit_id').value = data.id || '';
            document.getElementById('edit_adi').value = data.silo_adi || '';
            document.getElementById('edit_kapasite').value = data.kapasite_m3 || '';
            document.getElementById('edit_yogunluk').value = data.yogunluk || 0.6;
            document.getElementById('edit_durum').value = data.durum || 'aktif';
            document.getElementById('edit_aktif_kod').value = data.aktif_hammadde_kodu || '';

            document.querySelectorAll('.izinli-check').forEach(cb => cb.checked = false);
            if (data.izin_verilen_hammadde_kodlari) {
                try {
                    JSON.parse(data.izin_verilen_hammadde_kodlari).forEach(k => {
                        let cb = document.getElementById('chk_' + k);
                        if (cb) cb.checked = true;
                    });
                } catch (e) { }
            }
            new bootstrap.Modal(document.getElementById('duzenleModal')).show();
        }

        function sifirlaModal(id, adi) {
            document.getElementById('sifirla_id').value = id;
            document.getElementById('sifirla_adi').innerText = adi;
            new bootstrap.Modal(document.getElementById('sifirlaModal')).show();
        }

        function silModal(id, adi) {
            document.getElementById('sil_id').value = id;
            document.getElementById('sil_adi').innerText = adi;
            new bootstrap.Modal(document.getElementById('silModal')).show();
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
