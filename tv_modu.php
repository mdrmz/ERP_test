<?php
include("baglan.php");
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>CANLI FABRİKA İZLEME</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #000;
            color: #fff;
            overflow: hidden;
        }

        /* Tam Siyah Mod */
        .card-dark {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.1);
        }

        .buyuk-sayi {
            font-size: 5rem;
            font-weight: bold;
        }

        .yanip-sonen {
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }
    </style>
    <meta http-equiv="refresh" content="10">
</head>

<body class="d-flex flex-column justify-content-center p-4" style="height: 100vh;">

    <?php
    // VERİLERİ ÇEK
    $bugun = date("Y-m-d");

    // 1. Günlük Üretim
    $uretim = $baglanti->query("SELECT SUM(uretilen_miktar_kg) as top FROM uretim_hareketleri WHERE DATE(tarih)='$bugun'")->fetch_assoc();
    $tonaj = ($uretim['top'] > 0) ? number_format($uretim['top'] / 1000, 1) : 0;

    // 2. Bekleyen İş
    $isler = $baglanti->query("SELECT count(*) as sayi FROM is_emirleri WHERE durum='bekliyor'")->fetch_assoc()['sayi'];

    // 3. Silolar
    $silolar = $baglanti->query("SELECT * FROM silolar");
    ?>

    <div class="row text-center g-4">
        <div class="col-12 mb-4">
            <h1 class="display-4 fw-bold text-uppercase" style="letter-spacing: 5px;">
                <i class="fas fa-industry text-warning me-3"></i> ÖZBAL UN <span class="text-secondary">|</span> CANLI
                ÜRETİM
            </h1>
            <h4 class="text-success yanip-sonen">● SİSTEM AKTİF - <?php echo date("H:i"); ?></h4>
        </div>

        <div class="col-md-4">
            <div class="card card-dark h-100 p-4 border-success border-2">
                <h3 class="text-muted">BUGÜNKÜ ÜRETİM</h3>
                <div class="buyuk-sayi text-success"><?php echo $tonaj; ?> <span class="fs-4">TON</span></div>
                <p class="text-white-50">Hedef: 50 Ton</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-dark h-100 p-4 border-warning border-2">
                <h3 class="text-muted">BEKLEYEN İŞ EMRİ</h3>
                <div class="buyuk-sayi text-warning"><?php echo $isler; ?> <span class="fs-4">ADET</span></div>
                <p class="text-white-50">Planlama Bekleniyor</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-dark h-100 p-3">
                <h4 class="text-muted mb-3">KRİTİK SİLALAR</h4>
                <div class="d-flex flex-column gap-3 justify-content-center h-100">
                    <?php while ($s = $silolar->fetch_assoc()) {
                        $yuzde = ($s["kapasite_m3"] > 0) ? round(($s["doluluk_m3"] / $s["kapasite_m3"]) * 100) : 0;
                        $renk = ($yuzde > 80) ? 'bg-danger' : (($yuzde > 50) ? 'bg-warning' : 'bg-success');
                        ?>
                        <div>
                            <div class="d-flex justify-content-between h5">
                                <span><?php echo $s["silo_adi"]; ?></span>
                                <span>%<?php echo $yuzde; ?></span>
                            </div>
                            <div class="progress" style="height: 15px; background: #333;">
                                <div class="progress-bar <?php echo $renk; ?>" style="width: <?php echo $yuzde; ?>%"></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
