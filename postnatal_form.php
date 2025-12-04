<?php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
ob_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Helper: get all medications for a pregnancy_record (returns array)
function get_medications($pdo, $pregnancy_record_id) {
    $stmt = $pdo->prepare("SELECT m.medication_name FROM pregnancy_medication pm JOIN medication m ON pm.medication_id = m.medication_id WHERE pm.pregnancy_record_id = ?");
    $stmt->execute([$pregnancy_record_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    // Get postnatal data
    if ($_POST['ajax'] == 'get_postnatal_data') {
        try {
            $stmt = $pdo->prepare("
                SELECT pn.postnatal_id, pn.date_delivered, pn.delivery_location, pn.attendant, pn.postnatal_checkups, 
                       pn.service_source, pn.family_planning_intent, pn.risk_observed, pr.pregnancy_record_id
                FROM records r 
                JOIN pregnancy_record pr ON r.records_id = pr.records_id 
                JOIN postnatal pn ON pr.pregnancy_record_id = pn.pregnancy_record_id 
                WHERE r.person_id = ? AND pr.pregnancy_period = 'Postnatal'
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'date_delivered' => '',
                        'delivery_location' => '',
                        'other_delivery_location' => '',
                        'attendant' => '',
                        'other_attendant' => '',
                        'risks' => [],
                        'checkups' => [],
                        'supplements' => [],
                        'service_source' => '',
                        'family_planning_intent' => ''
                    ]
                ]);
                exit;
            }

            $supplements = [];
            if ($data['pregnancy_record_id']) {
                $supplements = get_medications($pdo, $data['pregnancy_record_id']);
            }
            $risks = !empty($data['risk_observed']) ? explode(',', $data['risk_observed']) : [];
            $checkups = !empty($data['postnatal_checkups']) ? explode(',', $data['postnatal_checkups']) : [];

            $delivery_location = $data['delivery_location'];
            $other_delivery_location = '';
            if (strpos($delivery_location, ', ') !== false) {
                $parts = explode(', ', $delivery_location);
                $delivery_location = $parts[0];
                $other_delivery_location = $parts[1] ?? '';
            }
            $attendant = $data['attendant'];
            $other_attendant = '';
            if (strpos($attendant, ', ') !== false) {
                $parts = explode(', ', $attendant);
                $attendant = $parts[0];
                $other_attendant = $parts[1] ?? '';
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'date_delivered' => $data['date_delivered'] ?? '',
                    'delivery_location' => $delivery_location,
                    'other_delivery_location' => $other_delivery_location,
                    'attendant' => $attendant,
                    'other_attendant' => $other_attendant,
                    'risks' => $risks,
                    'checkups' => $checkups,
                    'supplements' => $supplements,
                    'service_source' => $data['service_source'] ?? '',
                    'family_planning_intent' => $data['family_planning_intent'] ?? ''
                ]
            ]);
        } catch (Exception $e) {
            error_log("getPostnatalData Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to fetch postnatal data']);
        }
        exit;
    }
    
    // Get mother details
    if ($_POST['ajax'] == 'get_mother_details') {
        try {
            $stmt = $pdo->prepare("
                SELECT p.full_name, p.age, p.birthdate, a.purok,
                       (SELECT COUNT(*) FROM records r 
                        JOIN pregnancy_record pr ON r.records_id = pr.records_id 
                        WHERE r.person_id = p.person_id) as pregnancy_count
                FROM person p
                LEFT JOIN address a ON p.address_id = a.address_id
                WHERE p.person_id = ?
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Calculate days since delivery
    if ($_POST['ajax'] == 'calculate_days_since_delivery') {
        try {
            $delivery_date = new DateTime($_POST['delivery_date']);
            $today = new DateTime();
            $days_since = $today->diff($delivery_date)->days;
            
            $checkup_recommendations = [];
            if ($days_since <= 1) {
                $checkup_recommendations[] = 'First 24 Hours';
            }
            if ($days_since <= 3) {
                $checkup_recommendations[] = 'First 72 Hours';
            }
            if ($days_since <= 7) {
                $checkup_recommendations[] = 'First 7 Days';
            }
            
            echo json_encode([
                'success' => true,
                'days_since' => $days_since,
                'recommendations' => $checkup_recommendations
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Validate delivery date
    if ($_POST['ajax'] == 'validate_delivery_date') {
        try {
            $delivery_date = new DateTime($_POST['delivery_date']);
            $today = new DateTime();
            $days_diff = $today->diff($delivery_date)->days;
            $is_future = $delivery_date > $today;
            
            $warnings = [];
            
            if ($is_future) {
                $warnings[] = 'Delivery date cannot be in the future';
            } elseif ($days_diff > 42) {
                $warnings[] = 'Delivery date is more than 6 weeks ago. Consider late postnatal care.';
            }
            
            echo json_encode([
                'success' => true,
                'warnings' => $warnings,
                'is_valid' => !$is_future
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Check for high-risk conditions
    if ($_POST['ajax'] == 'check_risks') {
        try {
            $risks = $_POST['risks'];
            $high_risk = ['Bleeding', 'Hypertension', 'Convulsion', 'Fever'];
            
            $critical_risks = array_intersect($risks, $high_risk);
            
            echo json_encode([
                'success' => true,
                'has_critical' => count($critical_risks) > 0,
                'critical_risks' => array_values($critical_risks)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// Fetch user role & purok
try {
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) die("Error: User not found for user_id: " . $_SESSION['user_id']);
    $role_id = $user['role_id'];

    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM users u 
        JOIN records r ON u.user_id = r.user_id 
        JOIN person p ON r.person_id = p.person_id 
        JOIN address a ON p.address_id = a.address_id 
        WHERE u.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
    if ($user_purok === false) die("Error: Unable to fetch user's purok.");
} catch (PDOException $e) {
    error_log("User/Purok Fetch Error: " . $e->getMessage());
    die("Database error: Unable to fetch user information.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    ob_clean();
    header('Content-Type: application/json');
    $person_id = $_POST['person_id'];
    $date_delivered = $_POST['delivery_date'];
    $delivery_location = $_POST['delivery_location'];
    $other_delivery_location = $_POST['other_delivery_location'] ?? '';
    $attendant = $_POST['attendant'];
    $other_attendant = $_POST['other_attendant'] ?? '';
    $risks = implode(',', $_POST['risks'] ?? []);
    $checkups = implode(',', $_POST['checkups'] ?? []);
    $supplements = $_POST['supplements'] ?? [];
    $service_source = $_POST['service_source'];
    $family_planning_intent = $_POST['family_planning_intent'];
    if (empty($person_id) || empty($date_delivered) || empty($delivery_location) || empty($attendant) || empty($service_source) || empty($family_planning_intent)) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
        exit;
    }
    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $mother_purok = $stmt->fetchColumn();
        if ($mother_purok !== $user_purok) {
            echo json_encode(['success' => false, 'error' => "BHW Staff can only submit records for their assigned purok ($user_purok)."]);
            exit;
        }
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)");
            $stmt->execute([$person_id]);
            $selected_user_id = $stmt->fetchColumn();
            if ($selected_user_id === false) throw new Exception("No user_id found for person_id: " . $person_id);
        }
        $stmt = $pdo->prepare("SELECT records_id FROM records WHERE user_id = ? AND person_id = ? AND record_type = ?");
        $stmt->execute([$selected_user_id, $person_id, 'pregnancy_record.postnatal']);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);
        $records_id = $existing_record ? $existing_record['records_id'] : null;
        if (!$records_id) {
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$selected_user_id, $person_id, 'pregnancy_record.postnatal', $_SESSION['user_id']]);
            $records_id = $pdo->lastInsertId();
        }
        // Find or insert pregnancy_record
        $stmt = $pdo->prepare("SELECT pregnancy_record_id FROM pregnancy_record WHERE records_id = ? AND pregnancy_period = 'Postnatal'");
        $stmt->execute([$records_id]);
        $pregnancy_record_id = $stmt->fetchColumn();
        if (!$pregnancy_record_id) {
            $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period, created_at, updated_at) VALUES (?, 'Postnatal', NOW(), NOW())");
            $stmt->execute([$records_id]);
            $pregnancy_record_id = $pdo->lastInsertId();
        }
        // Only add postnatal if one does not exist for this pregnancy_record
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM postnatal WHERE pregnancy_record_id = ?");
        $stmt->execute([$pregnancy_record_id]);
        if ($stmt->fetchColumn() > 0) throw new Exception("Postnatal record already exists for this pregnancy.");
        // Add the postnatal row
        $stmt = $pdo->prepare("INSERT INTO postnatal (pregnancy_record_id, delivery_location, attendant, postnatal_checkups, service_source, family_planning_intent, risk_observed, date_delivered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $pregnancy_record_id,
            $delivery_location . ($other_delivery_location ? ', ' . $other_delivery_location : ''),
            $attendant . ($other_attendant ? ', ' . $other_attendant : ''),
            $checkups,
            $service_source,
            $family_planning_intent,
            $risks,
            $date_delivered
        ]);
        // Handle medication (supplements): insert into junction table
        if (!empty($supplements)) {
            foreach ($supplements as $supplement) {
                $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                $stmt->execute([$supplement]);
                $medication_id = $stmt->fetchColumn();
                if ($medication_id === false) {
                    $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                    $stmt->execute([$supplement]);
                    $medication_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO pregnancy_medication (pregnancy_record_id, medication_id) VALUES (?, ?)");
                $stmt->execute([$pregnancy_record_id, $medication_id]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Form submitted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Form Submission Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Postnatal Form</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .navbar {
            background: rgba(43, 108, 176, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 80px;
        }
        .navbar-brand, .nav-link {
            color: #fff;
            font-weight: 500;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #e2e8f0;
        }
        .sidebar {
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
            width: 250px;
            height: calc(100vh - 80px);
            position: fixed;
            top: 80px;
            left: -250px;
            z-index: 1040;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar.open {
            transform: translateX(250px);
        }
        .sidebar .nav-link {
            color: #2d3748;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #edf2f7;
            color: #2b6cb0;
        }
        .content {
            padding: 20px;
            min-height: calc(100vh - 80px);
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
            position: relative;
            z-index: 1030;
            margin-top: 0;
        }
        .content.with-sidebar {
            margin-left: 0;
        }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-left: 25px;
            margin-right: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: rgba(43, 108, 176, 0.7);
            color: #fff;
            padding: 15px;
            border-bottom: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 15px;
            color: #1a202c;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
            background-color: #f8fafc;
        }
        .form-control.is-valid {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: #fff5f5;
        }
        .form-control::placeholder {
            color: #a0aec0;
            font-style: italic;
        }
        .form-control[readonly], .form-control:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
            height: 46px;
        }
        .form-control[type="date"] {
            padding-right: 40px;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280' stroke-width='2'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            cursor: pointer;
            position: relative;
        }
        .form-control[type="date"]:focus {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%233b82f6' stroke-width='2'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/%3e%3c/svg%3e");
        }
        .form-control[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 1;
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background: transparent;
            border: none;
        }
        .select2-container .select2-selection {
            border-radius: 10px;
            border: 1px solid #d1d5db;
            height: 46px;
            background: #ffffff;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            color: #1a202c;
            padding-left: 15px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            right: 10px;
        }
        .select2-container--default .select2-selection--multiple {
            min-height: 46px;
            padding: 5px;
        }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 12px 20px;
            font-size: 0.95rem;
            border-radius: 10px;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
            transform: translateY(-2px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
        .checkbox-group {
            margin-top: 0.5rem;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .checkbox-item input[type="checkbox"] {
            margin-top: 0.2rem;
            margin-right: 0.5rem;
            transform: scale(1.1);
        }
        .checkbox-item label {
            font-size: 0.95rem;
            color: #2d3748;
            margin-bottom: 0;
            word-break: break-word;
        }
        .other-field {
            display: none;
            margin-top: 10px;
        }
        .show { display: block; }
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 15px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #mother_info_card, #delivery_timeline, #risk_warnings, #checkup_recommendations {
            display: none;
            margin-bottom: 15px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .invalid-feedback, .valid-feedback {
            margin-top: 5px;
            font-size: 0.875rem;
        }
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
                height: calc(100vh - 80px);
                top: 80px;
            }
            .sidebar.open {
                transform: translateX(250px);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            }
            .content {
                margin-left: 0;
                width: 100%;
                padding: 10px;
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 10px;
            }
            .menu-toggle {
                display: block;
                color: #fff;
                font-size: 1.5rem;
                cursor: pointer;
                position: absolute;
                left: 10px;
                top: 20px;
                z-index: 1060;
            }
            .card {
                margin: 0;
                margin-bottom: 15px;
            }
            .card-body {
                padding: 1rem;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .checkbox-item input[type="checkbox"] {
                transform: scale(1.0);
            }
            .checkbox-item label {
                font-size: 0.9rem;
            }
            .navbar-brand {
                padding-left: 55px;
            }
            .form-control[type="date"] {
                padding-right: 45px;
                background-size: 18px;
                background-position: right 10px center;
            }
            .form-control[type="date"]::-webkit-calendar-picker-indicator {
                right: 8px;
                width: 18px;
                height: 18px;
            }
        }
        @media (min-width: 769px) {
            .menu-toggle { 
                display: none; 
            }
            .sidebar {
                left: 0;
                transform: translateX(0);
            }
            .content {
                margin-left: 250px;
            }
            .content.with-sidebar {
                margin-left: 250px;
            }
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 content">
                <div class="card">
                    <div class="card-header">Postnatal Form</div>
                    <div class="card-body p-4">
                        <div id="error-message"></div>
                        
                        <!-- Mother Info Card -->
                        <div id="mother_info_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user"></i> Mother Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="mother_name">-</span></p>
                                <p class="mb-1"><strong>Age:</strong> <span id="mother_age">-</span> years old</p>
                                <p class="mb-1"><strong>Purok:</strong> <span id="mother_purok">-</span></p>
                                <p class="mb-0"><strong>Pregnancy Records:</strong> <span id="pregnancy_count">0</span></p>
                            </div>
                        </div>

                        <!-- Delivery Timeline -->
                        <div id="delivery_timeline"></div>

                        <!-- Checkup Recommendations -->
                        <div id="checkup_recommendations"></div>

                        <!-- Risk Warnings -->
                        <div id="risk_warnings"></div>

                        <form id="postnatalForm" action="postnatal_form.php" method="POST">
                            <div class="form-group">
                                <label for="person_id">Select Mother <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="person_id" name="person_id" required>
                                    <option value="">Search and Select Mother...</option>
                                    <?php
                                    try {
                                        $current_date = new DateTime();
                                        $min_birthdate = clone $current_date;
                                        $min_birthdate->modify('-49 years');
                                        $max_birthdate = clone $current_date;
                                        $max_birthdate->modify('-15 years');

                                        if ($role_id == 2) {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name, p.birthdate 
                                                FROM person p 
                                                JOIN address a ON p.address_id = a.address_id 
                                                LEFT JOIN records r ON p.person_id = r.person_id 
                                                LEFT JOIN users u ON r.user_id = u.user_id 
                                                WHERE p.gender = 'F' 
                                                AND p.birthdate BETWEEN ? AND ? 
                                                AND a.purok = ?
                                            ");
                                            $stmt->execute([$min_birthdate->format('Y-m-d'), $max_birthdate->format('Y-m-d'), $user_purok]);
                                        } else {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name, p.birthdate 
                                                FROM person p 
                                                LEFT JOIN records r ON p.person_id = r.person_id 
                                                LEFT JOIN users u ON r.user_id = u.user_id 
                                                WHERE p.gender = 'F' 
                                                AND p.birthdate BETWEEN ? AND ? 
                                            ");
                                            $stmt->execute([$min_birthdate->format('Y-m-d'), $max_birthdate->format('Y-m-d')]);
                                        }

                                        $seen = [];
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            if (!isset($seen[$row['person_id']])) {
                                                echo "<option value='{$row['person_id']}'>" . htmlspecialchars($row['full_name']) . "</option>";
                                                $seen[$row['person_id']] = true;
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Person Select Error: " . $e->getMessage());
                                        echo "<option value=''>Error loading mothers</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="delivery_date">Delivery Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" required>
                                <div class="invalid-feedback" id="delivery_date_error"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="delivery_location">Delivery Location <span class="text-danger">*</span></label>
                                <select class="form-control" id="delivery_location" name="delivery_location" required>
                                    <option value="">Select Location</option>
                                    <option value="Center">Center</option>
                                    <option value="Hospital">Hospital</option>
                                    <option value="Bahay">Bahay</option>
                                    <option value="Others">Others</option>
                                </select>
                                <div class="other-field" id="other_delivery_location_field">
                                    <input type="text" class="form-control" id="other_delivery_location" name="other_delivery_location" placeholder="Specify other location">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="attendant">Attendant <span class="text-danger">*</span></label>
                                <select class="form-control" id="attendant" name="attendant" required>
                                    <option value="">Select Attendant</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Nurse">Nurse</option>
                                    <option value="Midwife">Midwife</option>
                                    <option value="Hilot">Hilot</option>
                                    <option value="Others">Others</option>
                                </select>
                                <div class="other-field" id="other_attendant_field">
                                    <input type="text" class="form-control" id="other_attendant" name="other_attendant" placeholder="Specify other attendant">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Risks Observed</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="risks[]" id="risk_none" value="None">
                                        <label for="risk_none">None</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="risks[]" id="risk_bleeding" value="Bleeding" class="risk-checkbox">
                                        <label for="risk_bleeding">Bleeding</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="risks[]" id="risk_fever" value="Fever" class="risk-checkbox">
                                        <label for="risk_fever">Fever</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="risks[]" id="risk_hypertension" value="Hypertension" class="risk-checkbox">
                                        <label for="risk_hypertension">Hypertension</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="risks[]" id="risk_convulsion" value="Convulsion" class="risk-checkbox">
                                        <label for="risk_convulsion">Convulsion</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Checkups</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="checkups[]" id="check_no" value="No Checkup">
                                        <label for="check_no">No Checkup</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="checkups[]" id="check_24" value="First 24 Hours">
                                        <label for="check_24">First 24 Hours</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="checkups[]" id="check_72" value="First 72 Hours">
                                        <label for="check_72">First 72 Hours</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="checkups[]" id="check_7" value="First 7 Days">
                                        <label for="check_7">First 7 Days</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Supplements</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="supplements[]" id="supp_none" value="None">
                                        <label for="supp_none">None</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="supplements[]" id="supp_ferrous" value="Ferrous Sulfate with Folic Acid">
                                        <label for="supp_ferrous">Ferrous Sulfate with Folic Acid</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="supplements[]" id="supp_vita" value="Vitamin A">
                                        <label for="supp_vita">Vitamin A</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="service_source">Service Source <span class="text-danger">*</span></label>
                                <select class="form-control" id="service_source" name="service_source" required>
                                    <option value="">Select Service Source</option>
                                    <option value="Health Center">Health Center</option>
                                    <option value="Barangay Health Station">Barangay Health Station</option>
                                    <option value="Private Clinic">Private Clinic</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="family_planning_intent">Family Planning Intent <span class="text-danger">*</span></label>
                                <select class="form-control" id="family_planning_intent" name="family_planning_intent" required>
                                    <option value="">Select Intent</option>
                                    <option value="Y">Yes</option>
                                    <option value="N">No</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="submit_btn">
                                <i class="fas fa-save"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            let currentPersonId = null;
            
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Search for Mother",
                allowClear: true
            });

            // Auto-fill form on person selection
            $('#person_id').on('change', function() {
                $('#error-message').empty();
                const personId = $(this).val();
                currentPersonId = personId;
                
                if (!personId) {
                    $('#mother_info_card, #delivery_timeline, #checkup_recommendations, #risk_warnings').slideUp();
                    return;
                }

                // Fetch mother details
                $.ajax({
                    url: 'postnatal_form.php',
                    type: 'POST',
                    data: { ajax: 'get_mother_details', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#mother_info_card').slideDown();
                            $('#mother_name').text(response.data.full_name);
                            $('#mother_age').text(response.data.age);
                            $('#mother_purok').text(response.data.purok);
                            $('#pregnancy_count').text(response.data.pregnancy_count);
                        }
                    }
                });

                // Fetch postnatal data
                $.ajax({
                    url: 'postnatal_form.php',
                    type: 'POST',
                    data: { ajax: 'get_postnatal_data', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#delivery_date').val(response.data.date_delivered);
                            $('#delivery_location').val(response.data.delivery_location).trigger('change');
                            $('#other_delivery_location').val(response.data.other_delivery_location);
                            $('#attendant').val(response.data.attendant).trigger('change');
                            $('#other_attendant').val(response.data.other_attendant);
                            $('#service_source').val(response.data.service_source);
                            $('#family_planning_intent').val(response.data.family_planning_intent);

                            // Handle checkboxes
                            $('input[name="risks[]"]').prop('checked', false);
                            response.data.risks.forEach(risk => {
                                $(`input[value="${risk}"][name="risks[]"]`).prop('checked', true);
                            });

                            $('input[name="checkups[]"]').prop('checked', false);
                            response.data.checkups.forEach(checkup => {
                                $(`input[value="${checkup}"][name="checkups[]"]`).prop('checked', true);
                            });

                            $('input[name="supplements[]"]').prop('checked', false);
                            response.data.supplements.forEach(supplement => {
                                $(`input[value="${supplement}"][name="supplements[]"]`).prop('checked', true);
                            });
                            
                            // Validate delivery date if exists
                            if (response.data.date_delivered) {
                                validateDeliveryDate();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.warn('No existing postnatal data found; form ready for new submission.');
                    }
                });
            });

            // Validate delivery date
            function validateDeliveryDate() {
                const deliveryDate = $('#delivery_date').val();
                
                if (!deliveryDate) return;
                
                $.ajax({
                    url: 'postnatal_form.php',
                    type: 'POST',
                    data: { ajax: 'validate_delivery_date', delivery_date: deliveryDate },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.warnings.length > 0) {
                                let html = '<div class="alert alert-warning alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-triangle"></i> Delivery Date Notice:</strong><ul class="mb-0 mt-2">';
                                response.warnings.forEach(warning => {
                                    html += `<li>${warning}</li>`;
                                });
                                html += '</ul></div>';
                                $('#delivery_timeline').html(html).slideDown();
                                
                                if (!response.is_valid) {
                                    $('#delivery_date').addClass('is-invalid');
                                    $('#delivery_date_error').text('Please select a valid delivery date');
                                } else {
                                    $('#delivery_date').removeClass('is-invalid').addClass('is-valid');
                                }
                            } else {
                                $('#delivery_timeline').slideUp().empty();
                                $('#delivery_date').removeClass('is-invalid').addClass('is-valid');
                            }
                        }
                    }
                });
                
                // Calculate days since delivery and show recommendations
                $.ajax({
                    url: 'postnatal_form.php',
                    type: 'POST',
                    data: { ajax: 'calculate_days_since_delivery', delivery_date: deliveryDate },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.recommendations.length > 0) {
                            let html = '<div class="alert alert-info alert-dismissible fade show">';
                            html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                            html += `<strong><i class="fas fa-calendar-check"></i> ${response.days_since} days since delivery</strong><br>`;
                            html += '<small>Recommended checkups:</small><ul class="mb-0 mt-1">';
                            response.recommendations.forEach(rec => {
                                html += `<li>${rec}</li>`;
                            });
                            html += '</ul></div>';
                            $('#checkup_recommendations').html(html).slideDown();
                        }
                    }
                });
            }

            $('#delivery_date').on('change', validateDeliveryDate);

            // Check for high-risk conditions
            $('.risk-checkbox').on('change', function() {
                const selectedRisks = [];
                $('.risk-checkbox:checked').each(function() {
                    selectedRisks.push($(this).val());
                });
                
                if (selectedRisks.length > 0) {
                    $.ajax({
                        url: 'postnatal_form.php',
                        type: 'POST',
                        data: { ajax: 'check_risks', risks: selectedRisks },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.has_critical) {
                                let html = '<div class="alert alert-danger alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-circle"></i> CRITICAL RISK DETECTED!</strong><br>';
                                html += '<p class="mb-2">The following conditions require immediate medical attention:</p>';
                                html += '<ul class="mb-0">';
                                response.critical_risks.forEach(risk => {
                                    html += `<li><strong>${risk}</strong></li>`;
                                });
                                html += '</ul>';
                                html += '<p class="mb-0 mt-2"><strong>Action Required:</strong> Refer to hospital immediately</p>';
                                html += '</div>';
                                $('#risk_warnings').html(html).slideDown();
                            } else {
                                $('#risk_warnings').slideUp().empty();
                            }
                        }
                    });
                } else {
                    $('#risk_warnings').slideUp().empty();
                }
            });

            // Toggle delivery location "Others" field
            $('#delivery_location').on('change', function() {
                if ($(this).val() === 'Others') {
                    $('#other_delivery_location_field').slideDown();
                } else {
                    $('#other_delivery_location_field').slideUp();
                    $('#other_delivery_location').val('');
                }
            });

            // Toggle attendant "Others" field
            $('#attendant').on('change', function() {
                if ($(this).val() === 'Others') {
                    $('#other_attendant_field').slideDown();
                } else {
                    $('#other_attendant_field').slideUp();
                    $('#other_attendant').val('');
                }
            });

            // Handle "None" selection for risks
            $('input[name="risks[]"]').on('change', function() {
                if ($(this).val() === 'None' && $(this).is(':checked')) {
                    $('input[name="risks[]"]').not(this).prop('checked', false);
                    $('#risk_warnings').slideUp().empty();
                } else if ($('input[name="risks[]"][value="None"]').is(':checked')) {
                    $('input[name="risks[]"][value="None"]').prop('checked', false);
                }
            });

            // Handle "No Checkup" selection
            $('input[name="checkups[]"]').on('change', function() {
                if ($(this).val() === 'No Checkup' && $(this).is(':checked')) {
                    $('input[name="checkups[]"]').not(this).prop('checked', false);
                } else if ($('input[name="checkups[]"][value="No Checkup"]').is(':checked')) {
                    $('input[name="checkups[]"][value="No Checkup"]').prop('checked', false);
                }
            });

            // Handle "None" selection for supplements
            $('input[name="supplements[]"]').on('change', function() {
                if ($(this).val() === 'None' && $(this).is(':checked')) {
                    $('input[name="supplements[]"]').not(this).prop('checked', false);
                } else if ($('input[name="supplements[]"][value="None"]').is(':checked')) {
                    $('input[name="supplements[]"][value="None"]').prop('checked', false);
                }
            });

            // Handle form submission
            $('#postnatalForm').on('submit', function(e) {
                e.preventDefault();
                
                if ($('.is-invalid').length > 0) {
                    $('#error-message').removeClass('text-success').addClass('text-danger').text('Please fix validation errors before submitting.');
                    return false;
                }
                
                $('#error-message').empty();
                $('#submit_btn').prop('disabled', true).html('<span class="loading-spinner"></span>Submitting...');
                
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#error-message').removeClass('text-danger').addClass('text-success').text(response.message || 'Form submitted successfully!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            $('#error-message').removeClass('text-success').addClass('text-danger').text(response.error || 'Unknown error occurred.');
                            $('#submit_btn').prop('disabled', false).html('<i class="fas fa-save"></i> Submit');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        let errorMsg = error;
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMsg = response.error;
                            }
                        } catch (e) {}
                        $('#error-message').removeClass('text-success').addClass('text-danger').text('Error submitting form: ' + errorMsg);
                        $('#submit_btn').prop('disabled', false).html('<i class="fas fa-save"></i> Submit');
                    }
                });
            });

            // Sidebar toggle
            $('.menu-toggle').on('click', toggleSidebar);

            function toggleSidebar() {
                const sidebar = $('.sidebar');
                const content = $('.content');
                sidebar.toggleClass('open');
                if (sidebar.hasClass('open')) {
                    content.addClass('with-sidebar');
                    if (window.innerWidth <= 768) {
                        $('<div class="sidebar-overlay"></div>').appendTo('body').on('click', function() {
                            sidebar.removeClass('open');
                            content.removeClass('with-sidebar');
                            $(this).remove();
                        }).addClass('active');
                    }
                } else {
                    content.removeClass('with-sidebar');
                    $('.sidebar-overlay').remove();
                }
                if (window.innerWidth > 768) {
                    content.css('margin-left', sidebar.hasClass('open') ? '250px' : '0');
                } else {
                    content.css('margin-left', '0');
                }
            }
            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });

            // Date input accessibility
            $('input[type="date"]').on('click', function() {
                $(this).focus();
                if (this.showPicker) {
                    this.showPicker();
                }
            }).on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.showPicker ? this.showPicker() : this.click();
                }
            });
        });
    </script>
</body>
</html>
