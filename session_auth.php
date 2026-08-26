<?php
// Initialize secure session storage layers if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for dynamic background AJAX channels to drop cleanly without spilling HTML elements
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_REQUEST['ajax_request']);

// 1. CRITICAL AUTHENTICATION CHECK: Detects if the user has a validated session ID token
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    
    if ($is_ajax) {
        echo "AUTH_ERROR";
        exit();
    }
    header("Location: ../login.php?msg=err_unauthorized_access");
    exit();
}

// 2. CRITICAL TIMEOUT GUARD: Automatically kicks out active tabs left idle for more than 15 minutes
$max_idle_time = 900; // 15 minutes mapped in seconds

if (isset($_SESSION['last_activity_timestamp']) && (time() - $_SESSION['last_activity_timestamp'] > $max_idle_time)) {
    session_unset();
    session_destroy();
    
    if ($is_ajax) {
        echo "SESSION_TIMEOUT";
        exit();
    }
    header("Location: ../login.php?msg=err_session_expired");
    exit();
}

// Update the user activity timer mark tracking
$_SESSION['last_activity_timestamp'] = time();
/**
 * Resolve the current operator from the authoritative users -> roles relationship.
 * Workspace permissions must not rely on a stale or forged session role.
 */
function getAuthenticatedWorkspaceRole() {
    static $resolved_user_id = null;
    static $resolved_role = null;

    $session_user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($session_user_id <= 0) {
        return '';
    }
    if ($resolved_user_id === $session_user_id && $resolved_role !== null) {
        return $resolved_role;
    }

    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return '';
    }

    $role_lookup = $conn->prepare("SELECT LOWER(TRIM(r.role_name)) AS role_name
                                  FROM users u
                                  INNER JOIN roles r ON r.id = u.role_id
                                  WHERE u.id = ?
                                    AND LOWER(COALESCE(u.account_status, 'active')) = 'active'
                                  LIMIT 1");
    if (!$role_lookup) {
        return '';
    }
    $role_lookup->bind_param('i', $session_user_id);
    if (!$role_lookup->execute()) {
        $role_lookup->close();
        return '';
    }
    $account = $role_lookup->get_result()->fetch_assoc();
    $role_lookup->close();

    $workspace_roles = ['admin', 'super_admin', 'staff', 'cashier', 'auditor', 'supervissoor', 'cleaner'];
    $role = strtolower(trim((string)($account['role_name'] ?? '')));
    $resolved_user_id = $session_user_id;
    $resolved_role = in_array($role, $workspace_roles, true) ? $role : '';
    if ($resolved_role !== '') {
        $_SESSION['role'] = $resolved_role;
    }
    return $resolved_role;
}
/**
 * Distinguish a direct Admin/Staff entry script from a shared component include.
 * SCRIPT_FILENAME remains the Staff wrapper when it includes a component from /admin.
 */
function getWorkspaceEntryArea() {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (preg_match('~/admin/[^/]+$~i', $script)) {
        return 'admin';
    }
    if (preg_match('~/staff/[^/]+$~i', $script)) {
        return 'staff';
    }
    return '';
}

function workspaceRoleCanEnterArea($role) {
    $area = getWorkspaceEntryArea();
    if ($area === 'admin') {
        return in_array($role, ['admin', 'super_admin'], true);
    }
    return $role !== '';
}

/**
 * Universal Access Control Gatekeeper
 * Validates dynamic roles and folder views using relational table parameters.
 */
function verifyWorkspaceClearance($target_file) {
    $role = getAuthenticatedWorkspaceRole();
    if (!workspaceRoleCanEnterArea($role)) {
        return false;
    }

    // A. MASTER BYPASS LAYER: Both 'super_admin' and 'admin' automatically clear all restriction checkpoints
    if (in_array($role, ['super_admin', 'admin'], true)) {
        return true;
    }

    // Initialize clean file mapping tracking filters
    $clean_file_target = basename(strtok($target_file, '?'));

    // B. BASELINE ACCESS: Core layouts and wrappers are open to verified workspace roles.
    $baseline_views = ['staff_dashboard.php'];
    if (in_array($clean_file_target, $baseline_views)) {
        return true;
    }

    // C. RELATIONAL BACKEND DATA ACCESS STREAM: Query live db map profiles directly for sub-users
    global $conn;
    if (isset($conn) && isset($_SESSION['user_id'])) {
        $session_user_id = intval($_SESSION['user_id']);
        
        $perm_query = $conn->prepare("SELECT id FROM staff_permissions WHERE user_id = ? AND TRIM(target_view) = ? LIMIT 1");
        if ($perm_query) {
            $perm_query->bind_param("is", $session_user_id, $clean_file_target);
            $perm_query->execute();
            $perm_res = $perm_query->get_result();
            
            if ($perm_res && $perm_res->num_rows > 0) {
                $perm_query->close();
                return true; // Match found inside live mapping tables!
            }
            $perm_query->close();
        }
    }

    // Live relational assignments are authoritative; missing or failed lookups deny access.

    // Default security layout protection gate fallback action parameter
    return false;
}

/**
 * Permission-sensitive module check.
 * Super Admin retains master access; every other operator must have the view assigned.
 */
function verifyExplicitWorkspaceClearance($target_file) {
    $role = getAuthenticatedWorkspaceRole();
    if (!workspaceRoleCanEnterArea($role)) {
        return false;
    }
    if ($role === 'super_admin') {
        return true;
    }

    $clean_file_target = basename(strtok($target_file, '?'));
    global $conn;

    if (isset($conn) && isset($_SESSION['user_id'])) {
        $session_user_id = (int) $_SESSION['user_id'];
        $permission = $conn->prepare("SELECT id FROM staff_permissions WHERE user_id = ? AND TRIM(target_view) = ? LIMIT 1");
        if ($permission) {
            $permission->bind_param("is", $session_user_id, $clean_file_target);
            $permission->execute();
            $result = $permission->get_result();
            $allowed = $result && $result->num_rows > 0;
            $permission->close();
            if ($allowed) {
                return true;
            }
        }
    }

    // Live relational assignments are authoritative; missing or failed lookups deny access.

    return false;
}
