<?php
require_once __DIR__.'/../session_auth.php';
require_once __DIR__.'/../db.php';
if(session_status()===PHP_SESSION_NONE)session_start();
if(!verifyWorkspaceClearance('low_stock_dispatcher.php')){
    if(!headers_sent())header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}
if(empty($_SESSION['reorder_csrf']))$_SESSION['reorder_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['reorder_csrf'];$flash='';$flash_type='success';$critical_limit=5;

function reorderAudit($conn,$details){
    $uid=(int)($_SESSION['user_id']??0);$name=$_SESSION['fullname']??$_SESSION['staff_name']??'Administrator';
    $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES (?,?,'Inventory Update',?)");
    if(!$log)throw new RuntimeException('Could not prepare the inventory audit record.');
    $log->bind_param('iss',$uid,$name,$details);
    if(!$log->execute()){ $log->close(); throw new RuntimeException('Could not save the inventory audit record.'); }
    $log->close();
}
function sendReorderEmail($recipient,$supplier_name,$product,$qty,$stock,$unit_cost,$lead_days){
    require_once __DIR__.'/../phpmailer/Exception.php';require_once __DIR__.'/../phpmailer/PHPMailer.php';require_once __DIR__.'/../phpmailer/SMTP.php';
    $cfg=require __DIR__.'/../mail_config.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->Host=$cfg['host'];$mail->SMTPAuth=true;$mail->Username=$cfg['username'];$mail->Password=$cfg['password'];$mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->Port=$cfg['port'];
    $mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
    $mail->setFrom($cfg['from_email'],$cfg['from_name']);$mail->addAddress($recipient,$supplier_name?:'Supplier');$mail->isHTML(true);
    $mail->Subject='Stock Replenishment Request: '.$product['product_name'].' ('.$product['sku'].')';
    $cost=$unit_cost>0?'KES '.number_format($unit_cost*$qty,2):'To be confirmed by supplier';
    $mail->Body='<h2 style="color:#172033">ADONAK Electronics Reorder Request</h2><p>Dear '.htmlspecialchars($supplier_name?:'Supply Fulfillment Team').',</p><p>Please confirm availability and delivery for the following replenishment request.</p><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe5ef"><tr><th align="left">Product</th><td>'.htmlspecialchars($product['product_name']).'</td></tr><tr><th align="left">SKU</th><td>'.htmlspecialchars($product['sku']).'</td></tr><tr><th align="left">Current stock</th><td>'.$stock.' units</td></tr><tr><th align="left">Requested quantity</th><td>'.$qty.' units</td></tr><tr><th align="left">Estimated total</th><td>'.$cost.'</td></tr><tr><th align="left">Requested lead time</th><td>'.$lead_days.' days</td></tr></table><p>Please reply with pricing, availability, expected dispatch date and tracking details.</p><p>Regards,<br><strong>ADONAK Procurement Team</strong></p>';
    $mail->AltBody="ADONAK reorder request\nProduct: {$product['product_name']}\nSKU: {$product['sku']}\nCurrent stock: {$stock}\nRequested quantity: {$qty}\nRequested lead time: {$lead_days} days\nPlease reply with pricing and dispatch details.";
    $mail->send();
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['reorder_action'])){
    if(!hash_equals($csrf,(string)($_POST['csrf_token']??''))){$flash='Security token expired. Refresh and try again.';$flash_type='error';}
    else{
        $action=(string)$_POST['reorder_action'];$reorder_id=filter_var($_POST['reorder_id']??null,FILTER_VALIDATE_INT);
        if(in_array($action,['confirmed','received','cancelled'],true)&&$reorder_id){
            $conn->begin_transaction();
            try{
                $locked=$conn->prepare("SELECT product_id,quantity_requested,status FROM purchase_reorders WHERE id=? FOR UPDATE");
                if(!$locked)throw new RuntimeException('Could not prepare the reorder lookup.');
                $locked->bind_param('i',$reorder_id);if(!$locked->execute())throw new RuntimeException('Could not load the reorder.');
                $record=$locked->get_result()->fetch_assoc();$locked->close();
                if(!$record||!in_array($record['status'],['sent','confirmed'],true))throw new DomainException('Reorder is no longer active.');
                if($action==='confirmed'&&$record['status']!=='sent')throw new DomainException('Only a sent reorder can be confirmed.');
                $update=$conn->prepare("UPDATE purchase_reorders SET status=? WHERE id=?");
                if(!$update)throw new RuntimeException('Could not prepare the status update.');
                $update->bind_param('si',$action,$reorder_id);if(!$update->execute()||$update->affected_rows!==1)throw new RuntimeException('Could not update the reorder.');$update->close();
                if($action==='received'){
                    $stock_update=$conn->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?");
                    if(!$stock_update)throw new RuntimeException('Could not prepare the stock update.');
                    $stock_update->bind_param('ii',$record['quantity_requested'],$record['product_id']);
                    if(!$stock_update->execute()||$stock_update->affected_rows!==1)throw new RuntimeException('Could not update product stock.');$stock_update->close();
                    reorderAudit($conn,"Reorder #{$reorder_id} received; {$record['quantity_requested']} units added to product #{$record['product_id']}.");
                    $flash='Reorder received and '.$record['quantity_requested'].' units added to inventory.';
                }else{
                    reorderAudit($conn,"Reorder #{$reorder_id} marked {$action}.");
                    $flash='Reorder marked '.ucfirst($action).'.';
                }
                $conn->commit();$_SESSION['reorder_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['reorder_csrf'];
            }catch(DomainException $e){$conn->rollback();$flash=$e->getMessage();$flash_type='error';}
            catch(Throwable $e){$conn->rollback();error_log('Reorder status update failed: '.$e->getMessage());$flash='The reorder could not be updated. Please try again.';$flash_type='error';}
        }elseif($action==='send'){
            $product_id=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT);$qty=filter_var($_POST['quantity_requested']??null,FILTER_VALIDATE_INT);$lead=filter_var($_POST['lead_days']??null,FILTER_VALIDATE_INT);
            $supplier_name=trim((string)($_POST['supplier_name']??''));$supplier_phone=trim((string)($_POST['supplier_phone']??''));
            if(!$product_id||!$qty||$qty<1||$qty>10000||!$lead||$lead<1||$lead>365||strlen($supplier_name)>150||strlen($supplier_phone)>50){$flash='Check the supplier details, requested quantity and lead time.';$flash_type='error';}
            else{
                $conn->begin_transaction();
                try{
                    $stmt=$conn->prepare("SELECT id,product_name,sku,stock_quantity,supplier_email,price FROM products WHERE id=? FOR UPDATE");if(!$stmt)throw new RuntimeException('Could not prepare the product lookup.');
                    $stmt->bind_param('i',$product_id);if(!$stmt->execute())throw new RuntimeException('Could not load the product.');$product=$stmt->get_result()->fetch_assoc();$stmt->close();
                    if(!$product||!filter_var($product['supplier_email'],FILTER_VALIDATE_EMAIL))throw new DomainException('Check the product supplier email before sending.');
                    if((int)$product['stock_quantity']>=$critical_limit)throw new DomainException('This product is no longer below the reorder threshold.');
                    $dup=$conn->prepare("SELECT id FROM purchase_reorders WHERE product_id=? AND status IN ('sent','confirmed') LIMIT 1 FOR UPDATE");if(!$dup)throw new RuntimeException('Could not check active reorders.');
                    $dup->bind_param('i',$product_id);if(!$dup->execute())throw new RuntimeException('Could not check active reorders.');$active=$dup->get_result()->fetch_assoc();$dup->close();
                    if($active)throw new DomainException('An active reorder already exists for this product (request #'.$active['id'].').');
                    $unit_cost=max(0,(float)$product['price']);$stock=(int)$product['stock_quantity'];
                    sendReorderEmail($product['supplier_email'],$supplier_name,$product,$qty,$stock,$unit_cost,$lead);
                    $stmt=$conn->prepare("INSERT INTO purchase_reorders(product_id,supplier_email,supplier_name,supplier_phone,quantity_requested,current_stock,estimated_unit_cost,lead_days,status,created_by,sent_at) VALUES (?,?,?,?,?,?,?,?,'sent',?,NOW())");if(!$stmt)throw new RuntimeException('Could not prepare the reorder record.');
                    $uid=(int)($_SESSION['user_id']??0);$stmt->bind_param('isssiidii',$product_id,$product['supplier_email'],$supplier_name,$supplier_phone,$qty,$stock,$unit_cost,$lead,$uid);
                    if(!$stmt->execute())throw new RuntimeException('Could not save the reorder record.');$rid=$stmt->insert_id;$stmt->close();
                    reorderAudit($conn,"Supplier reorder email sent for {$product['product_name']} ({$qty} units), request #{$rid}.");
                    $conn->commit();$flash='Email sent successfully and reorder request #'.$rid.' recorded.';$_SESSION['reorder_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['reorder_csrf'];
                }catch(DomainException $e){$conn->rollback();$flash=$e->getMessage();$flash_type='error';}
                catch(Throwable $e){$conn->rollback();error_log('Reorder send failed: '.$e->getMessage());$flash='The reorder could not be sent or recorded. Check the supplier details and email service, then try again.';$flash_type='error';}
            }
        }else{$flash='The requested reorder action is invalid.';$flash_type='error';}
    }
}
$sql="SELECT p.id,p.product_name,p.sku,p.stock_quantity,p.supplier_email,p.price,pr.id reorder_id,pr.status reorder_status,pr.quantity_requested,pr.sent_at,pr.supplier_name,pr.supplier_phone,pr.estimated_unit_cost,pr.lead_days FROM products p LEFT JOIN purchase_reorders pr ON pr.id=(SELECT r.id FROM purchase_reorders r WHERE r.product_id=p.id AND r.status IN ('sent','confirmed') ORDER BY r.id DESC LIMIT 1) WHERE p.stock_quantity<? ORDER BY p.stock_quantity ASC";
$stmt=$conn->prepare($sql);$stmt->bind_param('i',$critical_limit);$stmt->execute();$items=$stmt->get_result();
?>
<style>
.reorder-console{font-family:Inter,Segoe UI,Arial,sans-serif;color:#172033}.reorder-hero{padding:25px;border:1px solid #fed7aa;border-left:5px solid #ea580c;border-radius:15px;background:linear-gradient(135deg,#fff7ed,#fff)}.reorder-hero h2{margin:0 0 7px;font-size:25px}.reorder-hero p{margin:0;color:#64748b}.reorder-flash{margin:14px 0;padding:12px;border-radius:9px;background:#dcfce7;color:#166534;font-weight:800}.reorder-flash.error{background:#fee2e2;color:#991b1b}.reorder-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;margin-top:16px}.reorder-card{overflow:hidden;border:1px solid #dbe5ef;border-radius:14px;background:#fff;box-shadow:0 8px 22px #0f172a0d}.reorder-head{display:flex;justify-content:space-between;gap:12px;padding:16px;background:#f8fafc;border-bottom:1px solid #e2e8f0}.reorder-head h3{margin:0 0 4px}.critical{color:#dc2626;font-size:11px;font-weight:900}.reorder-status{padding:5px 8px;border-radius:99px;background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:900;text-transform:uppercase}.reorder-body{padding:16px}.reorder-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:14px}.reorder-stat{padding:11px;border-radius:9px;font-size:11px}.reorder-stat strong{display:block;margin-top:5px;font-size:16px}.reorder-stat.stock{background:#fff1f2;color:#b91c1c}.reorder-stat.qty{background:#ecfdf5;color:#047857}.reorder-stat.after{background:#eff6ff;color:#1d4ed8}.reorder-form{display:grid;grid-template-columns:1fr 1fr;gap:9px}.reorder-field{display:flex;flex-direction:column;gap:4px}.reorder-field label{font-size:10px;font-weight:900;text-transform:uppercase;color:#64748b}.reorder-input{width:100%;box-sizing:border-box;padding:9px;border:1px solid #cbd5e1;border-radius:8px}.reorder-actions{display:flex;gap:8px;grid-column:1/-1;margin-top:5px}.reorder-btn{padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-size:11px;font-weight:900;cursor:pointer}.reorder-btn.send{background:#2563eb;border-color:#2563eb;color:#fff}.reorder-btn.receive{background:#10b981;border-color:#10b981;color:#fff}.reorder-empty{margin-top:16px;padding:30px;text-align:center;border:1px dashed #86efac;border-radius:13px;background:#f0fdf4;color:#166534;font-weight:800}@media(max-width:900px){.reorder-grid{grid-template-columns:1fr}}@media(max-width:600px){.reorder-form,.reorder-stats{grid-template-columns:1fr}.reorder-actions{grid-column:auto;flex-wrap:wrap}}
</style>
<section class="reorder-console"><div class="reorder-hero"><h2>Low-Stock Reorder Dispatch</h2><p>Prepare, email, and track supplier replenishment requests for products below five units.</p></div><?php if($flash):?><div class="reorder-flash <?=$flash_type==='error'?'error':''?>"><?=htmlspecialchars($flash)?></div><?php endif;?>
<div class="reorder-grid"><?php if(!$items->num_rows):?><div class="reorder-empty">All products are above the critical stock threshold.</div><?php endif;?><?php while($row=$items->fetch_assoc()):$stock=(int)$row['stock_quantity'];$suggested=max(1,25-$stock);$active=!empty($row['reorder_id']);?>
<article class="reorder-card"><header class="reorder-head"><div><h3><?=htmlspecialchars($row['product_name'])?></h3><small><?=htmlspecialchars($row['sku'])?> · <?=htmlspecialchars($row['supplier_email'])?></small></div><div><?php if($active):?><span class="reorder-status"><?=htmlspecialchars($row['reorder_status'])?></span><?php else:?><span class="critical">CRITICAL LOW</span><?php endif;?></div></header><div class="reorder-body">
<div class="reorder-stats"><div class="reorder-stat stock"><span>Current stock</span><strong><?=$stock?> units</strong></div><div class="reorder-stat qty"><span>Suggested reorder</span><strong><?=$suggested?> units</strong></div><div class="reorder-stat after"><span>Expected stock</span><strong><?=$stock+$suggested?> units</strong></div></div>
<?php if(!$active):?><form class="reorder-form" method="POST" action="low_stock_dispatcher.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="reorder_action" value="send"><input type="hidden" name="product_id" value="<?=(int)$row['id']?>"><div class="reorder-field"><label>Supplier name</label><input class="reorder-input" name="supplier_name" placeholder="Supply contact or company"></div><div class="reorder-field"><label>Supplier phone</label><input class="reorder-input" name="supplier_phone" placeholder="+254..."></div><div class="reorder-field"><label>Requested quantity</label><input class="reorder-input reorder-qty" type="number" min="1" max="10000" name="quantity_requested" value="<?=$suggested?>" data-stock="<?=$stock?>" required></div><div class="reorder-field"><label>Database unit price (KES)</label><input class="reorder-input reorder-cost" type="number" step="0.01" value="<?=number_format((float)$row['price'],2,'.','')?>" readonly></div><div class="reorder-field"><label>Requested lead time (days)</label><input class="reorder-input" type="number" min="1" max="365" name="lead_days" value="7"></div><div class="reorder-field"><label>Estimated total</label><input class="reorder-input reorder-total" value="KES <?=number_format((float)$row['price']*$suggested,2)?>" readonly></div><div class="reorder-actions"><button class="reorder-btn" type="button" data-copy-reorder="<?=htmlspecialchars($row['product_name'].' | SKU '.$row['sku'].' | '.$suggested.' units requested')?>">Copy request</button><button class="reorder-btn send" type="submit">Send Email &amp; Record</button></div></form>
<?php else:?><p><strong>Request #<?=(int)$row['reorder_id']?></strong> · <?=htmlspecialchars($row['quantity_requested'])?> units · Sent <?=date('d M Y, h:i A',strtotime($row['sent_at']))?></p><div class="reorder-actions"><?php if($row['reorder_status']==='sent'):?><form method="POST" action="low_stock_dispatcher.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="reorder_action" value="confirmed"><input type="hidden" name="reorder_id" value="<?=(int)$row['reorder_id']?>"><button class="reorder-btn" type="submit">Mark Confirmed</button></form><?php endif;?><form method="POST" action="low_stock_dispatcher.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="reorder_action" value="received"><input type="hidden" name="reorder_id" value="<?=(int)$row['reorder_id']?>"><button class="reorder-btn receive" type="submit">Mark Received</button></form><form method="POST" action="low_stock_dispatcher.php" onsubmit="return confirm('Cancel this active reorder?')"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="reorder_action" value="cancelled"><input type="hidden" name="reorder_id" value="<?=(int)$row['reorder_id']?>"><button class="reorder-btn" type="submit">Cancel</button></form></div><?php endif;?></div></article><?php endwhile;?></div></section>
<script>(function(){document.querySelectorAll('.reorder-form').forEach(function(form){var qty=form.querySelector('.reorder-qty'),cost=form.querySelector('.reorder-cost'),total=form.querySelector('.reorder-total');function update(){var value=(parseFloat(qty.value)||0)*(parseFloat(cost.value)||0);total.value=value>0?'KES '+value.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}):'To be confirmed';}qty.addEventListener('input',update);cost.addEventListener('input',update);});document.querySelectorAll('[data-copy-reorder]').forEach(function(button){button.addEventListener('click',function(){navigator.clipboard.writeText(this.dataset.copyReorder).then(()=>{var old=this.textContent;this.textContent='Copied';setTimeout(()=>this.textContent=old,1200);});});});})();</script>