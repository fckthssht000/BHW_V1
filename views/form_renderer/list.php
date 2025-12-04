<?php
/**
 * File: views/form_renderer/list.php
 * List all available forms for Role 1 & 2
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Forms - BRGYCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-clipboard-list"></i> Available Forms</h2>
        <p class="text-muted">Select a form to fill for a resident</p>
        
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
                <i class="fas fa-info-circle"></i> No forms available at this time.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($forms as $form): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-file-alt text-primary"></i>
                                    <?= e($form['form_title']) ?>
                                </h5>
                                <p class="card-text small text-muted">
                                    <?= e($form['form_description'] ?? 'No description') ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="#" class="btn btn-primary btn-sm" 
                                   onclick="selectPerson(<?= $form['custom_form_id'] ?>); return false;">
                                    <i class="fas fa-plus"></i> Fill Form
                                </a>
                                <a href="?action=submissions&form_id=<?= $form['custom_form_id'] ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-list"></i> View Submissions
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Person Selection Modal -->
    <div class="modal fade" id="personModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Resident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="personSearch" 
                           placeholder="Search by name...">
                    <div id="personList" class="list-group" style="max-height: 400px; overflow-y: auto;">
                        <!-- Populated via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFormId = null;
        const personModal = new bootstrap.Modal(document.getElementById('personModal'));
        
        function selectPerson(formId) {
            selectedFormId = formId;
            loadPersonList();
            personModal.show();
        }
        
        function loadPersonList(searchTerm = '') {
            const purok = '<?= getUserPurok() ?? '' ?>';
            const needsFilter = <?= needsPurokFilter() ? 'true' : 'false' ?>;
            
            fetch('/api/get_persons.php?search=' + encodeURIComponent(searchTerm) + 
                  (needsFilter ? '&purok=' + purok : ''))
                .then(r => r.json())
                .then(persons => {
                    const list = document.getElementById('personList');
                    if (persons.length === 0) {
                        list.innerHTML = '<div class="text-muted text-center p-3">No residents found</div>';
                        return;
                    }
                    
                    list.innerHTML = persons.map(p => `
                        <a href="?action=fill&form_id=${selectedFormId}&person_id=${p.person_id}" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <strong>${p.full_name}</strong>
                                <span class="badge bg-secondary">${p.age} yrs</span>
                            </div>
                            <small class="text-muted">
                                ${p.gender} • Purok ${p.purok}
                            </small>
                        </a>
                    `).join('');
                });
        }
        
        document.getElementById('personSearch').addEventListener('input', (e) => {
            loadPersonList(e.target.value);
        });
    </script>
</body>
</html>
