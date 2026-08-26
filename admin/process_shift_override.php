<?php
session_start();
include '../db.php';

$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['shift_action'] ?? '') === 'clock_out') {
    header('Content-Type: application/json');

    if (!$isSuperAdmin) {
        echo json_encode(['status' => false, 'message' => 'Unauthorized action privileges.']);
        exit;
    }

    // Never infer a target from the latest row: the selected timecard ID is required.
    $attendanceId = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    if (!$attendanceId || $attendanceId < 1) {
        echo json_encode(['status' => false, 'message' => 'Choose an active employee shift to conclude.']);
        exit;
    }

    // Confirm the selected record is still open, and use its stored shift type.
    $checkStmt = $conn->prepare("SELECT staff_name, shift_type FROM staff_attendance WHERE id = ? AND shift_status = 'Active' LIMIT 1");
    $checkStmt->bind_param('i', $attendanceId);
    $checkStmt->execute();
    $activeTarget = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$activeTarget) {
        echo json_encode(['status' => false, 'message' => 'That shift is no longer active. Refresh the dashboard and try again.']);
        exit;
    }

    // Repeat the Active check in the UPDATE to prevent closing a row changed by another request.
    $updateStmt = $conn->prepare("UPDATE staff_attendance SET clock_out_time = NOW(), shift_status = 'Completed' WHERE id = ? AND shift_status = 'Active'");
    $updateStmt->bind_param('i', $attendanceId);
    $updateStmt->execute();
    $updated = $updateStmt->affected_rows === 1;
    $updateStmt->close();

    if (!$updated) {
        echo json_encode(['status' => false, 'message' => 'That shift changed before it could be concluded. Refresh the dashboard and try again.']);
        exit;
    }

    $shiftType = ucwords(str_replace('_', ' ', $activeTarget['shift_type'] ?: 'regular'));
    echo json_encode([
        'status' => true,
        'message' => "Override successful: concluded {$activeTarget['staff_name']}'s {$shiftType} shift."
    ]);
    exit;
}