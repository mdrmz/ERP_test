<?php
// planlama_canli_rota_tab.php
if (!isset($_SESSION["oturum"])) exit;

if (isset($_POST['baslat_rota'])) {
    $kaynak = (int)$_POST['kaynak_silo_id'];
    $hedef = (int)$_POST['hedef_silo_id'];
    
    if($kaynak > 0 && $hedef > 0 && $kaynak != $hedef) {
        $sql = "INSERT INTO canli_silo_transferleri (kaynak_silo_id, hedef_silo_id, baslangic_tarihi, son_guncelleme, durum) VALUES ($kaynak, $hedef, NOW(), NOW(), 'devam_ediyor')";
        $baglanti->query($sql);
        echo "<script>window.location.href='planlama.php?tab=canli_rota&msg=rota_olusturuldu';</script>";
        exit;
    } else {
        echo "<script>alert('Kaynak ve hedef geçerli/farklı olmalı.');</script>";
    }
}

if (isset($_GET['durdur_rota'])) {
    $id = (int)$_GET['durdur_rota'];
    $baglanti->query("UPDATE canli_silo_transferleri SET durum='durduruldu', son_guncelleme=NOW() WHERE id=$id");
    echo "<script>window.location.href='planlama.php?tab=canli_rota';</script>";
    exit;
}

if (isset($_GET['tamamla_rota'])) {
    $id = (int)$_GET['tamamla_rota'];
    $baglanti->query("UPDATE canli_silo_transferleri SET durum='tamamlandi', son_guncelleme=NOW() WHERE id=$id");
    echo "<script>window.location.href='planlama.php?tab=canli_rota';</script>";
    exit;
}
if (isset($_GET['sil_rota'])) {
    $id = (int)$_GET['sil_rota'];
    $baglanti->query("DELETE FROM canli_silo_transferleri WHERE id=$id");
    echo "<script>window.location.href='planlama.php?tab=canli_rota';</script>";
    exit;
}

// Tüm Silolar
$siloQ = $baglanti->query("SELECT id, silo_adi, tip, kapasite_m3, doluluk_m3 FROM silolar ORDER BY tip, silo_adi");
$siloList = [];
if($siloQ) while($r = $siloQ->fetch_assoc()) $siloList[] = $r;

