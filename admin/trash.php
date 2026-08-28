<?php
require_once __DIR__.'/../session_auth.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../trash_service.php';
if (!verifyExplicitWorkspaceClearance('trash.php')) { http_response_code(403); echo 'AUTH_ERROR'; exit; }
if (empty($_SESSION['trash_csrf'])) $_SESSION['trash_csrf']=bin2hex(random_bytes(32));
$message='';$error='';$types=['product'=>'Products','category'=>'Categories','brand'=>'Brands','customer'=>'Customers','staff'=>'Staff'];
$filter=(string)($_GET['trash_type']??'all');if($filter!=='all'&&!isset($types[$filter]))$filter='all';

if (!trashRegistryAvailable($conn)) {
    $error='Run database_migrations/2026_08_28_general_trash.sql before using Trash & Recovery.';
}

if (!$error && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['trash_action']??'')==='restore') {
    if (!hash_equals($_SESSION['trash_csrf'],(string)($_POST['csrf_token']??''))) $error='This page expired. Refresh it and try again.';
    else {
        $trashId=filter_var($_POST['trash_id']??null,FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
            $stmt=$conn->prepare("SELECT * FROM trash_records WHERE id=? AND status='trashed' FOR UPDATE");$stmt->bind_param('i',$trashId);$stmt->execute();$trash=$stmt->get_result()->fetch_assoc();$stmt->close();
            if(!$trash)throw new DomainException('Trash record not found or already restored.');
            $snapshot=json_decode($trash['record_snapshot'],true);if(!is_array($snapshot))throw new RuntimeException('Recovery snapshot is invalid.');
            $type=$trash['record_type'];$originalId=(int)$trash['original_id'];
            if(in_array($type,['customer','staff'],true)){
                $stmt=$conn->prepare("UPDATE users SET account_status='active' WHERE id=? AND account_status='purged'");$stmt->bind_param('i',$originalId);$stmt->execute();if($stmt->affected_rows!==1)throw new DomainException('The account cannot be restored because it is missing or already active.');$stmt->close();
                if($type==='staff'&&!empty($snapshot['_permissions'])&&is_array($snapshot['_permissions'])){
                    $allowed=[];$permissionResult=$conn->query('SELECT permission_key FROM system_permissions');while($permissionResult&&$permissionRow=$permissionResult->fetch_assoc())$allowed[]=$permissionRow['permission_key'];
                    $restorePermissions=array_values(array_intersect(array_unique(array_map('strval',$snapshot['_permissions'])),$allowed));
                    if($restorePermissions){$stmt=$conn->prepare('INSERT INTO staff_permissions(user_id,target_view) VALUES(?,?)');foreach($restorePermissions as $targetView){$stmt->bind_param('is',$originalId,$targetView);$stmt->execute();}$stmt->close();}
                }
            }else{
                $table=['product'=>'products','category'=>'categories','brand'=>'brands'][$type];
                $columns=[];$values=[];$bindTypes='';$result=$conn->query("SHOW COLUMNS FROM `{$table}`");$valid=[];while($result&&$column=$result->fetch_assoc())$valid[$column['Field']]=true;
                foreach($snapshot as $column=>$value){if(isset($valid[$column])){$columns[]='`'.$column.'`';$values[]=$value;$bindTypes.=is_int($value)?'i':(is_float($value)?'d':'s');}}
                if(!$columns)throw new RuntimeException('No recoverable fields were found.');
                $marks=implode(',',array_fill(0,count($columns),'?'));$sql="INSERT INTO `{$table}` (".implode(',',$columns).") VALUES ({$marks})";$stmt=$conn->prepare($sql);$bindValues=[];foreach($values as $key=>$value)$bindValues[$key]=&$values[$key];$stmt->bind_param($bindTypes,...$bindValues);if(!$stmt->execute())throw new RuntimeException('Record restore failed: '.$stmt->error);$stmt->close();
            }
            $operator=(int)$_SESSION['user_id'];$stmt=$conn->prepare("UPDATE trash_records SET status='restored',restored_by=?,restored_at=NOW() WHERE id=?");$stmt->bind_param('ii',$operator,$trashId);$stmt->execute();$stmt->close();
            trashAudit($conn,ucfirst($type).' #'.$originalId.' restored from Trash & Recovery.');$conn->commit();$message=$trash['display_name'].' restored successfully.';
        }catch(Throwable $e){$conn->rollback();$error=$e instanceof DomainException?$e->getMessage():'The record could not be restored safely.';if(!($e instanceof DomainException))error_log('Trash restore failed: '.$e->getMessage());}
    }
}

