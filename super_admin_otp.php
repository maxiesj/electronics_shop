<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/db.php';

$challenge=$_SESSION['super_admin_otp']??null;
if(!is_array($challenge)||empty($challenge['user_id'])||empty($challenge['code_hash'])){
    header('Location: login.php?msg=err_unauthorized_access');exit;
}
if(empty($_SESSION['otp_csrf']))$_SESSION['otp_csrf']=bin2hex(random_bytes(32));
$error='';$notice='';

function clearSuperAdminOtp(){unset($_SESSION['super_admin_otp'],$_SESSION['otp_csrf']);}
function sendSuperAdminOtp($email,$name,$code){
    require_once __DIR__.'/phpmailer/Exception.php';require_once __DIR__.'/phpmailer/PHPMailer.php';require_once __DIR__.'/phpmailer/SMTP.php';
    $cfg=require __DIR__.'/mail_config.php';$mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->Host=$cfg['host'];$mail->SMTPAuth=true;$mail->Username=$cfg['username'];$mail->Password=$cfg['password'];$mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->Port=$cfg['port'];$mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
    $mail->setFrom($cfg['from_email'],$cfg['from_name']);$mail->addAddress($email,$name);$mail->isHTML(true);$mail->Subject='Your ADONAK Super Administrator login code';$mail->Body='<p>Your one-time Super Administrator login code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">'.htmlspecialchars($code).'</p><p>This code expires in 5 minutes.</p>';$mail->AltBody="Your ADONAK Super Administrator login code is {$code}. It expires in 5 minutes.";$mail->send();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(empty($_POST['csrf_token'])||!hash_equals($_SESSION['otp_csrf'],(string)$_POST['csrf_token']))$error='Your secure form expired. Refresh it and try again.';
    elseif(isset($_POST['cancel_otp'])){clearSuperAdminOtp();header('Location: login.php');exit;}
    elseif(isset($_POST['resend_otp'])){
        $wait=60-(time()-(int)($challenge['last_sent_at']??0));
        if($wait>0)$error='Please wait '.$wait.' seconds before requesting another code.';
        else{
            try{$code=(string)random_int(100000,999999);sendSuperAdminOtp($challenge['email'],$challenge['fullname'],$code);$_SESSION['super_admin_otp']['code_hash']=password_hash($code,PASSWORD_DEFAULT);$_SESSION['super_admin_otp']['expires_at']=time()+300;$_SESSION['super_admin_otp']['attempts']=0;$_SESSION['super_admin_otp']['last_sent_at']=time();$_SESSION['otp_csrf']=bin2hex(random_bytes(32));$notice='A new code was sent.';$challenge=$_SESSION['super_admin_otp'];}catch(Throwable $e){error_log('Super Administrator OTP resend failed: '.$e->getMessage());$error='The new code could not be delivered. Please try again.';}
        }
    }elseif(isset($_POST['verify_otp'])){
        $code=preg_replace('/\D/','',(string)($_POST['otp_code']??''));
        if(time()>(int)$challenge['expires_at']){$error='This code expired. Request a new one.';}
        elseif((int)$challenge['attempts']>=5){clearSuperAdminOtp();header('Location: login.php?msg=err_unauthorized_access');exit;}
        elseif(!preg_match('/^\d{6}$/',$code)||!password_verify($code,$challenge['code_hash'])){$_SESSION['super_admin_otp']['attempts']=(int)$challenge['attempts']+1;$left=5-$_SESSION['super_admin_otp']['attempts'];$error=$left>0?'Incorrect code. '.$left.' attempts remaining.':'Too many incorrect codes. Return to login and try again.';if($left===0)clearSuperAdminOtp();}
        else{
            $stmt=$conn->prepare("SELECT u.id,u.fullname,u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND LOWER(TRIM(r.role_name))='super_admin' AND LOWER(COALESCE(u.account_status,'active'))='active' LIMIT 1");$uid=(int)$challenge['user_id'];$stmt->bind_param('i',$uid);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
            if(!$user){clearSuperAdminOtp();$error='This account is no longer eligible to log in.';}
            else{session_regenerate_id(true);clearSuperAdminOtp();$_SESSION['user_id']=(int)$user['id'];$_SESSION['fullname']=(string)$user['fullname'];$_SESSION['role']='super_admin';$_SESSION['last_activity_timestamp']=time();$_SESSION['csrf_token']=bin2hex(random_bytes(32));$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$details='Super Administrator login completed with email OTP via IP: '.$ip;$log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Staff Login',?)");if($log){$name=(string)$user['fullname'];$log->bind_param('iss',$uid,$name,$details);$log->execute();$log->close();}header('Location: admin/dashboard.php?view=dashboard_overview.php');exit;}
        }
    }
}
$masked=preg_replace('/(^.).*(@.*$)/','$1***$2',(string)$challenge['email']);
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify Super Administrator | ADONAK</title><link rel="stylesheet" href="css/auth.css"></head><body class="auth-page"><main class="auth-shell"><section class="auth-brand-panel"><div class="auth-brand"><span class="auth-brand-mark">&#9889;</span><span>ADONAK ELECTRONICS</span></div><div class="auth-brand-copy"><span class="auth-kicker">Protected access</span><h1>Confirm this Super Administrator login.</h1><p>A one-time code was sent to the registered email. The code expires after five minutes.</p><div class="auth-benefits"><div class="auth-benefit"><span class="auth-benefit-icon">6</span>Six-digit verification code</div><div class="auth-benefit"><span class="auth-benefit-icon">5</span>Five-minute expiry</div><div class="auth-benefit"><span class="auth-benefit-icon">1</span>Single-use access</div></div></div></section><section class="auth-form-panel"><p class="auth-eyebrow">Second verification step</p><h1 class="auth-title">Enter your email code</h1><p class="auth-subtitle">Code sent to <?=htmlspecialchars($masked)?>.</p><?php if($error):?><div class="auth-alert auth-alert-error" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($notice):?><div class="auth-alert auth-alert-success" role="status"><?=htmlspecialchars($notice)?></div><?php endif;?><form method="post" class="auth-form" data-auth-submit data-loading-text="Verifying..."><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['otp_csrf'])?>"><div class="auth-field"><label for="otp_code">Six-digit code</label><div class="auth-input-wrap"><span class="auth-input-icon">#</span><input id="otp_code" name="otp_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></div></div><button class="auth-submit" type="submit" name="verify_otp"><span class="auth-spinner"></span><span data-submit-text>Verify and Log In</span></button></form><div class="auth-switch"><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['otp_csrf'])?>"><button type="submit" name="resend_otp" style="border:0;background:none;color:#ea580c;font-weight:900;cursor:pointer">Resend code</button> <span class="auth-switch-separator">or</span> <button type="submit" name="cancel_otp" style="border:0;background:none;color:#2563eb;font-weight:900;cursor:pointer">Return to login</button></form></div></section></main><script src="js/auth-ui.js"></script></body></html>
