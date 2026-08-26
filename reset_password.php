<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/db.php';
if(empty($_SESSION['password_reset_csrf']))$_SESSION['password_reset_csrf']=bin2hex(random_bytes(32));
$msg='';$msg_class='';$show_form=false;$token='';
function loadResetUser($conn,$hash,$lock=false){$sql="SELECT id,password FROM users WHERE reset_token_hash=? AND reset_token_expires_at>NOW() AND LOWER(COALESCE(account_status,'active'))='active' LIMIT 1".($lock?' FOR UPDATE':'');$stmt=$conn->prepare($sql);if(!$stmt)return null;$stmt->bind_param('s',$hash);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row;}
if($_SERVER['REQUEST_METHOD']==='GET'){$token=(string)($_GET['token']??'');if(preg_match('/^[a-f0-9]{64}$/',$token)&&loadResetUser($conn,hash('sha256',$token)))$show_form=true;else{$msg='This reset link is invalid or has expired.';$msg_class='error';}}
elseif(isset($_POST['update_password'])){
 $token=(string)($_POST['token']??'');$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
 if(empty($_POST['csrf_token'])||!hash_equals($_SESSION['password_reset_csrf'],(string)$_POST['csrf_token'])){$msg='Your secure form expired. Open the reset link again and retry.';$msg_class='error';}
 elseif(!preg_match('/^[a-f0-9]{64}$/',$token)){$msg='This reset link is invalid or has expired.';$msg_class='error';}
 elseif($password===''||$confirm===''){$msg='Enter and confirm your new password.';$msg_class='error';$show_form=true;}
 elseif($password!==$confirm){$msg='Passwords do not match.';$msg_class='error';$show_form=true;}
 elseif(strlen($password)<8){$msg='Password must be at least 8 characters long.';$msg_class='error';$show_form=true;}
 else{$hash=hash('sha256',$token);$conn->begin_transaction();try{$user=loadResetUser($conn,$hash,true);if(!$user)throw new DomainException('This reset link is invalid or has expired.');if(password_verify($password,(string)$user['password']))throw new DomainException('Choose a password different from your current password.');$newHash=password_hash($password,PASSWORD_DEFAULT);$update=$conn->prepare('UPDATE users SET password=?,reset_token_hash=NULL,reset_token_expires_at=NULL WHERE id=?');if(!$update)throw new RuntimeException('Update preparation failed.');$uid=(int)$user['id'];$update->bind_param('si',$newHash,$uid);if(!$update->execute()||$update->affected_rows!==1)throw new RuntimeException('Password update failed.');$update->close();$conn->commit();$_SESSION['password_reset_csrf']=bin2hex(random_bytes(32));$msg='Your password was updated successfully. You can now log in.';$msg_class='success';}catch(DomainException $e){$conn->rollback();$msg=$e->getMessage();$msg_class='error';$show_form=strpos($msg,'different')!==false;}catch(Throwable $e){$conn->rollback();error_log('Password reset failed: '.$e->getMessage());$msg='Your password could not be updated. Please request a new reset link.';$msg_class='error';}}
}else{$msg='This reset link is invalid or has expired.';$msg_class='error';}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="uploads/logo.jpg">
    <title>ADONAK ELECTRONICS - Create New Password</title>
    <style>
        /* 1. Global Reset & Body Background Canvas */
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; transition: all 0.2s ease; }
        body {
            background-color: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Fixed from height to prevent mobile keyboard interface clipping */
            margin: 0;
            background-image: radial-gradient(circle at top right, #1e293b, #0f172a);
            padding: 16px;
        }
        
        /* 2. Reset Sheet Core Card */
        .reset-card {
            background: #ffffff;
            padding: 45px 35px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border: 1px solid #1e293b;
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }
        h2 { margin-top: 0; text-align: center; color: #0f172a; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
        p { color: #475569; font-size: 14px; margin-bottom: 20px; line-height: 1.5; font-weight: 500; }
        
        /* 3. Form Input Field & Action Control Structures */
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; }
        input[type="password"], input[type="text"] { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; background: #f8fafc; height: 42px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        
        /* Complete Password Override Action Button */
        button { width: 100%; padding: 14px 0; background-color: #3b82f6; color: white; font-weight: bold; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; height: 46px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; }
        button:hover { background-color: #2563eb; transform: translateY(-1px); }
        button:active { transform: translateY(0); }
        
        /* Interactive Toggle Component Box */
        .toggle-group {
            display: flex;
            align-items: center;
            margin: -4px 0 20px 0;
            font-size: 13px;
            color: #475569;
            cursor: pointer;
            font-weight: 600;
            width: fit-content;
            text-transform: none;
        }
        .toggle-group input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
            width: 16px;
            height: 16px;
            margin-bottom: 0;
        }
        
        /* System Transaction Notification Alerts */
        .alert { padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; text-align: center; font-weight: 600; box-sizing: border-box; }
        .alert.success { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        
        /* Secondary Navigation Link Utilities */
        .links { text-align: center; margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 16px; }
        .links a { color: #e67e22; text-decoration: none; font-size: 13px; font-weight: 600; }
        .links a:hover { color: #d35400; text-decoration: underline; }

        /* ==========================================================================
           4. RESPONSIVE MEDIA QUERIES (MOBILE PHONE VIEWPORT OPTIMIZATIONS)
           ========================================================================== */
        @media screen and (max-width: 480px) {
            body { padding: 12px; }
            .reset-card { padding: 30px 20px; margin: 8px; border-radius: 8px; }
            h2 { font-size: 1.25rem; margin-bottom: 16px; padding-bottom: 12px; }
            p { font-size: 13px; }
            
            /* Enlarge inputs for easier mobile thumb interaction targets */
            input[type="password"], input[type="text"] { height: 46px; font-size: 15px; }
            button { height: 48px; font-size: 14px; }
            .toggle-group { font-size: 12px; }
        }
    </style>
<link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<main class="auth-shell">
  <section class="auth-brand-panel" aria-label="Create a secure password">
    <div class="auth-brand"><span class="auth-brand-mark">&#9889;</span><span>ADONAK ELECTRONICS</span></div>
    <div class="auth-brand-copy"><span class="auth-kicker">Secure your account</span><h1>Create a strong password that is easy for you to remember.</h1><p>Your new password should be unique to ADONAK and must contain at least eight characters.</p><div class="auth-benefits"><div class="auth-benefit"><span class="auth-benefit-icon">A</span>Mix upper and lowercase letters</div><div class="auth-benefit"><span class="auth-benefit-icon">1</span>Add a number</div><div class="auth-benefit"><span class="auth-benefit-icon">#</span>Use a special character</div></div></div>
    <a class="auth-guest-link" href="customer/home.php">&#8592; Continue as guest</a>
  </section>
  <section class="auth-form-panel">
    <p class="auth-eyebrow">Final recovery step</p><h1 class="auth-title">Create a new password</h1><p class="auth-subtitle">Enter and confirm the new password for your account.</p>
    <?php if (!empty($msg)): ?><div class="auth-alert <?= $msg_class === 'success' ? 'auth-alert-success' : 'auth-alert-error' ?>" role="<?= $msg_class === 'success' ? 'status' : 'alert' ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if (!empty($msg) && $msg_class === 'success'): ?><p class="auth-subtitle">Returning to login in <strong id="countdown">3</strong> seconds…</p><?php endif; ?>
    <?php if ($show_form): ?>
    <form action="reset_password.php" method="POST" class="auth-form" data-auth-submit data-loading-text="Updating password...">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['password_reset_csrf'],ENT_QUOTES,'UTF-8')?>">
      <div class="auth-field"><label for="password">New password</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128274;</span><input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" data-password-strength><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show or hide password" aria-pressed="false">Show</button></div><div class="password-meter" aria-hidden="true"><span></span></div><p class="password-hint">Use at least 8 characters</p></div>
      <div class="auth-field"><label for="confirm_password">Confirm new password</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128274;</span><input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat your password"><button type="button" class="auth-password-toggle" data-password-toggle="confirm_password" aria-label="Show or hide password confirmation" aria-pressed="false">Show</button></div></div>
      <button type="submit" name="update_password" class="auth-submit"><span class="auth-spinner"></span><span data-submit-text>Update Password</span></button>
    </form>
    <?php endif; ?>
    <div class="auth-switch"><a href="login.php">Back to login</a></div>
  </section>
</main>
<?php if (!empty($msg) && $msg_class === 'success'): ?><script>let seconds=3;const countdownEl=document.getElementById('countdown');const interval=setInterval(function(){seconds--;if(countdownEl)countdownEl.textContent=seconds;if(seconds<=0){clearInterval(interval);window.location.href='login.php';}},1000);</script><?php endif; ?>
<script src="js/auth-ui.js"></script></body>
</html>
