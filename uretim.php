<?php
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

// Paçal işlemleri planlama.php'ye taşındı.


// --- TAVLAMA 1 SİLME ---
if (isset($_GET["tavlama_sil"])) {
    $sil_id = (int) $_GET["tavlama_sil"];
    if ($baglanti->query("DELETE FROM uretim_tavlama_1 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=tavlama_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "tavlama_deleted") {
    $mesaj = "✅ Tavlama 1 kaydı silindi.";
}

// --- TAVLAMA 1 KAYDETME ---
if (isset($_POST["tavlama1_kaydet"])) {
    $pacal_id = (int) $_POST["t_pacal_id"];
    $baslama_tarihi = mysqli_real_escape_string($baglanti, $_POST["t_baslama_tarihi"]);
    $bitis_tarihi = !empty($_POST["t_bitis_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["t_bitis_tarihi"]) . "'" : "NULL";
    $su_derecesi = is_numeric($_POST["t_su_derecesi"]) ? floatval($_POST["t_su_derecesi"]) : "NULL";
    $ortam_derecesi = is_numeric($_POST["t_ortam_derecesi"]) ? floatval($_POST["t_ortam_derecesi"]) : "NULL";
    $toplam_tonaj = is_numeric($_POST["t_toplam_tonaj"]) ? floatval($_POST["t_toplam_tonaj"]) : "NULL";
    $karisim = mysqli_real_escape_string($baglanti, $_POST["t_karisim_degerleri"] ?? '');

    if ($pacal_id <= 0 || empty($baslama_tarihi)) {
        $hata = "Paçal seçimi ve Başlama Tarihi zorunludur.";
    } else {
        $sql_t1 = "INSERT INTO uretim_tavlama_1 (pacal_id, baslama_tarihi, bitis_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, karisim_degerleri, olusturan)
                   VALUES ($pacal_id, '$baslama_tarihi', $bitis_tarihi, $su_derecesi, $ortam_derecesi, $toplam_tonaj, '$karisim', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_t1)) {
            $tavlama_1_id = $baglanti->insert_id;
            $tsatirlar = $_POST["tsatir"] ?? [];

            foreach ($tsatirlar as $ts) {
                // If the user didn't even fill the Ambar No, perhaps we skip. Or we save everything if partially filled. We will skip if ambar no and no lab value is entered.
                // We assume all rows submitted are to be saved.
                $yas = mysqli_real_escape_string($baglanti, $ts["yas_ambar_no"] ?? '');

                $nem = is_numeric($ts["nem"]) ? floatval($ts["nem"]) : 'NULL';
                $hedef_nem = is_numeric($ts["hedef_nem"]) ? floatval($ts["hedef_nem"]) : 'NULL';

                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $hektolitre = is_numeric($ts["hektolitre"]) ? floatval($ts["hektolitre"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';

                $a_p = is_numeric($ts["alveo_p"]) ? floatval($ts["alveo_p"]) : 'NULL';
                $a_g = is_numeric($ts["alveo_g"]) ? floatval($ts["alveo_g"]) : 'NULL';
                $a_pl = is_numeric($ts["alveo_pl"]) ? floatval($ts["alveo_pl"]) : 'NULL';
                $a_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $a_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';

                $p_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';
                $p_sertlik = is_numeric($ts["perten_sertlik"]) ? floatval($ts["perten_sertlik"]) : 'NULL';
                $p_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';

                $sql_tdetay = "INSERT INTO uretim_tavlama_1_detay 
                                (tavlama_1_id, yas_ambar_no, hedef_nem, nem, gluten, g_index, n_sedim, g_sedim, hektolitre,
                                 alveo_p, alveo_g, alveo_pl, alveo_w, alveo_ie, fn, perten_protein, perten_sertlik, perten_nisasta)
                               VALUES 
                                ($tavlama_1_id, '$yas', $hedef_nem, $nem, $gluten, $g_index, $n_sedim, $g_sedim, $hektolitre,
                                 $a_p, $a_g, $a_pl, $a_w, $a_ie, $fn, $p_protein, $p_sertlik, $p_nisasta)";
                $baglanti->query($sql_tdetay);
            }
            $mesaj = "✅ Tavlama 1 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "Tavlama kayıt hatası: " . $baglanti->error;
        }
    }
}

// --- TAVLAMA 2 SİLME ---
if (isset($_GET["tavlama2_sil"])) {
    $sil_id = (int) $_GET["tavlama2_sil"];
    if ($baglanti->query("DELETE FROM uretim_tavlama_2 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=tavlama2_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "tavlama2_deleted") {
    $mesaj = "✅ Tavlama 2 kaydı silindi.";
}

// --- TAVLAMA 2 KAYDETME ---
if (isset($_POST["tavlama2_kaydet"])) {
    $tavlama_1_id = (int) $_POST["t2_tavlama1_id"];
    $baslama_tarihi = mysqli_real_escape_string($baglanti, $_POST["t2_baslama_tarihi"]);
    $bitis_tarihi = !empty($_POST["t2_bitis_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["t2_bitis_tarihi"]) . "'" : "NULL";
    $su_derecesi = is_numeric($_POST["t2_su_derecesi"]) ? floatval($_POST["t2_su_derecesi"]) : "NULL";
    $ortam_derecesi = is_numeric($_POST["t2_ortam_derecesi"]) ? floatval($_POST["t2_ortam_derecesi"]) : "NULL";
    $toplam_tonaj = is_numeric($_POST["t2_toplam_tonaj"]) ? floatval($_POST["t2_toplam_tonaj"]) : "NULL";
    $karisim = mysqli_real_escape_string($baglanti, $_POST["t2_karisim_degerleri"] ?? '');

    if ($tavlama_1_id <= 0 || empty($baslama_tarihi)) {
        $hata = "Tavlama 1 seçimi ve Başlama Tarihi zorunludur.";
    } else {
        $sql_t2 = "INSERT INTO uretim_tavlama_2 (tavlama_1_id, baslama_tarihi, bitis_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, karisim_degerleri, olusturan)
                   VALUES ($tavlama_1_id, '$baslama_tarihi', $bitis_tarihi, $su_derecesi, $ortam_derecesi, $toplam_tonaj, '$karisim', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_t2)) {
            $tavlama_2_id = $baglanti->insert_id;
            $t2satirlar = $_POST["t2satir"] ?? [];

            foreach ($t2satirlar as $ts) {
                $yas = mysqli_real_escape_string($baglanti, $ts["yas_ambar_no"] ?? '');

                // Sadece yaş ambar nosu dolu olanları kaydedelim
                if (empty($yas))
                    continue;

                $nem = is_numeric($ts["nem"]) ? floatval($ts["nem"]) : 'NULL';
                $hedef_nem = is_numeric($ts["hedef_nem"]) ? floatval($ts["hedef_nem"]) : 'NULL';

                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $hektolitre = is_numeric($ts["hektolitre"]) ? floatval($ts["hektolitre"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';

                $a_p = is_numeric($ts["alveo_p"]) ? floatval($ts["alveo_p"]) : 'NULL';
                $a_g = is_numeric($ts["alveo_g"]) ? floatval($ts["alveo_g"]) : 'NULL';
                $a_pl = is_numeric($ts["alveo_pl"]) ? floatval($ts["alveo_pl"]) : 'NULL';
                $a_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $a_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';

                $p_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';
                $p_sertlik = is_numeric($ts["perten_sertlik"]) ? floatval($ts["perten_sertlik"]) : 'NULL';
                $p_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';

                $sql_t2detay = "INSERT INTO uretim_tavlama_2_detay 
                                (tavlama_2_id, yas_ambar_no, hedef_nem, nem, gluten, g_index, n_sedim, g_sedim, hektolitre,
                                 alveo_p, alveo_g, alveo_pl, alveo_w, alveo_ie, fn, perten_protein, perten_sertlik, perten_nisasta)
                               VALUES 
                                ($tavlama_2_id, '$yas', $hedef_nem, $nem, $gluten, $g_index, $n_sedim, $g_sedim, $hektolitre,
                                 $a_p, $a_g, $a_pl, $a_w, $a_ie, $fn, $p_protein, $p_sertlik, $p_nisasta)";
                $baglanti->query($sql_t2detay);
            }
            $mesaj = "✅ Tavlama 2 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "Tavlama 2 kayıt hatası: " . $baglanti->error;
        }
    }
}

// --- TAVLAMA 3 SİLME ---
if (isset($_GET["tavlama3_sil"])) {
    $sil_id = (int) $_GET["tavlama3_sil"];
    if ($baglanti->query("DELETE FROM uretim_tavlama_3 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=tavlama3_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "tavlama3_deleted") {
    $mesaj = "✅ Tavlama 3 kaydı silindi.";
}

// --- TAVLAMA 3 KAYDETME ---
if (isset($_POST["tavlama3_kaydet"])) {
    $tavlama_2_id = (int) $_POST["t3_tavlama2_id"];
    $baslama_tarihi = mysqli_real_escape_string($baglanti, $_POST["t3_baslama_tarihi"]);
    $bitis_tarihi = !empty($_POST["t3_bitis_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["t3_bitis_tarihi"]) . "'" : "NULL";
    $su_derecesi = is_numeric($_POST["t3_su_derecesi"]) ? floatval($_POST["t3_su_derecesi"]) : "NULL";
    $ortam_derecesi = is_numeric($_POST["t3_ortam_derecesi"]) ? floatval($_POST["t3_ortam_derecesi"]) : "NULL";
    $toplam_tonaj = is_numeric($_POST["t3_toplam_tonaj"]) ? floatval($_POST["t3_toplam_tonaj"]) : "NULL";
    $karisim = mysqli_real_escape_string($baglanti, $_POST["t3_karisim_degerleri"] ?? '');

    if ($tavlama_2_id <= 0 || empty($baslama_tarihi)) {
        $hata = "Tavlama 2 seçimi ve Başlama Tarihi zorunludur.";
    } else {
        $sql_t3 = "INSERT INTO uretim_tavlama_3 (tavlama_2_id, baslama_tarihi, bitis_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, karisim_degerleri, olusturan)
                   VALUES ($tavlama_2_id, '$baslama_tarihi', $bitis_tarihi, $su_derecesi, $ortam_derecesi, $toplam_tonaj, '$karisim', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_t3)) {
            $tavlama_3_id = $baglanti->insert_id;
            $t3satirlar = $_POST["t3satir"] ?? [];

            foreach ($t3satirlar as $ts) {
                $yas = mysqli_real_escape_string($baglanti, $ts["yas_ambar_no"] ?? '');

                // Sadece yaş ambar nosu dolu olanları kaydedelim
                if (empty($yas))
                    continue;

                $nem = is_numeric($ts["nem"]) ? floatval($ts["nem"]) : 'NULL';
                $hedef_nem = is_numeric($ts["hedef_nem"]) ? floatval($ts["hedef_nem"]) : 'NULL';

                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $hektolitre = is_numeric($ts["hektolitre"]) ? floatval($ts["hektolitre"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';

                $a_p = is_numeric($ts["alveo_p"]) ? floatval($ts["alveo_p"]) : 'NULL';
                $a_g = is_numeric($ts["alveo_g"]) ? floatval($ts["alveo_g"]) : 'NULL';
                $a_pl = is_numeric($ts["alveo_pl"]) ? floatval($ts["alveo_pl"]) : 'NULL';
                $a_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $a_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';

                $p_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';
                $p_sertlik = is_numeric($ts["perten_sertlik"]) ? floatval($ts["perten_sertlik"]) : 'NULL';
                $p_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';

                $sql_t3detay = "INSERT INTO uretim_tavlama_3_detay 
                                (tavlama_3_id, yas_ambar_no, hedef_nem, nem, gluten, g_index, n_sedim, g_sedim, hektolitre,
                                 alveo_p, alveo_g, alveo_pl, alveo_w, alveo_ie, fn, perten_protein, perten_sertlik, perten_nisasta)
                               VALUES 
                                ($tavlama_3_id, '$yas', $hedef_nem, $nem, $gluten, $g_index, $n_sedim, $g_sedim, $hektolitre,
                                 $a_p, $a_g, $a_pl, $a_w, $a_ie, $fn, $p_protein, $p_sertlik, $p_nisasta)";
                $baglanti->query($sql_t3detay);
            }
            $mesaj = "✅ Tavlama 3 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "Tavlama 3 kayıt hatası: " . $baglanti->error;
        }
    }
}

// --- B1 SİLME ---
if (isset($_GET["b1_sil"])) {
    $sil_id = (int) $_GET["b1_sil"];
    if ($baglanti->query("DELETE FROM uretim_b1 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=b1_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "b1_deleted") {
    $mesaj = "✅ B1 kaydı silindi.";
}

// --- B1 KAYDETME ---
if (isset($_POST["b1_kaydet"])) {
    $tavlama_3_id = (int) $_POST["b1_tavlama3_id"];
    $baslama_tarihi = mysqli_real_escape_string($baglanti, $_POST["b1_baslama_tarihi"]);
    $bitis_tarihi = !empty($_POST["b1_bitis_tarihi"]) ? "'" . mysqli_real_escape_string($baglanti, $_POST["b1_bitis_tarihi"]) . "'" : "NULL";
    $su_derecesi = is_numeric($_POST["b1_su_derecesi"]) ? floatval($_POST["b1_su_derecesi"]) : "NULL";
    $ortam_derecesi = is_numeric($_POST["b1_ortam_derecesi"]) ? floatval($_POST["b1_ortam_derecesi"]) : "NULL";
    $b1_tonaj = is_numeric($_POST["b1_tonaj"]) ? floatval($_POST["b1_tonaj"]) : "NULL";
    $karisim = mysqli_real_escape_string($baglanti, $_POST["b1_karisim_degerleri"] ?? '');

    if ($tavlama_3_id <= 0 || empty($baslama_tarihi)) {
        $hata = "Tavlama 3 seçimi ve Başlama Tarihi zorunludur.";
    } else {
        $sql_b1 = "INSERT INTO uretim_b1 (tavlama_3_id, baslama_tarihi, bitis_tarihi, su_derecesi, ortam_derecesi, b1_tonaj, karisim_degerleri, olusturan)
                   VALUES ($tavlama_3_id, '$baslama_tarihi', $bitis_tarihi, $su_derecesi, $ortam_derecesi, $b1_tonaj, '$karisim', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_b1)) {
            $b1_id = $baglanti->insert_id;
            $b1satirlar = $_POST["b1satir"] ?? [];

            foreach ($b1satirlar as $ts) {
                $yas = mysqli_real_escape_string($baglanti, $ts["yas_ambar_no"] ?? '');

                // Sadece yaş ambar nosu dolu olanları kaydedelim
                if (empty($yas))
                    continue;

                $nem = is_numeric($ts["nem"]) ? floatval($ts["nem"]) : 'NULL';
                $hedef_nem = is_numeric($ts["hedef_nem"]) ? floatval($ts["hedef_nem"]) : 'NULL';

                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $hektolitre = is_numeric($ts["hektolitre"]) ? floatval($ts["hektolitre"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';

                $a_p = is_numeric($ts["alveo_p"]) ? floatval($ts["alveo_p"]) : 'NULL';
                $a_g = is_numeric($ts["alveo_g"]) ? floatval($ts["alveo_g"]) : 'NULL';
                $a_pl = is_numeric($ts["alveo_pl"]) ? floatval($ts["alveo_pl"]) : 'NULL';
                $a_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $a_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';

                $p_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';
                $p_sertlik = is_numeric($ts["perten_sertlik"]) ? floatval($ts["perten_sertlik"]) : 'NULL';
                $p_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';

                $sql_b1detay = "INSERT INTO uretim_b1_detay 
                                (b1_id, yas_ambar_no, hedef_nem, nem, gluten, g_index, n_sedim, g_sedim, hektolitre,
                                 alveo_p, alveo_g, alveo_pl, alveo_w, alveo_ie, fn, perten_protein, perten_sertlik, perten_nisasta)
                               VALUES 
                                ($b1_id, '$yas', $hedef_nem, $nem, $gluten, $g_index, $n_sedim, $g_sedim, $hektolitre,
                                 $a_p, $a_g, $a_pl, $a_w, $a_ie, $fn, $p_protein, $p_sertlik, $p_nisasta)";
                $baglanti->query($sql_b1detay);
            }
            $mesaj = "✅ B1 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "B1 kayıt hatası: " . $baglanti->error;
        }
    }
}

// --- UN 1 SİLME ---
if (isset($_GET["un1_sil"])) {
    $sil_id = (int) $_GET["un1_sil"];
    if ($baglanti->query("DELETE FROM uretim_un1 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=un1_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "un1_deleted") {
    $mesaj = "✅ Un 1 kaydı silindi.";
}

// --- UN 1 KAYDETME ---
if (isset($_POST["un1_kaydet"])) {
    $b1_id = (int) $_POST["un1_b1_id"];
    $numune_saati = mysqli_real_escape_string($baglanti, $_POST["un1_numune_saati"]);

    if ($b1_id <= 0 || empty($numune_saati)) {
        $hata = "B1 seçimi ve Numune Saati zorunludur.";
    } else {
        $sql_un1 = "INSERT INTO uretim_un1 (b1_id, numune_saati, olusturan)
                   VALUES ($b1_id, '$numune_saati', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_un1)) {
            $un1_id = $baglanti->insert_id;
            $un1satirlar = $_POST["un1satir"] ?? [];

            foreach ($un1satirlar as $ts) {
                $silo_no = mysqli_real_escape_string($baglanti, $ts["silo_no"] ?? '');

                if (empty($silo_no))
                    continue;

                $miktar_kg = is_numeric($ts["miktar_kg"]) ? floatval($ts["miktar_kg"]) : 'NULL';
                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';
                $ffn = is_numeric($ts["ffn"]) ? intval($ts["ffn"]) : 'NULL';
                $s_d = is_numeric($ts["s_d"]) ? floatval($ts["s_d"]) : 'NULL';

                $perten_nem = is_numeric($ts["perten_nem"]) ? floatval($ts["perten_nem"]) : 'NULL';
                $perten_kul = is_numeric($ts["perten_kul"]) ? floatval($ts["perten_kul"]) : 'NULL';
                $perten_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';
                $perten_renk_b = is_numeric($ts["perten_renk_b"]) ? floatval($ts["perten_renk_b"]) : 'NULL';
                $perten_renk_l = is_numeric($ts["perten_renk_l"]) ? floatval($ts["perten_renk_l"]) : 'NULL';
                $perten_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';

                $cons_su_kaldirma = is_numeric($ts["cons_su_kaldirma"]) ? floatval($ts["cons_su_kaldirma"]) : 'NULL';
                $cons_tol = is_numeric($ts["cons_tol"]) ? floatval($ts["cons_tol"]) : 'NULL';

                $alveo_t = is_numeric($ts["alveo_t"]) ? floatval($ts["alveo_t"]) : 'NULL';
                $alveo_a = is_numeric($ts["alveo_a"]) ? floatval($ts["alveo_a"]) : 'NULL';
                $alveo_ta = is_numeric($ts["alveo_ta"]) ? floatval($ts["alveo_ta"]) : 'NULL';
                $alveo_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $alveo_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';


                $sql_un1detay = "INSERT INTO uretim_un1_detay 
                                (un1_id, silo_no, miktar_kg, gluten, g_index, n_sedim, g_sedim, fn, ffn, s_d,
                                 perten_nem, perten_kul, perten_nisasta, perten_renk_b, perten_renk_l, perten_protein,
                                 cons_su_kaldirma, cons_tol, alveo_t, alveo_a, alveo_ta, alveo_w, alveo_ie)
                               VALUES 
                                ($un1_id, '$silo_no', $miktar_kg, $gluten, $g_index, $n_sedim, $g_sedim, $fn, $ffn, $s_d,
                                 $perten_nem, $perten_kul, $perten_nisasta, $perten_renk_b, $perten_renk_l, $perten_protein,
                                 $cons_su_kaldirma, $cons_tol, $alveo_t, $alveo_a, $alveo_ta, $alveo_w, $alveo_ie)";
                $baglanti->query($sql_un1detay);
            }
            $mesaj = "✅ Un 1 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "Un 1 kayıt hatası: " . $baglanti->error;
        }
    }
}

// --- UN 1 SİLME ---
if (isset($_GET["un1_sil"])) {
    $sil_id = (int) $_GET["un1_sil"];
    if ($baglanti->query("DELETE FROM uretim_un1 WHERE id = $sil_id")) {
        header("Location: uretim.php?msg=un1_deleted");
        exit;
    }
}

if (isset($_GET["msg"]) && $_GET["msg"] == "un1_deleted") {
    $mesaj = "✅ Un 1 kaydı silindi.";
}

// --- UN 1 KAYDETME ---
if (isset($_POST["un1_kaydet"])) {
    $b1_id = (int) $_POST["un1_b1_id"];
    $numune_saati = mysqli_real_escape_string($baglanti, $_POST["un1_numune_saati"]);

    if ($b1_id <= 0 || empty($numune_saati)) {
        $hata = "B1 seçimi ve Numune Saati zorunludur.";
    } else {
        $sql_un1 = "INSERT INTO uretim_un1 (b1_id, numune_saati, olusturan)
                   VALUES ($b1_id, '$numune_saati', '{$_SESSION["kadi"]}')";

        if ($baglanti->query($sql_un1)) {
            $un1_id = $baglanti->insert_id;
            $un1satirlar = $_POST["un1satir"] ?? [];

            foreach ($un1satirlar as $ts) {
                $silo_no = mysqli_real_escape_string($baglanti, $ts["silo_no"] ?? '');

                if (empty($silo_no))
                    continue;

                $miktar_kg = is_numeric($ts["miktar_kg"]) ? floatval($ts["miktar_kg"]) : 'NULL';
                $gluten = is_numeric($ts["gluten"]) ? floatval($ts["gluten"]) : 'NULL';
                $g_index = is_numeric($ts["g_index"]) ? intval($ts["g_index"]) : 'NULL';
                $n_sedim = is_numeric($ts["n_sedim"]) ? intval($ts["n_sedim"]) : 'NULL';
                $g_sedim = is_numeric($ts["g_sedim"]) ? intval($ts["g_sedim"]) : 'NULL';
                $fn = is_numeric($ts["fn"]) ? intval($ts["fn"]) : 'NULL';
                $ffn = is_numeric($ts["ffn"]) ? intval($ts["ffn"]) : 'NULL';
                $s_d = is_numeric($ts["s_d"]) ? floatval($ts["s_d"]) : 'NULL';

                $perten_nem = is_numeric($ts["perten_nem"]) ? floatval($ts["perten_nem"]) : 'NULL';
                $perten_kul = is_numeric($ts["perten_kul"]) ? floatval($ts["perten_kul"]) : 'NULL';
                $perten_nisasta = is_numeric($ts["perten_nisasta"]) ? floatval($ts["perten_nisasta"]) : 'NULL';
                $perten_renk_b = is_numeric($ts["perten_renk_b"]) ? floatval($ts["perten_renk_b"]) : 'NULL';
                $perten_renk_l = is_numeric($ts["perten_renk_l"]) ? floatval($ts["perten_renk_l"]) : 'NULL';
                $perten_protein = is_numeric($ts["perten_protein"]) ? floatval($ts["perten_protein"]) : 'NULL';

                $cons_su_kaldirma = is_numeric($ts["cons_su_kaldirma"]) ? floatval($ts["cons_su_kaldirma"]) : 'NULL';
                $cons_tol = is_numeric($ts["cons_tol"]) ? floatval($ts["cons_tol"]) : 'NULL';

                $alveo_t = is_numeric($ts["alveo_t"]) ? floatval($ts["alveo_t"]) : 'NULL';
                $alveo_a = is_numeric($ts["alveo_a"]) ? floatval($ts["alveo_a"]) : 'NULL';
                $alveo_ta = is_numeric($ts["alveo_ta"]) ? floatval($ts["alveo_ta"]) : 'NULL';
                $alveo_w = is_numeric($ts["alveo_w"]) ? intval($ts["alveo_w"]) : 'NULL';
                $alveo_ie = is_numeric($ts["alveo_ie"]) ? floatval($ts["alveo_ie"]) : 'NULL';

                $sql_un1detay = "INSERT INTO uretim_un1_detay 
                                (un1_id, silo_no, miktar_kg, gluten, g_index, n_sedim, g_sedim, fn, ffn, s_d,
                                 perten_nem, perten_kul, perten_nisasta, perten_renk_b, perten_renk_l, perten_protein,
                                 cons_su_kaldirma, cons_tol, alveo_t, alveo_a, alveo_ta, alveo_w, alveo_ie)
                               VALUES 
                                ($un1_id, '$silo_no', $miktar_kg, $gluten, $g_index, $n_sedim, $g_sedim, $fn, $ffn, $s_d,
                                 $perten_nem, $perten_kul, $perten_nisasta, $perten_renk_b, $perten_renk_l, $perten_protein,
                                 $cons_su_kaldirma, $cons_tol, $alveo_t, $alveo_a, $alveo_ta, $alveo_w, $alveo_ie)";
                $baglanti->query($sql_un1detay);
            }
            $mesaj = "✅ Un 1 kaydı başarıyla oluşturuldu.";
        } else {
            $hata = "Un 1 kayıt hatası: " . $baglanti->error;
        }
    }
}

// Veri çekimleri - Sadece aktif paçallar (Tavlama 1 için gerekli)

// Sistemdeki mevcut Paçallar (Tavlama 1 ekranı için)
$tum_pacallar = $baglanti->query("
    SELECT id, parti_no, urun_adi, toplam_miktar_kg, tarih 
    FROM uretim_pacal 
    ORDER BY id DESC
");

// Son Tavlama 1 kayıtları
$son_tavlamalar = $baglanti->query("
    SELECT t.*, p.parti_no as pacal_parti_no, p.urun_adi,
           (SELECT COUNT(*) FROM uretim_tavlama_1_detay WHERE tavlama_1_id = t.id) as satir_sayisi
    FROM uretim_tavlama_1 t
    LEFT JOIN uretim_pacal p ON t.pacal_id = p.id
    ORDER BY t.olusturma_tarihi DESC
    LIMIT 10
");

// Tavlama 2 için: Tüm Tavlama 1 kayıtları
$tum_tavlama1_kayitlari = $baglanti->query("
    SELECT t1.id, t1.baslama_tarihi, t1.toplam_tonaj, p.parti_no, p.urun_adi 
    FROM uretim_tavlama_1 t1
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY t1.id DESC
");

// Son Tavlama 2 kayıtları
$son_tavlamalar2 = $baglanti->query("
    SELECT t2.*, p.parti_no as pacal_parti_no, p.urun_adi, t1.baslama_tarihi as t1_baslama,
           (SELECT COUNT(*) FROM uretim_tavlama_2_detay WHERE tavlama_2_id = t2.id) as satir_sayisi
    FROM uretim_tavlama_2 t2
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY t2.olusturma_tarihi DESC
    LIMIT 10
");

// Tavlama 3 için: Tüm Tavlama 2 kayıtları
$tum_tavlama2_kayitlari = $baglanti->query("
    SELECT t2.id, t2.baslama_tarihi, t2.toplam_tonaj, p.parti_no, p.urun_adi 
    FROM uretim_tavlama_2 t2
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY t2.id DESC
");

// Son Tavlama 3 kayıtları
$son_tavlamalar3 = $baglanti->query("
    SELECT t3.*, p.parti_no as pacal_parti_no, p.urun_adi, t2.baslama_tarihi as t2_baslama,
           (SELECT COUNT(*) FROM uretim_tavlama_3_detay WHERE tavlama_3_id = t3.id) as satir_sayisi
    FROM uretim_tavlama_3 t3
    LEFT JOIN uretim_tavlama_2 t2 ON t3.tavlama_2_id = t2.id
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY t3.olusturma_tarihi DESC
    LIMIT 10
");

// B1 için: Tüm Tavlama 3 kayıtları
$tum_tavlama3_kayitlari = $baglanti->query("
    SELECT t3.id, t3.baslama_tarihi, t3.toplam_tonaj, p.parti_no, p.urun_adi, t1.baslama_tarihi as t1_baslama_tarihi
    FROM uretim_tavlama_3 t3
    LEFT JOIN uretim_tavlama_2 t2 ON t3.tavlama_2_id = t2.id
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY t3.id DESC
");

// Son B1 kayıtları
$son_b1_kayitlari = $baglanti->query("
    SELECT b1.*, p.parti_no as pacal_parti_no, p.urun_adi, t1.baslama_tarihi as t1_baslama,
           (SELECT COUNT(*) FROM uretim_b1_detay WHERE b1_id = b1.id) as satir_sayisi
    FROM uretim_b1 b1
    LEFT JOIN uretim_tavlama_3 t3 ON b1.tavlama_3_id = t3.id
    LEFT JOIN uretim_tavlama_2 t2 ON t3.tavlama_2_id = t2.id
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY b1.olusturma_tarihi DESC
    LIMIT 10
");

// Un 1 için: Tüm B1 kayıtları
$tum_b1_kayitlari = $baglanti->query("
    SELECT b1.id, b1.baslama_tarihi, b1.b1_tonaj, p.parti_no, p.urun_adi 
    FROM uretim_b1 b1
    LEFT JOIN uretim_tavlama_3 t3 ON b1.tavlama_3_id = t3.id
    LEFT JOIN uretim_tavlama_2 t2 ON t3.tavlama_2_id = t2.id
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY b1.id DESC
");

// Son Un 1 kayıtları
$son_un1_kayitlari = $baglanti->query("
    SELECT un1.*, p.parti_no as pacal_parti_no, p.urun_adi,
           (SELECT COUNT(*) FROM uretim_un1_detay WHERE un1_id = un1.id) as satir_sayisi
    FROM uretim_un1 un1
    LEFT JOIN uretim_b1 b1 ON un1.b1_id = b1.id
    LEFT JOIN uretim_tavlama_3 t3 ON b1.tavlama_3_id = t3.id
    LEFT JOIN uretim_tavlama_2 t2 ON t3.tavlama_2_id = t2.id
    LEFT JOIN uretim_tavlama_1 t1 ON t2.tavlama_1_id = t1.id
    LEFT JOIN uretim_pacal p ON t1.pacal_id = p.id
    ORDER BY un1.olusturma_tarihi DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üretim Paçalı - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* === GENEL === */
        .page-header {
            background: linear-gradient(135deg, #1c2331 0%, #2c3e50 100%);
            color: #fff;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 20px;
        }

        /* === SEKMELER === */
        .nav-tabs .nav-link {
            font-weight: 600;
            color: #555;
            border: none;
            padding: 12px 24px;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, #2c3e50 0%, #1c2331 100%);
            border: none;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background: #ecf0f1;
        }

        .tab-content {
            background: #fff;
            border-radius: 0 0 15px 15px;
            padding: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        /* === BAŞLIK FORMU === */
        .pacal-header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            color: #fff;
        }

        .pacal-header .form-label {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.85rem;
            margin-bottom: 3px;
        }

        .pacal-header .form-control,
        .pacal-header .form-select {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .pacal-header .form-control:focus,
        .pacal-header .form-select:focus {
            background: rgba(255, 255, 255, 0.25);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.15);
        }

        .pacal-header .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .pacal-header .form-select option {
            color: #333;
            background: #fff;
        }

        /* === PAÇAL TABLOSU === */
        .pacal-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .pacal-table {
            min-width: 1700px;
            font-size: 0.78rem;
        }

        .pacal-table thead th {
            background: #2c3e50;
            color: #fff;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 6px 4px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .pacal-table thead th.group-lab {
            background: #2980b9;
        }

        .pacal-table thead th.group-alveo {
            background: #8e44ad;
        }

        .pacal-table thead th.group-perten {
            background: #27ae60;
        }

        .pacal-table thead th.group-consistograph {
            background: #d35400;
            /* Orange color */
        }

        .pacal-table tbody td {
            padding: 3px 2px;
            vertical-align: middle;
            text-align: center;
        }

        .pacal-table .form-control,
        .pacal-table .form-select {
            font-size: 0.78rem;
            padding: 3px 5px;
            height: 30px;
            text-align: center;
            min-width: 60px;
        }

        .pacal-table .form-select,
        .pacal-table .parti-select {
            min-width: 100px;
        }

        .pacal-table .lab-auto {
            background: #eaf6ff;
            border-color: #b3d9f2;
            color: #2c3e50;
            font-weight: 600;
        }

        .pacal-table .lab-auto:focus {
            background: #fff;
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .pacal-table .alveo-manual {
            background: #faf0ff;
            border-color: #d4a8e8;
        }

        .pacal-table .perten-manual {
            background: #e9f7ef;
            border-color: #a3e4d7;
        }

        .pacal-table .consist-manual {
            background: #ffffff;
            border-color: #f5b041;
        }

        .pacal-table tr.avg-row td {
            background: #f8f9fa;
            font-weight: 700;
            color: #2c3e50;
            border-top: 3px solid #2c3e50;
        }

        .pacal-table tr.avg-row .avg-val {
            background: #e8f8f0;
            border-radius: 4px;
            padding: 4px 6px;
        }

        .sira-no {
            width: 35px;
            font-weight: 700;
            color: #fff;
            background: #34495e !important;
        }

        /* === SON KAYITLAR === */
        .history-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            border-radius: 12px;
        }

        .history-card .card-header {
            background: #fff;
            border-bottom: 2px solid #f1f1f1;
            border-radius: 12px 12px 0 0;
        }

        .durum-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .page-header {
                padding: 15px;
            }

            .page-header h2 {
                font-size: 1.2rem;
            }

            .nav-tabs .nav-link {
                padding: 8px 14px;
                font-size: 0.85rem;
            }

            .pacal-header {
                padding: 15px;
            }

            .pacal-header .row>div {
                margin-bottom: 10px;
            }

            .pacal-table {
                font-size: 0.72rem;
            }

            .pacal-table .form-control,
            .pacal-table .form-select {
                font-size: 0.72rem;
                height: 26px;
                padding: 2px 3px;
                min-width: 50px;
            }

            .pacal-table .form-select {
                min-width: 80px;
            }

            .btn-lg {
                font-size: 0.9rem;
                padding: 8px 16px;
            }
        }

        @media (max-width: 576px) {
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }

            .nav-tabs .nav-link {
                white-space: nowrap;
                flex-shrink: 0;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container-fluid py-3 px-md-4">

        <!-- SAYFA BAŞLIĞI -->
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-wheat-awn me-2"></i>Üretim Paçalı Hazırlama ve İzleme</h2>
                <p class="mb-0 opacity-75">Buğday paçalı oluşturma, lab değerleri izleme ve tavlama süreci</p>
            </div>
            <div>
                <span class="badge bg-light text-dark px-3 py-2 fs-6">
                    <i class="fas fa-calendar me-1"></i> <?php echo date('d.m.Y'); ?>
                </span>
            </div>
        </div>

        <!-- SEKMELER -->
        <ul class="nav nav-tabs" id="uretimTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tavlama-tab" data-bs-toggle="tab" data-bs-target="#tavlama" type="button"
                    role="tab">
                    <i class="fas fa-water me-1"></i> Tavlama 1
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tavlama2-tab" data-bs-toggle="tab" data-bs-target="#tavlama2" type="button"
                    role="tab">
                    <i class="fas fa-tint me-1"></i> Tavlama 2
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tavlama3-tab" data-bs-toggle="tab" data-bs-target="#tavlama3" type="button"
                    role="tab">
                    <i class="fas fa-tint me-1"></i> Tavlama 3
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="b1-tab" data-bs-toggle="tab" data-bs-target="#b1" type="button" role="tab">
                    <i class="fas fa-industry me-1"></i> B1 (Üretime Giriş)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="un1-tab" data-bs-toggle="tab" data-bs-target="#un1" type="button"
                    role="tab">
                    <i class="fas fa-flask me-1"></i> Un 1
                </button>
            </li>
        </ul>

        <div class="tab-content" id="uretimTabsContent">

            <!-- ================== SEKME 2: TAVLAMA 1 ================== -->
            <div class="tab-pane fade show active p-3" id="tavlama" role="tabpanel">
                <form action="uretim.php" method="POST" id="tavlama1Form">
                    <!-- TAVLAMA 1 HEADER -->
                    <div class="pacal-header mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label required-field"><i class="fas fa-link me-1"></i> Bağlı Paçal
                                    Parti No</label>
                                <select name="t_pacal_id" class="form-select" id="tPacalSelect"
                                    onchange="tavlamaPacalSecildi(this)" required>
                                    <option value="">-- Paçal Seçiniz --</option>
                                    <?php if ($tum_pacallar && $tum_pacallar->num_rows > 0): ?>
                                        <?php while ($p = $tum_pacallar->fetch_assoc()): ?>
                                            <option value="<?php echo $p['id']; ?>"
                                                data-tonaj="<?php echo $p['toplam_miktar_kg']; ?>">
                                                <?php echo htmlspecialchars($p['parti_no']) . " (" . htmlspecialchars($p['urun_adi']) . ")"; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Başlama Tarihi ve Saati</label>
                                <input type="datetime-local" name="t_baslama_tarihi" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bitiş Tarihi ve Saati</label>
                                <input type="datetime-local" name="t_bitis_tarihi" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Su Derecesi (°C)</label>
                                <input type="number" step="0.1" name="t_su_derecesi" class="form-control"
                                    placeholder="Örn: 22.5">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ortam Derecesi (°C)</label>
                                <input type="number" step="0.1" name="t_ortam_derecesi" class="form-control"
                                    placeholder="Örn: 24.0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Toplam Tonaj / KG</label>
                                <input type="number" step="0.01" name="t_toplam_tonaj" id="tToplamTonaj"
                                    class="form-control" placeholder="Otomatik veya Manuel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Karışım Değerleri / Notlar</label>
                                <input type="text" name="t_karisim_degerleri" class="form-control"
                                    placeholder="Ekstra değer, reçete vs.">
                            </div>
                        </div>
                    </div>

                    <!-- TAVLAMA 1 TABLOSU -->
                    <div class="pacal-table-wrapper">
                        <table class="table table-bordered pacal-table mb-0" id="tavlama1Tablo">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sira-no">S.No</th>
                                    <th rowspan="2">Yaş Ambar No</th>
                                    <th colspan="7" class="group-lab">LAB DEĞERLERİ</th>
                                    <th colspan="6" class="group-alveo">ALVEO DEĞERLERİ</th>
                                    <th colspan="3" class="group-perten">PERTEN</th>
                                </tr>
                                <tr>
                                    <!-- LAB -->
                                    <th class="group-lab"
                                        style="background:#fcf8e3 !important; color:#8a6d3b !important;">Hedef Nem</th>
                                    <th class="group-lab">Nem</th>
                                    <th class="group-lab">Gluten</th>
                                    <th class="group-lab">G.Index</th>
                                    <th class="group-lab">N.Sedim</th>
                                    <th class="group-lab">G.Sedim</th>
                                    <th class="group-lab">Hektolitre</th>

                                    <!-- ALVEO -->
                                    <th class="group-alveo">P</th>
                                    <th class="group-alveo">G</th>
                                    <th class="group-alveo">P/L</th>
                                    <th class="group-alveo">W</th>
                                    <th class="group-alveo">IE</th>
                                    <th class="group-alveo">FN</th>
                                    <!-- PERTEN -->
                                    <th class="group-perten">Protein</th>
                                    <th class="group-perten">Sertlik</th>
                                    <th class="group-perten">Nişasta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr data-trow="<?php echo $i; ?>">
                                        <td class="sira-no"><?php echo $i; ?></td>
                                        <td>
                                            <input type="text" name="tsatir[<?php echo $i; ?>][yas_ambar_no]"
                                                class="form-control" placeholder="Ambar No">
                                        </td>
                                        <!-- Hedef Nem (Manuel) -->
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][hedef_nem]"
                                                class="form-control text-primary font-weight-bold" placeholder="-"></td>

                                        <!-- Lab Değerleri (Otomatik ama manuel editable) -->
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][nem]"
                                                class="form-control lab-val t-nem" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][gluten]"
                                                class="form-control lab-val t-gluten" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="tsatir[<?php echo $i; ?>][g_index]"
                                                class="form-control lab-val t-g_index" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="tsatir[<?php echo $i; ?>][n_sedim]"
                                                class="form-control lab-val t-n_sedim" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="tsatir[<?php echo $i; ?>][g_sedim]"
                                                class="form-control lab-val t-g_sedim" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][hektolitre]"
                                                class="form-control lab-val t-hektolitre" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Alveo Değerleri -->
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][alveo_p]"
                                                class="form-control alveo-manual t-alveo_p" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][alveo_g]"
                                                class="form-control alveo-manual t-alveo_g" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][alveo_pl]"
                                                class="form-control alveo-manual t-alveo_pl" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="tsatir[<?php echo $i; ?>][alveo_w]"
                                                class="form-control alveo-manual t-alveo_w" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="tsatir[<?php echo $i; ?>][alveo_ie]"
                                                class="form-control alveo-manual t-alveo_ie" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="tsatir[<?php echo $i; ?>][fn]"
                                                class="form-control alveo-manual t-fn" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Perten Değerleri -->
                                        <td><input type="number" step="0.01"
                                                name="tsatir[<?php echo $i; ?>][perten_protein]"
                                                class="form-control lab-val t-perten_protein" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="tsatir[<?php echo $i; ?>][perten_sertlik]"
                                                class="form-control lab-val t-perten_sertlik" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="tsatir[<?php echo $i; ?>][perten_nisasta]"
                                                class="form-control lab-val t-perten_nisasta" data-trow="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="tavlama1_kaydet" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Tavlama 1 İşlemini Kaydet
                        </button>
                    </div>
                </form>

                <!-- SON TAVLAMA 1 KAYITLARI -->
                <div class="card history-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son Tavlama 1 Kayıtları</span>
                        <span class="badge bg-secondary">
                            <?php echo $son_tavlamalar ? $son_tavlamalar->num_rows : 0; ?>
                            kayıt
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Başlama</th>
                                    <th>Bitiş</th>
                                    <th>Paçal Parti No</th>
                                    <th>Ürün</th>
                                    <th>Tonaj</th>
                                    <th>Su °C</th>
                                    <th>Ortam °C</th>
                                    <th>Ambar</th>
                                    <th>Oluşturan</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($son_tavlamalar && $son_tavlamalar->num_rows > 0) {
                                    while ($st = $son_tavlamalar->fetch_assoc()) { ?>
                                        <tr>
                                            <td>
                                                <?php echo $st["baslama_tarihi"] ? date("d.m.Y H:i", strtotime($st["baslama_tarihi"])) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $st["bitis_tarihi"] ? date("d.m.Y H:i", strtotime($st["bitis_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><span class="badge bg-dark">
                                                    <?php echo htmlspecialchars($st["pacal_parti_no"] ?? '-'); ?>
                                                </span></td>
                                            <td>
                                                <?php echo htmlspecialchars($st["urun_adi"] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <?php echo $st["toplam_tonaj"] ? number_format($st["toplam_tonaj"], 0, ',', '.') : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $st["su_derecesi"] ?? '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $st["ortam_derecesi"] ?? '-'; ?>
                                            </td>
                                            <td><span class="badge bg-light text-dark">
                                                    <?php echo $st["satir_sayisi"]; ?> ambar
                                                </span></td>
                                            <td>
                                                <?php echo htmlspecialchars($st["olusturan"] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <a href="uretim.php?tavlama_sil=<?php echo $st["id"]; ?>"
                                                    class="btn btn-sm btn-outline-danger sil-btn-tavlama" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">Henüz tavlama kaydı yok</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ================== SEKME 3: TAVLAMA 2 (PLACEHOLDER) ================== -->
            <div class="tab-pane fade p-3" id="tavlama2" role="tabpanel">
                <form action="uretim.php" method="POST" id="tavlama2Form">
                    <!-- TAVLAMA 2 HEADER -->
                    <div class="pacal-header mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label required-field"><i class="fas fa-link me-1"></i> Bağlı Tavlama
                                    1
                                    Kaydı (Paçal)</label>
                                <select name="t2_tavlama1_id" class="form-select" id="t2Tavlama1Select"
                                    onchange="tavlama2Tavlama1Secildi(this)" required>
                                    <option value="">-- Tavlama 1 Seçiniz --</option>
                                    <?php if ($tum_tavlama1_kayitlari && $tum_tavlama1_kayitlari->num_rows > 0): ?>
                                        <?php while ($t1 = $tum_tavlama1_kayitlari->fetch_assoc()): ?>
                                            <option value="<?php echo $t1['id']; ?>"
                                                data-tonaj="<?php echo $t1['toplam_tonaj']; ?>"
                                                data-baslama="<?php echo $t1['baslama_tarihi']; ?>">
                                                Paçal:
                                                <?php echo htmlspecialchars($t1['parti_no']) . " | Ürün: " . htmlspecialchars($t1['urun_adi']) . " | Tav.1 Başlama: " . date("d.m.Y H:i", strtotime($t1['baslama_tarihi'])); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Tav. 2 Başlama Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="t2_baslama_tarihi" required>
                                <div class="form-text text-danger" style="font-size:0.75rem;">(Tavlama 1 Başlama ile
                                    arasında en az 24 saat olmalıdır)</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tav. 2 Bitiş Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="t2_bitis_tarihi">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Toplam Tonaj / KG</label>
                                <input type="number" step="0.01" name="t2_toplam_tonaj" id="t2ToplamTonaj"
                                    class="form-control" placeholder="Otomatik veya Manuel">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Su Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="t2_su_derecesi">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Ortam Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="t2_ortam_derecesi">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Karışım Değerleri / Notlar</label>
                                <input type="text" class="form-control" name="t2_karisim_degerleri"
                                    placeholder="Manuel not veya özel karışım detayları girebilirsiniz...">
                            </div>
                        </div>
                    </div>

                    <!-- TAVLAMA 2 TABLOSU -->
                    <div class="pacal-table-wrapper">
                        <table class="table table-bordered pacal-table mb-0" id="tavlama2Tablo">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sira-no">S.No</th>
                                    <th rowspan="2">Yaş Ambar No</th>
                                    <th colspan="7" class="group-lab">LAB DEĞERLERİ</th>
                                    <th colspan="6" class="group-alveo">ALVEO DEĞERLERİ</th>
                                    <th colspan="3" class="group-perten">PERTEN</th>
                                </tr>
                                <tr>
                                    <!-- LAB -->
                                    <th class="group-lab"
                                        style="background:#fcf8e3 !important; color:#8a6d3b !important;">Hedef Nem</th>
                                    <th class="group-lab">Nem</th>
                                    <th class="group-lab">Gluten</th>
                                    <th class="group-lab">G.Index</th>
                                    <th class="group-lab">N.Sedim</th>
                                    <th class="group-lab">G.Sedim</th>
                                    <th class="group-lab">Hektolitre</th>

                                    <!-- ALVEO -->
                                    <th class="group-alveo">P</th>
                                    <th class="group-alveo">G</th>
                                    <th class="group-alveo">P/L</th>
                                    <th class="group-alveo">W</th>
                                    <th class="group-alveo">IE</th>
                                    <th class="group-alveo">FN</th>
                                    <!-- PERTEN -->
                                    <th class="group-perten">Protein</th>
                                    <th class="group-perten">Sertlik</th>
                                    <th class="group-perten">Nişasta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr data-trow="<?php echo $i; ?>">
                                        <td class="sira-no"><?php echo $i; ?></td>
                                        <td>
                                            <input type="text" name="t2satir[<?php echo $i; ?>][yas_ambar_no]"
                                                class="form-control" placeholder="Ambar No">
                                        </td>
                                        <!-- Hedef Nem (Manuel) -->
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][hedef_nem]"
                                                class="form-control text-primary font-weight-bold" placeholder="-"></td>

                                        <!-- Lab Değerleri (Otomatik ama manuel editable) -->
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][nem]"
                                                class="form-control lab-val t2-nem" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][gluten]"
                                                class="form-control lab-val t2-gluten" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t2satir[<?php echo $i; ?>][g_index]"
                                                class="form-control lab-val t2-g_index" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t2satir[<?php echo $i; ?>][n_sedim]"
                                                class="form-control lab-val t2-n_sedim" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t2satir[<?php echo $i; ?>][g_sedim]"
                                                class="form-control lab-val t2-g_sedim" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][hektolitre]"
                                                class="form-control lab-val t2-hektolitre" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Alveo Değerleri -->
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][alveo_p]"
                                                class="form-control alveo-manual t2-alveo_p" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][alveo_g]"
                                                class="form-control alveo-manual t2-alveo_g" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][alveo_pl]"
                                                class="form-control alveo-manual t2-alveo_pl" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t2satir[<?php echo $i; ?>][alveo_w]"
                                                class="form-control alveo-manual t2-alveo_w" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t2satir[<?php echo $i; ?>][alveo_ie]"
                                                class="form-control alveo-manual t2-alveo_ie" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t2satir[<?php echo $i; ?>][fn]"
                                                class="form-control alveo-manual t2-fn" data-t2row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Perten Değerleri -->
                                        <td><input type="number" step="0.01"
                                                name="t2satir[<?php echo $i; ?>][perten_protein]"
                                                class="form-control lab-val t2-perten_protein"
                                                data-t2row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="t2satir[<?php echo $i; ?>][perten_sertlik]"
                                                class="form-control lab-val t2-perten_sertlik"
                                                data-t2row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="t2satir[<?php echo $i; ?>][perten_nisasta]"
                                                class="form-control lab-val t2-perten_nisasta"
                                                data-t2row="<?php echo $i; ?>" placeholder="-"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="tavlama2_kaydet" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Tavlama 2 İşlemini Kaydet
                        </button>
                    </div>
                </form>

                <!-- SON TAVLAMA 2 KAYITLARI -->
                <div class="card history-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son Tavlama 2 Kayıtları</span>
                        <span
                            class="badge bg-secondary"><?php echo $son_tavlamalar2 ? $son_tavlamalar2->num_rows : 0; ?>
                            kayıt</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Başlama</th>
                                    <th>Bitiş</th>
                                    <th>Paçal Parti No</th>
                                    <th>Ürün</th>
                                    <th>Tonaj</th>
                                    <th>Su °C</th>
                                    <th>Ortam °C</th>
                                    <th>Ambar</th>
                                    <th>Oluşturan</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($son_tavlamalar2 && $son_tavlamalar2->num_rows > 0) {
                                    while ($st2 = $son_tavlamalar2->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $st2["baslama_tarihi"] ? date("d.m.Y H:i", strtotime($st2["baslama_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><?php echo $st2["bitis_tarihi"] ? date("d.m.Y H:i", strtotime($st2["bitis_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-dark"><?php echo htmlspecialchars($st2["pacal_parti_no"] ?? '-'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($st2["urun_adi"] ?? '-'); ?></td>
                                            <td><?php echo $st2["toplam_tonaj"] ? number_format($st2["toplam_tonaj"], 0, ',', '.') : '-'; ?>
                                            </td>
                                            <td><?php echo $st2["su_derecesi"] ?? '-'; ?></td>
                                            <td><?php echo $st2["ortam_derecesi"] ?? '-'; ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo $st2["satir_sayisi"]; ?>
                                                    ambar</span></td>
                                            <td><?php echo htmlspecialchars($st2["olusturan"] ?? '-'); ?></td>
                                            <td>
                                                <a href="uretim.php?tavlama2_sil=<?php echo $st2["id"]; ?>"
                                                    class="btn btn-sm btn-outline-danger sil-btn-tavlama2" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">Henüz tavlama 2 kaydı yok</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ================== SEKME 4: TAVLAMA 3 ================== -->
            <div class="tab-pane fade p-3" id="tavlama3" role="tabpanel">
                <form action="uretim.php" method="POST" id="tavlama3Form">
                    <!-- TAVLAMA 3 HEADER -->
                    <div class="pacal-header mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label required-field"><i class="fas fa-link me-1"></i> Bağlı Tavlama
                                    2
                                    Kaydı</label>
                                <select name="t3_tavlama2_id" class="form-select" id="t3Tavlama2Select"
                                    onchange="tavlama3Tavlama2Secildi(this)" required>
                                    <option value="">-- Tavlama 2 Seçiniz --</option>
                                    <?php if ($tum_tavlama2_kayitlari && $tum_tavlama2_kayitlari->num_rows > 0): ?>
                                        <?php while ($t2 = $tum_tavlama2_kayitlari->fetch_assoc()): ?>
                                            <option value="<?php echo $t2['id']; ?>"
                                                data-tonaj="<?php echo $t2['toplam_tonaj']; ?>"
                                                data-baslama="<?php echo $t2['baslama_tarihi']; ?>">
                                                Paçal:
                                                <?php echo htmlspecialchars($t2['parti_no']) . " | Ürün: " . htmlspecialchars($t2['urun_adi']) . " | Tav.2 Başlama: " . date("d.m.Y H:i", strtotime($t2['baslama_tarihi'])); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Tav. 3 Başlama Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="t3_baslama_tarihi" required>
                                <div class="form-text text-danger" style="font-size:0.75rem;">(Tavlama 2 Başlama ile
                                    arasında en az 19 saat olmalıdır)</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tav. 3 Bitiş Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="t3_bitis_tarihi">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Toplam Tonaj / KG</label>
                                <input type="number" step="0.01" name="t3_toplam_tonaj" id="t3ToplamTonaj"
                                    class="form-control" placeholder="Otomatik veya Manuel">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Su Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="t3_su_derecesi">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Ortam Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="t3_ortam_derecesi">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Karışım Değerleri / Notlar</label>
                                <input type="text" class="form-control" name="t3_karisim_degerleri"
                                    placeholder="Manuel not veya özel karışım detayları girebilirsiniz...">
                            </div>
                        </div>
                    </div>

                    <!-- TAVLAMA 3 TABLOSU -->
                    <div class="pacal-table-wrapper">
                        <table class="table table-bordered pacal-table mb-0" id="tavlama3Tablo">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sira-no">S.No</th>
                                    <th rowspan="2">Yaş Ambar No</th>
                                    <th colspan="7" class="group-lab">LAB DEĞERLERİ</th>
                                    <th colspan="6" class="group-alveo">ALVEO DEĞERLERİ</th>
                                    <th colspan="3" class="group-perten">PERTEN</th>
                                </tr>
                                <tr>
                                    <!-- LAB -->
                                    <th class="group-lab"
                                        style="background:#fcf8e3 !important; color:#8a6d3b !important;">Hedef Nem</th>
                                    <th class="group-lab">Nem</th>
                                    <th class="group-lab">Gluten</th>
                                    <th class="group-lab">G.Index</th>
                                    <th class="group-lab">N.Sedim</th>
                                    <th class="group-lab">G.Sedim</th>
                                    <th class="group-lab">Hektolitre</th>

                                    <!-- ALVEO -->
                                    <th class="group-alveo">P</th>
                                    <th class="group-alveo">G</th>
                                    <th class="group-alveo">P/L</th>
                                    <th class="group-alveo">W</th>
                                    <th class="group-alveo">IE</th>
                                    <th class="group-alveo">FN</th>
                                    <!-- PERTEN -->
                                    <th class="group-perten">Protein</th>
                                    <th class="group-perten">Sertlik</th>
                                    <th class="group-perten">Nişasta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr data-t3row="<?php echo $i; ?>">
                                        <td class="sira-no"><?php echo $i; ?></td>
                                        <td>
                                            <input type="text" name="t3satir[<?php echo $i; ?>][yas_ambar_no]"
                                                class="form-control" placeholder="Ambar No">
                                        </td>
                                        <!-- Hedef Nem (Manuel) -->
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][hedef_nem]"
                                                class="form-control text-primary font-weight-bold" placeholder="-"></td>

                                        <!-- Lab Değerleri (Otomatik ama manuel editable) -->
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][nem]"
                                                class="form-control lab-val t3-nem" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][gluten]"
                                                class="form-control lab-val t3-gluten" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t3satir[<?php echo $i; ?>][g_index]"
                                                class="form-control lab-val t3-g_index" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t3satir[<?php echo $i; ?>][n_sedim]"
                                                class="form-control lab-val t3-n_sedim" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t3satir[<?php echo $i; ?>][g_sedim]"
                                                class="form-control lab-val t3-g_sedim" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][hektolitre]"
                                                class="form-control lab-val t3-hektolitre" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Alveo Değerleri -->
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][alveo_p]"
                                                class="form-control alveo-manual t3-alveo_p" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][alveo_g]"
                                                class="form-control alveo-manual t3-alveo_g" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][alveo_pl]"
                                                class="form-control alveo-manual t3-alveo_pl" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t3satir[<?php echo $i; ?>][alveo_w]"
                                                class="form-control alveo-manual t3-alveo_w" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="t3satir[<?php echo $i; ?>][alveo_ie]"
                                                class="form-control alveo-manual t3-alveo_ie" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="t3satir[<?php echo $i; ?>][fn]"
                                                class="form-control alveo-manual t3-fn" data-t3row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Perten Değerleri -->
                                        <td><input type="number" step="0.01"
                                                name="t3satir[<?php echo $i; ?>][perten_protein]"
                                                class="form-control lab-val t3-perten_protein"
                                                data-t3row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="t3satir[<?php echo $i; ?>][perten_sertlik]"
                                                class="form-control lab-val t3-perten_sertlik"
                                                data-t3row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="t3satir[<?php echo $i; ?>][perten_nisasta]"
                                                class="form-control lab-val t3-perten_nisasta"
                                                data-t3row="<?php echo $i; ?>" placeholder="-"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="tavlama3_kaydet" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Tavlama 3 İşlemini Kaydet
                        </button>
                    </div>
                </form>

                <!-- SON TAVLAMA 3 KAYITLARI -->
                <div class="card history-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son Tavlama 3 Kayıtları</span>
                        <span
                            class="badge bg-secondary"><?php echo $son_tavlamalar3 ? $son_tavlamalar3->num_rows : 0; ?>
                            kayıt</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Başlama</th>
                                    <th>Bitiş</th>
                                    <th>Paçal Parti No</th>
                                    <th>Ürün</th>
                                    <th>Tonaj</th>
                                    <th>Su °C</th>
                                    <th>Ortam °C</th>
                                    <th>Ambar</th>
                                    <th>Oluşturan</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($son_tavlamalar3 && $son_tavlamalar3->num_rows > 0) {
                                    while ($st3 = $son_tavlamalar3->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $st3["baslama_tarihi"] ? date("d.m.Y H:i", strtotime($st3["baslama_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><?php echo $st3["bitis_tarihi"] ? date("d.m.Y H:i", strtotime($st3["bitis_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-dark"><?php echo htmlspecialchars($st3["pacal_parti_no"] ?? '-'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($st3["urun_adi"] ?? '-'); ?></td>
                                            <td><?php echo $st3["toplam_tonaj"] ? number_format($st3["toplam_tonaj"], 0, ',', '.') : '-'; ?>
                                            </td>
                                            <td><?php echo $st3["su_derecesi"] ?? '-'; ?></td>
                                            <td><?php echo $st3["ortam_derecesi"] ?? '-'; ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo $st3["satir_sayisi"]; ?>
                                                    ambar</span></td>
                                            <td><?php echo htmlspecialchars($st3["olusturan"] ?? '-'); ?></td>
                                            <td>
                                                <a href="uretim.php?tavlama3_sil=<?php echo $st3["id"]; ?>"
                                                    class="btn btn-sm btn-outline-danger sil-btn-tavlama3" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">Henüz tavlama 3 kaydı yok</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ================== SEKME 5: B1 ÜRETİME GİRİŞ ================== -->
            <div class="tab-pane fade p-3" id="b1" role="tabpanel">
                <form action="uretim.php" method="POST" id="b1Form">
                    <!-- B1 HEADER -->
                    <div class="pacal-header mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label required-field"><i class="fas fa-link me-1"></i> Bağlı
                                    Tavlama 3 Kaydı</label>
                                <select name="b1_tavlama3_id" class="form-select" id="b1Tavlama3Select"
                                    onchange="b1Tavlama3Secildi(this)" required>
                                    <option value="">-- Tavlama 3 Seçiniz --</option>
                                    <?php if ($tum_tavlama3_kayitlari && $tum_tavlama3_kayitlari->num_rows > 0): ?>
                                        <?php while ($t3 = $tum_tavlama3_kayitlari->fetch_assoc()): ?>
                                            <option value="<?php echo $t3['id']; ?>"
                                                data-tonaj="<?php echo $t3['toplam_tonaj']; ?>"
                                                data-t1baslama="<?php echo $t3['t1_baslama_tarihi']; ?>">
                                                Paçal:
                                                <?php echo htmlspecialchars($t3['parti_no']) . " | Ürün: " . htmlspecialchars($t3['urun_adi']) . " | Tav.3 Başlama: " . date("d.m.Y H:i", strtotime($t3['baslama_tarihi'])); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">B1 Başlama Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="b1_baslama_tarihi" required>
                                <div class="form-text text-danger" style="font-size:0.75rem;">(Tavlama 1 Başlama ile
                                    arasında en az 95 Saat 50 Dakika olmalıdır)</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">B1 Bitiş Tarihi/Saati</label>
                                <input type="datetime-local" class="form-control" name="b1_bitis_tarihi">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">B1 Tonaj / KG</label>
                                <input type="number" step="0.01" name="b1_tonaj" id="b1Tonaj" class="form-control"
                                    placeholder="Otomatik veya Manuel">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Su Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="b1_su_derecesi">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tavlama Ortam Derecesi (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="b1_ortam_derecesi">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Karışım Değerleri / Notlar</label>
                                <input type="text" class="form-control" name="b1_karisim_degerleri"
                                    placeholder="Manuel not veya özel karışım detayları girebilirsiniz...">
                            </div>
                        </div>
                    </div>

                    <!-- B1 TABLOSU -->
                    <div class="pacal-table-wrapper">
                        <table class="table table-bordered pacal-table mb-0" id="b1Tablo">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sira-no">S.No</th>
                                    <th rowspan="2">Yaş Ambar No</th>
                                    <th colspan="7" class="group-lab">LAB DEĞERLERİ</th>
                                    <th colspan="6" class="group-alveo">ALVEO DEĞERLERİ</th>
                                    <th colspan="3" class="group-perten">PERTEN</th>
                                </tr>
                                <tr>
                                    <!-- LAB -->
                                    <th class="group-lab"
                                        style="background:#fcf8e3 !important; color:#8a6d3b !important;">Hedef Nem</th>
                                    <th class="group-lab">Nem</th>
                                    <th class="group-lab">Gluten</th>
                                    <th class="group-lab">G.Index</th>
                                    <th class="group-lab">N.Sedim</th>
                                    <th class="group-lab">G.Sedim</th>
                                    <th class="group-lab">Hektolitre</th>

                                    <!-- ALVEO -->
                                    <th class="group-alveo">P</th>
                                    <th class="group-alveo">G</th>
                                    <th class="group-alveo">P/L</th>
                                    <th class="group-alveo">W</th>
                                    <th class="group-alveo">IE</th>
                                    <th class="group-alveo">FN</th>
                                    <!-- PERTEN -->
                                    <th class="group-perten">Protein</th>
                                    <th class="group-perten">Sertlik</th>
                                    <th class="group-perten">Nişasta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr data-b1row="<?php echo $i; ?>">
                                        <td class="sira-no"><?php echo $i; ?></td>
                                        <td>
                                            <input type="text" name="b1satir[<?php echo $i; ?>][yas_ambar_no]"
                                                class="form-control" placeholder="Ambar No">
                                        </td>
                                        <!-- Hedef Nem (Manuel) -->
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][hedef_nem]"
                                                class="form-control text-primary font-weight-bold" placeholder="-"></td>

                                        <!-- Lab Değerleri (Otomatik ama manuel editable) -->
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][nem]"
                                                class="form-control lab-val b1-nem" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][gluten]"
                                                class="form-control lab-val b1-gluten" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="b1satir[<?php echo $i; ?>][g_index]"
                                                class="form-control lab-val b1-g_index" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="b1satir[<?php echo $i; ?>][n_sedim]"
                                                class="form-control lab-val b1-n_sedim" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="b1satir[<?php echo $i; ?>][g_sedim]"
                                                class="form-control lab-val b1-g_sedim" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][hektolitre]"
                                                class="form-control lab-val b1-hektolitre" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Alveo Değerleri -->
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][alveo_p]"
                                                class="form-control alveo-manual b1-alveo_p" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][alveo_g]"
                                                class="form-control alveo-manual b1-alveo_g" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][alveo_pl]"
                                                class="form-control alveo-manual b1-alveo_pl" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="b1satir[<?php echo $i; ?>][alveo_w]"
                                                class="form-control alveo-manual b1-alveo_w" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="b1satir[<?php echo $i; ?>][alveo_ie]"
                                                class="form-control alveo-manual b1-alveo_ie" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="b1satir[<?php echo $i; ?>][fn]"
                                                class="form-control alveo-manual b1-fn" data-b1row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Perten Değerleri -->
                                        <td><input type="number" step="0.01"
                                                name="b1satir[<?php echo $i; ?>][perten_protein]"
                                                class="form-control lab-val b1-perten_protein"
                                                data-b1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="b1satir[<?php echo $i; ?>][perten_sertlik]"
                                                class="form-control lab-val b1-perten_sertlik"
                                                data-b1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="b1satir[<?php echo $i; ?>][perten_nisasta]"
                                                class="form-control lab-val b1-perten_nisasta"
                                                data-b1row="<?php echo $i; ?>" placeholder="-"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="b1_kaydet" class="btn btn-warning btn-lg px-5 text-dark fw-bold">
                            <i class="fas fa-save me-2"></i>B1 İşlemini Kaydet
                        </button>
                    </div>
                </form>

                <!-- SON B1 KAYITLARI -->
                <div class="card history-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son B1 Kayıtları</span>
                        <span
                            class="badge bg-secondary"><?php echo $son_b1_kayitlari ? $son_b1_kayitlari->num_rows : 0; ?>
                            kayıt</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Başlama</th>
                                    <th>Bitiş</th>
                                    <th>Paçal Parti No</th>
                                    <th>Ürün</th>
                                    <th>B1 Tonaj</th>
                                    <th>Su °C</th>
                                    <th>Ortam °C</th>
                                    <th>Ambar</th>
                                    <th>Oluşturan</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($son_b1_kayitlari && $son_b1_kayitlari->num_rows > 0) {
                                    while ($sb1 = $son_b1_kayitlari->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $sb1["baslama_tarihi"] ? date("d.m.Y H:i", strtotime($sb1["baslama_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><?php echo $sb1["bitis_tarihi"] ? date("d.m.Y H:i", strtotime($sb1["bitis_tarihi"])) : '-'; ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-dark"><?php echo htmlspecialchars($sb1["pacal_parti_no"] ?? '-'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($sb1["urun_adi"] ?? '-'); ?></td>
                                            <td><?php echo $sb1["b1_tonaj"] ? number_format($sb1["b1_tonaj"], 0, ',', '.') : '-'; ?>
                                            </td>
                                            <td><?php echo $sb1["su_derecesi"] ?? '-'; ?></td>
                                            <td><?php echo $sb1["ortam_derecesi"] ?? '-'; ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo $sb1["satir_sayisi"]; ?>
                                                    ambar</span></td>
                                            <td><?php echo htmlspecialchars($sb1["olusturan"] ?? '-'); ?></td>
                                            <td>
                                                <a href="uretim.php?b1_sil=<?php echo $sb1["id"]; ?>"
                                                    class="btn btn-sm btn-outline-danger sil-btn-b1" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">Henüz B1 kaydı yok</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ================== SEKME 6: UN 1 ================== -->
            <div class="tab-pane fade p-3" id="un1" role="tabpanel">
                <form action="uretim.php" method="POST" id="un1Form">
                    <!-- UN 1 HEADER -->
                    <div class="pacal-header mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required-field"><i class="fas fa-link me-1"></i> Bağlı
                                    B1 Kaydı</label>
                                <select name="un1_b1_id" class="form-select" id="un1B1Select"
                                    onchange="un1B1Secildi(this)" required>
                                    <option value="">-- B1 Seçiniz --</option>
                                    <?php if ($tum_b1_kayitlari && $tum_b1_kayitlari->num_rows > 0): ?>
                                        <?php while ($b1 = $tum_b1_kayitlari->fetch_assoc()): ?>
                                            <option value="<?php echo $b1['id']; ?>">
                                                Paçal:
                                                <?php echo htmlspecialchars($b1['parti_no']) . " | Ürün: " . htmlspecialchars($b1['urun_adi']) . " | B1 Başlama: " . date("d.m.Y H:i", strtotime($b1['baslama_tarihi'])); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field"><i class="fas fa-clock me-1"></i> Numune Saati
                                    (Tarih ve Saat)</label>
                                <input type="datetime-local" name="un1_numune_saati" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <!-- UN 1 DETAY TABLOSU -->
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle pacal-table text-center" id="un1Table">
                            <thead>
                                <tr class="align-middle">
                                    <th rowspan="2" class="sira-no">S.No</th>
                                    <th rowspan="2" style="width: 100px;">Silo Numarası</th>
                                    <th rowspan="2" style="width: 100px;">Miktarı KG</th>

                                    <th colspan="7" class="group-lab">LAB Değerleri</th>
                                    <th colspan="6" class="group-perten">PERTEN IM9500</th>
                                    <th colspan="2" class="group-consistograph">CONSISTOGRAPH</th>
                                    <th colspan="5" class="group-alveo">ALVEO DEĞERLERİ</th>
                                </tr>
                                <tr class="align-middle" style="font-size: 0.8rem;">
                                    <!-- LAB -->
                                    <th class="group-lab">GLUTEN</th>
                                    <th class="group-lab">G.İNDEX</th>
                                    <th class="group-lab">N.SEDİM</th>
                                    <th class="group-lab">G.SEDİM</th>
                                    <th class="group-lab">FN</th>
                                    <th class="group-lab">FFN</th>
                                    <th class="group-lab">S.D</th>

                                    <!-- PERTEN -->
                                    <th class="group-perten">NEM</th>
                                    <th class="group-perten">KÜL</th>
                                    <th class="group-perten">NİŞASTA</th>
                                    <th class="group-perten">RENK B</th>
                                    <th class="group-perten">RENK L</th>
                                    <th class="group-perten">PROTEİN</th>

                                    <!-- CONSISTOGRAPH -->
                                    <th class="group-consistograph">SU KALDIRMA</th>
                                    <th class="group-consistograph">TOL</th>

                                    <!-- ALVEO -->
                                    <th class="group-alveo">T</th>
                                    <th class="group-alveo">A</th>
                                    <th class="group-alveo">T/A</th>
                                    <th class="group-alveo">W</th>
                                    <th class="group-alveo">IE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr>
                                        <td class="sira-no"><?php echo $i; ?></td>
                                        <td>
                                            <input type="text" name="un1satir[<?php echo $i; ?>][silo_no]"
                                                class="form-control fw-bold" placeholder="Silo?">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][miktar_kg]"
                                                class="form-control fw-bold text-primary" placeholder="KG">
                                        </td>

                                        <!-- Lab Değerleri -->
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][gluten]"
                                                class="form-control lab-val un1-gluten" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][g_index]"
                                                class="form-control lab-val un1-g_index" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][n_sedim]"
                                                class="form-control lab-val un1-n_sedim" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][g_sedim]"
                                                class="form-control lab-val un1-g_sedim" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][fn]"
                                                class="form-control lab-val un1-fn" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][ffn]"
                                                class="form-control lab-val un1-ffn" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][s_d]"
                                                class="form-control lab-val un1-s_d" data-un1row="<?php echo $i; ?>"
                                                placeholder="-"></td>

                                        <!-- Perten -->
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][perten_nem]"
                                                class="form-control perten-manual un1-perten_nem"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][perten_kul]"
                                                class="form-control perten-manual un1-perten_kul"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="un1satir[<?php echo $i; ?>][perten_nisasta]"
                                                class="form-control perten-manual un1-perten_nisasta"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="un1satir[<?php echo $i; ?>][perten_renk_b]"
                                                class="form-control perten-manual un1-perten_renk_b"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="un1satir[<?php echo $i; ?>][perten_renk_l]"
                                                class="form-control perten-manual un1-perten_renk_l"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01"
                                                name="un1satir[<?php echo $i; ?>][perten_protein]"
                                                class="form-control perten-manual un1-perten_protein"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>

                                        <!-- Consistograph -->
                                        <td><input type="number" step="0.01"
                                                name="un1satir[<?php echo $i; ?>][cons_su_kaldirma]"
                                                class="form-control consist-manual un1-cons_su_kaldirma"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][cons_tol]"
                                                class="form-control consist-manual un1-cons_tol"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>

                                        <!-- Alveo -->
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][alveo_t]"
                                                class="form-control alveo-manual un1-alveo_t"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][alveo_a]"
                                                class="form-control alveo-manual un1-alveo_a"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][alveo_ta]"
                                                class="form-control alveo-manual un1-alveo_ta"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="1" name="un1satir[<?php echo $i; ?>][alveo_w]"
                                                class="form-control alveo-manual un1-alveo_w"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                        <td><input type="number" step="0.01" name="un1satir[<?php echo $i; ?>][alveo_ie]"
                                                class="form-control alveo-manual un1-alveo_ie"
                                                data-un1row="<?php echo $i; ?>" placeholder="-"></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="un1_kaydet" class="btn btn-secondary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Un 1 İşlemini Kaydet
                        </button>
                    </div>
                </form>

                <!-- SON UN 1 KAYITLARI -->
                <div class="card history-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son Un 1 Kayıtları</span>
                        <span
                            class="badge bg-secondary"><?php echo $son_un1_kayitlari ? $son_un1_kayitlari->num_rows : 0; ?>
                            kayıt</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Numune Saati</th>
                                    <th>Paçal Parti No</th>
                                    <th>Ürün</th>
                                    <th>Silo / Tonaj</th>
                                    <th>Oluşturan</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($son_un1_kayitlari && $son_un1_kayitlari->num_rows > 0) {
                                    while ($su1 = $son_un1_kayitlari->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $su1["numune_saati"] ? date("d.m.Y H:i", strtotime($su1["numune_saati"])) : '-'; ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-dark"><?php echo htmlspecialchars($su1["pacal_parti_no"] ?? '-'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($su1["urun_adi"] ?? '-'); ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo $su1["satir_sayisi"]; ?>
                                                    Silo Kaydı</span></td>
                                            <td><?php echo htmlspecialchars($su1["olusturan"] ?? '-'); ?></td>
                                            <td>
                                                <a href="uretim.php?un1_sil=<?php echo $su1["id"]; ?>"
                                                    class="btn btn-sm btn-outline-danger sil-btn-un1" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">Henüz Un 1 kaydı yok</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>


        // SweetAlert2 mesajlar ve Form Onayı
        document.addEventListener('DOMContentLoaded', function () {



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



            // Tavlama 1 Form Submit (Tarih Kontrolü)
            const tavlama1Form = document.getElementById('tavlama1Form');
            if (tavlama1Form) {
                tavlama1Form.addEventListener('submit', function (e) {
                    const baslama = this.querySelector('input[name="t_baslama_tarihi"]').value;
                    const bitis = this.querySelector('input[name="t_bitis_tarihi"]').value;
                    if (baslama && bitis) {
                        const bTarih = new Date(baslama);
                        const biTarih = new Date(bitis);
                        if (biTarih < bTarih) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Bitiş tarihi, başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                        }
                    }
                });
            }
        });

        /**
         * Tavlama 1 sekmesinde Paçal Seçilince çalışır
         */
        function tavlamaPacalSecildi(selectEl) {
            const pacalId = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const tonajInput = document.getElementById('tToplamTonaj');

            // Tüm satırları temizle
            for (let r = 1; r <= 5; r++) {
                const ambarEl = document.querySelector(`input[name="tsatir[${r}][yas_ambar_no]"]`);
                if (ambarEl) ambarEl.value = '';
            }
            document.querySelectorAll('.t-nem, .t-gluten, .t-g_index, .t-n_sedim, .t-g_sedim, .t-hektolitre, .t-alveo_p, .t-alveo_g, .t-alveo_pl, .t-alveo_w, .t-alveo_ie, .t-fn, .t-perten_protein, .t-perten_sertlik, .t-perten_nisasta').forEach(el => el.value = '');

            if (!pacalId) {
                tonajInput.value = '';
                return;
            }

            // Toplam tonajı oto doldur
            const tonaj = selectedOption.getAttribute('data-tonaj');
            if (tonaj) {
                tonajInput.value = tonaj;
            }

            // Ağırlıklı Ortalamayı AJAX ile Çek
            fetch(`ajax/ajax_get_pacal_ortalama.php?pacal_id=${pacalId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        const d = res.data;
                        const ambarlar = res.yas_ambarlar || [];

                        // Kaç satır dolduracağız? Yaş ambar sayısı kadar (en az 1, en fazla 5)
                        const satirSayisi = ambarlar.length > 0 ? Math.min(ambarlar.length, 5) : 1;

                        for (let r = 1; r <= satirSayisi; r++) {
                            // Yaş Ambar No'yu otomatik yerleştir
                            if (ambarlar[r - 1]) {
                                const ambarEl = document.querySelector(`input[name="tsatir[${r}][yas_ambar_no]"]`);
                                if (ambarEl) ambarEl.value = ambarlar[r - 1];
                            }

                            const setV = (col, val) => {
                                const el = document.querySelector(`.t-${col}[data-trow="${r}"]`);
                                if (el && val !== null && val !== '') el.value = val;
                            };

                            setV('nem', d.nem);
                            setV('gluten', d.gluten);
                            setV('g_index', d.g_index);
                            setV('n_sedim', d.n_sedim);
                            setV('g_sedim', d.g_sedim);
                            setV('hektolitre', d.hektolitre);

                            setV('alveo_p', d.alveo_p);
                            setV('alveo_g', d.alveo_g);
                            setV('alveo_pl', d.alveo_pl);
                            setV('alveo_w', d.alveo_w);
                            setV('alveo_ie', d.alveo_ie);
                            setV('fn', d.fn);

                            setV('perten_protein', d.perten_protein);
                            setV('perten_sertlik', d.perten_sertlik);
                            setV('perten_nisasta', d.perten_nisasta);
                        }
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: 'Seçili paçal için hesaplanabilen ortalama laboratuvar verisi bulunamadı.',
                            showConfirmButton: false,
                            timer: 4000
                        });
                    }
                })
                .catch(err => console.error("Ortalama çekme hatası:", err));
        }

        // Tavlama silme onayı
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.sil-btn-tavlama').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: 'Bu tavlama kaydı kalıcı olarak silinecek!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'Vazgeç'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });

            // Tavlama 2 silme onayı
            document.querySelectorAll('.sil-btn-tavlama2').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: 'Bu tavlama 2 kaydı kalıcı olarak silinecek!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'Vazgeç'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });

            // Tavlama 2 Form Submit (24 Saat ve Tarih Kontrolü)
            const tavlama2Form = document.getElementById('tavlama2Form');
            if (tavlama2Form) {
                tavlama2Form.addEventListener('submit', function (e) {
                    const selectEl = document.getElementById('t2Tavlama1Select');
                    const selectedOption = selectEl.options[selectEl.selectedIndex];
                    const t1Baslama = selectedOption.getAttribute('data-baslama');

                    const t2Baslama = this.querySelector('input[name="t2_baslama_tarihi"]').value;
                    const t2Bitis = this.querySelector('input[name="t2_bitis_tarihi"]').value;

                    if (t2Bitis && t2Baslama) {
                        if (new Date(t2Bitis) < new Date(t2Baslama)) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Bitiş tarihi, başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }
                    }

                    if (t1Baslama && t2Baslama) {
                        const dateT1 = new Date(t1Baslama);
                        const dateT2 = new Date(t2Baslama);
                        const diffTime = Math.abs(dateT2 - dateT1);
                        const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));

                        if (dateT2 < dateT1) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Tavlama 2 başlama tarihi, Tavlama 1 başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }

                        if (diffHours < 24) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Zaman Hatası!',
                                text: 'Tavlama 1 ile Tavlama 2 arasında en az 24 saat fark olmalıdır.',
                                confirmButtonColor: '#0f172a'
                            });
                        }
                    }
                });
            }
        });

        /**
         * Tavlama 2 sekmesinde Tavlama 1 Seçilince çalışır
         */
        function tavlama2Tavlama1Secildi(selectEl) {
            const t1Id = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const tonajInput = document.getElementById('t2ToplamTonaj');

            // Tüm satırları temizle
            for (let r = 1; r <= 5; r++) {
                const ambarEl = document.querySelector(`input[name="t2satir[${r}][yas_ambar_no]"]`);
                if (ambarEl) ambarEl.value = '';
            }
            document.querySelectorAll('.t2-nem, .t2-gluten, .t2-g_index, .t2-n_sedim, .t2-g_sedim, .t2-hektolitre, .t2-alveo_p, .t2-alveo_g, .t2-alveo_pl, .t2-alveo_w, .t2-alveo_ie, .t2-fn, .t2-perten_protein, .t2-perten_sertlik, .t2-perten_nisasta').forEach(el => el.value = '');

            if (!t1Id) {
                tonajInput.value = '';
                return;
            }

            // Toplam tonajı oto doldur
            const tonaj = selectedOption.getAttribute('data-tonaj');
            if (tonaj) {
                tonajInput.value = tonaj;
            }

            // Tavlama 1 detaylarını çek
            fetch(`ajax/ajax_get_tavlama1_detay.php?tavlama_1_id=${t1Id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.detaylar) {
                        const detaylar = res.detaylar;

                        for (let r = 1; r <= detaylar.length; r++) {
                            const row = detaylar[r - 1];

                            const ambarEl = document.querySelector(`input[name="t2satir[${r}][yas_ambar_no]"]`);
                            if (ambarEl && row.yas_ambar_no) ambarEl.value = row.yas_ambar_no;

                            const setV = (col, val) => {
                                const el = document.querySelector(`.t2-${col}[data-t2row="${r}"]`);
                                if (el && val !== null && val !== '') el.value = val;
                            };

                            setV('nem', row.nem);
                            setV('gluten', row.gluten);
                            setV('g_index', row.g_index);
                            setV('n_sedim', row.n_sedim);
                            setV('g_sedim', row.g_sedim);
                            setV('hektolitre', row.hektolitre);

                            setV('alveo_p', row.alveo_p);
                            setV('alveo_g', row.alveo_g);
                            setV('alveo_pl', row.alveo_pl);
                            setV('alveo_w', row.alveo_w);
                            setV('alveo_ie', row.alveo_ie);
                            setV('fn', row.fn);

                            setV('perten_protein', row.perten_protein);
                            setV('perten_sertlik', row.perten_sertlik);
                            setV('perten_nisasta', row.perten_nisasta);
                        }
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: 'Bu kayıt için detay bulunamadı.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(err => console.error("Tavlama 1 veri çekme hatası:", err));
        }

        // Tavlama 3 DOMContentLoaded İçi İşlemler (Dışarıda document.ready benzeri tekrar ekleyebiliriz)
        document.addEventListener('DOMContentLoaded', function () {
            // Tavlama 3 silme onayı
            document.querySelectorAll('.sil-btn-tavlama3').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: 'Bu tavlama 3 kaydı kalıcı olarak silinecek!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'Vazgeç'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });

            // Tavlama 3 Form Submit (19 Saat ve Tarih Kontrolü)
            const tavlama3Form = document.getElementById('tavlama3Form');
            if (tavlama3Form) {
                tavlama3Form.addEventListener('submit', function (e) {
                    const selectEl = document.getElementById('t3Tavlama2Select');
                    const selectedOption = selectEl.options[selectEl.selectedIndex];
                    const t2Baslama = selectedOption.getAttribute('data-baslama');

                    const t3Baslama = this.querySelector('input[name="t3_baslama_tarihi"]').value;
                    const t3Bitis = this.querySelector('input[name="t3_bitis_tarihi"]').value;

                    if (t3Bitis && t3Baslama) {
                        if (new Date(t3Bitis) < new Date(t3Baslama)) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Bitiş tarihi, başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }
                    }

                    if (t2Baslama && t3Baslama) {
                        const dateT2 = new Date(t2Baslama);
                        const dateT3 = new Date(t3Baslama);
                        const diffTime = Math.abs(dateT3 - dateT2);
                        const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));

                        if (dateT3 < dateT2) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Tavlama 3 başlama tarihi, Tavlama 2 başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }

                        if (diffHours < 19) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Zaman Hatası!',
                                text: 'Tavlama 2 ile Tavlama 3 arasında en az 19 saat fark olmalıdır.',
                                confirmButtonColor: '#0f172a'
                            });
                        }
                    }
                });
            }
        });

        /**
         * Tavlama 3 sekmesinde Tavlama 2 Seçilince çalışır
         */
        function tavlama3Tavlama2Secildi(selectEl) {
            const t2Id = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const tonajInput = document.getElementById('t3ToplamTonaj');

            // Tüm satırları temizle
            for (let r = 1; r <= 5; r++) {
                const ambarEl = document.querySelector(`input[name="t3satir[${r}][yas_ambar_no]"]`);
                if (ambarEl) ambarEl.value = '';
            }
            document.querySelectorAll('.t3-nem, .t3-gluten, .t3-g_index, .t3-n_sedim, .t3-g_sedim, .t3-hektolitre, .t3-alveo_p, .t3-alveo_g, .t3-alveo_pl, .t3-alveo_w, .t3-alveo_ie, .t3-fn, .t3-perten_protein, .t3-perten_sertlik, .t3-perten_nisasta').forEach(el => el.value = '');

            if (!t2Id) {
                tonajInput.value = '';
                return;
            }

            // Toplam tonajı oto doldur
            const tonaj = selectedOption.getAttribute('data-tonaj');
            if (tonaj) {
                tonajInput.value = tonaj;
            }

            // Tavlama 2 detaylarını çek
            fetch(`ajax/ajax_get_tavlama2_detay.php?tavlama_2_id=${t2Id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.detaylar) {
                        const detaylar = res.detaylar;

                        for (let r = 1; r <= detaylar.length; r++) {
                            const row = detaylar[r - 1];

                            const ambarEl = document.querySelector(`input[name="t3satir[${r}][yas_ambar_no]"]`);
                            if (ambarEl && row.yas_ambar_no) ambarEl.value = row.yas_ambar_no;

                            const setV = (col, val) => {
                                const el = document.querySelector(`.t3-${col}[data-t3row="${r}"]`);
                                if (el && val !== null && val !== '') el.value = val;
                            };

                            setV('nem', row.nem);
                            setV('gluten', row.gluten);
                            setV('g_index', row.g_index);
                            setV('n_sedim', row.n_sedim);
                            setV('g_sedim', row.g_sedim);
                            setV('hektolitre', row.hektolitre);

                            setV('alveo_p', row.alveo_p);
                            setV('alveo_g', row.alveo_g);
                            setV('alveo_pl', row.alveo_pl);
                            setV('alveo_w', row.alveo_w);
                            setV('alveo_ie', row.alveo_ie);
                            setV('fn', row.fn);

                            setV('perten_protein', row.perten_protein);
                            setV('perten_sertlik', row.perten_sertlik);
                            setV('perten_nisasta', row.perten_nisasta);
                        }
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: 'Bu kayıt için detay bulunamadı.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(err => console.error("Tavlama 2 veri çekme hatası:", err));
        }
        // B1 DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
            // B1 silme onayı
            document.querySelectorAll('.sil-btn-b1').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: 'Bu B1 kaydı kalıcı olarak silinecek!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'Vazgeç'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });

            // B1 Form Submit (95 Saat 50 Dakika Kontrolü - Tavlama 1 Başlamasına Göre)
            const b1Form = document.getElementById('b1Form');
            if (b1Form) {
                b1Form.addEventListener('submit', function (e) {
                    const selectEl = document.getElementById('b1Tavlama3Select');
                    const selectedOption = selectEl.options[selectEl.selectedIndex];
                    const t1Baslama = selectedOption.getAttribute('data-t1baslama'); // Tavlama 1 Zamanı!
                    const b1BaslamaTarihi = this.querySelector('input[name="b1_baslama_tarihi"]').value;
                    const b1BitisTarihi = this.querySelector('input[name="b1_bitis_tarihi"]').value;

                    if (b1BitisTarihi && b1BaslamaTarihi) {
                        if (new Date(b1BitisTarihi) < new Date(b1BaslamaTarihi)) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'Bitiş tarihi, başlama tarihinden daha eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }
                    }

                    if (t1Baslama && b1BaslamaTarihi) {
                        const dateT1 = new Date(t1Baslama);
                        const dateB1 = new Date(b1BaslamaTarihi);

                        if (dateB1 < dateT1) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata!',
                                text: 'B1 başlama tarihi, Tavlama 1 başlama tarihinden eski olamaz!',
                                confirmButtonColor: '#0f172a'
                            });
                            return;
                        }

                        // Dakika cinsinden hesapla
                        const diffTime = Math.abs(dateB1 - dateT1);
                        const diffMinutes = Math.floor(diffTime / (1000 * 60));

                        // 95 saat 50 dakika = 5750 dakika
                        if (diffMinutes < 5750) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Zaman Hatası!',
                                text: 'Tavlama 1 saati ile B1 başlama saati arasında en az 95 saat 50 dakika fark olmalıdır.',
                                confirmButtonColor: '#0f172a'
                            });
                        }
                    }
                });
            }
        });

        /**
         * B1 sekmesinde Tavlama 3 Seçilince çalışır
         */
        function b1Tavlama3Secildi(selectEl) {
            const t3Id = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const tonajInput = document.getElementById('b1Tonaj');

            // Tüm satırları temizle
            for (let r = 1; r <= 5; r++) {
                const ambarEl = document.querySelector(`input[name="b1satir[${r}][yas_ambar_no]"]`);
                if (ambarEl) ambarEl.value = '';
            }
            document.querySelectorAll('.b1-nem, .b1-gluten, .b1-g_index, .b1-n_sedim, .b1-g_sedim, .b1-hektolitre, .b1-alveo_p, .b1-alveo_g, .b1-alveo_pl, .b1-alveo_w, .b1-alveo_ie, .b1-fn, .b1-perten_protein, .b1-perten_sertlik, .b1-perten_nisasta').forEach(el => el.value = '');

            if (!t3Id) {
                tonajInput.value = '';
                return;
            }

            // Toplam tonajı oto doldur
            const tonaj = selectedOption.getAttribute('data-tonaj');
            if (tonaj) {
                tonajInput.value = tonaj;
            }

            // Tavlama 3 detaylarını çek
            fetch(`ajax/ajax_get_tavlama3_detay.php?tavlama_3_id=${t3Id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.detaylar) {
                        const detaylar = res.detaylar;

                        for (let r = 1; r <= detaylar.length; r++) {
                            const row = detaylar[r - 1];

                            const ambarEl = document.querySelector(`input[name="b1satir[${r}][yas_ambar_no]"]`);
                            if (ambarEl && row.yas_ambar_no) ambarEl.value = row.yas_ambar_no;

                            const setV = (col, val) => {
                                const el = document.querySelector(`.b1-${col}[data-b1row="${r}"]`);
                                if (el && val !== null && val !== '') el.value = val;
                            };

                            setV('nem', row.nem);
                            setV('gluten', row.gluten);
                            setV('g_index', row.g_index);
                            setV('n_sedim', row.n_sedim);
                            setV('g_sedim', row.g_sedim);
                            setV('hektolitre', row.hektolitre);

                            setV('alveo_p', row.alveo_p);
                            setV('alveo_g', row.alveo_g);
                            setV('alveo_pl', row.alveo_pl);
                            setV('alveo_w', row.alveo_w);
                            setV('alveo_ie', row.alveo_ie);
                            setV('fn', row.fn);

                            setV('perten_protein', row.perten_protein);
                            setV('perten_sertlik', row.perten_sertlik);
                            setV('perten_nisasta', row.perten_nisasta);
                        }
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: 'Bu kayıt için detay bulunamadı.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(err => console.error("Tavlama 3 veri çekme hatası:", err));
        }

        // UN 1 DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
            // UN 1 silme onayı
            document.querySelectorAll('.sil-btn-un1').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: 'Bu Un 1 kaydı kalıcı olarak silinecek!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'Vazgeç'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            });
        });

        /**
         * Un 1 sekmesinde B1 Seçilince çalışır
         */
        function un1B1Secildi(selectEl) {
            const b1Id = selectEl.value;

            // Tüm satırları temizle
            for (let r = 1; r <= 5; r++) {
                const siloEl = document.querySelector(`input[name="un1satir[${r}][silo_no]"]`);
                if (siloEl) siloEl.value = '';
            }
            document.querySelectorAll('.un1-gluten, .un1-g_index, .un1-n_sedim, .un1-g_sedim, .un1-fn, .un1-ffn, .un1-s_d, .un1-perten_nem, .un1-perten_kul, .un1-perten_nisasta, .un1-perten_renk_b, .un1-perten_renk_l, .un1-perten_protein, .un1-cons_su_kaldirma, .un1-cons_tol, .un1-alveo_t, .un1-alveo_a, .un1-alveo_ta, .un1-alveo_w, .un1-alveo_ie').forEach(el => el.value = '');

            if (!b1Id) {
                return;
            }

            // B1 detaylarını çek
            fetch(`ajax/ajax_get_b1_detay.php?b1_id=${b1Id}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.detaylar) {
                        const detaylar = res.detaylar;

                        for (let r = 1; r <= detaylar.length; r++) {
                            const row = detaylar[r - 1];

                            // B1'deki Ambar numarasını Un1'deki Silo Nosuna atayabiliriz 
                            const siloEl = document.querySelector(`input[name="un1satir[${r}][silo_no]"]`);
                            if (siloEl && row.yas_ambar_no) siloEl.value = row.yas_ambar_no;

                            const setV = (col, val) => {
                                const el = document.querySelector(`.un1-${col}[data-un1row="${r}"]`);
                                if (el && val !== null && val !== '') el.value = val;
                            };

                            // Ortak olan değerleri B1 den çekerek formda otomatik yazdıralım. 
                            setV('gluten', row.gluten);
                            setV('g_index', row.g_index);
                            setV('n_sedim', row.n_sedim);
                            setV('g_sedim', row.g_sedim);
                            setV('fn', row.fn);

                            setV('perten_protein', row.perten_protein);
                            setV('perten_nisasta', row.perten_nisasta);

                            // Alveo P -> Alveo T ye falan eşleşmiyor. İsteyene bağlı eklenebilir ama şu an Un1 alanları boş kalıp manuel girilecek.
                            setV('alveo_w', row.alveo_w);
                            setV('alveo_ie', row.alveo_ie);
                        }
                    } else {
                        // Eğer detay yoksa sessizce veya alert ile geçebiliriz
                        console.log("Bu B1 kaydı için detay boş geldi.");
                    }
                })
                .catch(err => console.error("B1 veri çekme hatası:", err));
        }

    </script>
</body>

</html>