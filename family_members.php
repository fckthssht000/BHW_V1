<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Helper function to format dates to MM/DD/YYYY
function format_date($date_string) {
    if (empty($date_string) || $date_string === 'N/A' || $date_string === '0000-00-00') {
        return 'N/A';
    }
    $date = DateTime::createFromFormat('Y-m-d', $date_string);
    if (!$date) {
        return 'N/A';
    }
    return $date->format('m/d/Y');
}

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Fetch person_id from records table
$stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_person_id = $stmt->fetchColumn();
if ($user_person_id === false) {
    die("Error: No person record found for this user.");
}

// Fetch role_id from users table
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

// Count total records for pagination
if ($role_id == 3) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.person_id) as total
        FROM person p
        WHERE (p.related_person_id = ? OR p.person_id = ?) AND (p.deceased IS NULL OR p.deceased = 0)
    ");
    $stmt->execute([$user_person_id, $user_person_id]);
} else {
    $purok_condition = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.person_id) as total
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN users u ON r.user_id = u.user_id
        WHERE u.role_id = 3 AND (p.deceased IS NULL OR p.deceased = 0) $purok_condition
    ");
    $params = [];
    if ($role_id == 2 && $user_purok) {
        $params = [$user_purok];
    }
    $stmt->execute($params);
}
$total_records_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_records = $total_records_result['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query based on role WITH PAGINATION
// Query based on role WITH PAGINATION
if ($role_id == 3) {
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.relationship_type, p.gender, p.birthdate, p.age, p.civil_status, p.contact_number, p.household_number, a.purok
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        WHERE (p.related_person_id = ? OR p.person_id = ?) AND (p.deceased IS NULL OR p.deceased = 0)
        GROUP BY p.person_id
        LIMIT " . intval($records_per_page) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute([$user_person_id, $user_person_id]);
} else {
    $purok_condition = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.relationship_type, p.gender, p.birthdate, p.age, p.civil_status, p.contact_number, p.household_number, a.purok
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN users u ON r.user_id = u.user_id
        WHERE u.role_id = 3 AND (p.deceased IS NULL OR p.deceased = 0) $purok_condition
        GROUP BY p.person_id
        ORDER BY a.purok, p.household_number, p.full_name
        LIMIT " . intval($records_per_page) . " OFFSET " . intval($offset) . "
    ");
    $params = [];
    if ($role_id == 2 && $user_purok) {
        $params = [$user_purok];
    }
    $stmt->execute($params);
}
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Calculate statistics (using total, not paginated)
$total_families = 0;
$total_members = $total_records;
$total_households = 0;
$families_with_records = 0;
$record_types_count = [
    'child' => 0,
    'infant' => 0,
    'senior' => 0,
    'pregnant' => 0,
    'postnatal' => 0,
    'family_planning' => 0
];

if ($role_id != 3) {
    $purok_condition = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.household_number) as total_households
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN users u ON r.user_id = u.user_id
        WHERE u.role_id = 3 AND (p.deceased IS NULL OR p.deceased = 0) $purok_condition
    ");
    $params = [];
    if ($role_id == 2 && $user_purok) {
        $params = [$user_purok];
    }
    $stmt->execute($params);
    $household_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_households = $household_result['total_households'];
    $total_families = $total_households;
}

