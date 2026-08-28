<?php
// Shared settlement source of truth. Delivery, refunds, and statements must use this
// calculation instead of trusting the order status label alone.
if (!function_exists('getOrderSettlementState')) {
    function getOrderSettlementState(mysqli $conn, int $orderId, bool $lockOrder = false): ?array
    {
        $orderSql = "SELECT total_amount, order_status FROM orders WHERE id = ? LIMIT 1" . ($lockOrder ? " FOR UPDATE" : "");
        $orderStmt = $conn->prepare($orderSql);
        if (!$orderStmt) return null;
        $orderStmt->bind_param("i", $orderId);
        if (!$orderStmt->execute()) {
            $orderStmt->close();
            return null;
        }
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        if (!$order) return null;

        // When a caller requests a lock, lock the underlying payment rows as well.
        // This prevents a concurrent payment/refund from changing settlement while
        // fulfillment or cancellation is being finalized.
        $paidTotal = 0.00;
        $paymentSql = "SELECT id, amount, payment_status FROM payments WHERE order_id = ?" . ($lockOrder ? " FOR UPDATE" : "");
        $paymentStmt = $conn->prepare($paymentSql);
        if (!$paymentStmt) return null;
        $paymentStmt->bind_param("i", $orderId);
        if (!$paymentStmt->execute()) {
            $paymentStmt->close();
            return null;
        }
        $paymentResult = $paymentStmt->get_result();
        while ($paymentRow = $paymentResult->fetch_assoc()) {
            if (strtolower(trim((string)$paymentRow['payment_status'])) === 'completed') {
                $paidTotal += (float)$paymentRow['amount'];
            }
        }
        $paymentStmt->close();
        $paidTotal = round($paidTotal, 2);

        $planSql = "SELECT balance_remaining, status FROM layaway_plans WHERE order_id = ? ORDER BY id DESC LIMIT 1" . ($lockOrder ? " FOR UPDATE" : "");
        $planStmt = $conn->prepare($planSql);
        if (!$planStmt) return null;
        $planStmt->bind_param("i", $orderId);
        if (!$planStmt->execute()) {
            $planStmt->close();
            return null;
        }
        $plan = $planStmt->get_result()->fetch_assoc();
        $planStmt->close();

        $totalAmount = round((float)$order['total_amount'], 2);
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
