<?php
session_start();
require_once 'db_connect.php';

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Get child details
    if ($_POST['ajax'] == 'get_child_details') {
        $person_id = $_POST['person_id'];
        
        $stmt = $pdo->prepare("
            SELECT p.full_name, p.age, a.purok, cr.weight, cr.height, 
                   cr.measurement_date, cr.risk_observed, cr.service_source, 
                   cr.immunization_status, cr.child_type
            FROM person p
            LEFT JOIN address a ON p.address_id = a.address_id
            LEFT JOIN records r ON p.person_id = r.person_id
            LEFT JOIN child_record cr ON r.records_id = cr.records_id
            WHERE p.person_id = ? AND r.record_type = 'child_record'
            ORDER BY cr.measurement_date DESC LIMIT 1
        ");
        $stmt->execute([$person_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    // Calculate BMI
    if ($_POST['ajax'] == 'calculate_bmi') {
        $weight = floatval($_POST['weight']);
        $height = floatval($_POST['height']) / 100; // cm to m
        
        if ($weight > 0 && $height > 0) {
            $bmi = $weight / ($height * $height);
            $status = '';
            $class = '';
            
            if ($bmi < 15) {
                $status = 'Severely Underweight';
                $class = 'danger';
            } elseif ($bmi >= 15 && $bmi < 16.5) {
                $status = 'Underweight';
                $class = 'warning';
            } elseif ($bmi >= 16.5 && $bmi < 18.5) {
                $status = 'Normal Weight';
                $class = 'success';
            } elseif ($bmi >= 18.5 && $bmi < 25) {
                $status = 'Overweight';
                $class = 'warning';
            } else {
                $status = 'Obese';
                $class = 'danger';
            }
            
            echo json_encode([
                'success' => true,
                'bmi' => number_format($bmi, 2),
                'status' => $status,
                'class' => $class
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Get immunization suggestions based on age
    if ($_POST['ajax'] == 'get_immunization_suggestions') {
        $person_id = $_POST['person_id'];
        
        $stmt = $pdo->prepare("SELECT age FROM person WHERE person_id = ?");
        $stmt->execute([$person_id]);
        $age = $stmt->fetchColumn();
        
        $suggestions = [];
        if ($age >= 1 && $age <= 2) {
            $suggestions = ['MMR (12-15 Months)', 'Vitamin A (12-59 Months)'];
        } elseif ($age >= 2 && $age <= 4) {
            $suggestions = ['Vitamin A (12-59 Months)', 'Fully Immunized (FIC)'];
        } elseif ($age >= 5 && $age <= 6) {
            $suggestions = ['Completely Immunized (CIC)', 'Vitamin A (12-59 Months)'];
        }
        
        echo json_encode(['success' => true, 'suggestions' => $suggestions, 'age' => $age]);
        exit;
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role and purok
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT a.purok FROM users u JOIN records r ON u.user_id = r.user_id JOIN person p ON r.person_id = p.person_id JOIN address a ON p.address_id = a.address_id WHERE u.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_purok = $stmt->fetchColumn();
if ($user_purok === false) {
    die("Error: Unable to fetch user's purok.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $person_id = $_POST['person_id'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $measurement_date = $_POST['measurement_date'];
    $risks = implode(',', $_POST['risks'] ?? []);
    if (in_array('Others', $_POST['risks'] ?? []) && !empty($_POST['other_risk'])) {
        $risks .= ',' . $_POST['other_risk'];
    }
    $service_source = null;
    $immunization_status = implode(',', $_POST['immunization_status'] ?? []);
    $child_type = 'Child';

    // Validate purok for BHW Staff
    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $child_purok = $stmt->fetchColumn();
        if ($child_purok !== $user_purok) {
            die("Error: BHW Staff can only update records for their assigned purok ($user_purok).");
        }
    }

    // Fetch the user_id associated with the selected person_id
    $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
    $stmt->execute([$person_id]);
    $selected_user_id = $stmt->fetchColumn();
    if ($selected_user_id === false) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            die("Error: No user_id found for person_id: " . $person_id);
        }
    }

    // Check if a child_record already exists for this person
    $stmt = $pdo->prepare("SELECT r.records_id, cr.service_source FROM records r JOIN child_record cr ON r.records_id = cr.records_id WHERE r.person_id = ? AND r.record_type = 'child_record'");
    $stmt->execute([$person_id]);
    $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_record && $existing_record['service_source']) {
        $service_source = $existing_record['service_source'];
    } elseif (isset($_POST['service_source']) && !empty($_POST['service_source'])) {
        $service_source = $_POST['service_source'];
    } else {
        die("Error: Service source is required. Please select a service source or ensure an existing record has one.");
    }

    try {
        if ($existing_record) {
            $records_id = $existing_record['records_id'];
            $stmt = $pdo->prepare("UPDATE child_record SET weight = ?, height = ?, measurement_date = ?, risk_observed = ?, service_source = ?, immunization_status = ?, child_type = ? WHERE records_id = ?");
            $stmt->execute([$weight, $height, $measurement_date, $risks, $service_source, $immunization_status, $child_type, $records_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$selected_user_id, $person_id, 'child_record', $_SESSION['user_id']]);
            $records_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO child_record (records_id, weight, height, measurement_date, risk_observed, service_source, immunization_status, child_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$records_id, $weight, $height, $measurement_date, $risks, $service_source, $immunization_status, $child_type]);
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
    header("Location: child_health_form.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Child Health Form</title>
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
        }
        .form-control[type="date"]:focus {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%233b82f6' stroke-width='2'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/%3e%3c/svg%3e");
        }
        .form-control[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 40px;              /* clickable area on the right side */
            height: auto;
            cursor: pointer;
            opacity: 0;               /* hide default icon but keep it clickable */
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
        .form-check {
            margin-bottom: 0.75rem;
            flex: 1 1 45%;
            min-width: 150px;
        }
        .form-check-input {
            margin-top: 0.2rem;
            transform: scale(1.1);
        }
        .form-check-label {
            font-size: 0.95rem;
            color: #2d3748;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .error-message, .invalid-feedback {
            color: #dc3545;
            margin-top: 5px;
            font-size: 0.875rem;
        }
        .valid-feedback {
            color: #28a745;
            margin-top: 5px;
            font-size: 0.875rem;
        }
        .other-risk {
            display: none;
            margin-top: 10px;
        }
        .show { display: block !important; }
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
        #bmi_result {
            margin-top: 10px;
        }
        #child_info_card {
            display: none;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        #immunization_suggestions {
            margin-top: 10px;
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
            .form-check {
                flex: 1 1 45%;
                min-width: 120px;
                margin-bottom: 0.75rem;
            }
            .checkbox-group {
                gap: 0.75rem;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .form-check-label {
                font-size: 0.9rem;
                word-wrap: break-word;
            }
            .form-check-input {
                transform: scale(1.0);
            }
            .navbar-brand {
                padding-left: 55px;
            }
            .form-control[type="date"] {
                padding-right: 45px;
                background-size: 18px;
                background-position: right 10px center;
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
                    <div class="card-header">Child Health Form</div>
                    <div class="card-body p-4">
                        <!-- Child Info Card -->
                        <div id="child_info_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user"></i> Child Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="child_name">-</span></p>
                                <p class="mb-1"><strong>Age:</strong> <span id="child_age">-</span> years old</p>
                                <p class="mb-0"><strong>Purok:</strong> <span id="child_purok">-</span></p>
                            </div>
                        </div>

                        <form action="child_health_form.php" method="POST" id="childHealthForm" novalidate>
                            <div class="form-group">
                                <label for="person_id">Select Child <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="person_id" name="person_id" required>
                                    <option value="">Search and Select Child...</option>
                                    <?php
                                    try {
                                        if ($role_id == 1 || $role_id == 4) {
                                            $stmt = $pdo->prepare("SELECT p.person_id, p.full_name FROM person p WHERE p.age BETWEEN 1 AND 6");
                                            $stmt->execute();
                                        } else {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name 
                                                FROM person p 
                                                JOIN address a ON p.address_id = a.address_id 
                                                WHERE p.age BETWEEN 1 AND 6 
                                                AND a.purok = ?
                                            ");
                                            $stmt->execute([$user_purok]);
                                        }
                                        $seen = [];
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            if (!isset($seen[$row['person_id']])) {
                                                echo "<option value='{$row['person_id']}'>" . htmlspecialchars($row['full_name']) . "</option>";
                                                $seen[$row['person_id']] = true;
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Child Select Error: " . $e->getMessage());
                                        echo "<option value=''>Error loading children</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="weight">Weight (kg) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="weight" name="weight" min="5" max="50" required>
                                <div class="invalid-feedback" id="weight_error"></div>
                                <div class="valid-feedback">Weight is valid</div>
                            </div>

                            <div class="form-group">
                                <label for="height">Height (cm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="height" name="height" min="50" max="150" required>
                                <div class="invalid-feedback" id="height_error"></div>
                                <div class="valid-feedback">Height is valid</div>
                            </div>

                            <!-- BMI Result Display -->
                            <div id="bmi_result"></div>

                            <div class="form-group">
                                <label for="measurement_date">Measurement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="measurement_date" name="measurement_date" required>
                                <div class="invalid-feedback" id="date_error"></div>
                            </div>

                            <div class="form-group">
                                <label>Risks Observed</label>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_tigdas" value="Tigdas">
                                        <label class="form-check-label" for="risk_tigdas">Tigdas</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_pulmonia" value="Pneumonia">
                                        <label class="form-check-label" for="risk_pulmonia">Pneumonia</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_pagtatae" value="Diarrhea">
                                        <label class="form-check-label" for="risk_pagtatae">Diarrhea (Pagtatae)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_others" value="Others">
                                        <label class="form-check-label" for="risk_others">Others</label>
                                    </div>
                                </div>
                                <div class="other-risk">
                                    <label for="other_risk">Specify Other Risk</label>
                                    <input type="text" class="form-control" id="other_risk" name="other_risk" placeholder="Enter other risk...">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Immunization Status</label>
                                <div id="immunization_suggestions"></div>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="immunization_status[]" id="imm_mmr" value="MMR (12-15 Months)">
                                        <label class="form-check-label" for="imm_mmr">MMR (12-15 Months)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="immunization_status[]" id="imm_vita" value="Vitamin A (12-59 Months)">
                                        <label class="form-check-label" for="imm_vita">Vitamin A (12-59 Months)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="immunization_status[]" id="imm_fic" value="Fully Immunized (FIC)">
                                        <label class="form-check-label" for="imm_fic">Fully Immunized (FIC)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="immunization_status[]" id="imm_cic" value="Completely Immunized (CIC)">
                                        <label class="form-check-label" for="imm_cic">Completely Immunized (CIC)</label>
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

                            <div class="form-group" style="display: none;">
                                <label for="child_type">Child Type</label>
                                <select class="form-control" id="child_type" name="child_type" disabled>
                                    <option value="Child" selected>Child</option>
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
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Search and Select Child...",
                allowClear: true
            });

            // When child is selected, fetch their details
            $('#person_id').on('change', function() {
                const personId = $(this).val();
                
                if (personId) {
                    $.ajax({
                        url: 'child_health_form.php',
                        type: 'POST',
                        data: { 
                            ajax: 'get_child_details', 
                            person_id: personId 
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.data) {
                                // Show child info card
                                $('#child_info_card').slideDown();
                                $('#child_name').text(response.data.full_name || '-');
                                $('#child_age').text(response.data.age || '-');
                                $('#child_purok').text(response.data.purok || '-');
                                
                                // Pre-fill existing data if available
                                if (response.data.weight) {
                                    $('#weight').val(response.data.weight);
                                }
                                if (response.data.height) {
                                    $('#height').val(response.data.height);
                                }
                                if (response.data.measurement_date) {
                                    $('#measurement_date').val(response.data.measurement_date);
                                }
                                
                                // Check existing risks
                                if (response.data.risk_observed) {
                                    const risks = response.data.risk_observed.split(',');
                                    $('input[name="risks[]"]').prop('checked', false);
                                    risks.forEach(risk => {
                                        const trimmedRisk = risk.trim();
                                        $(`input[name="risks[]"][value="${trimmedRisk}"]`).prop('checked', true);
                                        
                                        // Handle "Others" case
                                        if (!['Tigdas', 'Pneumonia', 'Diarrhea'].includes(trimmedRisk)) {
                                            $('#risk_others').prop('checked', true);
                                            $('.other-risk').addClass('show');
                                            $('#other_risk').val(trimmedRisk);
                                        }
                                    });
                                }
                                
                                // Check existing immunization status
                                if (response.data.immunization_status) {
                                    const immunizations = response.data.immunization_status.split(',');
                                    $('input[name="immunization_status[]"]').prop('checked', false);
                                    immunizations.forEach(imm => {
                                        $(`input[name="immunization_status[]"][value="${imm.trim()}"]`).prop('checked', true);
                                    });
                                }
                                
                                // Pre-select service source
                                if (response.data.service_source) {
                                    $('#service_source').val(response.data.service_source);
                                }
                                
                                // Calculate BMI if both weight and height are available
                                if (response.data.weight && response.data.height) {
                                    calculateBMI();
                                }
                            }
                        },
                        error: function() {
                            console.error('Error fetching child details');
                        }
                    });
                    
                    // Get immunization suggestions
                    $.ajax({
                        url: 'child_health_form.php',
                        type: 'POST',
                        data: { 
                            ajax: 'get_immunization_suggestions', 
                            person_id: personId 
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.suggestions.length > 0) {
                                const suggestionsHtml = `
                                    <div class="alert alert-info alert-dismissible fade show">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <strong><i class="fas fa-info-circle"></i> Recommended for age ${response.age}:</strong><br>
                                        ${response.suggestions.join(', ')}
                                    </div>
                                `;
                                $('#immunization_suggestions').html(suggestionsHtml);
                            }
                        }
                    });
                } else {
                    $('#child_info_card').slideUp();
                    $('#immunization_suggestions').empty();
                }
            });

            // Real-time BMI calculation
            function calculateBMI() {
                const weight = parseFloat($('#weight').val());
                const height = parseFloat($('#height').val());
                
                if (weight > 0 && height > 0) {
                    $.ajax({
                        url: 'child_health_form.php',
                        type: 'POST',
                        data: { 
                            ajax: 'calculate_bmi', 
                            weight: weight, 
                            height: height 
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const alertHtml = `
                                    <div class="alert alert-${response.class} alert-dismissible fade show">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <strong><i class="fas fa-heartbeat"></i> BMI: ${response.bmi}</strong> - ${response.status}
                                    </div>
                                `;
                                $('#bmi_result').html(alertHtml);
                            }
                        }
                    });
                }
            }

            $('#weight, #height').on('input', function() {
                calculateBMI();
                validateInput($(this));
            });

            // Validate weight
            function validateInput($element) {
                const id = $element.attr('id');
                const val = parseFloat($element.val());
                
                if (id === 'weight') {
                    if (val < 5 || val > 50) {
                        $element.addClass('is-invalid').removeClass('is-valid');
                        $('#weight_error').text('Weight should be between 5-50 kg for children aged 1-6 years');
                    } else {
                        $element.addClass('is-valid').removeClass('is-invalid');
                    }
                }
                
                if (id === 'height') {
                    if (val < 50 || val > 150) {
                        $element.addClass('is-invalid').removeClass('is-valid');
                        $('#height_error').text('Height should be between 50-150 cm for children aged 1-6 years');
                    } else {
                        $element.addClass('is-valid').removeClass('is-invalid');
                    }
                }
            }

            // Validate date (cannot be future date)
            $('#measurement_date').on('change', function() {
                const selectedDate = new Date($(this).val());
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate > today) {
                    $(this).addClass('is-invalid');
                    $('#date_error').text('Measurement date cannot be in the future');
                } else {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });

            // Show/hide other risk text box
            $('#risk_others').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.other-risk').slideDown();
                } else {
                    $('.other-risk').slideUp();
                    $('#other_risk').val('');
                }
            });

            // Form submission with loading state
            $('#childHealthForm').on('submit', function(e) {
                // Show loading state
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
        });
    </script>
</body>
</html>
