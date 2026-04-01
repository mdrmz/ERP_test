<?php
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

// --- MIGRATION (OTOMATİK) ---
// Not: Normalde bu ayrı dosyada olur ama pratiklik için buraya ekliyoruz
$check = $baglanti->query("SHOW COLUMNS FROM paketleme_hareketleri LIKE 'uretim_parti_no'");
if ($check->num_rows == 0) {
    $baglanti->query("ALTER TABLE paketleme_hareketleri ADD COLUMN uretim_parti_no VARCHAR(50) DEFAULT NULL COMMENT 'Hangi üretim partisinden paketlendi' AFTER urun_id");
    $baglanti->query("ALTER TABLE paketleme_hareketleri ADD INDEX idx_uretim_parti (uretim_parti_no)");
}
$check2 = $baglanti->query("SHOW COLUMNS FROM sevkiyatlar LIKE 'parti_no'");
if ($check2 && $check2->num_rows == 0) {
    $baglanti->query("ALTER TABLE sevkiyatlar ADD COLUMN parti_no VARCHAR(50) DEFAULT NULL COMMENT 'Sevk edilen paket parti no' AFTER id");
}

// --- İŞLEM 1: PAKETLEME (SİLODAN STOĞA) ---
if (isset($_POST["paketleme_yap"])) {
    $silo_id = $_POST["silo_id"];
    $urun_id = $_POST["urun_id"];
    $uretim_parti_no = $_POST["uretim_parti_no"];
    $parti_no = $uretim_parti_no; // Üretim parti numarası paketleme partisi olarak kullanılıyor

    // ** YENİ: Duplicate kontrol - Bu parti daha önce paketlendi mi? **
    $paketleme_kontrol = $baglanti->query("SELECT id FROM paketleme_hareketleri WHERE uretim_parti_no='$uretim_parti_no'");
    if ($paketleme_kontrol->num_rows > 0) {
        $hata = "⚠️ HATA: Bu üretim partisi daha önce paketlendi! Aynı partiyi tekrar paketleyemezsiniz.";
    } else {
        // Üretim partisinden kg bilgisini çek
        $uretim_bilgi = $baglanti->query("SELECT uretilen_miktar_kg FROM uretim_hareketleri WHERE parti_no='$uretim_parti_no'")->fetch_assoc();

        if (!$uretim_bilgi) {
            $hata = "HATA: Seçilen üretim partisi bulunamadı!";
        } else {
            $uretilen_kg = $uretim_bilgi["uretilen_miktar_kg"];

            // Bilgileri Çek
            $silo = $baglanti->query("SELECT * FROM silolar WHERE id=$silo_id")->fetch_assoc();
            $urun = $baglanti->query("SELECT * FROM urunler WHERE id=$urun_id")->fetch_assoc();

            // ** YENİ: Üretilen kg'dan çuval sayısını hesapla **
            $cuval_agirlik = 50; // kg
            $adet = ceil($uretilen_kg / $cuval_agirlik); // Yukarı yuvarla
            $toplam_kg = $uretilen_kg; // Gerçek üretilen miktar
            $dusulecek_m3 = $toplam_kg / 550; // Un yoğunluğu (yaklaşık)

            if ($silo["doluluk_m3"] < $dusulecek_m3) {
                $hata = "HATA: Siloda yeterli un yok! (Gerekli: " . number_format($dusulecek_m3, 1) . " m³, Mevcut: " . number_format($silo["doluluk_m3"], 1) . " m³)";
            } else {
                // Eğer hata yoksa devam et
                if (empty($hata)) {
                    // 1. Silodan Düş
                    $yeni_silo_m3 = $silo["doluluk_m3"] - $dusulecek_m3;
                    $baglanti->query("UPDATE silolar SET doluluk_m3 = $yeni_silo_m3 WHERE id=$silo_id");

                    // 2. Stoğa Ekle (Varsa güncelle, yoksa ekle)
                    $stok_kontrol = $baglanti->query("SELECT * FROM depo_stok WHERE urun_id=$urun_id");
                    if ($stok_kontrol->num_rows > 0) {
                        $baglanti->query("UPDATE depo_stok SET miktar = miktar + $adet WHERE urun_id=$urun_id");
                    } else {
                        $baglanti->query("INSERT INTO depo_stok (urun_id, depo_id, miktar) VALUES ($urun_id, 1, $adet)");
                    }

                    // 3. Geçmişe Kaydet
                    $baglanti->query("INSERT INTO paketleme_hareketleri (parti_no, urun_id, miktar, uretim_parti_no, personel)
                                      VALUES ('$parti_no', $urun_id, $adet, '$uretim_parti_no', '{$_SESSION["kadi"]}')");

                    $mesaj = "✅ {$toplam_kg} kg → {$adet} çuval paketlendi ve stoğa girdi. Silo: -{$dusulecek_m3} m³";
                }
            }
        }
    } // else - duplicate check
}

// --- İŞLEM 2: YENİ SEVKİYAT RANDEVUSU ---
if (isset($_POST["randevu_olustur"])) {
    $musteri = $_POST["musteri"];
    $tarih = $_POST["tarih"] . " " . $_POST["saat"];
    $plaka = $_POST["plaka"];

    $baglanti->query("INSERT INTO sevkiyat_randevulari (musteri_adi, randevu_tarihi, arac_plaka, durum) VALUES ('$musteri', '$tarih', '$plaka', 'bekliyor')");
    $mesaj = "✅ Randevu oluşturuldu.";
}

// --- İŞLEM 3: SEVKİYATI TAMAMLA (STOKTAN DÜŞ) ---
if (isset($_POST["sevkiyat_bitir"])) {
    $randevu_id = $_POST["randevu_id"];
    $sevk_parti_no = $_POST["sevk_parti_no"];
    $sevk_miktari = $_POST["sevk_miktari"]; // Adet (çuval)

    if (empty($sevk_parti_no) || empty($sevk_miktari)) {
        $hata = "HATA: Parti numarası ve miktar seçilmelidir!";
    } else {
        // Randevu bilgilerini çek
        $randevu = $baglanti->query("SELECT * FROM sevkiyat_randevulari WHERE id=$randevu_id")->fetch_assoc();

        // Stoktan düş (Miktar kontrolü yapılabilir ama şimdilik doğrudan düşüyoruz)
        // Hangi ürün olduğunu paketleme partisinden bulmamız lazım
        $paket = $baglanti->query("SELECT * FROM paketleme_hareketleri WHERE parti_no = '$sevk_parti_no'")->fetch_assoc();

        if ($paket) {
            $urun_id = $paket["urun_id"];

            // Stok güncelle
            $baglanti->query("UPDATE depo_stok SET miktar = miktar - $sevk_miktari WHERE urun_id=$urun_id");

            // Sevkiyat kaydı oluştur
            $baglanti->query("INSERT INTO sevkiyatlar (parti_no, musteri_adi, sevk_tarihi, sevk_miktari, birim, arac_plaka, sevk_eden_user_id)
                              VALUES ('$sevk_parti_no', '{$randevu["musteri_adi"]}', NOW(), $sevk_miktari, 'adet', '{$randevu["arac_plaka"]}', {$_SESSION["user_id"]})");

            // Randevu güncelle
            $baglanti->query("UPDATE sevkiyat_randevulari SET durum='tamamlandi', onay_durum='tamamlandi' WHERE id=$randevu_id");

            // Bildirim
            bildirimOlustur($baglanti, 'sevkiyat_tamam', 'Sevkiyat Çıktı', "{$randevu["musteri_adi"]} sevkiyatı tamamlandı. Parti: $sevk_parti_no", 3); // 3: İdari Sevkiyat

            $mesaj = "✅ Sevkiyat işlemi başarıyla kaydedildi ve stoktan düşüldü.";
        } else {
            $hata = "HATA: Seçilen parti numarası ($sevk_parti_no) sistemde bulunamadı!";
        }
    }
}

// LİSTELER (Güvenli Sorgular)

// 1. Silolar: 'tip' sütunu olmayabilir, genel çekiyoruz.
$un_silolari = $baglanti->query("SELECT * FROM silolar");
if (!$un_silolari) {
    // Eğer tablo yoksa veya sorgu hatalıysa
    $un_silolari = false;
}

// 2. Ürünler
$urunler = $baglanti->query("SELECT * FROM urunler");

// 3. Stoklar
$stoklar = $baglanti->query("SELECT ds.*, u.urun_adi FROM depo_stok ds JOIN urunler u ON ds.urun_id = u.id");

// 4. Randevular: Tablo olmayabilir!
$randevular = $baglanti->query("SELECT * FROM sevkiyat_randevulari WHERE durum != 'tamamlandi' ORDER BY randevu_tarihi ASC");
if (!$randevular) {
    // Tablo yoksa false döner, bu durumda boş sonuç gibi davranacağız
    $randevular = false;
}

// 5. Üretim Partileri (Tekrar ekleniyor)
$son_uretimler = $baglanti->query("
    SELECT uh.parti_no, uh.uretilen_miktar_kg, uh.tarih 
    FROM uretim_hareketleri uh 
    WHERE uh.parti_no NOT IN (SELECT uretim_parti_no FROM paketleme_hareketleri WHERE uretim_parti_no IS NOT NULL)
    ORDER BY uh.tarih DESC LIMIT 20
");
if (!$son_uretimler) {
    $son_uretimler = false;
}

// 6. Paketleme Partileri
$paket_partileri = $baglanti->query("SELECT ph.*, u.urun_adi FROM paketleme_hareketleri ph JOIN urunler u ON ph.urun_id = u.id ORDER BY ph.tarih DESC LIMIT 50");
if (!$paket_partileri) {
    $paket_partileri = false;
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depo & Sevkiyat - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .nav-tabs .nav-link.active {
            background-color: #1c2331;
            color: #fff;
            border-color: #1c2331;
        }

        .nav-tabs .nav-link {
            color: #555;
            font-weight: 600;
        }

        .stok-kutusu {
            border-left: 5px solid #28a745;
            background: #fff;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">



        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="paket-tab" data-bs-toggle="tab" data-bs-target="#paketleme"
                    type="button"><i class="fas fa-box-open"></i> Paketleme & Üretim</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="stok-tab" data-bs-toggle="tab" data-bs-target="#stoklar" type="button"><i
                        class="fas fa-warehouse"></i> Depo Stokları</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="sevk-tab" data-bs-toggle="tab" data-bs-target="#sevkiyat" type="button"><i
                        class="fas fa-truck"></i> Sevkiyat Planı</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">

            <div class="tab-pane fade show active" id="paketleme">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-dark text-white">Yeni Paketleme Emri</div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label>Parti No</label>
                                        <select name="uretim_parti_no" class="form-select" required>
                                            <option value="">Seçiniz...</option>
                                            <?php
                                            if ($son_uretimler && $son_uretimler->num_rows > 0) {
                                                while ($u = $son_uretimler->fetch_assoc()) {
                                                    // Hata önlemek için sadece parti no ve kg gösteriyoruz
                                                    echo "<option value='{$u["parti_no"]}'>{$u["parti_no"]} ({$u["uretilen_miktar_kg"]} kg)</option>";
                                                }
                                            } else {
                                                echo "<option value='' disabled>Paketlenecek yeni üretim yok</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label>Kaynak Un Silosu</label>
                                        <select name="silo_id" class="form-select" required>
                                            <?php
                                            if ($un_silolari && $un_silolari->num_rows > 0) {
                                                $un_silolari->data_seek(0);
                                                while ($s = $un_silolari->fetch_assoc()) {
                                                    // KG Hesabı: m3 * 550 (yaklaşık un yoğunluğu)
                                                    $kg = round($s["doluluk_m3"] * 550);
                                                    echo "<option value='{$s["id"]}'>{$s["silo_adi"]} (Dolu: {$s["doluluk_m3"]} m³ / " . number_format($kg, 0, ',', '.') . " kg)</option>";
                                                }
                                            } else {
                                                echo "<option value=''>Silo bulunamadı</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label>Üretilecek Ürün (Çuval)</label>
                                        <select name="urun_id" class="form-select" required>
                                            <?php
                                            if ($urunler && $urunler->num_rows > 0) {
                                                $urunler->data_seek(0);
                                                while ($u = $urunler->fetch_assoc()) {
                                                    echo "<option value='{$u["id"]}'>{$u["urun_adi"]}</option>";
                                                }
                                            } else {
                                                echo "<option value=''>Ürün bulunamadı</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> <strong>Otomatik Hesaplama:</strong><br>
                                        Seçilen üretim partisindeki kg miktarı otomatik olarak çuval sayısına
                                        dönüştürülecek (50kg/çuval).
                                    </div>
                                    <button type="submit" name="paketleme_yap" class="btn btn-success w-100"><i
                                            class="fas fa-check"></i> Üret ve Stoğa Ekle</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Nasıl Çalışır?</h5>
                            <p>Burada "Üret" dediğinizde:</p>
                            <ol>
                                <li><strong>Parti No</strong> seçildiğinde, o partide üretilen <strong>kg
                                        miktarı</strong> otomatik alınır.</li>
                                <li>Sistem bu kg'ı <strong>çuval sayısına çevirir</strong> (50kg/çuval).</li>
                                <li>Seçilen <strong>un silosundan</strong> hesaplanan m³ düşer.</li>
                                <li>Çuval adedi <strong>Depo Stoklarına</strong> eklenir.</li>
                                <li>İşlem kayıt altına alınır.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="stoklar">
                <div class="row">
                    <?php if ($stoklar->num_rows > 0) {
                        while ($stok = $stoklar->fetch_assoc()) { ?>
                            <div class="col-md-4">
                                <div class="stok-kutusu d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo $stok["urun_adi"]; ?></h5>
                                        <small class="text-muted">Depo ID: <?php echo $stok["depo_id"]; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <h3 class="mb-0 text-success"><?php echo $stok["miktar"]; ?></h3>
                                        <small>Adet</small>
                                    </div>
                                </div>
                            </div>
                        <?php }
                    } else {
                        echo "<div class='alert alert-warning'>Depoda hiç ürün yok. Lütfen paketleme yapın.</div>";
                    } ?>
                </div>
            </div>

            <div class="tab-pane fade" id="sevkiyat">
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body bg-warning bg-opacity-10">
                        <form method="post" class="row align-items-end">
                            <div class="col-md-3">
                                <label>Müşteri Adı</label>
                                <input type="text" name="musteri" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label>Plaka</label>
                                <input type="text" name="plaka" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label>Tarih</label>
                                <input type="date" name="tarih" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label>Saat</label>
                                <input type="time" name="saat" class="form-control" value="09:00" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="randevu_olustur" class="btn btn-warning w-100">Randevu
                                    Ver</button>
                            </div>
                        </form>
                    </div>
                </div>

                <table class="table table-hover bg-white shadow-sm rounded">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih/Saat</th>
                            <th>Müşteri</th>
                            <th>Plaka</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($randevular && $randevular->num_rows > 0) {
                            while ($randevu = $randevular->fetch_assoc()) {
                                $durum_renk = ($randevu["durum"] == 'bekliyor') ? 'warning' : 'success';
                                ?>
                                <tr>
                                    <td><?php echo date("d.m.Y H:i", strtotime($randevu["randevu_tarihi"])); ?></td>
                                    <td><?php echo $randevu["musteri_adi"]; ?></td>
                                    <td><span class="badge bg-dark"><?php echo $randevu["arac_plaka"]; ?></span></td>
                                    <td><span
                                            class="badge bg-<?php echo $durum_renk; ?>"><?php echo strtoupper($randevu["durum"]); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($randevu["durum"] == 'bekliyor' || $randevu["durum"] == 'yukleniyor') { ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal"
                                                data-bs-target="#sevkiyatModal"
                                                onclick="sevkiyatBilgisiDoldur(<?php echo $randevu['id']; ?>, '<?php echo $randevu['musteri_adi']; ?>', '<?php echo $randevu['arac_plaka']; ?>')">
                                                <i class="fas fa-check"></i> Sevk Et
                                            </button>
                                        <?php } else {
                                            echo '<i class="fas fa-check-circle text-success"></i> Tamamlandı';
                                        } ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center text-muted'>Kayıtlı randevu bulunamadı veya tablo eksik.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Sevkiyat Modal -->
    <div class="modal fade" id="sevkiyatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sevkiyatı Tamamla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="randevu_id" id="modal_randevu_id">

                        <div class="mb-3">
                            <label>Müşteri</label>
                            <input type="text" class="form-control" id="modal_musteri" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Araç Plaka</label>
                            <input type="text" class="form-control" id="modal_plaka" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Sevk Edilecek Parti (Paket)</label>
                            <select name="sevk_parti_no" class="form-select" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                if ($paket_partileri && $paket_partileri->num_rows > 0) {
                                    $paket_partileri->data_seek(0);
                                    while ($p = $paket_partileri->fetch_assoc()) {
                                        echo "<option value='{$p['parti_no']}'>{$p['parti_no']} - {$p['urun_adi']} ({$p['miktar']} adet)</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Sevk Miktarı (Adet/Çuval)</label>
                            <input type="number" name="sevk_miktari" class="form-control" required>
                        </div>

                        <button type="submit" name="sevkiyat_bitir" class="btn btn-success w-100">ONAYLA ve ÇIKIŞ
                            YAP</button>
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

        function sevkiyatBilgisiDoldur(id, musteri, plaka) {
            document.getElementById('modal_randevu_id').value = id;
            document.getElementById('modal_musteri').value = musteri;
            document.getElementById('modal_plaka').value = plaka;
        }
    </script>
    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
