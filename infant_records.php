<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file for a specific year - UPDATED
function refresh_infant_json_file($pdo, $year, $json_file) {
    $query = "
        SELECT DISTINCT p.person_id, p.full_name, p.gender, p.birthdate, p.household_number, 
               cr.weight, cr.height, cr.measurement_date, 
               GROUP_CONCAT(DISTINCT i.immunization_type) AS immunization_type, 
               ir.breastfeeding_months, ir.solid_food_start, cr.service_source, a.purok, cr.records_id, cr.created_at
        FROM person p
        LEFT JOIN address a ON p.address_id = a.address_id
        LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'child_record.infant_record'
        LEFT JOIN child_record cr ON r.records_id = cr.records_id
        LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
        LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
        LEFT JOIN immunization i ON ci.immunization_id = i.immunization_id
        WHERE p.birthdate IS NOT NULL
        AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 0 AND 365
        AND (YEAR(cr.created_at) = ? OR cr.created_at IS NULL)
        GROUP BY p.person_id, cr.records_id 
        ORDER BY a.purok, p.household_number, p.full_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    foreach ($all_records as &$record) {
        if ($record['birthdate']) {
            $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
            $age_in_days = $current_date->diff($birthdate)->days;
            if ($age_in_days == 0) {
                $record['age'] = 'Newborn';
            } elseif ($age_in_days < 28) {
                $record['age'] = ceil($age_in_days / 7) . ' weeks';
            } else {
                $record['age'] = floor($age_in_days / 30) . ' months';
            }
        }
    }
    
    file_put_contents($json_file, json_encode($all_records));
    return $all_records;
}

// Function to check if JSON needs refresh
function needs_infant_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT MAX(created_at) as latest FROM child_record WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $db_latest = $stmt->fetchColumn();
    
    if (!$db_latest) return false;
    
    $file_time = filemtime($json_file);
    $db_time = strtotime($db_latest);
    
    return $db_time > $file_time;
}

