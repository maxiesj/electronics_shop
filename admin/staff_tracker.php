real_escape_string($search_term) . "%' 
                    OR sl.action_details LIKE '%" . $conn->real_escape_string($search_term) . "%'
                    OR u.fullname LIKE '%" . $conn->real_escape_string($search_term) . "%'";
}

$query_str .= " ORDER BY sl.id DESC LIMIT 30";
$result = $conn->query($query_str);
?>

<!-- Main Audit Tracker Content Grid Card -->
<div class="card" style="background:white; padding:30px; border-radius:12px; border:1px solid #e2e8f0; width:100%; box-sizing:border-box; font-family:sans-serif; margin-top:20px;">
    <h2 style="margin-top:0; color:#0f172a; display:flex; align-items:center; gap:8px;">🔷 System Outbox Activity Log Timeline</h2>
    
    <!-- Filter Search Form Input Bar Component -->
    <!-- FIXED: Redirects form action targeting inside your central template routing dashboard wrapper to prevent page breakouts -->
    <form method="GET" action="dashboard.php" id="log-search-form" style="display:flex; gap:10px; margin-bottom:25px; max-width:600px; margin-top: 15px;">
        <!-- CRITICAL WORKSPACE HOLDERS: Guarantees your browser engine tracks the current sub-view on reloads -->
        <input type="hidden" name="view" value="workspace_tracker">
        
        <input type="text" id="log-search-input" name="search_log" placeholder="Search system audit logs..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex:1; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
        <button type="submit" id="log-filter-btn" style="background:#3b82f6; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer; font-size:14px;">Filter Stream</button>
    </form>

    <div style="overflow-x: auto; width: 100%;">
        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
                <tr style="color:#64748b; text-transform:uppercase; font-size:11px; border-bottom:2px solid #e2e8f0; background:#f8f9fa;">
                    <th style="padding:12px;">Log ID</th>
                    <th style="padding:12px;">Operator Identity</th>
                    <th style="padding:12px;">Action Class</th>
                    <th style="padding:12px;">Detailed Activity Summary Description</th>
                    <th style="padding:12px;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #edf2f7; color:#334155;">
                        <td style="padding:12px; color:#64748b;">#<?php echo $row['log_id']; ?></td>
                        <td style="padding:12px; font-weight:bold;"><?php echo htmlspecialchars($row['fullname']); ?></td>
                        <td style="padding:12px;"><span style="background:#e0f2fe; color:#0369a1; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:11px; text-transform:uppercase;"><?php echo htmlspecialchars($row['action_class']); ?></span></td>
                        <td style="padding:12px; color:#475569;"><?php echo htmlspecialchars($row['activity_description']); ?></td>
                        <td style="padding:12px; color:#94a3b8; font-size:12px;"><?php echo date('d M Y - h:i A', strtotime($row['logged_at'])); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8; font-style:italic;">No historical activity logs found matching the filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
