<?php
session_start();
include '../db.php';

$user_id = $_SESSION['user_id'] ?? null;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$user_id || $order_id <= 0) {
    header("Location: home.php");
    exit;
}

// Load only an order that belongs to the signed-in customer.
$st = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$st->bind_param("ii", $order_id, $user_id);
$st->execute();
$order = $st->get_result()->fetch_assoc();
$st->close();

if (!$order) {
    http_response_code(404);
    die("The requested receipt verification data was not found.");
}

// Load the most recent payment record attached to this order.
$pay_st = $conn->prepare(
    "SELECT id, payment_method, transaction_code, amount, payment_status, created_at
     FROM payments
     WHERE order_id = ?
     ORDER BY id DESC
     LIMIT 1"
);
$pay_st->bind_param("i", $order_id);
$pay_st->execute();
$payment = $pay_st->get_result()->fetch_assoc();
$pay_st->close();

$payment_status = strtolower(trim((string)($payment['payment_status'] ?? 'pending')));
$order_status = strtolower(trim((string)($order['order_status'] ?? 'pending')));
$payment_method = trim((string)($payment['payment_method'] ?? 'System Account Channels'));
$transaction_ref = trim((string)($payment['transaction_code'] ?? ''));
$display_amount = isset($payment['amount']) && (float)$payment['amount'] > 0
    ? (float)$payment['amount']
    : (float)$order['total_amount'];

$is_paid = $payment_status === 'completed';
$page_title = $is_paid ? 'Invoice Paid Successfully' : 'Order Recorded Successfully';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title><?= htmlspecialchars($page_title); ?></title>
    <style>
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .receipt-card { max-width: 28rem; width: 100%; background-color: white; border: 1px solid #e5e7eb; padding: 32px; border-radius: 1rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; margin: 16px; box-sizing: border-box; }
    .check-icon { width: 64px; height: 64px; background-color: #d1fae5; border-radius: 9999px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #059669; font-size: 1.5rem; font-weight: 900; flex-shrink: 0; }
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; letter-spacing: -0.025em; margin: 0; line-height: 1.2; }
    .ref-text { font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; margin-top: 4px; margin-bottom: 0; word-break: break-all; }
    .details-box { margin: 24px 0; padding: 16px; background-color: #f9fafb; border-radius: 0.75rem; border: 1px solid #f3f4f6; text-align: left; font-size: 0.75rem; font-weight: 600; color: #4b5563; box-sizing: border-box; }
    .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; gap: 12px; align-items: center; }
    .info-row:last-child { margin-bottom: 0; }
    .status-badge { color: #046c4e; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
    .order-status-badge { color: #1d4ed8; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
    .total-row { display: flex; justify-content: space-between; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 8px; font-size: 0.875rem; color: #111827; font-weight: 700; gap: 12px; align-items: center; }
    .grand-price { color: #059669; font-weight: 900; font-size: 1.25rem; white-space: nowrap; }
    .home-btn { width: 100%; box-sizing: border-box; background-color: #111827; color: white; padding: 12px 0; border-radius: 6px; text-decoration: none; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; text-align: center; cursor: pointer; transition: background-color 0.2s ease; height: 44px; display: inline-flex; align-items: center; justify-content: center; }
    .home-btn:hover { background-color: #1f2937; }
    .spinner { width: 50px; height: 50px; border: 5px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 480px) {
        body { padding: 8px; }
        .receipt-card { padding: 24px 16px; margin: 8px; border-radius: 0.75rem; }
        .main-title { font-size: 1.25rem; }
        .check-icon { width: 56px; height: 56px; font-size: 1.25rem; margin-bottom: 12px; }
        .details-box { margin: 16px 0; padding: 12px; font-size: 0.7rem; }
        .info-row { margin-bottom: 10px; }
        .home-btn { height: 48px; font-size: 0.9rem; }
    }
    </style>
    <script src="../js/page-progress-dialog.js"></script>
</head>
<body>
    <div class="receipt-card">
        <div class="check-icon">✓</div>
        <h1 class="main-title"><?= htmlspecialchars($page_title); ?></h1>
        <p class="ref-text">
            Transaction Ref:
            <?= htmlspecialchars($transaction_ref !== '' ? $transaction_ref : ('ORDER-' . $order['id'])); ?>
        </p>

        <div class="details-box">
            <div class="info-row">
                <span>Settlement Mode:</span>
                <strong style="color:#111827;"><?= htmlspecialchars($payment_method); ?></strong>
            </div>
            <div class="info-row">
                <span>Payment Status:</span>
                <span class="status-badge"><?= htmlspecialchars($payment_status); ?></span>
            </div>
            <div class="info-row">
                <span>Order Status:</span>
                <span class="order-status-badge"><?= htmlspecialchars($order_status); ?></span>
            </div>
            <div class="total-row">
                <span><?= $is_paid ? 'Amount Paid:' : 'Order Total:'; ?></span>
                <span class="grand-price">KES <?= number_format($display_amount, 2); ?></span>
            </div>
        </div>

        <a href="home.php" class="home-btn nav-loading-link">Return to Store Layout</a>
    </div>

    <div id="global-page-loader" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner"></div>
        <p style="color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; font-family: sans-serif;">Connecting Securely...</p>
    </div>

    <script>
    document.querySelectorAll('.nav-loading-link').forEach(link => {
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
</body>
</html>