// Handle downloads - UPDATED QUERIES
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
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.person_id, p.full_name, p.gender, p.birthdate, p.household_number, 
                   cr.weight, cr.height, cr.measurement_date, 
                   GROUP_CONCAT(DISTINCT i.immunization_type) AS immunization_type, 
                   ir.breastfeeding_months, ir.solid_food_start, cr.service_source, a.purok, r.records_id
            FROM person p
            LEFT JOIN address a ON p.address_id = a.address_id
            LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'child_record.infant_record'
            LEFT JOIN child_record cr ON r.records_id = cr.records_id
            LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
            LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
            LEFT JOIN immunization i ON ci.immunization_id = i.immunization_id
            WHERE p.birthdate IS NOT NULL AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 0 AND 365
            GROUP BY p.person_id, cr.records_id 
            ORDER BY a.purok, p.household_number, p.full_name
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($report_type == 'per_purok' && $purok && ($role_id_download == 1 || $role_id_download == 4 || ($role_id_download == 2 && $purok == $user_purok_download))) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.person_id, p.full_name, p.gender, p.birthdate, p.household_number, 
                   cr.weight, cr.height, cr.measurement_date, 
                   GROUP_CONCAT(DISTINCT i.immunization_type) AS immunization_type, 
                   ir.breastfeeding_months, ir.solid_food_start, cr.service_source, a.purok, r.records_id
            FROM person p
            LEFT JOIN address a ON p.address_id = a.address_id
            LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'child_record.infant_record'
            LEFT JOIN child_record cr ON r.records_id = cr.records_id
            LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
            LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
            LEFT JOIN immunization i ON ci.immunization_id = i.immunization_id
            WHERE p.birthdate IS NOT NULL AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 0 AND 365 AND a.purok = ?
            GROUP BY p.person_id, cr.records_id 
            ORDER BY p.household_number, p.full_name
        ");
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
    <title>Infant Records</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { padding: 12px; }
        .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
        .meta { text-align: center; font-size: 12px; color: #444; }
        .address-details { text-align: center; margin-bottom: 10px; }
        .address-details span { display: inline-block; margin: 0 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #000; padding: 2px; text-align: center; word-wrap: break-word; vertical-align: middle; }
        th { background: #f2f2f2; }
        .grouped-header { background: #e9e9e9; }
        .col-narrow { width: 25px; }
        .col-name { width: 70px; }
        .col-tiny { width: 25px; }
        .col-small { width: 30px; }
        .col-medium { width: 40px; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="title">
            <h1>Infant Records</h1>
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
                    <th rowspan="3" class="col-narrow">No.</th>
                    <th colspan="4" class="grouped-header">Personal Information</th>
                    <th colspan="3" class="grouped-header">Health Records</th>
                    <th colspan="7" class="grouped-header">Feeding Information</th>
                    <th colspan="14" class="grouped-header">Immunization Status</th>
                    <th rowspan="3" class="col-medium"  style="width: 60px;">Service Source</th>
                </tr>
                <tr>
                    <th rowspan="2" class="col-name">Full Name</th>
                    <th rowspan="2" class="col-small">Gender</th>
                    <th rowspan="2" class="col-small">Age</th>
                    <th rowspan="2" class="col-medium">Birthdate</th>
                    <th rowspan="2" class="col-small">Weight (kg)</th>
                    <th rowspan="2" class="col-small">Height (cm)</th>
                    <th rowspan="2" class="col-medium" style="width: 70px;">Measurement Date</th>
                    <th rowspan="2" class="col-tiny">BF (mo)</th>
                    <th colspan="6" class="grouped-header">Solid Food Start</th>
                    <th rowspan="2" class="col-tiny">BCG</th>
                    <th rowspan="2" class="col-tiny">HepB</th>
                    <th rowspan="2" class="col-tiny">DTP1</th>
                    <th rowspan="2" class="col-tiny">DTP2</th>
                    <th rowspan="2" class="col-tiny">DTP3</th>
                    <th rowspan="2" class="col-tiny">OPV1</th>
                    <th rowspan="2" class="col-tiny">OPV2</th>
                    <th rowspan="2" class="col-tiny">OPV3</th>
                    <th rowspan="2" class="col-tiny">IPV</th>
                    <th rowspan="2" class="col-tiny">PCV1</th>
                    <th rowspan="2" class="col-tiny">PCV2</th>
                    <th rowspan="2" class="col-tiny">PCV3</th>
                    <th rowspan="2" class="col-tiny">MCV1</th>
                    <th rowspan="2" class="col-tiny">MCV2</th>
                </tr>
                <tr>
                    <th class="col-tiny">1st</th>
                    <th class="col-tiny">2nd</th>
                    <th class="col-tiny">3rd</th>
                    <th class="col-tiny">4th</th>
                    <th class="col-tiny">5th</th>
                    <th class="col-tiny">6th</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                foreach ($data as $row) {
                    $birthdate = new DateTime($row['birthdate'], new DateTimeZone('America/Los_Angeles'));
                    $age = $current_date->diff($birthdate);
                    $age_str = ($age->days == 0) ? 'Newborn' : (($age->days < 28) ? ceil($age->days / 7) . 'w' : floor($age->days / 30) . 'm');
                    
                    // Format dates
                    $birthdate_formatted = $birthdate->format('m/d/Y');
                    $measurement_date_formatted = 'N/A';
                    if (!empty($row['measurement_date'])) {
                        $measurement_date_obj = new DateTime($row['measurement_date'], new DateTimeZone('America/Los_Angeles'));
                        $measurement_date_formatted = $measurement_date_obj->format('m/d/Y');
                    }
                    // Parse breastfeeding months (1-6)
                    $bf_months = '';
                    if (!empty($row['breastfeeding_months'])) {
                        $bf_text = strtolower(trim($row['breastfeeding_months']));
                        
                        // Map text to numbers
                        $month_mapping = [
                            'first' => 1,
                            'first month' => 1,
                            'second' => 2,
                            'second month' => 2,
                            'third' => 3,
                            'third month' => 3,
                            'fourth' => 4,
                            'fourth month' => 4,
                            'fifth' => 5,
                            'fifth month' => 5,
                            'sixth' => 6,
                            'sixth month' => 6,
                        ];
                        
                        // Check if it's text or number
                        if (is_numeric($bf_text)) {
                            $bf_months = intval($bf_text);
                        } else {
                            foreach ($month_mapping as $key => $value) {
                                if (strpos($bf_text, $key) !== false) {
                                    $bf_months = $value;
                                    break;
                                }
                            }
                        }
                    }            
                    // Parse solid food start
                    $solid_food_start = strtolower($row['solid_food_start'] ?? '');
                    $solid_1st = strpos($solid_food_start, 'first') !== false ? '✓' : '';
                    $solid_2nd = strpos($solid_food_start, 'second') !== false ? '✓' : '';
                    $solid_3rd = strpos($solid_food_start, 'third') !== false ? '✓' : '';
                    $solid_4th = strpos($solid_food_start, 'fourth') !== false ? '✓' : '';
                    $solid_5th = strpos($solid_food_start, 'fifth') !== false ? '✓' : '';
                    $solid_6th = strpos($solid_food_start, 'sixth') !== false ? '✓' : '';
                    
                    // Parse immunizations
                    $immunizations = explode(',', strtoupper($row['immunization_type'] ?? ''));
                    $immunizations = array_map('trim', $immunizations);
                    $bcg = in_array('BCG', $immunizations) ? '✓' : '';
                    $hepb = in_array('HEPB', $immunizations) || in_array('HEP B', $immunizations) ? '✓' : '';
                    $dtp1 = in_array('DTP1', $immunizations) ? '✓' : '';
                    $dtp2 = in_array('DTP2', $immunizations) ? '✓' : '';
                    $dtp3 = in_array('DTP3', $immunizations) ? '✓' : '';
                    $opv1 = in_array('OPV1', $immunizations) ? '✓' : '';
                    $opv2 = in_array('OPV2', $immunizations) ? '✓' : '';
                    $opv3 = in_array('OPV3', $immunizations) ? '✓' : '';
                    $ipv = in_array('IPV', $immunizations) ? '✓' : '';
                    $pcv1 = in_array('PCV1', $immunizations) ? '✓' : '';
                    $pcv2 = in_array('PCV2', $immunizations) ? '✓' : '';
                    $pcv3 = in_array('PCV3', $immunizations) ? '✓' : '';
                    $mcv1 = in_array('MCV1', $immunizations) ? '✓' : '';
                    $mcv2 = in_array('MCV2', $immunizations) ? '✓' : '';
                    
                    echo '<tr>
                        <td>' . $count++ . '</td>
                        <td style="text-align: left;">' . htmlspecialchars(substr($row['full_name'], 0, 25)) . '</td>
                        <td>' . htmlspecialchars($row['gender']) . '</td>
                        <td>' . $age_str . '</td>
                        <td>' . $birthdate_formatted . '</td>
                        <td>' . htmlspecialchars($row['weight'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['height'] ?? 'N/A') . '</td>
                        <td>' . $measurement_date_formatted . '</td>
                        <td>' . $bf_months . '</td>
                        <td>' . $solid_1st . '</td>
                        <td>' . $solid_2nd . '</td>
                        <td>' . $solid_3rd . '</td>
                        <td>' . $solid_4th . '</td>
                        <td>' . $solid_5th . '</td>
                        <td>' . $solid_6th . '</td>
                        <td>' . $bcg . '</td>
                        <td>' . $hepb . '</td>
                        <td>' . $dtp1 . '</td>
                        <td>' . $dtp2 . '</td>
                        <td>' . $dtp3 . '</td>
                        <td>' . $opv1 . '</td>
                        <td>' . $opv2 . '</td>
                        <td>' . $opv3 . '</td>
                        <td>' . $ipv . '</td>
                        <td>' . $pcv1 . '</td>
                        <td>' . $pcv2 . '</td>
                        <td>' . $pcv3 . '</td>
                        <td>' . $mcv1 . '</td>
                        <td>' . $mcv2 . '</td>
                        <td>' . htmlspecialchars(substr($row['service_source'] ?? 'N/A', 0, 15)) . '</td>
                    </tr>';
                }
                // Add empty rows to fill the page
                for ($i = count($data); $i < 50; $i++) {
                    echo '<tr><td>' . ($i + 1) . '</td>';
                    for ($j = 0; $j < 29; $j++) {
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
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Infant_Records_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

// Handle AJAX get record - UPDATED QUERY
if (isset($_GET['action']) && $_GET['action'] == 'get_record' && isset($_GET['records_id'])) {
    $records_id = $_GET['records_id'];
    $stmt = $pdo->prepare("
        SELECT cr.weight, cr.height, cr.measurement_date, cr.service_source, 
            GROUP_CONCAT(DISTINCT i.immunization_type) AS immunization_type, 
            ir.breastfeeding_months, ir.solid_food_start 
        FROM child_record cr
        LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
        LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
        LEFT JOIN immunization i ON ci.immunization_id = i.immunization_id
        WHERE cr.records_id = ?
        GROUP BY cr.records_id
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

// Handle POST actions - UPDATED FOR CHILD_IMMUNIZATION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $current_year = date('Y');
    $json_dir = 'data/infant_records/';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    $json_file = $json_dir . $current_year . '_infant_record.json';
    
    if ($_POST['action'] == 'update') {
        $records_id = $_POST['records_id'];
        $weight = $_POST['weight'];
        $height = $_POST['height'];
        $measurement_date = $_POST['measurement_date'];
        $service_source = $_POST['service_source'] ?? '';
        $immunization_status = $_POST['immunization_status'] ?? [];
        $breastfeeding_months = $_POST['breastfeeding_months'] ?? '';
        $solid_food_start = $_POST['solid_food_start'] ?? '';
        
        // Update child_record
        $stmt = $pdo->prepare("UPDATE child_record SET weight = ?, height = ?, measurement_date = ?, service_source = ? WHERE records_id = ?");
        $stmt->execute([$weight, $height, $measurement_date, $service_source, $records_id]);
        
        // Get child_record_id
        $stmt = $pdo->prepare("SELECT child_record_id FROM child_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $child_record_id = $stmt->fetchColumn();
        
        // Update immunizations using child_immunization table
        if ($child_record_id && !empty($immunization_status)) {
            // Clear existing immunizations
            $stmt = $pdo->prepare("DELETE FROM child_immunization WHERE child_record_id = ?");
            $stmt->execute([$child_record_id]);
            
            // Insert new immunizations
            foreach ($immunization_status as $vaccine) {
                // Get or create immunization_id
                $stmt = $pdo->prepare("SELECT immunization_id FROM immunization WHERE immunization_type = ?");
                $stmt->execute([$vaccine]);
                $immunization = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($immunization) {
                    $immunization_id = $immunization['immunization_id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO immunization (immunization_type) VALUES (?)");
                    $stmt->execute([$vaccine]);
                    $immunization_id = $pdo->lastInsertId();
                }
                
                // Insert into child_immunization
                $stmt = $pdo->prepare("INSERT INTO child_immunization (child_record_id, immunization_id) VALUES (?, ?)");
                $stmt->execute([$child_record_id, $immunization_id]);
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "UPDATED: infant_record records_id:$records_id"]);
        
        refresh_infant_json_file($pdo, $current_year, $json_file);
        
        header("Location: infant_records.php");
        exit;
    } elseif ($_POST['action'] == 'delete') {
        $records_id = $_POST['records_id'];
        
        // Get child_record_id first
        $stmt = $pdo->prepare("SELECT child_record_id FROM child_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        $child_record_id = $stmt->fetchColumn();
        
        if ($child_record_id) {
            // Delete from child_immunization first (due to foreign key constraints)
            $stmt = $pdo->prepare("DELETE FROM child_immunization WHERE child_record_id = ?");
            $stmt->execute([$child_record_id]);
            
            // Delete from infant_record
            $stmt = $pdo->prepare("DELETE FROM infant_record WHERE child_record_id = ?");
            $stmt->execute([$child_record_id]);
        }
        
        // Delete from child_record
        $stmt = $pdo->prepare("DELETE FROM child_record WHERE records_id = ?");
        $stmt->execute([$records_id]);
        
        // Delete from records
        $stmt = $pdo->prepare("DELETE FROM records WHERE records_id = ?");
        $stmt->execute([$records_id]);
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "DELETED: infant_record records_id:$records_id"]);
        
        refresh_infant_json_file($pdo, $current_year, $json_file);
        
        header("Location: infant_records.php");
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
$stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(cr.created_at) as year 
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    WHERE r.record_type = 'child_record.infant_record'
    ORDER BY year DESC");
$stmt_years->execute();
$years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);


// Get selected year
$current_year = date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? $_GET['year'] : $current_year;
$is_editable = ($selected_year == $current_year);

// JSON file management
$json_dir = 'data/infant_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}
$json_file = $json_dir . $selected_year . '_infant_record.json';

if (needs_infant_json_refresh($pdo, $selected_year, $json_file)) {
    $all_records = refresh_infant_json_file($pdo, $selected_year, $json_file);
    $records = $all_records;
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
$total_infants = 0;
$breastfeeding_count = 0;
$immunized_count = 0;
$newborn_count = 0;

foreach ($records as $record) {
    if ($record['birthdate']) {
        $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
        $age_interval = $current_date->diff($birthdate);
        $age_days = $age_interval->days;
        if ($age_days == 0) {
            $record['age'] = 'Newborn';
            $newborn_count++;
        } elseif ($age_days < 28) {
            $record['age'] = ceil($age_days / 7) . ' weeks';
        } else {
            $record['age'] = floor($age_days / 30) . ' months';
        }
        $filtered_records[] = $record;
        $total_infants++;
        
        if (!empty($record['breastfeeding_months']) && $record['breastfeeding_months'] > 0) {
            $breastfeeding_count++;
        }
        
        if (!empty($record['immunization_type']) && $record['immunization_type'] !== 'None') {
            $immunized_count++;
        }
    }
}

$breastfeeding_rate = $total_infants > 0 ? round(($breastfeeding_count / $total_infants) * 100, 1) : 0;
$immunization_rate = $total_infants > 0 ? round(($immunized_count / $total_infants) * 100, 1) : 0;

// Group by purok (single table, no household spacers)
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

if ($role_id == 2 && $user_purok) {
    $puroks = [$user_purok];
} else {
    $puroks = array_keys($purok_records);
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
    <title>BRGYCare - Infant Records (Enhanced)</title>
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
                                <div class="stat-label">Total Infants</div>
                                <div class="stat-value"><?php echo number_format($total_infants); ?></div>
                                <small class="text-muted">0-12 months</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Breastfeeding Rate</div>
                                <div class="stat-value <?php echo $breastfeeding_rate > 60 ? 'text-success' : 'text-warning'; ?>"><?php echo $breastfeeding_rate; ?>%</div>
                                <small class="text-muted"><?php echo $breastfeeding_count; ?> breastfeeding</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Immunization Rate</div>
                                <div class="stat-value <?php echo $immunization_rate > 80 ? 'text-success' : 'text-warning'; ?>"><?php echo $immunization_rate; ?>%</div>
                                <small class="text-muted"><?php echo $immunized_count; ?> immunized</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Newborns</div>
                                <div class="stat-value"><?php echo $newborn_count; ?></div>
                                <small class="text-muted">0-7 days old</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_editable): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?php echo $selected_year; ?> are read-only. Switch to <?php echo $current_year; ?> to edit records.
                        </div>
                    <?php endif; ?>
                    <?php if ($breastfeeding_rate < 50): ?>
                        <div class="alert-warning-custom alert-custom">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Low Breastfeeding Alert:</strong> Only <?php echo $breastfeeding_rate; ?>% breastfeeding rate. Promote breastfeeding awareness.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header"><i class="fas fa-baby"></i> Infant Records <?php echo $role_id == 2 ? "($user_purok)" : ''; ?></div>
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
                                            <th>Breastfeeding (mo)</th>
                                            <th>Solid Food Start</th>
                                            <th>Immunization</th>
                                            <th>Service Source</th>
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
                                                <td><?php echo htmlspecialchars($record['breastfeeding_months'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['solid_food_start'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['immunization_type'] ?? 'None'); ?></td>
                                                <td><?php echo htmlspecialchars($record['service_source'] ?? 'N/A'); ?></td>
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
                                    $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok);
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
                                    <?php $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok); ?>
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
                                                            <th>Weight</th>
                                                            <th>Height</th>
                                                            <th>Date</th>
                                                            <th>BF</th>
                                                            <th>Solid Food</th>
                                                            <th>Immunization</th>
                                                            <th>Source</th>
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
                                                                <td><?php echo htmlspecialchars($record['breastfeeding_months'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['solid_food_start'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['immunization_type'] ?? 'None'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['service_source'] ?? 'N/A'); ?></td>
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
                                                No infant records found for Purok <?php echo htmlspecialchars($purok); ?>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No infant records found for the selected year.
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

        $(document).on('click', '.edit-btn', function() {
            const recordsId = $(this).data('records-id');
            $('#edit_records_id').val(recordsId);
            $.get('?action=get_record&records_id=' + recordsId, function(data) {
                const record = JSON.parse(data);
                $('#edit_weight').val(record.weight);
                $('#edit_height').val(record.height);
                $('#edit_measurement_date').val(record.measurement_date);
                $('#edit_service_source').val(record.service_source);
                
                // Handle checkboxes for immunization - uncheck all first
                $('input[name="immunization_status[]"]').prop('checked', false);
                if (record.immunization_type) {
                    const immunizations = record.immunization_type.split(',');
                    immunizations.forEach(imm => {
                        const trimmedImm = imm.trim().toUpperCase();
                        // Match all possible variations
                        if (trimmedImm === 'BCG') $('#edit_bcg').prop('checked', true);
                        if (trimmedImm === 'HEPB' || trimmedImm === 'HEP B') $('#edit_hepb').prop('checked', true);
                        if (trimmedImm === 'DTP1') $('#edit_dtp1').prop('checked', true);
                        if (trimmedImm === 'DTP2') $('#edit_dtp2').prop('checked', true);
                        if (trimmedImm === 'DTP3') $('#edit_dtp3').prop('checked', true);
                        if (trimmedImm === 'OPV1') $('#edit_opv1').prop('checked', true);
                        if (trimmedImm === 'OPV2') $('#edit_opv2').prop('checked', true);
                        if (trimmedImm === 'OPV3') $('#edit_opv3').prop('checked', true);
                        if (trimmedImm === 'IPV') $('#edit_ipv').prop('checked', true);
                        if (trimmedImm === 'PCV1') $('#edit_pcv1').prop('checked', true);
                        if (trimmedImm === 'PCV2') $('#edit_pcv2').prop('checked', true);
                        if (trimmedImm === 'PCV3') $('#edit_pcv3').prop('checked', true);
                        if (trimmedImm === 'MCV1') $('#edit_mcv1').prop('checked', true);
                        if (trimmedImm === 'MCV2') $('#edit_mcv2').prop('checked', true);
                    });
                }
                
                $('#edit_breastfeeding_months').val(record.breastfeeding_months);
                $('#edit_solid_food_start').val(record.solid_food_start);
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
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Infant Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="records_id" id="edit_records_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_weight">Weight (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="edit_weight" name="weight" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_height">Height (cm)</label>
                                <input type="number" step="0.01" class="form-control" id="edit_height" name="height" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_measurement_date">Measurement Date</label>
                        <input type="date" class="form-control" id="edit_measurement_date" name="measurement_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_service_source">Service Source</label>
                        <select class="form-control" id="edit_service_source" name="service_source">
                            <option value="">Select Service Source</option>
                            <option value="Health Center">Health Center</option>
                            <option value="Barangay Health Station">Barangay Health Station</option>
                            <option value="Private Clinic">Private Clinic</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Immunization Status</label>
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="BCG" id="edit_bcg">
                                    <label class="form-check-label" for="edit_bcg">BCG</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="HepB" id="edit_hepb">
                                    <label class="form-check-label" for="edit_hepb">HepB</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="DTP1" id="edit_dtp1">
                                    <label class="form-check-label" for="edit_dtp1">DTP1</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="DTP2" id="edit_dtp2">
                                    <label class="form-check-label" for="edit_dtp2">DTP2</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="DTP3" id="edit_dtp3">
                                    <label class="form-check-label" for="edit_dtp3">DTP3</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="OPV1" id="edit_opv1">
                                    <label class="form-check-label" for="edit_opv1">OPV1</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="OPV2" id="edit_opv2">
                                    <label class="form-check-label" for="edit_opv2">OPV2</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="OPV3" id="edit_opv3">
                                    <label class="form-check-label" for="edit_opv3">OPV3</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="IPV" id="edit_ipv">
                                    <label class="form-check-label" for="edit_ipv">IPV</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="PCV1" id="edit_pcv1">
                                    <label class="form-check-label" for="edit_pcv1">PCV1</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="PCV2" id="edit_pcv2">
                                    <label class="form-check-label" for="edit_pcv2">PCV2</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="PCV3" id="edit_pcv3">
                                    <label class="form-check-label" for="edit_pcv3">PCV3</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="MCV1" id="edit_mcv1">
                                    <label class="form-check-label" for="edit_mcv1">MCV1</label>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="immunization_status[]" value="MCV2" id="edit_mcv2">
                                    <label class="form-check-label" for="edit_mcv2">MCV2</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_breastfeeding_months">Breastfeeding Months</label>
                        <select class="form-control" id="edit_breastfeeding_months" name="breastfeeding_months">
                            <option value="">Select Month</option>
                            <option value="First Month">First Month</option>
                            <option value="Second Month">Second Month</option>
                            <option value="Third Month">Third Month</option>
                            <option value="Fourth Month">Fourth Month</option>
                            <option value="Fifth Month">Fifth Month</option>
                            <option value="Sixth Month">Sixth Month</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_solid_food_start">Solid Food Start</label>
                        <input type="date" class="form-control" id="edit_solid_food_start" name="solid_food_start">
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
                <h5 class="modal-title">Delete Infant Record</h5>
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
