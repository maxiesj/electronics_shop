<?php
// Ensure your universal security gatekeeper file is pulled in safely
require_once dirname(__FILE__) . '/../session_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once file_exists('../db.php') ? '../db.php' : '../../db.php';

// FIXED SECURITY GATE: Allows both Super Admin and regular Admins automatically
if (!verifyWorkspaceClearance('db_backup.php')) {
    if (isset($_REQUEST['ajax_request'])) { 
        echo "backup_error: Access Denied"; 
        exit(); 
    }
    echo "<script>window.location.href = '../login.php?msg=err_unauthorized_access';</script>";
    exit();
}

$dir = 'D:/xampp/htdocs/electronics_shop/backups_vault/';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

$tables = []; $res = $conn->query("SHOW TABLES");
while ($r = $res->fetch_row()) { $tables[] = $r[0]; }

$dump = "-- ADONAK RESTORATION VAULT SNAPSHOT\nSET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $tName) {
    if ($c_res = $conn->query("SHOW CREATE TABLE `$tName`")) {
        $r2 = $c_res->fetch_row(); $dump .= "\n\n" . $r2[1] . ";\n\n";
    }
    $d_res = $conn->query("SELECT * FROM `$tName`");
    if ($d_res) {
        $fields = $d_res->field_count;
        while ($d_row = $d_res->fetch_row()) {
            $dump .= "INSERT INTO `$tName` VALUES(";
            for ($j=0; $j<$fields; $j++) {
                $dump .= isset($d_row[$j]) ? '"'.$conn->real_escape_string($d_row[$j]).'"' : 'NULL';
                if ($j < ($fields-1)) { $dump .= ','; }
            } // 1. CLOSES THE FOR LOOP
            $dump .= ");\n";
        } // 2. CLOSES THE WHILE LOOP
    }
} // 3. CLOSES THE FOREACH LOOP PERFECTLY

$dump .= "\n\nSET FOREIGN_KEY_CHECKS=1;\n";
$file = 'autobackup_'.date('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(3)).'ea.sql';

if (file_put_contents($dir.$file, $dump) !== false) {
    $msg_det = "Automated Snapshot Log Generated: [{$file}] saved to backups_vault.";
    $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'System Update', ?)");
    if ($audit) { $audit->bind_param("iss", $_SESSION['user_id'], $_SESSION['fullname'], $msg_det); $audit->execute(); }
    
    if (isset($_REQUEST['ajax_request'])) { 
        echo "backup_success:".$file; 
        exit(); 
    }
    // FIXED REDIRECT: Safe JavaScript routing to dodge output header buffer conflicts
    echo "<script>window.location.href = 'db_backup.php?msg=auto_success&file=" . urlencode($file) . "';</script>";
    exit();
} else { 
    exit("Write Failure"); 
}
?>
