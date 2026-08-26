<?php
session_start();
include '../db.php';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$fullname = $_SESSION['fullname'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
<title>About ADONAK Electronics</title>
<style>
*{box-sizing:border-box} body{margin:0;background:#f5f7fb;color:#1e293b;font-family:ui-sans-serif,system-ui,sans-serif}
nav{min-height:72px;padding:14px max(24px,calc((100vw - 1200px)/2));display:flex;align-items:center;justify-content:space-between;gap:20px;background:#111827;color:#fff}
.brand{color:#f97316;font-weight:900;text-decoration:none;font-size:18px}.navlinks{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.navlinks a{color:#e5e7eb;text-decoration:none;font-size:13px;font-weight:700}.navlinks a:hover{color:#fb923c}
.hero{padding:72px 24px;background:linear-gradient(120deg,#172554,#1d4ed8);color:#fff}.hero-inner,.content{width:min(1120px,100%);margin:auto}.eyebrow{color:#fdba74;text-transform:uppercase;font-size:11px;font-weight:900;letter-spacing:.12em}.hero h1{max-width:760px;margin:10px 0 14px;font-size:clamp(30px,5vw,52px);line-height:1.08}.hero p{max-width:720px;color:#bfdbfe;line-height:1.7;margin:0}.hero-actions{display:flex;gap:10px;margin-top:25px;flex-wrap:wrap}.hero-actions a{padding:11px 17px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:900}.primary{background:#f97316;color:#fff}.secondary{border:1px solid #93c5fd;color:#fff}.secondary-disabled{padding:11px 17px;border-radius:8px;font-size:12px;font-weight:900;border:1px solid rgba(203,213,225,.35);color:#cbd5e1;background:rgba(15,23,42,.25);opacity:.65;cursor:not-allowed;user-select:none}
.content{padding:42px 20px 70px}.section-title{text-align:center;margin:0 0 25px}.section-title h2{margin:0 0 8px;font-size:25px}.section-title p{margin:0;color:#64748b}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.card{padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 5px 18px rgba(15,23,42,.05)}.card span{font-size:25px}.card h3{margin:14px 0 8px;font-size:16px}.card p{margin:0;color:#64748b;font-size:13px;line-height:1.65}
.promise{margin-top:30px;padding:28px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center}.promise h2{margin:0 0 8px;font-size:21px}.promise p{margin:0;color:#9a3412;line-height:1.6;font-size:13px}.promise strong{color:#ea580c;font-size:18px;white-space:nowrap}
footer{padding:24px;text-align:center;background:#111827;color:#94a3b8;font-size:12px}
@media(max-width:760px){nav{flex-direction:column;padding:14px 16px}.navlinks{justify-content:center}.hero{padding:48px 20px}.cards{grid-template-columns:1fr}.promise{grid-template-columns:1fr}.promise strong{white-space:normal}}
</style>
<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
<nav>
<a class="brand" href="home.php">&#9889; ADONAK ELECTRONICS</a>
<div class="navlinks">
<span>Hello, <strong><?= htmlspecialchars($fullname); ?></strong></span>
<a href="home.php">Shop</a>
<a href="about.php">About Us</a>
<?php if ($user_id > 0): ?>
<a href="cart.php">Cart</a><a href="my_orders.php">Orders</a><a href="profile.php">Profile</a><a href="../logout.php" style="color:#f87171">Log Out</a>
<?php else: ?>
<a href="../register.php" style="color:#fb923c">Create Account</a><a href="../login.php">Log In</a>
<?php endif; ?>
</div>
</nav>
<section class="hero">
<div class="hero-inner">
<span class="eyebrow">About ADONAK Electronics</span>
<h1>Reliable electronics with flexible ways to pay.</h1>
<p>We help customers find genuine phones, laptops, televisions, appliances and accessories while keeping ordering, payment tracking and after-sales support clear and convenient.</p>
<div class="hero-actions">
<a class="primary" href="home.php">Explore Products</a>
<?php if ($user_id > 0): ?>
<a class="secondary" href="my_orders.php">Track My Orders</a>
<?php else: ?>
<span class="secondary-disabled" aria-disabled="true" title="Create an account or log in to track orders">Log in to Track Orders</span>
<?php endif; ?>
</div>
</div>
</section>
<main class="content">
<div class="section-title"><h2>What we stand for</h2><p>A practical shopping experience built around trust and flexibility.</p></div>
<div class="cards">
<article class="card"><span>&#9989;</span><h3>Genuine catalogue</h3><p>Clear product information, visible stock levels and transparent pricing help customers make confident choices.</p></article>
<article class="card"><span>&#9203;</span><h3>Lipa Pole Pole</h3><p>Eligible purchases can begin with a 50% deposit, followed by flexible balance payments within the 30-day plan.</p></article>
<article class="card"><span>&#128274;</span><h3>Accountable payments</h3><p>Wallet transactions, installment payments, balances and statements remain connected to each customer order.</p></article>
<article class="card"><span>&#128666;</span><h3>Order visibility</h3><p>Customers can follow processing and delivery progress while staff manage fulfillment from one coordinated system.</p></article>
<article class="card"><span>&#128736;</span><h3>After-sales support</h3><p>Order statements, product feedback and customer account tools remain available after checkout.</p></article>
<article class="card"><span>&#129309;</span><h3>Customer-first service</h3><p>We aim for clear communication, polite payment reminders and fair safeguards throughout the purchase journey.</p></article>
</div>
<section class="promise"><div><h2>Our promise</h2><p>To provide dependable electronics, accurate financial records and a shopping experience that respects the customer at every stage.</p></div><strong>Shop confidently. Pay flexibly.</strong></section>
</main>
<footer>&copy; <?= date('Y'); ?> ADONAK Electronics. All rights reserved.</footer>
</body>
</html>