<?php
session_start();
include("baglan.php");
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

$hikaye = null;
$aranan_parti = "";

// --- LAB SONUCU GİRİŞİ ---
if (isset($_POST["analiz_kaydet"])) {
    $parti = $_POST["parti_no"];
    $prot = $_POST["protein"];
    $gluten = $_POST["gluten"];
    $idx = $_POST["index_degeri"];
    $sedim = $_POST["sedim"];

    $sql = "INSERT INTO lab_analizleri (parti_no, protein, gluten, index_degeri, sedimantasyon, laborant) 
            VALUES ('$parti', '$prot', '$gluten', '$idx', '$sedim', '{$_SESSION["kadi"]}')";
    $baglanti->query($sql);
    $mesaj = "✅ Analiz sonuçları sisteme işlendi.";
}

// --- İZLENEBİLİRLİK SORGULAMA (PARTİ NO İLE) ---
if (isset($_GET["sorgula"])) {
    $aranan_parti = $_GET["parti_no"];
    $found = false;

    // 1. PAKETLEME BİLGİSİ (Doğum Belgesi)
    $paket = $baglanti->query("SELECT p.*, u.urun_adi FROM paketleme_hareketleri p 
                               JOIN urunler u ON p.urun_id = u.id 
                               WHERE p.parti_no = '$aranan_parti'")->fetch_assoc();

    if ($paket) {
        $hikaye["paket"] = $paket;
        $found = true;

        // 2. SEVKİYAT BİLGİSİ (Bu parti hangi sevkiyata dahil?)
        $sevk_icerik = @$baglanti->query("SELECT si.*, sr.musteri_adi, sr.randevu_tarihi, sr.arac_plaka, 
                                           sr.sofor_adi, sr.durum, sr.miktar_ton
                                           FROM sevkiyat_icerik si 
                                           JOIN sevkiyat_randevulari sr ON si.sevkiyat_id = sr.id 
                                           WHERE si.parti_no = '$aranan_parti'")->fetch_assoc();
        if ($sevk_icerik) {
            $hikaye["sevkiyat"] = $sevk_icerik;
        }

        // 3. DEPO STOK BİLGİSİ (Hangi depoda?)
        $depo_stok = @$baglanti->query("SELECT ds.*, d.depo_adi, d.depo_kodu, u.urun_adi 
                                         FROM depo_stok ds 
                                         LEFT JOIN depolar d ON ds.depo_id = d.id 
                                         LEFT JOIN urunler u ON ds.urun_id = u.id 
                                         WHERE ds.urun_id = {$paket['urun_id']} 
                                         ORDER BY ds.son_guncelleme DESC LIMIT 1")->fetch_assoc();
        if ($depo_stok) {
            $hikaye["depo"] = $depo_stok;
        }

        // 4. LAB ANALİZLERİ (Karne)
        $lab = $baglanti->query("SELECT * FROM lab_analizleri WHERE parti_no = '$aranan_parti'")->fetch_assoc();
        $hikaye["lab"] = $lab;

        // 5. ÜRETİM BİLGİSİ (Parti no ile doğrudan eşleşme)
        $uretim = $baglanti->query("SELECT uh.*, ie.is_kodu
                                    FROM uretim_hareketleri uh 
                                    LEFT JOIN is_emirleri ie ON uh.is_emri_id = ie.id
                                    WHERE uh.parti_no = '$aranan_parti' 
                                    ORDER BY uh.tarih DESC LIMIT 1")->fetch_assoc();
        $hikaye["uretim"] = $uretim;

        // 6. HAMMADDE GİRİŞİ (Yıkama kaydından hammadde_parti_no ile bağlantı)
        $hammadde_parti = null;
        $yk = @$baglanti->query("SELECT hammadde_parti_no FROM yikama_kayitlari WHERE parti_no = '$aranan_parti'")->fetch_assoc();
        if ($yk && $yk['hammadde_parti_no']) {
            $hammadde_parti = $yk['hammadde_parti_no'];
        }
        if ($hammadde_parti) {
            $hammadde = $baglanti->query("SELECT hg.*, h.ad as bugday_cinsi 
                                          FROM hammadde_girisleri hg 
                                          LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                                          WHERE hg.parti_no = '$hammadde_parti'")->fetch_assoc();
        } else {
            $hammadde = $baglanti->query("SELECT hg.*, h.ad as bugday_cinsi 
                                          FROM hammadde_girisleri hg 
                                          LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
                                          ORDER BY hg.tarih DESC LIMIT 1")->fetch_assoc();
        }
        $hikaye["hammadde"] = $hammadde;
    }

    // 7. YIKAMA KAYİTLARI
    $yikama = @$baglanti->query("SELECT * FROM yikama_kayitlari WHERE parti_no = '$aranan_parti'")->fetch_assoc();
    if ($yikama) {
        $hikaye["yikama"] = $yikama;
        $found = true;

        // İlişkili kayıtları da getir (Eski akış devam ediyor ama yenileri de ekliyoruz)
        $aktarma = @$baglanti->query("SELECT * FROM aktarma_kayitlari WHERE yikama_id = {$yikama['id']}")->fetch_assoc();
        if ($aktarma) {
            $hikaye["aktarma"] = $aktarma;

            $b1 = @$baglanti->query("SELECT * FROM b1_degirmen_kayitlari WHERE aktarma_id = {$aktarma['id']}")->fetch_assoc();
            if ($b1) {
                $hikaye["b1"] = $b1;

                $un_cikis = @$baglanti->query("SELECT * FROM un_cikis_kayitlari WHERE b1_id = {$b1['id']}")->fetch_assoc();
                if ($un_cikis) {
                    $hikaye["un_cikis"] = $un_cikis;
                }
            }
        }
    }

    // 8. YENİ ÜRETİM MODÜLÜ VERİLERİ (Paçal -> Tav 1 -> Tav 2 -> Tav 3 -> B1 -> Un 1)
    // Bu kısım parti_no üzerinden uretim_pacal tablosundan başlar
    $pacal = @$baglanti->query("SELECT * FROM uretim_pacal WHERE parti_no = '$aranan_parti'")->fetch_assoc();
    if ($pacal) {
        $hikaye["uretim_pacal"] = $pacal;
        $found = true;

        // Paçal Detayları (Buğdaylar)
        $pacal_id = $pacal['id'];
        $hikaye["uretim_pacal_detay"] = [];
        $pd_res = $baglanti->query("SELECT pd.*, h.ad as bugday_adi FROM uretim_pacal_detay pd LEFT JOIN hammaddeler h ON pd.hammadde_id = h.id WHERE pd.pacal_id = $pacal_id ORDER BY pd.sira_no ASC");
        while($pd = @$pd_res->fetch_assoc()) {
            $hikaye["uretim_pacal_detay"][] = $pd;
        }

        // Tavlama 1
        $t1 = @$baglanti->query("SELECT * FROM uretim_tavlama_1 WHERE pacal_id = $pacal_id")->fetch_assoc();
        if ($t1) {
            $hikaye["tavlama1"] = $t1;
            $t1_id = $t1['id'];
            $hikaye["tavlama1_detay"] = @$baglanti->query("SELECT * FROM uretim_tavlama_1_detay WHERE tavlama_1_id = $t1_id")->fetch_assoc();

            // Tavlama 2
            $t2 = @$baglanti->query("SELECT * FROM uretim_tavlama_2 WHERE tavlama_1_id = $t1_id")->fetch_assoc();
            if ($t2) {
                $hikaye["tavlama2"] = $t2;
                $t2_id = $t2['id'];
                $hikaye["tavlama2_detay"] = @$baglanti->query("SELECT * FROM uretim_tavlama_2_detay WHERE tavlama_2_id = $t2_id")->fetch_assoc();

                // Tavlama 3
                $t3 = @$baglanti->query("SELECT * FROM uretim_tavlama_3 WHERE tavlama_2_id = $t2_id")->fetch_assoc();
                if ($t3) {
                    $hikaye["tavlama3"] = $t3;
                    $t3_id = $t3['id'];
                    $hikaye["tavlama3_detay"] = @$baglanti->query("SELECT * FROM uretim_tavlama_3_detay WHERE tavlama_3_id = $t3_id")->fetch_assoc();

                    // B1
                    $ub1 = @$baglanti->query("SELECT * FROM uretim_b1 WHERE tavlama_3_id = $t3_id")->fetch_assoc();
                    if ($ub1) {
                        $hikaye["ub1"] = $ub1;
                        $ub1_id = $ub1['id'];
                        $hikaye["ub1_detay"] = @$baglanti->query("SELECT * FROM uretim_b1_detay WHERE b1_id = $ub1_id")->fetch_assoc();

                        // Un 1
                        $un1 = @$baglanti->query("SELECT * FROM uretim_un1 WHERE b1_id = $ub1_id")->fetch_assoc();
                        if ($un1) {
                            $hikaye["uun1"] = $un1;
                            $un1_id = $un1['id'];
                            $hikaye["uun1_detay"] = @$baglanti->query("SELECT * FROM uretim_un1_detay WHERE un1_id = $un1_id")->fetch_assoc();
                        }
                    }
                }
            }
        }
    }

    if (!$found) {
        $hata = "❌ Bu parti numarasına ait kayıt bulunamadı!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İzlenebilirlik - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .timeline {
            border-left: 4px solid #1c2331;
            margin-left: 20px;
            padding-left: 30px;
            position: relative;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 40px;
        }

        .timeline-icon {
            position: absolute;
            left: -54px;
            top: 0;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f39c12;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .lab-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 10px;
            background: #e9ecef;
            margin-right: 5px;
        }
    </style>
</head>

<body class="bg-light">

    <?php include("navbar.php"); ?>

    <div class="container py-4">

        <div class="card p-4 shadow-sm border-0 mb-5"
            style="background: linear-gradient(135deg, #1c2331 0%, #2c3e50 100%);">
            <h3 class="text-white mb-3"><i class="fas fa-search-location text-warning"></i> Ürün Kimlik Sorgulama</h3>
            <form method="get" class="d-flex gap-2">
                <input type="text" name="parti_no" class="form-control form-control-lg"
                    placeholder="Barkod / Parti No Giriniz (Örn: PRT-260120-1430)" value="<?php echo $aranan_parti; ?>"
                    required>
                <button type="submit" name="sorgula" class="btn btn-warning btn-lg fw-bold px-5">SORGULA</button>
            </form>
        </div>



        <?php if ($hikaye) { ?>
            <div class="row">
                <div class="col-lg-8">
                    <h4 class="mb-4">Ürün Yolculuğu: <span class="text-primary"><?php echo $aranan_parti; ?></span></h4>

                    <div class="timeline">

                        <!-- 1. SEVKİYAT (En üst) -->
                        <?php if (isset($hikaye["sevkiyat"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #8e44ad;"><i class="fas fa-truck-fast fa-lg"></i>
                                </div>
                                <div class="card border-0 shadow-sm border-start border-4"
                                    style="border-color: #8e44ad !important;">
                                    <div class="card-body">
                                        <h5 class="fw-bold" style="color: #8e44ad;">🚚 Sevkiyat &amp; Teslimat</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["sevkiyat"]["randevu_tarihi"])); ?>
                                        </p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Müşteri:</strong>
                                                <?php echo $hikaye["sevkiyat"]["musteri_adi"]; ?></div>
                                            <div class="col-6"><strong>Plaka:</strong> <span
                                                    class="badge bg-dark"><?php echo $hikaye["sevkiyat"]["arac_plaka"]; ?></span>
                                            </div>
                                            <div class="col-6 mt-2"><strong>Şoför:</strong>
                                                <?php echo $hikaye["sevkiyat"]["sofor_adi"] ?? '-'; ?></div>
                                            <div class="col-6 mt-2"><strong>Miktar:</strong>
                                                <?php echo $hikaye["sevkiyat"]["miktar"] ?? $hikaye["sevkiyat"]["miktar_ton"]; ?>
                                                Ton</div>
                                            <div class="col-12 mt-2"><strong>Durum:</strong>
                                                <?php
                                                $d = strtoupper($hikaye["sevkiyat"]["durum"]);
                                                $dr = 'bg-secondary';
                                                if ($d == 'BEKLIYOR')
                                                    $dr = 'bg-warning text-dark';
                                                if ($d == 'YUKLENIYOR')
                                                    $dr = 'bg-info text-dark';
                                                if ($d == 'TAMAMLANDI')
                                                    $dr = 'bg-success';
                                                ?>
                                                <span class="badge <?php echo $dr; ?>"><?php echo $d; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 2. DEPO STOK -->
                        <?php if (isset($hikaye["depo"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #2980b9;"><i class="fas fa-warehouse fa-lg"></i>
                                </div>
                                <div class="card border-0 shadow-sm border-start border-primary border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-primary">🏭 Depo &amp; Stok</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["depo"]["son_guncelleme"])); ?></p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Depo:</strong> <?php echo $hikaye["depo"]["depo_adi"]; ?>
                                            </div>
                                            <div class="col-6"><strong>Kod:</strong> <span
                                                    class="badge bg-secondary"><?php echo $hikaye["depo"]["depo_kodu"]; ?></span>
                                            </div>
                                            <div class="col-6 mt-2"><strong>Ürün:</strong>
                                                <?php echo $hikaye["depo"]["urun_adi"]; ?></div>
                                            <div class="col-6 mt-2"><strong>Stok:</strong>
                                                <?php echo $hikaye["depo"]["miktar"]; ?> adet</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 3. PAKETLEME (Son Ürün) -->
                        <div class="timeline-item">
                            <div class="timeline-icon bg-success"><i class="fas fa-box-open fa-lg"></i></div>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="fw-bold">📦 Son Ürün &amp; Paketleme</h5>
                                    <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                        <?php echo date("d.m.Y H:i", strtotime($hikaye["paket"]["tarih"])); ?></p>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6"><strong>Ürün:</strong>
                                            <?php echo $hikaye["paket"]["urun_adi"]; ?></div>
                                        <div class="col-6"><strong>Adet:</strong> <?php echo $hikaye["paket"]["miktar"]; ?>
                                            Çuval</div>
                                        <div class="col-12 mt-2"><strong>Paketleyen:</strong>
                                            <?php echo $hikaye["paket"]["personel"]; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. ÜRETİM -->
                        <?php if ($hikaye["uretim"]) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-warning"><i class="fas fa-cogs fa-lg"></i></div>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="fw-bold">⚙️ Öğütme &amp; Üretim Hattı</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["uretim"]["tarih"])); ?></p>
                                        <hr>
                                        <p class="mb-1"><strong>İş Kodu:</strong>
                                            <?php echo $hikaye["uretim"]["is_kodu"] ?? 'Belirtilmemiş'; ?></p>
                                        <p class="mb-1"><strong>Parti No:</strong> <?php echo $hikaye["uretim"]["parti_no"]; ?>
                                        </p>
                                        <p class="mb-0"><strong>Üretilen:</strong>
                                            <?php echo $hikaye["uretim"]["uretilen_miktar_kg"]; ?> kg</p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 4.1. UN 1 LABORATUVAR (Yeni Modül) -->
                        <?php if (isset($hikaye["uun1"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #27ae60;"><i class="fas fa-microscope fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-success border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-success">🧪 Un 1 Laboratuvar Analizi</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["uun1"]["numune_saati"])); ?></p>
                                        <hr>
                                        <?php if ($hikaye["uun1_detay"]) { 
                                            $ud = $hikaye["uun1_detay"]; ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="lab-badge bg-success text-white">Protein: <b>%<?php echo $ud["perten_protein"]; ?></b></span>
                                                <span class="lab-badge">Nem: <b>%<?php echo $ud["perten_nem"]; ?></b></span>
                                                <span class="lab-badge">Kül: <b><?php echo $ud["perten_kul"]; ?></b></span>
                                                <span class="lab-badge">Gluten: <b>%<?php echo $ud["gluten"]; ?></b></span>
                                                <span class="lab-badge bg-warning text-dark">Index: <b><?php echo $ud["g_index"]; ?></b></span>
                                                <span class="lab-badge">W (Alveo): <b><?php echo $ud["alveo_w"]; ?></b></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 4.2. B1 DEĞİRMEN (Yeni Modül) -->
                        <?php if (isset($hikaye["ub1"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #34495e;"><i class="fas fa-industry fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-dark border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-dark">⚙️ B1 Değirmen İşlemi</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["ub1"]["baslama_tarihi"])); ?></p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Tonaj:</strong> <?php echo $hikaye["ub1"]["b1_tonaj"]; ?> Ton</div>
                                            <div class="col-6"><strong>Su Derecesi:</strong> <?php echo $hikaye["ub1"]["su_derecesi"]; ?>°C</div>
                                        </div>
                                        <?php if ($hikaye["ub1_detay"]) { 
                                            $bd = $hikaye["ub1_detay"]; ?>
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                <span class="lab-badge">Hektolitre: <b><?php echo $bd["hektolitre"]; ?></b></span>
                                                <span class="lab-badge">Protein: <b>%<?php echo $bd["perten_protein"]; ?></b></span>
                                                <span class="lab-badge">Nem: <b>%<?php echo $bd["nem"]; ?></b></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 4.3. TAVLAMA AŞAMALARI -->
                        <?php for ($i = 3; $i >= 1; $i--) { 
                            $t_key = "tavlama".$i;
                            $td_key = "tavlama".$i."_detay";
                            if (isset($hikaye[$t_key])) { 
                                $t_data = $hikaye[$t_key];
                                $td_data = $hikaye[$td_key];
                                $t_color = ($i == 3) ? '#e67e22' : (($i == 2) ? '#f39c12' : '#f1c40f');
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon" style="background: <?php echo $t_color; ?>;"><i class="fas fa-droplet fa-lg"></i></div>
                                    <div class="card border-0 shadow-sm border-start border-4" style="border-color: <?php echo $t_color; ?> !important;">
                                        <div class="card-body">
                                            <h5 class="fw-bold" style="color: <?php echo $t_color; ?>;">💧 Tavlama <?php echo $i; ?></h5>
                                            <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                                <?php echo date("d.m.Y H:i", strtotime($t_data["baslama_tarihi"])); ?></p>
                                            <hr>
                                            <div class="row">
                                                <div class="col-6"><strong>Top. Tonaj:</strong> <?php echo $t_data["toplam_tonaj"]; ?> Ton</div>
                                                <div class="col-6"><strong>Ortam Derecesi:</strong> <?php echo $t_data["ortam_derecesi"]; ?>°C</div>
                                            </div>
                                            <?php if ($td_data) { ?>
                                                <div class="mt-2 d-flex flex-wrap gap-2">
                                                    <span class="lab-badge">Hedef Nem: <b>%<?php echo $td_data["hedef_nem"]; ?></b></span>
                                                    <span class="lab-badge">Ölçülen Nem: <b>%<?php echo $td_data["nem"]; ?></b></span>
                                                    <span class="lab-badge">Protein: <b>%<?php echo $td_data["perten_protein"]; ?></b></span>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } 
                        } ?>

                        <!-- 4.4. PAÇAL (BUĞDAY KARIŞIMI) -->
                        <?php if (isset($hikaye["uretim_pacal"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #1abc9c;"><i class="fas fa-blender fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-teal border-4" style="border-color: #1abc9c !important;">
                                    <div class="card-body">
                                        <h5 class="fw-bold" style="color: #1abc9c;">🥣 Paçal (Buğday Karışımı)</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y", strtotime($hikaye["uretim_pacal"]["tarih"])); ?></p>
                                        <hr>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Buğday Cinsi</th>
                                                        <th>Yöre</th>
                                                        <th>Miktar (Kg)</th>
                                                        <th>Oran (%)</th>
                                                        <th>Protein</th>
                                                        <th>Gluten</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($hikaye["uretim_pacal_detay"] as $pd) { ?>
                                                        <tr>
                                                            <td><?php echo $pd["bugday_adi"]; ?></td>
                                                            <td class="small"><?php echo $pd["yoresi"]; ?></td>
                                                            <td><?php echo number_format($pd["miktar_kg"], 0, ',', '.'); ?></td>
                                                            <td class="fw-bold text-primary">%<?php echo $pd["oran"]; ?></td>
                                                            <td>%<?php echo $pd["perten_protein"]; ?></td>
                                                            <td>%<?php echo $pd["gluten"]; ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                                <tfoot class="table-light fw-bold">
                                                    <tr>
                                                        <td colspan="2">TOPLAM</td>
                                                        <td><?php echo number_format($hikaye["uretim_pacal"]["toplam_miktar_kg"], 0, ',', '.'); ?></td>
                                                        <td colspan="3">%100</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <?php if (!empty($hikaye["uretim_pacal"]["notlar"])) { ?>
                                            <div class="mt-3 p-2 bg-light rounded small italic">
                                                <strong>Notlar:</strong> <?php echo $hikaye["uretim_pacal"]["notlar"]; ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 5. UN ÇIKIŞ -->
                        <?php if (isset($hikaye["un_cikis"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #27ae60;"><i class="fas fa-wheat-awn fa-lg"></i>
                                </div>
                                <div class="card border-0 shadow-sm border-start border-success border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-success">🌾 UN 1 Çıkış</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["un_cikis"]["cikis_tarihi"])); ?></p>
                                        <hr>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="lab-badge bg-success text-white">Protein:
                                                <b>%<?php echo $hikaye["un_cikis"]["protein"]; ?></b></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 6. B1 DEĞİRMEN -->
                        <?php if (isset($hikaye["b1"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #3498db;"><i class="fas fa-cogs fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-primary border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-primary">⚙️ B1 Değirmen</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["b1"]["uretim_tarihi"])); ?></p>
                                        <hr>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="lab-badge">Hektolitre:
                                                <b><?php echo $hikaye["b1"]["hektolitre"]; ?></b></span>
                                            <span class="lab-badge">Nem: <b>%<?php echo $hikaye["b1"]["nem"]; ?></b></span>
                                            <span class="lab-badge">Protein:
                                                <b>%<?php echo $hikaye["b1"]["protein"]; ?></b></span>
                                            <span class="lab-badge">Sertlik:
                                                <b><?php echo $hikaye["b1"]["sertlik"]; ?></b></span>
                                            <span class="lab-badge bg-info text-white">Su:
                                                <b><?php echo $hikaye["b1"]["verilen_su_litre"]; ?> L</b></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 7. AKTARMA (Tav) -->
                        <?php if (isset($hikaye["aktarma"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #f39c12;"><i
                                        class="fas fa-exchange-alt fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-warning border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-warning">🔄 Aktarma (Tavlama)</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["aktarma"]["aktarma_tarihi"])); ?>
                                        </p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Nem:</strong> %<?php echo $hikaye["aktarma"]["nem"]; ?>
                                            </div>
                                            <div class="col-6"><strong>Verilen Su:</strong>
                                                <?php echo $hikaye["aktarma"]["verilen_su_litre"]; ?> L</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 8. YIKAMA -->
                        <?php if (isset($hikaye["yikama"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #e74c3c;"><i class="fas fa-shower fa-lg"></i>
                                </div>
                                <div class="card border-0 shadow-sm border-start border-danger border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-danger">🚿 Yıkama İşlemi</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["yikama"]["yikama_tarihi"])); ?></p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Ürün:</strong>
                                                <?php echo $hikaye["yikama"]["urun_adi"] ?? '-'; ?></div>
                                            <div class="col-6"><strong>Hektolitre:</strong>
                                                <?php echo $hikaye["yikama"]["hektolitre"]; ?></div>
                                            <div class="col-4 mt-2"><strong>Nem:</strong>
                                                %<?php echo $hikaye["yikama"]["nem"]; ?></div>
                                            <div class="col-4 mt-2"><strong>Protein:</strong>
                                                %<?php echo $hikaye["yikama"]["protein"]; ?></div>
                                            <div class="col-4 mt-2"><strong>Sertlik:</strong>
                                                <?php echo $hikaye["yikama"]["sertlik"]; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 9. LAB KONTROL -->
                        <?php if ($hikaye["lab"]) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-info"><i class="fas fa-flask fa-lg"></i></div>
                                <div class="card border-0 shadow-sm border-start border-info border-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold text-info">🔬 Laboratuvar Kalite Kontrol</h5>
                                        <div class="d-flex flex-wrap mt-3">
                                            <span class="lab-badge">Protein:
                                                <b>%<?php echo $hikaye["lab"]["protein"]; ?></b></span>
                                            <span class="lab-badge">Gluten:
                                                <b>%<?php echo $hikaye["lab"]["gluten"]; ?></b></span>
                                            <span class="lab-badge bg-warning text-dark">Index:
                                                <b><?php echo $hikaye["lab"]["index_degeri"]; ?></b></span>
                                            <span class="lab-badge">Sedim:
                                                <b><?php echo $hikaye["lab"]["sedimantasyon"]; ?></b></span>
                                        </div>
                                        <div class="mt-2 small text-muted">Laborant: <?php echo $hikaye["lab"]["laborant"]; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-secondary"><i class="fas fa-plus"></i></div>
                                <div class="card border-0 shadow-sm bg-light">
                                    <div class="card-body">
                                        <h6 class="text-muted">Bu parti için laboratuvar verisi girilmemiş.</h6>
                                        <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="collapse"
                                            data-bs-target="#labGirisForm">+ Analiz Sonucu Ekle</button>
                                        <div class="collapse mt-3" id="labGirisForm">
                                            <form method="post">
                                                <input type="hidden" name="parti_no" value="<?php echo $aranan_parti; ?>">
                                                <div class="row g-2">
                                                    <div class="col-4"><input type="text" name="protein"
                                                            class="form-control form-control-sm" placeholder="Protein"></div>
                                                    <div class="col-4"><input type="text" name="gluten"
                                                            class="form-control form-control-sm" placeholder="Gluten"></div>
                                                    <div class="col-4"><input type="text" name="index_degeri"
                                                            class="form-control form-control-sm" placeholder="Index"></div>
                                                    <div class="col-4"><input type="text" name="sedim"
                                                            class="form-control form-control-sm" placeholder="Sedim"></div>
                                                    <div class="col-4"><button type="submit" name="analiz_kaydet"
                                                            class="btn btn-sm btn-success w-100">Kaydet</button></div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- 10. HAMMADDE KABUL (En alt) -->
                        <?php if (isset($hikaye["hammadde"])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-dark"><i class="fas fa-truck fa-lg"></i></div>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="fw-bold">🌾 Hammadde Kabul (Başlangıç)</h5>
                                        <p class="text-muted mb-1"><i class="far fa-clock"></i>
                                            <?php echo date("d.m.Y H:i", strtotime($hikaye["hammadde"]["tarih"])); ?></p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Plaka:</strong> <span
                                                    class="badge bg-dark"><?php echo $hikaye["hammadde"]["arac_plaka"]; ?></span>
                                            </div>
                                            <div class="col-6"><strong>Ürün:</strong>
                                                <?php echo $hikaye["hammadde"]["bugday_cinsi"]; ?></div>
                                            <div class="col-12 mt-2"><strong>Giriş Lab:</strong> Hektolitre:
                                                <?php echo $hikaye["hammadde"]["hektolitre"]; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm text-center p-4 sticky-top" style="top: 20px;">
                        <i class="fas fa-qrcode fa-5x mb-3 text-dark"></i>
                        <h4><?php echo $aranan_parti; ?></h4>
                        <p class="text-muted">Parti Kimlik Kartı</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Rapor
                                Yazdır</button>
                            <button class="btn btn-outline-primary"><i class="fas fa-envelope"></i> Müşteriye Mail
                                At</button>
                        </div>
                        <div class="alert alert-warning mt-3 text-start small">
                            <i class="fas fa-info-circle"></i> Bu rapor, ISO 22000 Gıda Güvenliği standartlarına uygun
                            izlenebilirlik verisi içerir.
                        </div>
                    </div>
                </div>
            </div>
        <?php } elseif (isset($_GET["sorgula"])) { ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3"></i>
                <h4>Kayıt Bulunamadı</h4>
                <p>Lütfen parti numarasını kontrol edip tekrar deneyin.</p>
            </div>
        <?php } ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // SweetAlert2 Alerts
            <?php if (!empty($mesaj)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo addslashes(str_replace(["✅ ", "✓ ", "⚠️ "], "", strip_tags($mesaj))); ?>',
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
                    text: '<?php echo addslashes(str_replace(["❌ ", "✖ ", "HATA: ", "⚠️ "], "", strip_tags($hata))); ?>',
                    confirmButtonColor: '#0f172a'
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>
