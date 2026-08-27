<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
    exit;
}

$last = (int)($_SESSION['last_activity_timestamp'] ?? time());
if (time() - $last > 3600) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
    exit;
}

$_SESSION['last_activity_timestamp'] = time();
echo json_encode(['status' => 'active']);