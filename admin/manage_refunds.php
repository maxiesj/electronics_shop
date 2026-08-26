<?php
require_once __DIR__ . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

if (!verifyWorkspaceClearance('manage_refunds.php')) {
    if (!headers_sent()) {
        header('Location: ../login.php?msg=err_unauthorized_access');
    } else {
        echo "<script>window.location.href='../login.php?msg=err_unauthorized_access';</script>";
    }
    exit;
}

date_default_timezone_set('Africa/Nairobi');
if (empty($_SESSION['refund_csrf'])) {
    $_SESSION['refund_csrf'] = bin2hex(random_bytes(32));
}

$msg = '';
$error = '';
$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorName = trim((string)($_SESSION['fullname'] ?? 'System operator'));
$allowedActions = ['M-Pesa Reversal', 'Converted to Credit'];

$normalizeReference = static function ($value) {
    $value = strtoupper(trim((string)$value));
    return preg_replace('/\s+/', '', $value);
};

$referenceExistsElsewhere = static function (mysqli $conn, string $reference): ?string {
    $checks = [
        ['payments', 'transaction_code', 'customer/sales payment'],
        ['payroll_records', 'reference_number', 'payroll payment'],
        ['operating_expenses', 'reference_number', 'operating expense'],
        ['refund_logs', 'reversal_reference', 'refund/reversal'],
    ];

    foreach ($checks as [$table, $column, $label]) {
        $sql = "SELECT 1 FROM {$table} WHERE {$column} IS NOT NULL AND UPPER(TRIM({$column})) = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $found = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($found) return $label;
    }
    return null;
};

