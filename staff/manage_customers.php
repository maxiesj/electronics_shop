<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../session_auth.php';
if(!verifyExplicitWorkspaceClearance('manage_customers.php')){
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');exit;
}
$staff_id=(int)($_SESSION['user_id']??0);
$staff_name=$_SESSION['fullname']??$_SESSION['staff_name']??'Operational Staff';
$operator_role=strtolower(trim((string)($_SESSION['role']??'')));
$can_deactivate_customer=in_array($operator_role,['admin','super_admin'],true);
if(empty($_SESSION['customer_management_csrf']))$_SESSION['customer_management_csrf']=bin2hex(random_bytes(32));
$msg='';$err='';

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['customer_action'])){
    if(!$can_deactivate_customer){http_response_code(403);$err='Only an administrator can deactivate customer accounts.';}
    elseif(empty($_POST['csrf_token'])||!hash_equals($_SESSION['customer_management_csrf'],(string)$_POST['csrf_token'])){$err='This page expired. Refresh it and try again.';}
    elseif($_POST['customer_action']!=='deactivate'){$err='The requested customer action is invalid.';}
    else{
        $customer_id=filter_var($_POST['customer_id']??null,FILTER_VALIDATE_INT);
        if(!$customer_id){$err='Select a valid customer account.';}
        else{
            $conn->begin_transaction();
            try{
                $lookup=$conn->prepare("SELECT u.id,u.fullname,u.email,u.account_status,r.role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE");
                if(!$lookup)throw new RuntimeException('Customer lookup preparation failed.');
                $lookup->bind_param('i',$customer_id);if(!$lookup->execute()){$lookup->close();throw new RuntimeException('Customer lookup failed.');}
                $customer=$lookup->get_result()->fetch_assoc();$lookup->close();
                if(!$customer||strtolower(trim((string)$customer['role_name']))!=='customer')throw new DomainException('The selected account is not a customer.');
                if(strtolower((string)$customer['account_status'])==='purged')throw new DomainException('This customer is already deactivated.');
                $update=$conn->prepare("UPDATE users SET account_status='purged',reset_token_hash=NULL,reset_token_expires_at=NULL WHERE id=?");
                if(!$update)throw new RuntimeException('Customer update preparation failed.');
                $update->bind_param('i',$customer_id);if(!$update->execute()||$update->affected_rows!==1){$update->close();throw new RuntimeException('Customer update failed.');}$update->close();
                $details="Customer account deactivated: {$customer['fullname']} ({$customer['email']}), user #{$customer_id}. Orders, payments, wallet and review history preserved.";
                $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Customer Deactivation',?)");
                if(!$log)throw new RuntimeException('Audit preparation failed.');
                $log->bind_param('iss',$staff_id,$staff_name,$details);if(!$log->execute()){$log->close();throw new RuntimeException('Audit logging failed.');}$log->close();
                $conn->commit();$_SESSION['customer_management_csrf']=bin2hex(random_bytes(32));$msg='Customer account deactivated. Historical records were preserved.';
            }catch(DomainException $e){$conn->rollback();$err=$e->getMessage();}
            catch(Throwable $e){$conn->rollback();error_log('Customer deactivation failed: '.$e->getMessage());$err='The customer could not be deactivated. Please try again.';}
        }
    }
}
// 4. FETCH VALID CLIENT RECORDS (FIXED: Handles structural table joins and filters out purged profiles)
$query = "
    SELECT u.id,u.fullname,u.email,u.phone,u.created_at,u.kra_pin,u.shipping_address,u.account_status,r.role_name,
           COUNT(o.id) total_orders,
           SUM(CASE WHEN o.id IS NOT NULL AND LOWER(TRIM(COALESCE(o.order_status,''))) <> 'cancelled' THEN 1 ELSE 0 END) non_cancelled_orders,
           COALESCE(SUM(CASE WHEN o.id IS NOT NULL AND LOWER(TRIM(COALESCE(o.order_status,''))) <> 'cancelled' THEN o.total_amount ELSE 0 END),0) non_cancelled_order_value
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    LEFT JOIN orders o ON o.user_id=u.id
    WHERE LOWER(TRIM(r.role_name)) = 'customer' AND COALESCE(LOWER(TRIM(u.account_status)),'active') <> 'purged'
    GROUP BY u.id,u.fullname,u.email,u.phone,u.created_at,u.kra_pin,u.shipping_address,u.account_status,r.role_name
    ORDER BY u.id DESC
