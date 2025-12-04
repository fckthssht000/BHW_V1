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
$stmt = $pdo->prepare("SELECT p.*, a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
$stmt->execute([$person_id]);
$person = $stmt->fetch(PDO::FETCH_ASSOC);

if ($person) {
    echo json_encode(['success' => true, 'person_id' => $person['person_id'], 'full_name' => $person['full_name'], 'relationship_type' => $person['relationship_type'], 'gender' => $person['gender'], 'birthdate' => $person['birthdate'], 'civil_status' => $person['civil_status'], 'contact_number' => $person['contact_number'], 'purok' => $person['purok'], 'deceased' => $person['deceased'] ?? 0]);
} else {
    echo json_encode(['success' => false, 'message' => 'Person not found']);
}
?>