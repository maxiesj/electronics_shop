<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../ShiftValidator.php';

if (!verifyWorkspaceClearance('staff_dashboard.php')) {
    header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}

// Safely extract session variables into local context variables
$user_id = $_SESSION['user_id'] ?? $_SESSION['staff_id'];
$staff_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Workspace Operator';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (empty($_SESSION['staff_shift_csrf'])) {
    $_SESSION['staff_shift_csrf'] = bin2hex(random_bytes(32));
}


// --- PRIVILEGE CACHING ENGINE ---
$my_privileges = [];
$priv_stmt = $conn->prepare("SELECT DISTINCT TRIM(target_view) AS view_file FROM staff_permissions WHERE user_id = ?");
$priv_stmt->bind_param("i", $user_id);
$priv_stmt->execute();
$priv_res = $priv_stmt->get_result();
while ($p_row = $priv_res->fetch_assoc()) {
    $my_privileges[] = $p_row['view_file'];
}
$priv_stmt->close();

// Super Administrator status comes only from the authoritative users -> roles relationship.
$isSuperAdmin = getAuthenticatedWorkspaceRole() === 'super_admin';

$staff_quick_actions = [
    'manage_orders.php' => 'Process Orders',
    'low_stock_monitor.php' => 'Check Stock',
    'layaway_defaulters.php' => 'Installment Alerts',
    'manage_customers.php' => 'Customers',
];
$visible_quick_actions = [];
foreach ($staff_quick_actions as $target => $label) {
    if ($isSuperAdmin || in_array($target, $my_privileges, true)) {
        $visible_quick_actions[$target] = $label;
    }
}

