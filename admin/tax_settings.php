<?php
require_once dirname(__FILE__) . '/../session_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include_once dirname(__FILE__) . '/../db.php';
if (!verifyWorkspaceClearance('tax_settings.php')) {
    header('Location: ../login.php');
    exit;
}
if (empty($_SESSION['tax_settings_csrf'])) $_SESSION['tax_settings_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['tax_settings_csrf'];
$msg=$err='';
$current=16.00;
$result=$conn->query('SELECT setting_value FROM system_settings WHERE setting_key=\'tax_rate\' LIMIT 1');
if ($result && $row=$result->fetch_assoc()) $current=(float)$row['setting_value'];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['execute_tax_update'])) {
    $raw=trim((string)($_POST['vat_rate_value'] ?? ''));
    $valid=preg_match('/^(?:0|[1-9]\d?)(?:\.\d{1,2})?$|^100(?:\.0{1,2})?$/',$raw)===1;
    $rate=$valid ? round((float)$raw,2) : -1;
    if (!hash_equals($csrf,(string)($_POST['csrf_token'] ?? ''))) {
        $err='Your security token expired. Refresh and try again.';
    } elseif (!$valid || $rate<0 || $rate>100) {
        $err='Enter a valid tax rate from 0.00% to 100.00%.';
    } elseif (abs($rate-$current)<0.001) {
        $err='That tax rate is already active.';
    } else {
        $conn->begin_transaction();
        try {
            $old=number_format($current,2,'.','');
            $new=number_format($rate,2,'.','');
            $key='tax_rate_archived_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
            $stmt=$conn->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES (?,?)');
            $stmt->bind_param('ss',$key,$old);
            if (!$stmt->execute()) throw new RuntimeException('Archive failed.');
            $stmt->close();
            $sql='INSERT INTO system_settings(setting_key,setting_value) VALUES (\'tax_rate\',' . chr(63) . ') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)';
            $stmt=$conn->prepare($sql);
            $stmt->bind_param('s',$new);
            if (!$stmt->execute()) throw new RuntimeException('Setting update failed.');
            $stmt->close();
            $details='Global VAT rate changed from '.$old.'% to '.$new.'%. Future orders only; historical order tax values remain unchanged.';
            $staff_id=(int)($_SESSION['user_id'] ?? 0);
            $staff_name=(string)($_SESSION['fullname'] ?? 'Unknown operator');
            $sql='INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES (' . implode(',',array_fill(0,4,chr(63))) . ')';
            $stmt=$conn->prepare($sql);
            $action='System Update';
            $stmt->bind_param('isss',$staff_id,$staff_name,$action,$details);
            if (!$stmt->execute()) throw new RuntimeException('Audit failed.');
            $stmt->close();
            $conn->commit();
            $current=$rate;
            $_SESSION['tax_settings_csrf']=bin2hex(random_bytes(32));
            $csrf=$_SESSION['tax_settings_csrf'];
            $msg='Tax rate updated to '.number_format($rate,2).'%.';
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Tax update failed: '.$e->getMessage());
            $err='The tax rate could not be updated. No setting was changed.';
        }
    }
}
$history=[];
$sql='SELECT setting_key,setting_value FROM system_settings';
$sql .= ' WHERE setting_key LIKE \'tax_rate_archived_%\' ORDER BY setting_key DESC LIMIT 20';
$result=$conn->query($sql);
if ($result) while($row=$result->fetch_assoc()) {
    $label='Historical snapshot';
    if (preg_match('/tax_rate_archived_(\d{14})/',$row['setting_key'],$match)) {
        $parsed=DateTimeImmutable::createFromFormat('YmdHis',$match[1]);
        if ($parsed) $label=$parsed->format('d M Y, h:i A');
    } elseif (preg_match('/tax_rate_archived_(\d{10})$/',$row['setting_key'],$match)) {
        $parsed=(new DateTimeImmutable('@'.$match[1]))->setTimezone(new DateTimeZone('Africa/Nairobi'));
        $label=$parsed->format('d M Y, h:i A');
    }
    $history[]=['date'=>$label,'rate'=>(float)$row['setting_value']];
}
$last_changed=$history[0]['date'] ?? 'No recorded change yet';
foreach ($history as $index=>$item) {
    $history[$index]['new_rate']=$index===0 ? $current : $history[$index-1]['rate'];
}
?>
<style>
.tax-settings{font-family:system-ui;color:#172033}.tax-alert{padding:12px;border-radius:9px;margin-bottom:14px;background:#ecfdf5;color:#047857}.tax-alert.error{background:#fef2f2;color:#b91c1c}.tax-head p,.tax-note{color:#64748b}.tax-current{padding:18px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:12px;margin:18px 0}.tax-current small,.tax-current strong{display:block}.tax-current strong{font-size:28px;margin-top:5px}.tax-form{display:flex;gap:10px;align-items:end;padding:20px;border:1px solid #e2e8f0;border-radius:12px;background:white}.tax-form label{font-weight:800}.tax-form input{display:block;margin-top:6px;padding:11px;border:1px solid #cbd5e1;border-radius:8px;width:140px}.tax-form button{padding:11px 16px;border:0;border-radius:8px;background:#4f46e5;color:white;font-weight:800}.tax-warning{margin:14px 0;padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:9px;color:#92400e}.tax-history{margin-top:22px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}.tax-row{display:flex;justify-content:space-between;gap:15px;padding:13px 16px;border-bottom:1px solid #e2e8f0}.tax-row:first-child{background:#f8fafc;font-size:11px;text-transform:uppercase;font-weight:800;color:#64748b}.tax-row:last-child{border-bottom:0}@media(max-width:560px){.tax-form{align-items:stretch;flex-direction:column}.tax-form input,.tax-form button{width:100%;box-sizing:border-box}}
</style>
<style>
.tax-settings{max-width:1180px;margin:0 auto}.tax-head{margin-bottom:20px}.tax-head h2{margin:0 0 7px;font-size:26px;letter-spacing:-.02em}.tax-head p{margin:0;line-height:1.55}.tax-primary{display:grid;grid-template-columns:minmax(260px,.75fr) minmax(420px,1.55fr);gap:16px;align-items:stretch}.tax-current{position:relative;margin:0;padding:22px 24px;overflow:hidden;background:linear-gradient(135deg,#eef6ff 0%,#e6f0ff 100%);border-color:#bdd7ff;box-shadow:0 8px 22px rgba(37,99,235,.08)}.tax-current:after{content:'%';position:absolute;right:18px;bottom:-29px;font-size:112px;font-weight:900;color:rgba(37,99,235,.07);line-height:1}.tax-current small{font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.055em;font-size:10px}.tax-current strong{position:relative;z-index:1;font-size:36px;color:#1d4ed8}.tax-current span{position:relative;z-index:1;display:block;margin-top:7px;color:#64748b;font-size:12px}.tax-form{position:relative;display:grid;grid-template-columns:minmax(150px,190px) auto;align-content:center;justify-content:start;gap:10px 12px;padding:22px 24px;box-shadow:0 8px 22px rgba(15,23,42,.045)}.tax-form h3{grid-column:1/-1;margin:0;font-size:17px}.tax-form p{grid-column:1/-1;margin:0 0 5px;color:#64748b;font-size:12px}.tax-form label{font-size:12px;color:#475569}.tax-form input{width:100%;box-sizing:border-box;font-size:16px;font-weight:800}.tax-form button{align-self:end;min-height:43px;box-shadow:0 6px 14px rgba(79,70,229,.2);cursor:pointer}.tax-form button:hover{background:#4338ca;transform:translateY(-1px)}.tax-warning{display:flex;gap:10px;align-items:flex-start;margin:16px 0 26px;padding:13px 15px;background:#fffdf5;color:#854d0e}.tax-warning-icon{flex:0 0 auto;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#fef3c7;font-weight:900}.tax-settings>h3{margin:0 0 5px;font-size:19px}.tax-note{margin:0 0 13px;line-height:1.5}.tax-history{margin-top:0;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.035)}.tax-row{align-items:center;padding:14px 18px}.tax-row:not(:first-child):hover{background:#f8fafc}.tax-rate-badge{display:inline-flex;min-width:72px;justify-content:center;padding:5px 10px;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:900}@media(max-width:760px){.tax-primary{grid-template-columns:1fr}.tax-form{grid-template-columns:1fr auto}}@media(max-width:560px){.tax-form{grid-template-columns:1fr}.tax-form h3,.tax-form p{grid-column:auto}.tax-current strong{font-size:31px}.tax-warning{font-size:13px}.tax-row{padding:12px}}
</style>
<style>
.tax-transition{display:flex;gap:7px;align-items:center}.tax-transition-arrow{color:#94a3b8;font-weight:900}.tax-form button:disabled{cursor:not-allowed;opacity:.5;box-shadow:none;transform:none}.tax-last-change{font-size:11px!important;color:#475569!important}
</style>
<section class='tax-settings'>
<?php if ($err): ?><div class='tax-alert error'><?=htmlspecialchars($err)?></div><?php endif; ?>
<?php if ($msg): ?><div class='tax-alert'><?=htmlspecialchars($msg)?></div><?php endif; ?>
<header class='tax-head'><h2>Global Tax Settings</h2><p>Set the VAT percentage used when new customer orders are created.</p></header>
<div class='tax-primary'>
<div class='tax-current'><small>Active VAT rate</small><strong><?=number_format($current,2)?>%</strong><span>Applied when future orders are created</span><span class='tax-last-change'>Last changed: <?=htmlspecialchars($last_changed)?></span></div>
<form class='tax-form' method='post' action='tax_settings.php'><h3>Change the future-order rate</h3><p>Use up to two decimal places. This will not recalculate existing orders.</p><input type='hidden' name='execute_tax_update' value='1'><input type='hidden' name='csrf_token' value='<?=htmlspecialchars($csrf)?>'><label>New VAT rate (%)<input type='number' name='vat_rate_value' min='0' max='100' step='0.01' value='<?=number_format($current,2,'.','')?>' required></label><button type='submit'>Save new rate</button></form>
</div>
<div class='tax-warning'><span class='tax-warning-icon' aria-hidden='true'>i</span><div><strong>Historical records are preserved.</strong> Existing orders, receipts, invoices and reports keep the rate saved when each order was created.</div></div>
<h3>Rate change history</h3><p class='tax-note'>The most recent change appears first. Operator details are retained in the Workspace Tracker.</p><div class='tax-history'><div class='tax-row'><span>Changed on</span><span>Rate change</span></div>
<?php if ($history): foreach($history as $item): ?><div class='tax-row'><span><?=htmlspecialchars($item['date'])?></span><span class='tax-transition'><strong class='tax-rate-badge'><?=number_format($item['rate'],2)?>%</strong><span class='tax-transition-arrow'>→</span><strong class='tax-rate-badge'><?=number_format($item['new_rate'],2)?>%</strong></span></div><?php endforeach; else: ?><div class='tax-row'><span>No previous tax rates have been archived.</span></div><?php endif; ?>
</div>
<script>(function(){const form=document.querySelector('.tax-form'),input=form.elements.vat_rate_value,button=form.querySelector('button'),active=<?=json_encode((float)$current)?>;function syncButton(){const value=Number(input.value);button.disabled=!Number.isFinite(value)||value<0||value>100||Math.abs(value-active)<.001;}input.addEventListener('input',syncButton);syncButton();form.addEventListener('submit',function(event){const rate=input.value;if(!confirm('Set the tax rate for future orders to '+rate+'%? Existing orders will not change.')){event.preventDefault();event.stopImmediatePropagation();return;}button.disabled=true;button.textContent='Updating...';});})();</script>
</section>
