<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user's role and privileges
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT p.privilege_id, p.privilege_name 
                       FROM role_privilege rp 
                       JOIN privilege p ON rp.privilege_id = p.privilege_id 
                       WHERE rp.role_id = ?");
$stmt->execute([$role_id]);
$privileges = [];
while ($row = $stmt->fetch()) {
    $privileges[$row['privilege_id']] = $row['privilege_name'];
}

// Check for manage accounts privilege (privilege_id = 8)
if (!isset($privileges[8]) || $privileges[8] != 'access_manage_account') {
    header("Location: dashboard.php");
    exit;
}

// Pagination variables
$items_per_page = 15;
$admin_page = isset($_GET['admin_page']) ? max(1, intval($_GET['admin_page'])) : 1;
$user_page = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_purok'])) {
    $user_id = $_POST['user_id'];
    $purok = $_POST['purok'];
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "ASSIGNED_PUROK: user_id:$user_id to purok:$purok"]);
    $stmt = $pdo->prepare("UPDATE address a JOIN person p ON a.address_id = p.address_id JOIN records r ON p.person_id = r.person_id SET a.purok = ? WHERE r.user_id = ?");
    $stmt->execute([$purok, $user_id]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role_id = $_POST['role_id'];
    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
    $stmt->execute([$new_role_id, $user_id]);
    if ($new_role_id == 2) { // BHW Staff
        $stmt = $pdo->prepare("UPDATE person p JOIN records r ON p.person_id = r.person_id SET p.household_number = 0 WHERE r.user_id = ?");
        $stmt->execute([$user_id]);
    }
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "CHANGED_ROLE: user_id:$user_id to role_id:$new_role_id"]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    // Delete related activity logs first
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    // Then delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "DELETED_USER: user_id:$user_id"]);
}

// Fetch admin users with pagination
$admin_offset = ($admin_page - 1) * $items_per_page;
$admin_stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.username, p.full_name, a.purok, p.contact_number, al.activity 
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    JOIN address a ON p.address_id = a.address_id 
    LEFT JOIN (SELECT activity, user_id FROM activity_logs WHERE activity LIKE 'ASSIGNED_PUROK: user_id:%' ORDER BY created_at DESC) al ON u.user_id = al.user_id 
    WHERE u.role_id = 2 AND p.relationship_type = 'Head'
    LIMIT ? OFFSET ?
");
$admin_stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$admin_stmt->bindValue(2, $admin_offset, PDO::PARAM_INT);
$admin_stmt->execute();
$admin_users = $admin_stmt->fetchAll();

