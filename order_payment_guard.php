<?php
// Shared settlement source of truth. Delivery, refunds, and statements must use this
// calculation instead of trusting the order status label alone.
if (!function_exists('getOrderSettlementState')) {
    function getOrderSettlementState(mysqli $conn, int $orderId, bool $lockOrder = false): ?array
    {
        $orderSql = "SELECT total_amount, order_status FROM orders WHERE id = ? LIMIT 1" . ($lockOrder ? " FOR UPDATE" : "");
        $orderStmt = $conn->prepare($orderSql);
        $orderStmt->bind_param("i", $orderId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if (!$order) return null;

        $paymentStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0.00) AS paid_total FROM payments WHERE order_id = ? AND LOWER(TRIM(payment_status)) = 'completed'");
        $paymentStmt->bind_param("i", $orderId);
        $paymentStmt->execute();
        $payment = $paymentStmt->get_result()->fetch_assoc();
        $paymentStmt->close();

        $planStmt = $conn->prepare("SELECT balance_remaining, status FROM layaway_plans WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $planStmt->bind_param("i", $orderId);
        $planStmt->execute();
        $plan = $planStmt->get_result()->fetch_assoc();
        $planStmt->close();

        $totalAmount = round((float)$order['total_amount'], 2);
        $paidTotal = round((float)($payment['paid_total'] ?? 0), 2);
        $paymentOutstanding = max(0, round($totalAmount - $paidTotal, 2));
        $layawayOutstanding = $plan ? max(0, round((float)$plan['balance_remaining'], 2)) : 0.00;
        $outstandingBalance = max($paymentOutstanding, $layawayOutstanding);

        return [
            'total_amount' => $totalAmount,
            'paid_total' => $paidTotal,
            'outstanding_balance' => $outstandingBalance,
            'is_fully_paid' => $outstandingBalance <= 0.009,
            'order_status' => strtolower(trim((string)$order['order_status'])),
            'is_layaway' => $plan !== null,
            'layaway_status' => $plan['status'] ?? null,
            'layaway_balance' => $layawayOutstanding
        ];
    }
}