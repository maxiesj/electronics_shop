<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';
if (!verifyWorkspaceClearance('layaway_defaulters.php')) {
    header('Location: ../login.php');
    exit;
}
date_default_timezone_set('Africa/Nairobi');
if (empty($_SESSION['defaulter_csrf'])) $_SESSION['defaulter_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['defaulter_csrf'];
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_installment_action'])) {
    $plan_id = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $raw_amount = trim((string)($_POST['payment_amount'] ?? ''));
    $valid_amount = preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $raw_amount) === 1;
    $amount = $valid_amount ? (float)$raw_amount : 0.0;
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $err = 'Your security token expired. Refresh and try again.';
    } elseif (!$plan_id || !$valid_amount || $amount <= 0) {
        $err = 'Enter a valid payment amount greater than KES 0.00.';
    } else {
        $conn->begin_transaction();
        try {
            $sql = 'SELECT order_id,total_amount,deposit_paid,balance_remaining,created_at FROM layaway_plans';
            $sql .= ' WHERE id=' . chr(63) . ' FOR UPDATE';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $plan_id);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$plan) throw new RuntimeException('Active plan not found.');
            $order_id = (int)$plan['order_id'];
            $order = $conn->query('SELECT order_status FROM orders WHERE id=' . $order_id . ' FOR UPDATE')->fetch_assoc();
            if (!$order || strtolower($order['order_status']) === 'cancelled') throw new RuntimeException('Order is cancelled.');
            $due = new DateTimeImmutable($plan['created_at'], new DateTimeZone('Africa/Nairobi'));
            $due = $due->modify('+30 days');
            $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
            if ($now <= $due) throw new RuntimeException('Plan is not overdue.');
            $balance = round((float)$plan['balance_remaining'], 2);
            if ($amount > $balance) throw new RuntimeException('Payment exceeds balance.');
            $new_balance = max(0, round($balance - $amount, 2));
            $new_paid = round((float)$plan['deposit_paid'] + $amount, 2);
            $new_status = $new_balance <= 0.009 ? 'Fully Paid' : 'Active';
            $sql = 'UPDATE layaway_plans SET deposit_paid=' . chr(63) . ',balance_remaining=' . chr(63) . ',status=' . chr(63) . ' WHERE id=' . chr(63);
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ddsi', $new_paid, $new_balance, $new_status, $plan_id);
            if (!$stmt->execute()) throw new RuntimeException('Plan update failed.');
            $stmt->close();
            $code = 'INSTAL_' . strtoupper(bin2hex(random_bytes(4)));
            $method = 'Lipa Pole-Pole Cash Installment';
            $sql = 'INSERT INTO payments (order_id,payment_method,transaction_code,amount,payment_status,created_at) VALUES (' . implode(',', array_fill(0,5,chr(63))) . ',NOW())';
            $stmt = $conn->prepare($sql);
            $status = 'completed';
            $stmt->bind_param('issds', $order_id, $method, $code, $amount, $status);
            if (!$stmt->execute()) throw new RuntimeException('Payment write failed.');
            $stmt->close();
            if ($new_status === 'Fully Paid') {
                $sql = 'UPDATE orders SET order_status=\'processing\'';
                $sql .= ' WHERE LOWER(order_status)<>\'cancelled\' AND id=' . (int)$order_id;
                if (!$conn->query($sql)) throw new RuntimeException('Order update failed.');
            }
            $details = 'Plan #' . $plan_id . ': KES ' . number_format($amount,2) . ' collected; balance KES ' . number_format($new_balance,2);
            $staff_id = (int)($_SESSION['user_id'] ?? 0);
            $staff_name = (string)($_SESSION['fullname'] ?? 'Unknown operator');
            $sql = 'INSERT INTO staff_logs (user_id,staff_name,action_type,action_details) VALUES (' . implode(',',array_fill(0,4,chr(63))) . ')';
            $stmt = $conn->prepare($sql);
            $action = 'Financial Update';
            $stmt->bind_param('isss', $staff_id, $staff_name, $action, $details);
            if (!$stmt->execute()) throw new RuntimeException('Audit failed.');
            $stmt->close();
            $conn->commit();
            $msg = 'KES ' . number_format($amount,2) . ' recorded. Balance: KES ' . number_format($new_balance,2) . '.';
            $csrf = $_SESSION['defaulter_csrf'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Collection failed: '.$e->getMessage());
            $err = 'Payment failed. No records changed.';
        }
    }
}
$plans = [];
$total_overdue = 0.0;
$customers = [];
$sql = 'SELECT lp.*,u.fullname,u.phone FROM layaway_plans lp';
$sql .= ' JOIN orders o ON o.id=lp.order_id JOIN users u ON u.id=lp.user_id';
$sql .= ' WHERE LOWER(lp.status)=\'active\'';
$sql .= ' AND lp.balance_remaining BETWEEN 0.01 AND 99999999';
$sql .= ' AND DATE_ADD(lp.created_at,INTERVAL 30 DAY) < NOW()';
$sql .= ' AND LOWER(o.order_status) <> \'cancelled\'';
$result = $conn->query($sql);
if ($result) while ($row=$result->fetch_assoc()) {
    $plans[] = $row;
    $total_overdue += (float)$row['balance_remaining'];
    $customers[(int)$row['user_id']] = true;
}
?>
<style>
.default-hub{font-family:system-ui;color:#172033}.default-alert{padding:12px;border-radius:9px;margin-bottom:14px;background:#ecfdf5;color:#047857}.default-alert.error{background:#fef2f2;color:#b91c1c}.default-head p{color:#64748b}.default-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:18px 0}.default-card{padding:17px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px}.default-card:nth-child(2){background:#fff7ed;border-color:#fed7aa}.default-card:nth-child(3){background:#eff6ff;border-color:#bfdbfe}.default-card small,.default-card strong{display:block}.default-card strong{font-size:21px;margin-top:6px}.default-tools{display:flex;gap:9px;margin-bottom:13px}.default-tools input{flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:8px}.default-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.default-table{width:100%;border-collapse:collapse;min-width:900px}.default-table th,.default-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.default-table th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase}.overdue{color:#b91c1c;font-weight:800}.collect-form{display:flex;gap:6px}.collect-form input{width:110px;padding:8px;border:1px solid #cbd5e1;border-radius:8px}.collect-form button{background:#ea580c;color:white;border:0;border-radius:8px;padding:8px 12px;font-weight:800}.default-empty{text-align:center;padding:30px;color:#64748b}@media(max-width:760px){.default-cards{grid-template-columns:1fr}.default-tools{flex-direction:column}}
</style>
<section class='default-hub'>
<?php if ($err): ?><div class='default-alert error'><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($msg): ?><div class='default-alert'><?=htmlspecialchars($msg)?></div><?php endif; ?>
<header class='default-head'><h2>Installment Defaulters</h2><p>Active Lipa Pole Pole plans that still have a balance after their 30-day payment deadline.</p></header>
<div class='default-cards'><article class='default-card'><small>Overdue plans</small><strong><?=count($plans)?></strong></article><article class='default-card'><small>Outstanding overdue</small><strong>KES <?=number_format($total_overdue,2)?></strong></article><article class='default-card'><small>Affected customers</small><strong><?=count($customers)?></strong></article></div>
<div class='default-tools'><input id='default-search' type='search' placeholder='Search customer, phone, plan or order'><strong id='default-count'><?=count($plans)?> overdue</strong></div>
<div class='default-table-wrap'><table class='default-table'><thead><tr><th>Plan / order</th><th>Customer</th><th>Deadline</th><th>Days overdue</th><th>Paid</th><th>Balance</th><th>Record payment</th></tr></thead><tbody>
<?php if ($plans): foreach ($plans as $row): $due=(new DateTimeImmutable($row['created_at'],new DateTimeZone('Africa/Nairobi')))->modify('+30 days'); $days=max(1,(int)$due->diff(new DateTimeImmutable('now',new DateTimeZone('Africa/Nairobi')))->days); ?>
<tr data-default-row data-search='<?=htmlspecialchars(strtolower($row['id'].' '.$row['order_id'].' '.$row['fullname'].' '.$row['phone']))?>'>
<td><strong>Plan #<?=(int)$row['id']?></strong><br><small>Order #<?=(int)$row['order_id']?></small></td>
<td><strong><?=htmlspecialchars($row['fullname'])?></strong><br><small><?=htmlspecialchars($row['phone'] ?: 'No phone recorded')?></small></td>
<td><?=$due->format('d M Y, h:i A')?></td><td class='overdue'><?=$days?> day<?=$days===1?'':'s'?></td>
<td>KES <?=number_format((float)$row['deposit_paid'],2)?></td><td class='overdue'>KES <?=number_format((float)$row['balance_remaining'],2)?></td>
<td><form class='collect-form' method='post' action='layaway_defaulters.php'><input type='hidden' name='settle_installment_action' value='1'><input type='hidden' name='plan_id' value='<?=(int)$row['id']?>'><input type='hidden' name='csrf_token' value='<?=htmlspecialchars($csrf)?>'><input type='number' name='payment_amount' min='0.01' max='<?=number_format((float)$row['balance_remaining'],2,'.','')?>' step='0.01' placeholder='KES 0.00' required><button type='submit'>Record</button></form></td>
</tr>
<?php endforeach; else: ?><tr><td colspan='7' class='default-empty'>No active installment plan is past its 30-day deadline.</td></tr><?php endif; ?>
</tbody></table></div>
<script>
(function(){
 const search=document.getElementById('default-search'),rows=[...document.querySelectorAll('[data-default-row]')],count=document.getElementById('default-count');
 search.addEventListener('input',function(){const q=this.value.trim().toLowerCase();let shown=0;rows.forEach(row=>{const visible=!q||row.dataset.search.includes(q);row.style.display=visible?'':'none';if(visible)shown++;});count.textContent=shown+' overdue';});
 document.querySelectorAll('.collect-form').forEach(form=>form.addEventListener('submit',function(event){if(!confirm('Record this installment payment? Confirm that the funds were actually received.')){event.preventDefault();event.stopImmediatePropagation();return;}const button=this.querySelector('button');button.disabled=true;button.textContent='Recording...';}));
})();
</script>
</section>