";

$result = $conn->query($query);
$users_list = [];
if (!$result) {
    error_log('Customer registry query failed: '.$conn->error);
    $load_error = 'Customer records could not be loaded. Please try again.';
    $err = $err !== '' ? $err.' '.$load_error : $load_error;
} else {
    $users_list = $result->fetch_all(MYSQLI_ASSOC);
}
$customer_count=count($users_list);
$customers_with_orders=0;
$customer_order_value=0.0;
foreach($users_list as $customer) {
    if ((int)$customer['total_orders']>0) $customers_with_orders++;
    $customer_order_value+=(float)$customer['non_cancelled_order_value'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <link rel="icon" type="image/jpeg" href="../uploads/logo.jpg">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Customer Registry Hub | ADONAK ELECTRONICS</title>
   <style>
    /* 1. Global Baseline Reset Styles */
    body { background-color: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; color: #1f2937; }
    
    /* 2. Navigation Header Section Shell Layout Components */
    nav { background-color: #111827; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; z-index: 50; }
    .nav-brand { font-weight: 800; font-size: 1.25rem; color: #f97316; white-space: nowrap; }
    .nav-center-links { display: flex; gap: 16px; font-size: 0.875rem; font-weight: 600; align-items: center; }
    .nav-center-links a { color: #d1d5db; text-decoration: none; padding: 6px 12px; border-radius: 4px; transition: background 0.2s, color 0.2s; white-space: nowrap; }
    .nav-center-links a:hover, .nav-center-links a.active { color: white; background-color: #1f2937; }
    
    /* Session Meta & Exit Controls */
    .nav-right-meta { display: flex; align-items: center; gap: 20px; font-size: 0.875rem; }
    .logout-btn { color: #f87171 !important; text-decoration: none; font-weight: 700; border: 1px solid #7f1d1d; padding: 6px 12px; border-radius: 6px; background-color: rgba(153, 27, 27, 0.12); white-space: nowrap; transition: background 0.2s, color 0.2s; }
    .logout-btn:hover { background-color: rgba(153, 27, 27, 0.25); color: #fca5a5 !important; }
    
    /* 3. Core Structural Container (Default Desktop View) */
    main { max-width: 85rem; margin: 40px auto; padding: 0 24px 64px; box-sizing: border-box; width: 100%; }
    
    /* Fixed broken non-standard style properties allocation values */
    .main-title { font-size: 1.5rem; font-weight: 900; color: #111827; margin: 0; letter-spacing: -0.025em; }
    .sub-subtitle { font-size: 0.813rem; color: #6b7280; font-weight: 600; margin-bottom: 24px; text-transform: uppercase; margin-top: 8px; }

    /* Action Status Messages */
    .alert-box { padding: 12px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; box-sizing: border-box; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* 4. White Data Matrix Table Wrapper Block */
    .content-block { background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; }
    table { width: 100%; border-collapse: collapse; font-size: 0.813rem; text-align: left; }
    th { background-color: #f9fafb; color: #4b5563; padding: 10px 12px; font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #374151; font-weight: 500; vertical-align: middle; }
    tr:hover td { background-color: #f8fafc; }
    
    /* Inline Role Form Selectors & Save Triggers */
    .role-select { border: 1px solid #d1d5db; border-radius: 4px; padding: 0 6px; background-color: white; font-size: 0.75rem; font-weight: 700; outline: none; cursor: pointer; color: #374151; height: 26px; box-sizing: border-box; }
    .save-btn { background-color: #111827; color: white; font-weight: 700; border: none; padding: 6px 14px; border-radius: 4px; font-size: 10px; text-transform: uppercase; cursor: pointer; letter-spacing: 0.02em; transition: background-color 0.2s; height: 26px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .save-btn:hover { background-color: #1f2937; }

    /* Interactive Access Level System Pills */
    .role-pill { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; display: inline-block; white-space: nowrap; }
    .role-admin { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .role-staff { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .role-customer { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .role-inactive { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

    /* 5. Processing Loader Overlay Styles */
    .loader-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(17, 24, 39, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; padding: 20px; box-sizing: border-box; }
    .spinner { width: 40px; height: 40px; border: 4px solid #d1d5db; border-top-color: #f97316; border-radius: 50%; animation: spin 0.8s linear infinite; }
    
    /* Fixed invalid custom property string syntax assignments */
    .loader-text { color: #e5e7eb; font-size: 0.875rem; font-weight: 700; margin-top: 16px; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ==========================================================================
       6. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 💻 DESKTOP & LANDSCAPE TABLETS FLUIDITY (Max 1024px Width Viewports) */
    @media (max-width: 1024px) {
        main { margin: 24px auto; padding: 0 16px 40px; }
        .content-block { padding: 16px; }
    }

    /* 📱 TRANSITIONAL PORTRAIT TABLETS & SMARTPHONES BREAKPOINT (Max 768px Viewports) */
    @media (max-width: 768px) {
        /* Restructure Navbar row elements into stacked vertical flow */
        nav { flex-direction: column; gap: 14px; padding: 14px 16px; text-align: center; }
        .nav-center-links { gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .nav-center-links a { font-size: 0.8rem; padding: 4px 8px; }
        .nav-right-meta { width: 100%; justify-content: center; border-top: 1px solid #374151; padding-top: 10px; margin-top: 2px; }
        
        /* Main Document Wrapper padding boundaries shrinkages */
        main { margin: 16px auto; padding: 0 12px 32px; }
        .main-title { font-size: 1.3rem; }
        .sub-subtitle { font-size: 0.75rem; margin-bottom: 16px; }
        
        /* Responsive Horizontal Table Data Scrolling Solution */
        .content-block { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 0.5rem; }
        table { display: block; width: 100%; }
        th, td { white-space: nowrap; padding: 10px; }
        
        /* Expand touch boundaries for inline account modifications */
        .role-select { height: 34px; font-size: 0.85rem; padding: 0 8px; }
        .save-btn { height: 34px; padding: 0 16px; font-size: 11px; }
    }
</style>

<link rel="stylesheet" href="../css/panel-polish.css">
<style>
.customer-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin:18px 0}.customer-stat{padding:17px;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff}.customer-stat:nth-child(2){background:#ecfdf5;border-color:#a7f3d0}.customer-stat:nth-child(3){background:#f5f3ff;border-color:#ddd6fe}.customer-stat small,.customer-stat strong{display:block}.customer-stat strong{font-size:21px;margin-top:6px}.customer-tools{display:flex;gap:10px;align-items:center;margin-bottom:14px}.customer-tools input{flex:1;padding:11px 13px;border:1px solid #cbd5e1;border-radius:9px}.customer-result-count{padding:7px 11px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800;font-size:12px}.customer-hidden{display:none}.customer-readonly-note{font-size:12px;color:#64748b}.customer-deactivate{padding:7px 10px;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#b42318;font-size:11px;font-weight:800;cursor:pointer}.customer-deactivate:hover{background:#fff1f0}@media(max-width:700px){.customer-summary{grid-template-columns:1fr}.customer-tools{flex-direction:column}.customer-result-count{text-align:center}}
</style>
<script src="../js/page-progress-dialog.js"></script>
</head>
<body>
<?php include_once 'navbar.php'; ?>

    

        <main>
        <a class="staff-back-link" href="staff_dashboard.php" aria-label="Back to Staff Dashboard">&larr; Back to Staff Dashboard</a>
        <h1 class="main-title">Registered Customer Profile Hub</h1>
        <p class="sub-subtitle">Operational ledger auditing client records & shipping specifications</p>
        
        <?php if (!empty($msg)): ?><div class="alert-box alert-success">✅ <?= htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($err)): ?><div class="alert-box alert-danger">❌ <?= htmlspecialchars($err); ?></div><?php endif; ?>

        <div class="content-block">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Profile Address</th>
                        <th>Telephone Line</th>
                        <th>KRA PIN</th>
                        <th>Default Shipping Address</th>
                        <th>Order Records</th>
                        <th>Account Status</th>
                        <?php if($can_deactivate_customer):?><th>Account Action</th><?php endif;?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users_list) > 0): foreach ($users_list as $user_row):
                        $total_orders = (int)$user_row['total_orders'];
                        $non_cancelled_orders = (int)$user_row['non_cancelled_orders'];
                        $account_status = strtolower(trim((string)($user_row['account_status'] ?? 'active')));
                        if ($account_status === '') $account_status = 'active';
                        $account_status_label = ucwords(str_replace('_', ' ', $account_status));
                        $account_status_class = $account_status === 'active' ? 'role-customer' : 'role-inactive';
                    ?>
                    <tr data-customer-row>
                        <td style="font-weight: 700; color: #2563eb;">#<?= $user_row['id']; ?></td>
                        <td style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($user_row['fullname']); ?></td>
                        <td style="font-weight: 600; color: #4b5563;"><?= htmlspecialchars($user_row['email']); ?></td>
                        <td style="font-family: monospace; font-weight: 700;"><?= htmlspecialchars($user_row['phone'] ? $user_row['phone'] : 'None'); ?></td>
                        <td style="font-family: monospace; font-weight: 700; text-transform: uppercase; color: #b91c1c;"><?= htmlspecialchars($user_row['kra_pin'] ? $user_row['kra_pin'] : 'Not Configured'); ?></td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($user_row['shipping_address'] ?? ''); ?>">
                            <?= $user_row['shipping_address'] ? htmlspecialchars($user_row['shipping_address']) : '<span style="color:#9ca3af;font-style:italic;">No Address Saved</span>'; ?>
                        </td>
                        <td style="font-weight: 800; color: #2563eb; text-align: center; font-size: 13px;"><?= $total_orders; ?> records<br><small style="color:#64748b;font-weight:600;"><?= $non_cancelled_orders; ?> non-cancelled</small></td>
                        <td><span class="role-pill <?=$account_status_class?>"><?=htmlspecialchars($account_status_label)?> Client</span></td>
                        <?php if($can_deactivate_customer):?><td><form method="post" action="manage_customers.php" onsubmit="return confirm('Deactivate this customer account? The customer will be unable to log in, but orders, payments, wallet and reviews will be preserved.');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['customer_management_csrf'])?>"><input type="hidden" name="customer_action" value="deactivate"><input type="hidden" name="customer_id" value="<?=(int)$user_row['id']?>"><button class="customer-deactivate" type="submit">Deactivate</button></form></td><?php endif;?>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="<?=$can_deactivate_customer?9:8?>" style="text-align: center; color: #9ca3af; padding: 32px 0; font-weight: 600;">No customer registration records mapped in the system.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

       <!-- Global Interface Inter-Page Loader Transition Overlay Box Wrapper Frame -->
    <div class="loader-overlay" id="global-page-loader">
        <div class="spinner"></div>
        <p class="loader-text">Loading Operations Desk...</p>
    </div>

    <!-- JavaScript Navigation Routing Interceptors -->
    <script>
    document.querySelectorAll('.nav-center-links a, table a, .nav-loading-link, .logout-btn').forEach(link => {
        // Skip fallback actions, javascript tasks, or form submission buttons handled by system alerts
        if (link.getAttribute('href') === '#' || link.getAttribute('href').startsWith('javascript:') || link.type === 'submit') return;
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            
            // Open full-screen transition spinner layout overlay instantly
            document.getElementById('global-page-loader').style.display = 'flex';
            
            // Hold redirect cushion for 350ms to render the animation smoothly
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 350);
        });
    });
    </script>
<script>
(function(){
 const main=document.querySelector('main'),block=document.querySelector('.content-block');
 const subtitle=document.querySelector('.sub-subtitle');
 subtitle.textContent='Customer contact and order overview';
 subtitle.insertAdjacentHTML('afterend','<p class=customer-readonly-note><?=$can_deactivate_customer?'Administrators can deactivate access while preserving every historical record.':'Read-only directory. Account access is managed by administrators.'?></p>');
 const summary=document.createElement('div');summary.className='customer-summary';
 summary.innerHTML='<article class=customer-stat><small>Visible customers</small><strong><?=number_format($customer_count)?></strong></article><article class=customer-stat><small>Customers with order records</small><strong><?=number_format($customers_with_orders)?></strong></article><article class=customer-stat><small>Non-cancelled order value</small><strong>KES <?=number_format($customer_order_value,2)?></strong></article>';
 subtitle.nextElementSibling.insertAdjacentElement('afterend',summary);
 const tools=document.createElement('div');tools.className='customer-tools';
 tools.innerHTML='<input type=search><span class=customer-result-count></span>';
 main.insertBefore(tools,block);
 const rows=[...document.querySelectorAll('tbody tr[data-customer-row]')],input=tools.querySelector('input'),count=tools.querySelector('span');
 input.placeholder='Search name, email, phone, PIN or address';
 function filter(){const q=input.value.trim().toLowerCase();let shown=0;rows.forEach(row=>{const visible=!q||row.textContent.toLowerCase().includes(q);row.classList.toggle('customer-hidden',!visible);if(visible)shown++;});count.textContent=shown+' customer'+(shown===1?'':'s');}
 input.addEventListener('input',filter);filter();
})();
</script>
</body>
</html>
