<?php
$bekleyen_araclar_sorgu = "
    SELECT hg.*, h.hammadde_kodu, h.ad as hammadde_adi, h.yogunluk_kg_m3,
           la.hektolitre as lab_hektolitre, la.protein, la.nem, la.hektolitre
    FROM hammadde_girisleri hg
    INNER JOIN hammadde_kabul_akisi hka ON hka.hammadde_giris_id = hg.id
    LEFT JOIN hammaddeler h ON hg.hammadde_id = h.id
    LEFT JOIN lab_analizleri la ON hg.id = la.hammadde_giris_id
    WHERE hg.analiz_yapildi > 0 
      AND (hg.silo_id IS NULL OR hg.silo_id = 0)
      AND hka.asama = 'tamamlandi' 
      AND hka.onay_durum NOT IN ('reddedildi', 'satinalma_red')
    ORDER BY hg.tarih ASC
";
$bekleyen_araclar = $baglanti->query($bekleyen_araclar_sorgu);
?>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-truck-loading me-2 text-warning"></i>Silo Aktarma Bekleyen Araçlar</h5>
                <span class="badge bg-warning text-dark"><?php echo $bekleyen_araclar->num_rows; ?> Bekleyen Kayıt</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Tarih</th>
                                <th>Plaka / Tedarikçi</th>
                                <th>Hammadde / Parti</th>
                                <th>Miktar</th>
                                <th>Analiz Durumu</th>
                                <th class="text-end pe-4">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bekleyen_araclar && $bekleyen_araclar->num_rows > 0): ?>
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
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($row['hammadde_adi']); ?></div>
                                            <div class="small text-muted"><i class="fas fa-barcode me-1"></i><?php echo htmlspecialchars($row['parti_no']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold fs-6"><?php echo number_format($row['miktar_kg'], 0, ',', '.'); ?> <small class="text-muted fw-normal">KG</small></div>
                                        </td>
                                        <td>
                                            <?php if ($row['analiz_yapildi'] == 2): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Tam Analiz</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-edit me-1"></i>Kısmi Analiz</span>
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
                                        Silo aktarımı bekleyen araç bulunmuyor.
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
                                        <option value="<?php echo $s['id']; ?>" data-bos-m3="<?php echo $bos_m3; ?>" data-izinli="<?php echo htmlspecialchars($s['izin_verilen_hammadde_kodlari']); ?>">
                                            <?php echo htmlspecialchars($s['silo_adi']); ?> (Boş: <?php echo number_format($bos_m3, 1); ?> m³)
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

<script>
    const aktarmaSiloOptionlar = `
        <option value="">Silo Seç...</option>
        <?php 
            $silolar = $baglanti->query("SELECT * FROM silolar WHERE tip='bugday' ORDER BY id ASC");
            while ($s = $silolar->fetch_assoc()):
                $bos_m3 = max(0, $s['kapasite_m3'] - $s['doluluk_m3']);
        ?>
            <option value="<?php echo $s['id']; ?>" data-bos-m3="<?php echo $bos_m3; ?>" data-izinli="<?php echo htmlspecialchars($s['izin_verilen_hammadde_kodlari']); ?>">
                <?php echo addslashes($s['silo_adi']); ?> (Boş: <?php echo number_format($bos_m3, 1); ?> m³)
            </option>
        <?php endwhile; ?>
    `;

    document.addEventListener('DOMContentLoaded', function() {
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
                // Select options fitrele
                const kod = btn.getAttribute('data-kodu');
                const selects = document.querySelectorAll('.dagitim-silo-select');
                selects.forEach(sel => {
                    Array.from(sel.options).forEach(opt => {
                        const izinliJson = opt.getAttribute('data-izinli');
                        opt.disabled = false;
                        if (izinliJson) {
                            try {
                                const arr = JSON.parse(izinliJson);
                                if (Array.isArray(arr) && arr.length > 0 && !arr.includes(kod)) {
                                    opt.disabled = true;
                                }
                            }catch(e){}
                        }
                        if (!opt.disabled && parseFloat(opt.getAttribute('data-bos-m3')) <= 0) {
                            opt.disabled = true;
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
        
        // Disable state for options
        const hhKodu = document.getElementById('aktarma_hammadde_kodu').value;
        const newSelect = row.querySelector('.dagitim-silo-select');
        Array.from(newSelect.options).forEach(opt => {
            const izinliJson = opt.getAttribute('data-izinli');
            opt.disabled = false;
            if (izinliJson) {
                try {
                    const arr = JSON.parse(izinliJson);
                    if (Array.isArray(arr) && arr.length > 0 && !arr.includes(hhKodu)) {
                        opt.disabled = true;
                    }
                }catch(e){}
            }
            if (!opt.disabled && parseFloat(opt.getAttribute('data-bos-m3')) <= 0) {
                opt.disabled = true;
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
