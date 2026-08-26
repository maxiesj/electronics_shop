<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';

if (!verifyWorkspaceClearance('manage_wallets.php')) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['wallet_adjustment_csrf'])) {
    $_SESSION['wallet_adjustment_csrf'] = bin2hex(random_bytes(32));
}
$wallet_csrf = $_SESSION['wallet_adjustment_csrf'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_credit_action'])) {
    $wallet_id = filter_var($_POST['wallet_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $type = (string)($_POST['adjustment_type'] ?? '');
    $amount_raw = trim((string)($_POST['adjustment_amount'] ?? ''));
    $reason = trim((string)($_POST['adjustment_reason'] ?? ''));
    $amount_valid = preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amount_raw) === 1;
    $amount = $amount_valid ? round((float)$amount_raw, 2) : 0.0;
    $staff_id = (int)($_SESSION['user_id'] ?? 0);
    $staff_name = trim((string)($_SESSION['fullname'] ?? ''));

    if (!hash_equals($wallet_csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $err = 'Your security token expired. Refresh the workspace and try again.';
    } elseif (!$wallet_id || !in_array($type, ['add', 'deduct'], true) || !$amount_valid || $amount <= 0) {
        $err = 'Choose a valid adjustment and enter an amount greater than KES 0.00.';
    } elseif (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
        $err = 'Enter an adjustment reason between 5 and 255 characters.';
    } elseif ($staff_id <= 0 || $staff_name === '') {
        $err = 'Your staff session is incomplete. Sign in again before adjusting a wallet.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'SELECT cw.available_balance, cw.user_id, u.fullname, u.email
                 FROM customer_wallets cw
                 JOIN users u ON u.id = cw.user_id
                 WHERE cw.id = ?
                 FOR UPDATE'
            );
            if (!$stmt) throw new RuntimeException('Wallet lock could not be prepared.');
            $stmt->bind_param('i', $wallet_id);
            $stmt->execute();
            $wallet = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$wallet) throw new DomainException('Wallet not found.');

            $before = round((float)$wallet['available_balance'], 2);
            if ($before < 0) throw new RuntimeException('Wallet balance is invalid and requires review.');
            if ($type === 'deduct' && $amount > $before) throw new DomainException('Insufficient funds.');

            $after = round($type === 'add' ? $before + $amount : $before - $amount, 2);
            if ($after < 0 || $after > 99999999.99) {
                throw new DomainException('The resulting wallet balance is outside the allowed range.');
            }

            $stmt = $conn->prepare('UPDATE customer_wallets SET available_balance = ?, updated_at = NOW() WHERE id = ?');
            if (!$stmt) throw new RuntimeException('Wallet update could not be prepared.');
            $stmt->bind_param('di', $after, $wallet_id);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('Wallet update failed.');
            }
            $stmt->close();

            $direction = $type === 'add' ? 'Added' : 'Deducted';
            $customer_name = (string)$wallet['fullname'];
            $details = sprintf(
                '%s KES %s on wallet #%d for %s. Balance: KES %s -> KES %s. Reason: %s',
                $direction,
                number_format($amount, 2),
                $wallet_id,
                $customer_name,
                number_format($before, 2),
                number_format($after, 2),
                $reason
            );

            $stmt = $conn->prepare(
                "INSERT INTO staff_logs (user_id, staff_name, action_type, action_details)
                 VALUES (?, ?, 'Financial Update', ?)"
            );
            if (!$stmt) throw new RuntimeException('Audit entry could not be prepared.');
            $stmt->bind_param('iss', $staff_id, $staff_name, $details);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Audit entry failed.');
            }
            $stmt->close();

            $conn->commit();

            $_SESSION['wallet_adjustment_csrf'] = bin2hex(random_bytes(32));
            $wallet_csrf = $_SESSION['wallet_adjustment_csrf'];
            $msg = 'Wallet updated. New balance: KES ' . number_format($after, 2);
        } catch (DomainException $e) {
            $conn->rollback();
            $err = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Wallet adjustment failed: ' . $e->getMessage());
            $err = 'The wallet could not be updated. No balance was changed.';
        }
    }
}

