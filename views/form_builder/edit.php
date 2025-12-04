<?php
/**
 * File: views/form_builder/edit.php
 * Drag & drop form builder interface
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Form: <?= e($form['form_title']) ?> - BRGYCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/form-builder.css">
    <style>
        /* Inline critical styles for form builder */
        .form-builder-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            min-height: 600px;
        }
        
        .builder-sidebar {
            width: 280px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #dee2e6;
        }
        
        .builder-canvas {
            flex: 1;
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 2px dashed #ced4da;
            min-height: 500px;
        }
        
        .builder-canvas.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .field-type-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: move;
            transition: all 0.2s;
        }
        
        .field-type-item:hover {
            background: #e9ecef;
            border-color: #0d6efd;
            transform: translateX(5px);
        }
        
        .field-type-item.dragging {
            opacity: 0.5;
        }
        
        .field-item-container {
            position: relative;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .field-item-container:hover {
            border-color: #0d6efd;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
        }
        
        .field-mini-panel {
            position: absolute;
            top: -12px;
            right: 10px;
            display: flex;
            gap: 5px;
            background: white;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .field-control-btn {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .field-control-btn:hover {
            color: #0d6efd;
        }
        
        .field-control-btn.delete-btn:hover {
            color: #dc3545;
        }
        
        .field-width-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .sortable-ghost {
            opacity: 0.4;
            background: #e9ecef;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ced4da;
            margin-bottom: 15px;
        }
        
        .placeholder-row {
            background: linear-gradient(90deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            border: 2px dashed rgba(13, 110, 253, 0.5);
            border-radius: 4px;
            min-height: 10px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
            <div>
                <h4 class="mb-0">
                    <i class="fas fa-edit"></i> 
                    <span id="formTitle"><?= e($form['form_title']) ?></span>
                </h4>
                <small class="text-muted">Form Code: de><?= e($form['form_code']) ?></code></small>
            </div>
            <div class="btn-toolbar gap-2">
                <a href="/controllers/form_builder.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <i class="fas fa-cog"></i> Settings
                </button>
                <button type="button" class="btn btn-success" id="saveFormBtn">
                    <i class="fas fa-save"></i> Save Form
                </button>
                <button type="button" class="btn btn-info" id="previewFormBtn">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?php if ($msg = getFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3">
                <?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Form Builder Container -->
        <div class="form-builder-container">
            <!-- Left Sidebar: Field Palette -->
            <div class="builder-sidebar">
                <h6 class="mb-3"><i class="fas fa-toolbox"></i> Field Types</h6>
                <p class="small text-muted">Drag fields to the canvas →</p>
                
                <div id="fieldPalette">
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
                    
                    <div class="field-type-item" data-field-type="select" draggable="true">
                        <i class="fas fa-list"></i>
                        <span>Dropdown</span>
                    </div>
                    
                    <div class="field-type-item" data-field-type="checkbox" draggable="true">
                        <i class="fas fa-check-square"></i>
                        <span>Checkboxes</span>
                    </div>
                    
                    <div class="field-type-item" data-field-type="radio" draggable="true">
                        <i class="fas fa-dot-circle"></i>
                        <span>Radio Buttons</span>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle"></i>
                    <strong>Tip:</strong> Click a field to edit its properties.
                </div>
            </div>
            
            <!-- Right Side: Canvas -->
            <div class="builder-canvas empty" id="builderCanvas">
                <div class="empty-state">
                    <i class="fas fa-plus-circle"></i>
                    <h5>Start Building Your Form</h5>
                    <p>Drag field types from the left panel to add them here</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?action=update_settings">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="form_id" value="<?= $form['custom_form_id'] ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Form Title</label>
                            <input type="text" class="form-control" name="form_title" 
                                   value="<?= e($form['form_title']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="form_description" rows="3"><?= e($form['form_description']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="is_active" <?= $form['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Form is Active
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_purok_match" 
                                       id="requires_purok_match" <?= $form['requires_purok_match'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="requires_purok_match">
                                    Role 2 can only see their purok
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allow_duplicates" 
                                       id="allow_duplicates" <?= $form['allow_duplicates'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allow_duplicates">
                                    Allow multiple submissions per person
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_in_dashboard" 
                                       id="show_in_dashboard" <?= $form['show_in_dashboard'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_in_dashboard">
                                    Show in forms dashboard
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Field Edit Modal (dynamically populated) -->
    <div class="modal fade" id="fieldEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="fieldEditBody">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pass data to JavaScript -->
    <script>
        window.formData = {
            formId: <?= $form['custom_form_id'] ?>,
            formCode: '<?= e($form['form_code']) ?>',
            formTitle: '<?= e($form['form_title']) ?>',
            fields: <?= json_encode($fields) ?>
        };
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="/assets/js/form-builder.js"></script>
</body>
</html>
