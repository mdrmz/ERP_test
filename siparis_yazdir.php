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

$siparis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($siparis_id <= 0) {
    die("Geçersiz sipariş ID.");
}

// Sipariş başlık bilgilerini çek
$sql = "SELECT s.*, m.firma_adi, m.yetkili_kisi, m.telefon, m.adres 
        FROM siparisler s 
        JOIN musteriler m ON s.musteri_id = m.id 
        WHERE s.id = $siparis_id";
$res = $baglanti->query($sql);

if (!$res || $res->num_rows === 0) {
    die("Sipariş bulunamadı.");
}

$siparis = $res->fetch_assoc();

// Sipariş detaylarını çek
$detay_sql = "SELECT * FROM siparis_detaylari WHERE siparis_id = $siparis_id";
$detaylar = $baglanti->query($detay_sql);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Formu - <?php echo $siparis['siparis_kodu']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .print-container { background-color: #fff; padding: 40px; margin: 20px auto; max-width: 800px; border: 1px solid #ddd; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .invoice-title { font-size: 24px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #333; }
        .company-name { font-size: 28px; font-weight: bold; color: #d35400; }
        .info-box { border: 1px solid #e0e0e0; padding: 15px; border-radius: 5px; background-color: #fafafa; height: 100%; }
        .info-box h6 { font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; color: #555; }
        
        @media print {
            body { background-color: #fff; }
            .print-container { box-shadow: none; border: none; margin: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-end mt-4 mb-2 no-print">
        <button onclick="window.print();" class="btn btn-primary btn-lg"><i class="fas fa-print"></i> Yazdır / PDF Kaydet</button>
        <button onclick="window.close();" class="btn btn-secondary btn-lg">Kapat</button>
    </div>

    <div class="print-container">
        <!-- Header -->
        <div class="row invoice-header">
            <div class="col-8">
                <div class="company-name">ÖZBAL UN SANAYİ</div>
                <div class="text-muted small">Ön Sipariş Teyit Formu</div>
            </div>
            <div class="col-4 text-end">
                <div class="invoice-title">SİPARİŞ</div>
                <div><strong>No:</strong> <?php echo $siparis['siparis_kodu']; ?></div>
                <div><strong>Tarih:</strong> <?php echo date('d.m.Y', strtotime($siparis['siparis_tarihi'])); ?></div>
            </div>
        </div>

        <!-- Müşteri ve Teslimat Bilgileri -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="info-box">
                    <h6>Müşteri Bilgileri</h6>
                    <div><strong>Firma:</strong> <?php echo $siparis['firma_adi']; ?></div>
                    <div><strong>Yetkili:</strong> <?php echo $siparis['yetkili_kisi'] ?: '-'; ?></div>
                    <div><strong>Telefon:</strong> <?php echo $siparis['telefon'] ?: '-'; ?></div>
                    <div class="mt-2"><strong>Adres:</strong><br><?php echo nl2br($siparis['adres'] ?: '-'); ?></div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="info-box">
                    <h6>Teslimat & Ödeme Bilgileri</h6>
                    <?php if (!empty($siparis['alici_adi'])): ?>
                    <div class="text-primary mb-2"><strong>Teslim Alacak Kişi/Firma:</strong> <?php echo $siparis['alici_adi']; ?></div>
                    <?php endif; ?>
                    <div><strong>Teslim Tarihi:</strong> <span class="badge bg-warning text-dark fs-6"><?php echo date('d.m.Y', strtotime($siparis['teslim_tarihi'])); ?></span></div>
                    
                    <?php if (!empty($siparis['odeme_tarihi'])): ?>
                    <div class="mt-2"><strong>Planlanan Ödeme:</strong> <?php echo date('d.m.Y', strtotime($siparis['odeme_tarihi'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sipariş Kalemleri -->
        <h6 class="fw-bold mb-3 border-bottom pb-2">Sipariş Kalemleri</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="55%">Ürün Adı</th>
                        <th width="20%" class="text-center">Miktar</th>
                        <th width="20%" class="text-center">Birim</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($detaylar && $detaylar->num_rows > 0) {
                        $sayac = 1;
                        while ($d = $detaylar->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$sayac}</td>
                                    <td><strong>{$d['urun_adi']}</strong></td>
                                    <td class='text-center fs-5'>{$d['miktar']}</td>
                                    <td class='text-center'>{$d['birim']}</td>
                                  </tr>";
                            $sayac++;
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center'>Sipariş detayı bulunamadı.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Açıklama -->
        <?php if (!empty($siparis['aciklama'])): ?>
        <div class="mb-4">
            <h6 class="fw-bold border-bottom pb-1">Sipariş Notları / Açıklama</h6>
            <div class="p-3 bg-light border rounded">
                <?php echo nl2br($siparis['aciklama']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Signatures -->
        <div class="row mt-5 pt-4">
            <div class="col-4 text-center">
                <div class="border-top pt-2 mx-3">
                    <strong>Pazarlama / Satış Temsilcisi</strong><br>
                    <small class="text-muted">Ad Soyad / İmza</small>
                </div>
            </div>
            <div class="col-4 text-center">
                <div class="border-top pt-2 mx-3">
                    <strong>Siparişi Onaylayan</strong><br>
                    <small class="text-muted">Müşteri / Yetkili İmza</small>
                </div>
            </div>
            <div class="col-4 text-center">
                <div class="border-top pt-2 mx-3">
                    <strong>Depo Teslim Onayı</strong><br>
                    <small class="text-muted">Ad Soyad / İmza</small>
                </div>
            </div>
        </div>
        
        <div class="text-center text-muted mt-5 small" style="font-size: 0.8rem;">
            Bu belge ön sipariş teyidi içindir. Fatura yerine geçmez. Sistem tarafından <?php echo date('d.m.Y H:i'); ?> tarihinde oluşturulmuştur.
        </div>
    </div>
</div>

<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>
</html>
