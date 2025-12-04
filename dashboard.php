<?php
session_start();
require_once 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    die("Error: User not found in users table for user_id: " . $_SESSION['user_id']);
}
$role_id = $user['role_id'];

// Fetch user's privileges
$stmt = $pdo->prepare("
    SELECT p.privilege_id, p.privilege_name 
    FROM role_privilege rp 
    JOIN privilege p ON rp.privilege_id = p.privilege_id 
    WHERE rp.role_id = ?
");
$stmt->execute([$role_id]);
$privileges = [];
while ($row = $stmt->fetch()) {
    $privileges[$row['privilege_id']] = $row['privilege_name'];
}

// Require dashboard access privilege
if (!isset($privileges[9]) || $privileges[9] != 'access_dashboard') {
    header("Location: index.php");
    exit;
}

// Use session person_id if available, fallback to records
$user_person_id = $_SESSION['person_id'] ?? null;
if (!$user_person_id) {
    $stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_person_id = $stmt->fetchColumn();
    if ($user_person_id === false) {
        die("Error: No person record found for user_id: " . $_SESSION['user_id']);
    }
}

// Determine assigned purok for role_id = 2
$assigned_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM address a
        JOIN person p ON a.address_id = p.address_id
        JOIN records r ON p.person_id = r.person_id
        WHERE r.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $assigned_purok = $stmt->fetchColumn();
}

// --- 🔹 Dashboard Card Data ---
$purok_filter = ($role_id == 2 && $assigned_purok) ? "AND a.purok = :assigned_purok" : "";
$params = ($role_id == 2 && $assigned_purok) ? ['assigned_purok' => $assigned_purok] : [];

$query = "
    SELECT 
        COUNT(DISTINCT p.household_number) as total_household,
        COUNT(DISTINCT p.person_id) as total_population,
        COUNT(DISTINCT CASE WHEN p.gender = 'M' THEN p.person_id END) as total_male,
        COUNT(DISTINCT CASE WHEN p.gender = 'F' THEN p.person_id END) as total_female,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 0 AND 1 THEN p.person_id END) as infant_count,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 1 AND 5 THEN p.person_id END) as early_childhood_count,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 6 AND 12 THEN p.person_id END) as middle_childhood_count,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 13 AND 19 THEN p.person_id END) as teen_count,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 20 AND 59 THEN p.person_id END) as adult_count,
        COUNT(DISTINCT CASE WHEN p.age >= 60 THEN p.person_id END) as elderly_count
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN records r ON p.person_id = r.person_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE (p.deceased IS NULL OR p.deceased = 0)
    AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4))
    $purok_filter
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 🔹 Health Statistics ---
$health_query = "
    SELECT 
        COUNT(DISTINCT CASE WHEN r.record_type = 'pregnancy_record.prenatal' AND prr.pregnancy_period = 'Prenatal' THEN p.person_id END) as pregnant_count,
        COUNT(DISTINCT CASE WHEN p.age >= 60 THEN p.person_id END) as senior_count,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 0 AND 1 THEN p.person_id END) as infant_count,
        COUNT(DISTINCT CASE WHEN r.record_type = 'child_record.infant_record' THEN p.person_id END) as tracked_infant_count,
        COUNT(DISTINCT CASE WHEN r.record_type = 'senior_record.medication' THEN p.person_id END) as tracked_senior_count,
        COUNT(DISTINCT CASE WHEN r.record_type = 'pregnancy_record.postnatal' AND prr.pregnancy_period = 'Postnatal' THEN p.person_id END) as postnatal_count
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN records r ON p.person_id = r.person_id
    LEFT JOIN pregnancy_record prr ON r.records_id = prr.records_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE (p.deceased IS NULL OR p.deceased = 0)
    AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4))
    $purok_filter
";
$stmt = $pdo->prepare($health_query);
$stmt->execute($params);
$health_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate health coverage
$vulnerable_population = $health_data['pregnant_count'] + $health_data['senior_count'] + $health_data['infant_count'];
$tracked_population = $health_data['pregnant_count'] + $health_data['tracked_senior_count'] + $health_data['tracked_infant_count'];
$coverage_rate = $vulnerable_population > 0 ? round(($tracked_population / $vulnerable_population) * 100, 1) : 0;

// --- 🔹 Purok Statistics ---
$purok_query = "
    SELECT 
        a.purok,
        COUNT(DISTINCT p.household_number) as household_count,
        (
            SELECT COUNT(DISTINCT p2.person_id) 
            FROM person p2 
            JOIN address a2 ON p2.address_id = a2.address_id 
            WHERE a2.purok = a.purok 
            AND (p2.deceased IS NULL OR p2.deceased = 0)
            GROUP BY p2.household_number 
            ORDER BY COUNT(DISTINCT p2.person_id) DESC 
            LIMIT 1
        ) as mode_inhabitants,
        AVG((
            SELECT COUNT(DISTINCT p3.person_id) 
            FROM person p3 
            JOIN address a3 ON p3.address_id = a3.address_id 
            WHERE a3.purok = a.purok 
            AND p3.household_number = p.household_number 
            AND (p3.deceased IS NULL OR p3.deceased = 0)
        )) as avg_residents
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN records r ON p.person_id = r.person_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE (p.deceased IS NULL OR p.deceased = 0)
    AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4))
    $purok_filter
    GROUP BY a.purok
";
$stmt = $pdo->prepare($purok_query);
$stmt->execute($params);
$purok_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 🔹 Household Heads + Members ---
$sql = "SELECT p.person_id, p.household_number, p.full_name, a.purok 
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        WHERE p.relationship_type = 'Head' 
        AND p.household_number IS NOT NULL 
        AND p.household_number != 0";
$params_households = [];
if ($role_id == 2 && $assigned_purok) {
    $sql .= " AND a.purok = :assigned_purok";
    $params_households['assigned_purok'] = $assigned_purok;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params_households);