// Fetch records for each person based on record_type
$person_records = [];
foreach ($records as $person) {
    $stmt = $pdo->prepare("
        SELECT r.record_type, MAX(r.records_id) as latest_record_id
        FROM records r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.person_id = ? AND (u.role_id = 3 OR r.user_id = ?)
        GROUP BY r.record_type
    ");
    $stmt->execute([$person['person_id'], $_SESSION['user_id']]);
    $record_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($record_types as $type) {
        $record_type = $type['record_type'];
        $latest_record_id = $type['latest_record_id'];

        switch ($record_type) {
            case 'child_record':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.age, p.gender, chr.weight, chr.height, chr.measurement_date, chr.risk_observed, chr.immunization_status
                    FROM child_record chr
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    WHERE r.person_id = ?
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $person_records[$person['person_id']]['child'] = $record;
                    $record_types_count['child']++;
                }
                break;

            case 'family_planning_record':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.age, p.gender, fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using
                    FROM family_planning_record fpr
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    WHERE r.person_id = ?
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $person_records[$person['person_id']]['family_planning'] = $record;
                    $record_types_count['family_planning']++;
                }
                break;

            case 'child_record.infant_record':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.gender, p.birthdate, cr.weight, cr.height, cr.measurement_date, i.immunization_type, ir.breastfeeding_months, ir.solid_food_start, cr.service_source
                    FROM child_record cr
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
                    LEFT JOIN immunization i ON cr.immunization_id = i.immunization_id
                    WHERE r.person_id = ?
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $birthdate = new DateTime($record['birthdate']);
                    $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
                    $age_in_days = $current_date->diff($birthdate)->days;
                    $age_display = $age_in_days < 30 ? ($age_in_days > 0 ? floor($age_in_days / 7) . ' weeks' : '0') : floor($age_in_days / 30) . ' months';
                    $record['age_display'] = $age_display;
                    $person_records[$person['person_id']]['infant'] = $record;
                    $record_types_count['infant']++;
                }
                break;

            case 'senior_record.medication':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.age, p.gender, sr.bp_reading, sr.bp_date_taken, GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medication_name
                    FROM senior_record sr
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
                    JOIN medication m ON sm.medication_id = m.medication_id
                    WHERE r.person_id = ?
                    GROUP BY p.person_id, p.full_name, p.age, p.gender, sr.bp_reading, sr.bp_date_taken
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $person_records[$person['person_id']]['senior'] = $record;
                    $record_types_count['senior']++;
                }
                break;

            case 'pregnancy_record.prenatal':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.age, p.gender, pre.months_pregnancy, pre.checkup_date, m.medication_name, pre.risk_observed, pre.birth_plan
                    FROM prenatal pre
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    JOIN pregnancy_record prr ON r.records_id = prr.records_id
                    LEFT JOIN medication m ON prr.medication_id = m.medication_id
                    WHERE r.person_id = ?
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $person_records[$person['person_id']]['pregnant'] = $record;
                    $record_types_count['pregnant']++;
                }
                break;

            case 'pregnancy_record.postnatal':
                $stmt = $pdo->prepare("
                    SELECT p.person_id, p.full_name, p.age, p.gender, pr.date_delivered, pr.delivery_location, pr.attendant, pr.risk_observed, pr.postnatal_checkups, GROUP_CONCAT(DISTINCT m.medication_name SEPARATOR ', ') as medication_name, pr.service_source, pr.family_planning_intent
                    FROM postnatal pr
                    JOIN records r ON r.records_id = ?
                    JOIN person p ON r.person_id = p.person_id
                    JOIN pregnancy_record prr ON r.records_id = prr.records_id
                    LEFT JOIN medication m ON prr.medication_id = m.medication_id
                    WHERE r.person_id = ?
                    GROUP BY p.person_id, p.full_name, p.age, p.gender, pr.date_delivered, pr.delivery_location, pr.attendant, pr.risk_observed, pr.postnatal_checkups, pr.service_source, pr.family_planning_intent
                ");
                $stmt->execute([$latest_record_id, $person['person_id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $person_records[$person['person_id']]['postnatal'] = $record;
                    $record_types_count['postnatal']++;
                }
                break;
        }
    }
}

// Count families with records
$households_with_records = [];
foreach ($person_records as $person_id => $records_data) {
    foreach ($records as $person) {
        if ($person['person_id'] == $person_id && !empty($person['household_number'])) {
            $households_with_records[$person['household_number']] = true;
            break;
        }
    }
}
$families_with_records = count($households_with_records);
$coverage_rate = $total_families > 0 ? round(($families_with_records / $total_families) * 100, 1) : 0;

