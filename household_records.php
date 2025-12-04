<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file
function refresh_json_file($pdo, $year, $json_file) {
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.age, p.gender, p.household_number, p.relationship_type, p.birthdate, p.civil_status, hr.water_source, hr.toilet_type, hr.visit_months, p.health_condition, a.purok, hr.created_at, hr.updated_at
        FROM household_record hr
        JOIN records r ON hr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE YEAR(hr.created_at) = ? OR (hr.updated_at IS NOT NULL AND YEAR(hr.updated_at) = ?)
        GROUP BY p.person_id
        ORDER BY a.purok, p.household_number, p.full_name
    ");
    $stmt->execute([$year, $year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($json_file, json_encode($all_records));
    return $all_records;
}

// Function to check if JSON needs refresh (based on latest record timestamp)
function needs_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    // Get the latest update time from database
    $stmt = $pdo->prepare("SELECT MAX(GREATEST(COALESCE(created_at, '1970-01-01'), COALESCE(updated_at, '1970-01-01'))) as latest FROM household_record WHERE YEAR(created_at) = ? OR (updated_at IS NOT NULL AND YEAR(updated_at) = ?)");
    $stmt->execute([$year, $year]);
    $db_latest = $stmt->fetchColumn();
    
    // Get the file modification time
    $file_time = filemtime($json_file);
    
    if (!$db_latest) return false;
    
    $db_time = strtotime($db_latest);
    return $db_time > $file_time;
}

// Fetch user role and person_id
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_person_id = null;
if ($role_id == 3) {
    $stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_person_id = $stmt->fetchColumn();
    if ($user_person_id === false) {
        die("Error: No person record found for user_id: " . $_SESSION['user_id']);
    }
}

$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

// Fetch user's household_number for role_id = 3
$user_household_number = null;
if ($role_id == 3 && $user_person_id) {
    $stmt = $pdo->prepare("SELECT household_number FROM person WHERE person_id = ?");
    $stmt->execute([$user_person_id]);
    $user_household_number = $stmt->fetchColumn();
    if ($user_household_number === false) {
        die("Error: No household number found for person_id: " . $_SESSION['user_id']);
    }
}

// Handle POST requests for update and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['download'])) {
    $person_id = $_POST['person_id'] ?? null;
    if (!$person_id) {
        $_SESSION['message'] = 'Invalid request.';
        header("Location: household_records.php");
        exit;
    }

    // Check permissions based on role
    $can_access = false;
    if ($role_id == 1 || $role_id == 4) {
        $can_access = true;
    } elseif ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $record_purok = $stmt->fetchColumn();
        $can_access = ($record_purok == $user_purok);
    } elseif ($role_id == 3 && $user_household_number) {
        $stmt = $pdo->prepare("SELECT household_number FROM person WHERE person_id = ?");
        $stmt->execute([$person_id]);
        $record_household = $stmt->fetchColumn();
        $can_access = ($record_household == $user_household_number);
    }

    if (!$can_access) {
        $_SESSION['message'] = 'You do not have permission to perform this action.';
        header("Location: household_records.php");
        exit;
    }

    // Get records_id
    $stmt = $pdo->prepare("SELECT records_id FROM records WHERE person_id = ?");
    $stmt->execute([$person_id]);
    $records_id = $stmt->fetchColumn();
    if (!$records_id) {
        $_SESSION['message'] = 'Record not found.';
        header("Location: household_records.php");
        exit;
    }

    $json_dir = 'data/household_records/';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }

    if ($_POST['action'] === 'update') {
        $water_source = $_POST['water_source'] ?? '';
        $toilet_type = $_POST['toilet_type'] ?? '';
        $visit_months = $_POST['visit_months'] ?? [];
        $visit_months_str = is_array($visit_months) ? implode(',', $visit_months) : trim($visit_months);
        $health_condition = $_POST['health_condition'] ?? '';

        if (empty($water_source) || empty($toilet_type) || empty($health_condition)) {
            $_SESSION['message'] = 'All fields are required.';
            header("Location: household_records.php");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE household_record SET water_source = ?, toilet_type = ?, visit_months = ?, updated_at = NOW() WHERE records_id = ?");
        $stmt_person = $pdo->prepare("UPDATE person SET health_condition = ? WHERE person_id = ?");
        if ($stmt->execute([$water_source, $toilet_type, $visit_months_str, $records_id]) && $stmt_person->execute([$health_condition, $person_id])) {
            $_SESSION['message'] = 'Record updated successfully.';
        } else {
            $_SESSION['message'] = 'Failed to update record.';
        }

        // Force JSON refresh for current year since updated_at = NOW()
        $current_year = date('Y');
        $current_json = $json_dir . 'household_records_' . $current_year . '.json';
        refresh_json_file($pdo, $current_year, $current_json);
    } elseif ($_POST['action'] === 'delete') {
        // Get year before delete
        $year_stmt = $pdo->prepare("SELECT COALESCE(YEAR(updated_at), YEAR(created_at)) as y FROM household_record WHERE records_id = ?");
        $year_stmt->execute([$records_id]);
        $rec_year = $year_stmt->fetchColumn() ?: date('Y');

        $stmt = $pdo->prepare("DELETE FROM household_record WHERE records_id = ?");
        if ($stmt->execute([$records_id])) {
            $_SESSION['message'] = 'Record deleted successfully.';
        } else {
            $_SESSION['message'] = 'Failed to delete record.';
        }

        // Force JSON refresh for the record's year
        $rec_json = $json_dir . 'household_records_' . $rec_year . '.json';
        refresh_json_file($pdo, $rec_year, $rec_json);
    }

    header("Location: household_records.php");
    exit;
}

