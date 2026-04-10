<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) { header("Location: login.php"); exit; }
sayfaErisimKontrol($baglanti);

$mesaj = "";
$hata = "";
$force_tab = '';

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

// --- SİLO AKTARMA İŞLEMİ ---
if (isset($_POST["silo_aktarma_kaydet"])) {
    $force_tab = 'bekleyen';
    $giris_id = (int)$_POST["giris_id"];
    $dagitim_silo_ids = $_POST['dagitim_silo_id'] ?? [];
    $dagitim_kgs = $_POST['dagitim_kg'] ?? [];
    $hata_silo = "";

    if ($giris_id <= 0) $hata_silo = "Geçersiz giriş ID.";
    
    if(empty($hata_silo)){
        $mevcut = $baglanti->query("
            SELECT hg.id, hg.parti_no, hg.miktar_kg, h.ad as hammadde_adi, h.hammadde_kodu,
                   (SELECT la.hektolitre FROM lab_analizleri la WHERE la.hammadde_giris_id = hg.id ORDER BY la.id DESC LIMIT 1) as lab_hektolitre,
                   h.yogunluk_kg_m3
            FROM hammadde_girisleri hg
            LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
            WHERE hg.id = $giris_id
        ")->fetch_assoc();
        
        if(!$mevcut) $hata_silo = "Kayıt bulunamadı.";
        else {
            $referans_kg = (float)$mevcut['miktar_kg'];
            $hl = (float)$mevcut['lab_hektolitre'];
            $yogunluk = ($hl > 0) ? ($hl * 10) : (float)$mevcut['yogunluk_kg_m3'];
            if($yogunluk <= 0) $yogunluk = 780;
            
            $dagitimlar = [];
            $toplam = 0.0;
            $ilk_silo = 0;
            $max = max(count($dagitim_silo_ids), count($dagitim_kgs));
            for($i=0; $i<$max; $i++) {
                $sid= (int)($dagitim_silo_ids[$i]??0);
                $kg = (float)($dagitim_kgs[$i]??0);
                if($sid<=0 || $kg<=0) { $hata_silo="Geçersiz silo dağıtımı!"; break; }
                if($ilk_silo==0) $ilk_silo=$sid;
                if(!isset($dagitimlar[$sid])) $dagitimlar[$sid]=0.0;
                $dagitimlar[$sid] += $kg;
                $toplam += $kg;
            }
            
            if(empty($hata_silo) && count($dagitimlar)==0) $hata_silo = "Silo dağıtımı girmediniz.";
            if(empty($hata_silo) && abs($toplam - $referans_kg) > 0.01) $hata_silo = "Dağıtım toplamı kantar değerine ($referans_kg KG) eşit olmalıdır.";
            
            // Backend Silo Kapasite ve İzin Kontrolleri
            if(empty($hata_silo)) {
                $silo_ids = array_keys($dagitimlar);
                $id_list = implode(',', $silo_ids);
                $silo_kayitlari = $baglanti->query("SELECT id, silo_adi, kapasite_m3, doluluk_m3, izin_verilen_hammadde_kodlari FROM silolar WHERE id IN ($id_list)");
                $silo_map = [];
                while($s = $silo_kayitlari->fetch_assoc()) $silo_map[(int)$s['id']] = $s;

                foreach($dagitimlar as $sid => $mkg) {
                    if(!isset($silo_map[$sid])) { $hata_silo = "Silo bulunamadı (ID: $sid)."; break; }
                    $s = $silo_map[$sid];
                    $bos_m3 = max(0, (float)$s['kapasite_m3'] - (float)$s['doluluk_m3']);
                    $max_kg = $bos_m3 * $yogunluk;
                    if(($mkg - $max_kg) > 0.01) { $hata_silo = "{$s['silo_adi']} silosunda yeterli boşluk yok (Maks: ".number_format($max_kg,0)." KG)."; break; }
                    
                    $izinli_raw = trim((string)$s['izin_verilen_hammadde_kodlari']);
                    if(!empty($izinli_raw)) {
                        $izinli_list = json_decode($izinli_raw, true);
                        if(is_array($izinli_list) && count($izinli_list) > 0 && !in_array($mevcut['hammadde_kodu'], $izinli_list, true)) {
                            $hata_silo = "{$s['silo_adi']} silosuna {$mevcut['hammadde_kodu']} kodlu hammadde girişi izinli değil."; break;
                        }
                    }
                }
            }

            if(empty($hata_silo)) {
                $baglanti->begin_transaction();
                $islem_ok = true;
                
                $guncel_m3 = $referans_kg / $yogunluk;
                $p_no = $baglanti->real_escape_string($mevcut['parti_no']);
                $h_turu = $baglanti->real_escape_string($mevcut['hammadde_adi']);
                
                if(!$baglanti->query("UPDATE hammadde_girisleri SET giris_m3=$guncel_m3, silo_id=$ilk_silo WHERE id=$giris_id")) {
                    $islem_ok = false; $hata_silo = "Giriş güncellenemedi.";
                }
                
                if ($islem_ok) {
                    foreach($dagitimlar as $sid => $mkg) {
                        $f_sql = "INSERT INTO silo_stok_detay (silo_id, parti_kodu, hammadde_turu, giren_miktar_kg, kalan_miktar_kg, giris_tarihi, durum) 
                                  VALUES ($sid, '$p_no', '$h_turu', $mkg, $mkg, NOW(), 'aktif')";
                        if(!$baglanti->query($f_sql)) { $islem_ok = false; $hata_silo = "FIFO eklenemedi."; break; }
                        
                        $m3_ekle = $mkg / $yogunluk;
                        if(!$baglanti->query("UPDATE silolar SET doluluk_m3 = doluluk_m3 + $m3_ekle WHERE id=$sid")) {
                            $islem_ok = false; $hata_silo = "Doluluk işlenemedi."; break;
                        }
                    }
                }
                
                if($islem_ok) {
                    $baglanti->commit();
                    $mesaj = "Silo aktarımı başarıyla tamamlandı (Parti: {$mevcut['parti_no']})!";
                    systemLogKaydet($baglanti, 'INSERT', 'Silo Aktarma', "Silo dağıtımı. Parti: {$mevcut['parti_no']} Toplam: {$referans_kg} KG");
                } else {
                    $baglanti->rollback();
                    $hata = "Hata: " . $hata_silo;
                }
            } else {
                $hata = $hata_silo;
            }
        }
    }
}

$aktif_tab = $force_tab ?: ($_GET['tab'] ?? 'bekleyen');
if (!in_array($aktif_tab, ['bekleyen', 'gecmis'], true)) {
    $aktif_tab = 'bekleyen';
}

$excel_export_istek = isset($_GET['excel_export']) && $_GET['excel_export'] === '1';
if ($excel_export_istek) {
    $export_adet = isset($_GET['export_adet']) ? (int) $_GET['export_adet'] : 10;
    $export_adet = max(1, min(500, $export_adet));

    $excel_sorgu = "
        SELECT
            hg.tarih,
            hg.arac_plaka,
            hg.tedarikci,
            hg.parti_no,
            hg.miktar_kg,
            h.hammadde_kodu,
            h.ad AS hammadde_adi,
            MAX(ssd.giris_tarihi) AS son_aktarim_tarihi,
            SUM(ssd.giren_miktar_kg) AS toplam_aktarim_kg,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(s.silo_adi, CONCAT('Silo-', ssd.silo_id)),
                    ': ',
                    REPLACE(FORMAT(ssd.giren_miktar_kg, 0), ',', '.'),
                    ' KG'
                )
                ORDER BY ssd.giris_tarihi DESC, ssd.id DESC
                SEPARATOR ' | '
            ) AS silo_dagilim_ozeti
        FROM hammadde_girisleri hg
        INNER JOIN (
            SELECT hka1.hammadde_giris_id, hka1.asama, hka1.onay_durum
            FROM hammadde_kabul_akisi hka1
            INNER JOIN (
                SELECT hammadde_giris_id, MAX(id) AS max_id
                FROM hammadde_kabul_akisi
                GROUP BY hammadde_giris_id
            ) hka_max ON hka_max.max_id = hka1.id
        ) hka ON hka.hammadde_giris_id = hg.id
        INNER JOIN silo_stok_detay ssd ON ssd.parti_kodu = hg.parti_no
        LEFT JOIN silolar s ON s.id = ssd.silo_id
        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
        WHERE hg.silo_id IS NOT NULL
          AND hg.silo_id > 0
          AND hka.asama = 'tamamlandi'
          AND hka.onay_durum = 'onaylandi'
        GROUP BY hg.id, hg.tarih, hg.arac_plaka, hg.tedarikci, hg.parti_no, hg.miktar_kg, h.hammadde_kodu, h.ad
        ORDER BY son_aktarim_tarihi DESC, hg.id DESC
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
        'Aktarim Tarihi',
        'Giris Tarihi',
        'Plaka',
        'Tedarikci',
        'Hammadde Kodu',
        'Hammadde',
        'Parti No',
        'Kantar Miktari (kg)',
        'Toplam Aktarim (kg)',
        'Aktarilan Silolar'
    ];

    while ($row = $excel_sonuc->fetch_assoc()) {
        $satirlar[] = [
            !empty($row['son_aktarim_tarihi']) ? date('d.m.Y H:i', strtotime($row['son_aktarim_tarihi'])) : '-',
            !empty($row['tarih']) ? date('d.m.Y H:i', strtotime($row['tarih'])) : '-',
            $row['arac_plaka'] ?? '-',
            $row['tedarikci'] ?? '-',
            $row['hammadde_kodu'] ?? '-',
            $row['hammadde_adi'] ?? '-',
            $row['parti_no'] ?? '-',
            number_format((float)($row['miktar_kg'] ?? 0), 0, ',', '.'),
            number_format((float)($row['toplam_aktarim_kg'] ?? 0), 0, ',', '.'),
            $row['silo_dagilim_ozeti'] ?? '-'
        ];
    }

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'erp_xlsx_');
    if ($tmpXlsx === false || !xlsxDosyaOlustur($tmpXlsx, $satirlar)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "XLSX olusturulamadi. Sunucuda ZipArchive uzantisi aktif olmalidir.";
        exit;
    }

    $dosya_adi = "silo_aktarma_gecmisi_" . date('Y-m-d_H-i-s') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');
    header('Content-Length: ' . filesize($tmpXlsx));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tmpXlsx);
    @unlink($tmpXlsx);
    exit;
}

