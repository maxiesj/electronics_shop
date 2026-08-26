<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';
if (!verifyWorkspaceClearance('layaway_defaulters.php')) {
    header('Location: ../login.php');
    exit;
}
date_default_timezone_set('Africa/Nairobi');

if (empty($_SESSION['defaulter_csrf'])) {
    $_SESSION['defaulter_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['defaulter_csrf'];
$msg = $err = '';

function normalizeInstallmentReference(string $value): string {
    return strtoupper(trim($value));
}

function installmentReferenceExists(mysqli $conn, string $reference): bool {
    $checks = [
        ['SELECT 1 FROM payments WHERE transaction_code IS NOT NULL AND TRIM(transaction_code) <> \'\' AND UPPER(TRIM(transaction_code)) = ? LIMIT 1', 'sales/customer payments'],
        ['SELECT 1 FROM payroll_records WHERE reference_number IS NOT NULL AND TRIM(reference_number) <> \'\' AND UPPER(TRIM(reference_number)) = ? LIMIT 1', 'payroll'],
        ['SELECT 1 FROM operating_expenses WHERE reference_number IS NOT NULL AND TRIM(reference_number) <> \'\' AND UPPER(TRIM(reference_number)) = ? LIMIT 1', 'operating expenses'],
        ['SELECT 1 FROM refund_logs WHERE reversal_reference IS NOT NULL AND TRIM(reversal_reference) <> \'\' AND UPPER(TRIM(reversal_reference)) = ? LIMIT 1', 'refunds'],
    ];

    foreach ($checks as [$sql]) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($exists) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_installment_action'])) {
    $plan_id = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $raw_amount = trim((string)($_POST['payment_amount'] ?? ''));
    $valid_amount = preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $raw_amount) === 1;
    $amount = $valid_amount ? round((float)$raw_amount, 2) : 0.0;
    $method = trim((string)($_POST['payment_method'] ?? ''));
    $reference = normalizeInstallmentReference((string)($_POST['transaction_reference'] ?? ''));
    $allowed_methods = ['Cash', 'M-Pesa', 'Bank Transfer', 'Card'];
    $staff_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $staff_name = trim((string)($_SESSION['fullname'] ?? ''));

    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $err = 'Your security token expired. Refresh and try again.';
    } elseif (!$staff_id || $staff_name === '') {
        $err = 'Your staff session is incomplete. Sign in again before recording a payment.';
    } elseif (!$plan_id || !$valid_amount || $amount <= 0) {
        $err = 'Enter a valid payment amount greater than KES 0.00.';
    } elseif (!in_array($method, $allowed_methods, true)) {
        $err = 'Choose a valid payment method.';
    } elseif ($reference === '' || strlen($reference) > 100 || !preg_match('/^[A-Z0-9._\-\/ ]+$/', $reference)) {
        $err = 'Enter a valid payment reference using letters, numbers, spaces, dash, slash, dot or underscore.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT id,order_id,user_id,total_amount,deposit_paid,balance_remaining,status,created_at FROM layaway_plans WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $plan_id);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$plan) throw new DomainException('Layaway plan not found.');
            if (strtolower((string)$plan['status']) !== 'active') throw new DomainException('This layaway plan is no longer active.');

            $order_id = (int)$plan['order_id'];
            $stmt = $conn->prepare('SELECT id,user_id,order_status FROM orders WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) throw new DomainException('The linked order no longer exists.');
            if ((int)$order['user_id'] !== (int)$plan['user_id']) throw new DomainException('The layaway customer does not match the linked order.');
            if (strtolower((string)$order['order_status']) === 'cancelled') throw new DomainException('The linked order is cancelled.');

            $due = (new DateTimeImmutable((string)$plan['created_at'], new DateTimeZone('Africa/Nairobi')))->modify('+30 days');
            $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
            if ($now <= $due) throw new DomainException('This plan is not overdue yet.');

            $total = round((float)$plan['total_amount'], 2);
            $paid = round((float)$plan['deposit_paid'], 2);
            $balance = round((float)$plan['balance_remaining'], 2);
            if ($total <= 0 || $paid < 0 || $balance <= 0 || abs(($paid + $balance) - $total) > 0.02) {
                throw new DomainException('The layaway totals are inconsistent. Review the plan before collecting another payment.');
            }
            if ($amount > $balance) throw new DomainException('Payment exceeds the remaining balance.');

            if (installmentReferenceExists($conn, $reference)) {
                throw new DomainException('That payment reference is already used in another financial record.');
            }

            $new_balance = round($balance - $amount, 2);
            if ($new_balance < 0) $new_balance = 0.0;
            $new_paid = round($paid + $amount, 2);
            $new_status = $new_balance <= 0.009 ? 'Fully Paid' : 'Active';

            $stmt = $conn->prepare('UPDATE layaway_plans SET deposit_paid=?,balance_remaining=?,status=? WHERE id=?');
            $stmt->bind_param('ddsi', $new_paid, $new_balance, $new_status, $plan_id);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Plan update failed.');
            $stmt->close();

            $payment_method = 'Lipa Pole-Pole ' . $method . ' Installment';
            $status = 'completed';
            $stmt = $conn->prepare('INSERT INTO payments (order_id,payment_method,transaction_code,amount,payment_status,created_at) VALUES (?,?,?,?,?,NOW())');
            $stmt->bind_param('issds', $order_id, $payment_method, $reference, $amount, $status);
            if (!$stmt->execute()) throw new RuntimeException('Payment write failed.');
            $payment_id = (int)$stmt->insert_id;
            $stmt->close();

            if ($new_status === 'Fully Paid') {
                $stmt = $conn->prepare("UPDATE orders SET order_status='processing' WHERE id=? AND LOWER(order_status)<>'cancelled'");
                $stmt->bind_param('i', $order_id);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Order update failed.');
                $stmt->close();
            }

            $details = 'Plan #' . $plan_id . ', order #' . $order_id . ', payment #' . $payment_id . ': KES ' . number_format($amount, 2) . ' via ' . $method . ' (' . $reference . '); balance KES ' . number_format($new_balance, 2);
            $action = 'Financial Update';
            $stmt = $conn->prepare('INSERT INTO staff_logs (user_id,staff_name,action_type,action_details) VALUES (?,?,?,?)');
            $stmt->bind_param('isss', $staff_id, $staff_name, $action, $details);
            if (!$stmt->execute()) throw new RuntimeException('Audit failed.');
            $stmt->close();

            $conn->commit();
            $msg = 'KES ' . number_format($amount, 2) . ' recorded successfully. Balance: KES ' . number_format($new_balance, 2) . '.';
            $csrf = $_SESSION['defaulter_csrf'] = bin2hex(random_bytes(32));
        } catch (DomainException $e) {
            $conn->rollback();
            $err = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Layaway collection failed: ' . $e->getMessage());
            $err = 'Payment failed. No records changed.';
        }
    }
}

