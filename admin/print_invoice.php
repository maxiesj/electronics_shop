<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../order_payment_guard.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!verifyWorkspaceClearance('manage_orders.php')) {
    if (!empty($is_ajax)) {
        http_response_code(403);
        echo 'AUTH_ERROR';
    } else {
        header('Location: ../login.php?msg=err_unauthorized_access');
    }
    exit;
}

$order_id = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$order_id) {
    header('Location: dashboard.php?view=manage_orders.php');
    exit;
}

$meta_stmt = $conn->prepare("SELECT o.id,o.net_amount,o.vat_amount,o.applied_tax_rate,o.total_amount,o.order_status,o.created_at,o.kra_pin,o.processed_by,u.fullname,u.email,u.phone FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=? LIMIT 1");
if (!$meta_stmt) { http_response_code(500); exit('Unable to prepare the invoice.'); }
$meta_stmt->bind_param('i', $order_id);
if (!$meta_stmt->execute()) { http_response_code(500); exit('Unable to load the invoice.'); }
$order_meta = $meta_stmt->get_result()->fetch_assoc();
$meta_stmt->close();
if (!$order_meta) { header('Location: dashboard.php?view=manage_orders.php'); exit; }

$settlement = getOrderSettlementState($conn, $order_id);
if (!$settlement) { http_response_code(500); exit('Unable to load the order settlement.'); }
$is_cancelled = strtolower(trim((string)$order_meta['order_status'])) === 'cancelled';
$is_fully_paid = !$is_cancelled && !empty($settlement['is_fully_paid']);
$completed_paid = (float)$settlement['paid_total'];
$outstanding_balance = $is_cancelled ? 0.00 : (float)$settlement['outstanding_balance'];
$row_tax_rate = (float)$order_meta['applied_tax_rate'];
$grand_total = (float)$order_meta['total_amount'];
$net_amount = (float)$order_meta['net_amount'];
$vat_amount = (float)$order_meta['vat_amount'];

if ($is_cancelled) {
    $document_title = 'Cancelled Order Record';
    $document_badge = 'Cancelled / Reversed';
    $document_color = '#6d28d9';
} elseif ($is_fully_paid) {
    $document_title = 'Paid Sales Receipt';
    $document_badge = 'Fully Paid in Store Records';
    $document_color = '#047857';
} else {
    $document_title = !empty($settlement['is_layaway']) ? 'Provisional Pole Pole Invoice' : 'Provisional Order Invoice';
    $document_badge = 'Outstanding KES ' . number_format($outstanding_balance, 2);
    $document_color = '#b45309';
}

$served_by_raw = trim((string)($order_meta['processed_by'] ?? ''));
$served_by = $served_by_raw !== '' ? $served_by_raw : 'Not yet assigned';
$issued_by = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Administrator';

$payments_stmt = $conn->prepare('SELECT amount,payment_method,transaction_code,payment_status,created_at FROM payments WHERE order_id=? ORDER BY id ASC');
if (!$payments_stmt) { http_response_code(500); exit('Unable to prepare payment history.'); }
$payments_stmt->bind_param('i', $order_id);
if (!$payments_stmt->execute()) { http_response_code(500); exit('Unable to load payment history.'); }
$payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$payments_stmt->close();
$refunded_amount = 0.00;
foreach ($payments as $payment_row) {
    if (strtolower(trim((string)$payment_row['payment_status'])) === 'refunded') $refunded_amount += (float)$payment_row['amount'];
}