$wallets = [];
$total_balance = 0.0;
$funded = 0;
$result = $conn->query(
    'SELECT cw.id wallet_id, u.fullname, u.email, cw.available_balance, cw.updated_at
     FROM customer_wallets cw
     JOIN users u ON u.id = cw.user_id
     ORDER BY cw.available_balance DESC'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $wallets[] = $row;
        $total_balance += (float)$row['available_balance'];
        if ((float)$row['available_balance'] > 0) $funded++;
    }
}
?>
<style>
.wallet-hub{font-family:system-ui;color:#172033}.wallet-alert{padding:12px;border-radius:9px;margin-bottom:14px;background:#ecfdf5;color:#047857}.wallet-alert.error{background:#fef2f2;color:#b91c1c}.wallet-head{display:flex;justify-content:space-between;gap:15px;align-items:end}.wallet-head p{color:#64748b}.wallet-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:18px 0}.wallet-card{padding:17px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe}.wallet-card:nth-child(2){background:#ecfdf5;border-color:#a7f3d0}.wallet-card:nth-child(3){background:#f5f3ff;border-color:#ddd6fe}.wallet-card small,.wallet-card strong{display:block}.wallet-card strong{font-size:21px;margin-top:6px}.wallet-tools{display:flex;gap:9px;margin-bottom:13px}.wallet-tools input,.wallet-tools select,.wallet-form input,.wallet-form select{padding:9px;border:1px solid #cbd5e1;border-radius:8px}.wallet-tools input{flex:1}.wallet-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.wallet-table{width:100%;border-collapse:collapse;min-width:980px}.wallet-table th,.wallet-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.wallet-table th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase}.wallet-balance{font-weight:800;color:#047857;white-space:nowrap}.wallet-form{display:flex;gap:6px;flex-wrap:wrap}.wallet-form input[type=number]{width:105px}.wallet-form input[type=text]{min-width:180px;flex:1}.wallet-form button{background:#2563eb;color:white;border:0;border-radius:8px;padding:9px 12px;font-weight:700}.wallet-empty{text-align:center;color:#64748b;padding:30px}.wallet-no-results{display:none;text-align:center;padding:20px;color:#64748b}@media(max-width:760px){.wallet-cards{grid-template-columns:1fr}.wallet-head,.wallet-tools{align-items:stretch;flex-direction:column}}
</style>
<section class='wallet-hub'>
<?php if ($err): ?><div class='wallet-alert error'><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($msg): ?><div class='wallet-alert'><?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class='wallet-head'><div><h2>Customer Wallets</h2><p>Review store credit and make traceable corrections.</p></div><strong id='wallet-count'><?=count($wallets)?> wallets</strong></div>
<div class='wallet-cards'><article class='wallet-card'><small>Customer wallets</small><strong><?=number_format(count($wallets))?></strong></article><article class='wallet-card'><small>Total available credit</small><strong>KES <?=number_format($total_balance,2)?></strong></article><article class='wallet-card'><small>Wallets with funds</small><strong><?=number_format($funded)?></strong></article></div>
<div class='wallet-tools'><input id='wallet-search' type='search' placeholder='Search customer, email or wallet'><select id='wallet-filter'><option value='all'>All balances</option><option value='positive'>With funds</option><option value='zero'>Zero balance</option></select></div>
<div class='wallet-table-wrap'><table class='wallet-table'><thead><tr><th>Wallet</th><th>Customer</th><th>Balance</th><th>Last changed</th><th>Adjustment</th></tr></thead><tbody>
<?php if ($wallets): foreach ($wallets as $row): $balance=(float)$row['available_balance']; ?>
<tr data-wallet-row data-balance='<?=$balance>0?'positive':'zero'?>' data-search='<?=htmlspecialchars(strtolower($row['wallet_id'].' '.$row['fullname'].' '.$row['email']))?>'>
<td>W-<?=str_pad((string)$row['wallet_id'],4,'0',STR_PAD_LEFT)?></td>
<td><strong><?=htmlspecialchars($row['fullname'])?></strong><br><small><?=htmlspecialchars($row['email'])?></small></td>
<td class='wallet-balance'>KES <?=number_format($balance,2)?></td>
<td><?=!empty($row['updated_at'])?date('d M Y, h:i A',strtotime($row['updated_at'])):'Not recorded'?></td>
<td><form class='wallet-form' action='manage_wallets.php' method='post'><input type='hidden' name='adjust_credit_action' value='1'><input type='hidden' name='wallet_id' value='<?=(int)$row['wallet_id']?>'><input type='hidden' name='csrf_token' value='<?=htmlspecialchars($wallet_csrf)?>'><select name='adjustment_type' required><option value='add'>Add credit</option><option value='deduct'>Deduct</option></select><input type='number' name='adjustment_amount' min='0.01' max='99999999.99' step='0.01' placeholder='KES 0.00' required><input type='text' name='adjustment_reason' minlength='5' maxlength='255' placeholder='Reason for adjustment' required><button type='submit'>Apply</button></form></td>
</tr>
<?php endforeach; else: ?><tr><td colspan='5' class='wallet-empty'>No customer wallets have been created yet.</td></tr><?php endif; ?>
</tbody></table><div class='wallet-no-results' id='wallet-empty'>No wallets match this search.</div></div>
</section>
<script>
(function(){
 const search=document.getElementById('wallet-search'),filter=document.getElementById('wallet-filter'),rows=[...document.querySelectorAll('[data-wallet-row]')],count=document.getElementById('wallet-count'),empty=document.getElementById('wallet-empty');
 function apply(){const q=search.value.trim().toLowerCase(),f=filter.value;let shown=0;rows.forEach(row=>{const visible=(!q||row.dataset.search.includes(q))&&(f==='all'||row.dataset.balance===f);row.style.display=visible?'':'none';if(visible)shown++;});count.textContent=shown+' wallet'+(shown===1?'':'s');empty.style.display=rows.length&&shown===0?'block':'none';}
 search.addEventListener('input',apply);filter.addEventListener('change',apply);
 document.querySelectorAll('.wallet-form').forEach(form=>form.addEventListener('submit',function(event){const type=this.elements.adjustment_type.value;const amount=this.elements.adjustment_amount.value;const reason=this.elements.adjustment_reason.value.trim();const action=type==='deduct'?'deduct':'add';if(!confirm('Confirm '+action+' KES '+amount+'?\nReason: '+reason)){event.preventDefault();event.stopImmediatePropagation();return;}const button=this.querySelector('button');button.disabled=true;button.textContent='Applying...';}));
})();
</script>
