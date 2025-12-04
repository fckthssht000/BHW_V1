<?php
session_start();
require_once 'db_connect.php';
require_once 'vendor/autoload.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file for a specific year
function refresh_fp_json_file($pdo, $year, $json_file) {
    $query = "
        SELECT DISTINCT p.person_id, p.full_name, p.age, p.gender, p.birthdate, p.household_number, 
               fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using, 
               a.purok, r.records_id, fpr.created_at
        FROM person p
        LEFT JOIN address a ON p.address_id = a.address_id
        LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'family_planning_record'
        LEFT JOIN family_planning_record fpr ON r.records_id = fpr.records_id
        WHERE p.gender = 'F' AND p.birthdate IS NOT NULL
        AND (YEAR(fpr.created_at) = ? OR fpr.created_at IS NULL)
        ORDER BY a.purok, p.household_number, p.full_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter for females aged 15-49
    $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $filtered_records = array_filter($all_records, function($record) use ($current_date) {
        if (!$record['birthdate']) return false;
        $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
        $age_in_days = $current_date->diff($birthdate)->days;
        return $age_in_days >= 5475 && $age_in_days <= 17885; // 15-49 years
    });
    
    file_put_contents($json_file, json_encode(array_values($filtered_records), JSON_PRETTY_PRINT));
    return array_values($filtered_records);
}