// Get total admin count for pagination
$admin_count_stmt = $pdo->query("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    WHERE u.role_id = 2 AND p.relationship_type = 'Head'
");
$total_admin_users = $admin_count_stmt->fetchColumn();
$total_admin_pages = ceil($total_admin_users / $items_per_page);

// Fetch user users with pagination
$user_offset = ($user_page - 1) * $items_per_page;
$user_stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.username, p.full_name, a.purok, p.contact_number, u.role_id
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    JOIN address a ON p.address_id = a.address_id 
    WHERE u.role_id = 3 AND p.relationship_type = 'Head'
    LIMIT ? OFFSET ?
");
$user_stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$user_stmt->bindValue(2, $user_offset, PDO::PARAM_INT);
$user_stmt->execute();
$user_users = $user_stmt->fetchAll();

// Get total user count for pagination
$user_count_stmt = $pdo->query("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    WHERE u.role_id = 3 AND p.relationship_type = 'Head'
");
$total_user_users = $user_count_stmt->fetchColumn();
$total_user_pages = ceil($total_user_users / $items_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Manage Accounts</title>
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
        .content.with-sidebar { margin-left: 0; }
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
        .nav-tabs .nav-link {
            color: #2d3748;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background: #edf2f7;
            color: #2b6cb0;
            border-bottom: 2px solid #2b6cb0;
        }
        .table {
            background: #ffffff;
            border-radius: 10px;
            min-width: 800px; /* Ensure table has minimum width for horizontal scrolling */
        }
        .table thead th {
            background: rgba(43, 108, 176, 0.9);
            color: #fff;
            border-bottom: none;
            font-weight: 500;
            white-space: nowrap;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1a202c;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
        }
        .form-control::placeholder { color: #718096; }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 8px 15px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: #e53e3e;
            border: none;
            padding: 8px 15px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
            margin-left: 5px;
        }
        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-1px);
        }

        /* Mobile responsive styles */
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
                margin-left: 0px;
                width: 100%;
                padding: 10px;
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 0;
            }
            .menu-toggle { 
                display: block; 
                color: #fff; 
                font-size: 1.5rem; 
                cursor: pointer; 
                position: absolute; 
                left: 10px; 
                top: 20px; 
                z-index: 1060; 
            }
            .card { 
                margin-bottom: 15px; 
                margin-left: 0;
                margin-right: 0;
            }
            .table-responsive { 
                overflow-x: auto; 
                -webkit-overflow-scrolling: touch;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }
            .navbar-brand { 
                padding-left: 55px;
            }
            
            /* Mobile table adjustments */
            .table {
                font-size: 0.8rem;
                margin-bottom: 0;
            }
            .table th,
            .table td {
                padding: 8px 6px;
                white-space: nowrap;
            }
            
            /* Form adjustments for mobile */
            .table form {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            .table select {
                min-width: 100px;
                font-size: 0.8rem;
            }
            .table .btn {
                font-size: 0.75rem;
                padding: 6px 10px;
            }
            
            /* Pagination mobile styles */
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
                margin: 15px 0;
            }
            
            .page-item {
                margin: 2px;
            }
            
            .page-link {
                padding: 8px 12px;
                font-size: 0.85rem;
                min-width: 44px;
                text-align: center;
                border-radius: 6px;
            }
            
            .page-item:not(.page-prev):not(.page-next):not(.active) {
                display: none;
            }
            
            .page-item.active,
            .page-item:first-child,
            .page-item:last-child {
                display: block !important;
            }
            
            .page-item.disabled .page-link {
                display: inline-block;
                padding: 8px 6px;
            }
        }

        @media (min-width: 769px) {
            .sidebar { 
                left: 0; 
                transform: translateX(0); 
            }
            .content { 
                margin-left: 250px; 
            }
            .content.with-sidebar { 
                margin-left: 250px; 
            }
            .menu-toggle { 
                display: none; 
            }
        }

        /* Pagination styles */
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .page-link {
            color: #2b6cb0;
            border: 1px solid #dee2e6;
        }
        .page-item.active .page-link {
            background-color: #2b6cb0;
            border-color: #2b6cb0;
            color: white;
        }
        .page-link:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        /* Enhanced mobile pagination for very small screens */
        @media (max-width: 400px) {
            .pagination {
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
            
            .page-item {
                width: 100%;
                max-width: 200px;
            }
            
            .page-link {
                width: 100%;
                padding: 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 content">
                <div class="card">
                    <div class="card-header">Manage Accounts</div>
                    <div class="card-body p-3">
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#admin">Admin (BHW Staff)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#user">User (Household User)</a>
                            </li>
                        </ul>
                        <div class="tab-content mt-3">
                            <div class="tab-pane active" id="admin">
                                <input class="form-control mb-3" type="search" placeholder="Search Admins" id="adminSearch">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Name</th>
                                                <th>Purok</th>
                                                <th>Contact</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="adminTable">
                                            <?php foreach ($admin_users as $row): ?>
                                                <?php 
                                                $purok = $row['activity'] ? explode('purok:', $row['activity'])[1] : $row['purok'];
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($purok); ?></td>
                                                    <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                    <td>
                                                        <form action='superadmin_manage_accounts.php' method='POST' onsubmit='return confirm("Are you sure you want to delete this user?");'>
                                                            <input type='hidden' name='user_id' value='<?php echo htmlspecialchars($row['user_id']); ?>'>
                                                            <select name='purok' class="form-control form-control-sm d-inline-block w-auto">
                                                                <option value='Purok 1' <?php echo ($purok == 'Purok 1' ? 'selected' : ''); ?>>Purok 1</option>
                                                                <option value='Purok 2' <?php echo ($purok == 'Purok 2' ? 'selected' : ''); ?>>Purok 2</option>
                                                                <option value='Purok 3' <?php echo ($purok == 'Purok 3' ? 'selected' : ''); ?>>Purok 3</option>
                                                                <option value='Purok 4A' <?php echo ($purok == 'Purok 4A' ? 'selected' : ''); ?>>Purok 4A</option>
                                                                <option value='Purok 4B' <?php echo ($purok == 'Purok 4B' ? 'selected' : ''); ?>>Purok 4B</option>
                                                                <option value='Purok 5' <?php echo ($purok == 'Purok 5' ? 'selected' : ''); ?>>Purok 5</option>
                                                                <option value='Purok 6' <?php echo ($purok == 'Purok 6' ? 'selected' : ''); ?>>Purok 6</option>
                                                                <option value='Purok 7' <?php echo ($purok == 'Purok 7' ? 'selected' : ''); ?>>Purok 7</option>
                                                            </select>
                                                            <button type='submit' name='assign_purok' class='btn btn-sm btn-primary'>Assign</button>
                                                            <button type='submit' name='delete_user' class='btn btn-sm btn-danger'>Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Admin Pagination -->
                                <?php if ($total_admin_pages > 1): ?>
                                <nav aria-label="Admin pagination">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo $admin_page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link page-prev" href="?admin_page=<?php echo $admin_page - 1; ?>&user_page=<?php echo $user_page; ?>&active_tab=admin#admin">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start_page = max(1, $admin_page - 2);
                                        $end_page = min($total_admin_pages, $admin_page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): 
                                        ?>
                                            <li class="page-item <?php echo $i == $admin_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?admin_page=<?php echo $i; ?>&user_page=<?php echo $user_page; ?>&active_tab=admin#admin"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $admin_page == $total_admin_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link page-next" href="?admin_page=<?php echo $admin_page + 1; ?>&user_page=<?php echo $user_page; ?>&active_tab=admin#admin">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                            
                            <div class="tab-pane" id="user">
                                <input class="form-control mb-3" type="search" placeholder="Search Users" id="userSearch">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Name</th>
                                                <th>Purok</th>
                                                <th>Contact</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="userTable">
                                            <?php foreach ($user_users as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['purok']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                    <td>
                                                        <form action='superadmin_manage_accounts.php' method='POST' onsubmit='return confirm("Are you sure you want to delete this user?");'>
                                                            <input type='hidden' name='user_id' value='<?php echo htmlspecialchars($row['user_id']); ?>'>
                                                            <select name='role_id' class="form-control form-control-sm d-inline-block w-auto">
                                                                <option value='2' <?php echo ($row['role_id'] == 2 ? 'selected' : ''); ?>>BHW Staff</option>
                                                                <option value='3' <?php echo ($row['role_id'] == 3 ? 'selected' : ''); ?>>Household User</option>
                                                            </select>
                                                            <button type='submit' name='change_role' class='btn btn-sm btn-primary'>Change Role</button>
                                                            <button type='submit' name='delete_user' class='btn btn-sm btn-danger'>Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- User Pagination -->
                                <?php if ($total_user_pages > 1): ?>
                                <nav aria-label="User pagination">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo $user_page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link page-prev" href="?admin_page=<?php echo $admin_page; ?>&user_page=<?php echo $user_page - 1; ?>&active_tab=user#user">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start_page = max(1, $user_page - 2);
                                        $end_page = min($total_user_pages, $user_page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): 
                                        ?>
                                            <li class="page-item <?php echo $i == $user_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?admin_page=<?php echo $admin_page; ?>&user_page=<?php echo $i; ?>&active_tab=user#user"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $user_page == $total_user_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link page-next" href="?admin_page=<?php echo $admin_page; ?>&user_page=<?php echo $user_page + 1; ?>&active_tab=user#user">
                                                Next <i class="fas fa-chevron-right"></i>
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
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Function to get URL parameters
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }
    
        // Set active tab based on URL parameter or default to 'admin'
        $(document).ready(function() {
            var activeTab = getUrlParameter('active_tab') || 'admin';
            $('.nav-tabs a[href="#' + activeTab + '"]').tab('show');
            
            // Update all pagination links to maintain the active tab
            updatePaginationLinks(activeTab);
            
            // Handle tab changes to update URL without reloading
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var tabName = $(e.target).attr('href').substring(1);
                updateUrlParameter('active_tab', tabName);
                updatePaginationLinks(tabName);
            });
        });
    
        // Function to update URL parameter without reloading
        function updateUrlParameter(key, value) {
            var url = new URL(window.location);
            url.searchParams.set(key, value);
            window.history.replaceState({}, '', url);
        }
    
        // Function to update all pagination links with active tab
        function updatePaginationLinks(activeTab) {
            $('.pagination .page-link').each(function() {
                var href = $(this).attr('href');
                if (href) {
                    // Remove existing active_tab parameter
                    href = href.replace(/&active_tab=[^&]*/, '');
                    // Add current active tab
                    href += '&active_tab=' + activeTab;
                    $(this).attr('href', href);
                }
            });
        }
        // Search functionality
        $('#adminSearch').on('input', function() {
            let value = $(this).val().toLowerCase();
            $('#adminTable tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
        
        $('#userSearch').on('input', function() {
            let value = $(this).val().toLowerCase();
            $('#userTable tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
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

        // Initialize accordion
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });

        // Handle window resize for pagination
        $(window).on('resize', function() {
            // Update pagination display based on screen size
            $('.pagination').each(function() {
                const isMobile = window.innerWidth <= 768;
                const $pagination = $(this);
                
                if (isMobile) {
                    $pagination.find('.page-item:not(.page-prev):not(.page-next):not(.active)').hide();
                    $pagination.find('.page-item.active, .page-item:first-child, .page-item:last-child').show();
                } else {
                    $pagination.find('.page-item').show();
                }
            });
        }).trigger('resize');

        // Smooth scroll to top when changing pages on mobile
        function scrollToTop() {
            if (window.innerWidth <= 768) {
                $('html, body').animate({
                    scrollTop: $('.card').offset().top - 20
                }, 300);
            }
        }

        // Add click handlers for pagination links
        $('.pagination .page-link').on('click', function() {
            scrollToTop();
        });

        // Tab change handler for mobile
        $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
            if (window.innerWidth <= 768) {
                scrollToTop();
            }
        });
        
        // Real-time form submission without page reload (AJAX)
        $(document).on('submit', 'form', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var formData = form.serialize();
            var action = form.attr('action');
            
            // Show loading state
            var submitBtn = form.find('button[type="submit"]');
            var originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
            
            $.ajax({
                type: 'POST',
                url: action,
                data: formData,
                success: function(response) {
                    // Create a temporary DOM element to parse the response
                    var tempDoc = document.implementation.createHTMLDocument("temp");
                    tempDoc.documentElement.innerHTML = response;
                    
                    // Extract the updated table content
                    var updatedAdminTable = $(tempDoc).find('#adminTable').html();
                    var updatedUserTable = $(tempDoc).find('#userTable').html();
                    
                    // Update the tables
                    $('#adminTable').html(updatedAdminTable);
                    $('#userTable').html(updatedUserTable);
                    
                    // Show success message
                    showAlert('Operation completed successfully!', 'success');
                },
                error: function() {
                    showAlert('An error occurred. Please try again.', 'danger');
                },
                complete: function() {
                    // Restore button state
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        });
    
        // Function to show alert messages
        function showAlert(message, type) {
            var alertClass = 'alert-' + type;
            var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                           message +
                           '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                           '<span aria-hidden="true">&times;</span>' +
                           '</button>' +
                           '</div>';
            
            // Remove existing alerts
            $('.alert').remove();
            
            // Add new alert
            $('.card-body').prepend(alertHtml);
            
            // Auto remove after 3 seconds
            setTimeout(function() {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    </script>
</body>
</html>