<?php
session_start();
include("baglan.php");

// Patron-only erişim
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}
$rol_adi = $_SESSION["rol_adi"] ?? '';
$rol_id  = (int)($_SESSION["rol_id"] ?? 0);
$is_patron = ($rol_adi === 'Patron' || $rol_id === 1);
if (!$is_patron) {
    header("Location: panel.php");
    exit;
}

// Verileri çek
$bugun = date("Y-m-d");
$uretim = $baglanti->query("SELECT SUM(uretilen_miktar_kg) AS top FROM uretim_hareketleri WHERE DATE(tarih)='$bugun'")->fetch_assoc();
$tonaj  = (($uretim['top'] ?? 0) > 0) ? number_format($uretim['top'] / 1000, 1) : 0;

// Tüm silolar + PLC veri
$silolar = [];
$sr = $baglanti->query("
    SELECT s.id, s.silo_adi, s.tip, s.kapasite_m3, s.doluluk_m3, s.plc_ip_adresi,
           (SELECT po.veriler FROM plc_okumalari po
            JOIN plc_cihazlari pc ON pc.id = po.cihaz_id
            WHERE pc.ip_adresi = s.plc_ip_adresi AND s.plc_ip_adresi IS NOT NULL
            ORDER BY po.id DESC LIMIT 1) AS plc_verisi
    FROM silolar s
    ORDER BY s.tip, s.silo_adi
");
if ($sr) {
    while ($s = $sr->fetch_assoc()) {
        $silolar[] = $s;
    }
}
$tip_gruplar = ['bugday' => 'Buğday', 'un' => 'Un', 'tav' => 'Tav', 'kepek' => 'Kepek'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Canlı Fabrika İzleme — Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0a0e1a;
            --card: #111827;
            --border: rgba(255,255,255,0.08);
            --green: #22c55e;
            --yellow: #f5a623;
            --dim: #6b7280;
        }
        * { box-sizing: border-box; margin:0; padding:0; }
        body {
            background: var(--bg);
            color: #e2e8f0;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        header {
            padding: 10px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.02);
            flex-shrink: 0;
        }
        .header-brand { font-size: 1.1rem; font-weight: 700; letter-spacing: 3px; color: var(--yellow); }
        .header-meta { font-size: 0.8rem; color: var(--dim); display: flex; gap:20px; align-items:center; }
        .live-dot { width:9px; height:9px; background:var(--green); border-radius:50%; animation:pulse 2s infinite; display:inline-block; margin-right:6px; }
        @keyframes pulse { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(34,197,94,.5)} 50%{opacity:.7;box-shadow:0 0 0 6px rgba(34,197,94,0)} }
        .main-grid { flex:1; overflow-y:auto; padding:16px 24px; }
        .stat-row { display:flex; gap:16px; margin-bottom:16px; }
        .stat-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 20px;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon { font-size: 1.8rem; opacity: 0.7; }
        .stat-label { font-size: 0.7rem; color: var(--dim); text-transform:uppercase; letter-spacing:1px; }
        .stat-value { font-size: 1.6rem; font-weight: 700; }
        .section-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--dim);
            font-weight: 600;
            margin: 12px 0 8px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 6px;
        }
        .silo-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:10px; }
        .silo-tile {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            transition: border-color .3s, background .3s;
            position: relative;
            overflow: hidden;
        }
        .silo-tile.active {
            border-color: var(--green);
            background: rgba(34,197,94,0.06);
        }
        .silo-tile::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--border);
        }
        .silo-tile.active::after { background: var(--green); }
        .silo-name { font-size: .8rem; font-weight: 700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px; }
        .silo-status { font-size: .65rem; margin-bottom:6px; }
        .silo-flow { font-size: 1.1rem; font-weight: 700; color: var(--dim); }
        .silo-flow.active { color: var(--green); }
        .silo-yuzde { font-size: .65rem; color: var(--dim); }
        .badge-tip { font-size:.6rem; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,.1); color:#94a3b8; }
        footer { padding:6px 24px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; font-size:.7rem; color:var(--dim); flex-shrink:0; }

        @media(max-width:768px) {
            body { overflow: auto; }
            .stat-row { flex-direction: column; }
            .silo-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<header>
    <div class="header-brand"><i class="fas fa-industry me-2"></i>ÖZBAL UN — CANLI ÜRETİM</div>
    <div class="header-meta">
        <span><span class="live-dot"></span>SİSTEM AKTİF</span>
        <span id="tv_saat"><?php echo date("d.m.Y  H:i:s"); ?></span>
        <span id="tv_guncelleme" style="color:#4ade80;">●</span>
        <a href="panel.php" style="color:var(--dim); text-decoration:none;"><i class="fas fa-times-circle"></i> Kapat</a>
    </div>
</header>

<div class="main-grid">

    <!-- Özet Stat Kutuları -->
    <div class="stat-row">
        <div class="stat-box">
            <div class="stat-icon text-success"><i class="fas fa-industry"></i></div>
            <div>
                <div class="stat-label">Bugünkü Üretim</div>
                <div class="stat-value text-success"><?php echo $tonaj; ?> <span style="font-size:.9rem;font-weight:400;">ton</span></div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon text-warning"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="stat-label">Aktif Silo</div>
                <div class="stat-value text-warning" id="tv_aktif_silo_sayisi">—</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon text-info"><i class="fas fa-tachometer-alt"></i></div>
            <div>
                <div class="stat-label">Toplam Anlık Akış</div>
                <div class="stat-value text-info" id="tv_toplam_akis">— <span style="font-size:.9rem;font-weight:400;">kg/h</span></div>
            </div>
        </div>
        <div class="stat-box" style="max-width:200px;">
            <div class="stat-icon" style="color:var(--dim);"><i class="fas fa-clock"></i></div>
            <div>
                <div class="stat-label">Son Güncelleme</div>
                <div class="stat-value" id="tv_son_guncelleme" style="font-size:1rem;">—</div>
            </div>
        </div>
    </div>

    <!-- Silo Grupları -->
    <?php
    foreach ($tip_gruplar as $tip => $tip_label) {
        $grup_silolar = array_filter($silolar, fn($s) => $s['tip'] === $tip);
        if (empty($grup_silolar)) continue;
    ?>
    <div class="section-title"><i class="fas fa-warehouse me-2"></i><?php echo $tip_label; ?> Siloları</div>
    <div class="silo-grid mb-2">
        <?php foreach ($grup_silolar as $s) {
            $plc_ip = htmlspecialchars($s['plc_ip_adresi'] ?? '', ENT_QUOTES, 'UTF-8');
            $has_plc = !empty($s['plc_ip_adresi']);
            $yuzde = ($s['kapasite_m3'] > 0) ? ($s['doluluk_m3'] / $s['kapasite_m3']) * 100 : 0;

            // PHP-side initial PLC state
            $init_akis = 0;
            $init_durum = 0;
            if ($has_plc && !empty($s['plc_verisi'])) {
                $pv = json_decode($s['plc_verisi'], true);
                $init_akis = (float)($pv['ANLIK_TONAJ'] ?? 0);
                $init_durum = (int)($pv['AKAR_DURUM'] ?? 0);
            }
            $is_active = ($init_durum === 2 && $init_akis > 0);
        ?>
        <div class="silo-tile <?php echo $is_active ? 'active' : ''; ?> tv-silo-card" <?php echo $has_plc ? "data-plc-ip=\"$plc_ip\"" : ''; ?>>
            <div class="silo-name" title="<?php echo htmlspecialchars($s['silo_adi']); ?>"><?php echo htmlspecialchars($s['silo_adi']); ?></div>
            <div class="silo-status tv-durum">
                <?php if (!$has_plc): ?>
                    <span style="color:var(--dim);">PLC Yok</span>
                <?php elseif ($is_active): ?>
                    <span style="color:var(--green);"><span class="live-dot" style="width:6px;height:6px;"></span> Akıyor</span>
                <?php else: ?>
                    <span style="color:var(--dim);">Beklemede</span>
                <?php endif; ?>
            </div>
            <div class="silo-flow <?php echo $is_active ? 'active' : ''; ?> tv-akis">
                <?php echo $has_plc ? ($is_active ? number_format($init_akis, 0, ',', '.') . ' <small style="font-size:.55rem;">kg/h</small>' : '—') : '—'; ?>
            </div>
            <div class="silo-yuzde">%<?php echo number_format($yuzde, 0); ?> dolu</div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

</div>

<footer>
    <span><i class="fas fa-shield-halved me-1"></i> Yalnızca Yetkili Görüntüleyebilir</span>
    <span>ÖZBAL UN FABRİKASI — CANLI MONİTOR</span>
    <span id="tv_footer_saat"><?php echo date("H:i:s"); ?></span>
</footer>

<script>
    // Saat güncelle
    setInterval(() => {
        const now = new Date();
        const s = now.toLocaleTimeString('tr-TR');
        const ft = document.getElementById('tv_footer_saat');
        if (ft) ft.innerText = s;
    }, 1000);

    // PLC polling
    function tvPlcGuncelle() {
        fetch('ajax/plc_veri.php')
            .then(r => r.json())
            .then(data => {
                if (!data.basari || !data.cihazlar) return;
                const plcMap = {};
                data.cihazlar.forEach(c => { if (c.ip) plcMap[c.ip] = c; });

                let aktifSayisi = 0;
                let toplamAkis = 0;

                document.querySelectorAll('.tv-silo-card').forEach(card => {
                    const ip = card.getAttribute('data-plc-ip');
                    const durumEl = card.querySelector('.tv-durum');
                    const akisEl = card.querySelector('.tv-akis');

                    if (!ip || !plcMap[ip]) return;
                    const v = plcMap[ip].veriler;
                    if (!v) return;

                    const akis = parseFloat(v.ANLIK_TONAJ) || 0;
                    const durum = parseInt(v.AKAR_DURUM) || 0;
                    const aktif = (durum === 2 && akis > 0);

                    if (aktif) {
                        aktifSayisi++;
                        toplamAkis += akis;
                        card.classList.add('active');
                        if (durumEl) durumEl.innerHTML = '<span style="color:var(--green);"><span class="live-dot" style="width:6px;height:6px;display:inline-block;margin-right:4px;"></span>Akıyor</span>';
                        if (akisEl) { akisEl.className = 'silo-flow active tv-akis'; akisEl.innerHTML = Math.round(akis).toLocaleString('tr-TR') + ' <small style="font-size:.55rem;">kg/h</small>'; }
                    } else {
                        card.classList.remove('active');
                        if (durumEl) durumEl.innerHTML = '<span style="color:var(--dim);">Beklemede</span>';
                        if (akisEl) { akisEl.className = 'silo-flow tv-akis'; akisEl.innerHTML = '—'; }
                    }
                });

                const el1 = document.getElementById('tv_aktif_silo_sayisi');
                if (el1) el1.innerText = aktifSayisi;
                const el2 = document.getElementById('tv_toplam_akis');
                if (el2) el2.innerHTML = Math.round(toplamAkis).toLocaleString('tr-TR') + ' <span style="font-size:.9rem;font-weight:400;">kg/h</span>';
                const el3 = document.getElementById('tv_son_guncelleme');
                if (el3) el3.innerText = new Date().toLocaleTimeString('tr-TR');

                // Yanıp sönen yeşil nokta
                const ind = document.getElementById('tv_guncelleme');
                if (ind) { ind.style.color = '#4ade80'; setTimeout(() => { if(ind) ind.style.color = 'transparent'; }, 400); }
            })
            .catch(() => {});
    }

    setTimeout(tvPlcGuncelle, 500);
    setInterval(tvPlcGuncelle, 5000);
</script>
</body>
</html>