// Function to check if JSON needs refresh
function needs_fp_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT MAX(updated_at) as latest FROM family_planning_record WHERE YEAR(created_at) = ?");
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
    $user_person_id = null;
    if ($role_id_download == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_purok_download = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT p.person_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_person_id = $stmt->fetchColumn();
        
        $report_type = 'per_purok';
        $purok = $user_purok_download;
    }

    $json_dir = 'data/family_planning_records/';
    $json_file = $json_dir . $year . '_family_planning_record.json';
    
    if (file_exists($json_file)) {
        $all_data = json_decode(file_get_contents($json_file), true);
    } else {
        $base_query = "
            SELECT DISTINCT p.person_id, p.full_name, p.age, p.gender, p.birthdate, p.household_number, 
                   fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using, a.purok
            FROM person p
            LEFT JOIN address a ON p.address_id = a.address_id
            LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'family_planning_record'
            LEFT JOIN family_planning_record fpr ON r.records_id = fpr.records_id
            WHERE p.gender = 'F' AND p.birthdate IS NOT NULL
            ORDER BY a.purok, p.household_number, p.full_name
        ";
        
        $stmt = $pdo->prepare($base_query);
        $stmt->execute();
        $all_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
        $all_data = array_filter($all_data, function($record) use ($current_date, $user_person_id) {
            if (!$record['birthdate']) return false;
            $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
            $age_in_days = $current_date->diff($birthdate)->days;
            return $age_in_days >= 5475 && $age_in_days <= 17885 && ($user_person_id === null || $record['person_id'] != $user_person_id);
        });
        
        $all_data = array_values($all_data);
    }

    $data = [];
    
    if ($report_type == 'barangay') {
        $data = $all_data;
    } elseif ($report_type == 'per_purok' && !empty($purok)) {
        $data = array_filter($all_data, function($record) use ($purok) {
            return $record['purok'] == $purok;
        });
        
        if (empty($data)) {
            die("No records found for Purok: " . htmlspecialchars($purok));
        }
    } else {
        die("Invalid report type or purok selection.");
    }

    $data = array_values($data);

    if (empty($data)) {
        die("No records found for the selected criteria.");
    }

    if ($_POST['download'] == 'xlsx') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Family_Planning_Records_' . $year . '_' . ($report_type == 'per_purok' ? $purok . '_' : '') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Household Number', 'Full Name', 'Age', 'Gender', 'Uses FP', 'FP Methods', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec', 'Reason for Non-Use']);
        foreach ($data as $row) {
            $uses_fp = ($row['uses_fp_method'] == 'Y') ? 'Y' : '';
            $months_used = explode(',', $row['months_used'] ?? '');
            $jan = in_array('January', $months_used) ? 'Y' : '';
            $feb = in_array('February', $months_used) ? 'Y' : '';
            $mar = in_array('March', $months_used) ? 'Y' : '';
            $apr = in_array('April', $months_used) ? 'Y' : '';
            $may = in_array('May', $months_used) ? 'Y' : '';
            $jun = in_array('June', $months_used) ? 'Y' : '';
            $jul = in_array('July', $months_used) ? 'Y' : '';
            $aug = in_array('August', $months_used) ? 'Y' : '';
            $sept = in_array('September', $months_used) ? 'Y' : '';
            $oct = in_array('October', $months_used) ? 'Y' : '';
            $nov = in_array('November', $months_used) ? 'Y' : '';
            $dec = in_array('December', $months_used) ? 'Y' : '';
            fputcsv($output, [
                $row['household_number'] ?? '',
                $row['full_name'] ?? '',
                $row['age'] ?? '',
                $row['gender'] ?? '',
                $uses_fp,
                $row['fp_method'] ?? '',
                $jan, $feb, $mar, $apr, $may, $jun, $jul, $aug, $sept, $oct, $nov, $dec,
                $row['reason_not_using'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    } elseif ($_POST['download'] == 'pdf') {
        $stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));

        ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Family Planning Records</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { padding: 12px; }
        .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
        .meta { text-align: center; font-size: 12px; color: #444; }
        .address-details { text-align: center; margin-bottom: 10px; }
        .address-details span { display: inline-block; margin: 0 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #000; padding: 2px; text-align: center; word-wrap: break-word; }
        th { background: #f2f2f2; }
        .grouped-header { background: #e9e9e9; }
        .col-narrow { width: 30px; }
        .col-name { width: 80px; }
        .col-small { width: 45px; }
        .col-medium { width: 60px; }
        .col-month { width: 55px; }
        .col-large { width: 90px; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="title">
            <h1>Family Planning Records</h1>
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
                    <th rowspan="2" class="col-narrow" style="align-items: center;">No.</th>
                    <th colspan="3" class="grouped-header">Personal Information</th>
                    <th rowspan="2" class="col-small" style="align-items: center;">Uses FP</th>
                    <th colspan="12" class="grouped-header">Months of Use</th>
                    <th rowspan="2" class="col-large" style="align-items: center;">Reason for Non-Use</th>
                </tr>
                <tr>
                    <th class="col-name">Full Name</th>
                    <th class="col-medium">Household Number</th>
                    <th class="col-small">Age</th>
                    <th class="col-month">Jan</th>
                    <th class="col-month">Feb</th>
                    <th class="col-month">Mar</th>
                    <th class="col-month">Apr</th>
                    <th class="col-month">May</th>
                    <th class="col-month">Jun</th>
                    <th class="col-month">Jul</th>
                    <th class="col-month">Aug</th>
                    <th class="col-month">Sep</th>
                    <th class="col-month">Oct</th>
                    <th class="col-month">Nov</th>
                    <th class="col-month">Dec</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                foreach ($data as $row) {
                    $birthdate = new DateTime($row['birthdate'], new DateTimeZone('America/Los_Angeles'));
                    $age = floor($current_date->diff($birthdate)->y);
                    $uses_fp = ($row['uses_fp_method'] == 'Y') ? '✓' : '';
                    $months_used = explode(',', $row['months_used'] ?? '');
                    $jan = in_array('January', $months_used) ? '✓' : '';
                    $feb = in_array('February', $months_used) ? '✓' : '';
                    $mar = in_array('March', $months_used) ? '✓' : '';
                    $apr = in_array('April', $months_used) ? '✓' : '';
                    $may = in_array('May', $months_used) ? '✓' : '';
                    $jun = in_array('June', $months_used) ? '✓' : '';
                    $jul = in_array('July', $months_used) ? '✓' : '';
                    $aug = in_array('August', $months_used) ? '✓' : '';
                    $sept = in_array('September', $months_used) ? '✓' : '';
                    $oct = in_array('October', $months_used) ? '✓' : '';
                    $nov = in_array('November', $months_used) ? '✓' : '';
                    $dec = in_array('December', $months_used) ? '✓' : '';
                    echo '<tr>
                        <td>' . $count++ . '</td>
                        <td style="text-align: left;">' . htmlspecialchars(substr($row['full_name'] ?? '', 0, 25)) . '</td>
                        <td>' . htmlspecialchars($row['household_number'] ?? 'N/A') . '</td>
                        <td>' . $age . '</td>
                        <td>' . $uses_fp . '</td>
                        <td>' . $jan . '</td><td>' . $feb . '</td><td>' . $mar . '</td><td>' . $apr . '</td>
                        <td>' . $may . '</td><td>' . $jun . '</td><td>' . $jul . '</td><td>' . $aug . '</td>
                        <td>' . $sept . '</td><td>' . $oct . '</td><td>' . $nov . '</td><td>' . $dec . '</td>
                        <td>' . htmlspecialchars(substr($row['reason_not_using'] ?? '', 0, 25)) . '</td>
                    </tr>';
                }
                for ($i = count($data); $i < 50; $i++) {
                    echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
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
        $filename = 'Family_Planning_Records_' . $year . '_' . ($report_type == 'per_purok' ? $purok . '_' : '') . date('Ymd_His') . '.pdf';
        $html2pdf->output($filename, 'D');
        exit;
    }
}

// Handle AJAX requests for fetching record data
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['records_id'])) {
    header('Content-Type: application/json');
    $records_id = $_GET['records_id'];
    
    // Check if records_id is 'create_new' (for N/A records)
    if ($records_id === 'create_new') {
        $person_id = $_GET['person_id'] ?? null;
        if ($person_id) {
            // Return empty data for new record
            echo json_encode([
                'success' => true,
                'is_new' => true,
                'person_id' => $person_id,
                'data' => [
                    'uses_fp_method' => 'N',
                    'fp_method' => '',
                    'months_used' => '',
                    'reason_not_using' => ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Person ID required for new record']);
        }
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using
            FROM family_planning_record fpr
            WHERE fpr.records_id = ?
        ");
        $stmt->execute([$records_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            echo json_encode(['success' => true, 'data' => $record, 'is_new' => false]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch record']);
    }
    exit;
}

// Handle POST actions for update and delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['records_id'])) {
    header('Content-Type: application/json');
    
    $current_year = date('Y');
    $records_id = $_POST['records_id'];
    
    // Check if this is a new record creation
    $is_new_record = ($records_id === 'create_new');
    
    if (isset($_POST['fp_usage'])) {
        $uses_fp = $_POST['fp_usage'] ?? 'N';
        $fp_methods = isset($_POST['fp_methods']) ? implode(',', $_POST['fp_methods']) : '';
        $months_used = isset($_POST['months_use']) ? implode(',', $_POST['months_use']) : '';
        $reason_not_using = $_POST['reason_not_use'] ?? '';
        
        if ($uses_fp === 'N') {
            $fp_methods = '';
            $months_used = '';
        }
        
        try {
            if ($is_new_record) {
                // Create new record
                $person_id = $_POST['person_id'] ?? null;
                if (!$person_id) {
                    echo json_encode(['success' => false, 'message' => 'Person ID required']);
                    exit;
                }
                
                // First create a records entry
                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, created_at) VALUES (?, 'family_planning_record', NOW())");
                $stmt->execute([$person_id]);
                $new_records_id = $pdo->lastInsertId();
                
                // Then create the family planning record
                $stmt = $pdo->prepare("INSERT INTO family_planning_record (records_id, uses_fp_method, fp_method, months_used, reason_not_using, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$new_records_id, $uses_fp, $fp_methods, $months_used, $reason_not_using]);
            } else {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE family_planning_record SET uses_fp_method = ?, fp_method = ?, months_used = ?, reason_not_using = ?, updated_at = NOW() WHERE records_id = ?");
                $stmt->execute([$uses_fp, $fp_methods, $months_used, $reason_not_using, $records_id]);
            }
            
            // Refresh JSON file for current year
            $json_dir = 'data/family_planning_records/';
            if (!is_dir($json_dir)) {
                mkdir($json_dir, 0755, true);
            }
            $json_file = $json_dir . $current_year . '_family_planning_record.json';
            refresh_fp_json_file($pdo, $current_year, $json_file);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to save record: ' . $e->getMessage()]);
        }
    } else {
        // Delete record
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM family_planning_record WHERE records_id = ?");
            $stmt->execute([$records_id]);
            $pdo->commit();
            
            // Refresh JSON file for current year
            $json_dir = 'data/family_planning_records/';
            $json_file = $json_dir . $current_year . '_family_planning_record.json';
            if (file_exists($json_file)) {
                refresh_fp_json_file($pdo, $current_year, $json_file);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete record: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Fetch user role
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

// Get available years
$stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(created_at) as year FROM family_planning_record ORDER BY year DESC");
$stmt_years->execute();
$years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// Get selected year
$current_year = date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? (int)$_GET['year'] : $current_year;
$is_current_year = ($selected_year == $current_year);

$current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));

// JSON file management
$json_dir = 'data/family_planning_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}
$json_file = $json_dir . $selected_year . '_family_planning_record.json';

if ($is_current_year && needs_fp_json_refresh($pdo, $selected_year, $json_file)) {
    $all_records = refresh_fp_json_file($pdo, $selected_year, $json_file);
} else if (file_exists($json_file)) {
    $json_data = json_decode(file_get_contents($json_file), true);
    $all_records = is_array($json_data) ? $json_data : [];
} else {
    $all_records = refresh_fp_json_file($pdo, $selected_year, $json_file);
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
            if (!isset($record['person_id']) || $record['person_id'] != $user_person_id) {
                $filtered_records[] = $record;
            }
        }
    }
} else {
    $filtered_records = $all_records;
}

// Calculate statistics
$total_women = count($filtered_records);
$using_fp = count(array_filter($filtered_records, fn($r) => ($r['uses_fp_method'] ?? 'N') === 'Y'));
$not_using_fp = $total_women - $using_fp;
$fp_rate = $total_women > 0 ? round(($using_fp / $total_women) * 100, 1) : 0;

// Count popular methods
$method_counts = [];
foreach ($filtered_records as $record) {
    if (($record['uses_fp_method'] ?? 'N') === 'Y' && !empty($record['fp_method'])) {
        $methods = explode(',', $record['fp_method']);
        foreach ($methods as $method) {
            $method = trim($method);
            if (!isset($method_counts[$method])) {
                $method_counts[$method] = 0;
            }
            $method_counts[$method]++;
        }
    }
}
arsort($method_counts);
$most_popular_method = !empty($method_counts) ? array_key_first($method_counts) : 'None';

// Group by purok
$purok_household_records = [];
if ($role_id == 1 || $role_id == 4 || $role_id == 2) {
    $puroks_to_show = ($role_id == 1 || $role_id == 4) ? array_unique(array_column($filtered_records, 'purok')) : [$user_purok];
    foreach ($puroks_to_show as $purok) {
        if ($purok === null || $purok === '') continue;
        $purok_household_records[$purok] = [];
        foreach ($filtered_records as $record) {
            if ($record['purok'] == $purok) {
                $purok_household_records[$purok][] = $record;
            }
        }
        if (empty($purok_household_records[$purok])) {
            unset($purok_household_records[$purok]);
        }
    }
} else {
    $filtered_records = array_values($filtered_records);
}

function sanitizePurokId($purok) {
    return 'purok-' . preg_replace('/[^a-z0-9]/', '-', strtolower($purok));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Family Planning Records</title>
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
        .content.with-sidebar { margin-left: 250px; }
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
        .table th {
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
            .card { margin-bottom: 15px; margin-left: 20px; margin-right: 0;}
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
                                <div class="stat-label">Total Women</div>
                                <div class="stat-value"><?php echo number_format($total_women); ?></div>
                                <small class="text-muted">Ages 15-49</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">FP Usage Rate</div>
                                <div class="stat-value <?php echo $fp_rate > 50 ? 'text-success' : 'text-warning'; ?>"><?php echo $fp_rate; ?>%</div>
                                <small class="text-muted"><?php echo $using_fp; ?> using FP</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Not Using FP</div>
                                <div class="stat-value <?php echo $not_using_fp > ($total_women * 0.5) ? 'text-danger' : 'text-success'; ?>"><?php echo $not_using_fp; ?></div>
                                <small class="text-muted"><?php echo $total_women > 0 ? round(($not_using_fp / $total_women) * 100, 1) : 0; ?>%</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Popular Method</div>
                                <div class="stat-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars(substr($most_popular_method, 0, 15)); ?></div>
                                <small class="text-muted">Most used</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_current_year): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?php echo $selected_year; ?> are read-only. Switch to <?php echo $current_year; ?> to edit records.
                        </div>
                    <?php endif; ?>
                    <?php if ($fp_rate < 40): ?>
                        <div class="alert-warning-custom alert-custom">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Low Coverage Alert:</strong> FP usage rate is <?php echo $fp_rate; ?>%. Consider awareness programs.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header"><i class="fas fa-heart"></i> Family Planning Records <?php echo $role_id == 2 ? "($user_purok)" : ''; ?></div>
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

                        <div class="mb-3">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                                <?php if ($role_id == 1 || $role_id == 4): ?>
                                    <div class="form-group d-inline-block mr-2">
                                        <label for="report_type" class="mr-2">Report Type:</label>
                                        <select name="report_type" id="report_type" class="form-control d-inline-block w-auto">
                                            <option value="barangay">Whole Barangay</option>
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
                                <?php else: ?>
                                    <input type="hidden" name="report_type" value="per_purok">
                                    <input type="hidden" name="purok" value="<?php echo htmlspecialchars($user_purok); ?>">
                                <?php endif; ?>
                                <button type="submit" name="download" value="pdf" class="btn btn-primary btn-sm">
                                    <i class="fas fa-download"></i> Download PDF
                                </button>
                                <button type="submit" name="download" value="xlsx" class="btn btn-secondary btn-sm ml-2">
                                    <i class="fas fa-download"></i> Download XLSX
                                </button>
                            </form>
                        </div>

                        <div class="mb-3">
                            <input type="text" id="search" class="form-control search-input" placeholder="Search records..." aria-label="Search">
                        </div>

                        <?php if ($role_id == 3): ?>
                            <!-- Resident View -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Household Number</th>
                                            <th>Full Name</th>
                                            <th>Age</th>
                                            <th>Uses FP</th>
                                            <th>FP Methods</th>
                                            <th>Months Used</th>
                                            <th>Reason for Non-Use</th>
                                            <?php if ($is_current_year): ?>
                                                <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="recordTable">
                                        <?php foreach ($filtered_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['household_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['age'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['uses_fp_method'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['fp_method'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['months_used'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['reason_not_using'] ?? 'N/A'); ?></td>
                                                <?php if ($is_current_year): ?>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary edit-btn" 
                                                                data-id="<?php echo $record['records_id'] ?? 'create_new'; ?>"
                                                                data-person-id="<?php echo $record['person_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (!empty($record['records_id'])): ?>
                                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $record['records_id']; ?>"><i class="fas fa-trash"></i></button>
                                                        <?php endif; ?>
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
                                <?php $first = true; foreach ($purok_household_records as $purok => $records): ?>
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
                                <?php $first = true; foreach ($purok_household_records as $purok => $records): ?>
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
                                                            <th>Uses FP</th>
                                                            <th>FP Methods</th>
                                                            <th>Months Used</th>
                                                            <th>Reason</th>
                                                            <?php if ($is_current_year): ?>
                                                                <th>Actions</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($records as $record): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($record['household_number'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['age'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['uses_fp_method'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['fp_method'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['months_used'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['reason_not_using'] ?? 'N/A'); ?></td>
                                                                <?php if ($is_current_year): ?>
                                                                    <td>
                                                                        <button class="btn btn-sm btn-primary edit-btn" 
                                                                                data-id="<?php echo $record['records_id'] ?? 'create_new'; ?>"
                                                                                data-person-id="<?php echo $record['person_id']; ?>">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <?php if (!empty($record['records_id'])): ?>
                                                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $record['records_id']; ?>"><i class="fas fa-trash"></i></button>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mt-3">
                                                No family planning records found for Purok <?php echo htmlspecialchars($purok); ?>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No family planning records found for the selected year.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Family Planning Record</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_records_id" name="records_id">
                        <input type="hidden" id="edit_person_id" name="person_id">
                        <div class="form-group">
                            <label>Uses Family Planning</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="fp_usage" id="edit_fp_usage_y" value="Y">
                                    <label class="form-check-label" for="edit_fp_usage_y">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="fp_usage" id="edit_fp_usage_n" value="N">
                                    <label class="form-check-label" for="edit_fp_usage_n">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="fp_methods_group">
                            <label>Family Planning Methods</label>
                            <div id="edit_fp_methods_group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="BTL (Ligation)" id="edit_btl">
                                    <label class="form-check-label" for="edit_btl">BTL (Ligation)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NSV (Vasectomy)" id="edit_nsv">
                                    <label class="form-check-label" for="edit_nsv">NSV (Vasectomy)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="P (Pills)" id="edit_pills">
                                    <label class="form-check-label" for="edit_pills">P (Pills)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="IUD" id="edit_iud">
                                    <label class="form-check-label" for="edit_iud">IUD</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="DMPA" id="edit_dmpa">
                                    <label class="form-check-label" for="edit_dmpa">DMPA</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NFP-CM (Cervical Mucus)" id="edit_nfp_cm">
                                    <label class="form-check-label" for="edit_nfp_cm">NFP-CM (Cervical Mucus)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NFP-BBT (Basal Body Temperature)" id="edit_nfp_bbt">
                                    <label class="form-check-label" for="edit_nfp_bbt">NFP-BBT (Basal Body Temperature)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NFP-STM (Sympto Thermal Method)" id="edit_nfp_stm">
                                    <label class="form-check-label" for="edit_nfp_stm">NFP-STM (Sympto Thermal Method)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NFP-SDM (Standard Days Method)" id="edit_nfp_sdm">
                                    <label class="form-check-label" for="edit_nfp_sdm">NFP-SDM (Standard Days Method)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="NFP-LAM (Lactation Amenorrhea Method)" id="edit_nfp_lam">
                                    <label class="form-check-label" for="edit_nfp_lam">NFP-LAM (Lactation Amenorrhea Method)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fp_methods[]" value="Condom" id="edit_condom">
                                    <label class="form-check-label" for="edit_condom">Condom</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="months_use_group">
                            <label>Months of Use</label>
                            <div id="edit_months_use_group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="January" id="edit_jan">
                                    <label class="form-check-label" for="edit_jan">January</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="February" id="edit_feb">
                                    <label class="form-check-label" for="edit_feb">February</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="March" id="edit_mar">
                                    <label class="form-check-label" for="edit_mar">March</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="April" id="edit_apr">
                                    <label class="form-check-label" for="edit_apr">April</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="May" id="edit_may">
                                    <label class="form-check-label" for="edit_may">May</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="June" id="edit_jun">
                                    <label class="form-check-label" for="edit_jun">June</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="July" id="edit_jul">
                                    <label class="form-check-label" for="edit_jul">July</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="August" id="edit_aug">
                                    <label class="form-check-label" for="edit_aug">August</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="September" id="edit_sep">
                                    <label class="form-check-label" for="edit_sep">September</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="October" id="edit_oct">
                                    <label class="form-check-label" for="edit_oct">October</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="November" id="edit_nov">
                                    <label class="form-check-label" for="edit_nov">November</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="months_use[]" value="December" id="edit_dec">
                                    <label class="form-check-label" for="edit_dec">December</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="reason_group">
                            <label for="edit_reason_not_use">Reason for Non-Use</label>
                            <textarea class="form-control" id="edit_reason_not_use" name="reason_not_use"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEdit">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this family planning record?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $('#search').on('input', function() {
            let value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        $('#report_type').on('change', function() {
            if ($(this).val() == 'per_purok') {
                $('#purok_group').show();
            } else {
                $('#purok_group').hide();
            }
        });

        $(document).on('click', '.edit-btn', function() {
            var recordsId = $(this).data('id');
            var personId = $(this).data('person-id');
            
            $('#edit_records_id').val(recordsId);
            $('#edit_person_id').val(personId);
            
            var fetchUrl = 'fp_records.php?records_id=' + recordsId;
            if (recordsId === 'create_new') {
                fetchUrl += '&person_id=' + personId;
            }
            
            $.ajax({
                url: fetchUrl,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        if (data.uses_fp_method === 'Y') {
                            $('#edit_fp_usage_y').prop('checked', true);
                        } else {
                            $('#edit_fp_usage_n').prop('checked', true);
                        }
                        
                        $('#edit_fp_methods_group input[type="checkbox"]').prop('checked', false);
                        if (data.fp_method && data.fp_method !== 'N/A' && data.fp_method !== '') {
                            var methods = data.fp_method.split(',');
                            methods.forEach(function(method) {
                                var trimmedMethod = method.trim();
                                $('#edit_fp_methods_group input[value="' + trimmedMethod + '"]').prop('checked', true);
                            });
                        }
                        
                        $('#edit_months_use_group input[type="checkbox"]').prop('checked', false);
                        if (data.months_used && data.months_used !== 'N/A' && data.months_used !== '') {
                            var months = data.months_used.split(',');
                            months.forEach(function(month) {
                                var trimmedMonth = month.trim();
                                $('#edit_months_use_group input[value="' + trimmedMonth + '"]').prop('checked', true);
                            });
                        }
                        
                        $('#edit_reason_not_use').val(data.reason_not_using && data.reason_not_using !== 'N/A' ? data.reason_not_using : '');
                        toggleFields();
                        $('#editModal').modal('show');
                    } else {
                        alert('Error fetching record: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Error fetching record.');
                }
            });
        });

        function toggleFields() {
            var usesFp = $('input[name="fp_usage"]:checked').val();
            if (usesFp === 'Y') {
                $('#fp_methods_group').show();
                $('#months_use_group').show();
                $('#reason_group').hide();
            } else {
                $('#fp_methods_group').hide();
                $('#months_use_group').hide();
                $('#reason_group').show();
                $('#edit_fp_methods_group input[type="checkbox"]').prop('checked', false);
                $('#edit_months_use_group input[type="checkbox"]').prop('checked', false);
            }
        }

        $('input[name="fp_usage"]').on('change', function() {
            if ($(this).val() === 'N') {
                $('#edit_fp_methods_group input[type="checkbox"]').prop('checked', false);
                $('#edit_months_use_group input[type="checkbox"]').prop('checked', false);
            }
            toggleFields();
        });

        $('#saveEdit').on('click', function() {
            var formData = new FormData(document.getElementById('editForm'));
            
            $.ajax({
                url: 'fp_records.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#editModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error updating record: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save Error:', error);
                    alert('Error updating record.');
                }
            });
        });

        $(document).on('click', '.delete-btn', function() {
            var recordsId = $(this).data('id');
            $('#confirmDelete').data('id', recordsId);
            $('#deleteModal').modal('show');
        });

        $('#confirmDelete').on('click', function() {
            var recordsId = $(this).data('id');
            $.ajax({
                url: 'fp_records.php',
                type: 'POST',
                data: { records_id: recordsId },
                success: function(response) {
                    if (response.success) {
                        $('#deleteModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error deleting record: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete Error:', error);
                    alert('Error deleting record.');
                }
            });
        });

        $(document).ready(function() {
            $('#purokTabs a').on('click', function(e) {
                e.preventDefault();
                $(this).tab('show');
            });

            if ($('#purokTabs .nav-link.active').length === 0) {
                $('#purokTabs .nav-link:first').tab('show');
            }
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
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
        }
    </style>
</body>
</html>
