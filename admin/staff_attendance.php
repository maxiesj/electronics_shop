<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../db.php'; 

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically while dodging header warnings
if (!verifyWorkspaceClearance('manage_staff.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; $err = "";

// 1. PROCESS ADMIN FORCE PUNCH-OUT OVERRIDES
if (isset($_POST['terminate_shift'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $current_time = date('Y-m-d H:i:s');
    
    // Lock down trailing ghost shifts instantly
    $term_stmt = $conn->prepare("UPDATE staff_attendance SET clock_out_time = ?, shift_status = 'Force Closed' WHERE id = ? AND shift_status = 'Active'");
    $term_stmt->bind_param("si", $current_time, $attendance_id);
    
    if ($term_stmt->execute()) {
        $term_stmt->close();
        
        // Log the security override into the primary activity outbox tracker
        $log_details = "Workforce Security Override: Administrator manually terminated active timecard sheet row reference ID #{$attendance_id} to block ghost hours.";
        $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
        $audit->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details); 
        $audit->execute();
        $audit->close();
        
        // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside dashboard templates
        echo "<script>window.location.href = 'dashboard.php?view=staff_attendance.php&msg=terminated';</script>"; 
        exit();
    } else { 
        $err = "Database Error: Failed to execute administrative override loop."; 
        $term_stmt->close();
    }
}

// 2. FETCH ACTIVE AND COMPLETED SHIFTS FROM REGISTRIES
$active_shifts = $conn->query("SELECT id, user_id, staff_name, clock_in_time, ip_address FROM staff_attendance WHERE shift_status = 'Active' ORDER BY id DESC");
$historical_shifts = $conn->query("SELECT id, staff_name, clock_in_time, clock_out_time, shift_status, ip_address FROM staff_attendance WHERE shift_status != 'Active' ORDER BY id DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg"><title>Shift Attendance Control</title>
  <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; transition: all 0.2s ease; }
    body { background-color: #f8fafc; margin: 0; display: flex; color: #1e293b; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #0f172a; color: white; padding: 25px 20px; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; border-bottom: 1px solid #1e293b; padding-bottom: 15px; font-size: 18px; margin-top: 0; color: #f8fafc; }
    .sidebar a { display: block; color: #94a3b8; padding: 12px 16px; text-decoration: none; font-size: 14px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #1e293b; color: #3b82f6; font-weight: bold; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 40px; max-width: 1250px; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .grid-split { display: grid; grid-template-columns: 1fr; gap: 25px; }
    .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.01); box-sizing: border-box; }
    h2 { margin-top: 0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    /* 4. Tabular Summary Listing Records */
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 14px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
    th { background-color: #f8fafc; color: #475569; text-transform: uppercase; font-size: 11px; font-weight: 700; }
    tr:hover td { background-color: #fcfcfc; }
    
    /* System Interaction Badges */
    .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; white-space: nowrap; }
    .status-completed { background: #e6fcf5; color: #0ca678; }
    .status-forceclosed { background: #fff5f5; color: #e03131; border: 1px solid #ffe3e3; }
    
    /* Contract Termination Action Button Component */
    .btn-terminate { background-color: #fee2e2; color: #ef4444; border: 1px solid #fecaca; padding: 6px 12px; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background-color 0.2s, color 0.2s; height: 30px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; white-space: nowrap; box-sizing: border-box; }
    .btn-terminate:hover { background-color: #ef4444; color: white; }

    /* ==========================================================================
       5. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media screen and (max-width: 1024px) {
        .main-content { padding: 24px; }
        .card { padding: 20px; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS BREAKPOINT (Max 992px Width Viewports) */
    @media screen and (max-width: 992px) {
        /* Convert desktop layout from flex-row to vertical stacked blocks */
        body { flex-direction: column; }
        
        /* Turn the fixed left sidebar menu into a standard top navigation header bar */
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .sidebar h2 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #1e293b; }
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows sets */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout options */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu tabs */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Card boundary padding adjustments */
        .main-content { padding: 12px; }
        .card { padding: 16px; margin-bottom: 16px; }
        h2 { font-size: 1rem; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; font-size: 13px; }
        
        /* Maximize click targets for touch screens */
        .btn-terminate { height: 36px; padding: 0 14px; font-size: 12px; display: inline-flex; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>ADONAK Admin</h2>
        <a href="dashboard.php">📦 Dashboard Overview</a>
        <a href="manage_orders.php">📊 Manage Sales Orders</a>
        <a href="manage_categories.php">📁 Categories & Brands</a>
        <a href="low_stock_monitor.php">⚠️ Warehouse Stock Monitor</a>
        <a href="low_stock_dispatcher.php">📨 Low Stock Dispatcher</a>
        <a href="sales_analytics.php">📈 Financial Analytics</a>
        <a href="invoice_batch_archiver.php">🗄️ Invoice PDF Archiver</a>
        <a href="staff_attendance.php" class="active">🕒 Worker Attendance</a>
        <a href="db_backup.php">💾 Core Data Backup</a>
        <a href="staff_tracker.php">🛡️ Workspace Tracker</a>
        <a href="manage_staff.php">👥 Manage Staff Network</a>
        <a href="../logout.php" style="background:#ef4444; color:white; text-align:center; font-weight:bold; margin-top:20px; display:block; padding:10px; border-radius:6px; text-decoration:none;">Logout</a>
    </div>

    <div class="main-content">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'terminated'): ?>
            <div style="background:#fff5f5; color:#c92a2a; padding:12px; border-radius:8px; margin-bottom:25px; font-size:14px; font-weight:bold; border:1px solid #ffe3e3;">
                ✓ Access Locked: Trailing employee shift successfully closed out and logged to track history.
            </div>
        <?php endif; ?>

        <div class="grid-split">
            <!-- SELECTION A: REAL-TIME ON-SHIFT WORKERS REGISTER -->
            <div class="card">
                <h2>🟢 Currently On-Shift Workforce Cards</h2>
                <table>
                    <thead>
                        <tr><th>Operator Employee</th><th>Clock-In Timestamp</th><th>Secure IP Allocation</th><th>Operational Duration</th><th>Security Actions Control</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($active_shifts && $active_shifts->num_rows > 0): while($row = $active_shifts->fetch_assoc()): 
                            $hours_elapsed = round((time() - strtotime($row['clock_in_time'])) / 3600, 1);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['staff_name']); ?></strong><br><small style="color:#64748b;">User ID reference: #<?php echo $row['user_id']; ?></small></td>
                                <td style="color:#475569; font-weight:500; font-size:13px;"><?php echo date('d M Y - h:i A', strtotime($row['clock_in_time'])); ?></td>
                                <td><code><?php echo htmlspecialchars($row['ip_address']); ?></code></td>
                                <td style="font-weight:bold; color:<?php echo ($hours_elapsed > 9) ? '#ef4444':'#10b981'; ?>;"><?php echo $hours_elapsed; ?> Hours Worked</td>
                                <td>
                                    <form method="POST" action="staff_attendance.php" style="margin:0;">
                                        <input type="hidden" name="attendance_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="terminate_shift" class="btn-terminate" onclick="return confirm('CRITICAL TIMECARD OVERRIDE: Force immediate punch-out for this operator?');">
                                            🛑 Force Punch-Out Clock Lock
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">No operational staff profiles are active on the fulfillment floors right now.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- SELECTION B: TIME CARD LOGS HISTORY INDEX -->
            <div class="card">
                <h2>📋 Historical Clock-Out Ledger Stream</h2>
                <table>
                    <thead>
                        <tr><th>Operator Identity</th><th>Punch-In Time</th><th>Punch-Out Time</th><th>Secure IP</th><th>Clearing Mode State</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($historical_shifts && $historical_shifts->num_rows > 0): while($row = $historical_shifts->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['staff_name']); ?></strong></td>
                                <td style="color:#64748b; font-size:13px;"><?php echo date('d M', strtotime($row['clock_in_time'])); ?> | <?php echo date('h:i A', strtotime($row['clock_in_time'])); ?></td>
                                <td style="color:#64748b; font-size:13px;"><?php echo date('d M', strtotime($row['clock_out_time'])); ?> | <?php echo date('h:i A', strtotime($row['clock_out_time'])); ?></td>
                                <td><code><?php echo htmlspecialchars($row['ip_address']); ?></code></td>
                                <td><span class="badge status-<?php echo str_replace(' ', '', $row['shift_status']); ?>"><?php echo $row['shift_status']; ?></span></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">No historical shift cards archived inside local drive partitions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
