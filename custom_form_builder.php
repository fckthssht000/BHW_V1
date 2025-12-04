<?php
/**
 * File: custom_form_builder.php
 * Complete dynamic form builder with target filters, all field types, and preview
 */
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

// Only Role 4
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role_id'] != 4) {
    header("Location: dashboard.php");
    exit;
}

// ==================== AJAX HANDLERS ====================
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    ob_start();
    
    try {
        // Create form with target filters
        if ($_POST['ajax'] == 'create_form') {
            $form_title = trim($_POST['form_title']);
            $form_description = trim($_POST['form_description'] ?? '');
            $form_code = strtolower(preg_replace('/[^a-z0-9]+/', '_', $form_title));
            $form_code = trim($form_code, '_');
            
            // Check if code exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM custom_forms WHERE form_code = ?");
            $check->execute([$form_code]);
            if ($check->fetchColumn() > 0) {
                $form_code .= '_' . time();
            }

            // Get user-defined record_type
            $record_type = trim($_POST['record_type']);
            $record_type = strtolower(preg_replace('/[^a-z0-9_]/', '_', $record_type));
            $record_type = trim($record_type, '_');

            if (empty($record_type)) {
                throw new Exception('Record type is required');
            }

            // Check if record_type already exists
            $check_rt = $pdo->prepare("SELECT COUNT(*) FROM custom_forms WHERE record_type = ?");
            $check_rt->execute([$record_type]);
            if ($check_rt->fetchColumn() > 0) {
                throw new Exception('Record type already exists. Please use a unique record type.');
            }
            
            // Parse target filters
            $target_filters = [];
            
            // Age range
            if (!empty($_POST['age_min']) || !empty($_POST['age_max'])) {
                $target_filters['age_min'] = (int)($_POST['age_min'] ?? 0);
                $target_filters['age_max'] = (int)($_POST['age_max'] ?? 120);
            }
            
            // Gender
            if (!empty($_POST['gender'])) {
                $target_filters['gender'] = $_POST['gender'];
            }
            
            // Civil Status
            if (!empty($_POST['civil_status']) && is_array($_POST['civil_status'])) {
                $target_filters['civil_status'] = $_POST['civil_status'];
            }
            
            if (isset($target_filters['relationship_type'])) {
                $filters_display[] = "Relationship: {$target_filters['relationship_type']}";
            }
            
            $target_filters_json = !empty($target_filters) ? json_encode($target_filters) : null;
            
            // Allowed roles (who can fill this form)
            $allowed_roles = isset($_POST['allowed_roles']) && is_array($_POST['allowed_roles']) 
                ? implode(',', $_POST['allowed_roles']) 
                : '1,2';
            
            $stmt = $pdo->prepare("
                INSERT INTO custom_forms 
                (form_code, form_title, form_description, record_type, target_filters, 
                 created_by, allowed_roles, is_active, requires_purok_match, 
                 allow_duplicates, show_in_dashboard)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)
            ");
            
            $stmt->execute([
                $form_code,
                $form_title,
                $form_description,
                $record_type,
                $target_filters_json,
                $_SESSION['user_id'],
                $allowed_roles,
                isset($_POST['requires_purok_match']) ? 1 : 0,
                isset($_POST['allow_duplicates']) ? 1 : 0
            ]);
            
            $form_id = $pdo->lastInsertId();
            
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'form_id' => $form_id, 
                'message' => 'Form created successfully'
            ]);
            exit;
        }
        
        // Update form settings
        if ($_POST['ajax'] == 'update_form_settings') {
            $form_id = (int)$_POST['form_id'];
            
            // Verify ownership
            $check = $pdo->prepare("SELECT created_by FROM custom_forms WHERE custom_form_id = ?");
            $check->execute([$form_id]);
            $form = $check->fetch();
            
            if (!$form || $form['created_by'] != $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            // Parse target filters
            $target_filters = [];
            
            if (!empty($_POST['age_min']) || !empty($_POST['age_max'])) {
                $target_filters['age_min'] = (int)($_POST['age_min'] ?? 0);
                $target_filters['age_max'] = (int)($_POST['age_max'] ?? 120);
            }
            
            if (!empty($_POST['gender'])) {
                $target_filters['gender'] = $_POST['gender'];
            }
            
            if (!empty($_POST['civil_status']) && is_array($_POST['civil_status'])) {
                $target_filters['civil_status'] = $_POST['civil_status'];
            }
            
            $target_filters_json = !empty($target_filters) ? json_encode($target_filters) : null;
            
            $allowed_roles = isset($_POST['allowed_roles']) && is_array($_POST['allowed_roles']) 
                ? implode(',', $_POST['allowed_roles']) 
                : '1,2';
            
            $stmt = $pdo->prepare("
                UPDATE custom_forms SET
                    form_title = ?,
                    form_description = ?,
                    target_filters = ?,
                    allowed_roles = ?,
                    is_active = ?,
                    requires_purok_match = ?,
                    allow_duplicates = ?,
                    show_in_dashboard = ?
                WHERE custom_form_id = ?
            ");
            
            $stmt->execute([
                trim($_POST['form_title']),
                trim($_POST['form_description'] ?? ''),
                $target_filters_json,
                $allowed_roles,
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['requires_purok_match']) ? 1 : 0,
                isset($_POST['allow_duplicates']) ? 1 : 0,
                isset($_POST['show_in_dashboard']) ? 1 : 0,
                $form_id
            ]);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            exit;
        }
        
        // Save fields
        if ($_POST['ajax'] == 'save_fields') {
            $form_id = (int)$_POST['form_id'];
            $fields = json_decode($_POST['fields'], true);
            
            $check = $pdo->prepare("SELECT created_by FROM custom_forms WHERE custom_form_id = ?");
            $check->execute([$form_id]);
            $form = $check->fetch();
            
            if (!$form || $form['created_by'] != $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            $pdo->beginTransaction();
            
            // Delete existing
            $pdo->prepare("DELETE FROM custom_form_field_options WHERE field_id IN (SELECT field_id FROM custom_form_fields WHERE custom_form_id = ?)")->execute([$form_id]);
            $pdo->prepare("DELETE FROM custom_form_fields WHERE custom_form_id = ?")->execute([$form_id]);
            
            // Insert new fields
            foreach ($fields as $index => $field) {
                $stmt = $pdo->prepare("
                    INSERT INTO custom_form_fields 
                    (custom_form_id, field_name, field_label, field_type, field_order, 
                     is_required, placeholder, default_value, help_text, 
                     validation_rules, conditional_logic, data_source_type, data_source_config,
                     field_group, has_other_option, css_classes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $field_name = $field['field_name'] ?? $field['name'];
                $field_label = $field['field_label'] ?? $field['label'];
                $field_type = $field['field_type'] ?? $field['type'];
                
                // Validation rules (for number fields)
                $validation_rules = null;
                if (isset($field['validation_rules'])) {
                    $validation_rules = json_encode($field['validation_rules']);
                } elseif ($field_type === 'number' && (isset($field['min']) || isset($field['max']))) {
                    $validation_rules = json_encode([
                        'min' => $field['min'] ?? null,
                        'max' => $field['max'] ?? null
                    ]);
                }
                
                $stmt->execute([
                    $form_id,
                    $field_name,
                    $field_label,
                    $field_type,
                    $index + 1,
                    $field['is_required'] ?? 0,
                    $field['placeholder'] ?? '',
                    $field['default_value'] ?? '',
                    $field['help_text'] ?? '',
                    $validation_rules,
                    isset($field['conditional_logic']) ? json_encode($field['conditional_logic']) : null,
                    $field['data_source_type'] ?? 'static',
                    isset($field['data_source_config']) ? json_encode($field['data_source_config']) : null,
                    $field['field_group'] ?? null,
                    0,
                    $field['css_classes'] ?? null
                ]);
                
                $field_id = $pdo->lastInsertId();
                
                // Insert options for select, checkbox_group, radio
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $opt_index => $option) {
                        $opt_label = is_array($option) ? ($option['option_label'] ?? $option['label']) : $option;
                        $opt_value = is_array($option) ? ($option['option_value'] ?? $option['value'] ?? $opt_label) : $option;
                        
                        if (preg_match('/^others?$/i', $opt_label)) {
                            $pdo->prepare("UPDATE custom_form_fields SET has_other_option = 1 WHERE field_id = ?")->execute([$field_id]);
                        }
                        
                        $opt_stmt = $pdo->prepare("
                            INSERT INTO custom_form_field_options 
                            (field_id, option_value, option_label, option_order, is_default)
                            VALUES (?, ?, ?, ?, 0)
                        ");
                        $opt_stmt->execute([$field_id, $opt_value, $opt_label, $opt_index + 1]);
                    }
                }
            }
            
            $pdo->commit();
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Form fields saved successfully']);
            exit;
        }
        
        // Delete form (HARD DELETE)
        if ($_POST['ajax'] == 'delete_form') {
            $form_id = (int)$_POST['form_id'];
            
            $check = $pdo->prepare("SELECT created_by FROM custom_forms WHERE custom_form_id = ?");
            $check->execute([$form_id]);
            $form = $check->fetch();
            
            if (!$form || $form['created_by'] != $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Delete related data first (foreign keys)
                $pdo->prepare("DELETE FROM custom_form_field_options WHERE field_id IN (SELECT field_id FROM custom_form_fields WHERE custom_form_id = ?)")->execute([$form_id]);
                $pdo->prepare("DELETE FROM custom_form_fields WHERE custom_form_id = ?")->execute([$form_id]);
                $pdo->prepare("DELETE FROM custom_form_submissions WHERE custom_form_id = ?")->execute([$form_id]);
                
                // Delete the form itself
                $pdo->prepare("DELETE FROM custom_forms WHERE custom_form_id = ?")->execute([$form_id]);
                
                $pdo->commit();
                
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Form deleted successfully']);
                exit;
            }catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        // Toggle form active status
        if ($_POST['ajax'] == 'toggle_active') {
            $form_id = (int)$_POST['form_id'];
            
            $check = $pdo->prepare("SELECT created_by, is_active FROM custom_forms WHERE custom_form_id = ?");
            $check->execute([$form_id]);
            $form = $check->fetch();
            
            if (!$form || $form['created_by'] != $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            $new_status = $form['is_active'] ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE custom_forms SET is_active = ? WHERE custom_form_id = ?");
            $stmt->execute([$new_status, $form_id]);
            
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'is_active' => $new_status,
                'message' => $new_status ? 'Form activated' : 'Form deactivated'
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ==================== GET DATA ====================
$stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE created_by = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editing_form = null;
$editing_fields = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $form_id = (int)$_GET['edit'];
    
    $stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE custom_form_id = ? AND created_by = ?");
    $stmt->execute([$form_id, $_SESSION['user_id']]);
    $editing_form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editing_form) {
        $fields_stmt = $pdo->prepare("SELECT * FROM custom_form_fields WHERE custom_form_id = ? ORDER BY field_order ASC");
        $fields_stmt->execute([$form_id]);
        $editing_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($editing_fields as &$field) {
            $opts_stmt = $pdo->prepare("SELECT * FROM custom_form_field_options WHERE field_id = ? ORDER BY option_order ASC");
            $opts_stmt->execute([$field['field_id']]);
            $field['options'] = $opts_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            if ($field['validation_rules']) {
                $field['validation_rules'] = json_decode($field['validation_rules'], true);
            }
        }
        unset($field);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing_form ? 'Edit Form' : 'Custom Forms' ?> - BRGYCare</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        /* Builder Container */
        .builder-container { display: flex; gap: 20px; margin: 20px 0; min-height: 600px; }
        .builder-sidebar { width: 300px; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-height: calc(100vh - 150px); overflow-y: auto; }
        .builder-canvas { flex: 1; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-height: 500px; border: 2px dashed #ddd; }
        .builder-canvas.empty { display: flex; align-items: center; justify-content: center; color: #999; }
        
        /* Field Palette */
        .field-palette-section { margin-bottom: 20px; }
        .field-palette-section h6 { font-size: 13px; font-weight: 600; color: #666; text-transform: uppercase; margin-bottom: 10px; }
        .field-type-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; margin-bottom: 8px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; cursor: move; transition: all 0.2s; font-size: 14px; }
        .field-type-item:hover { background: #e9ecef; border-color: #0d6efd; transform: translateX(5px); box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2); }
        .field-type-item i { width: 20px; color: #6c757d; }
        .field-type-item.dragging { opacity: 0.5; }
        
        /* Field Items in Canvas */
        .field-item-container { position: relative; margin-bottom: 15px; padding: 15px 20px; background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 8px; transition: all 0.2s; }
        .field-item-container:hover { border-color: #0d6efd; box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15); }
        
        .field-mini-panel { position: absolute; top: -12px; right: 15px; display: flex; gap: 5px; background: white; padding: 5px 10px; border-radius: 6px; border: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .field-control-btn { padding: 5px 10px; font-size: 12px; border: none; background: transparent; cursor: pointer; color: #6c757d; transition: color 0.2s; border-radius: 4px; }
        .field-control-btn:hover { color: #0d6efd; background: #f8f9fa; }
        .field-control-btn.delete-btn:hover { color: #dc3545; }
        
        .sortable-ghost { opacity: 0.4; background: #e9ecef; }
        .empty-state { text-align: center; padding: 80px 20px; }
        .empty-state i { font-size: 64px; color: #ced4da; margin-bottom: 20px; display: block; }
        .empty-state h5 { color: #6c757d; margin-bottom: 10px; }
        .empty-state p { color: #adb5bd; font-size: 14px; }
        
        /* Form Cards */
        .form-card { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; transition: all 0.2s; }
        .form-card:hover { border-color: #0d6efd; box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15); }
        
        /* Target Filters Section */
        .filters-section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
        .filters-section h6 { font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #495057; }
        
        /* Toggle Switch */
        .form-switch .form-check-input { width: 44px; height: 24px; cursor: pointer; }
        
        /* Preview Modal */
        .person-info { background: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; border-radius: 4px; }
        #previewFieldsContainer .form-label { font-weight: 600; }
        #previewFieldsContainer .form-control:disabled,
        #previewFieldsContainer .form-select:disabled,
        #previewFieldsContainer .form-check-input:disabled {
            background-color: #e9ecef;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <?php if (!$editing_form): ?>
            <!-- ==================== LIST FORMS VIEW ==================== -->
            <div class="d-flex justify-content-between align-items-center my-4">
                <div>
                    <h3><i class="fas fa-clipboard-list"></i> Custom Forms</h3>
                    <p class="text-muted mb-0">Create and manage dynamic forms for your barangay</p>
                </div>
                <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#createFormModal">
                    <i class="fas fa-plus"></i> Create New Form
                </button>
            </div>
            
            <?php if (empty($forms)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't created any custom forms yet. Click "Create New Form" to get started!
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($forms as $form): ?>
                        <?php
                        $target_filters = $form['target_filters'] ? json_decode($form['target_filters'], true) : [];
                        $filters_display = [];
                        if (isset($target_filters['age_min']) || isset($target_filters['age_max'])) {
                            $filters_display[] = "Age: {$target_filters['age_min']}-{$target_filters['age_max']}";
                        }
                        if (isset($target_filters['gender'])) {
                            $filters_display[] = "Gender: {$target_filters['gender']}";
                        }
                        if (isset($target_filters['civil_status'])) {
                            $filters_display[] = "Civil Status: " . implode(', ', $target_filters['civil_status']);
                        }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="form-card h-100">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0"><?= htmlspecialchars($form['form_title']) ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="?edit=<?= $form['custom_form_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit Builder
                                            </a></li>
                                            <li>
                                                <a class="dropdown-item"
                                                href="custom_form_builder.php?edit=<?= $form['custom_form_id'] ?>&preview=1">
                                                    <i class="fas fa-eye"></i> Preview Form
                                                </a>
                                            </li>
                                            <li><a class="dropdown-item" href="custom_form_submissions.php?form_id=<?= $form['custom_form_id'] ?>">
                                                <i class="fas fa-list"></i> View Submissions
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="toggleFormActive(<?= $form['custom_form_id'] ?>, <?= $form['is_active'] ? 'true' : 'false' ?>); return false;">
                                                    <i class="fas fa-<?= $form['is_active'] ? 'toggle-off' : 'toggle-on' ?>"></i> 
                                                    <?= $form['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </li></a>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteForm(<?= $form['custom_form_id'] ?>); return false;">
                                                <i class="fas fa-trash"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="text-muted small mb-3"><?= htmlspecialchars($form['form_description'] ?? 'No description') ?></p>
                                
                                <div class="mb-2">
                                    <small><strong>Form Code:</strong> de><?= htmlspecialchars($form['form_code']) ?></code></small>
                                </div>
                                <div class="mb-2">
                                    <small><strong>Record Type:</strong> de><?= htmlspecialchars($form['record_type']) ?></code></small>
                                </div>
                                
                                <?php if (!empty($filters_display)): ?>
                                    <div class="mb-2">
                                        <small><strong>Target Filters:</strong></small><br>
                                        <?php foreach ($filters_display as $filter): ?>
                                            <span class="badge bg-info me-1"><?= htmlspecialchars($filter) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2 align-items-center mt-3">
                                    <span class="badge <?= $form['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $form['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                    <?php if ($form['requires_purok_match']): ?>
                                        <span class="badge bg-warning text-dark">Purok Restricted</span>
                                    <?php endif; ?>
                                    <?php if ($form['allow_duplicates']): ?>
                                        <span class="badge bg-info">Duplicates Allowed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- ==================== FORM BUILDER VIEW ==================== -->
            <div class="d-flex justify-content-between align-items-center py-3 border-bottom bg-white px-3 rounded mt-3">
                <div>
                    <h4 class="mb-0"><i class="fas fa-edit"></i> <?= htmlspecialchars($editing_form['form_title']) ?></h4>
                    <small class="text-muted">Form Code: de><?= htmlspecialchars($editing_form['form_code']) ?></code></small>
                </div>
                <div class="btn-toolbar gap-2">
                    <a href="custom_form_builder.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cog"></i> Settings
                    </button>
                    <button type="button" class="btn btn-success" id="saveFormBtn">
                        <i class="fas fa-save"></i> Save Form
                    </button>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#previewModal">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
            </div>
            
            <div class="builder-container">
                <!-- Field Palette Sidebar -->
                <div class="builder-sidebar">
                    <div class="field-palette-section">
                        <h6><i class="fas fa-keyboard"></i> Input Fields</h6>
                        <div class="field-type-item" data-field-type="text" draggable="true">
                            <i class="fas fa-font"></i>
                            <span>Text Input</span>
                        </div>
                        <div class="field-type-item" data-field-type="textarea" draggable="true">
                            <i class="fas fa-align-left"></i>
                            <span>Text Area</span>
                        </div>
                        <div class="field-type-item" data-field-type="number" draggable="true">
                            <i class="fas fa-hashtag"></i>
                            <span>Number</span>
                        </div>
                        <div class="field-type-item" data-field-type="date" draggable="true">
                            <i class="fas fa-calendar"></i>
                            <span>Date</span>
                        </div>
                    </div>
                    
                    <div class="field-palette-section">
                        <h6><i class="fas fa-list-ul"></i> Selection Fields</h6>
                        <div class="field-type-item" data-field-type="select" draggable="true">
                            <i class="fas fa-list"></i>
                            <span>Dropdown</span>
                        </div>
                        <div class="field-type-item" data-field-type="select2" draggable="true">
                            <i class="fas fa-search"></i>
                            <span>Select2 (Searchable)</span>
                        </div>
                        <div class="field-type-item" data-field-type="checkbox_group" draggable="true">
                            <i class="fas fa-check-square"></i>
                            <span>Checkbox Group</span>
                        </div>
                        <div class="field-type-item" data-field-type="radio" draggable="true">
                            <i class="fas fa-dot-circle"></i>
                            <span>Radio Buttons</span>
                        </div>
                    </div>
                    
                    <div class="field-palette-section">
                        <h6><i class="fas fa-toggle-on"></i> Special Fields</h6>
                        <div class="field-type-item" data-field-type="toggle" draggable="true">
                            <i class="fas fa-toggle-on"></i>
                            <span>Toggle Switch</span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small mt-3">
                        <i class="fas fa-info-circle"></i> <strong>Tip:</strong> Drag fields to the canvas, then click to edit properties.
                    </div>
                </div>
                
                <!-- Canvas -->
                <div class="builder-canvas <?= empty($editing_fields) ? 'empty' : '' ?>" id="builderCanvas">
                    <?php if (empty($editing_fields)): ?>
                        <div class="empty-state">
                            <i class="fas fa-plus-circle"></i>
                            <h5>Start Building Your Form</h5>
                            <p>Drag field types from the left panel to add them here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ==================== CREATE FORM MODAL ==================== -->
    <div class="modal fade" id="createFormModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Custom Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createFormForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Form Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="form_title" required placeholder="e.g., COVID-19 Vaccination Record">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="form_description" rows="2" placeholder="Brief description of this form's purpose"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Record Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="record_type" required placeholder="e.g., covid_vaccination, dental_checkup">
                            <small class="text-muted">Unique identifier for this form type (lowercase, underscores only). This will be stored in the records table.</small>
                        </div>
                        
                        <div class="filters-section">
                            <h6><i class="fas fa-filter"></i> Target Filters (Who can use this form?)</h6>
                            <p class="small text-muted mb-3">Define criteria to filter eligible residents for this form</p>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Minimum Age</label>
                                    <input type="number" class="form-control form-control-sm" name="age_min" min="0" max="120" placeholder="e.g., 18">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Maximum Age</label>
                                    <input type="number" class="form-control form-control-sm" name="age_max" min="0" max="120" placeholder="e.g., 60">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Gender</label>
                                <select class="form-select form-select-sm" name="gender">
                                    <option value="">Any</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Civil Status</label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="civil_status[]" value="Single" id="cs_single">
                                            <label class="form-check-label small" for="cs_single">Single</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="civil_status[]" value="Married" id="cs_married">
                                            <label class="form-check-label small" for="cs_married">Married</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="civil_status[]" value="Widowed" id="cs_widowed">
                                            <label class="form-check-label small" for="cs_widowed">Widowed</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="civil_status[]" value="Separated" id="cs_separated">
                                            <label class="form-check-label small" for="cs_separated">Separated</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small">Relationship Type (Household)</label>
                                <select class="form-select form-select-sm" name="relationship_type">
                                    <option value="">Any</option>
                                    <option value="Head">Head of Household Only</option>
                                    <option value="Spouse">Spouse Only</option>
                                    <option value="Child">Children Only</option>
                                    <option value="Parent">Parents Only</option>
                                    <option value="Sibling">Siblings Only</option>
                                </select>
                                <small class="text-muted">Filter by relationship to household head (like household_form.php)</small>
                            </div>
                        </div>
                        
                        <div class="filters-section">
                            <h6><i class="fas fa-users"></i> Who Can Fill This Form?</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="1" id="role1" checked>
                                <label class="form-check-label" for="role1">
                                    <strong>Role 1</strong> - Barangay Health Worker (Full Access)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="2" id="role2" checked>
                                <label class="form-check-label" for="role2">
                                    <strong>Role 2</strong> - Purok Health Worker (Purok-Restricted)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_purok_match" id="requires_purok" checked>
                                <label class="form-check-label" for="requires_purok">
                                    <strong>Role 2 can only see their purok's data</strong>
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">When enabled, Role 2 users will only see residents from their assigned purok</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_duplicates" id="allow_dup">
                                <label class="form-check-label" for="allow_dup">
                                    <strong>Allow multiple submissions per person</strong>
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">Uncheck if each person should only have one record of this form</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Create Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ==================== SETTINGS MODAL ==================== -->
    <?php if ($editing_form): ?>
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog"></i> Form Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="settingsForm">
                    <input type="hidden" name="form_id" value="<?= $editing_form['custom_form_id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Form Title</label>
                            <input type="text" class="form-control" name="form_title" value="<?= htmlspecialchars($editing_form['form_title']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="form_description" rows="2"><?= htmlspecialchars($editing_form['form_description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Record Type</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($editing_form['record_type']) ?>" disabled>
                            <small class="text-muted">Record type cannot be changed after creation</small>
                        </div>
                        
                        <?php 
                        $current_filters = $editing_form['target_filters'] ? json_decode($editing_form['target_filters'], true) : [];
                        $current_roles = explode(',', $editing_form['allowed_roles']);
                        ?>
                        
                        <div class="filters-section">
                            <h6><i class="fas fa-filter"></i> Target Filters</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Minimum Age</label>
                                    <input type="number" class="form-control form-control-sm" name="age_min" value="<?= $current_filters['age_min'] ?? '' ?>" min="0" max="120">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Maximum Age</label>
                                    <input type="number" class="form-control form-control-sm" name="age_max" value="<?= $current_filters['age_max'] ?? '' ?>" min="0" max="120">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Gender</label>
                                <select class="form-select form-select-sm" name="gender">
                                    <option value="">Any</option>
                                    <option value="M" <?= ($current_filters['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Male</option>
                                    <option value="F" <?= ($current_filters['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Civil Status</label>
                                <div class="row">
                                    <?php
                                    $civil_statuses = ['Single', 'Married', 'Widowed', 'Separated'];
                                    $selected_cs = $current_filters['civil_status'] ?? [];
                                    foreach ($civil_statuses as $cs):
                                    ?>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="civil_status[]" value="<?= $cs ?>" 
                                                       id="cs_<?= strtolower($cs) ?>_edit" <?= in_array($cs, $selected_cs) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="cs_<?= strtolower($cs) ?>_edit"><?= $cs ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Relationship Type</label>
                            <select class="form-select form-select-sm" name="relationship_type">
                                <option value="">Any</option>
                                <option value="Head" <?= ($current_filters['relationship_type'] ?? '') === 'Head' ? 'selected' : '' ?>>Head of Household Only</option>
                                <option value="Spouse" <?= ($current_filters['relationship_type'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse Only</option>
                                <option value="Child" <?= ($current_filters['relationship_type'] ?? '') === 'Child' ? 'selected' : '' ?>>Children Only</option>
                                <option value="Parent" <?= ($current_filters['relationship_type'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parents Only</option>
                                <option value="Sibling" <?= ($current_filters['relationship_type'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Siblings Only</option>
                            </select>
                        </div>
                        
                        <div class="filters-section">
                            <h6><i class="fas fa-users"></i> Allowed Roles</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="1" id="role1_edit" <?= in_array('1', $current_roles) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="role1_edit">Role 1 - BHW</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="2" id="role2_edit" <?= in_array('2', $current_roles) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="role2_edit">Role 2 - Purok Health Worker</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $editing_form['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Form is Active</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_purok_match" id="requires_purok_edit" <?= $editing_form['requires_purok_match'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="requires_purok_edit">Purok Restriction (Role 2)</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_duplicates" id="allow_dup_edit" <?= $editing_form['allow_duplicates'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allow_dup_edit">Allow Duplicates</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_in_dashboard" id="show_dash" <?= $editing_form['show_in_dashboard'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_dash">Show in Dashboard</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ==================== FIELD EDIT MODAL ==================== -->
    <div class="modal fade" id="fieldEditModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="fieldEditBody">
                   <div class="form-group" id="validationGroup" style="display:none;">
                        <label>Validation Rules</label>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allowLettersOnly" value="letters">
                            <label class="form-check-label" for="allowLettersOnly">
                                Letters only (no numbers or special characters)
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allowNumbersOnly" value="numbers">
                            <label class="form-check-label" for="allowNumbersOnly">
                                Numbers only
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allowAlphanumeric" value="alphanumeric">
                            <label class="form-check-label" for="allowAlphanumeric">
                                Alphanumeric only (letters and numbers, no special characters)
                            </label>
                        </div>
                        
                        <div class="form-group mt-2">
                            <label for="minLength">Minimum Length</label>
                            <input type="number" class="form-control" id="minLength" placeholder="e.g., 2">
                        </div>
                        
                        <div class="form-group">
                            <label for="maxLength">Maximum Length</label>
                            <input type="number" class="form-control" id="maxLength" placeholder="e.g., 50">
                        </div>
                        
                        <div class="form-group">
                            <label for="customPattern">Custom Pattern (Regex)</label>
                            <input type="text" class="form-control" id="customPattern" placeholder="e.g., ^[A-Za-z\s]+$">
                            <small class="text-muted">For advanced users</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ==================== PREVIEW MODAL ==================== -->
    <?php if ($editing_form): ?>
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye"></i> Form Preview: 
                        <span id="previewFormTitle"><?= htmlspecialchars($editing_form['form_title']) ?></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background: #f5f5f5; max-height: 70vh; overflow-y: auto;">
                    <!-- Preview Header -->
                    <div class="alert alert-info mb-3">
                        <strong><i class="fas fa-info-circle"></i> Preview Mode</strong><br>
                        <small>This is how the form will appear to staff when filling it out. Fields are disabled in preview mode.</small>
                    </div>
                    
                    <!-- Sample Person Info -->
                    <div class="person-info mb-3">
                        <strong><i class="fas fa-user"></i> Sample Resident:</strong> 
                        Juan Dela Cruz (35 yrs, Male)
                        <br>
                        <small>Purok: Purok 1</small>
                    </div>
                    
                    <!-- Form Preview Container -->
                    <div class="card">
                        <div class="card-body">
                            <form id="previewForm">
                                <div id="previewFieldsContainer">
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-plus-circle fa-3x mb-3"></i>
                                        <p>No fields added yet. Add fields to see the preview.</p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close Preview
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <?php if ($editing_form): ?>
    <script>
        window.formData = {
            formId: <?= $editing_form['custom_form_id'] ?>,
            formCode: '<?= htmlspecialchars($editing_form['form_code']) ?>',
            formTitle: '<?= htmlspecialchars($editing_form['form_title']) ?>',
            fields: <?= json_encode($editing_fields) ?>
        };
    </script>
    <script src="assets/js/custom_form_builder.js?v=<?= time() ?>"></script>
    
    <script>
        // Settings form submission
        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&ajax=update_form_settings';
            
            $.post('custom_form_builder.php', formData, function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json');
        });
        
        // Preview Modal Handler
        $('#previewModal').on('show.bs.modal', function() {
            updatePreview();
        });
        
        // Update preview
        function updatePreview() {
            const container = $('#previewFieldsContainer');
            
            if (!window.formBuilder || !window.formBuilder.fields || window.formBuilder.fields.length === 0) {
                container.html(`
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-plus-circle fa-3x mb-3"></i>
                        <p>No fields added yet. Add fields to see the preview.</p>
                    </div>
                `);
                return;
            }
            
            const fields = window.formBuilder.fields;
            let html = '';
            
            fields.forEach(field => {
                html += `<div class="mb-3">`;

                if (field.field_group) {
                    html += `<div class="text-muted small mb-1"><strong>${escapeHtml(field.field_group)}</strong></div>`;
                }
                
                html += `<label class="form-label">
                    ${escapeHtml(field.field_label)}
                    ${field.is_required ? '<span class="text-danger">*</span>' : ''}
                </label>`;
                
                if (field.help_text) {
                    html += `<small class="d-block text-muted mb-1">${escapeHtml(field.help_text)}</small>`;
                }
                
                const placeholder = field.placeholder || '';
                const defaultVal = field.default_value || '';
                
                switch (field.field_type) {
                    case 'text':
                        html += `<input type="text" class="form-control" placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(defaultVal)}" disabled>`;
                        break;
                    
                    case 'textarea':
                        html += `<textarea class="form-control" rows="3" placeholder="${escapeHtml(placeholder)}" disabled>${escapeHtml(defaultVal)}</textarea>`;
                        break;
                    
                    case 'number':
                        const min = field.validation_rules?.min || '';
                        const max = field.validation_rules?.max || '';
                        html += `<input type="number" class="form-control" placeholder="${escapeHtml(placeholder)}" 
                                 value="${escapeHtml(defaultVal)}" min="${min}" max="${max}" disabled>`;
                        break;
                    
                    case 'date':
                        const dateVal = defaultVal.toUpperCase() === 'CURRENTDATE' ? new Date().toISOString().split('T')[0] : defaultVal;
                        html += `<input type="date" class="form-control" value="${dateVal}" disabled>`;
                        break;
                    
                    case 'select':
                    case 'select2':
                        html += `<select class="form-select" disabled>
                            <option value="">-- Select --</option>`;
                        (field.options || []).forEach(opt => {
                            const selected = opt.is_default ? 'selected' : '';
                            html += `<option ${selected}>${escapeHtml(opt.option_label)}</option>`;
                        });
                        if (field.has_other_option) {
                            html += `<option>Other (please specify)</option>`;
                        }
                        html += `</select>`;
                        if (field.field_type === 'select2') {
                            html += `<small class="text-muted d-block mt-1"><i class="fas fa-search"></i> Searchable dropdown in live form</small>`;
                        }
                        break;
                    
                    case 'checkbox_group':
                        (field.options || []).forEach(opt => {
                            const checked = opt.is_default ? 'checked' : '';
                            html += `<div class="form-check">
                                <input class="form-check-input" type="checkbox" ${checked} disabled>
                                <label class="form-check-label">${escapeHtml(opt.option_label)}</label>
                            </div>`;
                        });
                        break;
                    
                    case 'radio':
                        (field.options || []).forEach(opt => {
                            const checked = opt.is_default ? 'checked' : '';
                            html += `<div class="form-check">
                                <input class="form-check-input" type="radio" name="preview_${field.field_name}" ${checked} disabled>
                                <label class="form-check-label">${escapeHtml(opt.option_label)}</label>
                            </div>`;
                        });
                        break;
                    
                    case 'toggle':
                        const toggleChecked = defaultVal ? 'checked' : '';
                        html += `<div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" ${toggleChecked} disabled>
                            <label class="form-check-label">Toggle On/Off</label>
                        </div>`;
                        break;
                    
                    default:
                        html += `<input type="text" class="form-control" placeholder="${escapeHtml(placeholder)}" disabled>`;
                }
                
                html += `</div>`;
            });
            
            container.html(html);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        // Auto-open preview when coming from list with ?preview=1
        $(document).ready(function () {
            const params = new URLSearchParams(window.location.search);
            if (params.get('preview') === '1') {
                $('#previewModal').on('shown.bs.modal', function () {
                    updatePreview();
                });
                $('#previewModal').modal('show');
            }
        });
    </script>
    <?php else: ?>
    <script>
        // Create form submission
        $('#createFormForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&ajax=create_form';
            
            $.post('custom_form_builder.php', formData, function(response) {
                if (response.success) {
                    window.location.href = 'custom_form_builder.php?edit=' + response.form_id;
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json');
        });
        
        // Delete form
        function deleteForm(formId) {
            if (!confirm('Are you sure you want to delete this form? This cannot be undone.')) return;
            
            $.post('custom_form_builder.php', {
                ajax: 'delete_form',
                form_id: formId
            }, function(response) {
                if (response.success) {
                    alert(response.message || 'Form deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to delete form'));
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Delete failed:', error);
                console.log('Response:', xhr.responseText);
                alert('Failed to delete form. Check console for details.');
            });
        }
        // Toggle form active/inactive
        function toggleFormActive(formId, currentStatus) {
            const action = currentStatus ? 'deactivate' : 'activate';
            if (!confirm(`Are you sure you want to ${action} this form?`)) return;
            
            $.post('custom_form_builder.php', {
                ajax: 'toggle_active',
                form_id: formId
            }, function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Toggle failed:', error);
                console.log('Response:', xhr.responseText);
                alert('Failed to toggle form status. Check console for details.');
            });
        }
    </script>
    <?php endif; ?>
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
