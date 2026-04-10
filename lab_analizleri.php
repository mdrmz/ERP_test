<?php
session_start();
include("baglan.php");
include("helper_functions.php");

if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modul bazli yetki kontrolu
sayfaErisimKontrol($baglanti);

$mesaj = "";
$hata = "";

// REFERANS DEGERLER GUNCELLE
if (isset($_POST["referans_guncelle"])) {
    $r_protein_min = floatval($_POST["r_protein_min"]);
    $r_protein_max = floatval($_POST["r_protein_max"]);
    $r_gluten_min = floatval($_POST["r_gluten_min"]);
    $r_gluten_max = floatval($_POST["r_gluten_max"]);
    $r_index_min = intval($_POST["r_index_min"]);
    $r_index_max = intval($_POST["r_index_max"]);
    $r_sedim_min = intval($_POST["r_sedim_min"]);
    $r_sedim_max = intval($_POST["r_sedim_max"]);
    $r_gsedim_min = intval($_POST["r_gsedim_min"]);
    $r_gsedim_max = intval($_POST["r_gsedim_max"]);
    $r_hektolitre_min = floatval($_POST["r_hektolitre_min"]);
    $r_hektolitre_max = floatval($_POST["r_hektolitre_max"]);
    $r_nem_min = floatval($_POST["r_nem_min"]);
    $r_nem_max = floatval($_POST["r_nem_max"]);
    $r_fn_min = intval($_POST["r_fn_min"]);
    $r_fn_max = intval($_POST["r_fn_max"]);
    $r_sertlik_min = floatval($_POST["r_sertlik_min"]);
    $r_sertlik_max = floatval($_POST["r_sertlik_max"]);
    $r_nisasta_min = floatval($_POST["r_nisasta_min"]);
    $r_nisasta_max = floatval($_POST["r_nisasta_max"]);
    $r_doker_min = floatval($_POST["r_doker_min"]);
    $r_doker_max = floatval($_POST["r_doker_max"]);

    $sql = "UPDATE lab_referans_degerleri SET
            protein_min=$r_protein_min, protein_max=$r_protein_max,
            gluten_min=$r_gluten_min, gluten_max=$r_gluten_max,
            index_min=$r_index_min, index_max=$r_index_max,
            sedim_min=$r_sedim_min, sedim_max=$r_sedim_max, 
            gsedim_min=$r_gsedim_min, gsedim_max=$r_gsedim_max,
            hektolitre_min=$r_hektolitre_min, hektolitre_max=$r_hektolitre_max, 
            nem_min=$r_nem_min, nem_max=$r_nem_max,
            fn_min=$r_fn_min, fn_max=$r_fn_max,
            sertlik_min=$r_sertlik_min, sertlik_max=$r_sertlik_max,
            nisasta_min=$r_nisasta_min, nisasta_max=$r_nisasta_max,
            doker_min=$r_doker_min, doker_max=$r_doker_max
            WHERE id=1";

    if ($baglanti->query($sql)) {
        systemLogKaydet($baglanti, 'UPDATE', 'Lab Referans', "Referans spekt degerleri guncellendi");
        header("Location: lab_analizleri.php?msg=ref_updated");
        exit;
    } else {
        $hata = "Referans guncelleme hatasi: " . $baglanti->error;
    }
}

// YENI ANALIZ KAYDET
if (isset($_POST["analiz_kaydet"])) {
    $parti_no = mysqli_real_escape_string($baglanti, $_POST["parti_no"]);
    $hammadde_giris_id = !empty($_POST["hammadde_giris_id"]) ? (int) $_POST["hammadde_giris_id"] : null;
    $hammadde_id = !empty($_POST["hammadde_id"]) ? (int) $_POST["hammadde_id"] : null;
    $protein_sql = (isset($_POST["protein"]) && $_POST["protein"] !== '') ? floatval($_POST["protein"]) : "NULL";
    $gluten_sql = (isset($_POST["gluten"]) && $_POST["gluten"] !== '') ? floatval($_POST["gluten"]) : "NULL";
    $index_sql = (isset($_POST["index_degeri"]) && $_POST["index_degeri"] !== '') ? intval($_POST["index_degeri"]) : "NULL";
    $sedim_sql = (isset($_POST["sedimantasyon"]) && $_POST["sedimantasyon"] !== '') ? intval($_POST["sedimantasyon"]) : "NULL";
    $gecikmeli_sedim_sql = (isset($_POST["gecikmeli_sedimantasyon"]) && $_POST["gecikmeli_sedimantasyon"] !== '') ? intval($_POST["gecikmeli_sedimantasyon"]) : 0;
    $hektolitre = floatval($_POST["hektolitre"]);
    $nem = (isset($_POST["nem"]) && $_POST["nem"] !== '') ? floatval($_POST["nem"]) : 0;
    $fn = (isset($_POST["fn"]) && $_POST["fn"] !== '') ? intval($_POST["fn"]) : 0;
    $sertlik = (isset($_POST["sertlik"]) && $_POST["sertlik"] !== '') ? floatval($_POST["sertlik"]) : 0;
    $nisasta = (isset($_POST["nisasta"]) && $_POST["nisasta"] !== '') ? floatval($_POST["nisasta"]) : 0;
    $doker_orani = (isset($_POST["doker_orani"]) && $_POST["doker_orani"] !== '') ? floatval($_POST["doker_orani"]) : 0;
    // Laborant ismini al (Oturumdaki kullanici adi, yoksa user_id uzerinden bir fallback)
    $laborant = !empty($_SESSION["kadi"]) ? $_SESSION["kadi"] : (!empty($_SESSION["user_id"]) ? "UserID:" . $_SESSION["user_id"] : "Sistem");

    $protein_msg = is_numeric($protein_sql) ? $protein_sql : '-';
    $gluten_msg = is_numeric($gluten_sql) ? $gluten_sql : '-';

    // NULL kontrolu
    $hg_sql = $hammadde_giris_id ? $hammadde_giris_id : "NULL";

    // Analiz durum belirleme (Hepsi dolu mu?)
    $tamam_mi = ($protein_sql !== "NULL" && $gluten_sql !== "NULL" &&
        $index_sql !== "NULL" && $sedim_sql !== "NULL" &&
        $hektolitre > 0 && $nem > 0 && $fn > 0 &&
        $sertlik > 0 && $nisasta > 0);
    $analiz_durumu = $tamam_mi ? 2 : 1;

    // Benzersiz parti_no kontrolu
    // Eger duzenlemiyorsak ve bu parti no zaten sistemde var mi diye kontrol edebiliriz
    $chk = $baglanti->query("SELECT id FROM lab_analizleri WHERE parti_no='$parti_no'");
    if ($chk && $chk->num_rows > 0) {
        $hata = "Hata: Girilen '$parti_no' parti numarasi sistemde kayitli. Lutfen farkli bir numara tanimlayin.";
    } else {
        // Hammadde Secildiyse (Arac girisi onaylandi)
        if ($hammadde_giris_id && $hammadde_id) {
            $baglanti->query("UPDATE hammadde_girisleri SET hammadde_id=$hammadde_id, parti_no='$parti_no', analiz_yapildi=$analiz_durumu WHERE id=$hammadde_giris_id");
        }

        $sql = "INSERT INTO lab_analizleri (parti_no, hammadde_giris_id, protein, gluten, index_degeri, sedimantasyon, gecikmeli_sedimantasyon, hektolitre, nem, fn, sertlik, nisasta, doker_orani, laborant) 
                VALUES ('$parti_no', $hg_sql, $protein_sql, $gluten_sql, $index_sql, $sedim_sql, $gecikmeli_sedim_sql, $hektolitre, $nem, $fn, $sertlik, $nisasta, $doker_orani, '$laborant')";

        if ($baglanti->query($sql)) {
            $yeni_analiz_id = $baglanti->insert_id;

            // === SYSTEM LOG KAYDI ===
            systemLogKaydet(
                $baglanti,
                'INSERT',
                'Hammadde Analiz',
                "Yeni analiz kaydi: Parti No: $parti_no | Protein: $protein_msg% | Gluten: $gluten_msg%"
            );

            // === PATRON BILDIRIMI ===
            bildirimOlustur(
                $baglanti,
                'analiz_tamamlandi',
                "Hammadde Analizi Tamamlandi: $parti_no",
                "Protein: $protein_msg% | Gluten: $gluten_msg% | Laborant: $laborant",
                1, // Patron rol_id
                null,
                'lab_analizleri',
                $yeni_analiz_id,
                'lab_analizleri.php'
            );

            header("Location: lab_analizleri.php?msg=ok");
            exit;
        } else {
            $hata = "Kayit hatasi: " . $baglanti->error;
        }
    }
}

