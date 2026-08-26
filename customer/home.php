<?php 
session_start(); 
include '../db.php'; // Centralized MySQLi connection asset link

// Visitors may browse products, but must never inherit a real customer account.
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$fullname = $_SESSION['fullname'] ?? 'Guest';

// FETCH ACTUAL CART COUNT FROM DATABASE
$c_query = "SELECT SUM(quantity) FROM cart WHERE user_id = ?";
$c_stmt = $conn->prepare($c_query); 
$c_stmt->bind_param("i", $user_id); 
$c_stmt->execute(); 
$c_res = $c_stmt->get_result()->fetch_row(); 
// Ensure the fallback value defaults to 0 if the row value evaluates to NULL
$cart_count = isset($c_res[0]) ? (int)$c_res[0] : 0;

// Capture filtering parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; 
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest'; 
$category_id = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;
$categories_res = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_res ? $categories_res->fetch_all(MYSQLI_ASSOC) : [];
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Assemble database query lines elements
$query = "SELECT p.*, b.brand_name, c.category_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1"; 
if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR b.brand_name LIKE ?)";
}
if ($category_id > 0) {
    $query .= " AND p.category_id = ?";
}

// FIXED: Explicitly casting text fields to clean decimals so 'High to Low' works numerically
switch ($sort) { 
    case 'price_low': 
        $query .= " ORDER BY CAST(REPLACE(p.price, ',', '') AS DECIMAL(10,2)) ASC"; 
        break; 
    case 'price_high': 
        $query .= " ORDER BY CAST(REPLACE(p.price, ',', '') AS DECIMAL(10,2)) DESC"; 
        break; 
    default: 
        $query .= " ORDER BY p.id DESC"; 
        break; 
}

