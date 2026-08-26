<?php session_start(); 
include '../db.php'; // Pulls the standard $conn MySQLi link variable

$user_id = $_SESSION['user_id'] ?? null; 
if (!$user_id) { header("Location: ../register.php"); exit; }

// Handle Active Quantity Updates or Item Deletions Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    if (isset($_POST['update_quantity'])) { 
        $cart_id = (int)$_POST['cart_id']; 
        $new_qty = (int)$_POST['quantity'];
        
        $st = $conn->prepare("SELECT p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        $st->bind_param("ii", $cart_id, $user_id); $st->execute(); 
        $res = $st->get_result()->fetch_assoc();
        
        if ($new_qty > 0 && $new_qty <= (int)($res['stock_quantity'] ?? 0)) {
            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $upd->bind_param("iii", $new_qty, $cart_id, $user_id); $upd->execute();
        }
    } elseif (isset($_POST['remove_item'])) { 
        $cart_id = (int)$_POST['cart_id'];
        $del = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $cart_id, $user_id); $del->execute(); 
    } 
    header("Location: cart.php"); 
    exit; 
}

// Fetch Active Basket Summary tracking data lines
$st = $conn->prepare("SELECT c.id as cart_id, c.quantity, p.product_name, p.price, p.image, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$st->bind_param("i", $user_id); 
$st->execute(); 
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC); 
$total = 0; 
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>Basket Review</title> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    .back-btn.is-loading { pointer-events: none; opacity: 0.72; }
    .back-loader { display: none; align-items: center; gap: 3px; margin-right: 7px; }
    .back-loader i { width: 5px; height: 5px; background: #ffffff; border-radius: 50%; animation: backDotPulse 0.9s ease-in-out infinite; }
    .back-loader i:nth-child(2) { animation-delay: 0.15s; }
    .back-loader i:nth-child(3) { animation-delay: 0.3s; }
    .back-btn.is-loading .back-loader { display: inline-flex; }
    @keyframes backDotPulse { 0%, 80%, 100% { transform: translateY(0); opacity: 0.35; } 40% { transform: translateY(-4px); opacity: 1; } }
    
    /* 3. Core Cart Container (Default Desktop View) */
    main { max-width: 56rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 24px; }
    
    /* Shopping Product Row Items */
    .divide-row { border-bottom: 1px solid #e5e7eb; padding: 16px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; box-sizing: border-box; }
    .product-info { display: flex; align-items: center; gap: 16px; }
    .img-box { width: 64px; height: 64px; background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 0.5rem; padding: 4px; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .img-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .item-title { font-weight: 700; color: #1f2937; font-size: 0.875rem; text-transform: uppercase; margin: 0; line-height: 1.4; }
    .item-price { font-size: 0.75rem; color: #9ca3af; margin: 2px 0 0; font-weight: 500; white-space: nowrap; }
    
    /* Item Inputs & Quantity Controls Grid */
    .controls { display: flex; align-items: center; gap: 24px; font-size: 0.875rem; }
    .qty-form { display: flex; align-items: center; gap: 4px; background-color: #f9fafb; border: 1px solid #d1d5db; padding: 4px 8px; border-radius: 6px; height: 32px; box-sizing: border-box; }
    .qty-input { width: 40px; background: transparent; border: none; text-align: center; font-weight: 700; color: #2563eb; outline: none; }
    .subtotal-text { font-weight: 800; color: #374151; min-width: 90px; text-align: right; white-space: nowrap; }
    .remove-btn { background: none; border: none; color: #ef4444; font-weight: 700; font-size: 0.75rem; cursor: pointer; text-transform: uppercase; transition: color 0.2s ease; padding: 4px 0; }
    .remove-btn:hover { color: #dc2626; text-decoration: underline; }
    
    /* 4. Cart Totals Summary Footer Panel */
    .summary-box { margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 24px; display: flex; flex-direction: column; align-items: flex-end; box-sizing: border-box; }
    .total-row { display: flex; justify-content: space-between; width: 33.333%; font-weight: 600; font-size: 0.875rem; color: #1f2937; margin-bottom: 16px; align-items: center; gap: 16px; box-sizing: border-box; }
    .grand-price { color: #059669; font-weight: 900; font-size: 1.5rem; white-space: nowrap; }
    
    /* Primary Action CTA Link Block Button */
    .checkout-btn { width: 33.333%; background-color: #f97316; color: white; font-weight: 700; padding: 12px 0; border-radius: 6px; text-align: center; text-decoration: none; font-size: 0.875rem; text-transform: uppercase; box-shadow: 0 4px 6px -1px rgba(249,115,22,0.2); transition: background-color 0.2s ease; height: 42px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .checkout-btn:hover { background-color: #ea580c; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 MID-SIZED DEVICES BREAKPOINT (TABLETS - Max 850px Width Viewports) */
    @media (max-width: 850px) {
        .total-row, .checkout-btn { width: 50%; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Stack global navigation menu items vertically */
        nav { flex-direction: column; gap: 12px; padding: 12px 16px; text-align: center; }
        
        /* Main Document Wrapper padding boundaries shrinkages */
        main { margin: 16px; padding: 16px; border-radius: 0.5rem; }
        .main-title { font-size: 1.3rem; margin-bottom: 16px; }
        
        /* Break list layout rows apart into individual stacked blocks */
        .divide-row { flex-direction: column; align-items: flex-start; gap: 16px; padding: 16px 0; }
        .product-info { width: 100%; }
        .item-title { font-size: 0.8rem; }
        
        /* Spread input indicators across full widths to prevent text truncation */
        .controls { width: 100%; justify-content: space-between; border-top: 1px dashed #e5e7eb; padding-top: 12px; margin-top: 4px; gap: 12px; }
        .qty-form { height: 38px; padding: 4px 12px; }
        .subtotal-text { font-size: 0.85rem; text-align: right; min-width: unset; }
        .remove-btn { font-size: 0.8rem; padding: 6px 0; }
        
        /* Expand Summary Totals panels to utilize 100% device width rules */
        .summary-box { align-items: flex-start; margin-top: 24px; padding-top: 16px; }
        .total-row { width: 100%; font-size: 0.85rem; margin-bottom: 20px; }
        .grand-price { font-size: 1.3rem; }
        .checkout-btn { width: 100%; height: 46px; font-size: 0.9rem; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn" id="continueShoppingLink"><span class="back-loader" aria-hidden="true"><i></i><i></i><i></i></span><span class="back-home-text">← Continue Shopping</span></a>
    </nav>
       <main>
        <h1 class="main-title">Review Basket Summary</h1>
        <?php if (count($items) > 0): ?>
            <div>
                <?php foreach ($items as $item): $sub = $item['price'] * $item['quantity']; $total += $sub; ?>
                <div class="divide-row">
                    <div class="product-info">
                        <div class="img-box"><img src="../uploads/<?= htmlspecialchars($item['image'] ?? 'placeholder.png'); ?>"></div>
                        <div>
                            <h3 class="item-title"><?= htmlspecialchars($item['product_name']); ?></h3>
                            <p class="item-price">Unit Price: KES <?= number_format($item['price'], 2); ?></p>
                        </div>
                    </div>
                    <div class="controls">
                        <form method="POST" class="qty-form">
                            <span style="color:#9ca3af; font-weight:600; font-size:11px;">QTY:</span>
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                            <input type="number" name="quantity" value="<?= $item['quantity']; ?>" min="1" max="<?= $item['stock_quantity']; ?>" onchange="this.form.submit()" class="qty-input">
                            <input type="hidden" name="update_quantity" value="1">
                        </form>
                        <p class="subtotal-text">KES <?= number_format($sub, 2); ?></p>
                        <form method="POST">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                            <button type="submit" name="remove_item" class="remove-btn">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-box">
                <div class="total-row">
                    <span>Grand Total:</span>
                    <span class="grand-price">KES <?= number_format($total, 2); ?></span>
                </div>
                <a href="checkout.php" class="checkout-btn nav-loading-link">Proceed to Checkout →</a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 64px 0; color: #9ca3af; font-weight: 600; font-size: 0.875rem;">Your basket is currently empty.</div>
        <?php endif; ?>
    </main>

    <!-- Global Full-Screen Tab Transition Spinner Overlay Frame -->
    <div id="global-page-loader" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner" style="width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        <p style="color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; tracking-spacing: wide; letter-spacing: 0.05em; font-family: sans-serif;">Connecting Securely...</p>
    </div>
    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>

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
    // Give customers clear feedback before returning to the shop.
    const continueShoppingLink = document.getElementById('continueShoppingLink');
    if (continueShoppingLink) {
        continueShoppingLink.addEventListener('click', function(event) {
            event.preventDefault();
            continueShoppingLink.classList.add('is-loading');
            continueShoppingLink.querySelector('.back-home-text').textContent = 'Opening Shop...';
            setTimeout(function() {
                window.location.href = continueShoppingLink.href;
            }, 650);
        });
    }
</script>
<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