$heads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build households array
$households = [];
foreach ($heads as $h) {
    $stmt = $pdo->prepare("
        SELECT full_name, relationship_type, health_condition 
        FROM person 
        WHERE household_number = ? AND person_id != ?
    ");
    $stmt->execute([$h['household_number'], $h['person_id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $households[] = [
        "household_number" => $h['household_number'],
        "head_name" => $h['full_name'],
        "purok" => $h['purok'],
        "members" => $members
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Professional Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-layers.tree@latest/dist/L.Control.Layers.Tree.css">
    <link rel="stylesheet" href="css/qgis2web.css">
    <link rel="stylesheet" href="css/fontawesome-all.min.css">
    <link rel="stylesheet" href="css/leaflet.photon.css">
<style>
    /* Restore Original Scrolling */
    body, html {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow-y: auto; /* FIXED: Enable vertical scrolling */
        width: 100%;
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }

    body::-webkit-scrollbar, html::-webkit-scrollbar {
        width: 8px;
    }

    body::-webkit-scrollbar-track, html::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    body::-webkit-scrollbar-thumb, html::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    body::-webkit-scrollbar-thumb:hover, html::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* ORIGINAL COLOR SCHEME RESTORED */
    :root {
        --primary-blue: #2b6cb0;        /* YOUR ORIGINAL BLUE */
        --primary-dark: #2c5282;        /* YOUR ORIGINAL DARK BLUE */
        --success-green: #10b981;
        --warning-orange: #f59e0b;
        --danger-red: #ef4444;
        --purple: #8b5cf6;
        --pink: #ec4899;
        --indigo: #6366f1;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
    }

    body {
        background: linear-gradient(135deg, #e0eafc, #cfdef3);
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: #1a202c;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    /* Modern Navbar - ORIGINAL COLORS */
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
    .navbar, .navbar * {
        line-height: 1.2 !important;
    }
    .navbar .nav-link,
    .navbar .dropdown-toggle,
    .search-icon {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
        line-height: 1.2 !important;
    }
    .navbar-brand, .nav-link { color: #fff; font-weight: 500; }
    .navbar-brand:hover, .nav-link:hover { color: #e2e8f0; }

    /* Premium Sidebar - ORIGINAL STYLE */
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
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar .nav-link {
        color: #2d3748;
        padding: 12px 20px;
        border-radius: 8px;
        margin: 4px 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar .nav-link i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background: #edf2f7;
        color: #2b6cb0; /* YOUR ORIGINAL BLUE */
        transform: translateX(4px);
    }

    /* Main Content Area - FIXED SCROLLING */
    .content {
        margin-left: 250px;
        padding: 30px;
        min-height: calc(100vh - 80px);
        background: transparent;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: visible; /* FIXED: Allow content to scroll naturally */
    }

    /* Dashboard Header */
    .dashboard-header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 15px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        animation: slideDown 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .dashboard-header h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .dashboard-header h1 i {
        color: #2b6cb0; /* YOUR ORIGINAL BLUE */
    }

    .dashboard-header .subtitle {
        font-size: 0.95rem;
        color: var(--gray-700);
        margin-top: 4px;
    }

    .export-btn {
        background: linear-gradient(135deg, #2b6cb0 0%, #2c5282 100%); /* YOUR ORIGINAL BLUE */
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3); /* YOUR ORIGINAL BLUE */
        cursor: pointer;
    }

    .export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(43, 108, 176, 0.4); /* YOUR ORIGINAL BLUE */
    }

    /* Premium Stats Cards */
    .stats-row {
        display: grid;
        gap: 20px;
        margin-bottom: 15px;
        animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    

    .stats-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2b6cb0 0%, #2c5282 100%); /* YOUR ORIGINAL BLUE */
    }

    .stats-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    }

    .stats-card.stat-primary::before {
        background: linear-gradient(90deg, #2b6cb0 0%, #2c5282 100%); /* YOUR ORIGINAL BLUE */
    }

    .stats-card.stat-success::before {
        background: linear-gradient(90deg, var(--success-green) 0%, #059669 100%);
    }

    .stats-card.stat-warning::before {
        background: linear-gradient(90deg, var(--warning-orange) 0%, #d97706 100%);
    }

    .stats-card.stat-danger::before {
        background: linear-gradient(90deg, var(--danger-red) 0%, #dc2626 100%);
    }

    .stats-card.stat-purple::before {
        background: linear-gradient(90deg, var(--purple) 0%, #7c3aed 100%);
    }

    .stats-card.stat-pink::before {
        background: linear-gradient(90deg, var(--pink) 0%, #db2777 100%);
    }

    .stat-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        flex-shrink: 0;
    }

    .stat-icon.icon-primary {
        background: rgba(43, 108, 176, 0.1); /* YOUR ORIGINAL BLUE */
        color: #2b6cb0; /* YOUR ORIGINAL BLUE */
    }

    .stat-icon.icon-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .stat-icon.icon-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-orange);
    }

    .stat-icon.icon-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
    }

    .stat-icon.icon-purple {
        background: rgba(139, 92, 246, 0.1);
        color: var(--purple);
    }

    .stat-icon.icon-pink {
        background: rgba(236, 72, 153, 0.1);
        color: var(--pink);
    }

    .stats-card > div:not(.stat-icon) {
        flex: 1;
    }

    .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--gray-700);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .stat-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--gray-900);
        line-height: 1;
        margin-bottom: 2px;
    }

    .stat-change {
        font-size: 0.75rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stat-change.positive {
        color: var(--success-green);
    }

    .stat-change.negative {
        color: var(--danger-red);
    }

    .stat-change.neutral {
        color: var(--gray-700);
    }

    /* Chart Cards */
    .chart-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        margin-bottom: 10px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chart-card:hover {
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    }

    .chart-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
    }

    /* Map Card */
    .map-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        margin-bottom: 15px;
        height: 600px;
    }

    #map {
        height: 600px;
        width: 100%;
    }

    /* Table Enhancements */
    .table-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
        height: 600px; /* Match map height */
        display: flex;
        flex-direction: column;
    }

    .table-card .table-responsive {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden; /* Prevent horizontal scroll */
        max-height: calc(600px - 100px); /* Account for header and padding */
    }

    .table-card h3 {
        flex-shrink: 0; /* Keep header fixed */
        margin-bottom: 16px;
    }

    .table {
        margin-bottom: 0;
        width: 100%
    }

    .table thead th,
    .table tbody td {
        white-space: nowrap;
        padding: 12px 14px; /* Slightly reduce padding */
        font-size: 0.875rem;
    }

    .table-card .table-responsive::-webkit-scrollbar {
        width: 6px;
    }

    .table-card .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-card .table-responsive::-webkit-scrollbar-thumb {
        background: #2b6cb0;
        border-radius: 4px;
    }

    .table-card .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #2c5282;
    }


    .table thead th {
        background: rgba(43, 108, 176, 0.9); /* YOUR ORIGINAL BLUE */
        color: white;
        font-weight: 600;
        border: none;
        padding: 14px 10px;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background: var(--gray-50);
        transform: scale(1.01);
    }

    .table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid var(--gray-200);
    }

    /* Animations */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            left: -250px;
        }

        .sidebar.open {
            left: 0;
        }

        .content {
            margin-left: 0;
            padding: 20px;
        }

        .dashboard-header {
            flex-direction: column;
            gap: 16px;
            text-align: center;
        }

        .stats-row {
            grid-template-columns: repeat(2, 1fr); /* 2 columns */
            gap: 15px;
            padding: 0 10px;
        }
        
        .stats-card {
            margin: 0 !important; /* Remove extra margins */
            padding: 20px 16px;
        }
        
        .stat-value {
            font-size: 1.75rem; /* Slightly smaller for mobile */
        }
        
        .stat-label {
            font-size: 0.75rem; /* Smaller label */
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            font-size: 1.4rem;
            margin-bottom: 12px;
        }
        
        .stat-change {
            font-size: 0.75rem;
        }

        .chart-container {
            height: 250px;
        }

        #map {
            height: 400px;
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
            font-size: 1rem;
            padding-left: 55px;
        }
        .household-table {
            width: 37% !important;
            margin-top: 0px;
            margin-bottom: 0px;
            margin-right: auto;
            padding: 4px !important;
            overflow-x: auto !important;
            box-sizing: border-box !important;
        }
        .household-table .table-responsive {
            overflow-x: auto !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .household-table .table {
            width: 200 !important;  /* or higher if more columns */
            min-width: 100 !important;
            font-size: 0.80rem;
            table-layout: auto !important;
        }
        .household-table .table th,
        .household-table .table td {
            padding: 4px 8px !important;
            text-align: left;
        }
        .household-table .form-control{
            width: 100%;
        }
        .household-header {
            width: 37% !important;
            flex-direction: column !important;
            gap: 16px !important;
            text-align: center !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px 8px !important;
            box-sizing: border-box !important;
        }
        input[type=checkbox], input[type=radio] {
            box-sizing: border-box;
            padding: 0;
            margin-left: 5px;
        }
    }
    @media (min-width: 769px) and (max-width: 1024px) {
        .stats-row {
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        
        .stats-card {
            padding: 20px 18px;
        }
        
        .stat-value {
            font-size: 2rem;
        }
    }

    /* Extra small mobile (if needed) */
    @media (max-width: 480px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr); /* Still 2x2 */
            gap: 12px;
        }
        
        .stat-value {
            font-size: 1.5rem;
        }
        
        .stat-label {
            font-size: 0.7rem;
        }

        .stats-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .stat-icon {
            margin-bottom: 8px !important;
        }
    }
    @media (max-width: 400px) {
        .dashboard-header {
            flex-direction: column;
            gap: 16px;
            text-align: center;
            width: 99%;
        }
        .stats-row {
            grid-template-columns: repeat(2, 1fr); /* Still 2x2 */
            gap: 12px;
            width: 99%;
        }
        
        .stat-value {
            font-size: 1.5rem;
        }
        
        .stat-label {
            font-size: 0.7rem;
        }

        .stats-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .stat-icon {
            margin-bottom: 8px !important;
        }
        .stat-change {
            font-size: 0.7rem;
        }
        .chart-card {
            width: 99%;
        }
        .map-card{
            width: 99%;
            height: 80%;
            margin-bottom: -70px;
        }
        #map {
            height: 400px;
        }
        .col-md-8{
            margin-bottom: -15%;
        }
        .table-card {
            padding: 11px 8px !important;
            margin-bottom: 11px !important;
            border-radius: 12px !important;
            min-height: 0 !important;
            height: auto !important;
        }
        .table-card .table-responsive {
            max-height: unset !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            border-radius: 10px !important;
        }
        .table-card h3 {
            margin-bottom: 10px !important;
            font-size: 1rem !important;
            line-height: 1.15;
        }
        .table thead th, .table tbody td {
            font-size: 0.7rem !important;
            padding: 7px 5px !important;
            white-space: normal !important;
            line-height: 1.22 !important;
        }
        .table thead th {
            padding: 8px 5px !important;
        }
        .table tbody td {
            padding: 8px 7px !important;
        }
        .table-card .table-responsive::-webkit-scrollbar {
            width: 4px;
        }

        .household-table {
            width: 50% !important;
            margin-top: 0px;
            margin-bottom: 0px;
            margin-right: auto;
            padding: 4px !important;
            overflow-x: auto !important;
            box-sizing: border-box !important;
        }
        .household-table .table-responsive {
            overflow-x: auto !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .household-table .table {
            width: 200 !important;  /* or higher if more columns */
            min-width: 100 !important;
            font-size: 0.80rem;
            table-layout: auto !important;
        }
        .household-table .table th,
        .household-table .table td {
            padding: 4px 8px !important;
            text-align: left;
        }
        .household-table .form-control{
            width: 100%;
        }
        .household-header {
            width: 50% !important;
            flex-direction: column !important;
            gap: 16px !important;
            text-align: center !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px 8px !important;
            box-sizing: border-box !important;
        }
        input[type=checkbox], input[type=radio] {
            box-sizing: border-box;
            padding: 0;
            margin-left: 5px;
        }
    }


    .stats-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2b6cb0 0%, #2c5282 100%);
    }

    .stats-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    }

    @media (min-width: 769px) {
        .menu-toggle { 
            display: none; 
        }
        .stats-row {
            grid-template-columns: repeat(4, 1fr); /* 4 equal columns */
        }
    }

    /* Loading Animation */
    .loader {
        border: 4px solid var(--gray-200);
        border-top: 4px solid #2b6cb0; /* YOUR ORIGINAL BLUE */
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* List Group Items */
    .list-group-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 8px;
        padding: 15px;
        color: #2d3748;
        transition: all 0.2s ease;
        border-left: 4px solid #2b6cb0; /* YOUR ORIGINAL BLUE */
    }

    .list-group-item:hover {
        background: #f7fafc;
        transform: translateX(5px);
    }

    /* Buttons */
    .btn-primary {
        background: #2b6cb0; /* YOUR ORIGINAL BLUE */
        border: none;
        padding: 8px 15px;
        font-size: 0.875rem;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-primary:hover {
        background: #2c5282; /* YOUR ORIGINAL DARK BLUE */
        transform: translateY(-1px);
    }

    .btn-secondary {
        background: #718096;
        border: none;
        padding: 8px 15px;
        font-size: 0.875rem;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-secondary:hover {
        background: #4a5568;
        transform: translateY(-1px);
    }

    .btn-danger {
        background: #e53e3e;
        border: none;
        padding: 8px 15px;
        font-size: 0.875rem;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-danger:hover {
        background: #c53030;
        transform: translateY(-1px);
    }

    /* Form Controls */
    .form-control {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 15px;
        color: #1a202c;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: #2b6cb0; /* YOUR ORIGINAL BLUE */
        outline: none;
        box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1); /* YOUR ORIGINAL BLUE */
    }
</style>

</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="content">
                <?php if (in_array($role_id, [1, 2, 4])): ?>
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <div>
                            <h1>
                                <i class="fas fa-chart-line"></i>
                                Dashboard Overview
                            </h1>
                            <div class="subtitle">
                                <i class="far fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                                <?php if ($assigned_purok): ?>
                                    | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($assigned_purok); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button onclick="exportDashboard()" class="export-btn">
                            <i class="fas fa-download"></i>
                            Export Report
                        </button>
                    </div>

                    <!-- Population Statistics -->
                    <div class="stats-row">
                        <div class="stats-card stat-primary">
                            <div class="stat-icon icon-primary">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Total Households</div>
                                <div class="stat-value"><?php echo number_format($data['total_household']); ?></div>
                                <div class="stat-change neutral">
                                    <i class="fas fa-info-circle"></i>
                                    Active households
                                </div>
                            </div>                            
                        </div>

                        <div class="stats-card stat-success">
                            <div class="stat-icon icon-success">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Total Population</div>
                                <div class="stat-value"><?php echo number_format($data['total_population']); ?></div>
                                <div class="stat-change neutral">
                                    <i class="fas fa-male"></i> <?php echo number_format($data['total_male']); ?> Male
                                    <span style="margin: 0 8px;">|</span>
                                    <i class="fas fa-female"></i> <?php echo number_format($data['total_female']); ?> Female
                                </div>
                            </div>                             
                        </div>

                        <div class="stats-card stat-warning">
                            <div class="stat-icon icon-warning">
                                <i class="fas fa-child"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Children (0-12)</div>
                                <div class="stat-value">
                                    <?php echo number_format($data['infant_count'] + $data['early_childhood_count'] + $data['middle_childhood_count']); ?>
                                </div>
                                <div class="stat-change neutral">
                                    <?php 
                                    $total_population = $data['total_population'];
                                    if ($total_population > 0) {
                                        $child_percentage = round((($data['infant_count'] + $data['early_childhood_count'] + $data['middle_childhood_count']) / $total_population) * 100, 1);
                                        echo $child_percentage;
                                    } else {
                                        echo "0";
                                    }
                                    ?>% of population
                                </div>
                            </div>                               
                        </div>

                        <div class="stats-card stat-danger">
                            <div class="stat-icon icon-danger">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Senior Citizens</div>
                                <div class="stat-value"><?php echo number_format($data['elderly_count']); ?></div>
                                <div class="stat-change neutral">
                                    <?php
                                    $total_population = $data['total_population'];
                                    $elderly_count = $data['elderly_count'];
                                    if ($total_population > 0) {
                                        echo round(($elderly_count / $total_population) * 100, 1);
                                    } else {
                                        echo "0";
                                    }
                                    ?>% of population
                                </div>
                            </div>                               
                        </div>
                    </div>

                    <!-- Health Statistics -->
                    <div class="stats-row">
                        <div class="stats-card stat-pink">
                            <div class="stat-icon icon-pink">
                                <i class="fas fa-user-pregnant"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Pregnant Women</div>
                                <div class="stat-value"><?php echo number_format($health_data['pregnant_count']); ?></div>
                                <div class="stat-change neutral">
                                    <i class="fas fa-heartbeat"></i>
                                    Currently monitored
                                </div>
                            </div>                              
                        </div>

                        <div class="stats-card stat-purple">
                            <div class="stat-icon icon-purple">
                                <i class="fas fa-baby-carriage"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Postnatal Care</div>
                                <div class="stat-value"><?php echo number_format($health_data['postnatal_count']); ?></div>
                                <div class="stat-change neutral">
                                    <i class="fas fa-clinic-medical"></i>
                                    Active cases
                                </div>
                            </div>                           
                        </div>

                        <div class="stats-card stat-warning">
                            <div class="stat-icon icon-warning">
                                <i class="fas fa-baby"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Infants (0-1 yr)</div>
                                <div class="stat-value"><?php echo number_format($health_data['infant_count']); ?></div>
                                <div class="stat-change neutral">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo number_format($health_data['tracked_infant_count']); ?> tracked
                                </div>
                            </div>
                        </div>

                        <div class="stats-card stat-success">
                            <div class="stat-icon icon-success">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Health Coverage</div>
                                <div class="stat-value"><?php echo $coverage_rate; ?>%</div>
                                <div class="stat-change <?php echo $coverage_rate >= 80 ? 'positive' : ($coverage_rate >= 60 ? 'neutral' : 'negative'); ?>">
                                    <i class="fas fa-chart-line"></i>
                                    <?php echo number_format($tracked_population); ?> / <?php echo number_format($vulnerable_population); ?> vulnerable tracked
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="chart-card">
                                <h3><i class="fas fa-chart-bar"></i> Household Statistics</h3>
                                <div class="chart-container">
                                    <canvas id="householdChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-card">
                                <h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3>
                                <div class="chart-container">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-card">
                                <h3><i class="fas fa-users-cog"></i> Age Classification</h3>
                                <div class="chart-container">
                                    <canvas id="ageChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map & Table Section -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="map-card">
                                <div id="map"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="table-card">
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-900); margin-bottom: 20px;">
                                    <i class="fas fa-map-marked-alt"></i> Purok Statistics
                                </h3>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Purok</th>
                                                <th>Households</th>
                                                <th>Residents</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($purok_data as $purok) {
                                                $stmt = $pdo->prepare("
                                                    SELECT COUNT(DISTINCT p.person_id) as resident_count
                                                    FROM person p
                                                    JOIN address a ON p.address_id = a.address_id
                                                    LEFT JOIN records r ON p.person_id = r.person_id
                                                    LEFT JOIN users u ON r.user_id = u.user_id
                                                    WHERE a.purok = :purok
                                                    AND (p.deceased IS NULL OR p.deceased = 0)
                                                    AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4))
                                                ");
                                                $stmt->execute(['purok' => $purok['purok']]);
                                                $resident_count = $stmt->fetchColumn();
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($purok['purok']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($purok['household_count']); ?></td>
                                                    <td><?php echo htmlspecialchars($resident_count); ?></td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($role_id == 3): ?>
                    <!-- Resident View (existing code) -->
                    <div class="dashboard-header household-header">
                        <div>
                            <h1>
                                <i class="fas fa-home"></i>
                                My Household
                            </h1>
                            <div class="subtitle">
                                <i class="far fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="table-card household-table">
                        <div class="mb-3">
                            <input type="text" id="search" class="form-control" placeholder="Search members..." style="border-radius: 10px; padding: 12px 20px;">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Relationship</th>
                                        <th>Gender</th>
                                        <th>Birthdate</th>
                                        <th>Age</th>
                                        <th>Civil Status</th>
                                        <th>Contact Number</th>
                                        <th>Purok</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="memberTable">
                                    <?php
                                    $params_members = [$user_person_id, $user_person_id];
                                    if ($role_id == 2) {
                                        $params_members[] = $user_person_id;
                                    }
                                    $stmt = $pdo->prepare("SELECT p.*, a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.related_person_id = ? OR p.person_id = ? " . $purok_filter . " AND (p.deceased IS NULL OR p.deceased = 0)");
                                    $stmt->execute($params_members);
                                    while ($row = $stmt->fetch()) {
                                        echo "<tr>
                                            <td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>
                                            <td>" . htmlspecialchars($row['relationship_type']) . "</td>
                                            <td>" . htmlspecialchars($row['gender']) . "</td>
                                            <td>" . htmlspecialchars($row['birthdate']) . "</td>
                                            <td>" . htmlspecialchars($row['age']) . "</td>
                                            <td>" . htmlspecialchars($row['civil_status']) . "</td>
                                            <td>" . htmlspecialchars($row['contact_number']) . "</td>
                                            <td>" . htmlspecialchars($row['purok']) . "</td>
                                            <td>
                                                <button class='btn btn-sm btn-primary' onclick='viewRecord(" . $row['person_id'] . ")'><i class='fas fa-eye'></i></button>
                                                <button class='btn btn-sm btn-secondary' onclick='editRecord(" . $row['person_id'] . ")'><i class='fas fa-edit'></i></button>
                                                <button class='btn btn-sm btn-danger' onclick='deleteRecord(" . $row['person_id'] . ")'><i class='fas fa-trash'></i></button>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <!-- View Modal -->
        <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewModalLabel">View Member</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="viewForm">
                            <input type="hidden" id="view_person_id">
                            <div class="form-group">
                                <label for="view_full_name">Name</label>
                                <input type="text" class="form-control form-control-modal" id="view_full_name" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_relationship_type">Relationship</label>
                                <input type="text" class="form-control form-control-modal" id="view_relationship_type" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_gender">Gender</label>
                                <input type="text" class="form-control form-control-modal" id="view_gender" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_birthdate">Birthdate</label>
                                <input type="text" class="form-control form-control-modal" id="view_birthdate" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_civil_status">Civil Status</label>
                                <input type="text" class="form-control form-control-modal" id="view_civil_status" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_contact_number">Contact Number</label>
                                <input type="text" class="form-control form-control-modal" id="view_contact_number" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view_purok">Purok</label>
                                <input type="text" class="form-control form-control-modal" id="view_purok" readonly>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Member</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="editForm">
                            <input type="hidden" id="edit_person_id">
                            <div class="form-group">
                                <label for="edit_full_name">Name</label>
                                <input type="text" class="form-control form-control-modal" id="edit_full_name" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_relationship_type">Relationship</label>
                                <select type="text" class="form-control form-control-modal" id="edit_relationship_type">
                                    <option value="Head" readonly>Head(Head ng Pamilya)</option>
                                    <option value="Spouse">Spouse (Asawa)</option>
                                    <option value="Son">Son (Anak na lalaki)</option>                                    
                                    <option value="Daughter">Daughter (Anak na babae)</option>
                                    <option value="Parent">Parent (Magulang)</option>
                                    <option value="Sibling">Sibling (Kapatid)</option>
                                    <option value="Mother-in-Law">Mother-in-Law (Biyenan)</option>
                                    <option value="Father-in-Law">Father-in-Law (Biyenan)</option>
                                    <option value="Brother-in-Law">Brother-in-Law (Hipag)</option>
                                    <option value="Sister-in-Law">Sister-in-Law (Bayaw)</option>
                                    <option value="None">None (Wala)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_gender">Gender</label>
                                <select class="form-control form-control-modal" id="edit_gender">
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_birthdate">Birthdate</label>
                                <input type="date" class="form-control form-control-modal" id="edit_birthdate">
                            </div>
                            <div class="form-group">
                                <label for="edit_civil_status">Civil Status</label>
                                <select class="form-control form-control-modal" id="edit_civil_status">
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_contact_number">Contact Number</label>
                                <input type="tel" class="form-control form-control-modal" id="edit_contact_number" pattern="[0-9]{10,11}" placeholder="e.g., 09171234567">
                            </div>
                            <div class="form-group">
                                <label for="edit_purok">Purok</label>
                                <input type="text" class="form-control form-control-modal" id="edit_purok">
                            </div>
                            <div class="form-group">
                                <label for="edit_deceased">Deceased</label>
                                <input type="checkbox" class="form-check-input" id="edit_deceased">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this member? This action cannot be undone.</p>
                        <input type="hidden" id="delete_person_id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                    </div>
                </div>
            </div>
        </div>    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-layers.tree@latest/dist/L.Control.Layers.Tree.min.js"></script>
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
    <script src="js/leaflet.rotatedMarker.js"></script>
    <script src="js/leaflet.pattern.js"></script>
    <script src="js/leaflet-hash.js"></script>
    <script src="js/leaflet.photon.js"></script>
    <script src="js/Autolinker.min.js"></script>
    <script src="js/rbush.min.js"></script>
    <script src="js/labelgun.min.js"></script>
    <script src="js/labels.js"></script>
    <script src="data/sta_maria_0.js"></script>
    <script src="data/Purokcenters_1.js"></script>
    <script src="data/Randompointsinpolygons_3.js"></script>
    <script src="data/stamariapurok_2.js"></script>
    <script src="data/stamaria_3.js"></script>
         <script>
            // ==================== UTILITY FUNCTIONS ====================
            
            // Search functionality
            $('#search').on('input', function() {
                let value = $(this).val().toLowerCase();
                $('#memberTable tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Toggle sidebar
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

            // ==================== MODAL FUNCTIONS ====================

            function viewRecord(id) {
                $.ajax({
                    url: 'process/fetch_person.php',
                    type: 'POST',
                    data: { person_id: id },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('#view_person_id').val(data.person_id);
                            $('#view_full_name').val(data.full_name);
                            $('#view_relationship_type').val(data.relationship_type);
                            $('#view_gender').val(data.gender);
                            $('#view_birthdate').val(data.birthdate);
                            $('#view_civil_status').val(data.civil_status);
                            $('#view_contact_number').val(data.contact_number);
                            $('#view_purok').val(data.purok);
                            $('#viewModal').modal('show');
                        } else {
                            alert('Error fetching data: ' + data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while fetching data.');
                    }
                });
            }

            function editRecord(id) {
                $.ajax({
                    url: 'process/fetch_person.php',
                    type: 'POST',
                    data: { person_id: id },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('#edit_person_id').val(data.person_id);
                            $('#edit_full_name').val(data.full_name);
                            $('#edit_relationship_type').val(data.relationship_type);
                            $('#edit_gender').val(data.gender);
                            $('#edit_birthdate').val(data.birthdate);
                            $('#edit_civil_status').val(data.civil_status);
                            $('#edit_contact_number').val(data.contact_number);
                            $('#edit_purok').val(data.purok);
                            $('#edit_deceased').prop('checked', data.deceased === 1);
                            $('#editModal').modal('show');
                        } else {
                            alert('Error fetching data: ' + data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while fetching data.');
                    }
                });
            }

            function saveEdit() {
                const personId = $('#edit_person_id').val();
                const formData = {
                    person_id: personId,
                    full_name: $('#edit_full_name').val(),
                    relationship_type: $('#edit_relationship_type').val(),
                    gender: $('#edit_gender').val(),
                    birthdate: $('#edit_birthdate').val(),
                    civil_status: $('#edit_civil_status').val(),
                    contact_number: $('#edit_contact_number').val(),
                    purok: $('#edit_purok').val(),
                    deceased: $('#edit_deceased').is(':checked') ? 1 : 0
                };

                $.ajax({
                    url: 'process/update_person.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Member updated successfully.');
                            location.reload();
                        } else {
                            alert('Error updating member: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while updating.');
                    }
                });
                $('#editModal').modal('hide');
            }

            function deleteRecord(id) {
                $('#delete_person_id').val(id);
                $('#deleteModal').modal('show');
            }

            function confirmDelete() {
                const personId = $('#delete_person_id').val();
                $.ajax({
                    url: 'process/delete_person.php',
                    type: 'POST',
                    data: { person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Member deleted successfully.');
                            location.reload();
                        } else {
                            alert('Error deleting member: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while deleting.');
                    }
                });
                $('#deleteModal').modal('hide');
            }

            // ==================== EXPORT DASHBOARD ====================

            function exportDashboard() {
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>BRGYCare Dashboard Report</title>
                        <style>
                            @page { size: A4 landscape; margin: 15mm; }
                            body { 
                                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                                padding: 20px; 
                                color: #333;
                                line-height: 1.6;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 30px;
                                padding-bottom: 20px;
                                border-bottom: 3px solid #2563eb;
                            }
                            h1 { 
                                color: #2563eb; 
                                margin: 0 0 10px 0;
                                font-size: 28px;
                            }
                            .subtitle {
                                color: #666;
                                font-size: 14px;
                            }
                            .section-title {
                                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                                color: white;
                                padding: 12px 20px;
                                border-radius: 8px;
                                margin: 25px 0 15px 0;
                                font-size: 18px;
                                font-weight: 600;
                            }
                            .stat-grid { 
                                display: grid; 
                                grid-template-columns: repeat(4, 1fr); 
                                gap: 15px; 
                                margin: 20px 0; 
                            }
                            .stat-box { 
                                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                                padding: 18px; 
                                border-radius: 12px; 
                                border-left: 5px solid #2563eb;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                            }
                            .stat-box h3 { 
                                margin: 0 0 8px 0; 
                                font-size: 13px; 
                                color: #64748b;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                            }
                            .stat-box p { 
                                margin: 0; 
                                font-size: 28px; 
                                font-weight: 800; 
                                color: #1e293b;
                            }
                            .stat-box small { 
                                font-size: 11px; 
                                color: #64748b;
                                display: block;
                                margin-top: 5px;
                            }
                            table { 
                                width: 100%; 
                                border-collapse: collapse; 
                                margin: 20px 0;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                            }
                            th, td { 
                                border: 1px solid #e2e8f0; 
                                padding: 12px; 
                                text-align: left; 
                            }
                            th { 
                                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                                color: white;
                                font-weight: 600;
                                text-transform: uppercase;
                                font-size: 12px;
                                letter-spacing: 0.5px;
                            }
                            tbody tr:nth-child(even) {
                                background: #f8fafc;
                            }
                            tbody tr:hover {
                                background: #e0e7ff;
                            }
                            .footer { 
                                text-align: center; 
                                margin-top: 40px; 
                                padding-top: 20px;
                                border-top: 2px solid #e2e8f0;
                                font-size: 12px; 
                                color: #64748b;
                            }
                            .footer .logo {
                                font-size: 16px;
                                font-weight: 700;
                                color: #2563eb;
                                margin-bottom: 5px;
                            }
                            .badge {
                                display: inline-block;
                                padding: 4px 10px;
                                border-radius: 12px;
                                font-size: 11px;
                                font-weight: 600;
                                margin-left: 8px;
                            }
                            .badge-success {
                                background: #d1fae5;
                                color: #065f46;
                            }
                            .badge-warning {
                                background: #fef3c7;
                                color: #92400e;
                            }
                            .badge-danger {
                                background: #fee2e2;
                                color: #7f1d1d;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>🏥 BRGYCare Dashboard Report</h1>
                            <div class="subtitle">
                                Generated: <?php echo date('F d, Y h:i A'); ?>
                                <?php if ($assigned_purok): ?>
                                    | Purok: <?php echo htmlspecialchars($assigned_purok); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="section-title">📊 Population Statistics</div>
                        <div class="stat-grid">
                            <div class="stat-box">
                                <h3>Total Households</h3>
                                <p><?php echo number_format($data['total_household']); ?></p>
                                <small>Active households</small>
                            </div>
                            <div class="stat-box">
                                <h3>Total Population</h3>
                                <p><?php echo number_format($data['total_population']); ?></p>
                                <small>Current residents</small>
                            </div>
                            <div class="stat-box">
                                <h3>Male</h3>
                                <p><?php echo number_format($data['total_male']); ?></p>
                                <small><?php echo round(($data['total_male'] / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                            <div class="stat-box">
                                <h3>Female</h3>
                                <p><?php echo number_format($data['total_female']); ?></p>
                                <small><?php echo round(($data['total_female'] / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                            <div class="stat-box">
                                <h3>Children (0-12)</h3>
                                <p><?php echo number_format($data['infant_count'] + $data['early_childhood_count'] + $data['middle_childhood_count']); ?></p>
                                <small><?php echo round((($data['infant_count'] + $data['early_childhood_count'] + $data['middle_childhood_count']) / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                            <div class="stat-box">
                                <h3>Teens (13-19)</h3>
                                <p><?php echo number_format($data['teen_count']); ?></p>
                                <small><?php echo round(($data['teen_count'] / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                            <div class="stat-box">
                                <h3>Adults (20-59)</h3>
                                <p><?php echo number_format($data['adult_count']); ?></p>
                                <small><?php echo round(($data['adult_count'] / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                            <div class="stat-box">
                                <h3>Senior Citizens</h3>
                                <p><?php echo number_format($data['elderly_count']); ?></p>
                                <small><?php echo round(($data['elderly_count'] / $data['total_population']) * 100, 1); ?>% of population</small>
                            </div>
                        </div>

                        <div class="section-title">💊 Health Statistics</div>
                        <div class="stat-grid">
                            <div class="stat-box">
                                <h3>Pregnant Women</h3>
                                <p><?php echo number_format($health_data['pregnant_count']); ?></p>
                                <small>Currently monitored</small>
                            </div>
                            <div class="stat-box">
                                <h3>Postnatal Care</h3>
                                <p><?php echo number_format($health_data['postnatal_count']); ?></p>
                                <small>Active cases</small>
                            </div>
                            <div class="stat-box">
                                <h3>Infants (0-1 yr)</h3>
                                <p><?php echo number_format($health_data['infant_count']); ?></p>
                                <small><?php echo number_format($health_data['tracked_infant_count']); ?> tracked in records</small>
                            </div>
                            <div class="stat-box">
                                <h3>Health Coverage</h3>
                                <p><?php echo $coverage_rate; ?>%
                                    <?php if ($coverage_rate >= 80): ?>
                                        <span class="badge badge-success">Excellent</span>
                                    <?php elseif ($coverage_rate >= 60): ?>
                                        <span class="badge badge-warning">Good</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Needs Improvement</span>
                                    <?php endif; ?>
                                </p>
                                <small><?php echo number_format($tracked_population); ?> / <?php echo number_format($vulnerable_population); ?> vulnerable population tracked</small>
                            </div>
                        </div>

                        <div class="section-title">📍 Purok Statistics</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Purok</th>
                                    <th>Total Households</th>
                                    <th>Total Residents</th>
                                    <th>Avg. Residents/Household</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_households = 0;
                                $total_residents = 0;
                                foreach ($purok_data as $purok): 
                                    $stmt = $pdo->prepare("
                                        SELECT COUNT(DISTINCT p.person_id) as resident_count
                                        FROM person p
                                        JOIN address a ON p.address_id = a.address_id
                                        LEFT JOIN records r ON p.person_id = r.person_id
                                        LEFT JOIN users u ON r.user_id = u.user_id
                                        WHERE a.purok = :purok
                                        AND (p.deceased IS NULL OR p.deceased = 0)
                                        AND (u.role_id IS NULL OR u.role_id NOT IN (1, 2, 4))
                                    ");
                                    $stmt->execute(['purok' => $purok['purok']]);
                                    $resident_count = $stmt->fetchColumn();
                                    $total_households += $purok['household_count'];
                                    $total_residents += $resident_count;
                                    $avg_residents = $purok['household_count'] > 0 ? round($resident_count / $purok['household_count'], 1) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($purok['purok']); ?></strong></td>
                                    <td><?php echo number_format($purok['household_count']); ?></td>
                                    <td><?php echo number_format($resident_count); ?></td>
                                    <td><?php echo $avg_residents; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #e0e7ff; font-weight: bold;">
                                    <td>TOTAL</td>
                                    <td><?php echo number_format($total_households); ?></td>
                                    <td><?php echo number_format($total_residents); ?></td>
                                    <td><?php echo $total_households > 0 ? round($total_residents / $total_households, 1) : 0; ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="footer">
                            <div class="logo">🏥 BRGYCare Health Information System</div>
                            <div>Barangay Sta. Maria, Camiling, Tarlac</div>
                            <div style="margin-top: 10px;">
                                <em>This is a computer-generated report. No signature required.</em>
                            </div>
                        </div>
                    </body>
                    </html>
                `;

                const printWindow = window.open('', '', 'height=800,width=1200');
                printWindow.document.write(printContent);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            }

            // Initialize accordion
            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });

            // Initialize modals and charts
            $(document).ready(function() {
                $('#viewModal').on('show.bs.modal', function() {
                    console.log('View modal shown');
                });
                $('#viewModal').on('hidden.bs.modal', function() {
                    console.log('View modal hidden');
                });
                $('#editModal').on('show.bs.modal', function() {
                    console.log('Edit modal shown');
                });
                $('#editModal').on('hidden.bs.modal', function() {
                    console.log('Edit modal hidden');
                });
                $('#deleteModal').on('show.bs.modal', function() {
                    console.log('Delete modal shown');
                });
                $('#deleteModal').on('hidden.bs.modal', function() {
                    console.log('Delete modal hidden');
                });

                // Chart.js for Household Chart with detailed breakdown (Bar + Line)
                Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';
            
            // 1. HOUSEHOLD CHART (Bar + Line Combo)
            const householdCtx = document.getElementById('householdChart');
            if (householdCtx) {
                new Chart(householdCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($purok_data, 'purok')); ?>,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Total Households',
                                data: <?php echo json_encode(array_column($purok_data, 'household_count')); ?>,
                                backgroundColor: 'rgba(37, 99, 235, 0.8)',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                                barPercentage: 0.6
                            },
                            {
                                type: 'line',
                                label: 'Avg Residents',
                                data: <?php echo json_encode(array_map(function($x) { return round($x, 1); }, array_column($purok_data, 'avg_residents'))); ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                tension: 0.4,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointBackgroundColor: '#10b981',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    },
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y;
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // 2. GENDER CHART (Doughnut)
            const genderCtx = document.getElementById('genderChart');
            if (genderCtx) {
                new Chart(genderCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Male', 'Female'],
                        datasets: [{
                            data: [<?php echo $data['total_male']; ?>, <?php echo $data['total_female']; ?>],
                            backgroundColor: [
                                'rgba(37, 99, 235, 0.8)',
                                'rgba(236, 72, 153, 0.8)'
                            ],
                            borderColor: [
                                'rgba(37, 99, 235, 1)',
                                'rgba(236, 72, 153, 1)'
                            ],
                            borderWidth: 3,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    },
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        return data.labels.map((label, index) => {
                                            const value = data.datasets[0].data[index];
                                            const percentage = ((value / <?php echo $data['total_population']; ?>) * 100).toFixed(1);
                                            return {
                                                text: `${label}: ${value.toLocaleString()} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[index],
                                                hidden: false,
                                                index: index
                                            };
                                        });
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label;
                                        const value = context.parsed;
                                        const percentage = ((value / <?php echo $data['total_population']; ?>) * 100).toFixed(1);
                                        return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            }

            // 3. AGE CHART (Bar)
            const ageCtx = document.getElementById('ageChart');
            if (ageCtx) {
                new Chart(ageCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['0-1\n(Infant)', '1-5\n(Early)', '6-12\n(Middle)', '13-19\n(Teen)', '20-59\n(Adult)', '60+\n(Elderly)'],
                        datasets: [{
                            label: 'Population',
                            data: [
                                <?php echo $data['infant_count']; ?>,
                                <?php echo $data['early_childhood_count']; ?>,
                                <?php echo $data['middle_childhood_count']; ?>,
                                <?php echo $data['teen_count']; ?>,
                                <?php echo $data['adult_count']; ?>,
                                <?php echo $data['elderly_count']; ?>
                            ],
                            backgroundColor: [
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(99, 102, 241, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ],
                            borderColor: [
                                'rgba(236, 72, 153, 1)',
                                'rgba(245, 158, 11, 1)',
                                'rgba(139, 92, 246, 1)',
                                'rgba(99, 102, 241, 1)',
                                'rgba(16, 185, 129, 1)',
                                'rgba(239, 68, 68, 1)'
                            ],
                            borderWidth: 2,
                            borderRadius: 8,
                            barPercentage: 0.7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed.y;
                                        const percentage = ((value / <?php echo $data['total_population']; ?>) * 100).toFixed(1);
                                        return `Population: ${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
            // ==================== LEAFLET MAP INITIALIZATION ====================


            // Fallback functions for missing dependencies
        function removeEmptyRowsFromPopupContent(content, feature) {
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            var rows = tempDiv.querySelectorAll('tr');
            for (var i = 0; i < rows.length; i++) {
                var td = rows[i].querySelector('td.visible-with-data');
                var key = td ? td.id : '';
                if (td && td.classList.contains('visible-with-data') && feature.properties[key] == null) {
                    rows[i].parentNode.removeChild(rows[i]);
                }
            }
            return tempDiv.innerHTML;
        }

        function addClassToPopupIfMedia(content, popup) {
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            if (tempDiv.querySelector('td img')) {
                popup._contentNode.classList.add('media');
                setTimeout(function() { popup.update(); }, 5);
            } else {
                popup._contentNode.classList.remove('media');
            }
        }

        // Initialize map
        var map = L.map('map', {
            zoomControl: true,
            maxZoom: 18,
            minZoom: 10
        }).setView([15.641971867806623, 120.425852712125973], 15);
        
        var hash = new L.Hash(map);
        map.attributionControl.setPrefix('<a href="https://leafletjs.com">Leaflet</a> | BRGYCare');
        var autolinker = new Autolinker({ truncate: { length: 30, location: 'smart' } });
        var bounds_group = new L.featureGroup([]);
        var householdMarkers = {};

        // Barangay Hall Custom Marker
        var hallIcon = L.icon({
            iconUrl: 'imghall.png',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });

        var brgyHallGeoJSON = {
            "type": "FeatureCollection",
            "features": [
                { "type": "Feature", "properties": { "brgyhall": "brgyhall" }, "geometry": { "type": "Point", "coordinates": [120.425852712125973, 15.641971867806623] } }
            ]
        };

        L.geoJSON(brgyHallGeoJSON, {
            pointToLayer: function(feature, latlng) {
                return L.marker(latlng, { icon: hallIcon });
            },
            onEachFeature: function(feature, layer) {
                layer.bindPopup("<div style='text-align: center; padding: 10px;'><h4 style='margin: 0 0 8px 0; color: #2563eb;'>🏛️ Barangay Hall</h4><p style='margin: 0;'><strong>Sta. Maria</strong><br>Camiling, Tarlac</p></div>");
                bounds_group.addLayer(layer);
            }
        }).addTo(map);

        // Layer: sta_maria_0
        function pop_sta_maria_0(feature, layer) {
            var popupContent = '<table><tr><td colspan="2"><strong>' + (feature.properties['ADM4_EN'] !== null ? feature.properties['ADM4_EN'] : '') + '</strong></td></tr></table>';
            layer.bindPopup(popupContent, { maxHeight: 400 });
        }

        function style_sta_maria_0_0() {
            return {
                pane: 'pane_sta_maria_0',
                opacity: 1,
                color: '#4ade80',
                dashArray: '',
                lineCap: 'square',
                lineJoin: 'bevel',
                weight: 4.0,
                fillOpacity: 0,
                interactive: true,
            };
        }
        
        map.createPane('pane_sta_maria_0');
        map.getPane('pane_sta_maria_0').style.zIndex = 400;
        var layer_sta_maria_0 = new L.geoJson(json_sta_maria_0, {
            interactive: true,
            pane: 'pane_sta_maria_0',
            onEachFeature: pop_sta_maria_0,
            style: style_sta_maria_0_0,
        });
        bounds_group.addLayer(layer_sta_maria_0);
        map.addLayer(layer_sta_maria_0);

        // Layer: Purokcenters_1
        function style_Purokcenters_1_0() {
            return {
                pane: 'pane_Purokcenters_1',
                radius: 5.0,
                opacity: 1,
                color: '#2563eb',
                fillColor: '#fff',
                weight: 2,
                fill: true,
                fillOpacity: 1,
                interactive: true,
            };
        }
        map.createPane('pane_Purokcenters_1');
        map.getPane('pane_Purokcenters_1').style.zIndex = 401;
        var layer_Purokcenters_1 = new L.geoJson(json_Purokcenters_1, {
            interactive: true,
            pane: 'pane_Purokcenters_1',
            onEachFeature: function(feature, layer) {
                layer.bindPopup('<div style="padding: 8px;"><strong>' + (feature.properties['Purok'] || 'Unknown') + '</strong></div>');
            },
            pointToLayer: function(feature, latlng) {
                return L.circleMarker(latlng, style_Purokcenters_1_0());
            },
        });
        bounds_group.addLayer(layer_Purokcenters_1);
        map.addLayer(layer_Purokcenters_1);

        // Layer: stamariapurok_2
        function style_stamariapurok_2_0() {
            return {
                pane: 'pane_stamariapurok_2',
                opacity: 1,
                color: '#64748b',
                dashArray: '',
                weight: 2.0,
                fill: true,
                fillOpacity: 0.3,
                fillColor: '#e0e7ff',
                interactive: true,
            };
        }
        map.createPane('pane_stamariapurok_2');
        map.getPane('pane_stamariapurok_2').style.zIndex = 402;
        var layer_stamariapurok_2 = new L.geoJson(json_stamariapurok_2, {
            interactive: true,
            pane: 'pane_stamariapurok_2',
            onEachFeature: function(feature, layer) {
                layer.bindPopup('<div style="padding: 8px;"><strong>Purok: ' + (feature.properties['Purok'] || 'Unknown') + '</strong></div>');
            },
            style: style_stamariapurok_2_0,
        });
        bounds_group.addLayer(layer_stamariapurok_2);
        map.addLayer(layer_stamariapurok_2);

        // Extract centroids
        var purokCentroids = {};
        layer_stamariapurok_2.eachLayer(function(layer) {
            var purokName = layer.feature.properties['Purok'];
            if (purokName) {
                var centroid = layer.getBounds().getCenter();
                purokCentroids[purokName.toUpperCase()] = [centroid.lat, centroid.lng];
            }
        });

        // Labels
        layer_stamariapurok_2.eachLayer(function(layer) {
            layer.bindTooltip(
                '<div style="color: #1e293b; font-size: 12px; font-weight: 700; text-shadow: 1px 1px 2px rgba(255,255,255,0.8);">' + (layer.feature.properties['Purok'] || '') + '</div>',
                { permanent: true, direction: 'center', className: 'purok-label' }
            );
        });

        // Layer: stamaria_3
        function style_stamaria_3_0() {
            return {
                pane: 'pane_stamaria_3',
                opacity: 1,
                color: '#64748b',
                weight: 1.0,
                fill: true,
                fillOpacity: 0.2,
                fillColor: '#9ca3af',
                interactive: true,
            };
        }
        map.createPane('pane_stamaria_3');
        map.getPane('pane_stamaria_3').style.zIndex = 403;
        var layer_stamaria_3 = new L.geoJson(json_stamaria_3, {
            interactive: true,
            pane: 'pane_stamaria_3',
            style: style_stamaria_3_0,
        });
        bounds_group.addLayer(layer_stamaria_3);
        map.addLayer(layer_stamaria_3);

        // Group random points by purok
        var randomPointsByPurok = {};
        var randomPointsGeoJSON = json_Randompointsinpolygons_3.features || [];
        var purokPolygons = {};

        layer_stamariapurok_2.eachLayer(function(layer) {
            var purokName = layer.feature.properties['Purok'];
            if (purokName) {
                var geoJSON = layer.toGeoJSON();
                if (geoJSON.geometry.type === 'Polygon' || geoJSON.geometry.type === 'MultiPolygon') {
                    purokPolygons[purokName] = turf.feature(geoJSON.geometry, { name: purokName });
                }
            }
        });

        randomPointsGeoJSON.forEach(function(pointFeature) {
            var point = turf.point(pointFeature.geometry.coordinates);
            var assignedPurok = null;
            for (var purokName in purokPolygons) {
                if (turf.booleanPointInPolygon(point, purokPolygons[purokName])) {
                    assignedPurok = purokName;
                    break;
                }
            }
            if (assignedPurok) {
                if (!randomPointsByPurok[assignedPurok]) {
                    randomPointsByPurok[assignedPurok] = [];
                }
                randomPointsByPurok[assignedPurok].push({
                    lat: pointFeature.geometry.coordinates[1],
                    lng: pointFeature.geometry.coordinates[0]
                });
            }
        });

        // Household markers
        var households = <?php echo json_encode($households); ?>;

        function updateHouseholdMarkers() {
            Object.keys(householdMarkers).forEach(function(key) {
                if (householdMarkers[key]) {
                    map.removeLayer(householdMarkers[key]);
                }
            });
            householdMarkers = {};

            bounds_group.clearLayers();
            bounds_group.addLayer(layer_sta_maria_0);
            bounds_group.addLayer(layer_Purokcenters_1);
            bounds_group.addLayer(layer_stamariapurok_2);
            bounds_group.addLayer(layer_stamaria_3);

            var householdsByPurok = {};
            households.forEach(function(h) {
                var purokKey = h.purok.trim();
                if (!householdsByPurok[purokKey]) {
                    householdsByPurok[purokKey] = [];
                }
                householdsByPurok[purokKey].push(h);
            });

            Object.keys(householdsByPurok).forEach(function(purok) {
                householdsByPurok[purok].sort(function(a, b) {
                    return a.household_number - b.household_number;
                });
            });

            var dotIcon = L.divIcon({
                html: '<div style="width: 14px; height: 14px; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                className: '',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            Object.keys(householdsByPurok).forEach(function(purokKey) {
                var purokPoints = randomPointsByPurok[purokKey] || [];
                if (purokPoints.length === 0) {
                    var fallbackCoords = purokCentroids[purokKey.toUpperCase()];
                    if (fallbackCoords) {
                        var num = householdsByPurok[purokKey].length;
                        var radius = 0.001;
                        householdsByPurok[purokKey].forEach(function(h, index) {
                            var angle = (index / num) * 2 * Math.PI;
                            var randomRadius = radius * (0.5 + 0.5 * Math.random());
                            var lat = fallbackCoords[0] + randomRadius * Math.cos(angle);
                            var lng = fallbackCoords[1] + randomRadius * Math.sin(angle);
                            var content = '<div style="padding: 12px; min-width: 200px;"><h4 style="margin: 0 0 8px 0; color: #2563eb;">🏠 Household ' + h.household_number + '</h4><p style="margin: 0;"><strong>Head:</strong> ' + h.head_name + '<br><strong>Purok:</strong> ' + h.purok + '</p></div>';
                            var marker = L.marker([lat, lng], {icon: dotIcon}).addTo(map).bindPopup(content);
                            marker.householdNumber = h.household_number;
                            householdMarkers[h.household_number] = marker;
                            bounds_group.addLayer(marker);
                        });
                    }
                    return;
                }

                householdsByPurok[purokKey].forEach(function(h, householdIndex) {
                    var pointIndex = householdIndex % purokPoints.length;
                    var point = purokPoints[pointIndex];
                    var content = '<div style="padding: 12px; min-width: 200px;"><h4 style="margin: 0 0 8px 0; color: #2563eb;">🏠 Household ' + h.household_number + '</h4><p style="margin: 0;"><strong>Head:</strong> ' + h.head_name + '<br><strong>Purok:</strong> ' + h.purok + '</p></div>';
                    var marker = L.marker([point.lat, point.lng], {icon: dotIcon}).addTo(map).bindPopup(content);
                    marker.householdNumber = h.household_number;
                    householdMarkers[h.household_number] = marker;
                    bounds_group.addLayer(marker);
                });
            });

            if (Object.keys(householdMarkers).length > 0) {
                map.fitBounds(bounds_group.getBounds().pad(0.05));
            }
        }

        updateHouseholdMarkers();

        window.addEventListener('resize', function() {
            map.invalidateSize();
        });
    </script>

    <style>
        .purok-label {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
        }
        .leaflet-popup-content-wrapper {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .leaflet-popup-tip {
            box-shadow: 0 3px 14px rgba(0,0,0,0.1);
        }
    </style>
</body>
</html>