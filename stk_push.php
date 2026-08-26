<?php
// stk_push.php - Flat Diagnostic Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Standard Universal Testing Credentials
$key = 'v7G67H9tZ7UvA9vK5bX2zA1x7G5bZ7Uv';
$secret = 'G5bZ7UvA9vK5bX2zA1x7G5bZ7UvA9vK5';

$url = 'https://safaricom.co.ke';

echo "<h2>Running Direct Token Test...</h2>";

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($key . ':' . $secret)],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    // RESOLUTION: Force cURL to automatically step through Safaricom's internal 301 loops
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "<b>HTTP Gateway Status Code:</b> " . $code . "<br><br>";
echo "<b>Server Stream Response:</b> <pre>" . htmlspecialchars($response) . "</pre>";
?>
