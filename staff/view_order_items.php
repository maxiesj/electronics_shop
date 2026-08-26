<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';

if (!verifyExplicitWorkspaceClearance('manage_orders.php')) {
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');
    exit;
}
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: manage_orders.php");
    exit;
}

// 1. Fetch Primary Master Order Metadata to cross-check total amounts and states
$o_stmt = $conn->prepare("SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$o_stmt->bind_param("i", $order_id);
$o_stmt->execute();
$order_meta = $o_stmt->get_result()->fetch_assoc();

if (!$order_meta) {
    die("Error: The requested invoice reference record was not found on this server node.");
}

// 2. Fetch all individual product splits linked under this invoice contract split
$i_stmt = $conn->prepare("SELECT oi.*, p.product_name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$i_stmt->bind_param("i", $order_id);
$i_stmt->execute();
$items_list = $i_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Invoice Items Breakdown | ADONAK ELECTRONICS</title>
    <style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .nav-brand { font-weight: 800; font-size: 1.25rem; color: #f97316; white-space: nowrap; }
    .nav-links { display: flex; gap: 20px; font-size: 0.875rem; font-weight: 600; align-items: center; }
    .nav-links a { color: #d1d5db; text-decoration: none; padding: 6px 12px; border-radius: 4px; transition: background 0.2s, color 0.2s; white-space: nowrap; }
    .nav-links a:hover { color: white; background-color: #1f2937; }
    
    /* 3. Core Structural Containers (Default Desktop View) */
    main { max-width: 64rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0; }
    .back-link-btn { background-color: #4b5563; color: white; text-decoration: none; font-size: 0.75rem; font-weight: 700; padding: 8px 16px; border-radius: 6px; text-transform: uppercase; white-space: nowrap; transition: background 0.2s; }
    .back-link-btn:hover { background-color: #374151; }

    /* Blue Summary Metadata Row Layer */
    .meta-card { background-color: #1e40af; color: white; padding: 20px; border-radius: 0.75rem; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(30,64,175,0.1); display: flex; justify-content: space-between; align-items: center; gap: 20px; box-sizing: border-box; }
    .meta-label { font-size: 10px; color: #93c5fd; font-weight: 700; text-transform: uppercase; margin: 0; letter-spacing: 0.05em; }
    .meta-val { font-size: 1.25rem; font-weight: 900; margin: 2px 0 0; text-transform: uppercase; word-break: break-all; }

    /* White Data Summary Table Wrapper block */
    .content-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    table { width: 100%; border-collapse: collapse; font-size: 0.813rem; text-align: left; }
    th { background-color: #f9fafb; color: #4b5563; padding: 10px 12px; font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; }
    
    /* Product Image Previews boxes layout items */
    .img-preview-box { width: 44px; height: 44px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 2px; flex-shrink: 0; }
    .img-preview-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .text-right { text-align: right; }
    .font-bold { font-weight: 700; }

    /* 4. Full-Screen Page Loader Processing Actions Overlay */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    
    /* Fixed broken custom property allocation string value errors */
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 MID-SIZED DEVICES BREAKPOINT (TABLETS - Max 850px Width Viewports) */
    @media (max-width: 850px) {
        main { margin: 24px auto; padding: 0 16px 40px; }
        .content-block { padding: 16px; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Restructure Navbar elements to clean stacked vertical columns flow */
        nav { flex-direction: column; gap: 14px; padding: 14px 16px; text-align: center; }
        .nav-links { gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .nav-links a { font-size: 0.8rem; padding: 4px 8px; }
        
        /* Main document frame bounds shrinkages */
        main { margin: 16px auto; padding: 0 12px 32px; }
        
        /* Break down top header items into secondary rows layout lines */
        .header-row { flex-direction: column; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .main-title { font-size: 1.25rem; }
        .back-link-btn { width: 100%; text-align: center; height: 36px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
        
        /* Flatten horizontal blue statistics widgets cards to stacked fields blocks */
        .meta-card { flex-direction: column; align-items: flex-start; gap: 12px; padding: 16px; border-radius: 0.5rem; }
        .meta-val { font-size: 1.15rem; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .content-block { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 0.5rem; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <?php include_once 'navbar.php'; ?>

 

    <main>
        <div class="header-row">
            <h1 class="main-title">Itemized Items Breakdown Summary</h1>
            <a href="manage_orders.php" class="back-link-btn">← Back to Ledger</a>
        </div>

        <div class="meta-card">
            <div>
                <p class="meta-label">Customer Contract Reference</p>
                <p class="meta-val"><?= htmlspecialchars($order_meta['fullname']); ?> (#<?= $order_meta['id']; ?>)</p>
            </div>
            <div style="text-align: right;">
                <p class="meta-label">Total Document Value Paid</p>
                <p class="meta-val" style="color: #34d399;">KES <?= number_format($order_meta['total_amount'], 2); ?></p>
            </div>
        </div>

        <div class="content-block">
            <table>
                <thead>
                    <tr>
                        <th>Visual Preview</th>
                        <th>Product Name Description</th>
                        <th class="text-right">Quantity Mapped</th>
                        <th class="text-right">Net Price Split</th>
                        <th class="text-right">VAT Amount (7.00%)</th>
                        <th class="text-right">Gross Row Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_list as $item): 
                        $qty = (int)$item['quantity'];
                    ?>
                    <tr>
                        <td>
                            <div class="img-preview-box">
                                <img src="../uploads/<?= htmlspecialchars($item['image'] ?? 'placeholder.png'); ?>">
                            </div>
                        </td>
                        <td class="font-bold" style="text-transform: uppercase;"><?= htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-right"><?= $qty; ?> units</td>
                        <td class="text-right">KES <?= number_format($item['net_price'] * $qty, 2); ?></td>
                        <td class="text-right">KES <?= number_format($item['vat_price'] * $qty, 2); ?></td>
                        <td class="text-right font-bold" style="color: #059669;">KES <?= number_format($item['price'] * $qty, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

       <!-- Global Interface Inter-Page Loader Transition Overlay Box Wrapper Frame -->
    <div class="loader-overlay" id="global-page-loader">
        <div class="spinner"></div>
        <p class="loader-text">Loading Operations Desk...</p>
    </div>

    <!-- JavaScript Navigation Routing Interceptors -->
    <script>
    document.querySelectorAll('.nav-center-links a, table a, .nav-loading-link, .logout-btn').forEach(link => {
        // Skip fallback actions, javascript tasks, or form submission buttons handled by system alerts
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:') || link.type === 'submit') return;
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            
            // Open full-screen transition spinner layout overlay instantly
            document.getElementById('global-page-loader').style.display = 'flex';
            
            // Hold redirect cushion for 350ms to render the animation smoothly
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 350);
        });
    });
    </script>
</body>
</html>
