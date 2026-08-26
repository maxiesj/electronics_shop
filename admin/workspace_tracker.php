<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';

if (!verifyWorkspaceClearance('workspace_tracker.php') && !verifyWorkspaceClearance('workspace_tracker')) {
    echo '<div class="tracker-denied">Access denied.</div>';
    exit;
}

$search=trim($_GET['search_log']??'');
$category=trim($_GET['category']??'');
$operator=max(0,(int)($_GET['operator']??0));
$date_from=trim($_GET['date_from']??'');
$date_to=trim($_GET['date_to']??'');
$page=max(1,(int)($_GET['page']??1));
$per_page=15;

$where=[];
if($search!==''){
    $safe=$conn->real_escape_string($search);
    $where[]="(sl.action_type LIKE '%{$safe}%' OR sl.action_details LIKE '%{$safe}%' OR sl.staff_name LIKE '%{$safe}%' OR u.fullname LIKE '%{$safe}%')";
}
if($category!==''){
    $safe=$conn->real_escape_string($category);
    if($category==='Unclassified') $where[]="(sl.action_type='' OR sl.action_type IS NULL)";
    else $where[]="sl.action_type='{$safe}'";
}
if($operator>0) $where[]='sl.user_id='.$operator;
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from)) $where[]="sl.logged_at>='".$conn->real_escape_string($date_from)." 00:00:00'";
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)) $where[]="sl.logged_at<='".$conn->real_escape_string($date_to)." 23:59:59'";
$where_sql=$where?' WHERE '.implode(' AND ',$where):'';

$from_sql=" FROM staff_logs sl LEFT JOIN users u ON sl.user_id=u.id";
$count_result=$conn->query('SELECT COUNT(*) AS total'.$from_sql.$where_sql);
$total_rows=$count_result?(int)$count_result->fetch_assoc()['total']:0;
$total_pages=max(1,(int)ceil($total_rows/$per_page));
$page=min($page,$total_pages);
$offset=($page-1)*$per_page;

$select_sql="SELECT sl.id AS log_id,sl.user_id,sl.action_type,sl.action_details,sl.product_target,sl.logged_at,COALESCE(NULLIF(u.fullname,''),sl.staff_name,'Former staff member') AS operator_name";
$result=$conn->query($select_sql.$from_sql.$where_sql." ORDER BY sl.id DESC LIMIT {$per_page} OFFSET {$offset}");

$operators=$conn->query("SELECT DISTINCT sl.user_id,COALESCE(NULLIF(u.fullname,''),sl.staff_name,'Former staff member') AS operator_name FROM staff_logs sl LEFT JOIN users u ON sl.user_id=u.id ORDER BY operator_name");
$categories=$conn->query("SELECT DISTINCT CASE WHEN action_type='' OR action_type IS NULL THEN 'Unclassified' ELSE action_type END AS category_name FROM staff_logs ORDER BY category_name");

$today_stats=['activities'=>0,'operators'=>0,'financial'=>0,'security'=>0];
$stats=$conn->query("SELECT COUNT(*) activities,COUNT(DISTINCT user_id) operators,SUM(action_type='Financial Update' OR action_details LIKE '%wallet%' OR action_details LIKE '%refund%') financial,SUM(action_type='Staff Login' OR action_details LIKE '%login%') security FROM staff_logs WHERE DATE(logged_at)=CURRENT_DATE()");
if($stats) $today_stats=array_merge($today_stats,$stats->fetch_assoc());

$query_params=$_GET;
unset($query_params['page'],$query_params['export']);
$base_query=http_build_query($query_params);
$refresh_target='workspace_tracker.php'.($base_query?'?'.$base_query:'');

if(($_GET['export']??'')==='csv'){
    $export=$conn->query($select_sql.$from_sql.$where_sql.' ORDER BY sl.id DESC');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="workspace_activity_'.date('Y-m-d_H-i').'.csv"');
    echo chr(239).chr(187).chr(191);
    $out=fopen('php://output','w');
    fputcsv($out,['Log ID','Operator','Activity Type','Details','Target','Timestamp']);
    while($export && $row=$export->fetch_assoc()) fputcsv($out,[$row['log_id'],$row['operator_name'],$row['action_type']?:'Unclassified',$row['action_details'],$row['product_target'],$row['logged_at']]);
    fclose($out); exit;
}

