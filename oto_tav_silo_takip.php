<?php
session_start();
if (!isset($_SESSION['oturum'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTO TAV Silo Takip (.71)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .card-shadow { box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 0; }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .mono { font-family: Consolas, Menlo, Monaco, monospace; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1 fw-bold">OTO TAV Silo Takip</h4>
                <div class="text-muted small">Kaynak PLC: <span class="mono">192.168.20.71</span></div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Ekran Güncelleme</div>
                <div id="ekranSaat" class="fw-bold">-</div>
            </div>
        </div>

        <div class="card card-shadow mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Servis Durumu</div>
                        <div id="servisDurum" class="fw-bold">-</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Son PLC Okuma</div>
                        <div id="okumaZamani" class="fw-bold">-</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Stale Durumu</div>
                        <div id="staleDurum" class="fw-bold">-</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Cihaz IP</div>
                        <div id="cihazIp" class="fw-bold mono">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-shadow mb-3">
            <div class="card-header bg-white fw-semibold">Tag Bazlı Eşleşme</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tag</th>
                            <th>Ham Değer (PLC)</th>
                            <th>Mapped Silo ID</th>
                            <th>Mapped Silo Adı</th>
                            <th>Tip</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody id="tagBody">
                        <tr><td colspan="6" class="text-center text-muted py-4">Veri bekleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-shadow">
            <div class="card-header bg-white fw-semibold">Uyarılar</div>
            <div class="card-body">
                <ul id="uyariList" class="mb-0">
                    <li class="text-muted">Henüz uyarı yok.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function formatNow() {
            const now = new Date();
            return now.toLocaleString('tr-TR');
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        }

        function renderTagTable(tagler) {
            const body = document.getElementById('tagBody');
            if (!body) return;
            const keys = Object.keys(tagler || {});
            if (keys.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tag verisi yok.</td></tr>';
                return;
            }

            let html = '';
            keys.forEach(tag => {
                const row = tagler[tag] || {};
                const found = !!row.mapping_bulundu;
                html += `
                    <tr>
                        <td class="fw-semibold mono">${tag}</td>
                        <td>${row.ham_deger ?? '-'}</td>
                        <td>${row.mapped_silo_id ?? '-'}</td>
                        <td>${row.mapped_silo_adi ?? '-'}</td>
                        <td>${row.mapped_tip ?? '-'}</td>
                        <td>${found
                            ? '<span class="badge bg-success">Mapping Var</span>'
                            : '<span class="badge bg-warning text-dark">Mapping Yok</span>'}
                        </td>
                    </tr>
                `;
            });
            body.innerHTML = html;
        }

        function renderWarnings(uyarilar) {
            const ul = document.getElementById('uyariList');
            if (!ul) return;
            if (!uyarilar || uyarilar.length === 0) {
                ul.innerHTML = '<li class="text-success">Uyarı yok.</li>';
                return;
            }
            ul.innerHTML = uyarilar.map(u => `<li>${u}</li>`).join('');
        }

        async function fetchData() {
            setText('ekranSaat', formatNow());
            try {
                const resp = await fetch('ajax/oto_tav_silo_takibi.php?_=' + Date.now(), { cache: 'no-store' });
                const data = await resp.json();

                setText('cihazIp', data.cihaz_ip || '-');
                setText('okumaZamani', data.okuma_zamani || '-');

                const servisEl = document.getElementById('servisDurum');
                if (servisEl) {
                    servisEl.innerHTML = data.basari
                        ? '<span class="status-dot bg-success"></span>Çalışıyor'
                        : '<span class="status-dot bg-danger"></span>Hata';
                }

                const staleEl = document.getElementById('staleDurum');
                if (staleEl) {
                    staleEl.innerHTML = data.stale
                        ? '<span class="badge bg-danger">STALE (eski veri)</span>'
                        : '<span class="badge bg-success">CANLI</span>';
                }

                renderTagTable(data.tagler || {});
                renderWarnings(data.uyarilar || []);
            } catch (err) {
                setText('servisDurum', 'Hata');
                renderWarnings(['Servis yanıtı alınamadı: ' + err.message]);
            }
        }

        fetchData();
        setInterval(fetchData, 5000);
        setInterval(() => setText('ekranSaat', formatNow()), 1000);
    </script>
</body>
</html>

