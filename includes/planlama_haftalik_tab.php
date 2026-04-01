<?php
// Tab 1: Haftalık Üretim Planı
// Beklenen değişkenler: $baglanti, $mesaj, $hata

$haftaNo = isset($_GET['hafta']) ? (int)$_GET['hafta'] : (int)date('W');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');

// Aktif siparişlerden ürün taleplerini çek
$siparis_talepler = $baglanti->query("
    SELECT sd.urun_adi, SUM(sd.miktar) as toplam_miktar, sd.birim,
           GROUP_CONCAT(DISTINCT m.firma_adi SEPARATOR ', ') as musteriler,
           MIN(s.teslim_tarihi) as en_yakin_teslim
    FROM siparis_detaylari sd
    JOIN siparisler s ON sd.siparis_id = s.id
    JOIN musteriler m ON s.musteri_id = m.id
    WHERE s.durum IN ('Bekliyor','Hazirlaniyor','KismiSevk')
    GROUP BY sd.urun_adi, sd.birim
    ORDER BY MIN(s.teslim_tarihi) ASC
");

// Bu haftanın planı
$hafta_plani = $baglanti->query("
    SELECT hp.*, s.siparis_kodu
    FROM haftalik_plan hp
    LEFT JOIN siparisler s ON hp.siparis_id = s.id
    WHERE hp.hafta_no = $haftaNo AND hp.yil = $yil
    ORDER BY FIELD(hp.oncelik,'acil','yuksek','normal','dusuk'), hp.id
");

// Ürün listesi
$urunler = $baglanti->query("SELECT DISTINCT urun_adi FROM siparis_detaylari ORDER BY urun_adi");
$urun_arr = [];
if ($urunler) while ($u = $urunler->fetch_assoc()) $urun_arr[] = $u['urun_adi'];
?>

<div class="row g-4">
    <!-- SOL: SİPARİŞ TALEPLERİ -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-header bg-white" style="border-radius:12px 12px 0 0">
                <h6 class="mb-0 fw-bold"><i class="fas fa-shopping-cart text-warning me-2"></i>Sipariş Talepleri (Aktif)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:.85rem">
                    <thead class="table-light"><tr><th>Ürün</th><th>Toplam</th><th>Müşteriler</th><th>Teslim</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($siparis_talepler && $siparis_talepler->num_rows > 0) {
                        while ($st = $siparis_talepler->fetch_assoc()) { ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($st['urun_adi']); ?></td>
                            <td><?php echo number_format($st['toplam_miktar'],0,',','.') . ' ' . $st['birim']; ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars(mb_substr($st['musteriler'],0,40)); ?></td>
                            <td><span class="badge bg-danger-subtle text-danger"><?php echo date('d.m', strtotime($st['en_yakin_teslim'])); ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-plana-ekle"
                                    data-urun="<?php echo htmlspecialchars($st['urun_adi']); ?>"
                                    data-miktar="<?php echo $st['toplam_miktar']; ?>">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </td>
                        </tr>
                    <?php } } else { ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aktif sipariş talebi yok</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MANUEL EKLEME -->
        <div class="card border-0 shadow-sm mt-3" style="border-radius:12px">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle text-success me-2"></i>Plana Ekle</h6></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="hafta_no" value="<?php echo $haftaNo; ?>">
                    <input type="hidden" name="yil" value="<?php echo $yil; ?>">
                    <div class="row g-2">
                        <div class="col-5">
                            <input type="text" name="plan_urun" id="planUrunInput" class="form-control form-control-sm" placeholder="Ürün adı" required>
                        </div>
                        <div class="col-3">
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" name="plan_miktar" id="planMiktarInput" class="form-control" placeholder="Ton" required>
                                <span class="input-group-text">T</span>
                            </div>
                        </div>
                        <div class="col-2">
                            <select name="plan_oncelik" class="form-select form-select-sm">
                                <option value="normal">Normal</option>
                                <option value="yuksek">Yüksek</option>
                                <option value="acil">Acil</option>
                                <option value="dusuk">Düşük</option>
                            </select>
                        </div>
                        <div class="col-2">
                            <button type="submit" name="plan_ekle" class="btn btn-success btn-sm w-100"><i class="fas fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SAĞ: HAFTALIK PLAN -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0">
                <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-week text-primary me-2"></i>Hafta <?php echo $haftaNo; ?> / <?php echo $yil; ?></h6>
                <div class="d-flex gap-2">
                    <a href="?hafta=<?php echo $haftaNo - 1; ?>&yil=<?php echo $yil; ?>#haftalikPlan" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
                    <a href="?hafta=<?php echo (int)date('W'); ?>&yil=<?php echo (int)date('Y'); ?>#haftalikPlan" class="btn btn-sm btn-outline-primary">Bu Hafta</a>
                    <a href="?hafta=<?php echo $haftaNo + 1; ?>&yil=<?php echo $yil; ?>#haftalikPlan" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Ürün</th><th>Miktar (Ton)</th><th>Öncelik</th><th>Durum</th><th>İşlem</th></tr></thead>
                    <tbody>
                    <?php if ($hafta_plani && $hafta_plani->num_rows > 0) {
                        while ($hp = $hafta_plani->fetch_assoc()) {
                            $onc_cls = match($hp['oncelik']) { 'acil'=>'bg-danger','yuksek'=>'bg-warning text-dark','dusuk'=>'bg-secondary', default=>'bg-info' };
                            $dur_cls = match($hp['durum']) { 'uretimde'=>'bg-primary','tamamlandi'=>'bg-success', default=>'bg-secondary' };
                    ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($hp['urun_adi']); ?>
                                <?php if ($hp['siparis_kodu']): ?><br><small class="text-muted"><?php echo $hp['siparis_kodu']; ?></small><?php endif; ?>
                            </td>
                            <td><?php echo number_format($hp['miktar_ton'],1,',','.'); ?></td>
                            <td><span class="badge <?php echo $onc_cls; ?>"><?php echo ucfirst($hp['oncelik']); ?></span></td>
                            <td><span class="badge <?php echo $dur_cls; ?>"><?php echo ucfirst($hp['durum']); ?></span></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="sil_plan_id" value="<?php echo $hp['id']; ?>">
                                    <button type="submit" name="plan_sil" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php } } else { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>Bu hafta için plan yok</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-plana-ekle').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('planUrunInput').value = this.dataset.urun;
        document.getElementById('planMiktarInput').value = (parseFloat(this.dataset.miktar) / 1000).toFixed(1);
        document.getElementById('planMiktarInput').focus();
    });
});
</script>
