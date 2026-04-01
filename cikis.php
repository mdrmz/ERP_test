<?php
session_start();

// 1. Session verisini ÖNCEden yakala (session_destroy'dan önce!)
$should_log = isset($_SESSION['user_id']);
$user_id = $should_log ? (int) $_SESSION['user_id'] : 0;
$kadi = $_SESSION['kadi'] ?? 'unknown';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// 2. Session'ı her zaman kapat (primary action)
session_destroy();

// 3. Log kaydet (secondary action - hata olsa da redirect çalışır)
if ($should_log) {
    include("baglan.php");
    if (isset($baglanti) && !$baglanti->connect_error) {
        $kadi_esc = $baglanti->real_escape_string($kadi);
        $baglanti->query("INSERT INTO system_logs (user_id, action_type, module, description, ip_address)
                          VALUES ($user_id, 'LOGOUT', 'auth', 'Kullanıcı çıkışı: $kadi_esc', '$ip')");
    }
}

header("Location: login.php");
exit;
?>
