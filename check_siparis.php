<?php
include("baglan.php");
$tables = ['siparisler', 'siparis_detaylari', 'sevkiyatlar', 'sevkiyat_icerik', 'sevkiyat_randevulari'];
$out = "";
foreach($tables as $t) {
    $out .= "--- $t ---\n";
    $r = $baglanti->query("DESCRIBE $t");
    if($r) {
        while($row = $r->fetch_assoc()) {
            $out .= $row['Field'] . " (" . $row['Type'] . ") | ";
        }
        $out .= "\n";
    }
}
file_put_contents("siparis_schema.txt", $out);
echo "Done";
?>
