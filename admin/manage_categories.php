<?php
require_once __DIR__.'/../session_auth.php';
require_once __DIR__.'/../db.php';
if(!verifyWorkspaceClearance('manage_categories.php')){header('Location: ../login.php?msg=err_unauthorized_access');exit;}
if(empty($_SESSION['taxonomy_csrf']))$_SESSION['taxonomy_csrf']=bin2hex(random_bytes(32));
$message='';$error='';

function taxonomyConfig($kind){
    $map=['category'=>['categories','category_name','category_id','Category'],'brand'=>['brands','brand_name','brand_id','Brand']];
    return $map[$kind]??null;
}
function taxonomyName($value){
    $value=preg_replace('/\s+/u',' ',trim((string)$value));
    return mb_strtoupper($value,'UTF-8');
}
function taxonomyAudit($conn,$action,$label,$id,$name){
    $uid=(int)($_SESSION['user_id']??0);$staff=(string)($_SESSION['fullname']??'System operator');
    $details="{$label} #{$id} {$action}: {$name}.";
    $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Catalog Taxonomy',?)");
    if($log){$log->bind_param('iss',$uid,$staff,$details);$log->execute();$log->close();}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['taxonomy_csrf'],(string)($_POST['csrf_token']??''))){
        $error='This page expired. Refresh it and try again.';
    }else{
        $action=(string)($_POST['taxonomy_action']??'');$kind=(string)($_POST['taxonomy_kind']??'');$cfg=taxonomyConfig($kind);
        if(!$cfg||!in_array($action,['add','rename','delete'],true)){
            $error='The requested catalog action is invalid.';
        }else{
            [$table,$column,$productColumn,$label]=$cfg;
            if($action==='add'||$action==='rename'){
                $name=taxonomyName($_POST['taxonomy_name']??'');
                if(mb_strlen($name)<2||mb_strlen($name)>100||!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s&+\/._-]*$/u',$name)){
                    $error="Enter a valid {$label} name using 2–100 letters, numbers or standard separators.";
                }else{
                    $id=filter_var($_POST['taxonomy_id']??null,FILTER_VALIDATE_INT);
                    try{
                        if($action==='add'){
                            $stmt=$conn->prepare("INSERT INTO {$table}({$column}) VALUES(?)");$stmt->bind_param('s',$name);
                            if(!$stmt->execute())throw new mysqli_sql_exception($stmt->error,$stmt->errno);
                            $id=$stmt->insert_id;$stmt->close();taxonomyAudit($conn,'created',$label,$id,$name);$message="{$label} {$name} was added.";
                        }elseif(!$id||$id<1){
                            $error="Select a valid {$label} to rename.";
                        }else{
                            $stmt=$conn->prepare("UPDATE {$table} SET {$column}=? WHERE id=?");$stmt->bind_param('si',$name,$id);
                            if(!$stmt->execute())throw new mysqli_sql_exception($stmt->error,$stmt->errno);
                            if($stmt->affected_rows<1){$check=$conn->prepare("SELECT id FROM {$table} WHERE id=?");$check->bind_param('i',$id);$check->execute();if(!$check->get_result()->fetch_assoc())$error="{$label} not found.";$check->close();}
                            $stmt->close();if(!$error){taxonomyAudit($conn,'renamed',$label,$id,$name);$message="{$label} was renamed to {$name}.";}
                        }
                    }catch(mysqli_sql_exception $e){if((int)$e->getCode()===1062)$error="That {$label} already exists.";else{error_log('Taxonomy save failed: '.$e->getMessage());$error="The {$label} could not be saved.";}}
                }
            }else{
                $id=filter_var($_POST['taxonomy_id']??null,FILTER_VALIDATE_INT);
                if(!$id||$id<1)$error="Select a valid {$label} to remove.";
                else{
                    $conn->begin_transaction();
                    try{
                        $find=$conn->prepare("SELECT {$column} name FROM {$table} WHERE id=? FOR UPDATE");$find->bind_param('i',$id);$find->execute();$record=$find->get_result()->fetch_assoc();$find->close();
                        if(!$record)throw new RuntimeException('NOT_FOUND');
                        $used=$conn->prepare("SELECT COUNT(*) total FROM products WHERE {$productColumn}=?");$used->bind_param('i',$id);$used->execute();$usage=(int)$used->get_result()->fetch_assoc()['total'];$used->close();
                        if($usage>0)throw new RuntimeException('IN_USE:'.$usage);
                        $delete=$conn->prepare("DELETE FROM {$table} WHERE id=?");$delete->bind_param('i',$id);
                        if(!$delete->execute()||$delete->affected_rows!==1)throw new RuntimeException('DELETE_FAILED');$delete->close();
                        taxonomyAudit($conn,'removed',$label,$id,$record['name']);$conn->commit();$message="{$label} {$record['name']} was removed.";
                    }catch(Throwable $e){$conn->rollback();if(strpos($e->getMessage(),'IN_USE:')===0){$usage=(int)substr($e->getMessage(),7);$error="This {$label} is assigned to {$usage} product".($usage===1?'':'s')." and cannot be removed.";}elseif($e->getMessage()==='NOT_FOUND')$error="{$label} not found.";else{error_log('Taxonomy delete failed: '.$e->getMessage());$error="The {$label} could not be removed.";}}
                }
            }
        }
    }
}
$catResult=$conn->query("SELECT c.id,c.category_name name,COUNT(p.id) usage_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id,c.category_name ORDER BY c.category_name");
$brandResult=$conn->query("SELECT b.id,b.brand_name name,COUNT(p.id) usage_count FROM brands b LEFT JOIN products p ON p.brand_id=b.id GROUP BY b.id,b.brand_name ORDER BY b.brand_name");
$categories=[];$brands=[];if($catResult)while($r=$catResult->fetch_assoc())$categories[]=$r;if($brandResult)while($r=$brandResult->fetch_assoc())$brands[]=$r;
?>
<style>
.taxonomy{color:#172033}.taxonomy *{box-sizing:border-box}.taxonomy-hero{padding:24px 26px;border:1px solid #dbe5ef;border-left:5px solid #6d5ce7;border-radius:16px;background:linear-gradient(135deg,#fff,#f5f3ff)}.taxonomy-hero h2{margin:0 0 6px;font-size:25px}.taxonomy-hero p{margin:0;color:#687a95}.taxonomy-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:16px 0}.taxonomy-stat{padding:17px 18px;border:1px solid;border-left-width:5px;border-radius:13px}.taxonomy-stat span{display:block;font-size:11px;font-weight:800;text-transform:uppercase}.taxonomy-stat strong{display:block;margin-top:7px;font-size:21px}.taxonomy-stat.categories{background:#eff6ff;border-color:#3b82f6;color:#1d4ed8}.taxonomy-stat.brands{background:#f3f0ff;border-color:#6d5ce7;color:#4938b8}.taxonomy-stat.products{background:#ecfdf5;border-color:#10b981;color:#087a53}.taxonomy-search{width:100%;margin-bottom:16px;padding:12px 14px;border:1px solid #cbd7e6;border-radius:10px;background:#fff;font:inherit;outline:none}.taxonomy-search:focus{border-color:#2f6fed;box-shadow:0 0 0 3px #2f6fed1f}.taxonomy-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.taxonomy-panel{border:1px solid #dbe5ef;border-radius:15px;background:#fff;overflow:hidden}.taxonomy-panel__head{padding:19px 20px;border-bottom:1px solid #e7edf4}.taxonomy-panel__head h3{margin:0 0 5px}.taxonomy-panel__head p{margin:0;color:#7a899f;font-size:13px}.taxonomy-add{display:flex;gap:9px;padding:15px 20px;border-bottom:1px solid #e7edf4;background:#fafbfd}.taxonomy-add input{min-width:0;flex:1;padding:11px 12px;border:1px solid #cbd7e6;border-radius:8px;font:inherit;text-transform:uppercase}.taxonomy-add button{padding:11px 15px;border:0;border-radius:8px;background:#2f6fed;color:#fff;font-weight:800;cursor:pointer}.taxonomy-list{max-height:530px;overflow:auto}.taxonomy-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:13px 20px;border-top:1px solid #edf1f5}.taxonomy-row:first-child{border-top:0}.taxonomy-name strong{display:block}.taxonomy-name small{display:block;margin-top:4px;color:#7b8aa0}.taxonomy-actions{display:flex;gap:7px}.taxonomy-action{padding:7px 9px;border:1px solid #d2dce9;border-radius:7px;background:#fff;font-size:12px;font-weight:800;cursor:pointer}.taxonomy-action.rename{color:#345ee8}.taxonomy-action.delete{color:#d92d20;border-color:#f3c6c2}.taxonomy-action:disabled{color:#98a2b3;border-color:#e4e7ec;background:#f7f8fa;cursor:not-allowed}.taxonomy-row.hidden{display:none}.taxonomy-empty{padding:30px;text-align:center;color:#7b8aa0}.taxonomy-notice{margin-bottom:14px;padding:13px 15px;border-radius:10px;font-weight:700}.taxonomy-notice.error{background:#fff1f0;border:1px solid #fecaca;color:#b42318}.taxonomy-notice.success{background:#ecfdf3;border:1px solid #bbf7d0;color:#087a43}.taxonomy-dialog{width:min(450px,calc(100% - 30px));border:0;border-radius:14px;padding:0;box-shadow:0 24px 60px #17203340}.taxonomy-dialog::backdrop{background:#17203380}.taxonomy-dialog__body{padding:22px}.taxonomy-dialog h3{margin:0 0 8px}.taxonomy-dialog p{margin:0 0 16px;color:#74839a}.taxonomy-dialog input{width:100%;padding:11px 12px;border:1px solid #cbd7e6;border-radius:8px;font:inherit;text-transform:uppercase}.taxonomy-dialog__actions{display:flex;justify-content:flex-end;gap:9px;margin-top:17px}.taxonomy-dialog button{padding:10px 14px;border:1px solid #cad5e3;border-radius:8px;background:#fff;font-weight:800;cursor:pointer}.taxonomy-dialog button[type=submit]{border-color:#2f6fed;background:#2f6fed;color:#fff}
@media(max-width:820px){.taxonomy-grid{grid-template-columns:1fr}.taxonomy-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.taxonomy-stats{grid-template-columns:1fr}.taxonomy-add{flex-direction:column}.taxonomy-row{grid-template-columns:1fr}.taxonomy-actions{justify-content:flex-end}}
</style>
<section class="taxonomy">
<?php if($error):?><div class="taxonomy-notice error" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($message):?><div class="taxonomy-notice success" role="status"><?=htmlspecialchars($message)?></div><?php endif;?>
<header class="taxonomy-hero"><h2>Categories & Brands</h2><p>Organise the product catalog using reusable categories and manufacturer brands.</p></header>
<div class="taxonomy-stats"><article class="taxonomy-stat categories"><span>Categories</span><strong><?=count($categories)?></strong></article><article class="taxonomy-stat brands"><span>Brands</span><strong><?=count($brands)?></strong></article><article class="taxonomy-stat products"><span>Catalog products</span><strong><?=array_sum(array_column($categories,'usage_count'))?></strong></article></div>
<input class="taxonomy-search" id="taxonomy-search" type="search" placeholder="Search categories and brands..." aria-label="Search categories and brands">
<div class="taxonomy-grid">
<?php foreach([['category','Product categories',$categories],['brand','Manufacturer brands',$brands]] as [$kind,$title,$items]):?>
<section class="taxonomy-panel"><header class="taxonomy-panel__head"><h3><?=$title?></h3><p><?=count($items)?> registered · names must be unique</p></header>
<form class="taxonomy-add" method="post" action="manage_categories.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['taxonomy_csrf'])?>"><input type="hidden" name="taxonomy_action" value="add"><input type="hidden" name="taxonomy_kind" value="<?=$kind?>"><input name="taxonomy_name" maxlength="100" placeholder="New <?=$kind?> name" aria-label="New <?=$kind?> name" required><button type="submit">Add</button></form>
<div class="taxonomy-list"><?php if($items):foreach($items as $item):$usage=(int)$item['usage_count'];?>
<article class="taxonomy-row" data-search="<?=htmlspecialchars(strtolower($item['name']))?>"><div class="taxonomy-name"><strong><?=htmlspecialchars($item['name'])?></strong><small><?=$usage?> assigned product<?=$usage===1?'':'s'?></small></div><div class="taxonomy-actions"><button type="button" class="taxonomy-action rename" data-rename-kind="<?=$kind?>" data-rename-id="<?=(int)$item['id']?>" data-rename-name="<?=htmlspecialchars($item['name'])?>">Rename</button><form method="post" action="manage_categories.php" onsubmit="return confirm('Remove this <?=$kind?>?');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['taxonomy_csrf'])?>"><input type="hidden" name="taxonomy_action" value="delete"><input type="hidden" name="taxonomy_kind" value="<?=$kind?>"><input type="hidden" name="taxonomy_id" value="<?=(int)$item['id']?>"><button class="taxonomy-action delete" type="submit" <?=$usage>0?'disabled title="Assigned products must be moved first"':''?>>Remove</button></form></div></article>
<?php endforeach;else:?><div class="taxonomy-empty">No <?=$kind?> records yet.</div><?php endif;?><div class="taxonomy-empty taxonomy-no-results" hidden>No matching <?=$kind?> records.</div></div></section>
<?php endforeach;?></div>
<dialog class="taxonomy-dialog" id="rename-dialog"><form method="post" action="manage_categories.php"><div class="taxonomy-dialog__body"><h3>Rename catalog property</h3><p>Update the label everywhere it appears in the catalog.</p><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['taxonomy_csrf'])?>"><input type="hidden" name="taxonomy_action" value="rename"><input type="hidden" name="taxonomy_kind" id="rename-kind"><input type="hidden" name="taxonomy_id" id="rename-id"><input name="taxonomy_name" id="rename-name" maxlength="100" required><div class="taxonomy-dialog__actions"><button type="button" id="rename-cancel">Cancel</button><button type="submit">Save name</button></div></div></form></dialog>
</section>
<script>
(function(){const search=document.getElementById('taxonomy-search');search.addEventListener('input',function(){const q=this.value.trim().toLowerCase();document.querySelectorAll('.taxonomy-list').forEach(list=>{let shown=0;list.querySelectorAll('.taxonomy-row').forEach(row=>{const visible=!q||row.dataset.search.includes(q);row.classList.toggle('hidden',!visible);if(visible)shown++;});list.querySelector('.taxonomy-no-results').hidden=shown!==0;});});const dialog=document.getElementById('rename-dialog');document.querySelectorAll('[data-rename-id]').forEach(button=>button.addEventListener('click',function(){document.getElementById('rename-kind').value=this.dataset.renameKind;document.getElementById('rename-id').value=this.dataset.renameId;document.getElementById('rename-name').value=this.dataset.renameName;dialog.showModal();document.getElementById('rename-name').focus();}));document.getElementById('rename-cancel').addEventListener('click',()=>dialog.close());document.querySelectorAll('.taxonomy-add').forEach(form=>form.addEventListener('submit',function(){const button=form.querySelector('button');if(form.checkValidity()){button.disabled=true;button.textContent='Adding...';}}));})();
</script>