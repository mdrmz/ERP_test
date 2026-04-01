<?php
$db = new mysqli('localhost', 'root', '', 'yonetim_paneli');
$o = '';
foreach(['uretim_pacal','uretim_pacal_detay','silo_stok_detay','silolar'] as $t) {
    $o .= "\nTABLE $t:\n";
    $r = $db->query("SHOW COLUMNS FROM $t");
    while($row = $r->fetch_assoc()) {
        $o .= $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
}
file_put_contents('schema_output2.txt', $o);
