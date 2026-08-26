<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../order_payment_guard.php';
require_once __DIR__ . '/../session_auth.php';

if (!verifyExplicitWorkspaceClearance('manage_orders.php')) {
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');
    exit;
}

$order_id = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$order_id) {
    header('Location: manage_orders.php');
    exit;
}

// 1. Fetch Primary Master Order Metadata and purchaser profiles 
$o_stmt = $conn->prepare("SELECT o.*, u.fullname, u.phone, u.email, u.shipping_address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
if (!$o_stmt) { http_response_code(500); exit('Unable to prepare the order document.'); }
$o_stmt->bind_param('i', $order_id);
if (!$o_stmt->execute()) { http_response_code(500); exit('Unable to load the order document.'); }
$order = $o_stmt->get_result()->fetch_assoc();
$o_stmt->close();

if (!$order) { http_response_code(404); exit('The requested order was not found.'); }

// 2. Fetch Item Splits purchased under this specific transaction sequence
$i_stmt = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
if (!$i_stmt) { http_response_code(500); exit('Unable to prepare the order items.'); }
$i_stmt->bind_param('i', $order_id);
if (!$i_stmt->execute()) { http_response_code(500); exit('Unable to load the order items.'); }
$items = $i_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$i_stmt->close();
$settlement = getOrderSettlementState($conn, (int)$order_id, false);
if (!$settlement) { http_response_code(500); exit('Unable to determine the order settlement.'); }
$order_status = strtolower(trim((string)($order['order_status'] ?? 'pending')));
$is_cancelled = $order_status === 'cancelled';
$is_fully_paid = !$is_cancelled && (bool)$settlement['is_fully_paid'];
$document_title = $is_cancelled ? 'Cancelled Order Record' : ($is_fully_paid ? 'Paid Sales Receipt' : 'Provisional Order Invoice');
$document_badge = $is_cancelled ? 'Cancelled / Reversed' : ($is_fully_paid ? 'Payment Verified in Store Records' : 'Payment Outstanding');
$document_color = $is_cancelled ? '#b91c1c' : ($is_fully_paid ? '#047857' : '#b45309');
$tax_rate = max(0, (float)($order['applied_tax_rate'] ?? 0));
$served_by_raw = trim((string)($order['processed_by'] ?? ''));
$served_by = $served_by_raw === '' ? 'Not yet assigned' : ($served_by_raw === 'System Automated Checkout' ? 'Online Checkout (Automated)' : $served_by_raw);
$issued_by = trim((string)($_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff operator'));
$paid_total = max(0, (float)$settlement['paid_total']);
$outstanding_total = max(0, (float)$settlement['outstanding_balance']);
$refunded_total = 0.0;
$refund_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0.00) AS refunded_total FROM payments WHERE order_id = ? AND LOWER(TRIM(payment_status)) = 'refunded'");
if ($refund_stmt) {
    $refund_stmt->bind_param('i', $order_id);
    if ($refund_stmt->execute()) {
        $refunded_total = max(0, (float)($refund_stmt->get_result()->fetch_assoc()['refunded_total'] ?? 0));
    }
    $refund_stmt->close();

}
$payment_stmt = $conn->prepare('SELECT payment_method, transaction_code, amount, payment_status, created_at FROM payments WHERE order_id = ? ORDER BY id ASC');
if (!$payment_stmt) { http_response_code(500); exit('Unable to prepare payment history.'); }
$payment_stmt->bind_param('i', $order_id);
if (!$payment_stmt->execute()) { http_response_code(500); exit('Unable to load payment history.'); }
$payments = $payment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$payment_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($document_title); ?> - ADONAK ELECTRONICS - #<?= (int)$order_id; ?></title>
   <style>
    /* 1. Global Baseline Reset Styles */
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 20px 40px 40px; color: #1f2937; background-color: #ffffff; }
    
    /* 2. Interactive Action Utility Control Header Styles */
    .no-print-bar { display: flex; justify-content: space-between; align-items: center; background-color: #111827; padding: 12px 24px; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .no-print-bar div { display: flex; gap: 12px; align-items: center; }
    .nav-rt-btn { color: #d1d5db; text-decoration: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 8px 14px; border-radius: 4px; border: 1px solid #374151; transition: background-color 0.2s, color 0.2s; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; height: 32px; box-sizing: border-box; }
    .nav-rt-btn:hover { color: white; background-color: #1f2937; }
    .print-trigger-btn { background-color: #f97316; border: none; color: white; cursor: pointer; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 8px 16px; border-radius: 4px; transition: background-color 0.2s; height: 32px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
    .print-trigger-btn:hover { background-color: #ea580c; }

    /* 3. Core Invoice Document Container (Default Desktop View) */
    .invoice-box { max-width: 800px; margin: 0 auto; border: 1px solid #e5e7eb; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); box-sizing: border-box; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
    .header-table td { vertical-align: top; border: none; padding: 0; }
    .company-title { font-size: 1.75rem; font-weight: 900; color: #111827; margin: 0; letter-spacing: -0.025em; }
    .tax-header { font-size: 0.75rem; font-weight: 800; background-color: #f97316; color: white; padding: 4px 10px; border-radius: 4px; display: inline-block; text-transform: uppercase; margin-top: 6px; white-space: nowrap; }
    .doc-title { font-size: 1.5rem; font-weight: 800; text-align: right; color: #374151; text-transform: uppercase; margin: 0; }
    .invoice-meta { text-align: right; font-size: 0.813rem; color: #4b5563; font-weight: 600; line-height: 1.5; margin-top: 8px; }
    
    /* Billing Parties Grid Framework */
    .client-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 40px; margin-bottom: 40px; font-size: 0.813rem; line-height: 1.5; }
    .info-title { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #9ca3af; margin-bottom: 4px; letter-spacing: 0.05em; }
    .info-val { font-weight: 600; color: #111827; }
    .kra-badge { display: inline-block; font-family: monospace; font-size: 11px; font-weight: 700; color: #b91c1c; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px; border: 1px solid #fca5a5; text-transform: uppercase; white-space: nowrap; }

    /* 4. Tabular Ledger Item Breakdowns */
    table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 32px; font-size: 0.813rem; }
    table.items-table th { background-color: #111827; color: white; font-weight: 700; text-transform: uppercase; font-size: 10px; padding: 10px 12px; }
    table.items-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; }
    table.items-table tr:nth-child(even) td { background-color: #f9fafb; }
    
    /* Pricing Aggregations Summary Column Group */
    .summary-wrapper { display: flex; flex-direction: column; align-items: flex-end; font-size: 0.813rem; font-weight: 600; color: #4b5563; margin-top: 24px; box-sizing: border-box; }
    .summary-row { display: flex; justify-content: space-between; width: 280px; padding: 6px 0; border-bottom: 1px solid #f3f4f6; gap: 16px; box-sizing: border-box; }
    .summary-row.grand-total { border-top: 2px solid #e5e7eb; border-bottom: 2px double #111827; padding-top: 10px; margin-top: 4px; font-size: 1rem; color: #111827; font-weight: 800; }
    
    .footer-note { margin-top: 64px; text-align: center; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1.5; border-top: 1px dashed #e5e7eb; padding-top: 24px; }
    .text-right { text-align: right; }
    .font-bold { font-weight: 700; }

    /* ==========================================================================
       5. RESPONSIVE SCREEN QUERIES (MOBILE PHONE VIEWPORT OPTIMIZATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 768px Width Screens) */
    @media screen and (max-width: 768px) {
        /* Restructure Navbar row elements into stacked vertical flow */
        body { padding: 12px; }
        .no-print-bar { flex-direction: column; gap: 14px; padding: 14px 16px; text-align: center; }
        .no-print-bar div { width: 100%; justify-content: center; flex-wrap: wrap; gap: 8px; }
        .nav-rt-btn { width: 100%; height: 38px; font-size: 0.8rem; }
        .print-trigger-btn { width: 100%; height: 38px; font-size: 0.8rem; }
        
        /* Contract parent layout boundaries to adapt to glass borders safely */
        .invoice-box { padding: 20px 14px; border-radius: 0.5rem; }
        
        /* Flatten traditional top corporate tables */
        .header-table, .header-table tbody, .header-table tr, .header-table td { display: block; width: 100%; }
        .header-table td { text-align: left !important; margin-bottom: 16px; }
        .doc-title { text-align: left; font-size: 1.3rem; }
        .invoice-meta { text-align: left; margin-top: 6px; }
        
        /* Flatten billing grids to standalone rows */
        .client-info-grid { grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        table.items-table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table.items-table th, table.items-table td { white-space: nowrap; padding: 10px; }
        
        /* Expand Summary Totals panels to utilize 100% device width rules */
        .summary-wrapper { align-items: stretch; margin-top: 16px; }
        .summary-row { width: 100%; font-size: 0.8rem; }
        .summary-row.grand-total { font-size: 0.95rem; }
        
        .footer-note { margin-top: 40px; padding-top: 16px; font-size: 10px; }
    }

    /* ==========================================================================
       6. PRINT MEDIA ATTRIBUTE OVERRIDES (A4 PAPER PRINTS AND SAVE-AS-PDF)
       ========================================================================== */
    @media print {
        body { padding: 0 !important; margin: 0 !important; background-color: #ffffff !important; color: #000000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .no-print { display: none !important; }
        .no-print-bar { display: none !important; }
        .invoice-box { border: none !important; padding: 0 !important; box-shadow: none !important; max-width: 100% !important; margin: 0 !important; }
        
        /* Enforce structural rules so pricing rows don't separate across physical pages */
        table.items-table tr { page-break-inside: avoid; break-inside: avoid; }
        .summary-wrapper { page-break-inside: avoid; break-inside: avoid; }
        
        /* Ensure table columns stay bounded on white paper sheets */
        .header-table { display: table !important; width: 100% !important; }
        .header-table tr { display: table-row !important; }
        .header-table td { display: table-cell !important; }
        .header-table td:last-child { text-align: right !important; }
        .doc-title { text-align: right !important; }
        .invoice-meta { text-align: right !important; }
        
        .client-info-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 40px !important; }
        table.items-table { display: table !important; width: 100% !important; }
        table.items-table th, table.items-table td { display: table-cell !important; white-space: normal !important; }
        .summary-wrapper { display: flex !important; align-items: flex-end !important; }
        .summary-row { width: 280px !important; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>

    <!-- Invisible to printers: Provides return paths and on-demand print firing commands -->
    <div class="no-print-bar no-print" style="max-width: 800px; margin: 0 auto 24px;">
        <div>
            <a href="staff_dashboard.php" class="nav-rt-btn">&#9632; Staff Dashboard</a>
            <a href="manage_orders.php" class="nav-rt-btn">&#128230; Order Ledger</a>
        </div>
        <button type="button" onclick="window.print();" class="print-trigger-btn">&#128424; Print Document</button>
    </div>

    <div class="invoice-box">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="company-title">&#9889; ADONAK ELECTRONICS</h1>
                    <div class="tax-header" style="background-color:<?= $document_color; ?>;"><?= htmlspecialchars($document_badge); ?></div>
                </td>
                <td>
                    <h2 class="doc-title"><?= htmlspecialchars($document_title); ?></h2>
                    <div class="invoice-meta">
                        Order ID: <strong style="color: #2563eb;">#<?= (int)$order['id']; ?></strong><br>
                        Issue Date: <?= date('d M Y, H:i', strtotime($order['created_at'])); ?><br>
                        Order Status: <span style="text-transform: uppercase; color: <?= $document_color; ?>; font-weight: 700;"><?= htmlspecialchars($order_status); ?></span><br>
                        Served By: <strong style="color: #4b5563; text-transform: uppercase;"><?= htmlspecialchars($served_by); ?></strong><br>
                        Document Issued By: <strong style="color: #4b5563; text-transform: uppercase;"><?= htmlspecialchars($issued_by); ?></strong>

                    </div>
                </td>
            </tr>
        </table>

        <div class="client-info-grid">
            <div>
                <div class="info-title">Supplier Authorization</div>
                <div class="info-val" style="text-transform: uppercase; font-weight: 800;">Adonak Hub Operations</div>
                <div>Eldoret - Chepkoilel Complex Road, Kenya</div>
                <div>PIN Reference: <strong>P051239850W</strong></div>
            </div>
            <div style="text-align: right;">
                <div class="info-title">Purchaser / Bill To</div>
                <div class="info-val" style="text-transform: uppercase; font-weight: 800;"><?= htmlspecialchars($order['fullname']); ?></div>
                <div>Contact Phone: <?= htmlspecialchars($order['phone']); ?></div>
                <div>Email Profile: <?= htmlspecialchars($order['email']); ?></div>
                <div style="margin-top: 4px;">KRA PIN Identification: <span class="kra-badge"><?= htmlspecialchars($order['kra_pin'] ?? 'N/A'); ?></span></div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Product Mapped Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Net Cost</th>
                    <th class="text-right">Unit VAT (<?= number_format($tax_rate, 2); ?>%)</th>
                    <th class="text-right">Gross Total Valuation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $qty = (int)$item['quantity'];
                ?>
                <tr>
                    <td class="font-bold" style="text-transform: uppercase;"><?= htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-right"><?= $qty; ?></td>
                    <td class="text-right">KES <?= number_format($item['net_price'], 2); ?></td>
                    <td class="text-right">KES <?= number_format($item['vat_price'], 2); ?></td>
                    <td class="text-right font-bold" style="color: #059669;">KES <?= number_format($item['price'] * $qty, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="info-title" style="margin-top:24px;">Payment History (as recorded)</div>
        <table class="items-table">
            <thead>
                <tr><th>Date</th><th>Payment Method</th><th>Reference</th><th class="text-right">Amount</th><th class="text-right">Stored Status</th></tr>
            </thead>
            <tbody>
                <?php if ($payments): ?>
                    <?php foreach ($payments as $payment):
                        $payment_status = strtolower(trim((string)$payment['payment_status']));
                        $status_color = $payment_status === 'completed' ? '#047857' : ($payment_status === 'refunded' ? '#6d28d9' : ($payment_status === 'failed' ? '#b91c1c' : '#b45309'));
                        $payment_reference = trim((string)$payment['transaction_code']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($payment['created_at']))); ?></td>
                        <td><?= htmlspecialchars($payment['payment_method'] ?: 'Not recorded'); ?></td>
                        <td><?= htmlspecialchars($payment_reference !== '' && $payment_reference !== '0' ? $payment_reference : 'Not recorded'); ?></td>
                        <td class="text-right font-bold">KES <?= number_format((float)$payment['amount'], 2); ?></td>
                        <td class="text-right font-bold" style="color:<?= $status_color; ?>;"><?= htmlspecialchars(ucfirst($payment_status ?: 'unknown')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No payment entries have been recorded for this order.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-wrapper">
            <div class="summary-row">
                <span>Net Total Excl. Tax Value:</span>
                <strong>KES <?= number_format($order['net_amount'], 2); ?></strong>
            </div>
            <div class="summary-row">
                <span>Total VAT (<?= number_format($tax_rate, 2); ?>%):</span>
                <strong style="color: #b91c1c;">KES <?= number_format($order['vat_amount'], 2); ?></strong>
            </div>
                      <div class="summary-row grand-total">
                <span>Order Total:</span>
                <span>KES <?= number_format($order['total_amount'], 2); ?></span>
            </div>
            <?php if ($is_cancelled): ?>
                <div class="summary-row"><span>Refunded Amount:</span><strong style="color:#6d28d9;">KES <?= number_format($refunded_total, 2); ?></strong></div>
            <?php else: ?>
                <div class="summary-row"><span>Verified Completed Payments:</span><strong style="color:#047857;">KES <?= number_format($paid_total, 2); ?></strong></div>
                <div class="summary-row"><span>Outstanding Balance:</span><strong style="color:<?= $outstanding_total > 0.009 ? '#b91c1c' : '#047857'; ?>;">KES <?= number_format($outstanding_total, 2); ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="footer-note">
            <?= $is_cancelled ? 'This order was cancelled and eligible completed payments were reversed.' : ($is_fully_paid ? 'Thank you for your business with ADONAK ELECTRONICS.' : 'This provisional invoice is not proof of full payment.'); ?><br>
            Generated from ADONAK store records. It does not claim independent KRA or payment-network verification.<br>
            Served by <?= htmlspecialchars($served_by); ?>; document issued by <?= htmlspecialchars($issued_by); ?>.
        </div>
    </div>
</body>
</html>


