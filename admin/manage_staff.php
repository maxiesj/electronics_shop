<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__DIR__) . '/session_auth.php';

// 1. DATABASE CONNECTION HOOK-UP
$db_path = dirname(__DIR__) . '/db.php';
if (file_exists($db_path)) {
    include_once $db_path;
} else {
    include_once '../db.php';
}

// FIXED SECURITY GATEPOINT: Uses your standardized workspace clearance function 
// to automatically approve Super Admin and Admin tiers while preventing redirect loop issues
if (!verifyWorkspaceClearance('manage_staff.php')) {
    echo "<script>window.location.href='../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$msg = ""; 
$error = "";

require_once __DIR__ . '/staff_management_guard.php';
unset($_GET['delete_staff_id']);

// 2. PROCESS FRESH NEW STAFF ACCOUNT CREATION 
if (isset($_POST['add_new_staff_account'])) {
    $fullname = trim($_POST['new_fullname']);
    $email = trim($_POST['new_email']);
    $password = trim($_POST['new_password']);
    $role_id = intval($_POST['new_role_id']);
    
    if (!empty($fullname) && !empty($email) && !empty($password) && $role_id > 0) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // 1. Check if an active or purged user profile exists with this email address
        $check_stmt = $conn->prepare("SELECT id, account_status FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res->num_rows > 0) {
            $existing_user = $check_res->fetch_assoc();
            $check_stmt->close();
            
            if ($existing_user['account_status'] !== 'purged') {
                $error = "Registration Failure: An active user account already exists with that email.";
            } else {
                // 2. AUTO-REACTIVATE: Overwrite credentials and flip status back to active
                $reactivate_stmt = $conn->prepare("UPDATE users SET fullname = ?, password = ?, role_id = ?, account_status = 'active' WHERE id = ?");
                $reactivate_stmt->bind_param("ssii", $fullname, $hashed_password, $role_id, $existing_user['id']);
                
                if ($reactivate_stmt->execute()) {
                    $reactivate_stmt->close();
                    
                    // Log account reactivation to audit trails
                    $log_details = "Staff Account Re-activated: Restored purged account profile for operational agent '{$fullname}' (Email: {$email}) with Role ID #{$role_id}.";
                    $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Management', ?)");
                    if ($log_stmt) {
                        $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $_SESSION['success_flash'] = "Historical Account Re-activated and Synchronized Cleanly";
                    echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
                    exit();
                } else {
                    $error = "Database Error: Failed to reactivate the purged profile.";
                    $reactivate_stmt->close();
                }
            }
        } else {
            $check_stmt->close();
            
            // 3. BRAND NEW ACCOUNT REGISTRATION
            $ins_user = $conn->prepare("INSERT INTO users (fullname, email, password, role_id, account_status) VALUES (?, ?, ?, ?, 'active')");
            $ins_user->bind_param("sssi", $fullname, $email, $hashed_password, $role_id);

            if ($ins_user->execute()) {
                $new_account_id = $ins_user->insert_id;
                $ins_user->close();
                
                // Log brand new profile generation to audit trails
                $log_details = "New Staff Account Registered: Created operational agent profile for '{$fullname}' (Email: {$email}, Assigned ID: #{$new_account_id}) with Role ID #{$role_id}.";
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Management', ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                $_SESSION['success_flash'] = "System Registry Synchronized Cleanly";
                echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
                exit();
            } else {
                $error = "Database Error: Could not save the new employee profile.";
                $ins_user->close();
            }
        }
    } else {
        $error = "Error: All fields are required to register a staff profile.";
    }
}
// BACKEND CONTROLLER: Process fresh custom role insertion
if (isset($_POST['add_custom_role_tier'])) {
    // FIXED: Dynamically matches against your updated session string variables
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        $error = "🛡️ SECURITY VIOLATION: Standard administrators hold no clearance rules to append database role tiers.";
    } else {
        $new_role_input = trim(strtolower($_POST['custom_role_name']));
        
        if (!empty($new_role_input)) {
            // Prepare statement to avoid duplicate entries securely
            $role_check = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
            $role_check->bind_param("s", $new_role_input);
            $role_check->execute();
            $role_res = $role_check->get_result();
            
            if ($role_res->num_rows > 0) {
                $error = "Role Creation Failure: That role tier string already exists.";
                $role_check->close();
            } else {
                $role_check->close();
                $ins_role = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
                $ins_role->bind_param("s", $new_role_input);
                if ($ins_role->execute()) {
                    $ins_role->close();
                    
                    // Log custom role addition to audit trails
                    $log_details = "New Custom Role Registered: Added database tier structure node for rank string designation '{$new_role_input}'.";
                    $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                    if ($log_stmt) {
                        $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $_SESSION['success_flash'] = "New Security Role Tier Registered Successfully";
                    echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
                    exit();
                } else {
                    $error = "Database Error: Could not save the custom role node.";
                    $ins_role->close();
                }
            }
        }
    }
}

// BACKEND CONTROLLER: Process fresh system permission view insertion
if (isset($_POST['add_new_system_privilege'])) {
    // FIXED: Dynamically matches against your updated session string variables
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        $error = "🛡️ SECURITY VIOLATION: Standard administrators hold no clearance rules to inject application view modules.";
    } else {
        $perm_key = trim(strtolower($_POST['new_perm_key']));
        $perm_name = trim($_POST['new_perm_name']);
        
        if (!empty($perm_key) && !empty($perm_name)) {
            // FIXED: Replaced str_ends_with with substr to support PHP 7.X server configurations
            if (substr($perm_key, -4) !== '.php') {
                $perm_key .= '.php';
            }
            
            // Prepare query statement to avoid duplicate permission strings securely
            $perm_check = $conn->prepare("SELECT id FROM system_permissions WHERE permission_key = ?");
            $perm_check->bind_param("s", $perm_key);
            $perm_check->execute();
            $perm_res = $perm_check->get_result();
            
            if ($perm_res->num_rows > 0) {
                $error = "Privilege Creation Failure: That permission key route already exists.";
                $perm_check->close();
            } else {
                $perm_check->close();
                $ins_perm = $conn->prepare("INSERT INTO system_permissions (permission_key, display_name) VALUES (?, ?)");
                $ins_perm->bind_param("ss", $perm_key, $perm_name);
                if ($ins_perm->execute()) {
                    $ins_perm->close();
                    
                    // Log custom permission matrix node addition to audit trails
                    $log_details = "Custom Permission Node Injected: Added privilege route map target [{$perm_key}] with friendly title label string '{$perm_name}'.";
                    $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                    if ($log_stmt) {
                        $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $_SESSION['success_flash'] = "Custom Permission Node Injected Successfully";
                    echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
                    exit();
                } else {
                    $error = "Database Error: Could not save the custom privilege link.";
                    $ins_perm->close();
                }
            }
        }
    }
}

// 3. PROCESS NEW CUSTOM PERMISSION CREATION NODE
if (isset($_POST['add_new_permission_rule'])) {
    // FIXED: Dynamically matches against your updated session string variables
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        $error = "🛡️ SECURITY VIOLATION: Standard administrators hold no clearance rules to register privilege definitions.";
    } else {
        $perm_key = trim($_POST['new_perm_key']);
        $perm_name = trim($_POST['new_perm_name']);
        
        if (!empty($perm_key) && !empty($perm_name)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO system_permissions (permission_key, display_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $perm_key, $perm_name);
            if ($stmt->execute()) {
                $stmt->close();
                
                // Log permission registration action to audit logs
                $log_details = "Permission Node Configured: Injected unique privilege node key [{$perm_key}] with friendly label '{$perm_name}'.";
                $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                echo "<script>window.location.href='dashboard.php?view=manage_staff.php&msg=success';</script>";
                exit();
            } else {
                $error = "Error adding new permission node.";
                $stmt->close();
            }
        }
    }
}

