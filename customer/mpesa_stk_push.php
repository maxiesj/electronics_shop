<?php
session_start();
header('Content-Type: application/json');

// --- DARAJA API ACCESS PARAMETERS (Safaricom Developer Portal Credentials) ---
$BusinessShortCode = '174379'; 
$Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2cbe9';

// CRITICAL: Put your real keys from developer.safaricom.co.ke here to go live!
$ConsumerKey = 'YOUR_DEVELOPER_CONSUMER_KEY';     
$ConsumerSecret = 'YOUR_DEVELOPER_CONSUMER_SECRET'; 

$PartyA = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$Amount = isset($_POST['amount']) ? round(floatval($_POST['amount'])) : 0; 

// Normalize phone formatting parameters cleanly (e.g., 0782052627 -> 254782052627)
$PartyA = preg_replace('/[^0-9]/', '', $PartyA);
if (substr($PartyA, 0, 1) === '0') { $PartyA = '254' . substr($PartyA, 1); }

if (empty($PartyA) || $Amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction amount or contact digits.']);
    exit();
}

// AUTOMATIC FALLBACK SIMULATOR BYPASS 
// If your Consumer Key is left as the default string or is empty, bypass Safaricom servers to allow seamless offline testing!
if (empty($ConsumerKey) || $ConsumerKey === 'YOUR_DEVELOPER_CONSUMER_KEY') {
    echo json_encode([
        'ResponseCode' => '0',
        'CustomerMessage' => 'Success',
        'status' => 'mock_success', 
        'message' => 'STK Prompt Simulated on Sandbox successfully.'
    ]);
    exit();
}

// 1. GENERATE DARAJA API OAUTH ACCESS TOKEN
$url = 'https://safaricom.co.ke';
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($ConsumerKey . ':' . $ConsumerSecret)]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($curl);
$token = json_decode($response)->access_token ?? '';

if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate token from Safaricom. Check your consumer keys.']);
    exit();
}

// 2. CONSTRUCT TIMESTAMP AND SECURITY PASSWORD
$Timestamp = date('YmdHis');
$Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);
$CallbackUrl = 'https://yourdomain.com'; 

// 3. DISPATCH SECURE STK PUSH REQUEST MATRIX
$stk_url = 'https://safaricom.co.ke';
$headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];

$curl_payload = [
    'BusinessShortCode' => $BusinessShortCode,
    'Password' => $Password,
    'Timestamp' => $Timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $Amount,
    'PartyA' => $PartyA,
    'PartyB' => $BusinessShortCode,
    'PhoneNumber' => $PartyA,
    'CallBackURL' => $CallbackUrl,
    'AccountReference' => 'ADONAK_ELECTRONICS',
    'TransactionDesc' => 'Storefront Invoice Checkout Payment'
];

$ch = curl_init($stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$stk_res = curl_exec($ch);

echo $stk_res;
exit();
