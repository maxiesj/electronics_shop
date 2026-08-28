<?php
require_once __DIR__.'/../trash_service.php';
if (!function_exists('staffManagementAudit')) {
    function staffManagementAudit(mysqli $conn, string $details): void {
        $operator=(int)($_SESSION['user_id'] ?? 0);
        $name=(string)($_SESSION['fullname'] ?? 'Unknown operator');
        $action='Staff Management';
        $stmt=$conn->prepare('INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES (?,?,?,?)');
        $stmt->bind_param('isss',$operator,$name,$action,$details);
        if (!$stmt->execute()) throw new RuntimeException('Audit entry failed.');
        $stmt->close();
    }
}
if (!function_exists('staffManagementFinish')) {
    function staffManagementFinish(string $message): void {
        $_SESSION['success_flash']=$message;
        echo '<script>window.location.href=dashboard.php?view=manage_staff.php;</script>';
        exit;
    }
}
if (empty($_SESSION['staff_management_csrf'])) $_SESSION['staff_management_csrf']=bin2hex(random_bytes(32));
$staff_csrf=$_SESSION['staff_management_csrf'];
$staff_actions=['add_new_staff_account','update_staff_credentials','suspend_staff_account','add_custom_role_tier','add_new_system_privilege','add_new_permission_rule'];
$submitted_action='';
foreach ($staff_actions as $candidate) if (isset($_POST[$candidate])) {$submitted_action=$candidate;break;}
if ($submitted_action && !hash_equals($staff_csrf,(string)($_POST['csrf_token'] ?? ''))) {
    $error='Your security token expired. Refresh and try again.';
    unset($_POST[$submitted_action]);
}
if ($submitted_action==='add_new_staff_account' && isset($_POST[$submitted_action])) {
    $fullname=trim((string)($_POST['new_fullname'] ?? ''));
    $email=strtolower(trim((string)($_POST['new_email'] ?? '')));
    $password=(string)($_POST['new_password'] ?? '');
    $role_id=(int)($_POST['new_role_id'] ?? 0);
    if (mb_strlen($fullname)<2 || mb_strlen($fullname)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10 || strlen($password)>128) {
        $error='Enter a valid name, email, and a password of at least 10 characters.';
        unset($_POST[$submitted_action]);
    } else {
        $stmt=$conn->prepare('SELECT role_name FROM roles WHERE id=?');
        $stmt->bind_param('i',$role_id);$stmt->execute();$role=$stmt->get_result()->fetch_assoc();$stmt->close();
        if (!$role || $role['role_name']==='customer' || ($role['role_name']==='super_admin' && ($_SESSION['role'] ?? '')!=='super_admin')) {
            $error='You cannot assign that role.';
            unset($_POST[$submitted_action]);
        } else {
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                $stmt=$conn->prepare('SELECT id,account_status FROM users WHERE email=? LIMIT 1 FOR UPDATE');
                $stmt->bind_param('s',$email);
                $stmt->execute();
                $existing=$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($existing && strtolower((string)$existing['account_status'])!=='purged') throw new DomainException('An account already uses that email.');
                $verb=$existing ? 'Reactivated' : 'Created';
                if ($existing) {
                    $id=(int)$existing['id'];
                    $sql='UPDATE users SET fullname=?,password=?,role_id=?,account_status=\'active\' WHERE id=?';
                    $stmt=$conn->prepare($sql);
                    $stmt->bind_param('ssii',$fullname,$hash,$role_id,$id);
                } else {
                    $sql='INSERT INTO users(fullname,email,password,role_id,account_status) VALUES (?,?,?,?,\'active\')';
                    $stmt=$conn->prepare($sql);
                    $stmt->bind_param('sssi',$fullname,$email,$hash,$role_id);
                }
                if (!$stmt->execute()) throw new RuntimeException('Account write failed.');
                if (!$existing) $id=$stmt->insert_id;
                $stmt->close();
                staffManagementAudit($conn,$verb.' staff account #'.$id.' for '.$fullname.' ('.$email.'), role '.$role['role_name'].'.');
                $conn->commit();
                $_SESSION['staff_management_csrf']=bin2hex(random_bytes(32));
                staffManagementFinish($verb.' staff account successfully');
            } catch (Throwable $e) {
                $conn->rollback();
                $error=$e instanceof DomainException ? $e->getMessage() : 'The staff account could not be saved.';
                if (!($e instanceof DomainException)) error_log('Staff creation failed: '.$e->getMessage());
                unset($_POST[$submitted_action]);
            }
        }
    }
}
if ($submitted_action==='update_staff_credentials' && isset($_POST[$submitted_action])) {
    $staff_id=(int)($_POST['staff_id'] ?? 0);
    $fullname=trim((string)($_POST['fullname'] ?? ''));
    $email=strtolower(trim((string)($_POST['email'] ?? '')));
    $role_id=(int)($_POST['role_id'] ?? 0);
    if ($staff_id<1 || mb_strlen($fullname)<2 || mb_strlen($fullname)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        $error='Enter a valid staff name and email.';
        unset($_POST[$submitted_action]);
    } else {
        $conn->begin_transaction();
        try {
            $sql='SELECT r.role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE';
            $stmt=$conn->prepare($sql);$stmt->bind_param('i',$staff_id);$stmt->execute();
            $target=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$target) throw new DomainException('Staff account not found.');
            if ($target['role_name']==='super_admin' && ($_SESSION['role'] ?? '')!=='super_admin') throw new DomainException('Only a super administrator can modify this account.');
            $stmt=$conn->prepare('SELECT role_name FROM roles WHERE id=?');
            $stmt->bind_param('i',$role_id);$stmt->execute();$role=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$role || $role['role_name']==='customer') throw new DomainException('That role cannot be assigned to staff.');
            if ($role['role_name']==='super_admin' && ($_SESSION['role'] ?? '')!=='super_admin') throw new DomainException('Only a super administrator can assign that role.');
            $stmt=$conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
            $stmt->bind_param('si',$email,$staff_id);$stmt->execute();$duplicate=$stmt->get_result()->fetch_assoc();$stmt->close();
            if ($duplicate) throw new DomainException('Another account already uses that email.');
            $stmt=$conn->prepare('UPDATE users SET fullname=?,email=?,role_id=? WHERE id=?');
            $stmt->bind_param('ssii',$fullname,$email,$role_id,$staff_id);
            if (!$stmt->execute()) throw new RuntimeException('Profile write failed.');
            $stmt->close();
            $submitted=$_POST['allowed_views'][$staff_id] ?? [];
            if (!is_array($submitted)) $submitted=[];
            $allowed=[];
            $result=$conn->query('SELECT permission_key FROM system_permissions');
            while($result && $row=$result->fetch_assoc()) $allowed[]=$row['permission_key'];
            $views=array_values(array_intersect(array_unique(array_map('trim',$submitted)),$allowed));
            $stmt=$conn->prepare('DELETE FROM staff_permissions WHERE user_id=?');
            $stmt->bind_param('i',$staff_id);
            if (!$stmt->execute()) throw new RuntimeException('Permission reset failed.');
            $stmt->close();
            $stmt=$conn->prepare('INSERT INTO staff_permissions(user_id,target_view) VALUES (?,?)');
            foreach($views as $view) {
                $stmt->bind_param('is',$staff_id,$view);
                if (!$stmt->execute()) throw new RuntimeException('Permission write failed.');
            }
            $stmt->close();
            staffManagementAudit($conn,'Updated staff #'.$staff_id.' ('.$fullname.'), role '.$role['role_name'].', '.count($views).' permissions.');
            $conn->commit();
            staffManagementFinish('Staff profile and permissions updated');
        } catch (Throwable $e) {
            $conn->rollback();
            $error=$e instanceof DomainException ? $e->getMessage() : 'The staff profile could not be updated.';
            if (!($e instanceof DomainException)) error_log('Staff update failed: '.$e->getMessage());
            unset($_POST[$submitted_action]);
        }
    }
}
if ($submitted_action==='suspend_staff_account' && isset($_POST[$submitted_action])) {
    $id=(int)($_POST['staff_id'] ?? 0);
    if ($id<1 || $id===(int)($_SESSION['user_id'] ?? 0)) {
        $error='You cannot suspend that account.';
    } else {
        $conn->begin_transaction();
        try {
            $sql='SELECT u.fullname,r.role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE';
            $stmt=$conn->prepare($sql);$stmt->bind_param('i',$id);$stmt->execute();$target=$stmt->get_result()->fetch_assoc();$stmt->close();
            if (!$target || $target['role_name']==='customer') throw new DomainException('Staff account not found.');
            if ($target['role_name']==='super_admin' && ($_SESSION['role'] ?? '')!=='super_admin') throw new DomainException('Only a super administrator can suspend this account.');
            $target['_permissions']=[];
            $permissionSnapshot=$conn->prepare('SELECT target_view FROM staff_permissions WHERE user_id=?');
            $permissionSnapshot->bind_param('i',$id);$permissionSnapshot->execute();
            $permissionResult=$permissionSnapshot->get_result();
            while($permissionResult&&$permissionRow=$permissionResult->fetch_assoc())$target['_permissions'][]=$permissionRow['target_view'];
            $permissionSnapshot->close();
            trashArchiveRecord($conn,'staff',$id,(string)$target['fullname'],$target);
            $sql='UPDATE users SET account_status=\'purged\' WHERE id=?';
            $stmt=$conn->prepare($sql);
            $stmt->bind_param('i',$id);
            if (!$stmt->execute()) throw new RuntimeException('Account update failed.');
            $stmt->close();
            $stmt=$conn->prepare('DELETE FROM staff_permissions WHERE user_id=?');
            $stmt->bind_param('i',$id);
            if (!$stmt->execute()) throw new RuntimeException('Permission cleanup failed.');
            $stmt->close();
            staffManagementAudit($conn,'Suspended staff #'.$id.' ('.$target['fullname'].', role '.$target['role_name'].') and revoked permissions.');
            $conn->commit();
            staffManagementFinish('Staff access suspended');
        } catch (Throwable $e) {
            $conn->rollback();
            $error=$e instanceof DomainException ? $e->getMessage() : 'The account could not be suspended.';
            if (!($e instanceof DomainException)) error_log('Staff suspension failed: '.$e->getMessage());
        }
    }
}
