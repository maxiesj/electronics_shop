<?php
require_once __DIR__ . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
if (!verifyWorkspaceClearance('operating_expenses.php')) {
    if (!headers_sent()) header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}

if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!headers_sent()) header('Pragma: no-cache');
if (!headers_sent()) header('Expires: 0');
if (!headers_sent()) header('X-Content-Type-Options: nosniff');
if (!headers_sent()) header('X-Frame-Options: DENY');
if (!headers_sent()) header('Referrer-Policy: same-origin');

date_default_timezone_set('Africa/Nairobi');
if (empty($_SESSION['expense_csrf'])) $_SESSION['expense_csrf'] = bin2hex(random_bytes(32));

$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorName = (string)($_SESSION['fullname'] ?? 'System operator');
$categories = ['Transport','Rent','Utilities','Internet & Airtime','Delivery & Logistics','Repairs & Maintenance','Marketing','Licences & Fees','Office Supplies','Security','Insurance','Bank Charges','Other'];
$methods = ['Bank Transfer','M-Pesa','Cash','Cheque','Card'];
$error = '';
$success = '';

$isDate = static function ($value) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$value);
    return $d && $d->format('Y-m-d') === $value;
};

$parseMoney = static function ($value) {
    $raw = trim((string)$value);
    if (!preg_match('/^(?:0|[1-9]\d{0,8})(?:\.\d{1,2})?$/', $raw)) return null;
    [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
    $cents = ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
    if ($cents <= 0 || $cents > 999999999999) return null;
    return $cents;
};

$logAction = static function ($type, $details) use ($conn, $actorId, $actorName) {
    $stmt = $conn->prepare("INSERT INTO staff_logs (user_id,staff_name,action_type,action_details) VALUES (?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param('isss', $actorId, $actorName, $type, $details);
        $stmt->execute();
        $stmt->close();
    }
};

$findReferenceCollision = static function ($reference) use ($conn) {
    $checks = [
        [
            'sql' => "SELECT id FROM operating_expenses WHERE reference_number IS NOT NULL AND TRIM(reference_number)<>'' AND UPPER(TRIM(reference_number))=? LIMIT 1",
            'message' => 'That payment reference is already attached to another operating expense.'
        ],
        [
            'sql' => "SELECT id FROM payroll_records WHERE reference_number IS NOT NULL AND TRIM(reference_number)<>'' AND UPPER(TRIM(reference_number))=? LIMIT 1",
            'message' => 'That payment reference is already used in payroll.'
        ],
        [
            'sql' => "SELECT id FROM payments WHERE transaction_code IS NOT NULL AND TRIM(transaction_code)<>'' AND UPPER(TRIM(transaction_code))=? LIMIT 1",
            'message' => 'That payment reference is already used in a customer/sales payment.'
        ],
        [
            'sql' => "SELECT id FROM refund_logs WHERE reversal_reference IS NOT NULL AND TRIM(reversal_reference)<>'' AND UPPER(TRIM(reversal_reference))=? LIMIT 1",
            'message' => 'That payment reference is already used in a refund or reversal record.'
        ]
    ];

    foreach ($checks as $check) {
        $stmt = $conn->prepare($check['sql']);
        if (!$stmt) return 'The payment reference could not be verified safely. Please try again.';
        $stmt->bind_param('s', $reference);
        if (!$stmt->execute()) {
            $stmt->close();
            return 'The payment reference could not be verified safely. Please try again.';
        }
        $match = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($match) return $check['message'];
    }
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['expense_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'This expense form expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['expense_action'] ?? '');

        if ($action === 'add') {
            $expenseDate = (string)($_POST['expense_date'] ?? '');
            $category = (string)($_POST['category'] ?? '');
            $amountCents = $parseMoney($_POST['amount'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $method = (string)($_POST['payment_method'] ?? '');
            $reference = strtoupper(trim((string)($_POST['reference_number'] ?? '')));

            if (!$isDate($expenseDate) || $expenseDate > date('Y-m-d') || !in_array($category, $categories, true) || $amountCents === null || strlen($description) < 3 || strlen($description) > 500 || !in_array($method, $methods, true) || !preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,99}$/', $reference)) {
                $error = 'Check the expense date, category, amount, description, payment method, and reference.';
            } else {
                $collision = $findReferenceCollision($reference);
                if ($collision !== null) {
                    $error = $collision;
                } else {
                    $amount = $amountCents / 100;
                    $stmt = $conn->prepare("INSERT INTO operating_expenses(expense_date,category,amount,description,payment_method,reference_number,recorded_by,recorded_by_name) VALUES (?,?,?,?,?,?,?,?)");
                    if (!$stmt) {
                        $error = 'The expense could not be prepared safely. Please try again.';
                    } else {
                        $stmt->bind_param('ssdsssis', $expenseDate, $category, $amount, $description, $method, $reference, $actorId, $actorName);
                        try {
                            $executed = $stmt->execute();
                            if ($executed) {
                                $expenseId = $stmt->insert_id;
                                $success = 'Expense #' . $expenseId . ' recorded.';
                                $logAction('Operating Expense', 'Expense #' . $expenseId . ' recorded: ' . $category . ', KES ' . number_format($amount, 2, '.', '') . ', reference ' . $reference . '.');
                            } else {
                                $error = 'The expense could not be recorded.';
                            }
                        } catch (Throwable $e) {
                            error_log('Operating expense insert failed: ' . $e->getMessage());
                            $error = 'The expense was not recorded. No financial record was changed.';
                        }
                        $stmt->close();
                    }
                }
            }
        } elseif ($action === 'void') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $reason = trim((string)($_POST['void_reason'] ?? ''));
            if ($expenseId <= 0 || strlen($reason) < 5 || strlen($reason) > 255) {
                $error = 'Enter a clear void reason of at least 5 characters.';
            } else {
                $stmt = $conn->prepare("UPDATE operating_expenses SET status='voided',voided_by=?,voided_by_name=?,voided_at=NOW(),void_reason=? WHERE id=? AND status='active'");
                if (!$stmt) {
                    $error = 'The expense could not be prepared for voiding safely.';
                } else {
                    $stmt->bind_param('issi', $actorId, $actorName, $reason, $expenseId);
                    try {
                        $stmt->execute();
                        if ($stmt->affected_rows === 1) {
                            $success = 'Expense #' . $expenseId . ' voided with its audit history retained.';
                            $logAction('Expense Voided', 'Expense #' . $expenseId . ' voided. Reason: ' . $reason);
                        } else {
                            $error = 'This expense is already voided or unavailable.';
                        }
                    } catch (Throwable $e) {
                        error_log('Operating expense void failed: ' . $e->getMessage());
                        $error = 'The expense could not be voided. No status was changed.';
                    }
                    $stmt->close();
                }
            }
        } else {
            $error = 'Invalid expense action.';
        }

        if ($success !== '') $_SESSION['expense_csrf'] = bin2hex(random_bytes(32));
    }
}

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $candidateFrom = (string)($_GET['date_from'] ?? $defaultFrom);
    $candidateTo = (string)($_GET['date_to'] ?? $defaultTo);
    if ($isDate($candidateFrom) && $isDate($candidateTo)) {
        if ($candidateFrom > $candidateTo) [$candidateFrom, $candidateTo] = [$candidateTo, $candidateFrom];
        $_SESSION['expense_date_from'] = $candidateFrom;
        $_SESSION['expense_date_to'] = $candidateTo;
    }
}
$from = (string)($_SESSION['expense_date_from'] ?? $defaultFrom);
$to = (string)($_SESSION['expense_date_to'] ?? $defaultTo);
if (!$isDate($from)) $from = $defaultFrom;
if (!$isDate($to)) $to = $defaultTo;
if ($from > $to) [$from, $to] = [$to, $from];

