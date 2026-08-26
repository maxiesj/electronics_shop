<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';
if(!verifyExplicitWorkspaceClearance('manage_reviews.php')){
    header('Location: staff_dashboard.php?msg=err_unauthorized_access');exit;
}
// Process moderation before rendering navigation so asynchronous responses stay clean.
if($_SERVER['REQUEST_METHOD']==='POST'){
    require __DIR__ . '/../admin/manage_reviews.php';
    exit;
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Feedback Moderator | ADONAK</title><style>body{margin:0;background:#f4f7fb;font-family:Inter,Segoe UI,Arial,sans-serif}.staff-review-shell{max-width:1280px;margin:30px auto;padding:0 22px 50px;box-sizing:border-box}@media(max-width:700px){.staff-review-shell{margin:18px auto;padding:0 12px 35px}}</style><script src="../js/page-progress-dialog.js"></script>
</head><body><?php include_once 'navbar.php'; ?><main class="staff-review-shell"><?php require __DIR__ . '/../admin/manage_reviews.php'; ?></main></body></html>