$bekleyen_araclar_sorgu = "
    SELECT hg.*, h.hammadde_kodu, h.ad as hammadde_adi, h.yogunluk_kg_m3,
           la.hektolitre as lab_hektolitre, la.nem as lab_nem, la.protein as lab_protein,
           la.nisasta as lab_nisasta, la.sertlik as lab_sertlik, la.gluten as lab_gluten,
           la.index_degeri as lab_index_degeri, la.sedimantasyon as lab_sedimantasyon,
           la.gecikmeli_sedimantasyon as lab_gecikmeli_sedimantasyon, la.fn as lab_fn,
           la.doker_orani as lab_doker_orani, la.laborant as lab_laborant, la.tarih as lab_tarih
    FROM hammadde_girisleri hg
    INNER JOIN (
        SELECT hka1.hammadde_giris_id, hka1.asama, hka1.onay_durum
        FROM hammadde_kabul_akisi hka1
        INNER JOIN (
            SELECT hammadde_giris_id, MAX(id) AS max_id
            FROM hammadde_kabul_akisi
            GROUP BY hammadde_giris_id
        ) hka_max ON hka_max.max_id = hka1.id
    ) hka ON hka.hammadde_giris_id = hg.id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN (
        SELECT la1.*
        FROM lab_analizleri la1
        INNER JOIN (
            SELECT hammadde_giris_id, MAX(id) AS max_id
            FROM lab_analizleri
            GROUP BY hammadde_giris_id
        ) la_max ON la_max.max_id = la1.id
    ) la ON la.hammadde_giris_id = hg.id
    WHERE hg.analiz_yapildi > 0
      AND (hg.silo_id IS NULL OR hg.silo_id = 0)
      AND hka.asama = 'tamamlandi'
      AND hka.onay_durum = 'onaylandi'
    ORDER BY hg.tarih ASC, hg.id ASC
