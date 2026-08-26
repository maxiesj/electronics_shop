<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$db_path = dirname(__FILE__) . '/../db.php';
include_once file_exists($db_path) ? $db_path : '../db.php';

// FIXED SECURITY CHECK: Allows both Super Admin and regular Admins automatically 
// and drops the header redirect warning inside asynchronous layout containers
if (!verifyWorkspaceClearance('admin_create_user.php')) {
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$error_msg = "";
$success_msg = "";

// 2. HANDLE DATA SUBMISSION
if (isset($_POST['create_user'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); 
    $password = trim($_POST['password']);
    $role_id = intval($_POST['role']); // CHANGED: Now expects a numeric role integer (e.g., 1, 2) from your form dropdown

    if (empty($fullname) || empty($email) || empty($phone) || empty($password) || empty($role_id)) {
        $error_msg = "Please populate all fields completely.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please provide a valid email format pattern.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_msg = "This email profile is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // FIXED STRUCTURAL COLUMN: Targets your relational role_id column using integer binding ('i')
            $insert_stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, password, role_id) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssi", $fullname, $email, $phone, $hashed_password, $role_id);
            
            if ($insert_stmt->execute()) {
                $success_msg = "User profile account successfully created!";
            } else {
                $error_msg = "Database insertion error occurred: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// 3. FETCH DATA FOR DISPLAY VIA SYSTEM STRUCTURAL ROLES JOIN
// FIXED: Pulls dynamic rank profiles relative to your active roles table structure
$sql = "SELECT u.id, u.fullname, u.email, u.phone, r.role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE r.role_name IN ('admin', 'super_admin', 'staff', 'cashier') 
        ORDER BY u.fullname ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create System User</title>
    <style>
    /* 1. Global Baseline Reset Styles */
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; display: flex; min-height: 100vh; }
    
    /* 2. Left Sidebar Navigation Panel Layout (Desktop Default View) */
    .sidebar { width: 260px; height: 100vh; background-color: #2c3e50; color: white; padding: 20px; position: fixed; top: 0; left: 0; z-index: 100; transition: transform 0.3s ease; }
    .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #34495e; padding-bottom: 10px; color: #ecf0f1; margin-top: 0; }
    .sidebar a { display: block; color: #bdc3c7; padding: 12px 15px; text-decoration: none; border-radius: 4px; margin-bottom: 8px; font-size: 14px; transition: background-color 0.2s, color 0.2s; }
    .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; }
    
    /* 3. Main Workspace Area Containers */
    .main-content { margin-left: 260px; flex-grow: 1; padding: 30px; min-height: 100vh; width: calc(100% - 260px); box-sizing: border-box; transition: margin-left 0.3s ease, width 0.3s ease; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; box-sizing: border-box; }
    
    /* 4. Form Complex Layout Input Systems & Entry Matrices */
    .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; box-sizing: border-box; }
    .form-group { display: flex; flex-direction: column; }
    label { margin-bottom: 6px; font-weight: 600; color: #333; font-size: 14px; }
    input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; height: 40px; box-sizing: border-box; background-color: #fff; transition: border-color 0.2s, box-shadow 0.2s; }
    input:focus, select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1); }
    
    /* Primary Submission Button Block */
    .btn { padding: 10px 20px; border-radius: 4px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; background-color: #2ecc71; color: white; width: 100%; margin-top: 15px; height: 40px; display: inline-flex; align-items: center; justify-content: center; text-transform: uppercase; transition: background-color 0.2s; box-sizing: border-box; }
    .btn:hover { background-color: #27ae60; }
    
    /* Data Indicators & System Pills */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: white; display: inline-block; white-space: nowrap; }
    .badge-admin { background-color: #e74c3c; }
    .badge-staff { background-color: #3498db; }
    
    /* Notifications & Alert Elements */
    .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; font-weight: 500; box-sizing: border-box; }
    .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    /* Tabular Parameters Ledger */
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 14px; text-align: left; border-bottom: 1px solid #eaeaea; vertical-align: middle; }
    th { background-color: #f8f9fa; color: #555; font-weight: 600; }
    tr:hover { background-color: #fcfcfc; }

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
        .sidebar br { display: none; } /* Prevents unwanted line breaks inside top links row */
        
        /* Render side hyperlinks horizontally into scrollable rows */
        .sidebar a { display: inline-block; margin-bottom: 0; margin-right: 8px; padding: 8px 12px; font-size: 13px; }
        
        /* Reset content box dimensions to fill the whole screen viewport layout */
        .main-content { margin-left: 0; width: 100%; padding: 20px; min-height: auto; }
        
        /* Downscale grid to 2-columns on medium tablet options viewports */
        .form-grid { grid-template-columns: repeat(2, 1fr); }
    }

    /* 📱 SMALL PORTRAIT DEVICES BREAKPOINT (PHONES - Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Enable swipable side scrolling for links row menu options */
        .sidebar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding: 12px; }
        .sidebar h2 { font-size: 16px; margin-bottom: 10px; text-align: left; border-bottom: none; padding-bottom: 0; }
        .sidebar a { font-size: 12px; padding: 6px 10px; }
        
        /* Card boundary adjustments */
        .main-content { padding: 12px; }
        .card { padding: 16px; margin-bottom: 16px; }
        
        /* Flatten multi-column grids down into independent single-width rows */
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        
        /* Maximize target boundaries for thumb tap operations */
        input, select { height: 44px; font-size: 15px; }
        .btn { height: 44px; font-size: 15px; margin-top: 10px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; font-size: 13px; }
    }
</style>

</head>
<body>

    <div class="sidebar">
        <h2>Electronics Shop</h2>
        <a href="dashboard.php">📦 View Inventory</a>
        <a href="add_product.php">➕ Add New Product</a>
        <a href="manage_categories.php">📁 Manage Categories</a>
        <a href="add_brand.php">🏷️ Add Brand Component</a>
        <a href="admin_create_user.php" class="active">👤 Add System Staff</a>
        <a href="../logout.php" style="margin-top: 60px; background-color: #c0392b; color: white; text-align: center;">Logout</a>
    </div>

    <div class="main-content">
        <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

        <div class="card">
            <h2>Provision New Staff Account</h2>
            <form method="POST" action="admin_create_user.php">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" required></div>
                    <div class="form-group"><label>Account Password</label><input type="password" name="password" required></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Access Role</label>
                        <select name="role" required>
                            <option value="">-- Choose Access Permission --</option>
                            <option value="staff">Staff (Standard Operations)</option>
                            <option value="admin">Admin (Full Control Access)</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 3;"><button type="submit" name="create_user" class="btn">Provision Account</button></div>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Active System Profiles</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>User Full Name</th><th>Email</th><th>Phone</th><th>Clearance Level</th></tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><span class="badge <?php echo ($row['role'] === 'admin') ? 'badge-admin' : 'badge-staff'; ?>"><?php echo $row['role']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #999;">No profiles indexed.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>