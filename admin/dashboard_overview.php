<?php
// Ensure your universal security gatekeeper file is pulled in safely to maintain consistency
require_once dirname(__FILE__) . '/../session_auth.php';

// Initialize session if not active inside background asynchronous operations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Establish database context mapping safely inside the AJAX view panel
$db_path = dirname(__FILE__) . '/../db.php';
if (file_exists($db_path)) {
    include_once $db_path;
} else {
    include_once '../db.php';
}

// FIXED SECURITY GATEPOINT: Blocks unauthenticated users or loose staff profiles from calling data dumps directly
if (!verifyWorkspaceClearance('dashboard_overview.php')) {
    echo "<div style='padding:20px; background:#fef2f2; color:#991b1b; border:1px solid #fee2e2; border-radius:8px;'>⚠️ Access Denied: You do not hold sufficient authorization metrics to stream dashboard metrics.</div>";
    exit();
}

// 2. Fetch Fresh Numeric Counter Indicators on every AJAX request loop
$p_res = $conn->query("SELECT COUNT(id) AS t FROM orders WHERE LOWER(TRIM(order_status))='pending'");
$count_pending = $p_res ? intval($p_res->fetch_assoc()['t']) : 0;

$l_res = $conn->query("SELECT COUNT(id) AS t FROM layaway_plans WHERE LOWER(TRIM(status))='active'");
$count_layaway = $l_res ? intval($l_res->fetch_assoc()['t']) : 0;

// Optimized stock alert calculation threshold boundary
$s_res = $conn->query("SELECT COUNT(id) AS t FROM products WHERE stock_quantity<5");
$count_low_stock = $s_res ? intval($s_res->fetch_assoc()['t']) : 0;

// FIXED: Joined with the dynamic roles table and filtered out soft-purged staff profiles
$st_query = "
    SELECT COUNT(u.id) AS t 
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.id 
    WHERE LOWER(TRIM(r.role_name)) IN ('staff', 'admin', 'super_admin', 'cashier', 'auditor', 'supervissoor', 'cleaner') 
    AND (u.account_status != 'purged' OR u.account_status IS NULL)
";
$st_res = $conn->query($st_query);
$count_staff = $st_res ? intval($st_res->fetch_assoc()['t']) : 0;


// 3. Calculate Monthly Revenue (Using your dynamic orders calendar matching checks)
$current_month = date('Y-m'); 
$total_revenue = 0;

$query_revenue = "SELECT SUM(total_amount) AS total_rev FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month' AND LOWER(TRIM(order_status)) != 'cancelled'";
$result_revenue = $conn->query($query_revenue);

if ($result_revenue && $row_revenue = $result_revenue->fetch_assoc()) {
    $total_revenue = $row_revenue['total_rev'] ?? 0;
}

// 4. Calculate Average Order Value
$avg_order_value = 0;
$query_aov = "SELECT AVG(total_amount) AS avg_value FROM orders WHERE LOWER(TRIM(order_status)) != 'cancelled'";
$result_aov = $conn->query($query_aov);

if ($result_aov && $row_aov = $result_aov->fetch_assoc()) {
    $avg_order_value = $row_aov['avg_value'] ?? 0;
}

// 5. Count Pending Wallet Approvals
$pending_wallets = 0;
// FIXED: SQL alias explicitly matches the associative key lookup below
$query_wallets = "SELECT COUNT(*) AS total_pending_wallets FROM payments WHERE payment_status = 'pending'";
$result_wallets = $conn->query($query_wallets);

if ($result_wallets && $row_wallets = $result_wallets->fetch_assoc()) {
    $pending_wallets = $row_wallets['total_pending_wallets'] ?? 0;
}

// 6. Count Active Defaulters / Installment Issues
$active_defaulters = 0;
$query_defaulters = "SELECT COUNT(*) AS total_defaulters FROM layaway_plans WHERE status = 'defaulted'";
$result_defaulters = $conn->query($query_defaulters);