$records = [];
$stmt = $conn->prepare("SELECT * FROM operating_expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC,id DESC LIMIT 500");
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $records[] = $row;
$stmt->close();

$summary = ['active_count'=>0,'active_total'=>0];
$stmt = $conn->prepare("SELECT SUM(status='active') active_count,COALESCE(SUM(CASE WHEN status='active' THEN amount ELSE 0 END),0) active_total FROM operating_expenses WHERE expense_date BETWEEN ? AND ?");
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $summary = $row;
$stmt->close();

$breakdown = [];
$stmt = $conn->prepare("SELECT category,COUNT(*) item_count,SUM(amount) total FROM operating_expenses WHERE status='active' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $breakdown[] = $row;
$stmt->close();
?>
<style>
.expenses{font-family:system-ui;color:#172033}.expenses *{box-sizing:border-box}.expenses-head{display:flex;justify-content:space-between;align-items:end;gap:16px}.expenses-head p{margin:5px 0;color:#64748b}.expenses-filter,.expense-form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.expenses label{font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase}.expenses input,.expenses select,.expenses textarea{display:block;width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.expenses button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.expenses button.danger{background:#b42318}.expense-notice{margin:14px 0;padding:12px 14px;border-radius:9px}.expense-notice.ok{background:#ecfdf5;color:#047857}.expense-notice.error{background:#fef2f2;color:#b42318}.expense-panel{margin:16px 0;padding:18px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.expense-fields{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}.expense-fields .wide{grid-column:span 2}.expense-cards{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin:16px 0}.expense-card{padding:16px;border:1px solid #fed7aa;border-radius:11px;background:#fff7ed}.expense-card strong,.expense-card small{display:block}.expense-card strong{font-size:20px;margin-top:5px}.expense-layout{display:grid;grid-template-columns:2fr 1fr;gap:16px}.expense-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:11px}.expense-table{width:100%;border-collapse:collapse;min-width:940px}.expense-table th,.expense-table td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.expense-table th{background:#f8fafc;font-size:11px;color:#64748b;text-transform:uppercase}.expense-inline{display:flex;gap:5px;align-items:end;min-width:330px}.status{font-weight:800;text-transform:capitalize}.status.active{color:#047857}.status.voided{color:#b42318}.breakdown{width:100%;border-collapse:collapse}.breakdown td{padding:9px;border-bottom:1px solid #e2e8f0}@media(max-width:900px){.expense-layout{grid-template-columns:1fr}.expense-fields{grid-template-columns:1fr 1fr}}@media(max-width:560px){.expenses-head{align-items:stretch;flex-direction:column}.expense-fields,.expense-cards{grid-template-columns:1fr}.expense-fields .wide{grid-column:auto}}
</style>
<section class="expenses">
<header class="expenses-head"><div><h2>Operating Expenses</h2><p>Transport, rent, utilities and other non-salary shop costs. Salary is recorded only in Payroll.</p></div><form class="expenses-filter" method="get" action="operating_expenses.php"><label>From<input type="date" name="date_from" value="<?=htmlspecialchars($from)?>"></label><label>To<input type="date" name="date_to" value="<?=htmlspecialchars($to)?>"></label><button>Apply</button></form></header>
<?php if($error):?><div class="expense-notice error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($success):?><div class="expense-notice ok"><?=htmlspecialchars($success)?></div><?php endif;?>
<div class="expense-cards"><article class="expense-card"><small>Active expenses in period</small><strong><?=number_format((int)$summary['active_count'])?></strong></article><article class="expense-card"><small>Total operating expenses</small><strong>KES <?=number_format((float)$summary['active_total'],2)?></strong></article></div>
<section class="expense-panel"><h3>Record non-salary expense</h3><form method="post" action="operating_expenses.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['expense_csrf'])?>"><input type="hidden" name="expense_action" value="add"><div class="expense-fields"><label>Expense date<input type="date" name="expense_date" value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>" required></label><label>Category<select name="category" required><?php foreach($categories as $category):?><option><?=htmlspecialchars($category)?></option><?php endforeach;?></select></label><label>Amount (KES)<input type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" required></label><label class="wide">Description<textarea name="description" minlength="3" maxlength="500" rows="2" required placeholder="What was paid for?"></textarea></label><label>Payment method<select name="payment_method" required><?php foreach($methods as $method):?><option><?=htmlspecialchars($method)?></option><?php endforeach;?></select></label><label>Receipt / transaction reference<input name="reference_number" minlength="3" maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._/-]{2,99}" title="Checked against expenses, payroll, sales payments, refunds and reversals" required></label></div><button style="margin-top:10px">Record expense</button></form></section>
<div class="expense-layout">
<section class="expense-panel"><h3>Expense register</h3><div class="expense-table-wrap"><table class="expense-table"><thead><tr><th>Date / ID</th><th>Category</th><th>Description</th><th>Amount</th><th>Payment evidence</th><th>Recorded by</th><th>Status / action</th></tr></thead><tbody>
<?php if($records):foreach($records as $record):?><tr><td><?=date('d M Y',strtotime($record['expense_date']))?><br><small>#<?=(int)$record['id']?></small></td><td><?=htmlspecialchars($record['category'])?></td><td><?=htmlspecialchars($record['description'])?></td><td><strong>KES <?=number_format((float)$record['amount'],2)?></strong></td><td><?=htmlspecialchars($record['payment_method'])?><br><small><?=htmlspecialchars($record['reference_number'])?></small></td><td><?=htmlspecialchars($record['recorded_by_name'])?><br><small><?=date('d M Y H:i',strtotime($record['recorded_at']))?></small></td><td><span class="status <?=htmlspecialchars($record['status'])?>"><?=htmlspecialchars($record['status'])?></span><?php if($record['status']==='voided'):?><br><small><?=htmlspecialchars((string)$record['void_reason'])?><br>By <?=htmlspecialchars((string)$record['voided_by_name'])?></small><?php else:?><form method="post" action="operating_expenses.php" class="expense-inline" style="margin-top:7px"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['expense_csrf'])?>"><input type="hidden" name="expense_action" value="void"><input type="hidden" name="expense_id" value="<?=(int)$record['id']?>"><label style="flex:1">Void reason<input name="void_reason" minlength="5" maxlength="255" required></label><button class="danger">Void</button></form><?php endif;?></td></tr><?php endforeach;else:?><tr><td colspan="7" style="text-align:center;padding:25px;color:#64748b">No expenses were recorded in this period.</td></tr><?php endif;?>
</tbody></table></div></section>
<aside class="expense-panel"><h3>Category summary</h3><table class="breakdown"><?php if($breakdown):foreach($breakdown as $row):?><tr><td><?=htmlspecialchars($row['category'])?><br><small><?=number_format((int)$row['item_count'])?> item(s)</small></td><td style="text-align:right"><strong>KES <?=number_format((float)$row['total'],2)?></strong></td></tr><?php endforeach;else:?><tr><td style="color:#64748b">No active expenses in this period.</td></tr><?php endif;?></table></aside>
</div>
</section>
