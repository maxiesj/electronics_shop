<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

$user_id = $_SESSION['user_id'] ?? null;
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("
    SELECT p.*, b.brand_name
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: home.php");
    exit;
}

$rev_stmt = $conn->prepare("
    SELECT *
    FROM product_reviews
    WHERE product_id = ? AND is_approved = 1 AND moderation_status = 'live'
      AND EXISTS (
          SELECT 1 FROM orders verified_order
          INNER JOIN order_items verified_item ON verified_item.order_id = verified_order.id
          WHERE verified_order.user_id = product_reviews.user_id
            AND verified_item.product_id = product_reviews.product_id
            AND LOWER(TRIM(verified_order.order_status)) = 'delivered'
      )
    ORDER BY id DESC
");
$rev_stmt->bind_param("i", $product_id);
$rev_stmt->execute();
$reviews = $rev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rev_stmt->close();

$avg_stmt = $conn->prepare("
    SELECT AVG(star_rating)
    FROM product_reviews
    WHERE product_id = ? AND is_approved = 1 AND moderation_status = 'live'
      AND EXISTS (
          SELECT 1 FROM orders verified_order
          INNER JOIN order_items verified_item ON verified_item.order_id = verified_order.id
          WHERE verified_order.user_id = product_reviews.user_id
            AND verified_item.product_id = product_reviews.product_id
            AND LOWER(TRIM(verified_order.order_status)) = 'delivered'
      )
");
$avg_stmt->bind_param("i", $product_id);
$avg_stmt->execute();
$res_avg = $avg_stmt->get_result()->fetch_row();
$avg_stars = ($res_avg && $res_avg[0] !== null)
    ? round((float) $res_avg[0], 1)
    : 'No ratings yet';
$avg_stmt->close();

$can_review_product = false;
if ($user_id) {
    $review_eligibility = $conn->prepare("
        SELECT 1 FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.product_id = ? AND LOWER(o.order_status) = 'delivered'
        LIMIT 1
    ");
    $review_eligibility->bind_param("ii", $user_id, $product_id);
    $review_eligibility->execute();
    $can_review_product = (bool)$review_eligibility->get_result()->fetch_row();
    $review_eligibility->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title><?= htmlspecialchars($product['product_name']); ?> | Detail</title>

    <style>
        body {
            background-color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, sans-serif;
            margin: 0;
            color: #1f2937;
        }

        nav {
            background-color: #111827;
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }

        .brand-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: #f97316;
            text-decoration: none;
            white-space: nowrap;
        }

        .back-btn {
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .back-btn:hover {
            color: white;
            text-decoration: underline;
        }
        .back-btn.is-loading { pointer-events: none; opacity: 0.72; }
        .back-loader { display: none; width: 13px; height: 13px; margin-right: 7px; border: 2px solid #6b7280; border-top-color: #ffffff; border-radius: 50%; animation: backNavSpin 0.7s linear infinite; }
        .back-btn.is-loading .back-loader { display: inline-block; }
        @keyframes backNavSpin { to { transform: rotate(360deg); } }

        main {
            max-width: 64rem;
            margin: 40px auto;
            padding: 24px;
            background-color: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            box-sizing: border-box;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 32px;
            margin-bottom: 40px;
        }

        .img-box {
            height: 320px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 16px;
            overflow: hidden;
            box-sizing: border-box;
        }

        .img-box img {
            max-height: 100%;
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .details-box {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
            color: #2563eb;
            background-color: #eff6ff;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #bfdbfe;
            width: fit-content;
        }

        .product-title {
            font-size: 1.5rem;
            font-weight: 900;
            color: #111827;
            text-transform: uppercase;
            margin: 12px 0;
            line-height: 1.2;
        }

        .rating-row {
            font-size: 0.75rem;
            font-weight: 700;
            color: #d97706;
        }

        .desc-text {
            color: #4b5563;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
            gap: 12px;
        }

        .price-text {
            color: #059669;
            font-weight: 900;
            font-size: 1.5rem;
            margin: 0;
        }

        .stock-text {
            font-size: 0.75rem;
            color: #4b5563;
            background-color: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            margin: 0;
        }

        .cart-form {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            margin-top: 16px;
        }

        .qty-input {
            width: 56px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 0;
            text-align: center;
            font-weight: 900;
            height: 38px;
            box-sizing: border-box;
        }

        .basket-btn {
            flex: 1;
            background-color: #2563eb;
            color: white;
            font-weight: 700;
            padding: 10px 0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 12px;
            height: 38px;
        }

        .basket-btn:hover {
            background-color: #1d4ed8;
        }

        .review-btn {
            display: inline-flex;
            width: 100%;
            margin-top: 12px;
            text-align: center;
            justify-content: center;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            color: #374151;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 10px 0;
            border-radius: 8px;
            text-decoration: none;
            text-transform: uppercase;
        }

        .reviews-section {
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
            margin-top: 24px;
        }

        .section-title {
            font-size: 0.875rem;
            font-weight: 900;
            color: #111827;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .review-card {
            padding: 16px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            margin-bottom: 16px;
        }

        .rev-meta {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 4px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .rev-user {
            color: #111827;
            text-transform: uppercase;
        }

        .rev-text {
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
            margin: 6px 0;
            line-height: 1.4;
            word-break: break-word;
        }

        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                gap: 12px;
                padding: 12px 16px;
                text-align: center;
            }

            main {
                margin: 16px;
                padding: 16px;
            }

            .grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 24px;
            }

            .img-box {
                height: 260px;
            }
        }
    </style>
<script src="../js/page-progress-dialog.js"></script>
</head>

<body>
    <nav>
        <a href="home.php" class="brand-title">âš¡ ADONAK ELECTRONICS</a>
        <a href="home.php" class="back-btn" id="backShopLink"><span class="back-loader" aria-hidden="true"></span><span class="back-home-text">â† Back to Shop</span></a>
    </nav>

    <main>
        <div class="grid">
            <div class="img-box">
                <img
                    src="../uploads/<?= htmlspecialchars($product['image'] ?? 'placeholder.png'); ?>"
                    alt="<?= htmlspecialchars($product['product_name']); ?>"
                >
            </div>

            <div class="details-box">
                <div>
                    <span class="badge"><?= htmlspecialchars($product['brand_name'] ?? 'General'); ?></span>
                    <h1 class="product-title"><?= htmlspecialchars($product['product_name']); ?></h1>
                    <div class="rating-row">â­ Average Score: <?= htmlspecialchars((string) $avg_stars); ?></div>
                    <p class="desc-text"><?= nl2br(htmlspecialchars($product['description'] ?? 'No product description available.')); ?></p>
                </div>

                <div>
                    <div class="price-row">
                        <p class="price-text">KES <?= number_format((float) $product['price'], 2); ?></p>

                        <?php if ((int) $product['stock_quantity'] <= 0): ?>
                            <p class="stock-text" style="color:#ef4444; background:#fee2e2;">Out of Stock</p>
                        <?php else: ?>
                            <p class="stock-text"><?= (int) $product['stock_quantity']; ?> units available</p>
                        <?php endif; ?>
                    </div>

                    <form id="detail-cart-form" class="cart-form">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                        <span style="color:#6b7280; font-weight:700;">QTY:</span>

                        <?php if ((int) $product['stock_quantity'] <= 0): ?>
                            <input type="number" value="0" disabled class="qty-input">
                            <button type="button" disabled class="basket-btn" style="background:#9ca3af; cursor:not-allowed;">
                                Out of Stock
                            </button>
                        <?php else: ?>
                            <input
                                type="number"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?= (int) $product['stock_quantity']; ?>"
                                class="qty-input"
                            >
                            <button type="submit" class="basket-btn">Add to Basket</button>
                        <?php endif; ?>
                    </form>
                    <?php if ($can_review_product): ?>
                    <a href="submit_review.php?product_id=<?= (int) $product['id']; ?>" class="review-btn">
                        Write Feedback Review
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="reviews-section">
            <h3 class="section-title">Customer Reviews (<?= count($reviews); ?>)</h3>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="review-card">
                        <div class="rev-meta">
                            <span>
                                By:
                                <span class="rev-user"><?= htmlspecialchars($rev['customer_name']); ?></span>
                            </span>
                            <span style="color:#f59e0b;">
                                <?= str_repeat('â­', (int) $rev['star_rating']); ?>
                            </span>
                        </div>

                        <p class="rev-text"><?= nl2br(htmlspecialchars($rev['review_comment'])); ?></p>
                        <span style="color:#9ca3af; font-size:10px;">
                            <?= htmlspecialchars($rev['created_at']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="rev-text" style="color:#6b7280;">
                    No approved reviews have been posted for this product yet.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const cartForm = document.getElementById('detail-cart-form');

        if (cartForm) {
            cartForm.addEventListener('submit', function (event) {
                event.preventDefault();

                fetch('add_to_cart_ajax.php', {
                    method: 'POST',
                    body: new FormData(cartForm)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = 'cart.php';
                        return;
                    }

                    alert(data.message || 'Unable to add this product to the basket.');
                })
                .catch(() => {
                    alert('Unable to add this product to the basket. Please try again.');
                });
            });
        }
    </script>
</body>
</html>
