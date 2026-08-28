<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../trash_manager.php';

if (!verifyWorkspaceClearance('trash.php')) {
    header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}
if (empty($_SESSION['trash_csrf'])) {
    $_SESSION['trash_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$filter = strtolower(trim((string)($_GET['entity'] ?? 'all')));
if (!in_array($filter, ['all', 'product', 'category', 'brand'], true)) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_action'])) {
    $entryId = filter_var($_POST['trash_entry_id'] ?? null, FILTER_VALIDATE_INT);
    $entityId = filter_var($_POST['entity_id'] ?? null, FILTER_VALIDATE_INT);
    $entityType = strtolower(trim((string)($_POST['entity_type'] ?? '')));

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['trash_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This page expired. Refresh it and try again.';
    } elseif (!$entryId || !$entityId || $entityType !== 'product') {
        $error = 'That trash item cannot be restored from this screen yet.';
    } else {
        $conn->begin_transaction();
        try {
            restoreProductFromTrash($conn, (int)$entryId, (int)$entityId);
            $conn->commit();
            $_SESSION['trash_csrf'] = bin2hex(random_bytes(32));
            $message = 'Product restored successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Trash restore failed: ' . $e->getMessage());
            $error = 'The item could not be restored.';
        }
    }
}

$where = "te.status='trashed'";
$params = [];
$types = '';
if ($filter !== 'all') {
    $where .= ' AND te.entity_type=?';
    $params[] = $filter;
    $types .= 's';
}
$sql = "SELECT te.id,te.entity_type,te.entity_id,te.entity_label,te.deleted_by_name,te.deleted_at,te.metadata
        FROM trash_entries te
        WHERE {$where}
        ORDER BY te.deleted_at DESC,te.id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$trashRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<style>
.trash-hub{color:#172033}.trash-hub *{box-sizing:border-box}.trash-hero{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:24px 26px;border:1px solid #dbe5ef;border-left:5px solid #64748b;border-radius:16px;background:linear-gradient(135deg,#fff,#f8fafc)}.trash-hero h2{margin:0 0 6px;font-size:25px}.trash-hero p{margin:0;color:#687a95}.trash-back{padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#334155;font-weight:800;background:#fff}.trash-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.trash-tab{padding:8px 12px;border:1px solid #d6deea;border-radius:999px;text-decoration:none;color:#52627a;font-weight:800;font-size:12px;background:#fff}.trash-tab.active{background:#334155;color:#fff;border-color:#334155}.trash-panel{background:#fff;border:1px solid #dbe5ef;border-radius:14px;overflow:hidden}.trash-table-wrap{overflow-x:auto}.trash-table{width:100%;border-collapse:collapse}.trash-table th{padding:13px 14px;background:#f8fafc;color:#64748b;text-align:left;font-size:11px;text-transform:uppercase}.trash-table td{padding:14px;border-top:1px solid #edf2f7}.trash-label{font-weight:850}.trash-meta{font-size:12px;color:#64748b;margin-top:4px}.restore-btn{padding:8px 12px;border:0;border-radius:8px;background:#16a34a;color:#fff;font-weight:850;cursor:pointer}.restore-btn:disabled{background:#94a3b8;cursor:not-allowed}.trash-empty{padding:42px!important;text-align:center;color:#7c8aa0}.trash-notice{margin-bottom:14px;padding:12px 14px;border-radius:9px;font-weight:750}.trash-notice.success{background:#ecfdf5;color:#087a53;border:1px solid #bbf7d0}.trash-notice.error{background:#fff1f0;color:#b42318;border:1px solid #fecaca}@media(max-width:650px){.trash-hero{align-items:flex-start;flex-direction:column}.trash-back{width:100%;text-align:center}}
</style>
<section class="trash-hub">
<?php if($message):?><div class="trash-notice success" role="status"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="trash-notice error" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<header class="trash-hero"><div><h2>Trash & Recovery</h2><p>Recover deleted operational records. Financial and audit ledgers are never permanently deleted here.</p></div><a class="trash-back ajax-link" data-target="warehouse.php" href="warehouse.php">Back to Warehouse</a></header>
<nav class="trash-tabs" aria-label="Trash filters">
<?php foreach(['all'=>'General Trash','product'=>'Products','category'=>'Categories','brand'=>'Brands'] as $key=>$label):?><a class="trash-tab <?=$filter===$key?'active':''?>" href="trash.php?entity=<?=$key?>" data-target="trash.php?entity=<?=$key?>"><?=$label?></a><?php endforeach;?>
</nav>
<div class="trash-panel"><div class="trash-table-wrap"><table class="trash-table"><thead><tr><th>Type</th><th>Item</th><th>Deleted by</th><th>Deleted at</th><th>Recovery</th></tr></thead><tbody>
<?php if($trashRows):foreach($trashRows as $row):$meta=json_decode((string)($row['metadata']??''),true)?:[];?>
<tr><td><?=htmlspecialchars(ucfirst($row['entity_type']))?></td><td><div class="trash-label"><?=htmlspecialchars($row['entity_label'])?></div><?php if(!empty($meta['sku'])):?><div class="trash-meta">SKU: <?=htmlspecialchars($meta['sku'])?></div><?php endif;?></td><td><?=htmlspecialchars($row['deleted_by_name'] ?: 'Unknown operator')?></td><td><?=htmlspecialchars($row['deleted_at'])?></td><td>
<form method="post" action="trash.php?entity=<?=urlencode($filter)?>" style="margin:0" onsubmit="return confirm('Restore this item?');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['trash_csrf'])?>"><input type="hidden" name="restore_action" value="1"><input type="hidden" name="trash_entry_id" value="<?=(int)$row['id']?>"><input type="hidden" name="entity_id" value="<?=(int)$row['entity_id']?>"><input type="hidden" name="entity_type" value="<?=htmlspecialchars($row['entity_type'])?>"><button class="restore-btn" type="submit" <?=$row['entity_type']!=='product'?'disabled title="Restore support will be enabled when this module is connected"':''?>><?=$row['entity_type']==='product'?'Restore':'Pending integration'?></button></form>
</td></tr>
<?php endforeach;else:?><tr><td colspan="5" class="trash-empty">Trash is empty for this filter.</td></tr><?php endif;?>
</tbody></table></div></div></section>
