<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Helper function to format dates to MM/DD/YYYY
function format_date($date_string) {
    if (empty($date_string) || $date_string === 'N/A') {
        return 'N/A';
    }
    $date = new DateTime($date_string);
    return $date->format('m/d/Y');
}

// Helper: get medications for a pregnancy_record (returns array)
function get_medications($pdo, $pregnancy_record_id) {
    $stmt = $pdo->prepare("SELECT m.medication_name FROM pregnancy_medication pm JOIN medication m ON pm.medication_id = m.medication_id WHERE pm.pregnancy_record_id = ?");
    $stmt->execute([$pregnancy_record_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Refresh JSON export for fast listing
function refresh_pregnant_json_file($pdo, $year, $json_file) {
    $query = "
        SELECT p.person_id, p.full_name, p.philhealth_number, p.age, p.gender, p.birthdate, p.household_number, 
               pre.prenatal_id, pre.months_pregnancy, pre.checkup_date, 
               pre.risk_observed, pre.birth_plan, pre.last_menstruation AS lmp, 
               pre.expected_delivery_date AS edc, pre.preg_count, pre.child_alive, prr.created_at, a.purok, 
               pre.pregnancy_record_id
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN pregnancy_record prr ON r.records_id = prr.records_id
        JOIN prenatal pre ON prr.pregnancy_record_id = pre.pregnancy_record_id
        WHERE r.record_type = 'pregnancy_record.prenatal' AND prr.pregnancy_period = 'Prenatal'
        AND (YEAR(prr.created_at) = ? OR prr.created_at IS NULL)
        ORDER BY a.purok, p.household_number, p.full_name
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get medications for all records (bulk)
    foreach ($all_records as &$rec) {
        // Will be a comma-separated string for summary
        $meds = get_medications($pdo, $rec['pregnancy_record_id']);
        $rec['medication_name'] = implode(', ', $meds);
    }

    file_put_contents($json_file, json_encode($all_records));
    return $all_records;
}
function needs_pregnant_json_refresh($pdo, $year, $json_file) {
    if (!file_exists($json_file)) return true;
    $stmt = $pdo->prepare("SELECT MAX(created_at) as latest FROM pregnancy_record WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $db_latest = $stmt->fetchColumn();
    if (!$db_latest) return false;
    $file_time = filemtime($json_file);
    $db_time = strtotime($db_latest);
    return $db_time > $file_time;
}

// PDF Download Handler (normalized)
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
    $json_dir = 'data/pregnant_records/';
    $json_file = $json_dir . $year . '_pregnant_record.json';

    if (file_exists($json_file)) {
        $all_data = json_decode(file_get_contents($json_file), true);
    } else {
        $all_data = refresh_pregnant_json_file($pdo, $year, $json_file);
    }
    // Normalize: fetch medications for all rows
    foreach ($all_data as &$rec) {
        $meds = get_medications($pdo, $rec['pregnancy_record_id']);
        $rec['medication_name'] = implode(', ', $meds);
    }
    $data = [];
    if ($report_type == 'barangay' && ($role_id_download == 1 || $role_id_download == 4)) {
        $data = $all_data;
    } elseif ($report_type == 'per_purok' && $purok && ($role_id_download == 1 || $role_id_download == 4 || ($role_id_download == 2 && $purok == $user_purok_download))) {
        $data = array_filter($all_data, function($record) use ($purok) { return $record['purok'] == $purok; });
        $data = array_values($data);
    } else {
        die("Unauthorized: Invalid report type or purok access.");
    }
    if (empty($data)) die("No records found for the selected criteria.");
    $stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
    $address = $stmt->fetch(PDO::FETCH_ASSOC);
    require_once 'vendor/autoload.php';
    ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Pregnant Records</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; }
        @page { size: legal landscape; margin: 8mm; }
        .paper { padding: 10px; }
        .title h1 { text-align: center; font-size: 14px; margin: 0 0 3px; }
        .meta { text-align: center; font-size: 10px; color: #444; margin-bottom: 5px; }
        .address-details { text-align: center; margin-bottom: 8px; font-size: 14px; }
        .address-details span { display: inline-block; margin: 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        th, td { border: 1px solid #000; padding: 1px; text-align: center; word-wrap: break-word; vertical-align: middle; font-size: 12px;}
        th { background: #f2f2f2; }
        .col-narrow { width: 18px; }
        .col-name { width: 120px; }
        .col-small { width: 90px; }
        .col-medium { width: 90px; }
        .col-check { width: 90px; }
        .col-risk { width: 90px; }
        .rotate-text { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 12px; padding: 2px 1px; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="title">
            <h1>Pregnant Records</h1>
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
                    <th rowspan="2" class="col-name">Full Name</th>
                    <th rowspan="2" class="col-medium">HH#</th>
                    <th rowspan="2" class="col-small">Age</th>
                    <th rowspan="2" class="col-medium">Birthdate</th>
                    <th rowspan="2" class="col-small">Mo. Preg</th>
                    <th colspan="3">Prenatal Checkup</th>
                    <th rowspan="2" class="col-medium">Birth Plan</th>
                    <th colspan="2">Medicines</th>
                    <th rowspan="2" class="col-risk">Risk (A-G)</th>
                </tr>
                <tr>
                    <th class="col-check rotate-text">1st Tri</th>
                    <th class="col-check rotate-text">2nd Tri</th>
                    <th class="col-check rotate-text">3rd Tri</th>
                    <th class="col-check rotate-text">Ferrous</th>
                    <th class="col-check rotate-text">Tetanus</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                $risk_map = [
                    'Headache accompanied by Blurred Vision' => 'A',
                    'Fever' => 'B',
                    'Vaginal Bleeding' => 'C',
                    'Convulsion' => 'D',
                    'Severe Abdominal Pain' => 'E',
                    'Paleness' => 'F',
                    'Swelling of the foot/feet' => 'G'
                ];
                foreach ($data as $row) {
                    $checkup_dates = explode(',', $row['checkup_date'] ?? '');
                    $first_tri = in_array('First Trimester (0-84 days)', $checkup_dates) ? '✓' : '';
                    $second_tri = in_array('Second Trimester (85-189 days)', $checkup_dates) ? '✓' : '';
                    $third_tri = in_array('Third Trimester (190+ days)', $checkup_dates) ? '✓' : '';

                    $medications = explode(',', $row['medication_name'] ?? '');
                    $ferrous = in_array('Ferrous Sulfate with Folic Acid', array_map('trim', $medications)) ? '✓' : '';
                    $tetanus = in_array('Tetanus Toxoid', array_map('trim', $medications)) ? '✓' : '';

                    $risks = explode(',', $row['risk_observed'] ?? '');
                    $risk_codes = [];
                    foreach ($risks as $risk) {
                        $risk = trim($risk);
                        if (isset($risk_map[$risk])) {
                            $risk_codes[] = $risk_map[$risk];
                        }
                    }
                    $risk_display = !empty($risk_codes) ? implode(', ', $risk_codes) : '';

                    $birth_plan = ($row['birth_plan'] == 'Y') ? 'Yes' : (($row['birth_plan'] == 'N') ? 'No' : $row['birth_plan']);

                    echo '<tr>
                        <td>' . $count++ . '</td>
                        <td style="text-align: left;">' . htmlspecialchars(substr($row['full_name'], 0, 18)) . '</td>
                        <td>' . htmlspecialchars($row['household_number'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['age'] ?? 'N/A') . '</td>
                        <td>' . format_date($row['birthdate'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['months_pregnancy'] ?? 'N/A') . '</td>
                        <td>' . $first_tri . '</td>
                        <td>' . $second_tri . '</td>
                        <td>' . $third_tri . '</td>
                        <td>' . htmlspecialchars($birth_plan) . '</td>
                        <td>' . $ferrous . '</td>
                        <td>' . $tetanus . '</td>
                        <td>' . htmlspecialchars($risk_display) . '</td>
                    </tr>';
                }
                for ($i = count($data); $i < 30; $i++) {
                    echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                }
                ?>
            </tbody>
        </table>
        <div style="margin-top:8px; font-size:14px;">
            <strong>Risk Codes:</strong> A=Headache w/ Blurred Vision, B=Fever, C=Vaginal Bleeding, D=Convulsion, E=Severe Abdominal Pain, F=Paleness, G=Swelling
        </div>
    </div>
</body>
</html>
<?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(8, 8, 8, 8));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Pregnant_Records_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

// Handle AJAX get record (edit modal) - normalized!
if (isset($_GET['action']) && $_GET['action'] == 'get_record' && isset($_GET['prenatal_id'])) {
    $prenatal_id = $_GET['prenatal_id'];
    $stmt = $pdo->prepare("
        SELECT pre.months_pregnancy, pre.checkup_date, 
               pre.risk_observed, pre.birth_plan, pre.last_menstruation AS lmp, 
               pre.expected_delivery_date AS edc, pre.preg_count, pre.child_alive,
               p.philhealth_number, p.person_id, pre.pregnancy_record_id
        FROM prenatal pre
        LEFT JOIN pregnancy_record prr ON pre.pregnancy_record_id = prr.pregnancy_record_id
        LEFT JOIN records r ON prr.records_id = r.records_id
        LEFT JOIN person p ON r.person_id = p.person_id
        WHERE pre.prenatal_id = ?
    ");
    $stmt->execute([$prenatal_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        // Fetch related medications (array) and return as a comma-separated string
        $meds = get_medications($pdo, $record['pregnancy_record_id']);
        $record['medication_name'] = implode(',', $meds);
        echo json_encode($record);
    } else {
        echo json_encode(['error' => 'Record not found']);
    }
    exit;
}

// Handle POST actions (UPDATE - normalized!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $current_year = date('Y');
    $json_dir = 'data/pregnant_records/';
    if (!is_dir($json_dir)) { mkdir($json_dir, 0755, true); }
    $json_file = $json_dir . $current_year . '_pregnant_record.json';
    if ($_POST['action'] == 'update') {
        $prenatal_id = $_POST['prenatal_id'];
        $checkup_date = implode(',', $_POST['checkup_date'] ?? []);
        $months_pregnancy = $_POST['months_pregnancy'];
        $medications = $_POST['medication'] ?? [];
        $risks = implode(',', $_POST['risks'] ?? []);
        $birth_plan = isset($_POST['birth_plan']) ? 'Y' : 'N';
        $lmp = $_POST['lmp'];
        $edc = $_POST['edc'];
        $preg_count = $_POST['preg_count'];
        $child_alive = $_POST['child_alive'];
        $philhealth_number = $_POST['philhealth_number'] ?? '';

        if (empty($prenatal_id) || empty($checkup_date) || empty($months_pregnancy) || empty($lmp) || empty($edc) || empty($preg_count) || empty($child_alive)) {
            die("Error: All required fields must be filled.");
        }
        // Get related person and pregnancy_record_id
        $stmt = $pdo->prepare("
            SELECT r.person_id, pre.pregnancy_record_id
            FROM prenatal pre
            JOIN pregnancy_record pr ON pre.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            WHERE pre.prenatal_id = ?
        ");
        $stmt->execute([$prenatal_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) die("Error: Unable to find person or pregnancy record for this prenatal record.");
        $person_id = $row['person_id'];
        $pregnancy_record_id = $row['pregnancy_record_id'];

        $stmt = $pdo->prepare("UPDATE person SET philhealth_number = ? WHERE person_id = ?");
        $stmt->execute([$philhealth_number, $person_id]);
        $stmt = $pdo->prepare("
            UPDATE prenatal 
            SET checkup_date = ?, months_pregnancy = ?, risk_observed = ?, 
                birth_plan = ?, last_menstruation = ?, expected_delivery_date = ?,
                preg_count = ?, child_alive = ?
            WHERE prenatal_id = ?
        ");
        $stmt->execute([$checkup_date, $months_pregnancy, $risks, $birth_plan, $lmp, $edc, $preg_count, $child_alive, $prenatal_id]);
        // ---- Medication update: clear and re-insert ---------
        $pdo->prepare("DELETE FROM pregnancy_medication WHERE pregnancy_record_id = ?")->execute([$pregnancy_record_id]);
        if (!empty($medications)) {
            foreach ($medications as $medication_name) {
                $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                $stmt->execute([$medication_name]);
                $medication_id = $stmt->fetchColumn();
                if ($medication_id === false) {
                    $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                    $stmt->execute([$medication_name]);
                    $medication_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO pregnancy_medication (pregnancy_record_id, medication_id) VALUES (?, ?)");
                $stmt->execute([$pregnancy_record_id, $medication_id]);
            }
        }
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "UPDATED: prenatal_record prenatal_id:$prenatal_id"]);
        refresh_pregnant_json_file($pdo, $current_year, $json_file);
        header("Location: pregnant_records.php");
        exit;
    } elseif ($_POST['action'] == 'delete') {
        $prenatal_id = $_POST['prenatal_id'];
        $stmt = $pdo->prepare("
            SELECT r.person_id, pre.pregnancy_record_id
            FROM prenatal pre
            JOIN pregnancy_record pr ON pre.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            WHERE pre.prenatal_id = ?
        ");
        $stmt->execute([$prenatal_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $person_id = $row['person_id'] ?? null;
        $pregnancy_record_id = $row['pregnancy_record_id'] ?? null;
        $pdo->prepare("DELETE FROM pregnancy_medication WHERE pregnancy_record_id = ?")->execute([$pregnancy_record_id]);
        $pdo->prepare("DELETE FROM prenatal WHERE prenatal_id = ?")->execute([$prenatal_id]);
        $pdo->prepare("
            DELETE pr, r 
            FROM pregnancy_record pr 
            JOIN records r ON pr.records_id = r.records_id 
            WHERE pr.pregnancy_record_id = ?
        ")->execute([$pregnancy_record_id]);
        $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())")->execute([$_SESSION['user_id'], "DELETED: prenatal_record prenatal_id:$prenatal_id for person_id:$person_id"]);
        refresh_pregnant_json_file($pdo, $current_year, $json_file);
        header("Location: pregnant_records.php");
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
$stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(created_at) as year FROM pregnancy_record WHERE pregnancy_period = 'Prenatal' ORDER BY year DESC");
$stmt_years->execute();
$years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// Get selected year
$current_year = date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? (int)$_GET['year'] : $current_year;
$is_editable = ($selected_year == $current_year);

// JSON file management
$json_dir = 'data/pregnant_records/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}
$json_file = $json_dir . $selected_year . '_pregnant_record.json';

if ($is_editable && needs_pregnant_json_refresh($pdo, $selected_year, $json_file)) {
    $all_records = refresh_pregnant_json_file($pdo, $selected_year, $json_file);
} else if (file_exists($json_file)) {
    $json_data = json_decode(file_get_contents($json_file), true);
    $all_records = is_array($json_data) ? $json_data : [];
} else {
    $all_records = refresh_pregnant_json_file($pdo, $selected_year, $json_file);
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

// Update months_pregnancy and handle transition
$current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
foreach ($filtered_records as &$record) {
    if ($record['lmp']) {
        $lmp = new DateTime($record['lmp'], new DateTimeZone('America/Los_Angeles'));
        $interval = $current_date->diff($lmp);
        $days_pregnant = $interval->days;
        $months_pregnancy = round($days_pregnant / 30);

        if ($months_pregnancy != $record['months_pregnancy']) {
            $stmt = $pdo->prepare("UPDATE prenatal SET months_pregnancy = ? WHERE prenatal_id = ?");
            $stmt->execute([$months_pregnancy, $record['prenatal_id']]);
            $record['months_pregnancy'] = $months_pregnancy;
        }

        if ($record['edc']) {
            $edc = new DateTime($record['edc'], new DateTimeZone('America/Los_Angeles'));
            $day_before_edc = clone $edc;
            $day_before_edc->modify('-1 day');
            if ($current_date >= $day_before_edc) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM records r 
                    JOIN pregnancy_record pr ON r.records_id = pr.records_id 
                    WHERE r.person_id = ? AND pr.pregnancy_period = 'Postnatal'
                ");
                $stmt->execute([$record['person_id']]);
                $postnatal_exists = $stmt->fetchColumn();
                
                if (!$postnatal_exists) {
                    $stmt = $pdo->prepare("INSERT INTO records (person_id, user_id, record_type, created_by) VALUES (?, ?, 'pregnancy_record.postnatal', ?)");
                    $stmt->execute([$record['person_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                    $new_record_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period, created_at, updated_at) VALUES (?, 'Postnatal', NOW(), NOW())");
                    $stmt->execute([$new_record_id]);
                    $pregnancy_record_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("
                        INSERT INTO postnatal (pregnancy_record_id, date_delivered, delivery_location, attendant, service_source, family_planning_intent) 
                        VALUES (?, ?, 'Unknown', 'Unknown', 'Unknown', 'Unknown')
                    ");
                    $stmt->execute([$pregnancy_record_id, $edc->format('Y-m-d')]);
                }
            }
        }
    }
}

// Calculate stats
$total_pregnant = count($filtered_records);
$with_birth_plan = 0;
$high_risk = 0;
$prenatal_checkup = 0;

foreach ($filtered_records as $record) {
    if ($record['birth_plan'] === 'Y') {
        $with_birth_plan++;
    }
    
    if (!empty($record['risk_observed']) && $record['risk_observed'] !== 'None') {
        $high_risk++;
    }
    
    if (!empty($record['checkup_date']) && $record['checkup_date'] !== 'None') {
        $prenatal_checkup++;
    }
}

$birth_plan_rate = $total_pregnant > 0 ? round(($with_birth_plan / $total_pregnant) * 100, 1) : 0;
$high_risk_rate = $total_pregnant > 0 ? round(($high_risk / $total_pregnant) * 100, 1) : 0;
$checkup_rate = $total_pregnant > 0 ? round(($prenatal_checkup / $total_pregnant) * 100, 1) : 0;

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

if ($role_id == 2 && $user_purok) {
    $puroks = [$user_purok];
} else {
    $puroks = array_keys($purok_records);
    $puroks = array_unique(array_filter($puroks, function($purok) {
        return !empty($purok) && $purok !== null;
    }));
}
sort($puroks);

function sanitizePurokId($purok) {
    return 'purok-' . preg_replace('/[^a-z0-9_-]/', '-', strtolower($purok));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Pregnant Records (Enhanced)</title>
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
                                <div class="stat-label">Total Pregnant</div>
                                <div class="stat-value"><?php echo number_format($total_pregnant); ?></div>
                                <small class="text-muted">Currently monitored</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Birth Plan Rate</div>
                                <div class="stat-value <?php echo $birth_plan_rate > 70 ? 'text-success' : 'text-warning'; ?>"><?php echo $birth_plan_rate; ?>%</div>
                                <small class="text-muted"><?php echo $with_birth_plan; ?> with plan</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">High Risk</div>
                                <div class="stat-value <?php echo $high_risk_rate > 20 ? 'text-danger' : 'text-success'; ?>"><?php echo $high_risk_rate; ?>%</div>
                                <small class="text-muted"><?php echo $high_risk; ?> cases</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Prenatal Checkup</div>
                                <div class="stat-value <?php echo $checkup_rate > 80 ? 'text-success' : 'text-warning'; ?>"><?php echo $checkup_rate; ?>%</div>
                                <small class="text-muted"><?php echo $prenatal_checkup; ?> attended</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_editable): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?php echo $selected_year; ?> are read-only. Switch to <?php echo $current_year; ?> to edit records.
                        </div>
                    <?php endif; ?>
                    <?php if ($high_risk_rate > 30): ?>
                        <div class="alert-danger-custom alert-custom">
                            <i class="fas fa-exclamation-circle"></i> <strong>Critical Alert:</strong> <?php echo $high_risk_rate; ?>% high-risk pregnancies. Immediate medical attention needed.
                        </div>
                    <?php elseif ($high_risk_rate > 15): ?>
                        <div class="alert-warning-custom alert-custom">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> <?php echo $high_risk_rate; ?>% high-risk pregnancies. Monitor closely.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header"><i class="fas fa-user-pregnant"></i> Pregnant Records <?php echo $role_id == 2 ? "($user_purok)" : ''; ?></div>
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
                                    <?php foreach ($puroks as $purok): ?>
                                        <option value="<?php echo htmlspecialchars($purok); ?>"><?php echo htmlspecialchars($purok); ?></option>
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
                                            <th>PhilHealth #</th>
                                            <th>Age</th>
                                            <th>Checkup</th>
                                            <th>Months Preg</th>
                                            <th># Preg</th>
                                            <th>Living Children</th>
                                            <th>Medication</th>
                                            <th>Risk</th>
                                            <th>Birth Plan</th>
                                            <?php if ($is_editable): ?>
                                            <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filtered_records as $record): 
                                            $birth_plan_display = ($record['birth_plan'] == 'Y') ? 'Yes' : (($record['birth_plan'] == 'N') ? 'No' : $record['birth_plan']);
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['philhealth_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['age'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['checkup_date'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['months_pregnancy'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['preg_count'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['child_alive'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['medication_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['risk_observed'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($birth_plan_display); ?></td>
                                                <?php if ($is_editable): ?>
                                                <td>
                                                    <button class="btn btn-sm btn-primary edit-btn" data-prenatal-id="<?php echo $record['prenatal_id']; ?>"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-sm btn-danger delete-btn" data-prenatal-id="<?php echo $record['prenatal_id']; ?>"><i class="fas fa-trash"></i></button>
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
                                                            <th>Name</th>
                                                            <th>PhilHealth</th>
                                                            <th>Age</th>
                                                            <th>Checkup</th>
                                                            <th>Mo.</th>
                                                            <th>#Preg</th>
                                                            <th>Living</th>
                                                            <th>Meds</th>
                                                            <th>Risk</th>
                                                            <th>Plan</th>
                                                            <?php if ($is_editable): ?>
                                                            <th>Actions</th>
                                                            <?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($records as $record): 
                                                            $birth_plan_display = ($record['birth_plan'] == 'Y') ? 'Yes' : (($record['birth_plan'] == 'N') ? 'No' : $record['birth_plan']);
                                                        ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($record['household_number'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['philhealth_number'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['age'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['checkup_date'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['months_pregnancy'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['preg_count'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['child_alive'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['medication_name'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['risk_observed'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($birth_plan_display); ?></td>
                                                                <?php if ($is_editable): ?>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary edit-btn" data-prenatal-id="<?php echo $record['prenatal_id']; ?>"><i class="fas fa-edit"></i></button>
                                                                    <button class="btn btn-sm btn-danger delete-btn" data-prenatal-id="<?php echo $record['prenatal_id']; ?>"><i class="fas fa-trash"></i></button>
                                                                </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mt-3">
                                                No pregnant records found for Purok <?php echo htmlspecialchars($purok); ?>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No pregnant records found for the selected year.
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
            const prenatalId = $(this).data('prenatal-id');
            $('#edit_prenatal_id').val(prenatalId);
            $.get('?action=get_record&prenatal_id=' + prenatalId, function(data) {
                const record = JSON.parse(data);
                
                $('#edit_philhealth_number').val(record.philhealth_number);
                $('#edit_lmp').val(record.lmp);
                $('#edit_edc').val(record.edc);
                $('#edit_preg_count').val(record.preg_count);
                $('#edit_child_alive').val(record.child_alive);
                $('#edit_months_pregnancy').val(record.months_pregnancy);
                
                $('.checkup-options input[type="checkbox"]').prop('checked', false);
                if (record.checkup_date) {
                    const checkupDates = record.checkup_date.split(',');
                    checkupDates.forEach(date => {
                        $(`.checkup-options input[value="${date.trim()}"]`).prop('checked', true);
                    });
                }
                
                $('input[name="medication[]"]').prop('checked', false);
                if (record.medication_name) {
                    const medications = record.medication_name.split(',');
                    medications.forEach(med => {
                        $(`input[name="medication[]"][value="${med.trim()}"]`).prop('checked', true);
                    });
                }
                
                $('input[name="risks[]"]').prop('checked', false);
                if (record.risk_observed) {
                    const risks = record.risk_observed.split(',');
                    risks.forEach(risk => {
                        $(`input[name="risks[]"][value="${risk.trim()}"]`).prop('checked', true);
                    });
                }
                
                $('#edit_birth_plan').prop('checked', record.birth_plan === 'Y');
                
                if ($('.checkup-options input[type="checkbox"]:checked').length === 0) {
                    $('.checkup-options input[value="None"]').prop('checked', true);
                }
            });
            $('#editModal').modal('show');
        });

        function calculatePregnancyDetailsEdit() {
            const lmpInput = document.getElementById('edit_lmp');
            const edcInput = document.getElementById('edit_edc');
            const monthsPregnancySelect = document.getElementById('edit_months_pregnancy');
            
            if (lmpInput.value) {
                const lmpDate = new Date(lmpInput.value);
                const currentDate = new Date();
                
                const diffTime = currentDate - lmpDate;
                const diffMonths = Math.round((diffTime / (1000 * 60 * 60 * 24 * 30)));
                
                const monthsPregnant = Math.max(1, Math.min(9, diffMonths));
                monthsPregnancySelect.value = monthsPregnant;

                const edcDate = new Date(lmpDate);
                edcDate.setDate(edcDate.getDate() + 280);
                const edcFormatted = edcDate.toISOString().split('T')[0];
                edcInput.value = edcFormatted;
            }
        }

        $(document).on('change', '.checkup-options input[type="checkbox"]', function() {
            if ($(this).val() === 'None') {
                if ($(this).is(':checked')) {
                    $('.checkup-options input[type="checkbox"]').not(this).prop('checked', false);
                }
            } else if ($('.checkup-options input[value="None"]').is(':checked')) {
                $('.checkup-options input[value="None"]').prop('checked', false);
            }
            
            if ($('.checkup-options input[type="checkbox"]:checked').length === 0) {
                $('.checkup-options input[value="None"]').prop('checked', true);
            }
        });

        $(document).on('click', '.delete-btn', function() {
            const prenatalId = $(this).data('prenatal-id');
            $('#delete_prenatal_id').val(prenatalId);
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
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pregnant Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="prenatal_id" id="edit_prenatal_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_philhealth_number">Philhealth Number</label>
                                <input type="text" class="form-control" id="edit_philhealth_number" name="philhealth_number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_lmp">Last Menstrual Period *</label>
                                <input type="date" class="form-control" id="edit_lmp" name="lmp" required onchange="calculatePregnancyDetailsEdit()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_edc">Expected Date of Childbirth *</label>
                                <input type="date" class="form-control" id="edit_edc" name="edc" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_preg_count">Number of Pregnancy *</label>
                                <input type="number" class="form-control" id="edit_preg_count" name="preg_count" required min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_child_alive">Number of Living Children *</label>
                                <input type="number" class="form-control" id="edit_child_alive" name="child_alive" required min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_months_pregnancy">Months Pregnant *</label>
                        <select class="form-control" id="edit_months_pregnancy" name="months_pregnancy" required>
                            <?php for ($i = 1; $i <= 9; $i++) { echo "<option value='$i'>$i month(s)</option>"; } ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Checkup Date *</label>
                        <div class="checkup-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checkup_date[]" value="None" id="edit_checkup_none">
                                <label class="form-check-label" for="edit_checkup_none">None</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checkup_date[]" value="First Trimester (0-84 days)" id="edit_checkup_first">
                                <label class="form-check-label" for="edit_checkup_first">First Trimester (0-84 days)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checkup_date[]" value="Second Trimester (85-189 days)" id="edit_checkup_second">
                                <label class="form-check-label" for="edit_checkup_second">Second Trimester (85-189 days)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checkup_date[]" value="Third Trimester (190+ days)" id="edit_checkup_third">
                                <label class="form-check-label" for="edit_checkup_third">Third Trimester (190+ days)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Medication</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="medication[]" value="Ferrous Sulfate with Folic Acid" id="edit_med_ferrous">
                            <label class="form-check-label" for="edit_med_ferrous">Ferrous Sulfate with Folic Acid</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="medication[]" value="Tetanus Toxoid" id="edit_med_tetanus">
                            <label class="form-check-label" for="edit_med_tetanus">Tetanus Toxoid</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Risks Observed</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Headache accompanied by Blurred Vision" id="edit_risk_headache">
                            <label class="form-check-label" for="edit_risk_headache">Headache accompanied by Blurred Vision</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Fever" id="edit_risk_fever">
                            <label class="form-check-label" for="edit_risk_fever">Fever</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Vaginal Bleeding" id="edit_risk_bleeding">
                            <label class="form-check-label" for="edit_risk_bleeding">Vaginal Bleeding</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Convulsion" id="edit_risk_convulsion">
                            <label class="form-check-label" for="edit_risk_convulsion">Convulsion</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Severe Abdominal Pain" id="edit_risk_pain">
                            <label class="form-check-label" for="edit_risk_pain">Severe Abdominal Pain</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Paleness" id="edit_risk_paleness">
                            <label class="form-check-label" for="edit_risk_paleness">Paleness</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="risks[]" value="Swelling of the foot/feet" id="edit_risk_swelling">
                            <label class="form-check-label" for="edit_risk_swelling">Swelling of the foot/feet</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="birth_plan" value="1" id="edit_birth_plan">
                            <label class="form-check-label" for="edit_birth_plan">Has Birth Plan</label>
                        </div>
                    </div>
                    
                    <div class="text-muted">
                        <small>* Required fields</small>
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
                <h5 class="modal-title">Delete Pregnant Record</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="prenatal_id" id="delete_prenatal_id">
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
