<?php

function trashRegistryAvailable(mysqli $conn): bool {
    static $available = null;
    if ($available !== null) return $available;
    $result = $conn->query("SHOW TABLES LIKE 'trash_records'");
    $available = $result && $result->num_rows > 0;
    return $available;
}

function trashRecordTypeForRole(string $role): string {
    return strtolower(trim($role)) === 'customer' ? 'customer' : 'staff';
}

function trashArchiveRecord(mysqli $conn, string $type, int $originalId, string $name, array $snapshot): void {
    $allowed = ['product','category','brand','customer','staff'];
    if (!in_array($type, $allowed, true) || $originalId < 1) {
        throw new InvalidArgumentException('Invalid trash record.');
    }
    if (!trashRegistryAvailable($conn)) {
        throw new RuntimeException('TRASH_MIGRATION_REQUIRED');
    }
    $operator = (int)($_SESSION['user_id'] ?? 0);
    $operatorName = (string)($_SESSION['fullname'] ?? 'System operator');
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Trash snapshot could not be encoded.');

    $clear = $conn->prepare("DELETE FROM trash_records WHERE record_type=? AND original_id=? AND status='restored'");
    $clear->bind_param('si', $type, $originalId);
    $clear->execute();
    $clear->close();

    $stmt = $conn->prepare("INSERT INTO trash_records(record_type,original_id,display_name,record_snapshot,deleted_by,deleted_by_name) VALUES(?,?,?,?,?,?)");
    if (!$stmt) throw new RuntimeException('Trash archive preparation failed.');
    $stmt->bind_param('sissis', $type, $originalId, $name, $json, $operator, $operatorName);
    if (!$stmt->execute()) throw new RuntimeException('Trash archive failed: '.$stmt->error);
    $stmt->close();
}

function trashAudit(mysqli $conn, string $details): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $name = (string)($_SESSION['fullname'] ?? 'System operator');
    $stmt = $conn->prepare("INSERT INTO staff_logs(user_id,staff_name,action_type,action_details) VALUES(?,?, 'System Update', ?)");
    if ($stmt) {$stmt->bind_param('iss',$uid,$name,$details);$stmt->execute();$stmt->close();}
}
