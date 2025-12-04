<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $records_id = $_POST['records_id'];
    $uses_fp = isset($_POST['fp_usage']) ? 'Y' : 'N';
    $fp_methods = isset($_POST['fp_methods']) ? implode(',', $_POST['fp_methods']) : '';
    $months_used = isset($_POST['months_use']) ? implode(',', $_POST['months_use']) : '';
    $reason_not_using = isset($_POST['reason_not_use']) ? $_POST['reason_not_use'] : '';

    try {
        // Update the record
        $stmt = $pdo->prepare("UPDATE family_planning_record SET uses_fp_method = ?, fp_method = ?, months_used = ?, reason_not_using = ? WHERE records_id = ?");
        $stmt->execute([$uses_fp, $fp_methods, $months_used, $reason_not_using, $records_id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