// 4. PROCESS PROFILE MODIFICATION AND CROSS-REFERENCE PERMISSIONS
if (isset($_POST['update_staff_credentials'])) {
    $staff_id = intval($_POST['staff_id']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']); 
    
    // BACKEND ACCESS MASK: Block standard Admin loops from overwriting structural Super Admin accounts
    $target_check = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $target_check->bind_param("i", $staff_id); $target_check->execute();
    $target_role = $target_check->get_result()->fetch_assoc()['role_name'] ?? '';
    $target_check->close();
    
    // FIXED: References your dynamic active backend session string layer variables securely
    if ($target_role === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) {
        $error = "🛡️ VIOLATION REFUSED: Standalone administrators possess no hierarchy authorization weights to modify a Super Admin profile slate.";
    } elseif (!empty($fullname) && !empty($email) && $role_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $fullname, $email, $role_id, $staff_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Delete old configurations safely for this specific profile row slate
            $del_stmt = $conn->prepare("DELETE FROM staff_permissions WHERE user_id = ?");
            $del_stmt->bind_param("i", $staff_id);
            $del_stmt->execute();
            $del_stmt->close();
            
            $permission_count = 0;
            // FIXED: Added sanitation logic using filter_var or trim to clean string data 
            // and strips out any corrupted arrays or notices before database execution
            if (isset($_POST['allowed_views'][$staff_id]) && is_array($_POST['allowed_views'][$staff_id])) {
                foreach ($_POST['allowed_views'][$staff_id] as $view) {
                    $clean_view = trim(filter_var($view, FILTER_DEFAULT));
                    
                    // CRITICAL FIX: Only insert valid file routes. Skips any string containing PHP notices/errors
                    if (!empty($clean_view) && strpos($clean_view, 'Notice') === false && strpos($clean_view, 'br /') === false) {
                        $ins_stmt = $conn->prepare("INSERT IGNORE INTO staff_permissions (user_id, target_view) VALUES (?, ?)");
                        $ins_stmt->bind_param("is", $staff_id, $clean_view);
                        $ins_stmt->execute();
                        $ins_stmt->close();
                        $permission_count++;
                    }
                }
            }
            
            // Log full profile and workspace permission alterations to audit trails
            $log_details = "Staff Profile Modified: Updated account data properties for Employee ID #{$staff_id} ('{$fullname}', Email: {$email}, Role ID: #{$role_id}). Remapped table links to assign {$permission_count} clear views.";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Management', ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_flash'] = "System Registry Synchronized Cleanly";
            echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
            exit();
        } else {
            $error = "Database execution error: Failed to modify staff credentials.";
            $stmt->close();
        }
    } else {
        $error = "Error: Invalid user values submitted.";
    }
}

