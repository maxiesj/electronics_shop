<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/db.php';
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));

if(isset($_GET['msg'])&&$_GET['msg']==='err_session_expired'&&$_SERVER['REQUEST_METHOD']!=='POST'){
    $_SESSION=[];
    if(ini_get('session.use_cookies')){$params=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);}
    session_destroy();session_start();$_SESSION['csrf_token']=bin2hex(random_bytes(32));
}
$msg='';$success_msg='';
if(isset($_GET['msg'])){
    if($_GET['msg']==='err_unauthorized_access')$msg='Access denied. Please log in to continue.';
    elseif($_GET['msg']==='err_session_expired')$msg='Your session expired after 15 minutes of inactivity. Please sign in again.';
    elseif($_GET['msg']==='logout_success')$success_msg='Your session was securely closed.';
    elseif($_GET['msg']==='registered')$success_msg='Account created successfully. You can now log in.';
    elseif($_GET['msg']==='reactivated')$success_msg='Welcome back. Your customer account was restored with your new password.';
}
if(!isset($_SESSION['login_attempts']))$_SESSION['login_attempts']=0;
if(!isset($_SESSION['lockout_time']))$_SESSION['lockout_time']=0;
$current_time=time();
if($_SESSION['lockout_time']&&$current_time>=$_SESSION['lockout_time']){$_SESSION['login_attempts']=0;$_SESSION['lockout_time']=0;}
if($_SESSION['login_attempts']>=3&&$current_time<$_SESSION['lockout_time'])$msg='Too many failed attempts. Try again in '.($_SESSION['lockout_time']-$current_time).' seconds.';

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['login'])){
    $msg='';
    if(empty($_POST['csrf_token'])||empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],(string)$_POST['csrf_token'])){
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));$msg='Your secure form expired. Please try again.';
    }elseif($_SESSION['login_attempts']>=3&&$current_time<$_SESSION['lockout_time']){
        $msg='Too many failed attempts. Try again in '.($_SESSION['lockout_time']-$current_time).' seconds.';
    }else{
        $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
        if($email===''||$password==='')$msg='Enter both your email address and password.';
        elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$msg='Enter a valid email address.';
        else{
            $stmt=$conn->prepare("SELECT u.id,u.fullname,u.email,u.password,r.role_name FROM users u LEFT JOIN roles r ON u.role_id=r.id WHERE LOWER(TRIM(u.email))=? AND LOWER(COALESCE(u.account_status,'active'))='active' LIMIT 1");
            if(!$stmt){error_log('Login lookup preparation failed: '.$conn->error);$msg='Login is temporarily unavailable. Please try again.';}
            else{
                $stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
                if(!$user||!password_verify($password,(string)$user['password'])){
                    $_SESSION['login_attempts']++;
                    if($_SESSION['login_attempts']>=3){$_SESSION['lockout_time']=time()+30;$msg='Too many failed attempts. Try again in 30 seconds.';}
                    else $msg='Incorrect email address or password. '.(3-$_SESSION['login_attempts']).' attempts remaining.';
                }else{
                    $role=strtolower(trim((string)($user['role_name']??'')));
                    $adminRoles=['admin','super_admin'];$staffRoles=['staff','cashier','auditor','supervissoor','cleaner'];
                    if(!in_array($role,array_merge($adminRoles,$staffRoles,['customer']),true))$msg='This account has no valid access role. Contact an administrator.';
                    else{
                        if($role==='super_admin'){
                            try{
                                require_once __DIR__.'/phpmailer/Exception.php';require_once __DIR__.'/phpmailer/PHPMailer.php';require_once __DIR__.'/phpmailer/SMTP.php';
                                $code=(string)random_int(100000,999999);$cfg=require __DIR__.'/mail_config.php';$mail=new PHPMailer\PHPMailer\PHPMailer(true);
                                $mail->isSMTP();$mail->Host=$cfg['host'];$mail->SMTPAuth=true;$mail->Username=$cfg['username'];$mail->Password=$cfg['password'];$mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->Port=$cfg['port'];$mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
                                $mail->setFrom($cfg['from_email'],$cfg['from_name']);$mail->addAddress($user['email'],$user['fullname']);$mail->isHTML(true);$mail->Subject='Your ADONAK Super Administrator login code';$mail->Body='<p>Your one-time Super Administrator login code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">'.htmlspecialchars($code).'</p><p>This code expires in 5 minutes. If you did not attempt to log in, change your password immediately.</p>';$mail->AltBody="Your ADONAK Super Administrator login code is {$code}. It expires in 5 minutes.";$mail->send();
                                session_regenerate_id(true);unset($_SESSION['user_id'],$_SESSION['fullname'],$_SESSION['role'],$_SESSION['staff_id'],$_SESSION['staff_name']);
                                $_SESSION['super_admin_otp']=['user_id'=>(int)$user['id'],'fullname'=>(string)$user['fullname'],'email'=>(string)$user['email'],'code_hash'=>password_hash($code,PASSWORD_DEFAULT),'expires_at'=>time()+300,'attempts'=>0,'last_sent_at'=>time()];
                                $_SESSION['otp_csrf']=bin2hex(random_bytes(32));$_SESSION['login_attempts']=0;$_SESSION['lockout_time']=0;
                                header('Location: super_admin_otp.php');exit;
                            }catch(Throwable $e){error_log('Super Administrator OTP delivery failed: '.$e->getMessage());$msg='The verification code could not be delivered. Please try again.';}
                        }else{
                        $_SESSION['login_attempts']=0;$_SESSION['lockout_time']=0;session_regenerate_id(true);
                        unset($_SESSION['staff_id'],$_SESSION['staff_name']);
                        $_SESSION['user_id']=(int)$user['id'];$_SESSION['fullname']=trim((string)$user['fullname']);$_SESSION['role']=$role;$_SESSION['last_activity_timestamp']=time();$_SESSION['csrf_token']=bin2hex(random_bytes(32));
                        if(in_array($role,$adminRoles,true)||in_array($role,$staffRoles,true)){
                            if(in_array($role,$staffRoles,true)){$_SESSION['staff_id']=(int)$user['id'];$_SESSION['staff_name']=trim((string)$user['fullname']);}
                            $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$details='Secure staff entry validated via IP: '.$ip;
                            $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Staff Login',?)");
                            if($log){$uid=(int)$user['id'];$name=(string)$user['fullname'];$log->bind_param('iss',$uid,$name,$details);$log->execute();$log->close();}
                            header('Location: '.(in_array($role,$adminRoles,true)?'admin/dashboard.php?view=dashboard_overview.php':'staff/staff_dashboard.php'));exit;
                        }
                        header('Location: customer/home.php');exit;
                        }
                    }
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <!-- CRITICAL MOBILE VIEWPORT ENGINE TRIGGER: Snaps layout to 100% native mobile resolution -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta charset="UTF-8">
    <link rel="icon" type="image/jpeg" href="uploads/logo.jpg">
    <title>ADONAK ELECTRONICS</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; transition: all 0.2s ease; }
        body { 
            background-color: #0f172a; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
            margin: 0; 
            background-image: radial-gradient(circle at top right, #1e293b, #0f172a); 
            padding: 16px;
        }
        
        .login-card { 
            background: #ffffff; 
            padding: 45px 35px; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); 
            border: 1px solid #1e293b; 
            box-sizing: border-box;
        }
        h2 { margin-top: 0; text-align: center; color: #0f172a; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; background: #f8fafc; height: 42px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        
        .btn-login { width: 100%; padding: 14px 0; background-color: #3b82f6; color: white; font-weight: bold; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; height: 46px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; position: relative; }
        .btn-login:hover { background-color: #2563eb; transform: translateY(-1px); }
        .btn-login:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        
        .loader-spinning-wheel {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spinAction 0.8s infinite linear;
            margin-right: 10px;
        }
        @keyframes spinAction {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert-error { background-color: #fef2f2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; text-align: center; border: 1px solid #fecaca; word-wrap: break-word; }
        .alert-success { background-color: #f0fdf4; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; text-align: center; border: 1px solid #bbf7d0; word-wrap: break-word; }
        .links-wrapper { display: flex; flex-direction: column; gap: 14px; margin-top: 24px; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 20px; }
        .forgot-link { color: #e67e22; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
        .forgot-link:hover { color: #d35400; text-decoration: underline; }
        
        .btn-register { width: 100%; background-color: #fff; color: #1e293b; border: 1px solid #cbd5e1; padding: 10px 0; border-radius: 6px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; height: 40px; box-sizing: border-box; transition: all 0.2s ease; }
        .btn-register:hover { background-color: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .btn-register.is-loading { pointer-events: none; opacity: 0.72; }
        .nav-loader { display: none; width: 14px; height: 14px; margin-right: 8px; border: 2px solid #cbd5e1; border-top-color: #2563eb; border-radius: 50%; animation: navSpin 0.7s linear infinite; }
        .is-loading .nav-loader { display: inline-block; }
        @keyframes navSpin { to { transform: rotate(360deg); } }

        @media screen and (max-width: 480px) {
            body { padding: 12px; }
            .login-card { padding: 30px 20px; margin: 8px; border-radius: 8px; }
            h2 { font-size: 1.25rem; margin-bottom: 20px; padding-bottom: 12px; }
            input { height: 46px; font-size: 15px; }
            .btn-login { height: 48px; font-size: 14px; }
        }
    </style>
<link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<main class="auth-shell">
  <section class="auth-brand-panel" aria-label="ADONAK account benefits">
    <div class="auth-brand"><span class="auth-brand-mark">&#9889;</span><span>ADONAK ELECTRONICS</span></div>
    <div class="auth-brand-copy"><span class="auth-kicker">Welcome back</span><h1>Your electronics, payments and orders in one secure place.</h1><p>Sign in to shop securely, manage your wallet, track deliveries and continue Lipa Pole Pole payments.</p><div class="auth-benefits"><div class="auth-benefit"><span class="auth-benefit-icon">&#128274;</span>Secure wallet checkout</div><div class="auth-benefit"><span class="auth-benefit-icon">&#128666;</span>Live order tracking</div><div class="auth-benefit"><span class="auth-benefit-icon">&#9203;</span>Flexible Lipa Pole Pole payments</div></div></div>
    <a class="auth-guest-link" href="customer/home.php">&#8592; Continue as guest</a>
  </section>
  <section class="auth-form-panel">
    <p class="auth-eyebrow">Customer &amp; team access</p><h1 class="auth-title">Log in to your account</h1><p class="auth-subtitle">Enter your registered email and password to continue.</p>
    <?php if (!empty($msg)): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if (!empty($success_msg)): ?><div class="auth-alert auth-alert-success" role="status"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
    <form action="" method="POST" id="authLoginForm" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      <div class="form-group"><label for="email">Email address</label><div class="auth-input-wrap"><span class="auth-input-icon">&#9993;</span><input type="email" id="email" name="email" required autocomplete="email" placeholder="name@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"></div></div>
      <div class="form-group"><label for="password">Password</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128274;</span><input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show or hide password" aria-pressed="false">Show</button></div></div>
      <div class="auth-row"><span class="auth-check">Secure 15-minute inactivity protection</span><a href="forgot_password.php" class="auth-link" data-email-required data-auth-route="recovery">Forgot password?</a></div>
      <input type="hidden" id="login" name="login" value="1">
      <button type="submit" id="loginSubmitBtn" class="auth-submit" <?php echo ($_SESSION['login_attempts'] >= 3 && $current_time < $_SESSION['lockout_time']) ? 'disabled' : ''; ?>><span class="auth-spinner" id="loginLoader"></span><span id="loginBtnText">Log In</span></button>
    </form>
    <div class="auth-switch"><span>New to ADONAK? <a href="register.php" data-auth-route="register"><span class="nav-loader" aria-hidden="true"></span><span class="nav-link-text">Create an account</span></a></span><span class="auth-switch-separator">or</span><a class="auth-guest-action" href="customer/home.php" data-auth-route="guest">Continue as Guest</a></div>
  </section>
</main>
<script src="js/auth-ui.js"></script>
</body>
</html>
