<?php
include("baglan.php");
$res = $baglanti->query("SELECT * FROM urunler");
if ($res) {
    if ($res->num_rows > 0) {
        echo "Table exists and has " . $res->num_rows . " rows.\n";
        while ($r = $res->fetch_assoc())
            print_r($r);
    } else {
        echo "Table exists but is empty.";
    }
} else {
    echo "Query failed: " . $baglanti->error;
}
?>