// Group records by purok for BHW Head/Super Admin or BHW Staff
$purok_records = [];
if ($role_id == 1 || $role_id == 4 || $role_id == 2) {
    foreach ($records as $record) {
        $purok = $record['purok'];
        if (!isset($purok_records[$purok])) {
            $purok_records[$purok] = [];
        }
        $purok_records[$purok][] = $record;
    }
}

// Function to sanitize purok names for HTML IDs
function sanitizePurokId($purok) {
    return 'purok-' . preg_replace('/[^a-z0-9]/', '-', strtolower($purok));
}

// Get record type badge
function getRecordTypeBadge($type) {
    $badges = [
        'child' => '<span class="badge badge-primary"><i class="fas fa-child"></i> Child</span>',
        'infant' => '<span class="badge badge-info"><i class="fas fa-baby"></i> Infant</span>',
        'senior' => '<span class="badge badge-secondary"><i class="fas fa-user-shield"></i> Senior</span>',
        'pregnant' => '<span class="badge badge-danger"><i class="fas fa-user-pregnant"></i> Pregnant</span>',
        'postnatal' => '<span class="badge badge-warning"><i class="fas fa-baby-carriage"></i> Postnatal</span>',
        'family_planning' => '<span class="badge badge-success"><i class="fas fa-venus-mars"></i> Family Planning</span>'
    ];
    return $badges[$type] ?? '<span class="badge badge-dark">' . ucfirst($type) . '</span>';
}

