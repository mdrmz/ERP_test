<?php
/**
 * SQL Entegrasyon Paneli - Özbal Un
 * 
 * Bu sayfa üzerinden dışarıdan gelen müşteri .sql dosyaları 
 * otomatik olarak çözümlenir ve sisteme aktarılır.
 */

session_start();
include("baglan.php");
include("helper_functions.php");

// Yetki Kontrolü
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

$mesaj = "";
$hata = "";

if (isset($_POST["sql_yukle"])) {
    // Tabloyu sıfırla seçeneği işaretli mi?
    if (isset($_POST["purge_table"])) {
        $baglanti->query("DELETE FROM musteriler");
    }

    if (isset($_FILES["sql_file"]) && $_FILES["sql_file"]["error"] == 0) {
        $dosya_icerik = file_get_contents($_FILES["sql_file"]["tmp_name"]);
        
        // SQL İçerisindeki INSERT VALUES kısımlarını bul
        // Çoklu INSERT INTO bloklarını ve her bloğundaki VALUES kısmını yakalarız
        if (preg_match_all('/INSERT\s+INTO\s+.*?\s+VALUES\s+(.*?)(?:;|\z)/is', $dosya_icerik, $matches)) {
            $eklenen = 0;
            $guncellenen = 0;
            $hata_sayisi = 0;
            $toplam = 0;

            foreach ($matches[1] as $values_block) {
                // Satırları ayır: (val1,val2,...),(val3,val4,...)
                // Parantez içindeki her bir satırı yakalayalım
                preg_match_all('/\((.*?)\)(?:,|\s*;|\s*\z)/s', $values_block, $rows);
                
                foreach ($rows[1] as $row) {
                    $toplam++;
                    // Virgülle ayrılmış değerleri al (Tırnak içindekileri koruyarak)
                    $cols = str_getcsv($row, ",", "'");
                    
                    if (count($cols) >= 11) {
                        $kod = $baglanti->real_escape_string(trim($cols[0]));
                        $unvan1 = trim($cols[1]);
                        $unvan2 = trim($cols[2]);
                        $vd_no = $baglanti->real_escape_string(trim($cols[3]));
                        $vd_adi = $baglanti->real_escape_string(trim($cols[4]));
                        $mail = $baglanti->real_escape_string(trim($cols[5]));
                        $cadde = trim($cols[6]);
                        $sokak = trim($cols[7]);
                        $il = $baglanti->real_escape_string(trim($cols[8]));
                        $ilce = $baglanti->real_escape_string(trim($cols[9]));
                        $tel = $baglanti->real_escape_string(trim($cols[10]));

                        // Mantık işlemleri
                        $tip = (substr($kod, 0, 3) === '320') ? 'Tedarikçi' : 'Müşteri';
                        $firma_adi = $baglanti->real_escape_string(trim($unvan1 . " " . $unvan2));
                        $adres = $baglanti->real_escape_string(trim($cadde . " " . $sokak));

                        $sql = "INSERT INTO musteriler 
                                (cari_kod, cari_tip, firma_adi, telefon, eposta, vergi_dairesi, vergi_no, il, ilce, adres) 
                                VALUES 
                                ('$kod', '$tip', '$firma_adi', '$tel', '$mail', '$vd_adi', '$vd_no', '$il', '$ilce', '$adres')
                                ON DUPLICATE KEY UPDATE 
                                firma_adi = VALUES(firma_adi),
                                telefon = VALUES(telefon),
                                eposta = VALUES(eposta),
                                vergi_dairesi = VALUES(vergi_dairesi),
                                vergi_no = VALUES(vergi_no),
                                il = VALUES(il),
                                ilce = VALUES(ilce),
                                adres = VALUES(adres)";
                        
                        if ($baglanti->query($sql)) {
                            if ($baglanti->affected_rows == 1) $eklenen++;
                            elseif ($baglanti->affected_rows == 2) $guncellenen++;
                        } else {
                            $hata_sayisi++;
                        }
                    }
                }
            }
            $mesaj = "✅ İşlem Tamamlandı: $toplam satır analiz edildi. $eklenen yeni eklendi, $guncellenen güncellendi. $hata_sayisi hata.";
        } else {
            $hata = "❌ SQL dosyası beklenen formatta değil (INSERT/VALUES kısmı bulunamadı).";
        }
    } else {
        $hata = "❌ Dosya yükleme hatası.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>SQL Müşteri Entegrasyonu - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #ccc;
            padding: 40px;
            text-align: center;
            background: #f9f9f9;
            cursor: pointer;
            transition: 0.3s;
            border-radius: 10px;
        }
        .upload-area:hover {
            border-color: #0d6efd;
            background: #f0f7ff;
        }
        .step-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white p-3">
                        <h4 class="mb-0"><i class="fas fa-database me-2"></i> SQL Müşteri Entegrasyon Paneli</h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if($mesaj): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo $mesaj; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if($hata): ?>
                            <div class="alert alert-danger"><?php echo $hata; ?></div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i> Nasıl Kullanılır?</h5>
                            <p class="small mb-0">Elinizdeki `.sql` dosyasını seçip yükleyin. Sistem otomatik olarak şunları yapar:</p>
                            <ul class="small mt-2">
                                <li><strong>Ünvan Birleştirme:</strong> ÜN_1 ve ÜN_2 birleştirilerek tam ünvan oluşturulur.</li>
                                <li><strong>Adres Oluşturma:</strong> Cadde ve Sokak birleştirilir.</li>
                                <li><strong>Akıllı Tip:</strong> Kod '120' ise Müşteri, '320' ise Tedarikçi olarak işaretlenir.</li>
                                <li><strong>Zeki Güncelleme:</strong> Eğer müşteri sistemde varsa, bilgileri en yeni haliyle güncellenir.</li>
                            </ul>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="mt-4">
                            <div class="mb-4">
                                <label class="form-label fw-bold small">Müşteri/Tedarikçi SQL Dosyası Seçin</label>
                                <div class="upload-area" onclick="document.getElementById('sql_file').click()">
                                    <i class="fas fa-file-export fa-3x text-muted mb-3"></i>
                                    <h6>Dosyayı buraya tıklayarak seçin veya bırakın</h6>
                                    <p class="text-muted small">Desteklenen: .sql dosyaları</p>
                                    <input type="file" name="sql_file" id="sql_file" class="d-none" accept=".sql" required onchange="updateFileName(this)">
                                    <div id="file-name" class="mt-2 fw-bold text-primary"></div>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="purge_table" id="purge_table" value="1">
                                <label class="form-check-label text-danger fw-bold" for="purge_table">
                                    <i class="fas fa-trash-alt me-1"></i> Tüm müşterileri sil ve sıfırdan yükle (Önerilir)
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="sql_yukle" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sync me-2"></i> Entegrasyonu Başlat
                                </button>
                                <a href="musteriler.php" class="btn btn-outline-secondary">
                                    Müşteri Listesine Geri Dön
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card step-card p-3 shadow-sm h-100">
                            <h6><i class="fas fa-check-circle text-success me-2"></i> Adım 1: Dosyayı Alın</h6>
                            <p class="small text-muted mb-0">Dış sistemden aldığınız SQL dosyasını bilgisayarınıza kaydedin.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card step-card p-3 shadow-sm h-100" style="border-left-color: #ffc107;">
                            <h6><i class="fas fa-magic text-warning me-2"></i> Adım 2: Aktarımı İzleyin</h6>
                            <p class="small text-muted mb-0">Sistem verileri tek tek kontrol eder ve hatasız şekilde sisteme yerleştirir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0].name;
            document.getElementById('file-name').innerHTML = '<i class="fas fa-file-code me-1"></i> ' + fileName;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
