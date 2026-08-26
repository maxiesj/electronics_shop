<?php
require_once __DIR__ . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
if (!verifyExplicitWorkspaceClearance('payroll.php')) {
    header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

date_default_timezone_set('Africa/Nairobi');
if (empty($_SESSION['payroll_csrf'])) $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));

$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorName = (string)($_SESSION['fullname'] ?? 'System operator');
$error = '';
$success = '';
$methods = ['Bank Transfer', 'M-Pesa', 'Cash', 'Cheque'];
$isDate = static function ($value) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$value);
    return $d && $d->format('Y-m-d') === $value;
};
$logAction = static function ($type, $details) use ($conn, $actorId, $actorName) {
    $stmt = $conn->prepare("INSERT INTO staff_logs (user_id,staff_name,action_type,action_details) VALUES (?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param('isss', $actorId, $actorName, $type, $details);
        $stmt->execute();
        $stmt->close();
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['payroll_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'This payroll form expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['payroll_action'] ?? '');
        if ($action === 'save_salary') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $salary = round((float)($_POST['monthly_basic_salary'] ?? 0), 2);
            $stmt = $conn->prepare("SELECT u.fullname FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND LOWER(r.role_name)<>'customer' AND LOWER(u.account_status)='active' LIMIT 1");
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $employee = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$employee || $salary <= 0 || $salary > 999999999.99) {
                $error = 'Select an active employee and enter a valid monthly basic salary.';
            } else {
                $stmt = $conn->prepare("INSERT INTO staff_salary_profiles(employee_id,monthly_basic_salary,updated_by,updated_by_name) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE monthly_basic_salary=VALUES(monthly_basic_salary),updated_by=VALUES(updated_by),updated_by_name=VALUES(updated_by_name)");
                $stmt->bind_param('idis', $employeeId, $salary, $actorId, $actorName);
                if ($stmt->execute()) {
                    $success = 'Monthly salary profile saved.';
                    $logAction('Payroll Salary Setup', 'Monthly basic salary updated for ' . $employee['fullname'] . ' to KES ' . number_format($salary, 2, '.', '') . '.');
                } else {
                    $error = 'The salary profile could not be saved.';
                }
                $stmt->close();
            }
        } elseif ($action === 'create_draft') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $periodStart = (string)($_POST['pay_period_start'] ?? '');
            $periodEnd = (string)($_POST['pay_period_end'] ?? '');
            $allowances = round((float)($_POST['allowances'] ?? 0), 2);
            $deductions = round((float)($_POST['deductions'] ?? 0), 2);
            $notes = trim((string)($_POST['notes'] ?? ''));
            $stmt = $conn->prepare("SELECT u.fullname,r.role_name,s.monthly_basic_salary FROM users u JOIN roles r ON r.id=u.role_id JOIN staff_salary_profiles s ON s.employee_id=u.id WHERE u.id=? AND LOWER(r.role_name)<>'customer' AND LOWER(u.account_status)='active' LIMIT 1");
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $employee = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$employee || !$isDate($periodStart) || !$isDate($periodEnd) || date('Y-m-01', strtotime($periodStart)) !== $periodStart || date('Y-m-t', strtotime($periodStart)) !== $periodEnd || $allowances < 0 || $deductions < 0 || strlen($notes) > 500) {
                $error = 'Check the employee, pay period, allowances, deductions, and notes.';
            } else {
                $basic = round((float)$employee['monthly_basic_salary'], 2);
                $gross = round($basic + $allowances, 2);
                $netPay = round($gross - $deductions, 2);
                if ($gross <= 0 || $netPay < 0) {
                    $error = 'Deductions cannot be greater than gross pay.';
                } else {
                    $stmt = $conn->prepare("SELECT id FROM payroll_records WHERE employee_id=? AND pay_period_start=? AND pay_period_end=? AND status<>'voided' LIMIT 1");
                    $stmt->bind_param('iss', $employeeId, $periodStart, $periodEnd);
                    $stmt->execute();
                    $duplicate = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($duplicate) {
                        $error = 'A non-voided payroll record already exists for this employee and pay period.';
                    } else {
                        $status = 'draft';
                        $stmt = $conn->prepare("INSERT INTO payroll_records(employee_id,employee_name,role_name,pay_period_start,pay_period_end,basic_salary,allowances,deductions,gross_pay,net_pay,status,notes,processed_by,processed_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->bind_param('issssdddddssis', $employeeId, $employee['fullname'], $employee['role_name'], $periodStart, $periodEnd, $basic, $allowances, $deductions, $gross, $netPay, $status, $notes, $actorId, $actorName);
                        if ($stmt->execute()) {
                            $recordId = $stmt->insert_id;
                            $success = 'Payroll draft #' . $recordId . ' created.';
                            $logAction('Payroll Draft', 'Payroll draft #' . $recordId . ' created for ' . $employee['fullname'] . '; gross KES ' . number_format($gross, 2, '.', '') . '; net KES ' . number_format($netPay, 2, '.', '') . '.');
                        } else {
                            $error = 'The payroll draft could not be created.';
                        }
                        $stmt->close();
                    }
                }
            }
        } elseif ($action === 'mark_paid') {
            $recordId = (int)($_POST['payroll_id'] ?? 0);
            $paymentDate = (string)($_POST['payment_date'] ?? '');
            $method = (string)($_POST['payment_method'] ?? '');
            $reference = trim((string)($_POST['reference_number'] ?? ''));
            if ($recordId <= 0 || !$isDate($paymentDate) || !in_array($method, $methods, true) || $reference === '' || strlen($reference) > 100) {
                $error = 'Enter a valid payment date, method, and payment reference.';
            } else {
                $stmt = $conn->prepare("UPDATE payroll_records SET status='paid',payment_date=?,payment_method=?,reference_number=?,paid_by=?,paid_by_name=?,paid_at=NOW() WHERE id=? AND status='draft'");
                $stmt->bind_param('sssisi', $paymentDate, $method, $reference, $actorId, $actorName, $recordId);
                $stmt->execute();
                if ($stmt->affected_rows === 1) {
                    $success = 'Payroll #' . $recordId . ' marked as paid.';
                    $logAction('Payroll Paid', 'Payroll #' . $recordId . ' paid on ' . $paymentDate . ' via ' . $method . '; reference ' . $reference . '.');
                } else {
                    $error = 'Only a current draft can be marked as paid.';
                }
                $stmt->close();
            }
        } elseif ($action === 'void') {
            $recordId = (int)($_POST['payroll_id'] ?? 0);
            $reason = trim((string)($_POST['void_reason'] ?? ''));
            if ($recordId <= 0 || strlen($reason) < 5 || strlen($reason) > 255) {
                $error = 'Enter a clear void reason of at least 5 characters.';
            } else {
                $stmt = $conn->prepare("UPDATE payroll_records SET status='voided',voided_by=?,voided_by_name=?,voided_at=NOW(),void_reason=? WHERE id=? AND status IN ('draft','paid')");
                $stmt->bind_param('issi', $actorId, $actorName, $reason, $recordId);
                $stmt->execute();
                if ($stmt->affected_rows === 1) {
                    $success = 'Payroll #' . $recordId . ' voided with its audit history retained.';
                    $logAction('Payroll Voided', 'Payroll #' . $recordId . ' voided. Reason: ' . $reason);
                } else {
                    $error = 'This payroll record is already voided or unavailable.';
                }
                $stmt->close();
            }
        }
    }
}

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-t');
$from = (string)($_GET['date_from'] ?? $defaultFrom);
$to = (string)($_GET['date_to'] ?? $defaultTo);
if (!$isDate($from)) $from = $defaultFrom;
if (!$isDate($to)) $to = $defaultTo;
if ($from > $to) [$from, $to] = [$to, $from];

