<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['person_id']) || !is_numeric($_POST['person_id']) || !isset($_POST['full_name']) || !isset($_POST['relationship_type']) || !isset($_POST['gender']) || !isset($_POST['birthdate']) || !isset($_POST['civil_status']) || !isset($_POST['contact_number']) || !isset($_POST['purok']) || !isset($_POST['deceased'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid data']);
    exit;
}

$person_id = $_POST['person_id'];
$full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
$relationship_type = filter_var($_POST['relationship_type'], FILTER_SANITIZE_STRING);
$gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
$birthdate = filter_var($_POST['birthdate'], FILTER_SANITIZE_STRING);
$civil_status = filter_var($_POST['civil_status'], FILTER_SANITIZE_STRING);
$contact_number = filter_var($_POST['contact_number'], FILTER_SANITIZE_STRING);
$purok = filter_var($_POST['purok'], FILTER_SANITIZE_STRING);
$deceased = (int)$_POST['deceased'];

// Calculate age from birthdate
try {
    $birth_date = new DateTime($birthdate);
    $current_date = new DateTime();
    $age = $current_date->diff($birth_date)->y;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid birthdate format']);
    exit;
}

// if (!preg_match('/^[0-9]{10,11}$/', $contact_number)) {
//     $response = ['success' => false, 'message' => 'Contact number must be 10 or 11 digits'];
//     echo json_encode($response);
//     exit;
// }

try {
    $pdo->beginTransaction();

    // Get or create address_id
    $stmt = $pdo->prepare("SELECT address_id FROM address WHERE purok = ?");
    $stmt->execute([$purok]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$address) {
        $stmt = $pdo->prepare("INSERT INTO address (purok) VALUES (?)");
        $stmt->execute([$purok]);
        $address_id = $pdo->lastInsertId();
    } else {
        $address_id = $address['address_id'];
    }

    // Update person record with age
    $stmt = $pdo->prepare("UPDATE person SET full_name = ?, relationship_type = ?, gender = ?, birthdate = ?, civil_status = ?, contact_number = ?, address_id = ?, deceased = ?, age = ? WHERE person_id = ?");
    $stmt->execute([$full_name, $relationship_type, $gender, $birthdate, $civil_status, $contact_number, $address_id, $deceased, $age, $person_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>