// ANALIZ GUNCELLE
if (isset($_POST["analiz_guncelle"])) {
    $id = (int) $_POST["analiz_id"];
    $parti_no = mysqli_real_escape_string($baglanti, $_POST["edit_parti_no"]);
    $hammadde_giris_id = !empty($_POST["edit_hammadde_giris_id"]) ? (int) $_POST["edit_hammadde_giris_id"] : null;
    $protein_sql = (isset($_POST["edit_protein"]) && $_POST["edit_protein"] !== '') ? floatval($_POST["edit_protein"]) : "NULL";
    $gluten_sql = (isset($_POST["edit_gluten"]) && $_POST["edit_gluten"] !== '') ? floatval($_POST["edit_gluten"]) : "NULL";
    $index_sql = (isset($_POST["edit_index_degeri"]) && $_POST["edit_index_degeri"] !== '') ? intval($_POST["edit_index_degeri"]) : "NULL";
    $sedim_sql = (isset($_POST["edit_sedimantasyon"]) && $_POST["edit_sedimantasyon"] !== '') ? intval($_POST["edit_sedimantasyon"]) : "NULL";
    $gecikmeli_sedim_sql = (isset($_POST["edit_gecikmeli_sedimantasyon"]) && $_POST["edit_gecikmeli_sedimantasyon"] !== '') ? intval($_POST["edit_gecikmeli_sedimantasyon"]) : 0;
    $hektolitre = floatval($_POST["edit_hektolitre"]);
    $nem = (isset($_POST["edit_nem"]) && $_POST["edit_nem"] !== '') ? floatval($_POST["edit_nem"]) : 0;
    $fn = (isset($_POST["edit_fn"]) && $_POST["edit_fn"] !== '') ? intval($_POST["edit_fn"]) : 0;
    $sertlik = (isset($_POST["edit_sertlik"]) && $_POST["edit_sertlik"] !== '') ? floatval($_POST["edit_sertlik"]) : 0;
    $nisasta = (isset($_POST["edit_nisasta"]) && $_POST["edit_nisasta"] !== '') ? floatval($_POST["edit_nisasta"]) : 0;
    $doker_orani = (isset($_POST["edit_doker_orani"]) && $_POST["edit_doker_orani"] !== '') ? floatval($_POST["edit_doker_orani"]) : 0;
    $guncelleme_notu = mysqli_real_escape_string($baglanti, $_POST["guncelleme_notu"] ?? '');

    // NULL kontrolu
    $hg_sql = $hammadde_giris_id ? $hammadde_giris_id : "NULL";

    // Tamamlanma Durumu Belirleme
    $tamam_mi = ($protein_sql !== "NULL" && $gluten_sql !== "NULL" &&
        $index_sql !== "NULL" && $sedim_sql !== "NULL" &&
        $hektolitre > 0 && $nem > 0 && $fn > 0 &&
        $sertlik > 0 && $nisasta > 0);
    $analiz_durumu = $tamam_mi ? 2 : 1;

    $sql = "UPDATE lab_analizleri SET 
            parti_no = '$parti_no',
            hammadde_giris_id = $hg_sql,
            protein = $protein_sql,
            gluten = $gluten_sql,
            index_degeri = $index_sql,
            sedimantasyon = $sedim_sql,
            gecikmeli_sedimantasyon = $gecikmeli_sedim_sql,
            hektolitre = $hektolitre,
            nem = $nem,
            fn = $fn,
            sertlik = $sertlik,
            nisasta = $nisasta,
            doker_orani = $doker_orani
            WHERE id = $id";

    if ($baglanti->query($sql)) {
        // Eger bir araca bagliysa durumunu burada da guncelleyelim
        if ($hammadde_giris_id) {
            $baglanti->query("UPDATE hammadde_girisleri SET parti_no='$parti_no', analiz_yapildi=$analiz_durumu WHERE id=$hammadde_giris_id");
        }

        // === SYSTEM LOG KAYDI ===
        systemLogKaydet(
            $baglanti,
            'UPDATE',
            'Hammadde Analiz',
            "Analiz guncellendi: ID: $id | Parti No: $parti_no | Not: $guncelleme_notu"
        );

        header("Location: lab_analizleri.php?msg=updated");
        exit;
    } else {
        $hata = "Guncelleme hatasi: " . $baglanti->error;
    }
}

// ANALIZ SIL
if (isset($_GET["sil"])) {
    $id = (int) $_GET["sil"];
    if ($baglanti->query("DELETE FROM lab_analizleri WHERE id=$id")) {
        header("Location: lab_analizleri.php?msg=deleted");
        exit;
    } else {
        $hata = "Silme hatasi: " . $baglanti->error;
    }
}

// Basari mesajlari
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'ok':
            $mesaj = "&#10004; Analiz sonuclari basariyla kaydedildi!";
            break;
        case 'updated':
            $mesaj = "&#10004; Analiz kaydi basariyla guncellendi!";
            break;
        case 'deleted':
            $mesaj = "&#10004; Analiz kaydi silindi.";
            break;
        case 'ref_updated':
            $mesaj = "&#10004; Referans spekt degerleri basariyla guncellendi!";
            break;
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
            . '<sheets><sheet name="Lab Analiz" sheetId="1" r:id="rId1"/></sheets>'
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

// FILTRELER
$f_baslangic = trim((string) ($_GET["f_baslangic"] ?? ''));
$f_bitis = trim((string) ($_GET["f_bitis"] ?? ''));
$f_arama = trim((string) ($_GET["f_arama"] ?? ''));
$f_hammadde_id = isset($_GET["f_hammadde_id"]) ? (int) $_GET["f_hammadde_id"] : 0;
$filtre_aktif = ($f_baslangic !== '' || $f_bitis !== '' || $f_arama !== '' || $f_hammadde_id > 0);

$whereSql = " WHERE 1=1";
if ($f_baslangic !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_baslangic)) {
    $whereSql .= " AND la.tarih >= '" . $baglanti->real_escape_string($f_baslangic) . " 00:00:00'";
}
if ($f_bitis !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_bitis)) {
    $whereSql .= " AND la.tarih <= '" . $baglanti->real_escape_string($f_bitis) . " 23:59:59'";
}
if ($f_arama !== '') {
    $aramaLike = $baglanti->real_escape_string($f_arama);
    $whereSql .= " AND (
                    la.parti_no LIKE '%$aramaLike%' OR
                    COALESCE(hg.arac_plaka, '') LIKE '%$aramaLike%' OR
                    COALESCE(hg.tedarikci, '') LIKE '%$aramaLike%' OR
                    COALESCE(la.laborant, '') LIKE '%$aramaLike%' OR
                    COALESCE(h.ad, '') LIKE '%$aramaLike%'
                )";
}
if ($f_hammadde_id > 0) {
    $whereSql .= " AND hg.hammadde_id = $f_hammadde_id";
}

