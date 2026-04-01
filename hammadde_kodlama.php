<?php
session_start();
include("baglan.php");
include("helper_functions.php");

oturumKontrol();

// Sayfa görüntüleme yetkisi
sayfaErisimKontrol($baglanti);

// Forma müdahale (yazma yetkisi) yoksa, PHP tarafında form işlemlerini engellemek için genel bir kontrol eklenebilir.
$yazma_yetkisi = yetkiKontrol($baglanti, 'Hammadde Kodlama', 'yazma');

// İşlem post edildiyse ve yetki yoksa:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$yazma_yetkisi) {
    die("Bu işlem için yazma yetkiniz bulunmamaktadır.");
}

$mesaj = "";
$hata = "";

// Hammadde Düzenleme
if (isset($_POST['duzenle_hammadde'])) {
    $hammadde_id = (int) $_POST['hammadde_id'];
    $ad = $baglanti->real_escape_string($_POST['ad']);
    $hammadde_kodu = strtoupper(trim($_POST['hammadde_kodu']));
    $yogunluk = (float) $_POST['yogunluk'];
    $aciklama = $baglanti->real_escape_string($_POST['aciklama'] ?? '');

    $kontrol = $baglanti->query("SELECT id FROM hammaddeler WHERE hammadde_kodu = '$hammadde_kodu' AND id != $hammadde_id");
    if ($kontrol && $kontrol->num_rows > 0) {
        $hata = "❌ Bu kod başka bir hammadde tarafından kullanılıyor!";
    } else {
        $sql = "UPDATE hammaddeler 
                SET ad = '$ad',
                    hammadde_kodu = '$hammadde_kodu',
                    yogunluk_kg_m3 = $yogunluk,
                    aciklama = '$aciklama'
                WHERE id = $hammadde_id";

        if ($baglanti->query($sql)) {
            $mesaj = "✅  Hammadde başarıyla güncellendi!";
            logKaydet($baglanti, 'hammadde_duzenle', 'hammaddeler', $hammadde_id, "Düzenlenen: $ad");
        } else {
            $hata = "❌ Güncelleme başarısız: " . $baglanti->error;
        }
    }
}

// Yeni hammadde ekleme
if (isset($_POST['yeni_hammadde'])) {
    $ad = $baglanti->real_escape_string($_POST['ad']);
    $hammadde_kodu = strtoupper(trim($_POST['hammadde_kodu']));
    $yogunluk = (float) $_POST['yogunluk'];
    $aciklama = $baglanti->real_escape_string($_POST['aciklama'] ?? '');

    // Kod benzersiz mi kontrol et
    $kontrol = $baglanti->query("SELECT id FROM hammaddeler WHERE hammadde_kodu = '$hammadde_kodu'");
    if ($kontrol && $kontrol->num_rows > 0) {
        $hata = "❌ Bu kod başka bir hammadde tarafından kullanılıyor!";
    } else {
        $sql = "INSERT INTO hammaddeler (ad, hammadde_kodu, yogunluk_kg_m3, aciklama) 
                VALUES ('$ad', '$hammadde_kodu', $yogunluk, '$aciklama')";

        if ($baglanti->query($sql)) {
            $mesaj = "✅ Yeni hammadde eklendi!";
            logKaydet($baglanti, 'hammadde_yeni', 'hammaddeler', $baglanti->insert_id, "Yeni hammadde: $ad, Kod: $hammadde_kodu");
        } else {
            $hata = "❌ Ekleme başarısız: " . $baglanti->error;
        }
    }
}

// Hammaddeyi tamamen silme
if (isset($_POST['hammadde_sil'])) {
    $hammadde_id = (int) $_POST['hammadde_id'];

    $sql = "DELETE FROM hammaddeler WHERE id = $hammadde_id";
    if ($baglanti->query($sql)) {
        $mesaj = "✅ Hammadde sistemden başarıyla silindi!";
        logKaydet($baglanti, 'hammadde_sil', 'hammaddeler', $hammadde_id, "Hammadde tamamen silindi");
    } else {
        // FK constraint hatası (1451) veya diğer hatalar varsa
        if ($baglanti->errno == 1451) {
            $sql_pasif = "UPDATE hammaddeler SET aktif = 0, hammadde_kodu = NULL WHERE id = $hammadde_id";
            if ($baglanti->query($sql_pasif)) {
                $mesaj = "✅ Hammadde geçmişte kullanıldığı için tamamen silinemedi, ancak pasif duruma getirildi ve erişime kapatıldı.";
                logKaydet($baglanti, 'hammadde_pasif', 'hammaddeler', $hammadde_id, "Kullanımda olan hammadde pasife alındı");
            }
        } else {
            $hata = "❌ Silme başarısız: " . $baglanti->error;
        }
    }
}

