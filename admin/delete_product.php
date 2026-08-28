<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../trash_manager.php';
header('Content-Type: text/plain; charset=utf-8');

if (!verifyWorkspaceClearance('warehouse.php')) {
    http_response_code(403);
    exit('AUTH_FAILURE');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('METHOD_NOT_ALLOWED');
}
if (!hash_equals((string)($_SESSION['warehouse_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('CSRF_FAILURE');
}
$productId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(422);
    exit('INVALID_PRODUCT');
}

$conn->begin_transaction();
try {
    softDeleteProduct($conn, (int)$productId);
    $conn->commit();
    exit('TRASH_SUCCESS');
} catch (Throwable $e) {
    $conn->rollback();
    if ($e->getMessage() === 'NOT_FOUND') {
        http_response_code(404);
        exit('NOT_FOUND');
    }
    if ($e->getMessage() === 'ALREADY_TRASHED') {
        http_response_code(409);
        exit('ALREADY_TRASHED');
    }
    error_log('Trash product failed: ' . $e->getMessage());
    http_response_code(500);
    exit('TRASH_FAILED');
}
