<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file for a specific year
function refresh_json_file($pdo, $year, $json_file) {
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.birthdate, p.gender, p.household_number, chr.weight, chr.height, chr.measurement_date, chr.risk_observed, chr.immunization_status, a.purok, chr.records_id, chr.created_at
        FROM child_record chr
        JOIN records r ON r.records_id = chr.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE p.birthdate IS NOT NULL
        AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 365 AND 2555
        AND (YEAR(chr.created_at) = ? OR YEAR(chr.measurement_date) = ?)
        ORDER BY a.purok, p.household_number, p.full_name
    ");
    $stmt->execute([$year, $year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($json_file, json_encode($all_records));
    return $all_records;
}

// Function to check if JSON needs refresh
function needs_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT MAX(created_at) as latest FROM child_record WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $db_latest = $stmt->fetchColumn();
    
    $file_time = filemtime($json_file);
    
    if (!$db_latest) return false;
    
    $db_time = strtotime($db_latest);
    return $db_time > $file_time;
}

// Handle downloads
if (isset($_POST['download'])) {
    $report_type = $_POST['report_type'] ?? 'barangay';
    $purok = $_POST['purok'] ?? '';

    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role_id_download = $stmt->fetchColumn();

    $user_purok_download = null;
    if ($role_id_download == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_purok_download = $stmt->fetchColumn();
    }

    $data = [];
    if ($report_type == 'barangay' && ($role_id_download == 1 || $role_id_download == 4)) {
        $stmt = $pdo->prepare("SELECT p.full_name, p.birthdate, p.gender, p.household_number, chr.weight, chr.height, chr.measurement_date, chr.risk_observed, chr.immunization_status, a.purok FROM child_record chr JOIN records r ON r.records_id = chr.records_id JOIN person p ON r.person_id = p.person_id JOIN address a ON p.address_id = a.address_id WHERE p.birthdate IS NOT NULL AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 365 AND 2555 ORDER BY a.purok, p.household_number, p.full_name");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($report_type == 'per_purok' && $purok && ($role_id_download == 1 || $role_id_download == 4 || ($role_id_download == 2 && $purok == $user_purok_download))) {
        $stmt = $pdo->prepare("SELECT p.full_name, p.birthdate, p.gender, p.household_number, chr.weight, chr.height, chr.measurement_date, chr.risk_observed, chr.immunization_status, a.purok FROM child_record chr JOIN records r ON r.records_id = chr.records_id JOIN person p ON r.person_id = p.person_id JOIN address a ON p.address_id = a.address_id WHERE p.birthdate IS NOT NULL AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 365 AND 2555 AND a.purok = ? ORDER BY p.household_number, p.full_name");
        $stmt->execute([$purok]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        die("Unauthorized: Invalid report type or purok access.");
    }

    if (empty($data)) {
        die("No records found for the selected criteria.");
    }

    $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $year = $current_date->format('Y');

    $stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    require_once 'vendor/autoload.php';
    ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Child Health Records</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { padding: 12px; }
        .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
        .meta { text-align: center; font-size: 12px; color: #444; }
        .address-details { text-align: center; margin-bottom: 10px; }
        .address-details span { display: inline-block; margin: 0 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 2px; text-align: center; word-wrap: break-word; }
        th { background: #f2f2f2; }
        .grouped-header { background: #e9e9e9; }
        .col-narrow { width: 30px; }
        .col-name { width: 80px; }
        .col-small { width: 45px; }
        .col-medium { width: 45px; }
        .col-large { width: 90px; }
    </style>
</head>
<body>
    <?php if ($report_type == 'barangay') { ?>
        <div class="paper">
            <div class="title">
                <h1>Child Health Records</h1>
                <div class="meta">Year: <?php echo $year; ?></div>
            </div>
            <div class="address-details">
                <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                <span><strong>Purok:</strong> All</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="col-narrow">No.</th>
                        <th colspan="4" class="grouped-header">Personal Information</th>
                        <th rowspan="2" class="col-large" style="width: 50px;">Home Based Record</th>
                        <th colspan="4" class="grouped-header">Immunization Status</th>
                        <th colspan="3" class="grouped-header">Health Records</th>
                        <th rowspan="2" class="col-large">Risk Observed</th>
                        <th rowspan="2" class="col-large">Service Source</th>
                    </tr>
                    <tr>
                        <th class="col-name">Full Name</th>
                        <th class="col-small">Gender</th>
                        <th class="col-small">Age</th>
                        <th class="col-medium">Birthdate</th>
                        <th class="col-large" style="width: 85px;">MMR (12-15 Months)</th>
                        <th class="col-large" style="width: 85px;">Vitamin A (12-59 Months)</th>
                        <th class="col-large" style="width: 85px;">Fully Immunized (FIC)</th>
                        <th class="col-large" style="width: 85px;">Completely Immunized (CIC)</th>
                        <th class="col-large">Measurement Date</th>
                        <th class="col-small">Weight (kg)</th>
                        <th class="col-small">Height (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    foreach ($data as $row) {
                        $birthdate = new DateTime($row['birthdate'], new DateTimeZone('America/Los_Angeles'));
                        $age = $current_date->diff($birthdate);
                        $age_str = $age->y . 'y ';

                        // Format birthdate as mm/dd/yyyy
                        $birthdate_formatted = $birthdate->format('m/d/Y');
                        
                        // Format measurement date as mm/dd/yyyy
                        $measurement_date_formatted = 'N/A';
                        if (!empty($row['measurement_date'])) {
                            try {
                                $measurement_date_obj = new DateTime($row['measurement_date'], new DateTimeZone('America/Los_Angeles'));
                                $measurement_date_formatted = $measurement_date_obj->format('m/d/Y');
                            } catch (Exception $e) {
                                $measurement_date_formatted = 'N/A';
                            }
                        }

                        $immunizations = explode(',', $row['immunization_status'] ?? '');
                        $mmr = in_array('MMR (12-15 Months)', $immunizations) ? '✓' : '';
                        $vitamin_a = in_array('Vitamin A (12-59 Months)', $immunizations) ? '✓' : '';
                        $fic = in_array('Fully Immunized (FIC)', $immunizations) ? '✓' : '';
                        $cic = in_array('Completely Immunized (CIC)', $immunizations) ? '✓' : '';
                        echo '<tr>
                            <td>' . $count++ . '</td>
                            <td style="text-align: left;">' . htmlspecialchars(substr($row['full_name'], 0, 25)) . '</td>
                            <td>' . htmlspecialchars($row['gender']) . '</td>
                            <td>' . $age_str . '</td>
                            <td>' . $birthdate_formatted . '</td>
                            <td></td>
                            <td>' . $mmr . '</td>
                            <td>' . $vitamin_a . '</td>
                            <td>' . $fic . '</td>
                            <td>' . $cic . '</td>
                            <td>' . $measurement_date_formatted . '</td>
                            <td>' . htmlspecialchars($row['weight'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($row['height'] ?? 'N/A') . '</td>
                            <td></td>
                            <td></td>
                        </tr>';
                    }
                    for ($i = count($data); $i < 50; $i++) {
                        echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php } elseif ($report_type == 'per_purok' && $purok) { ?>
        <div class="paper">
            <div class="title">
                <h1>Child Health Records</h1>
                <div class="meta">Year: <?php echo $year; ?></div>
            </div>
            <div class="address-details">
                <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                <span><strong>Purok:</strong> <?php echo htmlspecialchars($purok); ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="col-narrow">No.</th>
                        <th colspan="4" class="grouped-header">Personal Information</th>
                        <th rowspan="2" class="col-large">Home Based Record</th>
                        <th colspan="4" class="grouped-header">Immunization Status</th>
                        <th colspan="3" class="grouped-header">Health Records</th>
                        <th rowspan="2" class="col-large">Risk Observed</th>
                        <th rowspan="2" class="col-large">Service Source</th>
                    </tr>
                    <tr>
                        <th class="col-name">Full Name</th>
                        <th class="col-small">Gender</th>
                        <th class="col-small">Age</th>
                        <th class="col-medium">Birthdate</th>
                        <th class="col-large" style="width: 85px;">MMR (12-15 Months)</th>
                        <th class="col-large" style="width: 85px;">Vitamin A (12-59 Months)</th>
                        <th class="col-large" style="width: 85px;">Fully Immunized (FIC)</th>
                        <th class="col-large" style="width: 85px;">Completely Immunized (CIC)</th>
                        <th class="col-medium">Measurement Date</th>
                        <th class="col-small">Weight (kg)</th>
                        <th class="col-small">Height (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    foreach ($data as $row) {
                        $birthdate = new DateTime($row['birthdate'], new DateTimeZone('America/Los_Angeles'));
                        $age = $current_date->diff($birthdate);
                        $age_str = $age->y . 'y ';

                        // Format birthdate as mm/dd/yyyy
                        $birthdate_formatted = $birthdate->format('m/d/Y');
                        
                        // Format measurement date as mm/dd/yyyy
                        $measurement_date_formatted = 'N/A';
                        if (!empty($row['measurement_date'])) {
                            try {
                                $measurement_date_obj = new DateTime($row['measurement_date'], new DateTimeZone('America/Los_Angeles'));
                                $measurement_date_formatted = $measurement_date_obj->format('m/d/Y');
                            } catch (Exception $e) {
                                $measurement_date_formatted = 'N/A';
                            }
                        }

                        $immunizations = explode(',', $row['immunization_status'] ?? '');
                        $mmr = in_array('MMR (12-15 Months)', $immunizations) ? '✓' : '';
                        $vitamin_a = in_array('Vitamin A (12-59 Months)', $immunizations) ? '✓' : '';
                        $fic = in_array('Fully Immunized (FIC)', $immunizations) ? '✓' : '';
                        $cic = in_array('Completely Immunized (CIC)', $immunizations) ? '✓' : '';
                        echo '<tr>
                            <td>' . $count++ . '</td>
                            <td style="text-align: left;">' . htmlspecialchars(substr($row['full_name'], 0, 25)) . '</td>
                            <td>' . htmlspecialchars($row['gender']) . '</td>
                            <td>' . $age_str . '</td>
                            <td>' . $birthdate_formatted . '</td>
                            <td></td>
                            <td>' . $mmr . '</td>
                            <td>' . $vitamin_a . '</td>
                            <td>' . $fic . '</td>
                            <td>' . $cic . '</td>
                            <td>' . $measurement_date_formatted . '</td>
                            <td>' . htmlspecialchars($row['weight'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($row['height'] ?? 'N/A') . '</td>
                            <td></td>
                            <td></td>
                        </tr>';
                    }
                    for ($i = count($data); $i < 50; $i++) {
                        echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</body>
</html>
<?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Child_Health_Records_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

// Handle AJAX get record
if (isset($_GET['action']) && $_GET['action'] == 'get_record' && isset($_GET['records_id'])) {
    $records_id = $_GET['records_id'];
    $stmt = $pdo->prepare("SELECT chr.weight, chr.height, chr.measurement_date, chr.risk_observed, chr.immunization_status FROM child_record chr WHERE chr.records_id = ?");
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
    $json_dir = 'data/child_health_records/';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    $json_file = $json_dir . $current_year . '_child_health_record.json';
    
    if ($_POST['action'] == 'update') {
        $records_id = $_POST['records_id'];
        $weight = $_POST['weight'];
        $height = $_POST['height'];
        $measurement_date = $_POST['measurement_date'];
        $risks = implode(',', $_POST['risks'] ?? []);
        $immunization_status = implode(',', $_POST['immunization_status'] ?? []);
        $stmt = $pdo->prepare("UPDATE child_record SET weight = ?, height = ?, measurement_date = ?, risk_observed = ?, immunization_status = ? WHERE records_id = ?");
        $stmt->execute([$weight, $height, $measurement_date, $risks, $immunization_status, $records_id]);
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "UPDATED: child_health_record records_id:$records_id"]);
        
        refresh_json_file($pdo, $current_year, $json_file);
        
        header("Location: child_health_records.php");
        exit;
    } elseif ($_POST['action'] == 'delete') {
        $records_id = $_POST['records_id'];
        $stmt = $pdo->prepare("DELETE FROM child_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $stmt = $pdo->prepare("DELETE FROM records WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "DELETED: child_health_record records_id:$records_id"]);
        
        refresh_json_file($pdo, $current_year, $json_file);
        
        header("Location: child_health_records.php");
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

// Get available years from DB
$stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(created_at) as year FROM child_record ORDER BY year DESC");
$stmt_years->execute();
$years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// Get selected year
$current_year = date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? $_GET['year'] : $current_year;
$is_editable = ($selected_year == $current_year);

// JSON file management
$json_dir = 'data/child_health_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}
$json_file = $json_dir . $selected_year . '_child_health_record.json';

if (needs_json_refresh($pdo, $selected_year, $json_file)) {
    $records = refresh_json_file($pdo, $selected_year, $json_file);
} else {
    $records = json_decode(file_get_contents($json_file), true) ?: [];
}

// Filter by role
$filtered_records_for_display = [];

if ($role_id == 3) {
    foreach ($records as $record) {
        if ($record['person_id'] == $user_person_id) {
            $filtered_records_for_display[] = $record;
        }
    }
} elseif ($role_id == 2) {
    foreach ($records as $record) {
        if ($record['purok'] == $user_purok) {
            $filtered_records_for_display[] = $record;
        }
    }
} else {
    $filtered_records_for_display = $records;
}

$records = $filtered_records_for_display;

// Calculate age and stats
$current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
$filtered_records = [];
$total_children = 0;
$fully_immunized = 0;
$at_risk = 0;

foreach ($records as $record) {
    if ($record['birthdate']) {
        $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
        $age_interval = $current_date->diff($birthdate);
        $record['age'] = $age_interval->y . 'y ';
        $filtered_records[] = $record;
        $total_children++;
        
        // Check immunization
        if (strpos($record['immunization_status'] ?? '', 'Fully Immunized') !== false || 
            strpos($record['immunization_status'] ?? '', 'Completely Immunized') !== false) {
            $fully_immunized++;
        }
        
        // Check risk
        if (!empty($record['risk_observed']) && $record['risk_observed'] !== 'None') {
            $at_risk++;
        }
    }
}

$immunization_rate = $total_children > 0 ? round(($fully_immunized / $total_children) * 100, 1) : 0;
$at_risk_pct = $total_children > 0 ? round(($at_risk / $total_children) * 100, 1) : 0;

// Group by purok
$purok_household_records = [];
$all_puroks = [];

if ($role_id == 1 || $role_id == 4 || $role_id == 2) {
    foreach ($filtered_records as $record) {
        $purok = isset($record['purok']) && !empty($record['purok']) ? $record['purok'] : 'Unknown';
        $household_number = $record['household_number'] ?? 'Unknown';
        
        if ($role_id == 2 && $purok != $user_purok) {
            continue;
        }
        
        $purok_household_records[$purok][$household_number][] = $record;
        $all_puroks[$purok] = true;
    }
    
    if (empty($purok_household_records) && ($role_id == 1 || $role_id == 4)) {
        $purok_stmt = $pdo->prepare("SELECT DISTINCT purok FROM address WHERE purok IS NOT NULL AND purok != '' ORDER BY purok");
        $purok_stmt->execute();
        $available_puroks = $purok_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($available_puroks as $purok) {
            $purok_household_records[$purok] = [];
        }
    }
} else {
    $filtered_records = array_values($filtered_records);
}

if ($role_id == 2 && $user_purok) {
    $puroks = [$user_purok];
} else {
    $puroks = array_keys($purok_household_records);
    $puroks = array_unique(array_filter($puroks, function($purok) {
        return !empty($purok) && $purok !== null;
    }));
}
sort($puroks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Child Health Records</title>
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
        .alert-success-custom {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
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
        .household-spacer { margin-bottom: 20px; }
        .tab-content { padding: 0; padding-top:10px;}
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
            .card { margin-bottom: 15px; margin-left: 0; margin-right: 0}
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
                                <div class="stat-label">Total Children</div>
                                <div class="stat-value"><?php echo number_format($total_children); ?></div>
                                <small class="text-muted">Ages 1-7 years</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Immunization Rate</div>
                                <div class="stat-value <?php echo $immunization_rate > 80 ? 'text-success' : 'text-warning'; ?>"><?php echo $immunization_rate; ?>%</div>
                                <small class="text-muted"><?php echo $fully_immunized; ?> fully immunized</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">At Risk</div>
                                <div class="stat-value <?php echo $at_risk_pct > 10 ? 'text-danger' : 'text-success'; ?>"><?php echo $at_risk_pct; ?>%</div>
                                <small class="text-muted"><?php echo $at_risk; ?> children with risks</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Viewing Year</div>
                                <div class="stat-value"><?php echo $selected_year; ?></div>
                                <small class="text-muted"><?php echo $is_editable ? 'Editable' : 'Read-only'; ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_editable): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?php echo $selected_year; ?> are read-only. Switch to <?php echo $current_year; ?> to edit records.
                        </div>
                    <?php endif; ?>
                    <?php if ($at_risk_pct > 15): ?>
                        <div class="alert-warning-custom alert-custom">
                            <i class="fas fa-exclamation-triangle"></i> <strong>High Risk Alert:</strong> <?php echo $at_risk_pct; ?>% of children have observed health risks. Review required.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header"><i class="fas fa-child"></i> Child Health Records <?php echo $role_id == 2 ? "($user_purok)" : ''; ?></div>
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
                                    <?php foreach ($puroks as $purok) { 
                                        echo "<option value='" . htmlspecialchars($purok) . "'";
                                        if ($role_id == 2 && $purok != $user_purok) {
                                            echo " disabled";
                                        }
                                        echo ">" . htmlspecialchars($purok) . "</option>"; 
                                    } ?>
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
                                            <th>Weight (kg)</th>
                                            <th>Height (cm)</th>
                                            <th>Date Measured</th>
                                            <th>Risk Observed</th>
                                            <th>Immunization Status</th>
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
                                                <td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?></td>
                                                <td><?php 
                                                    $measurement_date = $record['measurement_date'] ?? 'N/A';
                                                    if (!empty($measurement_date) && $measurement_date !== 'N/A') {
                                                        $date_obj = DateTime::createFromFormat('Y-m-d', $measurement_date);
                                                        echo $date_obj ? htmlspecialchars($date_obj->format('m/d/Y')) : htmlspecialchars($bp_date);
                                                    } else {
                                                        echo htmlspecialchars($measurement_date);
                                                    }
                                                ?></td>
                                                <td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td>
                                                <td><?php echo htmlspecialchars($record['immunization_status'] ?? 'None'); ?></td>
                                                <?php if ($is_editable): ?>
                                                <td>
                                                    <button class="btn btn-sm btn-primary edit-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class='fas fa-edit'></i></button>
                                                    <button class="btn btn-sm btn-danger delete-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class='fas fa-trash'></i></button>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($purok_household_records)): ?>
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <?php $first = true; foreach ($purok_household_records as $purok => $households): ?>
                                    <?php 
                                    $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok); 
                                    $purok_count = 0;
                                    foreach ($households as $hh) $purok_count += count($hh);
                                    ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $first ? 'active' : ''; ?>" id="purok-tab-<?php echo $safe_purok; ?>" data-toggle="tab" href="#purok-<?php echo $safe_purok; ?>" role="tab">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($purok); ?> <span class="badge badge-secondary"><?php echo $purok_count; ?></span>
                                        </a>
                                    </li>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" id="purokTabsContent">
                                <?php $first = true; foreach ($purok_household_records as $purok => $households): ?>
                                    <?php $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok); ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="purok-<?php echo $safe_purok; ?>" role="tabpanel">
                                        <?php if (!empty($households)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>HH#</th>
                                                            <th>Full Name</th>
                                                            <th>Age</th>
                                                            <th>Gender</th>
                                                            <th>Weight (kg)</th>
                                                            <th>Height (cm)</th>
                                                            <th>Date Measured</th>
                                                            <th>Risk Observed</th>
                                                            <th>Immunization</th>
                                                            <?php if ($is_editable): ?>
                                                            <th>Actions</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        // Collect all records for this purok in a single array
                                                        $all_purok_records = [];
                                                        foreach ($households as $household_number => $members) {
                                                            foreach ($members as $record) {
                                                                $record['household_number'] = $household_number;
                                                                $all_purok_records[] = $record;
                                                            }
                                                        }
                                                        
                                                        // Sort by household number
                                                        usort($all_purok_records, fn($a, $b) => $a['household_number'] <=> $b['household_number']);
                                                        
                                                        // Display all records in one table
                                                        foreach ($all_purok_records as $record): 
                                                        ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($household_number); ?></td>
                                                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['age']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                                                <td><?php echo htmlspecialchars($record['weight'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['height'] ?? 'N/A'); ?></td>
                                                                <td><?php 
                                                                    $measurement_date = $record['measurement_date'] ?? 'N/A';
                                                                    if (!empty($measurement_date) && $measurement_date !== 'N/A') {
                                                                        $date_obj = DateTime::createFromFormat('Y-m-d', $measurement_date);
                                                                        echo $date_obj ? htmlspecialchars($date_obj->format('m/d/Y')) : htmlspecialchars($bp_date);
                                                                    } else {
                                                                        echo htmlspecialchars($measurement_date);
                                                                    }
                                                                ?></td>
                                                                <td><?php echo htmlspecialchars($record['risk_observed'] ?? 'None'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['immunization_status'] ?? 'None'); ?></td>
                                                                <?php if ($is_editable): ?>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary edit-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class='fas fa-edit'></i></button>
                                                                    <button class="btn btn-sm btn-danger delete-btn" data-records-id="<?php echo $record['records_id']; ?>"><i class='fas fa-trash'></i></button>
                                                                </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mt-3">
                                                No child health records found for Purok <?php echo htmlspecialchars($purok); ?>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No child health records found for the selected year.
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

        // Auto-capitalize first letter function
        function capitalizeFirstLetter(input) {
            let value = input.value;
            if (value.length > 0) {
                input.value = value.charAt(0).toUpperCase() + value.slice(1);
            }
        }

        $(document).ready(function() {
            // Show/hide Others text box on click
            $(document).on('change', '#risk_others', function() {
                if ($(this).is(':checked')) {
                    $('#others_risk_group').show();
                    $('#others_risk_text').prop('required', true).focus();
                } else {
                    $('#others_risk_group').hide();
                    $('#others_risk_text').prop('required', false).val('');
                }
            });

            // Also check on modal open, in case Edit is loading "Others" as already checked
            $('#editModal').on('shown.bs.modal', function() {
                if ($('#risk_others').is(':checked')) {
                    $('#others_risk_group').show();
                    $('#others_risk_text').prop('required', true);
                } else {
                    $('#others_risk_group').hide();
                    $('#others_risk_text').prop('required', false).val('');
                }
            });

            // Auto-capitalize first letter as you type
            $(document).on('input', '#others_risk_text', function() {
                let val = $(this).val();
                if (val && val.length > 0) {
                    $(this).val(val.charAt(0).toUpperCase() + val.slice(1));
                }
            });
        });

        // Handle edit button
        $(document).on('click', '.edit-btn', function() {
            const recordsId = $(this).data('records-id');
            $('#edit_records_id').val(recordsId);
            
            // Reset form
            $('#others_risk_group').hide();
            $('#others_risk_text').val('').prop('required', false);
            
            $.get('?action=get_record&records_id=' + recordsId, function(data) {
                const record = JSON.parse(data);
                $('#edit_weight').val(record.weight);
                $('#edit_height').val(record.height);
                $('#edit_measurement_date').val(record.measurement_date);
                
                // Handle checkboxes for risks
                $('input[name="risks[]"]').prop('checked', false);
                if (record.risk_observed) {
                    const risks = record.risk_observed.split(',');
                    let hasCustomRisk = false;
                    
                    risks.forEach(risk => {
                        const trimmedRisk = risk.trim();
                        // Check if it's one of the predefined risks
                        const predefinedRisk = $('input[name="risks[]"][value="' + trimmedRisk + '"]');
                        
                        if (predefinedRisk.length > 0 && trimmedRisk !== 'Others') {
                            predefinedRisk.prop('checked', true);
                        } else if (trimmedRisk !== 'Others' && trimmedRisk !== '' && trimmedRisk !== 'None') {
                            // It's a custom risk
                            hasCustomRisk = true;
                            $('#risk_others').prop('checked', true);
                            $('#others_risk_group').show();
                            $('#others_risk_text').val(trimmedRisk).prop('required', true);
                        } else if (trimmedRisk === 'Others') {
                            $('#risk_others').prop('checked', true);
                            $('#others_risk_group').show();
                            $('#others_risk_text').prop('required', true);
                        }
                    });
                }
                
                // Handle checkboxes for immunization
                $('input[name="immunization_status[]"]').prop('checked', false);
                if (record.immunization_status) {
                    const immunizations = record.immunization_status.split(',');
                    immunizations.forEach(imm => {
                        $('input[name="immunization_status[]"][value="' + imm.trim() + '"]').prop('checked', true);
                    });
                }
            });
            $('#editModal').modal('show');
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
                <h5 class="modal-title">Edit Child Health Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="records_id" id="edit_records_id">
                    <div class="form-group">
                        <label for="edit_weight">Weight (kg)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_weight" name="weight" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_height">Height (cm)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_height" name="height" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_measurement_date">Measurement Date</label>
                        <input type="date" class="form-control" id="edit_measurement_date" name="measurement_date" required>
                    </div>
                    <div class="form-group">
                        <label>Risks Observed</label>
                        <div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="risks[]" value="Tigdas" id="risk_tigdas">
                                <label class="form-check-label" for="risk_tigdas">Tigdas</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="risks[]" value="Pulmonia" id="risk_pulmonia">
                                <label class="form-check-label" for="risk_pulmonia">Pulmonia</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="risks[]" value="Pagtatae" id="risk_pagtatae">
                                <label class="form-check-label" for="risk_pagtatae">Pagtatae</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="risks[]" value="Others" id="risk_others">
                                <label class="form-check-label" for="risk_others">Others (Please specify)</label>
                            </div>
                            <div class="mt-2" id="others_risk_group" style="display: none;">
                                <input type="text" class="form-control" id="others_risk_text" name="risks[]" placeholder="Specify other risk..." style="text-transform: capitalize;">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Immunization Status</label>
                        <div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="immunization_status[]" value="MMR (12-15 Months)" id="imm_mmr">
                                <label class="form-check-label" for="imm_mmr">MMR (12-15 Months)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="immunization_status[]" value="Vitamin A (12-59 Months)" id="imm_vitamin">
                                <label class="form-check-label" for="imm_vitamin">Vitamin A (12-59 Months)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="immunization_status[]" value="Fully Immunized (FIC)" id="imm_fic">
                                <label class="form-check-label" for="imm_fic">Fully Immunized (FIC)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="immunization_status[]" value="Completely Immunized (CIC)" id="imm_cic">
                                <label class="form-check-label" for="imm_cic">Completely Immunized (CIC)</label>
                            </div>
                        </div>
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
                <h5 class="modal-title">Delete Child Health Record</h5>
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
