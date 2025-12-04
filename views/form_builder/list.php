<?php
/**
 * File: views/form_builder/list.php
 * List all forms created by Role 4
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Custom Forms - BRGYCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-alt"></i> My Custom Forms</h2>
            <a href="?action=create" class="btn btn-success">
                <i class="fas fa-plus"></i> Create New Form
            </a>
        </div>
        
        <?php if ($msg = getFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($msg = getFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($forms)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You haven't created any forms yet. 
                <a href="?action=create">Create your first form</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Form Title</th>
                            <th>Form Code</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <tr>
                                <td>
                                    <strong><?= e($form['form_title']) ?></strong>
                                    <?php if ($form['form_description']): ?>
                                        <br><small class="text-muted"><?= e(substr($form['form_description'], 0, 100)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>de><?= e($form['form_code']) ?></code></td>
                                <td>
                                    <?php if ($form['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($form['created_at'], 'M j, Y') ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&form_id=<?= $form['custom_form_id'] ?>" 
                                           class="btn btn-primary" title="Edit Form">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/controllers/form_renderer.php?form_id=<?= $form['custom_form_id'] ?>" 
                                           class="btn btn-info" title="Preview Form" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/controllers/form_submissions.php?form_id=<?= $form['custom_form_id'] ?>" 
                                           class="btn btn-secondary" title="View Submissions">
                                            <i class="fas fa-list"></i>
                                        </a>
                                        <button onclick="deleteForm(<?= $form['custom_form_id'] ?>)" 
                                                class="btn btn-danger" title="Delete Form">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteForm(formId) {
            if (confirm('Are you sure you want to delete this form? This action cannot be undone.')) {
                window.location.href = `?action=delete&form_id=${formId}`;
            }
        }
    </script>
</body>
</html>
