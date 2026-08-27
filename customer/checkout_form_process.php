<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/../db.php';

function checkoutFail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function checkoutReferenceExists(mysqli $conn, string $reference): bool
{
    $ref = strtoupper(trim($reference));
    $checks = [
        ['SELECT 1 FROM payments WHERE transaction_code IS NOT NULL AND UPPER(TRIM(transaction_code)) = ? LIMIT 1', 's'],
        ['SELECT 1 FROM payroll_records WHERE reference_number IS NOT NULL AND UPPER(TRIM(reference_number)) = ? LIMIT 1', 's'],
        ['SELECT 1 FROM operating_expenses WHERE reference_number IS NOT NULL AND UPPER(TRIM(reference_number)) = ? LIMIT 1', 's'],
        ['SELECT 1 FROM refund_logs WHERE reversal_reference IS NOT NULL AND UPPER(TRIM(reversal_reference)) = ? LIMIT 1', 's'],
    ];

    foreach ($checks as [$sql, $types]) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to validate transaction reference.');
        }
        $stmt->bind_param($types, $ref);
        $stmt->execute();
        $found = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($found) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkoutFail('Invalid checkout request method.', 405);
}

$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$user_id) {
    checkoutFail('Session expired. Please log in again.', 401);
}

$csrf_token = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['checkout_csrf_token']) || !hash_equals((string)$_SESSION['checkout_csrf_token'], $csrf_token)) {
    checkoutFail('Your checkout session is invalid or expired. Please refresh the checkout page and try again.', 403);
}

$method = strtolower(trim((string)($_POST['payment_method'] ?? 'wallet')));
if (!in_array($method, ['wallet', 'polepole', 'mpesa'], true)) {
    checkoutFail('Unsupported payment method.');
}
if ($method === 'mpesa') {
    checkoutFail('M-Pesa order settlement is not available yet. Please top up your wallet first, then check out using your wallet.');
}

