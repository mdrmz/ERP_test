<?php
// Tab 2: Paçal Hazırlama - Silo Bazlı Buğday Blend Formu
// Bu dosya planlama.php'den include edilir.
// Beklenen değişkenler: $baglanti, $mesaj, $hata, $_SESSION

// Silolar (buğday, aktif)
$silo_listesi = [];
$silo_res = $baglanti->query("
    SELECT s.id, s.silo_adi, s.tip, s.kapasite_m3, s.doluluk_m3, s.aktif_hammadde_kodu, s.yogunluk,
           h.ad as hammadde_adi, h.hammadde_kodu
    FROM silolar s
    LEFT JOIN hammaddeler h ON s.aktif_hammadde_kodu = h.hammadde_kodu
    WHERE s.tip='bugday' AND s.durum='aktif'
    ORDER BY s.silo_adi
");
if ($silo_res) {
    while ($row = $silo_res->fetch_assoc()) {
        $silo_listesi[] = $row;
    }
}

// Ürün tipleri
$urun_tipleri = ['Baklavalık', 'Ekmeklik', 'Böreklik', 'Tatlılık', 'Çok Amaçlı', 'Diğer'];

// Son paçal kayıtları
$son_pacallar = $baglanti->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM uretim_pacal_detay WHERE pacal_id = p.id) as satir_sayisi
    FROM uretim_pacal p 
    ORDER BY p.olusturma_tarihi DESC 
    LIMIT 10
");
?>

<!-- PAÇAL HAZIRLAMA FORMU -->
<style>
    .pacal-header .form-select {
        background: rgba(255, 255, 255, .15) !important;
        border-color: rgba(255, 255, 255, .3) !important;
        color: #fff !important;
    }

    .pacal-header .form-select:focus {
        background: rgba(255, 255, 255, .25) !important;
        border-color: rgba(255, 255, 255, .6) !important;
        color: #fff !important;
        box-shadow: 0 0 0 .2rem rgba(255, 255, 255, .15) !important;
    }

    .pacal-header .form-select option {
        background: #fff;
        color: #1f2937;
    }
</style>

