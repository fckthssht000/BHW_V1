<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch person_id from records table
$stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user === false) {
    die("Error: No person record found for this user.");
}
$person_id = $user['person_id'];

// Fetch role_id from users table
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

// Fetch user's purok from address table
$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ? LIMIT 1");
    $stmt->execute([$person_id]);
    $user_purok = $stmt->fetchColumn();
}

// Check access - Only BHW Head (1), BHW (2), and Super Admin (4) can access
if (!in_array($role_id, [1, 2, 4])) {
    header("Location: dashboard.php");
    exit;
}

// Server-Sent Events handler for real-time updates
if (isset($_GET['action']) && $_GET['action'] == 'sse') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $keys_data = getKeysData($role_id, $user_purok);
    echo "data: " . json_encode($keys_data) . "\n\n";
    ob_flush();
    flush();

    $lastModified = getLastModifiedTime($role_id, $user_purok);
    while (true) {
        $currentModified = getLastModifiedTime($role_id, $user_purok);
        if ($currentModified > $lastModified) {
            $keys_data = getKeysData($role_id, $user_purok);
            echo "data: " . json_encode($keys_data) . "\n\n";
            $lastModified = $currentModified;
        }
        ob_flush();
        flush();
        sleep(2);
    }
    exit;
}

// AJAX handler for fetching keys data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_keys_data') {
    echo json_encode(getKeysData($role_id, $user_purok));
    exit;
}

function getKeysData($role_id, $user_purok = null) {
    $keys_data = [];

    $purok_files = [
        'P1' => 'keys/P1_confirmation_key.json',
        'P2' => 'keys/P2_confirmation_key.json',
        'P3' => 'keys/P3_confirmation_key.json',
        'P4A' => 'keys/P4A_confirmation_key.json',
        'P4B' => 'keys/P4B_confirmation_key.json',
        'P5' => 'keys/P5_confirmation_key.json',
        'P6' => 'keys/P6_confirmation_key.json',
        'P7' => 'keys/P7_confirmation_key.json'
    ];

    $allowed_puroks = [];
    if ($role_id == 2) {
        if ($user_purok) {
            $purok_mapping = [
                'Purok 1' => 'P1',
                'Purok 2' => 'P2',
                'Purok 3' => 'P3',
                'Purok 4A' => 'P4A',
                'Purok 4B' => 'P4B',
                'Purok 5' => 'P5',
                'Purok 6' => 'P6',
                'Purok 7' => 'P7'
            ];
            $allowed_puroks = isset($purok_mapping[$user_purok]) ? [$purok_mapping[$user_purok]] : [];
        }
    } else {
        $allowed_puroks = array_keys($purok_files);
    }

    foreach ($allowed_puroks as $purok_name) {
        if (isset($purok_files[$purok_name]) && file_exists($purok_files[$purok_name])) {
            $data = json_decode(file_get_contents($purok_files[$purok_name]), true);
            if ($data) {
                $purok_keys = [];
                $used_count = 0;
                $total_count = count($data);

                foreach ($data as $key => $info) {
                    $is_used = $info['used'] ?? false;
                    if ($is_used) $used_count++;

                    $purok_keys[] = [
                        'key' => $key,
                        'used' => $is_used
                    ];
                }

                usort($purok_keys, function($a, $b) {
                    return strcmp($a['key'], $b['key']);
                });

                $display_purok = $purok_name;
                $purok_display_mapping = [
                    'P1' => 'Purok 1',
                    'P2' => 'Purok 2',
                    'P3' => 'Purok 3',
                    'P4A' => 'Purok 4A',
                    'P4B' => 'Purok 4B',
                    'P5' => 'Purok 5',
                    'P6' => 'Purok 6',
                    'P7' => 'Purok 7'
                ];
                $display_purok = $purok_display_mapping[$purok_name] ?? $purok_name;

                $keys_data[$display_purok] = [
                    'keys' => $purok_keys,
                    'total' => $total_count,
                    'used' => $used_count,
                    'available' => $total_count - $used_count
                ];
            }
        }
    }

    return $keys_data;
}

