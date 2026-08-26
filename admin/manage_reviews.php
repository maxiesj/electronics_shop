<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
if(session_status()===PHP_SESSION_NONE) session_start();

if(!verifyExplicitWorkspaceClearance('manage_reviews.php')){
    if($_SERVER['REQUEST_METHOD']==='POST' || !empty($is_ajax)){
        http_response_code(403);
        echo $_SERVER['REQUEST_METHOD']==='POST' ? 'ERROR|Access denied.' : 'AUTH_ERROR';
    } else {
        header('Location: ../login.php?msg=err_unauthorized_access');
    }
    exit;
}
if(empty($_SESSION['review_csrf_token'])) $_SESSION['review_csrf_token']=bin2hex(random_bytes(32));
$review_csrf=$_SESSION['review_csrf_token'];

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['moderation_action'])){
    header('Content-Type: text/plain; charset=UTF-8');
    if(!hash_equals($review_csrf,(string)($_POST['csrf_token']??''))){http_response_code(403);echo 'ERROR|Security token expired. Refresh and try again.';exit;}

    $review_id=filter_var($_POST['review_id']??null,FILTER_VALIDATE_INT);
    $action=(string)($_POST['moderation_action']??'');
    $allowed=['approve'=>'live','hide'=>'hidden','reject'=>'rejected'];
    if(!$review_id||$review_id<1||!isset($allowed[$action])){http_response_code(422);echo 'ERROR|Invalid moderation request.';exit;}

    $new_status=$allowed[$action];
    $approved=$new_status==='live'?1:0;
    $moderator=(int)$_SESSION['user_id'];
    $staff=$_SESSION['fullname']??$_SESSION['staff_name']??'Moderator';

    try{
        $conn->begin_transaction();
        $review_stmt=$conn->prepare("SELECT pr.user_id,pr.product_id,pr.customer_name,pr.moderation_status,pr.is_approved,COALESCE(p.product_name,'Unavailable product') product_name FROM product_reviews pr LEFT JOIN products p ON p.id=pr.product_id WHERE pr.id=? FOR UPDATE");
        if(!$review_stmt)throw new RuntimeException('Unable to prepare the review lookup.');
        $review_stmt->bind_param('i',$review_id);
        if(!$review_stmt->execute()){$review_stmt->close();throw new RuntimeException('Unable to load the review.');}
        $review=$review_stmt->get_result()->fetch_assoc();$review_stmt->close();
        if(!$review)throw new DomainException('Review not found.');

        $old_status=strtolower(trim((string)$review['moderation_status']));
        if($old_status==='')$old_status=(int)$review['is_approved']===1?'live':'pending';
        if($old_status===$new_status&&(int)$review['is_approved']===$approved)throw new DomainException('Review is already in this state.');

        if($new_status==='live'){
            $eligible=$conn->prepare("SELECT o.id FROM orders o INNER JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=? AND oi.product_id=? AND LOWER(TRIM(o.order_status))='delivered' LIMIT 1 FOR UPDATE");
            if(!$eligible)throw new RuntimeException('Unable to prepare purchase verification.');
            $eligible->bind_param('ii',$review['user_id'],$review['product_id']);
            if(!$eligible->execute()){$eligible->close();throw new RuntimeException('Unable to verify the purchase.');}
            $verified=$eligible->get_result()->fetch_row();$eligible->close();
            if(!$verified)throw new DomainException('This review cannot be approved because a delivered purchase was not found.');
        }

        $moderation_note=ucfirst($action).' by '.$staff;
        $update=$conn->prepare('UPDATE product_reviews SET moderation_status=?,is_approved=?,moderated_by=?,moderated_at=NOW(),moderation_note=? WHERE id=?');
        if(!$update)throw new RuntimeException('Unable to prepare the moderation update.');
        $update->bind_param('siisi',$new_status,$approved,$moderator,$moderation_note,$review_id);
        if(!$update->execute()||$update->affected_rows!==1){$update->close();throw new RuntimeException('Unable to update the review.');}
        $update->close();

        $details="Review #{$review_id} by {$review['customer_name']} for {$review['product_name']} changed from {$old_status} to {$new_status}.";
        $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES (?,?,'Review Moderation',?)");
        if(!$log)throw new RuntimeException('Unable to prepare the audit entry.');
        $log->bind_param('iss',$moderator,$staff,$details);
        if(!$log->execute()){$log->close();throw new RuntimeException('Unable to record the moderation audit.');}
        $log->close();

        $conn->commit();
        $_SESSION['review_csrf_token']=bin2hex(random_bytes(32));
        echo 'SUCCESS|Review marked '.ucfirst($new_status).'.';exit;
    }catch(DomainException $error){
        $conn->rollback();
        http_response_code(409);
        echo 'ERROR|'.$error->getMessage();exit;
    }catch(Throwable $error){
        $conn->rollback();
        error_log('Review moderation failed: '.$error->getMessage());
        http_response_code(500);
        echo 'ERROR|Moderation could not be saved.';exit;
    }
}

