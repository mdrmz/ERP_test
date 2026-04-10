<?php
session_start();
require_once 'baglan.php';
require_once 'helper_functions.php';

if (!isset($_SESSION["oturum"])) {
    die("Yetkisiz erişim. Lütfen giriş yapın.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Geçersiz işlem numarası.");
}

// Veritabanından bilgileri çek
$sql = "
    SELECT 
        a.kantar_net_kg,
        a.birim_fiyat,
        a.odeme_tarihi,
        hg.*, 
        h.ad as hammadde_adi,
        la.* 
    FROM hammadde_kabul_akisi a
    LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN lab_analizleri la ON hg.id = la.hammadde_giris_id
    WHERE a.id = ? AND a.asama = 'tamamlandi'
";

$stmt = $baglanti->prepare($sql);
if (!$stmt) {
    die("Sorgu hazırlanırken hata oluştu: " . $baglanti->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Kayıt bulunamadı veya satın alma henüz onaylanmamış.");
}

$veri = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sözleşme -
        <?php echo htmlspecialchars($veri['parti_no'] ?? $veri['id']); ?>
    </title>
    <!-- Arial veya benzeri sans-serif fontu için (Screenshot'a benzemesi için) -->
    <link href="https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red-color: #ff0000;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 0;
            font-size: 11pt;
            /* Kucultuldu */
            line-height: 1.2;
            /* Kucultuldu */
        }

        .print-container {
            width: 210mm;
            padding: 10mm;
            /* Kucultuldu */
            margin: 5mm auto;
            /* Kucultuldu */
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            margin-bottom: 2rem;
        }

        h1.title {
            text-align: center;
            font-size: 18pt;
            /* Kucultuldu */
            font-weight: normal;
            margin-top: 0;
            margin-bottom: 2rem;
            /* Kucultuldu */
            letter-spacing: 1px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 0.8rem;
            /* Kucultuldu */
            margin-bottom: 0.2rem;
        }

        .content-group {
            margin-bottom: 0.8rem;
            /* Kucultuldu */
            padding-left: 5px;
        }

        .row-text {
            margin-bottom: 0.15rem;
        }

        .label {
            display: inline-block;
        }

        .red-text {
            color: var(--red-color);
        }

        .indent {
            margin-left: 2rem;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            /* Kucultuldu */
            padding: 0 1rem;
        }

        .signature-box {
            text-align: left;
        }

        .signature-box.right {
            text-align: right;
            padding-right: 2rem;
        }

        .legal-text {
            text-align: justify;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            body {
                background-color: white;
                font-size: 11pt;
                /* Kucultuldu */
            }

            .print-container {
                margin: 0;
                padding: 10mm 10mm;
                /* Kucultuldu */
                box-shadow: none;
                width: auto;
                height: auto;
            }

            .no-print {
                display: none !important;
            }

            /* Sayfa kırılmalarını önle */
            .content-group {
                page-break-inside: avoid;
            }
        }

        /* Önizleme butonları */
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #dee2e6;
        }

        .btn-print {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            font-family: inherit;
        }

        .btn-print:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>

<body>

    <div class="controls no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Yazdır / PDF Al</button>
    </div>

    <div class="print-container">

        <div class="header-top">
            <span>
                <?php echo date('d.m.Y H:i'); ?>
            </span>
            <span>
                <?php echo $_SERVER['HTTP_HOST']; ?>/satin_alma_formu.php?id=
                <?php echo $id; ?>
            </span>
        </div>

        <h1 class="title">SÖZLEŞME</h1>

        <div class="content-group">
            <div class="section-title">1.TARAFLAR:</div>
            <div class="row-text">
                <span class="label">ALICI:</span> PAK GIDA SAN. VE TİC. LTD. ŞTİ.
            </div>
            <div class="row-text indent">
                ADRES:Başpınar mah. 83542 nolu cad. no:3 Org. San.5.Bölge <span
                    style="text-decoration: underline; font-weight: bold;">ŞEHİTKAMİL/GAZİANTEP</span>
            </div>
            <div class="row-text indent">
                V.D.:ŞEHİTKAMİL V.NO:7190019466
            </div>
            <div class="row-text" style="margin-top: 0.2rem;">
                <span class="label">SATICI:</span> <span class="red-text">
                    <?php echo mb_strtoupper($veri['tedarikci'] ?? 'BELİRTİLMEMİŞ'); ?>
                </span>
            </div>
        </div>

        <div class="content-group">
            <div class="section-title">2.SÖZLEŞME KONUSU:</div>
            <div class="red-text" contenteditable="true" spellcheck="false">
                <?php echo mb_strtoupper($veri['hammadde_adi'] ?? 'BUĞDAY'); ?> ALIMI
            </div>
        </div>

        <div class="content-group">
            <div class="section-title">3.ÜRÜN ÖZELLİKLERİ</div>
            <div class="red-text">PROTEİN: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['protein'] ?? ''; ?></span></div>
            <div class="red-text">NEM: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['nem'] ?? ''; ?></span></div>
            <div class="red-text">HEKTOLİTRE: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['hektolitre'] ?? ''; ?></span></div>
            <div class="red-text">DÖKER: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['doker_orani'] ?? ''; ?></span></div>
            <div class="red-text">GLUTEN: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['gluten'] ?? ''; ?></span></div>
            <div class="red-text">SEDİMANTASYON: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['sedimantasyon'] ?? ''; ?></span></div>
            <div class="red-text">ZELENY
                SEDİMANTASYON: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['gecikmeli_sedimantasyon'] ?? ''; ?></span></div>
            <div class="red-text">FALLİNG NUMBER: <span contenteditable="true"
                    spellcheck="false"><?php echo $veri['fn'] ?? ''; ?></span></div>
            <div class="red-text">TAT VE
                KOKU: <span contenteditable="true" spellcheck="false"><?php echo $veri['tat_ve_koku'] ?? ''; ?></span>
            </div>
        </div>

        <div class="content-group">
            <div class="section-title">4.ÜRÜN
                MİKTARI</div>
            <div class="red-text" contenteditable="true" spellcheck="false">
                <?php echo number_format($veri['kantar_net_kg'] ?? 0, 0, '', ''); ?>
            </div>
        </div>

        <div class="content-group">
            <div class="section-title">5.ÖDEME</div>
            <div class="red-text" style="font-weight: bold;" contenteditable="true" spellcheck="false">
                <?php echo !empty($veri['odeme_tarihi']) ? date('d.m.Y', strtotime($veri['odeme_tarihi'])) : '-'; ?>
            </div>
        </div>

        <div class="content-group legal-text">
            <div class="section-title">
                6.ANLAŞMAZLIKLARIN ÇÖZÜLMESİ
            </div>
            a. Herhangi bir anlaşmazlık
            durumunda, taraflar,
            anlaşmazlığın çözülmesi için iyi
            niyetle ellerinden gelen
            çabayı göstereceklerdir. Bunun
            mümkün olmadığı durumlarda,
            Gaziantep İCRA daireleri ve
            mahkemeleri
            yetkilidir.
            <br><br>
            <span style="font-weight: bold;">7.</span>Sözleşme
            bir sayfadan ibarettir. İki nüsha
            halinde Taraflarca
            imzalanmıştır.
        </div>

        <div class="signatures">
            <div class="signature-box">
                <div style="margin-bottom: 2rem;">
                    İMZA</div>
                <div>SATICI</div>
                <div>ALICI</div>
            </div>

            <div class="signature-box right">
                <div>İMZA</div>
            </div>
        </div>

    </div>

</body>

</html>