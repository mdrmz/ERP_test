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
    $laborant = $_SESSION["kadi"];
    $protein_msg = is_numeric($protein_sql) ? $protein_sql : '-';
    $gluten_msg = is_numeric($gluten_sql) ? $gluten_sql : '-';

    // NULL kontrolu
    $hg_sql = $hammadde_giris_id ? $hammadde_giris_id : "NULL";

    $sql = "INSERT INTO lab_analizleri (parti_no, hammadde_giris_id, protein, gluten, index_degeri, sedimantasyon, gecikmeli_sedimantasyon, hektolitre, nem, fn, sertlik, nisasta, doker_orani, laborant) 
            VALUES ('$parti_no', $hg_sql, $protein_sql, $gluten_sql, $index_sql, $sedim_sql, $gecikmeli_sedim_sql, $hektolitre, $nem, $fn, $sertlik, $nisasta, $doker_orani, '$laborant')";

    if ($baglanti->query($sql)) {
        $yeni_analiz_id = $baglanti->insert_id;

        // === SYSTEM LOG KAYDI ===
        systemLogKaydet(
            $baglanti,
            'INSERT',
            'Lab Analizleri',
            "Yeni analiz kaydi: Parti No: $parti_no | Protein: $protein_msg% | Gluten: $gluten_msg%"
        );

        // === PATRON BILDIRIMI ===
        bildirimOlustur(
            $baglanti,
            'analiz_tamamlandi',
            "Lab Analizi Tamamlandi: $parti_no",
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
        // === SYSTEM LOG KAYDI ===
        systemLogKaydet(
            $baglanti,
            'UPDATE',
            'Lab Analizleri',
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

// LISTELER
$ref = $baglanti->query("SELECT * FROM lab_referans_degerleri WHERE id=1")->fetch_assoc();
$analizler = $baglanti->query("SELECT la.*, hg.arac_plaka, hg.tedarikci, h.ad as hammadde_adi
                               FROM lab_analizleri la 
                               LEFT JOIN hammadde_girisleri hg ON la.hammadde_giris_id = hg.id 
                               LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                               ORDER BY la.tarih DESC LIMIT 500");

$hammadde_girisleri = $baglanti->query("SELECT hg.id, hg.parti_no, hg.arac_plaka, hg.tedarikci, hg.tarih, h.ad as hammadde_adi 
                                        FROM hammadde_girisleri hg 
                                        LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                                        LEFT JOIN lab_analizleri la ON hg.parti_no = la.parti_no
                                        WHERE la.id IS NULL
                                        ORDER BY hg.tarih DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Analizleri - Ozbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .lab-card {
            border-left: 4px solid #17a2b8;
        }

        .spec-badge {
            font-size: 0.85rem;
            padding: 8px 12px;
            margin: 3px;
            border-radius: 20px;
        }

        .spec-ok {
            background: #d4edda;
            color: #155724;
        }

        .spec-warn {
            background: #fff3cd;
            color: #856404;
        }

        .spec-bad {
            background: #f8d7da;
            color: #721c24;
        }

        .table thead th {
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .action-btns {
            white-space: nowrap;
            min-width: 90px;
        }

        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        /* DataTables buyuk ekranlarda tam genislik */
        .dataTables_wrapper {
            width: 100% !important;
        }

        .dataTables_wrapper .dataTables_scroll,
        .dataTables_wrapper .dataTables_scrollHead,
        .dataTables_wrapper .dataTables_scrollBody {
            width: 100% !important;
        }

        #analizlerTablo {
            width: 100% !important;
        }

        /* ===== RESPONSIVE FIXES ===== */
        @media (max-width: 768px) {

            /* Sayfa basligi stack */
            .page-header-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.75rem;
            }

            .page-header-flex .btn {
                width: 100%;
            }

            /* Referans kart header stack */
            .ref-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem;
            }

            /* Son Analizler kart header */
            .analiz-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem;
            }

            /* DataTables kontrolleri */
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

            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center !important;
                float: none !important;
                margin-top: 0.5rem;
            }

            /* Tablo font kucultme */
            #analizlerTablo {
                font-size: 0.75rem;
            }

            #analizlerTablo thead th {
                font-size: 0.7rem;
                padding: 0.3rem 0.25rem;
            }

            #analizlerTablo tbody td {
                padding: 0.3rem 0.25rem;
            }

            /* Spec badge kucultme */
            .spec-badge {
                font-size: 0.7rem;
                padding: 5px 8px;
                margin: 2px;
            }
        }

        /* Cok kucuk ekranlar */
        @media (max-width: 480px) {
            .container-fluid {
                padding-left: 8px !important;
                padding-right: 8px !important;
            }

            #analizlerTablo {
                font-size: 0.65rem;
            }

            #analizlerTablo thead th {
                font-size: 0.6rem;
                padding: 0.2rem 0.15rem;
            }

            #analizlerTablo tbody td {
                padding: 0.2rem 0.15rem;
            }

            .action-btns .btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
            }

            .spec-badge {
                font-size: 0.6rem;
                padding: 4px 6px;
                margin: 1px;
            }

            /* Sayfa basligi h2 kucult */
            .page-header-flex h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 page-header-flex">
            <div>
                <h2 class="fw-bold"><i class="fas fa-flask text-info"></i> Laboratuvar Analizleri</h2>
                <p class="text-muted mb-0">Hammadde ve ürün kalite kontrol sonuçları</p>
            </div>
            <button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#yeniAnalizModal">
                <i class="fas fa-plus-circle"></i> Yeni Analiz Gir
            </button>
        </div>



        <!-- REFERANS DEGERLER (DINAMIK) -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center ref-header">
                <span><i class="fas fa-ruler"></i> Referans Spekt Değerleri (Ekmeklik Un)</span>
                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#referansModal">
                    <i class="fas fa-cog"></i> Düzenle
                </button>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap">
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
                    <span class="spec-badge spec-ok"><b>Döker:</b> <?php echo number_format($ref['doker_min'], 1); ?> -
                        <?php echo number_format($ref['doker_max'], 1); ?>%</span>
                </div>
            </div>
        </div>

        <!-- ANALIZ LISTESI -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center analiz-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Son Analizler</h5>
                <small><i class="fas fa-info-circle"></i> Filtreleme ve sıralama için tablo başlıklarını
                    kullanın</small>
            </div>
            <div class="table-responsive p-3">
                <table id="analizlerTablo" class="table table-hover align-middle mb-0 table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>Parti No</th>
                            <th>Kaynak</th>
                            <th>Protein</th>
                            <th>Gluten</th>
                            <th>İndex</th>
                            <th>Sedim</th>
                            <th>G.Sedim</th>
                            <th>HL</th>
                            <th>Nem</th>
                            <th>FN</th>
                            <th>Sertlik</th>
                            <th>Nişasta</th>
                            <th>Döker</th>
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

                                $kaynak = $row["arac_plaka"] ? $row["arac_plaka"] . " (" . htmlspecialchars($row["tedarikci"] ?? '') . ")" : "Üretim Numunesi";
                                $hammadde_info = $row["hammadde_adi"] ? "<div class='small text-muted'>" . htmlspecialchars($row["hammadde_adi"]) . "</div>" : "";
                                ?>
                                <tr>
                                    <td data-order="<?php echo $row["tarih"]; ?>">
                                        <small><?php echo date("d.m.Y H:i", strtotime($row["tarih"])); ?></small>
                                    </td>
                                    <td><span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($row["parti_no"]); ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo $kaynak; ?></small>
                                        <?php echo $hammadde_info; ?>
                                    </td>
                                    <td class="fw-bold <?php echo $protein_cls; ?>">
                                        %<?php echo number_format($row["protein"], 2); ?></td>
                                    <td class="fw-bold <?php echo $gluten_cls; ?>">
                                        %<?php echo number_format($row["gluten"], 2); ?></td>
                                    <td class="fw-bold <?php echo $index_cls; ?>"><?php echo $row["index_degeri"]; ?></td>
                                    <td class="fw-bold <?php echo $sedim_cls; ?>"><?php echo $row["sedimantasyon"]; ?></td>
                                    <td class="fw-bold <?php echo $gsedim_cls; ?>">
                                        <?php echo $row["gecikmeli_sedimantasyon"]; ?>
                                    </td>
                                    <td class="fw-bold <?php echo $hl_cls; ?>">
                                        <?php echo number_format($row["hektolitre"], 2); ?>
                                    </td>
                                    <td class="fw-bold <?php echo $nem_cls; ?>">%<?php echo number_format($row["nem"], 2); ?>
                                    </td>
                                    <td class="fw-bold <?php echo $fn_cls; ?>"><?php echo $row["fn"]; ?></td>
                                    <td class="fw-bold <?php echo $sertlik_cls; ?>">
                                        <?php echo number_format($row["sertlik"], 2); ?>
                                    </td>
                                    <td class="fw-bold <?php echo $nisasta_cls; ?>">
                                        %<?php echo number_format($row["nisasta"], 2); ?></td>
                                    <td class="fw-bold <?php echo $doker_cls; ?>">
                                        %<?php echo number_format($row["doker_orani"], 2); ?></td>
                                    <td><small><?php echo htmlspecialchars($row["laborant"]); ?></small></td>
                                    <td class="text-center action-btns">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#duzenleModal" data-id="<?php echo $row['id']; ?>"
                                            data-parti="<?php echo htmlspecialchars($row['parti_no']); ?>"
                                            data-hgid="<?php echo $row['hammadde_giris_id'] ?? ''; ?>"
                                            data-protein="<?php echo $row['protein']; ?>"
                                            data-gluten="<?php echo $row['gluten']; ?>"
                                            data-index="<?php echo $row['index_degeri']; ?>"
                                            data-sedim="<?php echo $row['sedimantasyon']; ?>"
                                            data-gsedim="<?php echo $row['gecikmeli_sedimantasyon']; ?>"
                                            data-hektolitre="<?php echo $row['hektolitre']; ?>"
                                            data-nem="<?php echo $row['nem']; ?>" data-fn="<?php echo $row['fn']; ?>"
                                            data-sertlik="<?php echo $row['sertlik']; ?>"
                                            data-nisasta="<?php echo $row['nisasta']; ?>"
                                            data-doker="<?php echo $row['doker_orani']; ?>" title="Düzenle">
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
                                <td colspan="17" class="text-center p-4 text-muted">Henüz analiz kaydı yok.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- YENI ANALIZ MODAL -->
    <div class="modal fade" id="yeniAnalizModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-flask"></i> Yeni Analiz Girişi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="yeniAnalizForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label required-field">Hammadde Parti No Seçin</label>
                                <select name="parti_no" class="form-select" required id="parti_select">
                                    <option value="">-- Hangi hammadde partisini analiz ediyorsunuz? --</option>
                                    <?php
                                    if ($hammadde_girisleri && $hammadde_girisleri->num_rows > 0) {
                                        $hammadde_girisleri->data_seek(0);
                                        while ($hg = $hammadde_girisleri->fetch_assoc()) {
                                            // Sadece parti_no olan kayitlari goster
                                            if (!empty($hg["parti_no"])) {
                                                ?>
                                                <option value="<?php echo htmlspecialchars($hg["parti_no"]); ?>"
                                                    data-hgid="<?php echo $hg["id"]; ?>">
                                                    <?php echo htmlspecialchars($hg["parti_no"]) . " - " . $hg["arac_plaka"] . " (" . htmlspecialchars($hg["hammadde_adi"] ?? 'Bilinmiyor') . ") - " . date("d.m.Y H:i", strtotime($hg["tarih"])); ?>
                                                </option>
                                                <?php
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Sadece analiz girilmemiş hammadde girişlerindeki parti
                                    numaraları gösterilir</small>
                            </div>
                        </div>

                        <!-- Secilen partinin hammadde_giris_id'si otomatik set edilecek -->
                        <input type="hidden" name="hammadde_giris_id" id="hidden_hgid">

                        <hr>
                        <h6 class="text-muted mb-3"><i class="fas fa-microscope"></i> Analiz Değerleri</h6>

                        <div class="row">
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
                                <label class="form-label">İndex Değeri</label>
                                <input type="number" name="index_degeri" class="form-control" placeholder="85"
                                    min="0" max="200">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sedimantasyon</label>
                                <input type="number" name="sedimantasyon" class="form-control" placeholder="42"
                                    min="0" max="100">
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
                                <input type="number" name="fn" class="form-control" placeholder="300" min="0"
                                    max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sertlik</label>
                                <input type="number" step="0.01" name="sertlik" class="form-control" placeholder="65"
                                    min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nişasta (%)</label>
                                <input type="number" step="0.01" name="nisasta" class="form-control" placeholder="68.0"
                                    min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Döker Oranı (%)</label>
                                <input type="number" step="0.01" name="doker_orani" class="form-control"
                                    placeholder="55.0" min="0" max="100">
                            </div>
                        </div>

                        <div class="d-grid">
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
    <div class="modal fade" id="duzenleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Analiz Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="duzenleForm">
                        <input type="hidden" name="analiz_id" id="edit_analiz_id">

                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat:</strong> Analiz değerlerini düzenlemek izlenebilirlik açısından önemlidir.
                            Sadece hatalı girişleri düzeltin.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label required-field">Parti No / Numune Kodu</label>
                                <input type="text" name="edit_parti_no" id="edit_parti_no" class="form-control"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hammadde Girişi (Opsiyonel)</label>
                                <select name="edit_hammadde_giris_id" id="edit_hammadde_giris_id" class="form-select">
                                    <option value="">-- Üretim numunesi ise boş bırak --</option>
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
                        <h6 class="text-muted mb-3"><i class="fas fa-microscope"></i> Analiz Değerleri</h6>

                        <div class="row">
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
                                <label class="form-label">İndex Değeri</label>
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
                                <input type="number" name="edit_fn" id="edit_fn" class="form-control" min="0"
                                    max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sertlik</label>
                                <input type="number" step="0.01" name="edit_sertlik" id="edit_sertlik"
                                    class="form-control" min="0" max="999">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nişasta (%)</label>
                                <input type="number" step="0.01" name="edit_nisasta" id="edit_nisasta"
                                    class="form-control" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Döker Oranı (%)</label>
                                <input type="number" step="0.01" name="edit_doker_orani" id="edit_doker_orani"
                                    class="form-control" min="0" max="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Düzenleme Notu (Opsiyonel)</label>
                            <input type="text" name="guncelleme_notu" class="form-control"
                                placeholder="Örn: Protein değeri yanlış girilmişti, düzeltildi">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="analiz_guncelle" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Değişiklikleri Kaydet
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                İptal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- REFERANS DEGERLER DUZENLEME MODAL -->
    <div class="modal fade" id="referansModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-ruler"></i> Referans Spekt Değerlerini Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="referansForm">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu değerleri değiştirdiğinizde tablo renk kodlaması ve referans etiketleri otomatik
                            güncellenir. Tolerans istemediğiniz üst limitler için 9999 girebilirsiniz.
                        </div>

                        <div class="row">
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
                                <label class="form-label fw-bold text-muted mb-1">İndex</label>
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

                            <!-- Nişasta & Döker -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-muted mb-1">Nişasta (%)</label>
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
                                <label class="form-label fw-bold text-muted mb-1">Döker Oranı (%)</label>
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

                        <div class="d-grid">
                            <button type="submit" name="referans_guncelle" class="btn btn-dark btn-lg">
                                <i class="fas fa-save"></i> Referans Değerlerini Kaydet
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
        function silOnay(id, partiNo, tarih) {
            Swal.fire({
                title: 'Silmek istediğinize emin misiniz?',
                html: `Bu analiz kaydı kalıcı olarak silinecektir.<br><br><b>Parti No:</b> ${partiNo}<br><b>Tarih:</b> ${tarih}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Sil!',
                cancelButtonText: 'İptal'
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
                    title: '<?php echo addslashes(str_replace(["&#10004; ", "✅ ", "✓ "], "", $mesaj)); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: "], "", $hata)); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>

            // DataTables baslat
            $('#analizlerTablo').DataTable({
                "order": [[0, "desc"]], // Tarihe gore azalan (en yeni ustte)
                "pageLength": 25,
                "scrollX": true,
                "language": {
                    "emptyTable": "Tabloda herhangi bir veri mevcut değil",
                    "info": "_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor",
                    "infoEmpty": "Kayıt yok",
                    "infoFiltered": "(_MAX_ kayıt içerisinden bulunan)",
                    "thousands": ".",
                    "lengthMenu": "Sayfada _MENU_ kayıt göster",
                    "loadingRecords": "Yükleniyor...",
                    "processing": "İşleniyor...",
                    "search": "Ara:",
                    "zeroRecords": "Eşleşen kayıt bulunamadı",
                    "paginate": {
                        "first": "İlk",
                        "last": "Son",
                        "next": "Sonraki",
                        "previous": "Önceki"
                    }
                },
                "dom": '<"row mb-3"<"col-sm-6"l><"col-sm-6"f>>rt<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>'
            });

            // Duzenleme Modal veri doldurma
            var duzenleModal = document.getElementById('duzenleModal');
            if (duzenleModal) {
                duzenleModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;

                    document.getElementById('edit_analiz_id').value = button.getAttribute('data-id');
                    document.getElementById('edit_parti_no').value = button.getAttribute('data-parti');
                    document.getElementById('edit_hammadde_giris_id').value = button.getAttribute('data-hgid') || '';
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

            // Parti secildiginde hammadde_giris_id'yi otomatik doldur
            $('#parti_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var hgid = selectedOption.data('hgid');
                $('#hidden_hgid').val(hgid || '');
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
                        text: 'Lütfen tüm zorunlu alanları doldurun!',
                        confirmButtonText: 'Tamam'
                    });
                }
            });
        });
    </script>

    <?php echo yazmaYetkisiKontrolJS($baglanti); ?>
</body>

</html>