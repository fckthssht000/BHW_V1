<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_GET['records_id'])) {
    $records_id = $_GET['records_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using
            FROM family_planning_record fpr
            WHERE fpr.records_id = ?
        ");
        $stmt->execute([$records_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            echo json_encode(['success' => true, 'data' => $record]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch record']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