// 5. PROCESS ACCESS PURGE REMOVALS
if (isset($_GET['delete_staff_id'])) {
    $del_id = intval($_GET['delete_staff_id']);
    
    // BACKEND ACCESS MASK: Pull candidate rank before processing the drop query statement
    $purge_check = $conn->prepare("SELECT r.role_name, u.fullname FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $purge_check->bind_param("i", $del_id); $purge_check->execute();
    $purge_data = $purge_check->get_result()->fetch_assoc();
    $purge_target_role = $purge_data['role_name'] ?? '';
    $purge_target_name = $purge_data['fullname'] ?? '';
    $purge_check->close();
    
    // FIXED: References your dynamic active backend session string layer variables securely
    if ($purge_target_role === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) {
        $error = "🛡️ CRITICAL EXCEPTION: Standard administrators cannot delete or drop Super Administrator entries.";
    } elseif ($del_id !== intval($_SESSION['user_id'])) {
        
        // Change from DELETE to UPDATE to bypass foreign key constraint blocks safely
        $stmt = $conn->prepare("UPDATE users SET account_status = 'purged' WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Optional: Wipe their login/view privileges while keeping sales metrics clean
            $clean_stmt = $conn->prepare("DELETE FROM staff_permissions WHERE user_id = ?");
            $clean_stmt->bind_param("i", $del_id);
            $clean_stmt->execute();
            $clean_stmt->close();
            
            // Log soft-purge account suspension to audit trails
            $log_details = "Staff Profile Account Purged: Soft-deleted operational agent '{$purge_target_name}' (Assigned ID: #{$del_id}, Role: {$purge_target_role}) and wiped accompanying active view authorization lines.";
            $log_stmt = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Management', ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $log_details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_flash'] = "Staff Profile Account Purged Permanently";
            echo "<script>window.location.href='dashboard.php?view=manage_staff.php';</script>";
            exit();
        } else {
            die("Execution failure: " . $stmt->error);
        }
    } else {
        $error = "Security Violation: You cannot purge your own active admin session.";
    }
}

