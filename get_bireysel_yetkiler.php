<?php
/**
 * get_bireysel_yetkiler.php
 * Belirli bir kullanıcının bireysel override kayıtlarını VE rol yetkilerini JSON olarak döndürür.
 */
session_start();
include("baglan.php");
include("helper_functions.php");

oturumKontrol();
if (kullaniciRolu($baglanti) !== 'Patron') {
    http_response_code(403);
    echo json_encode(['error' => 'Yetkisiz erişim']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['overrides' => [], 'rol_yetkileri' => []]);
    exit;
}

// 1. Kullanıcının bireysel override kayıtları
$stmt = $baglanti->prepare(
    "SELECT modul_adi, okuma, yazma, onaylama 
     FROM kullanici_modul_yetkileri 
     WHERE user_id = ?
     ORDER BY modul_adi"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$overrides = [];
while ($row = $result->fetch_assoc()) {
    $overrides[$row['modul_adi']] = $row;
}
$stmt->close();

// 2. Kullanıcının rolünden gelen yetkiler
$rol_stmt = $baglanti->prepare(
    "SELECT m.modul_adi, m.okuma, m.yazma, m.onaylama
     FROM modul_yetkileri m
     JOIN users u ON u.rol_id = m.rol_id
     WHERE u.id = ?"
);
$rol_stmt->bind_param('i', $user_id);
$rol_stmt->execute();
$rol_result = $rol_stmt->get_result();
$rol_yetkileri = [];
while ($row = $rol_result->fetch_assoc()) {
    $rol_yetkileri[$row['modul_adi']] = $row;
}
$rol_stmt->close();

echo json_encode([
    'overrides' => $overrides,
    'rol_yetkileri' => $rol_yetkileri
], JSON_UNESCAPED_UNICODE);
