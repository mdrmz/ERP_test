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
$kantar_okuma_kolon_var = false;

if (!function_exists('formatBirimFiyatGoster')) {
    function formatBirimFiyatGoster($deger)
    {
        if ($deger === null || $deger === '') {
            return '-';
        }
        return number_format((float) $deger, 2, ',', '.');
    }
}

if (!function_exists('xlsxKolonAdi')) {
    function xlsxKolonAdi($sira)
    {
        $harf = '';
        while ($sira > 0) {
            $kalan = ($sira - 1) % 26;
            $harf = chr(65 + $kalan) . $harf;
            $sira = (int) (($sira - $kalan - 1) / 26);
        }
        return $harf;
    }
}

if (!function_exists('xlsxSatirXmlUret')) {
    function xlsxSatirXmlUret($satirNo, array $satirVeri)
    {
        $hucreler = '';
        $kolonNo = 1;
        foreach ($satirVeri as $deger) {
            $hucreRef = xlsxKolonAdi($kolonNo) . $satirNo;
            $deger = trim((string) $deger);
            $deger = str_replace(["\r", "\n", "\t"], ' ', $deger);
            $deger = htmlspecialchars($deger, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $hucreler .= '<c r="' . $hucreRef . '" t="inlineStr"><is><t>' . $deger . '</t></is></c>';
            $kolonNo++;
        }
        return '<row r="' . $satirNo . '">' . $hucreler . '</row>';
    }
}

if (!function_exists('xlsxDosyaOlustur')) {
    function xlsxDosyaOlustur($dosyaYolu, array $satirlar)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $sheetRowsXml = '';
        $satirNo = 1;
        foreach ($satirlar as $satir) {
            $sheetRowsXml .= xlsxSatirXmlUret($satirNo, $satir);
            $satirNo++;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRowsXml . '</sheetData>'
            . '</worksheet>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rapor" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $zip = new ZipArchive();
        if ($zip->open($dosyaYolu, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $relsXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);

        $zip->close();
        return true;
    }
}

$excel_export_istek = isset($_GET['excel_export']) && $_GET['excel_export'] === '1';
if ($excel_export_istek) {
    $export_adet = isset($_GET['export_adet']) ? (int) $_GET['export_adet'] : 10;
    $export_adet = max(1, min(500, $export_adet));

    $excel_sorgu = "
        SELECT a.id, a.guncelleme_tarihi, a.kantar_net_kg, a.birim_fiyat, a.odeme_tarihi,
               hg.arac_plaka, hg.tedarikci, hg.parti_no,
               h.hammadde_kodu, h.ad AS hammadde_adi,
               u.kadi AS onaylayan_kadi
        FROM hammadde_kabul_akisi a
        LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
        LEFT JOIN users u ON a.onaylayan_user_id = u.id
        WHERE a.asama = 'tamamlandi'
          AND a.onay_durum = 'onaylandi'
        ORDER BY a.guncelleme_tarihi DESC
        LIMIT $export_adet
    ";

    $excel_sonuc = $baglanti->query($excel_sorgu);
    if (!$excel_sonuc) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Rapor olusturulurken hata olustu.";
        exit;
    }

    $satirlar = [];
    $satirlar[] = [
        'Tarih',
        'Plaka',
        'Hammadde Kodu',
        'Hammadde',
        'Tedarikci',
        'Parti No',
        'Miktar (kg)',
        'Birim Fiyat',
        'Odeme Tarihi',
        'Islem Yapan'
    ];

    while ($row = $excel_sonuc->fetch_assoc()) {
        $satirlar[] = [
            !empty($row['guncelleme_tarihi']) ? date('d.m.Y H:i', strtotime($row['guncelleme_tarihi'])) : '-',
            $row['arac_plaka'] ?? '-',
            $row['hammadde_kodu'] ?? '-',
            $row['hammadde_adi'] ?? '-',
            $row['tedarikci'] ?? '-',
            $row['parti_no'] ?? '-',
            number_format((float) ($row['kantar_net_kg'] ?? 0), 0, ',', '.'),
            $row['birim_fiyat'] !== null ? number_format((float) $row['birim_fiyat'], 2, ',', '.') : '-',
            !empty($row['odeme_tarihi']) ? date('d.m.Y', strtotime($row['odeme_tarihi'])) : '-',
            $row['onaylayan_kadi'] ?? 'Sistem'
        ];
    }

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'erp_xlsx_');
    if ($tmpXlsx === false || !xlsxDosyaOlustur($tmpXlsx, $satirlar)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "XLSX olusturulamadi. Sunucuda ZipArchive uzantisi aktif olmalidir.";
        exit;
    }

    $dosya_adi = "hammadde_alim_raporu_" . date('Y-m-d_H-i-s') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');
    header('Content-Length: ' . filesize($tmpXlsx));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tmpXlsx);
    @unlink($tmpXlsx);
    exit;
}

$kolon_kontrol = @$baglanti->query("SHOW COLUMNS FROM hammadde_kabul_akisi LIKE 'kantar_okuma_id'");
if ($kolon_kontrol && $kolon_kontrol->num_rows > 0) {
    $kantar_okuma_kolon_var = true;
}

