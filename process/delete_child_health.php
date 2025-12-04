<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['records_id'])) {
    $records_id = $_GET['records_id'];

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Delete from child_record table
        $stmt = $pdo->prepare("DELETE FROM child_record WHERE records_id = ?");
        $stmt->execute([$records_id]);

        // Commit transaction
        $pdo->commit();

        // Redirect back with success message
        header("Location: ../child_health_records.php?message=Record deleted successfully");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        header("Location: ../child_health_records.php?error=Failed to delete record");
        exit;
    }
} else {
    header("Location: ../child_health_records.php");
    exit;
}
?>
