<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file for a specific year
function refresh_senior_json_file($pdo, $year, $json_file) {
    $query = "
        SELECT p.person_id, p.full_name, p.age, p.gender, p.household_number, sr.bp_reading, sr.bp_date_taken, 
               GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medication_name, a.purok, sr.records_id, sr.created_at
        FROM senior_record sr
        JOIN records r ON r.records_id = sr.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
        JOIN medication m ON sm.medication_id = m.medication_id
        WHERE r.record_type = 'senior_record.medication'
        AND (YEAR(sr.created_at) = ? OR sr.created_at IS NULL)
        GROUP BY p.person_id, p.full_name, p.age, p.gender, p.household_number, sr.bp_reading, sr.bp_date_taken, a.purok, sr.records_id, sr.created_at
        ORDER BY a.purok, p.household_number, p.full_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    file_put_contents($json_file, json_encode($all_records, JSON_PRETTY_PRINT));
    return $all_records;
}

// Function to check if JSON needs refresh
function needs_senior_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT MAX(updated_at) as latest FROM senior_record WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $db_latest = $stmt->fetchColumn();
    
    if (!$db_latest) return false;
    
    $file_time = filemtime($json_file);
    $db_time = strtotime($db_latest);
    
    return $db_time > $file_time;
}

// Handle downloads
if (isset($_POST['download'])) {
    $report_type = $_POST['report_type'] ?? 'barangay';
    $purok = $_POST['purok'] ?? '';
    $year = $_POST['year'] ?? date('Y');

    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role_id_download = $stmt->fetchColumn();

    $user_purok_download = null;
    if ($role_id_download == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_purok_download = $stmt->fetchColumn();
    }

    $json_dir = 'data/senior_health_records/';
    $json_file = $json_dir . $year . '_senior_health_record.json';
    
    if (file_exists($json_file)) {
        $all_data = json_decode(file_get_contents($json_file), true);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.person_id, p.full_name, p.age, p.gender, p.household_number, sr.bp_reading, sr.bp_date_taken, 
                   GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medication_name, a.purok
            FROM senior_record sr
            JOIN records r ON r.records_id = sr.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
            JOIN medication m ON sm.medication_id = m.medication_id
            WHERE r.record_type = 'senior_record.medication'
            GROUP BY p.person_id, p.full_name, p.age, p.gender, p.household_number, sr.bp_reading, sr.bp_date_taken, a.purok
            ORDER BY a.purok, p.household_number, p.full_name
        ");
        $stmt->execute();
        $all_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $data = [];
    
    if ($report_type == 'barangay' && ($role_id_download == 1 || $role_id_download == 4)) {
        $data = $all_data;
    } elseif ($report_type == 'per_purok' && $purok && ($role_id_download == 1 || $role_id_download == 4 || ($role_id_download == 2 && $purok == $user_purok_download))) {
        $data = array_filter($all_data, function($record) use ($purok) {
            return $record['purok'] == $purok;
        });
        $data = array_values($data);
    } else {
        die("Unauthorized: Invalid report type or purok access.");
    }

    if (empty($data)) {
        die("No records found for the selected criteria.");
    }

    $stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    // Define the specific medications to check
    $medication_list = [
        'Amlodipine 5mg',
        'Amlodipine 10mg',
        'Losartan 50mg',
        'Losartan 100mg',
        'Metoprolol 50mg',
        'Carvidolol 12.5mg',
        'Simvastatin 20mg',
        'Metformin 500mg',
        'Metformin 850mg',
        'Gliclazide 30mg'
    ];

    require_once 'vendor/autoload.php';
    ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Senior Health Records</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; }
        @page { size: legal landscape; margin: 8mm; }
        .paper { padding: 10px; }
        .title h1 { text-align: center; font-size: 18px; margin: 0 0 3px; }
        .meta { text-align: center; font-size: 10px; color: #444; margin-bottom: 5px; }
        .address-details { text-align: center; margin-bottom: 8px; font-size: 11px; }
        .address-details span { display: inline-block; margin: 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        th, td { border: 1px solid #000; padding: 1px; text-align: center; word-wrap: break-word; vertical-align: middle; font-size: 11px}
        th { background: #f2f2f2; }
        .grouped-header { background: #e9e9e9; font-weight: bold; }
        .col-narrow { width: 25px; }
        .col-name { width: 95px; }
        .col-small { width: 60px; }
        .col-medium { width: 65px; }
        .col-bp { width: 70px; }
        .col-med { width: 70px; }
        .rotate { writing-mode: vertical-lr; text-orientation: mixed; font-size: 11px; padding: 2px 1px; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="title">
            <h1>Senior Health Records - Medication Tracking</h1>
            <div class="meta">Year: <?php echo $year; ?></div>
        </div>
        <div class="address-details">
            <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
            <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
            <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
            <span><strong>Purok:</strong> <?php echo $report_type == 'per_purok' ? htmlspecialchars($purok) : 'All'; ?></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" class="col-narrow">No.</th>
                    <th colspan="4" class="grouped-header">Personal Information</th>
                    <th colspan="2" class="grouped-header">Blood Pressure</th>
                    <th colspan="10" class="grouped-header">Medication Name</th>
                </tr>
                <tr>
                    <th class="col-name">Full Name</th>
                    <th class="col-small">Age</th>
                    <th class="col-small">Gender</th>
                    <th class="col-medium">HH#</th>
                    <th class="col-bp">Reading</th>
                    <th class="col-bp">Date</th>
                    <?php foreach ($medication_list as $med): ?>
                        <th class="col-med rotate"><?php echo htmlspecialchars($med); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                foreach ($data as $row) {
                    $medications = array_map('trim', explode(',', $row['medication_name'] ?? ''));
                    // Format date to mm/dd/yyyy
                    $bp_date_formatted = 'N/A';
                    if (!empty($row['bp_date_taken']) && $row['bp_date_taken'] !== 'N/A') {
                        $date_obj = DateTime::createFromFormat('Y-m-d', $row['bp_date_taken']);
                        if ($date_obj) {
                            $bp_date_formatted = $date_obj->format('m/d/Y');
                        } else {
                            $bp_date_formatted = $row['bp_date_taken']; // fallback
                        }
                    }
                    echo '<tr>
                        <td>' . $count++ . '</td>
                        <td>' . htmlspecialchars(substr($row['full_name'], 0, 20)) . '</td>
                        <td>' . htmlspecialchars($row['age']) . '</td>
                        <td>' . htmlspecialchars($row['gender']) . '</td>
                        <td>' . htmlspecialchars($row['household_number'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['bp_reading'] ?? 'N/A') . '</td>
                        <td>' . $bp_date_formatted . '</td>';                
                    foreach ($medication_list as $med) {
                        $has_med = in_array($med, $medications) ? '✓' : '';
                        echo '<td>' . $has_med . '</td>';
                    }
                    
                    echo '</tr>';
                }
                for ($i = count($data); $i < 30; $i++) {
                    echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td>';
                    for ($j = 0; $j < 10; $j++) {
                        echo '<td></td>';
                    }
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(8, 8, 8, 8));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Senior_Health_Records_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

// Handle AJAX get record
if (isset($_GET['action']) && $_GET['action'] == 'get_record' && isset($_GET['records_id'])) {
    $records_id = $_GET['records_id'];
    
    $stmt = $pdo->prepare("
        SELECT sr.bp_reading, sr.bp_date_taken, GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medication_name 
        FROM senior_record sr
        JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
        JOIN medication m ON sm.medication_id = m.medication_id
        WHERE sr.records_id = ?
        GROUP BY sr.bp_reading, sr.bp_date_taken
    ");
    $stmt->execute([$records_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode($record);
    } else {
        echo json_encode(['error' => 'Record not found']);
    }
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $current_year = date('Y');
    $json_dir = 'data/senior_health_records/';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    $json_file = $json_dir . $current_year . '_senior_health_record.json';
    
    if ($_POST['action'] == 'update') {
        $records_id = $_POST['records_id'];
        $bp_reading = $_POST['bp_reading'];
        $bp_date_taken = $_POST['bp_date_taken'];
        $medication_names = $_POST['medication_name'] ?? [];
        
        $stmt = $pdo->prepare("UPDATE senior_record SET bp_reading = ?, bp_date_taken = ?, updated_at = NOW() WHERE records_id = ?");
        $stmt->execute([$bp_reading, $bp_date_taken, $records_id]);
        
        $stmt = $pdo->prepare("SELECT senior_record_id FROM senior_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $senior_record_id = $stmt->fetchColumn();
        
        if (!$senior_record_id) {
            die("Error: Could not find senior record for records_id: $records_id");
        }
        
        $stmt = $pdo->prepare("DELETE FROM senior_medication WHERE senior_record_id = ?");
        $stmt->execute([$senior_record_id]);
        
        foreach ($medication_names as $medication_name) {
            $medication_name = trim($medication_name);
            if (!empty($medication_name)) {
                $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                $stmt->execute([$medication_name]);
                $medication_id = $stmt->fetchColumn();
                
                if (!$medication_id) {
                    $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                    $stmt->execute([$medication_name]);
                    $medication_id = $pdo->lastInsertId();
                }
                
                $stmt = $pdo->prepare("INSERT INTO senior_medication (senior_record_id, medication_id) VALUES (?, ?)");
                $stmt->execute([$senior_record_id, $medication_id]);
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "UPDATED: senior_health_record records_id:$records_id"]);
        
        refresh_senior_json_file($pdo, $current_year, $json_file);
        
        header("Location: patient_medication_records.php");
        exit;
    } elseif ($_POST['action'] == 'delete') {
        $records_id = $_POST['records_id'];
        
        $stmt = $pdo->prepare("SELECT senior_record_id FROM senior_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $senior_record_id = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM senior_medication WHERE senior_record_id = ?");
        $stmt->execute([$senior_record_id]);
        
        $stmt = $pdo->prepare("DELETE FROM senior_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        
        $stmt = $pdo->prepare("DELETE FROM records WHERE records_id = ?");
        $stmt->execute([$records_id]);
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "DELETED: senior_health_record records_id:$records_id"]);
        
        refresh_senior_json_file($pdo, $current_year, $json_file);
        
        header("Location: patient_medication_records.php");
        exit;
    }
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_person_id = null;
$user_purok = null;

if ($role_id == 3) {
    $stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_person_id = $stmt->fetchColumn();
    if ($user_person_id === false) {
        die("Error: No person record found for user_id: " . $_SESSION['user_id']);
    }
} elseif ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
    if ($user_purok === false) {
        die("Error: Unable to fetch user's purok.");
    }
}

// Get available years
$stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(created_at) as year FROM senior_record ORDER BY year DESC");
$stmt_years->execute();
$years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// Get selected year
$current_year = date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? (int)$_GET['year'] : $current_year;
$is_editable = ($selected_year == $current_year);

// JSON file management
$json_dir = 'data/senior_health_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}
$json_file = $json_dir . $selected_year . '_senior_health_record.json';

if ($is_editable && needs_senior_json_refresh($pdo, $selected_year, $json_file)) {
    $all_records = refresh_senior_json_file($pdo, $selected_year, $json_file);
} else if (file_exists($json_file)) {
    $json_data = json_decode(file_get_contents($json_file), true);
    $all_records = is_array($json_data) ? $json_data : [];
} else {
    $all_records = refresh_senior_json_file($pdo, $selected_year, $json_file);
}

// Filter by role
$filtered_records = [];

if ($role_id == 3) {
    foreach ($all_records as $record) {
        if ($record['person_id'] == $user_person_id) {
            $filtered_records[] = $record;
        }
    }
} elseif ($role_id == 2 && $user_purok) {
    foreach ($all_records as $record) {
        if ($record['purok'] == $user_purok) {
            $filtered_records[] = $record;
        }
    }
} else {
    $filtered_records = $all_records;
}

// Calculate stats
$total_seniors = count($filtered_records);
$hypertension_count = 0;
$total_bp = 0;
$bp_count = 0;
$on_medication = 0;

foreach ($filtered_records as $record) {
    if (!empty($record['bp_reading']) && $record['bp_reading'] !== 'N/A') {
        $bp_parts = explode('/', $record['bp_reading']);
        if (count($bp_parts) == 2) {
            $systolic = (int)$bp_parts[0];
            if ($systolic >= 140) {
                $hypertension_count++;
            }
            $total_bp += $systolic;
            $bp_count++;
        }
    }
    
    if (!empty($record['medication_name']) && $record['medication_name'] !== 'N/A') {
        $on_medication++;
    }
}

$hypertension_rate = $total_seniors > 0 ? round(($hypertension_count / $total_seniors) * 100, 1) : 0;
$avg_systolic = $bp_count > 0 ? round($total_bp / $bp_count, 0) : 0;
$medication_rate = $total_seniors > 0 ? round(($on_medication / $total_seniors) * 100, 1) : 0;

// Group by purok (single table)
$purok_records = [];

if ($role_id == 1 || $role_id == 4 || $role_id == 2) {
    foreach ($filtered_records as $record) {
        $purok = isset($record['purok']) && !empty($record['purok']) ? $record['purok'] : 'Unknown';
        
        if ($role_id == 2 && $purok != $user_purok) {
            continue;
        }
        
        if (!isset($purok_records[$purok])) {
            $purok_records[$purok] = [];
        }
        $purok_records[$purok][] = $record;
    }
    
    if (empty($purok_records) && ($role_id == 1 || $role_id == 4)) {
        $purok_stmt = $pdo->prepare("SELECT DISTINCT purok FROM address WHERE purok IS NOT NULL AND purok != '' ORDER BY purok");
        $purok_stmt->execute();
        $available_puroks = $purok_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($available_puroks as $purok) {
            $purok_records[$purok] = [];
        }
    }
} else {
    $filtered_records = array_values($filtered_records);
}

function sanitizePurokId($purok) {
    return 'purok-' . preg_replace('/[^a-z0-9]/', '-', strtolower($purok));
}

// Get medication options
$medication_options = [];
$stmt = $pdo->query("SELECT DISTINCT medication_name FROM medication ORDER BY medication_name");
$medication_options = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Patient Medication Records (Enhanced)</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            z-index: 1030;
        }
        .content.with-sidebar { margin-left: 0; }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
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
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #2b6cb0;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .alert-custom {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .alert-warning-custom {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .alert-info-custom {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }
        .alert-danger-custom {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }
        .table {
            background: #ffffff;
            border-radius: 10px;
        }
        .table thead th {
            background: rgba(43, 108, 176, 0.9);
            color: #fff;
            border-bottom: none;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .table td {
            font-size: 0.85rem;
        }
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
        .btn-success {
            background: #28a745;
            border: none;
            padding: 8px 15px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        .nav-tabs .nav-link {
            color: #2d3748;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
            background: #edf2f7;
        }
        .nav-tabs .nav-link.active {
            color: #2b6cb0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-bottom: none;
        }
        .tab-content { padding: 0; padding-top:10px;}
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 38px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #2b6cb0;
            box-shadow: 0 0 5px rgba(43, 108, 176, 0.3);
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
            .card { margin-bottom: 15px; margin-left: 20px; margin-right: 0}
            .table-responsive { overflow-x: auto; }
            .tab-content{font-size: 12px;}
            .navbar-brand { padding-left: 55px;}
            .stats-container {
                margin-left: 0;
                margin-right: 0;
            }           
            .stats-container > [class*="col-"] {
                padding-left: 7.5px;
                padding-right: 7.5px;
            }           
            .stat-card {
                padding: 12px;
                margin-bottom: 0;
            }            
            .stat-value {
                font-size: 1.5rem;
            }           
            .stat-label {
                font-size: 0.75rem;
            }            
            .stat-card small {
                font-size: 0.7rem;
            }
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
            .stats-row {
                display: flex;
                gap: 0;
            }
            .stats-row > div {
                flex: 1;
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
                <?php if ($role_id == 1 || $role_id == 4): ?>
                    <!-- Dashboard Stats -->
                    <div class="row mb-3 stats-container">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Total Seniors</div>
                                <div class="stat-value"><?php echo number_format($total_seniors); ?></div>
                                <small class="text-muted">Monitored patients</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Hypertension Rate</div>
                                <div class="stat-value <?php echo $hypertension_rate > 40 ? 'text-danger' : 'text-success'; ?>"><?php echo $hypertension_rate; ?>%</div>
                                <small class="text-muted"><?php echo $hypertension_count; ?> with HTN</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Avg BP (Systolic)</div>
                                <div class="stat-value <?php echo $avg_systolic >= 140 ? 'text-danger' : 'text-success'; ?>"><?php echo $avg_systolic; ?></div>
                                <small class="text-muted">mmHg average</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">On Medication</div>
                                <div class="stat-value"><?php echo $medication_rate; ?>%</div>
                                <small class="text-muted"><?php echo $on_medication; ?> patients</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_editable): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?php echo $selected_year; ?> are read-only. Switch to <?php echo $current_year; ?> to edit records.
                        </div>
                    <?php endif; ?>
                    <?php if ($hypertension_rate > 50): ?>
                        <div class="alert-danger-custom alert-custom">
                            <i class="fas fa-exclamation-circle"></i> <strong>Critical Alert:</strong> <?php echo $hypertension_rate; ?>% hypertension rate. Immediate intervention needed.
                        </div>
                    <?php elseif ($hypertension_rate > 30): ?>
                        <div class="alert-warning-custom alert-custom">
                            <i class="fas fa-exclamation-triangle"></i> <strong>High Risk Alert:</strong> <?php echo $hypertension_rate; ?>% hypertension rate. Monitor closely.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header"><i class="fas fa-pills"></i> Patient Medication Records <?php echo $role_id == 2 ? "($user_purok)" : ''; ?></div>
                    <div class="card-body p-3">
                        <?php if ($role_id == 1 || $role_id == 4): ?>
                            <!-- Year Tabs -->
                            <ul class="nav nav-tabs mb-2" id="yearTabs" role="tablist">
                                <?php foreach ($years as $y): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $y == $selected_year ? 'active' : ''; ?>" href="?year=<?php echo $y; ?>">
                                            <i class="fas fa-calendar-alt"></i> <?php echo $y; ?> <?php echo $y == $current_year ? '<span class="badge badge-success ml-1">Current</span>' : ''; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" class="mb-3">
                            <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                            <div class="form-group d-inline-block mr-2">
                                <label for="report_type" class="mr-2">Report Type:</label>
                                <select name="report_type" id="report_type" class="form-control d-inline-block w-auto">
                                    <option value="barangay" <?php echo ($role_id == 1 || $role_id == 4) ? '' : 'disabled'; ?>>Whole Barangay</option>
                                    <option value="per_purok">Per Purok</option>
                                </select>
                            </div>
                            <div class="form-group d-inline-block mr-2" id="purok_group" style="display:none;">
                                <label for="purok" class="mr-2">Purok:</label>
                                <select name="purok" id="purok" class="form-control d-inline-block w-auto">
                                    <?php 
                                    $all_puroks = array_unique(array_column($all_records, 'purok'));
                                    sort($all_puroks);
                                    foreach ($all_puroks as $purok): 
                                        if (!empty($purok)): ?>
                                            <option value="<?php echo htmlspecialchars($purok); ?>"><?php echo htmlspecialchars($purok); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="download" class="btn btn-success"><i class="fas fa-file-pdf"></i> Download PDF</button>
                        </form>

                        <?php if ($role_id == 3): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <th>BP</th>
                                            <th>Date</th>
                                            <th>Medication</th>
                                            <?php if ($is_editable): ?>
                                            <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filtered_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['age']); ?></td>
                                                <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($record['bp_reading']); ?></td>
                                                <td><?php 
                                                    $bp_date = $record['bp_date_taken'];
                                                    if (!empty($bp_date) && $bp_date !== 'N/A') {
                                                        $date_obj = DateTime::createFromFormat('Y-m-d', $bp_date);
                                                        echo $date_obj ? htmlspecialchars($date_obj->format('m/d/Y')) : htmlspecialchars($bp_date);
                                                    } else {
                                                        echo htmlspecialchars($bp_date);
                                                    }
                                                ?></td>
                                                <td><?php echo htmlspecialchars($record['medication_name'] ?? 'N/A'); ?></td>
                                                <?php if ($is_editable): ?>
                                                <td>
                                                    <button class="btn btn-sm btn-primary edit-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-sm btn-danger delete-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class="fas fa-trash"></i></button>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($purok_records)): ?>
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <?php $first = true; foreach ($purok_records as $purok => $records): ?>
                                    <?php 
                                    $safe_purok = sanitizePurokId($purok);
                                    $count = count($records);
                                    ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $first ? 'active' : ''; ?>" id="purok-tab-<?php echo $safe_purok; ?>" data-toggle="tab" href="#purok-<?php echo $safe_purok; ?>" role="tab">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($purok); ?> <span class="badge badge-secondary"><?php echo $count; ?></span>
                                        </a>
                                    </li>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" id="purokTabsContent">
                                <?php $first = true; foreach ($purok_records as $purok => $records): ?>
                                    <?php $safe_purok = sanitizePurokId($purok); ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="purok-<?php echo $safe_purok; ?>" role="tabpanel">
                                        <?php if (!empty($records)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>HH#</th>
                                                            <th>Full Name</th>
                                                            <th>Age</th>
                                                            <th>Gender</th>
                                                            <th>BP</th>
                                                            <th>Date</th>
                                                            <th>Medication</th>
                                                            <?php if ($is_editable): ?>
                                                            <th>Actions</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($records as $record): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($record['household_number'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['age']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['bp_reading']); ?></td>
                                                                <td><?php 
                                                                    $bp_date = $record['bp_date_taken'];
                                                                    if (!empty($bp_date) && $bp_date !== 'N/A') {
                                                                        $date_obj = DateTime::createFromFormat('Y-m-d', $bp_date);
                                                                        echo $date_obj ? htmlspecialchars($date_obj->format('m/d/Y')) : htmlspecialchars($bp_date);
                                                                    } else {
                                                                        echo htmlspecialchars($bp_date);
                                                                    }
                                                                ?></td>
                                                                <td><?php echo htmlspecialchars($record['medication_name'] ?? 'N/A'); ?></td>
                                                                <?php if ($is_editable): ?>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary edit-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class="fas fa-edit"></i></button>
                                                                    <button class="btn btn-sm btn-danger delete-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mt-3">
                                                No senior health records found for Purok <?php echo htmlspecialchars($purok); ?>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No senior health records found for the selected year.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
        }
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });

        function initializeSelect2() {
            $('.select2-medication').select2({
                placeholder: "Select medications",
                allowClear: true,
                tags: true,
                tokenSeparators: [',', ';'],
                createTag: function (params) {
                    var term = $.trim(params.term);
                    if (term === '') {
                        return null;
                    }
                    return {
                        id: term,
                        text: term,
                        newTag: true
                    };
                }
            });
        }

        $(document).on('click', '.edit-btn', function() {
            const recordsId = $(this).data('records-id');
            $('#edit_records_id').val(recordsId);
            $.get('?action=get_record&records_id=' + recordsId, function(data) {
                const record = JSON.parse(data);
                $('#edit_bp_reading').val(record.bp_reading);
                $('#edit_bp_date_taken').val(record.bp_date_taken);
                
                const medications = record.medication_name.split(', ');
                $('#edit_medication_name').val(medications).trigger('change');
            });
            $('#editModal').modal('show');
            setTimeout(initializeSelect2, 100);
        });

        $(document).on('click', '.delete-btn', function() {
            const recordsId = $(this).data('records-id');
            $('#delete_records_id').val(recordsId);
            $('#deleteModal').modal('show');
        });

        $('#report_type').on('change', function() {
            const purokGroup = $('#purok_group');
            if ($(this).val() == 'per_purok') {
                purokGroup.show();
            } else {
                purokGroup.hide();
            }
        });

        $(document).ready(function() {
            initializeSelect2();
            
            $('#purokTabs a').on('click', function(e) {
                e.preventDefault();
                $(this).tab('show');
            });

            if ($('#purokTabs .nav-link.active').length === 0) {
                $('#purokTabs .nav-link:first').tab('show');
            }
        });
    </script>
    <style>
        .menu-toggle { display: none; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
        }
    </style>

<?php if ($is_editable): ?>
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Senior Health Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="records_id" id="edit_records_id">
                    <div class="form-group">
                        <label for="edit_bp_reading">Blood Pressure Reading</label>
                        <input type="text" class="form-control" id="edit_bp_reading" name="bp_reading" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_bp_date_taken">Date Taken</label>
                        <input type="date" class="form-control" id="edit_bp_date_taken" name="bp_date_taken" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_medication_name">Medication Name</label>
                        <select class="form-control select2-medication" id="edit_medication_name" name="medication_name[]" multiple="multiple" required style="width: 100%;">
                            <?php foreach ($medication_options as $medication): ?>
                                <option value="<?php echo htmlspecialchars($medication); ?>"><?php echo htmlspecialchars($medication); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Senior Health Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="records_id" id="delete_records_id">
                    <p>Are you sure you want to delete this record?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
