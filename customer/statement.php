<?php session_start();
include '../db.php'; // Pulls the standard $conn MySQLi link variable

$user_id = $_SESSION['user_id'] ?? null;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$user_id || $order_id <= 0) { header("Location: my_orders.php"); exit; }

// 1. Fetch Primary Master Order Entry Records Data
$o_st = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$o_st->bind_param("ii", $order_id, $user_id); $o_st->execute();
$order = $o_st->get_result()->fetch_assoc();
if (!$order) { die("The requested order reference entry was not found on this server."); }

// 2. Query Item Purchases Breakdown Splits Data
$i_st = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$i_st->bind_param("i", $order_id); $i_st->execute();
$order_items = $i_st->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Query All Installment & Deposit Transactions Logged in the Payments Table
$p_st = $conn->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id ASC");
$p_st->bind_param("i", $order_id); $p_st->execute();
$payments_history = $p_st->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals summaries
$total_paid_so_far = 0;
foreach ($payments_history as $pay) {
    if (strtolower($pay['payment_status'] ?? 'completed') === 'completed') {
        $total_paid_so_far += $pay['amount'];
    }
}
$outstanding_debt_balance = max(0, $order['total_amount'] - $total_paid_so_far);
$overall_status = strtolower($order['order_status'] ?? 'pending');
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Statement of Account - #<?= $order_id; ?></title>
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Statement Container (Default Desktop View) */
    main { max-width: 56rem; margin: 40px auto; padding: 32px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.05); box-sizing: border-box; }
    .title-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 24px; gap: 16px; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0; }
    
    /* Order Header Badges */
    .status-header-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 4px; letter-spacing: 0.05em; white-space: nowrap; }
    .status-header-badge.pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .status-header-badge.delivered { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    
    /* Financial Metadata Layout Grid */
    .meta-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 32px; }
    .meta-box { padding: 12px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
    .box-label { font-size: 9px; color: #9ca3af; font-weight: 800; text-transform: uppercase; margin: 0; }
    .box-val { font-size: 1.1rem; font-weight: 900; color: #111827; margin: 4px 0 0; }

    /* 4. Table and Section Elements */
    .section-heading { font-size: 0.875rem; font-weight: 900; color: #374151; text-transform: uppercase; margin: 24px 0 12px; letter-spacing: 0.05em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 0.813rem; }
    th { background-color: #111827; color: white; text-align: left; padding: 8px 12px; font-weight: 700; text-transform: uppercase; font-size: 10px; }
    td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; }
    tr:nth-child(even) td { background-color: #f9fafb; }
    .text-right { text-align: right; }
    .font-bold { font-weight: 700; }

    /* Full-Screen Page Loader CSS Spinner */
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

       /* ==========================================================================
       5. RESPONSIVE SCREEN QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 MID-SIZED DEVICES BREAKPOINT (TABLETS - Between 641px and 1024px Width) */
    @media (max-width: 1024px) {
        .meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px !important; }
        main { margin: 24px auto !important; padding: 24px !important; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* FORCE EVERYTHING TO INHERIT STRICT CONTAINER BORDERS WITH NO INLINE VARIATIONS */
        *, *:before, *::after {
            box-sizing: border-box !important;
        }

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
            background-color: #0f172a !important; /* Rich deep signature dark background matching your app theme */
            z-index: 9999 !important; /* Forces all product specs to pass cleanly underneath the menu bar */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1e293b !important;
        }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your data content safely below the fixed nav height */
        main { 
            margin-top: 140px !important; /* Pushes the product layout content card safely out of the navigation zone */
            margin-left: 12px !important;
            margin-right: 12px !important;
            margin-bottom: 12px !important;
            padding: 16px !important; 
            border-radius: 0.5rem !important; 
            box-sizing: border-box !important;
            display: block !important;
            width: calc(100% - 24px) !important;
        }
        
        /* Stack heading block data items */
        .title-row { 
            display: flex !important;
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 8px !important; 
            width: 100% !important;
        }
        .main-title { font-size: 1.25rem; }
        
        /* Collapse Financial Specs / Product Meta Grid info rows into standalone single columns blocks */
        .meta-grid { 
            display: flex !important;
            flex-direction: column !important;
            grid-template-columns: 1fr !important; 
            gap: 10px !important; 
            margin-bottom: 20px !important; 
            width: 100% !important;
        }
        
        /* Clear out inline parameter blocks on child elements for uniform spacing profiles */
        .meta-grid > div, .info-box, .spec-box {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .box-val { font-size: 1rem; }
        
        /* Responsive Horizontal Table / Specs Matrix Scrolling Solution */
        table { 
            display: block !important; 
            width: 100% !important; 
            overflow-x: auto !important; /* Enable native horizontal swipe tracking controls */
            -webkit-overflow-scrolling: touch !important; /* Adds smooth touch acceleration physics on iOS devices */
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
        }
        th, td { 
            white-space: nowrap !important; /* Prevents values from wrapping onto separate vertical rows */
            padding: 8px 10px !important; 
        }
        
        /* Universal Form Fields and Action Buttons Mobile Scaling layout profiles */
        form {
            display: flex !important;
            flex-direction: column !important;
            gap: 10px !important;
            width: 100% !important;
        }
        form input, form select, form button, button {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            font-size: 16px !important; /* Blocks native iOS Safari screen zoom bugs entirely */
            height: 44px !important; /* Optimizes tap boundary targets on touch screens */
        }
    }

</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="my_orders.php" class="back-btn">← Back to Invoices</a>
    </nav>
    <main>
        <div class="title-row">
            <div>
                <h1 class="main-title">Statement of Account</h1>
                <span style="font-size: 11px; font-weight:700; color:#9ca3af; text-transform:uppercase;">Order Ref: #<?= $order_id; ?></span>
            </div>
            <!-- Overall reality status tracker pinned clearly at top layout boundary header position -->
            <div class="status-header-badge <?= ($overall_status === 'pending' || $overall_status === 'pending installment') ? 'pending' : 'delivered'; ?>">
                Contract State: <?= htmlspecialchars($order['order_status'] ?? 'pending'); ?>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box"><p class="box-label">KRA PIN Mapped</p><p class="box-val" style="font-size:0.9rem; text-transform:uppercase;"><?= htmlspecialchars($order['kra_pin']); ?></p></div>
            <div class="meta-box"><p class="box-label">Contract Total</p><p class="box-val">KES <?= number_format($order['total_amount'], 2); ?></p></div>
            <div class="meta-box"><p class="box-label">Total Amount Paid</p><p class="box-val" style="color:#059669;">KES <?= number_format($total_paid_so_far, 2); ?></p></div>
            <div class="meta-box"><p class="box-label">Outstanding Balance</p><p class="box-val" style="color:#ef4444;">KES <?= number_format($outstanding_debt_balance, 2); ?></p></div>
        </div>

        <h3 class="section-heading">1. Items Under This Contract</h3>
        <table>
            <thead>
                <tr>
                    <th>Product Name Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Net Price</th>
                    <th class="text-right">VAT Price (<?= $order['applied_tax_rate']; ?>%)</th>
                    <th class="text-right">Gross Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                <tr>
                    <td class="font-bold text-transform: uppercase;"><?= htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-right"><?= $item['quantity']; ?></td>
                    <td class="text-right">KES <?= number_format($item['net_price'] * $item['quantity'], 2); ?></td>
                    <td class="text-right">KES <?= number_format($item['vat_price'] * $item['quantity'], 2); ?></td>
                    <td class="text-right font-bold">KES <?= number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3 class="section-heading">2. Transaction & Installment Payment Audit Log</h3>
        <table>
            <thead>
                <tr>
                    <th>Payment Date Logging</th>
                    <th>Settlement Route Method</th>
                    <th>Unique Transaction Code Reference</th>
                    <th class="text-right">Cash Amount Processed</th>
                    <th>Audit Status</th>
                </tr>
            </thead>
             <tbody>
                <?php if (count($payments_history) > 0): foreach ($payments_history as $pay): ?>
                <tr>
                    <td><?= htmlspecialchars($pay['created_at']); ?></td>
                    <td class="font-bold"><?= htmlspecialchars($pay['payment_method']); ?></td>
                    <!-- Updated to pull the precise, un-cropped column value tracking string directly -->
                    <td style="font-family: monospace; font-weight:700; color:#2563eb; text-transform: uppercase;">
                        <?= htmlspecialchars($pay['transaction_code'] ? $pay['transaction_code'] : 'TXN_SIMULATED_OK'); ?>
                    </td>
                    <td class="text-right font-bold" style="color: #059669;">KES <?= number_format($pay['amount'], 2); ?></td>
                    <td style="font-size:10px; font-weight:800; color:#065f46;"><span style="background-color:#d1fae5; padding:2px 6px; border-radius:4px;">TXN SETTLED</span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #9ca3af; font-weight: 600; padding: 24px 0;">No active payments logged under this installment reference.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <!-- Global Full-Screen Tab Transition Spinner Overlay Frame -->
    <div id="global-page-loader" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner" style="width: 40px; height: 40px; border-width: 5px; border-top-color: #f97316;"></div>
        <p style="color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; tracking-spacing: wide; letter-spacing: 0.05em; font-family: sans-serif;">Connecting Securely...</p>
    </div>

    <script>

    document.querySelectorAll('.back-btn, .nav-loading-link').forEach(link => {
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:')) return;
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            document.getElementById('global-page-loader').style.display = 'flex';
            setTimeout(() => { 
                window.location.href = targetUrl; 
            }, 400);
        });
    });
    </script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>