$plans = [];
$total_overdue = 0.0;
$customers = [];
$sql = "SELECT lp.*,u.fullname,u.phone FROM layaway_plans lp JOIN orders o ON o.id=lp.order_id JOIN users u ON u.id=lp.user_id WHERE LOWER(lp.status)='active' AND lp.balance_remaining BETWEEN 0.01 AND 99999999.99 AND DATE_ADD(lp.created_at,INTERVAL 30 DAY) < NOW() AND LOWER(o.order_status) <> 'cancelled' ORDER BY DATE_ADD(lp.created_at,INTERVAL 30 DAY) ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
        $total_overdue += (float)$row['balance_remaining'];
        $customers[(int)$row['user_id']] = true;
    }
}
?>
<style>
.default-hub{font-family:system-ui;color:#172033}.default-alert{padding:12px;border-radius:9px;margin-bottom:14px;background:#ecfdf5;color:#047857}.default-alert.error{background:#fef2f2;color:#b91c1c}.default-head p{color:#64748b}.default-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:18px 0}.default-card{padding:17px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px}.default-card:nth-child(2){background:#fff7ed;border-color:#fed7aa}.default-card:nth-child(3){background:#eff6ff;border-color:#bfdbfe}.default-card small,.default-card strong{display:block}.default-card strong{font-size:21px;margin-top:6px}.default-tools{display:flex;gap:9px;margin-bottom:13px}.default-tools input{flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:8px}.default-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.default-table{width:100%;border-collapse:collapse;min-width:1120px}.default-table th,.default-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.default-table th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase}.overdue{color:#b91c1c;font-weight:800}.collect-form{display:grid;grid-template-columns:105px 110px minmax(150px,1fr) auto;gap:6px;align-items:center}.collect-form input,.collect-form select{padding:8px;border:1px solid #cbd5e1;border-radius:8px;min-width:0}.collect-form button{background:#ea580c;color:white;border:0;border-radius:8px;padding:8px 12px;font-weight:800}.default-empty{text-align:center;padding:30px;color:#64748b}@media(max-width:900px){.collect-form{grid-template-columns:1fr}.default-cards{grid-template-columns:1fr}.default-tools{flex-direction:column}}
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
<td><form class='collect-form' method='post' action='layaway_defaulters.php' data-balance='<?=number_format((float)$row['balance_remaining'],2,'.','')?>'><input type='hidden' name='settle_installment_action' value='1'><input type='hidden' name='plan_id' value='<?=(int)$row['id']?>'><input type='hidden' name='csrf_token' value='<?=htmlspecialchars($csrf)?>'><input type='number' name='payment_amount' min='0.01' max='<?=number_format((float)$row['balance_remaining'],2,'.','')?>' step='0.01' placeholder='KES 0.00' required><select name='payment_method' required><option value=''>Method</option><option value='Cash'>Cash</option><option value='M-Pesa'>M-Pesa</option><option value='Bank Transfer'>Bank transfer</option><option value='Card'>Card</option></select><input type='text' name='transaction_reference' maxlength='100' placeholder='Receipt / transaction reference' required><button type='submit'>Record</button></form></td>
</tr>
<?php endforeach; else: ?><tr><td colspan='7' class='default-empty'>No active installment plan is past its 30-day deadline.</td></tr><?php endif; ?>
</tbody></table></div>
<script>
(function(){
 const search=document.getElementById('default-search'),rows=[...document.querySelectorAll('[data-default-row]')],count=document.getElementById('default-count');
 search.addEventListener('input',function(){const q=this.value.trim().toLowerCase();let shown=0;rows.forEach(row=>{const visible=!q||row.dataset.search.includes(q);row.style.display=visible?'':'none';if(visible)shown++;});count.textContent=shown+' overdue';});
 document.querySelectorAll('.collect-form').forEach(form=>form.addEventListener('submit',function(event){const amount=this.elements.payment_amount.value;const method=this.elements.payment_method.value;const reference=this.elements.transaction_reference.value.trim();if(!confirm('Record KES '+amount+' via '+method+' with reference '+reference+'? Confirm that the funds were actually received.')){event.preventDefault();event.stopImmediatePropagation();return;}const button=this.querySelector('button');button.disabled=true;button.textContent='Recording...';}));
})();
</script>
</section>
