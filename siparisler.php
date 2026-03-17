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

// --- 1. YENİ MÜŞTERİ EKLE ---
if (isset($_POST["musteri_ekle"])) {
    $kod = $baglanti->real_escape_string($_POST["cari_kod"]);
    $tip = (strpos($kod, '120') === 0) ? 'Müşteri' : ((strpos($kod, '320') === 0) ? 'Tedarikçi' : 'Müşteri');
    $ad = $baglanti->real_escape_string($_POST["firma_adi"]);
    $yetkili = $baglanti->real_escape_string($_POST["yetkili_kisi"]);
    $tel = $baglanti->real_escape_string($_POST["telefon"]);
    $adres = $baglanti->real_escape_string($_POST["adres"]);

    // Aynı kodda var mı kontrolü
    $kontrol = $baglanti->query("SELECT id FROM musteriler WHERE cari_kod = '$kod'");
    if ($kontrol && $kontrol->num_rows > 0) {
        $hata = "⚠️ Bu cari kod ($kod) zaten kayıtlı!";
    } else {
        $sql = "INSERT INTO musteriler (cari_kod, cari_tip, firma_adi, yetkili_kisi, telefon, adres) 
                VALUES ('$kod', '$tip', '$ad', '$yetkili', '$tel', '$adres')";
        if ($baglanti->query($sql)) {
            $mesaj = "✅ Müşteri eklendi: $ad ($kod)";
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// --- 2. YENİ SİPARİŞ OLUŞTUR ---
if (isset($_POST["siparis_olustur"])) {
    $musteri_id = (int) $_POST["musteri_id"];
    $tarih = $_POST["siparis_tarihi"];
    $teslim = $_POST["teslim_tarihi"];
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"]);
    $siparis_kodu = "SIP-" . date("Ymd") . "-" . rand(100, 999);

    // Ana Sipariş Kaydı
    $sql_baslik = "INSERT INTO siparisler (musteri_id, siparis_kodu, siparis_tarihi, teslim_tarihi, aciklama, durum) 
                   VALUES ($musteri_id, '$siparis_kodu', '$tarih', '$teslim', '$aciklama', 'Bekliyor')";

    if ($baglanti->query($sql_baslik)) {
        $siparis_id = $baglanti->insert_id;

        // Ürünleri Ekle
        if (isset($_POST["urunler"]) && is_array($_POST["urunler"])) {
            foreach ($_POST["urunler"] as $k => $urun_adi) {
                if (empty($urun_adi))
                    continue;
                $miktar = (int) $_POST["miktarlar"][$k];
                $birim = $_POST["birimler"][$k];

                // Ürün adını temizle
                $urun_adi = $baglanti->real_escape_string($urun_adi);

                $baglanti->query("INSERT INTO siparis_detaylari (siparis_id, urun_adi, miktar, birim) 
                                   VALUES ($siparis_id, '$urun_adi', $miktar, '$birim')");
            }
        }
        $mesaj = "✅ Sipariş oluşturuldu: $siparis_kodu";
    } else {
        $hata = "Sipariş oluşturulurken hata: " . $baglanti->error;
    }
}

// --- 3. SEVKİYAT GİRİŞİ (Parçalı Sevkiyat) ---
if (isset($_POST["sevkiyat_gir"])) {
    $siparis_id = (int) $_POST["siparis_id"];
    $plaka = $baglanti->real_escape_string($_POST["plaka"]);
    $sevk_tarihi = $_POST["sevk_tarihi"];

    $sevk_var = false;
    $hata_log = [];

    if (isset($_POST["sevk_miktar"]) && is_array($_POST["sevk_miktar"])) {
        foreach ($_POST["sevk_miktar"] as $detay_id => $miktar) {
            $miktar = (int) $miktar;
            if ($miktar > 0) {
                $sevk_var = true;

                // 1. Detay tablosunda sevk edilen miktarı güncelle
                $sql_upd = "UPDATE siparis_detaylari SET sevk_edilen_miktar = sevk_edilen_miktar + $miktar WHERE id = $detay_id";
                if (!$baglanti->query($sql_upd)) {
                    $hata_log[] = "Ürün ID $detay_id güncellenemedi: " . $baglanti->error;
                    continue;
                }

                // 2. Ürün adını al
                $detay_res = $baglanti->query("SELECT urun_adi FROM siparis_detaylari WHERE id=$detay_id");
                if (!$detay_res) {
                    $hata_log[] = "Ürün adı alınamadı: " . $baglanti->error;
                    continue;
                }

                $detay = $detay_res->fetch_assoc();
                $urun_adi = $baglanti->real_escape_string($detay['urun_adi']);

                // 3. Sevkiyat logu ekle
                $sql_ins = "INSERT INTO sevkiyat_detaylari (siparis_id, urun_adi, miktar, sevk_tarihi, plaka) 
                            VALUES ($siparis_id, '$urun_adi', $miktar, '$sevk_tarihi', '$plaka')";
                if (!$baglanti->query($sql_ins)) {
                    $hata_log[] = "Sevkiyat detay eklenemedi: " . $baglanti->error;
                }
            }
        }
    }

    if ($sevk_var && empty($hata_log)) {
        $toplam_sip_res = $baglanti->query("SELECT SUM(miktar) as t FROM siparis_detaylari WHERE siparis_id=$siparis_id");
        $toplam_sevk_res = $baglanti->query("SELECT SUM(sevk_edilen_miktar) as t FROM siparis_detaylari WHERE siparis_id=$siparis_id");

        $toplam_sip = ($toplam_sip_res) ? $toplam_sip_res->fetch_assoc()['t'] : 0;
        $toplam_sevk = ($toplam_sevk_res) ? $toplam_sevk_res->fetch_assoc()['t'] : 0;

        $yeni_durum = ($toplam_sip > 0 && $toplam_sevk >= $toplam_sip) ? 'TeslimEdildi' : 'KismiSevk';
        $baglanti->query("UPDATE siparisler SET durum='$yeni_durum' WHERE id=$siparis_id");

        $mesaj = "✅ Sevkiyat kaydedildi. Sipariş Durumu: $yeni_durum";
    } else {
        if (!empty($hata_log)) {
            $hata = "Sevkiyat sırasında bazı hatalar oluştu: <br>" . implode("<br>", $hata_log);
        } else {
            $hata = "Lütfen sevk edilecek miktarları girin.";
        }
    }
}

// VERİLERİ ÇEK
$musteriler = $baglanti->query("SELECT * FROM musteriler ORDER BY firma_adi");
$siparisler = $baglanti->query("SELECT s.*, m.firma_adi, m.cari_kod FROM siparisler s JOIN musteriler m ON s.musteri_id = m.id ORDER BY s.siparis_tarihi DESC");
$urunler_list = $baglanti->query("SELECT * FROM urunler"); // Ürün listesi (dropdown için)

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Satış & Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .progress-bar-striped {
            transition: width .6s ease;
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shopping-bag text-primary"></i> Satış & Sipariş Yönetimi</h2>
            <div>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#yeniMusteriModal">
                    <i class="fas fa-user-plus"></i> Müşteri Ekle
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniSiparisModal">
                    <i class="fas fa-plus-circle"></i> Sipariş Oluştur
                </button>
            </div>
        </div>



        <!-- SİPARİŞ LİSTESİ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sipariş Listesi</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cari Kod</th>
                            <th>Müşteri</th>
                            <th>Sipariş Tarihi</th>
                            <th>Durum</th>
                            <th>İlerleme</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($siparisler->num_rows > 0) {
                            while ($s = $siparisler->fetch_assoc()) {
                                // İlerleme Hesabı
                                $detaylar = $baglanti->query("SELECT SUM(miktar) as top, SUM(sevk_edilen_miktar) as sevk FROM siparis_detaylari WHERE siparis_id={$s['id']}")->fetch_assoc();
                                $yuzde = ($detaylar['top'] > 0) ? round(($detaylar['sevk'] / $detaylar['top']) * 100) : 0;

                                $renk = 'secondary';
                                if ($s['durum'] == 'Bekliyor')
                                    $renk = 'warning';
                                if ($s['durum'] == 'Hazirlaniyor')
                                    $renk = 'info';
                                if ($s['durum'] == 'KismiSevk')
                                    $renk = 'primary';
                                if ($s['durum'] == 'TeslimEdildi')
                                    $renk = 'success';
                                ?>
                                <tr>
                                    <td><small class="fw-bold"><?php echo $s['cari_kod']; ?></small></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $s['firma_adi']; ?></div>
                                        <div class="small text-muted"><?php echo $s['siparis_kodu']; ?></div>
                                    </td>
                                    <td>
                                        <?php echo date("d.m.Y", strtotime($s['siparis_tarihi'])); ?>
                                    </td>
                                    <td><span class="badge bg-<?php echo $renk; ?>">
                                            <?php echo $s['durum']; ?>
                                        </span></td>
                                    <td style="width: 150px;">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar progress-bar-striped bg-<?php echo $renk; ?>"
                                                role="progressbar" style="width: <?php echo $yuzde; ?>%"></div>
                                        </div>
                                        <small class="text-muted">%
                                            <?php echo $yuzde; ?> Tamamlandı
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info text-white"
                                            onclick="detayAc(<?php echo $s['id']; ?>)">
                                            <i class="fas fa-eye"></i> Detay
                                        </button>
                                        <?php if ($yuzde < 100) { ?>
                                            <button class="btn btn-sm btn-dark"
                                                onclick="sevkAc(<?php echo $s['id']; ?>, '<?php echo $s['siparis_kodu']; ?>')">
                                                <i class="fas fa-truck"></i> Sevk Et
                                            </button>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo "<tr><td colspan='6' class='text-center p-3'>Kayıt yok.</td></tr>";
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL: YENİ SİPARİŞ (BASİT) -->
    <div class="modal fade" id="yeniSiparisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Yeni Sipariş Oluştur</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label>Müşteri Seçin</label>
                                <select name="musteri_id" class="form-select" required>
                                    <option value="">Seçiniz...</option>
                                    <?php
                                    if ($musteriler && $musteriler->num_rows > 0) {
                                        $musteriler->data_seek(0);
                                        while ($m = $musteriler->fetch_assoc()) {
                                            echo "<option value='{$m['id']}'>[{$m['cari_kod']}] {$m['firma_adi']}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-3">
                                <label>Sipariş Tarihi</label>
                                <input type="date" name="siparis_tarihi" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-3">
                                <label>Teslim Tarihi</label>
                                <input type="date" name="teslim_tarihi" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Notlar</label>
                            <input type="text" name="aciklama" class="form-control" placeholder="Örn: Acil sipariş">
                        </div>
                        <hr>
                        <h6>Ürünler</h6>
                        <div id="urunListesi">
                            <div class="row mb-2">
                                <div class="col-6">
                                    <select name="urunler[]" class="form-select" required>
                                        <option value="">Ürün Seç...</option>
                                        <?php
                                        $urunler_list->data_seek(0);
                                        while ($u = $urunler_list->fetch_assoc())
                                            echo "<option value='{$u['urun_adi']}'>{$u['urun_adi']}</option>";
                                        ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="miktarlar[]" class="form-control" placeholder="Miktar"
                                        required>
                                </div>
                                <div class="col-3">
                                    <select name="birimler[]" class="form-select">
                                        <option value="Adet">Adet</option>
                                        <option value="Kg">Kg</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="satirEkle()">+ Ürün
                            Ekle</button>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="siparis_olustur" class="btn btn-primary">Siparişi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SEVKİYAT GİRİŞ -->
    <div class="modal fade" id="sevkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">Sevkiyat Çıkışı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="siparis_id" id="sevkSiparisId">
                        <p id="sevkBaslik" class="fw-bold text-primary"></p>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label>Sevk Tarihi</label>
                                <input type="date" name="sevk_tarihi" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label>Araç Plaka</label>
                                <input type="text" name="plaka" class="form-control" placeholder="34 ABC 123" required>
                            </div>
                        </div>

                        <h6>Sevk Edilecek Miktarlar:</h6>
                        <div id="sevkUrunListesi">Yükleniyor...</div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="sevkiyat_gir" class="btn btn-primary">Sevkiyatı Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SİPARİŞ DETAY -->
    <div class="modal fade" id="detayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sipariş Detayı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detayIcerik">
                    Yükleniyor...
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: YENİ MÜŞTERİ (CRM UYUMLU) -->
    <div class="modal fade" id="yeniMusteriModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Hızlı Müşteri Kaydı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="row g-2">
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-bold small">Cari Kod *</label>
                                <input type="text" name="cari_kod" class="form-control" placeholder="120.XX.XXX" required>
                                <div class="form-text small" style="font-size:0.65rem;">120: Müşteri, 320: Tedarikçi</div>
                            </div>
                            <div class="col-md-7 mb-3">
                                <label class="form-label fw-bold small">Firma Ünvanı *</label>
                                <input type="text" name="firma_adi" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Yetkili Kişi</label>
                                <input type="text" name="yetkili_kisi" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Telefon</label>
                                <input type="text" name="telefon" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Açık Adres</label>
                            <textarea name="adres" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="fas fa-info-circle me-1"></i> Daha detaylı vergi ve iletişim bilgileri için <a href="musteriler.php" class="alert-link">Müşteri Yönetimi</a> sayfasını kullanın.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="musteri_ekle" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
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

        function satirEkle() {
            const row = document.querySelector('#urunListesi .row').cloneNode(true);
            row.querySelectorAll('input').forEach(i => i.value = '');
            document.getElementById('urunListesi').appendChild(row);
        }

        function sevkAc(id, kod) {
            document.getElementById('sevkSiparisId').value = id;
            document.getElementById('sevkBaslik').textContent = kod + ' Nolu Sipariş';

            // AJAX ile ürünleri çek
            fetch('siparis_ajax.php?islem=getir_sevk&id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('sevkUrunListesi').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('sevkModal')).show();
                });
        }

        function detayAc(id) {
            fetch('siparis_ajax.php?islem=getir_detay&id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('detayIcerik').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detayModal')).show();
                });
        }
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