$msg = $_SESSION['shift_success_flash'] ?? '';
unset($_SESSION['shift_success_flash']);
$err = '';
// --- WORKFORCE ATTENDANCE ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_shift'])) {
    $action = strtolower(trim((string)($_POST['shift_action'] ?? '')));
    $shift_type = strtolower(trim((string)($_POST['shift_type'] ?? 'regular')));
    $allowed_actions = ['clock_in', 'clock_out'];
    $allowed_shift_types = ['regular', 'night', 'short_coverage'];

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['staff_shift_csrf'], (string)$_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'This page expired. Refresh it and try again.';
        header('Location: staff_dashboard.php');
        exit;
    }
    if (!in_array($action, $allowed_actions, true) || !in_array($shift_type, $allowed_shift_types, true)) {
        $_SESSION['error_flash'] = 'Select a valid shift action and shift type.';
        header('Location: staff_dashboard.php');
        exit;
    }
    
    // Set standard runtime timezone context matching your ShiftValidator layout parameters
    date_default_timezone_set('Africa/Nairobi');
    $currentDateTimeStr = date('Y-m-d H:i:s'); // Secure full format baseline
    
    if ($action === 'clock_in') {
        
        // Validate clock-in using our ShiftValidator Class with complete timestamp contexts
        $validation = ShiftValidator::validateClockIn($currentDateTimeStr, $shift_type, $isSuperAdmin);
        
        if ($validation['status']) {
            // FIX: Added shift_type to the SQL parameters to store the operational selection state
            $conn->begin_transaction();
            try {
                $user_lock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
                if (!$user_lock) throw new RuntimeException('Unable to prepare the operator lock.');
                $user_lock->bind_param('i', $user_id);
                if (!$user_lock->execute() || !$user_lock->get_result()->fetch_assoc()) throw new RuntimeException('Operator account was not found.');
                $user_lock->close();
                $active_check = $conn->prepare("SELECT id FROM staff_attendance WHERE user_id = ? AND shift_status = 'Active' LIMIT 1");
                if (!$active_check) throw new RuntimeException('Unable to check the active shift.');
                $active_check->bind_param('i', $user_id);
                if (!$active_check->execute()) throw new RuntimeException('Unable to check the active shift.');
                $already_active = $active_check->get_result()->fetch_assoc();
                $active_check->close();
                if ($already_active) throw new RuntimeException('An active shift already exists for this operator.');

            $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, staff_name, clock_in_time, ip_address, shift_status, shift_type) VALUES (?, ?, NOW(), ?, 'Active', ?)");
            if (!$stmt) throw new RuntimeException('Unable to prepare the shift start.');
            $stmt->bind_param('isss', $user_id, $staff_name, $ip_address, $shift_type);
            if (!$stmt->execute()) throw new RuntimeException('Unable to start the shift.');
            $stmt->close();

            $details = "Shift started: {$shift_type} shift from IP {$ip_address}.";
            $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
            if (!$audit) throw new RuntimeException('Unable to prepare the shift audit record.');
            $audit->bind_param('iss', $user_id, $staff_name, $details);
            if (!$audit->execute()) throw new RuntimeException('Unable to save the shift audit record.');
            $audit->close();
            $conn->commit();
            $_SESSION['staff_shift_csrf'] = bin2hex(random_bytes(32));
                $_SESSION['shift_success_flash'] = $validation['message'] . " Shift initialized successfully!";
                header('Location: staff_dashboard.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Shift start failed: ' . $e->getMessage());
                $err = $e->getMessage();
            }
        } else {
            // Flash variable error mapping to display on dashboard notification banners
            $_SESSION['error_flash'] = $validation['message'];
            header("Location: staff_dashboard.php");
            exit;
        }
        
    } elseif ($action === 'clock_out') {
        $conn->begin_transaction();
        // Fetch current active clock-in time entry from database to check duration boundaries
        // FIX: Included 'id' in selection to target the exact row for updating later
        $time_stmt = $conn->prepare("SELECT id, clock_in_time, shift_type FROM staff_attendance WHERE user_id = ? AND shift_status = 'Active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $time_stmt->bind_param("i", $user_id);
        $time_stmt->execute();
        $active_shift_record = $time_stmt->get_result()->fetch_assoc();
        $time_stmt->close();
        
        if ($active_shift_record) {
            $attendance_id = $active_shift_record['id']; // Unique record Row ID
            $clockInTimeStr = $active_shift_record['clock_in_time'];
            $active_shift_type = $active_shift_record['shift_type'] ?? $shift_type;
            
            // Validate entire duration metrics and closing window constraints using tracked assignment parameter
            $validation = ShiftValidator::validateClockOut($clockInTimeStr, $currentDateTimeStr, $active_shift_type, $isSuperAdmin);
            
            if ($validation['status']) {
                // FIX: Targeted row via primary key 'id' instead of relying on unsafe multi-where limit updates
                try {
                    $stmt = $conn->prepare("UPDATE staff_attendance SET clock_out_time = NOW(), shift_status = 'Completed' WHERE id = ? AND shift_status = 'Active'");
                    if (!$stmt) throw new RuntimeException('Unable to prepare the shift closure.');
                    $stmt->bind_param('i', $attendance_id);
                    if (!$stmt->execute()) throw new RuntimeException('Unable to conclude the shift.');
                    if ($stmt->affected_rows !== 1) throw new RuntimeException('The active shift was already concluded.');
                    $stmt->close();

                    $details = "Shift concluded: attendance #{$attendance_id}, type {$active_shift_type}.";
                    $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                    if (!$audit) throw new RuntimeException('Unable to prepare the shift audit record.');
                    $audit->bind_param('iss', $user_id, $staff_name, $details);
                    if (!$audit->execute()) throw new RuntimeException('Unable to save the shift audit record.');
                    $audit->close();
                    $conn->commit();
                    $_SESSION['staff_shift_csrf'] = bin2hex(random_bytes(32));
                    $_SESSION['shift_success_flash'] = $validation['message'] . " Shift concluded successfully. You may now start the next shift.";
                    header('Location: staff_dashboard.php?shift=ready');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Shift closure failed: ' . $e->getMessage());
                    $err = $e->getMessage();
                }
            } else {
                $_SESSION['error_flash'] = $validation['message'];
                $conn->rollback();
                header("Location: staff_dashboard.php");
                exit;
            }
        } else {
            $err = "Error: No active 'Active' shift instance found to conclude.";
            $conn->rollback();
        }
    }
}

