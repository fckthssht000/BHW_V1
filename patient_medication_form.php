<?php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    ob_start();
    
    // Get senior medication data
    if ($_POST['ajax'] == 'get_senior_medication_data') {
        $bp_reading = '';
        $bp_date_taken = '';
        $medications = [];
        try {
            $stmt = $pdo->prepare("
                SELECT sr.bp_reading, sr.bp_date_taken,
                       GROUP_CONCAT(m.medication_name) as medications
                FROM records r 
                JOIN senior_record sr ON r.records_id = sr.records_id
                LEFT JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
                LEFT JOIN medication m ON sm.medication_id = m.medication_id
                WHERE r.person_id = ? AND r.record_type = 'senior_record.medication'
                GROUP BY sr.senior_record_id 
                ORDER BY r.created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $bp_reading = $data['bp_reading'] ?? '';
                $bp_date_taken = $data['bp_date_taken'] ?? '';
                $medications = !empty($data['medications']) ? explode(',', $data['medications']) : [];
            }
        } catch (Exception $e) {
            error_log("getSeniorMedicationData Error: " . $e->getMessage());
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'bp_reading' => $bp_reading,
                'bp_date_taken' => $bp_date_taken,
                'medications' => $medications
            ]
        ]);
        exit;
    }
    
    // Get senior details
    if ($_POST['ajax'] == 'get_senior_details') {
        try {
            $stmt = $pdo->prepare("
                SELECT p.full_name, p.age, p.gender, a.purok, p.health_condition
                FROM person p
                LEFT JOIN address a ON p.address_id = a.address_id
                WHERE p.person_id = ?
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Validate blood pressure reading
    if ($_POST['ajax'] == 'validate_bp') {
        try {
            $bp_reading = $_POST['bp_reading'];
            preg_match('/(\d+)\/(\d+)/', $bp_reading, $matches);
            
            $warnings = [];
            $status = 'normal';
            
            if (count($matches) >= 3) {
                $systolic = intval($matches[1]);
                $diastolic = intval($matches[2]);
                
                if ($systolic >= 180 || $diastolic >= 120) {
                    $status = 'critical';
                    $warnings[] = 'CRITICAL: Hypertensive Crisis - Seek immediate medical attention!';
                } elseif ($systolic >= 140 || $diastolic >= 90) {
                    $status = 'high';
                    $warnings[] = 'High Blood Pressure (Stage 2 Hypertension) - Consult doctor';
                } elseif ($systolic >= 130 || $diastolic >= 80) {
                    $status = 'elevated';
                    $warnings[] = 'Elevated Blood Pressure (Stage 1 Hypertension)';
                } elseif ($systolic < 90 || $diastolic < 60) {
                    $status = 'low';
                    $warnings[] = 'Low Blood Pressure (Hypotension) - Monitor for dizziness';
                }
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'warnings' => $warnings, 'status' => $status]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Check medication interactions
    if ($_POST['ajax'] == 'check_interactions') {
        try {
            $medications = $_POST['medications'];
            $interactions = [];
            
            // Common interaction checks for elderly medications
            if (in_array('Amlodipine 5mg', $medications) && in_array('Simvastatin 20mg', $medications)) {
                $interactions[] = [
                    'severity' => 'moderate',
                    'drugs' => 'Amlodipine + Simvastatin',
                    'warning' => 'May increase risk of muscle pain or weakness'
                ];
            }
            
            if (in_array('Metformin 500mg', $medications) && in_array('Losartan 100mg', $medications)) {
                $interactions[] = [
                    'severity' => 'minor',
                    'drugs' => 'Metformin + Losartan',
                    'warning' => 'Monitor kidney function regularly'
                ];
            }
            
            if (in_array('Metoprolol 50mg', $medications) && in_array('Gliclazide 30mg', $medications)) {
                $interactions[] = [
                    'severity' => 'moderate',
                    'drugs' => 'Metoprolol + Gliclazide',
                    'warning' => 'May mask symptoms of low blood sugar'
                ];
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'interactions' => $interactions]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Get medication history count
    if ($_POST['ajax'] == 'get_medication_history') {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_visits,
                       MAX(sr.bp_date_taken) as last_visit
                FROM records r
                JOIN senior_record sr ON r.records_id = sr.records_id
                WHERE r.person_id = ? AND r.record_type = 'senior_record.medication'
            ");
            $stmt->execute([$_POST['person_id']]);
            $history = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'history' => $history]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// Fetch user role and purok
try {
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role_id = $stmt->fetchColumn();
    if ($role_id === false) {
        die("Error: User not found for user_id: " . $_SESSION['user_id']);
    }

    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM users u 
        JOIN records r ON u.user_id = r.user_id 
        JOIN person p ON r.person_id = p.person_id 
        JOIN address a ON p.address_id = a.address_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
    if ($user_purok === false) {
        die("Error: Unable to fetch user's purok.");
    }
} catch (PDOException $e) {
    error_log("User/Purok Fetch Error: " . $e->getMessage());
    die("Database error: Unable to fetch user information.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $person_id = $_POST['person_id'];
    $medication_names = $_POST['medication_name'] ?? [];
    $bp_reading = $_POST['bp_reading'];
    $bp_date_taken = $_POST['bp_date_taken'];

    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $senior_purok = $stmt->fetchColumn();
        if ($senior_purok !== $user_purok) {
            die("Error: BHW Staff can only update records for their assigned purok ($user_purok).");
        }
    }

    try {
        $pdo->beginTransaction();

        $medication_ids = [];
        foreach ($medication_names as $medication_name) {
            if (empty(trim($medication_name))) continue;
            
            $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
            $stmt->execute([$medication_name]);
            $existing_id = $stmt->fetchColumn();
            if ($existing_id) {
                $medication_ids[] = $existing_id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                $stmt->execute([$medication_name]);
                $medication_ids[] = $pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $stmt = $pdo->prepare("
                SELECT user_id 
                FROM users 
                WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)
            ");
            $stmt->execute([$person_id]);
            $selected_user_id = $stmt->fetchColumn();
            if ($selected_user_id === false) {
                throw new Exception("No user_id found for person_id: " . $person_id);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$selected_user_id, $person_id, 'senior_record.medication', $_SESSION['user_id']]);
        $records_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO senior_record (records_id, bp_reading, bp_date_taken) VALUES (?, ?, ?)");
        $stmt->execute([$records_id, $bp_reading, $bp_date_taken]);
        $senior_record_id = $pdo->lastInsertId();

        foreach ($medication_ids as $medication_id) {
            $stmt = $pdo->prepare("INSERT INTO senior_medication (senior_record_id, medication_id) VALUES (?, ?)");
            $stmt->execute([$senior_record_id, $medication_id]);
        }

        $pdo->commit();
        header("Location: patient_medication_form.php?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Form Submission Error: " . $e->getMessage());
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Senior Medication Form</title>
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
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            align-items: start;
        }
        .checkbox-group .checkbox-item {
            display: flex;
            align-items: center;
            margin: 0;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
            flex-shrink: 0;
        }
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            display: inline-block;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
        }
        .others-textbox {
            display: none;
            margin-top: 10px;
        }
        .others-textbox.active {
            display: block;
        }
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
        #senior_info_card, #bp_warnings, #interaction_warnings, #history_card {
            display: none;
            margin-bottom: 15px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .bp-status {
            padding: 10px;
            border-radius: 8px;
            margin-top: 5px;
            font-weight: 500;
        }
        .bp-critical { background: #fee2e2; color: #991b1b; }
        .bp-high { background: #fed7aa; color: #9a3412; }
        .bp-elevated { background: #fef3c7; color: #92400e; }
        .bp-normal { background: #d1fae5; color: #065f46; }
        .bp-low { background: #dbeafe; color: #1e40af; }
        .interaction-moderate { border-left: 4px solid #f59e0b; }
        .interaction-minor { border-left: 4px solid #3b82f6; }
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
            .checkbox-group {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .checkbox-group input[type="checkbox"] {
                transform: scale(1.1);
            }
            .form-group {
                margin-bottom: 1rem;
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
                    <div class="card-header">Senior Medication Form</div>
                    <div class="card-body p-4">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <i class="fas fa-check-circle"></i> Medication record saved successfully!
                            </div>
                        <?php endif; ?>
                        
                        <div id="error-message" class="error-message"></div>
                        
                        <!-- Senior Info Card -->
                        <div id="senior_info_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user-md"></i> Senior Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="senior_name">-</span></p>
                                <p class="mb-1"><strong>Age:</strong> <span id="senior_age">-</span> years old</p>
                                <p class="mb-0"><strong>Purok:</strong> <span id="senior_purok">-</span></p>
                            </div>
                        </div>

                        <!-- History Card -->
                        <div id="history_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-history"></i> Medication History</h6>
                                <p class="mb-1"><strong>Total Visits:</strong> <span id="total_visits">0</span></p>
                                <p class="mb-0"><strong>Last Visit:</strong> <span id="last_visit">Never</span></p>
                            </div>
                        </div>

                        <!-- BP Warnings -->
                        <div id="bp_warnings"></div>

                        <!-- Interaction Warnings -->
                        <div id="interaction_warnings"></div>

                        <form action="patient_medication_form.php" method="POST" id="medicationForm">
                            <div class="form-group">
                                <label for="person_id">Select Senior <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="person_id" name="person_id" required>
                                    <option value="">Search and Select Senior...</option>
                                    <?php
                                    try {
                                        if ($role_id == 1 || $role_id == 4) {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name 
                                                FROM person p 
                                                WHERE p.age >= 60
                                            ");
                                            $stmt->execute();
                                        } else {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name 
                                                FROM person p 
                                                JOIN address a ON p.address_id = a.address_id 
                                                WHERE p.age >= 60 
                                                AND a.purok = ?
                                            ");
                                            $stmt->execute([$user_purok]);
                                        }
                                        $seen = [];
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $stmt_check = $pdo->prepare("
                                                SELECT u.role_id 
                                                FROM records r 
                                                LEFT JOIN users u ON r.user_id = u.user_id 
                                                WHERE r.person_id = ? LIMIT 1
                                            ");
                                            $stmt_check->execute([$row['person_id']]);
                                            $person_role_id = $stmt_check->fetchColumn();
                                            if (($person_role_id === false || !in_array($person_role_id, [1, 2, 4])) && !isset($seen[$row['person_id']])) {
                                                echo "<option value='{$row['person_id']}'>" . htmlspecialchars($row['full_name']) . "</option>";
                                                $seen[$row['person_id']] = true;
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Senior Select Error: " . $e->getMessage());
                                        echo "<option value=''>Error loading seniors</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Medication Name</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Amlodipine 5mg" id="med1" class="med-checkbox">
                                        <label for="med1">Amlodipine 5mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Amlodipine 10mg" id="med2" class="med-checkbox">
                                        <label for="med2">Amlodipine 10mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Losartan 100mg" id="med3" class="med-checkbox">
                                        <label for="med3">Losartan 100mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Metoprolol 50mg" id="med4" class="med-checkbox">
                                        <label for="med4">Metoprolol 50mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Carvidolol 12.5mg" id="med5" class="med-checkbox">
                                        <label for="med5">Carvidolol 12.5mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Simvastatin 20mg" id="med6" class="med-checkbox">
                                        <label for="med6">Simvastatin 20mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Metformin 500mg" id="med7" class="med-checkbox">
                                        <label for="med7">Metformin 500mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="medication_name[]" value="Gliclazide 30mg" id="med8" class="med-checkbox">
                                        <label for="med8">Gliclazide 30mg</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="med_others" value="Others">
                                        <label for="med_others">Others</label>
                                    </div>
                                </div>
                                <div class="others-textbox" id="others_textbox">
                                    <input type="text" class="form-control" name="medication_name[]" id="others_medication" placeholder="Enter other medication name">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bp_reading">Blood Pressure <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="bp_reading" name="bp_reading" required placeholder="e.g., 120/80">
                                <small class="form-text text-muted">Enter in format: Systolic/Diastolic (e.g., 120/80)</small>
                                <div class="invalid-feedback" id="bp_error"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bp_date_taken">Date Taken <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="bp_date_taken" name="bp_date_taken" required>
                                <div class="invalid-feedback" id="date_error"></div>
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
                placeholder: "Search and Select Senior...",
                allowClear: true
            });

            // Toggle Others textbox visibility
            $('#med_others').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#others_textbox').addClass('active');
                    $('#others_medication').prop('disabled', false);
                } else {
                    $('#others_textbox').removeClass('active');
                    $('#others_medication').prop('disabled', true).val('');
                }
            });

            // Auto-fill form on person selection
            $('#person_id').on('change', function() {
                $('#error-message').empty();
                const personId = $(this).val();
                currentPersonId = personId;
                
                if (!personId) {
                    $('#senior_info_card, #history_card, #bp_warnings, #interaction_warnings').slideUp();
                    return;
                }

                // Fetch senior details
                $.ajax({
                    url: 'patient_medication_form.php',
                    type: 'POST',
                    data: { ajax: 'get_senior_details', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#senior_info_card').slideDown();
                            $('#senior_name').text(response.data.full_name);
                            $('#senior_age').text(response.data.age);
                            $('#senior_purok').text(response.data.purok);
                        }
                    }
                });

                // Fetch medication history
                $.ajax({
                    url: 'patient_medication_form.php',
                    type: 'POST',
                    data: { ajax: 'get_medication_history', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#history_card').slideDown();
                            $('#total_visits').text(response.history.total_visits);
                            $('#last_visit').text(response.history.last_visit || 'Never');
                        }
                    }
                });

                // Fetch senior medication data
                $.ajax({
                    url: 'patient_medication_form.php',
                    type: 'POST',
                    data: { ajax: 'get_senior_medication_data', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#bp_reading').val(response.data.bp_reading);
                            $('#bp_date_taken').val(response.data.bp_date_taken);

                            // Handle medications checkboxes
                            $('input[name="medication_name[]"]').prop('checked', false);
                            $('#others_textbox').removeClass('active');
                            $('#others_medication').prop('disabled', true).val('');
                            
                            response.data.medications.forEach(med => {
                                const checkbox = $(`.med-checkbox[value="${med}"]`);
                                if (checkbox.length) {
                                    checkbox.prop('checked', true);
                                } else {
                                    $('#med_others').prop('checked', true);
                                    $('#others_textbox').addClass('active');
                                    $('#others_medication').prop('disabled', false).val(med);
                                }
                            });
                            
                            // Trigger BP validation if value exists
                            if (response.data.bp_reading) {
                                validateBP();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                    }
                });
            });

            // Validate blood pressure
            function validateBP() {
                const bpReading = $('#bp_reading').val();
                
                $.ajax({
                    url: 'patient_medication_form.php',
                    type: 'POST',
                    data: { ajax: 'validate_bp', bp_reading: bpReading },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.warnings.length > 0) {
                                let html = '<div class="alert alert-' + (response.status === 'critical' ? 'danger' : 'warning') + ' alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-heartbeat"></i> Blood Pressure Alert:</strong><ul class="mb-0 mt-2">';
                                response.warnings.forEach(warning => {
                                    html += `<li>${warning}</li>`;
                                });
                                html += '</ul></div>';
                                
                                html += `<div class="bp-status bp-${response.status}">`;
                                html += `<i class="fas fa-info-circle"></i> Current Status: <strong>${response.status.toUpperCase()}</strong>`;
                                html += '</div>';
                                
                                $('#bp_warnings').html(html).slideDown();
                                
                                if (response.status === 'critical' || response.status === 'high') {
                                    $('#bp_reading').addClass('is-invalid').removeClass('is-valid');
                                } else if (response.status === 'low') {
                                    $('#bp_reading').addClass('is-invalid').removeClass('is-valid');
                                } else {
                                    $('#bp_reading').removeClass('is-invalid').addClass('is-valid');
                                }
                            } else {
                                $('#bp_warnings').slideUp().empty();
                                $('#bp_reading').removeClass('is-invalid').addClass('is-valid');
                            }
                        }
                    }
                });
            }

            $('#bp_reading').on('blur', validateBP);

            // Check medication interactions
            $('.med-checkbox, #med_others').on('change', function() {
                const selectedMeds = [];
                $('.med-checkbox:checked').each(function() {
                    selectedMeds.push($(this).val());
                });
                
                if ($('#med_others').is(':checked') && $('#others_medication').val()) {
                    selectedMeds.push($('#others_medication').val());
                }
                
                if (selectedMeds.length >= 2) {
                    $.ajax({
                        url: 'patient_medication_form.php',
                        type: 'POST',
                        data: { ajax: 'check_interactions', medications: selectedMeds },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.interactions.length > 0) {
                                let html = '<div class="alert alert-warning alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-triangle"></i> Potential Drug Interactions:</strong>';
                                html += '<div class="mt-2">';
                                response.interactions.forEach(interaction => {
                                    html += `<div class="interaction-${interaction.severity} p-2 mb-2 bg-white rounded">`;
                                    html += `<strong>${interaction.drugs}</strong><br>`;
                                    html += `<small class="text-muted">${interaction.severity.toUpperCase()}</small><br>`;
                                    html += `${interaction.warning}`;
                                    html += '</div>';
                                });
                                html += '</div></div>';
                                $('#interaction_warnings').html(html).slideDown();
                            } else {
                                $('#interaction_warnings').slideUp().empty();
                            }
                        }
                    });
                } else {
                    $('#interaction_warnings').slideUp().empty();
                }
            });

            // Validate date (cannot be future)
            $('#bp_date_taken').on('change', function() {
                const selectedDate = new Date($(this).val());
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate > today) {
                    $(this).addClass('is-invalid');
                    $('#date_error').text('Date taken cannot be in the future');
                } else {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });

            // Form submission with loading state
            $('#medicationForm').on('submit', function(e) {
                if ($('.is-invalid').length > 0) {
                    e.preventDefault();
                    alert('Please fix validation errors before submitting.');
                    return false;
                }
                
                // Ensure "Others" checkbox is checked if text is entered
                if ($('#others_medication').val().trim() !== '' && !$('#med_others').is(':checked')) {
                    $('#med_others').prop('checked', true);
                }
                
                $('#submit_btn').prop('disabled', true).html('<span class="loading-spinner"></span>Submitting...');
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
            // Initialize accordion (if any)
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