function getLastModifiedTime($role_id, $user_purok = null) {
    $purok_files = [
        'P1' => 'keys/P1_confirmation_key.json',
        'P2' => 'keys/P2_confirmation_key.json',
        'P3' => 'keys/P3_confirmation_key.json',
        'P4A' => 'keys/P4A_confirmation_key.json',
        'P4B' => 'keys/P4B_confirmation_key.json',
        'P5' => 'keys/P5_confirmation_key.json',
        'P6' => 'keys/P6_confirmation_key.json',
        'P7' => 'keys/P7_confirmation_key.json'
    ];

    $allowed_puroks = [];
    if ($role_id == 2) {
        if ($user_purok) {
            $purok_mapping = [
                'Purok 1' => 'P1',
                'Purok 2' => 'P2',
                'Purok 3' => 'P3',
                'Purok 4A' => 'P4A',
                'Purok 4B' => 'P4B',
                'Purok 5' => 'P5',
                'Purok 6' => 'P6',
                'Purok 7' => 'P7'
            ];
            $allowed_puroks = isset($purok_mapping[$user_purok]) ? [$purok_mapping[$user_purok]] : [];
        }
    } else {
        $allowed_puroks = array_keys($purok_files);
    }

    $latestTime = 0;
    foreach ($allowed_puroks as $purok_name) {
        if (isset($purok_files[$purok_name]) && file_exists($purok_files[$purok_name])) {
            $fileTime = filemtime($purok_files[$purok_name]);
            if ($fileTime > $latestTime) {
                $latestTime = $fileTime;
            }
        }
    }
    return $latestTime;
}

