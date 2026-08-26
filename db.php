<?php
date_default_timezone_set('Africa/Nairobi');
$host = "localhost";
$user = "root";
$password = "";
$database = "electronics_shop";

$conn = new mysqli($host, $user, $password, $database);

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}
// FORCE MYSQL TO USE EAST AFRICA TIME (UTC+3)
$conn->query("SET time_zone = '+03:00';");
// --- FIXED: GLOBAL DYNAMIC TAX CONFIGURATION PARSER ---
// Default fallback if database parameters are missing
$system_vat_rate = 16.00; 

$settings_res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'vat_rate' LIMIT 1");
if ($settings_res && $settings_res->num_rows > 0) {
    $system_vat_rate = floatval($settings_res->fetch_assoc()['setting_value']);
}

// Global math multi-pliers made accessible to checkout, printing, and analytical loops
$tax_multiplier = $system_vat_rate / 100; // e.g., 0.16
$tax_divisor = 1 + $tax_multiplier;      // e.g., 1.16

?>