<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';
if (!verifyWorkspaceClearance('mpesa_checker.php')) {
    header('Location: ../login.php');
    exit;
}
$query = strtoupper(trim((string)($_GET['payment_reference'] ?? '')));
$searched = $query !== '';
$results = [];
$error = '';
if ($searched) {
    if ($query === '0' || !preg_match('/^[A-Z0-9_-]{4,100}$/', $query)) {
        $error = 'Enter a valid payment reference using 4 to 100 letters, numbers, hyphens or underscores.';
    } else {
        $stmt = $conn->prepare("SELECT p.id,p.transaction_code,p.amount,p.payment_status,p.payment_method,p.order_id,p.created_at,COALESCE(u.fullname,du.fullname) AS fullname FROM payments p LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN users u ON u.id=o.user_id LEFT JOIN users du ON du.id=CAST(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(p.transaction_code,'_',3),'_',-1),'U','') AS UNSIGNED) AND p.transaction_code REGEXP '^TXN_DEP_U[0-9]+_' WHERE UPPER(TRIM(p.transaction_code))=? AND TRIM(p.transaction_code)<>'0' ORDER BY p.id DESC LIMIT 100");
        if (!$stmt) {
            $error = 'The payment records could not be searched right now. Please try again.';
        } else {
            $stmt->bind_param('s', $query);
            if (!$stmt->execute()) {
                $error = 'The payment records could not be searched right now. Please try again.';
            } else {
                $search_result = $stmt->get_result();
                while ($row = $search_result->fetch_assoc()) {
                    $results[] = $row;
                }
            }
            $stmt->close();
        }
    }
}
$recent = $conn->query("SELECT p.id,p.transaction_code,p.amount,p.payment_status,p.payment_method,p.order_id,p.created_at,COALESCE(u.fullname,du.fullname) AS fullname FROM payments p LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN users u ON u.id=o.user_id LEFT JOIN users du ON du.id=CAST(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(p.transaction_code,'_',3),'_',-1),'U','') AS UNSIGNED) AND p.transaction_code REGEXP '^TXN_DEP_U[0-9]+_' WHERE p.transaction_code IS NOT NULL AND TRIM(p.transaction_code)<>'' AND TRIM(p.transaction_code)<>'0' ORDER BY p.id DESC LIMIT 20");
$recent_error = $recent === false;
function payment_status_class($status) {
    $value = strtolower((string)$status);
    return in_array($value, ['completed','refunded','failed'], true) ? $value : 'pending';
}
?>
<style>
.payment-lookup{font-family:system-ui;color:#172033}.lookup-head h2{margin:0 0 7px}.lookup-head p,.lookup-note{color:#64748b}.lookup-form{display:flex;gap:9px;margin:20px 0}.lookup-form input{flex:1;max-width:570px;padding:12px;border:1px solid #cbd5e1;border-radius:9px;text-transform:uppercase;font-weight:700}.lookup-form button{padding:12px 20px;border:0;border-radius:9px;background:#2563eb;color:white;font-weight:800}.lookup-result{padding:18px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:12px;margin-bottom:20px}.lookup-result.error{border-color:#fecaca;background:#fef2f2;color:#991b1b}.lookup-result.duplicate{border-color:#fbbf24;background:#fffbeb;color:#92400e}.lookup-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.lookup-grid small,.lookup-grid strong{display:block}.lookup-grid small{color:#64748b;margin-bottom:4px}.status{display:inline-block;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:900;text-transform:uppercase;background:#fef3c7;color:#92400e}.status.completed{background:#dcfce7;color:#166534}.status.refunded{background:#e0e7ff;color:#3730a3}.status.failed{background:#fee2e2;color:#991b1b}.lookup-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.lookup-table{width:100%;border-collapse:collapse;min-width:820px}.lookup-table th,.lookup-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.lookup-table th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase}.reference{font-family:monospace;font-weight:800}.lookup-note{font-size:12px;margin-top:12px}@media(max-width:700px){.lookup-form{flex-direction:column}.lookup-form input{max-width:none}.lookup-grid{grid-template-columns:1fr}}
</style>
<section class='payment-lookup'>
<header class='lookup-head'><h2>Payment Reference Lookup</h2><p>Find a payment reference recorded by this store and review its stored processing status.</p></header>
<form class='lookup-form' method='get' action='mpesa_checker.php'><input name='payment_reference' value='<?=htmlspecialchars($query)?>' placeholder='Enter payment reference' minlength='4' maxlength='100' pattern='[A-Za-z0-9_-]+' required autocomplete='off'><button type='submit'>Search records</button></form>
<?php if ($searched): ?>
<?php if ($error): ?><div class='lookup-result error' role='alert'><?=htmlspecialchars($error)?></div>
<?php elseif (!$results): ?><div class='lookup-result error'><strong>No matching local record</strong><p>No stored payment uses reference <?=htmlspecialchars($query)?>.</p></div>
<?php else: ?>
<?php if (count($results)>1): ?><div class='lookup-result duplicate' role='alert'><strong><?=count($results)?> records use this reference.</strong> Review every match below; this reference is not unique.</div><?php endif; ?>
<?php foreach($results as $result): $status=payment_status_class($result['payment_status']); ?>
<article class='lookup-result'><div class='lookup-grid'>
<div><small>Record</small><strong>#<?=(int)$result['id']?></strong></div><div><small>Reference</small><strong class='reference'><?=htmlspecialchars($result['transaction_code'])?></strong></div>
<div><small>Customer</small><strong><?=htmlspecialchars($result['fullname'] ?: 'Not linked')?></strong></div>
<div><small>Order</small><strong><?=$result['order_id']?'#'.(int)$result['order_id']:'Not linked'?></strong></div>
<div><small>Method</small><strong><?=htmlspecialchars($result['payment_method'] ?: 'Not recorded')?></strong></div>
<div><small>Amount</small><strong>KES <?=number_format((float)$result['amount'],2)?></strong></div>
<div><small>Stored status</small><span class='status <?=$status?>'><?=htmlspecialchars($result['payment_status'] ?: 'Not recorded')?></span></div>
<div><small>Recorded</small><strong><?=!empty($result['created_at'])?date('d M Y, h:i A',strtotime($result['created_at'])):'Not recorded'?></strong></div>
</div></article><?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<h3>Recent payment references</h3>
<div class='lookup-table-wrap'><table class='lookup-table'><thead><tr><th>Reference</th><th>Customer</th><th>Order</th><th>Method</th><th>Amount</th><th>Status</th><th>Recorded</th></tr></thead><tbody>
<?php if ($recent_error): ?><tr><td colspan='7'>Recent payment records could not be loaded. Please try again.</td></tr><?php elseif ($recent && $recent->num_rows): while ($row=$recent->fetch_assoc()): $status=payment_status_class($row['payment_status']); ?>
<tr><td class='reference'><?=htmlspecialchars($row['transaction_code'])?></td><td><?=htmlspecialchars($row['fullname'] ?: 'Not linked')?></td><td><?=$row['order_id']?'#'.(int)$row['order_id']:'Not linked'?></td><td><?=htmlspecialchars($row['payment_method'] ?: 'Not recorded')?></td><td>KES <?=number_format((float)$row['amount'],2)?></td><td><span class='status <?=$status?>'><?=htmlspecialchars($row['payment_status'])?></span></td><td><?=!empty($row['created_at'])?date('d M Y, h:i A',strtotime($row['created_at'])):'Not recorded'?></td></tr>
<?php endwhile; else: ?><tr><td colspan='7'>No payment references have been recorded.</td></tr><?php endif; ?>
</tbody></table></div>
<p class='lookup-note'>This page checks records saved in ADONAK Electronics. It does not connect to Safaricom or independently confirm settlement on the M-Pesa network.</p>
</section>
