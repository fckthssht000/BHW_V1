<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['records_id'])) {
    $records_id = $_POST['records_id'];

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Delete from family_planning_record table
        $stmt = $pdo->prepare("DELETE FROM family_planning_record WHERE records_id = ?");
        $stmt->execute([$records_id]);

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
