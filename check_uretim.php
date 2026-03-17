<?php
include("baglan.php");
$tables = ['uretim_pacal', 'uretim_pacal_detay', 'uretim_tavlama_1', 'uretim_tavlama_2', 'uretim_tavlama_3', 'uretim_b1', 'uretim_un1'];
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
file_put_contents("uretim_schema.txt", $out);
echo "Done";
?>
