<?php
/**
 * File: controllers/form_renderer.php
 * Render and submit custom forms (Role 1 & 2)
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/CustomForm.php';
require_once __DIR__ . '/../models/CustomFormField.php';
require_once __DIR__ . '/../models/CustomFormFieldOption.php';
require_once __DIR__ . '/../models/CustomFormSubmission.php';

// Only Role 1 & 2 can access
if (!canFillForms()) {
    setFlash('error', 'You do not have permission to access this page');
    redirect('/dashboard.php');
}

$customFormModel = new CustomForm();
$fieldModel = new CustomFormField();
$optionModel = new CustomFormFieldOption();
$submissionModel = new CustomFormSubmission();

$action = $_GET['action'] ?? 'list';
$formId = $_GET['form_id'] ?? null;
$personId = $_GET['person_id'] ?? null;

// ==================== LIST AVAILABLE FORMS ====================
if ($action === 'list') {
    $forms = $customFormModel->getActiveForms();
    include __DIR__ . '/../views/form_renderer/list.php';
}

// ==================== FILL FORM ====================
elseif ($action === 'fill' && $formId && $personId) {
    $form = $customFormModel->getFormById($formId);
    
    if (!$form || !$form['is_active']) {
        setFlash('error', 'Form not found or inactive');
        redirect('/controllers/form_renderer.php');
    }
    
    // Get person data
    $db = new Database();
    $person = $db->fetch(
        "SELECT p.*, a.purok FROM person p 
         JOIN address a ON p.address_id = a.address_id 
         WHERE p.person_id = ?",
        [$personId]
    );
    
    if (!$person) {
        setFlash('error', 'Person not found');
        redirect('/controllers/form_renderer.php');
    }
    
    // Role 2 purok check
    if (needsPurokFilter() && $form['requires_purok_match']) {
        $userPurok = getUserPurok();
        if ($person['purok'] !== $userPurok) {
            setFlash('error', 'You can only fill forms for residents in your purok');
            redirect('/controllers/form_renderer.php');
        }
    }
    
    // Check for duplicates if not allowed
    if (!$form['allow_duplicates']) {
        if ($submissionModel->submissionExists($formId, $personId)) {
            setFlash('error', 'A submission already exists for this person');
            redirect('/controllers/form_renderer.php');
        }
    }
    
    // Get form fields with options
    $fields = $fieldModel->getFieldsByFormId($formId);
    foreach ($fields as &$field) {
        $field['options'] = $optionModel->getOptionsByFieldId($field['field_id']);
    }
    
    include __DIR__ . '/../views/form_renderer/fill.php';
}

// ==================== SUBMIT FORM ====================
elseif ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token');
        redirect('/controllers/form_renderer.php');
    }
    
    $formId = (int) $_POST['form_id'];
    $personId = (int) $_POST['person_id'];
    
    $form = $customFormModel->getFormById($formId);
    if (!$form) {
        setFlash('error', 'Form not found');
        redirect('/controllers/form_renderer.php');
    }
    
    // Check duplicates
    if (!$form['allow_duplicates'] && $submissionModel->submissionExists($formId, $personId)) {
        setFlash('error', 'A submission already exists for this person');
        redirect('/controllers/form_renderer.php');
    }
    
    // Get fields to validate
    $fields = $fieldModel->getFieldsByFormId($formId);
    $submissionData = [];
    $errors = [];
    
    foreach ($fields as $field) {
        $fieldName = $field['field_name'];
        $value = $_POST[$fieldName] ?? null;
        
        // Required field validation
        if ($field['is_required'] && empty($value)) {
            $errors[] = "{$field['field_label']} is required";
            continue;
        }
        
        // Handle checkbox arrays
        if ($field['field_type'] === 'checkbox' && is_array($value)) {
            $value = implode(', ', $value);
        }
        
        // Store value
        $submissionData[$fieldName] = $value;
    }
    
    if (!empty($errors)) {
        setFlash('error', 'Validation errors: ' . implode(', ', $errors));
        redirect($_SERVER['HTTP_REFERER'] ?? '/controllers/form_renderer.php');
    }
    
    // Get person's user_id
    $db = new Database();
    $personUser = $db->fetch("SELECT user_id FROM person WHERE person_id = ?", [$personId]);
    $userId = $personUser['user_id'] ?? $_SESSION['user_id'];
    
    // Create submission
    $submissionResult = $submissionModel->createSubmission([
        'custom_form_id' => $formId,
        'user_id' => $userId,
        'person_id' => $personId,
        'created_by' => $_SESSION['user_id'],
        'submission_data' => json_encode($submissionData)
    ]);
    
    if ($submissionResult) {
        setFlash('success', 'Form submitted successfully!');
        redirect('/controllers/form_renderer.php');
    } else {
        setFlash('error', 'Failed to submit form');
        redirect($_SERVER['HTTP_REFERER'] ?? '/controllers/form_renderer.php');
    }
}

// ==================== VIEW SUBMISSIONS ====================
elseif ($action === 'submissions' && $formId) {
    $form = $customFormModel->getFormById($formId);
    
    if (!$form) {
        setFlash('error', 'Form not found');
        redirect('/controllers/form_renderer.php');
    }
    
    // Get submissions (with purok filter for Role 2)
    if (needsPurokFilter() && $form['requires_purok_match']) {
        $submissions = $submissionModel->getSubmissionsByPurok($formId, getUserPurok());
    } else {
        $submissions = $submissionModel->getSubmissionsByFormId($formId);
    }
    
    include __DIR__ . '/../views/form_renderer/submissions.php';
}