$status=trim((string)($_GET['status']??''));
$rating=(int)($_GET['rating']??0);
$search=mb_substr(trim((string)($_GET['review_search']??'')),0,100);
$page=max(1,(int)($_GET['page']??1));
$per_page=12;
$where=[];$types='';$params=[];
if(in_array($status,['pending','live','hidden','rejected'],true)){$where[]='pr.moderation_status=?';$types.='s';$params[]=$status;}
if($rating>=1&&$rating<=5){$where[]='pr.star_rating=?';$types.='i';$params[]=$rating;}
if($search!==''){$where[]='(pr.customer_name LIKE ? OR pr.review_comment LIKE ? OR p.product_name LIKE ?)';$types.='sss';$term='%'.$search.'%';$params[]=$term;$params[]=$term;$params[]=$term;}
$where_sql=$where?' WHERE '.implode(' AND ',$where):'';
$base=" FROM product_reviews pr LEFT JOIN products p ON pr.product_id=p.id";
$load_error='';
$total=0;
$reviews=null;

$count_stmt=$conn->prepare('SELECT COUNT(*) total'.$base.$where_sql);
if(!$count_stmt){
    error_log('Review count preparation failed: '.$conn->error);
    $load_error='Review records could not be loaded. Please try again.';
}else{
    if($types!=='')$count_stmt->bind_param($types,...$params);
    if(!$count_stmt->execute()){
        error_log('Review count query failed: '.$count_stmt->error);
        $load_error='Review records could not be loaded. Please try again.';
    }else{
        $count=$count_stmt->get_result();
        $total=$count?(int)$count->fetch_assoc()['total']:0;
    }
    $count_stmt->close();
}

$pages=max(1,(int)ceil($total/$per_page));
$page=min($page,$pages);
$offset=($page-1)*$per_page;
if($load_error===''){
    $reviews_sql="SELECT pr.*,COALESCE(p.product_name,'Unavailable product') product_name,EXISTS(SELECT 1 FROM orders vo INNER JOIN order_items voi ON voi.order_id=vo.id WHERE vo.user_id=pr.user_id AND voi.product_id=pr.product_id AND LOWER(TRIM(vo.order_status))='delivered') verified_purchase".$base.$where_sql." ORDER BY pr.id DESC LIMIT ? OFFSET ?";
    $reviews_stmt=$conn->prepare($reviews_sql);
    if(!$reviews_stmt){
        error_log('Review list preparation failed: '.$conn->error);
        $load_error='Review records could not be loaded. Please try again.';
    }else{
        $review_types=$types.'ii';
        $review_params=$params;$review_params[]=$per_page;$review_params[]=$offset;
        $reviews_stmt->bind_param($review_types,...$review_params);
        if(!$reviews_stmt->execute()){
            error_log('Review list query failed: '.$reviews_stmt->error);
            $load_error='Review records could not be loaded. Please try again.';
        }else{
            $reviews=$reviews_stmt->get_result();
        }
        $reviews_stmt->close();
    }
}

