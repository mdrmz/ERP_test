<?php
include("baglan.php");

$materials = [
    ['Sülfürik Asit', 5.5, 'Litre', 'Protein Analizi', 2],
    ['Filtre Kağıdı', 100, 'Adet', 'Sedim Analizi', 20],
    ['Sodyum Hidroksit', 0.8, 'Kg', 'Gluten Analizi', 1],
    ['Distile Su', 25, 'Litre', 'Genel Analiz', 5],
];

foreach ($materials as $m) {
    $ad = $baglanti->real_escape_string($m[0]);
    $miktar = $m[1];
    $birim = $baglanti->real_escape_string($m[2]);
    $alan = $baglanti->real_escape_string($m[3]);
    $kritik = $m[4];
    
    $sql = "INSERT INTO bakim_lab_malzemeler (malzeme_adi, miktar, birim, kullanim_alani, kritik_seviye) VALUES ('$ad', $miktar, '$birim', '$alan', $kritik)";
    $baglanti->query($sql);
}

echo "Mock lab materials inserted.";
?>
