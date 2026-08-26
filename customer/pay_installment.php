<?php session_start(); 
include '../db.php'; // Pulls the standard $conn MySQLi link variable

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

$msg = ''; $err = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) { 
    $plan_id = (int)$_POST['plan_id']; 
    $amt = floatval($_POST['payment_amount']);
    $pay_method = isset($_POST['installment_method']) ? trim($_POST['installment_method']) : 'wallet';
    
    if ($amt <= 0) { 
        $err = "Please input a valid positive cash contribution amount."; 
    } else { 
        try { 
            $conn->begin_transaction();
            
            $pl_st = $conn->prepare("SELECT * FROM layaway_plans WHERE id = ? AND user_id = ? AND LOWER(TRIM(status)) = 'active' FOR UPDATE"); 
            $pl_st->bind_param("ii", $plan_id, $user_id); $pl_st->execute(); 
            $plan = $pl_st->get_result()->fetch_assoc();
            
            if (!$plan) throw new Exception("The requested active layaway installment tracking plan records were not found.");
            
            if ($plan['deposit_paid'] == 0.00) {
                $min_deposit = $plan['total_amount'] * 0.50;
                if ($amt < $min_deposit) {
                    throw new Exception("Lipa Pole Pole terms require an initial first installment deposit of at least 50% (Minimum: KES " . number_format($min_deposit, 2) . ").");
                }
            }

            if ($amt < 0.01) throw new Exception("The minimum installment payment is KES 0.01.");
            if ($amt > $plan['balance_remaining']) throw new Exception("Payment amount exceeds outstanding obligation limits. Due: KES " . number_format($plan['balance_remaining'], 2));
            
            if ($pay_method === 'wallet') {
                $w_st = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ? FOR UPDATE"); 
                $w_st->bind_param("i", $user_id); $w_st->execute(); 
                $w_res = $w_st->get_result()->fetch_assoc();
                $w_bal = isset($w_res['available_balance']) ? floatval($w_res['available_balance']) : -1;
                
                if ($w_bal < $amt) throw new Exception("Insufficient digital wallet balance to process this installment ledger line.");
                
                $upd_w = $conn->prepare("UPDATE customer_wallets SET available_balance = available_balance - ?, updated_at = NOW() WHERE user_id = ?"); 
                $upd_w->bind_param("di", $amt, $user_id); $upd_w->execute();
                $pay_label = 'Lipa Pole Pole (Wallet)';
                $txn_prefix = "TXN_POLE_W_";
            } else {
                $pay_label = 'Lipa Pole Pole (M-Pesa)';
                $txn_prefix = "TXN_POLE_M_";
            }
            
            $n_dep = $plan['deposit_paid'] + $amt; 
            $n_bal = $plan['balance_remaining'] - $amt; 
            $n_stat = ($n_bal <= 0) ? 'Completed' : 'Active';
            
            $upd_p = $conn->prepare("UPDATE layaway_plans SET deposit_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
            $upd_p->bind_param("ddsi", $n_dep, $n_bal, $n_stat, $plan_id); $upd_p->execute();
            
            if ($n_bal <= 0) {
                $upd_o = $conn->prepare("UPDATE orders SET order_status = 'processing' WHERE id = ?");
                $upd_o->bind_param("i", $plan['order_id']); $upd_o->execute();
            }

            $txn_code = $txn_prefix . strtoupper(bin2hex(random_bytes(3)));
            $p_status = "completed";
            $ins_p = $conn->prepare("INSERT INTO payments (order_id, payment_method, transaction_code, amount, payment_status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $ins_p->bind_param("issss", $plan['order_id'], $pay_label, $txn_code, $amt, $p_status); $ins_p->execute();
            
            $conn->commit(); 
            $msg = "Installment payment of KES " . number_format($amt, 2) . " via {$pay_method} processed successfully!"; 
        } catch (Exception $e) { 
            $conn->rollback(); $err = $e->getMessage(); 
        } 
    } 
}

$plans_st = $conn->prepare("SELECT * FROM layaway_plans WHERE user_id = ? AND balance_remaining > 0.009 AND LOWER(TRIM(status)) = 'active' ORDER BY id DESC"); 
$plans_st->bind_param("i", $user_id); $plans_st->execute(); 
$active_plans = $plans_st->get_result()->fetch_all(MYSQLI_ASSOC); 
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Installment Plans</title>
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Installment Container (Default Desktop View) */
    main { max-width: 56rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Alert Status Containers */
    .alert-box { padding: 12px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; box-sizing: border-box; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    
    /* Hire-Purchase Plan Cards Flex Layout */
    .plan-card { padding: 20px; border: 1px solid #e5e7eb; border-radius: 0.75rem; background-color: #f9fafb; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 24px; box-sizing: border-box; }
    .plan-id { font-size: 10px; font-weight: 900; background-color: #2563eb; color: white; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; width: fit-content; display: inline-block; }
    
    /* Financial Metrics Layout Grid */
    .metrics-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 12px 0; font-size: 0.75rem; font-weight: 700; width: 100%; }
    .metric-label { font-size: 10px; color: #9ca3af; text-transform: uppercase; margin: 0; }
    .metric-val { margin: 2px 0 0; color: #374151; }
    
    /* 4. Optimized Compact Processing Actions Layout */
    .pay-form { display: flex; align-items: center; gap: 6px; box-sizing: border-box; flex-wrap: wrap; }
    
    /* Select Element - Fixed height/padding balancing */
    .select-input { border: 1px solid #d1d5db; border-radius: 6px; padding: 0 8px; font-size: 0.75rem; font-weight: 700; background-color: white; outline: none; height: 34px; box-sizing: border-box; cursor: pointer; }
    
    /* Numeric Input Field - Restored layout compliance balance */
    .amt-input { width: 90px; border: 1px solid #d1d5db; border-radius: 6px; padding: 0 10px; font-size: 0.875rem; font-weight: 700; outline: none; height: 34px; box-sizing: border-box; }
    
    /* Submit Execution Button Block */
    .pay-btn { background-color: #059669; color: white; font-weight: 800; padding: 0 16px; border: none; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 11px; height: 34px; display: inline-flex; align-items: center; justify-content: center; transition: background-color 0.2s ease; box-sizing: border-box; }
    .pay-btn:hover { background-color: #047857; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 MID-SIZED DEVICES BREAKPOINT (TABLETS - Max 900px Width) */
    @media (max-width: 900px) {
        .plan-card { gap: 16px; padding: 16px; }
        .metrics-grid { gap: 10px; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 768px Width Screens) */
    @media (max-width: 768px) {
        /* Restructure Navbar items to clean stacked columns flow */
        nav { flex-direction: column; gap: 12px; padding: 12px 16px; text-align: center; }
        
        /* Main Document Wrapper boundaries padding reductions */
        main { margin: 16px; padding: 16px; border-radius: 0.5rem; }
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Flip layout card tracking columns to standalone stacked fields */
        .plan-card { flex-direction: column; align-items: flex-start; gap: 20px; }
        
        /* Collapse metrics grid configuration to dual rows layout */
        .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        
        /* Re-route payment forms input rows into full-width items */
        .pay-form { width: 100%; gap: 10px; }
        .select-input { width: 100%; height: 40px; font-size: 0.85rem; padding: 0 12px; }
        .amt-input { width: 100%; height: 40px; font-size: 0.95rem; }
        .pay-btn { width: 100%; height: 42px; font-size: 12px; margin-top: 4px; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn">← Back to Store</a>
    </nav>
     <main>
        <h1 class="main-title">Active Installment Plans</h1>
        <div style="margin:-12px 0 20px;padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1e40af;font-size:12px;line-height:1.5;"><strong>Flexible balance payments:</strong> after the initial 50% deposit, you may pay any amount from KES 0.01 up to the outstanding balance within 30 days.</div>
        <?php if (!empty($msg)): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($err)): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div><?php endif; ?>

        <?php if (count($active_plans) > 0): foreach ($active_plans as $pl): ?>
            <div class="plan-card">
                <div style="flex: 1;">
                    <span class="plan-id">Plan Tracker ID: #<?= $pl['id']; ?></span>
                    <span style="font-size: 11px; color:#9ca3af; font-weight:700; margin-left:8px;">Order Ref: #<?= $pl['order_id']; ?></span>
                    <div class="metrics-grid">
                        <div><p class="metric-label">Total Amount</p><p class="metric-val" style="font-weight: 800;">KES <?= number_format($pl['total_amount'], 2); ?></p></div>
                        <div><p class="metric-label">Deposited Paid</p><p class="metric-val" style="color:#059669; font-weight: 800;">KES <?= number_format($pl['deposit_paid'], 2); ?></p></div>
                        <div><p class="metric-label">Owed Balance</p><p class="metric-val" style="color:#ef4444; font-weight: 800;">KES <?= number_format($pl['balance_remaining'], 2); ?></p></div>
                    </div>
                    <?php if ($pl['deposit_paid'] == 0.00): ?>
                        <p style="margin: 0; font-size: 10px; color: #db2777; font-weight: 700; text-transform: uppercase;">⚠️ 50% First Installment Down-Payment Required (KES <?= number_format($pl['total_amount']*0.5, 2); ?>)</p>
                    <?php endif; ?>
                </div>
                <form method="POST" class="pay-form">
                    <input type="hidden" name="plan_id" value="<?= $pl['id']; ?>">
                    <select name="installment_method" class="select-input">
                        <option value="wallet">Wallet Balance</option>
                        <option value="mpesa">Direct M-Pesa</option>
                    </select>
                    <input type="number" step="0.01" name="payment_amount" placeholder="Any amount" required class="amt-input" min="0.01" max="<?= $pl['balance_remaining']; ?>">
                    <button type="submit" name="process_payment" class="pay-btn">Pay</button>
                </form>
            </div>
        <?php endforeach; else: ?>
            <div style="text-align: center; padding: 64px 0; color: #9ca3af; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">No open layaway balance lines found.</div>
        <?php endif; ?>
    </main>

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
            setTimeout(() => { window.location.href = targetUrl; }, 400);
        });
    });
    </script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
