<?php
/**
 * File: includes/helpers.php
 * Common utility functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate URL-safe slug from text
 */
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '_');
    $text = preg_replace('~_+~', '_', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'form_' . uniqid();
    }
    
    return $text;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($roleId) {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $roleId;
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Flash message helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate field value based on field type
 */
function validateFieldValue($value, $fieldType, $isRequired) {
    if ($isRequired && empty($value)) {
        return false;
    }
    
    switch ($fieldType) {
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL);
        case 'number':
            return is_numeric($value);
        case 'date':
            return (bool) strtotime($value);
        default:
            return true;
    }
}

/**
 * Get user's purok from session (for Role 2)
 */
function getUserPurok() {
    return $_SESSION['purok'] ?? null;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Escape HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
