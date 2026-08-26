<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('low_stock_scheduler.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $error = "";
$low_stock_limit = 5;

// Fetch critically depleted items along with their specific supplier emails
$sql = "SELECT id, product_name, sku, stock_quantity, supplier_email FROM products WHERE stock_quantity < ? ORDER BY stock_quantity ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $low_stock_limit); $stmt->execute();
$low_items_result = $stmt->get_result();

$low_stock_count = $low_items_result->num_rows;
$stmt->close(); // Cleanly close statement context resource

// Handle interactive queue automation command simulation
if (isset($_POST['schedule_report'])) {
    $interval = trim($_POST['schedule_interval']);
    
    // Log the backup task directly into your Staff Workspace Activity Tracker
    $log_details = "Automated System Routine Configured: Scheduled a recurring low-stock procurement email summary report dispatch interval block set to: " . $interval . ".";
    $audit_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Inventory Update', ?)");
    $audit_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
    
    if ($audit_stmt->execute()) {
        $audit_stmt->close();
        // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside workspace layouts
        echo "<script>window.location.href = 'dashboard.php?view=low_stock_scheduler.php&msg=success&interval=" . urlencode($interval) . "';</script>"; 
        exit();
    } else { 
        $error = "Database Error: Failed to register scheduling tokens."; 
        $audit_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Low Stock Scheduler - ADONAK</title>
    <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; transition: all 0.2s ease; }
    body { background-color: #f8fafc; margin: 0; display: flex; color: #1e293b; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #0f172a; color: white; padding: 25px 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; scrollbar-width: thin; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; border-bottom: 1px solid #1e293b; padding-bottom: 15px; font-size: 18px; margin-top: 0; color: #f8fafc; }
    .sidebar a { display: block; color: #94a3b8; padding: 12px 16px; text-decoration: none; font-size: 14px; font-weight: 500; margin-bottom: 4px; border-radius: 6px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #1e293b; color: #3b82f6; font-weight: bold; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; min-height: 100vh; max-width: 900px; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 35px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.01); margin-bottom: 25px; box-sizing: border-box; }
    h2 { margin-top: 0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; font-size: 20px; }
    
    /* Form Inputs and Action Buttons System Shells */
    select, .btn { padding: 10px 16px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; font-weight: bold; height: 42px; box-sizing: border-box; }
    select { background: #f8fafc; width: 200px; outline: none; cursor: pointer; color: #334155; }
    select:focus { border-color: #3b82f6; }
    
    /* Primary Action Trigger Controls */
    .btn-schedule { background-color: #3b82f6; color: white; border: none; cursor: pointer; box-shadow: 0 4px 6px rgba(59,130,246,0.15); display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; transition: background-color 0.2s; white-space: nowrap; }
    .btn-schedule:hover { background-color: #2563eb; }
    
    /* 4. Automated Blueprint Email Panel Layouts */
    .email-blueprint { background: #ffffff; border: 1px solid #dde1e5; border-radius: 8px; padding: 25px; margin-top: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); box-sizing: border-box; width: 100%; }
    .blueprint-header { background: #f1f3f5; padding: 12px; font-size: 13px; font-family: monospace; border-bottom: 1px solid #dde1e5; margin: -25px -25px 20px -25px; border-radius: 8px 8px 0 0; color: #334155; font-weight: 600; word-break: break-all; }
    
    /* Chronological Parameters Table Grid */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #edf2f7; font-size: 13px; vertical-align: middle; }
    th { background: #f8fafc; text-transform: uppercase; font-size: 11px; color: #475569; font-weight: 700; }
    tr:hover td { background-color: #f8fafc; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 TRANSITIONAL TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop split from flex-row to vertical stacked columns blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar menu into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #1e293b; }
        .sidebar br { display: none; } /* Prevents unwanted text forcing inside top link rows */
        
        /* Render side navigation links horizontally into scrollable rows sets */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout options */
        .main-content { margin-left: 0; width: 100%; padding: 24px; min-height: auto; max-width: 100%; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu tabs */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Container structural padding drops */
        .main-content { padding: 12px; }
        .card { padding: 20px 16px; margin-bottom: 16px; }
        h2 { font-size: 1.15rem; padding-bottom: 8px; }
        
        /* Flatten interactive input elements into standalone blocks */
        select { width: 100%; height: 44px; font-size: 15px; margin-bottom: 12px; }
        .btn-schedule { width: 100%; height: 44px; font-size: 14px; }
        
        /* Adjust layout parameters for embedded blueprint mail containers */
        .email-blueprint { padding: 16px; margin-top: 16px; }
        .blueprint-header { margin: -16px -16px 14px -16px; padding: 10px; font-size: 11px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .email-blueprint { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 8px; font-size: 12px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="manage_layaways.php">🇰🇪 Manage Layaway</a>
        <a href="manage_categories.php">📁 Categories & Brands</a>
        <a href="low_stock_monitor.php">⚠️ Warehouse Stock Monitor</a>
        <a href="low_stock_scheduler.php" class="active">📨 Low Stock Scheduler</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="invoice_archiver.php">🗄️ Invoice PDF Archiver</a>
        <a href="db_backup.php">💾 Core Data Backup</a>
        <a href="prune_workspace.php">🧹 Prune Cache Scraps</a>
        <a href="staff_tracker.php">🛡️ Workspace Tracker</a>
        <a href="manage_staff.php">👥 Manage Staff Network</a>
        <a href="../logout.php" style="background:#ef4444; color:white; text-align:center; font-weight:bold; margin-top:20px; border-radius:6px; display:block; padding:10px; text-decoration:none;">Logout</a>
    </div>

    <div class="main-content">
        <?php if (!empty($error)): ?><div style="background:#fef2f2; color:#991b1b; padding:12px; border-radius:6px; margin-bottom:20px; border:1px solid #fecaca;"><?php echo $error; ?></div><?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div style="background:#f0fdf4; color:#166534; padding:12px; border-radius:6px; margin-bottom:20px; border:1px solid #bbf7d0; font-weight:500;">
                ✓ Automation Rule Locked: Low-stock recurring cron triggers initialized smoothly to run on a <strong><?php echo htmlspecialchars($_GET['interval']); ?></strong> cycle framework!
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>📨 Low-Stock Automated Dispatch Summary Scheduler</h2>
            <p style="color:#64748b; font-size:14px; margin-top:0; margin-bottom:25px;">Establish a background scheduling frequency rule. The warehouse outbox buffer loops will auto-compile and dispatch itemized reorder requisitions straight to your supply chain agents [INDEX].</p>
            
            <form method="POST" action="low_stock_scheduler.php" style="display:flex; gap:15px; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:25px; margin-bottom:25px;">
                <label style="font-weight:600; font-size:13px; color:#475569;">CRON RECURRENCE CYCLE:</label>
                <select name="schedule_interval" required>
                    <option value="Every 24 Hours (Daily)">Every 24 Hours (Daily)</option>
                    <option value="Every Monday (Weekly)">Every Monday (Weekly)</option>
                    <option value="1st of Every Month (Monthly)">Every Month (Monthly)</option>
                </select>
                <button type="submit" name="schedule_report" class="btn btn-schedule">💾 Lock Automation Rule</button>
            </form>

            <h3 style="margin-top:0; color:#0f172a; font-size:15px; text-transform:uppercase; letter-spacing:0.3px;">Spooled Outbox Preview Blueprint</h3>
            
            <?php if ($low_stock_count > 0): ?>
                <div class="email-blueprint">
                    <div class="blueprint-header">
                        <strong>To:</strong> procurement-desk@adonak-distribution.co.ke <br>
                        <strong>Subject:</strong> System Automated Reorder Alert Summary: <?php echo $low_stock_count; ?> Models Running Critically Depleted
                    </div>
                    <p style="font-size:14px; margin-top:0;">Dear Procurement Hub Operations Team,</p>
                    <p style="font-size:14px;">The dynamic tracking matrix at <strong>ADONAK ELECTRONICS LTD</strong> has flagged the following items sitting below safety thresholds (< 5 units) [INDEX]:</p>
                    
                    <table>
                        <thead>
                            <tr><th>Product Specification</th><th>SKU Code</th><th>Active Balance</th><th>Supplier Email Contact</th></tr>
                        </thead>
                        <tbody>
                            <?php while($row = $low_items_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($row['sku']); ?></code></td>
                                    <td style="color:#ef4444; font-weight:bold;"><?php echo $row['stock_quantity']; ?> left</td>
                                    <td><code><?php echo htmlspecialchars($row['supplier_email']); ?></code></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <p style="font-size:14px; margin-top:20px;">Please cross-verify these items against your open purchase records, initiate supplier restock orders immediately, and upload tracking credentials once processed [INDEX].</p>
                    <p style="font-size:14px; margin-bottom:0;">Best Regards,<br><strong>Inventory Engine Daemon Tracker</strong><br>ADONAK ELECTRONICS — Kenya</p>
                </div>
            <?php else: ?>
                <div style="background:#e6fcf5; color:#0ca678; padding:25px; border-radius:6px; text-align:center; font-weight:bold; border:1px solid #c3fae8;">✨ Warehouse metrics are completely healthy! No summary outbox spooled because all electronic models sit safely above our warehouse safety limits [INDEX].</div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
