<?php
// FORCE BROWSER AND AJAX AGENTS TO FETCH FRESH DATA ALWAYS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// 1. SESSION INITIALIZATION & CENTRAL GATEKEEPER INTERCEPT
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}
$db_path = dirname(__FILE__) . '/../db.php';
include_once file_exists($db_path) ? $db_path : '../db.php'; 
require_once dirname(__FILE__) . '/../session_auth.php';

// FIXED ACCESS SECURITY GATE: Dynamically checks permissions via your centralized gatekeeper file
if (!verifyWorkspaceClearance('dashboard.php')) {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_REQUEST['ajax_request']);
    if ($is_ajax) {
        echo "AUTH_ERROR";
        exit();
    }
    header("Location: ../login.php?msg=err_unauthorized_access"); 
    exit(); 
}

// Enable live error tracking to diagnose data layers easily
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================================
// --- CENTRALIZED BACKEND PROCESSING ENGINE FOR CORE SQL DATABASE DUMPS ---
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger_backup') {
    if (ob_get_length()) ob_clean(); // Purge buffer to ensure clean file logs text output streams
    
    $backup_dir = __DIR__ . '/../backups/';
    if (!is_dir($backup_dir)) { 
        @mkdir($backup_dir, 0755, true); 
    }

    $filename = 'adonak_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $target_file = $backup_dir . $filename;
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result) { 
        while ($row = $result->fetch_row()) { 
            $tables[] = $row[0]; 
        } 
    }
    
    $sql_dump = "-- ADONAK Admin Automated SQL Data Backup\n";
    $sql_dump .= "-- Generated Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        $create_res = $conn->query("SHOW CREATE TABLE `$table`");
        if ($create_res) { 
            $create_row = $create_res->fetch_row();
            $sql_dump .= "\n\n" . $create_row[1] . ";\n\n"; 
        }
        
        $data_res = $conn->query("SELECT * FROM `$table`");
        if ($data_res) {
            while ($row = $data_res->fetch_assoc()) {
                $fields = array_map(function($val) use ($conn) {
                    return $val === null ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
                }, array_values($row));
                
                $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(", ", $fields) . ");\n";
            }
        }
    }
    $sql_dump .= "\n\nSET FOREIGN_KEY_CHECKS=1;\n";
    
    if (@file_put_contents($target_file, $sql_dump) !== false) {
        // Log backup generation event parameters cleanly inside your active audit trail database
        $msg_det = "Manual Data Snapshot Generated: Dashboard administrator triggered a structural database dump package [{$filename}] saved to backups folder.";
        $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
        if ($audit) {
            $audit->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $msg_det);
            $audit->execute();
            $audit->close();
        }
        
        echo "SUCCESS|Database successfully archived to vault file: " . $filename;
    } else {
        echo "ERROR|FileSystem permission error: Unable to write data dump package.";
    }
    exit(); 
}
// Fetch structural numeric counts for top metrics panels
$p_res = $conn->query("SELECT COUNT(id) AS t FROM orders WHERE LOWER(TRIM(order_status))='pending'");
$count_pending = $p_res ? intval($p_res->fetch_assoc()['t']) : 0;

$l_res = $conn->query("SELECT COUNT(id) AS t FROM layaway_plans WHERE LOWER(TRIM(status))='active'");
$count_layaway = $l_res ? intval($l_res->fetch_assoc()['t']) : 0;

$s_res = $conn->query("SELECT COUNT(id) AS t FROM products WHERE stock_quantity<5");
$count_low_stock = $s_res ? intval($s_res->fetch_assoc()['t']) : 0;

// FIXED: Joins the roles directory table to correctly calculate staff counts based on your active role_id parameters
$st_res = $conn->query("SELECT COUNT(u.id) AS t FROM users u JOIN roles r ON u.role_id = r.id WHERE LOWER(TRIM(r.role_name)) IN ('staff', 'admin', 'super_admin', 'cashier', 'auditor', 'cleaner') AND (u.account_status != 'purged' OR u.account_status IS NULL)");
$count_staff = $st_res ? intval($st_res->fetch_assoc()['t']) : 0;

// Fetch Monthly Revenue metrics for native graph bars
$m_chart = $conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') AS lbl, SUM(total_amount) AS yld FROM orders WHERE LOWER(TRIM(order_status))!='cancelled' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at DESC LIMIT 6");
$m_data = []; 
$max_m_val = 100;

if ($m_chart) {
    while ($mr = $m_chart->fetch_assoc()) {
        $v = floatval($mr['yld']); 
        if ($v > $max_m_val) { $max_m_val = $v; }
        $m_data[] = ['l' => $mr['lbl'], 'v' => $v];
    }
    $m_data = array_reverse($m_data); 
}

if (empty($m_data)){ 
    $m_data[] = ['l' => date('M Y'), 'v' => 0]; 
}


// Fetch Category Revenue metrics for native rows split
$c_chart = $conn->query("SELECT c.category_name AS cat, SUM(oi.quantity*oi.price) AS rev FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN categories c ON p.category_id=c.id JOIN orders o ON oi.order_id=o.id WHERE LOWER(TRIM(o.order_status))!='cancelled' GROUP BY c.id ORDER BY rev DESC LIMIT 5");
$c_data = []; 
$total_c_rev = 0;

if ($c_chart) {
    while ($cr = $c_chart->fetch_assoc()) {
        $v = floatval($cr['rev']); 
        $total_c_rev += $v;
        $c_data[] = ['c' => $cr['cat'], 'v' => $v];
    }
}
if ($total_c_rev == 0) { $total_c_rev = 1; }

