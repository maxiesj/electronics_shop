<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

$allowed_roles = ['admin', 'super_admin', 'staff', 'cashier', 'auditor', 'supervissoor', 'cleaner'];
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
$role = '';
if ($user_id > 0) {
    $role_lookup = $conn->prepare("SELECT LOWER(TRIM(r.role_name)) AS role_name
                                  FROM users u
                                  INNER JOIN roles r ON r.id = u.role_id
                                  WHERE u.id = ?
                                    AND LOWER(COALESCE(u.account_status, 'active')) = 'active'
                                  LIMIT 1");
    if ($role_lookup) {
        $role_lookup->bind_param('i', $user_id);
        if ($role_lookup->execute()) {
            $role_row = $role_lookup->get_result()->fetch_assoc();
            $role = strtolower(trim((string)($role_row['role_name'] ?? '')));
        }
        $role_lookup->close();
    }
}

if (in_array($role, $allowed_roles, true)) {
    $_SESSION['role'] = $role;
}

if ($user_id <= 0 || !in_array($role, $allowed_roles, true)) {
    header('Location: login.php?msg=err_unauthorized_access');
    exit;
}

if (empty($_SESSION['account_password_csrf'])) {
    $_SESSION['account_password_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = isset($_GET['password']) && $_GET['password'] === 'updated'
    ? 'Your password was changed successfully. Use the new password for your next sign-in.'
    : '';

if(!isset($_SESSION['account_password_attempts']))$_SESSION['account_password_attempts']=0;
if(!isset($_SESSION['account_password_lockout']))$_SESSION['account_password_lockout']=0;
if($_SESSION['account_password_lockout']&&time()>=$_SESSION['account_password_lockout']){$_SESSION['account_password_attempts']=0;$_SESSION['account_password_lockout']=0;}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['change_password'])){
    $csrf=(string)($_POST['csrf_token']??'');$current_password=(string)($_POST['current_password']??'');$new_password=(string)($_POST['new_password']??'');$confirm_password=(string)($_POST['confirm_password']??'');
    if(!hash_equals($_SESSION['account_password_csrf'],$csrf))$error='Your security form expired. Refresh the page and try again.';
    elseif($_SESSION['account_password_lockout']&&time()<$_SESSION['account_password_lockout'])$error='Too many incorrect current-password attempts. Try again in '.($_SESSION['account_password_lockout']-time()).' seconds.';
    elseif($new_password!==$confirm_password)$error='The new password and confirmation do not match.';
    elseif(strlen($new_password)<8||!preg_match('/[A-Z]/',$new_password)||!preg_match('/[a-z]/',$new_password)||!preg_match('/\d/',$new_password))$error='Use at least 8 characters with an uppercase letter, lowercase letter, and number.';
    else{
        $conn->begin_transaction();
        try{
            $lookup=$conn->prepare("SELECT password,fullname FROM users WHERE id=? AND LOWER(COALESCE(account_status,'active'))='active' LIMIT 1 FOR UPDATE");if(!$lookup)throw new RuntimeException('Account lookup preparation failed.');
            $lookup->bind_param('i',$user_id);if(!$lookup->execute())throw new RuntimeException('Account lookup failed.');$account=$lookup->get_result()->fetch_assoc();$lookup->close();
            if(!$account||!password_verify($current_password,(string)$account['password'])){
                $conn->rollback();$_SESSION['account_password_attempts']++;
                if($_SESSION['account_password_attempts']>=5){$_SESSION['account_password_lockout']=time()+60;$error='Too many incorrect current-password attempts. Try again in 60 seconds.';}else$error='The current password is incorrect. '.(5-$_SESSION['account_password_attempts']).' attempts remaining.';
            }elseif(password_verify($new_password,(string)$account['password'])){$conn->rollback();$error='The new password must be different from the current password.';}
            else{
                $hash=password_hash($new_password,PASSWORD_DEFAULT);$update=$conn->prepare('UPDATE users SET password=?,reset_token_hash=NULL,reset_token_expires_at=NULL WHERE id=?');if(!$update)throw new RuntimeException('Password update preparation failed.');$update->bind_param('si',$hash,$user_id);if(!$update->execute()||$update->affected_rows!==1)throw new RuntimeException('Password update failed.');$update->close();
                $name=(string)($account['fullname']?:($_SESSION['fullname']??'Account user'));$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$details='Account password changed by the account owner via IP: '.$ip.'. Unused password reset tokens invalidated.';$log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Password Change',?)");if(!$log)throw new RuntimeException('Audit preparation failed.');$log->bind_param('iss',$user_id,$name,$details);if(!$log->execute())throw new RuntimeException('Audit logging failed.');$log->close();
                $conn->commit();$_SESSION['account_password_attempts']=0;$_SESSION['account_password_lockout']=0;$_SESSION['account_password_csrf']=bin2hex(random_bytes(32));session_regenerate_id(true);header('Location: account_security.php?password=updated');exit;
            }
        }catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}error_log('Account password change failed: '.$e->getMessage());$error='The password could not be changed. Please try again.';}
    }
}
$is_admin = in_array($role, ['admin', 'super_admin'], true);
$back_url = $is_admin ? 'admin/dashboard.php?view=dashboard_overview.php' : 'staff/staff_dashboard.php';
$account_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Account User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>Account Security | ADONAK Electronics</title>
<link rel="icon" type="image/jpeg" href="uploads/logo.jpg">
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#1f2937;font-family:ui-sans-serif,system-ui,sans-serif}
nav{min-height:68px;padding:14px max(24px,calc((100vw - 880px)/2));display:flex;align-items:center;justify-content:space-between;gap:18px;background:#111827;color:#fff}
.brand{color:#f97316;font-weight:900}.back{color:#d1d5db;text-decoration:none;font-size:12px;font-weight:800}.back:hover{color:#fff}
main{width:min(680px,calc(100% - 32px));margin:42px auto;padding:26px;background:#fff;border:1px solid #e2e8f0;border-radius:13px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.security-heading{display:flex;gap:14px;align-items:flex-start;padding-bottom:20px;border-bottom:1px solid #e2e8f0}.icon{width:43px;height:43px;display:grid;place-items:center;border-radius:10px;background:#fff7ed;font-size:21px}.security-heading h1{margin:0;color:#0f172a;font-size:22px}.security-heading p{margin:5px 0 0;color:#64748b;font-size:12px;line-height:1.5}
.alert{margin:18px 0 0;padding:11px 13px;border-radius:7px;font-size:12px;font-weight:700}.success{color:#047857;background:#d1fae5;border:1px solid #a7f3d0}.error{color:#b91c1c;background:#fee2e2;border:1px solid #fecaca}
form{display:grid;gap:15px;margin-top:20px}.field{display:grid;gap:6px}.field label{font-size:10px;font-weight:900;color:#475569;text-transform:uppercase;letter-spacing:.04em}.field input{width:100%;min-height:43px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:7px;font:inherit;font-size:13px;outline:none}.field input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.password-wrap{position:relative}.password-wrap input{padding-right:58px}.password-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#2563eb;font-size:11px;font-weight:800;cursor:pointer}
.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.rules{margin:0;padding:12px 12px 12px 28px;border-radius:7px;background:#f8fafc;color:#64748b;font-size:11px;line-height:1.7}
.submit{min-height:43px;border:0;border-radius:7px;background:#c2410c;color:#fff;font-size:11px;font-weight:900;text-transform:uppercase;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}.submit:disabled{cursor:wait;opacity:.82}.password-button-spinner{display:none;width:15px;height:15px;margin-right:8px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:passwordButtonSpin .7s linear infinite}.submit.is-loading .password-button-spinner{display:inline-block}@keyframes passwordButtonSpin{to{transform:rotate(360deg)}}.submit:hover{background:#9a3412}.meta{margin:18px 0 0;text-align:center;color:#94a3b8;font-size:10px}
@media(max-width:600px){nav{padding:14px 16px;flex-direction:column}.two{grid-template-columns:1fr}main{margin:20px auto;padding:18px}}
</style>
</head>
<body>
<nav><span class="brand">&#128274; ADONAK ACCOUNT SECURITY</span><a class="back" href="<?= htmlspecialchars($back_url); ?>">&#8592; Back to Dashboard</a></nav>
<main>
<div class="security-heading"><div class="icon">&#128272;</div><div><h1>Change Password</h1><p>Signed in as <?= htmlspecialchars($account_name); ?>. Verify your current password before replacing it.</p></div></div>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST" action="account_security.php" autocomplete="off" data-password-form>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['account_password_csrf']); ?>">
<div class="field"><label for="current_password">Current password</label><div class="password-wrap"><input id="current_password" name="current_password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-toggle="current_password">Show</button></div></div>
<div class="two">
<div class="field"><label for="new_password">New password</label><div class="password-wrap"><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-toggle="new_password">Show</button></div></div>
<div class="field"><label for="confirm_password">Confirm new password</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-toggle="confirm_password">Show</button></div></div>
</div>
<ul class="rules"><li>At least 8 characters</li><li>Include uppercase, lowercase, and numeric characters</li><li>Must be different from the current password</li></ul>
<button class="submit" type="submit" name="change_password" data-password-submit><span class="password-button-spinner" aria-hidden="true"></span><span class="password-button-label">Update Password</span></button>
</form>
<p class="meta">Changing your password also invalidates any unused password-reset link.</p>
</main>
<script>document.querySelectorAll('[data-toggle]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.dataset.toggle);var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});</script>
<script>window.ADONAK_SESSION_EXPIRE_URL="session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="session_keepalive.php";</script>
<script src="js/session-idle.js"></script>
<script src="js/password-submit.js"></script>
</body>
</html>