<?php
/**
 * File: includes/auth.php
 * Session management and authentication
 */

session_start();

/**
 * Check if user is authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue');
        redirect('/login.php');
    }
}

/**
 * Check if user has required role
 */
function requireRole($roleId) {
    requireLogin();
    
    if (!hasRole($roleId)) {
        setFlash('error', 'You do not have permission to access this page');
        redirect('/dashboard.php');
    }
}

/**
 * Check if user can create forms (Role 4)
 */
function canCreateForms() {
    return hasRole(4);
}

/**
 * Check if user can fill forms (Role 1 or 2)
 */
function canFillForms() {
    return hasRole(1) || hasRole(2);
}

/**
 * Check if user needs purok filtering (Role 2)
 */
function needsPurokFilter() {
    return hasRole(2);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'role_id' => $_SESSION['role_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'purok' => $_SESSION['purok'] ?? null,
    ];
}
