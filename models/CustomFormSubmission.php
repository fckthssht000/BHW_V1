<?php
/**
 * File: models/CustomFormSubmission.php
 * Handles custom_form_submissions table operations
 */

require_once __DIR__ . '/../includes/Database.php';

class CustomFormSubmission {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all submissions for a form
     */
    public function getSubmissionsByFormId($formId) {
        $sql = "SELECT s.*, 
                       p.full_name as person_name,
                       u.username as created_by_name,
                       a.purok
                FROM custom_form_submissions s
                JOIN person p ON s.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id
                JOIN users u ON s.created_by = u.user_id
                WHERE s.custom_form_id = ?
                ORDER BY s.submitted_at DESC";
        
        return $this->db->fetchAll($sql, [$formId]);
    }
    
    /**
     * Get submissions by person (all forms for one person)
     */
    public function getSubmissionsByPersonId($personId) {
        $sql = "SELECT s.*, 
                       cf.form_title,
                       u.username as created_by_name
                FROM custom_form_submissions s
                JOIN custom_forms cf ON s.custom_form_id = cf.custom_form_id
                JOIN users u ON s.created_by = u.user_id
                WHERE s.person_id = ?
                ORDER BY s.submitted_at DESC";
        
        return $this->db->fetchAll($sql, [$personId]);
    }
    
    /**
     * Get submissions for Role 2 (purok-filtered)
     */
    public function getSubmissionsByPurok($formId, $purok) {
        $sql = "SELECT s.*, 
                       p.full_name as person_name,
                       u.username as created_by_name,
                       a.purok
                FROM custom_form_submissions s
                JOIN person p ON s.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id
                JOIN users u ON s.created_by = u.user_id
                WHERE s.custom_form_id = ? AND a.purok = ?
                ORDER BY s.submitted_at DESC";
        
        return $this->db->fetchAll($sql, [$formId, $purok]);
    }
    
    /**
     * Get single submission by ID
     */
    public function getSubmissionById($submissionId) {
        $sql = "SELECT s.*, 
                       p.full_name as person_name,
                       cf.form_title,
                       u.username as created_by_name,
                       a.purok
                FROM custom_form_submissions s
                JOIN person p ON s.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id
                JOIN custom_forms cf ON s.custom_form_id = cf.custom_form_id
                JOIN users u ON s.created_by = u.user_id
                WHERE s.submission_id = ?";
        
        return $this->db->fetch($sql, [$submissionId]);
    }
    
    /**
     * Create new submission
     */
    public function createSubmission($data) {
        $sql = "INSERT INTO custom_form_submissions 
                (custom_form_id, user_id, person_id, created_by, 
                 submission_data, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['custom_form_id'],
            $data['user_id'], // Resident's user_id
            $data['person_id'], // Person this form is about
            $data['created_by'], // Staff who filled it
            $data['submission_data'], // JSON blob
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update submission
     */
    public function updateSubmission($submissionId, $data) {
        $sql = "UPDATE custom_form_submissions SET 
                submission_data = ?
                WHERE submission_id = ?";
        
        $params = [
            $data['submission_data'], // JSON blob
            $submissionId
        ];
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete submission
     */
    public function deleteSubmission($submissionId) {
        $sql = "DELETE FROM custom_form_submissions WHERE submission_id = ?";
        return $this->db->delete($sql, [$submissionId]);
    }
    
    /**
     * Check if submission already exists (for forms that don't allow duplicates)
     */
    public function submissionExists($formId, $personId) {
        $sql = "SELECT COUNT(*) as count 
                FROM custom_form_submissions 
                WHERE custom_form_id = ? AND person_id = ?";
        
        $result = $this->db->fetch($sql, [$formId, $personId]);
        return $result['count'] > 0;
    }
    
    /**
     * Get submission statistics for a form
     */
    public function getFormStatistics($formId) {
        $sql = "SELECT 
                    COUNT(*) as total_submissions,
                    COUNT(DISTINCT person_id) as unique_persons,
                    MIN(submitted_at) as first_submission,
                    MAX(submitted_at) as last_submission
                FROM custom_form_submissions 
                WHERE custom_form_id = ?";
        
        return $this->db->fetch($sql, [$formId]);
    }
}