// Hammaddeleri çek
$hammaddeler = $baglanti->query("SELECT * FROM hammaddeler ORDER BY hammadde_kodu, ad");

// İstatistikler
$istatistik = $baglanti->query("SELECT 
    COUNT(*) as toplam,
    SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
    FROM hammaddeler")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hammadde Kodlama - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .kod-badge {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 8px 15px;
        }

        .hammadde-card {
            transition: transform 0.2s;
        }

        .hammadde-card:hover {
            transform: scale(1.02);
        }

        .cursor-pointer {
            cursor: pointer;
            user-select: none;
        }
        
        .cursor-pointer:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0"><i class="fas fa-barcode text-primary"></i> Hammadde Kodlama Sistemi</h2>
                <p class="text-muted mb-0">Hammadde index yönetimi ve silo kısıtlamaları</p>
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#yeniHammaddeModal">
                <i class="fas fa-plus"></i> Yeni Hammadde
            </button>
        </div>



        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body text-center">
                        <h2 class="mb-0">
                            <?php echo $istatistik['toplam'] ?? 0; ?>
                        </h2>
                        <small>Toplam Hammadde</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-success text-white">
                    <div class="card-body text-center">
                        <h2 class="mb-0">
                            <?php echo $istatistik['aktif'] ?? 0; ?>
                        </h2>
                        <small>Aktif Hammaddeler</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-secondary text-white">
                    <div class="card-body text-center">
                        <h2 class="mb-0">
                            <?php echo $istatistik['pasif'] ?? 0; ?>
                        </h2>
                        <small>Pasif Durumda</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hammadde Listesi -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3">
                <div class="fs-5">
                    <i class="fas fa-list text-primary"></i> Hammadde Listesi
                </div>
                
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <div class="input-group shadow-sm" style="max-width: 320px;">
                        <input type="text" id="aramaKutusu" class="form-control focus-ring focus-ring-primary" placeholder="Hammadde adı veya kod...">
                        <button class="btn btn-primary px-4" type="button" id="aramaButonu">
                            <i class="fas fa-search"></i> Ara
                        </button>
                    </div>
                    <select id="durumFiltresi" class="form-select shadow-sm focus-ring focus-ring-secondary" style="max-width: 160px;">
                        <option value="all">Tüm Durumlar</option>
                        <option value="active">Sadece Aktifler</option>
                        <option value="passive">Sadece Pasifler</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="cursor-pointer sortable" data-col="0">ID <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th class="cursor-pointer sortable" data-col="1">Hammadde Adı <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th class="cursor-pointer sortable" data-col="2">Hammadde Kodu <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th class="cursor-pointer sortable" data-col="3">Yoğunluk (kg/m³) <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th class="cursor-pointer sortable" data-col="4">Açıklama <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th class="cursor-pointer sortable" data-col="5">Durum <i class="fas fa-sort text-muted ms-1"></i></th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ham = $hammaddeler->fetch_assoc()) { ?>
                            <tr class="hammadde-card">
                                <td>
                                    <?php echo $ham['id']; ?>
                                </td>
                                <td><strong>
                                        <?php echo $ham['ad']; ?>
                                    </strong></td>
                                <td>
                                    <?php if ($ham['hammadde_kodu']) { ?>
                                        <span class="badge bg-success kod-badge">
                                            <?php echo $ham['hammadde_kodu']; ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo $ham['yogunluk_kg_m3'] ?? '780'; ?> kg/m³
                                </td>
                                <td><small class="text-muted">
                                        <?php echo $ham['aciklama'] ?? '-'; ?>
                                    </small></td>
                                <td>
                                    <?php if ($ham['aktif']) { ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Aktif</span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary"><i class="fas fa-times"></i> Pasif</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="duzenleModal(<?php echo $ham['id']; ?>, '<?php echo htmlspecialchars($ham['ad'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($ham['hammadde_kodu'], ENT_QUOTES); ?>', '<?php echo $ham['yogunluk_kg_m3']; ?>', '<?php echo htmlspecialchars($ham['aciklama'], ENT_QUOTES); ?>')"
                                            title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form id="silForm_<?php echo $ham['id']; ?>" method="post" class="d-inline">
                                            <input type="hidden" name="hammadde_id" value="<?php echo $ham['id']; ?>">
                                            <input type="hidden" name="hammadde_sil" value="1">
                                            <button type="button" class="btn btn-sm btn-danger"
                                                onclick="hammaddeSil(<?php echo $ham['id']; ?>)" title="Hammaddeyi Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kod Kullanım Örnekleri -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-info text-white fw-bold">
                <i class="fas fa-lightbulb"></i> Hammadde Kodlama Sistemi Hakkında
            </div>
            <div class="card-body">
                <h6 class="fw-bold">📌 Kod Formatı Önerileri:</h6>
                <div class="row">
                    <div class="col-md-4">
                        <code class="d-block bg-light p-2 mb-2">BG-EKSTRA</code>
                        <small>Ekstra kalite buğday</small>
                    </div>
                    <div class="col-md-4">
                        <code class="d-block bg-light p-2 mb-2">BG-MAKARNALIK</code>
                        <small>Makarnalık buğday</small>
                    </div>
                    <div class="col-md-4">
                        <code class="d-block bg-light p-2 mb-2">BG-SERT</code>
                        <small>Sert buğday</small>
                    </div>
                </div>

                <hr>

                <h6 class="fw-bold mt-3">🔒 Silo Kısıtlamaları:</h6>
                <p class="mb-0">
                    Hammadde kodları tanımladıktan sonra, <a href="silo_yonetimi.php">Silo Yönetimi</a> sayfasından
                    her siloya hangi hammaddelerin girebileceğini belirleyebilirsiniz. Bu sayede yanlış hammadde girişi
                    önlenir.
                </p>

                <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Önemli:</strong> Hammadde kodu siloya giriş yapıldıktan sonra değiştirilemez!
                    İzlenebilirlik için kodlar kalıcı olmalıdır.
                </div>
            </div>
        </div>

    </div>

    <!-- Hammadde Düzenle Modal -->
    <div class="modal fade" id="duzenleHammaddeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Hammadde Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="hammadde_id" id="duz_hammaddeId">

                        <div class="mb-3">
                            <label class="form-label">Hammadde Adı *</label>
                            <input type="text" name="ad" id="duz_hammaddeAdi" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Hammadde Kodu *</label>
                            <input type="text" name="hammadde_kodu" id="duz_hammaddeKodu" class="form-control" required
                                style="font-family: 'Courier New'; font-size: 1.2rem;">
                            <small class="text-muted">Büyük harf, tire ve rakam kullanabilirsiniz</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Yoğunluk (kg/m³) *</label>
                            <input type="number" name="yogunluk" id="duz_yogunluk" class="form-control" step="0.01"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="aciklama" id="duz_aciklama" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="duzenle_hammadde" class="btn btn-primary">
                            <i class="fas fa-save"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Yeni Hammadde Modal -->
    <div class="modal fade" id="yeniHammaddeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Yeni Hammadde Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Hammadde Adı *</label>
                            <input type="text" name="ad" class="form-control" placeholder="Örn: Ekstra Kalite Buğday"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Hammadde Kodu *</label>
                            <input type="text" name="hammadde_kodu" class="form-control" placeholder="Örn: BG-EKSTRA"
                                required style="font-family: 'Courier New'; font-size: 1.2rem;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Yoğunluk (kg/m³) *</label>
                            <input type="number" name="yogunluk" class="form-control" value="780" step="0.01" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="aciklama" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="yeni_hammadde" class="btn btn-success">
                            <i class="fas fa-plus"></i> Ekle
                        </button>
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

        const duzenleHammaddeModalEl = new bootstrap.Modal(document.getElementById('duzenleHammaddeModal'));

        function duzenleModal(id, ad, mevcut_kod, yogunluk, aciklama) {
            document.getElementById('duz_hammaddeId').value = id;
            document.getElementById('duz_hammaddeAdi').value = ad;
            document.getElementById('duz_hammaddeKodu').value = mevcut_kod;
            document.getElementById('duz_yogunluk').value = yogunluk || 780;
            document.getElementById('duz_aciklama').value = aciklama;

            duzenleHammaddeModalEl.show();
        }

        function hammaddeSil(id) {
            Swal.fire({
                title: 'Hammaddeyi tamamen silmek istediğinize emin misiniz?',
                text: 'Bu hammadde kalıcı olarak silinecektir. (Geçmiş işlemleri varsa pasife alınır)',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('silForm_' + id).submit();
                }
            });
        }

        // Otomatik büyük harf
        document.querySelectorAll('input[name="hammadde_kodu"]').forEach(function (input) {
            input.addEventListener('input', function (e) {
                e.target.value = e.target.value.toUpperCase();
            });
        });

        // Arama ve Filtreleme Mantığı
        document.addEventListener('DOMContentLoaded', function() {
            const aramaKutusu = document.getElementById('aramaKutusu');
            const aramaButonu = document.getElementById('aramaButonu');
            const durumFiltresi = document.getElementById('durumFiltresi');
            const tabloSatirlari = document.querySelectorAll('.hammadde-card');

            function filtrele() {
                const arananKelime = aramaKutusu.value.toLowerCase();
                const secilenDurum = durumFiltresi.value;

                tabloSatirlari.forEach(satir => {
                    const ad = satir.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const kod = satir.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const durumElementi = satir.querySelector('td:nth-child(6) span');
                    const isAktif = durumElementi.classList.contains('bg-success');
                    
                    const kelimeUyusuyor = ad.includes(arananKelime) || kod.includes(arananKelime);
                    let durumUyusuyor = true;

                    if (secilenDurum === 'active' && !isAktif) durumUyusuyor = false;
                    if (secilenDurum === 'passive' && isAktif) durumUyusuyor = false;

                    if (kelimeUyusuyor && durumUyusuyor) {
                        satir.style.display = '';
                    } else {
                        satir.style.display = 'none';
                    }
                });
            }

            aramaButonu.addEventListener('click', filtrele);
            aramaKutusu.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') filtrele();
            });
            durumFiltresi.addEventListener('change', filtrele);

            // Tablo Sıralama Mantığı
            document.querySelectorAll('th.sortable').forEach(function(th) {
                th.addEventListener('click', function() {
                    const table = th.closest('table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr.hammadde-card'));
                    const colIndex = parseInt(th.getAttribute('data-col'));
                    const isAsc = th.classList.contains('asc');
                    const isNumeric = colIndex === 0 || colIndex === 3;

                    // İkonları ve sınıfları sıfırla
                    table.querySelectorAll('th i.fas').forEach(i => {
                        i.className = 'fas fa-sort text-muted ms-1';
                    });
                    table.querySelectorAll('th').forEach(t => t.classList.remove('asc', 'desc'));

                    // Yeni yönü ayarla
                    th.classList.add(isAsc ? 'desc' : 'asc');
                    const icon = th.querySelector('i');
                    icon.className = isAsc ? 'fas fa-sort-down ms-1' : 'fas fa-sort-up ms-1';

                    // Sırala
                    rows.sort((a, b) => {
                        const aCol = a.querySelector(`td:nth-child(${colIndex + 1})`).textContent.trim();
                        const bCol = b.querySelector(`td:nth-child(${colIndex + 1})`).textContent.trim();

                        if (isNumeric) {
                            const aNum = parseFloat(aCol.replace(/[^\d.-]/g, '')) || 0;
                            const bNum = parseFloat(bCol.replace(/[^\d.-]/g, '')) || 0;
                            return isAsc ? bNum - aNum : aNum - bNum;
                        } else {
                            return isAsc ? bCol.localeCompare(aCol, 'tr') : aCol.localeCompare(bCol, 'tr');
                        }
                    });

                    // Tabloyu güncelleştir
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    </script>
</body>

</html>
