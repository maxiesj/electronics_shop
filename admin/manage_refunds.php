<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('manage_wallets.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; 
$error = "";

/* --- SECTION 1: BACKEND POST DATA PROCESSING --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Explicit tracking check matches hidden element tags
    if (isset($_POST['resolve_funds'])) {
        $payment_id = intval($_POST['payment_id']);
        $order_id = intval($_POST['order_id']);
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $action = trim($_POST['resolution_action']);
        $ref = !empty($_POST['reversal_ref']) ? strtoupper(trim($_POST['reversal_ref'])) : "CREDIT_" . time();

        $conn->begin_transaction();
        try {
            // 1. Log the transaction into refund history files
            $log = $conn->prepare("INSERT INTO refund_logs (order_id, payment_id, amount_processed, resolution_type, reversal_reference) VALUES (?, ?, ?, ?, ?)");
            $log->bind_param("iidss", $order_id, $payment_id, $amount, $action, $ref);
            $log->execute();
            $log->close();

            // 2. Route funds based on the action selected
            if ($action === 'Converted to Credit') {
                // Check if user has a wallet profile initialized
                $wallet_check = $conn->query("SELECT id FROM customer_wallets WHERE user_id = $user_id");
                if ($wallet_check->num_rows == 0) {
                    $conn->query("INSERT INTO customer_wallets (user_id, available_balance) VALUES ($user_id, 0.00)");
                }
                $up_wallet = $conn->prepare("UPDATE customer_wallets SET available_balance = available_balance + ? WHERE user_id = ?");
                $up_wallet->bind_param("di", $amount, $user_id);
                $up_wallet->execute();
                $up_wallet->close();
            }

            // 3. Mark the payment row status as Refunded so it locks out from recycling loops
            $up_pay = $conn->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE id = ?");
            $up_pay->bind_param("i", $payment_id);
            $up_pay->execute();
            $up_pay->close();

            // Log the financial ledger settlement directly into your staff logs trail
            $log_details = "Cancelled Funds Settled: Resolved Payment ID #{$payment_id} via action '{$action}' for amount $" . number_format($amount, 2) . ".";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Financial Update', ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $conn->commit();
            
            // Set message flags safely based on active navigation medium
            if (isset($_POST['ajax_request'])) {
                $_GET['msg_str'] = "Ledger updated. Funds successfully handled via: " . $action;
            } else {
                // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside workspace layouts
                echo "<script>window.location.href = 'dashboard.php?view=manage_wallets.php&msg=success';</script>";
                exit();
            }
        } catch (Exception $e) { 
            $conn->rollback(); 
            $error = "Failed to process funds resolution: " . $e->getMessage(); 
        }
    }
}

/* --- SECTION 2: FETCH RECORDS FOR DISPLAY --- */
$sql = "SELECT p.id AS pay_id, p.amount, p.transaction_code, p.payment_method, o.id AS ord_id, o.user_id, u.fullname 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN users u ON o.user_id = u.id 
        WHERE LOWER(TRIM(o.order_status)) = 'cancelled' AND p.payment_status != 'Refunded'";
$result = $conn->query($sql);

// Only render HTML content if script environment matching rules pass clean layout gates
if (basename($_SERVER['PHP_SELF']) === 'manage_refunds.php' || isset($load_view_component) || isset($_POST['ajax_request'])):
?>


<!-- Component Alert Notifications -->
<?php if(!empty($error)): ?>
    <div style="background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:15px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if(!empty($msg)): ?>
    <div style="background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;">✓ <?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if(isset($_GET['msg_str'])): ?>
    <div style="background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;">✓ <?php echo htmlspecialchars($_GET['msg_str']); ?></div>
<?php endif; ?>

<!-- Isolated Table Workspace Component Panel -->
<div class="card" style="background: white; padding: 25px; border-radius: 8px; width:100%; box-sizing:border-box;">
    <h2 style="margin-top:0;">💰 Stranded Payments Resolution Manager</h2>
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding:12px; text-align:left; border-bottom:1px solid #eaeaea;">Order ID</th>
                <th style="padding:12px; text-align:left; border-bottom:1px solid #eaeaea;">Customer</th>
                <th style="padding:12px; text-align:left; border-bottom:1px solid #eaeaea;">Paid Amount</th>
                <th style="padding:12px; text-align:left; border-bottom:1px solid #eaeaea;">M-Pesa Reference</th>
                <th style="padding:12px; text-align:left; border-bottom:1px solid #eaeaea;">Select Resolution Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="padding:12px; border-bottom:1px solid #eaeaea;">#<?php echo $row['ord_id']; ?></td>
                    <td style="padding:12px; border-bottom:1px solid #eaeaea;"><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                    <td style="padding:12px; border-bottom:1px solid #eaeaea; font-weight:bold; color:#c53929;">$<?php echo number_format($row['amount'], 2); ?></td>
                    <td style="padding:12px; border-bottom:1px solid #eaeaea;"><code><?php echo htmlspecialchars($row['transaction_code']); ?></code></td>
                    <td style="padding:12px; border-bottom:1px solid #eaeaea;">
                        
                        <!-- Form action uses '#' to force your dashboard AJAX router execution -->
                        <form method="POST" action="#" style="display:flex; gap:6px; flex-wrap:wrap; margin:0;">
                            <input type="hidden" name="payment_id" value="<?php echo $row['pay_id']; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $row['ord_id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo $row['amount']; ?>">
                            
                            <!-- CRITICAL FIXED HIDDEN PACKET FLAG FOR JS FORMDATA CAPTURING ENGINE -->
                            <input type="hidden" name="resolve_funds" value="1">

                            <select name="resolution_action" required style="padding:6px; border-radius:4px; border:1px solid #ccc;">
                                <option value="M-Pesa Reversal">M-Pesa Reversal Payout</option>
                                <option value="Converted to Credit">Convert to Store Credit</option>
                            </select>
                            
                            <input type="text" name="reversal_ref" placeholder="Payout Ref Code (e.g., SAB1C2D3E4)" style="padding:6px; border-radius:4px; border:1px solid #ccc;">
                            <button type="submit" style="background:#e67e22; color:white; border:none; font-weight:bold; cursor:pointer; padding:6px twelve_px; border-radius:4px;">Execute</button>
                        </form>
                        
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#999; padding:30px;">All payments for cancelled orders have been cleared and resolved.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
