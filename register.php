<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/db.php';
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$msg='';$success_msg='';

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['submit'])){
    if(empty($_POST['csrf_token'])||empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],(string)$_POST['csrf_token'])){
        $msg='Your form session expired. Please refresh the page and try again.';
    }else{
        $fullname=trim((string)($_POST['fullname']??''));
        $email=strtolower(trim((string)($_POST['email']??'')));
        $phone=trim((string)($_POST['phone']??''));
        $password=(string)($_POST['password']??'');
        $confirm_password=(string)($_POST['confirm_password']??'');
        if($fullname===''||$email===''||$phone===''||$password==='')$msg='All fields must be completed.';
        elseif(strlen($fullname)>100||strlen($email)>100||strlen($phone)>20)$msg='One or more details are too long.';
        elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$msg='Enter a valid email address.';
        elseif($password!==$confirm_password)$msg='Password confirmation does not match.';
        elseif(strlen($password)<8)$msg='Password must be at least 8 characters long.';
        else{
            $conn->begin_transaction();
            try{
                $roleStmt=$conn->prepare("SELECT id FROM roles WHERE LOWER(TRIM(role_name))='customer' LIMIT 1");
                if(!$roleStmt||!$roleStmt->execute())throw new RuntimeException('Customer role lookup failed.');
                $customerRole=$roleStmt->get_result()->fetch_assoc();$roleStmt->close();
                if(!$customerRole)throw new RuntimeException('Customer role is unavailable.');
                $roleId=(int)$customerRole['id'];
                $check=$conn->prepare('SELECT id,role_id,account_status FROM users WHERE LOWER(TRIM(email))=? LIMIT 1 FOR UPDATE');
                if(!$check)throw new RuntimeException('Account lookup failed.');
                $check->bind_param('s',$email);if(!$check->execute())throw new RuntimeException('Account lookup failed.');
                $existing=$check->get_result()->fetch_assoc();$check->close();
                $phoneCheck=$conn->prepare('SELECT id FROM users WHERE phone=? AND LOWER(TRIM(email))<>? LIMIT 1');
                if(!$phoneCheck)throw new RuntimeException('Phone lookup failed.');
                $phoneCheck->bind_param('ss',$phone,$email);if(!$phoneCheck->execute())throw new RuntimeException('Phone lookup failed.');
                $phoneOwner=$phoneCheck->get_result()->fetch_assoc();$phoneCheck->close();
                if($phoneOwner)throw new DomainException('That phone number is already registered to another account.');
                $hashedPassword=password_hash($password,PASSWORD_DEFAULT);
                if($existing){
                    if(strtolower((string)$existing['account_status'])!=='purged')throw new DomainException('A profile is already registered under that email.');
                    if((int)$existing['role_id']!==$roleId)throw new DomainException('This email belongs to a deleted staff account and cannot be restored through customer registration.');
                    $stmt=$conn->prepare("UPDATE users SET fullname=?,phone=?,password=?,role_id=?,account_status='active',reset_token_hash=NULL,reset_token_expires_at=NULL WHERE id=? AND account_status='purged'");
                    if(!$stmt)throw new RuntimeException('Account reactivation failed.');
                    $id=(int)$existing['id'];$stmt->bind_param('sssii',$fullname,$phone,$hashedPassword,$roleId,$id);
                    if(!$stmt->execute()||$stmt->affected_rows!==1)throw new RuntimeException('Account reactivation failed.');$stmt->close();
                }else{
                    $stmt=$conn->prepare("INSERT INTO users(fullname,email,phone,password,role_id,account_status) VALUES(?,?,?,?,?,'active')");
                    if(!$stmt)throw new RuntimeException('Registration failed.');
                    $stmt->bind_param('ssssi',$fullname,$email,$phone,$hashedPassword,$roleId);
                    if(!$stmt->execute())throw new RuntimeException('Registration failed.');$stmt->close();
                }
                $conn->commit();$_SESSION['csrf_token']=bin2hex(random_bytes(32));
                header('Location: login.php?msg='.($existing?'reactivated':'registered'));exit;
            }catch(DomainException $e){$conn->rollback();$msg=$e->getMessage();}
            catch(Throwable $e){$conn->rollback();error_log('Customer registration failed: '.$e->getMessage());$msg='Registration is temporarily unavailable. Please try again.';}
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="uploads/logo.jpg">
    <title>ADONAK ELECTRONICS - Register</title>
    <style>
        /* 1. Global Reset & Body Background Canvas */
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; transition: all 0.2s ease; }
        body { 
            background-color: #0f172a; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; /* Fixed from height to prevent mobile touch keyboard overflow drops */
            margin: 0; 
            background-image: radial-gradient(circle at top right, #1e293b, #0f172a); 
            padding: 16px;
        }
        
        /* 2. Onboarding Registration Shell Core Card */
        .myform { 
            background: #ffffff; 
            padding: 45px 35px; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); 
            border: 1px solid #1e293b; 
            box-sizing: border-box;
        }
        h2 { margin-top: 0; text-align: center; color: #0f172a; font-weight: 700; margin-bottom: 8px; }
        hr { border: none; border-top: 2px solid #f1f5f9; margin-bottom: 24px; margin-top: 14px; }
        
        /* 3. Form Input Field & Action Control Structures */
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; background: #f8fafc; height: 42px; box-sizing: border-box; outline: none; margin-bottom: 16px; }
        input:focus, input:hover { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        
        /* Complete Registration Action Trigger Button */
        button { width: 100%; padding: 14px 0; background-color: #059669; color: white; font-weight: bold; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; height: 46px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; margin-top: 8px; }
        button:hover { background-color: #047857; transform: translateY(-1px); }
        button:active { transform: translateY(0); }
        
        /* System Transaction Notification Alerts */
        .alert-error { background-color: #fef2f2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; text-align: center; border: 1px solid #fecaca; font-weight: 600; }
        
        /* 4. Secondary Navigation Routing Actions Utilities */
        .links-wrapper { display: flex; flex-direction: column; gap: 14px; margin-top: 24px; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 20px; font-size: 13px; color: #475569; font-weight: 600; }
        
        /* Existing Account Login Call to Action Layout Button Block */
        .btn-login-route { width: 100%; background-color: #fff; color: #1e293b; border: 1px solid #cbd5e1; padding: 10px 0; border-radius: 6px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; letter-spacing: 0.02em; height: 40px; box-sizing: border-box; margin-top: 4px; }
        .btn-login-route:hover { background-color: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .btn-login-route.is-loading { pointer-events: none; opacity: 0.72; }
        .nav-loader { display: none; width: 14px; height: 14px; margin-right: 8px; border: 2px solid #cbd5e1; border-top-color: #2563eb; border-radius: 50%; animation: navSpin 0.7s linear infinite; }
        .is-loading .nav-loader { display: inline-block; }
        @keyframes navSpin { to { transform: rotate(360deg); } }

        /* ==========================================================================
           5. RESPONSIVE MEDIA QUERIES (MOBILE PHONE VIEWPORT OPTIMIZATIONS)
           ========================================================================== */
        @media screen and (max-width: 480px) {
            body { padding: 12px; }
            .myform { padding: 30px 20px; margin: 8px; border-radius: 8px; }
            h2 { font-size: 1.25rem; }
            hr { margin-bottom: 16px; }
            
            /* Enlarge inputs for easier mobile thumb interaction targets */
            input { height: 46px; font-size: 15px; margin-bottom: 12px; }
            button { height: 48px; font-size: 14px; }
            .btn-login-route { height: 44px; font-size: 12px; }
        }
    </style>
<link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<main class="auth-shell auth-register-mode">
  <section class="auth-brand-panel" aria-label="Benefits of creating an ADONAK account">
    <div class="auth-brand"><span class="auth-brand-mark">&#9889;</span><span>ADONAK ELECTRONICS</span></div>
    <div class="auth-brand-copy"><span class="auth-kicker">Join ADONAK</span><h1>Shop confidently with every payment and order clearly tracked.</h1><p>Create your free customer account to unlock purchasing, delivery tracking, secure statements and flexible payment options.</p><div class="auth-benefits"><div class="auth-benefit"><span class="auth-benefit-icon">&#10003;</span>Verified customer checkout</div><div class="auth-benefit"><span class="auth-benefit-icon">&#128179;</span>Wallet and M-Pesa payments</div><div class="auth-benefit"><span class="auth-benefit-icon">&#128196;</span>Orders and statements in one place</div></div></div>
    <a class="auth-guest-link" href="customer/home.php">&#8592; Continue as guest</a>
  </section>
  <section class="auth-form-panel auth-register-panel">
    <p class="auth-eyebrow">Free customer account</p><h1 class="auth-title">Create your account</h1><p class="auth-subtitle">Use accurate contact details for payments, delivery updates and account recovery.</p>
    <?php if (!empty($msg)): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <form method="POST" class="auth-form" action="register.php" data-auth-submit data-loading-text="Creating account...">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="auth-form-grid">
        <div class="auth-field full"><label for="fullname">Full name</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128100;</span><input id="fullname" type="text" name="fullname" required autocomplete="name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
        <div class="auth-field"><label for="register-email">Email address</label><div class="auth-input-wrap"><span class="auth-input-icon">&#9993;</span><input id="register-email" type="email" name="email" required autocomplete="email" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
        <div class="auth-field"><label for="phone">Mobile number</label><div class="auth-input-wrap"><span class="auth-input-icon">&#9742;</span><input id="phone" class="auth-placeholder-motion" type="tel" name="phone" required autocomplete="tel" inputmode="tel" placeholder="07XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
        <div class="auth-field"><label for="register-password">Password</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128274;</span><input id="register-password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" data-password-strength><button type="button" class="auth-password-toggle" data-password-toggle="register-password" aria-label="Show or hide password" aria-pressed="false">Show</button></div><div class="password-meter" aria-hidden="true"><span></span></div><p class="password-hint">Use at least 8 characters</p></div>
        <div class="auth-field"><label for="confirm-password">Confirm password</label><div class="auth-input-wrap"><span class="auth-input-icon">&#128274;</span><input id="confirm-password" class="auth-placeholder-motion" type="password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat your password"><button type="button" class="auth-password-toggle" data-password-toggle="confirm-password" aria-label="Show or hide password confirmation" aria-pressed="false">Show</button></div></div>
      </div>
      <button type="submit" name="submit" class="auth-submit"><span class="auth-spinner"></span><span data-submit-text>Create Account</span></button>
    </form>
    <div class="auth-switch"><span>Already registered? <a href="login.php" data-auth-route="login"><span class="nav-loader" aria-hidden="true"></span><span class="nav-link-text">Log in</span></a></span><span class="auth-switch-separator">or</span><a class="auth-guest-action" href="customer/home.php" data-auth-route="guest">Continue as Guest</a></div>
  </section>
</main><script>
    // Give the user instant feedback while returning to the login page.
    const returnLoginLink = document.getElementById('returnLoginLink');
    if (returnLoginLink) {
        returnLoginLink.addEventListener('click', function(event) {
            event.preventDefault();
            returnLoginLink.classList.add('is-loading');
            returnLoginLink.querySelector('.nav-link-text').textContent = 'Opening Login...';
            setTimeout(function() {
                window.location.href = returnLoginLink.href;
            }, 650);
        });
    }
</script>
<script src="js/auth-ui.js"></script>
</body>
</html>
