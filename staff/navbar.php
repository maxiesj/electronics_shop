<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';

$workspace_role = getAuthenticatedWorkspaceRole();
if ($workspace_role === '') {
    header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
$staff_name = $_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Staff Member';
$is_super_admin = $workspace_role === 'super_admin';

// Fetch the allowed modules array for the logged-in user
$my_privileges = [];
$priv_stmt = $conn->prepare("SELECT DISTINCT TRIM(target_view) AS view_file FROM staff_permissions WHERE user_id = ?");
if ($priv_stmt) {
    $priv_stmt->bind_param('i', $user_id);
    if ($priv_stmt->execute()) {
        $priv_res = $priv_stmt->get_result();
        while ($p_row = $priv_res->fetch_assoc()) {
            $my_privileges[] = $p_row['view_file'];
        }
    }
    $priv_stmt->close();
}

// Identify current file name for highlighting the active state anchor
$active_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.staff-shared-nav{background:#111827;color:#fff;padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:20px;box-shadow:0 4px 12px #0f172a29;box-sizing:border-box;position:relative;z-index:100}.staff-shared-nav .nav-brand{font-weight:900;font-size:16px;color:#fb923c;white-space:nowrap}.staff-shared-nav .nav-center-links{display:flex;align-items:center;justify-content:center;gap:5px;flex:1;flex-wrap:wrap}.staff-shared-nav .nav-center-links a{color:#cbd5e1;text-decoration:none;padding:8px 10px;border-radius:7px;font-size:13px;font-weight:700;white-space:nowrap}.staff-shared-nav .nav-center-links a:hover{background:#1f2937;color:#fff}.staff-shared-nav .nav-center-links a.active{background:#2563eb;color:#fff}.staff-shared-nav .nav-right-meta{display:flex;align-items:center;gap:12px;font-size:12px;white-space:nowrap}.staff-shared-nav .logout-btn{color:#fecaca!important;text-decoration:none;font-weight:800;border:1px solid #7f1d1d;padding:7px 10px;border-radius:7px;background:#7f1d1d33}@media(max-width:900px){.staff-shared-nav{flex-wrap:wrap}.staff-shared-nav .nav-center-links{order:3;flex-basis:100%}}@media(max-width:620px){.staff-shared-nav{padding:12px}.staff-shared-nav .nav-center-links{overflow-x:auto;flex-wrap:nowrap;justify-content:flex-start}.staff-shared-nav .nav-right-meta{width:100%;justify-content:space-between}}
</style>
<nav class="staff-shared-nav" aria-label="Staff workspace navigation">
    <div class="nav-brand">&#9889; ADONAK STAFF</div>
    <div class="nav-center-links">
        <!-- Core Dashboard Base Module -->
        <a href="staff_dashboard.php" class="<?= ($active_page == 'staff_dashboard.php') ? 'active' : ''; ?>">&#9632; Overview</a>
        
        <!-- Order Books Privilege Check -->
        <?php if ($is_super_admin || in_array('manage_orders.php', $my_privileges, true)): ?>
            <a href="manage_orders.php" class="<?= ($active_page == 'manage_orders.php') ? 'active' : ''; ?>">&#128230; Order Books</a>
        <?php endif; ?>
        
        <!-- Pole Pole Privilege Check -->
        <?php if ($is_super_admin || in_array('layaway_defaulters.php', $my_privileges, true)): ?>
            <a href="layaway_defaulters.php" class="<?= ($active_page == 'layaway_defaulters.php') ? 'active' : ''; ?>">&#9201; Pole Pole</a>
        <?php endif; ?>
        
        <!-- Stock Alarms Privilege Check -->
        <?php if ($is_super_admin || in_array('low_stock_monitor.php', $my_privileges, true)): ?>
            <a href="low_stock_monitor.php" class="<?= ($active_page == 'low_stock_monitor.php') ? 'active' : ''; ?>">&#9888; Stock Alarms</a>
        <?php endif; ?>
        
        <!-- M-Pesa Check Privilege Check -->
        <?php if ($is_super_admin || in_array('mpesa_checker.php', $my_privileges, true)): ?>
            <a href="mpesa_checker.php" class="<?= ($active_page == 'mpesa_checker.php') ? 'active' : ''; ?>">&#128241; M-Pesa Check</a>
        <?php endif; ?>
        
        <!-- Clients Privilege Check -->
        <?php if ($is_super_admin || in_array('manage_customers.php', $my_privileges, true)): ?>
            <a href="manage_customers.php" class="<?= ($active_page == 'manage_customers.php') ? 'active' : ''; ?>">&#128101; Clients</a>
        <?php endif; ?>
        
        <!-- Reviews Privilege Check -->
        <?php if ($is_super_admin || in_array('manage_reviews.php', $my_privileges, true)): ?>
            <a href="manage_reviews.php" class="<?= ($active_page == 'manage_reviews.php') ? 'active' : ''; ?>">&#9733; Reviews</a>
        <?php endif; ?>
    </div>
    <div class="nav-right-meta">
        <span style="color: #9ca3af; font-weight: 600;">Operator: <strong style="color: white; text-transform: uppercase;"><?= htmlspecialchars($staff_name); ?></strong></span>
        <a href="../account_security.php" style="color:#d1d5db;text-decoration:none;font-weight:700;white-space:nowrap;">Security</a>
        <a href="../logout.php" class="logout-btn">Log Out</a>
    </div>
</nav>

<script>window.ADONAK_SESSION_EXPIRE_URL="../session_expire.php";window.ADONAK_SESSION_KEEPALIVE_URL="../session_keepalive.php";</script>
<script src="../js/page-progress-dialog.js"></script>
<script src="../js/session-idle.js"></script>
