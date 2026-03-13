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

// --- 1. YENİ REÇETE EKLEME İŞLEMİ ---
if (isset($_POST["recete_ekle"])) {
    $ad = $baglanti->real_escape_string($_POST["recete_adi"]);
    $tav = $_POST["tav_miktar"] ?: 0;
    $sure = $_POST["sure_saat"] ?: 0;
    $isi = $_POST["sicaklik"] ?: 0;
    $nem = $_POST["hedef_nem"] ?: 0;
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"] ?? '');

    $sql = "INSERT INTO receteler (recete_adi, tav_miktar, sure_saat, sicaklik, hedef_nem, aciklama) 
            VALUES ('$ad', $tav, $sure, $isi, $nem, '$aciklama')";

    if ($baglanti->query($sql)) {
        header("Location: planlama.php?msg=recete_ok&ad=" . urlencode($ad));
        exit;
    } else {
        $hata = "Hata oluştu: " . $baglanti->error;
    }
}

// Success messages from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'recete_ok') {
        $mesaj = "✅ Yeni reçete başarıyla tanımlandı: " . htmlspecialchars($_GET['ad'] ?? '');
    }
    if ($_GET['msg'] == 'emir_ok') {
        $mesaj = "✅ İş emri yayınlandı! Kod: <b>" . htmlspecialchars($_GET['kod'] ?? '') . "</b>";
    }
}

