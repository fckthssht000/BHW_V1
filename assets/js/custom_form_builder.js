/**
 * File: custom_form_builder.js
 * Complete drag-drop form builder with all field types for BRGYCare
 */

class FormBuilder {
    constructor() {
        this.formId = window.formData?.formId || null;
        this.fields = window.formData?.fields || [];
        this.currentEditingFieldId = null;
        this.sortableInstance = null;
        
        console.log('[FormBuilder] Initializing with', this.fields.length, 'fields');
        this.init();
    }
    
    init() {
        this.setupFieldPalette();
        this.setupDropZone();
        
        if (this.fields.length > 0) {
            this.renderFields();
        }
        
        this.setupButtons();
        this.updateEmptyState();
    }
    
    setupFieldPalette() {
        const items = document.querySelectorAll('.field-type-item');
        
        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                const fieldType = item.getAttribute('data-field-type');
                e.dataTransfer.setData('fieldType', fieldType);
                e.dataTransfer.effectAllowed = 'copy';
                item.classList.add('dragging');
            });
            
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });
    }
    
    setupDropZone() {
        const canvas = document.getElementById('builderCanvas');
        
        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        });
        
        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            const fieldType = e.dataTransfer.getData('fieldType');
            if (fieldType) {
                this.addField(fieldType);
            }
        });
        
        this.setupSortable();
    }
    
    setupSortable() {
        const canvas = document.getElementById('builderCanvas');
        
        if (typeof Sortable === 'undefined') {
            console.warn('SortableJS not loaded');
            return;
        }
        
        if (this.sortableInstance) {
            this.sortableInstance.destroy();
        }
        
        this.sortableInstance = new Sortable(canvas, {
            animation: 150,
            handle: '.drag-btn',
            ghostClass: 'sortable-ghost',
            onEnd: () => this.updateFieldOrder()
        });
    }
    
    addField(fieldType) {
        const field = {
            field_id: 'temp_' + Date.now(),
            field_name: `field_${Date.now()}`,
            field_label: this.getDefaultLabel(fieldType),
            field_type: fieldType,
            field_order: this.fields.length + 1,
            is_required: 0,
            placeholder: '',
            default_value: '',
            help_text: '',
            validation_rules: null,
            options: this.getDefaultOptions(fieldType)
        };
        
        this.fields.push(field);
        this.renderFields();
        this.updateEmptyState();
        
        // Auto-open edit modal
        setTimeout(() => this.editField(field.field_id), 100);
    }
    
    getDefaultLabel(type) {
        const labels = {
            text: 'Text Input',
            textarea: 'Text Area',
            number: 'Number',
            date: 'Date',
            select: 'Dropdown',
            select2: 'Searchable Dropdown',
            checkbox_group: 'Checkbox Group',
            radio: 'Radio Buttons',
            toggle: 'Toggle Switch'
        };
        return labels[type] || 'Field';
    }
    
    getDefaultOptions(type) {
        if (['select', 'select2', 'checkbox_group', 'radio'].includes(type)) {
            return [
                { option_label: 'Option 1', option_value: 'option1', option_order: 1, is_default: 0 },
                { option_label: 'Option 2', option_value: 'option2', option_order: 2, is_default: 0 },
                { option_label: 'Option 3', option_value: 'option3', option_order: 3, is_default: 0 }
            ];
        }
        return [];
    }
    
    renderFields() {
        const canvas = document.getElementById('builderCanvas');
        canvas.innerHTML = '';
        
        const sorted = [...this.fields].sort((a, b) => a.field_order - b.field_order);
        
        sorted.forEach(field => {
            canvas.appendChild(this.createFieldElement(field));
        });
        
        this.setupSortable();
        this.updateEmptyState();
    }
    
    createFieldElement(field) {
        const div = document.createElement('div');
        div.className = 'field-item-container';
        div.setAttribute('data-field-id', field.field_id);
        
        const reqBadge = field.is_required ? '<span class="badge bg-danger ms-2">Required</span>' : '';
        const optCount = field.options?.length || 0;
        const optBadge = optCount > 0 ? `<span class="badge bg-info ms-2">${optCount} options</span>` : '';
        
                // Validation badge
        let valBadge = '';
        if (field.validation_rules) {
            const rules = field.validation_rules;
            
            if (field.field_type === 'number' && (rules.min !== undefined || rules.max !== undefined)) {
                valBadge = `<span class="badge bg-secondary ms-2">Min: ${rules.min || 'none'}, Max: ${rules.max || 'none'}</span>`;
            } else if (['text', 'textarea'].includes(field.field_type)) {
                const validations = [];
                if (rules.pattern) validations.push(rules.pattern);
                if (rules.min_length) validations.push(`min:${rules.min_length}`);
                if (rules.max_length) validations.push(`max:${rules.max_length}`);
                if (validations.length > 0) {
                    valBadge = `<span class="badge bg-secondary ms-2"><i class="fas fa-shield-alt"></i> ${validations.join(', ')}</span>`;
                }
            }
        }
        
        div.innerHTML = `
            <div class="field-mini-panel">
                <span class="badge bg-secondary">${field.field_type}</span>
                <button type="button" class="field-control-btn drag-btn" title="Drag to reorder">
                    <i class="fas fa-grip-vertical"></i>
                </button>
                <button type="button" class="field-control-btn edit-btn" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="field-control-btn delete-btn" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="field-content">
                <label class="form-label">
                    <strong>${this.escapeHtml(field.field_label)}</strong>
                    ${reqBadge}${optBadge}${valBadge}
                </label>
                ${this.renderPreview(field)}
                ${field.help_text ? `<small class="text-muted d-block mt-1">${this.escapeHtml(field.help_text)}</small>` : ''}
            </div>
        `;
        
        div.querySelector('.edit-btn').onclick = () => this.editField(field.field_id);
        div.querySelector('.delete-btn').onclick = () => this.deleteField(field.field_id);
        
        return div;
    }
    
    renderPreview(field) {
        const ph = field.placeholder || '';
        const defaultVal = field.default_value || '';
        
        switch (field.field_type) {
            case 'text':
                return `<input type="text" class="form-control" placeholder="${this.escapeHtml(ph)}" value="${this.escapeHtml(defaultVal)}" disabled>`;
            
            case 'textarea':
                return `<textarea class="form-control" rows="3" placeholder="${this.escapeHtml(ph)}" disabled>${this.escapeHtml(defaultVal)}</textarea>`;
            
            case 'number':
                const min = field.validation_rules?.min || '';
                const max = field.validation_rules?.max || '';
                return `<input type="number" class="form-control" placeholder="${this.escapeHtml(ph)}" value="${this.escapeHtml(defaultVal)}" min="${min}" max="${max}" disabled>`;
            
            case 'date':
                const dateVal = defaultVal.toUpperCase() === 'CURRENTDATE' ? new Date().toISOString().split('T')[0] : defaultVal;
                return `<input type="date" class="form-control" value="${dateVal}" disabled>`;
            
            case 'select':
            case 'select2':
                let sel = `<select class="form-select" disabled><option>-- Select --</option>`;
                (field.options || []).forEach(o => {
                    const selected = o.is_default ? 'selected' : '';
                    sel += `<option ${selected}>${this.escapeHtml(o.option_label)}</option>`;
                });
                sel += '</select>';
                if (field.field_type === 'select2') {
                    sel += '<small class="text-muted d-block mt-1"><i class="fas fa-search"></i> Searchable in live form</small>';
                }
                return sel;
            
            case 'checkbox_group':
                let cbHtml = '<div>';
                (field.options || []).forEach(o => {
                    const checked = o.is_default ? 'checked' : '';
                    cbHtml += `<div class="form-check">
                        <input class="form-check-input" type="checkbox" ${checked} disabled>
                        <label class="form-check-label">${this.escapeHtml(o.option_label)}</label>
                    </div>`;
                });
                return cbHtml + '</div>';
            
            case 'radio':
                let radioHtml = '<div>';
                (field.options || []).forEach(o => {
                    const checked = o.is_default ? 'checked' : '';
                    radioHtml += `<div class="form-check">
                        <input class="form-check-input" type="radio" ${checked} disabled>
                        <label class="form-check-label">${this.escapeHtml(o.option_label)}</label>
                    </div>`;
                });
                return radioHtml + '</div>';
            
            case 'toggle':
                const toggleChecked = defaultVal ? 'checked' : '';
                return `<div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" ${toggleChecked} disabled>
                    <label class="form-check-label">Toggle On/Off</label>
                </div>`;
            
            default:
                return `<input type="text" class="form-control" placeholder="${this.escapeHtml(ph)}" disabled>`;
        }
    }
    
    editField(fieldId) {
        const field = this.fields.find(f => f.field_id === fieldId);
        if (!field) return;
        
        this.currentEditingFieldId = fieldId;
        const body = document.getElementById('fieldEditBody');
        const hasOpts = ['select', 'select2', 'checkbox_group', 'radio'].includes(field.field_type);
        const isNumber = field.field_type === 'number';
        const isToggle = field.field_type === 'toggle';
        
        let html = `
            <form id="fieldEditForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Field Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_label" value="${this.escapeHtml(field.field_label)}" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Field Name (internal) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" value="${this.escapeHtml(field.field_name)}" required>
                        <small class="text-muted">Lowercase, underscores only</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Field Type</label>
                    <input type="text" class="form-control" value="${field.field_type}" disabled>
                    <small class="text-muted">Cannot change type after creation</small>
                </div>
        `;
        
        // Placeholder (not for toggle)
        if (!isToggle) {
            html += `
                <div class="mb-3">
                    <label class="form-label">Placeholder Text</label>
                    <input type="text" class="form-control" id="edit_placeholder" value="${this.escapeHtml(field.placeholder || '')}">
                </div>
            `;
        }
        
        // Default value
        if (isToggle) {
            html += `
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="edit_default" ${field.default_value ? 'checked' : ''}>
                        <label class="form-check-label">Default to ON</label>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="mb-3">
                    <label class="form-label">Default Value</label>
                    <input type="text" class="form-control" id="edit_default" value="${this.escapeHtml(field.default_value || '')}">
                    <small class="text-muted">For dates: use "CURRENTDATE" for today's date</small>
                </div>
            `;
        }
        
        // Number field: min/max validation
        if (isNumber) {
            const minVal = field.validation_rules?.min ?? '';
            const maxVal = field.validation_rules?.max ?? '';
            html += `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Minimum Value</label>
                        <input type="number" class="form-control" id="edit_min" value="${minVal}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Maximum Value</label>
                        <input type="number" class="form-control" id="edit_max" value="${maxVal}">
                    </div>
                </div>
            `;
        }
        
        // Help text
        html += `
            <div class="mb-3">
                <label class="form-label">Help Text</label>
                <input type="text" class="form-control" id="edit_help" value="${this.escapeHtml(field.help_text || '')}">
                <small class="text-muted">Additional instructions shown below the field</small>
            </div>
        `;
        
        // Field group
        html += `
            <div class="mb-3">
                <label class="form-label">Field Group</label>
                <input type="text" class="form-control" id="edit_field_group" value="${this.escapeHtml(field.field_group || '')}">
                <small class="text-muted">Optional group name for organizing fields (e.g., "Vital Signs", "Lifestyle").</small>
            </div>
        `;
                // Validation rules for text/textarea fields
        if (['text', 'textarea'].includes(field.field_type)) {
            const valRules = field.validation_rules || {};
            const pattern = valRules.pattern || '';
            const minLen = valRules.min_length || '';
            const maxLen = valRules.max_length || '';
            const customPat = valRules.custom_pattern || '';
            
            html += `
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-shield-alt"></i> Validation Rules</label>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input validation-pattern" type="radio" name="validation_pattern" id="pattern_none" value="" ${!pattern ? 'checked' : ''}>
                        <label class="form-check-label" for="pattern_none">
                            No pattern validation
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input validation-pattern" type="radio" name="validation_pattern" id="pattern_letters" value="letters" ${pattern === 'letters' ? 'checked' : ''}>
                        <label class="form-check-label" for="pattern_letters">
                            Letters only (no numbers or special characters)
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input validation-pattern" type="radio" name="validation_pattern" id="pattern_numbers" value="numbers" ${pattern === 'numbers' ? 'checked' : ''}>
                        <label class="form-check-label" for="pattern_numbers">
                            Numbers only
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input validation-pattern" type="radio" name="validation_pattern" id="pattern_alphanumeric" value="alphanumeric" ${pattern === 'alphanumeric' ? 'checked' : ''}>
                        <label class="form-check-label" for="pattern_alphanumeric">
                            Alphanumeric only (letters and numbers, no special characters)
                        </label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="edit_minLength" class="form-label small">Minimum Length</label>
                            <input type="number" class="form-control form-control-sm" id="edit_minLength" value="${minLen}" placeholder="e.g., 2">
                        </div>
                        
                        <div class="col-md-6 mb-2">
                            <label for="edit_maxLength" class="form-label small">Maximum Length</label>
                            <input type="number" class="form-control form-control-sm" id="edit_maxLength" value="${maxLen}" placeholder="e.g., 50">
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label for="edit_customPattern" class="form-label small">Custom Pattern (Regex)</label>
                        <input type="text" class="form-control form-control-sm" id="edit_customPattern" value="${this.escapeHtml(customPat)}" placeholder="e.g., ^[A-Za-z\\s]+$">
                        <small class="text-muted">For advanced users only</small>
                    </div>
                </div>
            `;
        }
        
        // Required checkbox
        html += `
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="edit_required" ${field.is_required ? 'checked' : ''}>
                <label class="form-check-label" for="edit_required">
                    <strong>Required Field</strong>
                </label>
            </div>
        `;
        
        // Options editor for select/checkbox/radio
        if (hasOpts) {
            html += `
                <div class="mb-3">
                    <label class="form-label">Options</label>
                    <div id="optionsList" class="mb-2"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addOptionBtn">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                </div>
            `;
        }
        
        html += `
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        `;
        
        body.innerHTML = html;
        
        // Render options if applicable
        if (hasOpts) {
            this.renderOptionsEditor(field);
        }
        
        // Form submit handler
        document.getElementById('fieldEditForm').onsubmit = (e) => {
            e.preventDefault();
            this.saveFieldEdit();
        };
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('fieldEditModal'));
        modal.show();
    }
    
    renderOptionsEditor(field) {
        const container = document.getElementById('optionsList');
        const opts = field.options || [];
        
        container.innerHTML = opts.map((o, i) => `
            <div class="input-group mb-2" data-option-index="${i}">
                <input type="text" class="form-control option-label" value="${this.escapeHtml(o.option_label)}" placeholder="Option label" required>
                <input type="text" class="form-control option-value" value="${this.escapeHtml(o.option_value)}" placeholder="Value">
                <div class="input-group-text">
                    <input class="form-check-input option-default mt-0" type="checkbox" ${o.is_default ? 'checked' : ''} title="Set as default">
                </div>
                <button type="button" class="btn btn-outline-danger remove-option-btn" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');
        
        // Remove option handlers
        container.querySelectorAll('.remove-option-btn').forEach(btn => {
            btn.onclick = () => btn.closest('.input-group').remove();
        });
        
        // Add option handler
        document.getElementById('addOptionBtn').onclick = () => {
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" class="form-control option-label" placeholder="Option label" required>
                <input type="text" class="form-control option-value" placeholder="Value">
                <div class="input-group-text">
                    <input class="form-check-input option-default mt-0" type="checkbox" title="Set as default">
                </div>
                <button type="button" class="btn btn-outline-danger remove-option-btn" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(div);
            
            div.querySelector('.remove-option-btn').onclick = () => div.remove();
        };
    }
    
    saveFieldEdit() {
        const field = this.fields.find(f => f.field_id === this.currentEditingFieldId);
        if (!field) return;
        
        // Update basic properties
        field.field_label = document.getElementById('edit_label').value.trim();
        field.field_name = document.getElementById('edit_name').value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
        field.is_required = document.getElementById('edit_required').checked ? 1 : 0;
        
        // Placeholder (if exists)
        const placeholderInput = document.getElementById('edit_placeholder');
        if (placeholderInput) {
            field.placeholder = placeholderInput.value.trim();
        }
        
        // Default value
        if (field.field_type === 'toggle') {
            field.default_value = document.getElementById('edit_default').checked ? '1' : '';
        } else {
            const defaultInput = document.getElementById('edit_default');
            if (defaultInput) {
                field.default_value = defaultInput.value.trim();
            }
        }
        
        // Help text
        const helpInput = document.getElementById('edit_help');
        if (helpInput) {
            field.help_text = helpInput.value.trim();
        }

        // Field group
        const groupInput = document.getElementById('edit_field_group');
        if (groupInput) {
            field.field_group = groupInput.value.trim() || null;
        }
        
        // Number validation (min/max)
        if (field.field_type === 'number') {
            const minInput = document.getElementById('edit_min');
            const maxInput = document.getElementById('edit_max');
            
            if (minInput || maxInput) {
                field.validation_rules = {
                    min: minInput?.value ? parseInt(minInput.value) : null,
                    max: maxInput?.value ? parseInt(maxInput.value) : null
                };
            }
        }

        // Text/Textarea validation
        if (['text', 'textarea'].includes(field.field_type)) {
            const pattern = document.querySelector('input[name="validation_pattern"]:checked')?.value || null;
            const minLength = document.getElementById('edit_minLength')?.value || null;
            const maxLength = document.getElementById('edit_maxLength')?.value || null;
            const customPattern = document.getElementById('edit_customPattern')?.value?.trim() || null;
            
            if (pattern || minLength || maxLength || customPattern) {
                field.validation_rules = {
                    pattern: pattern,
                    min_length: minLength ? parseInt(minLength) : null,
                    max_length: maxLength ? parseInt(maxLength) : null,
                    custom_pattern: customPattern
                };
            } else {
                field.validation_rules = null;
            }
        }
        
        // Update options if applicable
        const optionsList = document.getElementById('optionsList');
        if (optionsList) {
            const inputs = optionsList.querySelectorAll('.input-group');
            field.options = Array.from(inputs).map((g, i) => {
                const label = g.querySelector('.option-label').value.trim();
                const value = g.querySelector('.option-value').value.trim() || label;
                const isDefault = g.querySelector('.option-default').checked;
                
                return {
                    option_label: label,
                    option_value: value,
                    option_order: i + 1,
                    is_default: isDefault ? 1 : 0
                };
            }).filter(o => o.option_label); // Remove empty options
        }
        
        // Re-render
        this.renderFields();
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('fieldEditModal'));
        modal.hide();
        
        this.showNotification('Field updated successfully', 'success');
    }
    
    deleteField(fieldId) {
        if (!confirm('Delete this field? This cannot be undone.')) return;
        
        this.fields = this.fields.filter(f => f.field_id !== fieldId);
        this.renderFields();
        this.updateFieldOrder();
        this.showNotification('Field deleted', 'warning');
    }
    
    updateFieldOrder() {
        const els = document.querySelectorAll('.field-item-container');
        els.forEach((el, i) => {
            const fid = el.getAttribute('data-field-id');
            const f = this.fields.find(x => x.field_id === fid);
            if (f) f.field_order = i + 1;
        });
    }
    
    updateEmptyState() {
        const canvas = document.getElementById('builderCanvas');
        
        if (this.fields.length === 0) {
            canvas.classList.add('empty');
            canvas.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-plus-circle"></i>
                    <h5>Start Building Your Form</h5>
                    <p>Drag field types from the left panel to add them here</p>
                </div>
            `;
        } else {
            canvas.classList.remove('empty');
        }
    }
    
    setupButtons() {
        const saveBtn = document.getElementById('saveFormBtn');
        if (saveBtn) {
            saveBtn.onclick = () => this.saveForm();
        }
    }
    
    saveForm() {
        if (this.fields.length === 0) {
            alert('Please add at least one field before saving');
            return;
        }
        
        const btn = document.getElementById('saveFormBtn');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        
        $.post('custom_form_builder.php', {
            ajax: 'save_fields',
            form_id: this.formId,
            fields: JSON.stringify(this.fields)
        }, (response) => {
            if (response.success) {
                this.showNotification(response.message, 'success');
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json').fail(() => {
            alert('Failed to save form. Please try again.');
        }).always(() => {
            btn.innerHTML = orig;
            btn.disabled = false;
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    showNotification(msg, type = 'info') {
        const colors = {
            success: 'success',
            warning: 'warning',
            danger: 'danger',
            info: 'info'
        };
        
        const alertType = colors[type] || 'info';
        const div = document.createElement('div');
        div.className = `alert alert-${alertType} alert-dismissible fade show position-fixed`;
        div.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);';
        div.innerHTML = `
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(div);
        
        setTimeout(() => {
            div.remove();
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.builder-container')) {
        window.formBuilder = new FormBuilder();
        console.log('[FormBuilder] Initialized successfully');
    }
});