$counts=array_fill_keys(array_keys($types),0);$total=0;$deletedToday=0;$restoredToday=0;$oldest=null;$records=[];
if(!$error){
    $result=$conn->query("SELECT record_type,COUNT(*) total FROM trash_records WHERE status='trashed' GROUP BY record_type");while($result&&$row=$result->fetch_assoc()){$counts[$row['record_type']]=(int)$row['total'];$total+=(int)$row['total'];}
    $result=$conn->query("SELECT SUM(status='trashed' AND DATE(deleted_at)=CURRENT_DATE) deleted_today,SUM(status='restored' AND DATE(restored_at)=CURRENT_DATE) restored_today,MIN(CASE WHEN status='trashed' THEN deleted_at END) oldest FROM trash_records");if($result&&$row=$result->fetch_assoc()){$deletedToday=(int)$row['deleted_today'];$restoredToday=(int)$row['restored_today'];$oldest=$row['oldest'];}
    $sql="SELECT id,record_type,original_id,display_name,deleted_by_name,deleted_at FROM trash_records WHERE status='trashed'";if($filter!=='all'){$sql.=' AND record_type=?';$stmt=$conn->prepare($sql.' ORDER BY deleted_at DESC');$stmt->bind_param('s',$filter);$stmt->execute();$result=$stmt->get_result();}else{$result=$conn->query($sql.' ORDER BY deleted_at DESC');}while($result&&$row=$result->fetch_assoc())$records[]=$row;if(isset($stmt))$stmt->close();
}
?>
<style>
.trash{color:#172033}.trash *{box-sizing:border-box}.trash-alert{padding:13px 15px;margin-bottom:14px;border-radius:10px;font-weight:700}.trash-alert.ok{background:#ecfdf3;color:#087a43;border:1px solid #bbf7d0}.trash-alert.err{background:#fff1f0;color:#b42318;border:1px solid #fecaca}.trash-hero{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:24px 26px;border:1px solid #dbe5ef;border-left:5px solid #64748b;border-radius:16px;background:#fff}.trash-hero h2{margin:0 0 6px;font-size:25px}.trash-hero p{margin:0;color:#687a95}.trash-back{padding:11px 14px;border:1px solid #cbd5e1;border-radius:9px;color:#334155;text-decoration:none;font-weight:800}.trash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}.trash-stat{padding:16px;border:1px solid #dbe5ef;border-radius:12px;background:#fff}.trash-stat span,.trash-stat strong{display:block}.trash-stat span{font-size:11px;color:#64748b;text-transform:uppercase;font-weight:800}.trash-stat strong{margin-top:6px;font-size:21px}.trash-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.trash-tab{padding:8px 12px;border:1px solid #d5deea;border-radius:999px;background:#fff;color:#475569;text-decoration:none;font-size:12px;font-weight:800}.trash-tab.active{background:#334155;color:#fff;border-color:#334155}.trash-table{overflow:auto;border:1px solid #dbe5ef;border-radius:14px;background:#fff}.trash-table table{width:100%;border-collapse:collapse;min-width:760px}.trash-table th{padding:13px 14px;background:#f7f9fc;color:#64748b;font-size:11px;text-align:left}.trash-table td{padding:14px;border-top:1px solid #edf1f5}.trash-restore{padding:8px 11px;border:0;border-radius:8px;background:#087a53;color:#fff;font-weight:800;cursor:pointer}.trash-empty{text-align:center;color:#7a899f;padding:42px!important}@media(max-width:800px){.trash-stats{grid-template-columns:repeat(2,1fr)}.trash-hero{align-items:flex-start;flex-direction:column}}@media(max-width:480px){.trash-stats{grid-template-columns:1fr}}
</style>
<section class="trash">
<?php if($message):?><div class="trash-alert ok"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="trash-alert err"><?=htmlspecialchars($error)?></div><?php endif;?>
<header class="trash-hero"><div><h2>Trash & Recovery</h2><p>Recover deleted operational records. Financial and audit ledgers are never permanently deleted here.</p></div><a class="trash-back ajax-link" data-target="warehouse.php" href="warehouse.php">Back to Warehouse</a></header>
<div class="trash-stats"><article class="trash-stat"><span>Currently in trash</span><strong><?=$total?></strong></article><article class="trash-stat"><span>Deleted today</span><strong><?=$deletedToday?></strong></article><article class="trash-stat"><span>Restored today</span><strong><?=$restoredToday?></strong></article><article class="trash-stat"><span>Oldest deletion</span><strong style="font-size:14px"><?=$oldest?htmlspecialchars(date('d M Y',strtotime($oldest))):'None'?></strong></article></div>
<nav class="trash-tabs" aria-label="Trash filters"><a class="trash-tab <?=$filter==='all'?'active':''?> ajax-link" data-target="trash.php" href="trash.php">General Trash (<?=$total?>)</a><?php foreach($types as $key=>$label):?><a class="trash-tab <?=$filter===$key?'active':''?> ajax-link" data-target="trash.php?trash_type=<?=$key?>" href="trash.php?trash_type=<?=$key?>"><?=htmlspecialchars($label)?> (<?=$counts[$key]?>)</a><?php endforeach;?></nav>
<div class="trash-table"><table><thead><tr><th>Type</th><th>Item</th><th>Deleted by</th><th>Deleted at</th><th>Recovery</th></tr></thead><tbody><?php if($records):foreach($records as $row):?><tr><td><?=htmlspecialchars($types[$row['record_type']]??ucfirst($row['record_type']))?></td><td><strong><?=htmlspecialchars($row['display_name'])?></strong><br><small>#<?=(int)$row['original_id']?></small></td><td><?=htmlspecialchars($row['deleted_by_name'])?></td><td><?=htmlspecialchars(date('d M Y, H:i',strtotime($row['deleted_at'])))?></td><td><form method="post" action="trash.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['trash_csrf'])?>"><input type="hidden" name="trash_action" value="restore"><input type="hidden" name="trash_id" value="<?=(int)$row['id']?>"><button class="trash-restore" type="submit">Restore</button></form></td></tr><?php endforeach;else:?><tr><td class="trash-empty" colspan="5">Trash is empty for this filter.</td></tr><?php endif;?></tbody></table></div>
</section>