// --- 2. İŞ EMRİ OLUŞTURMA İŞLEMİ ---
if (isset($_POST["is_emri_ver"])) {
    $recete_id = (int) $_POST["recete_id"];
    $hedef_ton = $_POST["hedef_miktar"] ?: 0;
    $personel = $baglanti->real_escape_string($_POST["atanan_personel"] ?? '');
    $yikama_parti_no = $baglanti->real_escape_string($_POST["yikama_parti_no"] ?? '');

    // Silo yüzdeleri kontrolü
    $silo_ids = $_POST["silo_id"] ?? [];
    $silo_yuzdeleri = $_POST["silo_yuzde"] ?? [];

    // Yüzde toplamı kontrolü
    $toplam_yuzde = 0;
    $gecerli_silolar = [];
    for ($i = 0; $i < count($silo_ids); $i++) {
        if (!empty($silo_ids[$i]) && !empty($silo_yuzdeleri[$i])) {
            $toplam_yuzde += (float) $silo_yuzdeleri[$i];
            $gecerli_silolar[] = [
                'silo_id' => (int) $silo_ids[$i],
                'yuzde' => (float) $silo_yuzdeleri[$i]
            ];
        }
    }

    if (empty($gecerli_silolar)) {
        $hata = "En az bir silo seçmelisiniz!";
    } elseif (abs($toplam_yuzde - 100) > 0.1) {
        $hata = "Silo yüzdelerinin toplamı %100 olmalı! (Şu an: %" . number_format($toplam_yuzde, 1) . ")";
    } else {
        // Otomatik İş Kodu Üret (Örn: URT-8423)
        $kod = "URT-" . rand(1000, 9999);

        $sql = "INSERT INTO is_emirleri (is_kodu, recete_id, yikama_parti_no, hedef_miktar_ton, baslangic_tarihi, durum, atanan_personel) 
                VALUES ('$kod', $recete_id, ";
        $sql .= $yikama_parti_no ? "'$yikama_parti_no'" : "NULL";
        $sql .= ", $hedef_ton, NOW(), 'bekliyor', '$personel')";

        if ($baglanti->query($sql)) {
            $yeni_is_emri_id = $baglanti->insert_id;

            // Silo karışımlarını kaydet
            foreach ($gecerli_silolar as $silo) {
                $silo_id = $silo['silo_id'];
                $yuzde = $silo['yuzde'];
                $sql_silo = "INSERT INTO is_emri_silo_karisimlari (is_emri_id, silo_id, yuzde) 
                             VALUES ($yeni_is_emri_id, $silo_id, $yuzde)";
                $baglanti->query($sql_silo);
            }

            // === ONAY SİSTEMİ - Patron onayı gerekli ===
            // Reçete adını al
            $recete_result = $baglanti->query("SELECT recete_adi FROM receteler WHERE id = $recete_id");
            $recete_adi = ($recete_result && $r = $recete_result->fetch_assoc()) ? $r['recete_adi'] : 'Bilinmeyen Reçete';

            onayOlustur(
                $baglanti,
                'is_emri',
                $yeni_is_emri_id,
                "İş Emri: $kod | Reçete: $recete_adi | Hedef: {$hedef_ton} ton"
            );

            // === BİLDİRİM - Patron'a bildirim gönder ===
            bildirimOlustur(
                $baglanti,
                'onay_bekleniyor',
                "Yeni İş Emri Onay Bekliyor: $kod",
                "Reçete: $recete_adi | Hedef: {$hedef_ton} ton | Personel: $personel",
                1, // Patron rol_id
                null,
                'is_emirleri',
                $yeni_is_emri_id,
                'onay_merkezi.php'
            );

            // === SYSTEM LOG KAYDI ===
            systemLogKaydet(
                $baglanti,
                'INSERT',
                'Planlama',
                "Yeni iş emri oluşturuldu: $kod | Reçete: $recete_adi | Hedef: {$hedef_ton} ton"
            );

            header("Location: planlama.php?msg=emir_ok&kod=" . urlencode($kod));
            exit;
        } else {
            $hata = "Hata: " . $baglanti->error;
        }
    }
}

// LİSTELERİ ÇEK
$receteler = $baglanti->query("SELECT * FROM receteler ORDER BY id DESC");

// Buğday silolarını çek
$bugday_silolari = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' AND durum='aktif' ORDER BY silo_adi");
$bugday_silolari_arr = [];
while ($s = $bugday_silolari->fetch_assoc()) {
    $bugday_silolari_arr[] = $s;
}

// Yıkama partilerini getir
$yikama_partileri = $baglanti->query("
    SELECT parti_no, yikama_tarihi, urun_adi
    FROM yikama_kayitlari
    WHERE parti_no IS NOT NULL AND parti_no != ''
    ORDER BY yikama_tarihi DESC
    LIMIT 50
");

// Aktif iş emirleri (silo karışımı ile birlikte)
$aktif_emirler = $baglanti->query("
    SELECT ie.*, r.recete_adi,
           GROUP_CONCAT(CONCAT(s.silo_adi, ':', isk.yuzde, '%') SEPARATOR ', ') as silo_karisimi
    FROM is_emirleri ie 
    JOIN receteler r ON ie.recete_id = r.id 
    LEFT JOIN is_emri_silo_karisimlari isk ON ie.id = isk.is_emri_id
    LEFT JOIN silolar s ON isk.silo_id = s.id
    GROUP BY ie.id
    ORDER BY ie.id DESC
");
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üretim Planlama - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-check text-primary"></i> Üretim Planlama</h2>
            <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#yeniReceteModal">
                <i class="fas fa-scroll"></i> Yeni Reçete Tanımla
            </button>
        </div>



        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bullhorn"></i> İş Emri Oluştur</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label text-muted">Üretilecek Reçete</label>
                                <select name="recete_id" class="form-select" required>
                                    <option value="">Seçiniz...</option>
                                    <?php
                                    // Reçete listesini döngüye al, tekrar kullanmak için başa saracağız
                                    $receteler->data_seek(0);
                                    while ($r = $receteler->fetch_assoc()) {
                                        echo "<option value='{$r["id"]}'>{$r["recete_adi"]}</option>";
                                    }
                                    ?>
                                </select>
                                <div class="form-text">Listede yoksa sağ üstten ekleyin.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Hedef Miktar (Ton)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="hedef_miktar" class="form-control"
                                        placeholder="Örn: 50" required>
                                    <span class="input-group-text">Ton</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">
                                    <i class="fas fa-link"></i> Yıkama Parti No (Opsiyonel)
                                </label>
                                <select name="yikama_parti_no" class="form-select">
                                    <option value="">-- Belirtilmedi --</option>
                                    <?php
                                    if ($yikama_partileri && $yikama_partileri->num_rows > 0) {
                                        while ($yp = $yikama_partileri->fetch_assoc()) {
                                            ?>
                                            <option value="<?php echo htmlspecialchars($yp["parti_no"]); ?>">
                                                <?php echo htmlspecialchars($yp["parti_no"]) . " - " . htmlspecialchars($yp["urun_adi"] ?? "Bilinmiyor") . " (" . date("d.m.Y", strtotime($yp["yikama_tarihi"])) . ")"; ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="form-text">Hangi yıkama partisinden üretim yapılacak?</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Sorumlu Personel/Vardiya</label>
                                <input type="text" name="atanan_personel" class="form-control"
                                    placeholder="Örn: Ahmet Usta / A Vardiyası">
                            </div>

                            <!-- Silo Karışımı Bölümü -->
                            <div class="mb-3">
                                <label class="form-label text-muted">
                                    <i class="fas fa-database"></i> Buğday Silo Karışımı (Paçal)
                                </label>
                                <div id="siloKarisimContainer">
                                    <div class="silo-row d-flex align-items-center gap-2 mb-2">
                                        <select name="silo_id[]" class="form-select silo-select" style="width: 60%;">
                                            <option value="">Silo Seç...</option>
                                            <?php foreach ($bugday_silolari_arr as $s) {
                                                $doluluk = round(($s['doluluk_m3'] / $s['kapasite_m3']) * 100);
                                                ?>
                                                <option value="<?= $s['id'] ?>">
                                                    <?= $s['silo_adi'] ?> (<?= $doluluk ?>% dolu)
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="silo_yuzde[]" class="form-control silo-yuzde"
                                            placeholder="%" min="0" max="100" step="0.1" style="width: 80px;">
                                        <span class="text-muted">%</span>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-silo-sil"
                                            disabled>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnSiloEkle">
                                    <i class="fas fa-plus"></i> Silo Ekle
                                </button>
                                <div class="form-text">
                                    Toplam: <span id="toplamYuzde" class="fw-bold">0</span>%
                                    <span id="yuzdeUyari" class="text-danger d-none">(Toplam %100 olmalı!)</span>
                                </div>
                            </div>

                            <button type="submit" name="is_emri_ver" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> Emri Yayınla
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-secondary">Yayındaki İş Emirleri</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>İş Kodu</th>
                                    <th>Reçete</th>
                                    <th>Hedef</th>
                                    <th>Silo Karışımı</th>
                                    <th>Durum</th>
                                    <th>Sorumlu</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($aktif_emirler->num_rows > 0) {
                                    while ($is = $aktif_emirler->fetch_assoc()) {
                                        $renk = ($is["durum"] == 'bekliyor') ? 'bg-warning text-dark' : 'bg-primary';
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-dark"><?php echo $is["is_kodu"]; ?></span></td>
                                            <td class="fw-bold"><?php echo $is["recete_adi"]; ?></td>
                                            <td><?php echo $is["hedef_miktar_ton"]; ?> Ton</td>
                                            <td class="small">
                                                <?php echo $is["silo_karisimi"] ?: '<span class="text-muted">-</span>'; ?>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo $renk; ?>"><?php echo strtoupper($is["durum"]); ?></span>
                                            </td>
                                            <td class="small text-muted"><?php echo $is["atanan_personel"]; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary"><i
                                                        class="fas fa-print"></i></button>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="6" class="text-center p-4 text-muted">Aktif iş emri bulunmuyor.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="yeniReceteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Yeni Üretim Reçetesi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="mb-3">
                            <label>Reçete Adı</label>
                            <input type="text" name="recete_adi" class="form-control"
                                placeholder="Örn: Lüks Ekmeklik Un" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label>Tav Miktarı (lt)</label>
                                <input type="number" step="0.1" name="tav_miktar" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label>Süre (Saat)</label>
                                <input type="number" name="sure_saat" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label>Sıcaklık (°C)</label>
                                <input type="number" step="0.1" name="sicaklik" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label>Hedef Nem (%)</label>
                                <input type="number" step="0.1" name="hedef_nem" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Teknik Açıklama / Analiz Spektleri</label>
                            <textarea name="aciklama" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="recete_ekle" class="btn btn-success">Kaydet ve Listeye
                                Ekle</button>
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

            const container = document.getElementById('siloKarisimContainer');
            const btnEkle = document.getElementById('btnSiloEkle');
            const toplamSpan = document.getElementById('toplamYuzde');
            const uyariSpan = document.getElementById('yuzdeUyari');

            // Silo seçenekleri template
            const siloOptions = `<?php
            $opts = '<option value="">Silo Seç...</option>';
            foreach ($bugday_silolari_arr as $s) {
                $doluluk = round(($s['doluluk_m3'] / $s['kapasite_m3']) * 100);
                $opts .= '<option value="' . $s['id'] . '">' . $s['silo_adi'] . ' (' . $doluluk . '% dolu)</option>';
            }
            echo addslashes($opts);
            ?>`;

            // Yeni silo satırı ekle
            btnEkle.addEventListener('click', function () {
                const row = document.createElement('div');
                row.className = 'silo-row d-flex align-items-center gap-2 mb-2';
                row.innerHTML = `
                <select name="silo_id[]" class="form-select silo-select" style="width: 60%;">
                    ${siloOptions}
                </select>
                <input type="number" name="silo_yuzde[]" class="form-control silo-yuzde" 
                       placeholder="%" min="0" max="100" step="0.1" style="width: 80px;">
                <span class="text-muted">%</span>
                <button type="button" class="btn btn-outline-danger btn-sm btn-silo-sil">
                    <i class="fas fa-times"></i>
                </button>
            `;
                container.appendChild(row);
                updateSilButtons();
                hesaplaToplamYuzde();
            });

            // Silo silme
            container.addEventListener('click', function (e) {
                if (e.target.closest('.btn-silo-sil')) {
                    const rows = container.querySelectorAll('.silo-row');
                    if (rows.length > 1) {
                        e.target.closest('.silo-row').remove();
                        updateSilButtons();
                        hesaplaToplamYuzde();
                    }
                }
            });

            // Yüzde hesaplama ve silo seçim kontrolü
            container.addEventListener('input', function (e) {
                if (e.target.classList.contains('silo-yuzde')) {
                    // Maksimum 100 kontrolü
                    let val = parseFloat(e.target.value) || 0;
                    if (val > 100) {
                        e.target.value = 100;
                    }
                    if (val < 0) {
                        e.target.value = 0;
                    }
                    hesaplaToplamYuzde();
                }
            });

            // Silo seçildiğinde diğer dropdownlardan kaldır
            container.addEventListener('change', function (e) {
                if (e.target.classList.contains('silo-select')) {
                    updateSeciliSilolar();
                }
            });

            function updateSeciliSilolar() {
                // Tüm seçili siloları topla
                const siloSelects = container.querySelectorAll('.silo-select');
                const seciliDegerler = [];
                siloSelects.forEach(sel => {
                    if (sel.value) {
                        seciliDegerler.push(sel.value);
                    }
                });

                // Her dropdown için seçenekleri güncelle
                siloSelects.forEach(sel => {
                    const mevcutDeger = sel.value;
                    const options = sel.querySelectorAll('option');
                    options.forEach(opt => {
                        if (opt.value && opt.value !== mevcutDeger) {
                            // Bu seçenek başka bir dropdown'da seçiliyse devre dışı bırak
                            opt.disabled = seciliDegerler.includes(opt.value);
                        }
                    });
                });
            }

            function updateSilButtons() {
                const rows = container.querySelectorAll('.silo-row');
                const silButtons = container.querySelectorAll('.btn-silo-sil');
                silButtons.forEach(btn => {
                    btn.disabled = rows.length <= 1;
                });
                updateSeciliSilolar();
            }

            function hesaplaToplamYuzde() {
                const yuzdeInputs = container.querySelectorAll('.silo-yuzde');
                let toplam = 0;
                yuzdeInputs.forEach(input => {
                    toplam += parseFloat(input.value) || 0;
                });
                toplamSpan.textContent = toplam.toFixed(1);

                if (Math.abs(toplam - 100) > 0.1) {
                    uyariSpan.classList.remove('d-none');
                    toplamSpan.classList.add('text-danger');
                    toplamSpan.classList.remove('text-success');
                } else {
                    uyariSpan.classList.add('d-none');
                    toplamSpan.classList.remove('text-danger');
                    toplamSpan.classList.add('text-success');
                }
            }

            // Form submit validasyonu
            document.querySelector('form').addEventListener('submit', function (e) {
                // Sadece is_emri_ver formu için
                if (!e.target.querySelector('[name="is_emri_ver"]')) return;

                const yuzdeInputs = container.querySelectorAll('.silo-yuzde');
                const siloSelects = container.querySelectorAll('.silo-select');
                let toplam = 0;
                let seciliSilolar = [];
                let hataMesaji = '';

                yuzdeInputs.forEach((input, index) => {
                    const yuzde = parseFloat(input.value) || 0;
                    const siloId = siloSelects[index].value;

                    if (siloId && yuzde > 0) {
                        toplam += yuzde;
                        if (seciliSilolar.includes(siloId)) {
                            hataMesaji = 'Aynı silo birden fazla kez seçilemez!';
                        }
                        seciliSilolar.push(siloId);
                    }
                });

                if (seciliSilolar.length === 0) {
                    hataMesaji = 'En az bir silo seçmelisiniz!';
                } else if (Math.abs(toplam - 100) > 0.1) {
                    hataMesaji = 'Silo yüzdelerinin toplamı %100 olmalı! (Şu an: %' + toplam.toFixed(1) + ')';
                }

                if (hataMesaji) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Eksik / Hatalı Karışım',
                        text: hataMesaji,
                        confirmButtonText: 'Tamam'
                    });
                }
            });

            hesaplaToplamYuzde();
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>
