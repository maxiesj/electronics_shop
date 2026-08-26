<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
if (!verifyWorkspaceClearance('manage_categories.php')) {
    header('Location: ../login.php?msg=err_unauthorized_access');
    exit;
}
// Legacy mutations were retired; categories and brands are now changed through the CSRF-protected workspace.
header('Location: dashboard.php?view=manage_categories.php');
exit;