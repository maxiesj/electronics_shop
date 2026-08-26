<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../order_payment_guard.php';

if (!verifyExplicitWorkspaceClearance('layaway_defaulters.php')) {
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');
    exit;
}

$staff_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
$staff_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff Member';
if (empty($_SESSION['staff_layaway_csrf'])) {
    $_SESSION['staff_layaway_csrf'] = bin2hex(random_bytes(32));
}
$msg = ''; $err = '';

// Handle Contract Cancellation, Stock Replenishment, and Refund Logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_plan'])) {
    $plan_id = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT);
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $current_operator = $staff_name;

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['staff_layaway_csrf'], (string)$_POST['csrf_token'])) {
        $err = 'This page expired. Refresh it and try again.';
    } elseif (!$plan_id || !$order_id) {
        $err = 'Select a valid installment plan.';
    } else {
        try {
            $conn->begin_transaction();
            
            $p_info = $conn->prepare("SELECT order_id, user_id, status FROM layaway_plans WHERE id = ? LIMIT 1 FOR UPDATE");
            if (!$p_info) throw new Exception('Unable to prepare the installment lookup.');
            $p_info->bind_param('i', $plan_id);
            if (!$p_info->execute()) throw new Exception('Unable to load the installment plan.');
            $plan_data = $p_info->get_result()->fetch_assoc();
            $p_info->close();
            if (!$plan_data || (int)$plan_data['order_id'] !== (int)$order_id) {
                throw new Exception('The selected installment plan does not match this order.');
            }
            if (strtolower(trim((string)$plan_data['status'])) !== 'active') {
                throw new Exception('This installment plan is no longer active.');
            }

            $order_lock = $conn->prepare('SELECT user_id, order_status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
            if (!$order_lock) throw new Exception('Unable to prepare the order lookup.');
            $order_lock->bind_param('i', $order_id);
            if (!$order_lock->execute()) throw new Exception('Unable to load the order.');
            $order_data = $order_lock->get_result()->fetch_assoc();
            $order_lock->close();
            if (!$order_data || (int)$order_data['user_id'] !== (int)$plan_data['user_id']) throw new Exception('The order owner does not match the installment plan.');
            if (in_array(strtolower(trim((string)$order_data['order_status'])), ['cancelled', 'delivered'], true)) {
                throw new Exception('This order is already finalized.');
            }
            $target_buyer = (int)$plan_data['user_id'];
            $settlement = getOrderSettlementState($conn, (int)$order_id, false);
            if (!$settlement) throw new Exception('Unable to determine completed payments for this order.');
            $refund_amount = max(0, round((float)$settlement['paid_total'], 2));

            // 1. Mark the Layaway plan status as Canceled
            $upd_plan = $conn->prepare("UPDATE layaway_plans SET status = 'Canceled' WHERE id = ?");
            $upd_plan->bind_param('i', $plan_id);
            if (!$upd_plan->execute()) throw new Exception('Unable to cancel the installment plan.');
            
            // 2. Mark the Master Order entry status as Cancelled
            $upd_order = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
            $upd_order->bind_param('i', $order_id);
            if (!$upd_order->execute()) throw new Exception('Unable to cancel the linked order.');

            if ($refund_amount > 0) {
                $wallet_lock = $conn->prepare('SELECT id FROM customer_wallets WHERE user_id = ? LIMIT 1 FOR UPDATE');
                if (!$wallet_lock) throw new Exception('Unable to prepare the customer wallet lookup.');
                $wallet_lock->bind_param('i', $target_buyer);
                if (!$wallet_lock->execute()) throw new Exception('Unable to load the customer wallet.');
                $wallet_exists = $wallet_lock->get_result()->fetch_assoc();
                $wallet_lock->close();

                if ($wallet_exists) {
                    $wallet_credit = $conn->prepare('UPDATE customer_wallets SET available_balance = available_balance + ?, updated_at = NOW() WHERE user_id = ?');
                    if (!$wallet_credit) throw new Exception('Unable to prepare the wallet refund.');
                    $wallet_credit->bind_param('di', $refund_amount, $target_buyer);
                } else {
                    $wallet_credit = $conn->prepare('INSERT INTO customer_wallets (user_id, available_balance, updated_at) VALUES (?, ?, NOW())');
                    if (!$wallet_credit) throw new Exception('Unable to prepare the refund wallet.');
                    $wallet_credit->bind_param('id', $target_buyer, $refund_amount);
                }
                if (!$wallet_credit->execute()) throw new Exception('Unable to credit the customer wallet.');
                $wallet_credit->close();

                $refund_payments = $conn->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE order_id = ? AND LOWER(TRIM(payment_status)) = 'completed'");
                if (!$refund_payments) throw new Exception('Unable to prepare payment reconciliation.');
                $refund_payments->bind_param('i', $order_id);
                if (!$refund_payments->execute()) throw new Exception('Unable to mark completed payments as refunded.');
                $refund_payments->close();
            }
            
            // 3. Return unit values back to stock shelves
            $items_st = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_st->bind_param('i', $order_id);
            if (!$items_st->execute()) throw new Exception('Unable to load the ordered stock.');
            $items_list = $items_st->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $replenish_st = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($items_list as $item) {
                $replenish_st->bind_param("ii", $item['quantity'], $item['product_id']);
                if (!$replenish_st->execute()) throw new Exception('Unable to restore ordered stock.');
            }
            
            $refund_stmt = $conn->prepare("INSERT INTO refund_logs (order_id, user_id, refund_amount, processed_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            if (!$refund_stmt) throw new Exception('Unable to prepare the refund record.');
            $refund_stmt->bind_param('iids', $order_id, $target_buyer, $refund_amount, $current_operator);
            if (!$refund_stmt->execute()) throw new Exception('Unable to save the refund record.');
            $refund_stmt->close();

            $audit_details = "Overdue installment plan #{$plan_id} cancelled with order #{$order_id}. KES " . number_format($refund_amount, 2) . " in verified completed payments refunded to User ID #{$target_buyer}; ordered stock restored.";
            $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Financial Update', ?)");
            if (!$audit) throw new Exception('Unable to prepare the mandatory Staff audit record.');
            $audit->bind_param('iss', $staff_id, $staff_name, $audit_details);
            if (!$audit->execute()) throw new Exception('Unable to save the mandatory Staff audit record.');
            $audit->close();

            $conn->commit();
            $_SESSION['staff_layaway_csrf'] = bin2hex(random_bytes(32));
            $msg = $refund_amount > 0
                ? "Installment plan #{$plan_id} was cancelled. KES " . number_format($refund_amount, 2) . ' was refunded to the customer wallet and stock was restored.'
                : "Installment plan #{$plan_id} was cancelled. No completed payment was available to refund; stock was restored.";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Transaction rollback failed: " . $e->getMessage();
        }
    }
}

// Query layout scans for active open installment profiles matching date offsets greater than 30 days
$query = "SELECT l.*, u.fullname, u.phone, o.created_at as order_date 
          FROM layaway_plans l 
          JOIN users u ON l.user_id = u.id 
          JOIN orders o ON l.order_id = o.id 
          WHERE l.balance_remaining > 0 AND LOWER(l.status) = 'active' AND DATEDIFF(NOW(), o.created_at) > 30 
          ORDER BY l.id DESC";

$defaulters_result = $conn->query($query);
$defaulters_list = $defaulters_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Layaway Defaulters Ledger | ADONAK ELECTRONICS</title>
    <style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; z-index: 50; }
    .nav-brand { font-weight: 800; font-size: 1.25rem; color: #f97316; white-space: nowrap; }
    .nav-center-links { display: flex; gap: 16px; font-size: 0.875rem; font-weight: 600; align-items: center; }
    .nav-center-links a { color: #d1d5db; text-decoration: none; padding: 6px 12px; border-radius: 4px; transition: background 0.2s, color 0.2s; white-space: nowrap; }
    .nav-center-links a:hover, .nav-center-links a.active { color: white; background-color: #1f2937; }
    
    /* Session Meta & Exit Controls */
    .nav-right-meta { display: flex; align-items: center; gap: 20px; font-size: 0.875rem; }
    .logout-btn { color: #f87171 !important; text-decoration: none; font-weight: 700; border: 1px solid #7f1d1d; padding: 6px 12px; border-radius: 6px; background-color: rgba(153, 27, 27, 0.12); white-space: nowrap; transition: background 0.2s, color 0.2s; }
    .logout-btn:hover { background-color: rgba(153, 27, 27, 0.25); color: #fca5a5 !important; }
    
    /* 3. Core Structural Container (Default Desktop View) */
    main { max-width: 80rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    
    /* Fixed broken non-standard style properties allocation values */
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 8px; letter-spacing: -0.025em; }
    .sub-subtitle { font-size: 0.813rem; color: #6b7280; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; }

    /* Action Status Messages */
    .alert-box { padding: 12px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; box-sizing: border-box; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* 4. White Data Matrix Table Wrapper Block */
    .content-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    table { width: 100%; border-collapse: collapse; font-size: 0.813rem; text-align: left; }
    th { background-color: #f9fafb; color: #4b5563; padding: 10px 12px; font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; }
    tr:hover td { background-color: #f8fafc; }
    
    /* Administrative Cancellation Button and Contract Chronology Tags */
    .cancel-btn { background-color: #dc2626; color: white; font-weight: 700; border: none; padding: 6px 12px; border-radius: 4px; font-size: 10px; text-transform: uppercase; cursor: pointer; transition: background-color 0.2s; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; height: 26px; box-sizing: border-box; }
    .cancel-btn:hover { background-color: #b91c1c; }
    .days-badge { font-weight: 800; background-color: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: inline-block; }

    /* 5. Processing Loader Overlay Styles */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    
    /* Fixed invalid custom property string syntax assignments */
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       6. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media (max-width: 1024px) {
        main { margin: 24px auto; padding: 0 16px 40px; }
        .content-block { padding: 16px; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS & SMARTPHONES BREAKPOINT (Max 768px Viewports) */
    @media (max-width: 768px) {
        /* Restructure Navbar row elements into stacked vertical flow */
        nav { flex-direction: column; gap: 14px; padding: 14px 16px; text-align: center; }
        .nav-center-links { gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .nav-center-links a { font-size: 0.8rem; padding: 4px 8px; }
        .nav-right-meta { width: 100%; justify-content: center; border-top: 1px solid #374151; padding-top: 10px; margin-top: 2px; }
        
        /* Main Document Wrapper padding boundaries shrinkages */
        main { margin: 16px auto; padding: 0 12px 32px; }
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        .sub-subtitle { font-size: 0.75rem; margin-bottom: 16px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .content-block { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 0.5rem; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; }
        
        /* Expand touch boundaries for on-the-go administrative actions */
        .cancel-btn { height: 34px; padding: 0 14px; font-size: 11px; }
    }
</style>

<link rel="stylesheet" href="../css/panel-polish.css">
<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <?php include_once 'navbar.php'; ?>



    <main>
        <a class="staff-back-link" href="staff_dashboard.php" aria-label="Back to Staff Dashboard">&larr; Back to Staff Dashboard</a>
        <h1 class="main-title">Lipa Pole Pole Default Tracking Console</h1>
        <p class="sub-subtitle">Contracts exceeding the 30-day timeline allocation thresholds</p>
        
        <?php if (!empty($msg)): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($err)): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div><?php endif; ?>

        <div class="content-block">
            <table>
                <thead>
                    <tr>
                        <th>Plan ID</th>
                        <th>Order ID</th>
                        <th>Customer Mapped</th>
                        <th>Telephone Line</th>
                        <th>Invoice Date</th>
                        <th>Days Defaulted</th>
                        <th>Total Amount</th>
                        <th>Paid Balance</th>
                        <th>Owed Deficit</th>
                        <th>Forfeit Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($defaulters_list) > 0): foreach ($defaulters_list as $row): 
                        $order_date = new DateTime($row['order_date']);
                        $today = new DateTime();
                        $total_days = $today->diff($order_date)->days;
                        $days_overdue = $total_days - 30;
                    ?>
                    <tr>
                        <td style="font-weight: 700;">#<?= $row['id']; ?></td>
                        <td style="font-weight: 700; color: #2563eb;">#<?= $row['order_id']; ?></td>
                        <td style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($row['fullname']); ?></td>
                        <td style="font-family: monospace; font-weight: 700;"><?= htmlspecialchars($row['phone']); ?></td>
                        <td><?= date('Y-m-d', strtotime($row['order_date'])); ?></td>

                        <td><span class="days-badge"><?= $days_overdue; ?> Days Overdue</span></td>
                        <td>KES <?= number_format($row['total_amount'], 2); ?></td>
                        <td style="color: #059669; font-weight: 700;">KES <?= number_format($row['deposit_paid'], 2); ?></td>
                        <td style="color: #dc2626; font-weight: 700;">KES <?= number_format($row['balance_remaining'], 2); ?></td>
                        <td>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Confirm complete layaway termination? This action voids the contract and returns items back to stock shelves.');">
                                <input type="hidden" name="plan_id" value="<?= $row['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_layaway_csrf']); ?>">
                                <input type="hidden" name="order_id" value="<?= $row['order_id']; ?>">
                                <button type="submit" name="cancel_plan" class="cancel-btn">Void Contract</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #9ca3af; padding: 32px 0; font-weight: 600;">Excellent! No client accounts are currently in violation parameters.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

       <!-- Global Interface Inter-Page Loader Transition Overlay Box Wrapper Frame -->
    <div class="loader-overlay" id="global-page-loader">
        <div class="spinner"></div>
        <p class="loader-text">Loading Operations Desk...</p>
    </div>

    <!-- JavaScript Navigation Routing Interceptors -->
    <script>
    document.querySelectorAll('.nav-center-links a, table a, .nav-loading-link, .logout-btn').forEach(link => {
        // Skip fallback actions, javascript tasks, or form submission buttons handled by system alerts
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:') || link.type === 'submit') return;
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            
            // Open full-screen transition spinner layout overlay instantly
            document.getElementById('global-page-loader').style.display = 'flex';
            
            // Hold redirect cushion for 350ms to render the animation smoothly
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 350);
        });
    });
    </script>
</body>
</html>
