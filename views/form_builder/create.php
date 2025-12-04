<?php
/**
 * File: views/form_builder/create.php
 * Create new form
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Form - BRGYCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-plus"></i> Create New Form</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            
                            <div class="mb-3">
                                <label for="form_title" class="form-label">Form Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="form_title" name="form_title" 
                                       placeholder="e.g., COVID-19 Vaccination Record" required>
                                <small class="text-muted">This will be displayed to staff filling the form</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="form_description" class="form-label">Description</label>
                                <textarea class="form-control" id="form_description" name="form_description" rows="3"
                                          placeholder="Brief description of this form's purpose"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="requires_purok_match" 
                                           id="requires_purok_match" checked>
                                    <label class="form-check-label" for="requires_purok_match">
                                        Role 2 can only see their purok's data
                                    </label>
                                    <small class="d-block text-muted">Similar to hardcoded forms behavior</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_duplicates" id="allow_duplicates">
                                    <label class="form-check-label" for="allow_duplicates">
                                        Allow multiple submissions per person
                                    </label>
                                    <small class="d-block text-muted">Uncheck if each person should only have one record</small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="/controllers/form_builder.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Create Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
