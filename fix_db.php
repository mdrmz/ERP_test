<?php
include("baglan.php");
$res = $baglanti->query("DESCRIBE lab_analizleri");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "\n====\n";
$res2 = $baglanti->query("DESCRIBE hammadde_girisleri");
while ($row = $res2->fetch_assoc()) {
    print_r($row);
}
?>
