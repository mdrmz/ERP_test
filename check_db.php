<?php
include("baglan.php");
$out = "";
$tables = ['hammadde_girisleri', 'yikama_kayitlari', 'aktarma_kayitlari', 'b1_degirmen_kayitlari', 'un_cikis_kayitlari', 'uretim_hareketleri', 'lab_analizleri', 'paketleme_hareketleri', 'sevkiyatlar'];
foreach($tables as $t) {
    if(empty($t)) continue;
    $out .= "--- $t ---\n";
    $r = $baglanti->query("DESCRIBE $t");
    if($r) {
        while($row = $r->fetch_assoc()) {
            $out .= $row['Field'] . " (" . $row['Type'] . ") | ";
        }
        $out .= "\n";
    }
}
$out .= "\n--- ERRORS ---\n" . $baglanti->error;
file_put_contents("db_schema.txt", $out);
echo "Done";
?>
