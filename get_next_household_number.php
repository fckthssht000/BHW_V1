<?php
require_once 'db_connect.php';

header('Content-Type: text/plain'); // Ensure plain text response

if (isset($_POST['purok'])) {
    $puroks = $_POST['purok'];
    // Accepts either a single value or an array
    if (!is_array($puroks)) {
        $puroks = [$puroks];
    }

    $results = [];
    foreach ($puroks as $purok) {
        // Handle numeric purok and lettered purok (e.g., 4A, 4B)
        if (is_numeric($purok)) {
            $base_number = (int)$purok * 100 + 1;
        } else {
            // For lettered purok, assign a unique base (e.g., 4A = 401, 4B = 411)
            // You can adjust this logic as needed for your numbering scheme
            $number = (int)$purok;
            $letter = strtoupper(substr($purok, -1));
            $offset = ($letter === 'A') ? 1 : (($letter === 'B') ? 11 : 0);
            $base_number = $number * 100 + $offset;
        }

        $stmt = $pdo->prepare("SELECT MAX(household_number) FROM person WHERE household_number >= ? AND household_number < ?");
        $stmt->execute([$base_number, $base_number + 99]);
        $max_household_number = $stmt->fetchColumn();

        $results[$purok] = $max_household_number !== null ? $max_household_number : -1;
    }

    // Output as JSON for easier client handling
    echo json_encode($results);
}
?>
