<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if(session_status()===PHP_SESSION_NONE){session_start();}
$db_path=dirname(__FILE__).'/../db.php';
include_once file_exists($db_path)?$db_path:'../db.php';
require_once dirname(__FILE__) . '/../order_payment_guard.php';

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging raw text error outputs
if (!verifyWorkspaceClearance('manage_orders.php')) {
    if (!empty($is_ajax)) {
        http_response_code(403);
        echo 'AUTH_ERROR';
    } else {
        header('Location: ../login.php?msg=err_unauthorized_access');
    }
    exit;
}
if (empty($_SESSION['admin_orders_csrf'])) {
    $_SESSION['admin_orders_csrf'] = bin2hex(random_bytes(32));
}
$admin_orders_csrf = $_SESSION['admin_orders_csrf'];


$msg=$err="";
if($_SERVER['REQUEST_METHOD']==='POST'){
    if (isset($_POST['update_status_action'], $_POST['order_id'])) {
        $oid = intval($_POST['order_id']);
        $nst = strtolower(trim($_POST['order_status'] ?? ''));
        $allowed_statuses = ['pending', 'processing', 'delivered'];

        if (empty($_POST['csrf_token']) || !hash_equals($admin_orders_csrf, (string)$_POST['csrf_token'])) {
            $err = 'This page expired. Refresh it and try again.';
        } elseif ($oid <= 0 || !in_array($nst, $allowed_statuses, true)) {
            $err = "Invalid order status selected.";
        } else {
            $conn->begin_transaction();
            try {
                $settlement = getOrderSettlementState($conn, $oid, true);
                if (!$settlement) throw new Exception("Order #{$oid} was not found.");
                if (in_array($settlement['order_status'], ['delivered', 'cancelled'], true)) {
                    throw new Exception("Order #{$oid} is already finalized and cannot be changed.");
                }
                if ($nst === 'delivered' && !$settlement['is_fully_paid']) {
                    $planNote = $settlement['is_layaway'] ? " Lipa Pole Pole balance: KES " . number_format($settlement['layaway_balance'], 2) . "." : "";
                    throw new Exception(
                        "Delivery blocked: Order #{$oid} has not been fully paid. Paid KES " .
                        number_format($settlement['paid_total'], 2) . " of KES " .
                        number_format($settlement['total_amount'], 2) . ". Outstanding: KES " .
                        number_format($settlement['outstanding_balance'], 2) . "." . $planNote
                    );
                }

                $st = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                $st->bind_param("si", $nst, $oid);
                if (!$st->execute()) throw new Exception("Unable to update the order status.");
                $st->close();

                $log_details = "Order Logistics Modified: Changed Order #{$oid} status to '{$nst}'. Settlement verified before delivery.";
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                if (!$log_stmt) throw new Exception('Unable to prepare the mandatory audit record.');
                $operatorId = (int)($_SESSION['user_id'] ?? 0);
                $operatorName = $_SESSION['fullname'] ?? 'Administrator';
                $log_stmt->bind_param('iss', $operatorId, $operatorName, $log_details);
                if (!$log_stmt->execute()) throw new Exception('Unable to save the mandatory audit record.');
                $log_stmt->close();

                $conn->commit();
                $admin_orders_csrf = $_SESSION['admin_orders_csrf'] = bin2hex(random_bytes(32));
                $msg = "Order logistics updated.";
            } catch (Exception $e) {
                $conn->rollback();
                $err = $e->getMessage();
            }
        }
    }
    if (isset($_POST['cancel_refund_action'], $_POST['order_id'])) {
        $oid = intval($_POST['order_id']);
        $conn->begin_transaction();
        try {
            if (empty($_POST['csrf_token']) || !hash_equals($admin_orders_csrf, (string)$_POST['csrf_token'])) {
            $err = 'This page expired. Refresh it and try again.';
            throw new Exception($err);
            }
        if ($oid <= 0) throw new Exception('Select a valid order.');


            $order_stmt = $conn->prepare("SELECT user_id, order_status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
            $order_stmt->bind_param("i", $oid);
            if (!$order_stmt->execute()) throw new Exception('Unable to load the order.');
            $order = $order_stmt->get_result()->fetch_assoc();
            $order_stmt->close();

            if (!$order) {
                throw new Exception("Order #{$oid} was not found.");
            }

            $current_status = strtolower(trim($order['order_status']));
            if (in_array($current_status, ['cancelled', 'delivered'], true)) {
                throw new Exception("Action rejected: Order is already delivered or cancelled.");
            }

            $settlement = getOrderSettlementState($conn, $oid, false);
            if (!$settlement) {
                throw new Exception("Unable to determine the order settlement.");
            }

            $cid = (int)$order['user_id'];
            $refund_amount = max(0, round((float)$settlement['paid_total'], 2));

            $cancel_stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
            $cancel_stmt->bind_param("i", $oid);
            if (!$cancel_stmt->execute()) throw new Exception('Unable to cancel the order.');
            $cancel_stmt->close();

            if ($refund_amount > 0) {
                $wallet_stmt = $conn->prepare("SELECT id FROM customer_wallets WHERE user_id = ? LIMIT 1 FOR UPDATE");
                $wallet_stmt->bind_param("i", $cid);
                if (!$wallet_stmt->execute()) throw new Exception('Unable to load the customer wallet.');
                $wallet_exists = $wallet_stmt->get_result()->fetch_assoc();
                $wallet_stmt->close();

                if ($wallet_exists) {
                    $credit_stmt = $conn->prepare("UPDATE customer_wallets SET available_balance = available_balance + ?, updated_at = NOW() WHERE user_id = ?");
                    $credit_stmt->bind_param("di", $refund_amount, $cid);
                } else {
                    $credit_stmt = $conn->prepare("INSERT INTO customer_wallets (user_id, available_balance, updated_at) VALUES (?, ?, NOW())");
                    $credit_stmt->bind_param("id", $cid, $refund_amount);
                }
                if (!$credit_stmt->execute()) throw new Exception('Unable to credit the customer wallet.');
                $credit_stmt->close();

                $refund_payments = $conn->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE order_id = ? AND LOWER(TRIM(payment_status)) = 'completed'");
                $refund_payments->bind_param("i", $oid);
                if (!$refund_payments->execute()) throw new Exception('Unable to reconcile completed payments.');
                $refund_payments->close();
            }

            $cancel_plan = $conn->prepare("UPDATE layaway_plans SET status = 'Cancelled' WHERE order_id = ? AND LOWER(TRIM(status)) IN ('active', 'completed', 'fully paid')");
            $cancel_plan->bind_param("i", $oid);
            if (!$cancel_plan->execute()) throw new Exception('Unable to cancel the linked installment plan.');
            $cancel_plan->close();

            $items_stmt = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            if (!$items_stmt) throw new Exception('Unable to prepare ordered stock lookup.');
            $items_stmt->bind_param('i', $oid);
            if (!$items_stmt->execute()) throw new Exception('Unable to load ordered stock.');
            $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $items_stmt->close();

            $restore_stmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
            if (!$restore_stmt) throw new Exception('Unable to prepare inventory restoration.');
            foreach ($items as $item) {
                $quantity = (int)$item['quantity'];
                $product_id = (int)$item['product_id'];
                $restore_stmt->bind_param('ii', $quantity, $product_id);
                if (!$restore_stmt->execute()) throw new Exception('Unable to restore ordered inventory.');
            }
            $restore_stmt->close();

            $operatorId = (int)($_SESSION['user_id'] ?? 0);
            $operatorName = $_SESSION['fullname'] ?? 'Administrator';
            $refund_log = $conn->prepare('INSERT INTO refund_logs (order_id, user_id, refund_amount, processed_by, created_at) VALUES (?, ?, ?, ?, NOW())');
            if (!$refund_log) throw new Exception('Unable to prepare refund history.');
            $refund_log->bind_param('iids', $oid, $cid, $refund_amount, $operatorName);
            if (!$refund_log->execute()) throw new Exception('Unable to save refund history.');
            $refund_log->close();

            $operatorName = $_SESSION['fullname'] ?? 'Administrator';
            $log_details = "Order Cancelled & Refunded: Order #{$oid} cancelled. KES " . number_format($refund_amount, 2) . " of verified completed payments credited to User ID #{$cid}.";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Financial Update', ?)");
            if (!$log_stmt) throw new Exception('Unable to prepare the mandatory audit record.');
            $log_stmt->bind_param('iss', $operatorId, $operatorName, $log_details);
            if (!$log_stmt->execute()) throw new Exception('Unable to save the mandatory audit record.');
            $log_stmt->close();

            $conn->commit();
            $admin_orders_csrf = $_SESSION['admin_orders_csrf'] = bin2hex(random_bytes(32));
            $msg = $refund_amount > 0
                ? "Order cancelled. KES " . number_format($refund_amount, 2) . " refunded to the customer wallet."
                : "Order cancelled. No completed payment was available to refund.";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Refund error: " . $e->getMessage();
        }
    }
}
$res=$conn->query("SELECT o.id,u.fullname,o.total_amount,o.order_status FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.id DESC");
?>

<?php if(!empty($err)):?><div style="background:#fee2e2;color:#ef4444;padding:12px;border-radius:8px;margin-bottom:25px;font-family:sans-serif;">⚠️ <?=$err?></div><?php endif;?>
<?php if(!empty($msg)):?><div style="background:#e6fcf5;color:#0ca678;padding:15px;border-radius:8px;font-size:14px;font-family:sans-serif;margin-bottom:25px;border-left:4px solid #28a745;font-weight:bold;">✓ <?=$msg?></div><?php endif;?>
<div class="card sales-orders-card" style="background:white;padding:30px;border-radius:12px;border:1px solid #e2e8f0;font-family:sans-serif;width:100%;box-sizing:border-box;">
    <h2 style="margin-top:0;color:#0f172a;">📊 Sales Orders Processing Desk</h2>
    <p style="color:#64748b;font-size:14px;margin-top:0;margin-bottom:25px;">Track delivery logistics chains, manage fulfillment operations states, and invoke real-time automatic user wallet reversals.</p>
   <div class="table-scroll-container">
    <table class="sales-orders-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:bold;text-transform:uppercase;font-size:11px;letter-spacing:0.5px;">
                <th style="padding:14px 12px;">Order Code</th><th style="padding:14px 12px;">Customer Reference</th><th style="padding:14px 12px;">Expected Total Cost</th><th style="padding:14px 12px;">Paid / Outstanding</th><th style="padding:14px 12px;">State</th><th style="padding:14px 12px;text-align:center;">Action Control</th><th style="padding:14px 12px;text-align:center;">Manual Wallet Refund Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($res&&$res->num_rows>0):while($r=$res->fetch_assoc()):
                $st = strtoupper(trim($r['order_status']));
                $is_d = ($st === 'DELIVERED');
                $is_c = ($st === 'CANCELLED');
                $settlement_view = getOrderSettlementState($conn, (int)$r['id']);
                $can_deliver = $settlement_view && $settlement_view['is_fully_paid'];
                $outstanding_view = $settlement_view['outstanding_balance'] ?? (float)$r['total_amount'];
                $refund_available = max(0, (float)($settlement_view['paid_total'] ?? 0));
                $paid_view = max(0, (float)($settlement_view['paid_total'] ?? 0));
                $expected_total = max(0, (float)$r['total_amount']);
                $paid_percent = $expected_total > 0 ? min(100, round(($paid_view / $expected_total) * 100, 1)) : 0;
            ?>
                <tr style="border-bottom:1px solid #f1f5f9;color:#334155;">
                    <td style="padding:14px 12px;font-weight:bold;color:#3b82f6;">#<?=$r['id']?></td>
                    <td style="padding:14px 12px;text-transform:capitalize;"><?=htmlspecialchars($r['fullname'])?></td>
                                        <td style="padding:14px 12px;font-weight:700;color:#0f172a;white-space:nowrap;">KES <?=number_format($expected_total,2)?></td>
                    <td style="padding:12px;min-width:175px;">
                        <?php if ($is_c): ?>
                            <div style="font-size:11px;font-weight:800;color:#64748b;">Payment reversed</div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:3px;">Order cancelled and eligible payments refunded</div>
                        <?php else: ?>
                            <div style="display:flex;justify-content:space-between;gap:10px;font-size:11px;margin-bottom:6px;">
                                <span style="color:#16a34a;font-weight:800;">Paid KES <?=number_format($paid_view,2)?></span>
                                <span style="color:<?=$outstanding_view > 0.009 ? '#ef4444' : '#16a34a'?>;font-weight:800;">
                                    <?=$outstanding_view > 0.009 ? 'Due KES '.number_format($outstanding_view,2) : 'Fully paid'?>
                                </span>
                            </div>
                            <div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;" aria-label="<?=number_format($paid_percent,1)?> percent paid">
                                <div style="height:100%;width:<?=$paid_percent?>%;background:<?=$paid_percent >= 100 ? '#16a34a' : '#3b82f6'?>;border-radius:999px;"></div>
                            </div>
                            <div style="font-size:10px;color:#64748b;margin-top:5px;"><?=number_format($paid_percent,1)?>% of expected total paid</div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 12px;font-weight:bold;color:<?=$is_d?'#16a34a':($is_c?'#ef4444':'#eab308')?>;"><?=$st?></td>
                    <td style="padding:14px 12px;text-align:center;">
                        <?php if($is_d):?><span style="color:#16a34a;font-weight:600;font-size:12px;">✔ Order Finalized</span>
                        <?php elseif($is_c):?><span style="color:#ef4444;font-weight:600;font-size:12px;">🔒 Actions Locked</span>
                        <?php else: ?>
                            <form action="manage_orders.php" method="POST" style="margin:0;display:inline-flex;gap:6px;align-items:center;">
                                <input type="hidden" name="update_status_action" value="1"><input type="hidden" name="order_id" value="<?=$r['id']?>">
                                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($admin_orders_csrf)?>">
                                <select name="order_status" style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:12px;font-weight:600;background:#fff;outline:none;">
                                    <option value="pending" <?=$st==='PENDING'?'selected':''?>>Pending</option>
                                    <option value="processing" <?=$st==='PROCESSING'?'selected':''?>>Processing</option>
                                    <?php if ($can_deliver): ?>
                                        <option value="delivered">Delivered</option>
                                    <?php else: ?>
                                        <option value="delivered" disabled>Delivered — Outstanding KES <?=number_format($outstanding_view,2)?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="submit" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px 12px;font-size:12px;font-weight:bold;border-radius:6px;cursor:pointer;color:#475569;">Update</button>
                            </form>
                        <?php endif;?>
                    </td>
                    <td style="padding:14px 12px;text-align:center;">
                        <?php if($is_d):?><span style="color:#94a3b8;font-size:12px;font-style:italic;">✕ Non-Refundable (Delivered)</span>
                        <?php elseif($is_c):?><span style="color:#16a34a;font-weight:600;font-size:12px;">✔ Refunded to Wallet</span>
                        <?php else: ?>
                            <form action="manage_orders.php" method="POST" style="margin:0;" onsubmit="return confirm('Confirm cancellation for order #<?=$r['id']?>? Amount will be returned to wallet.');">
                                <input type="hidden" name="cancel_refund_action" value="1"><input type="hidden" name="order_id" value="<?=$r['id']?>">
                                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($admin_orders_csrf)?>">
                                <button type="submit" style="background:#ef4444;color:white;border:none;padding:7px 14px;font-size:12px;font-weight:bold;border-radius:6px;cursor:pointer;"><?= $refund_available > 0
    ? '🛑 Cancel & Refund (KES ' . number_format($refund_available, 2) . ')'
    : '🛑 Cancel Order (No Payment)' ?></button>
                            </form>
                        <?php endif;?>
                    </td>
                </tr>
            <?php endwhile;endif;?>
        </tbody>
    </table></div>
</div>