// Onay merkezinden onaylanmış ama satınalma listesine düşmemiş kayıtları otomatik ilerlet
$baglanti->query("UPDATE hammadde_kabul_akisi SET asama = 'satina_bekliyor' WHERE asama = 'onaylandi' AND onay_durum = 'onaylandi'");

// --- 1. MALZEME TALEBİ DURUM GÜNCELLEME ---
if (isset($_POST["durum_guncelle"])) {
    $id = (int) $_POST["talep_id"];
    $yeni_durum = $_POST["yeni_durum"];
    $yonetici_notu = $baglanti->real_escape_string($_POST["yonetici_notu"] ?? '');
    $user_id = $_SESSION["user_id"];

    $baglanti->query("UPDATE satin_alma_talepleri SET 
        onay_durum='$yeni_durum', 
        onaylayan_user_id=$user_id, 
        onay_tarihi=NOW(),
        yonetici_notu='$yonetici_notu' 
        WHERE id=$id");

    if ($yeni_durum == 'alindi') {
        $talep = $baglanti->query("SELECT * FROM satin_alma_talepleri WHERE id=$id")->fetch_assoc();
        $malzeme_adi_raw = $talep['malzeme_adi'];
        $miktar = (float) $talep['miktar'];
        $malzeme_id = null;

        if (strpos($malzeme_adi_raw, 'ID:') === 0) {
            preg_match('/ID:(\d+)\|/', $malzeme_adi_raw, $matches);
            if (isset($matches[1]))
                $malzeme_id = (int) $matches[1];
        }

        if (!$malzeme_id) {
            $malzeme_adi = $baglanti->real_escape_string($malzeme_adi_raw);
            $malzeme = $baglanti->query("SELECT id FROM malzemeler WHERE malzeme_adi LIKE '%$malzeme_adi%' OR malzeme_kodu LIKE '%$malzeme_adi%' LIMIT 1");
            if ($malzeme && $malzeme->num_rows > 0) {
                $mlz = $malzeme->fetch_assoc();
                $malzeme_id = $mlz['id'];
            }
        }

        if ($malzeme_id) {
            $baglanti->query("UPDATE malzemeler SET mevcut_stok = mevcut_stok + $miktar WHERE id = $malzeme_id");
            $baglanti->query("INSERT INTO malzeme_hareketleri (malzeme_id, hareket_tipi, miktar, aciklama, kullanici) 
                              VALUES ($malzeme_id, 'giris', $miktar, 'Satın alma #$id', '{$_SESSION["kadi"]}')");
            $mesaj = "✅ Malzeme stoğa eklendi ve işlem tamamlandı.";
        } else {
            $mesaj = "✅ Talep ALINDI işaretlendi (Stok eşleşmesi bulunamadı).";
        }
    } else {
        $mesaj = "✅ Talep durumu güncellendi: " . strtoupper($yeni_durum);
    }
}

// --- 2. HAMMADDE ALIMI ONAYLAMA / REDDETME ---
if (isset($_POST["hammadde_alimi_islem"])) {
    $akis_id = (int) $_POST["akis_id"];
    $islem = $_POST["hammadde_alimi_islem"]; // 'tamamla' or 'reddet'
    $red_nedeni = $baglanti->real_escape_string($_POST["red_nedeni"] ?? '');

    if ($islem === 'tamamla') {
        $baglanti->query("UPDATE hammadde_kabul_akisi SET asama = 'tamamlandi', guncelleme_tarihi = NOW() WHERE id = $akis_id");
        if (function_exists('akisGecmisKaydet')) {
            akisGecmisKaydet($baglanti, $akis_id, 'satina_bekliyor', 'tamamlandi', 'Satın alma onayı verildi.');
        }
        systemLogKaydet($baglanti, 'PURCHASE', 'Hammadde Alım Onayı', "Akış ID: $akis_id onaylandı.");
        $mesaj = "✅ Hammadde alımı başarıyla onaylandı!";
    } else {
        $baglanti->query("UPDATE hammadde_kabul_akisi SET asama = 'tamamlandi', onay_durum = 'satinalma_red', red_aciklama = '$red_nedeni', guncelleme_tarihi = NOW() WHERE id = $akis_id");
        if (function_exists('akisGecmisKaydet')) {
            akisGecmisKaydet($baglanti, $akis_id, 'satina_bekliyor', 'tamamlandi', "Satın alma reddetti: $red_nedeni");
        }
        systemLogKaydet($baglanti, 'PURCHASE', 'Hammadde Alım Reddi', "Akış ID: $akis_id reddedildi. Sebep: $red_nedeni");
        $mesaj = "❌ Hammadde alımı reddedildi.";
    }
}

// --- 3. HAMMADDE ALIMI KANTAR İLE ONAYLAMA ---
if (isset($_POST["hammadde_kantar_onayla"])) {
    $akis_id = (int) $_POST["akis_id"];
    $kantar_net_kg = (float) $_POST["kantar_net_kg"];

    $kantar_okuma_id = isset($_POST["kantar_okuma_id"]) ? (int) $_POST["kantar_okuma_id"] : 0;
    $kantar_okuma_sql = '';

    if ($kantar_okuma_kolon_var) {
        $kantar_okuma_sql = ", kantar_okuma_id = " . ($kantar_okuma_id > 0 ? $kantar_okuma_id : "NULL");
    }

    // Hem hammadde_kabul_akisi tablosuna kantar_net_kg'i işle, hem de onaylandi/tamamlandi yap
    $baglanti->query("UPDATE hammadde_kabul_akisi
                      SET asama = 'tamamlandi',
                          kantar_net_kg = $kantar_net_kg,
                          kantar_tarihi = NOW()
                          $kantar_okuma_sql,
                          guncelleme_tarihi = NOW()
                      WHERE id = $akis_id");

    // Hammadde girişleri tablosundaki miktar_kg da güncellensin
    $baglanti->query("UPDATE hammadde_girisleri hg
                      INNER JOIN hammadde_kabul_akisi hka ON hka.hammadde_giris_id = hg.id
                      SET hg.miktar_kg = $kantar_net_kg
                      WHERE hka.id = $akis_id");

    if (function_exists('akisGecmisKaydet')) {
        akisGecmisKaydet($baglanti, $akis_id, 'satina_bekliyor', 'tamamlandi', "Satın alma Kantar ile onaylandı. Miktar: $kantar_net_kg kg");
    }
    systemLogKaydet($baglanti, 'PURCHASE', 'Hammadde Alım Onayı (Kantar)', "Akış ID: $akis_id onaylandı. Miktar: $kantar_net_kg kg");
    $mesaj = "✅ Hammadde kantar verisiyle başarıyla onaylandı!";
}

// --- 4. YENİ MALZEME TALEBİ OLUŞTURMA ---
if (isset($_POST["talep_olustur"])) {
    $malzeme = $baglanti->real_escape_string($_POST["malzeme_adi"]);
    $miktar = (float) $_POST["miktar"];
    $birim = $baglanti->real_escape_string($_POST["birim"]);
    $aciliyet = $baglanti->real_escape_string($_POST["aciliyet"]);
    $user_id = $_SESSION["user_id"];

    $sql = "INSERT INTO satin_alma_talepleri (talep_eden_user_id, malzeme_adi, miktar, biris, aciliyet) 
            VALUES ($user_id, '$malzeme', '$miktar', '$birim', '$aciliyet')";
    // Not: Veritabanında sütun adı 'birim' mi 'biris' mi kontrol etmek gerekebilir. 
    // Önceki kodda 'birim' idi, malzeme_stok.php'de de 'birim'. Ama bazı tablolar 'biris' olabilir. 
    // Mevcut satin_alma.php'de table headers'da $row["birim"] kullanılmış. Ben 'birim' olarak devam ediyorum.

    $sql = "INSERT INTO satin_alma_talepleri (talep_eden_user_id, malzeme_adi, miktar, birim, aciliyet) 
            VALUES ($user_id, '$malzeme', '$miktar', '$birim', '$aciliyet')";

    if ($baglanti->query($sql)) {
        $mesaj = "✅ Talep başarıyla iletildi!";
    } else {
        $hata = "Hata: " . $baglanti->error;
    }
}

// Verileri Çek
$talepler = $baglanti->query("SELECT t.*, u.kadi as talep_eden 
                              FROM satin_alma_talepleri t 
                              LEFT JOIN users u ON t.talep_eden_user_id = u.id 
                              ORDER BY t.talep_tarihi DESC");

$hammadde_alim_bekleyenler = $baglanti->query("
    SELECT a.*, hg.arac_plaka, hg.tedarikci, hg.miktar_kg, hg.tarih as giris_tarihi, a.kantar_net_kg, hg.parti_no,
           h.ad as hammadde_adi, h.hammadde_kodu,
           la.protein as lab_protein, la.gluten as lab_gluten, la.nem as lab_nem
    FROM hammadde_kabul_akisi a
    LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN lab_analizleri la ON la.hammadde_giris_id = hg.id
    WHERE a.asama = 'satina_bekliyor'
    ORDER BY hg.tarih DESC, a.id DESC
");

$hammadde_alim_gecmisi = $baglanti->query("
    SELECT a.*, hg.arac_plaka, hg.tedarikci, hg.tarih as giris_tarihi, a.kantar_net_kg, hg.parti_no,
           h.ad as hammadde_adi, h.hammadde_kodu, u.kadi as onaylayan_kadi
    FROM hammadde_kabul_akisi a
    LEFT JOIN hammadde_girisleri hg ON a.hammadde_giris_id = hg.id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN users u ON a.onaylayan_user_id = u.id
    WHERE a.asama = 'tamamlandi' AND (a.onaylayan_user_id IS NOT NULL OR a.onay_durum = 'satinalma_red')
    ORDER BY a.guncelleme_tarihi DESC
");

$bekleyen_talep_sayisi = $baglanti->query("SELECT COUNT(*) as cnt FROM satin_alma_talepleri WHERE onay_durum = 'bekliyor'")->fetch_assoc()['cnt'];
$bekleyen_hammadde_sayisi = $hammadde_alim_bekleyenler->num_rows;

$aktif_tab = isset($_GET['tab']) ? $_GET['tab'] : 'hammadde';
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kantar & Muhasebe Yönetimi - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- Header & Glassmorphism --- */
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 2.5rem 2rem;
            border-radius: 1.5rem;
            margin-top: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -10%;
            width: 120%;
            height: 200%;
            background: radial-gradient(circle, rgba(245, 166, 35, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
            border-radius: 1.25rem;
            text-align: center;
            min-width: 140px;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
        }

        /* --- Tabs Modernization --- */
        .nav-pills {
            background: #e2e8f0;
            padding: 6px;
            border-radius: 16px;
            display: inline-flex;
        }

        .nav-pills .nav-link {
            color: #64748b;
            font-weight: 500;
            border-radius: 12px;
            padding: 10px 20px;
            border: none;
            transition: all 0.2s ease;
        }

        .nav-pills .nav-link.active {
            background: #fff;
            color: var(--primary-bg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* --- Table & Cards --- */
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            background: var(--surface);
        }

        .table thead th {
            background: #f8fafc;
            color: #64748b !important;
            opacity: 1;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #f1f5f9;
            padding: 1.25rem 1rem;
        }

        .table tbody td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .action-col {
            text-align: center !important;
            white-space: nowrap;
            width: 1%;
        }

        .table-action-buttons {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-table-action {
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
        }

        .meta-info-badge {
            font-size: 0.82rem !important;
            font-weight: 600;
        }

        .analiz-values-row {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            flex-wrap: nowrap;
        }

        .analiz-value-badge {
            font-size: 0.82rem !important;
            font-weight: 600;
            white-space: nowrap;
        }

        /* --- Status Badges --- */
        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.7rem;
            letter-spacing: 0.02em;
        }

        .status-bekliyor {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fef3c7;
        }

        .status-onaylandi {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
        }

        .status-alindi {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5;
        }

        .status-reddedildi {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .status-satina {
            background: #f5f3ff;
            color: #5b21b6;
            border: 1px solid #ede9fe;
        }

        /* --- Mobile Optimizations --- */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
                border-radius: 0 0 1.5rem 1.5rem;
                text-align: center;
            }

            .stat-container {
                justify-content: center !important;
                margin-top: 1.5rem;
            }

            .stat-card {
                flex: 1;
                min-width: 100px;
                padding: 0.75rem;
            }

            .nav-pills {
                width: 100%;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .nav-item {
                flex: 1;
            }

            .nav-pills .nav-link {
                width: 100%;
                font-size: 0.85rem;
                padding: 10px 5px;
            }

            .table-responsive {
                border-radius: 12px;
            }

            .new-talep-btn {
                width: 100%;
                margin-top: 10px;
            }
        }

        /* --- Animations --- */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-pane.active {
            animation: fadeIn 0.4s ease-out forwards;
        }

        .plaka-badge {
            background: var(--secondary-bg);
            color: #fff;
            padding: 5px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--primary-bg);
            border: none;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
        }

        .btn-primary:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            border: none;
        }

        .btn-success:hover {
            background: #059669;
        }
    </style>
</head>

<body>

    <?php include("navbar.php"); ?>

    <div class="container-fluid px-md-4">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="fw-bold mb-1"><i class="fas fa-shopping-cart me-2"></i> Kantar & Muhasebe Paneli</h1>
                    <p class="text-white-50 mb-0">Hammadde onayları ve malzeme talepleri yönetim merkezi</p>
                </div>
                <div class="col-md-6 d-flex justify-content-md-end gap-3 mt-3 mt-md-0 stat-container">
                    <div class="stat-card">
                        <small class="d-block text-white-50 uppercase fw-bold mb-1"
                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Gelen Hammadde</small>
                        <span class="fs-3 fw-bold"><?php echo $bekleyen_hammadde_sayisi; ?></span>
                    </div>
                    <div class="stat-card">
                        <small class="d-block text-white-50 uppercase fw-bold mb-1"
                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Malzeme Talebi</small>
                        <span class="fs-3 fw-bold"><?php echo $bekleyen_talep_sayisi; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-md-4 pb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <ul class="nav nav-pills" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $aktif_tab == 'hammadde' ? 'active' : ''; ?>"
                        id="pills-hammadde-tab" data-bs-toggle="pill" data-bs-target="#pills-hammadde" type="button"
                        role="tab">
                        <i class="fas fa-truck-loading me-2"></i> Hammadde Alımları
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $aktif_tab == 'talep' ? 'active' : ''; ?>" id="pills-talep-tab"
                        data-bs-toggle="pill" data-bs-target="#pills-talep" type="button" role="tab">
                        <i class="fas fa-list-ul me-2"></i> Malzeme Talepleri
                    </button>
                </li>
            </ul>
            <button class="btn btn-primary rounded-pill px-4 py-2 new-talep-btn" data-bs-toggle="modal"
                data-bs-target="#yeniTalepModal">
                <i class="fas fa-plus-circle me-2"></i> Yeni Talep
            </button>
        </div>

        <div class="tab-content" id="pills-tabContent">
            <!-- SEKMELİ: HAMMADDE ALIMLARI -->
            <div class="tab-pane fade <?php echo $aktif_tab == 'hammadde' ? 'show active' : ''; ?>" id="pills-hammadde"
                role="tabpanel">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="table-responsive p-3">
                        <table id="hammaddeBekleyenlerTablo" class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tarih / Plaka</th>
                                    <th>Hammadde / Tedarikçi</th>
                                    <th>Birim Fiyat / Ödeme Tarihi</th>
                                    <th>Kantar Net</th>
                                    <th>Durum</th>
                                    <th class="action-col">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($hammadde_alim_bekleyenler->num_rows > 0): ?>
                                    <?php while ($row = $hammadde_alim_bekleyenler->fetch_assoc()): ?>
                                        <tr>
                                            <td
                                                data-order="<?php echo (int) strtotime((string) ($row["giris_tarihi"] ?? '')); ?>">
                                                <div class="small text-muted mb-1">
                                                    <?php echo date("d.m.Y H:i", strtotime($row["giris_tarihi"])); ?>
                                                </div>
                                                <span class="plaka-badge"><?php echo $row["arac_plaka"]; ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo $row["hammadde_kodu"] . " - " . $row["hammadde_adi"]; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($row["tedarikci"] . (!empty($row["parti_no"]) ? " (Parti: " . $row["parti_no"] . ")" : "")); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="badge bg-light text-dark meta-info-badge">
                                                        Birim Fiyat:
                                                        <?php echo formatBirimFiyatGoster($row["birim_fiyat"] ?? null); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark meta-info-badge">
                                                        Ödeme:
                                                        <?php echo !empty($row["odeme_tarihi"]) ? date("d.m.Y", strtotime($row["odeme_tarihi"])) : '-'; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><span
                                                    class="fw-bold text-primary"><?php echo number_format($row["kantar_net_kg"] ?? 0, 0, ',', '.'); ?>
                                                    kg</span></td>
                                            <td><span class="badge-status status-satina">SATIN ALMA BEKLİYOR</span></td>
                                            <td class="action-col">
                                                <div class="table-action-buttons">
                                                    <button type="button"
                                                        class="btn btn-success btn-sm rounded-pill btn-table-action"
                                                        onclick="kantarModalAc(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['arac_plaka']); ?>', '<?php echo htmlspecialchars($row['hammadde_adi']); ?>', '<?php echo htmlspecialchars($row['tedarikci']); ?>')">
                                                        <i class="fas fa-balance-scale me-1"></i> Kantar Onayla
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm rounded-pill btn-table-action"
                                                        onclick="hammaddeRed(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['arac_plaka']); ?>')">
                                                        <i class="fas fa-times-circle me-1"></i> Reddet
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SEKMELİ: MALZEME TALEPLERİ -->
            <div class="tab-pane fade <?php echo $aktif_tab == 'talep' ? 'show active' : ''; ?>" id="pills-talep"
                role="tabpanel">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="table-responsive p-3">
                        <table id="malzemeTalepleriTablo" class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Talep Eden</th>
                                    <th>Malzeme / Miktar</th>
                                    <th>Aciliyet</th>
                                    <th>Durum</th>
                                    <th class="action-col">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $talep_modal_html = ''; ?>
                                <?php if ($talepler->num_rows > 0): ?>
                                    <?php while ($row = $talepler->fetch_assoc()): ?>
                                        <tr>
                                            <td><small><?php echo date("d.m H:i", strtotime($row["talep_tarihi"])); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width:30px; height:30px; font-size:0.75rem;">
                                                        <?php echo strtoupper(substr($row["talep_eden"], 0, 1)); ?>
                                                    </div>
                                                    <span class="fw-medium text-dark"><?php echo $row["talep_eden"]; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                // Format Kontrol (ID:123|İsim formatındaysa sadece ismi göster)
                                                $mlz_ad = $row["malzeme_adi"];
                                                if (strpos($mlz_ad, 'ID:') === 0) {
                                                    $parts = explode('|', $mlz_ad);
                                                    $mlz_ad = $parts[1] ?? $mlz_ad;
                                                }
                                                ?>
                                                <div class="fw-bold"><?php echo $mlz_ad; ?></div>
                                                <div class="small text-muted">
                                                    <?php echo $row["miktar"] . " " . $row["birim"]; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $acil_class = "secondary";
                                                if ($row["aciliyet"] == 'Acil')
                                                    $acil_class = "warning";
                                                if ($row["aciliyet"] == 'Çok Kritik')
                                                    $acil_class = "danger";
                                                ?>
                                                <span class="badge bg-<?php echo $acil_class; ?> rounded-pill"
                                                    style="font-size: 0.65rem;">
                                                    <?php echo $row["aciliyet"]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status status-<?php echo $row["onay_durum"]; ?>">
                                                    <?php echo strtoupper($row["onay_durum"]); ?>
                                                </span>
                                            </td>
                                            <td class="action-col">
                                                <?php if ($row["onay_durum"] == 'bekliyor'): ?>
                                                    <button class="btn btn-outline-primary btn-sm rounded-pill btn-table-action"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#talepModali<?php echo $row["id"]; ?>">
                                                        <i class="fas fa-cog me-1"></i> Yönet
                                                    </button>
                                                <?php elseif ($row["onay_durum"] == 'onaylandi'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="talep_id" value="<?php echo $row["id"]; ?>">
                                                        <input type="hidden" name="yeni_durum" value="alindi">
                                                        <button type="submit" name="durum_guncelle"
                                                            class="btn btn-outline-success btn-sm rounded-pill btn-table-action">
                                                            <i class="fas fa-box-open me-1"></i> Teslim Alındı
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <?php if ($row["onay_durum"] == 'bekliyor'):
                                            ob_start(); ?>
                                            <div class="modal fade" id="talepModali<?php echo $row["id"]; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold text-dark">Talep Yönetimi
                                                                #<?php echo $row["id"]; ?></h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="mb-4">
                                                                <label class="small text-muted text-uppercase fw-bold mb-2">Talep
                                                                    Detayı</label>
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <strong><?php echo $row["talep_eden"]; ?></strong> ·
                                                                    <?php echo $row["miktar"] . " " . $row["birim"] . " " . $mlz_ad; ?>
                                                                </div>
                                                            </div>
                                                            <form method="post">
                                                                <input type="hidden" name="talep_id"
                                                                    value="<?php echo $row["id"]; ?>">
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-bold">Yönetici Notu</label>
                                                                    <textarea name="yonetici_notu"
                                                                        class="form-control border-focus-primary" rows="3"
                                                                        placeholder="Sipariş verildi, depodan teslim edilecek vb."></textarea>
                                                                </div>
                                                                <div class="d-flex gap-3">
                                                                    <button type="submit" name="durum_guncelle" value="reddedildi"
                                                                        class="btn btn-outline-danger flex-fill py-3 fw-bold rounded-3">
                                                                        <i class="fas fa-times me-1"></i> REDDET
                                                                        <input type="hidden" name="yeni_durum" value="reddedildi"
                                                                            id="red_val_<?php echo $row["id"]; ?>">
                                                                    </button>
                                                                    <button type="submit" name="durum_guncelle" value="onaylandi"
                                                                        class="btn btn-primary flex-fill py-3 fw-bold rounded-3"
                                                                        onclick="document.getElementById('red_val_<?php echo $row["id"]; ?>').value='onaylandi'">
                                                                        <i class="fas fa-check me-1"></i> ONAYLA
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php $talep_modal_html .= ob_get_clean(); ?>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php echo $talep_modal_html; ?>
            </div>
        </div>

        <!-- HAMMADDE İŞLEM GEÇMİŞİ -->
        <div class="mt-4">
            <div class="card border-0 shadow-sm overflow-hidden">
                <div
                    class="card-header bg-white py-3 border-bottom-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-history me-2 text-secondary"></i>
                        <h5 class="mb-0 fw-bold">Son Hammadde Alım İşlemleri</h5>
                    </div>
                    <form method="get" class="d-flex align-items-center gap-2 mb-0">
                        <input type="hidden" name="tab" value="hammadde">
                        <input type="hidden" name="excel_export" value="1">
                        <label for="export_adet" class="small text-muted mb-0">Son</label>
                        <input type="number" name="export_adet" id="export_adet" min="1" max="500" value="10"
                            class="form-control form-control-sm" style="width: 86px;">
                        <span class="small text-muted">kayit</span>
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-file-excel me-1"></i> Excel Export
                        </button>
                    </form>
                </div>
                <div class="table-responsive p-3">
                    <table id="hammaddeGecmisTablo" class="table table-hover align-middle mb-0"
                        style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>Plaka</th>
                                <th>Hammadde / Tedarikçi</th>
                                <th>Miktar</th>
                                <th>İşlem Yapan</th>
                                <th class="text-end">Durum / İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hammadde_alim_gecmisi && $hammadde_alim_gecmisi->num_rows > 0): ?>
                                <?php while ($row = $hammadde_alim_gecmisi->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-muted" data-order="<?php echo strtotime($row["guncelleme_tarihi"]); ?>">
                                            <?php echo date("d.m.Y H:i", strtotime($row["guncelleme_tarihi"])); ?>
                                        </td>
                                        <td><span class="plaka-badge"
                                                style="font-size: 0.7rem; padding: 3px 8px;"><?php echo $row["arac_plaka"]; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold">
                                                <?php echo $row["hammadde_kodu"] . " - " . $row["hammadde_adi"]; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($row["tedarikci"] . (!empty($row["parti_no"]) ? " (Parti: " . $row["parti_no"] . ")" : "")); ?>
                                            </div>
                                            <div class="small mt-1">
                                                <span class="badge bg-light text-dark meta-info-badge">
                                                    Birim Fiyat:
                                                    <?php echo formatBirimFiyatGoster($row["birim_fiyat"] ?? null); ?>
                                                </span>
                                                <span class="badge bg-light text-dark meta-info-badge">
                                                    Ödeme:
                                                    <?php echo !empty($row["odeme_tarihi"]) ? date("d.m.Y", strtotime($row["odeme_tarihi"])) : '-'; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span
                                                class="fw-bold"><?php echo number_format($row["kantar_net_kg"] ?? 0, 0, ',', '.'); ?>
                                                kg</span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fas fa-user-circle text-muted"></i>
                                                <span><?php echo $row["onaylayan_kadi"] ?? 'Sistem'; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($row["onay_durum"] == 'satinalma_red'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 rounded"
                                                    title="<?php echo htmlspecialchars($row['red_aciklama'] ?? ''); ?>">
                                                    <i class="fas fa-times me-1"></i>S.ALMA REDDEDİLDİ
                                                </span>
                                            <?php elseif ($row["asama"] == 'tamamlandi'): ?>
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded">
                                                        <i class="fas fa-check me-1"></i>ONAYLANDI
                                                    </span>
                                                    <a href="sozlesme_yazdir.php?id=<?php echo $row['id']; ?>" target="_blank"
                                                        class="btn btn-sm btn-outline-secondary" title="Sözleşme Yazdır">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 rounded"
                                                    title="<?php echo htmlspecialchars($row['red_aciklama'] ?? ''); ?>">
                                                    <i class="fas fa-times me-1"></i>REDDEDİLDİ
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- YENİ TALEP MODAL -->
    <div class="modal fade" id="yeniTalepModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Yeni Kantar & Muhasebe Talebi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Malzeme Adı</label>
                            <input type="text" name="malzeme_adi" class="form-control border-focus-primary"
                                placeholder="Baskılı Çuval, 50kg Naylon vb." required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold">Miktar</label>
                                <input type="number" step="0.01" name="miktar" class="form-control" placeholder="0.00"
                                    required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Birim</label>
                                <select name="birim" class="form-select">
                                    <option>Adet</option>
                                    <option>Kg</option>
                                    <option>Ton</option>
                                    <option>Koli</option>
                                    <option>Litre</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Aciliyet</label>
                            <select name="aciliyet" class="form-select">
                                <option value="Normal">Normal</option>
                                <option value="Acil">Acil (Üretim etkilenir)</option>
                                <option value="Çok Kritik">Çok Kritik (Üretim durur!)</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="talep_olustur"
                                class="btn btn-primary py-3 fw-bold rounded-3">TALEBİ GÖNDER</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- KANTAR ONAY MODAL -->
    <div class="modal fade" id="kantarOnayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-balance-scale me-2"></i> Satınalma Onayı & Kantar
                        Tartımı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle me-1"></i> Aracı onaylamak ve sisteme kabul etmek için kantar
                        ağırlığını çekin veya girin.
                    </div>
                    <form method="post">
                        <input type="hidden" name="akis_id" id="modal_kantar_akis_id">
                        <input type="hidden" name="kantar_okuma_id" id="modal_kantar_okuma_id" value="">

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="text-muted small mb-1">Araç Plaka</label>
                                <input type="text" class="form-control fw-bold" id="modal_kantar_plaka" readonly>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small mb-1">Hammadde</label>
                                <input type="text" class="form-control" id="modal_kantar_hammadde" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small mb-1">Tedarikçi</label>
                            <input type="text" class="form-control" id="modal_kantar_tedarikci" readonly>
                        </div>

                        <div class="card bg-light border-0 p-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold m-0 text-success">Net Ağırlık (KG)</label>
                                <button type="button" id="btn_kantar_cek"
                                    class="btn btn-sm btn-warning fw-bold shadow-sm" title="Kantardan anlık veri çek">
                                    <i class="fas fa-wifi me-1"></i> Kantardan Çek
                                </button>
                            </div>
                            <input type="number" name="kantar_net_kg" id="modal_kantar_kg"
                                class="form-control form-control-lg text-center fw-bold text-primary" placeholder="0"
                                required min="1" style="font-size: 1.5rem;">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="hammadde_kantar_onayla"
                                class="btn btn-success py-3 fw-bold rounded-3 fs-5 shadow-sm">
                                <i class="fas fa-check-circle me-2"></i> Onayla ve Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <form id="hammaddeIslemForm" method="post" style="display:none;">
        <input type="hidden" name="akis_id" id="h_akis_id">
        <input type="hidden" name="hammadde_alimi_islem" id="h_islem">
        <input type="hidden" name="red_nedeni" id="h_red_nedeni">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        function kantarModalAc(id, plaka, hammadde, tedarikci) {
            document.getElementById('modal_kantar_akis_id').value = id;
            document.getElementById('modal_kantar_plaka').value = plaka;
            document.getElementById('modal_kantar_hammadde').value = hammadde;
            document.getElementById('modal_kantar_tedarikci').value = tedarikci;
            document.getElementById('modal_kantar_kg').value = ''; // Temizle
            document.getElementById('modal_kantar_okuma_id').value = '';

            var modal = new bootstrap.Modal(document.getElementById('kantarOnayModal'));
            modal.show();
        }

        function hammaddeRed(id, plaka) {
            Swal.fire({
                title: 'Alımı Reddet',
                text: plaka + " plakalı aracın alımı neden reddediliyor?",
                input: 'textarea',
                inputPlaceholder: 'Red nedenini buraya yazın...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Reddet',
                cancelButtonText: 'Vazgeç',
                inputValidator: (value) => {
                    if (!value) return 'Red nedeni boş bırakılamaz!'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('h_akis_id').value = id;
                    document.getElementById('h_islem').value = 'reddet';
                    document.getElementById('h_red_nedeni').value = result.value;
                    document.getElementById('hammaddeIslemForm').submit();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // DataTable Konfigürasyonu
            const dtLang = {
                "search": "Hızlı Ara:",
                "lengthMenu": "_MENU_ kayıt göster",
                "info": "_TOTAL_ kayıttan _START_ - _END_ arası",
                "infoEmpty": "Kayıt bulunamadı",
                "infoFiltered": "(_MAX_ kayıt içerisinden)",
                "paginate": { "first": "İlk", "last": "Son", "next": "Sonraki", "previous": "Önceki" },
                "emptyTable": "Gösterilecek veri bulunmuyor"
            };

            $('#hammaddeBekleyenlerTablo').DataTable({
                language: dtLang,
                order: [[0, 'desc']],
                pageLength: 10,
                autoWidth: false,
                columnDefs: [{ targets: -1, orderable: false }]
            });
            $('#malzemeTalepleriTablo').DataTable({
                language: dtLang,
                order: [[0, 'desc']],
                pageLength: 10,
                autoWidth: false,
                columnDefs: [{ targets: -1, orderable: false }]
            });
            $('#hammaddeGecmisTablo').DataTable({
                language: dtLang,
                order: [[0, 'desc']],
                pageLength: 10,
                autoWidth: false,
                columnDefs: [{ targets: -1, orderable: false }]
            });

            // =============================================
            // KANTAR ENTEGRASYONU - Satınalma Modalı İçin
            // =============================================
            $('#btn_kantar_cek').on('click', function () {
                var btn = $(this);
                var girilenPlaka = $('#modal_kantar_plaka').val().trim();

                if (!girilenPlaka) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Plaka Bilgisi Eksik',
                        text: 'Lütfen sayfayı yenileyip tekrar deneyin.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Çekiliyor...');

                $.ajax({
                    url: 'ajax/kantar_cek.php',
                    type: 'GET',
                    data: { plaka: girilenPlaka },
                    dataType: 'json',
                    timeout: 5000,
                    success: function (data) {
                        if (data.basari) {
                            if (typeof data.kantar_okuma_id !== 'undefined') {
                                $('#modal_kantar_okuma_id').val(data.kantar_okuma_id || '');
                            }
                            if (data.net_kg > 0) {
                                $('#modal_kantar_kg').val(data.net_kg);
                            }

                            Swal.fire({
                                icon: 'success',
                                title: '🚛 Kantar Verisi Alındı!',
                                html:
                                    '<table class="table table-sm text-start mt-2">' +
                                    '<tr><td><b>Plaka</b></td><td>' + data.plaka + '</td></tr>' +
                                    '<tr><td><b>Firma</b></td><td>' + data.firma + '</td></tr>' +
                                    '<tr><td><b>Kaynak</b></td><td>' + (data.kaynak || '-') + '</td></tr>' +
                                    '<tr><td><b>Net Ağırlık</b></td><td><b class="text-success fs-5">' + data.net_kg.toLocaleString('tr-TR') + ' kg</b></td></tr>' +
                                    '</table>',
                                confirmButtonText: 'Tamam',
                                confirmButtonColor: '#10b981',
                                timer: 5000
                            });
                        } else {
                            var hataMesaji = data.hata || 'Kantar verisi alınamadı.';
                            if (data.hata_kodu === 'PLAKA_ESLESMEDI') {
                                hataMesaji = 'Bu plakaya ait uygun kantar kaydı bulunamadı.';
                            } else if (data.hata_kodu === 'KARARSIZ_VERI') {
                                hataMesaji = 'Kantar verisi o an güncelleniyordu. Lütfen tekrar çekin.';
                            } else if (data.hata_kodu === 'KANTAR_ERISIM') {
                                hataMesaji = 'Kantar cihazına erişilemiyor. Ağ bağlantısını kontrol edin.';
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Kantar Bağlantı Hatası',
                                text: hataMesaji,
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Bağlantı Hatası',
                            text: 'Kantar sunucusuna ulaşılamadı. Ağ bağlantısını kontrol edin.',
                            confirmButtonColor: '#ef4444'
                        });
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fas fa-wifi me-1"></i> Kantardan Çek');
                    }
                });
            });

            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success', title: '<?php echo addslashes($mesaj); ?>',
                    showConfirmButton: false, timer: 4000, timerProgressBar: true
                });
            <?php endif; ?>
            <?php if (!empty($hata)): ?>
                Swal.fire({ icon: 'error', title: 'Hata!', text: '<?php echo addslashes($hata); ?>', confirmButtonColor: '#0f172a' });
            <?php endif; ?>
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>