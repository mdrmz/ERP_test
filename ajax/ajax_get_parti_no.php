<?php
include("../baglan.php");
session_start();

if (!isset($_SESSION["oturum"])) {
    http_response_code(403);
    exit("Yetkisiz erişim");
}

if (isset($_GET['hammadde_id'])) {
    $id = (int) $_GET['hammadde_id'];

    // Get hammadde_kodu
    $res = $baglanti->query("SELECT hammadde_kodu FROM hammaddeler WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $kod = $res->fetch_assoc()['hammadde_kodu'];

        if (empty($kod)) {
            echo "KOD-0001"; // Fallback
            exit;
        }

        // Get max parti_no that starts with $kod-
        // Using CAST to correctly convert the numeric part to INT for ordering
        $sql = "SELECT parti_no FROM hammadde_girisleri 
                WHERE parti_no LIKE '$kod-%' 
                ORDER BY CAST(SUBSTRING_INDEX(parti_no, '-', -1) AS UNSIGNED) DESC 
                LIMIT 1";

        $max_res = $baglanti->query($sql);

        if ($max_res && $max_res->num_rows > 0) {
            $last = $max_res->fetch_assoc()['parti_no'];
            $parts = explode('-', $last);
            if (count($parts) > 1) {
                // Get the last part and increment
                $num = (int) end($parts);
                $next_num = $num + 1;
            } else {
                $next_num = 1;
            }
        } else {
            $next_num = 1;
        }

        // Return structured code like D1-0001
        echo $kod . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    } else {
        echo "";
    }
}
?>