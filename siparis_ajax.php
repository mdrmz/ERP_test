<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"]))
    exit("Oturum kapalı");

// Modül bazlı yetki kontrolü (AJAX için sessiondan kontrol)
if (!modulYetkisiVar($baglanti, 'Satış & Siparişler')) {
    exit("Yetkisiz erişim");
}

$islem = isset($_GET['islem']) ? $_GET['islem'] : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($islem == 'getir_sevk') {
    // Siparişin ürünlerini ve ne kadar sevk edildiğini getir
    $sql = "SELECT * FROM siparis_detaylari WHERE siparis_id = $id";
    $result = $baglanti->query($sql);

    if (!$result) {
        die("<div class='alert alert-danger'>Veritabanı Hatası: " . $baglanti->error . "</div>");
    }

    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Ürün</th><th>Sipariş</th><th>Daha Önce Sevk</th><th>Şimdi Sevk Et</th></tr></thead>';
    echo '<tbody>';

    while ($row = $result->fetch_assoc()) {
        // Güvenli erişim - Sütun yoksa 0 kabul et ve hata verme
        $miktar = isset($row['miktar']) ? $row['miktar'] : 0;
        $sevk_edilen = isset($row['sevk_edilen_miktar']) ? $row['sevk_edilen_miktar'] : 0;

        $kalan = $miktar - $sevk_edilen;
        if ($kalan <= 0)
            continue;

        echo '<tr>';
        echo "<td>{$row['urun_adi']}</td>";
        echo "<td>{$miktar} {$row['birim']}</td>";
        echo "<td>{$sevk_edilen}</td>";
        echo "<td>
                <input type='number' name='sevk_miktar[{$row['id']}]' class='form-control form-control-sm' max='$kalan' min='0' placeholder='0'>
                <small class='text-muted'>Max: $kalan</small>
              </td>";
        echo '</tr>';
    }
    echo '</tbody></table>';

    if ($result->num_rows == 0)
        echo "Tüm ürünler sevk edilmiş veya kayıt yok.";
}

if ($islem == 'getir_detay') {
    // 1. Sipariş Detayları
    echo "<h6>Sipariş İçeriği</h6>";
    $sql = "SELECT * FROM siparis_detaylari WHERE siparis_id = $id";
    $result = $baglanti->query($sql);

    if (!$result) {
        echo "<div class='alert alert-danger'>Veritabanı Hatası: " . $baglanti->error . "</div>";
    } else {
        echo '<table class="table table-bordered table-sm mb-4">';
        echo '<thead class="table-light"><tr><th>Ürün</th><th>Miktar</th><th>Birim</th><th>Sevk Edilen</th><th>Kalan</th></tr></thead>';
        echo '<tbody>';
        while ($row = $result->fetch_assoc()) {
            $miktar = isset($row['miktar']) ? $row['miktar'] : 0;
            $sevk_edilen = isset($row['sevk_edilen_miktar']) ? $row['sevk_edilen_miktar'] : 0;

            $kalan = $miktar - $sevk_edilen;
            $bg = ($kalan == 0) ? 'bg-light' : '';
            echo "<tr class='$bg'>";
            echo "<td>{$row['urun_adi']}</td>";
            echo "<td>{$miktar}</td>";
            echo "<td>{$row['birim']}</td>";
            echo "<td class='text-success fw-bold'>{$sevk_edilen}</td>";
            echo "<td class='text-danger'>$kalan</td>";
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // 2. Sevkiyat Geçmişi
    echo "<h6><i class='fas fa-truck'></i> Sevkiyat Geçmişi (Neler Ne Zaman Gitti?)</h6>";

    // Tablo var mı kontrolü yapılabilir ama direkt sorgu atalım
    $sql_hist = "SELECT * FROM sevkiyat_detaylari WHERE siparis_id = $id ORDER BY sevk_tarihi DESC";
    $hist = $baglanti->query($sql_hist);

    if (!$hist) {
        echo "<div class='alert alert-warning'>Sevkiyat detay tablosu bulunamadı veya hata oluştu.</div>";
    } elseif ($hist->num_rows > 0) {
        echo '<ul class="list-group">';
        while ($h = $hist->fetch_assoc()) {
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
            echo "<div>
                    <strong>{$h['urun_adi']}</strong> <br>
                    <small class='text-muted'>Plaka: {$h['plaka']}</small>
                  </div>";
            echo "<div class='text-end'>
                    <span class='badge bg-primary'>{$h['miktar']} Adet</span> <br>
                    <small>" . date('d.m.Y', strtotime($h['sevk_tarihi'])) . "</small>
                  </div>";
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo "<p class='text-muted'>Henüz sevkiyat yapılmamış.</p>";
    }
}
?>