if ($result_defaulters && $row_defaulters = $result_defaulters->fetch_assoc()) {
    $active_defaulters = $row_defaulters['total_defaulters'] ?? 0;
}

// 7. Fetch Recent Store Order Streams for Pipeline Node (FIXED: Skips soft-purged user profile entries)
$query_recent = "
    SELECT o.id, u.fullname, o.total_amount, o.order_status 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE (u.account_status != 'purged' OR u.account_status IS NULL)
    ORDER BY o.id DESC LIMIT 5
";
$recent = $conn->query($query_recent);

// Active shifts are loaded for the Super Admin override selector. The record
// ID is submitted so the administrator explicitly chooses who to conclude.
$activeAttendance = [];
$activeAttendanceResult = $conn->query("SELECT id, staff_name, shift_type, clock_in_time FROM staff_attendance WHERE shift_status = 'Active' ORDER BY clock_in_time ASC");
if ($activeAttendanceResult) {
    while ($attendance = $activeAttendanceResult->fetch_assoc()) {
        $activeAttendance[] = $attendance;
    }
}
?>

<!-- Top Administrative Navigation Bar Component with Integrated Shift Controller -->
<!-- TOP ADMISTRATIVE HUB NAVBAR - PERFECTLY CENTERED FOR ALL ROLES -->
<div class="navbar" style="display: flex; align-items: center; justify-content: center; width: 100%; box-sizing: border-box; background: #ffffff; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; flex-wrap: wrap; gap: 30px;">
    
    <!-- Left Module: Context Route Path Titles -->
    <div style="font-size: 1.1rem; color: #0f172a; font-family: sans-serif; display: flex; align-items: center;">
        <strong>Administrative Hub</strong>&nbsp;/&nbsp;<span style="color: #64748b;" id="current-view-title">Dashboard Overview</span>
    </div>

    <!-- Center Module: Premium Shift Override Admin Matrix -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <div class="header-shift-panel" style="display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-family: sans-serif;">
            <span style="font-size: 0.85rem; font-weight: bold; color: #e11d48; display: flex; align-items: center; gap: 4px; white-space: nowrap;">
                🛡️ Admin Shift Override:
            </span>
            <form id="adminOutsideOverrideForm" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                <input type="hidden" name="shift_action" value="clock_out">
                <!-- Each option represents one open timecard; completed shifts are intentionally excluded. -->
                <select name="attendance_id" id="outsideAttendanceId" <?= empty($activeAttendance) ? 'disabled' : ''; ?> style="background: #ffffff; color: #1e293b; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500; outline: none; max-width: 250px;">
                    <?php if (empty($activeAttendance)): ?>
                        <option value="">No active shifts</option>
                    <?php else: foreach ($activeAttendance as $attendance): ?>
                        <option value="<?= (int)$attendance['id']; ?>"><?= htmlspecialchars($attendance['staff_name']); ?> — <?= htmlspecialchars(ucwords(str_replace('_', ' ', $attendance['shift_type'] ?: 'regular'))); ?> (<?= date('H:i', strtotime($attendance['clock_in_time'])); ?>)</option>
                    <?php endforeach; endif; ?>
                </select>

                <!-- No active shift means there is nothing safe for an override to conclude. -->
                <button type="button" onclick="fireOutsideOverride()" <?= empty($activeAttendance) ? 'disabled' : ''; ?> style="background: #e11d48; color: white; border: none; padding: 5px 12px; border-radius: 4px; font-weight: 600; cursor: <?= empty($activeAttendance) ? 'not-allowed' : 'pointer'; ?>; font-size: 0.85rem; transition: background 0.2s; white-space: nowrap; opacity: <?= empty($activeAttendance) ? '0.55' : '1'; ?>;">
                    Conclude
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Right Module: Account Privilege Tracker Label -->
    <div style="font-size: 14px; color: #64748b; font-family: sans-serif; display: flex; align-items: center; white-space: nowrap;">
        Role Access:&nbsp;<strong style="color: #0f172a;"><?php echo ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'Super Admin')); ?></strong>
    </div>
</div>