$employees = [];
$result = $conn->query("SELECT u.id,u.fullname,r.role_name,s.monthly_basic_salary FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN staff_salary_profiles s ON s.employee_id=u.id WHERE LOWER(r.role_name)<>'customer' AND LOWER(u.account_status)='active' ORDER BY u.fullname");
while ($result && $row = $result->fetch_assoc()) $employees[] = $row;

$records = [];
$stmt = $conn->prepare("SELECT * FROM payroll_records WHERE pay_period_start<=? AND pay_period_end>=? ORDER BY id DESC LIMIT 250");
$stmt->bind_param('ss', $to, $from);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $records[] = $row;
$stmt->close();

$summary = ['draft_count'=>0,'paid_count'=>0,'gross_paid'=>0,'net_paid'=>0];
$stmt = $conn->prepare("SELECT SUM(status='draft') draft_count,SUM(status='paid') paid_count,COALESCE(SUM(CASE WHEN status='paid' THEN gross_pay ELSE 0 END),0) gross_paid,COALESCE(SUM(CASE WHEN status='paid' THEN net_pay ELSE 0 END),0) net_paid FROM payroll_records WHERE pay_period_start<=? AND pay_period_end>=?");
$stmt->bind_param('ss', $to, $from);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) $summary = $row;
$stmt->close();
?>
<style>
.payroll{font-family:system-ui;color:#172033}.payroll *{box-sizing:border-box}.payroll-head{display:flex;justify-content:space-between;align-items:end;gap:16px}.payroll-head p{margin:5px 0;color:#64748b}.payroll-filter,.payroll-form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.payroll label{font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase}.payroll input,.payroll select,.payroll textarea{display:block;width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.payroll button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.payroll button.danger{background:#b42318}.payroll-notice{margin:14px 0;padding:12px 14px;border-radius:9px}.payroll-notice.ok{background:#ecfdf5;color:#047857}.payroll-notice.error{background:#fef2f2;color:#b42318}.payroll-panel{margin:16px 0;padding:18px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.payroll-grid{display:grid;grid-template-columns:1fr 2fr;gap:16px}.payroll-fields{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}.payroll-fields .wide{grid-column:1/-1}.payroll-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin:16px 0}.payroll-card{padding:15px;border:1px solid #bfdbfe;border-radius:11px;background:#eff6ff}.payroll-card strong,.payroll-card small{display:block}.payroll-card strong{font-size:19px;margin-top:5px}.payroll-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:11px}.payroll-table{width:100%;border-collapse:collapse;min-width:1100px}.payroll-table th,.payroll-table td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.payroll-table th{background:#f8fafc;font-size:11px;color:#64748b;text-transform:uppercase}.payroll-inline{display:flex;gap:5px;align-items:end;min-width:460px}.payroll-inline input,.payroll-inline select{padding:7px}.status{font-weight:800;text-transform:capitalize}.status.paid{color:#047857}.status.draft{color:#b45309}.status.voided{color:#b42318}@media(max-width:900px){.payroll-grid{grid-template-columns:1fr}.payroll-fields,.payroll-cards{grid-template-columns:1fr 1fr}}@media(max-width:560px){.payroll-fields,.payroll-cards{grid-template-columns:1fr}}
</style>
<section class="payroll">
<header class="payroll-head"><div><h2>Payroll</h2><p>Salary setup, pay-period drafts, payment accountability, and retained audit history.</p></div><form class="payroll-filter" method="get" action="payroll.php"><label>From<input type="date" name="date_from" value="<?=htmlspecialchars($from)?>"></label><label>To<input type="date" name="date_to" value="<?=htmlspecialchars($to)?>"></label><button>Apply</button></form></header>
<?php if($error):?><div class="payroll-notice error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($success):?><div class="payroll-notice ok"><?=htmlspecialchars($success)?></div><?php endif;?>
<div class="payroll-cards"><article class="payroll-card"><small>Active employees</small><strong><?=count($employees)?></strong></article><article class="payroll-card"><small>Draft payrolls</small><strong><?=number_format((int)$summary['draft_count'])?></strong></article><article class="payroll-card"><small>Paid gross salary cost</small><strong>KES <?=number_format((float)$summary['gross_paid'],2)?></strong></article><article class="payroll-card"><small>Paid net cash</small><strong>KES <?=number_format((float)$summary['net_paid'],2)?></strong></article></div>
<div class="payroll-grid">
<section class="payroll-panel"><h3>1. Monthly salary setup</h3><form method="post" action="payroll.php" class="payroll-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['payroll_csrf'])?>"><input type="hidden" name="payroll_action" value="save_salary"><label style="flex:1 1 230px">Employee<select name="employee_id" required><option value="">Select employee</option><?php foreach($employees as $employee):?><option value="<?=(int)$employee['id']?>"><?=htmlspecialchars($employee['fullname'].' - '.$employee['role_name'])?><?=$employee['monthly_basic_salary']!==null?' (KES '.number_format((float)$employee['monthly_basic_salary'],2).')':''?></option><?php endforeach;?></select></label><label style="flex:1 1 180px">Monthly basic salary<input type="number" name="monthly_basic_salary" min="0.01" step="0.01" required></label><button>Save salary</button></form></section>
<section class="payroll-panel"><h3>2. Create payroll draft</h3><form method="post" action="payroll.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['payroll_csrf'])?>"><input type="hidden" name="payroll_action" value="create_draft"><div class="payroll-fields"><label>Employee<select name="employee_id" required><option value="">Select salary-profile employee</option><?php foreach($employees as $employee):if($employee['monthly_basic_salary']===null)continue;?><option value="<?=(int)$employee['id']?>"><?=htmlspecialchars($employee['fullname'])?> - KES <?=number_format((float)$employee['monthly_basic_salary'],2)?></option><?php endforeach;?></select></label><label>Period start<input type="date" name="pay_period_start" value="<?=date('Y-m-01')?>" required></label><label>Period end<input type="date" name="pay_period_end" value="<?=date('Y-m-t')?>" required></label><label>Allowances<input type="number" name="allowances" value="0.00" min="0" step="0.01" required></label><label>Deductions<input type="number" name="deductions" value="0.00" min="0" step="0.01" required></label><label class="wide">Notes<textarea name="notes" maxlength="500" rows="2" placeholder="Optional payroll notes"></textarea></label></div><button style="margin-top:10px">Create draft</button></form></section>
</div>
<section class="payroll-panel"><h3>Payroll records</h3><div class="payroll-table-wrap"><table class="payroll-table"><thead><tr><th>ID / Employee</th><th>Pay period</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Gross cost</th><th>Net pay</th><th>Status / payment</th><th>Actions</th></tr></thead><tbody>
<?php if($records):foreach($records as $record):?><tr><td><strong>#<?=(int)$record['id']?> <?=htmlspecialchars($record['employee_name'])?></strong><br><small><?=htmlspecialchars($record['role_name'])?></small></td><td><?=date('d M Y',strtotime($record['pay_period_start']))?><br>to <?=date('d M Y',strtotime($record['pay_period_end']))?></td><td>KES <?=number_format((float)$record['basic_salary'],2)?></td><td>KES <?=number_format((float)$record['allowances'],2)?></td><td>KES <?=number_format((float)$record['deductions'],2)?></td><td><strong>KES <?=number_format((float)$record['gross_pay'],2)?></strong></td><td><strong>KES <?=number_format((float)$record['net_pay'],2)?></strong></td><td><span class="status <?=htmlspecialchars($record['status'])?>"><?=htmlspecialchars($record['status'])?></span><?php if($record['status']==='paid'):?><br><small><?=htmlspecialchars($record['payment_date'].' / '.$record['payment_method'].' / '.$record['reference_number'])?><br>Paid by <?=htmlspecialchars((string)$record['paid_by_name'])?></small><?php elseif($record['status']==='voided'):?><br><small><?=htmlspecialchars((string)$record['void_reason'])?></small><?php endif;?></td><td><?php if($record['status']==='draft'):?><form method="post" action="payroll.php" class="payroll-inline"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['payroll_csrf'])?>"><input type="hidden" name="payroll_action" value="mark_paid"><input type="hidden" name="payroll_id" value="<?=(int)$record['id']?>"><label>Paid on<input type="date" name="payment_date" value="<?=date('Y-m-d')?>" required></label><label>Method<select name="payment_method" required><?php foreach($methods as $method):?><option><?=htmlspecialchars($method)?></option><?php endforeach;?></select></label><label>Reference<input name="reference_number" maxlength="100" required></label><button>Mark paid</button></form><?php endif;?><?php if($record['status']!=='voided'):?><form method="post" action="payroll.php" class="payroll-inline" style="margin-top:7px"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['payroll_csrf'])?>"><input type="hidden" name="payroll_action" value="void"><input type="hidden" name="payroll_id" value="<?=(int)$record['id']?>"><label style="flex:1">Void reason<input name="void_reason" minlength="5" maxlength="255" required></label><button class="danger">Void</button></form><?php endif;?></td></tr><?php endforeach;else:?><tr><td colspan="9" style="text-align:center;padding:25px;color:#64748b">No payroll records overlap this period.</td></tr><?php endif;?>
</tbody></table></div><p style="font-size:12px;color:#64748b">Gross salary cost = basic salary + allowances. Deductions reduce net pay to the employee but do not reduce the shop's gross salary expense.</p></section>
</section>