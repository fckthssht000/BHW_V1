<?php
/**
 * File: views/form_renderer/fill.php
 * Fill out a form for a specific person
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($form['form_title']) ?> - BRGYCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?= e($form['form_title']) ?></h4>
                        <?php if ($form['form_description']): ?>
                            <small><?= e($form['form_description']) ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <!-- Person Info -->
                        <div class="alert alert-info">
                            <strong><i class="fas fa-user"></i> Resident:</strong> 
                            <?= e($person['full_name']) ?> 
                            (<?= e($person['age']) ?> yrs, <?= e($person['gender']) ?>)
                            <br>
                            <small>Purok: <?= e($person['purok']) ?></small>
                        </div>
                        
                        <!-- Form Fields -->
                        <form method="POST" action="?action=submit">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="form_id" value="<?= $form['custom_form_id'] ?>">
                            <input type="hidden" name="person_id" value="<?= $person['person_id'] ?>">
                            
                            <?php foreach ($fields as $field): ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <?= e($field['field_label']) ?>
                                        <?php if ($field['is_required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($field['help_text']): ?>
                                        <small class="d-block text-muted mb-1"><?= e($field['help_text']) ?></small>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $fieldName = $field['field_name'];
                                    $placeholder = $field['placeholder'] ?? '';
                                    $defaultValue = $field['default_value'] ?? '';
                                    $required = $field['is_required'] ? 'required' : '';
                                    
                                    switch ($field['field_type']):
                                        case 'text':
                                        case 'email':
                                        case 'number':
                                            $type = $field['field_type'];
                                            echo "<input type='$type' class='form-control' name='$fieldName' 
                                                         placeholder='$placeholder' value='$defaultValue' $required>";
                                            break;
                                        
                                        case 'textarea':
                                            echo "<textarea class='form-control' name='$fieldName' rows='3' 
                                                           placeholder='$placeholder' $required>$defaultValue</textarea>";
                                            break;
                                        
                                        case 'date':
                                            $value = '';
                                            if (strtoupper($defaultValue) === 'CURRENTDATE') {
                                                $value = date('Y-m-d');
                                            } elseif ($defaultValue) {
                                                $value = $defaultValue;
                                            }
                                            echo "<input type='date' class='form-control' name='$fieldName' 
                                                         value='$value' $required>";
                                            break;
                                        
                                        case 'select':
                                            echo "<select class='form-select' name='$fieldName' $required>";
                                            echo "<option value=''>-- Select --</option>";
                                            foreach ($field['options'] as $opt) {
                                                $value = e($opt['option_value']);
                                                $label = e($opt['option_label']);
                                                $selected = ($opt['is_default'] ? 'selected' : '');
                                                echo "<option value='$value' $selected>$label</option>";
                                            }
                                            if ($field['has_other_option']) {
                                                echo "<option value='Other'>Other (please specify)</option>";
                                            }
                                            echo "</select>";
                                            
                                            if ($field['has_other_option']) {
                                                echo "<input type='text' class='form-control mt-2' 
                                                             name='{$fieldName}_other' placeholder='Specify other' 
                                                             style='display:none' id='{$fieldName}_other_input'>";
                                            }
                                            break;
                                        
                                        case 'checkbox':
                                            foreach ($field['options'] as $opt) {
                                                $value = e($opt['option_value']);
                                                $label = e($opt['option_label']);
                                                $checked = ($opt['is_default'] ? 'checked' : '');
                                                echo "<div class='form-check'>
                                                        <input class='form-check-input' type='checkbox' 
                                                               name='{$fieldName}[]' value='$value' $checked>
                                                        <label class='form-check-label'>$label</label>
                                                      </div>";
                                            }
                                            break;
                                        
                                        case 'radio':
                                            foreach ($field['options'] as $opt) {
                                                $value = e($opt['option_value']);
                                                $label = e($opt['option_label']);
                                                $checked = ($opt['is_default'] ? 'checked' : '');
                                                echo "<div class='form-check'>
                                                        <input class='form-check-input' type='radio' 
                                                               name='$fieldName' value='$value' $checked $required>
                                                        <label class='form-check-label'>$label</label>
                                                      </div>";
                                            }
                                            break;
                                    endswitch;
                                    ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="/controllers/form_renderer.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Submit Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle "Other" option for select fields
        document.querySelectorAll('select[name*="[]"]').forEach(select => {
            const fieldName = select.name.replace('[]', '');
            const otherInput = document.getElementById(fieldName + '_other_input');
            
            if (otherInput) {
                select.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        otherInput.style.display = 'block';
                        otherInput.required = true;
                    } else {
                        otherInput.style.display = 'none';
                        otherInput.required = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
