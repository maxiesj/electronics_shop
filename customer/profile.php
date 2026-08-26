<?php session_start(); 
include '../db.php'; // Pulls the standard $conn MySQLi link variable

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

if (empty($_SESSION['profile_shipping_csrf'])) {
    $_SESSION['profile_shipping_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['profile_password_csrf'])) {
    $_SESSION['profile_password_csrf'] = bin2hex(random_bytes(32));
}

$password_error = '';
$password_success = isset($_GET['password']) && $_GET['password'] === 'updated'
    ? 'Your password was changed successfully. Use the new password the next time you sign in.'
    : '';

$shipping_error = '';
$shipping_success = isset($_GET['shipping']) && $_GET['shipping'] === 'updated'
    ? 'Your default shipping details were updated successfully.'
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shipping'])) {
    $csrf = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $shipping_phone = preg_replace('/[\s\-()]+/', '', trim((string)($_POST['shipping_phone'] ?? '')));
    $shipping_address = trim((string)($_POST['shipping_address'] ?? ''));

    if (!hash_equals($_SESSION['profile_shipping_csrf'], $csrf)) {
        $shipping_error = 'Your profile session expired. Please refresh the page and try again.';
    } elseif (!preg_match('/^(?:\+254|254|0)?[17]\d{8}$/', $shipping_phone)) {
        $shipping_error = 'Enter a valid Kenyan delivery phone number, for example 0712345678.';
    } elseif (mb_strlen($shipping_address) < 10 || mb_strlen($shipping_address) > 500) {
        $shipping_error = 'The shipping address must contain between 10 and 500 characters.';
    } else {
        $update = $conn->prepare("UPDATE users SET shipping_phone = ?, shipping_address = ? WHERE id = ?");
        $update->bind_param("ssi", $shipping_phone, $shipping_address, $user_id);
        if ($update->execute()) {
            $_SESSION['profile_shipping_csrf'] = bin2hex(random_bytes(32));
            header('Location: profile.php?shipping=updated');
            exit;
        }
        $shipping_error = 'The shipping details could not be saved. Please try again.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $csrf = isset($_POST['password_csrf_token']) ? (string)$_POST['password_csrf_token'] : '';
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (!hash_equals($_SESSION['profile_password_csrf'], $csrf)) {
        $password_error = 'Your password form expired. Please refresh the page and try again.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'The new password and confirmation do not match.';
    } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password)) {
        $password_error = 'Use at least 8 characters including an uppercase letter, lowercase letter, and number.';
    } else {
        $password_stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $password_stmt->bind_param("i", $user_id);
        $password_stmt->execute();
        $password_row = $password_stmt->get_result()->fetch_assoc();

        if (!$password_row || !password_verify($current_password, (string)$password_row['password'])) {
            $password_error = 'The current password is incorrect.';
        } elseif (password_verify($new_password, (string)$password_row['password'])) {
            $password_error = 'The new password must be different from your current password.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $password_update = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
            $password_update->bind_param("si", $new_hash, $user_id);
            if ($password_update->execute()) {
                $_SESSION['profile_password_csrf'] = bin2hex(random_bytes(32));
                session_regenerate_id(true);
                header('Location: profile.php?password=updated');
                exit;
            }
            $password_error = 'Your password could not be changed. Please try again.';
        }
    }
}
// Fetch user account information using MySQLi queries matching your layout parameters
$stmt = $conn->prepare("SELECT fullname, email, phone, shipping_phone, shipping_address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { die("Account metrics entry missing from local database record logs."); }

// Query current active customer wallet balances configuration properties
$w_st = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ?");
$w_st->bind_param("i", $user_id); $w_st->execute();
$w_res = $w_st->get_result()->fetch_assoc();
$wallet_balance = floatval($w_res['available_balance'] ?? 0.00);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Your Profile | ADONAK ELECTRONICS</title>
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Container Element (Default Desktop View) */
    main { max-width: 44rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Dual Grid Box Framework Blocks */
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-bottom: 24px; }
    .info-box { padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-sizing: border-box; }
    
    /* Wallet Card Display Elements */
    .wallet-box { padding: 16px; background: linear-gradient(135deg, #1e40af, #312e81); color: white; border-radius: 0.5rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(30,64,175,0.2); box-sizing: border-box; }
    .box-label { font-size: 10px; color: #9ca3af; font-weight: 700; text-transform: uppercase; margin: 0; }
    .wallet-label { font-size: 10px; color: #93c5fd; font-weight: 700; text-transform: uppercase; margin: 0; }
    .box-val { font-size: 1.1rem; font-weight: 900; color: #111827; margin: 4px 0 0; text-transform: uppercase; word-break: break-all; }
    .wallet-val { font-size: 1.5rem; font-weight: 900; color: #34d399; margin: 4px 0 0; white-space: nowrap; }
    
    /* Deposit Clickable CTA Button Component */
    .deposit-btn { display: block; width: 100%; text-align: center; background-color: #f97316; color: white; text-decoration: none; font-size: 11px; font-weight: 800; padding: 10px 0; border-radius: 4px; text-transform: uppercase; margin-top: 12px; box-shadow: 0 2px 4px rgba(249,115,22,0.2); box-sizing: border-box; height: 36px; display: inline-flex; align-items: center; justify-content: center; transition: background-color 0.2s ease; }
    .deposit-btn:hover { background-color: #ea580c; }
    
    /* 4. Financial Details Splits & Shipping Address Section Lists */
    .details-list { border-top: 1px solid #e5e7eb; padding-top: 24px; font-size: 0.875rem; font-weight: 600; color: #4b5563; }
    .detail-row { display: flex; justify-content: space-between; border-bottom: 1px solid #f3f4f6; padding: 12px 0; gap: 16px; }
    .detail-row:last-child { border-bottom: none; }
    .address-area { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; }
    .address-text { padding: 12px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; color: #1f2937; line-height: 1.5; font-weight: 500; box-sizing: border-box; }

       /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* FIXED TOP NAVIGATION BAR: Freezes your header firmly to the top edge of the mobile screen */
        nav { 
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: auto !important;
            flex-direction: column !important; 
            gap: 12px !important; 
            padding: 12px 16px !important; 
            text-align: center !important; 
            background-color: #0f172a !important; /* Deep dark background signature match */
            z-index: 9999 !important; /* Forces layout profiles to pass underneath the menu */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1e293b !important;
        }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your data content safely below the fixed nav height */
        main { 
            margin-top: 140px !important; /* Pushes profile content down out of the fixed nav area */
            margin-left: 16px !important;
            margin-right: 16px !important;
            margin-bottom: 16px !important;
            padding: 16px !important; 
            border-radius: 0.5rem !important; 
            box-sizing: border-box !important;
            display: block !important;
        }
        
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Flatten balance grid items to single full-width blocks */
        .grid { 
            display: flex !important;
            flex-direction: column !important;
            grid-template-columns: 1fr !important; 
            gap: 16px !important; 
            margin-bottom: 16px !important; 
            width: 100% !important;
        }
        
        /* Expand spacing dynamics for easier thumb interaction */
        .info-box, .wallet-box { 
            padding: 14px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .wallet-val { font-size: 1.35rem; }
        .deposit-btn { width: 100% !important; height: 42px; font-size: 12px; margin-top: 16px; box-sizing: border-box !important; }
        
        /* Item summary details line spacing adjustments */
        .details-list { padding-top: 16px; width: 100% !important; }
        .detail-row { padding: 10px 0; font-size: 0.8rem; }
        .address-text { padding: 10px; font-size: 0.8rem; width: 100% !important; box-sizing: border-box !important; }
    }

    .profile-alert { margin:0 0 18px; padding:11px 13px; border-radius:7px; font-size:12px; font-weight:700; }
    .profile-alert.success { color:#047857; background:#d1fae5; border:1px solid #a7f3d0; }
    .profile-alert.error { color:#b91c1c; background:#fee2e2; border:1px solid #fecaca; }
    .shipping-editor { margin-top:18px; padding:18px; border:1px solid #dbe3ef; border-radius:10px; background:#f8fafc; }
    .shipping-editor-header { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:16px; }
    .shipping-editor h2 { margin:0; color:#0f172a; font-size:16px; }
    .shipping-editor p { margin:5px 0 0; color:#64748b; font-size:11px; line-height:1.5; }
    .shipping-form { display:grid; gap:14px; }
    .shipping-field { display:grid; gap:6px; }
    .shipping-field label { color:#475569; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .shipping-field input, .shipping-field textarea { width:100%; padding:11px 12px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; color:#0f172a; font:inherit; font-size:13px; outline:none; box-sizing:border-box; }
    .shipping-field textarea { min-height:100px; resize:vertical; line-height:1.5; }
    .shipping-field input:focus, .shipping-field textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    .shipping-help { color:#94a3b8; font-size:10px; }
    .shipping-submit { min-height:42px; padding:0 18px; border:0; border-radius:7px; background:#2563eb; color:#fff; font-size:11px; font-weight:900; text-transform:uppercase; cursor:pointer; justify-self:start; }
    .shipping-submit:hover { background:#1d4ed8; }
    @media (max-width:640px) {
      .shipping-editor { padding:14px; }
      .shipping-editor-header { flex-direction:column; }
      .shipping-submit { width:100%; justify-self:stretch; }
    }
    .security-editor { margin-top:18px; padding:18px; border:1px solid #fed7aa; border-radius:10px; background:#fff7ed; }
    .security-editor-header { margin-bottom:16px; }
    .security-editor h2 { margin:0; color:#9a3412; font-size:16px; }
    .security-editor p { margin:5px 0 0; color:#c2410c; font-size:11px; line-height:1.5; }
    .password-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .password-grid .shipping-field:first-child { grid-column:1/-1; }
    .password-requirements { margin:0; padding-left:18px; color:#9a3412; font-size:10px; line-height:1.6; }
    .password-submit { background:#c2410c; }
    .password-submit:hover { background:#9a3412; }
    .password-submit:disabled { cursor:wait; opacity:.82; }
    .password-button-spinner { display:none; width:15px; height:15px; margin-right:8px; border:2px solid rgba(255,255,255,.45); border-top-color:#fff; border-radius:50%; animation:passwordButtonSpin .7s linear infinite; }
    .password-submit.is-loading .password-button-spinner { display:inline-block; }
    @keyframes passwordButtonSpin { to { transform:rotate(360deg); } }
    @media (max-width:640px) {
      .security-editor { padding:14px; }
      .password-grid { grid-template-columns:1fr; }
      .password-grid .shipping-field:first-child { grid-column:auto; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn">← Store Home</a>
    </nav>
       <main>
        <h1 class="main-title">Account Details</h1>
        <div class="grid">
            <div class="info-box">
                <p class="box-label">Account Holder</p>
                <p class="box-val"><?= htmlspecialchars($user['fullname']); ?></p>
            </div>
            <div class="wallet-box">
                <div>
                    <p class="wallet-label">Wallet Balance</p>
                    <p class="wallet-val">KES <?= number_format($wallet_balance, 2); ?></p>
                </div>
                <a href="deposit.php" class="deposit-btn nav-loading-link">➕ Top Up Wallet Balance</a>
            </div>
        </div>

        <div class="details-list">
            <div class="detail-row"><span>Email Account:</span><strong style="color:#111827;"><?= htmlspecialchars($user['email']); ?></strong></div>
            <div class="detail-row"><span>Phone Number:</span><strong style="color:#111827;"><?= htmlspecialchars($user['phone'] ?? 'None Mapped'); ?></strong></div>
            <div class="address-area">
                <span class="box-label" style="color:#6b7280;">Default Shipping Details:</span>
                <div class="address-text">
                    <strong>Delivery phone:</strong> <?= htmlspecialchars($user['shipping_phone'] ?: ($user['phone'] ?? 'Not provided')); ?><br>
                    <strong>Address:</strong><br><?= nl2br(htmlspecialchars($user['shipping_address'] ?: 'No default shipping address has been saved.')); ?>
                </div>
            </div>
        </div>

        <section class="shipping-editor" aria-labelledby="shipping-editor-title">
            <div class="shipping-editor-header">
                <div>
                    <h2 id="shipping-editor-title">Edit Shipping Details</h2>
                    <p>This phone number and address will be used as your default delivery contact information.</p>
                </div>
            </div>
            <?php if ($shipping_success): ?><div class="profile-alert success"><?= htmlspecialchars($shipping_success); ?></div><?php endif; ?>
            <?php if ($shipping_error): ?><div class="profile-alert error"><?= htmlspecialchars($shipping_error); ?></div><?php endif; ?>
            <form method="POST" action="profile.php" class="shipping-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['profile_shipping_csrf']); ?>">
                <div class="shipping-field">
                    <label for="shipping_phone">Delivery phone number</label>
                    <input id="shipping_phone" name="shipping_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" required value="<?= htmlspecialchars($_POST['shipping_phone'] ?? ($user['shipping_phone'] ?: $user['phone'])); ?>" placeholder="0712345678">
                    <span class="shipping-help">Use a Kenyan number that the delivery team can reach.</span>
                </div>
                <div class="shipping-field">
                    <label for="shipping_address">Full delivery address</label>
                    <textarea id="shipping_address" name="shipping_address" maxlength="500" required autocomplete="street-address" placeholder="Building or house, street, area, town/county and nearby landmark"><?= htmlspecialchars($_POST['shipping_address'] ?? ($user['shipping_address'] ?? '')); ?></textarea>
                    <span class="shipping-help">Include the building or house, area, town/county, and a useful landmark.</span>
                </div>
                <button type="submit" name="update_shipping" class="shipping-submit">Save Shipping Details</button>
            </form>
        </section>
        <section class="security-editor" aria-labelledby="password-editor-title">
            <div class="security-editor-header">
                <h2 id="password-editor-title">Change Password</h2>
                <p>Confirm your current password before setting a new account password.</p>
            </div>
            <?php if ($password_success): ?><div class="profile-alert success"><?= htmlspecialchars($password_success); ?></div><?php endif; ?>
            <?php if ($password_error): ?><div class="profile-alert error"><?= htmlspecialchars($password_error); ?></div><?php endif; ?>
            <form method="POST" action="profile.php" class="shipping-form" autocomplete="off" data-password-form>
                <input type="hidden" name="password_csrf_token" value="<?= htmlspecialchars($_SESSION['profile_password_csrf']); ?>">
                <div class="password-grid">
                    <div class="shipping-field">
                        <label for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="shipping-field">
                        <label for="new_password">New password</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="shipping-field">
                        <label for="confirm_password">Confirm new password</label>
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <ul class="password-requirements">
                    <li>At least 8 characters</li>
                    <li>At least one uppercase letter, one lowercase letter, and one number</li>
                    <li>Must be different from the current password</li>
                </ul>
                <button type="submit" name="change_password" class="shipping-submit password-submit" data-password-submit><span class="password-button-spinner" aria-hidden="true"></span><span class="password-button-label">Update Password</span></button>
            </form>
        </section>    </main>

    <!-- Global Full-Screen Tab Transition Spinner Overlay Frame -->
    <div id="global-page-loader" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner" style="width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        <p style="color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; tracking-spacing: wide; letter-spacing: 0.05em; font-family: sans-serif;">Connecting Securely...</p>
    </div>
    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>

    <script>
    document.querySelectorAll('nav a, .back-btn, .nav-loading-link').forEach(link => {
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:')) return;
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            document.getElementById('global-page-loader').style.display = 'flex';
            setTimeout(() => { window.location.href = targetUrl; }, 650);
        });
    });
    </script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
<script src="../js/password-submit.js"></script>
</body>
</html>
