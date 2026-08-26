<?php
// reset_password.php
require_once 'db.php';

$message = '';
$error = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $tokenHash = hash("sha256", $token);

    // Look up the valid token that hasn't expired yet
    $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Token invalid or expired. Please request a new link.";
    }
} else {
    $error = "Missing token parameter.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new credential securely
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update database password and scrub the token details cleanly
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $updateStmt->execute([$newHash, $user['id']]);

        $message = "Your password has been successfully updated! You can now log in.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #666; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #28a745; border: none; border-radius: 4px; color: white; cursor: pointer; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
<div class="card">
    <h2>New Password</h2>
    
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>

    <?php if (!$error && !$message): ?>
    <form method="POST">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">Update Password</button>
    </form>
    <?php endif; ?>
    <br><a href="login.php">Back to Login</a>
</div>
</body>
</html>
