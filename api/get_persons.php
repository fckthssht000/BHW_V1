<?php
/**
 * File: api/get_persons.php
 * Get list of persons for form filling
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireLogin();

$db = new Database();
$search = $_GET['search'] ?? '';
$purok = $_GET['purok'] ?? '';

$sql = "SELECT p.person_id, p.full_name, p.age, p.gender, a.purok
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND p.full_name LIKE ?";
    $params[] = "%$search%";
}

if ($purok && needsPurokFilter()) {
    $sql .= " AND a.purok = ?";
    $params[] = $purok;
}

$sql .= " ORDER BY p.full_name LIMIT 50";

$persons = $db->fetchAll($sql, $params);

echo json_encode($persons);