// Only an unresolved active attendance row represents the current shift.
// Completed rows remain in attendance history and must not block the next shift.
$att_check = $conn->prepare("
    SELECT * FROM staff_attendance
    WHERE user_id = ?
    AND shift_status = 'Active'
    ORDER BY id DESC LIMIT 1
");
$att_check->bind_param("i", $user_id);
$att_check->execute();
$current_shift = $att_check->get_result()->fetch_assoc();
$att_check->close();

// Realized revenue follows the shared settlement definition: completed payments
// must cover the order total and the latest installment balance must be cleared.
$q_sales = "SELECT COALESCE(SUM(s.total_amount), 0.00) AS realized_total
    FROM (
        SELECT o.id, o.total_amount, COALESCE(p.paid_total, 0.00) AS paid_total,
            COALESCE((SELECT lp.balance_remaining FROM layaway_plans lp WHERE lp.order_id = o.id ORDER BY lp.id DESC LIMIT 1), 0.00) AS plan_balance
        FROM orders o
        LEFT JOIN (
            SELECT order_id, SUM(amount) AS paid_total
            FROM payments
            WHERE LOWER(TRIM(payment_status)) = 'completed'
            GROUP BY order_id
        ) p ON p.order_id = o.id
        WHERE LOWER(TRIM(o.order_status)) <> 'cancelled'
    ) s
    WHERE GREATEST(GREATEST(s.total_amount - s.paid_total, 0), GREATEST(s.plan_balance, 0)) <= 0.009";
$res_sales = $conn->query($q_sales);
$sales_row = $res_sales ? $res_sales->fetch_assoc() : [];
$total_sales = (float)($sales_row['realized_total'] ?? 0.00);

// Orders still waiting for payment or an operator decision.
$q_orders = "SELECT COUNT(*) FROM orders WHERE LOWER(order_status) = 'pending'";
$res_orders = $conn->query($q_orders);
$orders_row = $res_orders ? $res_orders->fetch_row() : [];
$pending_orders_count = (int)($orders_row[0] ?? 0);

// Active installment plans must still have a balance and a non-cancelled order.
$q_layaway = "SELECT COUNT(*) FROM layaway_plans lp JOIN orders o ON o.id = lp.order_id
    WHERE LOWER(TRIM(lp.status)) = 'active' AND lp.balance_remaining > 0.009
    AND LOWER(TRIM(o.order_status)) <> 'cancelled'";
$res_layaway = $conn->query($q_layaway);
$layaway_row = $res_layaway ? $res_layaway->fetch_row() : [];
$active_layaways_count = (int)($layaway_row[0] ?? 0);

// Match the shared low-stock monitor threshold: fewer than five units.
$q_stock = "SELECT COUNT(*) FROM products WHERE stock_quantity < 5";
$res_stock = $conn->query($q_stock);
$stock_row = $res_stock ? $res_stock->fetch_row() : [];
$low_stock_count = (int)($stock_row[0] ?? 0);

// Show stored payment state without claiming external network verification.
$q_recent = "SELECT p.*, o.user_id FROM payments p LEFT JOIN orders o ON p.order_id = o.id ORDER BY p.id DESC LIMIT 5";
$recent_result = $conn->query($q_recent);
$recent_payments = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <!-- CRITICAL MOBILE VIEWPORT ENGINE TRIGGER: Snaps layout to 100% native mobile resolution -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta charset="UTF-8">
    <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <title>Staff Control Dashboard | ADONAK ELECTRONICS</title>

   <style>
    /* ==========================================================================
       1. GLOBAL BASELINE RESET STYLES
       ========================================================================== */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* ==========================================================================
       2. NAVIGATION HEADER SECTION LAYOUT COMPONENTS
       ========================================================================== */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; z-index: 50; }
    .nav-brand { font-weight: 800; font-size: 1.25rem; color: #f97316; white-space: nowrap; }
    .nav-center-links { display: flex; gap: 16px; font-size: 0.875rem; font-weight: 600; align-items: center; }
    .nav-center-links a { color: #d1d5db; text-decoration: none; padding: 6px 12px; border-radius: 4px; transition: background 0.2s, color 0.2s; white-space: nowrap; }
    .nav-center-links a:hover, .nav-center-links a.active { color: white; background-color: #1f2937; }
    
    /* Session Meta & Exit Controls */
    .nav-right-meta { display: flex; align-items: center; gap: 20px; font-size: 0.875rem; }
    .logout-btn { color: #f87171 !important; text-decoration: none; font-weight: 700; border: 1px solid #7f1d1d; padding: 6px 12px; border-radius: 6px; background-color: rgba(153, 27, 27, 0.12); white-space: nowrap; transition: background 0.2s, color 0.2s; }
    .logout-btn:hover { background-color: rgba(153, 27, 27, 0.25); color: #fca5a5 !important; }
    
    /* ==========================================================================
       3. CORE STRUCTURAL DASHBOARD FRAMEWORK (DEFAULT DESKTOP VIEW)
       ========================================================================== */
    main { max-width: 80rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    .welcome-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
    .welcome-title { font-size: 1.75rem; font-weight: 900; color: #111827; margin: 0; letter-spacing: -0.025em; }
    .welcome-meta { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; white-space: nowrap; }

    /* Attendance Interactive Card Block UI Parameters */
    .attendance-banner { padding: 16px 20px; border-radius: 0.75rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; font-size: 0.875rem; font-weight: 700; border: 1px solid; gap: 16px; box-sizing: border-box; }
    .att-off-duty { background-color: #fef2f2; color: #991b1b; border-color: #fca5a5; }
    .att-on-duty { background-color: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .att-completed { background-color: #f8fafc; color: #475569; border-color: #cbd5e1; }
    .shift-action-btn { border: none; font-weight: 800; padding: 8px 16px; border-radius: 6px; font-size: 11px; text-transform: uppercase; cursor: pointer; color: white; white-space: nowrap; height: 32px; display: inline-flex; align-items: center; transition: background-color 0.2s; }
    .btn-clock-in { background-color: #059669; } .btn-clock-in:hover { background-color: #047857; }
    .btn-clock-out { background-color: #dc2626; } .btn-clock-out:hover { background-color: #b91c1c; }

    /* 4-Column Analytical Metrics Grid layout */
    .metrics-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; margin-bottom: 40px; }
    .metric-card { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .metric-label { font-size: 10px; color: #9ca3af; font-weight: 700; text-transform: uppercase; margin: 0; letter-spacing: 0.05em; }
    .metric-val { font-size: 1.75rem; font-weight: 900; margin: 6px 0 0; letter-spacing: -0.025em; color: #111827; }
    /* Staff KPI semantic palette aligned with the Admin overview. */
    .staff-pending-card { position: relative; overflow: hidden; background: linear-gradient(135deg,#fffbeb,#fef3c7); border-color:#fde68a; border-left:4px solid #d97706; }
    .staff-pending-card .metric-label { color:#92400e; } .staff-pending-card .metric-val { color:#78350f !important; }
    .staff-layaway-card { position: relative; overflow: hidden; background: linear-gradient(135deg,#faf5ff,#ede9fe); border-color:#ddd6fe; border-left:4px solid #6d28d9; }
    .staff-layaway-card .metric-label { color:#6b21a8; } .staff-layaway-card .metric-val { color:#581c87 !important; }
    .staff-stock-card { position: relative; overflow: hidden; color:#fff; background: linear-gradient(135deg,#9a3412 0%,#c2410c 55%,#ea580c 100%); border-color:rgba(255,237,213,.24); border-left:4px solid #fdba74; box-shadow:0 12px 26px rgba(154,52,18,.24); }
    .staff-stock-card::after { content:''; position:absolute; width:100px; height:100px; right:-45px; top:-55px; border-radius:50%; background:rgba(255,255,255,.10); }
    .staff-stock-card .metric-label { position:relative; z-index:1; color:#ffedd5; } .staff-stock-card .metric-val { position:relative; z-index:1; color:#fff !important; }
    .staff-pending-card, .staff-layaway-card, .staff-stock-card { transition:transform .2s ease,box-shadow .2s ease; }
    .staff-pending-card:hover, .staff-layaway-card:hover, .staff-stock-card:hover { transform:translateY(-3px); box-shadow:0 14px 28px rgba(15,23,42,.11); }
    /* Premium payment-card-inspired revenue summary (no payment-network branding). */
    .revenue-card { position: relative; min-height: 165px; padding: 22px 24px !important; overflow: hidden; color: #ffffff; border: 1px solid rgba(255,255,255,0.16); background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 52%, #2563eb 100%); box-shadow: 0 14px 30px rgba(30,58,138,0.22); isolation: isolate; }
    .revenue-card::before, .revenue-card::after { content: ''; position: absolute; border-radius: 50%; pointer-events: none; z-index: -1; }
    .revenue-card::before { width: 190px; height: 190px; right: -85px; top: -105px; background: rgba(255,255,255,0.10); }
    .revenue-card::after { width: 125px; height: 125px; right: 26px; bottom: -92px; border: 25px solid rgba(249,115,22,0.13); }
    .revenue-card-top, .revenue-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; position: relative; z-index: 1; }
    .revenue-card .metric-label { color: #bfdbfe; letter-spacing: 0.11em; }
    .revenue-contactless { color: #dbeafe; font-size: 17px; letter-spacing: -4px; transform: rotate(-12deg); padding-right: 4px; }
    .revenue-chip { width: 38px; height: 28px; margin: 17px 0 10px; border-radius: 6px; border: 1px solid rgba(120,86,18,0.35); background: linear-gradient(135deg,#fef3c7,#d6a94d 48%,#fff2b2); box-shadow: inset 0 0 0 1px rgba(255,255,255,0.38); position: relative; }
    .revenue-chip::before, .revenue-chip::after { content: ''; position: absolute; background: rgba(113,78,17,0.32); }
    .revenue-chip::before { width: 1px; height: 100%; left: 50%; top: 0; }
    .revenue-chip::after { height: 1px; width: 100%; left: 0; top: 50%; }
    .revenue-amount { margin: 0; color: #ffffff; font-size: clamp(1.35rem,2vw,1.8rem); font-weight: 900; letter-spacing: -0.035em; text-shadow: 0 2px 8px rgba(2,6,23,0.24); white-space: nowrap; }
    .revenue-card-footer { margin-top: 13px; color: #bfdbfe; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
    .revenue-card-brand { color: #ffffff; letter-spacing: 0.08em; }

    /* Tabular Summary Listings Components Layout */
    .layout-split { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 24px; }
    .content-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    .block-title { font-size: 0.875rem; font-weight: 900; color: #374151; text-transform: uppercase; margin: 0 0 16px; letter-spacing: 0.05em; }
    
    table { width: 100%; border-collapse: collapse; font-size: 0.813rem; text-align: left; }
    th { background-color: #f9fafb; color: #4b5563; padding: 10px 12px; font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; }
    .status-pill { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; display: inline-block; white-space: nowrap; }
    .status-success { background-color: #d1fae5; color: #065f46; }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-refunded { background-color: #ede9fe; color: #6d28d9; }
    .status-failed { background-color: #fee2e2; color: #b91c1c; }

    /* Processing Loader Overlay Styles */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       4. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media (max-width: 1024px) {
        main { margin: 24px auto; }
        .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS & SMARTPHONES BREAKPOINT (Max 768px Viewports) */
    @media (max-width: 768px) {
        /* FIXED TOP NAVIGATION BAR: Freezes your header firmly to the top edge of the mobile screen */
        nav { 
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: auto !important;
            flex-direction: column !important; 
            gap: 10px !important; 
            padding: 14px 16px !important; 
            text-align: center !important; 
            z-index: 9999 !important; /* Forces dashboard elements to pass underneath the menu */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15) !important;
            border-bottom: 2px solid #1f2937 !important;
        }
        
        .nav-center-links { 
            display: flex !important;
            flex-direction: row !important;
            gap: 6px !important; 
            flex-wrap: wrap !important; /* Allows your navigation links to stack into two neat rows */
            justify-content: center !important; 
            width: 100% !important; 
        }
        .nav-center-links a { 
            font-size: 0.8rem !important; 
            padding: 6px 10px !important; 
            flex-shrink: 0 !important; /* Stops the browser from shrinking buttons */
        }
        .nav-right-meta { 
            width: 100% !important; 
            justify-content: center !important; 
            border-top: 1px solid #374151 !important; 
            padding-top: 10px !important; 
            margin-top: 2px !important; 
        }
        
        /* CONTENT CLEARANCE OFFSET: Dynamically spaces your data content safely below the fixed nav height */
        main { 
            padding: 0 12px 40px !important; 
            margin-top: 185px !important; /* Adjust this value precisely if your page components overlap */
            margin-left: auto !important;
            margin-right: auto !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Stack profile welcome details fields */
        .welcome-row { flex-direction: column; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
        .welcome-title { font-size: 1.4rem; }
        
        /* Wrap shift tracking check-in components vertically */
        .attendance-banner { flex-direction: column; align-items: flex-start; gap: 14px; padding: 14px; }
        .shift-action-btn { width: 100%; height: 38px; justify-content: center; font-size: 12px; }
        
        /* Horizontal Table Data Scrolling Solution */
             
        .content-block { 
            overflow-x: auto !important; 
            -webkit-overflow-scrolling: touch !important; /* Adds smooth touch acceleration physics on iOS devices */
            padding: 16px !important; 
            width: 100% !important;
            box-sizing: border-box !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
        }
        table { 
            display: table !important; /* Restores table behavior to keep database cells perfectly straight */
            width: 100% !important; 
            min-width: 500px !important; /* Stops table columns from crushing on narrow screens */
        }
        th, td { 
            white-space: nowrap !important; /* Prevents values from wrapping onto separate vertical rows */
            padding: 12px 10px !important; 
        }
    }

    /* 📱 MINI SMARTPHONE DISPLAY CONSTRAINTS (Max 640px Width Screens) */
    @media (max-width: 640px) {
        /* Drop Metrics Cards down to single fluid rows */
        .metrics-grid { grid-template-columns: 1fr !important; gap: 16px !important; margin-bottom: 24px !important; }
        .metric-card { padding: 16px; }
        .metric-val { font-size: 1.5rem; }
    }

</style>
<link rel="stylesheet" href="../css/panel-polish.css">
<script src="../js/page-progress-dialog.js"></script>
</head>

<body class="panel-ui staff-panel">
<?php include_once 'navbar.php'; ?>
			<?php if (isset($_SESSION['error_flash'])): ?>
			<div id="toastAlertBox" style="background: #fef2f2; color: #991b1b; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #ef4444; font-size: 13px; font-weight: 500; margin-bottom: 15px; box-sizing: border-box; width: 100%;">
				⚠️ <?php echo htmlspecialchars($_SESSION['error_flash']); ?>
			</div>
			<script>
				// Dismiss the message alert cleanly after 4 seconds
				setTimeout(() => {
					const el = document.getElementById('toastAlertBox');
					if (el) el.style.display = 'none';
				}, 4000);
			</script>
			<?php unset($_SESSION['error_flash']); // Instantly wipe flash cache ?>
		<?php endif; ?>
    

    <main>
        <div class="welcome-row">
            <div>
                <h1 class="welcome-title">Management Control Desk</h1>
                <span class="welcome-meta">Active Operator Session: <strong><?= htmlspecialchars($staff_name); ?></strong></span>
            </div>
        </div><!-- DYNAMIC WORKFORCE SHIFT MONITOR INTERFACE BANNER UNIT -->
        <?php if ($visible_quick_actions): ?>
        <div class="panel-quick-actions" aria-label="Staff quick actions">
            <?php if (isset($visible_quick_actions['manage_orders.php'])): ?>
                <a href="manage_orders.php">Process Orders</a>
            <?php endif; ?>
            <?php if (isset($visible_quick_actions['low_stock_monitor.php'])): ?>
                <a href="low_stock_monitor.php">Check Stock</a>
            <?php endif; ?>
            <?php if (isset($visible_quick_actions['layaway_defaulters.php'])): ?>
                <a href="layaway_defaulters.php">Installment Alerts</a>
            <?php endif; ?>
            <?php if (isset($visible_quick_actions['manage_customers.php'])): ?>
                <a href="manage_customers.php">Customers</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
<?php if (!$current_shift): ?>
    <!-- 1. OFF-DUTY: Employee is ready to start. Show Shift Type Selector Dropdown -->
    <div class="attendance-banner att-off-duty">
        <span>⚠️ STATUS: OFF-DUTY. Your shift tracking log data lines for today are open.</span>
        <!-- Added id="clockInForm" to the form below -->
        <form id="clockInForm" method="POST" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="shift_action" value="clock_in">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_shift_csrf']); ?>">
            
            <!-- Shift selection dropdown linked directly to validator profiles -->
            <!-- Added id="shiftTypeSelect" to the dropdown below -->
            <select id="shiftTypeSelect" name="shift_type" class="shift-select-dropdown" style="background: #1e293b; color: #fff; padding: 6px 12px; border: 1px solid #475569; border-radius: 4px; cursor: pointer;">
                <option value="regular">Regular Day Shift</option>
                <option value="night">Night Shift</option>
                <option value="short_coverage">Short Coverage Shift</option>
            </select>

            <!-- Added id="clockInBtn" to the button below -->
            <button type="submit" id="clockInBtn" name="toggle_shift" class="shift-action-btn btn-clock-in">🛫 Clock In Shift</button>
        </form>
    </div>

<?php elseif ($current_shift['shift_status'] === 'Active'): ?>
    <!-- 2. ON-DUTY: Employee is actively working. -->
    <div class="attendance-banner att-on-duty">
        <span>⚡ STATUS: ACTIVE ON-DUTY. (Clocked In at: <?= date('H:i', strtotime($current_shift['clock_in_time'])); ?>)</span>
        
        <!-- The form remains open until clicked manually -->
        <form id="clockOutForm" method="POST" style="margin: 0;" data-clock-in="<?= htmlspecialchars($current_shift['clock_in_time']); ?>">
            <input type="hidden" name="shift_action" value="clock_out">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_shift_csrf']); ?>">
            <input type="hidden" name="shift_type" value="<?= htmlspecialchars($current_shift['shift_type'] ?? 'regular'); ?>">
            <button type="submit" name="toggle_shift" class="shift-action-btn btn-clock-out">🛬 Conclude Shift</button>
        </form>
    </div>

<?php else: ?>
    <!-- 3. COMPLETED: Shift successfully validated and concluded for the day -->
    <div class="attendance-banner att-completed">
        <span>ℹ️ STATUS: SHIFT TERMINATED. Today's working requirements are concluded (In: <?= date('H:i', strtotime($current_shift['clock_in_time'])); ?> | Out: <?= date('H:i', strtotime($current_shift['clock_out_time'])); ?>).</span>
        <button type="button" disabled class="shift-action-btn" style="background-color: #94a3b8; cursor: not-allowed;">Concluded</button>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- 2. THE POLITE MODAL REMINDER               -->
<!-- ========================================== -->
<div id="shiftReminderModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(3px);">
    <div style="background-color: #ffffff; margin: 12% auto; padding: 25px; border-radius: 8px; width: 100%; max-width: 480px; box-shadow: 0 4px 20px rgba(0,0,0,0.25); font-family: sans-serif;">
        
        <!-- Header -->
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="background-color: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 50%; margin-right: 12px; font-weight: bold; font-size: 18px;">⚠️</div>
            <h3 style="margin: 0; color: #333333; font-size: 1.25rem; font-weight: 600;">Shift Boundary Reminder</h3>
        </div>
        
        <!-- Message Body -->
        <div style="color: #555555; font-size: 0.95rem; line-height: 1.5; margin-bottom: 25px;">
            <p style="margin: 0 0 10px 0;">Hello! It looks like you are checking in outside of our standard regular morning window <strong>(7:30 AM – 9:00 AM)</strong>.</p>
            <p style="margin: 0;">Would you prefer to proceed with a <strong>Short Shift</strong> for today, or wait to be logged for the upcoming <strong>Night Shift</strong>?</p>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button id="modalCancelBtn" type="button" style="background-color: #f8f9fa; border: 1px solid #ddd; color: #666; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 500;">
                Cancel (Mark Absent)
            </button>
            <button id="modalAcceptBtn" type="button" style="background-color: #007bff; border: none; color: #fff; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 500;">
                Continue Short Shift
            </button>
        </div>
    </div>
</div>




        <?php if (!empty($msg)): ?><div style="padding: 12px; background-color: #d1fae5; color: #065f46; font-size: 0.875rem; font-weight: 700; border-radius: 6px; margin-bottom: 20px; border: 1px solid #a7f3d0;">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>

        <div class="metrics-grid">
            <div class="metric-card revenue-card" aria-label="Total realized revenue summary">
                <div class="revenue-card-top">
                    <p class="metric-label">Total Realized Revenue</p>
                    <span class="revenue-contactless" aria-hidden="true">)))</span>
                </div>
                <div class="revenue-chip" aria-hidden="true"></div>
                <p class="revenue-amount">KES <?= number_format($total_sales, 2); ?></p>
                <div class="revenue-card-footer">
                    <span>Realized Sales</span>
                    <span class="revenue-card-brand">ADONAK Finance</span>
                </div>
            </div>
            <div class="metric-card staff-pending-card">
                <p class="metric-label">Pending Invoices Backlog</p>
                <p class="metric-val" style="color: #d97706;"><?= $pending_orders_count; ?> Orders</p>
            </div>
            <div class="metric-card staff-layaway-card">
                <p class="metric-label">Active Layaway Contracts</p>
                <p class="metric-val" style="color: #2563eb;"><?= $active_layaways_count; ?> Plans</p>
            </div>
            <div class="metric-card staff-stock-card">
                <p class="metric-label">Low Stock Alerts</p>
                <p class="metric-val" style="color: #dc2626;"><?= $low_stock_count; ?> Alerts</p>
            </div>
        </div>

        <div class="layout-split">
            <div class="content-block">
                <h3 class="block-title">Recent Stored Payment Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction Timestamp</th>
                            <th>Master Order ID</th>
                            <th>Settlement Channel Method</th>
                            <th>Payment Reference</th>
                            <th>Recorded Amount</th>
                            <th>Stored Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_payments) > 0): foreach ($recent_payments as $pay): 
                            // FIX: Sanitize the raw transaction input string token 
                            $raw_code = trim($pay['transaction_code'] ?? '');
                            $display_code = ($raw_code === '0' || empty($raw_code)) ? 'Not recorded' : $raw_code;
                            $stored_status = strtolower(trim((string)($pay['payment_status'] ?? '')));
                            $status_label = $stored_status !== '' ? ucfirst($stored_status) : 'Not recorded';
                            $status_class = ['completed'=>'status-success','refunded'=>'status-refunded','failed'=>'status-failed'][$stored_status] ?? 'status-pending';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($pay['created_at']); ?></td>
                            <td style="font-weight: 700; color: #2563eb;"><?= !empty($pay['order_id']) ? '#' . (int)$pay['order_id'] : 'Not linked'; ?></td>
                            <td style="font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars((string)($pay['payment_method'] ?? 'Not recorded')); ?></td>
                            <!-- Renders the dynamic alphanumeric fallback value seamlessly -->
                            <td style="font-family: monospace; font-weight: 700; color:#4b5563; text-transform: uppercase;"><?= htmlspecialchars($display_code); ?></td>
                            <td style="font-weight: 800; color: <?= $stored_status === 'completed' ? '#059669' : ($stored_status === 'refunded' ? '#6d28d9' : '#475569'); ?>;">KES <?= number_format((float)($pay['amount'] ?? 0), 2); ?></td>
                            <td><span class="status-pill <?= $status_class; ?>"><?= htmlspecialchars($status_label); ?></span></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #9ca3af; padding: 24px 0; font-weight: 600;">No payment transaction ledger rows recorded.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>
        </div>
    </main>

       <!-- Global Interface Inter-Page Loader Transition Overlay Box Wrapper Frame -->
    <div class="loader-overlay" id="global-page-loader">
        <div class="spinner"></div>
        <p class="loader-text">Loading Operations Desk...</p>
    </div>

    <!-- JavaScript Navigation Routing Interceptors -->
 <script>
    // 1. Loader Overlay Interceptor Routing Engine
    document.querySelectorAll('.nav-center-links a, table a, .nav-loading-link, .logout-btn').forEach(link => {
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:') || link.type === 'submit') return;
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            document.getElementById('global-page-loader').style.display = 'flex';
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 350);
        });
    });
	
    // 2. Clear element mapping using explicit dashboard parameters
    document.addEventListener("DOMContentLoaded", function() {
        const clockInForm = document.getElementById('clockInForm'); 
        const shiftTypeSelect = document.getElementById('shiftTypeSelect'); 
        const clockOutForm = document.getElementById('clockOutForm');
        
        const modal = document.getElementById('shiftReminderModal');
        const acceptBtn = document.getElementById('modalAcceptBtn');
        const cancelBtn = document.getElementById('modalCancelBtn');
        
        // Evaluate clock-in boundaries from your backend setup
        const isRegularTime = <?php echo is_regular_clockin_time() ? 'true' : 'false'; ?>;
        
        // FIX 1: Pass the admin state context from PHP to JavaScript
        const isSuperAdmin = <?php echo ($isSuperAdmin) ? 'true' : 'false'; ?>;

        // --- PATHWAY A: CLOCK-IN INTERCEPTOR CONTROL ---
        if (clockInForm && shiftTypeSelect) {
            clockInForm.addEventListener('submit', function(e) {
                // Admins bypass the clock-in boundary reminder popup entirely
                if (isSuperAdmin) return;

                if (shiftTypeSelect.value === 'regular' && !isRegularTime && !document.getElementById('forceBypassFlag')) {
                    e.preventDefault(); 
                    if (modal) modal.style.display = 'block'; 
                }
            });
        }

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function() {
                if (modal) modal.style.display = 'none';
                if (shiftTypeSelect) {
                    shiftTypeSelect.value = 'short_coverage'; 
                    const bypassInput = document.createElement('input');
                    bypassInput.type = 'hidden';
                    bypassInput.id = 'forceBypassFlag';
                    bypassInput.name = 'modal_bypass_authorized';
                    bypassInput.value = 'true';
                    clockInForm.appendChild(bypassInput);
                }
                if (clockInForm) clockInForm.submit(); 
            });
        }

				if (cancelBtn) {
				cancelBtn.addEventListener('click', function() {
					if (modal) modal.style.display = 'none';
				});
			}

        // --- PATHWAY B: DYNAMIC CLOCK-OUT INTERCEPTOR (WITH ADMIN OVERRIDE) ---
        if (clockOutForm) {
            clockOutForm.addEventListener('submit', function(e) {
                // FIX 2: If a Super Admin is logged in, bypass all duration constraints immediately
                if (isSuperAdmin) {
                    console.log("[Privilege Verified] Super Admin override active. Bypassing shift duration restrictions...");
                    return; 
                }

                // --- REGULAR EMPLOYEE SELF-CHECKOUT LOGIC ---
                // Extract raw clock-in datetime from our HTML tracking element attribute
                const clockInRawStr = clockOutForm.getAttribute('data-clock-in'); 
                if (!clockInRawStr) return;

                // Read the tracking active shift profile type dynamically from the form payload
                const shiftTypeInput = clockOutForm.querySelector('input[name="shift_type"]');
                const activeShiftType = shiftTypeInput ? shiftTypeInput.value : 'regular';

                // Set dynamic targets based on your backend ShiftValidator profiles
               const requiredHoursByShift = {
					regular: 1.0,
					night: 1.0,
					short_coverage: 0
				};

const requiredHours = requiredHoursByShift[activeShiftType] ?? 1.0;

                // Parse dates explicitly avoiding browser-specific timezone variations
                const clockInTime = new Date(clockInRawStr.replace(/-/g, '/'));
                
                // Fetch accurate current server runtime synchronization profile timestamp context
                const currentTime = new Date(<?php echo time() * 1000; ?>);

                // Compute total active hours accumulated 
                const timeDifferenceMs = currentTime - clockInTime;
                const hoursWorked = timeDifferenceMs / (1000 * 60 * 60);

                // If they are a normal employee and haven't worked required hours yet, block them
                if (hoursWorked < requiredHours) {
                    e.preventDefault(); // Stop submission immediately
                    
                    const hoursRemaining = (requiredHours - hoursWorked).toFixed(2);
                    alert(`Hello! Your current tracking profile (${activeShiftType}) duration requirements are not yet complete.\n\nYou have logged ${hoursWorked.toFixed(2)} hours. Please complete the remaining ${hoursRemaining} hours before concluding your shift allocation!`);
                } else {
                    // Employee has completed their shift hours, let them log out themselves
                    const finalizeInput = document.createElement('input');
                    finalizeInput.type = 'hidden';
                    finalizeInput.name = 'toggle_shift';
                    finalizeInput.value = '1';
                    clockOutForm.appendChild(finalizeInput);
                    
                    console.log("[Authorization Verified] Shift duration parameters satisfied. Regular employee self-checkout allowed.");
                }
            });
        }
    });
</script>




</body>
</html>

