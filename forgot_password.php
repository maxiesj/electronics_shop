<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/phpmailer/Exception.php';
require_once __DIR__.'/phpmailer/PHPMailer.php';
require_once __DIR__.'/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
if(empty($_SESSION['password_request_csrf']))$_SESSION['password_request_csrf']=bin2hex(random_bytes(32));
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=strtolower(trim((string)($_POST['email']??'')));
    if(empty($_POST['csrf_token'])||!hash_equals($_SESSION['password_request_csrf'],(string)$_POST['csrf_token']))$error='Your secure form expired. Refresh it and try again.';
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';
    elseif(!empty($_SESSION['password_request_time'])&&time()-(int)$_SESSION['password_request_time']<60)$error='Please wait a minute before requesting another reset link.';
    else{
        $_SESSION['password_request_time']=time();$_SESSION['password_request_csrf']=bin2hex(random_bytes(32));
        $stmt=$conn->prepare("SELECT id,fullname,email FROM users WHERE LOWER(TRIM(email))=? AND LOWER(COALESCE(account_status,'active'))='active' LIMIT 1");
        $user=null;if($stmt){$stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();}
        if($user){
            try{
                $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);$expiry=date('Y-m-d H:i:s',time()+1800);
                $https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)($_SERVER['HTTP_HOST']??'localhost'));$dir=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/electronics_shop/forgot_password.php'))),'/');$base=rtrim((string)(getenv('ADONAK_BASE_URL')?:''),'/');if($base==='')$base=($https?'https':'http').'://'.$host.$dir;$resetLink=$base.'/reset_password.php?token='.rawurlencode($token);
                $cfg=require __DIR__.'/mail_config.php';$mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=$cfg['host'];$mail->SMTPAuth=true;$mail->Username=$cfg['username'];$mail->Password=$cfg['password'];$mail->SMTPSecure=PHPMailer::ENCRYPTION_SMTPS;$mail->Port=$cfg['port'];$mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];$mail->setFrom($cfg['from_email'],$cfg['from_name']);$mail->addAddress($user['email'],$user['fullname']?:'ADONAK customer');$mail->isHTML(true);$mail->Subject='Reset your ADONAK password';$safeLink=htmlspecialchars($resetLink,ENT_QUOTES,'UTF-8');$mail->Body='<p>Use this secure one-time link to reset your ADONAK password:</p><p><a href="'.$safeLink.'">Reset password</a></p><p>The link expires in 30 minutes.</p>';$mail->AltBody="Reset your ADONAK password: {$resetLink}\nThe link expires in 30 minutes.";$mail->send();
                $update=$conn->prepare('UPDATE users SET reset_token_hash=?,reset_token_expires_at=? WHERE id=?');if(!$update)throw new RuntimeException('Token update failed.');$uid=(int)$user['id'];$update->bind_param('ssi',$tokenHash,$expiry,$uid);if(!$update->execute())throw new RuntimeException('Token update failed.');$update->close();
            }catch(Throwable $e){error_log('Password reset request failed: '.$e->getMessage());$clear=$conn->prepare('UPDATE users SET reset_token_hash=NULL,reset_token_expires_at=NULL WHERE id=?');if($clear){$uid=(int)$user['id'];$clear->bind_param('i',$uid);$clear->execute();$clear->close();}}
        }
        $message='If that account is active, a secure reset link will be sent to its registered email.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ADONAK</title>
   <style>
    /* ==========================================================================
       1. GLOBAL RESET & FLEX ALIGNMENT (DEFAULT DESKTOP VIEW)
       ========================================================================== */
    body { 
        background-color: #f3f4f6; 
        font-family: ui-sans-serif, system-ui, sans-serif; /* Updated to modern web-fallback tokens */
        margin: 0; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        min-height: 100vh; /* Changed from height to min-height to prevent clipping when keyboard opens */
    }
    
    /* 2. Core Recover Card Container Form Canvas */
    .card { 
        background: #ffffff; 
        padding: 35px 30px; 
        border-radius: 12px; /* Smoothed from 8px to mirror your dashboard theme */
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05); 
        width: 100%; 
        max-width: 400px; 
        box-sizing: border-box; /* Enforces layout integrity within borders */
    }
    h2 { 
        margin-top: 0; 
        color: #111827; 
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin-bottom: 24px;
    }
    
    /* 3. Form Input Field & Action Control Structures */
    .form-group { 
        margin-bottom: 20px; 
    }
    label { 
        display: block; 
        margin-bottom: 6px; 
        color: #4b5563; 
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    input[type="email"] { 
        width: 100%; 
        padding: 10px 14px; 
        border: 1px solid #cbd5e1; 
        border-radius: 6px; 
        box-sizing: border-box; 
        font-size: 14px;
        color: #1f2937;
        background-color: #ffffff;
        height: 40px; /* Synchronized input height for cross-browser symmetry */
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    input[type="email"]:focus {
        border-color: #0056b3;
        box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.15);
    }
    
    /* Primary Verification Request Trigger Button */
    button { 
        width: 100%; 
        padding: 10px 0; 
        background-color: #0056b3; 
        border: none; 
        border-radius: 6px; 
        color: #ffffff; 
        font-size: 14px; 
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer; 
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }
    button:hover { 
        background-color: #004085; 
    }
    button:active {
        transform: scale(0.99);
    }
    
    /* System Transaction Notification Alerts */
    .alert { 
        padding: 12px 14px; 
        border-radius: 6px; 
        margin-bottom: 20px; 
        font-size: 13px;
        font-weight: 600;
        box-sizing: border-box;
    }
    .alert-success { 
        background-color: #d1fae5; 
        color: #065f46; 
        border: 1px solid #a7f3d0; 
    }
    .alert-danger { 
        background-color: #fee2e2; 
        color: #991b1b; 
        border: 1px solid #fca5a5; 
    }
    
    /* Secondary Back Routing Anchor Link */
    .back-link { 
        display: block; 
        text-align: center; 
        margin-top: 16px; 
        color: #0056b3; 
        text-decoration: none; 
        font-size: 0.875rem;
        font-weight: 600;
        transition: color 0.2s ease;
    }
    .back-link:hover {
        color: #004085;
        text-decoration: underline;
    }

    /* ==========================================================================
       4. RESPONSIVE MEDIA QUERIES (MOBILE PHONE VIEWPORT OPTIMIZATIONS)
       ========================================================================== */

    /* 📱 SMARTPHONES & COMPACT TOUCH VIEWS (Max 480px Width Screens) */
    @media screen and (max-width: 480px) {
        body {
            padding: 16px; /* Prevents card from fusing with screen borders */
        }
        .card { 
            padding: 24px 16px; 
            margin: 8px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); /* Softened shadow profile for mobile displays */
        }
        h2 {
            font-size: 1.25rem;
            margin-bottom: 16px;
        }
        
        /* Enlarge touch interaction boxes to defend against zoom overrides on mobile viewports */
        input[type="email"] {
            height: 46px;
            font-size: 15px;
            padding: 12px;
        }
        button {
            height: 46px;
            font-size: 15px;
        }
        .back-link {
            font-size: 0.8rem;
            padding: 8px 0; /* Creates an expanded tap boundary line */
        }
    }