$recent = $conn->query("SELECT o.id, u.fullname, o.total_amount, o.order_status, o.created_at FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">

<head>
        <!-- CRITICAL MOBILE ENGINE FIX: Tells the phone browser to render elements at 100% native pixel crispness -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>ADONAK ELECTRONICS - MASTER PORTAL</title>
 <style>
    /* ==========================================================================
       1. GLOBAL BASELINE RESET STYLES
       ========================================================================== */
    * { 
        box-sizing: border-box; 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease; 
    }
    body { 
        background: #f8fafc; 
        margin: 0; 
        padding: 0; 
        display: flex; 
        color: #1e293b; 
        min-height: 100vh; 
        width: 100vw;
        overflow-x: hidden;
    }
    
    /* ==========================================================================
       2. LEFT SIDEBAR NAVIGATION PANEL LAYOUT (DESKTOP DEFAULT VIEW)
       ========================================================================== */
    .sidebar {
        width: 260px;
        height: 100vh;
        background: #1e293b; 
        color: white;
        padding: 20px;
        position: fixed;
        top: 0;
        left: 0;
        overflow-y: auto;
        overflow-x: hidden;
        box-sizing: border-box;
        z-index: 100;
        transition: transform 0.3s ease, width 0.3s ease, height 0.3s ease;
    }
    .sidebar h2 { 
        text-align: center; 
        border-bottom: 1px solid #334155; 
        padding-bottom: 15px; 
        font-size: 18px; 
        margin: 0 0 15px 0; 
        color: #f8fafc; 
    }
    .sidebar a { 
        display: block; 
        color: #94a3b8; 
        padding: 12px; 
        text-decoration: none; 
        font-size: 14px; 
        border-radius: 6px; 
        margin-bottom: 4px; 
    }
    .sidebar a:hover, .sidebar a.active { 
        background: #334155; 
        color: #3b82f6; 
        font-weight: bold; 
    }
    
    /* ==========================================================================
       3. MAIN WORKSPACE AREA CONTAINERS
       ========================================================================== */
    .main-content { 
        margin-left: 260px; 
        flex-grow: 1; 
        padding: 30px; 
        width: calc(100% - 260px); 
        box-sizing: border-box; 
        transition: margin-left 0.3s ease, width 0.3s ease; 
    }
    
    .navbar { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        background: white; 
        padding: 20px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        border: 1px solid #edf2f7; 
        box-sizing: border-box; 
        gap: 16px; 
    }
    .stats { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 15px; 
        margin-bottom: 20px; 
        width: 100%; 
        box-sizing: border-box; 
    }
    .card { 
        background: white; 
        padding: 20px; 
        border-radius: 10px; 
        border: 1px solid #e2e8f0; 
        border-left: 5px solid #3b82f6; 
        box-sizing: border-box; 
    }
    
    /* Analytical Charts System Splits */
    .charts { 
        display: grid; 
        grid-template-columns: 1.4fr 1fr; 
        gap: 20px; 
        margin-bottom: 20px; 
        width: 100%; 
        box-sizing: border-box; 
    }
    .c-card { 
        background: white; 
        padding: 20px; 
        border-radius: 10px; 
        border: 1px solid #e2e8f0; 
        box-sizing: border-box; 
    }
    
    /* CSS Column Bar Chart Visual Configurations */
    .chart-box { 
        height: 220px; 
        display: flex; 
        align-items: flex-end; 
        justify-content: space-around; 
        padding-top: 20px; 
        box-sizing: border-box; 
        width: 100%; 
    }
    .bar-col { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        flex-grow: 1; 
        height: 100%; 
        justify-content: flex-end; 
        position: relative; 
    }
    .bar-pill { 
        width: 35px; 
        background: #3b82f6; 
        border-radius: 4px 4px 0 0; 
        position: relative; 
        max-height: 100%; 
    }
    .bar-pill:hover { 
        background: #2563eb; 
    }
    .bar-tip { 
        position: absolute; 
        top: -25px; 
        left: 50%; 
        transform: translateX(-50%); 
        background: #0f172a; 
        color: white; 
        font-size: 10px; 
        padding: 2px 6px; 
        border-radius: 4px; 
        opacity: 0; 
        pointer-events: none; 
        white-space: nowrap; 
        z-index: 10; 
    }
    .bar-col:hover .bar-tip { 
        opacity: 1; 
    }
    
    /* Summary Listing Elements Row */
    .row-split { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        font-size: 13px; 
        padding: 8px 0; 
        border-bottom: 1px dashed #f1f5f9; 
        gap: 16px; 
        box-sizing: border-box; 
    }
    .row-split:last-child { 
        border-bottom: none; 
    }
    
    /* ==========================================================================
       4. TABULAR DATA SUMMARY LEDGER SYSTEMS
       ========================================================================== */
    .table-scroll-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #edf2f7;
        border-radius: 8px;
        margin-top: 15px;
        background: white;
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 10px; 
    }
    th, td { 
        padding: 12px; 
        text-align: left; 
        border-bottom: 1px solid #f1f5f9; 
        font-size: 14px; 
        vertical-align: middle; 
    }
    th { 
        background: #f8fafc; 
        text-transform: uppercase; 
        font-size: 11px; 
        color: #475569; 
        font-weight: 700; 
        white-space: nowrap;
    }
    
    .badge { 
        padding: 4px 8px; 
        border-radius: 12px; 
        font-size: 11px; 
        font-weight: bold; 
        text-transform: uppercase; 
        display: inline-block; 
        white-space: nowrap; 
    }
    .s-pending { background: #fef3c7; color: #d97706; } 
    .s-processing { background: #e0f2fe; color: #0369a1; }
    
    .spinner {
        border: 4px solid #e2e8f0;
        border-top: 4px solid #6366f1;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 0.8s linear infinite;
        margin: 20px auto;
        display: none;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* ==========================================================================
       5. RESPONSIVE SCREEN QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

      /* 💻 MEDIUM LAPTOPS & LANDSCAPE TABLETS FLUIDITY (Max 1024px viewports) */
    @media screen and (max-width: 1024px) {
        .stats { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
        .charts { grid-template-columns: 1fr !important; gap: 20px !important; }
    }

      /* 💻 MEDIUM LAPTOPS & LANDSCAPE TABLETS FLUIDITY (Max 1024px viewports) */
    @media screen and (max-width: 1024px) {
        .stats { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
        .charts { grid-template-columns: 1fr !important; gap: 20px !important; }
    }

        /* 📱 PORTRAIT TABLETS & SMARTPHONES TOUCH INTERFACES (Max 768px Width) */
    @media screen and (max-width: 768px) {
        /* Force strict layout boundaries with no horizontal overflow spills */
        *, *:before, *::after {
            box-sizing: border-box !important;
        }

        html, body { 
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            display: block !important; 
            margin: 0 !important;
            padding: 0 !important;
            background: #f8fafc !important;
        }
        
        /* FIXED: Converts top menu bar into an absolute static fixed banner */
        .sidebar { 
            width: 100% !important; 
            height: 55px !important; /* Forces a fixed, clean compact height profile */
            max-width: 100% !important;
            
            /* CRITICAL FIXED PORTAL STACKING SETTINGS */
            position: fixed !important; 
            top: 0 !important;
            left: 0 !important;
            z-index: 9999 !important; /* Guarantees user lists slide safely behind the bar */
            
            padding: 10px 16px !important; 
            overflow-x: auto !important; 
            display: flex !important;
            flex-direction: row !important;
            white-space: nowrap !important; 
            -webkit-overflow-scrolling: touch; 
            gap: 8px !important;
            border-right: none !important;
            border-bottom: 1px solid #334155 !important;
            box-sizing: border-box !important;
        }
        .sidebar h2 { 
            display: none !important; 
        }
        
        .sidebar a { 
            display: inline-flex !important; 
            align-items: center !important;
            margin-bottom: 0 !important; 
            padding: 6px 12px !important; 
            font-size: 13px !important; 
            flex-shrink: 0 !important;
        }
        
        /* FIXED OVERLAP GAP: Pushes the main panel down by exactly 55px so it clears the fixed header cleanly */
        .main-content { 
            margin-left: 0 !important; 
            margin-top: 55px !important; /* CRITICAL OFFSET: Creates room for the fixed top nav bar */
            width: 100% !important; 
            max-width: 100% !important;
            padding: 20px 12px !important; 
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }
        
        .navbar { 
            flex-direction: column !important; 
            align-items: flex-start !important; 
            gap: 10px !important; 
            padding: 12px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Forces both grid and inline flex configurations to wrap vertically */
        .stats, .charts, [class*="stats"], [class*="charts"], [style*="display: flex"], [style*="display:flex"] { 
            display: flex !important;
            flex-direction: column !important; 
            gap: 12px !important; 
            width: 100% !important;
            max-width: 100% !important;
            margin-bottom: 15px !important;
            box-sizing: border-box !important;
        }

        .card, .c-card, [class*="card"], [style*="background:white"], [style*="background: white"] { 
            width: 100% !important;
            max-width: 100% !important;
            padding: 16px !important; 
            margin: 0 0 12px 0 !important;
            box-sizing: border-box !important;
            border-radius: 12px !important;
        }
        
        /* Enforces structural scroll limits to allow components to expand freely underneath */
        #dynamic-workspace {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        .table-scroll-container {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important; 
            -webkit-overflow-scrolling: touch !important; 
            margin-top: 15px !important;
            border: 1px solid #edf2f7 !important;
            border-radius: 8px !important;
            background: #ffffff !important; 
            box-sizing: border-box !important;
        }
        
        table {
            display: table !important; 
            width: 100% !important;
            min-width: 550px !important; 
            border-collapse: collapse !important;
        }
        
        tr {
            display: table-row !important;
            background: transparent !important;
        }

        th, td { 
            display: table-cell !important;
            white-space: nowrap !important; 
            padding: 14px 12px !important; 
            font-size: 13px !important; 
            background: #ffffff !important; 
        }
        
        th {
            background: #f8fafc !important; 
        }
        
        /* Universal Form Fields Mobile Scaling layout profiles */
        form {
            display: flex !important;
            flex-direction: column !important;
            gap: 10px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        form input[type="text"], form input[type="search"], form button {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            font-size: 16px !important;
            height: 42px !important;
        }
		
		        /* --------------------------------=========================================
           FIXED FINANCIAL CHARTS AND COLUMN OVERLAP OVERRIDES
           --------------------------------========================================= */
        /* Forces the outer chart cards layout to expand into clean vertical stacks */
        .charts, [class*="charts"] {
            display: flex !important;
            flex-direction: column !important;
            gap: 20px !important;
            width: 100% !important;
        }

        /* FIXED OVERLAP BUG: Decouples the inner chart box to scroll smoothly left-to-right */
        .chart-box, [class*="chart-box"] {
            width: 100% !important;
            height: 240px !important; /* Locks a clean uniform height boundary canvas */
            overflow-x: auto !important; /* Enable dedicated horizontal swipe controls */
            overflow-y: hidden !important;
            display: flex !important;
            flex-direction: row !important; /* Enforces bars to align horizontally in a straight line */
            align-items: flex-end !important;
            justify-content: flex-start !important; /* Starts plot alignments from the left margin edge */
            gap: 24px !important; /* Adds uniform separation spacing properties between monthly columns */
            padding: 20px 10px 10px 10px !important;
            -webkit-overflow-scrolling: touch !important; /* Smooth momentum scrolling layout for mobile touch browsers */
            box-sizing: border-box !important;
        }

        /* Protect real bar pillar metrics columns text data constraints */
        .bar-col, [class*="bar-col"] {
            flex: 0 0 50px !important; /* Stops the browser from crushing column bars out of shape */
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-end !important;
            align-items: center !important;
        }

        .bar-pill, [class*="bar-pill"] {
            width: 28px !important; /* Optimal compact touch-width thickness boundaries */
            min-height: 4px !important;
        }

        /* Forces date labels beneath graph bars to stay on a single line */
        .chart-box text, .chart-box span, .bar-col + span, [style*="font-size"] {
            white-space: nowrap !important;
            font-size: 11px !important;
        }

    }



    /* ==========================================================================
       6. PRINT MEDIA ATTRIBUTE OVERRIDES (A4 PAPER PRINTS AND SAVE-AS-PDF)
       ========================================================================== */
    @media print {
        /* Completely sweep away background layouts, sidebar navigation panels, and interactive elements */
        .sidebar, .navbar, .etims-top-action-bar, .etims-top-bar, button, select, #return-analytics-link-btn, .spinner { 
            display: none !important; 
        }
        
        /* Expand the primary application background shell canvas to take up 100% width with no shifts */
        body, .main-content, #dynamic-workspace, .card, .etims-panel, .etims-gateway-panel, .charts, .c-card, .stats { 
            margin: 0 !important; 
            padding: 0 !important; 
            width: 100% !important; 
            background: transparent !important; 
            border: none !important;
            box-shadow: none !important;
            display: block !important;
            position: static !important;
        }
        
        /* Adjust layout spacing for printed data items */
        .card, .c-card { 
            margin-bottom: 20px !important; 
            border: 1px solid #cbd5e1 !important; 
            padding: 15px !important; 
            page-break-inside: avoid; 
            break-inside: avoid; 
        }
        .stats { 
            display: grid !important; 
            grid-template-columns: repeat(2, 1fr) !important; 
            gap: 15px !important; 
            margin-bottom: 20px !important; 
        }
        
        /* Retain standard graph bars readability on monochromatic prints */
        .chart-box { 
            display: flex !important; 
            align-items: flex-end !important; 
            justify-content: space-around !important; 
            height: 180px !important; 
        }
        .bar-pill { 
            background: #334155 !important; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        /* Ensure itemized table cells render cleanly with clear boundaries on physical paper sheets */
        table { 
            display: table !important; 
            width: 100% !important; 
            border: 1px solid #cbd5e1 !important; 
            margin-top: 15px !important; 
        }
        tr { 
            display: table-row !important; 
            page-break-inside: avoid; 
            break-inside: avoid; 
        }
        th, td { 
            display: table-cell !important; 
            padding: 8px 10px !important; 
            border-bottom: 1px solid #e2e8f0 !important; 
            white-space: normal !important; 
            color: #000000 !important; 
        }
        th { 
            background: #f1f5f9 !important; 
            font-weight: bold !important; 
        }
    }
	
	/* ==========================================================================
   SIDEBAR TOGGLE CONTROL ELEMENT ENGINE STYLES
   ========================================================================== */
.sidebar-collapse-trigger-btn {
    position: absolute;
    top: 15px;
    right: 8px;
    width: 28px;
    height: 28px;
    background: #3b82f6;
    color: white;
    border: 1px solid #1e293b;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    z-index: 105;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    outline: none;
    transition: background-color 0.2s, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-collapse-trigger-btn:hover {
    background: #2563eb;
}

/* Condensed Sidebar State Modification Parameters */
.sidebar.condensed {
    width: 75px !important;
    min-width: 75px !important;
    padding: 20px 10px !important;
}

/* Hide structural components and typography layouts when condensed state class is active */
.sidebar.condensed h2,
.sidebar.condensed .link-label-text {
    display: none !important;
}

.sidebar.condensed a {
    text-align: center !important;
    padding: 12px 0 !important;
    font-size: 18px !important; /* Enlarge icon emojis slightly for clean visual center alignments */
}

/* Dynamically snap open the primary main workspace content grid to take up full available canvas widths */
.sidebar.condensed + .main-content {
    margin-left: 75px !important;
    width: calc(100% - 75px) !important;
}

/* Rotate button indicator icon mirror arrow directions dynamically */
.sidebar.condensed .sidebar-collapse-trigger-btn {
    right: 8px;
}

/* Safe protection loop to block toggle trigger overlay buttons from overflowing off-screen on smartphones */
@media screen and (max-width: 768px) {
    .sidebar-collapse-trigger-btn {
        display: none !important; /* Turn button completely off on small touch viewports since layout naturally swipable */
    }
}

</style>



<link rel="stylesheet" href="../css/panel-polish.css?v=20260811-13">
<script src="../js/page-progress-dialog.js"></script>
</head>
<body class="panel-ui admin-panel">
 <div class="sidebar" id="mainSidebarPanel" role="navigation" aria-label="Admin workspace navigation">
    <!-- COLLAPSIBLE TRIGGER NAVIGATION BUTTON CONTROLLER -->
    <button type="button" id="sidebarToggleCollapseBtn" class="sidebar-collapse-trigger-btn" aria-label="Toggle Navigation Workspace Panel">
        <span id="toggleButtonArrowIconSymbol">◀</span>
    </button>

    <button type="button" id="adminMobileMenuToggle" class="admin-mobile-menu-toggle" aria-expanded="false" aria-controls="mainSidebarPanel">
        <span class="admin-mobile-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="admin-mobile-menu-label">Menu</span>
    </button>

    <h2>ADONAK Admin</h2>
    
    <?php
    // Group related workspace destinations while preserving the existing permission checks.
    $menu_sections = [
        'Overview' => [
            ['dashboard_overview.php', 'Dashboard Overview', '&#9632;', 'ajax-link active']
        ],
        'Commerce' => [
            ['manage_orders.php', 'Manage Sales Orders', '&#128722;', 'ajax-link'],
            ['manage_reviews.php', 'Feedback Moderator', '&#128172;', 'ajax-link']
        ],
        'Inventory' => [
            ['add_product.php', 'Add New Product', '&#43;', 'ajax-link'],
            ['warehouse.php', 'Warehouse Stock', '&#127981;', 'ajax-link'],
            ['low_stock_monitor.php', 'Low Stock Monitor', '&#9888;', 'ajax-link'],
            ['low_stock_dispatcher.php', 'Stock Dispatcher', '&#9993;', 'ajax-link'],
            ['manage_categories.php', 'Categories & Brands', '&#128193;', 'ajax-link']
        ],
        'Customers & Payments' => [
            ['manage_wallets.php', 'Customer Wallets', '&#128179;', 'ajax-link'],
            ['layaway_defaulters.php', 'Installment Defaulters', '&#9203;', 'ajax-link'],
            ['mpesa_checker.php', 'M-Pesa Code Checker', '&#128241;', 'ajax-link']
        ],
        'Finance & Reports' => [
            ['sales_analytics.php', 'Financial Analytics', '&#128200;', 'ajax-link'],
            ['payroll.php', 'Payroll', '&#128176;', 'ajax-link'],
            ['operating_expenses.php', 'Operating Expenses', '&#128179;', 'ajax-link'],
            ['invoice_archiver.php', 'Invoice PDF Archive', '&#128451;', 'ajax-link'],
            ['etims_pdf_report.php', 'Monthly Reports', '&#128196;', 'ajax-link'],
            ['tax_settings.php', 'Global Tax Settings', '&#9881;', 'ajax-link']
        ],
        'Team' => [
            ['manage_staff.php', 'Manage Staff Network', '&#128101;', 'ajax-link']
        ],
        'System' => [
            ['trash.php', 'Trash & Recovery', '&#128465;', 'ajax-link'],
            ['db_backup.php', 'Core Data Backup', '&#128190;', 'ajax-link'],
            ['workspace_tracker.php', 'Workspace Tracker', '&#128737;', 'ajax-link']
        ]
    ];

    foreach ($menu_sections as $section_label => $section_items) {
        $authorized_links = [];

        foreach ($section_items as $item) {
            [$target, $label, $icon, $class] = $item;
            $base_file = explode('?', $target)[0];
            $has_access = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

            if (!$has_access) {
                $stmt = $conn->prepare("SELECT id FROM staff_permissions WHERE user_id = ? AND target_view = ? LIMIT 1");
                $stmt->bind_param("is", $_SESSION['user_id'], $base_file);
                $stmt->execute();
                $res = $stmt->get_result();
                $has_access = $res && $res->num_rows > 0;
                $stmt->close();
            }

            if (!$has_access) {
                continue;
            }

            $icon_html = '<span class="sidebar-link-icon" aria-hidden="true">' . $icon . '</span>';
            $label_html = '<span class="link-label-text">' . htmlspecialchars($label) . '</span>';

            if ($class === 'sidebar-direct-link') {
                $authorized_links[] = '<a href="dashboard.php?view=' . htmlspecialchars($target) . '" class="' . $class . '">' . $icon_html . $label_html . '</a>';
            } else {
                $authorized_links[] = '<a href="#" class="' . $class . '" data-target="' . htmlspecialchars($target) . '">' . $icon_html . $label_html . '</a>';
            }
        }

        if ($authorized_links) {
            $section_id = 'sidebar-section-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($section_label));
            $is_open = $section_label === 'Overview';

            echo '<div class="sidebar-section' . ($is_open ? ' is-open' : '') . '">';
            echo '<button type="button" class="sidebar-section-toggle" aria-expanded="' . ($is_open ? 'true' : 'false') . '" aria-controls="' . htmlspecialchars($section_id) . '">';
            echo '<span class="sidebar-section-title">' . htmlspecialchars($section_label) . '</span><span class="sidebar-section-indicator" aria-hidden="true"><span class="sidebar-section-loader"></span><span class="sidebar-section-chevron">&#9662;</span></span>';
            echo '</button>';
            echo '<div class="sidebar-section-content" id="' . htmlspecialchars($section_id) . '">';
            echo implode('', $authorized_links);
            echo '</div>';
            echo '</div>';
        }
    }
    ?>

    <a href="../account_security.php" class="logout-sidebar-btn" style="background:#334155; color:white; text-align:center; font-weight:bold; margin-top:20px; display:block; padding:10px; border-radius:6px; text-decoration:none;"><span class="link-label-text">&#128274; Account Security</span></a>
    <a href="../logout.php" class="logout-sidebar-btn" style="background:#ef4444; color:white; text-align:center; font-weight:bold; margin-top:20px; display:block; padding:10px; border-radius:6px; text-decoration:none;"><span class="link-label-text">Logout</span></a>
</div>





<!-- START OF THE COMPONENT WORKSPACE ENGINE -->
<div class="main-content">
    
    <!-- Top Administrative Navigation Bar Component -->
    <!-- Shared progress dialog is loaded from js/page-progress-dialog.js. -->
<!-- Content Workspace Frame Target Layer with Embedded AJAX Wrapper Id -->
    <div id="live-stats-panel" style="width: 100%;">
	
        <div id="dynamic-workspace">
            <?php
            // 1. DYNAMIC ROUTING ISOLATION: Initialize default fallback strictly if no GET queries exist
            $requested_view = isset($_GET['view']) ? trim($_GET['view']) : '';

            // -------------------------------------------------------------------------
            // CONDITION A: WORKSPACE TRACKER LOG ENGINE
            // -------------------------------------------------------------------------
            if ($requested_view === 'workspace_tracker') {
                // Preserve old bookmarked tracker URLs while rendering the new standalone component.
                include 'workspace_tracker.php';
            } elseif (!empty($requested_view)) {
                $allowed_view_file = basename($requested_view);
                if (file_exists($allowed_view_file)) {
                    include $allowed_view_file;
                } else {
                    echo '<div style="padding:20px; background:#fff; border-radius:12px; border:1px solid #e2e8f0; color:#ef4444;">⚠️ Layout Route File Missing Reference.</div>';
                }

            // -------------------------------------------------------------------------
            // CONDITION C: CLEAN FALLBACK (RUNS ONLY IF NO PARAMETERS ARE REQUESTED)
            // -------------------------------------------------------------------------
            } else {
                if (file_exists('dashboard_overview.php')) {
                    include 'dashboard_overview.php';
                } else {
                    echo '<div style="padding:20px; background:#fff; border-radius:12px; border:1px solid #e2e8f0;">';
                    echo '<h3 style="margin:0; color:#0f172a;">Operational Dashboard Main Workspace Terminal Node</h3>';
                    echo '<p style="color:#64748b; font-size:13px; margin:5px 0 0 0;">Please select an option from the sidebar navigational registry matrices to manage system data array parameters.</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
	
</div>

<!-- END OF THE MAIN CONTENT CONTAINER WRAPPER PANEL CODES -->



<!-- END OF THE MAIN CONTENT CONTAINER WRAPPER PANEL CODES -->


            <!-- 2. Category Revenue Breakdowns Card Component -->

<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. INITIALIZATION LAYER: Manage and parse active view tokens on reload or refresh
    const currentUrlParams = new URL(window.location.href);
    let hasUpdatedUrlHistory = false;
    const activeWorkspaceState = currentUrlParams.searchParams.get('view');

    // Skip deleting the message string if we are actively managing staff credentials
    if (activeWorkspaceState !== 'manage_staff.php') {
        if (currentUrlParams.searchParams.has('msg')) { 
            currentUrlParams.searchParams.delete('msg'); 
            hasUpdatedUrlHistory = true;
        }
    }

    if (hasUpdatedUrlHistory && window.history.replaceState) {
        window.history.replaceState({}, '', currentUrlParams.href); 
    }
    
    // Core Layout Element Structural Target Connectors
    const view = document.getElementById('dynamic-workspace') || document.querySelector('.main-content');
    const head = document.getElementById('current-view-title');

    // Smooth UI state loader engine: preserve the workspace dimensions while new content loads.
    const ui = (load) => {
        if (view) {
            view.classList.toggle('workspace-is-loading', load);
            view.setAttribute('aria-busy', load ? 'true' : 'false');
        }
        if (window.ADONAKPageProgress) {
            load ? window.ADONAKPageProgress.show('Please wait while we load your workspace.') : window.ADONAKPageProgress.hide();
        }
    };
    
    // Engine helper to force-evaluate embedded script execution tags inside injected files
    const executeInjectedScripts = (container) => {
        if (!container) return;
        container.querySelectorAll("script").forEach(oldScript => {
            const newScript = document.createElement("script");
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    };
    
    let activeWorkspaceRequest = null;
    let workspaceRequestSequence = 0;

       // 2. Updated content asynchronous view transition frame syncer
    const sync = async (url, shouldPushToAddressBar = true) => {
        if (!url || url === '#') return;

        if (activeWorkspaceRequest) {
            activeWorkspaceRequest.abort();
        }
        activeWorkspaceRequest = new AbortController();
        const currentRequestSequence = ++workspaceRequestSequence;
        ui(true);

        try {
            const requestUrl = url + (url.includes('?') ? '&' : '?') + 'nc=' + Date.now();
            const fetchPromise = fetch(requestUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeWorkspaceRequest.signal
            });
            const animationPromise = new Promise(resolve => setTimeout(resolve, 500));
            
            const [res] = await Promise.all([fetchPromise, animationPromise]);

            if (res.ok) {
                let responseText = await res.text();

                // DETECT EXPIRED SESSION: Check if the returned text is actually a login gate
                if (responseText.includes('AUTH_ERROR') || responseText.includes('SESSION_TIMEOUT') || responseText.includes('SECURE_AUTHENTICATION') || responseText.includes('name="password"') || responseText.includes('SESSION EXPIRED')) {
                    console.warn("Session invalidation detected. Redirecting to authentication portal...");
                    window.location.href = '../session_expire.php'; // Securely clear the expired session before returning to login.
                    return;
                }

                // Component routes must not inject a second dashboard shell. If an older
                // module returns a complete HTML document, retain only its workspace body.
                const parsedResponse = new DOMParser().parseFromString(responseText, 'text/html');
                const nestedWorkspace = parsedResponse.querySelector('#dynamic-workspace');
                const containsDashboardShell = parsedResponse.querySelector('#mainSidebarPanel');
                if (nestedWorkspace && containsDashboardShell) {
                    responseText = nestedWorkspace.innerHTML;
                }

                if (view) {
                    view.innerHTML = responseText;
                    executeInjectedScripts(view);
                }
            }
            
            document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
            
            const cleanTarget = url.split('?')[0];
            const active = document.querySelector(`.sidebar a[data-target="${url}"]`) ||
                           document.querySelector(`.sidebar a[data-target="${cleanTarget}"]`) || 
                           document.querySelector(`.sidebar a[data-target^="${cleanTarget}"]`);
                           
            if (active) { 
                active.classList.add('active'); 
                if (head) head.textContent = active.textContent.replace(/[^\w\s\+&]/g,'').trim(); 
            }

            if (shouldPushToAddressBar && window.history.pushState) {
                const liveQueryUrl = new URL(window.location.href);
                liveQueryUrl.searchParams.set('view', url);
                window.history.pushState({ targetActiveLayoutFile: url }, '', liveQueryUrl.href);
            }
        } catch(e) {
            if (e.name !== 'AbortError') {
                console.error("Layout Engine Sync Exception:", e);
            }
        } finally {
            if (currentRequestSequence === workspaceRequestSequence) {
                activeWorkspaceRequest = null;
                ui(false);
            }
        }
    };


    // Expose sync helper to the runtime environment window context globally
    window.syncWorkspaceView = sync;

    // 3, 4 & 5. CONSOLIDATED ENGINE INTERCEPTOR 
    document.addEventListener('click', (e) => {
        // [3] Purge Exclusions Bypass
        if (e.target.closest('.purge-action-trigger-link')) {
            return; 
        }

        // [4] Warehouse Deletion Routine
        const deleteBtn = e.target.closest('.async-delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const itemId = deleteBtn.getAttribute('data-id');
            const itemName = deleteBtn.getAttribute('data-name');
            const csrfToken = deleteBtn.getAttribute('data-csrf');
            const targetRow = document.getElementById(`row-item-${itemId}`);

            if (!itemId || !confirm(`⚠️ Catalog Destruction Alert!\n\nAre you sure you want to permanently delete "${itemName}" from registries?`)) return;

            ui(true);
            fetch('delete_product.php', {
                method: 'POST',
                body: new URLSearchParams({ id: itemId, csrf_token: csrfToken || '' }),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
            })
            .then(r => r.text())
            .then(data => {
                if (data.trim().includes('DELETION_SUCCESS') && targetRow) {
                    targetRow.style.transition = 'all 0.3s ease';
                    targetRow.style.background = '#fee2e2';
                    targetRow.style.opacity = '0';
                    setTimeout(() => { targetRow.remove(); sync('warehouse.php', false); }, 300);
                } else {
                    alert(`System Warning:\n${data.trim()}`);
                }
            })
            .catch(err => console.error("Deletion Engine Fault:", err))
            .finally(() => ui(false));
            return;
        }

        // Standard Navigation Interceptions
        const a = e.target.closest('a');
        if (!a) return;

        if ((a.closest('.sidebar') !== null || a.classList.contains('ajax-link')) && 
            !a.getAttribute('href')?.includes('logout.php') && !a.classList.contains('sidebar-direct-link')) {
            const tgt = a.getAttribute('data-target'); 
            if (tgt) { e.preventDefault(); sync(tgt); }
        }
    });

    // Deliberate KPI navigation: one click selects, double-click opens; Enter remains accessible.
    document.addEventListener('click', (e) => {
        const card = e.target.closest('.admin-kpi-double-link');
        if (!card) return;
        document.querySelectorAll('.admin-kpi-double-link.is-selected').forEach((item) => {
            if (item !== card) item.classList.remove('is-selected');
        });
        card.classList.add('is-selected');
    });

    document.addEventListener('dblclick', (e) => {
        const card = e.target.closest('.admin-kpi-double-link');
        if (!card) return;
        e.preventDefault();
        const target = card.getAttribute('data-target');
        if (target) sync(target);
    });

    document.addEventListener('keydown', (e) => {
        const card = e.target.closest('.admin-kpi-double-link');
        if (!card || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault();
        const target = card.getAttribute('data-target');
        if (target) sync(target);
    });
    // 6. Global data-mutation form post payload engine
    document.addEventListener('submit', async (e) => {
        // Respect validation or cancellation performed by the page-level form handler.
        if (e.defaultPrevented) return;
        const f = e.target; 
        
        // Target connector fallback matching the main viewport layout area
        const currentWorkspace = document.getElementById('dynamic-workspace') || document.querySelector('.main-content');
        if (!currentWorkspace) return; 

        // Backup actions return compact status messages, so handle them separately instead of
        // replacing the entire dashboard workspace with raw SUCCESS/ERROR response text.
        if (f.classList.contains('backup-action-form')) {
            e.preventDefault();
            const backupButton = e.submitter || f.querySelector('button[type="submit"]');
            const backupPayload = new FormData(f);
            const backupAction = f.getAttribute('action') || 'db_backup.php';

            if (backupButton) {
                backupButton.disabled = true;
                backupButton.classList.add('is-loading');
            }
            if (typeof ui === 'function') ui(true);

            try {
                const response = await fetch(backupAction, {
                    method: 'POST',
                    body: backupPayload,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = (await response.text()).trim();
                const separator = result.indexOf('|');
                const status = separator >= 0 ? result.slice(0, separator) : 'ERROR';
                const message = separator >= 0 ? result.slice(separator + 1) : 'Unexpected backup response.';

                alert(message);
                if (status === 'SUCCESS') {
                    const reloadBackupView = typeof sync === 'function' ? sync : window.syncWorkspaceView;
                    if (reloadBackupView) await reloadBackupView('db_backup.php', false);
                }
            } catch (backupError) {
                console.error('Backup action failed:', backupError);
                alert('The backup action could not be completed. Please try again.');
            } finally {
                if (backupButton) {
                    backupButton.disabled = false;
                    backupButton.classList.remove('is-loading');
                }
                if (typeof ui === 'function') ui(false);
            }
            return;
        }

        // CRITICAL BYPASS: Intercept staff updates or system purges cleanly before killing the event loop
        if (f.classList.contains('staff-row-update-form')) {
            console.log("Bypassing AJAX form payload engine to execute native registry updates.");
            return; // Exits immediately! Lets the form post and redirect naturally.
        }

        // Standard workflows intercept cleanly here to utilize fast dynamic loaders
        e.preventDefault();

        const fd = new FormData(f);
        const btn = e.submitter; 
        const act = f.getAttribute('action') || window.location.href;
        
        const explicitPost = ['edit_product.php', 'add_product.php', 'invoice_archiver.php'];
        const method = explicitPost.includes(act) ? 'POST' : (f.getAttribute('method')?.toUpperCase() || 'POST');
        
        if (typeof ui === 'function') ui(true); 

        if (btn?.name && !['filter_stack', 'compile_archive'].includes(btn.name)) {
            fd.set(btn.name, btn.value || '1');
        }

        try {
            if (method === 'GET') {
                // Resolve relative form actions from the current admin page, not from the website root.
                const url = new URL(act, window.location.href);
                for (const [key, val] of fd.entries()) {
                    url.searchParams.set(key, val);
                }
                const r = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) {
                    currentWorkspace.innerHTML = await r.text();
                    if (typeof executeInjectedScripts === 'function') executeInjectedScripts(currentWorkspace);
                } else {
                    // Never leave a failed filter looking like it succeeded with unchanged rows.
                    alert('The requested filter could not be applied. Please try again.');
                }
            } else {
                fd.set('ajax_request', '1');
                const r = await fetch(act, { 
                    method: 'POST', 
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const cType = r.headers.get('content-type') || '';
                const isArchiveBtn = btn?.id === 'compile-archive-btn' || btn?.name === 'compile_archive';

                if (cType.match(/zip|pdf|octet-stream/) && isArchiveBtn) {
                    // Extract file extensions safely from Content-Type headers
                    let extension = "pdf";
                    if (cType.includes("zip")) extension = "zip";
                    if (cType.includes("csv")) extension = "csv";
                    
                    const blob = await r.blob();
                    const dUrl = window.URL.createObjectURL(blob);
                    const dlAnchor = document.createElement('a');
                    dlAnchor.href = dUrl;
                    dlAnchor.download = `system_compiled_report_${Date.now()}.${extension}`;
                    document.body.appendChild(dlAnchor);
                    dlAnchor.click();
                    dlAnchor.remove();
                    window.URL.revokeObjectURL(dUrl); // Free up browser runtime memory allocations
                } else {
                    if (r.ok) {
                        currentWorkspace.innerHTML = await r.text();
                        // Re-executes page inline script behaviors upon updating content view models
                        if (typeof executeInjectedScripts === 'function') executeInjectedScripts(currentWorkspace);
                    }
                }
            }
        } catch (err) {
            console.error("Form Processing Error:", err);
        } finally {
            if (typeof ui === 'function') ui(false);
        }
    });

    // 7. BROWSER STEP CONTROLS POPSTATE SYNC: Handles clicking the back and forward arrows seamlessly
    window.addEventListener('popstate', (event) => {
        const runSync = typeof sync === 'function' ? sync : window.syncWorkspaceView;
        if (!runSync) return;

        if (event.state && event.state.targetActiveLayoutFile) {
            runSync(event.state.targetActiveLayoutFile, false);
        } else {
            const backgroundUrlParams = new URLSearchParams(window.location.search);
            const fallbackTargetView = backgroundUrlParams.get('view') || 'dashboard_overview.php';
            runSync(fallbackTargetView, false);
        }
    });

    // FIXED - 8. INITIAL LOAD INJECTOR MATRIX ROUTINE: Safely loads without looping or clearing manage_staff
    const runInitialSync = typeof sync === 'function' ? sync : window.syncWorkspaceView;
    if (activeWorkspaceState) {
        if (activeWorkspaceState === 'manage_staff.php') {
            // Freeze AJAX reloading lookups entirely on hard load so PHP variables draw natively
            if (typeof ui === 'function') ui(false);
            if (head) head.textContent = 'Manage Staff Network';
        } else if (runInitialSync) {
            runInitialSync(activeWorkspaceState, false);
        }
    } else if (runInitialSync) {
        runInitialSync('dashboard_overview.php', false);
    }
	
    // 9. GROUPED SIDEBAR ACCORDION
    const sidebarSections = Array.from(document.querySelectorAll('.sidebar-section'));

    function setSidebarSectionState(section, shouldOpen) {
        const toggle = section.querySelector('.sidebar-section-toggle');
        section.classList.toggle('is-open', shouldOpen);
        if (toggle) toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }

    function openSidebarSectionForTarget(target) {
        if (!target) return;
        const matchingLink = Array.from(document.querySelectorAll('.sidebar-section a')).find(link => {
            return link.dataset.target === target || link.getAttribute('href')?.includes('view=' + target);
        });
        if (!matchingLink) return;

        const matchingSection = matchingLink.closest('.sidebar-section');
        sidebarSections.forEach(section => setSidebarSectionState(section, section === matchingSection));
    }

    sidebarSections.forEach(section => {
        const toggle = section.querySelector('.sidebar-section-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function() {
            if (section.classList.contains('is-loading')) return;

            const willOpen = !section.classList.contains('is-open');
            section.classList.add('is-loading');
            toggle.disabled = true;
            toggle.setAttribute('aria-busy', 'true');

            window.setTimeout(() => {
                sidebarSections.forEach(otherSection => setSidebarSectionState(otherSection, false));
                setSidebarSectionState(section, willOpen);

                window.setTimeout(() => {
                    section.classList.remove('is-loading');
                    toggle.disabled = false;
                    toggle.removeAttribute('aria-busy');
                }, 280);
            }, 120);
        });
    });

    openSidebarSectionForTarget(activeWorkspaceState || 'dashboard_overview.php');
    // 9. SIDEBAR MECHANISM ACTION CONTROLLER: TOGGLE COMPACT STATE PERSISTENCE
    const sidebarPanel = document.getElementById('mainSidebarPanel');
    const toggleCollapseBtn = document.getElementById('sidebarToggleCollapseBtn');
    const arrowIconSymbol = document.getElementById('toggleButtonArrowIconSymbol');
    const mobileMenuToggle = document.getElementById('adminMobileMenuToggle');

    function setAdminMobileMenuState(shouldOpen) {
        if (!sidebarPanel || !mobileMenuToggle) return;
        sidebarPanel.classList.toggle('mobile-open', shouldOpen);
        mobileMenuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        const mobileLabel = mobileMenuToggle.querySelector('.admin-mobile-menu-label');
        if (mobileLabel) mobileLabel.textContent = shouldOpen ? 'Close' : 'Menu';
        document.body.classList.toggle('admin-mobile-menu-open', shouldOpen);
    }

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            setAdminMobileMenuState(!sidebarPanel.classList.contains('mobile-open'));
        });

        sidebarPanel.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.matchMedia('(max-width: 768px)').matches) {
                    setAdminMobileMenuState(false);
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') setAdminMobileMenuState(false);
        });

        window.addEventListener('resize', function() {
            if (!window.matchMedia('(max-width: 768px)').matches) {
                setAdminMobileMenuState(false);
            }
        });
    }

    if (sidebarPanel && toggleCollapseBtn && arrowIconSymbol) {
        const isSidebarCondensedCached = localStorage.getItem('adonak_sidebar_condensed') === 'true';
        
        if (isSidebarCondensedCached) {
            sidebarPanel.classList.add('condensed');
            arrowIconSymbol.innerText = '▶';
        }

        toggleCollapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const isCurrentlyCondensed = sidebarPanel.classList.toggle('condensed');
            arrowIconSymbol.innerText = isCurrentlyCondensed ? '▶' : '◀';
            localStorage.setItem('adonak_sidebar_condensed', isCurrentlyCondensed);
        });
    }
});
//global override function
function fireOutsideOverride() {
    // Submit the selected active timecard ID; the server obtains the actual shift type.
    const attendanceField = document.getElementById('outsideAttendanceId');
    if (!attendanceField || !attendanceField.value) return;

    const dataPayload = new FormData();
    dataPayload.append('shift_action', 'clock_out');
    dataPayload.append('attendance_id', attendanceField.value);

    // Call the dedicated standalone worker script file directly!
    fetch('process_shift_override.php', {
        method: 'POST',
        body: dataPayload
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        
        // Refresh the current view tab layout seamlessly using your AJAX container engine rules
        const activeLink = document.querySelector('.sidebar a.active, .sidebar a.ajax-link.active');
        if (activeLink) {
            activeLink.click(); 
        } else {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network connection or worker execution exception occurred.');
    });
}
</script>

<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/session-idle.js"></script>
</body>
</html>
