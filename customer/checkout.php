<?php session_start(); 
include '../db.php'; // Centralized MySQLi connection link

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}
$checkout_csrf_token = $_SESSION['checkout_csrf_token'];

// Fetch funding limit metrics safely using native MySQLi drivers parameters
$w_st = $conn->prepare("SELECT available_balance FROM customer_wallets WHERE user_id = ?");
$w_st->bind_param("i", $user_id); $w_st->execute(); $w_res = $w_st->get_result()->fetch_assoc();
$bal = floatval($w_res['available_balance'] ?? 0.00);

// Fetch tax configuration data parameters rows rules
$tx_st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'tax_rate' LIMIT 1");
$tx_st->execute(); $tx_res = $tx_st->get_result()->fetch_assoc();
$tax = floatval($tx_res['setting_value'] ?? 7.00);

// Compile active totals values calculations
$c_st = $conn->prepare("SELECT c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$c_st->bind_param("i", $user_id); $c_st->execute(); $cart_rows = $c_st->get_result()->fetch_all(MYSQLI_ASSOC);

$total = 0; foreach ($cart_rows as $i) { $total += $i['price'] * $i['quantity']; } 
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Checkout</title>
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Checkout Container (Default Desktop View) */
    main { max-width: 48rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Order Pricing Grid Metrics */
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-bottom: 24px; }
    .info-box { padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-sizing: border-box; }
    .box-label { font-size: 10px; color: #9ca3af; font-weight: 700; text-transform: uppercase; margin: 0; }
    .box-val { font-size: 1.25rem; font-weight: 900; margin: 4px 0 0; white-space: nowrap; }
    
    /* 4. Payment Method Mode Selections & CTA Actions */
    .pm-box { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; margin-bottom: 24px; box-sizing: border-box; }
    .pm-label { display: block; font-size: 11px; font-weight: 800; color: #4b5563; text-transform: uppercase; margin-bottom: 6px; }
    .pm-select { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; background-color: white; font-weight: 700; font-size: 0.875rem; outline: none; color: #111827; cursor: pointer; height: 42px; box-sizing: border-box; }
    .pm-select:focus { border-color: #2563eb; }
    .pm-guidance { margin: 10px 0 0; color: #4b5563; font-size: 0.8rem; line-height: 1.45; }
    
    /* Primary Complete Checkout Order Button */
    .pay-btn { width: 100%; background-color: #f97316; color: white; padding: 12px 0; border: none; border-radius: 6px; cursor: pointer; text-align: center; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; transition: background-color 0.2s ease; height: 44px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .pay-btn:hover { background-color: #ea580c; }
    .pay-btn:disabled { background-color: #9ca3af; cursor: not-allowed; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Stack global navigation menu items vertically */
        nav { flex-direction: column; gap: 12px; padding: 12px 16px; text-align: center; }
        
        /* Main Document Wrapper padding boundaries shrinkages */
        main { margin: 16px; padding: 16px; border-radius: 0.5rem; }
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Flatten pricing grid summary blocks into standalone rows */
        .grid { grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
        .info-box { padding: 14px; }
        .box-val { font-size: 1.15rem; }
        
        /* Touch friendly height adjustments for inputs and form execution button blocks */
        .pm-box { padding: 10px; margin-bottom: 20px; }
        .pm-select { height: 46px; font-size: 0.9rem; }
        .pay-btn { height: 48px; font-size: 0.9rem; letter-spacing: 0.03em; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="cart.php" class="back-btn">← Return to Cart</a>
    </nav>
    <main>
        <h1 class="main-title">Complete Secure Checkout</h1>
        <div class="grid">
            <div class="info-box">
                <p class="box-label">Wallet Balance Limits</p>
                <p class="box-val" style="color: #059669;">KES <?= number_format($bal, 2); ?></p>
            </div>
            <div class="info-box">
                <p class="box-label">Order Gross Total (<?= $tax; ?>% VAT Incl.)</p>
                <p class="box-val" style="color: #111827;">KES <?= number_format($total, 2); ?></p>
            </div>
        </div>

        <!-- Integrated Payment Mode Selector Dropdown Component Wrapper -->
        <div class="pm-box">
            <label class="pm-label">Choose Settlement Mode:</label>
            <select id="payment_method" class="pm-select">
                <option value="wallet" selected>💳 Personal Account Digital Wallet</option>
                <option value="mpesa">📱 M-Pesa Wallet Top-Up</option>
                <option value="polepole">⏱️ Lipa Pole Pole Installments Scheme Plan</option>
            </select>
            <p id="payment-guidance" class="pm-guidance">Pays the full order total from your available wallet balance.</p>
        </div>

        <button id="pay-btn" class="pay-btn">Authorize Order Settlement</button>
    </main>
    <script>
    const paymentMethod = document.getElementById('payment_method');
    const paymentGuidance = document.getElementById('payment-guidance');
    const payButton = document.getElementById('pay-btn');
    const guidance = {
        wallet: 'Pays the full order total from your available wallet balance.',
        mpesa: 'M-Pesa checkout is not enabled yet. Continue to wallet top-up, then pay using your wallet.',
        polepole: 'Pays a 50% deposit from your wallet and creates an installment plan for the balance.'
    };

    paymentMethod.addEventListener('change', function () {
        paymentGuidance.textContent = guidance[this.value] || '';
        payButton.textContent = this.value === 'mpesa' ? 'Continue to Wallet Top-Up' : 'Authorize Order Settlement';
    });

    payButton.addEventListener('click', function() {
        const method = paymentMethod.value;
        if (method === 'mpesa') {
            window.location.href = 'deposit.php';
            return;
        }

        if (confirm("Confirm checkout authorization using selected payment method structure?")) {
            payButton.disabled = true;
            payButton.textContent = 'Processing...';
            const formData = new FormData();
            formData.append('payment_method', method);
            formData.append('csrf_token', <?= json_encode($checkout_csrf_token); ?>);

            fetch('checkout_form_process.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                alert(d.message);
                if (d.status === 'success') {
                    if (method === 'polepole') window.location.href = 'pay_installment.php';
                    else window.location.href = 'confirmation.php?order_id=' + d.order_id;
                    return;
                }
                payButton.disabled = false;
                payButton.textContent = 'Authorize Order Settlement';
            }).catch(err => {
                alert("Network transmission interruption: " + err.message);
                payButton.disabled = false;
                payButton.textContent = 'Authorize Order Settlement';
            });
        }
    });
    </script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
