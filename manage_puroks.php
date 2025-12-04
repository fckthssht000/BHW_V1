<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

if ($role_id != 4) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_purok') {
        $purok = $_POST['purok'];
        $stmt = $pdo->prepare("INSERT INTO address (purok, barangay, municipality, province) VALUES (?, 'Sta. Maria', 'Camiling', 'Tarlac')");
        $stmt->execute([$purok]);
    } elseif ($_POST['action'] == 'edit_purok') {
        $purok_id = $_POST['purok_id'];
        $new_purok = $_POST['purok'];
        $stmt = $pdo->prepare("UPDATE address SET purok = ? WHERE address_id = ?");
        $stmt->execute([$new_purok, $purok_id]);
    } elseif ($_POST['action'] == 'delete_purok') {
        $purok_id = $_POST['purok_id'];
        $stmt = $pdo->prepare("DELETE FROM address WHERE address_id = ?");
        $stmt->execute([$purok_id]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Manage Puroks</title>
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
        .btn-success {
            background: #48bb78;
            border: none;
            padding: 10px 20px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-1px);
        }
        .btn-warning {
            background: #ecc94b;
            border: none;
            padding: 5px 10px;
            font-size: 0.75rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-warning:hover {
            background: #d69e2e;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: #e53e3e;
            border: none;
            padding: 5px 10px;
            font-size: 0.75rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-1px);
        }
        .list-group-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .list-group-item form { margin: 0; }
        .list-group-item input[type="text"] {
            margin-right: 10px;
            width: 150px;
        }
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
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 0;
            }
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
            .card { margin-bottom: 15px; margin-left: 20px; margin-right: 0;}
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
                <div class="card">
                    <div class="card-header">Manage Puroks</div>
                    <div class="card-body">
                        <form method="POST" id="addPurokForm">
                            <input type="hidden" name="action" value="add_purok">
                            <div class="form-group">
                                <label for="purok">New Purok</label>
                                <input type="text" class="form-control" name="purok" id="purok" required>
                            </div>
                            <button type="submit" class="btn btn-success">Add Purok</button>
                        </form>
                        <h4 class="mt-4">Existing Puroks</h4>
                        <ul class="list-group">
                            <?php
                            $stmt = $pdo->query("SELECT address_id, purok FROM address WHERE barangay = 'Sta. Maria' AND municipality = 'Camiling' AND province = 'Tarlac'");
                            while ($row = $stmt->fetch()) {
                                echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                                echo $row['purok'];
                                echo "<div>
                                    <form method='POST' style='display:inline;' class='editForm'>
                                        <input type='hidden' name='action' value='edit_purok'>
                                        <input type='hidden' name='purok_id' value='{$row['address_id']}'>
                                        <input type='text' name='purok' value='{$row['purok']}' class='form-control d-inline-block w-50'>
                                        <button type='submit' class='btn btn-warning btn-sm'>Save</button>
                                    </form>
                                    <form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure?\");'>
                                        <input type='hidden' name='action' value='delete_purok'>
                                        <input type='hidden' name='purok_id' value='{$row['address_id']}'>
                                        <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                                    </form>
                                </div>";
                                echo "</li>";
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
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
        // Initialize accordion
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });
    </script>
    <style>
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
            .card { margin-bottom: 15px; }
            .navbar-brand { padding-left: 55px;}
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
        }
    </style>
</body>
</html>