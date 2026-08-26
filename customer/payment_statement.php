<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../register.php"); exit();
}
$user_id = intval($_SESSION['user_id']);

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: my_orders.php"); exit();
}
$order_id = intval($_GET['order_id']);

// 1. FETCH BASELINE TAX ORDER METADATA AND CANCELLED STATUS TIMELINES
$order_sql = "SELECT o.id, o.net_amount, o.vat_amount, o.applied_tax_rate, o.total_amount, o.order_status, o.created_at, o.kra_pin 
             FROM orders o WHERE o.id = ? AND o.user_id = ? LIMIT 1";
$o_stmt = $conn->prepare($order_sql); $o_stmt->bind_param("ii", $order_id, $user_id); $o_stmt->execute();
$order_meta = $o_stmt->get_result()->fetch_assoc();

if (!$order_meta) { header("Location: my_orders.php"); exit(); }

$is_cancelled = (strtolower(trim($order_meta['order_status'])) === 'cancelled');
$row_tax_rate = floatval($order_meta['applied_tax_rate']);

// 2. FETCH EVERY ASSOCIATED TRANSACTION ALLOCATION TIED TO THIS INVOICE [INDEX]
$pay_sql = "SELECT amount, payment_method, transaction_code, payment_status, created_at FROM payments WHERE order_id = ? ORDER BY id ASC";
$p_stmt = $conn->prepare($pay_sql); $p_stmt->bind_param("i", $order_id); $p_stmt->execute();
$payments_result = $p_stmt->get_result();

