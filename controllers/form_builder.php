<?php
/**
 * File: controllers/form_builder.php
 * Form builder interface (Role 4 only)
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/CustomForm.php';
require_once __DIR__ . '/../models/CustomFormField.php';
require_once __DIR__ . '/../models/CustomFormFieldOption.php';

// Only Role 4 can access
requireRole(4);

$customFormModel = new CustomForm();
$fieldModel = new CustomFormField();
$optionModel = new CustomFormFieldOption();

// Get action from URL
$action = $_GET['action'] ?? 'list';
$formId = $_GET['form_id'] ?? null;

// ==================== LIST FORMS ====================
if ($action === 'list') {
    $userId = $_SESSION['user_id'];
    $forms = $customFormModel->getFormsByCreator($userId);
    
    include __DIR__ . '/../views/form_builder/list.php';
}

// ==================== CREATE NEW FORM ====================
elseif ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Invalid security token');
            redirect('/controllers/form_builder.php');
        }
        
        // Sanitize inputs
        $formTitle = sanitize($_POST['form_title']);
        $formDescription = sanitize($_POST['form_description'] ?? '');
        $formCode = generateSlug($formTitle);
        
        // Check if code exists
        if ($customFormModel->codeExists($formCode)) {
            $formCode .= '_' . uniqid();
        }
        
        // Generate record type
        $recordType = 'custom_' . $formCode;
        
        // Create form
        $formData = [
            'form_code' => $formCode,
            'form_title' => $formTitle,
            'form_description' => $formDescription,
            'record_type' => $recordType,
            'created_by' => $_SESSION['user_id'],
            'target_filters' => $_POST['target_filters'] ?? null,
            'allowed_roles' => '1,2',
            'is_active' => 1,
            'requires_purok_match' => isset($_POST['requires_purok_match']) ? 1 : 0,
            'allow_duplicates' => isset($_POST['allow_duplicates']) ? 1 : 0,
            'show_in_dashboard' => 1
        ];
        
        $newFormId = $customFormModel->createForm($formData);
        
        if ($newFormId) {
            setFlash('success', 'Form created successfully! Now add fields to your form.');
            redirect("/controllers/form_builder.php?action=edit&form_id=$newFormId");
        } else {
            setFlash('error', 'Failed to create form');
            redirect('/controllers/form_builder.php');
        }
    }
    
    include __DIR__ . '/../views/form_builder/create.php';
}

// ==================== EDIT FORM (BUILDER UI) ====================
elseif ($action === 'edit' && $formId) {
    $form = $customFormModel->getFormById($formId);
    
    if (!$form) {
        setFlash('error', 'Form not found');
        redirect('/controllers/form_builder.php');
    }
    
    // Check ownership
    if ($form['created_by'] != $_SESSION['user_id']) {
        setFlash('error', 'You do not have permission to edit this form');
        redirect('/controllers/form_builder.php');
    }
    
    // Get existing fields
    $fields = $fieldModel->getFieldsByFormId($formId);
    
    // Get options for each field
    foreach ($fields as &$field) {
        $field['options'] = $optionModel->getOptionsByFieldId($field['field_id']);
    }
    
    include __DIR__ . '/../views/form_builder/edit.php';
}

// ==================== SAVE FIELDS (AJAX) ====================
elseif ($action === 'save_fields' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['form_id']) || !isset($data['fields'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
    }
    
    $formId = (int) $data['form_id'];
    $fields = $data['fields'];
    
    // Verify ownership
    $form = $customFormModel->getFormById($formId);
    if (!$form || $form['created_by'] != $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    try {
        $db = new Database();
        $db->getPDO()->beginTransaction();
        
        // Delete existing fields
        $fieldModel->deleteFieldsByFormId($formId);
        
        // Insert new fields
        foreach ($fields as $index => $fieldData) {
            // Prepare field data
            $newField = [
                'custom_form_id' => $formId,
                'field_name' => $fieldData['name'] ?? $fieldData['field_name'],
                'field_label' => $fieldData['label'] ?? $fieldData['field_label'],
                'field_type' => $fieldData['type'] ?? $fieldData['field_type'],
                'field_order' => $index + 1,
                'is_required' => $fieldData['required'] ?? 0,
                'placeholder' => $fieldData['placeholder'] ?? null,
                'default_value' => $fieldData['default_value'] ?? null,
                'help_text' => $fieldData['help_text'] ?? null,
                'validation_rules' => isset($fieldData['validation_rules']) ? json_encode($fieldData['validation_rules']) : null,
                'conditional_logic' => isset($fieldData['conditional_logic']) ? json_encode($fieldData['conditional_logic']) : null,
                'data_source_type' => $fieldData['data_source_type'] ?? 'static',
                'data_source_config' => isset($fieldData['data_source_config']) ? json_encode($fieldData['data_source_config']) : null,
                'field_group' => $fieldData['field_group'] ?? null,
                'has_other_option' => 0, // Will be detected from options
                'css_classes' => $fieldData['css_classes'] ?? null
            ];
            
            // Create field
            $newFieldId = $fieldModel->createField($newField);
            
            // Handle options for select/dropdown/checkbox/radio fields
            if (isset($fieldData['options']) && is_array($fieldData['options'])) {
                foreach ($fieldData['options'] as $optIndex => $option) {
                    // Check if "Others" option exists
                    if (is_array($option)) {
                        $optLabel = $option['label'] ?? '';
                        $optValue = $option['value'] ?? $option['sub_field'] ?? $optLabel;
                    } else {
                        $optLabel = $option;
                        $optValue = $option;
                    }
                    
                    // Detect "Others" option
                    if (preg_match('/^others?$/i', $optLabel)) {
                        $db->query(
                            "UPDATE custom_form_fields SET has_other_option = 1 WHERE field_id = ?",
                            [$newFieldId]
                        );
                    }
                    
                    $optionData = [
                        'field_id' => $newFieldId,
                        'option_value' => $optValue,
                        'option_label' => $optLabel,
                        'option_order' => $optIndex + 1,
                        'is_default' => 0
                    ];
                    
                    $optionModel->createOption($optionData);
                }
            }
        }
        
        $db->getPDO()->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Form fields saved successfully',
            'csrf_token' => generateCsrfToken()
        ]);
        
    } catch (Exception $e) {
        $db->getPDO()->rollBack();
        error_log("Save fields error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Failed to save fields: ' . $e->getMessage()], 500);
    }
}

// ==================== DELETE FORM ====================
elseif ($action === 'delete' && $formId) {
    $form = $customFormModel->getFormById($formId);
    
    if (!$form || $form['created_by'] != $_SESSION['user_id']) {
        setFlash('error', 'You do not have permission to delete this form');
        redirect('/controllers/form_builder.php');
    }
    
    if ($customFormModel->deleteForm($formId)) {
        setFlash('success', 'Form deleted successfully');
    } else {
        setFlash('error', 'Failed to delete form');
    }
    
    redirect('/controllers/form_builder.php');
}

// ==================== UPDATE FORM SETTINGS ====================
elseif ($action === 'update_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token');
        redirect('/controllers/form_builder.php');
    }
    
    $formId = (int) $_POST['form_id'];
    $form = $customFormModel->getFormById($formId);
    
    if (!$form || $form['created_by'] != $_SESSION['user_id']) {
        setFlash('error', 'Unauthorized');
        redirect('/controllers/form_builder.php');
    }
    
    $updateData = [
        'form_title' => sanitize($_POST['form_title']),
        'form_description' => sanitize($_POST['form_description'] ?? ''),
        'target_filters' => $_POST['target_filters'] ?? null,
        'allowed_roles' => '1,2',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'requires_purok_match' => isset($_POST['requires_purok_match']) ? 1 : 0,
        'allow_duplicates' => isset($_POST['allow_duplicates']) ? 1 : 0,
        'show_in_dashboard' => isset($_POST['show_in_dashboard']) ? 1 : 0
    ];
    
    if ($customFormModel->updateForm($formId, $updateData)) {
        setFlash('success', 'Form settings updated successfully');
    } else {
        setFlash('error', 'Failed to update form settings');
    }
    
    redirect("/controllers/form_builder.php?action=edit&form_id=$formId");
}
