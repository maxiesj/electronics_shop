<?php session_start(); 
include '../db.php';
require_once '../order_payment_guard.php'; // Centralized paid/outstanding balance calculation.

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

// Fetch historical order records using explicit MySQLi queries matching your layout
$st = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$st->bind_param("i", $user_id); 
$st->execute(); 
$orders = $st->get_result()->fetch_all(MYSQLI_ASSOC); 
$review_stmt = $conn->prepare("
    SELECT product_id, star_rating, review_comment, created_at
    FROM product_reviews
    WHERE user_id = ? AND is_approved = 1
    ORDER BY id DESC
");
$review_stmt->bind_param("i", $user_id);
$review_stmt->execute();
$review_rows = $review_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$review_stmt->close();

$reviews_by_product = [];

foreach ($review_rows as $review) {
    if (!isset($reviews_by_product[$review['product_id']])) {
        $reviews_by_product[$review['product_id']] = $review;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>Your Historical Orders</title> 
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    .back-btn.is-loading { pointer-events: none; opacity: 0.72; }
    .back-loader { display: none; width: 13px; height: 13px; margin-right: 7px; border: 2px solid #6b7280; border-top-color: #ffffff; border-radius: 50%; animation: backNavSpin 0.7s linear infinite; }
    .back-btn.is-loading .back-loader { display: inline-block; }
    @keyframes backNavSpin { to { transform: rotate(360deg); } }
    
    /* 3. Core Content Dashboard Layout (Default Desktop View) */
    main { max-width: 56rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Invoice Card Frame Structures */
    .order-card { padding: 20px; border: 1px solid #e5e7eb; border-radius: 0.75rem; background-color: #f9fafb; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); box-sizing: border-box; }
    .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; font-size: 0.75rem; font-weight: 700; color: #6b7280; gap: 16px; }
    .id-text { color: #2563eb; }
    .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    
    /* Status Labels and Quick CTA Links */
    .status-badge { background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; font-size: 10px; font-weight: 800; white-space: nowrap; }
    .status-badge.pending { background-color: #fef3c7; color: #92400e; }
    .polepole-badge { background:#ede9fe; color:#6d28d9; padding:3px 8px; border-radius:999px; font-size:10px; font-weight:900; text-transform:uppercase; white-space:nowrap; }
    .inline-pay-btn { background-color: #f97316; color: white; border: none; border-radius: 4px; padding: 4px 10px; font-size: 10px; font-weight: 800; text-transform: uppercase; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; height: 24px; box-sizing: border-box; transition: background-color 0.2s ease; }
    .inline-pay-btn:hover { background-color: #ea580c; }
    
    /* Countdown and Reminder Alerts Box Styles */
    .tracking-banner { padding: 10px 14px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-sizing: border-box; }
    .banner-normal { background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .banner-warning { background-color: #fffaf0; color: #c2410c; border: 1px solid #ffedd5; animation: pulse 2s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.9; } }

    /* 4. Product Row Item Lists */
    .divide-row { padding: 12px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px dashed #e5e7eb; box-sizing: border-box; }
    .divide-row:last-child { border-bottom: none; }
    .product-info { display: flex; align-items: center; gap: 12px; }
    .img-box { width: 40px; height: 40px; background-color: white; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 2px; overflow: hidden; display: flex; align-items: center; justify-content: center; shrink: 0; flex-shrink: 0; }
    .img-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .item-title { font-weight: 700; color: #1f2937; font-size: 0.75rem; text-transform: uppercase; margin: 0; line-height: 1.3; }
    .item-qty { font-size: 0.75rem; color: #9ca3af; margin: 2px 0 0; font-weight: 500; }
    
    /* Product Reviews & Pricing Columns */
    .action-col { text-align: right; display: flex; align-items: center; gap: 16px; justify-content: flex-end; }
    .item-price { font-weight: 700; color: #374151; font-size: 0.75rem; margin: 0; white-space: nowrap; }
    .feedback-btn { background-color: white; border: 1px solid #d1d5db; color: #374151; font-weight: 700; font-size: 10px; padding: 6px 10px; border-radius: 4px; text-decoration: none; text-transform: uppercase; display: inline-flex; align-items: center; justify-content: center; height: 26px; box-sizing: border-box; transition: background-color 0.2s ease; white-space: nowrap; }
    .feedback-btn:hover { background-color: #f3f4f6; color: black; }
    
    /* Card Summary Footers */
    .card-footer { margin-top: 16px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: right; font-size: 0.75rem; font-weight: 700; color: #6b7280; }
    .footer-price { color: #059669; font-weight: 900; font-size: 0.875rem; margin-left: 4px; white-space: nowrap; }

     /* ==========================================================================
       5. RESPONSIVE SCREEN QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 TRANSITIONAL PORTRAIT TABLETS & SMARTPHONES BREAKPOINT (Max 768px Viewports) */
    @media (max-width: 768px) {
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
            background-color: #0f172a !important; /* Rich deep signature dark background */
            z-index: 9999 !important; /* Forces layout profiles to pass underneath the menu */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1e293b !important;
        }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your data content safely below the fixed nav height */
        main { 
            margin-top: 140px !important; /* Pushes order history cards safely out of the navigation zone */
            margin-left: 16px !important;
            margin-right: 16px !important;
            margin-bottom: 16px !important;
            padding: 16px !important; 
            border-radius: 0.5rem !important; 
            box-sizing: border-box !important;
            display: block !important;
            width: calc(100% - 32px) !important;
        }
        
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Flatten order block card headers elements into stacked vertical flow */
        .card-header { 
            display: flex !important;
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 10px !important; 
            padding-bottom: 10px !important; 
            width: 100% !important;
        }
        
        .header-actions { 
            display: flex !important;
            width: 100% !important; 
            justify-content: flex-start !important; 
            gap: 6px !important; 
        }
        
        .inline-pay-btn { height: 32px; padding: 0 12px; font-size: 11px; }
        
        /* Wrap live layout order tracking steps elements components vertically */
        .tracking-banner { 
            display: flex !important;
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 6px !important; 
            padding: 10px !important; 
            font-size: 0.7rem !important; 
            width: 100% !important;
        }
        
        /* Flatten internal product rows into stacked horizontal view lines blocks */
        .divide-row { 
            display: flex !important;
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 14px !important; 
            padding: 14px 0 !important; 
            width: 100% !important;
        }
        
        .product-info { 
            width: 100% !important; 
        }
        
        /* Interactive actions adjustments for easier touch tracking controls */
        .action-col { 
            display: flex !important;
            width: 100% !important; 
            justify-content: space-between !important; 
            border-top: 1px dashed #e5e7eb !important; 
            padding-top: 10px !important; 
            margin-top: 2px !important; 
        }
        
        .feedback-btn { height: 36px; padding: 0 14px; font-size: 11px; }
        .card-footer { font-size: 0.7rem; width: 100% !important; }
        .footer-price { font-size: 0.8rem; }
    }
	.product-review {
    margin: -2px 0 12px 52px;
    padding: 10px 12px;
    border-left: 3px solid #f59e0b;
    background: #fffbeb;
    border-radius: 0 6px 6px 0;
}

.product-review-title {
    font-size: 11px;
    font-weight: 800;
    color: #92400e;
    text-transform: uppercase;
}

.product-review-text {
    margin: 5px 0 0;
    color: #4b5563;
    font-size: 12px;
    line-height: 1.45;
}

</style>
<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn" id="backHomeLink"><span class="back-loader" aria-hidden="true"></span><span class="back-home-text">← Back to Home</span></a>
    </nav>
   <main>
        <h1 class="main-title">Historical Invoices</h1>
        <?php if (count($orders) > 0): foreach ($orders as $order): 
            $current_status = strtolower($order['order_status'] ?? 'pending');
            $order_id = $order['id'];

            // 🛠️ BUG FIX ROUTINE: Authenticate relationship with layaway ledger
            $stmt_layaway = $conn->prepare("SELECT * FROM layaway_plans WHERE order_id = ?");
            $stmt_layaway->bind_param("i", $order_id);
            $stmt_layaway->execute();
            $layaway_res = $stmt_layaway->get_result();
            $layaway_plan = $layaway_res->fetch_assoc();

            // Fetch linked item rows using your exact database column schema (product_name)
            $stmt_items = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            $order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
            <div class="order-card" style="box-sizing: border-box;">
                <div class="card-header">
                    <div>Invoice ID: <span class="id-text">#<?= $order_id; ?></span></div>
                    <div>Date: <?= htmlspecialchars($order['created_at']); ?></div>
                    <div class="header-actions">
                        <span class="status-badge <?= $current_status; ?>"><?= $current_status; ?></span>
                        <?php
                            $settlement_state = getOrderSettlementState($conn, (int)$order_id);
                            $is_fully_paid = $settlement_state && $settlement_state['is_fully_paid'];
                        ?>
                        <?php if ($layaway_plan): ?>
                            <span class="polepole-badge">&#9203; Lipa Pole Pole</span>
                        <?php endif; ?>
                        <?php if ($layaway_plan || $is_fully_paid): ?>
                            <a href="statement.php?order_id=<?= $order_id; ?>" class="feedback-btn" style="height:24px;">&#128196; Statement</a>
                        <?php endif; ?>
                        <?php if ($layaway_plan && (float)$layaway_plan['balance_remaining'] > 0.009 && strtolower((string)$layaway_plan['status']) === 'active'): ?>
                            <a href="pay_installment.php?order_id=<?= $order_id; ?>" class="inline-pay-btn">&#128179; Pay Installment</a>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Render Tracing Banner Component ONLY if verified layaway relationship exists -->
                <?php if ($layaway_plan): 
                    // Example simple countdown display computation logic
                    $created_time = strtotime($layaway_plan['created_at']);
                    $due_time = strtotime('+30 days', $created_time);
                    $days_passed = max(0, (int)floor((time() - $created_time) / 86400));
                    $days_remaining = max(0, (int)ceil(($due_time - time()) / 86400));
                    $plan_day = min(30, $days_passed + 1);
                    $is_final_ten_days = (float)$layaway_plan['balance_remaining'] > 0.009 && $days_remaining <= 10;
                    $banner_class = $is_final_ten_days ? 'banner-warning' : 'banner-normal';
                ?>
                    <div class="tracking-banner <?= $banner_class; ?>">
                        <div>📊 Lipa Pole Pole (Day <?= $plan_day; ?>): payment window ends <?= date('d M Y, h:i A', $due_time); ?>. <?= $days_remaining > 0 ? 'Days remaining: ' . $days_remaining : 'Payment is now due.'; ?></div>
                        <?php if ($is_final_ten_days): ?><div style="margin-top:5px;">A friendly reminder: please continue paying any amount you can toward the balance. On days 21–25, one reminder is sent at 1:00 PM. On days 26–30, reminders are sent at 9:00 AM and 6:00 PM until the balance is cleared.</div><?php endif; ?>
                    </div>
                    <div class="tracking-banner banner-normal" style="background:#fff5f5; color:#e53e3e; border-color:#fed7d7; margin-top:-8px; margin-bottom:14px;">
                        <div>Initial 50% Deposit: <span style="font-weight:900;">KES <?= number_format($order['total_amount'] * 0.50, 2); ?></span> &nbsp;|&nbsp; Total Deposited So Far: <span style="font-weight:900; color:#2f855a;">KES <?= number_format($layaway_plan['deposit_paid'], 2); ?></span> &nbsp;|&nbsp; Outstanding Balance Owed: <span style="font-weight:900;">KES <?= number_format($layaway_plan['balance_remaining'], 2); ?></span></div>
                    </div>
                <?php endif; ?>

                <!-- Product Line Items List Container Container -->
                <div style="display: flex; flex-direction: column; width: 100%; gap: 4px;">
                    <?php foreach ($order_items as $item): ?>
					<?php
						$product_review = $reviews_by_product[$item['product_id']] ?? null;
						$can_edit_review = $product_review
							&& (time() - strtotime($product_review['created_at'])) <= 900;
						?>
                        <div class="divide-row" style="display: flex; align-items: center; justify-content: space-between; width: 100%; box-sizing: border-box; float: none;">
                            
                            <!-- Left Block Container: Package icon, Title, Quantity, and Unit math -->
                            <div class="product-info" style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                <div class="img-box" style="display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <span style="font-size: 18px;">📦</span>
                                </div>
                                <div>
                                    <h4 class="item-title" style="margin: 0; padding: 0;"><?= htmlspecialchars($item['product_name'] ?? 'Electronic Item'); ?></h4>
                                    <div class="item-qty" style="margin-top: 4px;">
                                        Qty ordered: <span style="font-weight: 700; color: #1f2937;"><?= $item['quantity']; ?> units</span> 
                                        <span style="color: #94a3b8; font-weight: 400; margin-left: 6px;">
                                            @ KES <?= number_format($item['price'], 2); ?> each
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Block Container: Row calculation sum and feedback submission trigger -->
                            <div class="action-col" style="text-align: right; display: flex; align-items: center; gap: 16px; justify-content: flex-end; flex-shrink: 0;">
                                <p class="item-price" style="margin: 0; padding: 0; white-space: nowrap;">KES <?= number_format($item['price'] * $item['quantity'], 2); ?></p>
                                
                                <?php if ($current_status === 'delivered'): ?>
							<a href="submit_review.php?product_id=<?= $item['product_id']; ?>&order_id=<?= $order_id; ?>" class="feedback-btn">
								<?= !$product_review ? 'Feedback' : ($can_edit_review ? 'Edit Review' : 'View Review'); ?>
							</a>
						<?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
				
				<?php if ($product_review && $current_status === 'delivered'): ?>
    <div class="product-review">
        <div class="product-review-title">
            Your review: <?= str_repeat('⭐', (int) $product_review['star_rating']); ?>
        </div>

        <p class="product-review-text">
            <?= nl2br(htmlspecialchars($product_review['review_comment'])); ?>
        </p>

        <small style="color:#9ca3af; font-size:10px;">
            Submitted <?= htmlspecialchars($product_review['created_at']); ?>
        </small>
    </div>
<?php endif; ?>

                <div class="card-footer">
                    Total Contract Valuation Price: <span class="footer-price">KES <?= number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p style="text-align:center; padding:40px; color:#6b7280; font-style:italic;">You haven't placed any orders yet.</p>
        <?php endif; ?>
    </main>




    <script>
    // Locate your existing AJAX cart submission snippet
document.querySelectorAll('.cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevents standard hard page refreshes
        
        const currentForm = this;
        const formData = new FormData(currentForm);
        
        fetch('add_to_cart_handler.php', { // Swap with your actual cart processing endpoint filename
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // 1. DYNAMIC UI FIX: Locate the numeric text field inside the current card context
                const qtyInput = currentForm.querySelector('.qty-input');
                if (qtyInput) {
                    qtyInput.value = '1'; // Snaps back to standard baseline order threshold
                }
                
                // 2. OPTIONAL: Proactively update the navigation cart count badge layout parameters instantly
                const cartCounter = document.querySelector('.cart-count-badge-selector'); // Update to match your navbar selector
                if (cartCounter && data.new_cart_total) {
                    cartCounter.textContent = data.new_cart_total;
                }
                
                alert("Product pushed to basket successfully!");
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => console.error('Error handling transaction splits:', error));
    });
});

    </script>
<script>
    // Keep the loading state visible briefly before returning to the customer home page.
    const backHomeLink = document.getElementById('backHomeLink');
    if (backHomeLink) {
        backHomeLink.addEventListener('click', function(event) {
            event.preventDefault();
            backHomeLink.classList.add('is-loading');
            backHomeLink.querySelector('.back-home-text').textContent = 'Opening Home...';
            setTimeout(function() {
                window.location.href = backHomeLink.href;
            }, 650);
        });
    }
</script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
