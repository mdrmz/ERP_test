<?php
// Tab 3: Reçete & İş Emirleri
// Beklenen: $baglanti, $receteler, $bugday_silolari_arr, $yikama_partileri, $aktif_emirler
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-bullhorn"></i> İş Emri Oluştur</h5></div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label text-muted">Üretilecek Reçete</label>
                        <select name="recete_id" class="form-select" required>
                            <option value="">Seçiniz...</option>
                            <?php $receteler->data_seek(0); while ($r = $receteler->fetch_assoc()): ?>
                                <option value="<?php echo $r["id"]; ?>"><?php echo $r["recete_adi"]; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Hedef Miktar (Ton)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="hedef_miktar" class="form-control" placeholder="50" required>
                            <span class="input-group-text">Ton</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted"><i class="fas fa-link"></i> Yıkama Parti No</label>
                        <select name="yikama_parti_no" class="form-select">
                            <option value="">-- Belirtilmedi --</option>
                            <?php if ($yikama_partileri && $yikama_partileri->num_rows > 0) {
                                $yikama_partileri->data_seek(0);
                                while ($yp = $yikama_partileri->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($yp["parti_no"]); ?>">
                                    <?php echo htmlspecialchars($yp["parti_no"]) . " - " . htmlspecialchars($yp["urun_adi"] ?? "") . " (" . date("d.m.Y", strtotime($yp["yikama_tarihi"])) . ")"; ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Sorumlu Personel/Vardiya</label>
                        <input type="text" name="atanan_personel" class="form-control" placeholder="Ahmet Usta / A Vardiyası">
                    </div>
                    <!-- Silo Karışımı -->
                    <div class="mb-3">
                        <label class="form-label text-muted"><i class="fas fa-database"></i> Buğday Silo Karışımı (Paçal)</label>
                        <div id="siloKarisimContainer">
                            <div class="silo-row d-flex align-items-center gap-2 mb-2">
                                <select name="silo_id[]" class="form-select" style="width:60%">
                                    <option value="">Silo Seç...</option>
                                    <?php foreach ($bugday_silolari_arr as $s) {
                                        $dol = ($s['kapasite_m3']>0)?round(($s['doluluk_m3']/$s['kapasite_m3'])*100):0; ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo $s['silo_adi']; ?> (<?php echo $dol; ?>% dolu)</option>
                                    <?php } ?>
                                </select>
                                <input type="number" name="silo_yuzde[]" class="form-control silo-yuzde" placeholder="%" min="0" max="100" step="0.1" style="width:80px">
                                <span class="text-muted">%</span>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-silo-sil" disabled><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnSiloEkle"><i class="fas fa-plus"></i> Silo Ekle</button>
                        <div class="form-text">Toplam: <span id="toplamYuzde" class="fw-bold">0</span>% <span id="yuzdeUyari" class="text-danger d-none">(Toplam %100 olmalı!)</span></div>
                    </div>
                    <button type="submit" name="is_emri_ver" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i> Emri Yayınla</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><h5 class="mb-0 text-secondary">Yayındaki İş Emirleri</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>İş Kodu</th><th>Reçete</th><th>Hedef</th><th>Silo Karışımı</th><th>Durum</th><th>Sorumlu</th><th>İşlem</th></tr></thead>
                    <tbody>
                        <?php if ($aktif_emirler && $aktif_emirler->num_rows > 0) {
                            while ($is = $aktif_emirler->fetch_assoc()) {
                                $renk = ($is["durum"]=='bekliyor') ? 'bg-warning text-dark' : 'bg-primary'; ?>
                        <tr>
                            <td><span class="badge bg-dark"><?php echo $is["is_kodu"]; ?></span></td>
                            <td class="fw-bold"><?php echo $is["recete_adi"]; ?></td>
                            <td><?php echo $is["hedef_miktar_ton"]; ?> Ton</td>
                            <td class="small"><?php echo $is["silo_karisimi"] ?: '<span class="text-muted">-</span>'; ?></td>
                            <td><span class="badge <?php echo $renk; ?>"><?php echo strtoupper($is["durum"]); ?></span></td>
                            <td class="small text-muted"><?php echo $is["atanan_personel"]; ?></td>
                            <td><button class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></button></td>
                        </tr>
                        <?php } } else { ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted">Aktif iş emri bulunmuyor.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