// Directory for JSON files
$json_dir = 'data/household_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}

// Get current year
$current_year = date('Y');

// Query years from DB
$stmt_years = $pdo->prepare("
    SELECT DISTINCT YEAR(created_at) as year FROM household_record
    UNION
    SELECT DISTINCT YEAR(updated_at) as year FROM household_record WHERE updated_at IS NOT NULL
    ORDER BY year DESC
");
$stmt_years->execute();
$years_result = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
$years = array_unique($years_result);

// Get selected year
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? $_GET['year'] : $current_year;

// Determine if selected year is editable (only current year)
$is_editable = ($selected_year == $current_year);

$json_file = $json_dir . 'household_records_' . $selected_year . '.json';

// Always fetch records from database if refresh is needed
if (needs_json_refresh($pdo, $selected_year, $json_file)) {
    $records = refresh_json_file($pdo, $selected_year, $json_file);
} else {
    // Load from JSON file
    $records = json_decode(file_get_contents($json_file), true) ?: [];
}

// Filter records based on role
if ($role_id == 3 && $user_household_number) {
    $records = array_filter($records, function($record) use ($user_household_number) {
        return $record['household_number'] == $user_household_number;
    });
} elseif ($role_id == 2 && $user_purok) {
    $records = array_filter($records, function($record) use ($user_purok) {
        return $record['purok'] == $user_purok;
    });
}

// Calculate dashboard statistics
$total_households = count(array_unique(array_column($records, 'household_number')));
$total_members = count($records);
$level3_count = count(array_filter($records, fn($r) => strpos($r['water_source'], 'Level 3') !== false || strpos($r['water_source'], 'Nawasa') !== false));
$no_toilet_count = count(array_filter($records, fn($r) => strpos($r['toilet_type'], 'Wala') !== false || strpos($r['toilet_type'], 'w/o') !== false));
$water_coverage = $total_households > 0 ? round(($level3_count / $total_households) * 100, 1) : 0;
$no_toilet_pct = $total_households > 0 ? round(($no_toilet_count / $total_households) * 100, 1) : 0;

// Fetch barangay name
$stmt = $pdo->prepare("SELECT barangay, municipality, province FROM address LIMIT 1");
$stmt->execute();
$address = $stmt->fetch(PDO::FETCH_ASSOC);

// Group records by purok and household for BHW Head/Super Admin
$purok_household_records = [];
if ($role_id == 1 || $role_id == 4) {
    foreach ($records as $record) {
        $purok = $record['purok'];
        $household_number = $record['household_number'];
        $purok_household_records[$purok][$household_number][] = $record;
    }
}

// Handle PDF download
if (isset($_POST['download'])) {
    $report_type = $_POST['report_type'] ?? 'whole_barangay';
    $selected_purok_pdf = $_POST['purok'] ?? null;
    $download_records = $records;
    if ($report_type == 'per_purok' && $selected_purok_pdf) {
        $download_records = array_filter($download_records, function($record) use ($selected_purok_pdf) {
            return $record['purok'] == $selected_purok_pdf;
        });
    }

    require_once 'vendor/autoload.php';
    ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Household Records Report</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; background: #fff; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { width: 100%; padding: 12px; box-sizing: border-box; }
        .header { text-align: left; margin-bottom: 8px; }
        .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
        .meta { text-align: center; font-size: 12px; color: #444; }
        .address-details { text-align: center; margin-bottom: 10px; }
        .address-details span { display: inline-block; margin: 0 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 1px 2px; text-align: center; word-wrap: break-word;}
        th { background: #f2f2f2; }
        .grouped-header { background: #e9e9e9; }
        .col-narrow { width: 30px; }
        .col-name { width: 70px; }
        .col-small { width: 30px; }
        .col-medium { width: 60px; }
        tbody td { height: 16px; }
    </style>
</head>
<body>
    <?php if ($report_type === 'whole_barangay') { ?>
        <div class="paper">
            <div class="header">
                <div class="title">
                    <h1>Household Records Report</h1>
                    <div class="meta">Year: <?php echo $selected_year; ?></div>
                </div>
                <div class="address-details">
                    <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                    <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                    <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                    <span><strong>Purok:</strong> All</span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="col-narrow">No.</th>
                        <th colspan="8" class="grouped-header">Personal Information</th>
                        <th rowspan="2" class="col-medium">Water Source</th>
                        <th rowspan="2" class="col-medium">Toilet Type</th>
                        <th colspan="12" class="grouped-header">Visit Months</th>
                    </tr>
                    <tr>
                        <th class="col-name">Household Member</th>
                        <th class="col-small">HH#</th>
                        <th class="col-small">Relationship</th>
                        <th class="col-small">Sex</th>
                        <th class="col-small">Age</th>
                        <th class="col-medium">Birthdate</th>
                        <th class="col-medium">Civil Status</th>
                        <th class="col-medium">Address</th>
                        <th class="col-small">Jan</th>
                        <th class="col-small">Feb</th>
                        <th class="col-small">Mar</th>
                        <th class="col-small">Apr</th>
                        <th class="col-small">May</th>
                        <th class="col-small">Jun</th>
                        <th class="col-small">Jul</th>
                        <th class="col-small">Aug</th>
                        <th class="col-small">Sept</th>
                        <th class="col-small">Oct</th>
                        <th class="col-small">Nov</th>
                        <th class="col-small">Dec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    foreach ($download_records as $record) {
                        $visit_months = explode(',', $record['visit_months'] ?? '');
                        $health_condition = $record['health_condition'] ?? 'NRP';
                        if (preg_match('/^([^\(]+)/', $health_condition, $matches)) {
                            $health_condition = trim($matches[1]);
                        }
                        $water_source = $record['water_source'] ?? 'N/A';
                        if (preg_match('/^(WRS|Level 1|Level 2|Level 3)/i', $water_source, $matches)) {
                            $water_source = $matches[1];
                        }
                        $jan = in_array('Jan', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $feb = in_array('Feb', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $mar = in_array('Mar', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $apr = in_array('Apr', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $may = in_array('May', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $jun = in_array('Jun', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $jul = in_array('Jul', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $aug = in_array('Aug', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $sept = in_array('Sept', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $oct = in_array('Oct', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $nov = in_array('Nov', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $dec = in_array('Dec', $visit_months) ? htmlspecialchars($health_condition) : '';
                        echo '<tr>
                            <td>' . $count++ . '</td>
                            <td style="text-align:left;">' . htmlspecialchars(substr($record['full_name'], 0, 25)) . '</td>
                            <td>' . htmlspecialchars($record['household_number'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['relationship_type'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['gender'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['age'] ?? 'N/A') . '</td>
                            <td>' . (!empty($record['birthdate']) && $record['birthdate'] != 'N/A'
                                    ? date('m/d/Y', strtotime($record['birthdate']))
                                    : 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['civil_status'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['purok'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($water_source) . '</td>
                            <td>' . htmlspecialchars($record['toilet_type'] ?? 'N/A') . '</td>
                            <td>' . $jan . '</td><td>' . $feb . '</td><td>' . $mar . '</td><td>' . $apr . '</td>
                            <td>' . $may . '</td><td>' . $jun . '</td><td>' . $jul . '</td><td>' . $aug . '</td>
                            <td>' . $sept . '</td><td>' . $oct . '</td><td>' . $nov . '</td><td>' . $dec . '</td>
                        </tr>';
                    }
                    for ($i = count($download_records); $i < 50; $i++) {
                        echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php } elseif ($report_type === 'per_purok' && $selected_purok_pdf) { ?>
        <div class="paper">
            <div class="header">
                <div class="title">
                    <h1>Household Records Report</h1>
                    <div class="meta">Year: <?php echo $selected_year; ?></div>
                </div>
                <div class="address-details">
                    <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                    <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                    <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                    <span><strong>Purok:</strong> <?php echo htmlspecialchars($selected_purok_pdf); ?></span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="col-narrow">No.</th>
                        <th colspan="8" class="grouped-header">Personal Information</th>
                        <th rowspan="2" class="col-medium">Water Source</th>
                        <th rowspan="2" class="col-medium">Toilet Type</th>
                        <th colspan="12" class="grouped-header">Visit Months</th>
                    </tr>
                    <tr>
                        <th class="col-name">Household Member</th>
                        <th class="col-small">HH#</th>
                        <th class="col-small">Relationship</th>
                        <th class="col-small">Sex</th>
                        <th class="col-small">Age</th>
                        <th class="col-medium">Birthdate</th>
                        <th class="col-medium">Civil Status</th>
                        <th class="col-medium">Address</th>
                        <th class="col-small">Jan</th>
                        <th class="col-small">Feb</th>
                        <th class="col-small">Mar</th>
                        <th class="col-small">Apr</th>
                        <th class="col-small">May</th>
                        <th class="col-small">Jun</th>
                        <th class="col-small">Jul</th>
                        <th class="col-small">Aug</th>
                        <th class="col-small">Sept</th>
                        <th class="col-small">Oct</th>
                        <th class="col-small">Nov</th>
                        <th class="col-small">Dec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    foreach ($download_records as $record) {
                        $visit_months = explode(',', $record['visit_months'] ?? '');
                        $health_condition = $record['health_condition'] ?? 'NRP';
                        if (preg_match('/^([^\(]+)/', $health_condition, $matches)) {
                            $health_condition = trim($matches[1]);
                        }
                        $water_source = $record['water_source'] ?? 'N/A';
                        if (preg_match('/^(WRS|Level 1|Level 2|Level 3)/i', $water_source, $matches)) {
                            $water_source = $matches[1];
                        }
                        $jan = in_array('Jan', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $feb = in_array('Feb', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $mar = in_array('Mar', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $apr = in_array('Apr', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $may = in_array('May', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $jun = in_array('Jun', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $jul = in_array('Jul', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $aug = in_array('Aug', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $sept = in_array('Sept', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $oct = in_array('Oct', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $nov = in_array('Nov', $visit_months) ? htmlspecialchars($health_condition) : '';
                        $dec = in_array('Dec', $visit_months) ? htmlspecialchars($health_condition) : '';
                        echo '<tr>
                            <td>' . $count++ . '</td>
                            <td style="text-align:left;">' . htmlspecialchars(substr($record['full_name'], 0, 25)) . '</td>
                            <td>' . htmlspecialchars($record['household_number'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['relationship_type'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['gender'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['age'] ?? 'N/A') . '</td>
                            <td>' . (!empty($record['birthdate']) && $record['birthdate'] != 'N/A'
                                    ? date('m/d/Y', strtotime($record['birthdate']))
                                    : 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['civil_status'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($record['purok'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($water_source) . '</td>
                            <td>' . htmlspecialchars($record['toilet_type'] ?? 'N/A') . '</td>
                            <td>' . $jan . '</td><td>' . $feb . '</td><td>' . $mar . '</td><td>' . $apr . '</td>
                            <td>' . $may . '</td><td>' . $jun . '</td><td>' . $jul . '</td><td>' . $aug . '</td>
                            <td>' . $sept . '</td><td>' . $oct . '</td><td>' . $nov . '</td><td>' . $dec . '</td>
                        </tr>';
                    }
                    for ($i = count($download_records); $i < 50; $i++) {
                        echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
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
    $html2pdf->output('Household_Records_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

// Apply additional filters
$filtered_records = $records;
if (isset($_GET['filter_age']) && $_GET['filter_age'] != 'All') {
    $filtered_records = array_filter($filtered_records, function($record) {
        $age = $record['age'];
        switch ($_GET['filter_age']) {
            case 'Infant': return $age >= 0.00 && $age < 1;
            case 'Early Childhood': return $age >= 1 && $age < 6;
            case 'Middle Childhood': return $age >= 6 && $age < 13;
            case 'Teen': return $age >= 13 && $age < 20;
            case 'Adult': return $age >= 20 && $age < 60;
            case 'Elderly': return $age >= 60;
            default: return true;
        }
    });
}
if (isset($_GET['filter_sex']) && $_GET['filter_sex'] != 'All') {
    $filtered_records = array_filter($filtered_records, function($record) {
        return $record['gender'] == $_GET['filter_sex'];
    });
}
if (isset($_GET['filter_month']) && $_GET['filter_month'] != 'All') {
    $filtered_records = array_filter($filtered_records, function($record) {
        return strpos($record['visit_months'], $_GET['filter_month']) !== false;
    });
}
if (isset($_GET['filter_health']) && $_GET['filter_health'] != 'All') {
    $filtered_records = array_filter($filtered_records, function($record) {
        return $record['health_condition'] == $_GET['filter_health'];
    });
}
if (!empty($search = isset($_GET['search']) ? trim($_GET['search']) : '')) {
    $filtered_records = array_filter($filtered_records, function($record) use ($search) {
        return stripos($record['full_name'], $search) !== false;
    });
}
$filtered_records = array_values($filtered_records);
$filtered_records = array_unique($filtered_records, SORT_REGULAR);

$puroks = array_unique(array_column($filtered_records, 'purok'));
sort($puroks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Household Records (Enhanced)</title>
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
        .btn-secondary {
            background: #718096;
            border: none;
            padding: 8px 15px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-secondary:hover {
            background: #4a5568;
            transform: translateY(-1px);
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
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
        .tab-content { padding: 15px; }
        .readonly { pointer-events: none; background-color: #e9ecef; }
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
            .card { margin-bottom: 15px; margin-left: 0; margin-right: 0;}
            .table-responsive { overflow-x: auto; }
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
                                <div class="stat-label">Total Households</div>
                                <div class="stat-value"><?php echo number_format($total_households); ?></div>
                                <small class="text-muted"><?php echo number_format($total_members); ?> members</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Level III Water</div>
                                <div class="stat-value <?php echo $water_coverage > 70 ? 'text-success' : 'text-warning'; ?>"><?php echo $water_coverage; ?>%</div>
                                <small class="text-muted"><?php echo $level3_count; ?> households</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Without Toilet</div>
                                <div class="stat-value <?php echo $no_toilet_pct > 10 ? 'text-danger' : 'text-success'; ?>"><?php echo $no_toilet_pct; ?>%</div>
                                <small class="text-muted"><?php echo $no_toilet_count; ?> households</small>
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
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-home"></i> Household Records <?php echo $role_id == 3 ? '(My Household)' : ($role_id == 2 ? "($user_purok)" : ''); ?>
                    </div>
                    <?php if (isset($_SESSION['message'])) { echo '<div class="alert alert-info m-3">' . htmlspecialchars($_SESSION['message']) . '</div>'; unset($_SESSION['message']); } ?>
                    <div class="card-body">
                        <?php if ($role_id == 1 || $role_id == 4): ?>
                            <!-- Year Tabs -->
                            <ul class="nav nav-tabs mb-3" id="yearTabs" role="tablist">
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
                            <?php if ($role_id == 1 || $role_id == 4): ?>
                                <div class="form-group d-inline-block mr-2">
                                    <label for="report_type" class="mr-2">Report Type:</label>
                                    <select name="report_type" id="report_type" class="form-control d-inline-block w-auto">
                                        <option value="whole_barangay">Whole Barangay</option>
                                        <option value="per_purok">Per Purok</option>
                                    </select>
                                </div>
                                <div class="form-group d-inline-block mr-2" id="purok_group" style="display:none;">
                                    <label for="purok" class="mr-2">Purok:</label>
                                    <select name="purok" id="purok" class="form-control d-inline-block w-auto">
                                        <?php foreach ($puroks as $purok) { echo "<option value='" . htmlspecialchars($purok) . "'>" . htmlspecialchars($purok) . "</option>"; } ?>
                                    </select>
                                </div>
                            <?php elseif ($role_id == 2): ?>
                                <input type="hidden" name="report_type" value="per_purok">
                                <input type="hidden" name="purok" value="<?php echo htmlspecialchars($user_purok); ?>">
                            <?php else: ?>
                                <input type="hidden" name="report_type" value="whole_barangay">
                            <?php endif; ?>
                            <button type="submit" name="download" class="btn btn-success"><i class="fas fa-file-pdf"></i> Download PDF</button>
                        </form>
                        
                        <div class="filter-section">
                            <form method="get" class="form-inline">
                                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                                <div class="form-group mr-2">
                                    <label for="filter_age" class="mr-2">Age:</label>
                                    <select name="filter_age" id="filter_age" class="form-control">
                                        <option value="All">All</option>
                                        <option value="Infant" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Infant' ? 'selected' : ''; ?>>Infant</option>
                                        <option value="Early Childhood" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Early Childhood' ? 'selected' : ''; ?>>Early Childhood</option>
                                        <option value="Middle Childhood" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Middle Childhood' ? 'selected' : ''; ?>>Middle Childhood</option>
                                        <option value="Teen" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Teen' ? 'selected' : ''; ?>>Teen</option>
                                        <option value="Adult" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Adult' ? 'selected' : ''; ?>>Adult</option>
                                        <option value="Elderly" <?php echo isset($_GET['filter_age']) && $_GET['filter_age'] == 'Elderly' ? 'selected' : ''; ?>>Elderly</option>
                                    </select>
                                </div>
                                <div class="form-group mr-2">
                                    <label for="filter_sex" class="mr-2">Sex:</label>
                                    <select name="filter_sex" id="filter_sex" class="form-control">
                                        <option value="All">All</option>
                                        <option value="M" <?php echo isset($_GET['filter_sex']) && $_GET['filter_sex'] == 'M' ? 'selected' : ''; ?>>M</option>
                                        <option value="F" <?php echo isset($_GET['filter_sex']) && $_GET['filter_sex'] == 'F' ? 'selected' : ''; ?>>F</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Apply</button>
                                <a href="?year=<?php echo $selected_year; ?>" class="btn btn-secondary ml-2">Clear</a>
                            </form>
                        </div>
                        
                        <?php if ($role_id == 1 || $role_id == 4): ?>
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="whole-barangay-tab" data-toggle="tab" href="#whole-barangay" role="tab"><i class="fas fa-globe"></i> Whole Barangay</a>
                                </li>
                                <?php
                                $puroks_list = array_keys($purok_household_records);
                                foreach ($puroks_list as $purok) {
                                    $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok);
                                    $count = 0;
                                    if (isset($purok_household_records[$purok])) {
                                        foreach ($purok_household_records[$purok] as $hh) $count += count($hh);
                                    }
                                    echo "<li class='nav-item'>";
                                    echo "<a class='nav-link' id='tab-$safe_purok' data-toggle='tab' href='#purok-$safe_purok' role='tab'>";
                                    echo "<i class='fas fa-map-marker-alt'></i> " . htmlspecialchars($purok) . " <span class='badge badge-secondary'>$count</span>";
                                    echo "</a>";
                                    echo "</li>";
                                }
                                ?>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="whole-barangay" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Purok</th>
                                                    <th>HH#</th>
                                                    <th>Full Name</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <th>Relationship</th>
                                                    <th>Water Source</th>
                                                    <th>Toilet Type</th>
                                                    <th>Visit Months</th>
                                                    <th>Health Condition</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $all_records = [];
                                                foreach ($purok_household_records as $purok => $households) {
                                                    foreach ($households as $household_number => $members) {
                                                        foreach ($members as $record) {
                                                            if (in_array($record, $filtered_records)) {
                                                                $all_records[] = $record;
                                                            }
                                                        }
                                                    }
                                                }
                                                $all_records = array_unique($all_records, SORT_REGULAR);
                                                usort($all_records, function($a, $b) {
                                                    if ($a['purok'] != $b['purok']) return $a['purok'] <=> $b['purok'];
                                                    return $a['household_number'] <=> $b['household_number'];
                                                });
                                                foreach ($all_records as $record) {
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($record['purok']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['household_number']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['full_name']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['age']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['gender']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['relationship_type']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['water_source']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['toilet_type']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['visit_months']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($record['health_condition']) . "</td>";
                                                    echo "</tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php foreach ($purok_household_records as $purok => $households): ?>
                                    <?php $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok); ?>
                                    <div class="tab-pane fade" id="purok-<?php echo $safe_purok; ?>" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>HH#</th>
                                                        <th>Full Name</th>
                                                        <th>Age</th>
                                                        <th>Gender</th>
                                                        <th>Relationship</th>
                                                        <th>Water</th>
                                                        <th>Toilet</th>
                                                        <th>Visits</th>
                                                        <th>Health</th>
                                                        <?php if ($is_editable): ?>
                                                        <th>Actions</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $purok_records = [];
                                                    foreach ($households as $household_number => $members) {
                                                        foreach ($members as $record) {
                                                            if (in_array($record, $filtered_records)) {
                                                                $purok_records[] = $record;
                                                            }
                                                        }
                                                    }
                                                    $purok_records = array_unique($purok_records, SORT_REGULAR);
                                                    usort($purok_records, fn($a, $b) => $a['household_number'] <=> $b['household_number']);
                                                    foreach ($purok_records as $record) {
                                                        echo "<tr>";
                                                        echo "<td>" . htmlspecialchars($record['household_number']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['full_name']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['age']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['gender']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['relationship_type']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['water_source']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['toilet_type']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['visit_months']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($record['health_condition']) . "</td>";
                                                        if ($is_editable) {
                                                            echo "<td><button class=\"btn btn-sm btn-warning edit-btn\" data-person-id=\"" . $record['person_id'] . "\" data-water=\"" . htmlspecialchars($record['water_source']) . "\" data-toilet=\"" . htmlspecialchars($record['toilet_type']) . "\" data-visit=\"" . htmlspecialchars($record['visit_months']) . "\" data-health=\"" . htmlspecialchars($record['health_condition']) . "\"><i class='fas fa-edit'></i></button> <button class=\"btn btn-sm btn-danger delete-btn\" data-person-id=\"" . $record['person_id'] . "\"><i class='fas fa-trash'></i></button></td>";
                                                        }
                                                        echo "</tr>";
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <?php if ($role_id == 2): ?><th>HH#</th><?php endif; ?>
                                            <th>Full Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <th>Relationship</th>
                                            <th>Water Source</th>
                                            <th>Toilet Type</th>
                                            <th>Visit Months</th>
                                            <th>Health Condition</th>
                                            <?php if ($is_editable): ?>
                                            <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filtered_records as $record): ?>
                                            <tr>
                                                <?php if ($role_id == 2): ?><td><?php echo htmlspecialchars($record['household_number']); ?></td><?php endif; ?>
                                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['age']); ?></td>
                                                <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($record['relationship_type']); ?></td>
                                                <td><?php echo htmlspecialchars($record['water_source']); ?></td>
                                                <td><?php echo htmlspecialchars($record['toilet_type']); ?></td>
                                                <td><?php echo htmlspecialchars($record['visit_months']); ?></td>
                                                <td><?php echo htmlspecialchars($record['health_condition']); ?></td>
                                                <?php if ($is_editable): ?>
                                                <td><button class="btn btn-sm btn-warning edit-btn" data-person-id="<?php echo $record['person_id']; ?>" data-water="<?php echo htmlspecialchars($record['water_source']); ?>" data-toilet="<?php echo htmlspecialchars($record['toilet_type']); ?>" data-visit="<?php echo htmlspecialchars($record['visit_months']); ?>" data-health="<?php echo htmlspecialchars($record['health_condition']); ?>"><i class='fas fa-edit'></i></button> <button class="btn btn-sm btn-danger delete-btn" data-person-id="<?php echo $record['person_id']; ?>"><i class='fas fa-trash'></i></button></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
            var personId = $(this).data('person-id');
            var water = $(this).data('water');
            var toilet = $(this).data('toilet');
            var visit = $(this).data('visit');
            var health = $(this).data('health');
            $('#edit_person_id').val(personId);
            $('#edit_water_source').val(water);
            $('#edit_toilet_type').val(toilet);
            $('#edit_visit_months').val(visit);
            $('#edit_health_condition').val(health);
            if (visit) {
                var months = visit.split(',');
                months.forEach(function(m) {
                    m = m.trim();
                    $(".edit-month-checkbox[value='" + m + "']").prop('checked', true);
                });
            }
            $('#editModal').modal('show');
        });
        $(document).on('click', '.delete-btn', function() {
            if (confirm('Are you sure you want to delete this record?')) {
                var personId = $(this).data('person-id');
                $('#delete_person_id').val(personId);
                $('#deleteForm').submit();
            }
        });
        $('#report_type').on('change', function() {
            if ($(this).val() == 'per_purok') {
                $('#purok_group').show();
            } else {
                $('#purok_group').hide();
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
                    <h5 class="modal-title">Edit Household Record</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="person_id" id="edit_person_id">
                        <div class="form-group">
                            <label for="edit_water_source">Water Source</label>
                            <select class="form-control" id="edit_water_source" name="water_source" required>
                                <option value="">Select Water Source</option>
                                <option value="Level 1 (Poso)">Level 1 (Poso)</option>
                                <option value="Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)">Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)</option>
                                <option value="Level 3 (Nawasa)">Level 3 (Nawasa)</option>
                                <option value="WRS (Water Refilling Station)">WRS (Water Refilling Station)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_toilet_type">Toilet Type</label>
                            <select class="form-control" id="edit_toilet_type" name="toilet_type" required>
                                <option value="">Select Toilet Type</option>
                                <option value="De Buhos">De Buhos</option>
                                <option value="Sanitary Pit">Sanitary Pit</option>
                                <option value="Pit Privy">Pit Privy</option>
                                <option value="Wala">Wala</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Visit Months</label>
                            <div class="checkbox-group" id="edit_visit_months_group">
                                <?php
                                $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                foreach ($months as $month) {
                                    echo "<label><input type='checkbox' name='visit_months[]' value='$month' class='edit-month-checkbox'> $month</label>";
                                }
                                ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_health_condition">Health Condition</label>
                            <select class="form-control" id="edit_health_condition" name="health_condition" required>
                                <option value="NB (Newborn 0-28 days)">NB (Newborn 0-28 days)</option>
                                <option value="I (Infant 1-11 months)">I (Infant 1-11 months)</option>
                                <option value="C (Child 12-71 months)">C (Child 12-71 months)</option>
                                <option value="NP (Non-Pregnant)">NP (Non-Pregnant)</option>
                                <option value="P (Pregnant)">P (Pregnant)</option>
                                <option value="PP (Post-Partum 6 weeks)">PP (Post-Partum 6 weeks)</option>
                                <option value="CC (Coughing 2 weeks or more)">CC (Coughing 2 weeks or more)</option>
                                <option value="M (Malaria)">M (Malaria)</option>
                                <option value="E (Elderly 60+)">E (Elderly 60+)</option>
                                <option value="PWD (Person With Disability)">PWD (Person With Disability)</option>
                                <option value="DM (Diabetic)">DM (Diabetic)</option>
                                <option value="HPN (High Blood Pressure)">HPN (High Blood Pressure)</option>
                                <option value="CA (Cancer)">CA (Cancer)</option>
                                <option value="B (Bukol)">B (Bukol)</option>
                                <option value="NRP (No Record Provided)">NRP (No Record Provided)</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" form="editForm">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <form id="deleteForm" method="post" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="person_id" id="delete_person_id">
    </form>
    <?php endif; ?>
</body>
</html>
