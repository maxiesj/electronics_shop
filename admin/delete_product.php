<?php
require_once __DIR__ . '/../session_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../trash_service.php';
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
    $find = $conn->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');
    $find->bind_param('i', $productId);
    $find->execute();
    $product = $find->get_result()->fetch_assoc();
    $find->close();
    if (!$product) throw new RuntimeException('NOT_FOUND');

    trashArchiveRecord($conn,'product',$productId,(string)$product['product_name'],$product);
    // The database snapshot is retained for recovery; keep the image file in place.
    $delete = $conn->prepare('DELETE FROM products WHERE id=?');
    $delete->bind_param('i', $productId);
    if (!$delete->execute()) throw new mysqli_sql_exception($delete->error, $delete->errno);
    $delete->close();

    $userId=(int)($_SESSION['user_id']??0);
    $staffName=(string)($_SESSION['fullname']??'System operator');
    $details="Product #{$productId} ({$product['product_name']}) removed from the catalog.";
    $log=$conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?,'Inventory Delete',?)");
    if($log){$log->bind_param('iss',$userId,$staffName,$details);$log->execute();$log->close();}
    $conn->commit();

    exit('DELETION_SUCCESS');
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('Delete product failed: '.$e->getMessage());
    if((int)$e->getCode()===1451){http_response_code(409);exit('PRODUCT_IN_USE');}
    http_response_code(500);exit('DELETE_FAILED');
} catch (Throwable $e) {
    $conn->rollback();
    if($e->getMessage()==='NOT_FOUND'){http_response_code(404);exit('NOT_FOUND');}
    if($e->getMessage()==='TRASH_MIGRATION_REQUIRED'){http_response_code(503);exit('TRASH_MIGRATION_REQUIRED');}
    error_log('Delete product failed: '.$e->getMessage());
    http_response_code(500);exit('DELETE_FAILED');
}