// EXCEL EXPORT (FILTRELENMIS SONUCLARDAN)
$excel_export_istek = isset($_GET['excel_export']) && $_GET['excel_export'] === '1';
if ($excel_export_istek) {
    $export_adet = isset($_GET['export_adet']) ? (int) $_GET['export_adet'] : 10;
    $export_adet = max(1, min(500, $export_adet));

    $excel_sorgu = "SELECT la.tarih, la.parti_no, la.laborant,
                           h.ad as hammadde_adi,
                           hg.arac_plaka, hg.tedarikci,
                           la.protein, la.gluten, la.index_degeri, la.sedimantasyon, la.gecikmeli_sedimantasyon,
                           la.hektolitre, la.nem, la.fn, la.sertlik, la.nisasta, la.doker_orani
                    FROM lab_analizleri la
                    LEFT JOIN hammadde_girisleri hg ON la.hammadde_giris_id = hg.id
                    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                    $whereSql
                    ORDER BY la.tarih DESC
                    LIMIT $export_adet";

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
        'Parti No',
        'Hammadde Cinsi',
        'Kaynak',
        'Laborant',
        'Protein',
        'Gluten',
        'Index',
        'Sedim',
        'G.Sedim',
        'HL',
        'Nem',
        'FN',
        'Sertlik',
        'Nisasta',
        'Doker'
    ];

    while ($row = $excel_sonuc->fetch_assoc()) {
        $kaynak = !empty($row['arac_plaka'])
            ? $row['arac_plaka'] . ' / ' . ($row['tedarikci'] ?? '')
            : 'Uretim Numunesi';
        $satirlar[] = [
            !empty($row['tarih']) ? date('d.m.Y H:i', strtotime($row['tarih'])) : '-',
            $row['parti_no'] ?? '-',
            $row['hammadde_adi'] ?? '-',
            $kaynak,
            $row['laborant'] ?? '-',
            $row['protein'] !== null ? number_format((float) $row['protein'], 2, ',', '.') : '-',
            $row['gluten'] !== null ? number_format((float) $row['gluten'], 2, ',', '.') : '-',
            $row['index_degeri'] ?? '-',
            $row['sedimantasyon'] ?? '-',
            $row['gecikmeli_sedimantasyon'] ?? '-',
            $row['hektolitre'] !== null ? number_format((float) $row['hektolitre'], 2, ',', '.') : '-',
            $row['nem'] !== null ? number_format((float) $row['nem'], 2, ',', '.') : '-',
            $row['fn'] ?? '-',
            $row['sertlik'] !== null ? number_format((float) $row['sertlik'], 2, ',', '.') : '-',
            $row['nisasta'] !== null ? number_format((float) $row['nisasta'], 2, ',', '.') : '-',
            $row['doker_orani'] !== null ? number_format((float) $row['doker_orani'], 2, ',', '.') : '-'
        ];
    }

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'erp_xlsx_');
    if ($tmpXlsx === false || !xlsxDosyaOlustur($tmpXlsx, $satirlar)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "XLSX olusturulamadi. Sunucuda ZipArchive uzantisi aktif olmalidir.";
        exit;
    }

    $dosya_adi = "lab_analiz_raporu_" . date('Y-m-d_H-i-s') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');
    header('Content-Length: ' . filesize($tmpXlsx));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tmpXlsx);
    @unlink($tmpXlsx);
    exit;
}

