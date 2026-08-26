<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');

if (empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['logout_csrf'], $csrf)) {
        http_response_code(403);
        $logout_error = 'The logout request expired. Please refresh this page and try again.';
    } else {
        $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? 0);
        $fullname = (string)($_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'Account User');
        $role = strtolower(trim((string)($_SESSION['role'] ?? '')));

        // Audit only when the database definition supports Staff Logout; an audit failure must never retain a session.
        if ($user_id > 0 && in_array($role, ['admin', 'super_admin', 'staff', 'cashier', 'auditor', 'supervissoor', 'cleaner'], true)) {
            $column = $conn->query("SHOW COLUMNS FROM staff_logs LIKE 'action_type'");
            $definition = $column ? (string)($column->fetch_assoc()['Type'] ?? '') : '';
            if (stripos($definition, "'Staff Logout'") !== false) {
                $details = 'Secure session terminated via IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $audit = $conn->prepare("INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, 'Staff Logout', ?)");
                if ($audit) {
                    $audit->bind_param("iss", $user_id, $fullname, $details);
                    $audit->execute();
                }
            }
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool)$params['secure'],
                'httponly' => true,
                'samesite' => $params['samesite'] ?: 'Lax'
            ]);
        }
        session_destroy();
        header('Clear-Site-Data: "cache", "storage"');
        header('Location: login.php?msg=logout_success');
        exit;
    }
}

$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['staff_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm Logout</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#f3f4f6;font-family:ui-sans-serif,system-ui,sans-serif;color:#1e293b}.box{width:min(420px,100%);padding:28px;background:#fff;border:1px solid #e2e8f0;border-radius:13px;box-shadow:0 14px 30px rgba(15,23,42,.09);text-align:center}.icon{width:52px;height:52px;margin:auto;display:grid;place-items:center;border-radius:50%;background:#fee2e2;font-size:24px}.box h1{margin:15px 0 8px;font-size:21px}.box p{margin:0;color:#64748b;font-size:12px;line-height:1.6}.error{margin:14px 0 0;padding:10px;border-radius:7px;background:#fee2e2;color:#b91c1c;font-size:12px;font-weight:700}.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:22px}.actions a,.actions button{min-height:42px;border-radius:7px;display:grid;place-items:center;text-decoration:none;font-size:11px;font-weight:900;text-transform:uppercase;cursor:pointer}.cancel{border:1px solid #cbd5e1;color:#475569;background:#fff}.logout{border:0;background:#dc2626;color:#fff}.logout:hover{background:#b91c1c}@media(max-width:420px){.actions{grid-template-columns:1fr}}
</style></head>
<body><main class="box"><div class="icon">&#128682;</div><h1><?= $is_logged_in ? 'Confirm Logout' : 'Session Already Closed'; ?></h1><p><?= $is_logged_in ? 'Are you sure you want to securely end this session?' : 'There is no active account session on this browser.'; ?></p>
<?php if (!empty($logout_error)): ?><div class="error"><?= htmlspecialchars($logout_error); ?></div><?php endif; ?>
<?php if ($is_logged_in): ?><div class="actions"><a class="cancel" href="javascript:history.back()">Cancel</a><form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['logout_csrf']); ?>"><button class="logout" type="submit" name="confirm_logout">Log Out Securely</button></form></div>
<?php else: ?><div class="actions" style="grid-template-columns:1fr"><a class="cancel" href="login.php">Return to Login</a></div><?php endif; ?>
</main></body></html>