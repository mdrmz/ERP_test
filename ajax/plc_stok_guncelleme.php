<?php
// ajax/plc_stok_guncelleme.php
error_reporting(0); 
include("../baglan.php");

header('Content-Type: application/json');

$response = ['basari' => false, 'mesaj' => '', 'islem_goren_transferler' => 0];

try {
    $baglanti->begin_transaction();
    
    // AKTİF TRANSFERLER VE FOR UPDATE KİLİDİ (RACE CONDITION ENGELLER)
    $transfer_res = $baglanti->query("SELECT * FROM canli_silo_transferleri WHERE durum = 'devam_ediyor' FOR UPDATE");
    
    if (!$transfer_res || $transfer_res->num_rows == 0) {
        $baglanti->rollback();
        echo json_encode(['basari' => true, 'mesaj' => 'Aktif transfer yok.', 'islem_goren_transferler' => 0]);
        exit;
    }
    
    // Tüm silolar ve plc okuma tablosu verileri hafızada kalsın
    $plc_map = [];
    $cihaz_res = $baglanti->query("
        SELECT 
           c.ip_adresi, 
           (SELECT veriler FROM plc_okumalari p WHERE p.cihaz_id = c.id ORDER BY p.id DESC LIMIT 1) as son_okuma
        FROM plc_cihazlari c
    ");
    while($cr = $cihaz_res->fetch_assoc()) {
        if ($cr['son_okuma']) {
            $plc_map[$cr['ip_adresi']] = json_decode($cr['son_okuma'], true);
        }
    }
    
    $islem_adeti = 0;
    
    while ($t = $transfer_res->fetch_assoc()) {
        $t_id = (int)$t['id'];
        $kaynak_id = (int)$t['kaynak_silo_id'];
        $hedef_id = (int)$t['hedef_silo_id'];
        $son_guncelleme = $t['son_guncelleme'];
        
        $sql_kaynak = "SELECT s.*, 
            (SELECT COALESCE(yogunluk_kg_m3, 780) FROM hammaddeler WHERE hammadde_kodu = s.aktif_hammadde_kodu LIMIT 1) as yogunluk 
            FROM silolar s WHERE id = $kaynak_id";
        $kaynak_res = $baglanti->query($sql_kaynak);
        
        $sql_hedef = "SELECT s.*, 
            (SELECT COALESCE(yogunluk_kg_m3, 780) FROM hammaddeler WHERE hammadde_kodu = s.aktif_hammadde_kodu LIMIT 1) as yogunluk 
            FROM silolar s WHERE id = $hedef_id";
        $hedef_res = $baglanti->query($sql_hedef);
        
        if (!$kaynak_res || !$hedef_res || $kaynak_res->num_rows == 0 || $hedef_res->num_rows == 0) continue;
        
        $kaynak_silo = $kaynak_res->fetch_assoc();
        $hedef_silo = $hedef_res->fetch_assoc();
        
        $plc_ip = $kaynak_silo['plc_ip_adresi'];
        if (!$plc_ip || !isset($plc_map[$plc_ip])) continue; // PLC bağlı değilse geç
        
        $plc_verisi = $plc_map[$plc_ip];
        $akar_durum = (int)($plc_verisi['AKAR_DURUM'] ?? 0);
        $anlik_tonaj = (float)($plc_verisi['ANLIK_TONAJ'] ?? 0);
        
        // 2: Çalışıyor
        if ($akar_durum !== 2 || $anlik_tonaj <= 0) continue;
        
        // BAŞARILI, SİSTEM ÇALIŞIYOR. ZAMAN FARKINI VE AKTARILACAK.
        $zaman_farki_sorgu = $baglanti->query("SELECT TIMESTAMPDIFF(SECOND, '$son_guncelleme', NOW()) as saniyeFark");
        $saniye_farki = (int)$zaman_farki_sorgu->fetch_assoc()['saniyeFark'];
        
        if ($saniye_farki < 5) continue; // Çok sık gelirse atla, en az 5 sn fark olsun ki SQL lock aşırı olmasın
        if ($saniye_farki > 120) $saniye_farki = 10; // Makine kapanmış ama web istek gelmemisse (Risk mitigation)
        
        // kg hesabı
        $transfer_kg = ($anlik_tonaj / 3600) * $saniye_farki;
        if ($transfer_kg <= 0) continue;
        
        // KAYNAK SİLO DÜŞÜM İŞLEMİ (FIFO)
        $kaynak_kalan_m3 = (float)$kaynak_silo['doluluk_m3'];
        if ($kaynak_kalan_m3 <= 0.001) { 
            // Silo boşaldı, transferi durdur.
            $baglanti->query("UPDATE canli_silo_transferleri SET durum='tamamlandi', son_guncelleme=NOW() WHERE id=$t_id");
            continue; 
        }
        
        $k_yogunluk = (float)$kaynak_silo['yogunluk'];
        if($k_yogunluk <= 0) $k_yogunluk = 780;
        $h_yogunluk = (float)$hedef_silo['yogunluk'];
        if($h_yogunluk <= 0) $h_yogunluk = 780;
        
        $dusen_kg = 0;
        $fifo_res = $baglanti->query("SELECT id, kalan_miktar_kg FROM silo_stok_detay WHERE silo_id = $kaynak_id AND kalan_miktar_kg > 0 ORDER BY giris_tarihi ASC");
        
        $kalan_transfer = $transfer_kg;
        while($kalan_transfer > 0 && ($f = $fifo_res->fetch_assoc())) {
            $f_id = $f['id'];
            $stok_kapat = (float)$f['kalan_miktar_kg'];
            if ($stok_kapat > $kalan_transfer) {
                $baglanti->query("UPDATE silo_stok_detay SET kalan_miktar_kg = kalan_miktar_kg - $kalan_transfer WHERE id = $f_id");
                $dusen_kg += $kalan_transfer;
                $kalan_transfer = 0;
            } else {
                $baglanti->query("UPDATE silo_stok_detay SET kalan_miktar_kg = 0, durum='tükendi' WHERE id = $f_id");
                $dusen_kg += $stok_kapat;
                $kalan_transfer -= $stok_kapat;
            }
        }
        
        if ($dusen_kg > 0) {
            // M3 düşüşü
            $k_m3_dusus = $dusen_kg / $k_yogunluk;
            $baglanti->query("UPDATE silolar SET doluluk_m3 = GREATEST(0, doluluk_m3 - $k_m3_dusus) WHERE id = $kaynak_id");
            
            // HEDEF SİLO EKLEME İŞLEMİ
            $h_m3_artis = $dusen_kg / $h_yogunluk;
            $baglanti->query("UPDATE silolar SET doluluk_m3 = doluluk_m3 + $h_m3_artis WHERE id = $hedef_id");
            
            // Hedefe Stok detay record girmek
            $p_kod = "TRN-K{$kaynak_id}-H{$hedef_id}";
            
            // Hedef silonun varsayılan hammadde turu muhtemelen 'TAV_PACAL' vs dir.
            $h_turu = $hedef_silo['aktif_hammadde_kodu'] ?: 'PAÇAL';
            
            $h_stok_check = $baglanti->query("SELECT id FROM silo_stok_detay WHERE silo_id = $hedef_id AND parti_kodu = '$p_kod' AND durum = 'aktif' AND DATE(giris_tarihi) = CURDATE() ORDER BY id DESC LIMIT 1");
            if ($h_stok_check && $h_stok_check->num_rows > 0) {
                $h_stok_id = $h_stok_check->fetch_assoc()['id'];
                $baglanti->query("UPDATE silo_stok_detay SET giren_miktar_kg = giren_miktar_kg + $dusen_kg, kalan_miktar_kg = kalan_miktar_kg + $dusen_kg WHERE id = $h_stok_id");
            } else {
                $baglanti->query("INSERT INTO silo_stok_detay (silo_id, parti_kodu, hammadde_turu, giren_miktar_kg, kalan_miktar_kg, giris_tarihi, durum) VALUES ($hedef_id, '$p_kod', '$h_turu', $dusen_kg, $dusen_kg, NOW(), 'aktif')");
            }
            
            $baglanti->query("UPDATE canli_silo_transferleri SET transfer_kg = transfer_kg + $dusen_kg, son_guncelleme=NOW() WHERE id=$t_id");
            $islem_adeti++;
        }
    }
    
    $baglanti->commit();
    echo json_encode(['basari' => true, 'mesaj' => 'İşlem tamam.', 'islem_goren_transferler' => $islem_adeti]);

} catch (Exception $e) {
    if ($baglanti) $baglanti->rollback();
    echo json_encode(['basari' => false, 'hata' => $e->getMessage()]);
}
