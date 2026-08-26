<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../order_payment_guard.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!verifyWorkspaceClearance('invoice_archiver.php')) {
    echo "<script>window.location.href='../login.php?msg=err_unauthorized_access';</script>";
    exit;
}

function formatReceiptPhone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($digits) === 9) return '+254 ' . substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
    if (strlen($digits) === 10 && substr($digits, 0, 1) === '0') return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    if (strlen($digits) === 12 && substr($digits, 0, 3) === '254') return '+254 ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
    return $phone ?: 'Not provided';
}

function cleanPaymentReference($reference) {
    $reference = trim((string)$reference);
    return $reference !== '' && $reference !== '0' ? $reference : 'Not recorded';
}

if (empty($_SESSION['invoice_archive_csrf'])) $_SESSION['invoice_archive_csrf'] = bin2hex(random_bytes(32));
$invoice_csrf = $_SESSION['invoice_archive_csrf'];
$search_query = isset($_POST['search_invoice']) ? (int)trim((string)$_POST['search_invoice']) : 0;
$p_item = null;
$items = [];
$payments_history = [];
$search_error = '';
$document_available = false;
$document_title = $document_badge = $badge_style = $document_no = '';
$served_by = 'Not yet assigned';
$issued_by = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Administrator';
$completed_paid = $refunded_amount = $balance = 0.00;
$is_cancelled = $is_fully_paid = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_invoice'])) {
    $submitted_token = (string)($_POST['invoice_archive_csrf'] ?? '');
    if (!hash_equals($invoice_csrf, $submitted_token)) {
        $search_error = 'Your invoice lookup session expired. Please refresh and try again.';
    } elseif ($search_query <= 0) {
        $search_error = 'Enter a valid order or invoice number.';
    } else {
        $stmt = $conn->prepare("SELECT o.id,o.user_id,o.total_amount,o.net_amount,o.vat_amount,o.applied_tax_rate,o.order_status,o.created_at,o.kra_pin,o.processed_by,u.fullname,u.email,u.phone FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=? LIMIT 1");
        if (!$stmt) {
            $search_error = 'The order lookup could not be prepared.';
        } else {
            $stmt->bind_param('i', $search_query);
            if ($stmt->execute()) $p_item = $stmt->get_result()->fetch_assoc();
            else $search_error = 'The order lookup could not be completed.';
            $stmt->close();
        }

        if (!$search_error && !$p_item) {
            $search_error = 'No order was found with that reference number.';
        } elseif (!$search_error) {
            $settlement = getOrderSettlementState($conn, $search_query);
            if (!$settlement) {
                $search_error = 'The payment settlement record could not be loaded.';
            } else {
                $is_cancelled = strtolower(trim((string)$p_item['order_status'])) === 'cancelled';
                $is_fully_paid = !$is_cancelled && !empty($settlement['is_fully_paid']);
                $completed_paid = (float)$settlement['paid_total'];
                $balance = $is_cancelled ? 0.00 : (float)$settlement['outstanding_balance'];

                if ($is_cancelled) {
                    $document_title = 'Cancelled Order Record';
                    $document_badge = 'Cancelled / Reversed';
                    $badge_style = 'background:#f3e8ff;color:#6d28d9;';
                    $document_prefix = 'VOID';
                } elseif ($is_fully_paid) {
                    $document_title = 'Paid Sales Receipt';
                    $document_badge = 'Fully Paid in Store Records';
                    $badge_style = 'background:#dcfce7;color:#15803d;';
                    $document_prefix = 'PAID';
                } else {
                    $document_title = !empty($settlement['is_layaway']) ? 'Provisional Pole Pole Invoice' : 'Provisional Order Invoice';
                    $document_badge = 'Outstanding KES ' . number_format($balance, 2);
                    $badge_style = 'background:#fef3c7;color:#92400e;';
                    $document_prefix = 'PROV';
                }

                $served_by_raw = trim((string)($p_item['processed_by'] ?? ''));
                $served_by = $served_by_raw !== '' ? $served_by_raw : 'Not yet assigned';
                $document_no = $document_prefix . '-' . date('Ymd', strtotime($p_item['created_at'])) . '-' . str_pad((string)$p_item['id'], 6, '0', STR_PAD_LEFT);

                $payment_stmt = $conn->prepare('SELECT payment_method,transaction_code,amount,payment_status,created_at FROM payments WHERE order_id=? ORDER BY id ASC');
                if (!$payment_stmt) {
                    $search_error = 'The payment history could not be prepared.';
                } else {
                    $payment_stmt->bind_param('i', $search_query);
                    if ($payment_stmt->execute()) {
                        $payments_history = $payment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        foreach ($payments_history as $payment_row) {
                            if (strtolower(trim((string)$payment_row['payment_status'])) === 'refunded') $refunded_amount += (float)$payment_row['amount'];
                        }
                    } else $search_error = 'The payment history could not be loaded.';
                    $payment_stmt->close();
                }

                if (!$search_error) {
                    $items_stmt = $conn->prepare("SELECT oi.quantity,oi.price,COALESCE(p.product_name,'Unavailable product') product_name FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
                    if (!$items_stmt) {
                        $search_error = 'The invoice items could not be prepared.';
                    } else {
                        $items_stmt->bind_param('i', $search_query);
                        if ($items_stmt->execute()) $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        else $search_error = 'The invoice items could not be loaded.';
                        $items_stmt->close();
                    }
                }

                if (!$search_error) {
                    $document_available = true;
                    if (PHP_SAPI !== 'cli') {
                        $uid = (int)($_SESSION['user_id'] ?? 0);
                        $details = $document_title . " generated for order #{$search_query}; issued by {$issued_by}.";
                        $log = $conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES (?,?,'Financial Update',?)");
                        if ($log) {
                            $log->bind_param('iss', $uid, $issued_by, $details);
                            $log->execute();
                            $log->close();
                        }
                    }
                    $_SESSION['invoice_archive_csrf'] = bin2hex(random_bytes(32));
                    $invoice_csrf = $_SESSION['invoice_archive_csrf'];
                }
            }
        }
    }
}
?>
<style>
.receipt-center{width:100%;max-width:100%;overflow-x:hidden;padding-bottom:45px;box-sizing:border-box;font-family:Inter,Segoe UI,Arial,sans-serif;color:#172033}.receipt-hero{padding:25px;border:1px solid #dbe5ef;border-radius:16px;background:linear-gradient(135deg,#fff,#f4f8ff)}.receipt-hero h2{margin:0 0 7px;font-size:25px}.receipt-hero p{margin:0;color:#64748b}.receipt-search{display:grid;grid-template-columns:auto minmax(180px,280px) auto auto;gap:10px;align-items:center;padding:12px 16px;margin:16px 0;border:1px solid #dbe5ef;border-radius:13px;background:#fff}.receipt-search label{font-size:11px;font-weight:900;text-transform:uppercase;color:#475569}.receipt-input{padding:11px 13px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}.receipt-btn{padding:11px 15px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer;text-decoration:none;text-align:center}.receipt-btn.secondary{background:#fff;color:#475569;border:1px solid #cbd5e1}.receipt-btn.print{background:#4f46e5}.receipt-preview-head{display:flex;justify-content:space-between;gap:12px;align-items:center;max-width:930px;margin:20px auto 12px}.receipt-preview-head h3{margin:0;font-size:13px;text-transform:uppercase}.receipt-actions{display:flex;gap:8px}.receipt-empty,.receipt-error{padding:34px;border:1px dashed #cbd5e1;border-radius:13px;text-align:center;background:#f8fafc;color:#64748b}.receipt-error{border-color:#fecaca;background:#fff1f2;color:#b91c1c}.receipt-paper{max-width:930px;margin:20px auto 0;padding:28px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.07)}.receipt-brand{display:flex;justify-content:space-between;gap:20px;padding-bottom:14px;border-bottom:2px solid #172033}.receipt-brand h3{margin:0 0 4px}.receipt-brand p{margin:0;color:#64748b;font-size:12px}.verified{align-self:flex-start;padding:6px 10px;border-radius:99px;font-size:11px;font-weight:900;text-transform:uppercase}.receipt-info{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin:18px 0}.receipt-info-card{padding:14px;border-radius:10px;background:#eff6ff;color:#334155;font-size:12px;line-height:1.7}.receipt-info-card.payment{background:#ecfdf5}.receipt-table-wrap{overflow:auto}.invoice-table{width:100%;min-width:650px;border-collapse:collapse;margin:18px 0;font-size:13px}.invoice-table th{padding:11px;background:#f8fafc;color:#475569;text-align:left;text-transform:uppercase;font-size:10px}.invoice-table td{padding:12px;border-bottom:1px solid #edf1f5;vertical-align:top}.receipt-section-title{margin:22px 0 0;font-size:12px;text-transform:uppercase;color:#334155}.receipt-totals{margin-left:auto;width:min(360px,100%);font-size:13px}.receipt-total-row{display:flex;justify-content:space-between;padding:7px 9px}.receipt-total-row.vat{background:#fff7ed;color:#c2410c;border-radius:7px}.receipt-total-row.grand{margin-top:5px;padding-top:11px;border-top:2px solid #dbe5ef;color:#1d4ed8;font-size:16px;font-weight:900}.receipt-footer{margin-top:22px;padding-top:13px;border-top:1px dashed #cbd5e1;color:#64748b;font-size:10px;text-align:center}
@media(max-width:760px){.receipt-search{grid-template-columns:1fr}.receipt-info{grid-template-columns:1fr}.receipt-preview-head{align-items:stretch;flex-direction:column}.receipt-actions .receipt-btn{flex:1}.receipt-paper{padding:17px;margin-bottom:25px}.receipt-brand{flex-direction:column}}
@media print{.sidebar,.navbar,#invoice-search-form,.receipt-hero,.receipt-preview-head,.receipt-empty,.receipt-error{display:none!important}body,.main-content,#dynamic-workspace,.card{margin:0!important;padding:0!important;width:100%!important;background:#fff!important;border:0!important;box-shadow:none!important}.receipt-paper{max-width:none;margin:0!important;padding:25px!important;border:1px solid #000!important;box-shadow:none!important}.invoice-table{display:table!important;min-width:0!important}}
</style>
<section class="receipt-center">
    <div class="receipt-hero"><h2>Invoice &amp; Payment Record Lookup</h2><p>Prepare a paid receipt, provisional Pole Pole invoice, or cancelled-order record from the stored order and payment ledger.</p></div>
    <form id="invoice-search-form" class="receipt-search" method="POST" action="invoice_archiver.php">
        <input type="hidden" name="invoice_archive_csrf" value="<?=htmlspecialchars($invoice_csrf)?>">
        <label for="invoice-reference">Order or invoice number</label>
        <input id="invoice-reference" class="receipt-input" type="number" min="1" name="search_invoice" value="<?=$search_query ?: ''?>" placeholder="e.g. 101" required>
        <button class="receipt-btn" type="submit">Find invoice</button>
        <?php if ($search_query): ?><a class="receipt-btn secondary ajax-link" data-target="invoice_archiver.php" href="invoice_archiver.php">Clear</a><?php endif; ?>
    </form>
    <div class="receipt-preview-head"><h3>Invoice preview</h3><?php if ($document_available): ?><div class="receipt-actions"><button class="receipt-btn print" type="button" onclick="window.print()">Print / Save PDF</button></div><?php endif; ?></div>
    <?php if ($search_error): ?>
        <div class="receipt-error"><?=htmlspecialchars($search_error)?></div>
    <?php elseif (!$p_item): ?>
        <div class="receipt-empty"><strong>No invoice selected</strong><br>Enter an order number to view its items, payment history, and current balance.</div>
    <?php elseif ($document_available):
        $gross = (float)$p_item['total_amount'];
        $net = (float)$p_item['net_amount'];
        $vat = (float)$p_item['vat_amount'];
        $rate = (float)$p_item['applied_tax_rate'];
    ?>
    <article class="receipt-paper">
        <div class="receipt-brand">
            <div><h3>ADONAK ELECTRONICS &mdash; <?=htmlspecialchars(strtoupper($document_title))?></h3><p>Document <?=htmlspecialchars($document_no)?> &middot; Generated <?=date('d M Y, h:i A')?></p></div>
            <span class="verified" style="<?=$badge_style?>"><?=htmlspecialchars($document_badge)?></span>
        </div>
        <div class="receipt-info">
            <div class="receipt-info-card"><strong>Order / Invoice:</strong> #<?=(int)$p_item['id']?><br><strong>Customer:</strong> <?=htmlspecialchars(ucwords(strtolower($p_item['fullname'] ?? 'Customer'))) ?><br><strong>Email:</strong> <?=htmlspecialchars($p_item['email'] ?? 'Not provided')?><br><strong>Phone:</strong> <?=htmlspecialchars(formatReceiptPhone($p_item['phone'] ?? ''))?></div>
            <div class="receipt-info-card payment"><strong>Order date:</strong> <?=date('d M Y, h:i A', strtotime($p_item['created_at']))?><br><strong>Order status:</strong> <?=htmlspecialchars(ucfirst((string)$p_item['order_status']))?><br><strong>Served By:</strong> <?=htmlspecialchars($served_by)?><br><strong>Document Issued By:</strong> <?=htmlspecialchars($issued_by)?><br><strong>KRA PIN:</strong> <?=htmlspecialchars($p_item['kra_pin'] ?: 'Not provided')?></div>
        </div>
        <h4 class="receipt-section-title">Purchased Items</h4>
        <div class="receipt-table-wrap"><table class="invoice-table"><thead><tr><th>Purchased item</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit price</th><th style="text-align:right">Total</th></tr></thead><tbody>
            <?php if ($items): foreach ($items as $item): ?><tr><td><strong><?=htmlspecialchars($item['product_name'])?></strong></td><td style="text-align:center"><?=(int)$item['quantity']?></td><td style="text-align:right">KES <?=number_format((float)$item['price'], 2)?></td><td style="text-align:right"><strong>KES <?=number_format((float)$item['price'] * (int)$item['quantity'], 2)?></strong></td></tr><?php endforeach; else: ?><tr><td colspan="4">No item details available.</td></tr><?php endif; ?>
        </tbody></table></div>
        <h4 class="receipt-section-title">Pole Pole / Payment History (as recorded)</h4>
        <div class="receipt-table-wrap"><table class="invoice-table"><thead><tr><th>Date paid / recorded</th><th>Payment method</th><th>Reference</th><th style="text-align:right">Amount</th><th style="text-align:right">Stored status</th></tr></thead><tbody>
            <?php if ($payments_history): foreach ($payments_history as $payment):
                $payment_status = strtolower(trim((string)$payment['payment_status']));
                $status_color = $payment_status === 'completed' ? '#047857' : ($payment_status === 'refunded' ? '#6d28d9' : ($payment_status === 'failed' ? '#b91c1c' : '#b45309'));
            ?><tr><td><?=htmlspecialchars(date('d M Y, h:i A', strtotime($payment['created_at'])))?></td><td><?=htmlspecialchars($payment['payment_method'] ?: 'Not recorded')?></td><td><?=htmlspecialchars(cleanPaymentReference($payment['transaction_code']))?></td><td style="text-align:right"><strong>KES <?=number_format((float)$payment['amount'], 2)?></strong></td><td style="text-align:right;color:<?=$status_color?>;font-weight:800"><?=htmlspecialchars(ucfirst($payment_status ?: 'unknown'))?></td></tr><?php endforeach; else: ?><tr><td colspan="5">No payment entries have been recorded for this order.</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="receipt-totals">
            <div class="receipt-total-row"><span>Net cost</span><strong>KES <?=number_format($net, 2)?></strong></div>
            <div class="receipt-total-row vat"><span>VAT (<?=number_format($rate, 2)?>%)</span><strong>KES <?=number_format($vat, 2)?></strong></div>
            <div class="receipt-total-row grand"><span>Order total</span><span>KES <?=number_format($gross, 2)?></span></div>
            <div class="receipt-total-row"><span>Completed payments</span><strong style="color:#047857">KES <?=number_format($completed_paid, 2)?></strong></div>
            <?php if ($refunded_amount > 0.009): ?><div class="receipt-total-row"><span>Refunded payments</span><strong style="color:#6d28d9">KES <?=number_format($refunded_amount, 2)?></strong></div><?php endif; ?>
            <?php if (!$is_cancelled): ?><div class="receipt-total-row"><span>Outstanding balance</span><strong style="color:<?=$balance > 0.009 ? '#b91c1c' : '#047857'?>">KES <?=number_format($balance, 2)?></strong></div><?php endif; ?>
        </div>
        <div class="receipt-footer">
            <?php if ($is_cancelled): ?>This historical record shows the cancelled order and recorded payment reversals.<?php elseif ($is_fully_paid): ?>This paid sales receipt reflects completed payments in the ADONAK store ledger.<?php else: ?>This provisional invoice records instalments made so far and is not proof of full payment.<?php endif; ?><br>
            Payment methods, references, amounts, dates, and statuses are shown exactly from the stored payment ledger. This document does not claim independent KRA or payment-network verification.<br>
            Served by <?=htmlspecialchars($served_by)?>; document issued by <?=htmlspecialchars($issued_by)?>.
        </div>
    </article>
    <?php endif; ?>
</section>
