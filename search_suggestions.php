<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];
$role_id = $_GET['role_id'] ?? null;
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query && strlen($query) > 2) {
    $user_person_id = $_SESSION['person_id'] ?? null;
    if (!$user_person_id) {
        $stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_person_id = $stmt->fetchColumn();
    }

    $purok_filter = ($role_id == 2) ? "AND a.purok = (SELECT purok FROM address WHERE address_id = (SELECT address_id FROM person WHERE person_id = ?))" : "";
    $params = [$user_person_id, $user_person_id];
    if ($role_id == 2) {
        $params[] = $user_person_id;
    }
    $searchTerm = "%$query%";

    // Search across multiple tables
    // Persons
    $stmt = $pdo->prepare("SELECT full_name, person_id FROM person p JOIN address a ON p.address_id = a.address_id WHERE (p.related_person_id = ? OR p.person_id = ?) " . $purok_filter . " AND full_name LIKE ? LIMIT 5");
    $stmt->execute(array_merge($params, [$searchTerm]));
    while ($row = $stmt->fetch()) {
        $results[] = '<div data-type="person" data-id="' . $row['person_id'] . '" onclick="selectSuggestion(this)">' . htmlspecialchars($row['full_name']) . ' (Person)</div>';
    }

    // Activity Logs (Notices)
    $stmt = $pdo->prepare("SELECT activity, activity_logs_id FROM activity_logs WHERE activity LIKE ? AND activity LIKE ? LIMIT 5");
    $stmt->execute(["%$query%", "%to_user_id:$user_id%"]);
    while ($row = $stmt->fetch()) {
        $notice_content = substr($row['activity'], 0, strpos($row['activity'], ' to_user_id:'));
        if (stripos($notice_content, $query) !== false) {
            $results[] = '<div data-type="notice" data-id="' . $row['activity_logs_id'] . '" onclick="selectSuggestion(this)">' . htmlspecialchars($notice_content) . ' (Notice)</div>';
        }
    }

    // Pregnancy Records
    $stmt = $pdo->prepare("SELECT pr.records_id, p.full_name FROM pregnancy_record pr JOIN records r ON pr.records_id = r.records_id JOIN person p ON r.person_id = p.person_id WHERE p.full_name LIKE ? LIMIT 5");
    $stmt->execute([$searchTerm]);
    while ($row = $stmt->fetch()) {
        $results[] = '<div data-type="pregnancy" data-id="' . $row['records_id'] . '" onclick="selectSuggestion(this)">' . htmlspecialchars($row['full_name']) . ' (Pregnancy Record)</div>';
    }

    // Senior Records
    $stmt = $pdo->prepare("SELECT sr.records_id, p.full_name FROM senior_record sr JOIN records r ON sr.records_id = r.records_id JOIN person p ON r.person_id = p.person_id WHERE p.full_name LIKE ? LIMIT 5");
    $stmt->execute([$searchTerm]);
    while ($row = $stmt->fetch()) {
        $results[] = '<div data-type="senior" data-id="' . $row['records_id'] . '" onclick="selectSuggestion(this)">' . htmlspecialchars($row['full_name']) . ' (Senior Record)</div>';
    }
}

echo implode('', $results);
?>