";
$bekleyen_araclar = $baglanti->query($bekleyen_araclar_sorgu);
$bekleyen_sayisi = $bekleyen_araclar ? (int)$bekleyen_araclar->num_rows : 0;

$tamamlanan_sayisi_sorgu = "
    SELECT COUNT(DISTINCT hg.id) AS toplam
    FROM hammadde_girisleri hg
    INNER JOIN (
        SELECT hka1.hammadde_giris_id, hka1.asama, hka1.onay_durum
        FROM hammadde_kabul_akisi hka1
        INNER JOIN (
            SELECT hammadde_giris_id, MAX(id) AS max_id
            FROM hammadde_kabul_akisi
            GROUP BY hammadde_giris_id
        ) hka_max ON hka_max.max_id = hka1.id
    ) hka ON hka.hammadde_giris_id = hg.id
    WHERE hg.silo_id IS NOT NULL
      AND hg.silo_id > 0
      AND hka.asama = 'tamamlandi'
      AND hka.onay_durum = 'onaylandi'
";
$tamamlanan_sayisi = 0;
$tamamlanan_sayisi_res = $baglanti->query($tamamlanan_sayisi_sorgu);
if ($tamamlanan_sayisi_res && ($tamamlanan_row = $tamamlanan_sayisi_res->fetch_assoc())) {
    $tamamlanan_sayisi = (int)($tamamlanan_row['toplam'] ?? 0);
}

$gecmis_aktarimlar_sorgu = "
    SELECT
        hg.id,
        hg.tarih,
        hg.arac_plaka,
        hg.tedarikci,
        hg.parti_no,
        hg.miktar_kg,
        h.hammadde_kodu,
        h.ad AS hammadde_adi,
        MAX(la.hektolitre) AS lab_hektolitre,
        MAX(la.nem) AS lab_nem,
        MAX(la.protein) AS lab_protein,
        MAX(la.nisasta) AS lab_nisasta,
        MAX(la.sertlik) AS lab_sertlik,
        MAX(la.gluten) AS lab_gluten,
        MAX(la.index_degeri) AS lab_index_degeri,
        MAX(la.sedimantasyon) AS lab_sedimantasyon,
        MAX(la.gecikmeli_sedimantasyon) AS lab_gecikmeli_sedimantasyon,
        MAX(la.fn) AS lab_fn,
        MAX(la.doker_orani) AS lab_doker_orani,
        MAX(la.laborant) AS lab_laborant,
        MAX(la.tarih) AS lab_tarih,
        MAX(ssd.giris_tarihi) AS son_aktarim_tarihi,
        SUM(ssd.giren_miktar_kg) AS toplam_aktarim_kg,
        GROUP_CONCAT(
            CONCAT(
                COALESCE(s.silo_adi, CONCAT('Silo-', ssd.silo_id)),
                ': ',
                REPLACE(FORMAT(ssd.giren_miktar_kg, 0), ',', '.'),
                ' KG'
            )
            ORDER BY ssd.giris_tarihi DESC, ssd.id DESC
            SEPARATOR ' | '
        ) AS silo_dagilim_ozeti
    FROM hammadde_girisleri hg
    INNER JOIN (
        SELECT hka1.hammadde_giris_id, hka1.asama, hka1.onay_durum
        FROM hammadde_kabul_akisi hka1
        INNER JOIN (
            SELECT hammadde_giris_id, MAX(id) AS max_id
            FROM hammadde_kabul_akisi
            GROUP BY hammadde_giris_id
        ) hka_max ON hka_max.max_id = hka1.id
    ) hka ON hka.hammadde_giris_id = hg.id
    INNER JOIN silo_stok_detay ssd ON ssd.parti_kodu = hg.parti_no
    LEFT JOIN silolar s ON s.id = ssd.silo_id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN (
        SELECT la1.*
        FROM lab_analizleri la1
        INNER JOIN (
            SELECT hammadde_giris_id, MAX(id) AS max_id
            FROM lab_analizleri
            GROUP BY hammadde_giris_id
        ) la_max ON la_max.max_id = la1.id
    ) la ON la.hammadde_giris_id = hg.id
    WHERE hg.silo_id IS NOT NULL
      AND hg.silo_id > 0
      AND hka.asama = 'tamamlandi'
      AND hka.onay_durum = 'onaylandi'
    GROUP BY hg.id, hg.tarih, hg.arac_plaka, hg.tedarikci, hg.parti_no, hg.miktar_kg, h.hammadde_kodu, h.ad
    ORDER BY son_aktarim_tarihi DESC, hg.id DESC