// 6. FETCH RECOGNIZED SYSTEM USER ROLES
// FIXED: Added WHERE clause to filter out 'purged' accounts so they disappear from the dashboard list
$staff_result = $conn->query("
    SELECT u.id, u.fullname, u.email, u.role_id, u.created_at, r.role_name 
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.id 
    WHERE (u.account_status != 'purged' OR u.account_status IS NULL)
      AND LOWER(r.role_name) != 'customer'
    ORDER BY u.id DESC
");
?>
<!-- System Status Indicators Container Desk -->
<?php if (!empty($error)): ?>
    <div style="background:#f8d7da; color:#721c24; padding:12px; border-radius:8px; margin-bottom:25px; border-left:4px solid #dc3545; font-size:14px; box-sizing: border-box; width: 100%;">
        ⚠️ <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success_flash'])): ?>
    <!-- UTILIZED BLANK SPACE: Session Driven Interactive Splash Overlay Loader -->
    <div id="successSplashOverlay" style="background:#ffffff; padding:40px; border-radius:12px; text-align:center; border:1px solid #e2e8f0; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom:25px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:15px; width:100%; box-sizing:border-box; animation: fadeInAction 0.4s ease;">
        <div style="width: 60px; height: 60px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #16a34a; font-size: 28px; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.1); font-weight: bold;">
            ✓
        </div>
        <h3 style="margin:0; color:#0f172a; font-size:18px; font-weight:700;">
            <?php echo htmlspecialchars($_SESSION['success_flash']); ?>
        </h3>
        <p style="margin:0; color:#64748b; font-size:13px; max-width:450px; line-height:1.5;">
            Operational backend modifications have been written cleanly into active database arrays. Resuming control dashboard layout metrics in <span id="splashCountdownTimer" style="font-weight:bold; color:#3b82f6;">2</span> seconds...
        </p>
        <!-- Horizontal Running Processing Loading Tracker Bar -->
        <div style="width:100%; max-width:250px; background:#f1f5f9; height:4px; border-radius:2px; overflow:hidden; margin-top:5px;">
            <div style="background:#28a745; height:100%; width:0%; border-radius:2px; animation: loadBarAction 2s linear forwards;"></div>
        </div>
    </div>

    <style>
        @keyframes fadeInAction { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes loadBarAction { from { width: 0%; } to { width: 100%; } }
    </style>

    <script>
        let countdownTimeLeft = 2;
        const countdownDisplayNode = document.getElementById('splashCountdownTimer');
        
        const countdownIntervalTimer = setInterval(() => {
            countdownTimeLeft--;
            if(countdownDisplayNode) { countdownDisplayNode.innerText = countdownTimeLeft; }
            
            if (countdownTimeLeft <= 0) {
                clearInterval(countdownIntervalTimer);
                // Automatically removes the splash overlay element card container gracefully from active UI visibility
                document.getElementById('successSplashOverlay').style.display = 'none';
            }
        }, 1000);
    </script>
    
    <?php 
    // CRITICAL: Unset the flash variable immediately so the alert card doesn't loop forever on manual refreshes
    unset($_SESSION['success_flash']); 
    ?>
<?php endif; ?>
<!-- =================== FORM CONTAINER OVERLAYS (MOBILE RESPONSIVE GRID) =================== -->
<div style="display: flex; flex-direction: row; flex-wrap: wrap; gap: 20px; width: 100%; margin-bottom: 25px; box-sizing: border-box; align-items: flex-start;">
    
    <!-- FORM 1: DYNAMIC STAFF MEMBER REGISTRATION FORMS (Open to all Admins) -->
    <div style="flex: 1 1 450px; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box;">
        <h3 style="margin-top:0; color:#1e293b; font-size: 16px; display: flex; align-items: center; gap: 8px;">➕ Add New Operational Staff Member</h3>
        <p style="color:#64748b; font-size:12px; margin-top:0; margin-bottom:20px;">Create unique access credentials for a new cashier, manager, or custom store handler profile node.</p>
        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 14px; margin: 0;">
            <!-- Row 1: Name and Email Inputs -->
            <div style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%;">
                <div style="flex: 1 1 200px; display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Full Employee Name</span>
                    <input type="text" name="new_fullname" placeholder="e.g., Johnathan Doe" required style="padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; box-sizing: border-box; width: 100%; background: #fff;">
                </div>
                <div style="flex: 1 1 200px; display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Account Email Address</span>
                    <input type="email" name="new_email" placeholder="e.g., john@shop.com" required style="padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; box-sizing: border-box; width: 100%; background: #fff;">
                </div>
            </div>
            <!-- Row 2: Password and Role Rank Select Options -->
            <div style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%;">
                <div style="flex: 1 1 200px; display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Secure Password</span>
                    <input type="password" name="new_password" placeholder="••••••••" required style="padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; box-sizing: border-box; width: 100%; background: #fff;">
                </div>
                <div style="flex: 1 1 200px; display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Security Role Rank</span>
                    <select name="new_role_id" required style="padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; box-sizing: border-box; width: 100%; background: #fff; height: 39px; color: #1e293b; font-weight: bold;">
                        <option value="">-- Choose Role --</option>
                        <?php 
                        $roles_list_query = $conn->query("SELECT id, role_name FROM roles ORDER BY id ASC");
                        while($role_opt = $roles_list_query->fetch_assoc()):
                            $is_selected = ($role_opt['role_name'] === 'cashier') ? 'selected' : '';
                        ?>
                            <option value="<?php echo $role_opt['id']; ?>" <?php echo $is_selected; ?>>
                                <?php echo htmlspecialchars(ucfirst($role_opt['role_name'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_new_staff_account" style="background: #3b82f6; color: white; border: none; padding: 11px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size:13px; margin-top: 6px; box-shadow: 0 2px 4px rgba(59,130,246,0.15);">+ Register Staff Account</button>
        </form>
    </div> <!-- Form 1 closes cleanly here -->

    <!-- FIXED DYNAMIC UI MASK LAYER: Form 2 and Form 3 vanish completely if logged-in user is just an ordinary Admin -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        
        <!-- FORM 2: SECURITY ROLE TIER REGISTRATION -->
        <div style="flex: 1 1 350px; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box;">
            <h3 style="margin-top:0; color:#1e293b; font-size: 16px; display: flex; align-items: center; gap: 8px;">🔑 Create New Security Role Tier</h3>
            <p style="color:#64748b; font-size:12px; margin-top:0; margin-bottom:15px;">Type in any custom administrative role level name to inject it into selection drop-downs instantly.</p>
            
            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 12px; margin: 0;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; display: block; margin-bottom: 4px;">Role Level Name</span>
                    <input type="text" name="custom_role_name" placeholder="e.g., cashier, supervisor, auditor" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; width: 100%; box-sizing: border-box; background: #fff; color: #1e293b;">
                </div>   
                <button type="submit" name="add_custom_role_tier" style="background: #8b5cf6; color: white; border: none; padding: 11px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size:13px; margin-top: 5px; box-shadow: 0 2px 4px rgba(139,92,246,0.15);">+ Register Role Tier</button>
            </form>
        </div>

        
    <!-- FORM 3: REGISTER NEW SYSTEM PRIVILEGE MODULE NODE -->
        <div style="flex: 1 1 350px; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box;">
            <h3 style="margin-top:0; color:#1e293b; font-size: 16px; display: flex; align-items: center; gap: 8px;">🛠️ Register Permission Module</h3>
            <p style="color:#64748b; font-size:12px; margin-top:0; margin-bottom:15px;">Inject fresh custom application functionality views into layout privilege checkboxes dynamically.</p>
            
            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 12px; margin: 0;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; display: block; margin-bottom: 4px;">File name / Key code</span>
                    <input type="text" name="new_perm_key" placeholder="e.g., warehouse.php, view_logs.php" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; width: 100%; box-sizing: border-box; background: #fff; color: #1e293b;">
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; display: block; margin-bottom: 4px;">Display Label text</span>
                    <input type="text" name="new_perm_name" placeholder="e.g., Warehouse Stock Control, Audit Trails" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size:13px; width: 100%; box-sizing: border-box; background: #fff; color: #1e293b;">
                </div>
                <button type="submit" name="add_new_system_privilege" style="background: #10b981; color: white; border: none; padding: 11px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size:13px; margin-top: 5px; box-shadow: 0 2px 4px rgba(16,185,129,0.15);">+ Add Permission Node</button>
            </form>
        </div>

    <?php endif; ?>
</div>
<!-- ==================== MAIN STAFF ACCOUNT LIST REGISTRY BOARD ==================== -->
<div class="card" style="background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box; width: 100%;">
    <h2>👥 Operational Backend User Registry</h2>
    <p style="color:#64748b; font-size:14px; margin-top:0; margin-bottom:25px;">
        Review active administrative employee profiles. Modify name credentials, assign system clearance ranks, or revoke database network access permissions permanently.
    </p>
    
    <!-- RESPONSIVE FLEX MATRIX ROWS CARDS -->
    <div style="display: flex; flex-direction: column; gap: 20px; width: 100%;">
    
    <?php if($staff_result && $staff_result->num_rows > 0): while($row = $staff_result->fetch_assoc()): 
        $staff_id = $row['id'];
        $row_role_name = $row['role_name']; // Caches rank for structural comparison locks
        $assigned_views = [];
		$perm_stmt = $conn->prepare("SELECT TRIM(target_view) AS clean_view FROM staff_permissions WHERE user_id = ?");
		$perm_stmt->bind_param("i", $staff_id);
		$perm_stmt->execute();
		$perm_res = $perm_stmt->get_result();

		while($p_row = $perm_res->fetch_assoc()) {
			$assigned_views[] = $p_row['clean_view']; // Caches the cleaned view text strings safely
		}
        $perm_stmt->close(); // OPTIMIZED: Cleanly close statement data thread resource context scope
    ?>
        <form method="POST" action="" class="staff-row-update-form" style="margin: 0; width: 100%;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; display: flex; flex-direction: row; flex-wrap: wrap; gap: 20px; align-items: stretch; justify-content: space-between; box-sizing: border-box; width: 100%;">
			<!-- 1. STAFF ID ELEMENT BOX -->
                <div style="color: #64748b; font-weight: 600; font-size: 14px; flex: 0 0 auto; min-width: 45px; padding-top: 4px;">
                    #<?php echo $staff_id; ?>
                    <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                </div>
                
                <!-- 2. CREDENTIAL TEXT INPUTS -->
                <div style="flex: 1 1 240px; display: flex; flex-direction: column; gap: 8px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; letter-spacing: 0.5px;">User Profile Details</span>
                    <!-- FIXED WORKSPACE CLEARANCE FLAGS: Uses your verified dynamic session string comparison loops -->
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($row['fullname']); ?>" required style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: bold; color: #1e293b; background: #fff; width: 100%; box-sizing: border-box;" <?php echo ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) ? 'disabled' : ''; ?>>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #475569; background: #fff; width: 100%; box-sizing: border-box;" <?php echo ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) ? 'disabled' : ''; ?>>
                </div>

                
              <!-- 4. SECURITY RANK DROPDOWN STATUS -->
                <div style="flex: 1 1 140px; display: flex; flex-direction: column; gap: 8px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; letter-spacing: 0.5px;">Security Role</span>
                    
                    <!-- FIXED SECURITY LOCKS: Swapped static checking loops with verified active session parameters -->
                    <select name="role_id" required style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; font-weight: bold; background: #fff; width: 100%; box-sizing: border-box; color: #1e293b; height: 35px;" <?php echo ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) ? 'disabled' : ''; ?>>
                        <?php 
                        $edit_roles_query = $conn->query("SELECT id, role_name FROM roles ORDER BY id ASC");
                        while($role_row = $edit_roles_query->fetch_assoc()):
                            $sel_flag = ($role_row['id'] == $row['role_id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $role_row['id']; ?>" <?php echo $sel_flag; ?>>
                                <?php echo htmlspecialchars(ucfirst($role_row['role_name'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <!-- CHANGED: Evaluates dynamic role names rather than previous static strings -->
                    <?php if($row['role_name'] === 'super_admin'): ?>
                        <span style="background: #fef3c7; color: #d97706; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-align: center; display: block; text-transform: uppercase;">SUPER ADMIN</span>
                    <?php elseif($row['role_name'] === 'admin'): ?>
                        <span style="background: #fce7f3; color: #db2777; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-align: center; display: block; text-transform: uppercase;">ADMIN</span>
                    <?php elseif($row['role_name'] === 'cashier'): ?>
                        <span style="background: #dcfce7; color: #16a34a; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-align: center; display: block; text-transform: uppercase;">CASHIER</span>
                    <?php else: ?>
                        <span style="background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-align: center; display: block; text-transform: uppercase;"><?php echo htmlspecialchars($row['role_name'] ?? 'Custom'); ?></span>
                    <?php endif; ?>
                </div>
                <!-- 4. PROFILE GENERATION DATE -->
                <div style="flex: 1 1 100px; display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: bold; letter-spacing: 0.5px;">Registered</span>
                    <span style="color: #475569; font-size: 13px; font-weight: 500; white-space: nowrap; padding-top: 6px;">
                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                    </span>
                </div>

            <!-- 5. DYNAMIC ACCESS CHECKBOX PRIVILEGES MATRIX -->
                <div style="flex: 2 1 260px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; width: 100%; height: 110px; max-height: 110px; overflow-y: auto; font-size: 12px; display: flex; flex-direction: column; gap: 6px; box-sizing: border-box;">
                        <strong style="color: #475569; text-transform: uppercase; font-size: 10px; display: block; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 2px;">🔓 Access Privileges</strong>
                        
                       <?php 
						$all_perms = $conn->query("SELECT TRIM(permission_key) AS clean_perm_key, display_name FROM system_permissions WHERE TRIM(permission_key) != '' ORDER BY display_name ASC");

						if($all_perms && $all_perms->num_rows > 0):
							while($p_row = $all_perms->fetch_assoc()):
								// Using clean string keys prevents mismatch breaks
								$chk = in_array($p_row['clean_perm_key'], $assigned_views) ? 'checked' : '';
						?>
                            <!-- FIXED MASK CHECK: Uses your verified dynamic session string comparison loops -->
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #334155; margin: 0; font-weight: 500; padding: 1px 0; <?php echo ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) ? 'pointer-events:none; opacity:0.7;' : ''; ?>">
							<!-- FIXED: Grouped your checkboxes array uniquely using the specific loop staff_id variable -->
							<input type="checkbox" name="allowed_views[<?php echo $staff_id; ?>][]" value="<?php echo htmlspecialchars($p_row['clean_perm_key']); ?>" <?php echo $chk; ?> style="margin: 0; width: 14px; height: 14px; flex-shrink: 0; cursor: pointer;" <?php echo ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')) ? 'disabled' : ''; ?>> 
							<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($p_row['display_name']); ?></span>
							</label>

						<?php 
							endwhile;
						endif; 
						?>

                    </div>
                    
                    <!-- BUTTON ACTIONS SUBMITS -->
                    <div style="display: flex; gap: 8px; width: 100%; box-sizing: border-box; margin-top: 8px;">
                        
                        <!-- FIXED DYNAMIC HIERARCHY PROTECTION LAYER: Standard Admins can never touch a Super Admin card row -->
                        <?php if ($row_role_name === 'super_admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin')): ?>
                            <span style="background: #f1f5f9; color: #94a3b8; padding: 0 12px; border-radius: 6px; font-weight: bold; font-size: 12px; border: 1px dashed #cbd5e1; cursor: not-allowed; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; line-height: 1; height: 32px; box-sizing: border-box; width: 100%;">
                                🛡️ Protected (Super Admin Access Only)
                            </span>
                        <?php else: ?>
                            <!-- Save Changes Form Submit Button -->
                            <button type="submit" name="update_staff_credentials" style="background: #3b82f6; color: white; border: none; padding: 9px 16px; border-radius: 6px; font-weight: bold; font-size: 12px; cursor: pointer; flex: 1; line-height: 1; height: 32px;">Save Changes</button>
                            
                            <!-- CONDITIONAL SAFETY LAYER: Disallow self-purging from your own session -->
                            <?php if ($staff_id !== intval($_SESSION['user_id'])): ?>
                                <a href="dashboard.php?view=manage_staff.php&delete_staff_id=<?php echo $row['id']; ?>" 
                                   class="purge-action-trigger-link"
                                   onclick="return confirm('⚠️ SYSTEM PURGE WARNING:\n\nAre you sure you want to soft-purge the profile account for <?php echo htmlspecialchars($row['fullname']); ?>? This flags them as inactive while protecting your historical orders and KRA audit trails.');" 
                                   style="background: #ef4444; color: white; padding: 0 12px; border-radius: 6px; font-weight: bold; font-size: 12px; cursor: pointer; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; line-height: 1; height: 32px; box-sizing: border-box;">
                                    Purge
                                </a>
                            <?php else: ?>
                                <span style="background: #e2e8f0; color: #94a3b8; padding: 0 12px; border-radius: 6px; font-weight: bold; font-size: 12px; border: 1px dashed #cbd5e1; cursor: not-allowed; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; line-height: 1; height: 32px; box-sizing: border-box;">
                                    🔒 Locked
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    <?php endwhile; else: ?>
        <div style="text-align: center; padding: 40px; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 8px; font-style: italic; background: #f8fafc;">
            No historical operational staff accounts registered inside system arrays.
        </div>
    <?php endif; ?>
    
    </div>
</div>
<style>
.staff-network-tools{display:flex;gap:10px;align-items:center;margin:0 0 18px}.staff-network-tools input{flex:1;padding:11px 13px;border:1px solid #cbd5e1;border-radius:9px}.staff-network-count{padding:7px 11px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:800;white-space:nowrap}.staff-row-update-form>div{transition:border-color .2s,box-shadow .2s,transform .2s}.staff-row-update-form>div:hover{border-color:#bfdbfe!important;box-shadow:0 8px 20px rgba(15,23,42,.055);transform:translateY(-1px)}.staff-row-update-form input:focus,.staff-row-update-form select:focus{outline:3px solid rgba(59,130,246,.14);border-color:#3b82f6!important}.staff-row-update-form.is-hidden{display:none!important}.staff-row-update-form button:disabled{opacity:.6;cursor:wait}@media(max-width:700px){.staff-network-tools{align-items:stretch;flex-direction:column}.staff-network-count{text-align:center}.staff-row-update-form>div{gap:14px!important;padding:16px!important}}
</style>
<script>
(function(){
 const csrf=<?=json_encode($staff_csrf)?>;
 document.querySelectorAll('form').forEach(form=>{if(form.querySelector('[name=csrf_token]'))return;const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=csrf;form.appendChild(token);});
 document.querySelectorAll('select[name=new_role_id],select[name=role_id]').forEach(select=>{[...select.options].forEach(option=>{if(option.textContent.trim().toLowerCase()==='customer')option.remove();});});
 const registry=[...document.querySelectorAll('.staff-row-update-form')];
 if(registry.length){
  const board=registry[0].parentElement;
  const tools=document.createElement('div');
  tools.className='staff-network-tools';
  tools.innerHTML='<input type=search><span class=staff-network-count></span>';
  board.parentElement.insertBefore(tools,board);
  const input=tools.querySelector('input'),count=tools.querySelector('span');
  input.placeholder='Search by name, email, role or ID';
  function filter(){const q=input.value.trim().toLowerCase();let shown=0;registry.forEach(form=>{const visible=!q||form.textContent.toLowerCase().includes(q)||form.innerHTML.toLowerCase().includes(q);form.classList.toggle('is-hidden',!visible);if(visible)shown++;});count.textContent=shown+' active staff';}
  input.addEventListener('input',filter);filter();
 }
 document.querySelectorAll('.purge-action-trigger-link').forEach(link=>{
  link.textContent='Suspend access';
  link.removeAttribute('onclick');
  link.addEventListener('click',event=>{
   event.preventDefault();
   const match=link.href.match(/delete_staff_id=(\d+)/);
   if(!match||!confirm('Suspend this staff account and revoke all permissions?'))return;
   const form=document.createElement('form');
   form.method='post';form.action='manage_staff.php';
   const fields={suspend_staff_account:'1',staff_id:match[1],csrf_token:csrf};
   Object.keys(fields).forEach(name=>{const input=document.createElement('input');input.name=name;input.value=fields[name];form.appendChild(input);});
   document.body.appendChild(form);form.submit();
  });
 });
})();
</script>