// LISTELER
$ref = $baglanti->query("SELECT * FROM lab_referans_degerleri WHERE id=1")->fetch_assoc();
$analizler = $baglanti->query("SELECT la.*, hg.arac_plaka, hg.tedarikci, h.ad as hammadde_adi
                               FROM lab_analizleri la
                               LEFT JOIN hammadde_girisleri hg ON la.hammadde_giris_id = hg.id
                               LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                               $whereSql
                               ORDER BY la.tarih DESC LIMIT 500");

$hammadde_girisleri = $baglanti->query("SELECT hg.id, hg.parti_no, hg.arac_plaka, hg.tedarikci, hg.tarih, h.ad as hammadde_adi
                                        FROM hammadde_girisleri hg
                                        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                                        LEFT JOIN lab_analizleri la ON hg.id = la.hammadde_giris_id
                                        WHERE la.id IS NULL AND hg.analiz_yapildi = 0
                                          AND (hg.islem_turu IS NULL OR hg.islem_turu <> 'yukleme')
                                        ORDER BY hg.tarih DESC");

$tum_hammaddeler = $baglanti->query("SELECT id, ad FROM hammaddeler ORDER BY ad ASC");
$hammaddeler_arr = [];
if ($tum_hammaddeler) {
    while ($hm = $tum_hammaddeler->fetch_assoc()) {
        $hammaddeler_arr[] = $hm;
    }
}

$bekleyen_analiz_sayisi = 0;
$bekleyen_analiz_result = $baglanti->query("SELECT COUNT(*) AS cnt
                                            FROM hammadde_girisleri hg
                                            LEFT JOIN lab_analizleri la ON hg.id = la.hammadde_giris_id
                                            WHERE la.id IS NULL AND hg.analiz_yapildi = 0
                                              AND (hg.islem_turu IS NULL OR hg.islem_turu <> 'yukleme')");
if ($bekleyen_analiz_result && ($row = $bekleyen_analiz_result->fetch_assoc())) {
    $bekleyen_analiz_sayisi = (int) ($row['cnt'] ?? 0);
}

$toplam_tamamlanan_analiz_sayisi = 0;
$toplam_analiz_result = $baglanti->query("SELECT COUNT(*) AS cnt FROM lab_analizleri");
if ($toplam_analiz_result && ($row = $toplam_analiz_result->fetch_assoc())) {
    $toplam_tamamlanan_analiz_sayisi = (int) ($row['cnt'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hammadde Analiz - Ozbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --lab-bg: #f2f5fb;
            --lab-surface: #ffffff;
            --lab-surface-soft: #f8fafc;
            --lab-border: #dbe4f0;
            --lab-text: #122033;
            --lab-muted: #5f6f84;
            --lab-accent: #0ea5a4;
            --lab-accent-strong: #0f766e;
            --lab-dark: #111827;
            --lab-chip-ok-bg: #e7f8ef;
            --lab-chip-ok-text: #166534;
            --lab-danger: #dc2626;
            --lab-warning: #d97706;
        }

        body.bg-light {
            background:
                radial-gradient(1200px 420px at 100% -40%, rgba(14, 165, 164, 0.12), rgba(14, 165, 164, 0)),
                linear-gradient(180deg, #f7f9fd 0%, var(--lab-bg) 100%) !important;
            color: var(--lab-text);
        }

        .lab-page {
            max-width: 1680px;
            margin: 0 auto;
        }

        .page-hero {
            background: linear-gradient(128deg, #0f172a 0%, #1f2937 62%, #0f766e 145%);
            color: #fff;
            border-radius: 1rem;
            padding: 1.3rem 1.4rem;
            margin-bottom: 1.15rem;
            box-shadow: 0 16px 26px -18px rgba(15, 23, 42, 0.9);
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            top: -65%;
            right: -6%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .page-hero .hero-title {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .page-hero .hero-subtitle {
            margin: 0.35rem 0 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 1rem;
        }

        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            position: relative;
            z-index: 1;
            max-width: 560px;
            margin-left: auto;
        }

        .hero-stat-card {
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.72rem;
            padding: 0.52rem 0.72rem;
            backdrop-filter: blur(6px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hero-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -18px rgba(2, 6, 23, 0.95);
        }

        .hero-stat-card .label {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.76);
            margin-bottom: 0.15rem;
            text-align: center;
        }

        .hero-stat-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.1;
            text-align: center;
        }

        .hero-actions {
            margin-top: 0.65rem;
            display: flex;
            justify-content: flex-end;
        }

        .btn-hero-primary {
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.65rem;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 10px 18px -15px rgba(0, 0, 0, 0.8);
        }

        .btn-hero-primary:hover,
        .btn-hero-primary:focus {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.44);
            background: rgba(255, 255, 255, 0.22);
        }

        .surface-card {
            background: var(--lab-surface);
            border: 1px solid var(--lab-border) !important;
            border-radius: 0.95rem;
            box-shadow: 0 8px 20px -18px rgba(15, 23, 42, 0.55);
            overflow: hidden;
        }

        .section-header {
            border: 0;
            padding: 0.85rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header.ref-header {
            background: linear-gradient(115deg, #0f172a, #273449);
            color: #fff;
        }

        .section-header.analiz-header {
            background: linear-gradient(115deg, #0f766e, #0e94a6);
            color: #fff;
        }

        .section-header small {
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.74rem;
            font-weight: 500;
        }

        .analiz-header-meta {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            flex: 1 1 auto;
            min-width: 220px;
        }

        .analiz-header .header-subtitle {
            margin: 0;
        }

        .filter-toggle-header {
            background: linear-gradient(115deg, #0f172a, #1f2937);
            color: #fff;
            cursor: pointer;
        }

        .filter-toggle-header .badge {
            background: #f59e0b !important;
            color: #111827;
        }

        .filter-card .card-body {
            background: #f8fbff;
            border-top: 1px solid #e2e8f0;
        }

        .export-form {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: nowrap;
            gap: 0.4rem;
            margin-left: auto;
        }

        .export-form .form-control {
            max-width: 80px;
            min-height: 34px;
        }

        .export-form .btn {
            min-height: 34px;
            border-radius: 0.55rem;
            white-space: nowrap;
        }

        .export-form label {
            white-space: nowrap;
        }

        .spec-card-body {
            background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        }

        .spec-chip-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .spec-badge {
            font-size: 0.81rem;
            padding: 0.46rem 0.7rem;
            border-radius: 999px;
            border: 1px solid #c7edd8;
            line-height: 1.35;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .spec-ok {
            background: var(--lab-chip-ok-bg);
            color: var(--lab-chip-ok-text);
        }

        .spec-warn {
            background: #fff4de;
            color: #975a16;
            border-color: #f6deb1;
        }

        .spec-bad {
            background: #ffe8e8;
            color: #991b1b;
            border-color: #f8c9c9;
        }

        .table-shell {
            background: var(--lab-surface);
        }

        .table-lab {
            width: 100% !important;
            margin-bottom: 0 !important;
        }

        .table-lab thead th {
            font-size: 0.78rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #3e4e63;
            white-space: nowrap;
            vertical-align: middle;
            border-bottom-width: 1px;
        }

        .table-lab tbody td {
            border-color: #edf2f8;
            vertical-align: middle;
            font-size: 0.86rem;
        }

        .table-lab.table-striped>tbody>tr:nth-of-type(odd)>* {
            --bs-table-accent-bg: #fbfdff;
        }

        .table-lab.table-hover>tbody>tr:hover>* {
            --bs-table-accent-bg: #f2f8ff;
        }

        #analizlerTablo th.source-col,
        #analizlerTablo td.source-col {
            min-width: 240px;
            width: 240px;
            white-space: normal;
        }

        #analizlerTablo th.metric-col,
        #analizlerTablo td.metric-col {
            white-space: nowrap;
            text-align: center;
        }

        .action-btns {
            white-space: nowrap;
            min-width: 98px;
        }

        .action-btns .btn {
            border-radius: 0.48rem;
            padding: 0.24rem 0.52rem;
            font-size: 0.78rem;
        }

        .required-field::after {
            content: " *";
            color: #ef4444;
            font-weight: 700;
        }

        .form-label {
            color: #304256;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.38rem;
        }

        .form-control,
        .form-select,
        .input-group-text {
            border-color: #d6deea;
            border-radius: 0.6rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(14, 165, 164, 0.62);
            box-shadow: 0 0 0 0.24rem rgba(14, 165, 164, 0.14);
        }

        .form-section {
            border: 1px solid #e4ebf5;
            background: var(--lab-surface-soft);
            border-radius: 0.85rem;
            padding: 0.95rem 1rem 0.5rem;
            margin-bottom: 0.95rem;
        }

        .row.form-section {
            margin-left: 0;
            margin-right: 0;
        }

        .row.form-section>[class*="col-"] {
            padding-left: 0.55rem;
            padding-right: 0.55rem;
        }

        .referans-grid {
            padding-top: 0.8rem;
            padding-bottom: 0.35rem;
        }

        .referans-grid .col-md-6 {
            margin-bottom: 0.45rem !important;
        }

        .modal-lab .modal-content {
            border: 1px solid #d9e2ef;
            border-radius: 0.95rem;
            overflow: hidden;
            box-shadow: 0 20px 42px -24px rgba(15, 23, 42, 0.65);
        }

        .modal-header-lab {
            border-bottom: 0;
            padding: 0.9rem 1rem;
        }

        .modal-header-info {
            background: linear-gradient(125deg, #0f766e, #0ea5a4);
            color: #fff;
        }

        .modal-header-primary {
            background: linear-gradient(125deg, #1d4ed8, #1e40af);
            color: #fff;
        }

        .modal-header-dark {
            background: linear-gradient(125deg, #0f172a, #1f2937);
            color: #fff;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .modal-lab .modal-body {
            padding: 1rem;
            background: #fff;
        }

        .form-actions .btn {
            border-radius: 0.6rem;
            font-weight: 600;
            min-height: 44px;
        }

        .swal-lab-popup {
            border-radius: 0.85rem !important;
            border: 1px solid #dbe3ef !important;
            box-shadow: 0 20px 35px -28px rgba(15, 23, 42, 0.9) !important;
        }

        .swal-lab-confirm {
            background: var(--lab-dark) !important;
            border: 1px solid var(--lab-dark) !important;
            color: #fff !important;
            border-radius: 0.55rem !important;
            padding: 0.45rem 0.9rem !important;
        }

        .swal-lab-cancel {
            border: 1px solid #ced8e5 !important;
            color: #334155 !important;
            background: #fff !important;
            border-radius: 0.55rem !important;
            padding: 0.45rem 0.9rem !important;
        }

        .dataTables_wrapper {
            width: 100% !important;
        }

        .dataTables_wrapper .dataTables_scroll,
        .dataTables_wrapper .dataTables_scrollHead,
        .dataTables_wrapper .dataTables_scrollBody {
            width: 100% !important;
        }

        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info {
            color: #506177;
            font-size: 0.84rem;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d5dfec;
            border-radius: 0.55rem;
            min-height: 34px;
        }

        .dataTables_wrapper .paginate_button .page-link {
            border-color: #d9e2ee;
            color: #334155;
        }

        .dataTables_wrapper .paginate_button.active .page-link {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }

        @media (max-width: 991px) {
            .page-hero {
                padding: 1rem;
            }

            .page-header-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.85rem;
            }

            .page-header-flex .btn {
                width: 100%;
            }

            .hero-stats-grid {
                grid-template-columns: 1fr;
            }

            .hero-actions {
                justify-content: flex-start;
            }

            .section-header.ref-header,
            .section-header.analiz-header {
                flex-direction: column !important;
                align-items: flex-start !important;
            }

            .export-form {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .section-header small {
                font-size: 0.72rem;
            }
        }

        @media (max-width: 768px) {
            .container-fluid.lab-page {
                padding-left: 0.7rem !important;
                padding-right: 0.7rem !important;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: left !important;
                float: none !important;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100% !important;
                margin-left: 0 !important;
            }

            .export-form {
                width: 100%;
            }

            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center !important;
                float: none !important;
                margin-top: 0.55rem;
            }

            #analizlerTablo {
                font-size: 0.75rem;
            }

            #analizlerTablo thead th {
                font-size: 0.67rem;
                padding: 0.32rem 0.28rem;
            }

            #analizlerTablo tbody td {
                padding: 0.35rem 0.27rem;
            }

            .spec-badge {
                font-size: 0.71rem;
                padding: 0.34rem 0.52rem;
            }

            .modal-lab .modal-dialog {
                margin: 0.6rem;
            }
        }

        @media (max-width: 480px) {
            #analizlerTablo {
                font-size: 0.67rem;
            }

            #analizlerTablo thead th {
                font-size: 0.61rem;
                padding: 0.24rem 0.16rem;
            }

            #analizlerTablo tbody td {
                padding: 0.21rem 0.16rem;
            }

            .action-btns .btn {
                padding: 0.18rem 0.35rem;
                font-size: 0.69rem;
            }

            .page-hero .hero-title {
                font-size: 1.45rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container-fluid py-4 lab-page">
        <div class="page-hero page-header-flex">
            <div class="row g-3 align-items-stretch">
                <div class="col-lg-7 d-flex flex-column justify-content-center">
                    <h2 class="hero-title"><i class="fas fa-flask me-2"></i>Hammadde Analiz</h2>
                    <p class="hero-subtitle">Hammadde ve ürün kalite kontrol sonuçları</p>
                </div>
                <div class="col-lg-5">
                    <div class="hero-stats-grid">
                        <div class="hero-stat-card">
                            <div class="label"><i class="fas fa-hourglass-half me-1"></i>Yapilmasi Beklenen Hammadde
                                Analizi</div>
                            <div class="value"><?php echo number_format($bekleyen_analiz_sayisi, 0, ',', '.'); ?></div>
                        </div>
                        <div class="hero-stat-card">
                            <div class="label"><i class="fas fa-check-circle me-1"></i>Toplam Tamamlanan Analiz</div>
                            <div class="value">
                                <?php echo number_format($toplam_tamamlanan_analiz_sayisi, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <div class="hero-actions">
                        <button class="btn btn-hero-primary" data-bs-toggle="modal" data-bs-target="#yeniAnalizModal">
                            <i class="fas fa-plus-circle"></i> Yeni Analiz Gir
                        </button>
                    </div>
                </div>
            </div>
        </div>



        <!-- REFERANS DEGERLER (DINAMIK) -->
        <div class="card mb-4 border-0 shadow-sm surface-card">
            <div class="card-header section-header ref-header">
                <span><i class="fas fa-ruler"></i> Referans Spekt Değerleri</span>
                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#referansModal">
                    <i class="fas fa-cog"></i> Düzenle
                </button>
            </div>
            <div class="card-body spec-card-body">
                <div class="spec-chip-wrap">
                    <span class="spec-badge spec-ok"><b>Protein:</b>
                        <?php echo number_format($ref['protein_min'], 1); ?> -
                        <?php echo number_format($ref['protein_max'], 1); ?>%</span>
                    <span class="spec-badge spec-ok"><b>Gluten:</b> <?php echo number_format($ref['gluten_min'], 1); ?>
                        - <?php echo number_format($ref['gluten_max'], 1); ?>%</span>
                    <span class="spec-badge spec-ok"><b>İndex:</b> <?php echo $ref['index_min']; ?> -
                        <?php echo $ref['index_max']; ?></span>
                    <span class="spec-badge spec-ok"><b>Sedim:</b> <?php echo $ref['sedim_min']; ?> -
                        <?php echo $ref['sedim_max']; ?>
                    </span>
                    <span class="spec-badge spec-ok"><b>G.Sedim:</b>
                        <?php echo $ref['gsedim_min']; ?> - <?php echo $ref['gsedim_max']; ?>
                    </span>
                    <span class="spec-badge spec-ok"><b>Hektolitre:</b>
                        <?php echo number_format($ref['hektolitre_min'], 1); ?> -
                        <?php echo number_format($ref['hektolitre_max'], 1); ?></span>
                    <span class="spec-badge spec-ok"><b>Nem:</b> <?php echo number_format($ref['nem_min'], 1); ?> -
                        <?php echo number_format($ref['nem_max'], 1); ?>%
                    </span>
                    <span class="spec-badge spec-ok"><b>FN:</b>
                        <?php echo $ref['fn_min']; ?> - <?php echo $ref['fn_max']; ?>
                    </span>
                    <span class="spec-badge spec-ok"><b>Sertlik:</b>
                        <?php echo number_format($ref['sertlik_min'], 1); ?> -
                        <?php echo number_format($ref['sertlik_max'], 1); ?></span>
                    <span class="spec-badge spec-ok"><b>Nişasta:</b>
                        <?php echo number_format($ref['nisasta_min'], 1); ?> -
                        <?php echo number_format($ref['nisasta_max'], 1); ?>%</span>
                    <span class="spec-badge spec-ok"><b>Döker:</b>
                        <?php echo number_format($ref['doker_min'], 1); ?> -
                        <?php echo number_format($ref['doker_max'], 1); ?>%</span>
                </div>
            </div>
        </div>

        <!-- FILTRELEME -->
        <div class="card mb-4 border-0 shadow-sm surface-card filter-card">
            <div class="card-header section-header filter-toggle-header" style="cursor:pointer;"
                data-bs-toggle="collapse" data-bs-target="#analizFilterCollapse">
                <span>
                    <i class="fas fa-filter me-2"></i>Kayitlari Filtrele
                    <?php if ($filtre_aktif): ?>
                        <span class="badge ms-2">Aktif</span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-down text-white-50"></i>
            </div>
            <div class="collapse <?php echo $filtre_aktif ? 'show' : ''; ?>" id="analizFilterCollapse">
                <div class="card-body">
                    <form method="get" action="lab_analizleri.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Baslangic Tarihi</label>
                            <input type="date" name="f_baslangic" class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($f_baslangic); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Bitis Tarihi</label>
                            <input type="date" name="f_bitis" class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($f_bitis); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Kelime Ara</label>
                            <input type="text" name="f_arama" class="form-control form-control-sm"
                                placeholder="Parti, plaka, firma, laborant..."
                                value="<?php echo htmlspecialchars($f_arama); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Hammadde Cinsi</label>
                            <select name="f_hammadde_id" class="form-select form-select-sm">
                                <option value="0">Tum Hammaddeler</option>
                                <?php foreach ($hammaddeler_arr as $hm): ?>
                                    <option value="<?php echo (int) $hm['id']; ?>" <?php echo ($f_hammadde_id === (int) $hm['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hm['ad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                            <button type="submit" class="btn btn-sm btn-dark">
                                <i class="fas fa-search me-1"></i>Filtrele
                            </button>
                            <a href="lab_analizleri.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times me-1"></i>Temizle
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ANALIZ LISTESI -->
        <div class="card border-0 shadow-sm surface-card">
            <div class="card-header section-header analiz-header">
                <div class="analiz-header-meta">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Son Analizler</h5>
                    <small class="header-subtitle"><i class="fas fa-file-export me-1"></i>Export filtrelenmis
                        kayitlardan uretilir</small>
                </div>
                <form method="get" action="lab_analizleri.php" class="export-form">
                    <input type="hidden" name="excel_export" value="1">
                    <input type="hidden" name="f_baslangic" value="<?php echo htmlspecialchars($f_baslangic); ?>">
                    <input type="hidden" name="f_bitis" value="<?php echo htmlspecialchars($f_bitis); ?>">
                    <input type="hidden" name="f_arama" value="<?php echo htmlspecialchars($f_arama); ?>">
                    <input type="hidden" name="f_hammadde_id" value="<?php echo (int) $f_hammadde_id; ?>">
                    <label for="export_adet" class="text-white-50 small mb-0">Kayit</label>
                    <input type="number" id="export_adet" name="export_adet" class="form-control form-control-sm"
                        min="1" max="500"
                        value="<?php echo isset($_GET['export_adet']) ? max(1, min(500, (int) $_GET['export_adet'])) : 50; ?>">
                    <button type="submit" class="btn btn-sm btn-dark">
                        <i class="fas fa-file-excel me-1"></i>Excel Export
                    </button>
                </form>
            </div>
            <div class="table-responsive p-3 table-shell">
                <table id="analizlerTablo" class="table table-hover align-middle mb-0 table-striped table-lab">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>Parti No</th>
                            <th class="source-col">Kaynak</th>
                            <th class="metric-col">Protein</th>
                            <th class="metric-col">Gluten</th>
                            <th class="metric-col">İndex</th>
                            <th class="metric-col">Sedim</th>
                            <th class="metric-col">G.Sedim</th>
                            <th class="metric-col">HL</th>
                            <th class="metric-col">Nem</th>
                            <th class="metric-col">FN</th>
                            <th class="metric-col">Sertlik</th>
                            <th class="metric-col">Nişasta</th>
                            <th class="metric-col">Döker</th>
                            <th>Laborant</th>
                            <th class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($analizler && $analizler->num_rows > 0) {
                            while ($row = $analizler->fetch_assoc()) {
                                // Spekt kontrolu - dinamik referans degerlerden
                                $protein_cls = ($row["protein"] >= $ref['protein_min'] && $row["protein"] <= $ref['protein_max']) ? 'text-success' : 'text-danger';
                                $gluten_cls = ($row["gluten"] >= $ref['gluten_min'] && $row["gluten"] <= $ref['gluten_max']) ? 'text-success' : 'text-danger';
                                $index_cls = ($row["index_degeri"] >= $ref['index_min'] && $row["index_degeri"] <= $ref['index_max']) ? 'text-success' : 'text-warning';
                                $sedim_cls = ($row["sedimantasyon"] >= $ref['sedim_min'] && $row["sedimantasyon"] <= $ref['sedim_max']) ? 'text-success' : 'text-danger';
                                $gsedim_cls = ($row["gecikmeli_sedimantasyon"] >= $ref['gsedim_min'] && $row["gecikmeli_sedimantasyon"] <= $ref['gsedim_max']) ? 'text-success' : 'text-danger';
                                $hl_cls = ($row["hektolitre"] >= $ref['hektolitre_min'] && $row["hektolitre"] <= $ref['hektolitre_max']) ? 'text-success' : 'text-danger';
                                $nem_cls = ($row["nem"] >= $ref['nem_min'] && $row["nem"] <= $ref['nem_max']) ? 'text-success' : 'text-danger';
                                $fn_cls = ($row["fn"] >= $ref['fn_min'] && $row["fn"] <= $ref['fn_max']) ? 'text-success' : 'text-danger';
                                $sertlik_cls = ($row["sertlik"] >= $ref['sertlik_min'] && $row["sertlik"] <= $ref['sertlik_max']) ? 'text-success' : 'text-danger';
                                $nisasta_cls = ($row["nisasta"] >= $ref['nisasta_min'] && $row["nisasta"] <= $ref['nisasta_max']) ? 'text-success' : 'text-danger';
                                $doker_cls = ($row["doker_orani"] >= $ref['doker_min'] && $row["doker_orani"] <= $ref['doker_max']) ? 'text-success' : 'text-danger';

                                $kaynak = $row["arac_plaka"] ? $row["arac_plaka"] . " (" . htmlspecialchars($row["tedarikci"] ?? '') . ")" : "Uretim Numunesi";
                                $hammadde_info = $row["hammadde_adi"] ? "<div class='small text-muted'>" . htmlspecialchars($row["hammadde_adi"]) . "</div>" : "";
                                $edit_hg_text = $row["arac_plaka"] ? ($row["arac_plaka"] . " / " . ($row["tedarikci"] ?? '')) : "";
                                ?>
                                <tr>
                                    <td data-order="<?php echo $row["tarih"]; ?>">
                                        <small><?php echo date("d.m.Y H:i", strtotime($row["tarih"])); ?></small>
                                    </td>
                                    <td><span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($row["parti_no"]); ?></span>
                                    </td>
                                    <td class="source-col">
                                        <small><?php echo $kaynak; ?></small>
                                        <?php echo $hammadde_info; ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $protein_cls; ?>">
                                        %<?php echo number_format($row["protein"], 2); ?></td>
                                    <td class="fw-bold metric-col <?php echo $gluten_cls; ?>">
                                        %<?php echo number_format($row["gluten"], 2); ?></td>
                                    <td class="fw-bold metric-col <?php echo $index_cls; ?>"><?php echo $row["index_degeri"]; ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $sedim_cls; ?>">
                                        <?php echo $row["sedimantasyon"]; ?></td>
                                    <td class="fw-bold metric-col <?php echo $gsedim_cls; ?>">
                                        <?php echo $row["gecikmeli_sedimantasyon"]; ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $hl_cls; ?>">
                                        <?php echo number_format($row["hektolitre"], 2); ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $nem_cls; ?>">
                                        %<?php echo number_format($row["nem"], 2); ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $fn_cls; ?>"><?php echo $row["fn"]; ?></td>
                                    <td class="fw-bold metric-col <?php echo $sertlik_cls; ?>">
                                        <?php echo number_format($row["sertlik"], 2); ?>
                                    </td>
                                    <td class="fw-bold metric-col <?php echo $nisasta_cls; ?>">
                                        %<?php echo number_format($row["nisasta"], 2); ?></td>
                                    <td class="fw-bold metric-col <?php echo $doker_cls; ?>">
                                        %<?php echo number_format($row["doker_orani"], 2); ?></td>
                                    <td><small><?php echo htmlspecialchars($row["laborant"]); ?></small></td>
                                    <td class="text-center action-btns">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#duzenleModal" data-id="<?php echo $row['id']; ?>"
                                            data-parti="<?php echo htmlspecialchars($row['parti_no']); ?>"
                                            data-hgid="<?php echo $row['hammadde_giris_id'] ?? ''; ?>"
                                            data-hgtext="<?php echo htmlspecialchars($edit_hg_text, ENT_QUOTES); ?>"
                                            data-protein="<?php echo $row['protein']; ?>"
                                            data-gluten="<?php echo $row['gluten']; ?>"
                                            data-index="<?php echo $row['index_degeri']; ?>"
                                            data-sedim="<?php echo $row['sedimantasyon']; ?>"
                                            data-gsedim="<?php echo $row['gecikmeli_sedimantasyon']; ?>"
                                            data-hektolitre="<?php echo $row['hektolitre']; ?>"
                                            data-nem="<?php echo $row['nem']; ?>" data-fn="<?php echo $row['fn']; ?>"
                                            data-sertlik="<?php echo $row['sertlik']; ?>"
                                            data-nisasta="<?php echo $row['nisasta']; ?>"
                                            data-doker="<?php echo $row['doker_orani']; ?>" title="Duzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="silOnay(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['parti_no'], ENT_QUOTES); ?>', '<?php echo date('d.m.Y H:i', strtotime($row['tarih'])); ?>')"
                                            title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="16" class="text-center p-4 text-muted">Henuz analiz kaydi yok.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- YENI ANALIZ MODAL -->
    <div class="modal fade modal-lab" id="yeniAnalizModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-lab modal-header-info">
                    <h5 class="modal-title"><i class="fas fa-flask"></i> Yeni Analiz Girisi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="yeniAnalizForm">
                        <div class="row mb-3 form-section">
                            <div class="col-md-12 mb-3">
                                <label class="form-label required-field">Hammadde Girisi Secin (Arac
                                    Plaka/Firma)</label>
                                <select name="hammadde_giris_id" class="form-select" id="hammadde_giris_select">
                                    <option value="">-- Uretim Numunesi / Serbest Analiz --</option>
                                    <?php
                                    if ($hammadde_girisleri && $hammadde_girisleri->num_rows > 0) {
                                        $hammadde_girisleri->data_seek(0);
                                        while ($hg = $hammadde_girisleri->fetch_assoc()) {
                                            ?>
                                            <option value="<?php echo htmlspecialchars($hg["id"]); ?>">
                                                <?php echo $hg["arac_plaka"] . " / " . ($hg["tedarikci"] ?? '') . " - " . date("d.m.Y H:i", strtotime($hg["tarih"])); ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Kantar'dan girisi yapilmis ama henuz analizi girilmemis
                                    araclar listelenir.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Hammadde Cinsi</label>
                                <select name="hammadde_id" class="form-select" id="hammadde_cinsi_select" required>
                                    <option value="">-- Cins Secin --</option>
                                    <?php foreach ($hammaddeler_arr as $hm) { ?>
                                        <option value="<?php echo $hm['id']; ?>"><?php echo htmlspecialchars($hm['ad']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Parti No / Numune Kodu</label>
                                <input type="text" name="parti_no" id="parti_no_input" class="form-control" required
                                    placeholder="Hammadde cinsini secin...">
                                <small class="text-muted" id="parti_no_uyari">Parti numarasi otomatik olusturulur,
                                    mudahale edilebilir.</small>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-microscope"></i> Analiz Degerleri</h6>

                        <div class="row form-section">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Protein (%)</label>
                                <input type="number" step="0.01" name="protein" class="form-control" placeholder="12.5"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gluten (%)</label>
                                <input type="number" step="0.01" name="gluten" class="form-control" placeholder="28.0"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Index Degeri</label>
                                <input type="number" name="index_degeri" class="form-control" placeholder="85" min="0"
                                    max="200">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sedimantasyon</label>
                                <input type="number" name="sedimantasyon" class="form-control" placeholder="42" min="0"
                                    max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gecikmeli Sedimantasyon</label>
                                <input type="number" name="gecikmeli_sedimantasyon" class="form-control"
                                    placeholder="38" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">Hektolitre (kg/hl)</label>
                                <input type="number" step="0.01" name="hektolitre" class="form-control"
                                    placeholder="78.5" required min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nem (%)</label>
                                <input type="number" step="0.01" name="nem" class="form-control" placeholder="12.5"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">FN (Falling Number)</label>
                                <input type="number" name="fn" class="form-control" placeholder="300" min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sertlik</label>
                                <input type="number" step="0.01" name="sertlik" class="form-control" placeholder="65"
                                    min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nisasta (%)</label>
                                <input type="number" step="0.01" name="nisasta" class="form-control" placeholder="68.0"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Doker Orani (%)</label>
                                <input type="number" step="0.01" name="doker_orani" class="form-control"
                                    placeholder="55.0" min="0" max="100">
                            </div>
                        </div>

                        <div class="d-grid form-actions">
                            <button type="submit" name="analiz_kaydet" class="btn btn-info btn-lg text-white">
                                <i class="fas fa-save"></i> Analizi Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- DUZENLEME MODAL -->
    <div class="modal fade modal-lab" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-lab modal-header-primary">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Analiz Duzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="duzenleForm">
                        <input type="hidden" name="analiz_id" id="edit_analiz_id">

                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat:</strong> Analiz degerlerini duzenlemek izlenebilirlik acisindan onemlidir.
                            Sadece hatali girisleri duzeltin.
                        </div>

                        <div class="row mb-3 form-section">
                            <div class="col-md-6">
                                <label class="form-label required-field">Parti No / Numune Kodu</label>
                                <input type="text" name="edit_parti_no" id="edit_parti_no" class="form-control"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hammadde Girisi (Opsiyonel)</label>
                                <select name="edit_hammadde_giris_id" id="edit_hammadde_giris_id" class="form-select">
                                    <option value="">-- Uretim numunesi ise bos birak --</option>
                                    <?php
                                    if ($hammadde_girisleri && $hammadde_girisleri->num_rows > 0) {
                                        $hammadde_girisleri->data_seek(0);
                                        while ($hg = $hammadde_girisleri->fetch_assoc()) { ?>
                                            <option value="<?php echo $hg["id"]; ?>">
                                                <?php echo htmlspecialchars($hg["parti_no"] ?? 'Parti yok') . " - " . $hg["arac_plaka"] . " (" . date("d.m", strtotime($hg["tarih"])) . ")"; ?>
                                            </option>
                                        <?php }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-microscope"></i> Analiz Degerleri</h6>

                        <div class="row form-section">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Protein (%)</label>
                                <input type="number" step="0.01" name="edit_protein" id="edit_protein"
                                    class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gluten (%)</label>
                                <input type="number" step="0.01" name="edit_gluten" id="edit_gluten"
                                    class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Index Degeri</label>
                                <input type="number" name="edit_index_degeri" id="edit_index_degeri"
                                    class="form-control" min="0" max="200">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sedimantasyon</label>
                                <input type="number" name="edit_sedimantasyon" id="edit_sedimantasyon"
                                    class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gecikmeli Sedimantasyon</label>
                                <input type="number" name="edit_gecikmeli_sedimantasyon"
                                    id="edit_gecikmeli_sedimantasyon" class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">Hektolitre (kg/hl)</label>
                                <input type="number" step="0.01" name="edit_hektolitre" id="edit_hektolitre"
                                    class="form-control" required min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nem (%)</label>
                                <input type="number" step="0.01" name="edit_nem" id="edit_nem" class="form-control"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">FN (Falling Number)</label>
                                <input type="number" name="edit_fn" id="edit_fn" class="form-control" min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sertlik</label>
                                <input type="number" step="0.01" name="edit_sertlik" id="edit_sertlik"
                                    class="form-control" min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nisasta (%)</label>
                                <input type="number" step="0.01" name="edit_nisasta" id="edit_nisasta"
                                    class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Doker Orani (%)</label>
                                <input type="number" step="0.01" name="edit_doker_orani" id="edit_doker_orani"
                                    class="form-control" min="0" max="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Duzenleme Notu (Opsiyonel)</label>
                            <input type="text" name="guncelleme_notu" class="form-control"
                                placeholder="Orn: Protein degeri yanlis girilmisti, duzeltildi">
                        </div>

                        <div class="d-grid gap-2 form-actions">
                            <button type="submit" name="analiz_guncelle" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Degisiklikleri Kaydet
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Iptal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- REFERANS DEGERLER DUZENLEME MODAL -->
    <div class="modal fade modal-lab" id="referansModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-lab modal-header-dark">
                    <h5 class="modal-title"><i class="fas fa-ruler"></i> Referans Spekt Degerlerini Duzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="referansForm">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu degerleri degistirdiginizde tablo renk kodlamasi ve referans etiketleri otomatik
                            guncellenir. Tolerans istemediginiz ust limitler icin 9999 girebilirsiniz.
                        </div>

                        <div class="row form-section g-2 referans-grid">
                            <!-- Protein & Gluten -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Protein (%)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_protein_min" class="form-control" required
                                        value="<?php echo $ref['protein_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_protein_max" class="form-control" required
                                        value="<?php echo $ref['protein_max']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Gluten (%)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_gluten_min" class="form-control" required
                                        value="<?php echo $ref['gluten_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_gluten_max" class="form-control" required
                                        value="<?php echo $ref['gluten_max']; ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Index</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" name="r_index_min" class="form-control" required
                                        value="<?php echo $ref['index_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" name="r_index_max" class="form-control" required
                                        value="<?php echo $ref['index_max']; ?>">
                                </div>
                            </div>

                            <!-- Sedim & Gecikmeli Sedim -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Sedimantasyon</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" name="r_sedim_min" class="form-control" required
                                        value="<?php echo $ref['sedim_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" name="r_sedim_max" class="form-control" required
                                        value="<?php echo $ref['sedim_max'] ?? 100; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Gec. Sedimantasyon</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" name="r_gsedim_min" class="form-control" required
                                        value="<?php echo $ref['gsedim_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" name="r_gsedim_max" class="form-control" required
                                        value="<?php echo $ref['gsedim_max'] ?? 100; ?>">
                                </div>
                            </div>

                            <!-- Hektolitre & Nem -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Hektolitre (kg/hl)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_hektolitre_min" class="form-control"
                                        required value="<?php echo $ref['hektolitre_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_hektolitre_max" class="form-control"
                                        required value="<?php echo $ref['hektolitre_max'] ?? 100; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Nem (%)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_nem_min" class="form-control" required
                                        value="<?php echo $ref['nem_min'] ?? 0; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_nem_max" class="form-control" required
                                        value="<?php echo $ref['nem_max']; ?>">
                                </div>
                            </div>

                            <!-- FN & Sertlik -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">FN (Falling Number)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" name="r_fn_min" class="form-control" required
                                        value="<?php echo $ref['fn_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" name="r_fn_max" class="form-control" required
                                        value="<?php echo $ref['fn_max'] ?? 999; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Sertlik</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_sertlik_min" class="form-control" required
                                        value="<?php echo $ref['sertlik_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_sertlik_max" class="form-control" required
                                        value="<?php echo $ref['sertlik_max']; ?>">
                                </div>
                            </div>

                            <!-- Nisasta & Doker -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Nisasta (%)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_nisasta_min" class="form-control" required
                                        value="<?php echo $ref['nisasta_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_nisasta_max" class="form-control" required
                                        value="<?php echo $ref['nisasta_max']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Doker Orani (%)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Min</span>
                                    <input type="number" step="0.01" name="r_doker_min" class="form-control" required
                                        value="<?php echo $ref['doker_min']; ?>">
                                    <span class="input-group-text bg-light border-start-0 border-end-0">Max</span>
                                    <input type="number" step="0.01" name="r_doker_max" class="form-control" required
                                        value="<?php echo $ref['doker_max']; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 form-actions">
                            <button type="submit" name="referans_guncelle" class="btn btn-dark btn-lg">
                                <i class="fas fa-save"></i> Referans Degerlerini Kaydet
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Iptal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
        $.fn.dataTable.ext.errMode = 'none';

        function silOnay(id, partiNo, tarih) {
            Swal.fire({
                title: 'Silmek istediginize emin misiniz?',
                html: `Bu analiz kaydi kalici olarak silinecektir.<br><br><b>Parti No:</b> ${partiNo}<br><b>Tarih:</b> ${tarih}`,
                icon: 'warning',
                showCancelButton: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'swal-lab-popup',
                    confirmButton: 'swal-lab-confirm',
                    cancelButton: 'swal-lab-cancel'
                },
                confirmButtonText: 'Evet, Sil!',
                cancelButtonText: 'Iptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?sil=' + id;
                }
            });
        }

        $(document).ready(function () {
            // SweetAlert2 Alerts
            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'swal-lab-popup'
                    },
                    title: '<?php echo addslashes(str_replace("&#10004; ", "", $mesaj)); ?>',
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
                    text: '<?php echo addslashes(str_replace("HATA: ", "", $hata)); ?>',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'swal-lab-popup',
                        confirmButton: 'swal-lab-confirm'
                    }
                });
            <?php endif; ?>

            // DataTables baslat
            $('#analizlerTablo').DataTable({
                "order": [[0, "desc"]], // Tarihe gore azalan (en yeni ustte)
                "pageLength": 25,
                "scrollX": true,
                "autoWidth": false,
                "searching": false,
                "columnDefs": [
                    { "targets": 2, "width": "240px" }
                ],
                "language": {
                    "emptyTable": "Tabloda herhangi bir veri mevcut degil",
                    "info": "_TOTAL_ kayittan _START_ - _END_ arasindaki kayitlar gosteriliyor",
                    "infoEmpty": "Kayit yok",
                    "infoFiltered": "(_MAX_ kayit icerisinden bulunan)",
                    "thousands": ".",
                    "lengthMenu": "Sayfada _MENU_ kayit goster",
                    "loadingRecords": "Yukleniyor...",
                    "processing": "Isleniyor...",
                    "zeroRecords": "Eslesen kayit bulunamadi",
                    "paginate": {
                        "first": "Ilk",
                        "last": "Son",
                        "next": "Sonraki",
                        "previous": "Onceki"
                    }
                },
                "dom": '<"row g-2 align-items-center mb-3"<"col-md-12"l>>rt<"row g-2 align-items-center mt-3"<"col-md-5"i><"col-md-7 text-md-end"p>>'
            });

            // Duzenleme Modal veri doldurma
            var duzenleModal = document.getElementById('duzenleModal');
            if (duzenleModal) {
                duzenleModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var hammaddeSelect = document.getElementById('edit_hammadde_giris_id');
                    var hammaddeGirisId = button.getAttribute('data-hgid') || '';
                    var hammaddeGirisText = button.getAttribute('data-hgtext') || '';

                    // Listede olmayan mevcut baglantiyi gecici olarak ekleyip secimi koru.
                    hammaddeSelect.querySelectorAll('option[data-temp="1"]').forEach(function (opt) {
                        opt.remove();
                    });
                    if (hammaddeGirisId && !hammaddeSelect.querySelector('option[value="' + hammaddeGirisId + '"]')) {
                        var tempOption = document.createElement('option');
                        tempOption.value = hammaddeGirisId;
                        tempOption.textContent = hammaddeGirisText || ('Hammadde Giris #' + hammaddeGirisId);
                        tempOption.setAttribute('data-temp', '1');
                        hammaddeSelect.appendChild(tempOption);
                    }

                    document.getElementById('edit_analiz_id').value = button.getAttribute('data-id');
                    document.getElementById('edit_parti_no').value = button.getAttribute('data-parti');
                    hammaddeSelect.value = hammaddeGirisId;
                    document.getElementById('edit_protein').value = button.getAttribute('data-protein');
                    document.getElementById('edit_gluten').value = button.getAttribute('data-gluten');
                    document.getElementById('edit_index_degeri').value = button.getAttribute('data-index');
                    document.getElementById('edit_sedimantasyon').value = button.getAttribute('data-sedim');
                    document.getElementById('edit_gecikmeli_sedimantasyon').value = button.getAttribute('data-gsedim') || '';
                    document.getElementById('edit_hektolitre').value = button.getAttribute('data-hektolitre') || '';
                    document.getElementById('edit_nem').value = button.getAttribute('data-nem') || '';
                    document.getElementById('edit_fn').value = button.getAttribute('data-fn') || '';
                    document.getElementById('edit_sertlik').value = button.getAttribute('data-sertlik') || '';
                    document.getElementById('edit_nisasta').value = button.getAttribute('data-nisasta') || '';
                    document.getElementById('edit_doker_orani').value = button.getAttribute('data-doker') || '';
                });
            }

            // Hammadde cinsi secildiginde AJAX ile otomatik parti numarasi (numune kodu) getir
            $('#hammadde_cinsi_select').on('change', function () {
                var hammaddeId = $(this).val();
                if (hammaddeId) {
                    $.ajax({
                        url: 'ajax/ajax_get_parti_no.php',
                        type: 'GET',
                        data: { hammadde_id: hammaddeId },
                        success: function (response) {
                            if (response && response.trim() !== '') {
                                $('#parti_no_input').val(response.trim());
                                $('#parti_no_input').removeClass('is-invalid');
                            }
                        },
                        error: function () {
                            console.error("Parti numarasi cekerken hata olustu.");
                        }
                    });
                } else {
                    $('#parti_no_input').val('');
                }
            });

            // Form validasyonu
            $('#yeniAnalizForm, #duzenleForm, #referansForm').on('submit', function (e) {
                var isValid = true;
                $(this).find('input[required], select[required]').each(function () {
                    if (!$(this).val()) {
                        isValid = false;
                        $(this).addClass('is-invalid');
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Eksik Bilgi',
                        text: 'Lutfen tum zorunlu alanlari doldurun!',
                        buttonsStyling: false,
                        customClass: {
                            popup: 'swal-lab-popup',
                            confirmButton: 'swal-lab-confirm'
                        },
                        confirmButtonText: 'Tamam'
                    });
                }
            });
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>