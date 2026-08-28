<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../order_payment_guard.php';

if (!verifyExplicitWorkspaceClearance('manage_orders.php')) {
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');
    exit;
}

$staff_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
$staff_name = trim((string)($_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff Member'));
if (empty($_SESSION['staff_orders_csrf'])) {
    $_SESSION['staff_orders_csrf'] = bin2hex(random_bytes(32));
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $new_status = strtolower(trim((string)($_POST['new_status'] ?? '')));
    $allowed_statuses = ['pending', 'processing', 'delivered'];

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['staff_orders_csrf'], (string)$_POST['csrf_token'])) {
        $err = 'This page expired. Refresh it and try again.';
    } elseif (!$order_id || !in_array($new_status, $allowed_statuses, true)) {
        $err = 'Select a valid order and status.';
    } else {
        $conn->begin_transaction();
        try {
            $settlement = getOrderSettlementState($conn, (int)$order_id, true);
            if (!$settlement) {
                throw new Exception("Order #{$order_id} was not found.");
            }

            $current_state = strtolower(trim((string)$settlement['order_status']));
            if (in_array($current_state, ['delivered', 'cancelled'], true)) {
                throw new Exception("Order #{$order_id} is already finalized and cannot be changed.");
            }

            // Processing is a fulfillment state and may be used while a Lipa Pole Pole
            // order is still being paid. Delivery remains blocked until fully settled.
            if ($new_status === 'delivered' && !$settlement['is_fully_paid']) {
                throw new Exception(
                    "Delivery blocked: Order #{$order_id} has not been fully paid. Paid KES " .
                    number_format($settlement['paid_total'], 2) . ' of KES ' .
                    number_format($settlement['total_amount'], 2) . '. Outstanding: KES ' .
                    number_format($settlement['outstanding_balance'], 2) . '.'
                );
            }

            $stmt = $conn->prepare('UPDATE orders SET order_status = ?, processed_by = ? WHERE id = ?');
            if (!$stmt) {
                throw new Exception('Unable to prepare the order update.');
            }
            $stmt->bind_param('ssi', $new_status, $staff_name, $order_id);
            if (!$stmt->execute()) {
                throw new Exception('Unable to update the order status.');
            }
            $stmt->close();

            $details = "Order #{$order_id} status changed from '{$current_state}' to '{$new_status}'. Settlement verified before fulfillment.";
            $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
            if (!$audit) {
                throw new Exception('Unable to prepare the required audit record.');
            }
            $audit->bind_param('iss', $staff_id, $staff_name, $details);
            if (!$audit->execute()) {
                throw new Exception('Unable to save the required audit record.');
            }
            $audit->close();

            $conn->commit();
            $_SESSION['staff_orders_csrf'] = bin2hex(random_bytes(32));
            $msg = "Order #{$order_id} was updated to " . ucfirst($new_status) . '.';
        } catch (Throwable $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}

$query = "SELECT o.*, u.fullname,
            (SELECT COALESCE(SUM(p.amount), 0.00)
             FROM payments p
             WHERE p.order_id = o.id AND LOWER(TRIM(p.payment_status)) = 'completed') AS total_deposited_so_far
          FROM orders o
          JOIN users u ON o.user_id = u.id
          ORDER BY o.id DESC";

$orders_result = $conn->query($query);
$orders_list = $orders_result ? $orders_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>Manage Order Books | ADONAK ELECTRONICS</title>
    <link rel="stylesheet" href="../css/panel-polish.css">
    <script src="../js/page-progress-dialog.js"></script>
    <style>
        body{background:#f3f4f6;font-family:ui-sans-serif,system-ui,sans-serif;margin:0;color:#1f2937}
        main{max-width:85rem;margin:40px auto;padding:0 24px 64px;box-sizing:border-box;width:100%}
        .main-title{font-size:1.5rem;font-weight:900;color:#111827;margin:0 0 24px}
        .alert-box{padding:12px;border-radius:6px;font-size:.875rem;font-weight:700;margin-bottom:20px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
        .content-block{background:#fff;border:1px solid #e5e7eb;border-radius:.75rem;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:.813rem;text-align:left;min-width:980px}
        th{background:#f9fafb;color:#4b5563;padding:10px 12px;font-weight:700;text-transform:uppercase;font-size:10px;border-bottom:1px solid #e5e7eb}
        td{padding:12px;border-bottom:1px solid #e5e7eb;color:#374151;font-weight:500}
        .status-pill{font-size:10px;font-weight:800;padding:2px 6px;border-radius:4px;text-transform:uppercase;display:inline-block}
        .status-delivered{background:#d1fae5;color:#065f46}.status-pending{background:#fef3c7;color:#92400e}.status-processing{background:#ffedd5;color:#c2410c}.status-cancelled{background:#fee2e2;color:#991b1b}
        .status-select{border:1px solid #d1d5db;border-radius:4px;padding:0 6px;background:#fff;font-size:.75rem;font-weight:700;height:28px}
        .action-btn{background:#111827;color:#fff;font-weight:700;border:0;padding:5px 9px;border-radius:4px;font-size:10px;text-transform:uppercase;cursor:pointer;height:28px}
        .view-items-link{color:#2563eb;text-decoration:none;font-weight:700;text-transform:uppercase;font-size:10px;background:#eff6ff;padding:4px 8px;border-radius:4px;border:1px solid #bfdbfe}
        @media(max-width:768px){main{padding:0 12px 40px;margin-top:185px}.content-block{padding:14px}}
    </style>
</head>
<body>
<?php include_once 'navbar.php'; ?>
<main>
    <a class="staff-back-link" href="staff_dashboard.php" aria-label="Back to Staff Dashboard">&larr; Back to Staff Dashboard</a>
    <h1 class="main-title">Incoming Store Order Ledger</h1>

    <?php if ($msg !== ''): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="content-block">
        <table>
            <thead>
            <tr>
                <th>Invoice ID</th><th>Customer Name</th><th>KRA PIN</th><th>Net Amount</th><th>VAT Amount</th><th>Contract Total</th><th>Amount Deposited</th><th>Outstanding</th><th>Order Status</th><th>Modify State</th><th>View Details</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($orders_list): foreach ($orders_list as $order):
                $clean_status = strtolower(trim((string)($order['order_status'] ?? 'pending')));
                $settlement_view = getOrderSettlementState($conn, (int)$order['id']);
                $paid_so_far = (float)($settlement_view['paid_total'] ?? $order['total_deposited_so_far'] ?? 0);
                $outstanding = (float)($settlement_view['outstanding_balance'] ?? max(0, (float)$order['total_amount'] - $paid_so_far));
                $is_fully_paid = $settlement_view ? (bool)$settlement_view['is_fully_paid'] : $outstanding <= 0.009;
                $is_finalized = in_array($clean_status, ['delivered', 'cancelled'], true);
                $status_class = 'status-' . (in_array($clean_status, ['pending','processing','delivered','cancelled'], true) ? $clean_status : 'pending');
            ?>
            <tr>
                <td style="font-weight:700;color:#2563eb;">#<?= (int)$order['id'] ?></td>
                <td style="text-transform:uppercase;font-weight:700;"><?= htmlspecialchars($order['fullname']) ?></td>
                <td style="font-family:monospace;text-transform:uppercase;font-weight:700;"><?= htmlspecialchars($order['kra_pin'] ?? 'N/A') ?></td>
                <td>KES <?= number_format((float)$order['net_amount'], 2) ?></td>
                <td>KES <?= number_format((float)$order['vat_amount'], 2) ?></td>
                <td style="font-weight:700;">KES <?= number_format((float)$order['total_amount'], 2) ?></td>
                <td style="font-weight:800;color:#059669;">KES <?= number_format($paid_so_far, 2) ?></td>
                <td style="font-weight:800;color:<?= $outstanding > 0.009 ? '#dc2626' : '#059669' ?>;">KES <?= number_format($outstanding, 2) ?></td>
                <td><span class="status-pill <?= $status_class ?>"><?= htmlspecialchars($clean_status) ?></span></td>
                <td>
                    <?php if ($is_finalized): ?>
                        <span style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;">🔒 Finalized</span>
                    <?php else: ?>
                        <form method="POST" style="display:flex;align-items:center;gap:4px;margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_orders_csrf']) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                            <select name="new_status" class="status-select">
                                <option value="pending" <?= $clean_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $clean_status === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <?php if ($is_fully_paid): ?>
                                    <option value="delivered">Delivered</option>
                                <?php else: ?>
                                    <option value="delivered" disabled>Delivered — Due KES <?= number_format($outstanding, 2) ?></option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" name="update_order_status" class="action-btn">Save</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:4px;align-items:center;">
                        <a href="view_order_items.php?order_id=<?= (int)$order['id'] ?>" class="view-items-link nav-loading-link">Items</a>
                        <a href="print_invoice.php?order_id=<?= (int)$order['id'] ?>" target="_blank" style="color:#059669;text-decoration:none;font-weight:700;text-transform:uppercase;font-size:10px;background:#d1fae5;padding:4px 8px;border-radius:4px;border:1px solid #a7f3d0;">🧾 Invoice</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="11" style="text-align:center;color:#9ca3af;padding:32px 0;font-weight:600;">No invoices have been logged in the store database.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
