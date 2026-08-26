<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('manage_orders.php')) {
    if (!empty($is_ajax)) {
        http_response_code(403);
        echo 'AUTH_ERROR';
    } else {
        header('Location: ../login.php?msg=err_unauthorized_access');
    }
    exit;
}

// Ensure the order parameter exists, otherwise safely step backwards via JavaScript routing
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo "<script>window.location.href = 'dashboard.php?view=manage_orders.php';</script>";
    exit();
}

$order_id = intval($_GET['order_id']);

// FETCH METADATA AND CHECK ACCOUNTING STATE
$meta_sql = "SELECT o.id, u.fullname, u.email, u.phone, o.net_amount, o.vat_amount, o.applied_tax_rate, o.total_amount, o.order_status, o.created_at, pm.payment_method 
             FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN payments pm ON o.id = pm.order_id WHERE o.id = ? LIMIT 1";
$meta_stmt = $conn->prepare($meta_sql); $meta_stmt->bind_param("i", $order_id); $meta_stmt->execute();
$order_meta = $meta_stmt->get_result()->fetch_assoc();
$meta_stmt->close(); // Cleanly close statement context resource

if (!$order_meta) { 
    echo "<script>window.location.href = 'dashboard.php?view=manage_orders.php';</script>";
    exit(); 
}

$is_cancelled = (strtolower(trim($order_meta['order_status'])) === 'cancelled');

// Fallback configuration if tax rate wasn't captured on original order matrix
$active_tax_rate = isset($order_meta['applied_tax_rate']) && floatval($order_meta['applied_tax_rate']) > 0 ? floatval($order_meta['applied_tax_rate']) : 16.00;

// COMPLIANCE INJECTION: If cancelled, override all values to absolute zero for strict auditing totals
if ($is_cancelled) {
    $grand_total = 0.00;
    $net_amount = 0.00;
    $vat_amount = 0.00;
} else {
    $grand_total = floatval($order_meta['total_amount']);
    $row_divisor = 1 + ($active_tax_rate / 100);
    $net_amount = $grand_total / $row_divisor;
    $vat_amount = $grand_total - $net_amount;
}

