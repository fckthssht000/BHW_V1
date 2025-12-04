<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role and purok
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$password, $_SESSION['user_id']]);
    $_SESSION['message'] = "Password updated successfully.";
}

// List backup files with pagination
$items_per_page = 5;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

$backup_dir = $role_id == 2 ? "backup/automatic/$user_purok/all/" : "backup/automatic/all/";
$manual_backup_dir = $role_id == 2 ? "backup/manual/$user_purok/all/" : "backup/manual/all/";
$backup_files = [];
foreach (['automatic', 'manual'] as $type) {
    $dir = $type == 'automatic' ? $backup_dir : $manual_backup_dir;
    if (is_dir($dir)) {
        $files = glob($dir . "all_*.json");
        foreach ($files as $file) {
            $backup_files[] = ['file' => $file, 'type' => $type, 'date' => date('Y-m-d H:i:s', filemtime($file))];
        }
    }
}
usort($backup_files, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate total pages
$total_backups = count($backup_files);
$total_pages = max(1, ceil($total_backups / $items_per_page));

// Slice the array for the current page
$paginated_backups = array_slice($backup_files, $offset, $items_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHWCare - Settings</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .navbar {
            background: rgba(43, 108, 176, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 80px;
        }
        .navbar-brand, .nav-link { color: #fff; font-weight: 500; }
        .navbar-brand:hover, .nav-link:hover { color: #e2e8f0; }
        .sidebar {
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
            width: 250px;
            height: calc(100vh - 80px);
            position: fixed;
            top: 80px;
            left: -250px;
            z-index: 1040;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar.open { transform: translateX(250px); }
        .sidebar .nav-link {
            color: #2d3748;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #edf2f7;
            color: #2b6cb0;
        }
        .content {
            padding: 20px;
            min-height: calc(100vh - 80px);
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
            position: relative;
            z-index: 1030;
            margin-top: 0;
        }
        .content.with-sidebar { margin-left: 250px; }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-right: -90px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: rgba(43, 108, 176, 0.7);
            color: #fff;
            padding: 15px;
            border-bottom: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group label {
            color: #2d3748;
            font-weight: 500;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            transition: border-color 0.2s ease;
        }
        .form-control:focus {
            border-color: #2b6cb0;
            box-shadow: 0 0 5px rgba(43, 108, 176, 0.3);
        }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: background 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
        }
        .table-responsive { overflow-x: auto; }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }
        .pagination .page-item {
            margin: 0 2px;
        }
        .pagination .page-link {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: #2b6cb0;
            background: #fff;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .pagination .page-link:hover {
            background: #2b6cb0;
            color: #fff;
            border-color: #2b6cb0;
        }
        .pagination .page-item.active .page-link {
            background: #2b6cb0;
            color: #fff;
            border-color: #2b6cb0;
        }
        .pagination .page-item.disabled .page-link {
            color: #a0aec0;
            cursor: not-allowed;
        }
        .menu-toggle { display: none; }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
            display: none;
        }
        .sidebar-overlay.active { display: block; }
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
                height: calc(100vh - 80px);
                top: 80px;
            }
            .sidebar.open {
                transform: translateX(250px);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            }
            .content {
                margin-left: 0;
                width: 100%;
                padding-left: 0;
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 0;
            }
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
            .card { margin-bottom: 15px; margin-left: 20px; margin-right: 0; }
            .navbar-brand { padding-left: 55px; }
            .pagination .page-link {
                padding: 6px 10px;
                font-size: 0.85rem;
            }
            .pagination {
                gap: 5px;
            }
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="col-md-9 content">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['backup_success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['backup_success']); unset($_SESSION['backup_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['backup_error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['backup_error']); unset($_SESSION['backup_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['restore_success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['restore_success']); unset($_SESSION['restore_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['restore_error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['restore_error']); unset($_SESSION['restore_error']); ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-header">Settings</div>
                <div class="card-body">
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Backup & Restore</div>
                <div class="card-body">
                    <a href="backup.php?type=manual" class="btn btn-primary mb-3">Create Manual Backup</a>
                    <h5>Available Backups</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>File</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginated_backups as $backup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($backup['type']); ?></td>
                                        <td><?php echo htmlspecialchars(basename($backup['file'])); ?></td>
                                        <td><?php echo htmlspecialchars($backup['date']); ?></td>
                                        <td><a href="restore.php?file=<?php echo urlencode($backup['file']); ?>" class="btn btn-sm btn-warning">Restore</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Backup pagination">
                            <ul class="pagination">
                                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                    $end_page = min($total_pages, $start_page + 4);
                                }
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    function toggleSidebar() {
        const sidebar = $('.sidebar');
        const content = $('.content');
        sidebar.toggleClass('open');
        if (sidebar.hasClass('open')) {
            content.addClass('with-sidebar');
            if (window.innerWidth <= 768) {
                $('<div class="sidebar-overlay"></div>').appendTo('body').on('click', function() {
                    sidebar.removeClass('open');
                    content.removeClass('with-sidebar');
                    $(this).remove();
                });
            }
        } else {
            content.removeClass('with-sidebar');
            $('.sidebar-overlay').remove();
        }
        if (window.innerWidth > 768) {
            content.css('margin-left', sidebar.hasClass('open') ? '250px' : '0');
        } else {
            content.css('margin-left', '0');
        }
    }
    $('.accordion-header').on('click', function() {
        const content = $(this).next('.accordion-content');
        content.toggleClass('active');
    });
</script>
</body>
</html>