<?php
/**
 * File: models/CustomFormField.php
 * Handles custom_form_fields table operations
 */

require_once __DIR__ . '/../includes/Database.php';

class CustomFormField {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all fields for a form (ordered)
     */
    public function getFieldsByFormId($formId) {
        $sql = "SELECT * FROM custom_form_fields 
                WHERE custom_form_id = ? 
                ORDER BY field_order ASC";
        
        return $this->db->fetchAll($sql, [$formId]);
    }
    
    /**
     * Get single field by ID
     */
    public function getFieldById($fieldId) {
        $sql = "SELECT * FROM custom_form_fields WHERE field_id = ?";
        return $this->db->fetch($sql, [$fieldId]);
    }
    
    /**
     * Create new field
     */
    public function createField($data) {
        $sql = "INSERT INTO custom_form_fields 
                (custom_form_id, field_name, field_label, field_type, field_order,
                 is_required, placeholder, default_value, help_text,
                 validation_rules, conditional_logic, data_source_type, data_source_config,
                 field_group, has_other_option, css_classes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['custom_form_id'],
            $data['field_name'],
            $data['field_label'],
            $data['field_type'],
            $data['field_order'] ?? 0,
            $data['is_required'] ?? 0,
            $data['placeholder'] ?? null,
            $data['default_value'] ?? null,
            $data['help_text'] ?? null,
            $data['validation_rules'] ?? null, // JSON
            $data['conditional_logic'] ?? null, // JSON
            $data['data_source_type'] ?? 'static',
            $data['data_source_config'] ?? null, // JSON
            $data['field_group'] ?? null,
            $data['has_other_option'] ?? 0,
            $data['css_classes'] ?? null
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update field
     */
    public function updateField($fieldId, $data) {
        $sql = "UPDATE custom_form_fields SET 
                field_name = ?,
                field_label = ?,
                field_type = ?,
                field_order = ?,
                is_required = ?,
                placeholder = ?,
                default_value = ?,
                help_text = ?,
                validation_rules = ?,
                conditional_logic = ?,
                data_source_type = ?,
                data_source_config = ?,
                field_group = ?,
                has_other_option = ?,
                css_classes = ?
                WHERE field_id = ?";
        
        $params = [
            $data['field_name'],
            $data['field_label'],
            $data['field_type'],
            $data['field_order'] ?? 0,
            $data['is_required'] ?? 0,
            $data['placeholder'] ?? null,
            $data['default_value'] ?? null,
            $data['help_text'] ?? null,
            $data['validation_rules'] ?? null,
            $data['conditional_logic'] ?? null,
            $data['data_source_type'] ?? 'static',
            $data['data_source_config'] ?? null,
            $data['field_group'] ?? null,
            $data['has_other_option'] ?? 0,
            $data['css_classes'] ?? null,
            $fieldId
        ];
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete field
     */
    public function deleteField($fieldId) {
        $sql = "DELETE FROM custom_form_fields WHERE field_id = ?";
        return $this->db->delete($sql, [$fieldId]);
    }
    
    /**
     * Delete all fields for a form
     */
    public function deleteFieldsByFormId($formId) {
        $sql = "DELETE FROM custom_form_fields WHERE custom_form_id = ?";
        return $this->db->delete($sql, [$formId]);
    }
    
    /**
     * Bulk save fields (used by form builder)
     * Deletes existing fields and inserts new ones
     */
    public function bulkSaveFields($formId, $fieldsData) {
        try {
            $this->db->getPDO()->beginTransaction();
            
            // Delete existing fields
            $this->deleteFieldsByFormId($formId);
            
            // Insert new fields
            foreach ($fieldsData as $index => $fieldData) {
                $fieldData['custom_form_id'] = $formId;
                $fieldData['field_order'] = $index + 1;
                $this->createField($fieldData);
            }
            
            $this->db->getPDO()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getPDO()->rollBack();
            error_log("Bulk save fields error: " . $e->getMessage());
            return false;
        }
    }
}