// Pagination function
function renderPagination($current_page, $total_pages) {
    if ($total_pages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    $disabled = $current_page == 1 ? 'disabled' : '';
    $html .= '<li class="page-item ' . $disabled . '">';
    $html .= '<a class="page-link" href="?page=' . ($current_page - 1) . '" aria-label="Previous">';
    $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    $disabled = $current_page == $total_pages ? 'disabled' : '';
    $html .= '<li class="page-item ' . $disabled . '">';
    $html .= '<a class="page-link" href="?page=' . ($current_page + 1) . '" aria-label="Next">';
    $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Family Members</title>
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 12px;
        }
        .stat-icon.blue {
            background: linear-gradient(135deg, rgba(43, 108, 176, 0.1) 0%, rgba(43, 108, 176, 0.2) 100%);
            color: #2b6cb0;
        }
        .stat-icon.green {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.1) 0%, rgba(72, 187, 120, 0.2) 100%);
            color: #48bb78;
        }
        .stat-icon.orange {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.1) 0%, rgba(237, 137, 54, 0.2) 100%);
            color: #ed8936;
        }
        .stat-icon.purple {
            background: linear-gradient(135deg, rgba(159, 122, 234, 0.1) 0%, rgba(159, 122, 234, 0.2) 100%);
            color: #9f7aea;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin: 8px 0;
        }
        .stat-sublabel {
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(135deg, rgba(43, 108, 176, 0.9) 0%, rgba(43, 108, 176, 0.7) 100%);
            color: #fff;
            padding: 18px 24px;
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table {
            background: #ffffff;
            border-radius: 10px;
            margin-bottom: 0;
        }
        .table thead th {
            background: rgba(43, 108, 176, 0.9);
            color: #fff;
            border-bottom: none;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 14px 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: #edf2f7;
        }
        .table td {
            padding: 12px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .search-input {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 20px 12px 45px;
            color: #1a202c;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
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
            font-size: 1.1rem;
        }
        .search-input::placeholder { color: #a0aec0; }
        .search-input:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
        }
        
        .nested-table { 
            width: 100%; 
            margin-top: 5px;
            font-size: 0.85rem;
        }
        .nested-table td { 
            padding: 4px 8px;
            border-bottom: 1px solid #edf2f7;
        }
        .nested-table td:first-child {
            font-weight: 600;
            color: #4a5568;
            width: 40%;
        }
        
        .badge {
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge i {
            font-size: 0.85rem;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            color: #4a5568;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
            background: transparent;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .nav-tabs .nav-link:hover {
            background: #edf2f7;
            color: #2b6cb0;
        }
        .nav-tabs .nav-link.active {
            color: #2b6cb0;
            background: #fff;
            border: 2px solid #2b6cb0;
            border-bottom: 2px solid #fff;
            margin-bottom: -2px;
        }
        .tab-content { 
            padding: 0; 
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-info-custom {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }
        
        .pagination {
            margin: 20px 0;
        }
        .pagination .page-link {
            color: #2b6cb0;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            margin: 0 2px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .pagination .page-link:hover {
            background: #2b6cb0;
            color: #fff;
            border-color: #2b6cb0;
        }
        .pagination .page-item.active .page-link {
            background: #2b6cb0;
            border-color: #2b6cb0;
        }
        .pagination .page-item.disabled .page-link {
            color: #a0aec0;
            background: #f7fafc;
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
                padding: 15px;
            }
            .content.with-sidebar {
                margin-left: 0;
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
            }
            .navbar-brand { 
                padding-left: 55px;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .stat-value {
                font-size: 1.5rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
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
                
                <?php if ($role_id == 1 || $role_id == 4 || $role_id == 2): ?>
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="stat-label">Total Families</div>
                            <div class="stat-value"><?php echo number_format($total_families); ?></div>
                            <div class="stat-sublabel"><?php echo number_format($total_households); ?> households</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-label">Total Members</div>
                            <div class="stat-value"><?php echo number_format($total_members); ?></div>
                            <div class="stat-sublabel">Registered residents</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon orange">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="stat-label">Families with Records</div>
                            <div class="stat-value"><?php echo number_format($families_with_records); ?></div>
                            <div class="stat-sublabel"><?php echo $coverage_rate; ?>% coverage</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon purple">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="stat-label">Active Health Records</div>
                            <div class="stat-value"><?php echo number_format(array_sum($record_types_count)); ?></div>
                            <div class="stat-sublabel">All record types</div>
                        </div>
                    </div>
                <?php endif; ?>
                                
                <?php if ($role_id == 3): ?>
                    <!-- Resident View: Family Members -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users"></i>
                            Family Members
                        </div>
                        <div class="card-body">
                            <div class="search-wrapper">
                                <i class="fas fa-search"></i>
                                <input type="text" id="search" class="search-input" placeholder="Search family members..." aria-label="Search">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Relationship</th>
                                            <th>Gender</th>
                                            <th>Birthdate</th>
                                            <th>Age</th>
                                            <th>Civil Status</th>
                                            <th>Contact</th>
                                            <th>Purok</th>
                                        </tr>
                                    </thead>
                                    <tbody id="familyTable">
                                        <?php foreach ($records as $row): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['relationship_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                <td><?php echo format_date($row['birthdate']); ?></td>
                                                <td><?php echo htmlspecialchars($row['age']); ?></td>
                                                <td><?php echo htmlspecialchars($row['civil_status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['purok']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php echo renderPagination($current_page, $total_pages); ?>
                        </div>
                    </div>
                    
                    <!-- Resident View: Family Records -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clipboard-list"></i>
                            Family Health Records
                        </div>
                        <div class="card-body">
                            <?php if (empty($person_records)): ?>
                                <div class="alert-info-custom alert-custom">
                                    <i class="fas fa-info-circle"></i>
                                    <span>No health records found for your family members.</span>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Record Type</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($records as $row): ?>
                                                <?php if (!empty($person_records[$row['person_id']])): ?>
                                                    <?php foreach ($person_records[$row['person_id']] as $record_type => $record): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                            <td><?php echo getRecordTypeBadge($record_type); ?></td>
                                                            <td>
                                                                <table class="nested-table">
                                                                    <?php if ($record_type == 'child'): ?>
                                                                        <tr><td><i class="fas fa-weight"></i> Weight</td><td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?> kg</td></tr>
                                                                        <tr><td><i class="fas fa-ruler-vertical"></i> Height</td><td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?> cm</td></tr>
                                                                        <tr><td><i class="far fa-calendar"></i> Date Measured</td><td><?php echo format_date($record['measurement_date'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-exclamation-triangle"></i> Risk Observed</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-syringe"></i> Immunization Status</td><td><?php echo htmlspecialchars($record['immunization_status'] ?? 'N/A'); ?></td></tr>
                                                                    <?php elseif ($record_type == 'family_planning'): ?>
                                                                        <tr><td><i class="fas fa-check-circle"></i> Uses FP</td><td><?php echo htmlspecialchars($record['uses_fp_method'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-venus-mars"></i> FP Method</td><td><?php echo htmlspecialchars($record['fp_method'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="far fa-clock"></i> Months Used</td><td><?php echo htmlspecialchars($record['months_used'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-comment"></i> Reason Not Using</td><td><?php echo htmlspecialchars($record['reason_not_using'] ?? 'N/A'); ?></td></tr>
                                                                    <?php elseif ($record_type == 'infant'): ?>
                                                                        <tr><td><i class="fas fa-birthday-cake"></i> Age</td><td><?php echo htmlspecialchars($record['age_display'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-weight"></i> Weight</td><td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?> kg</td></tr>
                                                                        <tr><td><i class="fas fa-ruler-vertical"></i> Height</td><td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?> cm</td></tr>
                                                                        <tr><td><i class="far fa-calendar"></i> Date Measured</td><td><?php echo format_date($record['measurement_date'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-baby"></i> Breastfeeding</td><td><?php echo htmlspecialchars($record['breastfeeding_months'] ?? 'N/A'); ?> months</td></tr>
                                                                        <tr><td><i class="fas fa-utensils"></i> Solid Food Start</td><td><?php echo htmlspecialchars($record['solid_food_start'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-syringe"></i> Vaccination</td><td><?php echo htmlspecialchars($record['immunization_type'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-hospital"></i> Service Source</td><td><?php echo htmlspecialchars($record['service_source'] ?? 'N/A'); ?></td></tr>
                                                                    <?php elseif ($record_type == 'senior'): ?>
                                                                        <tr><td><i class="fas fa-heartbeat"></i> Blood Pressure</td><td><?php echo htmlspecialchars($record['bp_reading'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="far fa-calendar"></i> Date Taken</td><td><?php echo format_date($record['bp_date_taken'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-pills"></i> Medication</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                    <?php elseif ($record_type == 'pregnant'): ?>
                                                                        <tr><td><i class="fas fa-calendar-check"></i> Months Pregnant</td><td><?php echo htmlspecialchars($record['months_pregnancy'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="far fa-calendar"></i> Checkup Date</td><td><?php echo htmlspecialchars($record['checkup_date'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-pills"></i> Medication</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-exclamation-triangle"></i> Risk Observed</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-clipboard-check"></i> Birth Plan</td><td><?php echo htmlspecialchars($record['birth_plan'] ?? 'N/A'); ?></td></tr>
                                                                    <?php elseif ($record_type == 'postnatal'): ?>
                                                                        <tr><td><i class="far fa-calendar"></i> Date Delivered</td><td><?php echo format_date($record['date_delivered'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-hospital"></i> Delivery Location</td><td><?php echo htmlspecialchars($record['delivery_location'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-user-md"></i> Attendant</td><td><?php echo htmlspecialchars($record['attendant'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-exclamation-triangle"></i> Risks Observed</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-calendar-check"></i> Postnatal Checkups</td><td><?php echo htmlspecialchars($record['postnatal_checkups'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-pills"></i> Supplements</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-hospital"></i> Service Source</td><td><?php echo htmlspecialchars($record['service_source'] ?? 'N/A'); ?></td></tr>
                                                                        <tr><td><i class="fas fa-venus-mars"></i> FP Intent</td><td><?php echo htmlspecialchars($record['family_planning_intent'] ?? 'N/A'); ?></td></tr>
                                                                    <?php endif; ?>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Admin/BHW View: All Families by Purok -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users"></i>
                            Family Members by Purok <?php echo $role_id == 2 ? "($user_purok)" : ''; ?>
                            <span class="badge badge-light ml-2">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist" style="padding: 15px 15px 0 15px; margin-bottom: 0;">
                                <?php $first = true; foreach (array_keys($purok_records) as $purok): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $first ? 'active' : ''; ?>" data-toggle="tab" href="#<?php echo sanitizePurokId($purok); ?>">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($purok); ?>
                                            <span class="badge badge-secondary ml-2"><?php echo count($purok_records[$purok]); ?></span>
                                        </a>
                                    </li>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" style="padding: 20px;">
                                <?php $first = true; foreach ($purok_records as $purok => $purok_members): ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="<?php echo sanitizePurokId($purok); ?>" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>HH#</th>
                                                        <th>Name</th>
                                                        <th>Relationship</th>
                                                        <th>Gender</th>
                                                        <th>Birthdate</th>
                                                        <th>Age</th>
                                                        <th>Civil Status</th>
                                                        <th>Contact</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($purok_members as $row): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($row['household_number']); ?></strong></td>
                                                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($row['relationship_type']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                            <td><?php echo format_date($row['birthdate']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['age']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['civil_status']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <div style="padding: 0 20px 20px 20px;">
                                <?php echo renderPagination($current_page, $total_pages); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Admin/BHW View: Family Health Records by Purok -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clipboard-list"></i>
                            Family Health Records by Purok
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav nav-tabs" id="recordTabs" role="tablist" style="padding: 15px 15px 0 15px; margin-bottom: 0;">
                                <?php $first = true; foreach (array_keys($purok_records) as $purok): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $first ? 'active' : ''; ?>" data-toggle="tab" href="#records-<?php echo sanitizePurokId($purok); ?>">
                                            <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($purok); ?>
                                        </a>
                                    </li>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" style="padding: 20px;">
                                <?php $first = true; foreach ($purok_records as $purok => $purok_members): ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="records-<?php echo sanitizePurokId($purok); ?>" role="tabpanel">
                                        <?php 
                                        $has_records = false;
                                        foreach ($purok_members as $row) {
                                            if (!empty($person_records[$row['person_id']])) {
                                                $has_records = true;
                                                break;
                                            }
                                        }
                                        ?>
                                        
                                        <?php if (!$has_records): ?>
                                            <div class="alert-info-custom alert-custom">
                                                <i class="fas fa-info-circle"></i>
                                                <span>No health records found for <?php echo htmlspecialchars($purok); ?>.</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>HH#</th>
                                                            <th>Name</th>
                                                            <th>Record Type</th>
                                                            <th>Details</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($purok_members as $row): ?>
                                                            <?php if (!empty($person_records[$row['person_id']])): ?>
                                                                <?php foreach ($person_records[$row['person_id']] as $record_type => $record): ?>
                                                                    <tr>
                                                                        <td><strong><?php echo htmlspecialchars($row['household_number']); ?></strong></td>
                                                                        <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                                        <td><?php echo getRecordTypeBadge($record_type); ?></td>
                                                                        <td>
                                                                            <table class="nested-table">
                                                                                <?php if ($record_type == 'child'): ?>
                                                                                    <tr><td><i class="fas fa-weight"></i> Weight</td><td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?> kg</td></tr>
                                                                                    <tr><td><i class="fas fa-ruler-vertical"></i> Height</td><td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?> cm</td></tr>
                                                                                    <tr><td><i class="far fa-calendar"></i> Date Measured</td><td><?php echo format_date($record['measurement_date'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-exclamation-triangle"></i> Risk Observed</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-syringe"></i> Immunization</td><td><?php echo htmlspecialchars($record['immunization_status'] ?? 'N/A'); ?></td></tr>
                                                                                <?php elseif ($record_type == 'family_planning'): ?>
                                                                                    <tr><td><i class="fas fa-check-circle"></i> Uses FP</td><td><?php echo htmlspecialchars($record['uses_fp_method'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-venus-mars"></i> Method</td><td><?php echo htmlspecialchars($record['fp_method'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="far fa-clock"></i> Duration</td><td><?php echo htmlspecialchars($record['months_used'] ?? 'N/A'); ?> months</td></tr>
                                                                                    <tr><td><i class="fas fa-comment"></i> Reason (if not using)</td><td><?php echo htmlspecialchars($record['reason_not_using'] ?? 'N/A'); ?></td></tr>
                                                                                <?php elseif ($record_type == 'infant'): ?>
                                                                                    <tr><td><i class="fas fa-birthday-cake"></i> Age</td><td><?php echo htmlspecialchars($record['age_display'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-weight"></i> Weight</td><td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?> kg</td></tr>
                                                                                    <tr><td><i class="fas fa-ruler-vertical"></i> Height</td><td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?> cm</td></tr>
                                                                                    <tr><td><i class="far fa-calendar"></i> Measured</td><td><?php echo format_date($record['measurement_date'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-baby"></i> Breastfeeding</td><td><?php echo htmlspecialchars($record['breastfeeding_months'] ?? 'N/A'); ?> mo.</td></tr>
                                                                                    <tr><td><i class="fas fa-utensils"></i> Solid Food</td><td><?php echo htmlspecialchars($record['solid_food_start'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-syringe"></i> Vaccination</td><td><?php echo htmlspecialchars($record['immunization_type'] ?? 'N/A'); ?></td></tr>
                                                                                <?php elseif ($record_type == 'senior'): ?>
                                                                                    <tr><td><i class="fas fa-heartbeat"></i> BP Reading</td><td><?php echo htmlspecialchars($record['bp_reading'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="far fa-calendar"></i> Date Taken</td><td><?php echo format_date($record['bp_date_taken'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-pills"></i> Medication</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                                <?php elseif ($record_type == 'pregnant'): ?>
                                                                                    <tr><td><i class="fas fa-calendar-check"></i> Months Pregnant</td><td><?php echo htmlspecialchars($record['months_pregnancy'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="far fa-calendar"></i> Checkup</td><td><?php echo htmlspecialchars($record['checkup_date'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-pills"></i> Medication</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-exclamation-triangle"></i> Risks</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-clipboard-check"></i> Birth Plan</td><td><?php echo htmlspecialchars($record['birth_plan'] ?? 'N/A'); ?></td></tr>
                                                                                <?php elseif ($record_type == 'postnatal'): ?>
                                                                                    <tr><td><i class="far fa-calendar"></i> Delivered</td><td><?php echo format_date($record['date_delivered'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-hospital"></i> Location</td><td><?php echo htmlspecialchars($record['delivery_location'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-user-md"></i> Attendant</td><td><?php echo htmlspecialchars($record['attendant'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-exclamation-triangle"></i> Risks</td><td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-calendar-check"></i> Checkups</td><td><?php echo htmlspecialchars($record['postnatal_checkups'] ?? 'N/A'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-pills"></i> Supplements</td><td><?php echo htmlspecialchars($record['medication_name'] ?? 'None'); ?></td></tr>
                                                                                    <tr><td><i class="fas fa-venus-mars"></i> FP Intent</td><td><?php echo htmlspecialchars($record['family_planning_intent'] ?? 'N/A'); ?></td></tr>
                                                                                <?php endif; ?>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Search functionality
        $('#search').on('input', function() {
            let value = $(this).val().toLowerCase();
            $('#familyTable tr').filter(function() {
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
        }
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
            .navbar-brand { 
                padding-left: 55px;
            }
        }
    </style>
</body>
</html>
