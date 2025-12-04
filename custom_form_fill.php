<?php
/**
 * File: custom_form_fill.php
 * Fill custom forms for residents (Role 1 & 2)
 * Similar structure to your child_health_form.php
 */
session_start();
require_once 'db_connect.php';

// Turn off error display
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Get user role and purok
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!in_array($user['role_id'], [1, 2])) {
    header("Location: dashboard.php");
    exit;
}

// Get user's purok (for Role 2)
$user_purok = null;
if ($user['role_id'] == 2) {
    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM users u
        JOIN person p ON u.user_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $purok_data = $stmt->fetch();
    $user_purok = $purok_data['purok'] ?? null;
}

// ==================== AJAX HANDLERS ====================
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    ob_start();
    
    try {
        // Get persons list (filtered by purok for Role 2 AND target filters)
        if ($_POST['ajax'] == 'get_persons') {
            $search = trim($_POST['search'] ?? '');
            $form_id = (int)($_POST['form_id'] ?? 0);
            
            // Get form's target filters
            $target_filters = [];
            if ($form_id) {
                $form_stmt = $pdo->prepare("SELECT target_filters FROM custom_forms WHERE custom_form_id = ?");
                $form_stmt->execute([$form_id]);
                $form_data = $form_stmt->fetch();
                if ($form_data && $form_data['target_filters']) {
                    $target_filters = json_decode($form_data['target_filters'], true);
                }
            }
            
            $sql = "
                SELECT p.person_id, p.full_name, p.age, p.gender, p.civil_status, a.purok
                FROM person p
                JOIN address a ON p.address_id = a.address_id
                JOIN users u ON u.user_id = p.person_id
                WHERE 1=1
                AND u.role_id = 3
            ";
            
            $params = [];
            
            // Search filter
            if ($search) {
                $sql .= " AND p.full_name LIKE ?";
                $params[] = "%$search%";
            }
            
            // Role 2 purok filter
            if ($user['role_id'] == 2 && $user_purok) {
                $sql .= " AND a.purok = ?";
                $params[] = $user_purok;
            }
            
            // === TARGET FILTERS (like your hardcoded forms) ===
            
            // Age filter
            if (isset($target_filters['age_min']) && $target_filters['age_min'] > 0) {
                $sql .= " AND p.age >= ?";
                $params[] = $target_filters['age_min'];
            }
            
            if (isset($target_filters['age_max']) && $target_filters['age_max'] > 0) {
                $sql .= " AND p.age <= ?";
                $params[] = $target_filters['age_max'];
            }
            
            // Gender filter (like pregnant_form: only Female)
            if (!empty($target_filters['gender'])) {
                $sql .= " AND p.gender = ?";
                $params[] = $target_filters['gender'];
            }
            
            // Civil Status filter (array of allowed statuses)
            if (!empty($target_filters['civil_status']) && is_array($target_filters['civil_status'])) {
                $placeholders = str_repeat('?,', count($target_filters['civil_status']) - 1) . '?';
                $sql .= " AND p.civil_status IN ($placeholders)";
                $params = array_merge($params, $target_filters['civil_status']);
            }
            
            // Relationship Type filter (like household_form: only Head)
            if (!empty($target_filters['relationship_type'])) {
                $sql .= " AND p.relationship_type = ?";
                $params[] = $target_filters['relationship_type'];
            }
            
            $sql .= " ORDER BY p.full_name LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'persons' => $persons]);
            exit;
        }
        
        // Get person details
        if ($_POST['ajax'] == 'get_person_details') {
            $person_id = (int)$_POST['person_id'];
            
            $stmt = $pdo->prepare("
                SELECT p.*, a.purok, a.barangay, a.municipality
                FROM person p
                JOIN address a ON p.address_id = a.address_id
                WHERE p.person_id = ?
            ");
            $stmt->execute([$person_id]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check purok access for Role 2
            if ($user['role_id'] == 2 && $user_purok && $person['purok'] !== $user_purok) {
                throw new Exception('You can only access residents in your purok');
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'person' => $person]);
            exit;
        }
        
        // Check existing submission (for forms that don't allow duplicates)
        if ($_POST['ajax'] == 'check_submission') {
            $form_id = (int)$_POST['form_id'];
            $person_id = (int)$_POST['person_id'];
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM custom_form_submissions 
                WHERE custom_form_id = ? AND person_id = ?
            ");
            $stmt->execute([$form_id, $person_id]);
            $exists = $stmt->fetchColumn() > 0;
            
            ob_end_clean();
            echo json_encode(['success' => true, 'exists' => $exists]);
            exit;
        }
        
        // Submit form
        if ($_POST['ajax'] == 'submit_form') {
            $form_id = (int)$_POST['form_id'];
            $person_id = (int)$_POST['person_id'];
            $form_data = json_decode($_POST['form_data'], true);
            
            // Get form details
            $stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE custom_form_id = ? AND is_active = 1");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$form) {
                throw new Exception('Form not found or inactive');
            }
            
            // Get person details for purok check
            $stmt = $pdo->prepare("
                SELECT p.*, a.purok 
                FROM person p
                JOIN address a ON p.address_id = a.address_id
                WHERE p.person_id = ?
            ");
            $stmt->execute([$person_id]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$person) {
                throw new Exception('Person not found');
            }
            
            // Role 2 purok check
            if ($user['role_id'] == 2 && $user_purok && $form['requires_purok_match'] && $person['purok'] !== $user_purok) {
                throw new Exception('You can only submit forms for residents in your purok');
            }
            
            // Check duplicates
            if (!$form['allow_duplicates']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM custom_form_submissions 
                    WHERE custom_form_id = ? AND person_id = ?
                ");
                $stmt->execute([$form_id, $person_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('A submission already exists for this person');
                }
            }
            
            // Get form fields for validation
            $stmt = $pdo->prepare("
                SELECT * FROM custom_form_fields 
                WHERE custom_form_id = ? 
                ORDER BY field_order ASC
            ");
            $stmt->execute([$form_id]);
            $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Validate required fields
            $errors = [];
            foreach ($fields as $field) {
                if ($field['is_required']) {
                    $value = $form_data[$field['field_name']] ?? null;
                    if (empty($value)) {
                        $errors[] = $field['field_label'] . ' is required';
                    }
                }
            }
            
            if (!empty($errors)) {
                throw new Exception('Validation errors: ' . implode(', ', $errors));
            }
            
            // Get person's user_id (for the submission)
            $user_id = $_SESSION['user_id']; // Default to staff's user_id
            
            // Insert submission
            $stmt = $pdo->prepare("
                INSERT INTO custom_form_submissions 
                (custom_form_id, user_id, person_id, created_by, submission_data, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_id,
                $user_id,
                $person_id,
                $_SESSION['user_id'],
                json_encode($form_data),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            $submission_id = $pdo->lastInsertId();
            
            // Also create a record in the main records table
            $stmt = $pdo->prepare("
                INSERT INTO records (user_id, person_id, record_type, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $person_id,
                $form['record_type'],
                $_SESSION['user_id']
            ]);
            
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Form submitted successfully!',
                'submission_id' => $submission_id
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ==================== GET FORM DATA ====================
$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;
$person_id = isset($_GET['person_id']) ? (int)$_GET['person_id'] : null;

$form = null;
$fields = [];
$person = null;

if ($form_id) {
    // Get form
    $stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE custom_form_id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($form) {
        // Get fields
        $stmt = $pdo->prepare("
            SELECT * FROM custom_form_fields 
            WHERE custom_form_id = ? 
            ORDER BY field_order ASC
        ");
        $stmt->execute([$form_id]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get options for each field
        foreach ($fields as &$field) {
            $stmt = $pdo->prepare("
                SELECT * FROM custom_form_field_options 
                WHERE field_id = ? 
                ORDER BY option_order ASC
            ");
            $stmt->execute([$field['field_id']]);
            $field['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($field);
    }
    
    // Get person if specified
    if ($person_id) {
        $stmt = $pdo->prepare("
            SELECT p.*, a.purok, a.barangay 
            FROM person p
            JOIN address a ON p.address_id = a.address_id
            WHERE p.person_id = ?
        ");
        $stmt->execute([$person_id]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Role 2 purok check
        if ($user['role_id'] == 2 && $user_purok && $form['requires_purok_match'] && $person['purok'] !== $user_purok) {
            $_SESSION['error'] = 'You can only access residents in your purok';
            header("Location: custom_form_fill.php");
            exit;
        }
    }
}

// Get all active forms for the list view
$stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE is_active = 1 AND show_in_dashboard = 1 ORDER BY form_title ASC");
$stmt->execute();
$all_forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $form ? htmlspecialchars($form['form_title']) : 'Custom Forms' ?> - BRGYCare</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        /* Modern Navbar - ORIGINAL COLORS */
        .navbar {
            background: rgba(43, 108, 176, 0.9) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 80px !important;
            padding: 0 1rem !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Menu toggle (logo) */
        .menu-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .menu-toggle .logo {
            max-width: 50px;
            border-radius: 50px;
            margin: 0;
        }

        /* Navbar brand */
        .navbar-brand {
            color: #fff !important;
            font-weight: 600;
            font-size: 1.5rem;
            padding: 0;
            margin: 0 0 0 15px;
        }

        .navbar-brand:hover {
            color: #e2e8f0 !important;
        }

        /* Right side items container */
        .navbar .ml-auto {
            margin-left: auto !important;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Notification wrapper */
        .notification-wrapper {
            position: relative;
            margin-right: 0 !important;
        }

        .notification-bell {
            color: #fff;
            padding: 10px;
            cursor: pointer;
            transition: color 0.2s;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .notification-bell:hover {
            color: #e2e8f0;
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e53e3e;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }

        /* Notification dropdown */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            z-index: 1060;
            margin-top: 10px;
            max-height: 450px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f7fafc;
        }

        .notification-header h6 {
            margin: 0;
            color: #1a202c;
            font-weight: 600;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-item:hover {
            background: #f7fafc;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #718096;
            margin-top: 4px;
        }

        .notification-footer {
            padding: 10px 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            background: #f7fafc;
        }

        .notification-footer a {
            color: #2b6cb0;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .notification-footer a:hover {
            text-decoration: underline;
        }

        /* Profile dropdown */
        .nav-item.dropdown {
            position: relative;
        }

        .nav-item.dropdown .nav-link {
            color: #fff !important;
            padding: 10px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
        }

        .nav-item.dropdown .nav-link:hover {
            color: #e2e8f0 !important;
        }

        .nav-item.dropdown .dropdown-menu {
            background: rgba(43, 108, 176, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            min-width: 160px;
        }

        .nav-item.dropdown .dropdown-menu .dropdown-item {
            color: #fff !important;
            padding: 10px 15px;
            transition: all 0.2s;
        }

        .nav-item.dropdown .dropdown-menu .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.15);
            padding-left: 20px;
        }

        /* Dropdown menu positioning */
        .dropdown-menu-right {
            right: 0 !important;
            left: auto !important;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .navbar {
                height: auto !important;
                flex-wrap: wrap;
                padding: 0.5rem 1rem !important;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
                margin-left: 10px;
            }
            
            .menu-toggle .logo {
                max-width: 40px;
            }
            
            .notification-wrapper {
                margin-right: 10px !important;
            }
            
            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
            
            .notification-bell,
            .nav-item.dropdown .nav-link {
                font-size: 1rem;
                padding: 8px;
            }
        }

        /* Ensure no conflicts with builder styles */
        .navbar * {
            box-sizing: border-box;
        }

        /* Fix z-index hierarchy */
        .navbar {
            z-index: 1050 !important;
        }

        .notification-dropdown {
            z-index: 1060 !important;
        }

        .dropdown-menu {
            z-index: 1060 !important;
        }
        @media (min-width: 769px) {
            .menu-toggle { 
                display: none; 
            }
        }
        /* Sidebar Styles */
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

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #edf2f7;
            color: #2b6cb0;
        }

        /* Content area */
        .content {
            padding: 20px;
            min-height: calc(100vh - 80px);
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Desktop with sidebar */
        @media (min-width: 769px) {
            .sidebar {
                left: 0;
                transform: translateX(0);
            }
            .content {
                margin-left: 250px;
            }
        }

        /* Mobile sidebar */
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.open {
                transform: translateX(250px);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            }
            .content {
                margin-left: 0;
                width: 100%;
            }
        }
        .form-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .person-info { background: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-item { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; transition: all 0.2s; cursor: pointer; }
        .form-item:hover { border-color: #0d6efd; box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2); transform: translateY(-2px); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <div class="container mt-4">
                    <?php if (!$form): ?>
                        <!-- LIST FORMS VIEW -->
                        <h3 class="mb-4"><i class="fas fa-clipboard-list"></i> Available Custom Forms</h3>
                        <p class="text-muted">Select a form to fill for a resident</p>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= htmlspecialchars($_SESSION['success']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= htmlspecialchars($_SESSION['error']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <?php if (empty($all_forms)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No custom forms available at this time.
                            </div>
                        <?php else: ?>
                            <div class="form-grid">
                                <?php foreach ($all_forms as $f): ?>
                                    <div class="form-item" onclick="selectPerson(<?= $f['custom_form_id'] ?>)">
                                        <h5><i class="fas fa-file-alt text-primary"></i> <?= htmlspecialchars($f['form_title']) ?></h5>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($f['form_description'] ?? 'No description') ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif (!$person): ?>
                        <a href="custom_form_fill.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Forms
                        </a>
                        <!-- SELECT PERSON VIEW -->
                        <h3 class="mb-4"><i class="fas fa-user-check"></i> Select Resident for <?= htmlspecialchars($form['form_title']) ?></h3>
                        
                        <div class="form-card">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="personSearch" placeholder="Search by name...">
                            </div>
                            
                            <div id="personsList" class="list-group">
                                <!-- Populated via AJAX -->
                            </div>
                        </div>
                        
                        <a href="custom_form_fill.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Forms
                        </a>
                        
                    <?php else: ?>
                        <!-- FILL FORM VIEW -->
                        <h3 class="mb-4"><i class="fas fa-edit"></i> <?= htmlspecialchars($form['form_title']) ?></h3>
                        
                        <div class="person-info">
                            <strong><i class="fas fa-user"></i> Resident:</strong> 
                            <?= htmlspecialchars($person['full_name']) ?> 
                            (<?= htmlspecialchars($person['age']) ?> yrs, <?= htmlspecialchars($person['gender']) ?>)
                            <br>
                            <small>Purok: <?= htmlspecialchars($person['purok']) ?></small>
                        </div>
                        
                        <?php if ($form['form_description']): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($form['form_description']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-card">
                            <form id="customForm">
                                <?php foreach ($fields as $field): ?>
                                    <div class="mb-3">
                                        <?php if (!empty($field['field_group'])): ?>
                                            <div class="text-muted small mb-1"><strong><?= htmlspecialchars($field['field_group']) ?></strong></div>
                                        <?php endif; ?>
                                        <label class="form-label">
                                            <?= htmlspecialchars($field['field_label']) ?>
                                            <?php if ($field['is_required']): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($field['help_text']): ?>
                                            <small class="d-block text-muted mb-1"><?= htmlspecialchars($field['help_text']) ?></small>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $field_name = htmlspecialchars($field['field_name']);
                                        $placeholder = htmlspecialchars($field['placeholder'] ?: '');
                                        $default_value = htmlspecialchars($field['default_value'] ?: '');
                                        $required = $field['is_required'] ? 'required' : '';
                                        
                                        switch ($field['field_type']):
                                            case 'text':
                                                $validation_rules = $field['validation_rules'] 
                                                    ? json_decode($field['validation_rules'], true) 
                                                    : [];
                                                $patternAttr = '';
                                                $dataValidation = '';
                                                $titleAttr = '';
                                                $minlengthAttr = '';
                                                $maxlengthAttr = '';

                                                if (!empty($validation_rules['pattern'])) {
                                                    switch ($validation_rules['pattern']) {
                                                        case 'letters':
                                                            $patternAttr = 'pattern="^[A-Za-z\s]+$"';
                                                            $dataValidation = 'data-validation="letters"';
                                                            $titleAttr = 'title="Only letters and spaces are allowed"';
                                                            break;
                                                        case 'numbers':
                                                            $patternAttr = 'pattern="^[0-9]+$"';
                                                            $dataValidation = 'data-validation="numbers"';
                                                            $titleAttr = 'title="Only numbers are allowed"';
                                                            break;
                                                        case 'alphanumeric':
                                                            $patternAttr = 'pattern="^[A-Za-z0-9\s]+$"';
                                                            $dataValidation = 'data-validation="alphanumeric"';
                                                            $titleAttr = 'title="Only letters, numbers, and spaces are allowed"';
                                                            break;
                                                    }
                                                } elseif (!empty($validation_rules['custom_pattern'])) {
                                                    $patternAttr = 'pattern="' . htmlspecialchars($validation_rules['custom_pattern']) . '"';
                                                }

                                                if (!empty($validation_rules['min_length'])) {
                                                    $minlengthAttr = 'minlength="' . (int)$validation_rules['min_length'] . '"';
                                                }
                                                if (!empty($validation_rules['max_length'])) {
                                                    $maxlengthAttr = 'maxlength="' . (int)$validation_rules['max_length'] . '"';
                                                }

                                                echo "<input type='text' class='form-control' name='$field_name' ".
                                                    "placeholder='$placeholder' value='$default_value' $required ".
                                                    "$patternAttr $minlengthAttr $maxlengthAttr $dataValidation $titleAttr>";

                                                if (!empty($validation_rules['pattern'])) {
                                                    $msg = '';
                                                    switch ($validation_rules['pattern']) {
                                                        case 'letters':
                                                            $msg = 'Only letters and spaces are allowed.';
                                                            break;
                                                        case 'numbers':
                                                            $msg = 'Only numbers are allowed.';
                                                            break;
                                                        case 'alphanumeric':
                                                            $msg = 'Only letters, numbers, and spaces are allowed.';
                                                            break;
                                                    }
                                                    if ($msg) {
                                                        echo "<small class='text-muted d-block mt-1'><i class='fas fa-info-circle'></i> $msg</small>";
                                                    }
                                                }
                                                break;
                                            
                                            case 'number':
                                                echo "<input type='number' class='form-control' name='$field_name' placeholder='$placeholder' value='$default_value' $required>";
                                                break;
                                            
                                            case 'textarea':
                                                echo "<textarea class='form-control' name='$field_name' rows='3' placeholder='$placeholder' $required>$default_value</textarea>";
                                                break;
                                            
                                            case 'date':
                                                $date_value = '';
                                                if (strtoupper($default_value) === 'CURRENTDATE') {
                                                    $date_value = date('Y-m-d');
                                                } elseif ($default_value) {
                                                    $date_value = $default_value;
                                                }
                                                echo "<input type='date' class='form-control' name='$field_name' value='$date_value' $required>";
                                                break;
                                            
                                            case 'select':
                                                echo "<select class='form-select' name='$field_name' $required>";
                                                echo "<option value=''>-- Select --</option>";
                                                foreach ($field['options'] as $opt) {
                                                    $opt_value = htmlspecialchars($opt['option_value']);
                                                    $opt_label = htmlspecialchars($opt['option_label']);
                                                    $selected = ($opt['is_default'] ? 'selected' : '');
                                                    echo "<option value='$opt_value' $selected>$opt_label</option>";
                                                }
                                                if ($field['has_other_option']) {
                                                    echo "<option value='Other'>Other (please specify)</option>";
                                                }
                                                echo "</select>";
                                                
                                                if ($field['has_other_option']) {
                                                    echo "<input type='text' class='form-control mt-2' name='{$field_name}_other' placeholder='Specify other' style='display:none' id='{$field_name}_other_input'>";
                                                }
                                                break;
                                            
                                            case 'checkbox':
                                                foreach ($field['options'] as $opt) {
                                                    $opt_value = htmlspecialchars($opt['option_value']);
                                                    $opt_label = htmlspecialchars($opt['option_label']);
                                                    $checked = ($opt['is_default'] ? 'checked' : '');
                                                    echo "<div class='form-check'>
                                                            <input class='form-check-input' type='checkbox' name='{$field_name}[]' value='$opt_value' $checked>
                                                            <label class='form-check-label'>$opt_label</label>
                                                          </div>";
                                                }
                                                break;
                                            
                                            case 'radio':
                                                foreach ($field['options'] as $opt) {
                                                    $opt_value = htmlspecialchars($opt['option_value']);
                                                    $opt_label = htmlspecialchars($opt['option_label']);
                                                    $checked = ($opt['is_default'] ? 'checked' : '');
                                                    echo "<div class='form-check'>
                                                            <input class='form-check-input' type='radio' name='$field_name' value='$opt_value' $checked $required>
                                                            <label class='form-check-label'>$opt_label</label>
                                                          </div>";
                                                }
                                                break;
                                        endswitch;
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="custom_form_fill.php?form_id=<?= $form['custom_form_id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Change Person
                                    </a>
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="fas fa-check"></i> Submit Form
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });

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
                    });
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
        document.addEventListener('beforeinput', function (e) {
            const input = e.target;
            const rule = input.dataset.validation;
            if (!rule) return;

            // Build what the value would be *after* this input
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const oldValue = input.value;
            const inserted = e.data ?? '';   // for deletions this is null
            let newValue;

            if (e.inputType === 'deleteContentBackward') {
                newValue = oldValue.slice(0, start - 1) + oldValue.slice(end);
            } else if (e.inputType === 'deleteContentForward') {
                newValue = oldValue.slice(0, start) + oldValue.slice(end + 1);
            } else {
                newValue = oldValue.slice(0, start) + inserted + oldValue.slice(end);
            }

            let ok = true;

            if (rule === 'letters') {
                ok = /^[A-Za-z\s]*$/.test(newValue);
            } else if (rule === 'numbers') {
                ok = /^[0-9]*$/.test(newValue);
            } else if (rule === 'alphanumeric') {
                ok = /^[A-Za-z0-9\s]*$/.test(newValue);
            }

            if (!ok) {
                e.preventDefault(); // block the change completely
            }
        });
        // Person selection modal trigger
        function selectPerson(formId) {
            window.location.href = 'custom_form_fill.php?form_id=' + formId;
        }
        
        <?php if ($form && !$person): ?>
        // Load persons list
        function loadPersons(search = '') {
            $.post('custom_form_fill.php', {
                ajax: 'get_persons',
                form_id: <?= $form['custom_form_id'] ?>,
                search: search
            }, function(response) {
                if (response.success) {
                    const list = $('#personsList');
                    if (response.persons.length === 0) {
                        list.html('<div class="text-muted text-center p-3">No residents found</div>');
                        return;
                    }
                    
                    list.html(response.persons.map(p => `
                        <a href="custom_form_fill.php?form_id=<?= $form['custom_form_id'] ?>&person_id=${p.person_id}" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <strong>${p.full_name}</strong>
                                <span class="badge bg-secondary">${p.age} yrs</span>
                            </div>
                            <small class="text-muted">${p.gender} • Purok ${p.purok}</small>
                        </a>
                    `).join(''));
                }
            }, 'json');
        }
        
        // Initial load
        loadPersons();
        
        // Search
        $('#personSearch').on('input', function() {
            loadPersons($(this).val());
        });
        <?php endif; ?>
        
        <?php if ($form && $person): ?>
        // Handle "Other" option for select fields
        $('select').on('change', function() {
            const fieldName = $(this).attr('name');
            const otherInput = $(`#${fieldName}_other_input`);
            
            if (otherInput.length) {
                if ($(this).val() === 'Other') {
                    otherInput.show().prop('required', true);
                } else {
                    otherInput.hide().prop('required', false);
                }
            }
        });
        
        // Submit form
        $('#customForm').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $('#submitBtn');
            const originalText = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> Submitting...').prop('disabled', true);
            
            // Collect form data
            const formData = {};
            $(this).find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                if (!name) return;
                
                if ($(this).attr('type') === 'checkbox') {
                    if (!formData[name.replace('[]', '')]) {
                        formData[name.replace('[]', '')] = [];
                    }
                    if ($(this).is(':checked')) {
                        formData[name.replace('[]', '')].push($(this).val());
                    }
                } else if ($(this).attr('type') === 'radio') {
                    if ($(this).is(':checked')) {
                        formData[name] = $(this).val();
                    }
                } else {
                    formData[name] = $(this).val();
                }
            });
            
            // Handle checkbox arrays (join to comma-separated string)
            Object.keys(formData).forEach(key => {
                if (Array.isArray(formData[key])) {
                    formData[key] = formData[key].join(', ');
                }
            });
            
            $.post('custom_form_fill.php', {
                ajax: 'submit_form',
                form_id: <?= $form['custom_form_id'] ?>,
                person_id: <?= $person['person_id'] ?>,
                form_data: JSON.stringify(formData)
            }, function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = 'custom_form_fill.php';
                } else {
                    alert('Error: ' + response.message);
                    btn.html(originalText).prop('disabled', false);
                }
            }, 'json').fail(function() {
                alert('Failed to submit form. Please try again.');
                btn.html(originalText).prop('disabled', false);
            });
        });
        <?php endif; ?>
    </script>
    <script>
        // Fix dropdown functionality
        $(document).ready(function() {
            // Handle dropdown toggle click
            $('.dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $menu = $(this).next('.dropdown-menu');
                
                // Close other dropdowns
                $('.dropdown-menu').not($menu).removeClass('show');
                
                // Toggle this dropdown
                $menu.toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            $('.dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
