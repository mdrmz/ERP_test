<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["oturum"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Oturum gerekli."
    ]);
    exit;
}

include("../baglan.php");

$parti_no = trim((string) ($_GET['parti_no'] ?? $_POST['parti_no'] ?? ''));
if ($parti_no === '') {
    echo json_encode([
        "success" => false,
        "exists" => false,
        "message" => "Pacal Parti No bos olamaz."
    ]);
    exit;
}

$parti_no_esc = $baglanti->real_escape_string($parti_no);
$res = $baglanti->query("SELECT id FROM uretim_pacal WHERE parti_no = '$parti_no_esc' LIMIT 1");
$exists = ($res && $res->num_rows > 0);

echo json_encode([
    "success" => true,
    "exists" => $exists,
    "message" => $exists ? ("Bu Pacal Parti No zaten kayitli: " . $parti_no) : ""
]);

$baglanti->close();

