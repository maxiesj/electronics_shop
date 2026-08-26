<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../order_payment_guard.php';

if (!verifyExplicitWorkspaceClearance('manage_orders.php')) {
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');
    exit;
}
$staff_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
$staff_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff Member';
if (empty($_SESSION['staff_orders_csrf'])) {
    $_SESSION['staff_orders_csrf'] = bin2hex(random_bytes(32));
}

$msg = ''; $err = '';

// Handle Order Processing Status Update Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $new_status = strtolower(trim((string)($_POST['new_status'] ?? '')));
    $allowed_statuses = ['pending', 'processing', 'delivered'];
    $current_operator = $_SESSION['staff_name'] ?? 'Duty Staff Operator';
    
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['staff_orders_csrf'], (string)$_POST['csrf_token'])) {
        $err = 'This page expired. Refresh it and try again.';
    } elseif (!$order_id || !in_array($new_status, $allowed_statuses, true)) {
        $err = 'Select a valid order and status.';
    } else {
        // 1. Fetch current status and total amount
        $chk = $conn->prepare("SELECT order_status, total_amount FROM orders WHERE id = ?");
        $chk->bind_param("i", $order_id); $chk->execute();
        $order_data = $chk->get_result()->fetch_assoc();
        $current_state = strtolower(trim($order_data['order_status'] ?? 'pending'));
        $total_amount = floatval($order_data['total_amount']);

        // 2. Fetch total payments made so far for this order
        $p_st = $conn->prepare("SELECT COALESCE(SUM(amount), 0.00) FROM payments WHERE order_id = ? AND LOWER(payment_status) = 'completed'");
        $p_st->bind_param("i", $order_id); $p_st->execute();
        $res_p = $p_st->get_result()->fetch_row();
        $total_deposited = floatval($res_p[0] ?? 0.00);
        
        $settlement = getOrderSettlementState($conn, $order_id);
        $is_fully_paid = $settlement && $settlement['is_fully_paid'];
        $outstanding_balance = $settlement['outstanding_balance'] ?? max(0, $total_amount - $total_deposited);

        // --- LIFECYCLE GUARDRAIL CHECKPOINTS ---
        if (in_array($current_state, ['delivered', 'cancelled'], true)) {
            $err = "Security Guard Override: Order #{$order_id} is already finalized as Delivered and cannot be altered.";
        } 
        // Rule: If trying to mark as Paid or Delivered but the customer has only paid a partial deposit
        elseif (($new_status === 'delivered' || $new_status === 'processing') && !$is_fully_paid) {
            $err = "❌ ACTION REFUSED: Order #{$order_id} cannot transition to '{$new_status}'. Outstanding balance must be fully settled first. (Paid: KES " . number_format($total_deposited, 2) . " / Total: KES " . number_format($total_amount, 2) . "; Outstanding: KES " . number_format($outstanding_balance, 2) . ")";
        } 
        else {
            $conn->begin_transaction();
            try {
                $locked_settlement = getOrderSettlementState($conn, (int)$order_id, true);
                if (!$locked_settlement) throw new Exception("Order #{$order_id} was not found.");
                $locked_state = strtolower(trim((string)$locked_settlement['order_status']));
                if (in_array($locked_state, ['delivered', 'cancelled'], true)) {
                    throw new Exception("Order #{$order_id} is already finalized and cannot be changed.");
                }
                if (in_array($new_status, ['processing', 'delivered'], true) && !$locked_settlement['is_fully_paid']) {
                    throw new Exception("Order #{$order_id} still has an outstanding balance and cannot move to " . ucfirst($new_status) . '.');
                }
                $stmt = $conn->prepare('UPDATE orders SET order_status = ?, processed_by = ? WHERE id = ?');
                if (!$stmt) throw new Exception('Unable to prepare the order update.');
                $stmt->bind_param('ssi', $new_status, $staff_name, $order_id);
                if (!$stmt->execute()) throw new Exception('Unable to update the order status.');
                $stmt->close();

                $details = "Order #{$order_id} status changed from '{$locked_state}' to '{$new_status}'. Settlement verified before fulfillment.";
                $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                if (!$audit) throw new Exception('Unable to prepare the required audit record.');
                $audit->bind_param('iss', $staff_id, $staff_name, $details);
                if (!$audit->execute()) throw new Exception('Unable to save the required audit record.');
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
}

// FETCH MASTER ORDERS AND CALCULATE ACTUALLY PAID DEPOSITS VIA PAYMENTS AGGREGATION SUBQUERY
$query = "SELECT o.*, u.fullname,
            (SELECT COALESCE(SUM(p.amount), 0.00) 
             FROM payments p 
             WHERE p.order_id = o.id AND LOWER(p.payment_status) = 'completed') as total_deposited_so_far
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.id DESC";

$orders_result = $conn->query($query);
$orders_list = $orders_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Manage Order Books | ADONAK ELECTRONICS</title>
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
    main { max-width: 85rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    
    /* Fixed broken non-standard style properties allocation values */
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; letter-spacing: -0.025em; }
    
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
    
    /* Inline Detail Reference Anchor Actions */
    .view-items-link { color: #2563eb; text-decoration: none; font-weight: 700; text-transform: uppercase; font-size: 10px; background-color: #eff6ff; padding: 4px 8px; border-radius: 4px; border: 1px solid #bfdbfe; white-space: nowrap; display: inline-flex; align-items: center; }
    .view-items-link:hover { background-color: #dbeafe; }

    /* Administrative Status Form Selectors & Trigger Buttons */
    .status-select { border: 1px solid #d1d5db; border-radius: 4px; padding: 0 6px; background-color: white; font-size: 0.75rem; font-weight: 700; outline: none; cursor: pointer; color: #374151; height: 26px; box-sizing: border-box; }
    .action-btn { background-color: #111827; color: white; font-weight: 700; border: none; padding: 4px 8px; border-radius: 4px; font-size: 10px; text-transform: uppercase; cursor: pointer; height: 26px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; transition: background-color 0.2s; white-space: nowrap; }
    .action-btn:hover { background-color: #1f2937; }

    /* Order Status Indicators */
    .status-pill { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; display: inline-block; white-space: nowrap; }
    .status-delivered { background-color: #d1fae5; color: #065f46; }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-processing { background-color: #ffedd5; color: #c2410c; }
    .status-paid { background-color: #e0f2fe; color: #0369a1; }

    /* 5. Processing Loader Overlay Styles */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    
    /* Fixed broken custom property naming layout declarations */
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       6. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media (max-width: 1024px) {
        main { margin: 24px auto; }
        .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS & SMARTPHONES BREAKPOINT (Max 768px Viewports) */
    @media (max-width: 768px) {
        /* FIXED TOP NAVIGATION BAR: Freezes your header firmly to the top edge of the mobile screen */
        nav { 
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: auto !important;
            flex-direction: column !important; 
            gap: 10px !important; 
            padding: 14px 16px !important; 
            text-align: center !important; 
            z-index: 9999 !important; /* Forces dashboard elements to pass underneath the menu */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1f2937 !important;
        }
        
        .nav-center-links { 
            display: flex !important;
            flex-direction: row !important;
            gap: 6px !important; 
            flex-wrap: wrap !important; /* Allows your navigation links to stack into two neat rows */
            justify-content: center !important; 
            width: 100% !important; 
        }
        .nav-center-links a { 
            font-size: 0.8rem !important; 
            padding: 6px 10px !important; 
            flex-shrink: 0 !important; /* Stops the browser from shrinking buttons */
        }
        .nav-right-meta { 
            width: 100% !important; 
            justify-content: center !important; 
            border-top: 1px solid #374151 !important; 
            padding-top: 10px !important; 
            margin-top: 2px !important; 
        }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your data content safely below the fixed nav height */
        main { 
            padding: 0 12px 40px !important; 
            margin-top: 185px !important; /* Adjust this value precisely if your page components overlap */
            margin-left: auto !important;
            margin-right: auto !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Stack profile welcome details fields */
        .welcome-row { flex-direction: column; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
        .welcome-title { font-size: 1.4rem; }
        
        /* Wrap shift tracking check-in components vertically */
        .attendance-banner { flex-direction: column; align-items: flex-start; gap: 14px; padding: 14px; }
        .shift-action-btn { width: 100%; height: 38px; justify-content: center; font-size: 12px; }
        
        /* Horizontal Table Data Scrolling Solution */
        .content-block { 
            overflow-x: auto !important; 
            -webkit-overflow-scrolling: touch !important; /* Adds smooth touch acceleration physics on iOS devices */
            padding: 16px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
        }
        table { 
            display: table !important; /* Restores table behavior to keep database cells perfectly straight */
            width: 100% !important; 
            min-width: 500px !important; /* Stops table columns from crushing on narrow screens */
        }
        th, td { 
            white-space: nowrap !important; /* Prevents values from wrapping onto separate vertical rows */
            padding: 12px 10px !important; 
        }
    }

    /* 📱 MINI SMARTPHONE DISPLAY CONSTRAINTS (Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Drop Metrics Cards down to single fluid rows */
        .metrics-grid { grid-template-columns: 1fr !important; gap: 16px !important; margin-bottom: 24px !important; }
        .metric-card { padding: 16px; }
        .metric-val { font-size: 1.5rem; }
    }
</style>

<link rel="stylesheet" href="../css/panel-polish.css">
<script src="../js/page-progress-dialog.js"></script>
</head>
<body>

    <?php include_once 'navbar.php'; ?>

    <main>
        <a class="staff-back-link" href="staff_dashboard.php" aria-label="Back to Staff Dashboard">&larr; Back to Staff Dashboard</a>
        <h1 class="main-title">Incoming Store Order Ledger</h1>
        
        <?php if (!empty($msg)): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($err)): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div><?php endif; ?>

        <div class="content-block">
            <table>
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Customer Name</th>
                        <th>KRA PIN</th>
                        <th>Net Amount</th>
                        <th>VAT Amount</th>
                        <th>Contract Total</th>
                        <th>Amount Deposited</th>
                        <th>Order Status</th>
                        <th>Modify State</th>
                        <th>View Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders_list) > 0): foreach ($orders_list as $order): 
                        $clean_status = strtolower(trim($order['order_status'] ?? 'pending'));
                        $paid_so_far = floatval($order['total_deposited_so_far']);
                        $total_contract = floatval($order['total_amount']);
                        
                        $is_fully_paid = ($paid_so_far >= $total_contract);

                        $status_class = 'status-pending';
                        if (in_array($clean_status, ['delivered', 'paid', 'processing'])) {
                                                   $status_class = 'status-' . $clean_status;
                        }
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: #2563eb;">#<?= $order['id']; ?></td>
                        <td style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($order['fullname']); ?></td>
                        <td style="font-family: monospace; text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($order['kra_pin'] ?? 'N/A'); ?></td>
                        <td>KES <?= number_format($order['net_amount'], 2); ?></td>
                        <td>KES <?= number_format($order['vat_amount'], 2); ?></td>
                        <td style="font-weight: 700; color: #4b5563;">KES <?= number_format($total_contract, 2); ?></td>
                        <td style="font-weight: 800; color: #059669;">KES <?= number_format($paid_so_far, 2); ?></td>
                        <td><span class="status-pill <?= $status_class; ?>"><?= htmlspecialchars($order['order_status']); ?></span></td>
                        <td>
                            <?php if ($clean_status === 'delivered'): ?>
                                <span style="font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase;">🔒 Finalized</span>
                            <?php else: ?>
                                <form method="POST" style="display: flex; align-items: center; gap: 4px; margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_orders_csrf']); ?>">
                                    <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
                                    <select name="new_status" class="status-select">
                                        <?php if (!$is_fully_paid): ?>
                                            <option value="pending" selected>Pending Payment</option>
                                        <?php else: ?>
                                            <?php if ($clean_status === 'pending'): ?>
                                                <option value="pending" selected>Pending</option>
                                            <?php endif; ?>
                                            <option value="processing" <?= $clean_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="paid" <?= $clean_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="delivered">Delivered</option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="submit" name="update_order_status" class="action-btn" <?= !$is_fully_paid ? 'disabled style="background-color:#94a3b8; cursor:not-allowed;"' : ''; ?>>Save</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px; align-items: center;">
                                <a href="view_order_items.php?order_id=<?= $order['id']; ?>" class="view-items-link nav-loading-link">Items</a>
                                <a href="print_invoice.php?order_id=<?= $order['id']; ?>" target="_blank" style="color: #059669; text-decoration: none; font-weight: 700; text-transform: uppercase; font-size: 10px; background-color: #d1fae5; padding: 4px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">🧾 Invoice</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #9ca3af; padding: 32px 0; font-weight: 600;">No invoices have been logged in the store database.</td>
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


