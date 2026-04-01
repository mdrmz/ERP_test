<?php
include("baglan.php");

// Cihaz sayısı
$r = $baglanti->query("SELECT COUNT(*) as c FROM plc_cihazlari");
$row = $r->fetch_assoc();
echo "Toplam Cihaz: " . $row['c'] . "\n";

// Etiket sayısı
$r = $baglanti->query("SELECT COUNT(*) as c FROM plc_etiketleri");
$row = $r->fetch_assoc();
echo "Toplam Etiket: " . $row['c'] . "\n";

echo "\n--- CİHAZLAR (Akar Kantar hariç) ---\n";
$r = $baglanti->query("SELECT cihaz_adi, ip_adresi, cihaz_tipi FROM plc_cihazlari WHERE cihaz_tipi != 'akar_kantar' ORDER BY ip_adresi");
while ($row = $r->fetch_assoc()) {
    echo $row['cihaz_adi'] . " -> " . $row['ip_adresi'] . " (" . $row['cihaz_tipi'] . ")\n";
}

echo "\n--- ETİKET DAĞILIMI ---\n";
$r = $baglanti->query("SELECT c.cihaz_adi, COUNT(e.id) as etiket_sayisi FROM plc_cihazlari c LEFT JOIN plc_etiketleri e ON e.cihaz_id = c.id WHERE c.cihaz_tipi != 'akar_kantar' GROUP BY c.id ORDER BY c.ip_adresi");
while ($row = $r->fetch_assoc()) {
    echo $row['cihaz_adi'] . ": " . $row['etiket_sayisi'] . " etiket\n";
}

$baglanti->close();
