<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php'; 

// FIXED SECURITY GATE: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('etims_pdf_report.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $err = ""; $json_payload_display = ""; $api_response_display = "";

// 1. CHOOSE AN UNSYNCED INVOICE TO GENERATE THE eTIMS DATA PACKET
$unsynced_orders = $conn->query("SELECT o.id, u.fullname, o.total_amount FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 10");

if (isset($_POST['simulate_sync'])) {
    $order_id = intval($_POST['order_id']);
    
    // Fetch core order data for KRA bundling logic parameters
    $order_sql = "SELECT o.id, o.net_amount, o.vat_amount, o.total_amount, o.created_at, o.kra_pin, u.fullname, u.email, u.phone 
                  FROM orders o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE o.id = ? LIMIT 1";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("i", $order_id); $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close(); // Cleanly close statement resource context
    
    if ($order) {
        // Fetch matching sub-item rows for array itemization structure
        $items_result = $conn->query("SELECT oi.*, p.product_name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
        $invoice_items = [];
        
        while ($item = $items_result->fetch_assoc()) {
            $invoice_items[] = [
                "itemNumber" => intval($item['id']),
                "productCode" => htmlspecialchars($item['sku']),
                "productName" => htmlspecialchars($item['product_name']),
                "quantity" => intval($item['quantity']),
                "unitPrice" => floatval($item['price']),
                "netPrice" => floatval($item['net_price']),
                "vatPrice" => floatval($item['vat_price']),
                "taxRate" => 16.00,
                "taxCategory" => "A" // KRA Code A represents standard 16% VAT rate
            ];
        }
        
        // 2. CONSTRUCT THE OFFICIAL KRA eTIMS JSON COMPLIANT DATA STRUCTURE PACKET
        $etims_packet = [
            "traderPIN" => "P051XXXXXXZ", // Adonak Electronics KRA PIN Reference
            "invoiceNumber" => "ADONAK-INV-" . $order['id'],
            "originalInvoiceNumber" => "",
            "customerPIN" => !empty($order['kra_pin']) ? htmlspecialchars($order['kra_pin']) : "N/A",
            "customerName" => htmlspecialchars($order['fullname']),
            "customerMobile" => htmlspecialchars($order['phone']),
            "invoiceType" => "Sales Invoice",
            "salesType" => "Normal",
            "paymentMethod" => "Mobile Money",
            "supplyType" => "Taxable",
            "invoiceDate" => date('YmdHis', strtotime($order['created_at'])),
            "summary" => [
                "totalNetAmount" => floatval($order['net_amount']),
                "totalVatAmount" => floatval($order['vat_amount']),
                "totalGrossAmount" => floatval($order['total_amount']),
                "currency" => "KES"
            ],
            "itemList" => $invoice_items
        ];
        
        // Prettify the JSON packet output text box presentation
        $json_payload_display = json_encode($etims_packet, JSON_PRETTY_PRINT);
        
        // 3. MOCK SIMULATED eTIMS REST API RESPONSE CONSOLE
        $simulated_kra_response = [
            "responseCode" => "00", // KRA standard API code '00' implies payload verification success
            "responseMessage" => "Invoice Transmission Successfully Validated by eTIMS Gateway Receiver Engine",
            "taxInvoiceReceiptNumber" => "KRA2026" . strtoupper(bin2hex(random_bytes(4))),
            "kraInternalRxnSignature" => strtoupper(bin2hex(random_bytes(16))),
            "serverTimestamp" => date('Y-m-d H:i:s')
        ];
        
        $api_response_display = json_encode($simulated_kra_response, JSON_PRETTY_PRINT);
        
        // Log transaction data bundling into history logs trail layout
        $log_details = "eTIMS Data Packet Transmission Simulation compiled cleanly for Invoice ID #{$order_id}.";
        $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        $msg = "Success! Transaction row data bundle parsed and transmitted cleanly to the simulated KRA eTIMS Gateway API endpoint.";
    } else { $err = "Invoice parameter selection data error."; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>eTIMS Sync Simulator - ADONAK ELECTRONICS</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; display: flex; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #2c3e50; color: white; padding: 20px; position: fixed; top: 0; left: 0; z-index: 100; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; border-bottom: 1px solid #34495e; padding-bottom: 10px; font-size: 20px; margin-top: 0; color: #ecf0f1; }
    .sidebar a { display: block; color: #bdc3c7; padding: 12px; text-decoration: none; font-size: 14px; font-weight: 500; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; border-radius: 4px; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 30px; min-height: 100vh; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); box-sizing: border-box; }
    
    /* 4. Terminal Console Grid Layout Output Boxes */
    .console-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; box-sizing: border-box; }
    
    /* Pure Monospace Developer Logs Screen */
    pre { background: #1e1e1e; color: #39d353; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; overflow-x: auto; height: 350px; border: 1px solid #333; margin: 0; box-sizing: border-box; -webkit-overflow-scrolling: touch; }
    
    /* Layout Input Filters & Action Selectors */
    select, button { padding: 10px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; height: 40px; box-sizing: border-box; }
    select { background-color: white; color: #1f2937; outline: none; cursor: pointer; }
    select:focus { border-color: #0ca678; }
    
    /* Primary Control Execution Trigger */
    button { background: #0ca678; color: white; border: none; font-weight: bold; cursor: pointer; text-transform: uppercase; letter-spacing: 0.02em; transition: background-color 0.2s; display: inline-flex; align-items: center; justify-content: center; }
    button:hover { background: #087f5b; }
    
    /* Operational Notification Popups */
    .alert { padding: 12px; border-radius: 4px; font-weight: bold; margin-bottom: 15px; font-size: 14px; box-sizing: border-box; }
    .alert-success { background: #e6fcf5; color: #0ca678; border: 1px solid #c3fae8; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 TRANSITIONAL TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #34495e; }
        
        /* Render side hyperlinks horizontally into scrollable rows */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES & LOG SCREENS - Max 768px) */
    @media screen and (max-width: 768px) {
        /* Flattens twin developer screen views into full vertical rows lists */
        .console-grid { grid-template-columns: 1fr; gap: 16px; }
        pre { height: 280px; padding: 12px; font-size: 11px; }
    }

    /* 📱 MINI SMARTPHONE DISPLAY CONSTRAINTS (Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for top horizontal row menu option tabs */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Box and card padding drops */
        .main-content { padding: 12px; }
        .card { padding: 16px; margin-bottom: 16px; }
        
        /* Enlarge form controls to avoid double taps on touch panels */
        select, button { height: 44px; font-size: 15px; width: 100%; }
        button { margin-top: 4px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="add_product.php">➕ Add New Product</a>
        <a href="manage_categories.php">📁 Manage Categories</a>
        <a href="add_brand.php">🏷️ Add Brand Component</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="manage_layaways.php">🇰🇪 Manage Layaway (Pole Pole)</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="etims_sync.php" class="active">⚡ eTIMS KRA Sync API</a>
        <a href="../logout.php" style="background:#c0392b; color:white; text-align:center; margin-top:30px; display:block; padding:8px; border-radius:4px; text-decoration:none;">Logout</a>
    </div>

    <div class="main-content">
        <div class="card">
            <h2 style="margin-top:0; color:#2c3e50;">KRA eTIMS Invoice Data Bundler</h2>
            <p style="color:#666; font-size:14px; margin:0 0 20px 0;">Select an invoice ledger ID code to see how PHP automatically map-structures database rows into JSON API parameters for transmission to KRA fiscal servers.</p>
            
            <?php if(!empty($msg)): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

            <form method="POST" action="etims_sync.php" style="display:flex; gap:15px; align-items:center;">
                <label style="font-weight:600; color:#555;">Choose Invoice Code:</label>
                <select name="order_id" required>
                    <option value="">-- Select Active Record --</option>
                    <?php while($row = $unsynced_orders->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>">Invoice #<?php echo $row['id']; ?> - <?php echo htmlspecialchars($row['fullname']); ?> ($<?php echo number_format($row['total_amount'],2); ?>)</option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="simulate_sync">Compile & Simulate API Sync</button>
            </form>
        </div>

        <?php if(!empty($json_payload_display)): ?>
            <!-- Two-Column API Payload Console Output Screens -->
            <div class="console-grid">
                <div class="card" style="margin:0;">
                    <h3 style="margin-top:0; color:#2c3e50; font-size:15px;">📤 Transmitted JSON Data Payload (eTIMS Format)</h3>
                    <pre><?php echo htmlspecialchars($json_payload_display); ?></pre>
                </div>
                <div class="card" style="margin:0;">
                    <h3 style="margin-top:0; color:#2c3e50; font-size:15px;">📥 Received JSON KRA Response Packet</h3>
                    <pre style="color: #61afef;"><?php echo htmlspecialchars($api_response_display); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
