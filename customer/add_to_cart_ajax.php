<?php session_start(); header('Content-Type: application/json');
include '../db.php';

$user_id = $_SESSION['user_id'] ?? null; if (!$user_id) { echo json_encode(['status' => 'error', 'message' => 'Please login first.']); exit; }
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0; $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
if ($product_id <= 0 || $quantity <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']); exit; }

$st = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
$st->bind_param("i", $product_id); $st->execute(); $p = $st->get_result()->fetch_assoc();
if (!$p || $p['stock_quantity'] < $quantity) { echo json_encode(['status' => 'error', 'message' => 'Requested quantity exceeds stock limits.']); exit; }

$st = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$st->bind_param("ii", $user_id, $product_id); $st->execute(); $exist = $st->get_result()->fetch_assoc();

if ($exist) { 
    $new_qty = $exist['quantity'] + $quantity; 
    if ($new_qty > $p['stock_quantity']) { echo json_encode(['status' => 'error', 'message' => 'Exceeds stock limits.']); exit; }
    $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?"); $upd->bind_param("ii", $new_qty, $exist['id']); $upd->execute();
} else { 
    $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)"); $ins->bind_param("iii", $user_id, $product_id, $quantity); $ins->execute();
}

$st = $conn->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?"); $st->bind_param("i", $user_id); $st->execute();
$res = $st->get_result()->fetch_row();
echo json_encode(['status' => 'success', 'message' => 'Product added to basket.', 'new_count' => (int)($res[0] ?? 0)]);