";
$gecmis_aktarimlar = $baglanti->query($gecmis_aktarimlar_sorgu);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hammadde Silo Aktarma - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { font-family:'Inter',system-ui,sans-serif; background:#f1f5f9!important; }
        .page-header { background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); color:#fff; border-radius:1.25rem; margin-top:1.25rem; margin-bottom:1.4rem; padding:1.55rem 1.7rem; box-shadow:0 16px 28px -14px rgba(15,23,42,.55); position:relative; overflow:hidden; }
        .page-header::before { content:""; position:absolute; top:-65%; right:-10%; width:440px; height:440px; border-radius:50%; background:radial-gradient(circle,rgba(245,158,11,.2) 0%,rgba(245,158,11,0) 72%); pointer-events:none; }
        .header-stats { position:relative; z-index:1; }
        .header-stat-box { min-width:170px; padding:.72rem .95rem; border-radius:.8rem; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); backdrop-filter:blur(4px); display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .header-stat-box:hover { transform:translateY(-6px); box-shadow:0 16px 28px -18px rgba(15,23,42,.9); border-color:rgba(255,255,255,.4); }
        .header-stat-label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:rgba(255,255,255,.78); margin-bottom:.2rem; text-align:center; }
        .header-stat-value { font-size:1.35rem; font-weight:700; line-height:1; color:#fff; }
        .nav-tabs .nav-link { font-weight:600; color:#64748b; border:none; padding:14px 24px; border-radius:12px 12px 0 0; transition:all .3s; font-size:.95rem; }
        .nav-tabs .nav-link.active { color:#fff; background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); border:none; }
        .nav-tabs .nav-link:hover:not(.active) { background:#e2e8f0; color:#1e293b; }
        .tab-content { background:#fff; border-radius:0 0 15px 15px; padding:0; box-shadow:0 4px 15px rgba(0,0,0,.08); }
        .tab-content .tab-pane { padding:20px; }
        .history-silo-list { min-width:260px; }
        .history-silo-item { font-size:.83rem; line-height:1.35; color:#334155; margin-bottom:.2rem; }
        .history-silo-item:last-child { margin-bottom:0; }
        .karisim-lab-btn .fw-bold { transition:color .2s ease; }
        .karisim-lab-btn:hover .fw-bold { color:#0b5ed7 !important; text-decoration:underline; }
        @media(max-width:992px) { .header-stat-box { min-width:150px; } }
        @media(max-width:768px) {
            .nav-tabs .nav-link { padding:10px 16px; font-size:.82rem; }
            .tab-content .tab-pane { padding:12px; }
            .header-stats { margin-top:.75rem; }
            .header-stat-box { min-width:138px; }
            .header-stat-value { font-size:1.15rem; }
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>
    
    <div class="container-fluid px-md-4 pb-4" style="max-width:1680px;margin:0 auto">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="fw-bold mb-1"><i class="fas fa-random me-2"></i>Hammadde Silo Aktarma</h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.78)">Hammaddeyi kantar girisinden ilgili silolara aktarin</p>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-2 header-stats">
                        <div class="header-stat-box">
                            <div class="header-stat-label">Bekleyen Hammadde</div>
                            <div class="header-stat-value"><?php echo number_format($bekleyen_sayisi, 0, ',', '.'); ?></div>
                        </div>
                        <div class="header-stat-box">
                            <div class="header-stat-label">Siloya Aktarilan Toplam</div>
                            <div class="header-stat-value"><?php echo number_format($tamamlanan_sayisi, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="siloAktarmaTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="silo_aktarma.php?tab=bekleyen" class="nav-link <?php echo $aktif_tab === 'bekleyen' ? 'active' : ''; ?>" id="bekleyen-tab" role="tab">
                    <i class="fas fa-truck-loading me-1 text-warning"></i> Silo Aktarma Bekleyen Araclar
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="silo_aktarma.php?tab=gecmis" class="nav-link <?php echo $aktif_tab === 'gecmis' ? 'active' : ''; ?>" id="gecmis-tab" role="tab">
                    <i class="fas fa-clock-rotate-left me-1 text-primary"></i> Gecmis Silo Aktarmalari
                </a>
            </li>
        </ul>

        <div class="tab-content" id="siloAktarmaTabsContent">
            <div class="tab-pane <?php echo $aktif_tab === 'bekleyen' ? 'show active' : 'd-none'; ?>" id="bekleyenTabPane" role="tabpanel" aria-labelledby="bekleyen-tab" tabindex="0">
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-truck-loading me-2 text-warning"></i>Silo Aktarma Bekleyen Araclar</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Tarih</th>
                                        <th>Plaka / Tedarikci</th>
                                        <th>Hammadde / Parti</th>
                                        <th>Miktar</th>
                                        <th>Analiz Durumu</th>
                                        <th class="text-end pe-4">Islem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($bekleyen_araclar && $bekleyen_sayisi > 0): ?>
                                        <?php while ($row = $bekleyen_araclar->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold"><?php echo date('d.m.Y', strtotime($row['tarih'])); ?></div>
                                                    <div class="text-muted small"><?php echo date('H:i', strtotime($row['tarih'])); ?></div>
                                                </td>
                                                <td>
                                                    <div class="badge bg-secondary mb-1"><?php echo htmlspecialchars($row['arac_plaka']); ?></div>
                                                    <div class="small fw-semibold"><?php echo htmlspecialchars($row['tedarikci']); ?></div>
                                                </td>
                                                <td>
                                                    <?php
                                                        $bekleyen_lab_payload = htmlspecialchars(json_encode([
                                                            'hektolitre' => $row['lab_hektolitre'] ?? null,
                                                            'nem' => $row['lab_nem'] ?? null,
                                                            'protein' => $row['lab_protein'] ?? null,
                                                            'nisasta' => $row['lab_nisasta'] ?? null,
                                                            'sertlik' => $row['lab_sertlik'] ?? null,
                                                            'gluten' => $row['lab_gluten'] ?? null,
                                                            'index_degeri' => $row['lab_index_degeri'] ?? null,
                                                            'sedimantasyon' => $row['lab_sedimantasyon'] ?? null,
                                                            'gecikmeli_sedimantasyon' => $row['lab_gecikmeli_sedimantasyon'] ?? null,
                                                            'fn' => $row['lab_fn'] ?? null,
                                                            'doker_orani' => $row['lab_doker_orani'] ?? null,
                                                            'laborant' => $row['lab_laborant'] ?? null,
                                                            'tarih' => $row['lab_tarih'] ?? null
                                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <button type="button" class="btn btn-link p-0 text-start text-decoration-none karisim-lab-btn"
                                                        data-hammadde-kodu="<?php echo htmlspecialchars($row['hammadde_kodu'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-parti-kodu="<?php echo htmlspecialchars($row['parti_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-lab="<?php echo $bekleyen_lab_payload; ?>">
                                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($row['hammadde_adi']); ?></div>
                                                        <div class="small text-muted"><i class="fas fa-barcode me-1"></i><?php echo htmlspecialchars($row['parti_no']); ?></div>
                                                    </button>
                                                </td>
                                                <td>
                                                    <div class="fw-bold fs-6"><?php echo number_format($row['miktar_kg'], 0, ',', '.'); ?> <small class="text-muted fw-normal">KG</small></div>
                                                </td>
                                                <td>
                                                    <?php if ($row['analiz_yapildi'] == 2): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Tam Analiz</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><i class="fas fa-edit me-1"></i>Kismi Analiz</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#siloAktarmaModal"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-plaka="<?php echo htmlspecialchars($row['arac_plaka']); ?>"
                                                        data-tedarikci="<?php echo htmlspecialchars($row['tedarikci']); ?>"
                                                        data-hammadde="<?php echo htmlspecialchars($row['hammadde_adi']); ?>"
                                                        data-kodu="<?php echo htmlspecialchars($row['hammadde_kodu']); ?>"
                                                        data-kg="<?php echo $row['miktar_kg']; ?>"
                                                        data-yogunluk="<?php echo floatval($row['lab_hektolitre']) > 0 ? (floatval($row['lab_hektolitre']) * 10) : floatval($row['yogunluk_kg_m3']); ?>"
                                                        >
                                                        <i class="fas fa-exchange-alt me-1"></i> Siloya Aktar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-check-circle fa-2x mb-3 text-success opacity-50 d-block"></i>
                                                Silo aktarimi bekleyen arac bulunmuyor.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane <?php echo $aktif_tab === 'gecmis' ? 'show active' : 'd-none'; ?>" id="gecmisTabPane" role="tabpanel" aria-labelledby="gecmis-tab" tabindex="0">
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Gecmis Silo Aktarmalari</h5>
                        <form method="get" class="d-flex align-items-center gap-2 mb-0">
                            <input type="hidden" name="tab" value="gecmis">
                            <input type="hidden" name="excel_export" value="1">
                            <label for="export_adet" class="small text-muted mb-0">Son</label>
                            <input type="number" name="export_adet" id="export_adet" min="1" max="500" value="<?php echo isset($_GET['export_adet']) ? max(1, min(500, (int)$_GET['export_adet'])) : 10; ?>" class="form-control form-control-sm" style="width: 86px;">
                            <span class="small text-muted">kayit</span>
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-file-excel me-1"></i> Excel Export
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Aktarim Tarihi</th>
                                        <th>Plaka / Tedarikci</th>
                                        <th>Hammadde / Parti</th>
                                        <th>Aktarilan Silo(lar)</th>
                                        <th class="text-end pe-4">Toplam Aktarim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($gecmis_aktarimlar && $gecmis_aktarimlar->num_rows > 0): ?>
                                        <?php while ($gecmis = $gecmis_aktarimlar->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <?php $aktarim_tarih = $gecmis['son_aktarim_tarihi'] ?: $gecmis['tarih']; ?>
                                                    <div class="fw-bold"><?php echo date('d.m.Y', strtotime($aktarim_tarih)); ?></div>
                                                    <div class="text-muted small"><?php echo date('H:i', strtotime($aktarim_tarih)); ?></div>
                                                </td>
                                                <td>
                                                    <div class="badge bg-secondary mb-1"><?php echo htmlspecialchars($gecmis['arac_plaka']); ?></div>
                                                    <div class="small fw-semibold"><?php echo htmlspecialchars($gecmis['tedarikci']); ?></div>
                                                </td>
                                                <td>
                                                    <?php
                                                        $gecmis_lab_payload = htmlspecialchars(json_encode([
                                                            'hektolitre' => $gecmis['lab_hektolitre'] ?? null,
                                                            'nem' => $gecmis['lab_nem'] ?? null,
                                                            'protein' => $gecmis['lab_protein'] ?? null,
                                                            'nisasta' => $gecmis['lab_nisasta'] ?? null,
                                                            'sertlik' => $gecmis['lab_sertlik'] ?? null,
                                                            'gluten' => $gecmis['lab_gluten'] ?? null,
                                                            'index_degeri' => $gecmis['lab_index_degeri'] ?? null,
                                                            'sedimantasyon' => $gecmis['lab_sedimantasyon'] ?? null,
                                                            'gecikmeli_sedimantasyon' => $gecmis['lab_gecikmeli_sedimantasyon'] ?? null,
                                                            'fn' => $gecmis['lab_fn'] ?? null,
                                                            'doker_orani' => $gecmis['lab_doker_orani'] ?? null,
                                                            'laborant' => $gecmis['lab_laborant'] ?? null,
                                                            'tarih' => $gecmis['lab_tarih'] ?? null
                                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <button type="button" class="btn btn-link p-0 text-start text-decoration-none karisim-lab-btn"
                                                        data-hammadde-kodu="<?php echo htmlspecialchars($gecmis['hammadde_kodu'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-parti-kodu="<?php echo htmlspecialchars($gecmis['parti_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-lab="<?php echo $gecmis_lab_payload; ?>">
                                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($gecmis['hammadde_adi']); ?></div>
                                                        <div class="small text-muted"><i class="fas fa-barcode me-1"></i><?php echo htmlspecialchars($gecmis['parti_no']); ?></div>
                                                    </button>
                                                </td>
                                                <td class="history-silo-list">
                                                    <?php
                                                        $silo_dagilimlari = array_filter(array_map('trim', explode('|', (string)($gecmis['silo_dagilim_ozeti'] ?? ''))));
                                                        if (count($silo_dagilimlari) > 0):
                                                            foreach ($silo_dagilimlari as $dagilim):
                                                    ?>
                                                        <div class="history-silo-item"><?php echo htmlspecialchars($dagilim); ?></div>
                                                    <?php
                                                            endforeach;
                                                        else:
                                                    ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="fw-bold fs-6"><?php echo number_format((float)($gecmis['toplam_aktarim_kg'] ?? 0), 0, ',', '.'); ?> <small class="text-muted fw-normal">KG</small></div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-3 opacity-50 d-block"></i>
                                                Gecmis silo aktarim kaydi bulunmuyor.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- SİLO AKTARIM MODAL -->
        <div class="modal fade" id="siloAktarmaModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <h5 class="modal-title fw-bold"><i class="fas fa-random me-2"></i>Silo Aktarım İşlemi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-warning py-2 small border-0 bg-warning bg-opacity-10 text-dark">
                            <i class="fas fa-info-circle me-1 text-warning"></i> Lab onayı almış aracın malzemesini uygun bir siloya veya birden fazla siloya bölebilirsiniz.
                        </div>
                        
                        <form method="post" id="siloAktarmaForm">
                            <input type="hidden" name="giris_id" id="aktarma_giris_id">
                            <input type="hidden" name="hammadde_adi" id="aktarma_hammadde_adi">
                            <input type="hidden" id="aktarma_yogunluk_kg_m3">
                            <input type="hidden" id="aktarma_hammadde_kodu">

                            <div class="row g-3 mb-4 bg-light p-3 rounded-3">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Araç Plaka</label>
                                    <div class="fw-bold" id="aktarma_plaka">-</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Tedarikçi</label>
                                    <div class="fw-bold" id="aktarma_tedarikci">-</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Hammadde</label>
                                    <div class="fw-bold text-primary" id="aktarma_hammadde">-</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Kantar (KG)</label>
                                    <div class="fw-bold fs-5 text-success" id="aktarma_kantar_kg">0</div>
                                    <input type="hidden" id="aktarma_kantar_kg_val" value="0">
                                </div>
                            </div>

                            <h6 class="fw-bold mb-3 border-bottom pb-2">Silo Dağılımı</h6>
                            <div id="aktarma_silo_alani">
                                <div class="row g-2 mb-2 aktarma-silo-satir">
                                    <div class="col-md-6">
                                        <select name="dagitim_silo_id[]" class="form-select dagitim-silo-select" required>
                                            <option value="">Silo Seç...</option>
                                            <?php 
                                                $silolar = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' ORDER BY id ASC");
                                                while ($s = $silolar->fetch_assoc()):
                                                    $bos_m3 = max(0, $s['kapasite_m3'] - $s['doluluk_m3']);
                                            ?>
                                                <option value="<?php echo $s['id']; ?>" data-silo-adi="<?php echo htmlspecialchars($s['silo_adi'] ?? '', ENT_QUOTES); ?>" data-bos-m3="<?php echo $bos_m3; ?>" data-izinli="<?php echo htmlspecialchars($s['izin_verilen_hammadde_kodlari'] ?? '', ENT_QUOTES); ?>">
                                                    <?php echo htmlspecialchars($s['silo_adi'] ?? ''); ?> (Boş: <?php echo number_format($bos_m3, 1); ?> m³)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <input type="number" name="dagitim_kg[]" class="form-control dagitim-kg-input" step="0.01" min="1" placeholder="Miktar" required>
                                            <span class="input-group-text bg-light">KG</span>
                                        </div>
                                    </div>
                                    <div class="col-md-1"></div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2 mb-4" onclick="yeniAktarmaSatiriEkle()">
                                <i class="fas fa-plus me-1"></i> Yeni Silo Ekle
                            </button>

                            <!-- Hesaplama Özeti -->
                            <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded border">
                                <div>
                                    <span class="text-muted small d-block mb-1">Dağıtılan Toplam</span>
                                    <span class="fs-5 fw-bold" id="toplamDagitilanKg">0.00</span> <small class="text-muted">KG</small>
                                </div>
                                <div class="text-end">
                                    <span class="text-muted small d-block mb-1">Kalan</span>
                                    <span class="fs-5 fw-bold text-danger" id="kalanDağıtılacakKg">0.00</span> <small class="text-muted">KG</small>
                                </div>
                            </div>

                            <div class="mt-4 d-grid">
                                <button type="submit" name="silo_aktarma_kaydet" class="btn btn-warning btn-lg fw-bold text-dark" id="btnAktarmaKaydet">
                                    <i class="fas fa-check-circle me-2"></i>Aktarımı Tamamla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="labAnalizModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-flask me-2"></i>Parti Lab Analizi</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div><strong>Hammadde Kodu:</strong> <span id="lab_modal_hammadde_kodu">-</span></div>
                            <div><strong>Parti Kodu:</strong> <span id="lab_modal_parti_kodu">-</span></div>
                        </div>
                        <div id="lab_modal_kayit_yok" class="alert alert-warning d-none mb-3">
                            Bu parti icin lab analizi bulunamadi.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <tbody>
                                    <tr>
                                        <th style="width: 25%;">Hektolitre</th>
                                        <td id="lab_modal_hektolitre">-</td>
                                        <th style="width: 25%;">Nem</th>
                                        <td id="lab_modal_nem">-</td>
                                    </tr>
                                    <tr>
                                        <th>Protein</th>
                                        <td id="lab_modal_protein">-</td>
                                        <th>Nisasta</th>
                                        <td id="lab_modal_nisasta">-</td>
                                    </tr>
                                    <tr>
                                        <th>Sertlik</th>
                                        <td id="lab_modal_sertlik">-</td>
                                        <th>Gluten</th>
                                        <td id="lab_modal_gluten">-</td>
                                    </tr>
                                    <tr>
                                        <th>Index</th>
                                        <td id="lab_modal_index">-</td>
                                        <th>Sedimantasyon</th>
                                        <td id="lab_modal_sedimantasyon">-</td>
                                    </tr>
                                    <tr>
                                        <th>Gecikmeli Sedimantasyon</th>
                                        <td id="lab_modal_gecikmeli_sedimantasyon">-</td>
                                        <th>FN</th>
                                        <td id="lab_modal_fn">-</td>
                                    </tr>
                                    <tr>
                                        <th>Doker Orani</th>
                                        <td id="lab_modal_doker_orani">-</td>
                                        <th>Laborant</th>
                                        <td id="lab_modal_laborant">-</td>
                                    </tr>
                                    <tr>
                                        <th>Tarih</th>
                                        <td colspan="3" id="lab_modal_tarih">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        const aktarmaSiloOptionlar = `
            <option value="">Silo Seç...</option>
            <?php 
                $silolar = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' ORDER BY id ASC");
                while ($s = $silolar->fetch_assoc()):
                    $bos_m3 = max(0, $s['kapasite_m3'] - $s['doluluk_m3']);
            ?>
                <option value="<?php echo $s['id']; ?>" data-silo-adi="<?php echo htmlspecialchars($s['silo_adi'] ?? '', ENT_QUOTES); ?>" data-bos-m3="<?php echo $bos_m3; ?>" data-izinli="<?php echo htmlspecialchars($s['izin_verilen_hammadde_kodlari'] ?? '', ENT_QUOTES); ?>">
                    <?php echo addslashes($s['silo_adi'] ?? ''); ?> (Boş: <?php echo number_format($bos_m3, 1); ?> m³)
                </option>
            <?php endwhile; ?>
        `;

        function labDegerFormat(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }
            return String(value);
        }

        function labModalDoldur(labData, hammaddeKodu, partiKodu) {
            document.getElementById('lab_modal_hammadde_kodu').innerText = hammaddeKodu || '-';
            document.getElementById('lab_modal_parti_kodu').innerText = partiKodu || '-';

            const alanlar = {
                hektolitre: 'lab_modal_hektolitre',
                nem: 'lab_modal_nem',
                protein: 'lab_modal_protein',
                nisasta: 'lab_modal_nisasta',
                sertlik: 'lab_modal_sertlik',
                gluten: 'lab_modal_gluten',
                index_degeri: 'lab_modal_index',
                sedimantasyon: 'lab_modal_sedimantasyon',
                gecikmeli_sedimantasyon: 'lab_modal_gecikmeli_sedimantasyon',
                fn: 'lab_modal_fn',
                doker_orani: 'lab_modal_doker_orani',
                laborant: 'lab_modal_laborant',
                tarih: 'lab_modal_tarih'
            };

            let analizVar = false;
            Object.keys(alanlar).forEach(function (key) {
                const rawVal = (labData && Object.prototype.hasOwnProperty.call(labData, key)) ? labData[key] : null;
                if (rawVal !== null && rawVal !== undefined && rawVal !== '') {
                    analizVar = true;
                }
                document.getElementById(alanlar[key]).innerText = labDegerFormat(rawVal);
            });

            const kayitYok = document.getElementById('lab_modal_kayit_yok');
            if (analizVar) {
                kayitYok.classList.add('d-none');
            } else {
                kayitYok.classList.remove('d-none');
            }
        }

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.karisim-lab-btn');
            if (!btn) {
                return;
            }

            event.preventDefault();

            let labData = {};
            try {
                labData = JSON.parse(btn.getAttribute('data-lab') || '{}');
            } catch (e) {
                labData = {};
            }

            const hammaddeKodu = btn.getAttribute('data-hammadde-kodu') || '-';
            const partiKodu = btn.getAttribute('data-parti-kodu') || '-';

            labModalDoldur(labData, hammaddeKodu, partiKodu);
            new bootstrap.Modal(document.getElementById('labAnalizModal')).show();
        });

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($mesaj)): ?>
            Swal.fire({ toast:true, position:'top-end', icon:'success', title:'<?php echo addslashes(strip_tags($mesaj)); ?>', showConfirmButton:false, showCloseButton:true, timer:5000, timerProgressBar:true });
            <?php endif; ?>
            <?php if (!empty($hata)): ?>
            Swal.fire({ icon:'error', title:'Hata!', text:'<?php echo addslashes(strip_tags($hata)); ?>', confirmButtonColor:'#0f172a' });
            <?php endif; ?>

            const modalEl = document.getElementById('siloAktarmaModal');
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function(event) {
                    const btn = event.relatedTarget;
                    document.getElementById('aktarma_giris_id').value = btn.getAttribute('data-id');
                    document.getElementById('aktarma_plaka').textContent = btn.getAttribute('data-plaka');
                    document.getElementById('aktarma_tedarikci').textContent = btn.getAttribute('data-tedarikci');
                    document.getElementById('aktarma_hammadde').textContent = btn.getAttribute('data-hammadde');
                    document.getElementById('aktarma_hammadde_adi').value = btn.getAttribute('data-hammadde');
                    
                    const kg = parseFloat(btn.getAttribute('data-kg')) || 0;
                    document.getElementById('aktarma_kantar_kg').textContent = kg.toLocaleString('tr-TR');
                    document.getElementById('aktarma_kantar_kg_val').value = kg;
                    
                    document.getElementById('aktarma_yogunluk_kg_m3').value = btn.getAttribute('data-yogunluk') || 780;
                    document.getElementById('aktarma_hammadde_kodu').value = btn.getAttribute('data-kodu');

                    // Form reset (keep first row)
                    const container = document.getElementById('aktarma_silo_alani');
                    const rows = container.querySelectorAll('.aktarma-silo-satir');
                    for (let i = 1; i < rows.length; i++) rows[i].remove();
                    
                    const firstRowInput = container.querySelector('.dagitim-kg-input');
                    if (firstRowInput) {
                        firstRowInput.value = kg;
                    }
                    const firstRowSelect = container.querySelector('.dagitim-silo-select');
                    if(firstRowSelect) firstRowSelect.value = "";
                    
                    hesaplaAktarmaOzet();
                    // Select options fitrele ve text güncelle
                    const kod = btn.getAttribute('data-kodu') || "";
                    const yogunlukText = btn.getAttribute('data-yogunluk');
                    const yogunluk = yogunlukText ? parseFloat(yogunlukText.replace(',', '.')) : 780;
                    const selects = document.querySelectorAll('.dagitim-silo-select');
                    selects.forEach(sel => {
                        Array.from(sel.options).forEach(opt => {
                            if(!opt.value) return;
                            const bosM3 = parseFloat(opt.getAttribute('data-bos-m3')) || 0;
                            const izinliStr = opt.getAttribute('data-izinli') || "";
                            const siloAdi = opt.getAttribute('data-silo-adi') || opt.text.split('(')[0].trim();
                            const bosTon = (bosM3 * (yogunluk || 780)) / 1000;
                            
                            opt.text = siloAdi + " (Boş: " + bosM3.toFixed(1) + " m³ / " + bosTon.toFixed(2) + " Ton)";
                            opt.disabled = false;
                            
                            if (izinliStr.length > 2 && kod !== "") {
                                if (!izinliStr.includes(kod)) {
                                    opt.disabled = true;
                                    opt.text += " - İZİNSİZ";
                                }
                            }
                            if (!opt.disabled && bosM3 <= 0.001) {
                                opt.disabled = true;
                                opt.text += " - DOLU";
                            }
                        });
                    });
                });
            }

            const area = document.getElementById('aktarma_silo_alani');
            if(area) {
                area.addEventListener('input', function(e){
                    if(e.target.classList.contains('dagitim-kg-input')) {
                        hesaplaAktarmaOzet();
                    }
                });
            }
            
            const form = document.getElementById('siloAktarmaForm');
            if(form) {
                form.addEventListener('submit', function(e) {
                    const total = parseFloat(document.getElementById('aktarma_kantar_kg_val').value) || 0;
                    const dagitilan = getDagitilanToplami();
                    if (Math.abs(total - dagitilan) > 0.01) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Eksik Miktar!',
                            text: 'Silolara dağıtılan miktar, referans kantar miktarına ('+total.toLocaleString('tr-TR')+' KG) tam olarak eşit olmalıdır.',
                            confirmButtonColor: '#d97706'
                        });
                    }
                });
            }
        });

        function yeniAktarmaSatiriEkle() {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 aktarma-silo-satir';
            
            row.innerHTML = `
                <div class="col-md-6">
                    <select name="dagitim_silo_id[]" class="form-select dagitim-silo-select" required>
                        ${aktarmaSiloOptionlar}
                    </select>
                </div>
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="number" name="dagitim_kg[]" class="form-control dagitim-kg-input" step="0.01" min="1" placeholder="Miktar" required>
                        <span class="input-group-text bg-light">KG</span>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.aktarma-silo-satir').remove(); hesaplaAktarmaOzet();"><i class="fas fa-times"></i></button>
                </div>
            `;
            document.getElementById('aktarma_silo_alani').appendChild(row);
            
            // Disable state for options and update texts
            const hhKodu = document.getElementById('aktarma_hammadde_kodu').value || "";
            const yogT = document.getElementById('aktarma_yogunluk_kg_m3').value;
            const yogunluk = (yogT ? parseFloat(yogT.replace(',', '.')) : 780) || 780;
            const newSelect = row.querySelector('.dagitim-silo-select');
            
            Array.from(newSelect.options).forEach(opt => {
                if(!opt.value) return;
                const bosM3 = parseFloat(opt.getAttribute('data-bos-m3')) || 0;
                const izinliStr = opt.getAttribute('data-izinli') || "";
                const siloAdi = opt.getAttribute('data-silo-adi') || opt.text.split('(')[0].trim();
                const bosTon = (bosM3 * yogunluk) / 1000;
                
                opt.text = siloAdi + " (Boş: " + bosM3.toFixed(1) + " m³ / " + bosTon.toFixed(2) + " Ton)";
                opt.disabled = false;
                
                if (izinliStr.length > 2 && hhKodu !== "") {
                    if (!izinliStr.includes(hhKodu)) {
                        opt.disabled = true;
                        opt.text += " - İZİNSİZ";
                    }
                }
                if (!opt.disabled && bosM3 <= 0.001) {
                    opt.disabled = true;
                    opt.text += " - DOLU";
                }
            });
            
            // Otomatik miktar doldur (Kalan = Miktar)
            const kgInput = row.querySelector('.dagitim-kg-input');
            const refKg = parseFloat(document.getElementById('aktarma_kantar_kg_val').value) || 0;
            let dag = 0;
            document.querySelectorAll('.dagitim-kg-input').forEach(i => {
               if (i !== kgInput) dag += parseFloat(i.value) || 0;
            });
            let f = refKg - dag;
            if(f > 0) kgInput.value = f.toFixed(2);
            
            hesaplaAktarmaOzet();
        }

        function getDagitilanToplami() {
            let dag = 0;
            document.querySelectorAll('.dagitim-kg-input').forEach(i => dag += parseFloat(i.value) || 0);
            return dag;
        }

        function hesaplaAktarmaOzet() {
            let dag = getDagitilanToplami();
            let limit = parseFloat(document.getElementById('aktarma_kantar_kg_val').value) || 0;
            let kalan = limit - dag;
            
            document.getElementById('toplamDagitilanKg').innerText = dag.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            const elKalan = document.getElementById('kalanDağıtılacakKg');
            elKalan.innerText = kalan.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            if (Math.abs(kalan) < 0.01) {
                elKalan.classList.remove('text-danger');
                elKalan.classList.add('text-success');
            } else {
                elKalan.classList.add('text-danger');
                elKalan.classList.remove('text-success');
            }
        }
    </script>
    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>
</html>
