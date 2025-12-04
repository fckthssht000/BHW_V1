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
    
    // Get family planning data
    if ($_POST['ajax'] == 'get_family_planning_data') {
        try {
            $stmt = $pdo->prepare("
                SELECT uses_fp_method, fp_method, months_used, reason_not_using 
                FROM records r 
                JOIN family_planning_record fpr ON r.records_id = fpr.records_id 
                WHERE r.person_id = ? AND r.record_type = 'family_planning_record'
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $uses_fp_method = false;
            $fp_methods = [];
            $months_used = [];
            $reason_not_using = '';

            if ($data) {
                $uses_fp_method = $data['uses_fp_method'] === 'Y';
                $fp_methods = !empty($data['fp_method']) ? explode(',', $data['fp_method']) : [];
                $months_used = !empty($data['months_used']) ? explode(',', $data['months_used']) : [];
                $reason_not_using = $data['reason_not_using'] ?? '';
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'uses_fp_method' => $uses_fp_method,
                    'fp_methods' => $fp_methods,
                    'months_used' => $months_used,
                    'reason_not_using' => $reason_not_using
                ]
            ]);
        } catch (Exception $e) {
            error_log("getFamilyPlanningData Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch family planning data']);
        }
        exit;
    }
    
    // Get person details and age-based recommendations
    if ($_POST['ajax'] == 'get_person_details') {
        try {
            $stmt = $pdo->prepare("
                SELECT p.full_name, p.birthdate, p.age, p.civil_status, a.purok
                FROM person p
                LEFT JOIN address a ON p.address_id = a.address_id
                WHERE p.person_id = ?
            ");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $age = $data['age'];
                $recommendations = [];
                
                // Age-based recommendations based on medical guidelines
                if ($age >= 15 && $age <= 19) {
                    $recommendations = ['Condom', 'P (Pills)', 'DMPA'];
                } elseif ($age >= 20 && $age <= 35) {
                    $recommendations = ['P (Pills)', 'IUD', 'DMPA', 'Condom', 'NFP-LAM (Lactation Amenorrhea Method)'];
                } elseif ($age >= 36 && $age <= 40) {
                    $recommendations = ['IUD', 'DMPA', 'BTL (Ligation)', 'NFP-CM (Cervical Mucus)'];
                } elseif ($age > 40) {
                    $recommendations = ['BTL (Ligation)', 'NSV (Vasectomy)', 'IUD', 'NFP-BBT (Basal Body Temperature)'];
                }
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'recommendations' => $recommendations
                ]);
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Person not found']);
            }
        } catch (Exception $e) {
            error_log("getPersonDetails Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch person details']);
        }
        exit;
    }
    
    // Validate contraceptive method based on health conditions
    if ($_POST['ajax'] == 'validate_method') {
        try {
            $method = $_POST['method'];
            $person_id = $_POST['person_id'];
            
            $stmt = $pdo->prepare("SELECT age, health_condition FROM person WHERE person_id = ?");
            $stmt->execute([$person_id]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $warnings = [];
            
            // Method-specific warnings
            if ($method == 'P (Pills)' && $person['age'] > 35) {
                $warnings[] = 'Pills may have increased risks for women over 35 (especially smokers)';
            }
            if ($method == 'IUD' && strpos($person['health_condition'], 'PID') !== false) {
                $warnings[] = 'IUD not recommended for those with history of pelvic inflammatory disease';
            }
            if (($method == 'BTL (Ligation)' || $method == 'NSV (Vasectomy)') && $person['age'] < 25) {
                $warnings[] = 'Permanent methods not recommended for very young individuals';
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'warnings' => $warnings
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
    $uses_fp = isset($_POST['fp_usage']) ? 'Y' : 'N';
    $fp_methods = isset($_POST['fp_methods']) ? implode(',', $_POST['fp_methods']) : '';
    $months_used = isset($_POST['months_use']) ? implode(',', $_POST['months_use']) : '';
    $reason_not_using = isset($_POST['reason_not_use']) ? $_POST['reason_not_use'] : '';

    if (empty($person_id)) {
        die("Error: Person ID is required.");
    }

    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $person_purok = $stmt->fetchColumn();
        if ($person_purok !== $user_purok) {
            die("Error: BHW Staff can only submit records for their assigned purok ($user_purok).");
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $selected_user_id = $_SESSION['user_id'];
        }

        $stmt = $pdo->prepare("SELECT r.records_id FROM records r JOIN family_planning_record fpr ON r.records_id = fpr.records_id WHERE r.person_id = ? AND r.record_type = 'family_planning_record'");
        $stmt->execute([$person_id]);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_record) {
            $records_id = $existing_record['records_id'];
            $stmt = $pdo->prepare("UPDATE family_planning_record SET uses_fp_method = ?, fp_method = ?, months_used = ?, reason_not_using = ? WHERE records_id = ?");
            $stmt->execute([$uses_fp, $fp_methods, $months_used, $reason_not_using, $records_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$selected_user_id, $person_id, 'family_planning_record', $_SESSION['user_id']]);
            $records_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO family_planning_record (records_id, uses_fp_method, fp_method, months_used, reason_not_using) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$records_id, $uses_fp, $fp_methods, $months_used, $reason_not_using]);
        }

        $pdo->commit();
        header("Location: family_planning_form.php?success=1");
        exit;
    } catch (PDOException $e) {
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
    <title>BRGYCare - Family Planning Form</title>
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
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
        }
        .disabled-style {
            color: #6c757d;
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .disabled-style input[type="checkbox"],
        .disabled-style textarea {
            opacity: 0.65;
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
        #person_info_card, #recommendations_card, #method_warnings {
            display: none;
            margin-bottom: 15px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .recommended-method {
            background-color: #d4edda;
            border-left: 3px solid #28a745;
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
            .checkbox-group {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
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
                    <div class="card-header">Family Planning Form</div>
                    <div class="card-body p-4">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <i class="fas fa-check-circle"></i> Family planning record saved successfully!
                            </div>
                        <?php endif; ?>
                        
                        <div id="error-message" class="error-message"></div>
                        
                        <!-- Person Info Card -->
                        <div id="person_info_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user"></i> Person Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="person_name">-</span></p>
                                <p class="mb-1"><strong>Age:</strong> <span id="person_age">-</span> years old</p>
                                <p class="mb-1"><strong>Civil Status:</strong> <span id="person_status">-</span></p>
                                <p class="mb-0"><strong>Purok:</strong> <span id="person_purok">-</span></p>
                            </div>
                        </div>

                        <!-- Recommendations Card -->
                        <div id="recommendations_card"></div>

                        <!-- Method Warnings -->
                        <div id="method_warnings"></div>

                        <form action="family_planning_form.php" method="POST" id="familyPlanningForm">
                            <div class="form-group">
                                <label for="person_id">Select Person <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="person_id" name="person_id" required>
                                    <option value="">Search and Select Person...</option>
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
                                                WHERE p.gender = 'F' 
                                                AND p.birthdate BETWEEN ? AND ? 
                                                AND a.purok = ?
                                            ");
                                            $stmt->execute([$min_birthdate->format('Y-m-d'), $max_birthdate->format('Y-m-d'), $user_purok]);
                                        } else {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name, p.birthdate 
                                                FROM person p 
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
                                        echo "<option value=''>Error loading persons</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Family Planning Usage</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="fp_usage" id="fp_usage" value="1">
                                    <label class="custom-control-label" for="fp_usage">Currently Using Family Planning</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Family Planning Methods</label>
                                <div id="fp_methods_group" class="disabled-style checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="BTL (Ligation)" data-method="BTL (Ligation)"> BTL (Ligation)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NSV (Vasectomy)" data-method="NSV (Vasectomy)"> NSV (Vasectomy)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="P (Pills)" data-method="P (Pills)"> P (Pills)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="IUD" data-method="IUD"> IUD
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="DMPA" data-method="DMPA"> DMPA
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NFP-CM (Cervical Mucus)" data-method="NFP-CM (Cervical Mucus)"> NFP-CM (Cervical Mucus)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NFP-BBT (Basal Body Temperature)" data-method="NFP-BBT (Basal Body Temperature)"> NFP-BBT (Basal Body Temperature)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NFP-STM (Sympto Thermal Method)" data-method="NFP-STM (Sympto Thermal Method)"> NFP-STM (Sympto Thermal Method)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NFP-SDM (Standard Days Method)" data-method="NFP-SDM (Standard Days Method)"> NFP-SDM (Standard Days Method)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="NFP-LAM (Lactation Amenorrhea Method)" data-method="NFP-LAM (Lactation Amenorrhea Method)"> NFP-LAM (Lactation Amenorrhea Method)
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="fp_methods[]" value="Condom" data-method="Condom"> Condom
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Months of Use</label>
                                <div id="months_use_group" class="disabled-style checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="January"> January
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="February"> February
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="March"> March
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="April"> April
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="May"> May
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="June"> June
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="July"> July
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="August"> August
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="September"> September
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="October"> October
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="November"> November
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="months_use[]" value="December"> December
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="reason_not_use">Reason for Non-Use</label>
                                <textarea class="form-control" id="reason_not_use" name="reason_not_use" rows="3" placeholder="Enter reason if not using family planning (e.g., planning pregnancy, religious beliefs, health concerns)"></textarea>
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
            let recommendedMethods = [];
            
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Search and Select Person...",
                allowClear: true
            });

            toggleFields();
            $('#fp_usage').on('change', function() {
                toggleFields();
            });

            // Auto-fill form on person selection
            $('#person_id').on('change', function() {
                $('#error-message').empty();
                const personId = $(this).val();
                currentPersonId = personId;
                
                if (!personId) {
                    $('#person_info_card, #recommendations_card, #method_warnings').slideUp();
                    return;
                }

                // Fetch person details and recommendations
                $.ajax({
                    url: 'family_planning_form.php',
                    type: 'POST',
                    data: { ajax: 'get_person_details', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show person info
                            $('#person_info_card').slideDown();
                            $('#person_name').text(response.data.full_name);
                            $('#person_age').text(response.data.age);
                            $('#person_status').text(response.data.civil_status || 'Not specified');
                            $('#person_purok').text(response.data.purok);
                            
                            // Show recommendations
                            if (response.recommendations.length > 0) {
                                recommendedMethods = response.recommendations;
                                let html = '<div class="alert alert-info alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-lightbulb"></i> Recommended Methods for Age ' + response.data.age + ':</strong><br>';
                                html += '<ul class="mb-0 mt-2">';
                                response.recommendations.forEach(method => {
                                    html += `<li>${method}</li>`;
                                });
                                html += '</ul>';
                                html += '<small class="text-muted">These are general recommendations. Consult a healthcare provider for personalized advice.</small>';
                                html += '</div>';
                                $('#recommendations_card').html(html).slideDown();
                                
                                // Highlight recommended methods
                                $('.checkbox-item').removeClass('recommended-method');
                                response.recommendations.forEach(method => {
                                    $(`input[value="${method}"]`).closest('.checkbox-item').addClass('recommended-method');
                                });
                            }
                        }
                    }
                });

                // Fetch family planning data
                $.ajax({
                    url: 'family_planning_form.php',
                    type: 'POST',
                    data: { ajax: 'get_family_planning_data', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Set usage checkbox
                            $('#fp_usage').prop('checked', response.data.uses_fp_method).trigger('change');

                            // Handle methods checkboxes
                            $('input[name="fp_methods[]"]').prop('checked', false);
                            response.data.fp_methods.forEach(method => {
                                $(`input[value="${method}"][name="fp_methods[]"]`).prop('checked', true);
                            });

                            // Handle months checkboxes
                            $('input[name="months_use[]"]').prop('checked', false);
                            response.data.months_used.forEach(month => {
                                $(`input[value="${month}"][name="months_use[]"]`).prop('checked', true);
                            });

                            // Set reason
                            $('#reason_not_use').val(response.data.reason_not_using);
                        } else {
                            $('#error-message').text(response.error || 'Failed to fetch family planning data');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        $('#error-message').text('Error fetching family planning data: ' + error);
                    }
                });
            });

            // Validate method selection
            $('input[name="fp_methods[]"]').on('change', function() {
                if (!currentPersonId) return;
                
                const method = $(this).data('method');
                const isChecked = $(this).is(':checked');
                
                if (isChecked) {
                    $.ajax({
                        url: 'family_planning_form.php',
                        type: 'POST',
                        data: { ajax: 'validate_method', method: method, person_id: currentPersonId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.warnings.length > 0) {
                                let html = '<div class="alert alert-warning alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-triangle"></i> Warning for ' + method + ':</strong><ul class="mb-0 mt-2">';
                                response.warnings.forEach(warning => {
                                    html += `<li>${warning}</li>`;
                                });
                                html += '</ul></div>';
                                $('#method_warnings').html(html).slideDown();
                            } else {
                                $('#method_warnings').slideUp().empty();
                            }
                        }
                    });
                }
            });

            function toggleFields() {
                if ($('#fp_usage').is(':checked')) {
                    $('#fp_methods_group, #months_use_group').removeClass('disabled-style').find('input[type="checkbox"]').prop('disabled', false);
                    $('#reason_not_use').prop('disabled', true).val('').parent().addClass('disabled-style');
                } else {
                    $('#fp_methods_group, #months_use_group').addClass('disabled-style').find('input[type="checkbox"]').prop('disabled', true).prop('checked', false);
                    $('#reason_not_use').prop('disabled', false).parent().removeClass('disabled-style');
                    $('#method_warnings').slideUp().empty();
                }
            }

            // Form submission with loading state
            $('#familyPlanningForm').on('submit', function(e) {
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
            // Initialize accordion
            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });
        });
    </script>
</body>
</html>