function trackerTone($type){
    $type=strtolower((string)$type);
    if(strpos($type,'financial')!==false) return 'financial';
    if(strpos($type,'inventory')!==false||strpos($type,'product')!==false) return 'inventory';
    if(strpos($type,'login')!==false||strpos($type,'security')!==false) return 'security';
    if(strpos($type,'staff')!==false||strpos($type,'permission')!==false) return 'staff';
    return 'system';
}
?>
<style>
.tracker{font-family:Inter,Segoe UI,Arial,sans-serif;color:#172033}.tracker-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:26px;border:1px solid #dbe5ef;border-radius:16px;background:linear-gradient(135deg,#fff,#f4f8ff)}.tracker-hero h2{margin:0 0 6px;font-size:25px}.tracker-hero p{margin:0;color:#64748b}.tracker-tools{display:flex;gap:8px}.tracker-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 13px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}.tracker-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.tracker-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin:16px 0}.tracker-stat{position:relative;overflow:hidden;padding:17px 18px;border:1px solid #e2e8f0;border-radius:13px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.04);transition:transform .2s ease,box-shadow .2s ease}.tracker-stat:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(15,23,42,.08)}.tracker-stat:nth-child(1){background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;border-left:4px solid #3b82f6}.tracker-stat:nth-child(2){background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-color:#ddd6fe;border-left:4px solid #8b5cf6}.tracker-stat:nth-child(3){background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-color:#a7f3d0;border-left:4px solid #10b981}.tracker-stat:nth-child(4){background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#fde68a;border-left:4px solid #f59e0b}.tracker-stat span{display:block;color:#64748b;font-size:11px;font-weight:800;text-transform:uppercase}.tracker-stat strong{display:block;margin-top:7px;font-size:22px}.tracker-filters{display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:9px;padding:16px;border:1px solid #e2e8f0;border-radius:13px;background:#fff;margin-bottom:16px}.tracker-input{width:100%;min-width:0;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.tracker-panel{overflow:hidden;border:1px solid #dbe5ef;border-radius:15px;background:#fff}.tracker-table-wrap{overflow:auto}.tracker-table{width:100%;min-width:900px;border-collapse:collapse}.tracker-table th{padding:12px 14px;background:#f8fafc;color:#64748b;font-size:11px;text-align:left;text-transform:uppercase}.tracker-table td{padding:14px;border-top:1px solid #edf1f5;font-size:13px;vertical-align:top}.tracker-badge{display:inline-block;padding:4px 8px;border-radius:99px;font-size:10px;font-weight:900;text-transform:uppercase}.tracker-badge.financial{background:#dcfce7;color:#15803d}.tracker-badge.inventory{background:#ffedd5;color:#c2410c}.tracker-badge.security{background:#dbeafe;color:#1d4ed8}.tracker-badge.staff{background:#f3e8ff;color:#7e22ce}.tracker-badge.system{background:#e2e8f0;color:#475569}.tracker-details summary{cursor:pointer;color:#334155;max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tracker-details[open] summary{white-space:normal}.tracker-details p{margin:8px 0 0;color:#64748b;line-height:1.5}.tracker-time strong{display:block}.tracker-time small{color:#94a3b8}.tracker-pagination{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-top:1px solid #edf1f5}.tracker-pages{display:flex;gap:6px}.tracker-page{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;color:#475569;text-decoration:none;font-size:12px}.tracker-page.active{background:#2563eb;color:#fff;border-color:#2563eb}.tracker-empty{text-align:center!important;padding:38px!important;color:#94a3b8}.tracker-denied{padding:20px;color:#dc2626}
@media(max-width:1000px){.tracker-stats{grid-template-columns:repeat(2,1fr)}.tracker-filters{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.tracker-hero{align-items:flex-start;flex-direction:column}.tracker-tools{width:100%}.tracker-tools .tracker-btn{flex:1}.tracker-stats{grid-template-columns:1fr 1fr}.tracker-filters{grid-template-columns:1fr}.tracker-table{min-width:0}.tracker-table thead{display:none}.tracker-table,.tracker-table tbody,.tracker-table tr,.tracker-table td{display:block;width:100%;box-sizing:border-box}.tracker-table tr{padding:13px;border-top:1px solid #edf1f5}.tracker-table td{padding:5px 0;border:0}.tracker-table td:before{content:attr(data-label);display:block;margin-bottom:3px;color:#94a3b8;font-size:10px;font-weight:800;text-transform:uppercase}.tracker-pagination{align-items:flex-start;gap:10px;flex-direction:column}}
</style>
<section class="tracker">
<div class="tracker-hero"><div><h2>Workspace Activity Tracker</h2><p>Review staff actions, security events, financial changes and operational updates.</p></div><div class="tracker-tools"><a class="tracker-btn ajax-link" data-target="<?=htmlspecialchars($refresh_target)?>" href="<?=htmlspecialchars($refresh_target)?>">&#8635; Refresh</a><a class="tracker-btn" href="workspace_tracker.php?<?=htmlspecialchars(http_build_query(array_merge($query_params,['export'=>'csv'])))?>">&#8595; Export CSV</a></div></div>
<div class="tracker-stats"><article class="tracker-stat"><span>Activities today</span><strong><?=(int)$today_stats['activities']?></strong></article><article class="tracker-stat"><span>Active operators</span><strong><?=(int)$today_stats['operators']?></strong></article><article class="tracker-stat"><span>Financial events</span><strong><?=(int)$today_stats['financial']?></strong></article><article class="tracker-stat"><span>Security events</span><strong><?=(int)$today_stats['security']?></strong></article></div>
<form class="tracker-filters tracker-filter-form" method="GET" action="workspace_tracker.php"><input type="hidden" name="view" value="workspace_tracker"><input class="tracker-input" name="search_log" value="<?=htmlspecialchars($search)?>" placeholder="Search activity..."><select class="tracker-input" name="operator"><option value="0">All operators</option><?php while($operators&&$op=$operators->fetch_assoc()):?><option value="<?=(int)$op['user_id']?>" <?=$operator===(int)$op['user_id']?'selected':''?>><?=htmlspecialchars($op['operator_name'])?></option><?php endwhile;?></select><select class="tracker-input" name="category"><option value="">All categories</option><?php while($categories&&$cat=$categories->fetch_assoc()):?><option value="<?=htmlspecialchars($cat['category_name'])?>" <?=$category===$cat['category_name']?'selected':''?>><?=htmlspecialchars($cat['category_name'])?></option><?php endwhile;?></select><input class="tracker-input" type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>" title="From date"><input class="tracker-input" type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>" title="To date"><button class="tracker-btn primary" type="submit">Apply filters</button></form>
<div class="tracker-panel"><div class="tracker-table-wrap"><table class="tracker-table"><thead><tr><th>Operator</th><th>Activity</th><th>Details</th><th>Timestamp</th></tr></thead><tbody>
<?php if(!$result||!$result->num_rows):?><tr><td colspan="4" class="tracker-empty">No activity records match these filters.</td></tr><?php endif;?>
<?php while($result&&$row=$result->fetch_assoc()):$label=$row['action_type']?:'Unclassified';?><tr><td data-label="Operator"><strong><?=htmlspecialchars($row['operator_name'])?></strong><br><small>#<?=htmlspecialchars($row['log_id'])?></small></td><td data-label="Activity"><span class="tracker-badge <?=trackerTone($label)?>"><?=htmlspecialchars($label)?></span></td><td data-label="Details"><details class="tracker-details"><summary><?=htmlspecialchars($row['action_details'])?></summary><p><?=nl2br(htmlspecialchars($row['action_details']))?><?php if($row['product_target']):?><br><strong>Target:</strong> <?=htmlspecialchars($row['product_target'])?><?php endif;?></p></details></td><td class="tracker-time" data-label="Timestamp"><strong><?=date('d M Y',strtotime($row['logged_at']))?></strong><small><?=date('h:i A',strtotime($row['logged_at']))?></small></td></tr><?php endwhile;?>
</tbody></table></div>
<div class="tracker-pagination"><span>Showing <?=($total_rows?($offset+1):0)?>–<?=min($offset+$per_page,$total_rows)?> of <?=$total_rows?></span><div class="tracker-pages"><?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++):$page_query=http_build_query(array_merge($query_params,['page'=>$i]));?><a class="tracker-page ajax-link <?=$i===$page?'active':''?>" data-target="workspace_tracker.php?<?=htmlspecialchars($page_query)?>" href="workspace_tracker.php?<?=htmlspecialchars($page_query)?>"><?=$i?></a><?php endfor;?></div></div></div>
</section>