<form method="post" id="pacalForm">

    <!-- BAŞLIK -->
    <div class="pacal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 12px; margin-bottom: 15px; color: #fff;">
        <div class="row align-items-end g-2 g-md-3">
            <div class="col-6 col-md-2">
                <label class="form-label" style="color:rgba(255,255,255,.85);font-size:.85rem">
                    <i class="fas fa-calendar-day me-1"></i> Tarih
                </label>
                <input type="date" name="pacal_tarih" class="form-control" value="<?php echo htmlspecialchars($pacal_tarih_degeri ?? date('Y-m-d')); ?>" required
                    style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" style="color:rgba(255,255,255,.85);font-size:.85rem">
                    <i class="fas fa-box me-1"></i> Üretilecek Ürün
                </label>
                <select name="urun_adi" class="form-select" required 
                    style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
                    <option value="">-- Seçin --</option>
                    <?php foreach ($urun_tipleri as $ut): ?>
                        <option value="<?php echo $ut; ?>" <?php echo (($pacal_urun_degeri ?? '') === $ut) ? 'selected' : ''; ?>>
                            <?php echo $ut; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-md-3">
                <label class="form-label" style="color:rgba(255,255,255,.85);font-size:.85rem">
                    <i class="fas fa-barcode me-1"></i> Paçal Parti No
                </label>
                <input type="text" name="pacal_parti_no" id="pacalPartiNo" class="form-control" 
                    value="<?php echo htmlspecialchars($pacal_parti_no_degeri ?? '', ENT_QUOTES); ?>"
                    placeholder="Örn: PCL-20260331-01"
                    pattern="^PCL-\d{8}-\d{2,}$"
                    title="Format: PCL-YYYYMMDD-01"
                    autocomplete="off"
                    required
                    style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" style="color:rgba(255,255,255,.85);font-size:.85rem">
                    <i class="fas fa-sticky-note me-1"></i> Notlar
                </label>
                <input type="text" name="pacal_notlar" class="form-control" placeholder="İsteğe bağlı not..."
                    value="<?php echo htmlspecialchars($pacal_notlar_degeri ?? '', ENT_QUOTES); ?>"
                    style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
            </div>
            <div class="col-4 col-md-2 text-end">
                <button type="submit" name="pacal_kaydet" class="btn btn-light btn-lg fw-bold w-100">
                    <i class="fas fa-save me-1"></i> KAYDET
                </button>
            </div>
        </div>
    </div>

    <!-- PAÇAL TABLOSU -->
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
        <table class="table table-bordered mb-0" id="pacalTablo" style="min-width:1700px;font-size:.78rem">
            <thead>
                <tr>
                    <th rowspan="2" style="background:#1e293b;color:#fff;width:35px;text-align:center">S.No</th>
                    <th rowspan="2" style="background:#1e293b;color:#fff;text-align:center">Silo</th>
                    <th rowspan="2" style="background:#1e293b;color:#fff;text-align:center">Buğday Cinsi</th>
                    <th rowspan="2" style="background:#1e293b;color:#fff;text-align:center">Kod</th>
                    <th rowspan="2" style="background:#1e293b;color:#fff;text-align:center">Miktar (KG)</th>
                    <th rowspan="2" style="background:#1e293b;color:#fff;text-align:center">Paçal Oranı %</th>
                    <th colspan="6" style="background:#2563eb;color:#fff;text-align:center">LAB DEĞERLERİ</th>
                    <th colspan="6" style="background:#7c3aed;color:#fff;text-align:center">ALVEO DEĞERLERİ</th>
                    <th colspan="3" style="background:#059669;color:#fff;text-align:center">PERTEN</th>
                </tr>
                <tr>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">Gluten</th>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">G.Index</th>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">N.Sedim</th>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">G.Sedim</th>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">Hektolitre</th>
                    <th style="background:#3b82f6;color:#fff;text-align:center;font-size:.72rem">Nem</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">P</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">G</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">P/L</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">W</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">IE</th>
                    <th style="background:#8b5cf6;color:#fff;text-align:center;font-size:.72rem">FN</th>
                    <th style="background:#10b981;color:#fff;text-align:center;font-size:.72rem">Protein</th>
                    <th style="background:#10b981;color:#fff;text-align:center;font-size:.72rem">Sertlik</th>
                    <th style="background:#10b981;color:#fff;text-align:center;font-size:.72rem">Nişasta</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 7; $i++): ?>
                <tr data-row="<?php echo $i; ?>">
                    <td style="background:#334155;color:#fff;font-weight:700;text-align:center"><?php echo $i; ?></td>
                    <!-- SİLO SEÇİMİ -->
                    <td>
                        <select name="satirlar[<?php echo $i; ?>][silo_id]" class="form-select silo-select" 
                            data-row="<?php echo $i; ?>" onchange="siloSecildi(this, <?php echo $i; ?>)"
                            style="min-width:160px;font-size:.78rem;padding:3px 5px;height:30px">
                            <option value="">-- Silo Seç --</option>
                            <?php foreach ($silo_listesi as $s):
                                $doluluk = ($s['kapasite_m3'] > 0) ? round(($s['doluluk_m3'] / $s['kapasite_m3']) * 100) : 0;
                                $label = $s['silo_adi'] . ' (' . ($s['hammadde_adi'] ?? 'Boş') . ' - ' . $doluluk . '%)';
                            ?>
                                <option value="<?php echo $s['id']; ?>" 
                                    data-hammadde="<?php echo htmlspecialchars($s['hammadde_adi'] ?? ''); ?>"
                                    data-kod="<?php echo htmlspecialchars($s['hammadde_kodu'] ?? ''); ?>"
                                    data-hid="<?php echo htmlspecialchars($s['aktif_hammadde_kodu'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="satirlar[<?php echo $i; ?>][hammadde_id]" class="hammadde-id-hidden" data-row="<?php echo $i; ?>">
                    </td>
                    <!-- BUĞDAY CİNSİ (readonly) -->
                    <td>
                        <input type="text" class="form-control bugday-cinsi" data-row="<?php echo $i; ?>" readonly
                            style="font-size:.78rem;padding:3px 5px;height:30px;background:#eaf6ff;min-width:100px">
                    </td>
                    <!-- KOD -->
                    <td>
                        <input type="text" name="satirlar[<?php echo $i; ?>][kod]" class="form-control kod-field" 
                            data-row="<?php echo $i; ?>" readonly
                            style="font-size:.78rem;padding:3px 5px;height:30px;background:#eaf6ff;min-width:70px">
                    </td>
                    <!-- MİKTAR KG -->
                    <td>
                        <input type="number" step="0.01" name="satirlar[<?php echo $i; ?>][miktar_kg]"
                            class="form-control miktar-field" data-row="<?php echo $i; ?>" placeholder="0"
                            oninput="hesaplaOrtalama()"
                            style="font-size:.78rem;padding:3px 5px;height:30px;min-width:80px">
                    </td>
                    <!-- PAÇAL ORANI -->
                    <td>
                        <input type="number" step="0.01" min="0" max="100"
                            name="satirlar[<?php echo $i; ?>][oran]" class="form-control oran-field"
                            data-row="<?php echo $i; ?>" placeholder="0.00"
                            oninput="if(this.value>100)this.value=100; hesaplaOrtalama()"
                            style="font-size:.78rem;padding:3px 5px;height:30px;min-width:70px">
                    </td>
                    <!-- LAB -->
                    <?php foreach (['gluten','g_index','n_sedim','g_sedim','hektolitre','nem'] as $col): ?>
                    <td><input type="number" step="0.01" name="satirlar[<?php echo $i; ?>][<?php echo $col; ?>]"
                        class="form-control lab-val" data-col="<?php echo $col; ?>" data-row="<?php echo $i; ?>"
                        oninput="hesaplaOrtalama()"
                        style="font-size:.78rem;padding:3px 5px;height:30px;min-width:60px;background:#eaf6ff"></td>
                    <?php endforeach; ?>
                    <!-- ALVEO -->
                    <?php foreach (['alveo_p','alveo_g','alveo_pl','alveo_w','alveo_ie','fn'] as $col): ?>
                    <td><input type="number" step="0.01" name="satirlar[<?php echo $i; ?>][<?php echo $col; ?>]"
                        class="form-control alveo-val lab-val" data-col="<?php echo $col; ?>" data-row="<?php echo $i; ?>"
                        oninput="hesaplaOrtalama()" placeholder="-"
                        style="font-size:.78rem;padding:3px 5px;height:30px;min-width:55px;background:#faf0ff"></td>
                    <?php endforeach; ?>
                    <!-- PERTEN -->
                    <?php foreach (['perten_protein','perten_sertlik','perten_nisasta'] as $col): ?>
                    <td><input type="number" step="0.01" name="satirlar[<?php echo $i; ?>][<?php echo $col; ?>]"
                        class="form-control lab-val" data-col="<?php echo $col; ?>" data-row="<?php echo $i; ?>"
                        oninput="hesaplaOrtalama()"
                        style="font-size:.78rem;padding:3px 5px;height:30px;min-width:60px;background:#eaf6ff"></td>
                    <?php endforeach; ?>
                </tr>
                <?php endfor; ?>
                <!-- ORTALAMA SATIRI -->
                <tr style="background:#f1f5f9;font-weight:700;border-top:3px solid #1e293b">
                    <td colspan="3" class="text-end">AĞIRLIKLI ORTALAMA</td>
                    <td id="avgToplam">0</td>
                    <td id="avgMiktar">0</td>
                    <td id="avgOranToplam">0.00</td>
                    <?php 
                    $avg_ids = ['avgGluten','avgGIndex','avgNSedim','avgGSedim','avgHektolitre','avgNem',
                                'avgAlveoP','avgAlveoG','avgAlveoPL','avgAlveoW','avgAlveoIE','avgFN',
                                'avgProtein','avgSertlik','avgNisasta'];
                    foreach ($avg_ids as $aid): ?>
                        <td><span style="background:#dcfce7;border-radius:4px;padding:4px 6px" id="<?php echo $aid; ?>">-</span></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