$logAction = static function (mysqli $conn, int $actorId, string $actorName, string $details): void {
    $stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Financial Update', ?)");
    if ($stmt) {
        $stmt->bind_param('iss', $actorId, $actorName, $details);
        $stmt->execute();
        $stmt->close();
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_funds'])) {
    if (!hash_equals((string)$_SESSION['refund_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'This refund form expired. Refresh the page and try again.';
    } else {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $action = trim((string)($_POST['resolution_action'] ?? ''));
        $reference = $normalizeReference($_POST['reversal_ref'] ?? '');

        if ($paymentId <= 0 || !in_array($action, $allowedActions, true)) {
            $error = 'Invalid refund request.';
        } elseif ($action === 'M-Pesa Reversal' && ($reference === '' || strlen($reference) < 4 || strlen($reference) > 100)) {
            $error = 'Enter a valid payout/reversal reference.';
        } else {
            if ($action === 'Converted to Credit') {
                $reference = 'CREDIT-' . strtoupper(bin2hex(random_bytes(6)));
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    "SELECT p.id AS payment_id, p.order_id, p.amount, p.payment_status, p.transaction_code,
                            o.user_id, o.order_status
                     FROM payments p
                     JOIN orders o ON o.id = p.order_id
                     WHERE p.id = ?
                     LIMIT 1
                     FOR UPDATE"
                );
                if (!$stmt) throw new RuntimeException('Unable to validate the payment.');
                $stmt->bind_param('i', $paymentId);
                $stmt->execute();
                $payment = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$payment) throw new RuntimeException('Payment record was not found.');
                if (strtolower(trim((string)$payment['order_status'])) !== 'cancelled') {
                    throw new RuntimeException('Only payments belonging to cancelled orders can be resolved here.');
                }
                if (strtolower(trim((string)$payment['payment_status'])) === 'refunded') {
                    throw new RuntimeException('This payment has already been refunded or resolved.');
                }

                $amount = round((float)$payment['amount'], 2);
                $orderId = (int)$payment['order_id'];
                $userId = (int)$payment['user_id'];
                if ($amount <= 0) throw new RuntimeException('The payment amount is invalid.');

                $dup = $conn->prepare('SELECT id FROM refund_logs WHERE payment_id = ? LIMIT 1 FOR UPDATE');
                if ($dup) {
                    $dup->bind_param('i', $paymentId);
                    $dup->execute();
                    $alreadyLogged = (bool)$dup->get_result()->fetch_row();
                    $dup->close();
                    if ($alreadyLogged) throw new RuntimeException('This payment has already been recorded in the refund ledger.');
                }

                $collision = $referenceExistsElsewhere($conn, $reference);
                if ($collision !== null) {
                    throw new RuntimeException('That reversal reference is already used in a ' . $collision . '.');
                }

                if ($action === 'Converted to Credit') {
                    $wallet = $conn->prepare('SELECT id FROM customer_wallets WHERE user_id = ? LIMIT 1 FOR UPDATE');
                    if (!$wallet) throw new RuntimeException('Unable to access the customer wallet.');
                    $wallet->bind_param('i', $userId);
                    $wallet->execute();
                    $walletExists = (bool)$wallet->get_result()->fetch_row();
                    $wallet->close();

                    if (!$walletExists) {
                        $createWallet = $conn->prepare('INSERT INTO customer_wallets (user_id, available_balance) VALUES (?, 0.00)');
                        if (!$createWallet) throw new RuntimeException('Unable to create the customer wallet.');
                        $createWallet->bind_param('i', $userId);
                        $createWallet->execute();
                        $createWallet->close();
                    }

                    $upWallet = $conn->prepare('UPDATE customer_wallets SET available_balance = available_balance + ? WHERE user_id = ?');
                    if (!$upWallet) throw new RuntimeException('Unable to credit the customer wallet.');
                    $upWallet->bind_param('di', $amount, $userId);
                    $upWallet->execute();
                    if ($upWallet->affected_rows !== 1) {
                        $upWallet->close();
                        throw new RuntimeException('The wallet credit was not completed.');
                    }
                    $upWallet->close();
                }

                $log = $conn->prepare('INSERT INTO refund_logs (order_id, payment_id, amount_processed, resolution_type, reversal_reference) VALUES (?, ?, ?, ?, ?)');
                if (!$log) throw new RuntimeException('Unable to write the refund audit record.');
                $log->bind_param('iidss', $orderId, $paymentId, $amount, $action, $reference);
                $log->execute();
                $log->close();

                $upPay = $conn->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE id = ? AND payment_status <> 'Refunded'");
                if (!$upPay) throw new RuntimeException('Unable to lock the refunded payment.');
                $upPay->bind_param('i', $paymentId);
                $upPay->execute();
                if ($upPay->affected_rows !== 1) {
                    $upPay->close();
                    throw new RuntimeException('Payment status changed before this refund could complete.');
                }
                $upPay->close();

                $details = 'Cancelled payment #' . $paymentId . ' for order #' . $orderId
                    . ' resolved via ' . $action . ', KES ' . number_format($amount, 2, '.', '')
                    . ', reference ' . $reference . '.';
                $logAction($conn, $actorId, $actorName, $details);

                $conn->commit();
                $msg = 'Funds resolved successfully via ' . $action . '. Reference: ' . $reference . '.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e instanceof mysqli_sql_exception
                    ? 'The refund could not be completed because a financial integrity rule was triggered.'
                    : $e->getMessage();
            }
        }
    }
}

$sql = "SELECT p.id AS pay_id, p.amount, p.transaction_code, p.payment_method,
               o.id AS ord_id, o.user_id, u.fullname
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN users u ON o.user_id = u.id
        WHERE LOWER(TRIM(o.order_status)) = 'cancelled'
          AND LOWER(TRIM(p.payment_status)) <> 'refunded'
          AND NOT EXISTS (SELECT 1 FROM refund_logs rl WHERE rl.payment_id = p.id)
        ORDER BY p.id DESC";
$result = $conn->query($sql);

if (basename($_SERVER['PHP_SELF']) === 'manage_refunds.php' || isset($load_view_component) || isset($_POST['ajax_request'])):
?>

<?php if ($error): ?>
    <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:4px;margin-bottom:15px;">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($msg): ?>
    <div style="background:#d4edda;color:#155724;padding:12px;border-radius:4px;margin-bottom:15px;">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card" style="background:white;padding:25px;border-radius:8px;width:100%;box-sizing:border-box;">
    <h2 style="margin-top:0;">💰 Stranded Payments Resolution Manager</h2>
    <p style="color:#64748b;margin-top:-5px;">Resolve paid funds attached to cancelled orders. Amounts and customer identities are verified again from the database when you submit.</p>
    <table style="width:100%;border-collapse:collapse;margin-top:15px;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th style="padding:12px;text-align:left;border-bottom:1px solid #eaeaea;">Order ID</th>
                <th style="padding:12px;text-align:left;border-bottom:1px solid #eaeaea;">Customer</th>
                <th style="padding:12px;text-align:left;border-bottom:1px solid #eaeaea;">Paid Amount</th>
                <th style="padding:12px;text-align:left;border-bottom:1px solid #eaeaea;">Payment Reference</th>
                <th style="padding:12px;text-align:left;border-bottom:1px solid #eaeaea;">Resolution</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td style="padding:12px;border-bottom:1px solid #eaeaea;">#<?= (int)$row['ord_id'] ?></td>
                <td style="padding:12px;border-bottom:1px solid #eaeaea;"><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                <td style="padding:12px;border-bottom:1px solid #eaeaea;font-weight:bold;color:#c53929;">KES <?= number_format((float)$row['amount'], 2) ?></td>
                <td style="padding:12px;border-bottom:1px solid #eaeaea;"><code><?= htmlspecialchars((string)$row['transaction_code']) ?></code></td>
                <td style="padding:12px;border-bottom:1px solid #eaeaea;">
                    <form method="post" action="" style="display:flex;gap:6px;flex-wrap:wrap;margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['refund_csrf']) ?>">
                        <input type="hidden" name="payment_id" value="<?= (int)$row['pay_id'] ?>">
                        <input type="hidden" name="resolve_funds" value="1">
                        <select name="resolution_action" required style="padding:6px;border-radius:4px;border:1px solid #ccc;">
                            <option value="M-Pesa Reversal">M-Pesa Reversal Payout</option>
                            <option value="Converted to Credit">Convert to Store Credit</option>
                        </select>
                        <input type="text" name="reversal_ref" maxlength="100" placeholder="Required for reversal payout" style="padding:6px;border-radius:4px;border:1px solid #ccc;">
                        <button type="submit" style="background:#e67e22;color:white;border:none;font-weight:bold;cursor:pointer;padding:7px 12px;border-radius:4px;">Execute</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">All payments for cancelled orders have been cleared and resolved.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
