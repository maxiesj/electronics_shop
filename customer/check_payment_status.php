<?php
session_start();
header('Content-Type: application/json');
include '../db.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing order identification parameter.']);
    exit;
}

// Query the order status to see if callback.php changed it from 'awaiting_payment'
$stmt = $conn->prepare("SELECT order_status FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

$status = $res['order_status'] ?? 'unknown';

echo json_encode(['status' => $status]);
