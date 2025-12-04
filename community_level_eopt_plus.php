<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_person_id = null;
$user_purok = null;

if ($role_id == 3) {
    // For Resident: get their person_id
    $stmt = $pdo->prepare("SELECT person_id FROM records WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_person_id = $stmt->fetchColumn();
    if ($user_person_id === false) {
        die("Error: No person record found for user_id: " . $_SESSION['user_id']);
    }
} elseif ($role_id == 2) {
    // For BHW Staff: get their purok
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

// Current date for age calculation
$current_date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));

// Query child records (ages 1 month to 5 years)
$query = "
    SELECT p.person_id, p.full_name, p.gender, p.birthdate, p.household_number, 
           chr.weight, chr.height, chr.measurement_date, a.purok, a.address_id,
           chr.records_id, chr.created_at
    FROM child_record chr
    JOIN records r ON r.records_id = chr.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE p.birthdate IS NOT NULL
    AND DATEDIFF(CURDATE(), p.birthdate) BETWEEN 29 AND 1825
";

$params = [];

// Add role-based filtering
if ($role_id == 3) {
    $query .= " AND p.person_id = ?";
    $params[] = $user_person_id;
} elseif ($role_id == 2 && $user_purok) {
    $query .= " AND a.purok = ?";
    $params[] = $user_purok;
}

