<?php
// callback.php - Must be public (e.g., https://yourdomain.com)
header("Content-Type: application/json");
include '../db.php'; 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// 1. Capture incoming secure JSON stream from Safaricom
$callbackData = file_get_contents('php://input');
$logFile = "mpesa_responses.log";
file_put_contents($logFile, $callbackData . PHP_EOL, FILE_APPEND); // Good for debugging logs

$data = json_decode($callbackData, true);

if (!$data || !isset($data['Body']['stkCallback'])) {
    echo json_encode(["ResultCode" => 1, "ResultDesc" => "Invalid Payload Structure"]);
    exit;
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'];
$merchantRequestID = $callback['MerchantRequestID'];
$checkoutRequestID = $callback['CheckoutRequestID'];

try {
    $conn->begin_transaction();

    // 2. Identify transaction status codes (0 means user entered correct PIN and paid)
    if ($resultCode === 0) {
        $metaData = $callback['CallbackMetadata']['Item'];
        $mpesaReceiptNumber = '';
        $amountPaid = 0;

        foreach ($metaData as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') $mpesaReceiptNumber = $item['Value'];
            if ($item['Name'] === 'Amount') $amountPaid = floatval($item['Value']);
        }

        // 3. Find the holding record using the CheckoutRequestID or tracking code
        // For this to map flawlessly, save your CheckoutRequestID into your payments table during Phase 1
        $upd_p = $conn->prepare("UPDATE payments SET transaction_code = ?, payment_status = 'completed', amount = ? WHERE transaction_code = ?");
        $upd_p->bind_param("sds", $mpesaReceiptNumber, $amountPaid, $checkoutRequestID);
        $upd_p->execute();

        // 4. Update the order flag status straight to delivered
        $upd_o = $conn->prepare("UPDATE orders o JOIN payments p ON o.id = p.order_id SET o.order_status = 'delivered' WHERE p.transaction_code = ?");
        $upd_o->bind_param("s", $mpesaReceiptNumber);
        $upd_o->execute();

        $conn->commit();
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback Processed Successfully"]);
    } else {
        // Payment failed or was canceled by user
        $upd_fail = $conn->prepare("UPDATE payments SET payment_status = 'failed' WHERE transaction_code = ?");
        $upd_fail->bind_param("s", $checkoutRequestID);
        $upd_fail->execute();
        
        $conn->commit();
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Failure Status Recorded"]);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["ResultCode" => 1, "ResultDesc" => "Database Execution Error: " . $e->getMessage()]);
}
