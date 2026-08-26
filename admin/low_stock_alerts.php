<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('low_stock_dispatcher.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $low_stock_limit = 5;

// Fetch critically low items along with their specific supplier emails
$sql = "SELECT id, product_name, sku, stock_quantity, supplier_email FROM products WHERE stock_quantity < ? ORDER BY stock_quantity ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $low_stock_limit);
$stmt->execute();
$low_items_result = $stmt->get_result();
$stmt->close(); // Cleanly close statement context resource

// Handle mock dispatch command simulation
if (isset($_POST['simulate_send'])) {
    $p_id = intval($_POST['p_id']);
    $p_name = htmlspecialchars($_POST['p_name']);
    $p_sku = htmlspecialchars($_POST['p_sku']);
    $supplier = htmlspecialchars($_POST['supplier_email']);
    
    // Log the automated procurement alert into the staff audit logs
    $log_details = "Stock replenishment order dispatch spooled for '{$p_name}' (SKU: {$p_sku}) to supplier: {$supplier}.";
    $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Inventory Update', ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $msg = "✓ Automated Alert Queue Triggered: Replenishment dispatch ticket generated successfully for <strong>{$p_name}</strong> and spooled into outbox buffer logs.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>Low Stock Email Dispatcher - ADONAK</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; display: flex; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #2c3e50; color: white; padding: 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #34495e; padding-bottom: 10px; color: #ecf0f1; margin-top: 0; }
    .sidebar a { display: block; color: #bdc3c7; padding: 12px; text-decoration: none; font-size: 14px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; border-radius: 4px; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 30px; min-height: 100vh; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; box-sizing: border-box; }
    h2 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #f4f6f9; padding-bottom: 10px; font-size: 1.5rem; font-weight: 700; }
    
    /* 4. Professional Email Spooler Template Styling */
    .email-preview-box { background: #ffffff; border: 1px solid #dde1e5; border-radius: 6px; margin-top: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; box-sizing: border-box; width: 100%; }
    .email-header { background: #f1f3f5; padding: 15px; border-bottom: 1px solid #dde1e5; font-size: 13px; color: #495057; font-weight: 600; }
    .email-body { padding: 25px; font-size: 14px; line-height: 1.6; color: #333; background: #fff; box-sizing: border-box; }
    .email-footer { background: #f8f9fa; padding: 12px; border-top: 1px dashed #e2e8f0; font-size: 11px; text-align: center; color: #868e96; font-weight: 500; }
    
    /* Queue Transmission Trigger Button */
    .btn-dispatch { background: #e67e22; color: white; border: none; padding: 6px 14px; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 12px; text-transform: uppercase; letter-spacing: 0.02em; height: 32px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; transition: background-color 0.2s; white-space: nowrap; }
    .btn-dispatch:hover { background: #d35400; }
    
    /* Spool Alert Banner */
    .alert { background: #e6fcf5; color: #0ca678; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: 500; border: 1px solid #c3fae8; box-sizing: border-box; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 TRANSITIONAL TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #34495e; }
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu options */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Card boundary adjustments */
        .main-content { padding: 12px; }
        .card { padding: 16px; margin-bottom: 16px; }
        h2 { font-size: 1.25rem; padding-bottom: 8px; margin-bottom: 16px; }
        
        /* Adjust email preview templates for narrower widths */
        .email-header { padding: 12px; font-size: 12px; word-break: break-all; }
        .email-body { padding: 16px; font-size: 13px; }
        .email-footer { padding: 10px; font-size: 10px; }
        
        /* Maximize target boundaries for thumb tap operations */
        .btn-dispatch { width: 100%; height: 42px; font-size: 13px; margin-top: 4px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="low_stock_monitor.php">⚠️ Warehouse Stock Monitor</a>
        <a href="low_stock_alerts.php" class="active">📨 Low Stock Mail Spooler</a>
        <a href="staff_tracker.php">🛡️ Workspace Tracker</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="../logout.php" style="background:#c0392b; color:white; text-align:center; margin-top:30px; display:block; padding:8px; border-radius:4px; text-decoration:none;">Logout</a>
    </div>

    <div class="main-content">
        <?php if (!empty($msg)): ?><div class="alert"><?php echo $msg; ?></div><?php endif; ?>

        <div class="card">
            <h2>📨 Automated Supplier Purchase Request Generator</h2>
            <p style="color:#666; font-size:14px; margin-top:0; margin-bottom:25px;">Review depleted warehouse products and auto-compile procurement email notification templates matching your vendor registries [INDEX].</p>

            <?php if ($low_items_result && $low_items_result->num_rows > 0): ?>
                <?php while($row = $low_items_result->fetch_assoc()): 
                    $qty = intval($row['stock_quantity']);
                    $reorder_target_volume = 25 - $qty; // Computes the ideal volume needed to restore baseline safety
                ?>
                    <!-- Individual Spooled Email Container Block -->
                    <div class="email-preview-box">
                        <div class="email-header">
                            <strong>To:</strong> <code><?php echo htmlspecialchars($row['supplier_email']); ?></code> &nbsp;|&nbsp; 
                            <strong>Subject:</strong> Urgent Inventory Supply Request: <?php echo htmlspecialchars($row['product_name']); ?> (SKU: <?php echo $row['sku']; ?>) &nbsp;|&nbsp;
                            <strong>Alert Status:</strong> <span style="color:#c92a2a; font-weight:bold;">CRITICAL RUNNING LOW (<?php echo $qty; ?> Units Left)</span>
                        </div>
                        
                        <div class="email-body">
                            <p>Dear Supply Fulfillment Team,</p>
                            <p>This is an automated purchase requisition notification from the Inventory Management Engine at <strong>ADONAK ELECTRONICS LTD</strong>.</p>
                            <p>Our real-time analytics warehouse dashboard indicates that our stock threshold for the following model has dropped below our critical safety parameters [INDEX]:</p>
                            
                            <div class="table-scroll-container">
							<table style="width: 100%; border-collapse: collapse;">
                                <tr style="background:#eee;">
                                    <th style="padding:8px; font-size:12px;">Product Model Name</th>
                                    <th style="padding:8px; font-size:12px;">SKU Serial Code</th>
                                    <th style="padding:8px; font-size:12px;">Current Warehouse Balance</th>
                                    <th style="padding:8px; font-size:12px; text-align:right;">Urgent Reorder Volume</th>
                                </tr>
                                <tr>
                                    <td style="padding:8px; font-weight:bold;"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td style="padding:8px;"><code><?php echo htmlspecialchars($row['sku']); ?></code></td>
                                    <td style="padding:8px; color:#c92a2a; font-weight:bold;"><?php echo $qty; ?> pieces left</td>
                                    <td style="padding:8px; color:#0ca678; font-weight:bold; text-align:right;">+<?php echo $reorder_target_volume; ?> Units</td>
                                </tr>
                            </table>
							</div>
                            <p>Please log this transaction invoice order under our corporate dealer profile contract, package the items for dispatch, and issue our logistics agent the corresponding delivery tracking code tracking number immediately.</p>
                            <p>Thank you for your swift turnaround cooperation.</p>
                            <p>Best Regards,<br><strong>Procurement Desk Overview</strong><br>ADONAK ELECTRONICS LTD — Kenya</p>
                        </div>
                        
                        <div class="email-header" style="border-top:1px solid #dde1e5; border-bottom:none; display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#777; font-size:12px;">💡 Tip: You can copy this formatted text block or push it straight to your server mail stack logs.</span>
                            <form method="POST" action="low_stock_alerts.php">
                                <input type="hidden" name="p_name" value="<?php echo htmlspecialchars($row['product_name']); ?>">
                                <button type="submit" name="simulate_send" class="btn-dispatch">🚀 Queue Mock Dispatch Outbox</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="background:#e6fcf5; color:#0ca678; padding:20px; border-radius:6px; text-align:center; font-weight:bold;">✨ Excellent: All systems sitting securely. No procurement notifications spooled because all electronic models sit safely above our warehouse safety limits.</div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
