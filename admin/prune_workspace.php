<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('db_backup.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $error = "";
$purged_files_count = 0;

// 2. RUN AUTONOMOUS DIRECTORY ITERATOR SCAN OPERATIONS
if (isset($_POST['execute_prune'])) {
    // Set target scan boundaries to your root project workspace folder
    $target_directory = realpath(__DIR__ . '/../'); 
    
    if ($target_directory && is_dir($target_directory)) {
        // Safe directory lookup filter configuration array
        $dir_iterator = new RecursiveDirectoryIterator($target_directory, RecursiveDirectoryIterator::SKIP_DOTS);
        $file_iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::CHILD_FIRST);
        
        foreach ($file_iterator as $file) {
            // Strict filtering rule: Only target temporary logs or loose text debug dump scraps
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                $filename = $file->getFilename();
                
                // Protect core operational project assets. Do NOT delete template instructions.
                if (($ext === 'log' || $ext === 'txt') && $filename !== 'requirements.txt' && $filename !== 'kra_instructions.txt') {
                    if (unlink($file->getRealPath())) {
                        $purged_files_count++;
                    }
                }
            }
        }
        
        // Log this cleanup event right inside your secure Workspace Activity Tracker
        if ($purged_files_count > 0) {
            $log_details = "Directory Optimization Maintenance Completed: Successfully scanned directory trees and permanently purged {$purged_files_count} stale cache/temporary debug text log files from local drive sectors.";
            $audit_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Inventory Update', ?)");
            $audit_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside dashboard templates
            echo "<script>window.location.href = 'dashboard.php?view=prune_workspace.php&msg=success&count=" . $purged_files_count . "';</script>"; 
            exit();
        } else {
            $msg = "Scan Complete: Your local folder trees are already 100% optimized. No trailing debug log text fragments were detected.";
        }
    } else { $error = "System Error: Project absolute path coordinates could not be mapped securely."; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Workspace Pruning - ADONAK ELECTRONICS</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; transition: all 0.2s ease; }
    body { background-color: #f8fafc; margin: 0; display: flex; color: #1e293b; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #0f172a; color: white; padding: 25px 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; scrollbar-width: thin; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; border-bottom: 1px solid #1e293b; padding-bottom: 15px; font-size: 18px; margin-top: 0; color: #f8fafc; }
    .sidebar a { display: block; color: #94a3b8; padding: 12px 16px; text-decoration: none; font-size: 14px; font-weight: 500; margin-bottom: 4px; border-radius: 6px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #1e293b; color: #3b82f6; font-weight: bold; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; min-height: 100vh; max-width: 800px; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 35px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.01); text-align: center; box-sizing: border-box; }
    h2 { margin-top: 0; color: #0f172a; font-size: 22px; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
    
    /* 4. Action Utility Buttons & Notification Layouts */
    .btn-prune { background-color: #ef4444; color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; box-shadow: 0 4px 10px rgba(239,68,68,0.25); display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 48px; box-sizing: border-box; transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s; }
    .btn-prune:hover { background-color: #dc2626; transform: translateY(-1px); box-shadow: 0 6px 15 rgba(239,68,68,0.35); }
    .btn-prune:active { transform: translateY(0); }
    
    /* Information Callout Banner */
    .alert-info { background: #e0f2fe; color: #0369a1; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; margin-bottom: 20px; border: 1px solid #bae6fd; box-sizing: border-box; text-align: left; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 TRANSITIONAL TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar menu into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #1e293b; }
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout options */
        .main-content { margin-left: 0; width: 100%; padding: 24px; min-height: auto; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu tabs */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Card boundary adjustments */
        .main-content { padding: 12px; }
        .card { padding: 24px 16px; margin-bottom: 16px; }
        h2 { font-size: 1.25rem; }
        .alert-info { font-size: 13px; padding: 12px; }
        
        /* Maximize target boundaries for thumb tap operations */
        .btn-prune { width: 100%; height: 52px; font-size: 16px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="manage_layaways.php">🇰🇪 Manage Layaway</a>
        <a href="manage_categories.php">📁 Categories & Brands</a>
        <a href="low_stock_monitor.php">⚠️ Warehouse Stock Monitor</a>
        <a href="layaway_defaulters.php">⏳ Installment Defaulters</a>
        <a href="mpesa_checker.php">📲 M-Pesa Code Checker</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="db_backup.php">💾 Core Data Backup</a>
        <a href="prune_workspace.php" class="active">🧹 Prune Cache Scraps</a>
        <a href="staff_tracker.php">🛡️ Workspace Tracker</a>
        <a href="manage_staff.php">👥 Manage Staff Network</a>
        <a href="../logout.php" style="background:#ef4444; color:white; text-align:center; margin-top:20px; border-radius:6px; display:block; padding:10px;">Logout</a>
    </div>

    <div class="main-content">
        <div class="card" style="margin-top: 40px;">
            <div style="font-size: 50px; margin-bottom: 15px;">🧹</div>
            <h2>Project Directory Maintenance Desk</h2>
            <p style="color:#64748b; font-size:14px; line-height:1.6; max-width:550px; margin: 10px auto 25px auto;">
                Triggering this operational scan command commands the backend to safely cycle through your local project files folder trees, identifying and clearing any loose, non-essential temporary <code>.txt</code> notes or trailing <code>.log</code> files.
            </p>
            
            <?php if (!empty($msg)): ?><div class="alert-info">💡 <?php echo $msg; ?></div><?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="alert-info" style="background:#f0fdf4; color:#166534; border-color:#bbf7d0;">
                    ✓ Optimization Complete: Cleaned out <strong><?php echo intval($_GET['count']); ?></strong> trailing temporary cache files from your server folders!
                </div>
            <?php endif; ?>
            
            <form method="POST" action="prune_workspace.php">
                <button type="submit" name="execute_prune" class="btn-prune" onclick="return confirm('Initiate background workspace optimization sweep?');">
                    🧹 Execute System Directory Pruning Sweep
                </button>
            </form>
        </div>
    </div>

</body>
</html>