</style>
<link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<main class="auth-shell">
  <section class="auth-brand-panel" aria-label="Secure account recovery">
    <div class="auth-brand"><span class="auth-brand-mark">&#9889;</span><span>ADONAK ELECTRONICS</span></div>
    <div class="auth-brand-copy"><span class="auth-kicker">Account recovery</span><h1>Regain access safely and continue where you left off.</h1><p>We will send reset instructions only to the email registered on your ADONAK account.</p><div class="auth-benefits"><div class="auth-benefit"><span class="auth-benefit-icon">&#128274;</span>Secure one-time reset link</div><div class="auth-benefit"><span class="auth-benefit-icon">&#9201;</span>Time-limited protection</div><div class="auth-benefit"><span class="auth-benefit-icon">&#10003;</span>Your password is never emailed</div></div></div>
    <a class="auth-guest-link" href="customer/home.php">&#8592; Continue as guest</a>
  </section>
  <section class="auth-form-panel">
    <p class="auth-eyebrow">Password help</p><h1 class="auth-title">Reset your password</h1><p class="auth-subtitle">Enter your registered email address and we’ll send you a secure reset link.</p>
    <?php if (!empty($message)): ?><div class="auth-alert auth-alert-success" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form action="forgot_password.php" method="POST" class="auth-form" data-auth-submit data-loading-text="Sending reset link...">      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['password_request_csrf'],ENT_QUOTES,'UTF-8')?>">
      <div class="auth-field"><label for="email">Email address</label><div class="auth-input-wrap"><span class="auth-input-icon">&#9993;</span><input type="email" id="email" name="email" required autocomplete="email" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
      <button type="submit" class="auth-submit"><span class="auth-spinner"></span><span data-submit-text>Send Reset Link</span></button>
    </form>
    <div class="auth-switch">Remembered your password? <a href="login.php">Back to login</a></div>
  </section>
</main>
<script src="js/auth-ui.js"></script></body>
</html>