<!-- Operational and financial KPI cards -->
<section class="admin-kpi-grid" aria-label="Business overview metrics">
    <article class="admin-kpi-card kpi-amber admin-pending-card" style="--kpi-delay: 0ms;">
        <div class="admin-kpi-heading">
            <h3>Pending Orders</h3>
            <span class="admin-kpi-icon" aria-hidden="true">&#128230;</span>
        </div>
        <div class="admin-kpi-value"><?php echo $count_pending; ?></div>
        <p class="admin-kpi-meta<?php echo $count_pending > 0 ? ' needs-attention' : ''; ?>">
            <span class="admin-kpi-status-dot" aria-hidden="true"></span>
            <?php echo $count_pending > 0 ? 'Awaiting dispatch' : 'All orders dispatched'; ?>
        </p>
    </article>

    <article class="admin-kpi-card kpi-blue admin-layaway-card" style="--kpi-delay: 55ms;">
        <div class="admin-kpi-heading">
            <h3>Active Layaways</h3>
            <span class="admin-kpi-icon" aria-hidden="true">&#128197;</span>
        </div>
        <div class="admin-kpi-value"><?php echo $count_layaway; ?></div>
        <p class="admin-kpi-meta"><span class="admin-kpi-status-dot" aria-hidden="true"></span>Installment plans</p>
    </article>

    <article class="admin-kpi-card admin-low-stock-card admin-kpi-double-link <?php echo $count_low_stock > 0 ? 'kpi-red is-alerting' : 'kpi-green'; ?>" style="--kpi-delay: 110ms;" data-target="low_stock_monitor.php" role="button" tabindex="0" aria-label="Open Low Stock Monitor. Double-click or press Enter." title="Double-click to open Low Stock Monitor">
        <div class="admin-kpi-heading">
            <h3>Low Stock Alerts</h3>
            <span class="admin-kpi-icon" aria-hidden="true"><?php echo $count_low_stock > 0 ? '&#9888;' : '&#10003;'; ?></span>
        </div>
        <div class="admin-kpi-value"><?php echo $count_low_stock; ?></div>
        <p class="admin-kpi-meta<?php echo $count_low_stock > 0 ? ' needs-attention' : ''; ?>">
            <span class="admin-kpi-status-dot" aria-hidden="true"></span>
            <?php echo $count_low_stock > 0 ? 'Items under 5 units' : 'Stock levels healthy'; ?>
        </p>
    </article>

    <article class="admin-kpi-card kpi-green admin-staff-card" style="--kpi-delay: 165ms;">
        <div class="admin-kpi-heading">
            <h3>Staff Accounts</h3>
            <span class="admin-kpi-icon" aria-hidden="true">&#128101;</span>
        </div>
        <div class="admin-kpi-value"><?php echo $count_staff; ?></div>
        <p class="admin-kpi-meta"><span class="admin-kpi-status-dot" aria-hidden="true"></span>Authorized system logins</p>
    </article>

    <article class="admin-kpi-card kpi-blue admin-revenue-card" style="--kpi-delay: 220ms;" aria-label="Monthly revenue summary">
        <div class="admin-kpi-heading">
            <h3>Monthly Revenue</h3>
            <span class="admin-revenue-contactless" aria-hidden="true">)))</span>
        </div>
        <div class="admin-revenue-chip" aria-hidden="true"></div>
        <div class="admin-kpi-value admin-kpi-currency"><span>Ksh</span> <?php echo number_format($total_revenue); ?></div>
        <div class="admin-revenue-card-footer">
            <span>Current Calendar Month</span>
            <span class="admin-revenue-card-brand">ADONAK Finance</span>
        </div>
    </article>

    <article class="admin-kpi-card kpi-green admin-average-card" style="--kpi-delay: 275ms;">
        <div class="admin-kpi-heading">
            <h3>Avg Order Value</h3>
            <span class="admin-kpi-icon" aria-hidden="true">&#128176;</span>
        </div>
        <div class="admin-kpi-value admin-kpi-currency"><span>Ksh</span> <?php echo number_format($avg_order_value, 2); ?></div>
        <p class="admin-kpi-meta"><span class="admin-kpi-status-dot" aria-hidden="true"></span>Lifetime store average</p>
    </article>

    <article class="admin-kpi-card admin-kpi-double-link admin-wallet-card <?php echo $pending_wallets > 0 ? 'kpi-amber is-alerting' : 'kpi-green'; ?>" style="--kpi-delay: 330ms;" data-target="manage_wallets.php" role="button" tabindex="0" aria-label="Open Customer Wallet Approvals. Double-click or press Enter." title="Double-click to open Wallet Approvals">
        <div class="admin-kpi-heading">
            <h3>Wallet Approvals</h3>
            <span class="admin-kpi-icon" aria-hidden="true">&#128179;</span>
        </div>
        <div class="admin-kpi-value"><?php echo $pending_wallets; ?></div>
        <p class="admin-kpi-meta<?php echo $pending_wallets > 0 ? ' needs-attention' : ''; ?>">
            <span class="admin-kpi-status-dot" aria-hidden="true"></span>
            <?php echo $pending_wallets > 0 ? 'Pending actions' : 'No approvals waiting'; ?>
        </p>
    </article>

    <article class="admin-kpi-card admin-installment-card <?php echo $active_defaulters > 0 ? 'kpi-red is-alerting' : 'kpi-green'; ?>" style="--kpi-delay: 385ms;">
        <div class="admin-kpi-heading">
            <h3>Installment Flags</h3>
            <span class="admin-kpi-icon" aria-hidden="true"><?php echo $active_defaulters > 0 ? '&#9873;' : '&#10003;'; ?></span>
        </div>
        <div class="admin-kpi-value"><?php echo $active_defaulters; ?></div>
        <p class="admin-kpi-meta<?php echo $active_defaulters > 0 ? ' needs-attention' : ''; ?>">
            <span class="admin-kpi-status-dot" aria-hidden="true"></span>
            <?php echo $active_defaulters > 0 ? 'Active defaulters' : 'No active defaulters'; ?>
        </p>
    </article>
