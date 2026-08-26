<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once '../session_auth.php';
if (!verifyExplicitWorkspaceClearance('mpesa_checker.php')) {
    header("Location: ../login.php");
    exit;
}

$staff_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff Member';
$search_code = isset($_GET['txn_code']) ? strtoupper(trim((string)$_GET['txn_code'])) : '';
$payment_rows = [];
$searched = false;
$search_error = '';

function staffPaymentStatusClass($status) {
    $value = strtolower(trim((string)$status));
    return in_array($value, ['completed', 'refunded', 'failed'], true) ? $value : 'pending';
}

function staffPaymentDisplayStatus($status) {
    $value = strtolower(trim((string)$status));
    return $value !== '' ? ucfirst($value) : 'Not recorded';
}

if ($search_code !== '') {
    $searched = true;
    if ($search_code === '0' || !preg_match('/^[A-Z0-9_-]{4,100}$/', $search_code)) {
        $search_error = 'Enter a valid payment reference using 4 to 100 letters, numbers, hyphens or underscores.';
    } else {
    // Return every genuine local match so reused references cannot be mistaken for unique records.
    $query = "SELECT p.id, p.order_id, p.payment_method, p.transaction_code, p.amount,
                     p.payment_status, p.created_at, o.user_id, o.total_amount AS order_total, u.fullname, u.phone
              FROM payments p 
              LEFT JOIN orders o ON p.order_id = o.id 
              LEFT JOIN users u ON o.user_id = u.id 
              WHERE UPPER(TRIM(p.transaction_code)) = ?
                AND TRIM(p.transaction_code) <> '0'
              ORDER BY p.id DESC LIMIT 100";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $search_error = 'The payment records could not be searched right now. Please try again.';
    } else {

    $stmt->bind_param("s", $search_code);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (empty($row['fullname']) && preg_match('/^TXN_DEP_U(\d+)_/i', (string)$row['transaction_code'], $deposit_match)) {
            $deposit_user_id = (int)$deposit_match[1];
            $user_stmt = $conn->prepare('SELECT fullname, phone FROM users WHERE id = ? LIMIT 1');
            $user_stmt->bind_param('i', $deposit_user_id);
            $user_stmt->execute();
            $deposit_user = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            if ($deposit_user) {
                $row['fullname'] = $deposit_user['fullname'];
                $row['phone'] = $deposit_user['phone'];
            }
        }
        $payment_rows[] = $row;
    }
    $stmt->close();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>M-Pesa Transaction Verification | ADONAK ELECTRONICS</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; z-index: 50; }
    .nav-brand { font-weight: 800; font-size: 1.25rem; color: #f97316; white-space: nowrap; }
    .nav-center-links { display: flex; gap: 16px; font-size: 0.875rem; font-weight: 600; align-items: center; }
    .nav-center-links a { color: #d1d5db; text-decoration: none; padding: 6px 12px; border-radius: 4px; transition: background 0.2s, color 0.2s; white-space: nowrap; }
    .nav-center-links a:hover, .nav-center-links a.active { color: white; background-color: #1f2937; }
    
    /* Session Meta & Exit Controls */
    .nav-right-meta { display: flex; align-items: center; gap: 20px; font-size: 0.875rem; }
    .logout-btn { color: #f87171 !important; text-decoration: none; font-weight: 700; border: 1px solid #7f1d1d; padding: 6px 12px; border-radius: 6px; background-color: rgba(153, 27, 27, 0.12); white-space: nowrap; transition: background 0.2s, color 0.2s; }
    .logout-btn:hover { background-color: rgba(153, 27, 27, 0.25); color: #fca5a5 !important; }
    
    /* 3. Core Structural Search Wrapper (Default Desktop View) */
    main { max-width: 44rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    
    /* Fixed broken custom property variable allocations */
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0 0 8px; letter-spacing: -0.025em; }
    .sub-subtitle { font-size: 0.813rem; color: #6b7280; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; }

    /* Serial Entry Bar Section Controls */
    .search-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; box-sizing: border-box; }
    .search-row { display: flex; gap: 12px; align-items: center; }
    .search-input { flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 14px; font-size: 0.875rem; font-weight: 700; outline: none; text-transform: uppercase; color: #111827; height: 42px; box-sizing: border-box; }
    .search-input:focus { border-color: #f97316; box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.1); }
    
    /* Execution Verification Action Trigger */
    .verify-btn { background-color: #f97316; color: white; font-weight: 800; border: none; padding: 0 24px; border-radius: 6px; font-size: 0.875rem; text-transform: uppercase; cursor: pointer; height: 42px; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; box-sizing: border-box; transition: background-color 0.2s; }
    .verify-btn:hover { background-color: #ea580c; }

    /* 4. Results Mapping Ledger Components */
    .result-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .result-title { font-size: 0.875rem; font-weight: 900; color: #374151; text-transform: uppercase; margin: 0 0 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; letter-spacing: 0.05em; }
    
    /* Dynamic Details Information Items View */
    .detail-row { display: flex; justify-content: space-between; border-bottom: 1px solid #f3f4f6; padding: 12px 0; font-size: 0.875rem; font-weight: 600; color: #4b5563; gap: 16px; align-items: center; box-sizing: border-box; }
    .detail-row:last-child { border-bottom: none; }
    .label-text { color: #9ca3af; font-size: 11px; text-transform: uppercase; font-weight: 700; white-space: nowrap; }
    
    .status-pill { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; white-space: nowrap; }
    .status-success,.status-completed { background-color: #d1fae5; color: #065f46; }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-refunded { background-color: #e0e7ff; color: #3730a3; }
    .status-failed { background-color: #fee2e2; color: #991b1b; }
    .duplicate-warning { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #fbbf24; border-radius: 8px; background: #fffbeb; color: #92400e; font-size: 13px; font-weight: 700; }
    .result-block + .result-block { margin-top: 14px; }

    /* Fallback Unmatched Empty Warnings Cards */
    .no-result { text-align: center; padding: 32px 16px; color: #6b7280; font-weight: 700; font-size: 0.875rem; text-transform: uppercase; background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; box-sizing: border-box; }

    /* Full-Screen Page Loader Processing Actions Overlay */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â± SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & TABLETS - Max 768px Width Screens) */
    @media (max-width: 768px) {
        /* Restructure Navbar row elements into stacked vertical flow */
        nav { flex-direction: column; gap: 14px; padding: 14px 16px; text-align: center; }
        .nav-center-links { gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .nav-center-links a { font-size: 0.8rem; padding: 4px 8px; }
        .nav-right-meta { width: 100%; justify-content: center; border-top: 1px solid #374151; padding-top: 10px; margin-top: 2px; }
        
        /* Compress master container bounds padding margins */
        main { padding: 0 16px 40px; margin: 16px auto; }
        .main-title { font-size: 1.3rem; }
        .sub-subtitle { font-size: 0.75rem; margin-bottom: 16px; }
        
        /* Deconstruct entry form rows layout splits into full-width stacks */
        .search-block { padding: 16px; margin-bottom: 16px; }
        .search-row { flex-direction: column; gap: 12px; align-items: stretch; width: 100%; }
        .search-input { width: 100%; height: 44px; font-size: 0.95rem; }
        .verify-btn { width: 100%; height: 44px; font-size: 0.9rem; }
        
        /* Results blocks grid spacing configurations */
        .result-block { padding: 16px; }
        .detail-row { padding: 10px 0; font-size: 0.8rem; gap: 12px; }
        .label-text { font-size: 10px; }
    }
</style>

<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
<?php include_once 'navbar.php'; ?>



    <main>
        <h1 class="main-title">Payment Reference Lookup</h1>
        <p class="sub-subtitle">Check M-Pesa, wallet and Pole Pole payment records stored by the shop</p>
        
        <div class="search-block">
            <form method="GET" class="search-row" id="checker-form">
                <input type="text" name="txn_code" value="<?= htmlspecialchars($search_code); ?>" placeholder="Enter payment reference... e.g. TXN_6A64D487C5C15" class="search-input" minlength="4" maxlength="100" pattern="[A-Za-z0-9_-]+" required autocomplete="off">
                <button type="submit" class="verify-btn">Search records</button>
            </form>
        </div>

        <?php if ($searched): ?>
            <?php if ($search_error): ?>
                <div class=no-result><?=htmlspecialchars($search_error)?></div>
            <?php elseif ($payment_rows): ?>
                <?php if (count($payment_rows) > 1): ?><div class="duplicate-warning" role="alert"><?= count($payment_rows); ?> ledger entries use this reference. Review every result below; this reference is not unique.</div><?php endif; ?>
                <?php foreach ($payment_rows as $payment_data): $stored_status=staffPaymentStatusClass($payment_data['payment_status']); ?>
                <div class="result-block">
                    <h3 class="result-title">Local Payment Record #<?= (int)$payment_data['id']; ?></h3>
                    
                    <div class="detail-row">
                        <span class="label-text">Recorded:</span>
                        <span style="color: #111827;"><?= htmlspecialchars($payment_data['created_at']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Payment Reference:</span>
                        <span style="font-family: monospace; font-weight: 700; color: #2563eb; text-transform: uppercase;"><?= htmlspecialchars($payment_data['transaction_code']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Payment Method:</span>
                        <span style="color: #111827; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($payment_data['payment_method']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Customer:</span>
                        <span style="color: #111827; text-transform: uppercase;"><?= htmlspecialchars($payment_data['fullname'] ?: 'Not linked'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Registered Mobile Phone:</span>
                        <span style="font-family: monospace;"><?= htmlspecialchars($payment_data['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Linked Order:</span>
                        <span style="font-weight: 700; color: #111827;"><?= !empty($payment_data['order_id']) ? '#' . (int)$payment_data['order_id'] : 'Not linked'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Recorded Amount:</span>
                        <span style="color: #059669; font-weight: 900; font-size: 15px;">KES <?= number_format($payment_data['amount'], 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label-text">Stored Status:</span>
                        <span><span class="status-pill status-<?= htmlspecialchars($stored_status); ?>"><?= htmlspecialchars(staffPaymentDisplayStatus($payment_data['payment_status'])); ?></span></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-result">
                    <!-- Legacy damaged message retained only to avoid re-encoding this file.
                    ÃƒÂ¢Ã‚ÂÃ…â€™ Verification Failed: The reference hash code "<?= htmlspecialchars($search_code); ?>" does not exist in the payments ledger logs.
                    -->
                    No stored payment record uses reference "<?= htmlspecialchars($search_code); ?>".
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <p class=local-record-note>This page checks records stored by ADONAK Electronics. It does not contact Safaricom or independently confirm M-Pesa settlement.</p>
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
<style>.local-record-note{color:#64748b;font-size:12px;line-height:1.5;margin-top:14px;padding:11px 13px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px}</style>
</body>
</html>
