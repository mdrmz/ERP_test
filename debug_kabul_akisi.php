<?php
echo "TEST BASLADI<br>";
flush();

echo "PHP Calisiyor: " . phpversion() . "<br>";
flush();

// Sadece 2 saniyelik cURL testi
$url = "http://192.168.1.53:1453/tartim.txt";
echo "Hedef: $url<br>";
flush();

if (function_exists('curl_init')) {
    echo "cURL YUKLU<br>";
    flush();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Kod: $code<br>";
    echo "Hata: $err<br>";
    echo "Sonuc: " . htmlspecialchars(substr($result, 0, 300)) . "<br>";
} else {
    echo "cURL YUKLU DEGIL<br>";
}

echo "<br>TEST BITTI";
?>