$summary=['pending'=>0,'live'=>0,'hidden'=>0,'rejected'=>0];
$sums=$conn->query("SELECT moderation_status,COUNT(*) total FROM product_reviews GROUP BY moderation_status");
if(!$sums){
    error_log('Review summary query failed: '.$conn->error);
    if($load_error==='')$load_error='Review summary could not be loaded. Please try again.';
}else{
    while($s=$sums->fetch_assoc()){
        if(isset($summary[$s['moderation_status']]))$summary[$s['moderation_status']]=(int)$s['total'];
    }
}
$query=$_GET;unset($query['page']);
?>
<style>
.review-desk{font-family:Inter,Segoe UI,Arial,sans-serif;color:#172033}.review-hero{padding:25px;border:1px solid #dbe5ef;border-radius:16px;background:linear-gradient(135deg,#fff,#f5f8ff)}.review-hero h2{margin:0 0 7px;font-size:25px}.review-hero p{margin:0;color:#64748b}.review-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}.review-stat{padding:16px;border-radius:13px;border:1px solid #e2e8f0}.review-stat span{display:block;font-size:11px;font-weight:900;text-transform:uppercase}.review-stat strong{display:block;margin-top:7px;font-size:22px}.review-stat:nth-child(1){background:#fffbeb;border-left:4px solid #f59e0b}.review-stat:nth-child(2){background:#ecfdf5;border-left:4px solid #10b981}.review-stat:nth-child(3){background:#eff6ff;border-left:4px solid #3b82f6}.review-stat:nth-child(4){background:#fff1f2;border-left:4px solid #ef4444}.review-filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:9px;padding:15px;background:#fff;border:1px solid #e2e8f0;border-radius:13px;margin-bottom:16px}.review-input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.review-apply{border:0;border-radius:8px;background:#2563eb;color:#fff;padding:10px 16px;font-weight:800;cursor:pointer}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.review-card{padding:18px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 7px 18px #0f172a0a}.review-top{display:flex;justify-content:space-between;gap:12px}.review-product{color:#2563eb;font-size:12px;font-weight:900;text-transform:uppercase}.review-stars{color:#f59e0b;letter-spacing:2px}.review-comment{margin:14px 0;color:#475569;line-height:1.55}.review-meta{color:#94a3b8;font-size:11px}.review-verification{display:inline-flex;margin-top:8px;padding:4px 8px;border-radius:99px;background:#dcfce7;color:#166534;font-size:10px;font-weight:900;text-transform:uppercase}.review-verification.unverified{background:#fee2e2;color:#991b1b}.review-warning{align-self:center;color:#991b1b;font-size:10px;font-weight:800}.review-badge{padding:4px 8px;border-radius:99px;font-size:10px;font-weight:900;text-transform:uppercase}.review-badge.pending{background:#fef3c7;color:#92400e}.review-badge.live{background:#d1fae5;color:#065f46}.review-badge.hidden{background:#dbeafe;color:#1d4ed8}.review-badge.rejected{background:#fee2e2;color:#991b1b}.review-actions{display:flex;gap:7px;margin-top:15px;flex-wrap:wrap}.review-action{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:11px;font-weight:800;cursor:pointer}.review-action.approve{background:#10b981;border-color:#10b981;color:#fff}.review-action.reject{color:#dc2626;border-color:#fecaca}.review-pages{display:flex;justify-content:center;gap:6px;margin-top:18px}.review-page{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;color:#475569}.review-page.active{background:#2563eb;color:#fff}.review-empty{grid-column:1/-1;padding:35px;text-align:center;color:#94a3b8}@media(max-width:800px){.review-stats{grid-template-columns:1fr 1fr}.review-filters,.review-grid{grid-template-columns:1fr}}
</style>
<section class="review-desk"><div class="review-hero"><h2>Customer Feedback Moderator</h2><p>Review verified-purchase feedback before it appears on product pages.</p></div>
<div class="review-stats"><article class="review-stat"><span>Pending</span><strong><?=$summary['pending']?></strong></article><article class="review-stat"><span>Live</span><strong><?=$summary['live']?></strong></article><article class="review-stat"><span>Hidden</span><strong><?=$summary['hidden']?></strong></article><article class="review-stat"><span>Rejected</span><strong><?=$summary['rejected']?></strong></article></div>
<form class="review-filters" method="GET" action="manage_reviews.php"><input class="review-input" name="review_search" value="<?=htmlspecialchars($search)?>" placeholder="Search customer, product or comment..."><select class="review-input" name="status"><option value="">All statuses</option><?php foreach(['pending','live','hidden','rejected'] as $v):?><option value="<?=$v?>" <?=$status===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach;?></select><select class="review-input" name="rating"><option value="0">All ratings</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=$rating===$i?'selected':''?>><?=$i?> stars</option><?php endfor;?></select><button class="review-apply">Apply filters</button></form>
<div class="review-grid"><?php if($load_error):?><div class="review-empty" role="alert"><?=htmlspecialchars($load_error)?></div><?php elseif(!$reviews||!$reviews->num_rows):?><div class="review-empty">No reviews match these filters.</div><?php endif;?><?php while(!$load_error&&$reviews&&$r=$reviews->fetch_assoc()):$state=$r['moderation_status']?:($r['is_approved']?'live':'pending');$verified=!empty($r['verified_purchase']);?>
<article class="review-card" id="review-row-<?=(int)$r['id']?>"><div class="review-top"><div><div class="review-product"><?=htmlspecialchars($r['product_name'])?></div><strong><?=htmlspecialchars($r['customer_name'])?></strong><br><span class="review-verification <?=$verified?'':'unverified'?>"><?=$verified?'Verified delivered purchase':'Delivered purchase not found'?></span></div><span class="review-badge <?=htmlspecialchars($state)?>"><?=htmlspecialchars($state)?></span></div><div class="review-stars"><?=str_repeat('&#9733;',(int)$r['star_rating'])?></div><p class="review-comment"><?=nl2br(htmlspecialchars($r['review_comment']))?></p><div class="review-meta"><?=date('d M Y, h:i A',strtotime($r['created_at']))?> - Review #<?=(int)$r['id']?><?php if(!empty($r['moderated_at'])):?> - Last moderated <?=date('d M Y, h:i A',strtotime($r['moderated_at']))?><?php endif;?></div><div class="review-actions"><?php if($state!=='live'&&$verified):?><button class="review-action approve" data-review-action="approve" data-review-id="<?=(int)$r['id']?>">Approve</button><?php elseif($state!=='live'):?><span class="review-warning">Approval blocked until a delivered purchase is verified.</span><?php endif;?><?php if($state!=='hidden'):?><button class="review-action" data-review-action="hide" data-review-id="<?=(int)$r['id']?>">Hide</button><?php endif;?><?php if($state!=='rejected'):?><button class="review-action reject" data-review-action="reject" data-review-id="<?=(int)$r['id']?>">Reject</button><?php endif;?></div></article><?php endwhile;?></div>
<?php if($load_error===''&&$pages>1):?><div class="review-pages"><?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):$pq=http_build_query(array_merge($query,['page'=>$i]));?><a class="review-page ajax-link <?=$i===$page?'active':''?>" data-target="manage_reviews.php?<?=htmlspecialchars($pq)?>" href="manage_reviews.php?<?=htmlspecialchars($pq)?>"><?=$i?></a><?php endfor;?></div><?php endif;?></section>
<script>(function(){document.querySelectorAll('[data-review-action]').forEach(function(button){if(button.dataset.ready)return;button.dataset.ready='1';button.addEventListener('click',async function(){var action=this.dataset.reviewAction;if(action==='reject'&&!confirm('Reject this review? It will remain stored for audit purposes.'))return;var fd=new FormData();fd.set('moderation_action',action);fd.set('review_id',this.dataset.reviewId);fd.set('csrf_token','<?=htmlspecialchars($review_csrf,ENT_QUOTES)?>');var button=this;var originalText=button.textContent;button.disabled=true;button.textContent='Saving...';try{var response=await fetch('manage_reviews.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});var text=(await response.text()).trim();var parts=text.split('|');alert(parts.slice(1).join('|')||'Moderation completed.');if(parts[0]==='SUCCESS'){var reload=typeof sync==='function'?sync:window.syncWorkspaceView;if(reload)reload('manage_reviews.php',false);else window.location.reload();return;}button.disabled=false;button.textContent=originalText;}catch(error){alert('Moderation could not be completed.');button.disabled=false;button.textContent=originalText;}});});})();</script>