</section>

<div class="charts" style="display: flex; gap: 20px;">
    <!-- Revenue Performance Timeline Graph Card -->
    <div class="c-card" style="flex: 2; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0;">📈 Revenue Performance Timeline</h3>
        <div class="chart-box" style="height:220px; display:flex; align-items:flex-end; justify-content:space-around; padding-top:20px; background:#ffffff;">
            <?php 
            $m_data = []; 
            $max_m_val = 100;

            for ($i = 5; $i >= 0; $i--) {
                $month_key = date('Y-m', strtotime("-$i months"));
                $month_lbl = date('M Y', strtotime("-$i months"));
                $m_data[$month_key] = ['l' => $month_lbl, 'v' => 0.0];
            }

            $m_chart = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS r_key, DATE_FORMAT(created_at,'%b %Y') AS lbl, SUM(total_amount) AS yld FROM orders WHERE LOWER(TRIM(order_status))!='cancelled' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at ASC");

            if ($m_chart) {
                while ($mr = $m_chart->fetch_assoc()) {
                    $k = $mr['r_key'];
                    $v = floatval($mr['yld']); 
                    if ($v > $max_m_val) { $max_m_val = $v; }
                    if (isset($m_data[$k])) {
                        $m_data[$k]['v'] = $v;
                    }
                }
            }

            foreach ($m_data as $md): 
                $h = max(10, round(($md['v'] / $max_m_val) * 130)); 
            ?>
                <div class="bar-col" style="display:flex; flex-direction:column; align-items:center; flex-grow:1; height:100%; justify-content:flex-end; position:relative;">
                    <div class="bar-tip" style="position: relative; display: block; background: #0f172a; color: white; font-size: 10px; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; font-weight: bold; text-align: center;">
                        Ksh <?php echo number_format($md['v'], 0); ?>
                    </div>
                    <div class="bar-pill" style="height:<?php echo $h; ?>px; width:35px; background:#3b82f6; border-radius:4px 4px 0 0;"></div>
                    <div style="font-size:11px; margin-top:8px; font-weight:bold; color:#64748b; text-transform:uppercase;">
                        <?php echo $md['l']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Category Revenue Breakdowns Card (RESTORED FROM CUTOFF) -->
       <!-- Category Revenue Breakdowns Card (RESTORED FROM CUTOFF) -->
    <div class="c-card" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0;">📊 Category Revenue Breakdowns</h3>
        <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 25px;">
            <?php
            $c_chart = $conn->query("SELECT c.category_name AS cat, SUM(oi.quantity * oi.price) AS rev FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN categories c ON p.category_id=c.id JOIN orders o ON oi.order_id=o.id WHERE LOWER(TRIM(o.order_status))!='cancelled' GROUP BY c.id ORDER BY rev DESC LIMIT 4");
            $total_cat_rev = 0;
            $categories_data = [];

            if ($c_chart) {
                while ($row = $c_chart->fetch_assoc()) {
                    $total_cat_rev += floatval($row['rev']);
                    $categories_data[] = $row;
                }
            }

            if (empty($categories_data)) {
                echo "<p style='color: #64748b; font-size: 13px;'>No transaction breakdown data available yet.</p>";
            } else {
                foreach ($categories_data as $cat_row):
                    $percentage = $total_cat_rev > 0 ? round((floatval($cat_row['rev']) / $total_cat_rev) * 100) : 0;
            ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 5px;">
                        <span><?php echo strtoupper($cat_row['cat']); ?></span>
                        <span style="color: #3b82f6;"><?php echo $percentage; ?>% <span style="color:#94a3b8; font-weight:normal;">(Ksh <?php echo number_format($cat_row['rev']); ?>)</span></span>
                    </div>
                    <div style="width: 100%; background: #f1f5f9; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="width: <?php echo $percentage; ?>%; background: #3b82f6; height: 100%;"></div>
                    </div>
                </div>
            <?php 
                endforeach;
            } 
            ?>
        </div>
    </div>
