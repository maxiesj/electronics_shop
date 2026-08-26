<?php session_start(); header('Content-Type: application/json');
include '../db.php'; // Centralized MySQLi connection link ($conn)

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']); exit; }

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['checkout_csrf_token']) || !is_string($csrf_token) || !hash_equals($_SESSION['checkout_csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Your checkout session is invalid or expired. Please refresh the checkout page and try again.']);
    exit;
}

$method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'wallet';

if ($method === 'mpesa') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'M-Pesa order settlement is not available yet. Please top up your wallet first, then check out using your wallet.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Fetch live system tax configurations
    $tx_st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'tax_rate' LIMIT 1");
    $tx_st->execute(); $tx_res = $tx_st->get_result()->fetch_assoc();
    $tax_rate = floatval($tx_res['setting_value'] ?? 7.00); $div = 1 + ($tax_rate / 100);

    // 2. Load the buyer's tax information parameter references
    $u_st = $conn->prepare("SELECT kra_pin FROM users WHERE id = ?");
    $u_st->bind_param("i", $user_id); $u_st->execute(); $u_res = $u_st->get_result()->fetch_assoc();
    $customer_pin = $u_res['kra_pin'] ?? 'A001276890C';

    // 3. Compile transaction products basket lists
    $c_st = $conn->prepare("SELECT c.quantity, p.id, p.price, p.cost_price, p.stock_quantity, p.product_name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? FOR UPDATE");
    $c_st->bind_param("i", $user_id); $c_st->execute(); $items = $c_st->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($items) === 0) throw new Exception("Your shopping basket selection records are empty.");

    $gross_total = 0; 
    foreach ($items as $i) { 
        if (!isset($i['cost_price']) || (float)$i['cost_price'] <= 0) throw new Exception("Checkout is temporarily unavailable for " . $i['product_name'] . " because its buying cost has not been recorded. Please contact the shop administrator.");
        if ($i['stock_quantity'] < $i['quantity']) throw new Exception("Insufficient stock available for: " . $i['product_name']); 
        $gross_total += $i['price'] * $i['quantity']; 
    }

    $r_net_total = round($gross_total / $div, 2);
    $r_vat_total = round($gross_total - ($gross_total / $div), 2);
    $r_gross_total = round($gross_total, 2);

    $txn_code = "TXN_" . strtoupper(uniqid());

    // Fetch user available funding parameters inside customer_wallets table row
    $w_st = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ? FOR UPDATE");
    $w_st->bind_param("i", $user_id); $w_st->execute(); $w_res = $w_st->get_result()->fetch_assoc();
    $bal = isset($w_res['available_balance']) ? floatval($w_res['available_balance']) : 0.00;

    // 4. Diverge Processing Conditions based on Payment Mode
    if ($method === 'wallet') {
        if ($bal < $r_gross_total) throw new Exception("Insufficient wallet funds available for a full payment purchase.");

        // Deduct full amount from wallet balance
        $upd_w = $conn->prepare("UPDATE customer_wallets SET available_balance = available_balance - ?, updated_at = NOW() WHERE user_id = ?");
        $upd_w->bind_param("di", $r_gross_total, $user_id); $upd_w->execute();
        
        $order_status = 'pending';
        $pay_method_label = 'Customer Wallet';
        $payment_cash_amount = $r_gross_total;
        $payment_status_string = 'completed';

    } elseif ($method === 'polepole') {
        // AUTOMATED 50% DOWNPAYMENT CALCULATION
        $required_deposit = round($r_gross_total * 0.50, 2);
        
        if ($bal < $required_deposit) {
            throw new Exception("Lipa Pole Pole checkout requires a 50% initial downpayment deposit. Required: KES " . number_format($required_deposit, 2) . ", but your wallet has KES " . number_format($bal, 2));
        }

        // Deduct exactly 50% from the customer's wallet instantly
        $upd_w = $conn->prepare("UPDATE customer_wallets SET available_balance = available_balance - ?, updated_at = NOW() WHERE user_id = ?");
        $upd_w->bind_param("di", $required_deposit, $user_id); $upd_w->execute();

        $order_status = 'pending';
        $pay_method_label = 'Lipa Pole Pole';
        $payment_cash_amount = $required_deposit;
        $payment_status_string = 'completed';

    } else {
        throw new Exception("Unsupported payment gateway channel mapping value.");
    }

    // 5. Append primary order row log into the database with balanced parameters
    $ins_o = $conn->prepare("INSERT INTO orders (user_id, kra_pin, net_amount, vat_amount, applied_tax_rate, total_amount, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    // Explicitly binding the 7 parameters directly to remove compilation errors
    $ins_o->bind_param("isdddds", $user_id, $customer_pin, $r_net_total, $r_vat_total, $tax_rate, $r_gross_total, $order_status);
    $ins_o->execute(); 
    
    $order_id = $conn->insert_id;

    // 6. Write transaction history ledger audit entry line straight into payments table
    $ins_p = $conn->prepare("INSERT INTO payments (order_id, payment_method, transaction_code, amount, payment_status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins_p->bind_param("issds", $order_id, $pay_method_label, $txn_code, $payment_cash_amount, $payment_status_string);
    $ins_p->execute();

    // 7. Write detailed item line splits into order_items and reduce warehouse inventory
    $ins_i = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, net_price, vat_price, price, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $upd_p = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");

    foreach ($items as $i) { 
        $gross = $i['price']; $net = $gross / $div; $vat = $gross - $net; 
        $item_net = round($net, 2); $item_vat = round($vat, 2); $item_gross = round($gross, 2);
        $item_cost = isset($i['cost_price']) && (float)$i['cost_price'] > 0 ? round((float)$i['cost_price'], 2) : null;
        
        $ins_i->bind_param("iiidddd", $order_id, $i['id'], $i['quantity'], $item_net, $item_vat, $item_gross, $item_cost); $ins_i->execute(); 
        $upd_p->bind_param("iii", $i['quantity'], $i['id'], $i['quantity']);
        $upd_p->execute();
        if ($upd_p->affected_rows !== 1) {
            throw new Exception("Insufficient stock available for: " . $i['product_name']);
        } 
    }

    // 8. If Lipa Pole Pole selected, set up open layaway installment tracking plan balance row automatically with 50% pre-paid parameters
    if ($method === 'polepole') {
        $remaining_50 = $r_gross_total - $payment_cash_amount;
        $ins_plan = $conn->prepare("INSERT INTO layaway_plans (order_id, user_id, total_amount, deposit_paid, balance_remaining, status, created_at) VALUES (?, ?, ?, ?, ?, 'Active', NOW())");
        $ins_plan->bind_param("iiddd", $order_id, $user_id, $r_gross_total, $payment_cash_amount, $remaining_50);
        $ins_plan->execute();
    }

    // 9. Clear current customer checkout shopping cart rows properties entries values
    $del_c = $conn->prepare("DELETE FROM cart WHERE user_id = ?"); $del_c->bind_param("i", $user_id); $del_c->execute();

    $conn->commit();
    unset($_SESSION['checkout_csrf_token']);
    
    $success_txt = ($method === 'polepole') 
        ? "Installment plan registered successfully! An initial downpayment of 50% (KES " . number_format($payment_cash_amount, 2) . ") was deducted automatically from your wallet."
        : "Checkout processed and logged successfully! Unique transaction tracking code code generated: " . $txn_code;

    echo json_encode(['status' => 'success', 'message' => $success_txt, 'order_id' => $order_id]);
} catch (Exception $e) { 
    $conn->rollback(); 
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); 
}
