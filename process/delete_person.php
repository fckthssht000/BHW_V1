<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['person_id']) || !is_numeric($_POST['person_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid person ID']);
    exit;
}

$person_id = $_POST['person_id'];

try {
    $pdo->beginTransaction();

    // Step 1: Delete from records (will cascade to related tables if ON DELETE CASCADE is set)
    $stmt = $pdo->prepare("DELETE FROM records WHERE person_id = ?");
    $stmt->execute([$person_id]);

    // Step 2: Delete from person
    $stmt = $pdo->prepare("DELETE FROM person WHERE person_id = ?");
    $stmt->execute([$person_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . '$e->getMessage']);
}
?>