// Aktif ve Eski rotalar
$rotaQ = $baglanti->query("
    SELECT c.*, 
           coalesce(sk.silo_adi, 'Silinen Silo') as kaynak_adi, coalesce(sk.tip, '') as kaynak_tip,
           coalesce(sh.silo_adi, 'Silinen Silo') as hedef_adi, coalesce(sh.tip, '') as hedef_tip
    FROM canli_silo_transferleri c
    LEFT JOIN silolar sk ON c.kaynak_silo_id = sk.id
    LEFT JOIN silolar sh ON c.hedef_silo_id = sh.id
    ORDER BY c.id DESC
");

?>
<div class="row">
    <div class="col-md-5">
        <div class="card card-custom border-primary mb-4 p-4 shadow-sm" style="border-radius:12px; border-left: 5px solid #0d6efd !important;">
            <h5 class="fw-bold mb-3"><i class="fas fa-route text-primary me-2"></i>Yeni Rota Oluştur</h5>
            <p class="small text-muted mb-3">SCADA anlık akış bilgilerini izleyerek, aşağıda seçtiğiniz kaynağın debisi baz alınarak, kaynaktan düşüp hedefe miktar eklenecektir.</p>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-bold">Kaynak Silo (Nereden Akıyor?)</label>
                    <select name="kaynak_silo_id" class="form-select" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach($siloList as $s) { 
                             echo "<option value='{$s['id']}'>".strtoupper($s['tip'])." - {$s['silo_adi']} (Doluluk: ".number_format($s['doluluk_m3'],1)."m3)</option>";
                        } ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Hedef Silo (Nereye Gidiyor?)</label>
                    <select name="hedef_silo_id" class="form-select" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach($siloList as $s) { 
                             echo "<option value='{$s['id']}'>".strtoupper($s['tip'])." - {$s['silo_adi']}</option>";
                        } ?>
                    </select>
                </div>
                <button type="submit" name="baslat_rota" class="btn btn-primary w-100 py-2"><i class="fas fa-play me-2"></i>Rotayı Başlat</button>
            </form>
        </div>
        
        <div class="alert alert-info shadow-sm mb-4" style="border-radius:12px;">
            <i class="fas fa-info-circle me-2"></i>
            Otomatik stok düşümü işlemi, SCADA üzerinde <strong>kaynak silo motoru (akar durum) aktif olduğunda</strong> gerçekleşir. Rota "Devam Ediyor" olsa bile, PLC'de vana açılmazsa stok düşümü (aktarım) hesaplanmaz.
        </div>
    </div>
    <div class="col-md-7">
        <h5 class="fw-bold mb-3"><i class="fas fa-list text-secondary me-2"></i>Aktif & Geçmiş Rotalar</h5>
        
        <?php if($rotaQ && $rotaQ->num_rows > 0) { ?>
            <?php while($rota = $rotaQ->fetch_assoc()) { 
                $badge = ($rota['durum'] == 'devam_ediyor') ? 'bg-success progress-bar-animated progress-bar-striped' : (($rota['durum'] == 'durduruldu') ? 'bg-warning text-dark' : 'bg-secondary');
                $d = str_replace(['devam_ediyor','durduruldu','tamamlandi'], ['AKTİF (Dinleniyor)','DURDURULDU','TAMAMLANDI'], $rota['durum']);
                $borderKRenk = ($rota['durum'] == 'devam_ediyor') ? 'success' : 'secondary';
            ?>
                <div class="card shadow-sm mb-3 border-0 border-start border-4 border-<?php echo $borderKRenk; ?>" style="border-radius:12px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <small class="text-muted d-block mb-1">Başlangıç: <?php echo date("d.m.Y H:i", strtotime($rota['baslangic_tarihi'])); ?></small>
                                <div class="d-flex align-items-center gap-3 mt-2">
                                    <div class="text-center">
                                        <div class="badge bg-dark fw-normal mb-1">Kaynak</div>
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($rota['kaynak_adi']); ?></h6>
                                    </div>
                                    <div class="text-primary text-center px-2">
                                        <i class="fas fa-arrow-right fa-2x <?php echo ($rota['durum']=='devam_ediyor')?'animate-arrow':'text-muted'; ?>"></i>
                                    </div>
                                    <div class="text-center">
                                        <div class="badge bg-dark fw-normal mb-1">Hedef</div>
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($rota['hedef_adi']); ?></h6>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge <?php echo $badge; ?> px-2 py-1 mb-2 d-inline-block"><?php echo $d; ?></span>
                                <div class="text-muted small">Aktarılan (Tahmini)</div>
                                <h5 class="fw-bold text-dark mb-0"><?php echo number_format($rota['transfer_kg'] ?? 0, 1); ?> kg</h5>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-top d-flex justify-content-end gap-2">
                            <?php if($rota['durum'] == 'devam_ediyor') { ?>
                                <a href="planlama.php?tab=canli_rota&durdur_rota=<?php echo $rota['id']; ?>" class="btn btn-sm btn-outline-warning">Durdur</a>
                                <a href="planlama.php?tab=canli_rota&tamamla_rota=<?php echo $rota['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> İşlemi Tamamla</a>
                            <?php } else { ?>
                                <a href="planlama.php?tab=canli_rota&sil_rota=<?php echo $rota['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Geçmiş kaydı silmek istediğinize emin misiniz?');"><i class="fas fa-trash"></i> Sil</a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="alert alert-light border text-center p-5" style="border-radius:12px;">
                <i class="fas fa-route fa-3x text-muted opacity-25 mb-3 d-block"></i>
                <p class="mb-0 text-muted">Şu anda kayıtlı bir rota bulunmuyor.</p>
            </div>
        <?php } ?>
    </div>
</div>
<style>
.animate-arrow {
    display: inline-block;
    animation: moveRight 1.5s infinite ease-in-out;
}
@keyframes moveRight {
    0% { transform: translateX(0); opacity: 0.5; }
    50% { transform: translateX(5px); opacity: 1; }
    100% { transform: translateX(0); opacity: 0.5; }
}
</style>