</form>

<!-- SON PAÇAL KAYITLARI -->
<div class="card border-0 shadow-sm mt-4" style="border-radius:12px">
    <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0">
        <span class="fw-bold"><i class="fas fa-history me-2"></i>Son Paçal Kayıtları</span>
        <span class="badge bg-secondary"><?php echo $son_pacallar ? $son_pacallar->num_rows : 0; ?> kayıt</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Tarih</th><th>Parti No</th><th>Ürün</th><th>Miktar (KG)</th><th>Satır</th><th>Durum</th><th>Oluşturan</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                <?php if ($son_pacallar && $son_pacallar->num_rows > 0) {
                    while ($sp = $son_pacallar->fetch_assoc()) {
                        $dc = match ($sp["durum"]) {
                            'hazirlaniyor' => 'bg-warning text-dark',
                            'tavlamada' => 'bg-info',
                            'uretimde' => 'bg-primary',
                            'tamamlandi' => 'bg-success',
                            default => 'bg-secondary'
                        };
                ?>
                <tr>
                    <td><?php echo date("d.m.Y", strtotime($sp["tarih"])); ?></td>
                    <td><span class="badge bg-dark"><?php echo htmlspecialchars($sp["parti_no"]); ?></span></td>
                    <td><?php echo htmlspecialchars($sp["urun_adi"]); ?></td>
                    <td><?php echo number_format($sp["toplam_miktar_kg"], 0, ',', '.'); ?></td>
                    <td><span class="badge bg-light text-dark"><?php echo $sp["satir_sayisi"]; ?> çeşit</span></td>
                    <td><span class="badge <?php echo $dc; ?>"><?php echo ucfirst($sp["durum"]); ?></span></td>
                    <td><?php echo htmlspecialchars($sp["olusturan"] ?? '-'); ?></td>
                    <td>
                        <a href="planlama.php?sil_pacal=<?php echo $sp["id"]; ?>" class="btn btn-sm btn-outline-danger sil-btn">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php }
                } else { ?>
                <tr><td colspan="8" class="text-center text-muted py-3">Henüz paçal kaydı yok</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
