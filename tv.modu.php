<?php
session_start();
include("baglan.php");

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

// Single-file API mode: tv_modu.php?plc_bridge=1
if (isset($_GET['plc_bridge']) && $_GET['plc_bridge'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $live_file = __DIR__ . DIRECTORY_SEPARATOR . 'php_data' . DIRECTORY_SEPARATOR . 'live_data.json';
    if (!is_file($live_file)) {
        echo json_encode([
            'basari' => false,
            'mesaj' => 'Canli veri dosyasi yok. plc_hub.py calisiyor mu?',
            'dosya' => $live_file,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = @file_get_contents($live_file);
    if ($raw === false || trim($raw) === '') {
        echo json_encode([
            'basari' => false,
            'mesaj' => 'Canli veri dosyasi okunamadi.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['plcs']) || !is_array($json['plcs'])) {
        echo json_encode([
            'basari' => false,
            'mesaj' => 'Canli veri formati gecersiz.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $map = [
        'un1' => ['ad' => 'UN1', 'flow_key' => 'UN1_AKIS', 'kg_key' => 'UN1_KG', 'flow_max' => 30000, 'kg_max' => 100000],
        'un2' => ['ad' => 'UN2', 'flow_key' => 'UN2_AKIS', 'kg_key' => 'UN2_KG', 'flow_max' => 30000, 'kg_max' => 100000],
        'kepek' => ['ad' => 'KEPEK', 'flow_key' => 'KEPEK_AKIS', 'kg_key' => 'KEPEK_KG', 'flow_max' => 30000, 'kg_max' => 100000],
    ];

    $cihazlar = [];
    foreach ($map as $plc_key => $cfg) {
        if (!isset($json['plcs'][$plc_key]) || !is_array($json['plcs'][$plc_key])) {
            continue;
        }

        $plc = $json['plcs'][$plc_key];
        $values = (isset($plc['values']) && is_array($plc['values'])) ? $plc['values'] : [];

        $akis_raw = $values[$cfg['flow_key']] ?? 0;
        $akis = is_numeric($akis_raw) ? (float)$akis_raw : 0.0;
        if ($akis < 0 || $akis > (float)$cfg['flow_max']) {
            $akis = 0.0;
        }
        $kg_raw = $values[$cfg['kg_key']] ?? 0;
        $kg = is_numeric($kg_raw) ? (float)$kg_raw : 0.0;
        if ($kg < 0 || $kg > (float)$cfg['kg_max']) {
            $kg = 0.0;
        }

        $durum_text = (string)($plc['status'] ?? 'error');
        $durum = ($durum_text !== 'error' && $akis > 0.01) ? 2 : 0;

        $cihazlar[] = [
            'ad' => $cfg['ad'],
            'ip' => (string)($plc['ip'] ?? ''),
            'durum' => $durum_text,
            'veriler' => [
                'ANLIK_TONAJ' => $akis,
                'ANLIK_KG' => $kg,
                'AKAR_DURUM' => $durum,
            ],
            'kg_ozet' => (isset($plc['kg_summary']) && is_array($plc['kg_summary'])) ? $plc['kg_summary'] : [],
        ];
    }

    echo json_encode([
        'basari' => true,
        'zaman' => $json['generated_at'] ?? date('c'),
        'cihazlar' => $cihazlar,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$bugun = date("Y-m-d");
$uretim = $baglanti->query("SELECT SUM(uretilen_miktar_kg) AS top FROM uretim_hareketleri WHERE DATE(tarih)='$bugun'")->fetch_assoc();
$tonaj  = (($uretim['top'] ?? 0) > 0) ? number_format($uretim['top'] / 1000, 1) : 0;

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

$tip_gruplar = [
    'bugday' => 'Bugday',
    'un' => 'Un',
    'tav' => 'Tav',
    'kepek' => 'Kepek',
];

$kantarlar = [
    ['kod' => 'UN1', 'ip' => '192.168.20.103'],
    ['kod' => 'UN2', 'ip' => '192.168.20.104'],
    ['kod' => 'KEPEK', 'ip' => '192.168.20.105'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Canli Fabrika Izleme - Ozbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0a0e1a;
            --card: #111827;
            --border: rgba(255,255,255,0.08);
            --green: #22c55e;
            --yellow: #f5a623;
            --red: #ef4444;
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
        .header-brand { font-size: 1.1rem; font-weight: 700; letter-spacing: 2px; color: var(--yellow); }
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
            min-height: 84px;
        }
        .stat-icon { font-size: 1.8rem; opacity: 0.7; }
        .stat-label { font-size: 0.7rem; color: var(--dim); text-transform:uppercase; letter-spacing:1px; }
        .stat-value { font-size: 1.4rem; font-weight: 700; }

        .section-title {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--dim);
            font-weight: 600;
            margin: 12px 0 8px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 6px;
        }

        .silo-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:10px; }
        .silo-tile {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            transition: border-color .3s, background .3s;
            position: relative;
            overflow: hidden;
            min-height: 120px;
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
        .silo-name { font-size: .82rem; font-weight: 700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px; }
        .silo-status { font-size: .65rem; margin-bottom:6px; }
        .silo-flow { font-size: 1.08rem; font-weight: 700; color: var(--dim); }
        .silo-flow.active { color: var(--green); }
        .silo-yuzde { font-size: .65rem; color: var(--dim); }

        .kantar-card { min-height: 135px; }
        .kantar-meta { font-size: .66rem; color: var(--dim); }
        .kantar-kg { font-size: .72rem; margin-top: 4px; color: #94a3b8; }
        .kantar-top-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; margin-bottom:12px; }
        .kantar-top {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            min-height: 94px;
            transition: border-color .2s, background .2s;
        }
        .kantar-top.active {
            border-color: var(--green);
            background: rgba(34,197,94,0.06);
        }
        .kantar-top-head {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:8px;
        }
        .kantar-top-title { font-size:.88rem; font-weight:700; }
        .kantar-top-status { font-size:.64rem; color:var(--dim); }
        .kantar-top-row { display:flex; justify-content:space-between; gap:8px; font-size:.72rem; color:#cbd5e1; }
        .kantar-top-row strong { color:#f8fafc; font-size:.82rem; }

        footer {
            padding:6px 24px;
            border-top:1px solid var(--border);
            display:flex;
            justify-content:space-between;
            align-items:center;
            font-size:.7rem;
            color:var(--dim);
            flex-shrink:0;
        }

        @media(max-width:768px) {
            body { overflow: auto; }
            .stat-row { flex-direction: column; }
            .silo-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<header>
    <div class="header-brand"><i class="fas fa-industry me-2"></i>OZBAL UN - CANLI URETIM</div>
    <div class="header-meta">
        <span><span class="live-dot"></span>SISTEM AKTIF</span>
        <span id="tv_saat"><?php echo date("d.m.Y  H:i:s"); ?></span>
        <span id="tv_guncelleme" style="color:#4ade80;">●</span>
        <a href="panel.php" style="color:var(--dim); text-decoration:none;"><i class="fas fa-times-circle"></i> Kapat</a>
    </div>
</header>

<div class="main-grid">

    <div class="stat-row">
        <div class="stat-box">
            <div class="stat-icon text-success"><i class="fas fa-industry"></i></div>
            <div>
                <div class="stat-label">Bugunku Uretim</div>
                <div class="stat-value text-success"><?php echo $tonaj; ?> <span style="font-size:.9rem;font-weight:400;">ton</span></div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon text-warning"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="stat-label">Aktif Silo</div>
                <div class="stat-value text-warning" id="tv_aktif_silo_sayisi">-</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon text-info"><i class="fas fa-tachometer-alt"></i></div>
            <div>
                <div class="stat-label">Toplam Anlik Akis</div>
                <div class="stat-value text-info" id="tv_toplam_akis">- <span style="font-size:.9rem;font-weight:400;">kg/h</span></div>
            </div>
        </div>
        <div class="stat-box" style="max-width:220px;">
            <div class="stat-icon" style="color:#4ade80;"><i class="fas fa-network-wired"></i></div>
            <div>
                <div class="stat-label">PLC Online</div>
                <div class="stat-value" id="tv_plc_online" style="font-size:1rem;color:#4ade80;">-</div>
            </div>
        </div>
        <div class="stat-box" style="max-width:200px;">
            <div class="stat-icon" style="color:var(--dim);"><i class="fas fa-clock"></i></div>
            <div>
                <div class="stat-label">Son Guncelleme</div>
                <div class="stat-value" id="tv_son_guncelleme" style="font-size:1rem;">-</div>
            </div>
        </div>
    </div>

    <div class="section-title"><i class="fas fa-gauge-high me-2"></i>Kantar Canli Ozet (Saniyelik)</div>
    <div class="kantar-top-grid">
        <?php foreach ($kantarlar as $k): ?>
        <div class="kantar-top tv-kantar-top" data-plc-name="<?php echo htmlspecialchars($k['kod'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="kantar-top-head">
                <div class="kantar-top-title"><?php echo htmlspecialchars($k['kod'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="kantar-top-status tv-top-status">Bekleniyor</div>
            </div>
            <div class="kantar-top-row">
                <span>Anlik Akis</span>
                <strong class="tv-top-akis">- kg/h</strong>
            </div>
            <div class="kantar-top-row">
                <span>Anlik KG</span>
                <strong class="tv-top-kg">- kg</strong>
            </div>
            <div class="kantar-top-row">
                <span>Saatlik</span>
                <strong class="tv-top-saatlik">- kg</strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title"><i class="fas fa-scale-balanced me-2"></i>Kantarlar (Sabit Gosterim)</div>
    <div class="silo-grid mb-2">
        <?php foreach ($kantarlar as $k): ?>
        <div class="silo-tile kantar-card tv-kantar-card" data-plc-name="<?php echo htmlspecialchars($k['kod'], ENT_QUOTES, 'UTF-8'); ?>" data-plc-ip="<?php echo htmlspecialchars($k['ip'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="silo-name"><?php echo htmlspecialchars($k['kod'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="kantar-meta"><?php echo htmlspecialchars($k['ip'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="silo-status tv-kantar-status" style="margin-top:6px;color:var(--dim);">Veri bekleniyor</div>
            <div class="silo-flow tv-kantar-flow">-</div>
            <div class="kantar-kg tv-kantar-kg">Bugun: -</div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    foreach ($tip_gruplar as $tip => $tip_label) {
        $grup_silolar = array_filter($silolar, fn($s) => $s['tip'] === $tip);
        if (empty($grup_silolar)) continue;
    ?>
    <div class="section-title"><i class="fas fa-warehouse me-2"></i><?php echo $tip_label; ?> Silolari</div>
    <div class="silo-grid mb-2">
        <?php foreach ($grup_silolar as $s):
            $plc_ip = htmlspecialchars($s['plc_ip_adresi'] ?? '', ENT_QUOTES, 'UTF-8');
            $has_plc = !empty($s['plc_ip_adresi']);
            $yuzde = ($s['kapasite_m3'] > 0) ? ($s['doluluk_m3'] / $s['kapasite_m3']) * 100 : 0;

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
                    <span style="color:var(--green);"><span class="live-dot" style="width:6px;height:6px;"></span> Akiyor</span>
                <?php else: ?>
                    <span style="color:var(--dim);">Beklemede</span>
                <?php endif; ?>
            </div>
            <div class="silo-flow <?php echo $is_active ? 'active' : ''; ?> tv-akis">
                <?php echo $has_plc ? ($is_active ? number_format($init_akis, 0, ',', '.') . ' <small style="font-size:.55rem;">kg/h</small>' : '-') : '-'; ?>
            </div>
            <div class="silo-yuzde">%<?php echo number_format($yuzde, 0); ?> dolu</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php } ?>

</div>

<footer>
    <span><i class="fas fa-shield-halved me-1"></i> Sadece Yetkili Goruuntuler</span>
    <span>OZBAL UN FABRIKASI - CANLI MONITOR</span>
    <span id="tv_footer_saat"><?php echo date("H:i:s"); ?></span>
</footer>

<script>
    function formatKg(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) return '-';
        return Math.round(n).toLocaleString('tr-TR');
    }

    function setKantarCard(plcName, plcData) {
        const card = document.querySelector('.tv-kantar-card[data-plc-name="' + plcName + '"]');
        if (!card) return;

        const statusEl = card.querySelector('.tv-kantar-status');
        const flowEl = card.querySelector('.tv-kantar-flow');
        const kgEl = card.querySelector('.tv-kantar-kg');

        if (!plcData) {
            card.classList.remove('active');
            if (statusEl) { statusEl.textContent = 'Veri yok'; statusEl.style.color = 'var(--dim)'; }
            if (flowEl) { flowEl.className = 'silo-flow tv-kantar-flow'; flowEl.innerHTML = '-'; }
            if (kgEl) { kgEl.textContent = 'Bugun: -'; }
            return;
        }

        const veriler = plcData.veriler || {};
        const akis = Number(veriler.ANLIK_TONAJ || 0);
        const anlikKg = Number(veriler.ANLIK_KG || 0);
        const durumKod = Number(veriler.AKAR_DURUM || 0);
        const durumText = String(plcData.durum || 'error');
        const aktif = (durumKod === 2 && akis > 0.01 && durumText !== 'error');

        if (durumText === 'error') {
            card.classList.remove('active');
            if (statusEl) { statusEl.textContent = 'Baglanti hatasi'; statusEl.style.color = 'var(--red)'; }
            if (flowEl) { flowEl.className = 'silo-flow tv-kantar-flow'; flowEl.innerHTML = '-'; }
        } else if (aktif) {
            card.classList.add('active');
            if (statusEl) { statusEl.textContent = 'Akiyor'; statusEl.style.color = 'var(--green)'; }
            if (flowEl) { flowEl.className = 'silo-flow active tv-kantar-flow'; flowEl.innerHTML = formatKg(akis) + ' <small style="font-size:.55rem;">kg/h</small>'; }
        } else {
            card.classList.remove('active');
            if (statusEl) { statusEl.textContent = 'Beklemede'; statusEl.style.color = 'var(--dim)'; }
            if (flowEl) { flowEl.className = 'silo-flow tv-kantar-flow'; flowEl.innerHTML = '-'; }
        }

        const kgOzet = plcData.kg_ozet || {};
        if (kgEl) {
            const birakim = (kgOzet.last_drop_residual_kg === null || kgOzet.last_drop_residual_kg === undefined)
                ? '-'
                : formatKg(kgOzet.last_drop_residual_kg);
            const parti = Number(kgOzet.cycle_count_today || 0);
            kgEl.textContent = 'Anlik: ' + formatKg(anlikKg) + ' kg | Saatlik: ' + formatKg(kgOzet.this_hour) + ' kg | Bugun: ' + formatKg(kgOzet.today) + ' kg | Birakim: ' + birakim + ' kg | Parti: ' + parti;
        }
    }

    function setTopKantarSummary(plcName, plcData) {
        const card = document.querySelector('.tv-kantar-top[data-plc-name="' + plcName + '"]');
        if (!card) return;

        const statusEl = card.querySelector('.tv-top-status');
        const akisEl = card.querySelector('.tv-top-akis');
        const kgEl = card.querySelector('.tv-top-kg');
        const saatlikEl = card.querySelector('.tv-top-saatlik');

        if (!plcData) {
            card.classList.remove('active');
            if (statusEl) { statusEl.textContent = 'Veri yok'; statusEl.style.color = 'var(--dim)'; }
            if (akisEl) akisEl.textContent = '- kg/h';
            if (kgEl) kgEl.textContent = '- kg';
            if (saatlikEl) saatlikEl.textContent = '- kg';
            return;
        }

        const veriler = plcData.veriler || {};
        const kgOzet = plcData.kg_ozet || {};
        const akis = Number(veriler.ANLIK_TONAJ || 0);
        const anlikKg = Number(veriler.ANLIK_KG || 0);
        const saatlik = Number(kgOzet.this_hour || 0);
        const durumText = String(plcData.durum || 'error');
        const durumKod = Number(veriler.AKAR_DURUM || 0);
        const aktif = (durumText !== 'error' && durumKod === 2 && akis > 0.01);

        card.classList.toggle('active', aktif);

        if (durumText === 'error') {
            if (statusEl) { statusEl.textContent = 'Baglanti'; statusEl.style.color = 'var(--red)'; }
        } else if (aktif) {
            if (statusEl) { statusEl.textContent = 'Akiyor'; statusEl.style.color = 'var(--green)'; }
        } else {
            if (statusEl) { statusEl.textContent = 'Beklemede'; statusEl.style.color = 'var(--dim)'; }
        }

        if (akisEl) akisEl.textContent = formatKg(akis) + ' kg/h';
        if (kgEl) kgEl.textContent = formatKg(anlikKg) + ' kg';
        if (saatlikEl) saatlikEl.textContent = formatKg(saatlik) + ' kg';
    }

    setInterval(() => {
        const now = new Date();
        const s = now.toLocaleTimeString('tr-TR');
        const ft = document.getElementById('tv_footer_saat');
        if (ft) ft.innerText = s;
    }, 1000);

    function tvPlcGuncelle() {
        fetch('<?php echo basename($_SERVER["PHP_SELF"]); ?>?plc_bridge=1', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.basari || !Array.isArray(data.cihazlar)) return;

                const plcMapByIp = {};
                const plcMapByName = {};
                data.cihazlar.forEach(c => {
                    if (c && c.ip) plcMapByIp[c.ip] = c;
                    if (c && c.ad) plcMapByName[String(c.ad).toUpperCase()] = c;
                });

                const toplamPlc = data.cihazlar.length || 0;
                const onlinePlc = data.cihazlar.filter(c => (c && c.durum) !== 'error').length;

                setKantarCard('UN1', plcMapByName['UN1'] || plcMapByIp['192.168.20.103']);
                setKantarCard('UN2', plcMapByName['UN2'] || plcMapByIp['192.168.20.104']);
                setKantarCard('KEPEK', plcMapByName['KEPEK'] || plcMapByIp['192.168.20.105']);

                setTopKantarSummary('UN1', plcMapByName['UN1'] || plcMapByIp['192.168.20.103']);
                setTopKantarSummary('UN2', plcMapByName['UN2'] || plcMapByIp['192.168.20.104']);
                setTopKantarSummary('KEPEK', plcMapByName['KEPEK'] || plcMapByIp['192.168.20.105']);

                let aktifSayisi = 0;
                let toplamAkis = 0;

                document.querySelectorAll('.tv-silo-card').forEach(card => {
                    const ip = card.getAttribute('data-plc-ip');
                    const durumEl = card.querySelector('.tv-durum');
                    const akisEl = card.querySelector('.tv-akis');

                    if (!ip || !plcMapByIp[ip]) {
                        return;
                    }

                    const v = (plcMapByIp[ip].veriler || {});
                    const akis = Number(v.ANLIK_TONAJ || 0);
                    const durum = Number(v.AKAR_DURUM || 0);
                    const aktif = (durum === 2 && akis > 0.01);

                    if (aktif) {
                        aktifSayisi++;
                        toplamAkis += akis;
                        card.classList.add('active');
                        if (durumEl) durumEl.innerHTML = '<span style="color:var(--green);"><span class="live-dot" style="width:6px;height:6px;display:inline-block;margin-right:4px;"></span>Akiyor</span>';
                        if (akisEl) {
                            akisEl.className = 'silo-flow active tv-akis';
                            akisEl.innerHTML = formatKg(akis) + ' <small style="font-size:.55rem;">kg/h</small>';
                        }
                    } else {
                        card.classList.remove('active');
                        if (durumEl) durumEl.innerHTML = '<span style="color:var(--dim);">Beklemede</span>';
                        if (akisEl) {
                            akisEl.className = 'silo-flow tv-akis';
                            akisEl.innerHTML = '-';
                        }
                    }
                });

                const el1 = document.getElementById('tv_aktif_silo_sayisi');
                if (el1) el1.innerText = aktifSayisi;

                const el2 = document.getElementById('tv_toplam_akis');
                if (el2) {
                    el2.innerHTML = formatKg(toplamAkis) + ' <span style="font-size:.9rem;font-weight:400;">kg/h</span>';
                }

                const el3 = document.getElementById('tv_son_guncelleme');
                if (el3) el3.innerText = new Date().toLocaleTimeString('tr-TR');

                const el4 = document.getElementById('tv_plc_online');
                if (el4) {
                    el4.innerText = onlinePlc + '/' + toplamPlc;
                    el4.style.color = (toplamPlc > 0 && onlinePlc === toplamPlc) ? '#4ade80' : '#f5a623';
                }

                const ind = document.getElementById('tv_guncelleme');
                if (ind) {
                    ind.style.color = '#4ade80';
                    setTimeout(() => { if (ind) ind.style.color = 'transparent'; }, 350);
                }
            })
            .catch(() => {
                const el4 = document.getElementById('tv_plc_online');
                if (el4) {
                    el4.innerText = '0/3';
                    el4.style.color = '#ef4444';
                }
                setTopKantarSummary('UN1', null);
                setTopKantarSummary('UN2', null);
                setTopKantarSummary('KEPEK', null);
            });
    }

    setTimeout(tvPlcGuncelle, 300);
    setInterval(tvPlcGuncelle, 1000);
</script>
</body>
</html>