try {
    $conn->begin_transaction();

    // 1. Load and validate live tax configuration.
    $tx_st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'tax_rate' LIMIT 1");
    if (!$tx_st || !$tx_st->execute()) throw new RuntimeException('Tax configuration could not be loaded.');
    $tx_res = $tx_st->get_result()->fetch_assoc();
    $tx_st->close();
    $tax_rate = isset($tx_res['setting_value']) ? (float)$tx_res['setting_value'] : 7.00;
    if (!is_finite($tax_rate) || $tax_rate < 0 || $tax_rate > 100) {
        throw new DomainException('Checkout is temporarily unavailable because the configured tax rate is invalid.');
    }
    $div = 1 + ($tax_rate / 100);

    // 2. Lock and validate the customer record.
    $u_st = $conn->prepare('SELECT id, kra_pin FROM users WHERE id = ? FOR UPDATE');
    if (!$u_st) throw new RuntimeException('Customer lookup could not be prepared.');
    $u_st->bind_param('i', $user_id);
    $u_st->execute();
    $u_res = $u_st->get_result()->fetch_assoc();
    $u_st->close();
    if (!$u_res) throw new DomainException('Your customer account could not be found. Please sign in again.');
    $customer_pin = trim((string)($u_res['kra_pin'] ?? ''));

    // 3. Lock the current basket and product stock rows.
    $c_st = $conn->prepare(
        'SELECT c.quantity, p.id, p.price, p.cost_price, p.stock_quantity, p.product_name '
        . 'FROM cart c JOIN products p ON c.product_id = p.id '
        . 'WHERE c.user_id = ? FOR UPDATE'
    );
    if (!$c_st) throw new RuntimeException('Shopping basket could not be prepared.');
    $c_st->bind_param('i', $user_id);
    $c_st->execute();
    $items = $c_st->get_result()->fetch_all(MYSQLI_ASSOC);
    $c_st->close();

    if (!$items) throw new DomainException('Your shopping basket is empty.');

    $gross_total = 0.0;
    foreach ($items as $item) {
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $cost = (float)$item['cost_price'];
        $stock = (int)$item['stock_quantity'];
        $name = (string)$item['product_name'];

        if ($quantity <= 0) throw new DomainException('Your basket contains an invalid quantity for ' . $name . '.');
        if (!is_finite($price) || $price <= 0) throw new DomainException('Checkout is temporarily unavailable for ' . $name . ' because its selling price is invalid.');
        if (!is_finite($cost) || $cost <= 0) throw new DomainException('Checkout is temporarily unavailable for ' . $name . ' because its buying cost has not been recorded. Please contact the shop administrator.');
        if ($stock < $quantity) throw new DomainException('Insufficient stock available for: ' . $name);

        $line_total = round($price * $quantity, 2);
        if ($line_total <= 0 || $line_total > 99999999.99) throw new DomainException('A basket line has an invalid total.');
        $gross_total = round($gross_total + $line_total, 2);
        if ($gross_total > 99999999.99) throw new DomainException('The order total exceeds the supported checkout limit.');
    }

    if ($gross_total <= 0) throw new DomainException('The order total must be greater than KES 0.00.');

    $r_net_total = round($gross_total / $div, 2);
    $r_vat_total = round($gross_total - $r_net_total, 2);
    $r_gross_total = round($gross_total, 2);

    // Generate a high-entropy reference and confirm it is unused across financial ledgers.
    do {
        $txn_code = 'TXN_' . strtoupper(bin2hex(random_bytes(8)));
    } while (checkoutReferenceExists($conn, $txn_code));

    // 4. Lock the customer's wallet and validate its state.
    $w_st = $conn->prepare('SELECT id, available_balance, is_active_toggle FROM customer_wallets WHERE user_id = ? FOR UPDATE');
    if (!$w_st) throw new RuntimeException('Wallet lookup could not be prepared.');
    $w_st->bind_param('i', $user_id);
    $w_st->execute();
    $w_res = $w_st->get_result()->fetch_assoc();
    $w_st->close();
    if (!$w_res) throw new DomainException('A customer wallet has not been created for your account.');
    if ((int)$w_res['is_active_toggle'] !== 1) throw new DomainException('Your customer wallet is not active.');

    $bal = round((float)$w_res['available_balance'], 2);
    if ($bal < 0) throw new RuntimeException('Wallet balance is invalid.');

    if ($method === 'wallet') {
        $required_amount = $r_gross_total;
        $pay_method_label = 'Customer Wallet';
    } else {
        $required_amount = round($r_gross_total * 0.50, 2);
        $pay_method_label = 'Lipa Pole Pole';
    }

    if ($required_amount <= 0 || $required_amount > $r_gross_total) {
        throw new RuntimeException('Calculated checkout payment amount is invalid.');
    }
    if ($bal + 0.009 < $required_amount) {
        $prefix = $method === 'polepole' ? 'Lipa Pole Pole checkout requires a 50% initial downpayment. ' : '';
        throw new DomainException($prefix . 'Required: KES ' . number_format($required_amount, 2) . ', available: KES ' . number_format($bal, 2) . '.');
    }

    $upd_w = $conn->prepare(
        'UPDATE customer_wallets SET available_balance = available_balance - ?, updated_at = NOW() '
        . 'WHERE user_id = ? AND is_active_toggle = 1 AND available_balance >= ?'
    );
    if (!$upd_w) throw new RuntimeException('Wallet update could not be prepared.');
    $upd_w->bind_param('did', $required_amount, $user_id, $required_amount);
    if (!$upd_w->execute() || $upd_w->affected_rows !== 1) {
        $upd_w->close();
        throw new DomainException('Your wallet balance changed before checkout completed. Please try again.');
    }
    $upd_w->close();

    $order_status = 'pending';
    $payment_status_string = 'completed';
    $payment_cash_amount = $required_amount;

    // 5. Create the order from server-calculated values only.
    $ins_o = $conn->prepare(
        'INSERT INTO orders (user_id, kra_pin, net_amount, vat_amount, applied_tax_rate, total_amount, order_status, created_at) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$ins_o) throw new RuntimeException('Order write could not be prepared.');
    $ins_o->bind_param('isdddds', $user_id, $customer_pin, $r_net_total, $r_vat_total, $tax_rate, $r_gross_total, $order_status);
    if (!$ins_o->execute()) throw new RuntimeException('Order could not be created.');
    $order_id = (int)$conn->insert_id;
    $ins_o->close();
    if ($order_id <= 0) throw new RuntimeException('Order identifier was not created.');

    // 6. Record the initial payment ledger entry.
    $ins_p = $conn->prepare(
        'INSERT INTO payments (order_id, payment_method, transaction_code, amount, payment_status, created_at) '
        . 'VALUES (?, ?, ?, ?, ?, NOW())'
    );
    if (!$ins_p) throw new RuntimeException('Payment write could not be prepared.');
    $ins_p->bind_param('issds', $order_id, $pay_method_label, $txn_code, $payment_cash_amount, $payment_status_string);
    if (!$ins_p->execute()) throw new RuntimeException('Payment record could not be created.');
    $ins_p->close();

    // 7. Write item snapshots and reduce inventory atomically.
    $ins_i = $conn->prepare(
        'INSERT INTO order_items (order_id, product_id, quantity, net_price, vat_price, price, unit_cost) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $upd_p = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
    if (!$ins_i || !$upd_p) throw new RuntimeException('Order item processing could not be prepared.');

    foreach ($items as $item) {
        $product_id = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        $gross = round((float)$item['price'], 2);
        $net = round($gross / $div, 2);
        $vat = round($gross - $net, 2);
        $item_cost = round((float)$item['cost_price'], 2);

        $ins_i->bind_param('iiidddd', $order_id, $product_id, $quantity, $net, $vat, $gross, $item_cost);
        if (!$ins_i->execute()) throw new RuntimeException('An order item could not be recorded.');

        $upd_p->bind_param('iii', $quantity, $product_id, $quantity);
        if (!$upd_p->execute() || $upd_p->affected_rows !== 1) {
            throw new DomainException('Insufficient stock available for: ' . (string)$item['product_name']);
        }
    }
    $ins_i->close();
    $upd_p->close();

    // 8. Create a 50% layaway plan for Pole Pole orders.
    if ($method === 'polepole') {
        $remaining_50 = round($r_gross_total - $payment_cash_amount, 2);
        if (abs(round($payment_cash_amount + $remaining_50, 2) - $r_gross_total) > 0.01) {
            throw new RuntimeException('Installment totals are inconsistent.');
        }
        $ins_plan = $conn->prepare(
            "INSERT INTO layaway_plans (order_id, user_id, total_amount, deposit_paid, balance_remaining, status, created_at) "
            . "VALUES (?, ?, ?, ?, ?, 'Active', NOW())"
        );
        if (!$ins_plan) throw new RuntimeException('Installment plan could not be prepared.');
        $ins_plan->bind_param('iiddd', $order_id, $user_id, $r_gross_total, $payment_cash_amount, $remaining_50);
        if (!$ins_plan->execute()) throw new RuntimeException('Installment plan could not be created.');
        $ins_plan->close();
    }

    // 9. Clear only this customer's cart after every financial write succeeds.
    $del_c = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
    if (!$del_c) throw new RuntimeException('Cart cleanup could not be prepared.');
    $del_c->bind_param('i', $user_id);
    if (!$del_c->execute()) throw new RuntimeException('Cart cleanup failed.');
    $del_c->close();

    $conn->commit();
    unset($_SESSION['checkout_csrf_token']);

    $success_txt = $method === 'polepole'
        ? 'Installment plan registered successfully. An initial downpayment of 50% (KES ' . number_format($payment_cash_amount, 2) . ') was deducted from your wallet.'
        : 'Checkout processed successfully. Transaction reference: ' . $txn_code;

    echo json_encode([
        'status' => 'success',
        'message' => $success_txt,
        'order_id' => $order_id,
        'transaction_code' => $txn_code,
    ]);
} catch (DomainException $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Checkout failed for user #' . (int)$user_id . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Checkout could not be completed. No funds, stock, or order records were changed.']);
}
