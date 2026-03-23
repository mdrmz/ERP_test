<?php
require 'baglan.php';
$tables = ['musteriler', 'siparisler', 'siparis_detaylari'];
foreach($tables as $t) {
    echo "--- $t ---\n";
    $res = $baglanti->query("SHOW COLUMNS FROM $t");
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
