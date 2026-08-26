<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!verifyWorkspaceClearance('db_backup.php')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { echo 'ERROR|Access denied.'; exit; }
    header('Location: ../login.php?msg=err_unauthorized_access'); exit;
}

date_default_timezone_set('Africa/Nairobi');
$db_host='localhost'; $db_user='root'; $db_pass=''; $db_name='electronics_shop';
$backup_dir=__DIR__ . '/backups/';

if (!is_dir($backup_dir) && !mkdir($backup_dir,0750,true)) {
    http_response_code(500); exit('Backup storage is unavailable.');
}
@chmod($backup_dir,0750);
// Prevent the web server from serving raw SQL files without authentication.
if (!file_exists($backup_dir.'.htaccess')) @file_put_contents($backup_dir.'.htaccess',"Require all denied\nDeny from all\n");

if (empty($_SESSION['backup_csrf_token'])) $_SESSION['backup_csrf_token']=bin2hex(random_bytes(32));
$csrf_token=$_SESSION['backup_csrf_token'];

function validBackupPath($dir,$filename) {
    $clean=basename((string)$filename);
    if (!preg_match('/^autobackup_[A-Za-z0-9_-]+\.sql$/',$clean)) return null;
    $path=$dir.$clean;
    return is_file($path) ? $path : null;
}
function readableBytes($bytes) {
    return $bytes>=1048576 ? number_format($bytes/1048576,2).' MB' : number_format($bytes/1024,2).' KB';
}
function backupDb($host,$user,$pass,$name) {
    $db=new mysqli($host,$user,$pass,$name);
    if ($db->connect_error) throw new Exception('Database unavailable.');
    $db->set_charset('utf8mb4');
    return $db;
}
function auditBackup($db,$details) {
    $uid=(int)($_SESSION['user_id']??0);
    $staff=$_SESSION['fullname']??$_SESSION['staff_name']??'Administrator';
    $stmt=$db->prepare("INSERT INTO staff_logs (user_id,staff_name,action_type,action_details) VALUES (?,?,'System Update',?)");
    if ($stmt) { $stmt->bind_param('iss',$uid,$staff,$details); $stmt->execute(); $stmt->close(); }
}
function createBackupFile($target,$host,$user,$pass,$name) {
    $db=backupDb($host,$user,$pass,$name);
    $out=fopen($target,'wb');
    if (!$out) { $db->close(); throw new Exception('Storage unavailable.'); }
    try {
        fwrite($out,"-- ADONAK Electronics database backup\n-- Generated: ".date('Y-m-d H:i:s')."\n\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $tables=$db->query('SHOW TABLES');
        if (!$tables) throw new Exception('Cannot read database tables.');
        while ($row=$tables->fetch_row()) {
            $table=$row[0];
            $quoted=chr(96).$table.chr(96);
            $structure=$db->query('SHOW CREATE TABLE '.$quoted);
            $create=$structure?$structure->fetch_row():null;
            if (!$create) throw new Exception('Cannot export table structure.');
            fwrite($out,'DROP TABLE IF EXISTS '.$quoted.";\n".$create[1].";\n\n");
            // Stream result rows to keep memory stable as the database grows.
            $data=$db->query('SELECT * FROM '.$quoted,MYSQLI_USE_RESULT);
            if (!$data) throw new Exception('Cannot export table records.');
            while ($record=$data->fetch_assoc()) {
                $values=[];
                foreach (array_values($record) as $value) {
                    $values[]=$value===null?'NULL':"'".$db->real_escape_string($value)."'";
                }
                fwrite($out,'INSERT INTO '.$quoted.' VALUES ('.implode(', ',$values).");\n");
            }
            $data->free(); fwrite($out,"\n");
        }
        fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");
    } catch (Exception $e) {
        fclose($out); $db->close(); @unlink($target); throw $e;
    }
    fclose($out);
    return $db;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['action']??'')==='download') {
    $path=validBackupPath($backup_dir,$_GET['filename']??'');
    if (!$path) { http_response_code(404); exit('Backup not found.'); }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    header('Content-Length: '.filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    if (!hash_equals($csrf_token,(string)($_POST['csrf_token']??''))) {
        echo 'ERROR|Your security token expired. Refresh and try again.'; exit;
    }
    if ($_POST['action']==='run_backup') {
        $filename='autobackup_'.date('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(3)).'.sql';
        try {
            $db=createBackupFile($backup_dir.$filename,$db_host,$db_user,$db_pass,$db_name);
            auditBackup($db,'Manual database backup created: '.$filename.'.'); $db->close();
            echo 'SUCCESS|Backup created successfully: '.$filename;
        } catch (Exception $e) {
            error_log('Backup failed: '.$e->getMessage());
            echo 'ERROR|Backup failed. Check database and storage availability.';
        }
        exit;
    }
    if ($_POST['action']==='delete_backup') {
        $path=validBackupPath($backup_dir,$_POST['filename']??'');
        if (!$path) { echo 'ERROR|Backup not found.'; exit; }
        $name=basename($path);
        if (!unlink($path)) { echo 'ERROR|Backup could not be deleted.'; exit; }
        try { $db=backupDb($db_host,$db_user,$db_pass,$db_name); auditBackup($db,'Database backup deleted: '.$name.'.'); $db->close(); }
        catch (Exception $e) { error_log('Backup audit failed: '.$e->getMessage()); }
        echo 'SUCCESS|Backup deleted successfully.'; exit;
    }
    echo 'ERROR|Unknown backup action.'; exit;
}

$backup_files=[]; $total_bytes=0;
foreach (is_dir($backup_dir)?scandir($backup_dir):[] as $file) {
    $path=validBackupPath($backup_dir,$file);
    if (!$path) continue;
    $size=filesize($path); $modified=filemtime($path); $total_bytes+=$size;
    $backup_files[]=['name'=>$file,'weight'=>readableBytes($size),'modified'=>$modified,'timestamp'=>date('d M Y, g:i A',$modified)];
}
usort($backup_files,function($a,$b){return $b['modified']<=>$a['modified'];});
$latest=$backup_files[0]??null;
?>
<style>
.backup-center{max-width:1120px;margin:auto;padding:28px 22px 52px;color:#172033;font-family:Inter,Segoe UI,Arial,sans-serif}.backup-hero{display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;padding:28px;border:1px solid #dbe5ef;border-radius:18px;background:linear-gradient(135deg,#fff,#f3f9ff);box-shadow:0 12px 30px rgba(15,23,42,.06)}.backup-heading{display:flex;gap:16px;align-items:center}.backup-shield{width:58px;height:58px;border-radius:16px;background:#e0f2fe;color:#0284c7;display:grid;place-items:center;font-size:27px}.backup-heading h2{margin:0 0 7px;font-size:25px}.backup-heading p{margin:0;color:#64748b;line-height:1.5}.backup-primary{min-width:210px;padding:14px 20px;border:0;border-radius:11px;background:#0f9f78;color:#fff;font-weight:800;cursor:pointer;box-shadow:0 8px 18px rgba(15,159,120,.22);transition:.2s}.backup-primary:hover{transform:translateY(-2px)}.backup-primary:disabled{opacity:.7;cursor:wait}.backup-spinner{display:none;width:14px;height:14px;margin-right:7px;border:2px solid #ffffff66;border-top-color:#fff;border-radius:50%;vertical-align:-3px;animation:spin .7s linear infinite}.is-loading .backup-spinner{display:inline-block}@keyframes spin{to{transform:rotate(360deg)}}.backup-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0 28px}.backup-stat{position:relative;overflow:hidden;padding:18px 20px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.04);transition:transform .2s ease,box-shadow .2s ease}.backup-stat:hover{transform:translateY(-2px);box-shadow:0 12px 25px rgba(15,23,42,.08)}.backup-stat:nth-child(1){background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-color:#a7f3d0;border-left:4px solid #10b981}.backup-stat:nth-child(2){background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;border-left:4px solid #3b82f6}.backup-stat:nth-child(3){background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#fde68a;border-left:4px solid #f59e0b}.backup-stat span{display:block;color:#64748b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.backup-stat strong{display:block;margin-top:8px;font-size:18px}.backup-stat small{display:block;margin-top:4px;color:#8795aa}.backup-panel{overflow:hidden;border:1px solid #dbe5ef;border-radius:16px;background:#fff;box-shadow:0 8px 24px #0f172a0c}.backup-toolbar{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:18px 20px}.backup-toolbar h3{margin:0;font-size:14px;text-transform:uppercase;letter-spacing:.05em}.backup-search{width:min(320px,100%);padding:10px 13px;border:1px solid #cbd5e1;border-radius:9px}.backup-table-wrap{overflow:auto}.backup-table{width:100%;min-width:760px;border-collapse:collapse}.backup-table th{padding:13px 18px;background:#f8fafc;color:#64748b;font-size:11px;text-align:left}.backup-table td{padding:16px 18px;border-top:1px solid #edf1f5;font-size:13px}.backup-name{font-family:Consolas,monospace}.latest{margin-left:8px;padding:3px 7px;border-radius:99px;background:#dcfce7;color:#15803d;font-size:10px;font-weight:800}.backup-actions{display:flex;justify-content:flex-end;gap:7px}.backup-action{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer}.backup-download{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 5px 12px rgba(37,99,235,.18)}.backup-download:hover{background:#1d4ed8;border-color:#1d4ed8;color:#fff;transform:translateY(-1px)}.backup-delete{border-color:#fecaca;color:#dc2626}.backup-empty{padding:42px!important;text-align:center;color:#94a3b8}.backup-note{color:#718096;font-size:12px;margin:14px 2px}@media(max-width:760px){.backup-center{padding:18px 12px}.backup-hero{grid-template-columns:1fr}.backup-primary{width:100%}.backup-stats{grid-template-columns:1fr}.backup-toolbar{align-items:stretch;flex-direction:column}.backup-search{width:100%;box-sizing:border-box}}
</style>
<section class="backup-center">
<div class="backup-hero"><div class="backup-heading"><div class="backup-shield">&#128737;</div><div><h2>Project Data Integrity Center</h2><p>Create and manage protected database snapshots for transaction and account recovery.</p></div></div>
<form class="backup-action-form" action="db_backup.php" method="POST"><input type="hidden" name="action" value="run_backup"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>"><button class="backup-primary" type="submit"><span class="backup-spinner"></span><span>Create Backup Now</span></button></form></div>
<div class="backup-stats">
<article class="backup-stat"><span>Latest successful backup</span><strong><?=$latest?htmlspecialchars($latest['timestamp']):'Not available'?></strong><small><?=$latest?'Ready to download':'Create the first backup'?></small></article>
<article class="backup-stat"><span>Stored archives</span><strong><?=count($backup_files)?></strong><small>Protected SQL snapshots</small></article>
<article class="backup-stat"><span>Storage used</span><strong><?=readableBytes($total_bytes)?></strong><small>Local backup vault</small></article>
</div>
<div class="backup-panel"><div class="backup-toolbar"><h3>Database backup archive</h3><input id="backup-search" class="backup-search" type="search" placeholder="Search by filename or date..." aria-label="Search backups"></div><div class="backup-table-wrap"><table class="backup-table"><thead><tr><th>Backup file</th><th>Size</th><th>Created</th><th style="text-align:right">Actions</th></tr></thead><tbody>
<?php if(!$backup_files):?><tr><td colspan="4" class="backup-empty">No database backups have been created yet.</td></tr><?php endif;?>
<?php foreach($backup_files as $index=>$file):?><tr data-backup-row data-search="<?=htmlspecialchars(strtolower($file['name'].' '.$file['timestamp']))?>"><td><span class="backup-name"><?=htmlspecialchars($file['name'])?></span><?php if($index===0):?><span class="latest">LATEST</span><?php endif;?></td><td><?=htmlspecialchars($file['weight'])?></td><td><?=htmlspecialchars($file['timestamp'])?></td><td><div class="backup-actions"><a class="backup-action backup-download" href="db_backup.php?action=download&amp;filename=<?=rawurlencode($file['name'])?>">&#8595; Download</a><form class="backup-action-form" action="db_backup.php" method="POST" onsubmit="return confirm('Delete this backup permanently? This cannot be undone.');"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="filename" value="<?=htmlspecialchars($file['name'])?>"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>"><button class="backup-action backup-delete" type="submit">Delete</button></form></div></td></tr><?php endforeach;?>
</tbody></table></div></div><p class="backup-note">Restore remains restricted to maintenance mode to prevent accidental replacement of live transaction data.</p>
</section>
<script>(function(){var s=document.getElementById('backup-search');if(!s||s.dataset.ready)return;s.dataset.ready='1';s.addEventListener('input',function(){var q=this.value.trim().toLowerCase();document.querySelectorAll('[data-backup-row]').forEach(function(r){r.hidden=!!q&&!r.dataset.search.includes(q);});});})();</script>