<?php
/**
 * File: models/CustomFormFieldOption.php
 * Handles custom_form_field_options table operations
 */

require_once __DIR__ . '/../includes/Database.php';

class CustomFormFieldOption {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all options for a field (ordered)
     */
    public function getOptionsByFieldId($fieldId) {
        $sql = "SELECT * FROM custom_form_field_options 
                WHERE field_id = ? 
                ORDER BY option_order ASC";
        
        return $this->db->fetchAll($sql, [$fieldId]);
    }
    
    /**
     * Create new option
     */
    public function createOption($data) {
        $sql = "INSERT INTO custom_form_field_options 
                (field_id, option_value, option_label, option_order, is_default)
                VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $data['field_id'],
            $data['option_value'],
            $data['option_label'],
            $data['option_order'] ?? 0,
            $data['is_default'] ?? 0
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update option
     */
    public function updateOption($optionId, $data) {
        $sql = "UPDATE custom_form_field_options SET 
                option_value = ?,
                option_label = ?,
                option_order = ?,
                is_default = ?
                WHERE option_id = ?";
        
        $params = [
            $data['option_value'],
            $data['option_label'],
            $data['option_order'] ?? 0,
            $data['is_default'] ?? 0,
            $optionId
        ];
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete option
     */
    public function deleteOption($optionId) {
        $sql = "DELETE FROM custom_form_field_options WHERE option_id = ?";
        return $this->db->delete($sql, [$optionId]);
    }
    
    /**
     * Delete all options for a field
     */
    public function deleteOptionsByFieldId($fieldId) {
        $sql = "DELETE FROM custom_form_field_options WHERE field_id = ?";
        return $this->db->delete($sql, [$fieldId]);
    }
    
    /**
     * Bulk save options for a field
     */
    public function bulkSaveOptions($fieldId, $optionsData) {
        try {
            $this->db->getPDO()->beginTransaction();
            
            // Delete existing options
            $this->deleteOptionsByFieldId($fieldId);
            
            // Insert new options
            foreach ($optionsData as $index => $optionData) {
                $optionData['field_id'] = $fieldId;
                $optionData['option_order'] = $index + 1;
                $this->createOption($optionData);
            }
            
            $this->db->getPDO()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getPDO()->rollBack();
            error_log("Bulk save options error: " . $e->getMessage());
            return false;
        }
    }
}