$query .= " ORDER BY a.purok, p.household_number, p.full_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch mother/caregiver for each record
foreach ($records as &$record) {
    $child_name_parts = explode(' ', trim($record['full_name']));
    $child_surname = end($child_name_parts);

    $stmt = $pdo->prepare("
        SELECT p.full_name, p.relationship_type, p.gender, p.birthdate
        FROM person p
        WHERE p.household_number = ?
    ");
    $stmt->execute([$record['household_number']]);
    $household_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_same_surname = true;
    foreach ($household_members as $member) {
        $member_name_parts = explode(' ', trim($member['full_name']));
        $member_surname = end($member_name_parts);
        if ($member_surname !== $child_surname) {
            $all_same_surname = false;
            break;
        }
    }

    $mother = 'N/A';

    // Helper function to check if member is female and aged 20-49
    function isEligibleCaregiver($member, $current_date, $child_full_name) {
        if (
            $member['gender'] === 'F' &&
            !empty($member['birthdate']) &&
            $member['full_name'] !== $child_full_name
        ) {
            $birthdate = new DateTime($member['birthdate'], new DateTimeZone('America/Los_Angeles'));
            $age = $current_date->diff($birthdate)->y;
            return ($age >= 20 && $age <= 49);
        }
        return false;
    }

    if ($all_same_surname) {
        foreach ($household_members as $member) {
            if (in_array($member['relationship_type'], ['Spouse', 'Head']) && isEligibleCaregiver($member, $current_date, $record['full_name'])) {
                $mother = $member['full_name'];
                break;
            }
        }
    } else {
        $candidates = [];
        foreach ($household_members as $member) {
            $member_name_parts = explode(' ', trim($member['full_name']));
            $member_surname = end($member_name_parts);
            if ($member_surname === $child_surname && in_array($member['relationship_type'], ['Daughter-in-Law', 'Son-in-Law', 'Daughter', 'Son']) && $member['full_name'] !== $record['full_name']) {
                if (isEligibleCaregiver($member, $current_date, $record['full_name'])) {
                    $candidates[] = $member;
                }
            }
        }
        if (!empty($candidates)) {
            $mother = $candidates[0]['full_name'];
        } else {
            foreach ($household_members as $member) {
                if (in_array($member['relationship_type'], ['Spouse', 'Head']) && isEligibleCaregiver($member, $current_date, $record['full_name'])) {
                    $mother = $member['full_name'];
                    break;
                }
            }
        }
    }
    $record['mother_caregiver'] = $mother;
}
unset($record);

// Filter records for age 0–59 months and calculate age
$filtered_records = [];
foreach ($records as $record) {
    if ($record['birthdate']) {
        $birthdate = new DateTime($record['birthdate'], new DateTimeZone('America/Los_Angeles'));
        $age_in_days = $current_date->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;
        
        if ($age_in_months >= 1 && $age_in_months <= 59) {
            $record['age_in_days'] = $age_in_days;
            $record['age_in_months'] = floor($age_in_months);
            $filtered_records[] = $record;
        }
    }
}

// Function to load CSV data with LMS parameters
function loadCsvData($filename, $keyColumn, $valueColumns) {
    if (!file_exists($filename)) {
        error_log("CSV file not found: $filename");
        return [];
    }
    $rows = array_map('str_getcsv', file($filename));
    $headers = array_shift($rows);
    $data = [];
    foreach ($rows as $index => $row) {
        if (!isset($row[$keyColumn])) {
            error_log("Invalid row at line " . ($index + 2) . " in $filename: " . json_encode($row));
            continue;
        }
        $key = floatval($row[$keyColumn]);
        $values = [
            'L' => isset($row[1]) ? floatval($row[1]) : 0,
            'M' => isset($row[2]) ? floatval($row[2]) : 0,
            'S' => isset($row[3]) ? floatval($row[3]) : 0
        ];
        if ($values['M'] <= 0 || $values['S'] <= 0) {
            error_log("Invalid LMS values at key $key in $filename: L={$values['L']}, M={$values['M']}, S={$values['S']}");
            continue;
        }
        foreach ($valueColumns as $status => $index) {
            $values[$status] = isset($row[$index]) ? floatval($row[$index]) : null;
        }
        $data[$key] = $values;
    }
    if (empty($data)) {
        error_log("No valid data loaded from $filename");
    }
    return $data;
}

// Define column mappings for CSV files
$wfa_value_columns = ['SUW' => 4, 'UW' => 5, 'Normal' => 7, 'OW' => 9];
$hfa_value_columns = ['SSt' => 4, 'St' => 5, 'Normal' => 7];
$wflh_value_columns = ['SW' => 4, 'MW' => 5, 'Normal' => 7, 'OW' => 9, 'Ob' => 10];

// Load WHO reference data based on age
function getDatasetForAge($gender, $age_in_months, $metric) {
    $gender_prefix = $gender === 'M' ? 'boys' : 'girls';
    if ($metric === 'wfa') {
        return $age_in_months < 1 ? 
            "who_datasets/wfa_{$gender_prefix}_0-to-13-weeks_zscores.csv" : 
            "who_datasets/wfa_{$gender_prefix}_0-to-5-years_zscores.csv";
    } elseif ($metric === 'hfa') {
        if ($age_in_months < 1) {
            return "who_datasets/lhfa_{$gender_prefix}_0-to-13-weeks_zscores.csv";
        } elseif ($age_in_months <= 24) {
            return "who_datasets/lhfa_{$gender_prefix}_0-to-2-years_zscores.csv";
        } else {
            return "who_datasets/lhfa_{$gender_prefix}_2-to-5-years_zscores.csv";
        }
    } elseif ($metric === 'wflh') {
        return "who_datasets/wfl_{$gender_prefix}_0-to-2-years_zscores.csv";
    }
    return null;
}

// Function to interpolate between two data points
function interpolate($x0, $y0, $x1, $y1, $x) {
    if ($x0 == $x1) return $y0;
    return $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
}

// Function to calculate z-score using LMS parameters
function calculateZScore($x, $L, $M, $S) {
    if ($x <= 0 || $M <= 0 || $S <= 0) {
        error_log("Invalid input for z-score: x=$x, L=$L, M=$M, S=$S");
        return null;
    }
    if (abs($L) < 0.0001) {
        return log($x / $M) / $S;
    }
    $z = (pow($x / $M, $L) - 1) / ($L * $S);
    if (abs($z) > 5) {
        error_log("Extreme z-score detected: z=$z, x=$x, L=$L, M=$M, S=$S");
    }
    return $z;
}

// Function to get nutritional values for a specific age or height
function getNutritionalValues($data, $key, $statuses) {
    $keys = array_keys($data);
    if (empty($keys)) {
        error_log("No data available for key: $key");
        return null;
    }
    sort($keys);
    
    if (isset($data[$key])) {
        return $data[$key];
    }
    
    $lower_key = null;
    $upper_key = null;
    foreach ($keys as $k) {
        if ($k <= $key && ($lower_key === null || $k > $lower_key)) {
            $lower_key = $k;
        }
        if ($k >= $key && ($upper_key === null || $k < $upper_key)) {
            $upper_key = $k;
        }
    }
    
    if ($lower_key === null || $upper_key === null || $lower_key == $upper_key) {
        error_log("Cannot interpolate for key: $key, lower: $lower_key, upper: $upper_key");
        return null;
    }
    
    $result = [];
    $fields = array_merge(['L', 'M', 'S'], $statuses);
    foreach ($fields as $field) {
        if (!isset($data[$lower_key][$field]) || !isset($data[$upper_key][$field])) {
            error_log("Missing field $field for keys $lower_key or $upper_key");
            return null;
        }
        $result[$field] = interpolate(
            $lower_key,
            $data[$lower_key][$field],
            $upper_key,
            $data[$upper_key][$field],
            $key
        );
    }
    return $result;
}

// Load WHO datasets only as needed
$who_data_cache = [];

// Calculate nutritional status for all records
foreach ($filtered_records as &$record) {
    $age_in_months = $record['age_in_months'];
    $age_in_days = $record['age_in_days'];
    $gender = $record['gender'] === 'Male' ? 'M' : 'F';
    $weight = floatval($record['weight']);
    $height = floatval($record['height']);
    $age_in_weeks = $age_in_days / 7;

    // WFA Status
    $wfa_file = getDatasetForAge($gender, $age_in_months, 'wfa');
    if (!isset($who_data_cache[$wfa_file])) {
        $who_data_cache[$wfa_file] = loadCsvData($wfa_file, 0, $wfa_value_columns);
    }
    $wfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
    $wfa_values = getNutritionalValues($who_data_cache[$wfa_file], $wfa_key, ['SUW', 'UW', 'Normal', 'OW']);
    if ($wfa_values && $weight > 0) {
        $wfa_z = calculateZScore($weight, $wfa_values['L'], $wfa_values['M'], $wfa_values['S']);
        $record['wfa_z'] = $wfa_z !== null ? round($wfa_z, 2) : 'N/A';
        if ($wfa_z === null) {
            $record['wfa_status'] = 'N/A';
        } else {
            if ($wfa_z < -3) {
                $record['wfa_status'] = 'Severely Underweight';
            } elseif ($wfa_z < -2) {
                $record['wfa_status'] = 'Underweight';
            } elseif ($wfa_z <= 2) {
                $record['wfa_status'] = 'Normal';
            } else {
                $record['wfa_status'] = 'Overweight';
            }
        }
    } else {
        $record['wfa_status'] = 'N/A';
        $record['wfa_z'] = 'N/A';
    }

    // HFA Status
    $hfa_file = getDatasetForAge($gender, $age_in_months, 'hfa');
    if (!isset($who_data_cache[$hfa_file])) {
        $who_data_cache[$hfa_file] = loadCsvData($hfa_file, 0, $hfa_value_columns);
    }
    $hfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
    $hfa_values = getNutritionalValues($who_data_cache[$hfa_file], $hfa_key, ['SSt', 'St', 'Normal']);
    if ($hfa_values && $height > 0) {
        $hfa_z = calculateZScore($height, $hfa_values['L'], $hfa_values['M'], $hfa_values['S']);
        $record['hfa_z'] = $hfa_z !== null ? round($hfa_z, 2) : 'N/A';
        if ($hfa_z === null) {
            $record['hfa_status'] = 'N/A';
        } else {
            if ($hfa_z < -3) {
                $record['hfa_status'] = 'Severely Stunted';
            } elseif ($hfa_z < -2) {
                $record['hfa_status'] = 'Stunted';
            } else {
                $record['hfa_status'] = 'Normal';
            }
        }
    } else {
        $record['hfa_status'] = 'N/A';
        $record['hfa_z'] = 'N/A';
    }

    // WFL/H Status
    $wflh_file = getDatasetForAge($gender, $age_in_months, 'wflh');
    unset($who_data_cache[$wflh_file]);
    $who_data_cache[$wflh_file] = loadCsvData($wflh_file, 0, $wflh_value_columns);
    $height_key = $height;
    $height_keys = array_keys($who_data_cache[$wflh_file]);
    if (empty($height_keys)) {
        $record['wflh_status'] = 'N/A';
        $record['wflh_z'] = 'N/A';
    } else {
        $max_height = max($height_keys);
        $min_height = min($height_keys);
        if ($height_key < $min_height || $height_key > $max_height) {
            $height_key = $height < $min_height ? $min_height : $max_height;
        }
        $wflh_values = getNutritionalValues($who_data_cache[$wflh_file], $height_key, ['SW', 'MW', 'Normal', 'OW', 'Ob']);
        if ($wflh_values && $weight > 0) {
            $wflh_z = calculateZScore($weight, $wflh_values['L'], $wflh_values['M'], $wflh_values['S']);
            $record['wflh_z'] = $wflh_z !== null ? round($wflh_z, 2) : 'N/A';
            if ($wflh_z === null) {
                $record['wflh_status'] = 'N/A';
            } else {
                if ($wflh_z < -3) {
                    $record['wflh_status'] = 'Severely Wasted';
                } elseif ($wflh_z < -2) {
                    $record['wflh_status'] = 'Moderately Wasted';
                } elseif ($wflh_z <= 2) {
                    $record['wflh_status'] = 'Normal';
                } elseif ($wflh_z <= 3) {
                    $record['wflh_status'] = 'Overweight';
                } else {
                    $record['wflh_status'] = 'Obese';
                }
            }
        } else {
            $record['wflh_status'] = 'N/A';
            $record['wflh_z'] = 'N/A';
        }
    }
}
unset($record);

// Group records by purok
$purok_records = [];
if ($role_id == 1 || $role_id == 2 || $role_id == 4) {
    foreach ($filtered_records as $record) {
        $purok = $record['purok'];
        $purok_records[$purok][] = $record;
    }
} else {
    $purok_records['My Records'] = $filtered_records;
}

// Calculate statistics for dashboard
$total_children = count($filtered_records);
$severely_underweight = count(array_filter($filtered_records, fn($r) => $r['wfa_status'] === 'Severely Underweight'));
$underweight = count(array_filter($filtered_records, fn($r) => $r['wfa_status'] === 'Underweight'));
$stunted = count(array_filter($filtered_records, fn($r) => in_array($r['hfa_status'], ['Stunted', 'Severely Stunted'])));
$wasted = count(array_filter($filtered_records, fn($r) => in_array($r['wflh_status'], ['Severely Wasted', 'Moderately Wasted'])));
$overweight = count(array_filter($filtered_records, fn($r) => in_array($r['wflh_status'], ['Overweight', 'Obese'])));
$normal = count(array_filter($filtered_records, fn($r) => $r['wfa_status'] === 'Normal' && $r['hfa_status'] === 'Normal' && $r['wflh_status'] === 'Normal'));

$malnutrition_rate = $total_children > 0 ? round((($severely_underweight + $underweight + $stunted + $wasted) / $total_children) * 100, 1) : 0;

// Fetch address details
$stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
$address = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle PDF download - KEPT EXACTLY AS YOUR ORIGINAL
if (isset($_POST['download']) && isset($_POST['report_type'])) {
    require_once 'vendor/autoload.php';
    $report_type = $_POST['report_type'];
    ob_start();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Nutritional Status Tool</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            @page { size: legal landscape; margin: 10mm; }
            .paper { width: 100%; padding: 12px; box-sizing: border-box; }
            .header { text-align: left; margin-bottom: 10px; }
            .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
            .meta { text-align: center; font-size: 12px; color: #444; }
            .address-details span { display: inline-block; margin: 0 15px; }
            .address-details { text-align: center;}
            table { width: 100%; border-collapse: collapse; font-size: 9px; }
            th, td { border: 1px solid #000; padding: 4px; text-align: center; }
            th { background: #f2f2f2; }
            .col-seq { width: 40px; }
            .col-purok { width: 60px; }
            .col-mother { width: 100px; }
            .col-name { width: 100px; }
            .col-ip { width: 50px; }
            .col-sex { width: 40px; }
            .col-date { width: 80px; }
            .col-weight { width: 60px; }
            .col-height { width: 60px; }
            .col-age { width: 60px; }
            .col-status { width: 80px; }
            .col-zscore { width: 60px; }
        </style>
    </head>
    <body>
        <?php if ($report_type === 'total' || $report_type === 'all') { ?>
            <div class="paper">
                <div class="header">
                    <div class="title">
                        <h1>Nutritional Status Tool</h1>
                        <div class="meta">Year: <?php echo date('Y'); ?> | Form 1B</div>
                    </div>
                    <div class="address-details">
                        <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? 'N/A'); ?></span>
                        <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? 'N/A'); ?></span>
                        <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? 'N/A'); ?></span>
                        <span><strong>Purok:</strong> <?php echo htmlspecialchars($user_purok ?? 'All'); ?></span>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th class="col-seq">Child Seq.</th>
                            <th class="col-purok">Address</th>
                            <th class="col-mother">Mother/Caregiver</th>
                            <th class="col-name">Child's Full Name</th>
                            <th class="col-ip">IP Group?</th>
                            <th class="col-sex">Sex</th>
                            <th class="col-date">Date of Birth</th>
                            <th class="col-date">Date Last Measured</th>
                            <th class="col-weight">Weight (kg)</th>
                            <th class="col-height">Height (cm)</th>
                            <th class="col-age">Age in Months</th>
                            <th class="col-status">WFA Status</th>
                            <th class="col-status">HFA Status</th>
                            <th class="col-status">WFL/H Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        foreach ($filtered_records as $record) {
                            echo '<tr>
                                <td>' . $count++ . '</td>
                                <td>' . htmlspecialchars($record['purok']) . '</td>
                                <td>' . htmlspecialchars($record['mother_caregiver']) . '</td>
                                <td>' . htmlspecialchars($record['full_name']) . '</td>
                                <td>NO</td>
                                <td>' . htmlspecialchars($record['gender'][0]) . '</td>
                                <td>' . (!empty($record['birthdate']) ? date('m/d/y', strtotime($record['birthdate'])) : '') . '</td>
                                <td>' . (!empty($record['measurement_date']) ? date('m/d/y', strtotime($record['measurement_date'])) : '') . '</td>
                                <td>' . htmlspecialchars($record['weight']) . '</td>
                                <td>' . htmlspecialchars($record['height']) . '</td>
                                <td>' . htmlspecialchars($record['age_in_months']) . '</td>
                                <td>' . htmlspecialchars($record['wfa_status']) . '</td>
                                <td>' . htmlspecialchars($record['hfa_status']) . '</td>
                                <td>' . htmlspecialchars($record['wflh_status']) . '</td>
                            </tr>';
                        }
                        for ($i = count($filtered_records); $i < 50; $i++) {
                            echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
        <?php if ($report_type === 'per_purok' || $report_type === 'all') {
            foreach ($purok_records as $purok => $records) { ?>
                <div class="paper">
                    <div class="header">
                        <div class="title">
                            <h1>Nutritional Status Tool</h1>
                            <div class="meta">Year: <?php echo date('Y'); ?> | Form 1B</div>
                        </div>
                        <div class="address-details">
                            <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? 'N/A'); ?></span>
                            <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? 'N/A'); ?></span>
                            <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? 'N/A'); ?></span>
                            <span><strong>Purok:</strong> <?php echo htmlspecialchars($purok); ?></span>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th class="col-seq">Child Seq.</th>
                                <th class="col-purok">Address</th>
                                <th class="col-mother">Mother/Caregiver</th>
                                <th class="col-name">Child's Full Name</th>
                                <th class="col-ip">IP Group?</th>
                                <th class="col-sex">Sex</th>
                                <th class="col-date">Date of Birth</th>
                                <th class="col-date">Date Last Measured</th>
                                <th class="col-weight">Weight (kg)</th>
                                <th class="col-height">Height (cm)</th>
                                <th class="col-age">Age in Months</th>
                                <th class="col-status">WFA Status</th>
                                <th class="col-status">HFA Status</th>
                                <th class="col-status">WFL/H Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 1;
                            foreach ($records as $record) {
                                echo '<tr>
                                    <td>' . $count++ . '</td>
                                    <td>' . htmlspecialchars($record['purok']) . '</td>
                                    <td>' . htmlspecialchars($record['mother_caregiver']) . '</td>
                                    <td>' . htmlspecialchars($record['full_name']) . '</td>
                                    <td>NO</td>
                                    <td>' . htmlspecialchars($record['gender'][0]) . '</td>
                                    <td>' . (!empty($record['birthdate']) ? date('m/d/y', strtotime($record['birthdate'])) : '') . '</td>
                                    <td>' . (!empty($record['measurement_date']) ? date('m/d/y', strtotime($record['measurement_date'])) : '') . '</td>
                                    <td>' . htmlspecialchars($record['weight']) . '</td>
                                    <td>' . htmlspecialchars($record['height']) . '</td>
                                    <td>' . htmlspecialchars($record['age_in_months']) . '</td>
                                    <td>' . htmlspecialchars($record['wfa_status']) . '</td>
                                    <td>' . htmlspecialchars($record['hfa_status']) . '</td>
                                    <td>' . htmlspecialchars($record['wflh_status']) . '</td>
                                </tr>';
                            }
                            for ($i = count($records); $i < 50; $i++) {
                                echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        <?php } ?>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', [10, 10, 10, 10]);
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Nutritional_Status_Tool_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Nutritional Status Tool</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Poppins', sans-serif;
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
        .alert-danger-custom {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        .alert-warning-custom {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
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
            border: 1px solid #e2e8f0;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
            padding: 8px;
            font-size: 0.85rem;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .table td {
            font-size: 0.85rem;
            padding: 8px;
            text-align: center;
        }
        .badge-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-severely-underweight, .status-severely-wasted, .status-severely-stunted {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-underweight, .status-moderately-wasted, .status-stunted {
            background: #fed7aa;
            color: #9a3412;
        }
        .status-normal {
            background: #d1fae5;
            color: #065f46;
        }
        .status-overweight {
            background: #fef3c7;
            color: #92400e;
        }
        .status-obese {
            background: #fecaca;
            color: #991b1b;
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
        .form-control {
            display: inline-block;
            width: auto;
            vertical-align: middle;
        }
        .download-btn { margin-bottom: 15px; }
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
            .card { margin-bottom: 15px; margin-left: 0; margin-right: 0; }
            .table-responsive { overflow-x: auto; }
            .tab-content { font-size: 12px; }
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
            .menu-toggle { display: none;}
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
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Nutritional Status Tool
                    </div>
                    <div class="card-body p-3">
                        <!-- Key Statistics Dashboard -->
                        <div class="row mb-3 stats-container">
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Total Children</div>
                                    <div class="stat-value"><?php echo number_format($total_children); ?></div>
                                    <small class="text-muted">Aged 1-59 months</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Malnutrition Rate</div>
                                    <div class="stat-value <?php echo $malnutrition_rate > 15 ? 'text-danger' : ''; ?>"><?php echo $malnutrition_rate; ?>%</div>
                                    <small class="text-muted">Combined UW+Stunted+Wasted</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Underweight</div>
                                    <div class="stat-value text-warning"><?php echo $underweight + $severely_underweight; ?></div>
                                    <small class="text-muted"><?php echo $total_children > 0 ? round((($underweight + $severely_underweight) / $total_children) * 100, 1) : 0; ?>% prevalence</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Normal Weight</div>
                                    <div class="stat-value text-success"><?php echo $normal; ?></div>
                                    <small class="text-muted"><?php echo $total_children > 0 ? round(($normal / $total_children) * 100, 1) : 0; ?>% healthy</small>
                                </div>
                            </div>
                        </div>

                        <!-- Health Alerts -->
                        <?php if ($severely_underweight > 0): ?>
                            <div class="alert-danger-custom alert-custom">
                                <i class="fas fa-exclamation-circle"></i> <strong>Critical Alert:</strong> <?php echo $severely_underweight; ?> children are severely underweight. Immediate intervention required.
                            </div>
                        <?php endif; ?>
                        <?php if ($wasted > 0): ?>
                            <div class="alert-warning-custom alert-custom">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> <?php echo $wasted; ?> children show signs of wasting (acute malnutrition).
                            </div>
                        <?php endif; ?>
                        <?php if ($stunted > 0): ?>
                            <div class="alert-warning-custom alert-custom">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Chronic Issue:</strong> <?php echo $stunted; ?> children are stunted (chronic malnutrition).
                            </div>
                        <?php endif; ?>
                        <?php if ($malnutrition_rate < 10): ?>
                            <div class="alert-success-custom alert-custom">
                                <i class="fas fa-check-circle"></i> <strong>Good Performance:</strong> Malnutrition rate is below 10%. Keep up the good work!
                            </div>
                        <?php endif; ?>

                        <h5 class="mt-3">Year: <?php echo date('Y'); ?> | Barangay: <?php echo htmlspecialchars($address['barangay'] ?? 'N/A'); ?> | Municipality: <?php echo htmlspecialchars($address['municipality'] ?? 'N/A'); ?> | Province: <?php echo htmlspecialchars($address['province'] ?? 'N/A'); ?></h5>

                        <form method="post" class="download-btn">
                            <select class="form-control" name="report_type">
                                <option value="total">Total Barangay</option>
                                <option value="per_purok">Per Purok</option>
                                <option value="all">All</option>
                            </select>
                            <button type="submit" name="download" class="btn btn-primary ml-2">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </form>

                        <?php if ($role_id == 1 || $role_id == 2 || $role_id == 4): ?>
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <?php
                                $first = true;
                                foreach (array_keys($purok_records) as $purok) {
                                    $safe_id = 'purok-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($purok));
                                    $purok_count = count($purok_records[$purok]);
                                    echo "<li class='nav-item'>
                                        <a class='nav-link " . ($first ? 'active' : '') . "' id='{$safe_id}-tab' data-toggle='tab' href='#{$safe_id}' role='tab' aria-controls='{$safe_id}' aria-selected='" . ($first ? 'true' : 'false') . "'>
                                            <i class='fas fa-map-marker-alt'></i> $purok <span class='badge badge-secondary ml-1'>$purok_count</span>
                                        </a>
                                    </li>";
                                    $first = false;
                                }
                                ?>
                            </ul>
                            <div class="tab-content" id="purokTabContent">
                                <?php
                                $first = true;
                                foreach ($purok_records as $purok => $records) {
                                    $safe_id = 'purok-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($purok));
                                    ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="<?php echo $safe_id; ?>" role="tabpanel" aria-labelledby="<?php echo $safe_id; ?>-tab">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Seq.</th>
                                                        <th>Address</th>
                                                        <th>Mother/Caregiver</th>
                                                        <th>Child's Full Name</th>
                                                        <th>Sex</th>
                                                        <th>DOB</th>
                                                        <th>Last Measured</th>
                                                        <th>Weight</th>
                                                        <th>Height</th>
                                                        <th>Age (mos)</th>
                                                        <th>WFA</th>
                                                        <th>HFA</th>
                                                        <th>WFL/H</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $count = 1;
                                                    foreach ($records as $record) {
                                                        $wfa_class = 'status-' . strtolower(str_replace(' ', '-', $record['wfa_status']));
                                                        $hfa_class = 'status-' . strtolower(str_replace(' ', '-', $record['hfa_status']));
                                                        $wflh_class = 'status-' . strtolower(str_replace(' ', '-', $record['wflh_status']));
                                                        
                                                        echo '<tr>
                                                            <td>' . $count++ . '</td>
                                                            <td>' . htmlspecialchars($record['purok']) . '</td>
                                                            <td>' . htmlspecialchars($record['mother_caregiver']) . '</td>
                                                            <td>' . htmlspecialchars($record['full_name']) . '</td>
                                                            <td>' . htmlspecialchars($record['gender'][0]) . '</td>
                                                            <td>' . (!empty($record['birthdate']) ? date('m/d/y', strtotime($record['birthdate'])) : '') . '</td>
                                                            <td>' . (!empty($record['measurement_date']) ? date('m/d/y', strtotime($record['measurement_date'])) : '') . '</td>
                                                            <td>' . htmlspecialchars($record['weight']) . ' kg</td>
                                                            <td>' . htmlspecialchars($record['height']) . ' cm</td>
                                                            <td>' . htmlspecialchars($record['age_in_months']) . '</td>
                                                            <td><span class="badge-status ' . $wfa_class . '">' . htmlspecialchars($record['wfa_status']) . '</span></td>
                                                            <td><span class="badge-status ' . $hfa_class . '">' . htmlspecialchars($record['hfa_status']) . '</span></td>
                                                            <td><span class="badge-status ' . $wflh_class . '">' . htmlspecialchars($record['wflh_status']) . '</span></td>
                                                        </tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php $first = false; ?>
                                <?php } ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Seq.</th>
                                            <th>Address</th>
                                            <th>Mother/Caregiver</th>
                                            <th>Child's Full Name</th>
                                            <th>Sex</th>
                                            <th>DOB</th>
                                            <th>Last Measured</th>
                                            <th>Weight</th>
                                            <th>Height</th>
                                            <th>Age (mos)</th>
                                            <th>WFA</th>
                                            <th>HFA</th>
                                            <th>WFL/H</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 1;
                                        foreach ($filtered_records as $record) {
                                            $wfa_class = 'status-' . strtolower(str_replace(' ', '-', $record['wfa_status']));
                                            $hfa_class = 'status-' . strtolower(str_replace(' ', '-', $record['hfa_status']));
                                            $wflh_class = 'status-' . strtolower(str_replace(' ', '-', $record['wflh_status']));
                                            
                                            echo '<tr>
                                                <td>' . $count++ . '</td>
                                                <td>' . htmlspecialchars($record['purok']) . '</td>
                                                <td>' . htmlspecialchars($record['mother_caregiver']) . '</td>
                                                <td>' . htmlspecialchars($record['full_name']) . '</td>
                                                <td>' . htmlspecialchars($record['gender'][0]) . '</td>
                                                <td>' . (!empty($record['birthdate']) ? date('m/d/y', strtotime($record['birthdate'])) : '') . '</td>
                                                <td>' . (!empty($record['measurement_date']) ? date('m/d/y', strtotime($record['measurement_date'])) : '') . '</td>
                                                <td>' . htmlspecialchars($record['weight']) . ' kg</td>
                                                <td>' . htmlspecialchars($record['height']) . ' cm</td>
                                                <td>' . htmlspecialchars($record['age_in_months']) . '</td>
                                                <td><span class="badge-status ' . $wfa_class . '">' . htmlspecialchars($record['wfa_status']) . '</span></td>
                                                <td><span class="badge-status ' . $hfa_class . '">' . htmlspecialchars($record['hfa_status']) . '</span></td>
                                                <td><span class="badge-status ' . $wflh_class . '">' . htmlspecialchars($record['wflh_status']) . '</span></td>
                                            </tr>';
                                        }
                                        ?>
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
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
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
            if (window.innerWidth > 768) {
                content.css('margin-left', sidebar.hasClass('open') ? '250px' : '0');
            } else {
                content.css('margin-left', '0');
            }
        }
    </script>
</body>
</html>
