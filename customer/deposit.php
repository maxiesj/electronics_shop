<?php session_start(); 
include '../db.php'; // Pulls your standard $conn MySQLi link variable

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

if (empty($_SESSION['deposit_csrf_token'])) {
    $_SESSION['deposit_csrf_token'] = bin2hex(random_bytes(32));
}
$deposit_csrf_token = $_SESSION['deposit_csrf_token'];

// Fetch the client's current spending balance to display in the header summary card
$w_st = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ?");
$w_st->bind_param("i", $user_id); $w_st->execute(); $w_res = $w_st->get_result()->fetch_assoc();
$current_balance = floatval($w_res['available_balance'] ?? 0.00);

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_deposit'])) {
    $amount = round((float)($_POST['deposit_amount'] ?? 0), 2);
    $phone_num = preg_replace('/\D+/', '', trim($_POST['mpesa_phone'] ?? ''));
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (!is_string($submitted_token) || !hash_equals($_SESSION['deposit_csrf_token'], $submitted_token)) {
        $err = "Your top-up session expired. Please refresh the page and try again.";
    } elseif ($amount < 1 || $amount > 99999999.99) {
        $err = "Please enter a valid deposit amount.";
    } elseif (!preg_match('/^(?:254|0)?7\d{8}$/', $phone_num)) {
        $err = "Please enter a valid Kenyan M-Pesa phone number.";
    } else {
        try {
            $conn->begin_transaction();

            $txn_code = "TXN_DEP_U" . $user_id . "_" . strtoupper(bin2hex(random_bytes(4)));

            $wallet_upsert = $conn->prepare(
                "INSERT INTO customer_wallets (user_id, available_balance, updated_at, is_active_toggle)
                 VALUES (?, ?, NOW(), 1)
                 ON DUPLICATE KEY UPDATE
                    available_balance = available_balance + VALUES(available_balance),
                    updated_at = NOW(),
                    is_active_toggle = 1"
            );
            $wallet_upsert->bind_param("id", $user_id, $amount);
            if (!$wallet_upsert->execute() || $wallet_upsert->affected_rows < 1) {
                throw new Exception("The wallet balance could not be updated.");
            }
            $wallet_upsert->close();

            $ins_p = $conn->prepare(
                "INSERT INTO payments (order_id, payment_method, transaction_code, amount, payment_status, created_at)
                 VALUES (NULL, 'M-Pesa Deposit', ?, ?, 'completed', NOW())"
            );
            $ins_p->bind_param("sd", $txn_code, $amount);
            if (!$ins_p->execute()) {
                throw new Exception("The top-up ledger entry could not be recorded.");
            }
            $ins_p->close();

            $balance_stmt = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ? LIMIT 1");
            $balance_stmt->bind_param("i", $user_id);
            $balance_stmt->execute();
            $stored_wallet = $balance_stmt->get_result()->fetch_assoc();
            $balance_stmt->close();

            if (!$stored_wallet) {
                throw new Exception("The wallet record could not be verified after the top-up.");
            }

            $conn->commit();
            $current_balance = (float)$stored_wallet['available_balance'];
            $_SESSION['deposit_csrf_token'] = bin2hex(random_bytes(32));
            $deposit_csrf_token = $_SESSION['deposit_csrf_token'];
            $msg = "Top-up of KES " . number_format($amount, 2) . " was credited successfully. New balance: KES " . number_format($current_balance, 2) . ". Code: {$txn_code}";
        } catch (Exception $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Top Up Wallet</title>
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Deposit Card Container (Default Desktop View) */
    main { max-width: 32rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Current Wallet Balance Box Elements */
    .balance-box { padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; text-align: center; margin-bottom: 24px; box-sizing: border-box; }
    .balance-label { font-size: 10px; color: #9ca3af; font-weight: 700; text-transform: uppercase; margin: 0; }
    .balance-val { font-size: 1.75rem; font-weight: 900; color: #059669; margin: 4px 0 0; white-space: nowrap; }
    
    /* Transaction Messaging Alerts Box Styles */
    .alert-box { padding: 12px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; box-sizing: border-box; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    
    /* 4. Deposit Submission Form Fields & CTA Action Controls */
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.75rem; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 6px; }
    
    /* Amount Numeric Data Input */
    .form-input { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; font-size: 0.875rem; color: #111827; outline: none; font-weight: 700; height: 42px; }
    .form-input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1); }
    
    /* Primary Deposit Form Execution Button Block */
    .submit-btn { width: 100%; background-color: #f97316; color: white; padding: 12px 0; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; box-shadow: 0 4px 6px -1px rgba(249,115,22,0.2); transition: background-color 0.2s ease; height: 44px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .submit-btn:hover { background-color: #ea580c; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Restructure Navbar items to clean stacked columns flow */
        nav { flex-direction: column; gap: 12px; padding: 12px 16px; text-align: center; }
        
        /* Main Document Wrapper padding boundaries shrinkages */
        main { margin: 16px; padding: 16px; border-radius: 0.5rem; }
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Balance display optimizations for compact viewports */
        .balance-box { padding: 14px; margin-bottom: 16px; }
        .balance-val { font-size: 1.5rem; }
        
        /* Increase thumb click boundary tracking surfaces */
        .form-input { height: 46px; font-size: 0.95rem; }
        .submit-btn { height: 48px; font-size: 0.9rem; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="profile.php" class="back-btn">← Back to Profile</a>
    </nav>
    <main>
        <h1 class="main-title">Top Up Wallet via M-Pesa</h1>
        
        <div class="balance-box">
            <p class="balance-label">Current Account Balance</p>
            <p class="balance-val">KES <?= number_format($current_balance, 2); ?></p>
        </div>

        <?php if (!empty($msg)): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($err)): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div><?php endif; ?>

                <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($deposit_csrf_token); ?>">
            <div class="form-group">
                <label class="form-label">Enter Deposit Amount (KES):</label>
                <input type="number" step="0.01" name="deposit_amount" min="1" placeholder="e.g. 500" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">M-Pesa Mobile Number:</label>
                <input type="text" name="mpesa_phone" placeholder="e.g. 07XXXXXXXX" required class="form-input" value="0712345678">
            </div>
            <button type="submit" name="trigger_deposit" class="submit-btn">Request STK Push Payment</button>
        </form>
    </main>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