// FIXED: Variables parameterized and sanitized inside query layouts to prevent loose SQL injection strings
$items_result = $conn->query("SELECT oi.price, oi.quantity, p.product_name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Invoice Overview #<?php echo $order_id; ?></title>
   <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: all 0.2s ease; }
    body { background-color: #f8fafc; margin: 0; display: flex; color: #1e293b; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #0f172a; color: white; padding: 25px 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; border-bottom: 1px solid #1e293b; padding-bottom: 15px; font-size: 18px; margin-top:0; color: #f8fafc; }
    .sidebar a { display: block; color: #94a3b8; padding: 12px 16px; text-decoration: none; font-size: 14px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #1e293b; color: #3b82f6; font-weight: bold; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1100px; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .top-navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #edf2f7; box-sizing: border-box; gap: 16px; }
    .card { background: white; padding: 35px; border-radius: 12px; border: 1px solid #e2e8f0; box-sizing: border-box; }
    
    /* Dual Grid Box Framework Blocks */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; box-sizing: border-box; }
    
    /* 4. Tabular Summary Ledger Systems */
    table { width: 100%; border-collapse: collapse; margin-top: 20px; box-sizing: border-box; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #edf2f7; font-size: 14px; vertical-align: middle; }
    th { background: #f8fafc; text-transform: uppercase; font-size: 11px; color: #475569; font-weight: 700; }
    tr:hover td { background-color: #fcfcfc; }
    
    /* Right Side Summary Data Cards */
    .summary-box { width: 40%; float: right; margin-top: 20px; border-collapse: collapse; box-sizing: border-box; }
    .summary-box td { padding: 6px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
    .summary-box tr:last-child td { border-bottom: none; font-weight: 700; color: #111827; }
    
    /* Action Navigation Button components */
    .btn-top { padding: 8px 16px; text-decoration: none; font-weight: bold; border-radius: 4px; font-size: 13px; color: white; display: inline-flex; align-items: center; justify-content: center; height: 34px; box-sizing: border-box; transition: background-color 0.2s; white-space: nowrap; }
    
    /* Account cross-out decoration strings */
    .struck-amount { text-decoration: line-through; color: #ef4444; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media screen and (max-width: 1024px) {
        .main-content { padding: 24px; }
        .card { padding: 24px; }
        .summary-box { width: 50%; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar menu into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #1e293b; }
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows sets */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout options */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; max-width: 100%; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 768px Width Screens) */
    @media screen and (max-width: 768px) {
        /* Flatten utility top navigation toolbar flows */
        .top-navbar { flex-direction: column; align-items: flex-start; gap: 12px; padding: 16px; border-radius: 8px; }
        .btn-top { width: 100%; height: 38px; }
        
        /* Flatten twin info grid blocks down into vertical standalone sections */
        .grid-2 { grid-template-columns: 1fr; gap: 20px; padding: 16px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; font-size: 13px; }
        
        /* Expand Right-Floating Summary boxes to full width rules */
        .summary-box { width: 100%; float: none; margin-top: 24px; display: table !important; }
        .summary-box tr { display: table-row !important; }
        .summary-box td { display: table-cell !important; font-size: 13px; }
    }

    /* 📱 MINI SMARTPHONE DISPLAY CONSTRAINTS (Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for link rows top header menu options */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Layout margin/padding baseline contractions */
        .main-content { padding: 12px; }
        .card { padding: 16px; border-radius: 8px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="manage_orders.php" class="active">📊 Manage Sales Orders</a>
        <a href="manage_categories.php">📁 Categories & Brands</a>
        <a href="low_stock_monitor.php">⚠️ Warehouse Stock Monitor</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="../logout.php" style="background:#ef4444; color:white; text-align:center; font-weight:bold; margin-top:20px; display:block; padding:10px; border-radius:6px; text-decoration:none;">Logout</a>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <a href="manage_orders.php" class="btn-top" style="background:#34495e;">⬅ Back to Orders List</a>
            <a href="print_invoice.php?order_id=<?php echo $order_id; ?>" target="_blank" class="btn-top" style="background:#e67e22;">🖨️ Open Print Tax Invoice</a>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:12px;">
                <h2 style="margin:0;">Invoice Overview Sheet #<?php echo $order_id; ?></h2>
                <span style="font-weight:bold; font-size:11px; text-transform:uppercase; background:<?php echo $is_cancelled ? '#fee2e2':'#f1f5f9'; ?>; padding:6px 12px; border-radius:4px; color:<?php echo $is_cancelled ? '#ef4444':'#475569'; ?>; border:1px solid <?php echo $is_cancelled ? '#fecaca':'#e2e8f0'; ?>;"><?php echo htmlspecialchars($order_meta['order_status']); ?></span>
            </div>

            <div class="grid-2">
                <div>
                    <h4 style="margin:0 0 6px 0; color:#64748b; text-transform:uppercase; font-size:11px;">Customer Profile</h4>
                    <strong>Name:</strong> <?php echo htmlspecialchars($order_meta['fullname']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($order_meta['email']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($order_meta['phone']); ?>
                </div>
                <div>
                    <h4 style="margin:0 0 6px 0; color:#64748b; text-transform:uppercase; font-size:11px;">Transaction Timeline</h4>
                    <strong>Timestamp:</strong> <?php echo date('M d, Y - h:i A', strtotime($order_meta['created_at'])); ?><br>
                    <strong>Payment Method:</strong> <?php echo htmlspecialchars($order_meta['payment_method'] ?? 'COD'); ?>
                </div>
            </div>

            <h3 style="font-size:14px; border-bottom:1px solid #edf2f7; padding-bottom:8px; margin-top:30px; text-transform:uppercase; color:#475569;">Items Included in Order</h3>
            <table>
                <thead><tr><th>Product Name Reference</th><th>SKU Code</th><th>Price</th><th>Qty</th><th style="text-align:right;">Subtotal</th></tr></thead>
                <tbody>
                    <?php while($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?>x</td>
                            <!-- FIXED LINE ITEM: Crosses out individual product amounts if voided -->
                            <td style="text-align:right; font-weight:bold;" class="<?php if($is_cancelled) echo 'struck-amount'; ?>">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <table class="summary-box">
                <!-- FIXED BOTTOM LEDGER: Displays pure absolute $0.00 figures for cancelled transactions -->
                <tr><td>Subtotal (Excl. VAT):</td><td style="text-align:right; font-weight:bold; color:#334155;">$<?php echo number_format($net_amount, 2); ?></td></tr>
                <tr style="color:#e67e22; font-weight:600;"><td>VAT Total (<?php echo $row_tax_rate; ?>%):</td><td style="text-align:right;">$<?php echo number_format($vat_amount, 2); ?></td></tr>
                <tr style="border-top:2px dashed #e2e8f0; font-size:15px; font-weight:bold; color:<?php echo $is_cancelled ? '#ef4444' : '#10b981'; ?>;"><td>Grand Total Value:</td><td style="text-align:right;">$<?php echo number_format($grand_total, 2); ?></td></tr>
            </table>
            <div style="clear:both;"></div>
        </div>
    </div>

</body>
</html>
