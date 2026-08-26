<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__DIR__) . '/session_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. DYNAMIC DATABASE CONNECTION HOOK-UP
$db_path = dirname(__DIR__) . '/db.php';
if (file_exists($db_path)) {
    include_once $db_path;
} else {
    include_once '../db.php';
}

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically 
// while replacing hard header resets with safe client-side JavaScript routers inside containers
if (!verifyWorkspaceClearance('manage_staff.php')) {
    if (isset($_POST['ajax_request'])) {
        echo "AUTH_ERROR";
        exit();
    }
    echo "<script>window.location.href='../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; 
$error = "";

// -------------------------------------------------------------------------
// 2. HANDLE STAFF CREDENTIALS & GRANULAR PERMISSIONS UPDATE PAYLOADS
// -------------------------------------------------------------------------
if (isset($_POST['update_staff_credentials']) || isset($_POST['commit_modifies'])) {
    $staff_id = intval($_POST['staff_id']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']); // FIXED: Adjusted to extract relational numeric role integer from form variables
    
    // Intercept and encode checked layout checkbox arrays cleanly into a JSON text string
    $assigned_views = isset($_POST['allowed_views']) && is_array($_POST['allowed_views']) ? $_POST['allowed_views'] : array();
    
    // Sanitize itemized view names to block directory traversal tricks
    $sanitized_views = array_map(function($view) {
        return basename(trim($view));
    }, $assigned_views);
    
    $encoded_permissions_json = json_encode($sanitized_views);
    
    if (!empty($fullname) && !empty($email) && $role_id > 0 && $staff_id > 0) {
        $conn->begin_transaction();

        try {
            // FIXED STRUCTURAL COLUMNS: Targets role_id (integer) instead of role (string) to prevent database mismatches
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role_id = ?, permissions = ? WHERE id = ?");
            $stmt->bind_param("ssisi", $fullname, $email, $role_id, $encoded_permissions_json, $staff_id);
            $stmt->execute();
            $stmt->close();
            
            // Live session configuration synchronization for the currently logged-in admin
            if ($staff_id === intval($_SESSION['user_id'])) {
                $_SESSION['user_permissions'] = $encoded_permissions_json;
            }

            // Commit an entry directly to the user activity logs table
            $log_details = "Updated profile & set JSON features permissions array for node account ID #" . $staff_id;
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Permissions Update', ?)");
            $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
            $log_stmt->execute();
            $log_stmt->close();

            $conn->commit();

            if (isset($_POST['ajax_request'])) {
                echo "SUCCESS"; // Inline confirmation token back to your AJAX listener loop
                exit();
            } else {
                // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside dashboard templates
                echo "<script>window.location.href='dashboard.php?view=manage_staff.php&msg=success';</script>";
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Transaction Rolled Back: Failed to modify staff credential record. " . $e->getMessage();
        }
    } else {
        $error = "Error: Invalid user values or blank fields submitted.";
    }
}

// -------------------------------------------------------------------------
// 3. HANDLE PERMANENT PERMISSIONS PURGE COMMANDS
// -------------------------------------------------------------------------
if (isset($_POST['purge_access']) || isset($_GET['delete_staff_id'])) {
    $del_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : intval($_GET['delete_staff_id']);
    
    // Security Boundary Layer: Prevent logged-in admins from accidentally deleting their own master sessions
    if ($del_id !== intval($_SESSION['user_id']) && $del_id > 0) {
        
        // BACKEND ACCESS MASK: Pull target employee data and rank before processing mutation queries
        $purge_check = $conn->prepare("SELECT r.role_name, u.fullname FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $purge_check->bind_param("i", $del_id); $purge_check->execute();
        $purge_data = $purge_check->get_result()->fetch_assoc();
        $purge_target_role = $purge_data['role_name'] ?? '';
        $purge_target_name = $purge_data['fullname'] ?? '';
        $purge_check->close();
        
        // SECURITY GATE: Standard administrators possess no clearance privilege layers to purge Super Admin profiles
        if ($purge_target_role === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) {
            $error = "🛡️ CRITICAL EXCEPTION: Standard administrators cannot delete or drop Super Administrator entries.";
        } else {
            $conn->begin_transaction();

            try {
                // FIXED BYPASS: Switches from hard DELETE row drop over to soft UPDATE status flags to avoid foreign key failures
                $stmt = $conn->prepare("UPDATE users SET account_status = 'purged' WHERE id = ?");
                $stmt->bind_param("i", $del_id);
                $stmt->execute();
                $stmt->close();
                
                // Wipe their current active panel layout view permissions while keeping historical data safe
                $clean_stmt = $conn->prepare("DELETE FROM staff_permissions WHERE user_id = ?");
                $clean_stmt->bind_param("i", $del_id);
                $clean_stmt->execute();
                $clean_stmt->close();
                
                // Commit soft-purge profile description parameters straight into your audit logs trace timeline
                $log_details = "Staff Profile Account Purged: Soft-deleted operational agent '{$purge_target_name}' (Assigned ID: #{$del_id}, Role: {$purge_target_role}) and wiped accompanying active view authorization lines.";
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Management', ?)");
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();

                $conn->commit();

                if (isset($_GET['ajax_request']) || isset($_POST['ajax_request'])) {
                    echo "DELETION_SUCCESS";
                    exit();
                } else {
                    // FIXED REDIRECT: Safe client-side routing to dodge output header buffer conflicts inside dashboard templates
                    echo "<script>window.location.href='dashboard.php?view=manage_staff.php&msg=purged';</script>";
                    exit();
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database execution failure: Unable to revoke account rights: " . $e->getMessage();
            }
        }
    } else {
        $error = "Security Violation: You cannot revoke or purge your own active admin session.";
    }
}

// -------------------------------------------------------------------------
// 4. FETCH FRESH ADMINISTRATIVE PROFILES DATA STREAM VIA SYSTEM ROLES JOIN
// -------------------------------------------------------------------------
// FIXED: Completely removes static text column lookups. Links to dynamic roles mapping table parameters
// and filters out soft-purged profiles to maintain an accurate and uniform user registry grid view
$staff_result = $conn->query("
    SELECT u.id, u.fullname, u.email, u.role_id, u.created_at, r.role_name 
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.id 
    WHERE (u.account_status != 'purged' OR u.account_status IS NULL) 
    AND r.role_name IN ('staff', 'admin', 'super_admin', 'cashier', 'auditor', 'supervissoor', 'cleaner')
    ORDER BY u.id DESC
");
?>