</div>

<!-- Recent Orders Pipeline Component Node -->
<div class="c-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 20px;">
    <h3 style="margin-top:0;">📦 Recent Store Order Pipeline</h3>
    <div class="table-scroll-container">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 2px solid #f1f5f9; color: #64748b; font-weight: bold;">
                <th style="padding: 10px 5px;">Order ID</th>
                <th style="padding: 10px 5px;">Customer</th>
                <th style="padding: 10px 5px;">Total Amount</th>
                <th style="padding: 10px 5px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($recent && $recent->num_rows > 0) {
                while ($ro = $recent->fetch_assoc()) {
                    $status_color = '#64748b';
                    $status_bg = '#f1f5f9';
                    $status_text = strtolower(trim($ro['order_status']));
                    
                    if ($status_text === 'pending') { $status_color = '#d97706'; $status_bg = '#fef3c7'; }
                    elseif ($status_text === 'delivered') { $status_color = '#16a34a'; $status_bg = '#dcfce7'; }
                    elseif ($status_text === 'cancelled') { $status_color = '#dc2626'; $status_bg = '#fee2e2'; }
            ?>
                <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                    <td style="padding: 12px 5px; font-weight: bold;">#<?php echo $ro['id']; ?></td>
                    <td style="padding: 12px 5px;"><?php echo htmlspecialchars($ro['fullname']); ?></td>
                    <td style="padding: 12px 5px; font-weight: 500;">Ksh <?php echo number_format($ro['total_amount'], 2); ?></td>
                    <td style="padding: 12px 5px;">
                        <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: <?php echo $status_color; ?>; background: <?php echo $status_bg; ?>; text-transform: uppercase;">
                            <?php echo $ro['order_status']; ?>
                        </span>
                    </td>
                </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='4' style='padding: 20px; text-align: center; color: #94a3b8;'>No recent order activities recorded.</td></tr>";
            }
            ?>
        </tbody>
    </table>
	</div>
</div>

