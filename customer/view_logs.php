<?php
// view_logs.php - Save this to your electronics_shop/customer/ directory folder
header('Content-Type: text/plain');

$logFile = 'mpesa_responses.log';

if (file_exists($logFile)) {
    echo "--- LATEST SAFARICOM M-PESA INBOUND PAYLOAD LOGS ---\n\n";
    echo file_get_contents($logFile);
} else {
    echo "No transaction callback attempts have been logged yet. Try triggering a fresh STK push checkout option first.";
}
?>