$items_stmt = $conn->prepare("SELECT oi.price,oi.quantity,COALESCE(p.product_name,'Unavailable product') product_name,COALESCE(p.sku,'N/A') sku FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
if (!$items_stmt) { http_response_code(500); exit('Unable to prepare invoice items.'); }
$items_stmt->bind_param('i', $order_id);
if (!$items_stmt->execute()) { http_response_code(500); exit('Unable to load invoice items.'); }
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
<title><?=htmlspecialchars($document_title)?> - #<?=(int)$order_id?></title>
<style>
*{box-sizing:border-box}body{font-family:Inter,Segoe UI,Arial,sans-serif;color:#263238;padding:0;margin:0;background:#f8fafc}.no-print-zone{position:sticky;top:0;background:#1e293b;padding:12px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.15);z-index:10}.nav-brand{color:#fff;font-weight:800}.nav-actions{display:flex;gap:10px}.btn{border:0;padding:9px 16px;border-radius:6px;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn-print{background:#2563eb}.btn-back{background:#ea580c}.invoice-container{padding:40px 20px}.invoice-box{max-width:900px;margin:auto;padding:38px;border:1px solid #e2e8f0;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.07);border-radius:10px}.header-table{width:100%;margin-bottom:24px}.doc-title{margin:0;color:#1e293b}.badge{display:inline-block;margin-top:7px;padding:6px 10px;border-radius:99px;font-size:11px;font-weight:900;text-transform:uppercase;background:#f8fafc}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;border-top:2px solid #e2e8f0;padding-top:16px}.info-card{padding:15px;border-radius:8px;background:#eff6ff;font-size:13px;line-height:1.7}.info-card.accountability{background:#ecfdf5}.section-title{margin:24px 0 8px;font-size:13px;text-transform:uppercase;color:#334155}.table-wrap{overflow:auto}.ledger{width:100%;min-width:680px;border-collapse:collapse}.ledger th,.ledger td{padding:11px 9px;border-bottom:1px solid #e2e8f0;font-size:13px;text-align:left;vertical-align:top}.ledger th{background:#f8fafc;text-transform:uppercase;font-size:10px;color:#475569}.text-right{text-align:right!important}.text-center{text-align:center!important}.summary-box{width:min(390px,100%);margin:25px 0 0 auto;border-collapse:collapse}.summary-box td{padding:8px;border-bottom:1px solid #f1f5f9;font-size:13px}.summary-box .grand td{border-top:2px solid #cbd5e1;font-size:16px;font-weight:900;color:#1d4ed8}.footer{margin-top:25px;padding-top:14px;border-top:1px dashed #cbd5e1;color:#64748b;font-size:11px;line-height:1.6;text-align:center}.watermark{position:absolute;inset:40% 0 auto;text-align:center;font-size:66px;color:rgba(109,40,217,.08);font-weight:900;transform:rotate(-25deg);pointer-events:none}.invoice-box{position:relative}
@media(max-width:700px){.no-print-zone{flex-direction:column;gap:10px}.invoice-container{padding:14px}.invoice-box{padding:20px 14px}.info-grid{grid-template-columns:1fr}.header-table,.header-table tbody,.header-table tr,.header-table td{display:block;width:100%}.header-table td{text-align:left!important;margin-bottom:10px}}
@media print{body{background:#fff}.no-print-zone{display:none!important}.invoice-container{padding:0}.invoice-box{max-width:none;margin:0;padding:20px;border:0;box-shadow:none}.ledger{display:table!important;min-width:0!important}.ledger tr,.summary-box tr{page-break-inside:avoid;break-inside:avoid}}
</style>
</head>
<body>
<div class="no-print-zone"><div class="nav-brand">&#9889; ADONAK INVOICE CENTER</div><div class="nav-actions"><button type="button" onclick="window.print()" class="btn btn-print">&#128424; Print / Save PDF</button><a href="view_order_items.php?order_id=<?=(int)$order_id?>" class="btn btn-back">&#8592; Return to Order</a></div></div>
<div class="invoice-container"><article class="invoice-box">
<?php if ($is_cancelled): ?><div class="watermark">CANCELLED</div><?php endif; ?>
<table class="header-table"><tr><td><h1 style="margin:0;color:#1e293b">&#9889; ADONAK ELECTRONICS</h1><div class="badge" style="color:<?=$document_color?>;border:1px solid <?=$document_color?>"><?=htmlspecialchars($document_badge)?></div></td><td class="text-right"><h2 class="doc-title"><?=htmlspecialchars($document_title)?></h2><strong>Order / Invoice:</strong> #<?=(int)$order_id?><br><strong>Issued:</strong> <?=date('d M Y, H:i')?></td></tr></table>
<div class="info-grid">
<div class="info-card"><strong>Customer</strong><br><?=htmlspecialchars($order_meta['fullname'] ?? 'Customer')?><br><?=htmlspecialchars($order_meta['phone'] ?? 'Not provided')?><br><?=htmlspecialchars($order_meta['email'] ?? 'Not provided')?><br>KRA PIN: <?=htmlspecialchars($order_meta['kra_pin'] ?: 'Not provided')?></div>
<div class="info-card accountability"><strong>Order date:</strong> <?=date('d M Y, H:i', strtotime($order_meta['created_at']))?><br><strong>Order status:</strong> <?=htmlspecialchars(ucfirst((string)$order_meta['order_status']))?><br><strong>Served By:</strong> <?=htmlspecialchars($served_by)?><br><strong>Document Issued By:</strong> <?=htmlspecialchars($issued_by)?></div>
</div>
<h3 class="section-title">Purchased Items</h3>
<div class="table-wrap"><table class="ledger"><thead><tr><th>Description</th><th>SKU</th><th class="text-center">Qty</th><th class="text-right">Net price</th><th class="text-right">VAT (<?=number_format($row_tax_rate,2)?>%)</th><th class="text-right">Gross total</th></tr></thead><tbody>
<?php if ($items): foreach ($items as $item): $gross_row=(float)$item['price']*(int)$item['quantity'];$divisor=1+($row_tax_rate/100);$net_row=$divisor>0?$gross_row/$divisor:$gross_row;$vat_row=$gross_row-$net_row; ?>
<tr><td><strong><?=htmlspecialchars($item['product_name'])?></strong></td><td><?=htmlspecialchars($item['sku'])?></td><td class="text-center"><?=(int)$item['quantity']?></td><td class="text-right">KES <?=number_format($net_row,2)?></td><td class="text-right">KES <?=number_format($vat_row,2)?></td><td class="text-right"><strong>KES <?=number_format($gross_row,2)?></strong></td></tr>
<?php endforeach; else: ?><tr><td colspan="6">No item details are recorded.</td></tr><?php endif; ?>
</tbody></table></div>
<h3 class="section-title">Pole Pole / Payment History (as recorded)</h3>
<div class="table-wrap"><table class="ledger"><thead><tr><th>Date paid / recorded</th><th>Payment method</th><th>Reference</th><th class="text-right">Amount</th><th class="text-right">Stored status</th></tr></thead><tbody>
<?php if ($payments): foreach ($payments as $payment): $status=strtolower(trim((string)$payment['payment_status']));$status_color=$status==='completed'?'#047857':($status==='refunded'?'#6d28d9':($status==='failed'?'#b91c1c':'#b45309'));$reference=trim((string)$payment['transaction_code']); ?>
<tr><td><?=htmlspecialchars(date('d M Y, H:i',strtotime($payment['created_at'])))?></td><td><?=htmlspecialchars($payment['payment_method']?:'Not recorded')?></td><td><?=htmlspecialchars($reference!==''&&$reference!=='0'?$reference:'Not recorded')?></td><td class="text-right"><strong>KES <?=number_format((float)$payment['amount'],2)?></strong></td><td class="text-right" style="color:<?=$status_color?>;font-weight:800"><?=htmlspecialchars(ucfirst($status?:'unknown'))?></td></tr>
<?php endforeach; else: ?><tr><td colspan="5">No payment entries have been recorded for this order.</td></tr><?php endif; ?>
</tbody></table></div>
<table class="summary-box"><tr><td>Net total:</td><td class="text-right">KES <?=number_format($net_amount,2)?></td></tr><tr><td>VAT total (<?=number_format($row_tax_rate,2)?>%):</td><td class="text-right">KES <?=number_format($vat_amount,2)?></td></tr><tr class="grand"><td>Order total:</td><td class="text-right">KES <?=number_format($grand_total,2)?></td></tr><tr><td>Completed payments:</td><td class="text-right" style="color:#047857">KES <?=number_format($completed_paid,2)?></td></tr><?php if($refunded_amount>0.009):?><tr><td>Refunded payments:</td><td class="text-right" style="color:#6d28d9">KES <?=number_format($refunded_amount,2)?></td></tr><?php endif;?><?php if(!$is_cancelled):?><tr><td>Outstanding balance:</td><td class="text-right" style="color:<?=$outstanding_balance>0.009?'#b91c1c':'#047857'?>">KES <?=number_format($outstanding_balance,2)?></td></tr><?php endif;?></table>
<div class="footer"><?php if($is_cancelled):?>This historical record shows the cancelled order and its recorded payment reversals.<?php elseif($is_fully_paid):?>This paid sales receipt reflects completed payments in the ADONAK store ledger.<?php else:?>This provisional invoice records instalments made so far and is not proof of full payment.<?php endif;?><br>Payment methods, references, amounts, dates and statuses are shown exactly from the stored payment ledger. This document does not claim independent KRA or payment-network verification.<br>Served by <?=htmlspecialchars($served_by)?>; document issued by <?=htmlspecialchars($issued_by)?>.</div>
</article></div>
</body></html>
