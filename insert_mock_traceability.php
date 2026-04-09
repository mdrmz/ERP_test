<?php
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("baglan.php");

echo "Generating rich mock traceability data (including Paçal & Tavlama)...\n";

$baglanti->query("SET FOREIGN_KEY_CHECKS=0");

try {
    $urun_id = 1;
    $urun_adi = 'Özel Amaçlı Buğday Unu';
    $musteri_id = 2; // Mock Test Müşterisi
    $musteri_adi = 'Mock Test Müşterisi';

    $parti_no = "PRT-FULL-" . rand(1000, 9999);
    $ham_parti_no = "HAM-FULL-" . rand(100, 999);
    
    // Dates
    $t_hammadde = date("Y-m-d H:i:s", strtotime("-5 days"));
    $t_pacal = date("Y-m-d", strtotime("-4 days"));
    $t_tav1 = date("Y-m-d H:i:s", strtotime("-3 days 10:00:00"));
    $t_tav2 = date("Y-m-d H:i:s", strtotime("-2 days 14:00:00"));
    $t_tav3 = date("Y-m-d H:i:s", strtotime("-1 days 08:00:00"));
    $t_b1 = date("Y-m-d H:i:s", strtotime("-1 days 20:00:00"));
    $t_un1 = date("Y-m-d H:i:s", strtotime("-1 days 22:00:00"));
    $t_paket = date("Y-m-d H:i:s", strtotime("now"));

    // --- 1. HAMMADDE ---
    echo "Inserting hammadde...\n";
    $baglanti->query("INSERT INTO hammadde_girisleri (parti_no, hammadde_id, miktar_kg, irsaliye_no, arac_plaka, tarih, hektolitre, nem, protein, sertlik) 
    VALUES ('$ham_parti_no', 1, 25000, 'IRS-FULL-1', '34 FULL 34', '$t_hammadde', 81, 11.5, 12, 65)");

    // --- 2. PAÇAL ---
    echo "Inserting pacal...\n";
    $baglanti->query("INSERT INTO uretim_pacal (tarih, urun_adi, parti_no, toplam_miktar_kg, notlar, olusturan) 
    VALUES ('$t_pacal', '$urun_adi', '$parti_no', 25000, 'Zenginleştirilmiş mock verisidir.', 'Antigravity')");
    $pacal_id = $baglanti->insert_id;

    // Paçal Detayları
    $baglanti->query("INSERT INTO uretim_pacal_detay (pacal_id, sira_no, hammadde_id, hammadde_parti_no, yoresi, miktar_kg, oran, perten_protein, gluten) 
    VALUES ($pacal_id, 1, 1, '$ham_parti_no', 'Konya', 15000, 60.00, 12.5, 28)");
    $baglanti->query("INSERT INTO uretim_pacal_detay (pacal_id, sira_no, hammadde_id, hammadde_parti_no, yoresi, miktar_kg, oran, perten_protein, gluten) 
    VALUES ($pacal_id, 2, 1, '$ham_parti_no', 'Ankara', 10000, 40.00, 11.8, 26)");

    // --- 3. TAVLAMA 1 ---
    echo "Inserting tav1...\n";
    $baglanti->query("INSERT INTO uretim_tavlama_1 (pacal_id, baslama_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, olusturan) 
    VALUES ($pacal_id, '$t_tav1', 18.5, 22.0, 25.0, 'Antigravity')");
    $t1_id = $baglanti->insert_id;
    $baglanti->query("INSERT INTO uretim_tavlama_1_detay (tavlama_1_id, yas_ambar_no, hedef_nem, nem, perten_protein) 
    VALUES ($t1_id, 'Silo-1', 15.5, 15.2, 12.1)");

    // --- 4. TAVLAMA 2 ---
    echo "Inserting tav2...\n";
    $baglanti->query("INSERT INTO uretim_tavlama_2 (tavlama_1_id, baslama_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, olusturan) 
    VALUES ($t1_id, '$t_tav2', 19.0, 23.5, 24.8, 'Antigravity')");
    $t2_id = $baglanti->insert_id;
    $baglanti->query("INSERT INTO uretim_tavlama_2_detay (tavlama_2_id, yas_ambar_no, hedef_nem, nem, perten_protein) 
    VALUES ($t2_id, 'Silo-2', 16.0, 15.8, 11.9)");

    // --- 5. TAVLAMA 3 ---
    echo "Inserting tav3...\n";
    $baglanti->query("INSERT INTO uretim_tavlama_3 (tavlama_2_id, baslama_tarihi, su_derecesi, ortam_derecesi, toplam_tonaj, olusturan) 
    VALUES ($t2_id, '$t_tav3', 19.5, 24.0, 24.5, 'Antigravity')");
    $t3_id = $baglanti->insert_id;
    $baglanti->query("INSERT INTO uretim_tavlama_3_detay (tavlama_3_id, yas_ambar_no, hedef_nem, nem, perten_protein) 
    VALUES ($t3_id, 'Silo-3', 16.5, 16.3, 11.8)");

    // --- 6. B1 DEĞİRMEN ---
    echo "Inserting b1...\n";
    $baglanti->query("INSERT INTO uretim_b1 (tavlama_3_id, baslama_tarihi, su_derecesi, ortam_derecesi, b1_tonaj, olusturan) 
    VALUES ($t3_id, '$t_b1', 20.0, 25.0, 24.2, 'Antigravity')");
    $ub1_id = $baglanti->insert_id;
    $baglanti->query("INSERT INTO uretim_b1_detay (b1_id, yas_ambar_no, hektolitre, nem, perten_protein) 
    VALUES ($ub1_id, 'B1-Ambar', 79.5, 15.5, 11.5)");

    // --- 7. UN 1 ANALİZ ---
    echo "Inserting un1...\n";
    $baglanti->query("INSERT INTO uretim_un1 (b1_id, numune_saati, olusturan) 
    VALUES ($ub1_id, '$t_un1', 'Lab-Auto')");
    $un1_id = $baglanti->insert_id;
    $baglanti->query("INSERT INTO uretim_un1_detay (un1_id, silo_no, perten_protein, perten_nem, perten_kul, gluten, g_index, alveo_w) 
    VALUES ($un1_id, 'Un-Silo-5', 11.2, 14.2, 0.55, 27, 92, 280)");

    // --- 8. ÜRETİM / PAKETLEME ---
    echo "Inserting paketleme...\n";
    $baglanti->query("INSERT INTO paketleme_hareketleri (urun_id, miktar, parti_no, tarih, personel) 
    VALUES ($urun_id, 500, '$parti_no', '$t_paket', 'Usta Paketçi')");

    // --- 9. SİPARİŞ & SEVKİYAT ---
    echo "Inserting siparis...\n";
    $siparis_kodu = "SIP-FULL-" . rand(100, 999);
    $baglanti->query("INSERT INTO siparisler (musteri_id, siparis_kodu, siparis_tarihi, teslim_tarihi, durum, aciklama) 
    VALUES ($musteri_id, '$siparis_kodu', '$t_paket', '$t_paket', 'TeslimEdildi', 'Tam izlenebilirlik testi.')");
    $siparis_id = $baglanti->insert_id;

    $baglanti->query("INSERT INTO sevkiyatlar (siparis_id, musteri_adi, sevk_tarihi, sevk_miktari, parti_no, arac_plaka) 
    VALUES ($siparis_id, '$musteri_adi', '$t_paket', 500, '$parti_no', '06 BOLD 06')");

    $baglanti->query("INSERT INTO sevkiyat_randevulari (musteri_adi, randevu_tarihi, arac_plaka, sofor_adi, durum, miktar_ton) 
    VALUES ('$musteri_adi', '$t_paket', '06 BOLD 06', 'Veli Şoför', 'Tamamlandi', 25.0)");
    $sevk_id = $baglanti->insert_id;

    $baglanti->query("INSERT INTO sevkiyat_icerik (sevkiyat_id, parti_no, miktar) 
    VALUES ($sevk_id, '$parti_no', 500)");

    echo "\nSuccess! Parti No: $parti_no\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
} finally {
    $baglanti->query("SET FOREIGN_KEY_CHECKS=1");
}
?>