// Calculate overall statistics
$all_keys_data = getKeysData($role_id, $user_purok);
$overall_total = 0;
$overall_used = 0;
$overall_available = 0;
foreach ($all_keys_data as $purok => $data) {
    $overall_total += $data['total'];
    $overall_used += $data['used'];
    $overall_available += $data['available'];
}
$overall_usage_rate = $overall_total > 0 ? round(($overall_used / $overall_total) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Keys Monitor</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            left: 0;
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
            margin-left: 250px;
            transition: margin-left 0.3s ease-in-out;
            z-index: 1030;
        }
        .content.with-sidebar { margin-left: 0; }
        
        /* Dashboard Stats - Overall */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card-overall {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card-overall::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .stat-card-overall.stat-primary::before {
            background: linear-gradient(90deg, #2b6cb0 0%, #4299e1 100%);
        }
        .stat-card-overall.stat-success::before {
            background: linear-gradient(90deg, #48bb78 0%, #68d391 100%);
        }
        .stat-card-overall.stat-danger::before {
            background: linear-gradient(90deg, #f56565 0%, #fc8181 100%);
        }
        .stat-card-overall.stat-warning::before {
            background: linear-gradient(90deg, #ed8936 0%, #f6ad55 100%);
        }
        .stat-card-overall:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .stat-icon-overall {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 16px;
        }
        .stat-icon-overall.icon-primary {
            background: linear-gradient(135deg, rgba(43, 108, 176, 0.1) 0%, rgba(66, 153, 225, 0.1) 100%);
            color: #2b6cb0;
        }
        .stat-icon-overall.icon-success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.1) 0%, rgba(104, 211, 145, 0.1) 100%);
            color: #48bb78;
        }
        .stat-icon-overall.icon-danger {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.1) 0%, rgba(252, 129, 129, 0.1) 100%);
            color: #f56565;
        }
        .stat-icon-overall.icon-warning {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.1) 0%, rgba(246, 173, 85, 0.1) 100%);
            color: #ed8936;
        }
        .stat-label-overall {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-value-overall {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }
        .stat-sublabel-overall {
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        /* Purok Stats - 2x2 Grid Style */
        .purok-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (min-width: 768px) {
            .purok-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 18px;
            }
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px 16px;
            box-shadow: 0 2px 10px rgba(43, 108, 176, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-height: 140px;
            justify-content: center;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(43, 108, 176, 0.15);
        }
        .stat-card.stat-primary {
            border-top: 3px solid #2b6cb0;
        }
        .stat-card.stat-success {
            border-top: 3px solid #48bb78;
        }
        .stat-card.stat-danger {
            border-top: 3px solid #f56565;
        }
        .stat-card.stat-warning {
            border-top: 3px solid #ed8936;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .icon-primary {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }
        .icon-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }
        .icon-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }
        .icon-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #4a5568;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-sublabel {
            font-size: 0.7rem;
            color: #718096;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(135deg, rgba(43, 108, 176, 0.9) 0%, rgba(43, 108, 176, 0.7) 100%);
            color: #fff;
            padding: 20px 24px;
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .realtime-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .realtime-dot {
            width: 8px;
            height: 8px;
            background-color: #48bb78;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .search-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1rem;
        }
        .search-input {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 20px 12px 45px;
            width: 100%;
            transition: all 0.2s ease;
        }
        .search-input:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
            background: white;
        }
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            padding: 0 20px;
        }
        .nav-tabs .nav-link {
            color: #4a5568;
            font-weight: 500;
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 12px 20px;
            margin-right: 5px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-tabs .nav-link:hover {
            background: #f7fafc;
            color: #2b6cb0;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #2b6cb0 0%, #4299e1 100%);
            color: #fff;
            border: none;
            margin-bottom: -2px;
        }
        .nav-tabs .nav-link .badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 0.7rem;
        }
        .nav-tabs .nav-link:not(.active) .badge {
            background: #e2e8f0;
            color: #4a5568;
        }
        .tab-content {
            background: #ffffff;
            padding: 24px;
        }
        .table {
            background: #ffffff;
            border-radius: 10px;
            margin-bottom: 0;
        }
        .table thead th {
            background: linear-gradient(135deg, #2b6cb0 0%, #4299e1 100%);
            color: #fff;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 16px 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: #f7fafc;
        }
        .table tbody td {
            padding: 14px 12px;
            vertical-align: middle;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-unused {
            background-color: #c6f6d5;
            color: #22543d;
        }
        .status-used {
            background-color: #fed7d7;
            color: #742a2a;
        }
        .key-code {
            background: #f7fafc;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2d3748;
            border: 1px solid #e2e8f0;
        }
        .last-updated {
            font-size: 0.85rem;
            color: #718096;
            padding: 16px 24px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pagination {
            justify-content: center;
            margin-top: 25px;
        }
        .page-link {
            color: #2b6cb0;
            border-radius: 8px;
            margin: 0 3px;
            border: 2px solid #e2e8f0;
            padding: 8px 14px;
        }
        .page-link:hover {
            background-color: #edf2f7;
            border-color: #2b6cb0;
        }
        .page-item.active .page-link {
            background: linear-gradient(135deg, #2b6cb0 0%, #4299e1 100%);
            border-color: #2b6cb0;
        }
        .export-btn {
            background: linear-gradient(135deg, #48bb78 0%, #68d391 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.open {
                transform: translateX(250px);
            }
            .content {
                margin-left: 0;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .stat-value-overall {
                font-size: 1.5rem;
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
            .navbar-brand { 
                padding-left: 55px;
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
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 content">
                
                <!-- Overall Statistics Dashboard -->
                <div class="stats-row">
                    <div class="stat-card-overall stat-primary">
                        <div class="stat-icon-overall icon-primary">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="stat-label-overall">Total Keys</div>
                        <div class="stat-value-overall"><?php echo number_format($overall_total); ?></div>
                        <div class="stat-sublabel-overall">All puroks combined</div>
                    </div>
                    
                    <div class="stat-card-overall stat-success">
                        <div class="stat-icon-overall icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-label-overall">Available Keys</div>
                        <div class="stat-value-overall"><?php echo number_format($overall_available); ?></div>
                        <div class="stat-sublabel-overall"><?php echo 100 - $overall_usage_rate; ?>% remaining</div>
                    </div>
                    
                    <div class="stat-card-overall stat-danger">
                        <div class="stat-icon-overall icon-danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-label-overall">Used Keys</div>
                        <div class="stat-value-overall"><?php echo number_format($overall_used); ?></div>
                        <div class="stat-sublabel-overall"><?php echo $overall_usage_rate; ?>% utilized</div>
                    </div>
                    
                    <div class="stat-card-overall stat-warning">
                        <div class="stat-icon-overall icon-warning">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="stat-label-overall">Usage Rate</div>
                        <div class="stat-value-overall"><?php echo $overall_usage_rate; ?>%</div>
                        <div class="stat-sublabel-overall">
                            <?php if ($overall_usage_rate < 50): ?>
                                Low usage
                            <?php elseif ($overall_usage_rate < 80): ?>
                                Moderate usage
                            <?php else: ?>
                                High usage
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Keys Monitor Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <i class="fas fa-shield-alt"></i>
                            <span>Confirmation Keys Monitor</span>
                        </div>
                        <div class="card-header-right">
                            <div class="realtime-indicator">
                                <div class="realtime-dot"></div>
                                <span>Live</span>
                            </div>
                            <div class="role-badge">
                                <?php 
                                if ($role_id == 1) echo "BHW Head";
                                elseif ($role_id == 2) echo "BHW - " . $user_purok;
                                elseif ($role_id == 4) echo "Super Admin";
                                ?>
                            </div>
                            <button class="export-btn" onclick="exportKeysReport()">
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="last-updated">
                        <i class="fas fa-sync-alt"></i>
                        <span>Last updated: <strong id="lastUpdated">Loading...</strong></span>
                    </div>

                    <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                        <!-- Tabs populated by JavaScript -->
                    </ul>

                    <div class="tab-content" id="purokTabContent">
                        <!-- Content populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let updateInterval;
        let currentKeysData = {};
        let eventSource;
        const itemsPerPage = 20;

        function updateKeysData() {
            $.ajax({
                url: 'keys_monitor.php',
                type: 'POST',
                data: { action: 'get_keys_data' },
                success: function(response) {
                    try {
                        currentKeysData = JSON.parse(response);
                        updateTabs();
                        $('#lastUpdated').text(new Date().toLocaleString());
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showError('Error loading data');
                    }
                },
                error: function() {
                    showError('Error loading data');
                }
            });
        }

        function updateTabs() {
            const $tabs = $('#purokTabs');
            const $content = $('#purokTabContent');

            $tabs.empty();
            $content.empty();

            let firstTab = true;
            Object.keys(currentKeysData).sort().forEach(function(purok) {
                const data = currentKeysData[purok];
                const tabId = purok.replace(/\s/g, '').toLowerCase();
                const isActive = firstTab ? 'active' : '';
                const usageRate = Math.round((data.used / data.total) * 100);

                $tabs.append(`
                    <li class="nav-item">
                        <a class="nav-link ${isActive}" id="${tabId}-tab" data-toggle="tab" href="#${tabId}" role="tab">
                            <i class="fas fa-map-marker-alt"></i>
                            ${purok}
                            <span class="badge">${data.available}/${data.total}</span>
                        </a>
                    </li>
                `);

                $content.append(`
                    <div class="tab-pane fade show ${isActive}" id="${tabId}" role="tabpanel">
                        <div class="purok-stats">
                            <div class="stat-card stat-primary">
                                <div class="stat-icon icon-primary">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="stat-value">${data.total}</div>
                                <div class="stat-label">TOTAL KEYS</div>
                                <div class="stat-sublabel">All puroks combined</div>
                            </div>
                            
                            <div class="stat-card stat-success">
                                <div class="stat-icon icon-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value">${data.available}</div>
                                <div class="stat-label">AVAILABLE</div>
                                <div class="stat-sublabel">${100 - usageRate}% remaining</div>
                            </div>
                            
                            <div class="stat-card stat-danger">
                                <div class="stat-icon icon-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-value">${data.used}</div>
                                <div class="stat-label">USED</div>
                                <div class="stat-sublabel">Already confirmed</div>
                            </div>
                            
                            <div class="stat-card stat-warning">
                                <div class="stat-icon icon-warning">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <div class="stat-value">${usageRate}%</div>
                                <div class="stat-label">USAGE RATE</div>
                                <div class="stat-sublabel">${usageRate < 50 ? 'Low usage' : usageRate < 80 ? 'Moderate usage' : 'High usage'}</div>
                            </div>
                        </div>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" id="${tabId}-search" placeholder="Search keys..." onkeyup="searchKeys('${tabId}')">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 60%;">Confirmation Key</th>
                                        <th style="width: 40%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="${tabId}-table-body">
                                </tbody>
                            </table>
                        </div>
                        <nav id="${tabId}-pagination"></nav>
                    </div>
                `);

                updateTableForPurok(tabId, data.keys, 1);
                firstTab = false;
            });

            if (Object.keys(currentKeysData).length === 0) {
                $content.html(`
                    <div class="tab-pane fade show active" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Key Access</h5>
                            <p class="text-muted">You don't have access to view any purok keys.</p>
                        </div>
                    </div>
                `);
            }
        }

        function updateTableForPurok(tabId, keys, page) {
            const startIndex = (page - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageKeys = keys.slice(startIndex, endIndex);

            const $tbody = $(`#${tabId}-table-body`);
            $tbody.empty();

            if (pageKeys.length === 0) {
                $tbody.html('<tr><td colspan="2" class="text-center py-4">No keys found</td></tr>');
                return;
            }

            pageKeys.forEach(function(key) {
                const statusClass = key.used ? 'status-used' : 'status-unused';
                const statusText = key.used ? 'Used' : 'Available';
                const statusIcon = key.used ? 'fas fa-times-circle' : 'fas fa-check-circle';

                $tbody.append(`
                    <tr>
                        <td><span class="key-code">${key.key}</span></td>
                        <td><span class="status-badge ${statusClass}"><i class="${statusIcon}"></i>${statusText}</span></td>
                    </tr>
                `);
            });

            updatePagination(tabId, keys.length, page);
        }

        function updatePagination(tabId, totalItems, currentPage) {
            const $pagination = $(`#${tabId}-pagination`);
            $pagination.empty();

            const totalPages = Math.ceil(totalItems / itemsPerPage);
            if (totalPages <= 1) return;

            let paginationHtml = '<ul class="pagination">';
            
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            paginationHtml += `<li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" onclick="changePage('${tabId}', ${currentPage - 1}); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;

            for (let i = 1; i <= Math.min(totalPages, 5); i++) {
                const activeClass = i === currentPage ? 'active' : '';
                paginationHtml += `<li class="page-item ${activeClass}">
                    <a class="page-link" href="#" onclick="changePage('${tabId}', ${i}); return false;">${i}</a>
                </li>`;
            }

            const nextDisabled = currentPage === totalPages ? 'disabled' : '';
            paginationHtml += `<li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" onclick="changePage('${tabId}', ${currentPage + 1}); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;

            paginationHtml += '</ul>';
            $pagination.html(paginationHtml);
        }

        function changePage(tabId, page) {
            const purokName = Object.keys(currentKeysData).find(key =>
                key.replace(/\s/g, '').toLowerCase() === tabId
            );
            if (purokName && page >= 1) {
                updateTableForPurok(tabId, currentKeysData[purokName].keys, page);
            }
            return false;
        }

        function searchKeys(tabId) {
            const searchValue = $(`#${tabId}-search`).val().toLowerCase();
            const purokName = Object.keys(currentKeysData).find(key =>
                key.replace(/\s/g, '').toLowerCase() === tabId
            );
            
            if (purokName) {
                const allKeys = currentKeysData[purokName].keys;
                const filteredKeys = allKeys.filter(k => k.key.toLowerCase().includes(searchValue));
                updateTableForPurok(tabId, filteredKeys, 1);
            }
        }

        function exportKeysReport() {
            let reportContent = 'BRGYCare - Confirmation Keys Report\n';
            reportContent += 'Generated: ' + new Date().toLocaleString() + '\n\n';
            reportContent += 'Overall Statistics:\n';
            reportContent += 'Total Keys: <?php echo $overall_total; ?>\n';
            reportContent += 'Available: <?php echo $overall_available; ?>\n';
            reportContent += 'Used: <?php echo $overall_used; ?>\n';
            reportContent += 'Usage Rate: <?php echo $overall_usage_rate; ?>%\n\n';
            
            Object.keys(currentKeysData).sort().forEach(function(purok) {
                const data = currentKeysData[purok];
                reportContent += '\n' + purok + ':\n';
                reportContent += 'Total: ' + data.total + ', Available: ' + data.available + ', Used: ' + data.used + '\n';
                reportContent += 'Keys:\n';
                data.keys.forEach(function(key) {
                    reportContent += '  ' + key.key + ' - ' + (key.used ? 'Used' : 'Available') + '\n';
                });
            });
            
            const blob = new Blob([reportContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'keys_report_' + new Date().getTime() + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        function showError(message) {
            $('#purokTabContent').html(`
                <div class="tab-pane fade show active" role="tabpanel">
                    <div class="text-center text-danger py-5">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h5>${message}</h5>
                    </div>
                </div>
            `);
        }

        $(document).ready(function() {
            updateKeysData();

            if (typeof(EventSource) !== "undefined") {
                eventSource = new EventSource('keys_monitor.php?action=sse');
                eventSource.onmessage = function(event) {
                    try {
                        const newData = JSON.parse(event.data);
                        currentKeysData = newData;
                        updateTabs();
                        $('#lastUpdated').text(new Date().toLocaleString());
                    } catch (e) {
                        console.error('Error parsing SSE data:', e);
                    }
                };
                eventSource.onerror = function() {
                    console.error('SSE connection error, falling back to polling');
                    updateInterval = setInterval(updateKeysData, 5000);
                };
            } else {
                updateInterval = setInterval(updateKeysData, 5000);
            }
        });

        $(window).on('beforeunload', function() {
            if (eventSource) eventSource.close();
            if (updateInterval) clearInterval(updateInterval);
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
        }

        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });
    </script>
    <style>
        .menu-toggle { display: none; }
        @media (max-width: 768px) {
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
        }
    </style>
</body>
</html>