$cart_count = 0;
$count_res = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $user_id");
if ($count_res) { $cart_count = intval($count_res->fetch_assoc()['total']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
	<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Payment Statement Ledger</title>
    <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: sans-serif; transition: all 0.2s ease; }
    body { background-color: #f8fafc; margin: 0; padding: 0; color: #1e293b; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    .navbar { display: flex; justify-content: space-between; align-items: center; background-color: #2c3e50; color: white; padding: 15px 5%; box-sizing: border-box; }
    .navbar h1 { margin: 0; font-size: 20px; color: #fff; white-space: nowrap; }
    .navbar a { color: #bdc3c7; text-decoration: none; margin-left: 20px; font-size: 14px; white-space: nowrap; }
    .navbar a:hover { color: #fff; }
    
    /* 3. Core Structural Containers (Default Desktop View) */
    .container { max-width: 700px; margin: 40px auto; padding: 0 20px; box-sizing: border-box; width: 100%; }
    
    /* Premium Statement Voucher Box Styling */
    .voucher-card { background: white; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.01); box-sizing: border-box; }
    .voucher-header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; gap: 16px; align-items: flex-start; }
    
    /* 4. Chronological Step Track Timeline Grid Loops */
    .timeline-track { position: relative; padding-left: 25px; margin: 20px 0; }
    .timeline-track::before { content: ''; position: absolute; left: 6px; top: 5px; bottom: 5px; width: 2px; background: #e2e8f0; }
    .timeline-node { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; position: relative; gap: 16px; box-sizing: border-box; }
    .timeline-node::before { content: ''; position: absolute; left: -22px; top: 20px; width: 10px; height: 10px; border-radius: 50%; background: #3b82f6; border: 2px solid white; transform: translateY(-50%); }
    
    /* Layout Component Elements & Badges */
    .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; white-space: nowrap; display: inline-block; }
    .badge-wallet { background: #e0f2fe; color: #0369a1; } 
    .badge-cash { background: #f1f5f9; color: #475569; }
    .val-cash { font-size: 15px; font-weight: 700; color: #10b981; white-space: nowrap; }
    
    /* Audit Overlay void stamps for cancelled invoices */
    .void-watermark { position: absolute; top: 35%; left: 10%; right: 10%; border: 4px solid #ef4444; color: #ef4444; font-size: 40px; font-weight: 900; text-transform: uppercase; text-align: center; padding: 15px; transform: rotate(-12deg); opacity: 0.15; pointer-events: none; border-radius: 8px; box-sizing: border-box; }
    .struck { text-decoration: line-through; color: #94a3b8 !important; }
    
    /* Ledger Breakdown Boxes */
    .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-top: 25px; box-sizing: border-box; }
    .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #475569; gap: 16px; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 768px Width Screens) */
    @media (max-width: 768px) {
        /* Stack Navbar components vertically */
        .navbar { flex-direction: column; gap: 12px; padding: 15px; text-align: center; }
        .navbar a { margin: 0 10px; font-size: 13px; }
        
        /* Reduce page margins on smaller devices */
        .container { margin: 20px auto; padding: 0 12px; }
        
        /* Drop card padding boundaries down slightly */
        .voucher-card { padding: 24px 16px; border-radius: 8px; }
        
        /* Stack Voucher Parent Details fields vertically */
        .voucher-header { flex-direction: column; gap: 12px; align-items: flex-start; }
        
        /* Structural adjustments for Timeline row node blocks */
        .timeline-node { flex-direction: column; align-items: flex-start; gap: 10px; padding: 12px; }
        
        /* Handle side alignment structures within stacked viewports */
        .timeline-node > div:last-child { width: 100%; display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 8px; margin-top: 2px; }
        
        /* Scale down the watermark font to ensure it remains bounded */
        .void-watermark { font-size: 28px; top: 40%; left: 5%; right: 5%; padding: 10px; }
        
        /* Adjust details listing element rows */
        .summary-box { padding: 14px; }
        .summary-row { font-size: 12px; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>

    <div class="navbar">
        <h1>⚡ ADONAK ELECTRONICS</h1>
        <div><a href="home.php">Shop Catalog</a><a href="my_orders.php" style="color:white; font-weight:bold;">Track Orders</a></div>
    </div>

    <div class="container">
        <div class="voucher-card">
            <!-- Intercepts cancelled status to project the background watermark stamp [INDEX] -->
            <?php if ($is_cancelled): ?><div class="void-watermark">Voided & Reversed</div><?php endif; ?>

            <div class="voucher-header">
                <div><h3 style="margin:0; font-size:18px; color:#0f172a;">🧾 Account Statement Voucher</h3><small style="color:#64748b;">Official eTIMS Financial Copy</small></div>
                <div style="text-align:right; font-size:13px; color:#475569;"><strong>Order Reference:</strong> #<?php echo $order_id; ?><br><strong>Dated:</strong> <?php echo date('d M Y', strtotime($order_meta['created_at'])); ?></div>
            </div>

            <div style="font-size:13px; color:#64748b; margin-bottom:15px;"><strong>Customer KRA PIN:</strong> <?php echo !empty($order_meta['kra_pin']) ? htmlspecialchars($order_meta['kra_pin']) : 'Not Specified'; ?></div>

            <h4 style="margin:0 0 10px 0; font-size:12px; text-transform:uppercase; color:#475569; letter-spacing:0.3px;">Chronological Allocation Timeline</h4>
            <div class="timeline-track">
                <?php 
                $seq = 1; $total_settled = 0;
                if ($payments_result && $payments_result->num_rows > 0):
                    while ($pay = $payments_result->fetch_assoc()): 
                        $total_settled += floatval($pay['amount']);
                        $method = strtolower(trim($pay['payment_method']));
                        $is_wallet = (strpos($method, 'credit') !== false || strpos($method, 'wallet') !== false);
                ?>
                        <div class="timeline-node">
                            <div>
                                <div style="font-weight:bold; font-size:14px;"><span class="badge <?php echo $is_wallet ? 'badge-wallet':'badge-cash'; ?>"><?php echo htmlspecialchars($pay['payment_method']); ?></span> Entry Step #<?php echo $seq++; ?></div>
                                <small style="color:#64748b;">Ref Code: <code><?php echo htmlspecialchars($pay['transaction_code']); ?></code> | <?php echo date('h:i A', strtotime($pay['created_at'])); ?></small>
                            </div>
                            <div class="val-cash <?php if($is_cancelled) echo 'struck'; ?>">+$<?php echo number_format($pay['amount'], 2); ?></div>
                        </div>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <p style="padding:10px 0; color:#94a3b8; font-size:13px;">No ledger deposit records tracked for this invoice reference code yet.</p>
                <?php endif; ?>
            </div>

            <div class="summary-box">
                <div class="summary-row"><span>Net Valuation Cost (Excl. VAT):</span><strong class="<?php if($is_cancelled) echo 'struck'; ?>">$<?php echo number_format($is_cancelled ? floatval($order_meta['net_amount']) : (floatval($order_meta['total_amount']) / (1 + ($row_tax_rate/100))), 2); ?></strong></div>
                <div class="summary-row" style="color:#e67e22;"><span>Accrued KRA VAT (<?php echo $row_tax_rate; ?>%):</span><strong class="<?php if($is_cancelled) echo 'struck'; ?>">$<?php echo number_format($is_cancelled ? floatval($order_meta['vat_amount']) : (floatval($order_meta['total_amount']) - (floatval($order_meta['total_amount']) / (1 + ($row_tax_rate/100)))), 2); ?></strong></div>
                <div class="summary-row" style="border-top:1px dashed #cbd5e0; padding-top:8px; margin-top:6px; font-size:15px; font-weight:bold; color:<?php echo $is_cancelled ? '#ef4444':'#10b981'; ?>;">
                    <span>Total Settled Book Balance:</span>
                    <span>$<?php echo number_format($is_cancelled ? 0.00 : $total_settled, 2); ?></span>
                </div>
            </div>

            <div style="text-align:center; margin-top:30px;">
                <a href="my_orders.php" style="color:#3b82f6; font-size:13px; font-weight:bold; text-decoration:none;">⬅ Back to Your Purchase Ledger</a>
            </div>
        </div>
    </div>

</body>
</html>
