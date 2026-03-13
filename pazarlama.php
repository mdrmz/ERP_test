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

// YENİ SİPARİŞ OLUŞTUR (Pazarlama Ekibi)
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

                $urun_adi = $baglanti->real_escape_string($urun_adi);

                $baglanti->query("INSERT INTO siparis_detaylari (siparis_id, urun_adi, miktar, birim) 
                                   VALUES ($siparis_id, '$urun_adi', $miktar, '$birim')");
            }
        }

        // Log Modülü
        systemLogKaydet($baglanti, 'INSERT', 'Pazarlama', "Yeni sipariş girildi: $siparis_kodu");

        $mesaj = "✅ Sipariş başarıyla oluşturuldu: $siparis_kodu (Depo yöneticisine iletildi)";
    } else {
        $hata = "Sipariş oluşturulurken hata: " . $baglanti->error;
    }
}

// Gerekli verileri çek (Sadece form için, listeleme detayları gizli)
$musteriler = $baglanti->query("SELECT id, firma_adi, yetkili_kisi FROM musteriler ORDER BY firma_adi");
$urunler_list = $baglanti->query("SELECT urun_adi FROM urunler ORDER BY urun_adi");

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pazarlama & Satış - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .pazarlama-card {
            border-top: 4px solid #f5a623;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-5">

        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-bullhorn fa-3x text-warning me-3"></i>
                    <div>
                        <h2 class="mb-0">Pazarlama & Sipariş Girişi</h2>
                        <p class="text-muted mb-0">Sahadan aldığınız siparişleri hızlıca sisteme girin.</p>
                    </div>
                </div>



                <!-- Canlı Depo Stokları -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white py-2">
                        <h6 class="mb-0"><i class="fas fa-warehouse me-2"></i>Güncel Depo Stokları (Paketlenmiş
                            Çuvallar)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Ürün Adı</th>
                                        <th>Miktar</th>
                                        <th>Birim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Ürünleri stoklarla birlikte çek
                                    $stoklar = $baglanti->query("SELECT ds.*, u.urun_adi FROM depo_stok ds JOIN urunler u ON ds.urun_id = u.id");
                                    if ($stoklar && $stoklar->num_rows > 0) {
                                        while ($stok = $stoklar->fetch_assoc()) {
                                            if ($stok['stok_miktari'] > 0) {
                                                echo "<tr>
                                                        <td><strong class='text-primary'>{$stok['urun_adi']}</strong></td>
                                                        <td><span class='badge bg-success fs-6'>{$stok['stok_miktari']}</span></td>
                                                        <td>Adet / Çuval</td>
                                                      </tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-3 text-muted'>Şu an depoda hazır satışa uygun ürün bulunmuyor. Lütfen 'Depo & Sevkiyat' sekmesinden paketleme yapıldığından emin olun veya 'urunler' tablosuna ürünlerin tanımlandığını kontrol edin.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm pazarlama-card border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar text-muted me-2"></i>Yeni Müşteri
                            Siparişi form</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-bold">1. Müşteri Seçin *</label>
                                    <select name="musteri_id" class="form-select" required>
                                        <option value="">-- Müşteri Seçiniz --</option>
                                        <?php
                                        if ($musteriler && $musteriler->num_rows > 0) {
                                            $musteriler->data_seek(0);
                                            while ($m = $musteriler->fetch_assoc()) {
                                                echo "<option value='{$m['id']}'>{$m['firma_adi']} " . ($m['yetkili_kisi'] ? "({$m['yetkili_kisi']})" : "") . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="form-text text-muted">Listede müşteri yoksa İdari ekipten (veya
                                        Müşteriler sayfasından) eklenmesini isteyin.</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Sipariş Tarihi *</label>
                                    <input type="date" name="siparis_tarihi" class="form-control"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">İstenen Teslim *</label>
                                    <input type="date" name="teslim_tarihi" class="form-control"
                                        value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                                </div>
                            </div>

                            <hr class="bg-light my-4">

                            <h6 class="fw-bold mb-3">2. Sipariş Kalemleri (Ürünler)</h6>
                            <div class="alert alert-secondary py-2 small">
                                <i class="fas fa-info-circle me-1"></i> Fiyat bilgisi girilmez, idari ekip anlaşmalı
                                fiyatlar üzerinden faturayı oluşturur.
                            </div>

                            <div id="urunListesi">
                                <div class="row mb-2 align-items-end urun-satiri">
                                    <div class="col-md-6 mb-2 mb-md-0">
                                        <label class="form-label small text-muted">Ürün</label>
                                        <select name="urunler[]" class="form-select" required>
                                            <option value="">Seç...</option>
                                            <?php
                                            if ($urunler_list && $urunler_list->num_rows > 0) {
                                                $urunler_list->data_seek(0);
                                                while ($u = $urunler_list->fetch_assoc()) {
                                                    echo "<option value='{$u['urun_adi']}'>{$u['urun_adi']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2 mb-md-0">
                                        <label class="form-label small text-muted">Miktar</label>
                                        <input type="number" name="miktarlar[]" class="form-control"
                                            placeholder="Örn: 50" required min="1">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">Birim</label>
                                        <select name="birimler[]" class="form-select">
                                            <option value="Adet">Çuval / Adet</option>
                                            <option value="Kg">Kg</option>
                                            <option value="Ton">Ton</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="satirEkle()">
                                    <i class="fas fa-plus me-1"></i> Yeni Satır Ekle
                                </button>
                            </div>

                            <hr class="bg-light my-4">

                            <div class="mb-4">
                                <label class="form-label fw-bold">3. Özel Notlar & Açıklama (Opsiyonel)</label>
                                <textarea name="aciklama" class="form-control" rows="3"
                                    placeholder="Teslimat adresi farklıysa, acele edilecekse vb. notlar..."></textarea>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" name="siparis_olustur"
                                    class="btn btn-warning btn-lg fw-bold text-dark">
                                    <i class="fas fa-paper-plane me-2"></i> SİPARİŞİ MERKEZE İLET
                                </button>
                            </div>
                        </form>
                    </div>
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

        function satirEkle() {
            const rows = document.querySelectorAll('#urunListesi .urun-satiri');
            if (rows.length > 0) {
                const row = rows[0].cloneNode(true);
                row.querySelectorAll('input').forEach(i => i.value = '');
                // Gerekirse selectbox value'sunu da resetle: row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
                document.getElementById('urunListesi').appendChild(row);
            }
        }
    </script>
    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
