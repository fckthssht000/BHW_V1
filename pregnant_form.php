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
    
    // Get pregnancy data
    if ($_POST['ajax'] == 'get_pregnancy_data') {
        try {
            $stmt = $pdo->prepare("SELECT philhealth_number FROM person WHERE person_id = ?");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'philhealth_number' => $data['philhealth_number'] ?? ''
                ]
            ]);
        } catch (Exception $e) {
            error_log("getPregnancyData Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch pregnancy data']);
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
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Calculate pregnancy details from LMP
    if ($_POST['ajax'] == 'calculate_pregnancy') {
        try {
            $lmp = new DateTime($_POST['lmp']);
            $today = new DateTime();
            
            // Calculate EDC (280 days / 40 weeks from LMP)
            $edc = clone $lmp;
            $edc->add(new DateInterval('P280D'));
            
            // Calculate gestational age in weeks and days
            $diff = $today->diff($lmp);
            $days_pregnant = $diff->days;
            $weeks = floor($days_pregnant / 7);
            $days = $days_pregnant % 7;
            
            // Calculate months (approximate)
            $months = min(9, max(1, ceil($days_pregnant / 30)));
            
            // Determine trimester
            $trimester = '';
            if ($days_pregnant <= 84) {
                $trimester = 'First Trimester (0-84 days)';
            } elseif ($days_pregnant <= 189) {
                $trimester = 'Second Trimester (85-189 days)';
            } else {
                $trimester = 'Third Trimester (190+ days)';
            }
            
            // Check if past EDC
            $is_overdue = $today > $edc;
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'edc' => $edc->format('Y-m-d'),
                'months' => $months,
                'weeks' => $weeks,
                'days' => $days,
                'trimester' => $trimester,
                'days_pregnant' => $days_pregnant,
                'is_overdue' => $is_overdue
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Validate pregnancy dates
    if ($_POST['ajax'] == 'validate_dates') {
        try {
            $lmp = new DateTime($_POST['lmp']);
            $edc = new DateTime($_POST['edc']);
            $today = new DateTime();
            
            $warnings = [];
            
            // LMP cannot be in future
            if ($lmp > $today) {
                $warnings[] = 'LMP cannot be in the future';
            }
            
            // LMP should not be more than 9 months ago
            $diff = $today->diff($lmp);
            if ($diff->days > 280) {
                $warnings[] = 'LMP is more than 9 months ago. Please verify the date.';
            }
            
            // EDC validation (should be approximately 280 days from LMP)
            $expected_edc = clone $lmp;
            $expected_edc->add(new DateInterval('P280D'));
            $edc_diff = abs($expected_edc->diff($edc)->days);
            
            if ($edc_diff > 14) {
                $warnings[] = 'EDC does not match LMP calculation (expected: ' . $expected_edc->format('Y-m-d') . ')';
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'warnings' => $warnings,
                'is_valid' => count($warnings) == 0
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Check for high-risk pregnancy
    if ($_POST['ajax'] == 'check_pregnancy_risks') {
        try {
            $risks = $_POST['risks'];
            $high_risk = [
                'Convulsion',
                'Vaginal Bleeding',
                'Severe Abdominal Pain',
                'Headache accompanied by Blurred Vision'
            ];
            
            $critical_risks = array_intersect($risks, $high_risk);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'has_critical' => count($critical_risks) > 0,
                'critical_risks' => array_values($critical_risks)
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// Fetch user role
try {
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Error: User not found for user_id: " . $_SESSION['user_id']);
    }
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
    $checkup_date = implode(',', $_POST['checkup_date'] ?? []);
    $months_pregnancy = $_POST['months_pregnancy'];
    $medications = $_POST['medication'] ?? [];
    $risks = implode(',', $_POST['risks'] ?? []);
    $birth_plan = isset($_POST['birth_plan']) ? 'Y' : 'N';
    $lmp = $_POST['lmp'] ?? '';
    $edc = $_POST['edc'] ?? '';
    $preg_count = $_POST['preg_count'] ?? '';
    $child_alive = $_POST['child_alive'] ?? '';
    $philhealth_number = $_POST['philhealth_number'] ?? '';


    // If BHW, restrict by purok
    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $mother_purok = $stmt->fetchColumn();
        if ($mother_purok !== $user_purok) {
            die("Error: BHW Staff can only submit records for their assigned purok ($user_purok).");
        }
    }

    try {
        $pdo->beginTransaction();

        // Duplicate check, remain unchanged
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM records r 
            JOIN pregnancy_record pr ON r.records_id = pr.records_id 
            JOIN prenatal p ON pr.pregnancy_record_id = p.pregnancy_record_id 
            WHERE r.person_id = ? 
            AND p.checkup_date = ? 
            AND p.months_pregnancy = ? 
            AND p.last_menstruation = ? 
            AND p.expected_delivery_date = ? 
            AND p.preg_count = ? 
            AND p.child_alive = ?
        ");
        $stmt->execute([$person_id, $checkup_date, $months_pregnancy, $lmp, $edc, $preg_count, $child_alive]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This submission already exists for the selected mother.");
        }

        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)");
            $stmt->execute([$person_id]);
            $selected_user_id = $stmt->fetchColumn();
            if ($selected_user_id === false) {
                throw new Exception("No user_id found for person_id: " . $person_id);
            }
        }

        // Update Philhealth
        $stmt = $pdo->prepare("UPDATE person SET philhealth_number = ? WHERE person_id = ?");
        $stmt->execute([$philhealth_number, $person_id]);

        // Insert records row
        $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$selected_user_id, $person_id, 'pregnancy_record.prenatal', $_SESSION['user_id']]);
        $records_id = $pdo->lastInsertId();

        // Insert into pregnancy_record
        $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period, created_at, updated_at) VALUES (?, 'Prenatal', NOW(), NOW())");
        $stmt->execute([$records_id]);
        $pregnancy_record_id = $pdo->lastInsertId();

        // Save all medications selected through the junction table
        if (!empty($medications)) {
            foreach ($medications as $medication_name) {
                // Try to find medication first
                $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                $stmt->execute([$medication_name]);
                $medication_id = $stmt->fetchColumn();
                if ($medication_id === false) {
                    $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                    $stmt->execute([$medication_name]);
                    $medication_id = $pdo->lastInsertId();
                }
                // Insert into pregnancy_medication junction table
                $stmt = $pdo->prepare("INSERT INTO pregnancy_medication (pregnancy_record_id, medication_id) VALUES (?, ?)");
                $stmt->execute([$pregnancy_record_id, $medication_id]);
            }
        }

        // Insert into prenatal form
        $stmt = $pdo->prepare("
            INSERT INTO prenatal (
                pregnancy_record_id, months_pregnancy, checkup_date, birth_plan, 
                risk_observed, last_menstruation, expected_delivery_date, preg_count, child_alive
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pregnancy_record_id,
            $months_pregnancy,
            $checkup_date,
            $birth_plan,
            $risks,
            $lmp,
            $edc,
            $preg_count,
            $child_alive
        ]);

        $pdo->commit();
        header("Location: pregnant_form.php?success=1");
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
    <title>BRGYCare - Pregnant Form</title>
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
        }
        .form-control[type="date"]:focus {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%233b82f6' stroke-width='2'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/%3e%3c/svg%3e");
        }
        .form-control[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
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
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
            align-items: flex-start;
        }
        .form-check {
            margin-bottom: 0;
            white-space: nowrap;
            flex: 0 0 auto;
        }
        .form-check-input {
            margin-top: 0.2rem;
            transform: scale(1.1);
        }
        .form-check-label {
            font-size: 0.95rem;
            color: #2d3748;
            word-break: break-word;
            margin-left: 0.5rem;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
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
        #mother_info_card, #pregnancy_timeline, #date_warnings, #risk_warnings, #gestational_age {
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
            .checkbox-group {
                display: block;
                gap: 0.5rem;
            }
            .form-check {
                white-space: normal;
                display: block;
                margin-bottom: 0.5rem;
            }
            .form-check-label {
                font-size: 0.9rem;
                word-break: break-word;
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
                    <div class="card-header">Pregnant Form</div>
                    <div class="card-body p-4">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <i class="fas fa-check-circle"></i> Pregnancy record saved successfully!
                            </div>
                        <?php endif; ?>
                        
                        <div id="error-message" class="error-message"></div>
                        
                        <!-- Mother Info Card -->
                        <div id="mother_info_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user"></i> Mother Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="mother_name">-</span></p>
                                <p class="mb-1"><strong>Age:</strong> <span id="mother_age">-</span> years old</p>
                                <p class="mb-0"><strong>Purok:</strong> <span id="mother_purok">-</span></p>
                            </div>
                        </div>

                        <!-- Gestational Age Display -->
                        <div id="gestational_age"></div>

                        <!-- Pregnancy Timeline -->
                        <div id="pregnancy_timeline"></div>

                        <!-- Date Warnings -->
                        <div id="date_warnings"></div>

                        <!-- Risk Warnings -->
                        <div id="risk_warnings"></div>

                        <form action="pregnant_form.php" method="POST" id="pregnantForm">
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
                                <label for="philhealth_number">Philhealth Number</label>
                                <input type="text" class="form-control" id="philhealth_number" name="philhealth_number" placeholder="12-345678910-1" pattern="[0-9]{2}-[0-9]{9}-[0-9]{1}">
                                <small class="form-text text-muted">Format: 12-345678910-1</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="lmp">Last Menstrual Period <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="lmp" name="lmp" required>
                                <div class="invalid-feedback" id="lmp_error"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edc">Expected Date of Childbirth <span class="text-danger"></span></label>
                                <input type="date" class="form-control" id="edc" name="edc" readonly>
                                <small class="form-text text-muted">Auto-calculated from LMP (280 days)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="preg_count">Number of Pregnancy <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="preg_count" name="preg_count" required min="0" max="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="child_alive">Number of Living Children <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="child_alive" name="child_alive" required min="0" max="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="months_pregnancy">Months Pregnant <span class="text-danger"></span></label>
                                <select class="form-control" id="months_pregnancy" name="months_pregnancy">
                                    <?php for ($i = 1; $i <= 9; $i++) { echo "<option value='$i'>$i month(s)</option>"; } ?>
                                </select>
                                <small class="form-text text-muted">Auto-calculated from LMP</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Checkup Date</label>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="checkup_date[]" id="check_none" value="None">
                                        <label class="form-check-label" for="check_none">None</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="checkup_date[]" id="check_first" value="First Trimester (0-84 days)">
                                        <label class="form-check-label" for="check_first">First Trimester (0-84 days)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="checkup_date[]" id="check_second" value="Second Trimester (85-189 days)">
                                        <label class="form-check-label" for="check_second">Second Trimester (85-189 days)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="checkup_date[]" id="check_third" value="Third Trimester (190+ days)">
                                        <label class="form-check-label" for="check_third">Third Trimester (190+ days)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Medication</label>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="medication[]" id="med_ferrous" value="Ferrous Sulfate with Folic Acid">
                                        <label class="form-check-label" for="med_ferrous">Ferrous Sulfate with Folic Acid</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="medication[]" id="med_tetanus" value="Tetanus Toxoid">
                                        <label class="form-check-label" for="med_tetanus">Tetanus Toxoid</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Risks Observed</label>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input risk-checkbox" type="checkbox" name="risks[]" id="risk_headache" value="Headache accompanied by Blurred Vision">
                                        <label class="form-check-label" for="risk_headache">Headache accompanied by Blurred Vision</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input risk-checkbox" type="checkbox" name="risks[]" id="risk_fever" value="Fever">
                                        <label class="form-check-label" for="risk_fever">Fever</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input risk-checkbox" type="checkbox" name="risks[]" id="risk_bleeding" value="Vaginal Bleeding">
                                        <label class="form-check-label" for="risk_bleeding">Vaginal Bleeding</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input risk-checkbox" type="checkbox" name="risks[]" id="risk_convulsion" value="Convulsion">
                                        <label class="form-check-label" for="risk_convulsion">Convulsion</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input risk-checkbox" type="checkbox" name="risks[]" id="risk_pain" value="Severe Abdominal Pain">
                                        <label class="form-check-label" for="risk_pain">Severe Abdominal Pain</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_paleness" value="Paleness">
                                        <label class="form-check-label" for="risk_paleness">Paleness</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="risks[]" id="risk_swelling" value="Swelling of the foot/feet">
                                        <label class="form-check-label" for="risk_swelling">Swelling of the foot/feet</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Birth Plan</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="birth_plan" id="birth_plan" value="1">
                                    <label class="form-check-label" for="birth_plan">Has Birth Plan</label>
                                </div>
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
                    $('#mother_info_card, #pregnancy_timeline, #gestational_age, #date_warnings, #risk_warnings').slideUp();
                    return;
                }

                // Fetch mother details
                $.ajax({
                    url: 'pregnant_form.php',
                    type: 'POST',
                    data: { ajax: 'get_mother_details', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#mother_info_card').slideDown();
                            $('#mother_name').text(response.data.full_name);
                            $('#mother_age').text(response.data.age);
                            $('#mother_purok').text(response.data.purok);
                        }
                    }
                });

                // Fetch pregnancy data
                $.ajax({
                    url: 'pregnant_form.php',
                    type: 'POST',
                    data: { ajax: 'get_pregnancy_data', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#philhealth_number').val(response.data.philhealth_number);
                        } else {
                            $('#error-message').text(response.error || 'Failed to fetch pregnancy data');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                    }
                });
            });

            // Calculate pregnancy details when LMP is entered
            $('#lmp').on('change', function() {
                const lmp = $(this).val();
                
                if (!lmp) return;
                
                $.ajax({
                    url: 'pregnant_form.php',
                    type: 'POST',
                    data: { ajax: 'calculate_pregnancy', lmp: lmp },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Set EDC
                            $('#edc').val(response.edc);
                            
                            // Set months
                            $('#months_pregnancy').val(response.months);
                            
                            // Show gestational age
                            let html = '<div class="alert alert-info alert-dismissible fade show">';
                            html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                            html += '<strong><i class="fas fa-baby"></i> Gestational Age:</strong> ';
                            html += `${response.weeks} weeks and ${response.days} days (${response.days_pregnant} days total)<br>`;
                            html += `<strong>Current Trimester:</strong> ${response.trimester}`;
                            
                            if (response.is_overdue) {
                                html += '<br><span class="text-danger"><strong>⚠️ OVERDUE - Refer to hospital immediately!</strong></span>';
                            }
                            
                            html += '</div>';
                            $('#gestational_age').html(html).slideDown();
                            
                            // Auto-check appropriate trimester
                            $('input[name="checkup_date[]"]').prop('checked', false);
                            $(`input[value="${response.trimester}"]`).prop('checked', true);
                            
                            // Validate dates
                            validateDates();
                        }
                    }
                });
            });

            // Validate dates
            function validateDates() {
                const lmp = $('#lmp').val();
                const edc = $('#edc').val();
                
                if (!lmp || !edc) return;
                
                $.ajax({
                    url: 'pregnant_form.php',
                    type: 'POST',
                    data: { ajax: 'validate_dates', lmp: lmp, edc: edc },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.warnings.length > 0) {
                                let html = '<div class="alert alert-warning alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-triangle"></i> Date Validation:</strong><ul class="mb-0 mt-2">';
                                response.warnings.forEach(warning => {
                                    html += `<li>${warning}</li>`;
                                });
                                html += '</ul></div>';
                                $('#date_warnings').html(html).slideDown();
                                
                                if (!response.is_valid) {
                                    $('#lmp').addClass('is-invalid');
                                    $('#lmp_error').text('Please verify LMP date');
                                } else {
                                    $('#lmp').removeClass('is-invalid').addClass('is-valid');
                                }
                            } else {
                                $('#date_warnings').slideUp().empty();
                                $('#lmp').removeClass('is-invalid').addClass('is-valid');
                            }
                        }
                    }
                });
            }

            // Check for high-risk conditions
            $('.risk-checkbox').on('change', function() {
                const selectedRisks = [];
                $('.risk-checkbox:checked').each(function() {
                    selectedRisks.push($(this).val());
                });
                
                if (selectedRisks.length > 0) {
                    $.ajax({
                        url: 'pregnant_form.php',
                        type: 'POST',
                        data: { ajax: 'check_pregnancy_risks', risks: selectedRisks },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.has_critical) {
                                let html = '<div class="alert alert-danger alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-circle"></i> HIGH-RISK PREGNANCY DETECTED!</strong><br>';
                                html += '<p class="mb-2">The following symptoms require immediate medical attention:</p>';
                                html += '<ul class="mb-0">';
                                response.critical_risks.forEach(risk => {
                                    html += `<li><strong>${risk}</strong></li>`;
                                });
                                html += '</ul>';
                                html += '<p class="mb-0 mt-2"><strong>Action Required:</strong> Refer to hospital immediately for high-risk pregnancy care</p>';
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

            // Handle "None" selection
            $('input[name="checkup_date[]"]').on('change', function() {
                if ($(this).val() === 'None') {
                    if ($(this).is(':checked')) {
                        $('input[name="checkup_date[]"]').not(this).prop('checked', false);
                    }
                } else if ($('input[name="checkup_date[]"][value="None"]').is(':checked')) {
                    $('input[name="checkup_date[]"][value="None"]').prop('checked', false);
                }
            });

            // Ensure at least one checkup or "None" is selected
            $('input[name="checkup_date[]"]').on('change', function() {
                if ($('input[name="checkup_date[]"]:checked').length === 0) {
                    $('input[name="checkup_date[]"][value="None"]').prop('checked', true);
                }
            });

            // Set default to "None" on load
            if ($('input[name="checkup_date[]"]:checked').length === 0) {
                $('input[name="checkup_date[]"][value="None"]').prop('checked', true);
            }

            // Handle form submission
            $('#pregnantForm').on('submit', function(e) {
                // Validation only - let form submit normally
                if ($('.is-invalid').length > 0) {
                    e.preventDefault();
                    $('#error-message').text('Please fix validation errors before submitting.');
                    return false;
                }
                
                if ($('input[name="checkup_date[]"]:checked').length === 0) {
                    e.preventDefault();
                    $('#error-message').text('Please select at least one checkup period.');
                    return false;
                }
                
                // Show loading state
                $('#submit_btn').prop('disabled', true)
                    .html('<span class="loading-spinner"></span>Submitting...');
                
                // Let form submit naturally - PHP will handle it
                return true;
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
        });
    </script>
</body>
</html>