$stmt = $conn->prepare($query); 
if (!empty($search) && $category_id > 0) {
    $s_term = "%$search%";
    $stmt->bind_param("ssi", $s_term, $s_term, $category_id);
} elseif (!empty($search)) {
    $s_term = "%$search%";
    $stmt->bind_param("ss", $s_term, $s_term);
} elseif ($category_id > 0) {
    $stmt->bind_param("i", $category_id);
}
$stmt->execute(); 
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- AJAX LIVE INTERCEPTOR BACKEND GRID RENDERER ---
if ($is_ajax) { 
    if (count($products) > 0) { 
        foreach ($products as $p) { 
            // FIXED: Clean string formatting artifacts out before passing to number_format
            $clean_numeric_price = floatval(str_replace(',', '', $p['price']));
            ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <span class="card-badge"><?= htmlspecialchars($p['brand_name'] ?? 'General'); ?></span>
                    <div class="img-box" style="height: 180px; width: 100%; display: flex; align-items: center; justify-content: center; background-color: #f9fafb; border-radius: 8px; margin-bottom: 16px; border: 1px solid #f3f4f6; overflow: hidden; padding: 10px; box-sizing: border-box;">
                        <a href="product_detail.php?id=<?= $p['id']; ?>" class="nav-loading-link" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                            <img src="../uploads/<?= htmlspecialchars($p['image'] ?? 'placeholder.png'); ?>" style="max-width: 100%; max-height: 100%; width: auto !important; height: auto !important; object-fit: contain !important; display: block; margin: 0 auto;">
                        </a>
                    </div>
                    <h3 class="product-title">
                        <a href="product_detail.php?id=<?= $p['id']; ?>" class="nav-loading-link"><?= htmlspecialchars($p['product_name']); ?></a>
                    </h3>
                </div>
                <div style="margin-top: 12px;">
                    <div class="price-row">
                        <p class="card-price">KES <?= number_format($clean_numeric_price, 2); ?></p>
                        <?php if ((int)$p['stock_quantity'] <= 0): ?>
                            <p class="stock-text" style="color: #ef4444; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px; font-weight: 700;">Out of Stock</p>
                        <?php else: ?>
                            <p class="stock-text"><?= (int)$p['stock_quantity']; ?> available</p>
                        <?php endif; ?>
                    </div>
                    <div class="polepole-price"><span>&#9203; Lipa Pole Pole</span><strong>Pay KES <?= number_format($clean_numeric_price * 0.50, 2); ?> now</strong></div>
                    <form class="ajax-cart-form cart-form">
                        <input type="hidden" name="product_id" value="<?= $p['id']; ?>">
                        <?php if ((int)$p['stock_quantity'] <= 0): ?>
                            <input type="number" value="0" disabled class="qty-input" style="background-color: #e5e7eb; color: #9ca3af; cursor: not-allowed; border-color: #d1d5db;">
                            <button type="button" disabled class="basket-btn" style="background-color: #9ca3af; color: #ffffff; cursor: not-allowed; box-shadow: none;">Out of Stock</button>
                        <?php else: ?>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock_quantity']; ?>" class="qty-input">
                            <button type="submit" class="basket-btn"><span class="basket-loader" aria-hidden="true"><i></i><i></i><i></i></span><span class="basket-btn-text">Add to Basket</span></button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php 
        } 
    } else { 
        echo '<div style="grid-column: span 3; text-align: center; padding: 48px 0; color: #9ca3af; font-weight: 600; text-transform: uppercase; font-size:13px;">No items found matching your filters.</div>'; 
    } 
    exit; 
} 
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
	 <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>Store Dashboard</title>
  <style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); position: relative; z-index: 50; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    
    /* Search Box Framework */
    .search-form { display: flex; align-items: center; width: 33.333%; max-width: 32rem; background-color: #1f2937; padding: 6px 12px; border: 1px solid #374151; border-radius: 6px; transition: width 0.2s ease; }
    .search-input { background: transparent; border: none; font-size: 0.875rem; width: 100%; color: #e5e7eb; outline: none; }
    .search-btn { background: none; border: none; color: #9ca3af; cursor: pointer; }
    
    /* Anchor Links Set Wrapper */
    .nav-links { display: flex; align-items: center; gap: 24px; font-size: 0.875rem; }
    .nav-links a { color: white; text-decoration: none; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .nav-links a:hover { color: #f97316; }

    /* 3. Promotional Layout Hero Banner Section Elements */
    .banner { max-width: 80rem; margin: 24px auto 0; padding: 0 16px; box-sizing: border-box; }
    .banner-box { background-color: #1e40af; color: white; border-radius: 0.5rem; padding: 32px; display: flex; justify-content: space-between; align-items: center; gap: 24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .badge { background-color: #ef4444; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; width: max-content; }
    .banner-title { font-size: 1.875rem; font-weight: 900; margin-top: 8px; margin-bottom: 0; line-height: 1.2; }
    .price-tag { font-size: 1.875rem; font-weight: 900; color: #34d399; margin: 4px 0 0; white-space: nowrap; }

    /* 4. Core Catalog Grid Layout Definitions (Default Desktop View) */
    main { max-width: 80rem; margin: 32px auto 0; padding: 0 16px 64px; box-sizing: border-box; }
    .catalog-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
    .catalog-title { font-weight: 700; text-transform: uppercase; color: #374151; font-size: 0.875rem; letter-spacing: 0.05em; margin: 0; }
    .sort-select { border: 1px solid #d1d5db; border-radius: 4px; padding: 4px 8px; background-color: white; color: #374151; font-size: 0.875rem; outline: none; cursor: pointer; }
    
    /* 3-Column Base Grid configuration */
    .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; position: relative; min-height: 200px; }
    
    /* Shopping Product Card Elements Block */
    .card { background-color: white; border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; }
    .card-badge { position: absolute; top: 16px; right: 16px; background-color: #f3f4f6; color: #4b5563; font-weight: 700; font-size: 10px; padding: 2px 8px; border-radius: 4px; border: 1px solid #e5e7eb; }
    .product-title { font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: #1f2937; margin-bottom: 8px; margin-top: 0; padding-right: 60px; line-height: 1.4; }
    .product-title a { color: #1f2937; text-decoration: none; }
    .product-title a:hover { text-decoration: underline; }
    .price-row { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px; gap: 8px; flex-wrap: wrap; }
    .card-price { color: #059669; font-weight: 800; font-size: 1rem; margin: 0; }
    .stock-text { font-size: 0.75rem; color: #9ca3af; margin: 0; font-weight: 500; }
    
    /* Item Add Cart Submission Inline Section View Row */
    .cart-form { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; margin-top: auto; }
    .qty-input { width: 48px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 0; text-align: center; font-weight: 600; outline: none; height: 28px; box-sizing: border-box; }
    .basket-btn { flex: 1; background-color: #2563eb; color: white; font-weight: 700; padding: 6px 0; border: none; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 11px; height: 28px; display: inline-flex; align-items: center; justify-content: center; }
    .basket-btn:hover { background-color: #1d4ed8; }
    .basket-btn.is-loading { cursor: wait; opacity: 0.8; }
    .basket-loader { display: none; align-items: center; gap: 3px; margin-right: 7px; }
    .basket-loader i { width: 4px; height: 4px; background: #ffffff; border-radius: 50%; animation: basketDotPulse 0.9s ease-in-out infinite; }
    .basket-loader i:nth-child(2) { animation-delay: 0.15s; }
    .basket-loader i:nth-child(3) { animation-delay: 0.3s; }
    .basket-btn.is-loading .basket-loader { display: inline-flex; }
    @keyframes basketDotPulse { 0%, 80%, 100% { transform: translateY(0); opacity: 0.35; } 40% { transform: translateY(-3px); opacity: 1; } }
    
    /* Loading Spinner Component Overlay Framework Structs */
    .spinner-overlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(243, 244, 246, 0.7); z-index: 10; justify-content: center; align-items: center; border-radius: 8px; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

       /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================= */

    /* 📱 MID-SIZED DEVICES BREAKPOINT (TABLETS - Between 769px and 1024px Width) */
    @media (max-width: 1024px) {
        .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .search-form { width: 40%; }
        .banner-title { font-size: 1.5rem; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 768px Width Screens) */
    @media (max-width: 768px) {
        /* FIXED TOP NAVIGATION BAR ENGINE: Anchors the customer header to the absolute top of the phone */
        nav { 
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: auto !important;
            flex-direction: column !important; 
            gap: 12px !important; 
            padding: 14px 16px !important; 
            text-align: center !important; 
            background-color: #0f172a !important; /* Enforces clean deep dark blue background opacity match */
            z-index: 9999 !important; /* Guarantees sliding catalog images pass directly behind the bar */
            border-bottom: 2px solid #1e293b !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2) !important;
        }
        
        .search-form { 
            display: none !important; /* Drops the desktop inline input wrapper node */
        } 
        
        .nav-links { 
            display: flex !important;
            flex-direction: row !important;
            gap: 16px !important; 
            flex-wrap: wrap !important; /* Wraps customer links onto two clean horizontal touch lines */
            justify-content: center !important; 
            font-size: 0.8rem !important; 
            width: 100% !important;
        }
        .nav-links a {
            white-space: nowrap !important;
            padding: 4px 6px !important;
        }
        
        /* FIXED POSITION CLEARANCE OFFSET: Safely spaces out your promotion hero element below the fixed nav box */
        .banner-box { 
            margin-top: 140px !important; /* Dynamically forces the "SUMSANG S26 ULTRA" card to drop clear of overlap zones */
            flex-direction: column !important; 
            align-items: flex-start !important; 
            padding: 20px !important; 
            gap: 14px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
			
			    background: radial-gradient(circle at 20% 30%, #2563eb 0%, #1e40af 100%) !important;
    border: 1px solid #1d4ed8 !important;
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.1), 0 4px 6px -4px rgba(37, 99, 235, 0.1) !important;
        }
        .banner-title { font-size: 1.25rem; }
        .price-tag { font-size: 1.5rem; margin-top: 4px;  color: #10b981 !important; /* Premium Mint Green text */
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15); /* Adds a subtle dark drop shadow layer to separate the color from the blue backing */
    font-weight: 800;}
        
        /* Flatten Catalog Matrix Columns Allocation into Singular Rows Lists */
        .catalog-header { 
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 10px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Forces all your shop catalog product items grid boxes into a clean single vertical stack layout */
        .grid { 
            grid-template-columns: 1fr !important; 
            gap: 16px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Product item card adjustments for tap targets on touch devices */
        .card { 
            padding: 16px !important; 
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .basket-btn { height: 34px; font-size: 12px; }
        .qty-input { height: 34px; width: 54px; }
    }
	/* ==========================================================================
   NEW ARRIVAL SHIMMER & PULSE ANIMATION MATRIX
   ========================================================================== */
.badge-animate {
    position: relative;
    overflow: hidden;
    animation: badgePulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Subtle breathing scale animation */
@keyframes badgePulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 12px 4px rgba(239, 68, 68, 0.3);
    }
}

/* Shimmer overlay sweep effect */
.badge-animate::after {
    content: '';
    position: absolute;
    top: 0;
    left: -150%;
    width: 50%;
    height: 100%;
    background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.4) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    transform: skewX(-25deg);
    animation: shimmerSweep 3s infinite ease-in-out;
}

@keyframes shimmerSweep {
    0% { left: -150%; }
    30% { left: 150%; }
    100% { left: 150%; }
}



    /* Storefront alignment and shopping discovery improvements */
    html, body { width:100%; max-width:100%; overflow-x:hidden; }
    nav { width:100%; box-sizing:border-box; padding-left:max(24px, calc((100vw - 1440px)/2)); padding-right:max(24px, calc((100vw - 1440px)/2)); }
    .banner, main, .trust-strip { width:min(1440px, calc(100% - 48px)); max-width:none; margin-left:auto; margin-right:auto; padding-left:0; padding-right:0; }
    .banner-box { min-height:235px; padding:30px 36px; background:linear-gradient(120deg,#172554 0%,#1d4ed8 62%,#2563eb 100%); position:relative; overflow:hidden; }
    .banner-box::after { content:''; position:absolute; width:310px; height:310px; right:22%; top:-155px; border-radius:50%; background:rgba(255,255,255,.08); }
    .hero-copy { position:relative; z-index:2; max-width:510px; }
    .hero-subtitle { margin:10px 0 18px; color:#bfdbfe; font-size:13px; line-height:1.55; }
    .hero-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .hero-actions a { min-height:38px; padding:0 16px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; text-decoration:none; }
    .hero-primary { background:#f97316; color:#fff; }
    .hero-secondary { color:#fff; border:1px solid rgba(255,255,255,.55); background:rgba(255,255,255,.08); }
    .hero-secondary-disabled { min-height:38px; padding:0 16px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; color:#cbd5e1; border:1px solid rgba(203,213,225,.28); background:rgba(15,23,42,.28); opacity:.62; cursor:not-allowed; user-select:none; }
    .hero-product { width:230px; height:190px; position:relative; z-index:2; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .hero-product img { max-width:100%; max-height:100%; object-fit:contain; filter:drop-shadow(0 18px 18px rgba(15,23,42,.35)); }
    .hero-price { position:relative; z-index:2; text-align:right; min-width:230px; }
    .hero-price p { margin:0 0 4px; color:#bfdbfe; font-size:12px; }
    .hero-price strong { display:block; color:#34d399; font-size:25px; white-space:nowrap; }
    .hero-price span { display:block; margin-top:8px; color:#dbeafe; font-size:11px; font-weight:700; }
    .trust-strip { margin-top:16px; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; overflow:hidden; border:1px solid #e2e8f0; border-radius:10px; background:#e2e8f0; }
    .trust-strip > div { min-width:0; padding:13px 16px; display:grid; grid-template-columns:30px 1fr; background:#fff; }
    .trust-strip > div > span { grid-row:1/3; font-size:19px; }
    .trust-strip strong { font-size:11px; color:#1e293b; text-transform:uppercase; }
    .trust-strip small { color:#64748b; font-size:10px; margin-top:2px; }
    .catalog-subtitle { margin:5px 0 0; color:#94a3b8; font-size:12px; }
    .category-filters { display:flex; gap:8px; flex-wrap:wrap; margin:-8px 0 22px; }
    .category-chip { min-height:34px; padding:0 13px; border:1px solid #dbe3ef; border-radius:999px; background:#fff; color:#475569; font-size:10px; font-weight:800; text-transform:uppercase; cursor:pointer; transition:.18s ease; }
    .category-chip:hover, .category-chip.is-active { background:#1d4ed8; border-color:#1d4ed8; color:#fff; transform:translateY(-1px); }
    .card { transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease; }
    .card:hover { transform:translateY(-3px); border-color:#bfdbfe; box-shadow:0 14px 26px rgba(15,23,42,.09); }
    .polepole-price { display:flex; justify-content:space-between; gap:8px; align-items:center; margin:-3px 0 11px; padding:7px 9px; border-radius:6px; background:#f5f3ff; color:#6d28d9; font-size:10px; }
    .polepole-price span { font-weight:800; }
    .polepole-price strong { color:#5b21b6; white-space:nowrap; }
    @media (max-width:1024px) { .hero-product { width:170px; } .hero-price { min-width:190px; } .trust-strip { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:768px) {
      nav { padding-left:16px !important; padding-right:16px !important; }
      .banner, main, .trust-strip { width:calc(100% - 32px); }
      .banner-box { min-height:0; }
      .hero-product { width:100%; height:160px; }
      .hero-price { width:100%; min-width:0; text-align:left; }
      .hero-price strong { font-size:21px; }
      .trust-strip { grid-template-columns:1fr; margin-top:14px; }
      .category-filters { flex-wrap:nowrap; overflow-x:auto; padding-bottom:6px; }
      .category-chip { flex:0 0 auto; }
    }</style>

<link rel="stylesheet" href="../css/panel-polish.css?v=20260811-7">
<script src="../js/page-progress-dialog.js"></script>
</head>
<body class="panel-ui customer-panel">
    <nav>
        <div class="brand-title">⚡ ADONAK ELECTRONICS</div>
        <div class="search-form">
            <input type="text" id="live-search" value="<?= htmlspecialchars($search); ?>" placeholder="Type to search instantly..." class="search-input" autocomplete="off">
            <button type="button" class="search-btn">🔍</button>
        </div>
        <div class="nav-links">
            <span style="color: #9ca3af;">Hello, <strong style="color: white;"><?= htmlspecialchars($fullname); ?></strong></span>
            <a href="home.php">Shop</a>
            <a href="about.php">About Us</a>
            <?php if ($user_id > 0): ?>
                <!-- Customer-only account navigation -->
                <a href="cart.php">Cart (<span id="cart-nav-count" style="color:#fb923c; font-weight:800;"><?= $cart_count; ?></span>)</a>
                <a href="my_orders.php">Orders</a>
                <a href="profile.php">Profile</a>
                <a href="../logout.php" style="color: #f87171;">Log Out</a>
            <?php else: ?>
                <!-- Guest onboarding navigation: no logout is shown without a session. -->
                <a href="../register.php" style="color: #fb923c;">Create Account</a>
                <a href="../login.php">Log In</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Marketing Banner Showcase Section -->
<header class="banner">
    <?php
    $target_banner_name = 'Latest electronics';
    $clean_banner_price = 0.00;
    $target_banner_id = 0;
    $target_banner_image = 'placeholder.png';
    if (!empty($products)) {
        $newest_product = $products[0];
        foreach ($products as $candidate) {
            if ((int)$candidate['id'] > (int)$newest_product['id']) $newest_product = $candidate;
        }
        $target_banner_name = $newest_product['product_name'];
        $clean_banner_price = (float)str_replace(',', '', $newest_product['price']);
        $target_banner_id = (int)$newest_product['id'];
        $target_banner_image = $newest_product['image'] ?: 'placeholder.png';
    }
    ?>
    <div class="banner-box">
        <div class="hero-copy">
            <span class="badge badge-animate">&#9889; New Arrival</span>
            <h1 class="banner-title"><?= htmlspecialchars($target_banner_name); ?></h1>
            <p class="hero-subtitle">Genuine electronics, secure wallet checkout, and flexible Lipa Pole Pole payments.</p>
            <div class="hero-actions">
                <?php if ($target_banner_id > 0): ?><a href="product_detail.php?id=<?= $target_banner_id; ?>" class="hero-primary nav-loading-link">View Product</a><?php endif; ?>
                <?php if ($user_id > 0): ?>
                    <a href="#catalog-section" class="hero-secondary">Browse Catalogue</a>
                <?php else: ?>
                    <span class="hero-secondary-disabled" aria-disabled="true" title="Create an account or log in to browse the catalogue">Browse Catalogue</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-product"><img src="../uploads/<?= htmlspecialchars($target_banner_image); ?>" alt="<?= htmlspecialchars($target_banner_name); ?>"></div>
        <div class="hero-price">
            <p>Starting at</p>
            <strong>KES <?= number_format($clean_banner_price, 2); ?></strong>
            <span>Lipa Pole Pole deposit: KES <?= number_format($clean_banner_price * 0.50, 2); ?></span>
        </div>
    </div>
</header>

<section class="trust-strip" aria-label="Shopping benefits">
    <div><span>&#128274;</span><strong>Secure payments</strong><small>Protected wallet checkout</small></div>
    <div><span>&#9203;</span><strong>Lipa Pole Pole</strong><small>Pay 50% now, balance in 30 days</small></div>
    <div><span>&#9989;</span><strong>Genuine products</strong><small>Verified catalogue stock</small></div>
    <div><span>&#128666;</span><strong>Order tracking</strong><small>Follow every order status</small></div>
</section>
    <!-- Main Catalog Marketplace Section Component Grid Layout -->
    <main id="catalog-section">
        <div class="catalog-header">
            <div><h2 class="catalog-title">Explore Available Catalog Items</h2><p class="catalog-subtitle">Choose a category or search for a specific model.</p></div>
            <select id="sort-selector" class="sort-select">
                <option value="latest" <?= $sort=='latest'?'selected':'';?>>Latest Arrivals</option>
                <option value="price_low" <?= $sort=='price_low'?'selected':'';?>>Price: Low to High</option>
                <option value="price_high" <?= $sort=='price_high'?'selected':'';?>>Price: High to Low</option>
            </select>
        </div>

        <div class="category-filters" role="group" aria-label="Filter products by category">
            <button type="button" class="category-chip <?= $category_id === 0 ? 'is-active' : ''; ?>" data-category="0">All products</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="category-chip <?= $category_id === (int)$category['id'] ? 'is-active' : ''; ?>" data-category="<?= (int)$category['id']; ?>"><?= htmlspecialchars($category['category_name']); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="grid" id="catalog-grid">
            <!-- Dynamic CSS Loading Spinner Overlay Container -->
            <div class="spinner-overlay" id="loader-spinner">
                <div class="spinner"></div>
            </div>

            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $p): 
                    // FIXED: Cleanses string formatting issues cleanly before passing to numeric converters
                    $clean_display_price = floatval(str_replace(',', '', $p['price']));
                ?>
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <!-- Real-Time Associated Brand Badge Tag -->
                            <span class="card-badge"><?= htmlspecialchars($p['brand_name'] ?? 'General'); ?></span>
                            
                            <!-- Proportionate Image Wrapping Frame Boundary Box -->
                            <div class="img-box" style="height: 180px; width: 100%; display: flex; align-items: center; justify-content: center; background-color: #f9fafb; border-radius: 8px; margin-bottom: 16px; border: 1px solid #f3f4f6; overflow: hidden; padding: 10px; box-sizing: border-box;">
                                <a href="product_detail.php?id=<?= $p['id']; ?>" class="nav-loading-link" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                    <img src="../uploads/<?= htmlspecialchars($p['image'] ?? 'placeholder.png'); ?>" style="max-width: 100%; max-height: 100%; width: auto !important; height: auto !important; object-fit: contain !important; display: block; margin: 0 auto;">
                                </a>
                            </div>
                            
                            <!-- Product Title Header Click Shortcut link -->
                            <h3 class="product-title">
                                <a href="product_detail.php?id=<?= $p['id']; ?>" class="nav-loading-link"><?= htmlspecialchars($p['product_name']); ?></a>
                            </h3>
                        </div>

                        <div style="margin-top: 12px;">
                            <!-- Dynamic Pricing and Warehouse Stock Levels Badges -->
                            <div class="price-row">
                                <p class="card-price">KES <?= number_format($clean_display_price, 2); ?></p>
                                <?php if ((int)$p['stock_quantity'] <= 0): ?>
                                    <p class="stock-text" style="color: #ef4444; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px; font-weight: 700;">Out of Stock</p>
                                <?php else: ?>
                                    <p class="stock-text"><?= (int)$p['stock_quantity']; ?> available</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Asynchronous Add to Cart Action Processing Submission Form Component Block -->
                            <div class="polepole-price"><span>&#9203; Lipa Pole Pole</span><strong>Pay KES <?= number_format($clean_display_price * 0.50, 2); ?> now</strong></div>
                    <form class="ajax-cart-form cart-form">
                                <input type="hidden" name="product_id" value="<?= $p['id']; ?>">
                                <?php if ((int)$p['stock_quantity'] <= 0): ?>
                                    <!-- Disable input interfaces completely for out-of-stock variations -->
                                    <input type="number" value="0" disabled class="qty-input" style="background-color: #e5e7eb; color: #9ca3af; cursor: not-allowed; border-color: #d1d5db;">
                                    <button type="button" disabled class="basket-btn" style="background-color: #9ca3af; color: #ffffff; cursor: not-allowed; box-shadow: none;">Out of Stock</button>
                                <?php else: ?>
                                    <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock_quantity']; ?>" class="qty-input">
                                    <button type="submit" class="basket-btn"><span class="basket-loader" aria-hidden="true"><i></i><i></i><i></i></span><span class="basket-btn-text">Add to Basket</span></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align: center; padding: 48px 0; color: #9ca3af; font-weight: 600;">No items found matching your filters.</div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Global Full-Screen Tab Transition Spinner Overlay Frame -->
    <div id="global-page-loader" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner" style="width: 50px; height: 50px; border-width: 5px; border-top-color: #f97316;"></div>
        <p style="color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; tracking-spacing: wide; letter-spacing: 0.05em; font-family: sans-serif;">Connecting Securely...</p>
    </div>



 <!-- Live Event Trigger Submissions Script Module Engine Controllers Layer -->
    <script>
    const sIn = document.getElementById('live-search');
    const sSel = document.getElementById('sort-selector');
    const categoryChips = Array.from(document.querySelectorAll('.category-chip'));
    let activeCategory = <?= (int)$category_id; ?>;
    const cG = document.getElementById('catalog-grid');
    const spinner = document.getElementById('loader-spinner');
    const globalPageLoader = document.getElementById('global-page-loader');

    // intercept the text input element wrapping form block container to eliminate forced tab refreshes
    const searchFormWrapper = sIn ? sIn.closest('form') : null;
    if (searchFormWrapper) {
        searchFormWrapper.addEventListener('submit', function(e) {
            e.preventDefault();
            doSearch();
        });
    }

    let debounceTimer;

    function doSearch() { 
        const q = sIn.value.trim(); 
        const s = sSel.value; 
        const category = activeCategory;
        
        if (q.length === 1) {
            if (spinner) spinner.style.display = 'none';
            return; 
        }
        
        if (spinner) spinner.style.display = 'flex'; 
        clearTimeout(debounceTimer);

        debounceTimer = setTimeout(() => {
            fetch(`home.php?ajax=1&search=${encodeURIComponent(q)}&sort=${s}&category=${category}`)
                .then(r => r.text())
                .then(h => { 
                    cG.innerHTML = ''; 
                    if (spinner) cG.appendChild(spinner); 
                    cG.insertAdjacentHTML('beforeend', h); 
                    if (spinner) spinner.style.display = 'none'; 
                    bBind(); 
                })
                .catch(e => { 
                    if (spinner) spinner.style.display = 'none'; 
                    console.error("Search fetch request error: ", e);
                }); 
        }, 400); // 400ms pace delay setting
    }

    if (sIn) sIn.addEventListener('input', doSearch); 
    if (sSel) sSel.addEventListener('change', doSearch);
    categoryChips.forEach(chip => chip.addEventListener('click', () => {
        activeCategory = Number(chip.dataset.category || 0);
        categoryChips.forEach(item => item.classList.toggle('is-active', item === chip));
        doSearch();
    }));

    // Global Sub-Page Link Redirect Transition Animation Controller 
    function attachGlobalTabLoaders() {
        const elementsToAnimate = document.querySelectorAll('nav a, .nav-loading-link');
        
        elementsToAnimate.forEach(link => {
            if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:')) return;
            
            link.addEventListener('click', function(e) {
                const targetUrl = this.href;
                const currentUrl = window.location.href.split('?')[0];
                const clickedUrl = targetUrl.split('?')[0];
                
                // If destination matches current URL location, exit to avoid hanging spinner elements
                if (currentUrl === clickedUrl) {
                    return; 
                }
                
                e.preventDefault();
                if (globalPageLoader) globalPageLoader.style.display = 'flex';
                
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 400);
            });
        });
    }

    // Re-bind submissions listener hooks dynamically to new nodes
    function bBind() { 
        document.querySelectorAll('.ajax-cart-form').forEach(f => { 
            if (f.dataset.bound) return; 
            f.dataset.bound = true; 
            
            f.addEventListener('submit', function(e) { 
                e.preventDefault(); 
                
                // Show an inline loader and prevent duplicate cart submissions.
                const currentForm = this;
                const submitButton = currentForm.querySelector('.basket-btn');
                const buttonText = currentForm.querySelector('.basket-btn-text');
                const loadingStartedAt = Date.now();
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
                buttonText.textContent = 'Adding...';

                fetch('add_to_cart_ajax.php', { method: 'POST', body: new FormData(currentForm) })
                    .then(r => r.json())
                    .then(d => {
                        if (d.status !== 'success') {
                            throw new Error(d.message || 'Unable to add the product to your basket.');
                        }

                        document.getElementById('cart-nav-count').textContent = d.new_count;
                        const qtyField = currentForm.querySelector('input[type="number"], .qty-input');
                        if (qtyField) qtyField.value = '1';

                        // Keep the loader visible long enough to be noticed, even on a fast local request.
                        const remainingLoaderTime = Math.max(0, 650 - (Date.now() - loadingStartedAt));
                        setTimeout(() => {
                            submitButton.classList.remove('is-loading');
                            buttonText.textContent = 'Added!';
                            submitButton.style.backgroundColor = '#059669';

                            setTimeout(() => {
                                submitButton.disabled = false;
                                submitButton.style.backgroundColor = '';
                                buttonText.textContent = 'Add to Basket';
                            }, 900);
                        }, remainingLoaderTime);
                    })
                    .catch(err => {
                        submitButton.disabled = false;
                        submitButton.classList.remove('is-loading');
                        buttonText.textContent = 'Add to Basket';
                        alert(err.message || 'Unable to add the product to your basket.');
                    });
            });
        }); 
        attachGlobalTabLoaders();
    } 
    bBind();
    </script>
<?php if ($user_id > 0): ?><script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script><script src="../js/session-idle.js"></script><?php endif; ?>
</body>
</html>
