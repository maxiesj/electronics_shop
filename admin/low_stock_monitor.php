<?php
require_once __DIR__.'/../session_auth.php';
require_once __DIR__.'/../db.php';
if(!verifyWorkspaceClearance('low_stock_monitor.php')){header('Location: ../login.php?msg=err_unauthorized_access');exit;}
const LOW_STOCK_LIMIT=5;
if(empty($_SESSION['stock_monitor_csrf']))$_SESSION['stock_monitor_csrf']=bin2hex(random_bytes(32));
$message='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['replenish_stock_action'])){
    if(!hash_equals($_SESSION['stock_monitor_csrf'],(string)($_POST['csrf_token']??''))){
        $error='This page expired. Refresh it and try again.';
    }else{
        $productId=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT);
        $amount=filter_var($_POST['restock_amount']??null,FILTER_VALIDATE_INT);
        if(!$productId||$productId<1||!$amount||$amount<1||$amount>100000){
            $error='Enter a restock quantity between 1 and 100,000 units.';
        }else{
            $conn->begin_transaction();
            try{
                $find=$conn->prepare('SELECT product_name,stock_quantity FROM products WHERE id=? FOR UPDATE');
                if(!$find)throw new RuntimeException('Could not prepare the product lookup.');
                $find->bind_param('i',$productId);
                if(!$find->execute()){$find->close();throw new RuntimeException('Could not load the product.');}
                $product=$find->get_result()->fetch_assoc();$find->close();
                if(!$product)throw new RuntimeException('Product not found.');
                $before=(int)$product['stock_quantity'];$after=$before+$amount;
                if($after>2147483647)throw new DomainException('The resulting stock quantity exceeds the database limit.');
                $update=$conn->prepare('UPDATE products SET stock_quantity=? WHERE id=?');
                if(!$update)throw new RuntimeException('Could not prepare the stock update.');
                $update->bind_param('ii',$after,$productId);
                if(!$update->execute()||$update->affected_rows!==1)throw new RuntimeException('Stock update failed.');
                $update->close();
                $userId=(int)($_SESSION['user_id']??0);$staffName=(string)($_SESSION['fullname']??$_SESSION['staff_name']??'System operator');
                $details="Restocked {$product['product_name']} (#{$productId}) by {$amount} units; stock {$before} to {$after}.";
                $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Inventory Restock',?)");
                if(!$log)throw new RuntimeException('Could not prepare the inventory audit record.');
                $log->bind_param('iss',$userId,$staffName,$details);
                if(!$log->execute()){ $log->close(); throw new RuntimeException('Could not save the inventory audit record.'); }
                $log->close();
                $conn->commit();$message="{$product['product_name']} now has {$after} units in stock.";
                $_SESSION['stock_monitor_csrf']=bin2hex(random_bytes(32));
            }catch(Throwable $e){$conn->rollback();error_log('Restock failed: '.$e->getMessage());$error='The stock update could not be completed. Please try again.';}
        }
    }
}

