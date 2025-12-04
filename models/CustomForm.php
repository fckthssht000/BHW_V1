<?php
/**
 * File: models/CustomForm.php
 * Handles custom_forms table operations
 */

require_once __DIR__ . '/../includes/Database.php';

class CustomForm {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all active forms (for Role 1 & 2 to fill)
     * Role 2 sees all forms but can only submit for their purok residents
     */
    public function getActiveForms($userId = null, $roleId = null) {
        $sql = "SELECT cf.*, u.username as created_by_name
                FROM custom_forms cf
                LEFT JOIN users u ON cf.created_by = u.user_id
                WHERE cf.is_active = 1 
                AND cf.show_in_dashboard = 1
                ORDER BY cf.created_at DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get forms created by specific user (Role 4)
     */
    public function getFormsByCreator($userId) {
        $sql = "SELECT * FROM custom_forms 
                WHERE created_by = ? 
                ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Get single form by ID
     */
    public function getFormById($formId) {
        $sql = "SELECT * FROM custom_forms WHERE custom_form_id = ?";
        return $this->db->fetch($sql, [$formId]);
    }
    
    /**
     * Get form by code (URL-friendly identifier)
     */
    public function getFormByCode($formCode) {
        $sql = "SELECT * FROM custom_forms WHERE form_code = ?";
        return $this->db->fetch($sql, [$formCode]);
    }
    
    /**
     * Create new form (Role 4 only)
     */
    public function createForm($data) {
        $sql = "INSERT INTO custom_forms 
                (form_code, form_title, form_description, record_type, 
                 target_filters, created_by, allowed_roles, is_active,
                 requires_purok_match, allow_duplicates, show_in_dashboard)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['form_code'],
            $data['form_title'],
            $data['form_description'] ?? null,
            $data['record_type'],
            $data['target_filters'] ?? null, // JSON
            $data['created_by'],
            $data['allowed_roles'] ?? '1,2',
            $data['is_active'] ?? 1,
            $data['requires_purok_match'] ?? 1,
            $data['allow_duplicates'] ?? 0,
            $data['show_in_dashboard'] ?? 1
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update form
     */
    public function updateForm($formId, $data) {
        $sql = "UPDATE custom_forms SET 
                form_title = ?,
                form_description = ?,
                target_filters = ?,
                allowed_roles = ?,
                is_active = ?,
                requires_purok_match = ?,
                allow_duplicates = ?,
                show_in_dashboard = ?
                WHERE custom_form_id = ?";
        
        $params = [
            $data['form_title'],
            $data['form_description'] ?? null,
            $data['target_filters'] ?? null,
            $data['allowed_roles'] ?? '1,2',
            $data['is_active'] ?? 1,
            $data['requires_purok_match'] ?? 1,
            $data['allow_duplicates'] ?? 0,
            $data['show_in_dashboard'] ?? 1,
            $formId
        ];
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete form (soft delete by setting is_active = 0)
     */
    public function deleteForm($formId) {
        $sql = "UPDATE custom_forms SET is_active = 0 WHERE custom_form_id = ?";
        return $this->db->update($sql, [$formId]);
    }
    
    /**
     * Check if form code already exists
     */
    public function codeExists($formCode, $excludeFormId = null) {
        if ($excludeFormId) {
            $sql = "SELECT COUNT(*) as count FROM custom_forms 
                    WHERE form_code = ? AND custom_form_id != ?";
            $result = $this->db->fetch($sql, [$formCode, $excludeFormId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM custom_forms WHERE form_code = ?";
            $result = $this->db->fetch($sql, [$formCode]);
        }
        
        return $result['count'] > 0;
    }
}
