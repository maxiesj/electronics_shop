<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('layaway_defaulters.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $error = "";

// 1. COLLECT CASH INSTALLMENT
if (isset($_POST['collect_payment'])) {
    $plan_id = intval($_POST['plan_id']);
    $payment_amount = floatval($_POST['payment_amount']);

    $stmt = $conn->prepare("SELECT order_id, balance_remaining, deposit_paid FROM layaway_plans WHERE id = ? AND status = 'Active'");
    $stmt->bind_param("i", $plan_id); $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close(); // Cleanly close statement context resource

    if ($plan && $payment_amount > 0 && $payment_amount <= $plan['balance_remaining']) {
        $new_balance = round(($plan['balance_remaining'] - $payment_amount), 2);
        $new_deposit_total = round(($plan['deposit_paid'] + $payment_amount), 2);
        $plan_status = ($new_balance <= 0) ? 'Fully Paid' : 'Active';

        $conn->begin_transaction();
        try {
            $up_plan = $conn->prepare("UPDATE layaway_plans SET deposit_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
            $up_plan->bind_param("ddsi", $new_deposit_total, $new_balance, $plan_status, $plan_id);
            $up_plan->execute();
            $up_plan->close();

            $txn_code = "INS_" . time(); $method = "Pole-Pole Cash";
            $ins_pay = $conn->prepare("INSERT INTO payments (order_id, payment_method, transaction_code, amount, payment_status) VALUES (?, ?, ?, ?, 'Completed')");
            $ins_pay->bind_param("issd", $plan['order_id'], $method, $txn_code, $payment_amount);
            $ins_pay->execute();
            $ins_pay->close();

            if ($new_balance <= 0) {
                $up_order = $conn->prepare("UPDATE orders SET order_status = 'Processing' WHERE id = ?");
                $up_order->bind_param("i", $plan['order_id']); $up_order->execute();
                $up_order->close();
            }

            // Log the payment collection action into the staff audit logs
            $log_details = "Installment payment of $" . number_format($payment_amount, 2) . " collected manually for Layaway Plan ID #{$plan_id}. New Balance: ${new_balance}.";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Financial Update', ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $conn->commit();
            // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside workspace layouts
            echo "<script>window.location.href = 'dashboard.php?view=manage_layaways.php&msg=Installment+Recorded';</script>"; 
            exit();
        } catch (Exception $e) { 
            $conn->rollback(); 
            $error = "Ledger error: " . $e->getMessage(); 
        }
    } else { $error = "Invalid transaction parameters."; }
}

$result = $conn->query("SELECT lp.*, u.fullname, u.phone FROM layaway_plans lp JOIN users u ON lp.user_id = u.id ORDER BY lp.status ASC, lp.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>Manage Layaways - ADONAK ELECTRONICS</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
    body { background: #f4f6f9; margin: 0; display: flex; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background: #2c3e50; color: white; padding: 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #34495e; padding-bottom: 10px; color: #ecf0f1; margin-top: 0; }
    .sidebar a { display: block; color: #bdc3c7; padding: 12px; text-decoration: none; font-size: 14px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a.active, .sidebar a:hover { background: #34495e; color: white; border-radius: 4px; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 30px; min-height: 100vh; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; box-sizing: border-box; }
    
    /* 4. Payment Execution Input Systems & Controls */
    .form-pay { display: flex; gap: 8px; align-items: center; box-sizing: border-box; }
    input[type="number"] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; width: 100px; height: 34px; box-sizing: border-box; font-size: 14px; background-color: #fafafa; transition: border-color 0.2s, background-color 0.2s; }
    input[type="number"]:focus { outline: none; border-color: #3498db; background-color: #fff; }
    
    /* Action Trigger Button Component */
    .btn { padding: 6px 16px; background: #2ecc71; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; height: 34px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em; transition: background-color 0.2s; white-space: nowrap; box-sizing: border-box; }
    .btn:hover { background: #27ae60; }
    
    /* 5. Tabular Parameter Ledgers */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eaeaea; vertical-align: middle; }
    th { background: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.02em; }
    tr:hover td { background-color: #fcfcfc; }
    
    /* Contract Allocation Badges */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; white-space: nowrap; }
    .badge-active { background: #ffeaa7; color: #b78a00; } 
    .badge-settled { background: #d4edda; color: #155724; }
    
    /* Operational Notification Popups */
    .alert { padding: 12px 20px; border-radius: 4px; margin-bottom: 15px; background: #d4edda; color: #155724; font-weight: 500; border: 1px solid #c3e6cb; box-sizing: border-box; font-size: 14px; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* ==========================================================================
       6. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 TRANSITIONAL TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar menu into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #34495e; }
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout options */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu tabs */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Card boundary adjustments */
        .main-content { padding: 12px; }
        .card { padding: 16px; margin-bottom: 16px; }
        
        /* Re-route inline layout forms into stacked vertical segments */
        .form-pay { flex-direction: column; align-items: stretch; gap: 10px; width: 100%; }
        input[type="number"] { width: 100%; height: 42px; font-size: 15px; padding: 10px; }
        
        /* Maximize click targets for touch screens */
        .btn { width: 100%; height: 42px; font-size: 13px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; font-size: 13px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="add_product.php">➕ Add New Product</a>
        <a href="manage_categories.php">📁 Manage Categories</a>
        <a href="add_brand.php">🏷️ Add Brand Component</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="manage_layaways.php" class="active">🇰🇪 Manage Layaway (Pole Pole)</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="etims_sync.php">⚡ eTIMS KRA Sync API</a>
        <a href="../logout.php" style="background:#c0392b; color:white; text-align:center; margin-top:30px;">Logout</a>
    </div>

    <div class="main-content">
        <?php if (!empty($msg) || isset($_GET['msg'])): ?><div class="alert">✓ Operation logged successfully.</div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Pole Pole Layaway Accounts</h2>
            <table>
                <thead>
                    <tr><th>Order ID</th><th>Buyer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Collect Payment</th></tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><a href="view_order_items.php?order_id=<?php echo $row['order_id']; ?>" style="color:#3498db; font-weight:bold; text-decoration:none;">#<?php echo $row['order_id']; ?></a></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong><br><small><?php echo htmlspecialchars($row['phone']); ?></small></td>
                                <td>KSH<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>KSH<?php echo number_format($row['deposit_paid'], 2); ?></td>
                                <td style="font-weight:bold; color:<?php echo ($row['balance_remaining'] > 0) ? '#e67e22' : '#2ecc71'; ?>;">KSH<?php echo number_format($row['balance_remaining'], 2); ?></td>
                                <td><span class="badge <?php echo ($row['status'] === 'Active') ? 'badge-active' : 'badge-settled'; ?>"><?php echo $row['status'] == 'Active' ? 'Paying' : 'Settled'; ?></span></td>
                                <td>
                                    <?php if ($row['status'] === 'Active'): ?>
                                        <form method="POST" action="manage_layaways.php" class="form-pay">
                                            <input type="hidden" name="plan_id" value="<?php echo $row['id']; ?>">
                                            <input type="number" name="payment_amount" min="0.01" max="<?php echo $row['balance_remaining']; ?>" step="0.01" placeholder="0.00" required>
                                            <button type="submit" name="collect_payment" class="btn">Collect</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#aaa; font-size:13px;">✓ Complete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