$sql="SELECT p.id,p.product_name,p.sku,p.stock_quantity,p.price,p.image,COALESCE(b.brand_name,'Unbranded') brand_name,COALESCE(c.category_name,'Uncategorised') category_name FROM products p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.stock_quantity ASC,p.product_name";
$result=$conn->query($sql);$rows=[];$out=0;$low=0;$healthy=0;$units=0;
if($result)while($row=$result->fetch_assoc()){$rows[]=$row;$qty=(int)$row['stock_quantity'];$units+=max(0,$qty);if($qty<=0)$out++;elseif($qty<LOW_STOCK_LIMIT)$low++;else$healthy++;}
?>
<style>
.stock-monitor{color:#172033}.stock-monitor *{box-sizing:border-box}.stock-hero{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:24px 26px;border:1px solid #dbe5ef;border-left:5px solid #ea6a1b;border-radius:16px;background:linear-gradient(135deg,#fff,#fff7ed)}.stock-hero h2{margin:0 0 6px;font-size:25px}.stock-hero p{margin:0;color:#687a95}.stock-dispatch{padding:11px 15px;border-radius:9px;background:#ea6a1b;color:#fff;text-decoration:none;font-weight:800;white-space:nowrap}.stock-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.stock-stat{padding:17px 18px;border:1px solid;border-left-width:5px;border-radius:13px}.stock-stat span{display:block;font-size:11px;font-weight:800;text-transform:uppercase}.stock-stat strong{display:block;margin-top:7px;font-size:21px}.stock-stat.out{background:#fff1f0;border-color:#ef4444;color:#b42318}.stock-stat.low{background:#fff7ed;border-color:#ea6a1b;color:#b94708}.stock-stat.healthy{background:#ecfdf5;border-color:#10b981;color:#087a53}.stock-stat.units{background:#eff6ff;border-color:#3b82f6;color:#1d4ed8}.stock-panel{border:1px solid #dbe5ef;border-radius:15px;background:#fff;overflow:hidden}.stock-tools{display:grid;grid-template-columns:minmax(240px,1fr) 220px auto;gap:12px;padding:16px;border-bottom:1px solid #e7edf4}.stock-tools input,.stock-tools select{padding:11px 12px;border:1px solid #cbd7e6;border-radius:9px;background:#fff;font:inherit;outline:none}.stock-tools input:focus,.stock-tools select:focus{border-color:#2f6fed;box-shadow:0 0 0 3px #2f6fed1f}.stock-count{align-self:center;color:#687a95;font-size:13px;font-weight:750}.stock-table-wrap{overflow-x:auto}.stock-table{width:100%;border-collapse:collapse}.stock-table th{padding:13px 14px;background:#f7f9fc;color:#5d6c82;font-size:11px;text-align:left;text-transform:uppercase}.stock-table td{padding:13px 14px;border-top:1px solid #edf1f5;vertical-align:middle}.stock-row[data-status=out]{background:#fffafa}.stock-row[data-status=low]{background:#fffdf8}.stock-product{display:flex;align-items:center;gap:12px;min-width:220px}.stock-image{width:48px;height:48px;display:grid;place-items:center;overflow:hidden;border:1px solid #e1e8f0;border-radius:9px;background:#eef3f8;color:#718096;font-size:10px}.stock-image img{width:100%;height:100%;object-fit:cover}.stock-product strong,.stock-product small{display:block}.stock-product small{margin-top:3px;color:#7a899f}.stock-sku{font-weight:800;color:#52627a}.stock-qty{font-weight:850}.status-pill{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:850}.status-pill.out{background:#fee2e2;color:#c62828}.status-pill.low{background:#ffedd5;color:#c2410c}.status-pill.healthy{background:#dff8ec;color:#087a53}.restock-form{display:flex;gap:7px;align-items:center}.restock-input{width:85px;padding:9px;border:1px solid #cbd7e6;border-radius:8px;font:inherit}.restock-button{padding:9px 12px;border:0;border-radius:8px;background:#2f6fed;color:#fff;font-weight:800;cursor:pointer}.restock-button:disabled{opacity:.65;cursor:wait}.stock-notice{margin-bottom:14px;padding:13px 15px;border-radius:10px;font-weight:700}.stock-notice.error{background:#fff1f0;border:1px solid #fecaca;color:#b42318}.stock-notice.success{background:#ecfdf3;border:1px solid #bbf7d0;color:#087a43}.stock-empty{padding:42px!important;text-align:center;color:#75849a}.stock-row.hidden{display:none}
@media(max-width:900px){.stock-stats{grid-template-columns:repeat(2,1fr)}.stock-tools{grid-template-columns:1fr 1fr}.stock-count{grid-column:1/-1}}@media(max-width:680px){.stock-hero{align-items:flex-start;flex-direction:column}.stock-dispatch{width:100%;text-align:center}.stock-stats,.stock-tools{grid-template-columns:1fr}.stock-count{grid-column:auto}.stock-table thead{display:none}.stock-table,.stock-table tbody,.stock-table tr,.stock-table td{display:block;width:100%}.stock-table tr{padding:14px;border-top:1px solid #e7edf4}.stock-table td{display:flex;justify-content:space-between;gap:14px;padding:7px 4px;border:0}.stock-table td:before{content:attr(data-label);color:#74839a;font-size:11px;font-weight:800;text-transform:uppercase}.stock-table td:first-child{display:block}.stock-table td:first-child:before{display:none}.restock-form{justify-content:flex-end}}
</style>
<section class="stock-monitor">
<?php if($error):?><div class="stock-notice error" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($message):?><div class="stock-notice success" role="status"><?=htmlspecialchars($message)?></div><?php endif;?>
<header class="stock-hero"><div><h2>Low Stock Monitor</h2><p>Items below <?=LOW_STOCK_LIMIT?> units require attention. Restock directly<?php if(empty($stock_monitor_staff_view)):?> or prepare a supplier request<?php endif;?>.</p></div><?php if(empty($stock_monitor_staff_view)):?><a class="stock-dispatch ajax-link" href="low_stock_dispatcher.php" data-target="low_stock_dispatcher.php">Open Stock Dispatcher</a><?php endif;?></header>
<div class="stock-stats"><article class="stock-stat out"><span>Out of stock</span><strong><?=$out?></strong></article><article class="stock-stat low"><span>Low stock</span><strong><?=$low?></strong></article><article class="stock-stat healthy"><span>Healthy products</span><strong><?=$healthy?></strong></article><article class="stock-stat units"><span>Available units</span><strong><?=number_format($units)?></strong></article></div>
<div class="stock-panel"><div class="stock-tools"><input id="stock-search" type="search" placeholder="Search product, SKU, brand or category..." aria-label="Search stock"><select id="stock-filter" aria-label="Filter stock status"><option value="attention">Needs attention</option><option value="all">All products</option><option value="out">Out of stock</option><option value="low">Low stock</option><option value="healthy">Healthy stock</option></select><span class="stock-count" id="stock-count"></span></div>
<div class="stock-table-wrap"><table class="stock-table"><thead><tr><th>Product</th><th>SKU</th><th>Unit price</th><th>Available</th><th>Status</th><th>Quick restock</th></tr></thead><tbody>
<?php if($rows):foreach($rows as $row):$qty=(int)$row['stock_quantity'];$state=$qty<=0?'out':($qty<LOW_STOCK_LIMIT?'low':'healthy');$label=$state==='out'?'Out of stock':($state==='low'?'Low stock':'Healthy');$img=basename((string)$row['image']);$hasImage=$img!==''&&is_file(__DIR__.'/../uploads/'.$img);$search=strtolower($row['product_name'].' '.$row['sku'].' '.$row['brand_name'].' '.$row['category_name']);?>
<tr class="stock-row" data-status="<?=$state?>" data-search="<?=htmlspecialchars($search)?>">
<td data-label="Product"><div class="stock-product"><div class="stock-image"><?php if($hasImage):?><img src="../uploads/<?=rawurlencode($img)?>" alt="<?=htmlspecialchars($row['product_name'])?>"><?php else:?>No image<?php endif;?></div><div><strong><?=htmlspecialchars($row['product_name'])?></strong><small><?=htmlspecialchars($row['brand_name'])?> &middot; <?=htmlspecialchars($row['category_name'])?></small></div></div></td>
<td data-label="SKU"><span class="stock-sku"><?=htmlspecialchars($row['sku'])?></span></td><td data-label="Unit price">KES <?=number_format((float)$row['price'],2)?></td><td data-label="Available"><span class="stock-qty"><?=number_format($qty)?> units</span></td><td data-label="Status"><span class="status-pill <?=$state?>"><?=$label?></span></td>
<td data-label="Quick restock"><form class="restock-form" action="low_stock_monitor.php" method="post"><input type="hidden" name="replenish_stock_action" value="1"><input type="hidden" name="product_id" value="<?=(int)$row['id']?>"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['stock_monitor_csrf'])?>"><input class="restock-input" type="number" name="restock_amount" min="1" max="100000" placeholder="+10" aria-label="Restock quantity for <?=htmlspecialchars($row['product_name'])?>" required><button class="restock-button" type="submit">Restock</button></form></td></tr>
<?php endforeach;else:?><tr><td colspan="6" class="stock-empty">No products are currently registered.</td></tr><?php endif;?>
<tr id="stock-no-results" class="hidden"><td colspan="6" class="stock-empty">No products match the selected view.</td></tr></tbody></table></div></div></section>
<script>
(function(){const search=document.getElementById('stock-search'),filter=document.getElementById('stock-filter'),count=document.getElementById('stock-count'),none=document.getElementById('stock-no-results');function apply(){const q=(search.value||'').trim().toLowerCase(),mode=filter.value;let shown=0;document.querySelectorAll('.stock-row').forEach(row=>{const status=row.dataset.status;const statusOk=mode==='all'||status===mode||(mode==='attention'&&(status==='out'||status==='low'));const visible=statusOk&&(!q||row.dataset.search.includes(q));row.classList.toggle('hidden',!visible);if(visible)shown++;});count.textContent=shown+(shown===1?' product':' products');none.classList.toggle('hidden',shown!==0);}search.addEventListener('input',apply);filter.addEventListener('change',apply);document.querySelectorAll('.restock-form').forEach(form=>form.addEventListener('submit',function(e){const input=form.querySelector('.restock-input'),button=form.querySelector('.restock-button');if(!input.checkValidity()){e.preventDefault();input.focus();return;}button.disabled=true;button.textContent='Updating...';}));apply();})();
</script>
