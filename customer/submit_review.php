<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

$user_id = $_SESSION['user_id'] ?? null;
$fullname = $_SESSION['fullname'] ?? 'Customer';

if (!$user_id) {
    die("Access denied. Please log in first.");
}

$product_id = isset($_GET['product_id'])
    ? (int) $_GET['product_id']
    : (isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0);

$p_name = "Hardware Appliance";

if ($product_id > 0) {
    $p_st = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
    $p_st->bind_param("i", $product_id);
    $p_st->execute();
    $p_res = $p_st->get_result()->fetch_assoc();
    $p_name = $p_res['product_name'] ?? $p_name;
    $p_st->close();
}

$msg = '';
$err = '';

if (empty($_SESSION['review_form_csrf'])) {
    $_SESSION['review_form_csrf'] = bin2hex(random_bytes(32));
}
$review_form_csrf = $_SESSION['review_form_csrf'];

// Reviews are reserved for products contained in this customer's delivered orders.
$eligible_stmt = $conn->prepare("
    SELECT 1 FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ? AND oi.product_id = ? AND LOWER(o.order_status) = 'delivered'
    LIMIT 1
");
$eligible_stmt->bind_param("ii", $user_id, $product_id);
$eligible_stmt->execute();
$is_verified_purchase = (bool)$eligible_stmt->get_result()->fetch_row();
$eligible_stmt->close();

$review_stmt = $conn->prepare("
    SELECT id, star_rating, review_comment, created_at
    FROM product_reviews
    WHERE user_id = ? AND product_id = ?
    LIMIT 1
");
$review_stmt->bind_param("ii", $user_id, $product_id);
$review_stmt->execute();
$existing_review = $review_stmt->get_result()->fetch_assoc();
$review_stmt->close();

$can_edit = false;

if ($existing_review) {
    $review_age_seconds = time() - strtotime($existing_review['created_at']);
    $can_edit = $review_age_seconds <= 900; // 15 minutes
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review_form'])) {
    $rating = (int) ($_POST['star_rating'] ?? 0);
    $comment = trim($_POST['review_comment'] ?? '');

    if (!hash_equals($review_form_csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $err = "Your secure form expired. Refresh and try again.";
    } elseif (!$is_verified_purchase) {
        $err = "Only customers who received this product can submit a review.";
    } elseif ($product_id <= 0 || empty($comment) || mb_strlen($comment) < 10 || mb_strlen($comment) > 1500 || $rating < 1 || $rating > 5) {
        $err = "Select a rating and enter a comment between 10 and 1,500 characters.";
    } elseif ($existing_review && !$can_edit) {
        $err = "This review can no longer be edited. Reviews are locked after 15 minutes.";
    } elseif ($existing_review) {
        $update = $conn->prepare("
            UPDATE product_reviews
            SET star_rating = ?, review_comment = ?, customer_name = ?,
                is_approved = 0, moderation_status = 'pending', moderated_by = NULL, moderated_at = NULL, moderation_note = NULL
            WHERE id = ? AND user_id = ? AND product_id = ?
        ");
        $update->bind_param(
            "issiii",
            $rating,
            $comment,
            $fullname,
            $existing_review['id'],
            $user_id,
            $product_id
        );

        if ($update->execute()) {
            $msg = "Your updated review was sent for moderation.";
            $existing_review['star_rating'] = $rating;
            $existing_review['review_comment'] = $comment;
            $_SESSION['review_form_csrf'] = bin2hex(random_bytes(32));
            $review_form_csrf = $_SESSION['review_form_csrf'];
        } else {
            $err = "Unable to update your review. Please try again.";
        }

        $update->close();
    } else {
        $insert = $conn->prepare("
            INSERT INTO product_reviews
                (user_id, product_id, customer_name, star_rating, review_comment, is_approved, moderation_status, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 'pending', NOW())
        ");
        $insert->bind_param("iisis", $user_id, $product_id, $fullname, $rating, $comment);

        if ($insert->execute()) {
            $msg = "Your review was submitted for moderation. You can edit it for the next 15 minutes.";

            $existing_review = [
                'star_rating' => $rating,
                'review_comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $can_edit = true;
            $_SESSION['review_form_csrf'] = bin2hex(random_bytes(32));
            $review_form_csrf = $_SESSION['review_form_csrf'];
        } else {
            $err = "Unable to publish your review. Please try again.";
        }

        $insert->close();
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>Write a Review</title> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
<style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; }
    .brand-title { font-weight: 800; font-size: 1.25rem; color: #f97316; text-decoration: none; white-space: nowrap; }
    .back-btn { color: #9ca3af; text-decoration: none; font-size: 0.875rem; font-weight: 600; white-space: nowrap; transition: color 0.2s ease; }
    .back-btn:hover { color: white; text-decoration: underline; }
    
    /* 3. Core Form Container (Default Desktop/Tablet View) */
    main { max-width: 36rem; margin: 40px auto; padding: 24px; background-color: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 16px; }
    
    /* Form Informational & Alert Structural Elements */
    .info-box { padding: 12px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; color: #374151; margin-bottom: 24px; }
    .product-highlight { color: #2563eb; text-transform: uppercase; }
    .alert-box { padding: 12px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    
    /* 4. Form Fields & Layout Inputs Grouping */
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.75rem; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 6px; }
    
    /* Select Element - Fixed bad property assignment string code errors */
    .form-select { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; background-color: white; color: #f59e0b; font-weight: 800; font-size: 0.875rem; outline: none; cursor: pointer; height: 38px; }
    
    /* Textarea Element */
    .form-textarea { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; font-size: 0.875rem; color: #1f2937; outline: none; min-height: 120px; resize: vertical; font-family: inherit; }
    .form-textarea:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1); }
    
    /* Form Submission Button Layout Block */
    .submit-btn { width: 100%; background-color: #111827; color: white; font-weight: 700; padding: 12px 0; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; transition: background-color 0.2s ease; height: 42px; display: inline-flex; align-items: center; justify-content: center; }
    .submit-btn:hover { background-color: #1f2937; }

       /* ==========================================================================
       5. RESPONSIVE SCREEN QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 768px Width Screens) */
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
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 12px 16px !important; 
            background-color: #0f172a !important; /* Rich deep signature dark background */
            z-index: 9999 !important; /* Forces inputs and buttons to pass underneath the menu */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1e293b !important;
        }

        .brand-title { font-size: 1.1rem !important; }
        .back-btn { font-size: 0.8rem !important; }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your form content safely below the fixed nav height */
        main { 
            margin-top: 85px !important; /* Pushes the review form card safely out of the fixed nav area */
            margin-left: 16px !important;
            margin-right: 16px !important;
            margin-bottom: 16px !important;
            padding: 16px !important; 
            border-radius: 0.5rem !important; 
            box-sizing: border-box !important;
            display: block !important;
            width: calc(100% - 32px) !important;
        }

        .main-title { font-size: 1.3rem; }
        
        /* Increase component heights for cleaner tap targets on mobile device view screens */
        .info-box { font-size: 0.8rem; margin-bottom: 16px; padding: 10px; width: 100% !important; box-sizing: border-box !important; }
        
        /* Universal Form Fields Mobile Scaling */
        form {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .form-select { 
            width: 100% !important;
            height: 42px !important; 
            font-size: 0.9rem !important; 
            box-sizing: border-box !important;
        }

        .form-textarea { 
            width: 100% !important;
            font-size: 0.9rem !important; 
            padding: 12px !important; 
            box-sizing: border-box !important;
        }

        .submit-btn { 
            width: 100% !important;
            height: 46px !important; 
            font-size: 0.9rem !important; 
            box-sizing: border-box !important;
        }
    }

</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <nav>
        <a href="home.php" class="brand-title">⚡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn">← Cancel</a>
    </nav>
    <main>
        <h1 class="main-title">
    <?= $existing_review ? 'Product Feedback' : 'Submit Product Feedback'; ?>
</h1>

<div class="info-box">
    Reviewing Item:
    <span class="product-highlight"><?= htmlspecialchars($p_name); ?></span>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div>
<?php endif; ?>

<?php if ($existing_review && !$can_edit): ?>
    <div class="alert-box alert-danger">
        🔒 Your review is locked. Reviews can only be edited within 15 minutes of publishing.
    </div>

    <div class="info-box">
        <strong>Your rating:</strong>
        <?= str_repeat('⭐', (int) $existing_review['star_rating']); ?>
        <br><br>
        <?= nl2br(htmlspecialchars($existing_review['review_comment'])); ?>
    </div>

<?php elseif (!$is_verified_purchase): ?>
    <div class="alert-box alert-danger">Only customers with a delivered order containing this product can submit feedback.</div>
<?php else: ?>
    <form method="POST" class="space-y-5">
        <input type="hidden" name="product_id" value="<?= $product_id; ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($review_form_csrf); ?>">

        <div class="form-group">
            <label class="form-label">Assign Star Rating:</label>

            <select name="star_rating" class="form-select">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                    <option
                        value="<?= $star; ?>"
                        <?= ((int) ($existing_review['star_rating'] ?? 5) === $star) ? 'selected' : ''; ?>
                    >
                        <?= str_repeat('⭐', $star); ?> <?= $star; ?> Star
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Write your experience details:</label>

            <textarea
                name="review_comment"
                class="form-textarea"
                required
            ><?= htmlspecialchars($existing_review['review_comment'] ?? ''); ?></textarea>
        </div>

        <button type="submit" name="submit_review_form" class="submit-btn">
            <?= $existing_review ? 'Update Product Review' : 'Publish Product Review'; ?>
        </button>
    </form>
<?php endif; ?>
    </main>
</body>
</html>
