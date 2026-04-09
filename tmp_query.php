<?php
include("baglan.php");
$res = $baglanti->query("SELECT * FROM plc_etiketleri");
while($row = $res->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
