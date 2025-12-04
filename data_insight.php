<?php
session_start();
require_once 'db_connect.php';

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

// Fetch user's purok for role_id 2
$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

// Date range filter with validation
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Validate dates
if (strtotime($date_from) > strtotime($date_to)) {
    $date_from = date('Y-m-d', strtotime('-6 months'));
    $date_to = date('Y-m-d');
}

// Current date for age calculation (dynamic)
$current_date = new DateTime('now');

// Growth calculation functions
function loadCsvData($filename, $keyColumn, $valueColumns) {
    if (!file_exists($filename)) {
        error_log("CSV file not found: $filename");
        return [];
    }
    $rows = array_map('str_getcsv', file($filename));
    if (empty($rows)) {
        error_log("CSV file is empty: $filename");
        return [];
    }
    $headers = array_shift($rows);
    $data = [];
    foreach ($rows as $index => $row) {
        if (!isset($row[$keyColumn]) || count($row) < 4) {
            continue;
        }
        $key = floatval($row[$keyColumn]);
        $values = [
            'L' => isset($row[1]) ? floatval($row[1]) : 0,
            'M' => isset($row[2]) ? floatval($row[2]) : 0,
            'S' => isset($row[3]) ? floatval($row[3]) : 0
        ];
        if ($values['M'] <= 0 || $values['S'] <= 0) {
            continue;
        }
        foreach ($valueColumns as $status => $index) {
            $values[$status] = isset($row[$index]) ? floatval($row[$index]) : null;
        }
        $data[$key] = $values;
    }
    return $data;
}

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
        return "who_datasets/wfl_{$gender_prefix}_0-to-5-years_zscores.csv";
    }
    return null;
}

function interpolate($x0, $y0, $x1, $y1, $x) {
    if ($x0 == $x1) return $y0;
    return $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
}

function calculateZScore($x, $L, $M, $S) {
    if ($x <= 0 || $M <= 0 || $S <= 0) {
        return null;
    }
    if (abs($L) < 0.0001) {
        return log($x / $M) / $S;
    }
    $z = (pow($x / $M, $L) - 1) / ($L * $S);
    return $z;
}

function getNutritionalValues($data, $key, $statuses) {
    $keys = array_keys($data);
    if (empty($keys)) {
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
        return null;
    }
    $result = [];
    $fields = array_merge(['L', 'M', 'S'], $statuses);
    foreach ($fields as $field) {
        if (!isset($data[$lower_key][$field]) || !isset($data[$upper_key][$field])) {
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

$wfa_value_columns = [
    'SUW'    => 4,
    'UW'     => 5,
    'Normal' => 7,
    'OW'     => 9
];

$hfa_value_columns = [
    'SSt'    => 4,
    'St'     => 5,
    'Normal' => 7
];

$wflh_value_columns = [
    'SW'     => 4,
    'MW'     => 5,
    'Normal' => 7,
    'OW'     => 9,
    'Ob'     => 10
];

$who_data_cache = [];

// Fetch child and infant records with date filtering
if ($role_id == 3) {
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.gender, p.birthdate, chr.weight, chr.height, chr.measurement_date, a.purok, a.address_id
        FROM child_record chr
        JOIN records r ON r.records_id = chr.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.user_id = ?
        AND chr.measurement_date BETWEEN ? AND ?
    ");
    $stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
} else {
    $purok_condition = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.gender, p.birthdate, chr.weight, chr.height, chr.measurement_date, a.purok, a.address_id
        FROM child_record chr
        JOIN records r ON r.records_id = chr.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE chr.measurement_date BETWEEN ? AND ?
        $purok_condition
        ORDER BY a.purok, p.full_name
    ");
    $params = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) {
        $params[] = $user_purok;
    }
    $stmt->execute($params);
}
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter records for age 0–59 months and calculate nutritional status
$filtered_records = [];
foreach ($records as $record) {
    if ($record['birthdate']) {
        $birthdate = new DateTime($record['birthdate']);
        $age_in_days = $current_date->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;
        if ($age_in_months >= 0 && $age_in_months <= 59) {
            $record['age_in_days'] = $age_in_days;
            $record['age_in_months'] = $age_in_months;
            $filtered_records[] = $record;
        }
    }
}

// Apply nutritional status calculations
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
    if (!isset($who_data_cache[$wflh_file])) {
        $who_data_cache[$wflh_file] = loadCsvData($wflh_file, 0, $wflh_value_columns);
    }
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

// Aggregate nutritional status by purok
$purok_stats = [];
foreach ($filtered_records as $record) {
    $purok = $role_id == 3 ? 'My Records' : ($record['purok'] ?? 'Unknown');
    if (!isset($purok_stats[$purok])) {
        $purok_stats[$purok] = [
            'wfa' => ['Severely Underweight' => 0, 'Underweight' => 0, 'Normal' => 0, 'Overweight' => 0],
            'hfa' => ['Severely Stunted' => 0, 'Stunted' => 0, 'Normal' => 0],
            'wflh' => ['Severely Wasted' => 0, 'Moderately Wasted' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0],
            'total' => 0
        ];
    }
    if ($record['wfa_status'] != 'N/A') {
        $purok_stats[$purok]['wfa'][$record['wfa_status']]++;
    }
    if ($record['hfa_status'] != 'N/A') {
        $purok_stats[$purok]['hfa'][$record['hfa_status']]++;
    }
    if ($record['wflh_status'] != 'N/A') {
        $purok_stats[$purok]['wflh'][$record['wflh_status']]++;
    }
    $purok_stats[$purok]['total']++;
}

// Build date filter for subsequent queries
$date_filter = "";
$date_params = [];
if ($role_id == 2 && $user_purok) {
    $date_filter = "AND a.purok = ?";
    $date_params = [$user_purok];
} elseif ($role_id == 3) {
    $date_filter = "AND r.user_id = ?";
    $date_params = [$_SESSION['user_id']];
}

// Fetch prenatal checkup compliance
$stmt = $pdo->prepare("
    SELECT purok, COUNT(*) as women
    FROM (
        SELECT a.purok, prr.pregnancy_record_id
        FROM prenatal pre
        JOIN pregnancy_record prr ON pre.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE prr.pregnancy_period = 'Prenatal'
        AND prr.created_at BETWEEN ? AND ?
        " . ($role_id == 2 && $user_purok ? "AND a.purok = ?" : "") . "
        " . ($role_id == 3 ? "AND r.user_id = ?" : "") . "
        GROUP BY prr.pregnancy_record_id, a.purok
        HAVING COUNT(pre.prenatal_id) >= 1
    ) sub
    GROUP BY purok
");
$params = [$date_from, $date_to];
if ($role_id == 2 && $user_purok) {
    $params[] = $user_purok;
} elseif ($role_id == 3) {
    $params[] = $_SESSION['user_id'];
}
$stmt->execute($params);
$prenatal_compliance = $stmt->fetchAll(PDO::FETCH_ASSOC);
$prenatal_stats = [];
foreach ($prenatal_compliance as $data) {
    $prenatal_stats[$data['purok']] = $data['women'];
}

// Fetch additional data for insights with date filtering
$where_clause = "WHERE 1=1 $date_filter";
$params = $date_params;

// Infant breastfeeding data
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_infants,
           SUM(CASE WHEN ir.exclusive_breastfeeding = 'Y' THEN 1 ELSE 0 END) as exclusive_count
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
    WHERE DATEDIFF(CURDATE(), p.birthdate) BETWEEN 29 AND 365
    AND cr.measurement_date BETWEEN ? AND ?
    $date_filter
");
$exec_params = [$date_from, $date_to];
if (!empty($params)) {
    $exec_params = array_merge($exec_params, $params);
}
$stmt->execute($exec_params);
$infant_breastfeeding_data = $stmt->fetch(PDO::FETCH_ASSOC);
$infant_breastfeeding = [
    'exclusive_pct' => $infant_breastfeeding_data && $infant_breastfeeding_data['total_infants'] > 0 
        ? round(($infant_breastfeeding_data['exclusive_count'] / $infant_breastfeeding_data['total_infants']) * 100, 1) 
        : 0
];

// Infant immunization data
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT cr.records_id) as total_infants,
        COUNT(DISTINCT CASE 
            WHEN (SELECT COUNT(DISTINCT i2.immunization_type) 
                  FROM immunization i2 
                  WHERE i2.immunization_id = cr.immunization_id 
                  AND i2.immunization_type IN ('BCG', 'HepB-BD', 'DTP-Hib-HepB1', 'DTP-Hib-HepB2', 'DTP-Hib-HepB3', 'OPV1', 'OPV2', 'OPV3', 'PCV1', 'PCV2', 'PCV3', 'IPV')) >= 12 
            THEN cr.records_id 
        END) as full_count
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
    WHERE DATEDIFF(CURDATE(), p.birthdate) BETWEEN 29 AND 365
    AND cr.measurement_date BETWEEN ? AND ?
    $date_filter
");
$stmt->execute($exec_params);
$infant_immunization_data = $stmt->fetch(PDO::FETCH_ASSOC);
$infant_immunization = [
    'full_pct' => $infant_immunization_data && $infant_immunization_data['total_infants'] > 0 
        ? round(($infant_immunization_data['full_count'] / $infant_immunization_data['total_infants']) * 100, 1) 
        : 0
];

// Child immunization data  
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT cr.records_id) as total_children,
        COUNT(DISTINCT CASE 
            WHEN (SELECT COUNT(DISTINCT i2.immunization_type) 
                  FROM immunization i2 
                  WHERE i2.immunization_id = cr.immunization_id 
                  AND i2.immunization_type IN ('MCV1', 'MCV2')) >= 2 
            THEN cr.records_id 
        END) as complete_count
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE DATEDIFF(CURDATE(), p.birthdate) / 30.4375 BETWEEN 12 AND 71
    AND cr.measurement_date BETWEEN ? AND ?
    $date_filter
");
$stmt->execute($exec_params);
$child_immunization_data = $stmt->fetch(PDO::FETCH_ASSOC);
$child_immunization = [
    'complete_pct' => $child_immunization_data && $child_immunization_data['total_children'] > 0 
        ? round(($child_immunization_data['complete_count'] / $child_immunization_data['total_children']) * 100, 1) 
        : 0
];

// Child health risks
$stmt = $pdo->prepare("
    SELECT cr.risk_observed as health_condition, COUNT(*) as count
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE cr.risk_observed IS NOT NULL
    AND cr.risk_observed != ''
    AND DATEDIFF(CURDATE(), p.birthdate) / 30.4375 BETWEEN 0 AND 71
    AND cr.measurement_date BETWEEN ? AND ?
    $date_filter
    GROUP BY cr.risk_observed
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute($exec_params);
$top_child_risks_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$top_child_risks = [];
foreach ($top_child_risks_data as $risk) {
    $top_child_risks[$risk['health_condition']] = $risk['count'];
}

// Prenatal care data
$stmt = $pdo->prepare("
    SELECT AVG(checkup_count) as avg_checkups,
           AVG(first_prenatal_month) as avg_first_month
    FROM (
        SELECT prr.pregnancy_record_id, 
               COUNT(pre.prenatal_id) as checkup_count, 
               COALESCE(MIN(pre.months_pregnancy), 0) as first_prenatal_month
        FROM prenatal pre
        JOIN pregnancy_record prr ON pre.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE prr.pregnancy_period = 'Prenatal'
        AND prr.created_at BETWEEN ? AND ?
        $date_filter
        GROUP BY prr.pregnancy_record_id
    ) sub
");
$stmt->execute($exec_params);
$prenatal_care_data = $stmt->fetch(PDO::FETCH_ASSOC);
$prenatal_care = [
    'avg_checkups' => $prenatal_care_data ? round($prenatal_care_data['avg_checkups'] ?? 0, 1) : 0,
    'avg_first_month' => $prenatal_care_data ? round($prenatal_care_data['avg_first_month'] ?? 0, 1) : 0
];

// Birth plan percentage
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT prr.pregnancy_record_id) as total_pregnancies,
           SUM(CASE WHEN pre.birth_plan = 'Y' THEN 1 ELSE 0 END) as birth_plan_count
    FROM pregnancy_record prr
    JOIN records r ON prr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN prenatal pre ON prr.pregnancy_record_id = pre.pregnancy_record_id
    WHERE prr.pregnancy_period = 'Prenatal'
    AND prr.created_at BETWEEN ? AND ?
    $date_filter
");
$stmt->execute($exec_params);
$birth_plan_data = $stmt->fetch(PDO::FETCH_ASSOC);
$birth_plan_pct = $birth_plan_data && $birth_plan_data['total_pregnancies'] > 0 
    ? round(($birth_plan_data['birth_plan_count'] / $birth_plan_data['total_pregnancies']) * 100, 1) 
    : 0;

// PhilHealth percentage
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_pregnancies,
           SUM(CASE WHEN p.philhealth_number IS NOT NULL AND p.philhealth_number != '' THEN 1 ELSE 0 END) as philhealth_count
    FROM pregnancy_record prr
    JOIN records r ON prr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE prr.pregnancy_period = 'Prenatal'
    AND prr.created_at BETWEEN ? AND ?
    $date_filter
");
$stmt->execute($exec_params);
$philhealth_data = $stmt->fetch(PDO::FETCH_ASSOC);
$philhealth_pct = $philhealth_data && $philhealth_data['total_pregnancies'] > 0 
    ? round(($philhealth_data['philhealth_count'] / $philhealth_data['total_pregnancies']) * 100, 1) 
    : 0;

// Postnatal compliance percentage
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_postnatal,
           SUM(CASE WHEN checkup_count >= 2 THEN 1 ELSE 0 END) as compliant_count
    FROM (
        SELECT prr.pregnancy_record_id, COUNT(pn.postnatal_id) as checkup_count
        FROM postnatal pn
        JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE prr.pregnancy_period = 'Postnatal'
        AND pn.date_delivered BETWEEN ? AND ?
        $date_filter
        GROUP BY prr.pregnancy_record_id
    ) sub
");
$stmt->execute($exec_params);
$postnatal_data = $stmt->fetch(PDO::FETCH_ASSOC);
$postnatal_compliance_pct = $postnatal_data && $postnatal_data['total_postnatal'] > 0 
    ? round(($postnatal_data['compliant_count'] / $postnatal_data['total_postnatal']) * 100, 1) 
    : 0;

// Family planning intent percentage
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_fp,
           SUM(CASE WHEN pn.family_planning_intent = 'Y' THEN 1 ELSE 0 END) as intent_count
    FROM postnatal pn
    JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
    JOIN records r ON prr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE prr.pregnancy_period = 'Postnatal'
    AND pn.date_delivered BETWEEN ? AND ?
    $date_filter
");
$stmt->execute($exec_params);
$fp_data = $stmt->fetch(PDO::FETCH_ASSOC);
$fp_intent_pct = $fp_data && $fp_data['total_fp'] > 0 
    ? round(($fp_data['intent_count'] / $fp_data['total_fp']) * 100, 1) 
    : 0;

// Health conditions percentage
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_persons,
           SUM(CASE WHEN p.health_condition IN (
                'HPN (High Blood Pressure)',
                'CC (Coughing 2 weeks or more)',
                'M (Malaria)',
                'PWD (Person With Disability)',
                'DM (Diabetic)',
                'CA (Cancer)',
                'B (Bukol)',
                'DG (Dengue)',
                'F (Flu)'
            ) THEN 1 ELSE 0 END) as condition_count
    FROM person p
    JOIN records r ON p.person_id = r.person_id
    JOIN address a ON p.address_id = a.address_id
    $where_clause
");
$stmt->execute($params);
$hpn_data = $stmt->fetch(PDO::FETCH_ASSOC);
$health_condition_pct = $hpn_data && $hpn_data['total_persons'] > 0 
    ? round(($hpn_data['condition_count'] / $hpn_data['total_persons']) * 100, 1) 
    : 0;

// Top medications
$stmt = $pdo->prepare("
    SELECT m.medication_name, COUNT(*) as count
    FROM medication m
    JOIN senior_medication sm ON m.medication_id = sm.medication_id
    JOIN senior_record sr ON sm.senior_record_id = sr.senior_record_id
    JOIN records r ON sr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    WHERE sr.bp_date_taken BETWEEN ? AND ?
    $date_filter
    GROUP BY m.medication_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute($exec_params);
$top_meds_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$top_meds = [];
foreach ($top_meds_data as $med) {
    $top_meds[$med['medication_name']] = $med['count'];
}

// Polypharmacy count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as polypharmacy_count
    FROM (
        SELECT r.records_id
        FROM medication m
        JOIN senior_medication sm ON m.medication_id = sm.medication_id
        JOIN senior_record sr ON sm.senior_record_id = sr.senior_record_id
        JOIN records r ON sr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE sr.bp_date_taken BETWEEN ? AND ?
        $date_filter
        GROUP BY r.records_id
        HAVING COUNT(DISTINCT m.medication_id) >= 5
    ) sub
");
$stmt->execute($exec_params);
$polypharmacy_data = $stmt->fetch(PDO::FETCH_ASSOC);
$polypharmacy_count = $polypharmacy_data ? ($polypharmacy_data['polypharmacy_count'] ?? 0) : 0;

// Missing birth plan
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT prr.pregnancy_record_id) as missing_count
    FROM pregnancy_record prr
    JOIN records r ON prr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN prenatal pre ON prr.pregnancy_record_id = pre.pregnancy_record_id
    WHERE prr.pregnancy_period = 'Prenatal'
    AND prr.created_at BETWEEN ? AND ?
    AND (pre.birth_plan IS NULL OR pre.birth_plan = 'N' OR pre.prenatal_id IS NULL)
    $date_filter
");
$stmt->execute($exec_params);
$missing_birth_plan_data = $stmt->fetch(PDO::FETCH_ASSOC);
$missing_birth_plan = $missing_birth_plan_data ? ($missing_birth_plan_data['missing_count'] ?? 0) : 0;

// Missing immunization
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT cr.records_id) as missing_count
    FROM child_record cr
    JOIN records r ON cr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN immunization i ON cr.immunization_id = i.immunization_id
    WHERE DATEDIFF(CURDATE(), p.birthdate) / 30.4375 BETWEEN 12 AND 71
    AND cr.measurement_date BETWEEN ? AND ?
    $date_filter
    AND (i.immunization_id IS NULL OR (
        SELECT COUNT(DISTINCT i2.immunization_type) 
        FROM immunization i2 
        WHERE i2.immunization_id = cr.immunization_id 
        AND i2.immunization_type IN ('MCV1', 'MCV2')
    ) < 2)
");
$stmt->execute($exec_params);
$missing_immunization_data = $stmt->fetch(PDO::FETCH_ASSOC);
$missing_immunization = $missing_immunization_data ? ($missing_immunization_data['missing_count'] ?? 0) : 0;

// Maternal-infant complete care
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT prr.pregnancy_record_id) as complete_count
    FROM pregnancy_record prr
    JOIN records r ON prr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    JOIN postnatal pn ON prr.pregnancy_record_id = pn.pregnancy_record_id
    WHERE prr.pregnancy_period = 'Postnatal'
    AND pn.date_delivered BETWEEN ? AND ?
    AND pn.postnatal_id IS NOT NULL
    $date_filter
");
$stmt->execute($exec_params);
$maternal_infant_data = $stmt->fetch(PDO::FETCH_ASSOC);
$maternal_infant_complete = $maternal_infant_data ? ($maternal_infant_data['complete_count'] ?? 0) : 0;

// Generate dynamic interpretations
function generateWFAInterpretation($purok_stats) {
    if (empty($purok_stats)) {
        return "No weight-for-age data available. Ensure child records are complete to assess nutritional status.";
    }
    
    $interpretation = "Weight-for-age analysis reveals nutritional patterns across monitored areas. ";
    $high_risk_puroks = [];
    $overweight_puroks = [];
    $total_records = 0;
    
    foreach ($purok_stats as $purok => $stats) {
        $total = $stats['total'] ?: 1;
        $underweight_percent = ($stats['wfa']['Severely Underweight'] + $stats['wfa']['Underweight']) / $total * 100;
        $overweight_percent = $stats['wfa']['Overweight'] / $total * 100;
        $total_records += $stats['total'];
        
        if ($underweight_percent > 20) {
            $high_risk_puroks[] = "$purok (" . round($underweight_percent, 1) . "% underweight)";
        }
        if ($overweight_percent > 10) {
            $overweight_puroks[] = "$purok (" . round($overweight_percent, 1) . "% overweight)";
        }
    }
    
    if ($total_records == 0) {
        return "No child weight data recorded in the selected period.";
    }
    
    if (!empty($high_risk_puroks)) {
        $interpretation .= "<strong>Critical Alert:</strong> High malnutrition risk in " . implode(", ", $high_risk_puroks) . ". Immediate nutrition intervention programs recommended. ";
    }
    if (!empty($overweight_puroks)) {
        $interpretation .= "<strong>Action Required:</strong> Elevated overweight prevalence in " . implode(", ", $overweight_puroks) . ". Implement dietary education and physical activity programs. ";
    }
    if (empty($high_risk_puroks) && empty($overweight_puroks)) {
        $interpretation .= "<strong>Status: Healthy.</strong> Nutritional indicators within acceptable ranges. Maintain regular monitoring and preventive care.";
    }
    
    return $interpretation;
}

function generateHFAInterpretation($purok_stats) {
    if (empty($purok_stats)) {
        return "No height-for-age data available. Ensure child records include height measurements.";
    }
    
    $interpretation = "Height-for-age analysis identifies growth patterns and chronic malnutrition. ";
    $stunted_puroks = [];
    $total_records = 0;
    
    foreach ($purok_stats as $purok => $stats) {
        $total = $stats['total'] ?: 1;
        $stunted_percent = ($stats['hfa']['Severely Stunted'] + $stats['hfa']['Stunted']) / $total * 100;
        $total_records += $stats['total'];
        
        if ($stunted_percent > 20) {
            $stunted_puroks[] = "$purok (" . round($stunted_percent, 1) . "% stunted)";
        }
    }
    
    if ($total_records == 0) {
        return "No child height data recorded in the selected period.";
    }
    
    if (!empty($stunted_puroks)) {
        $interpretation .= "<strong>Critical Alert:</strong> High stunting rates in " . implode(", ", $stunted_puroks) . " indicate chronic malnutrition. Requires long-term nutritional supplementation, growth monitoring, and family nutrition education.";
    } else {
        $interpretation .= "<strong>Status: Healthy.</strong> Growth patterns show adequate linear development across monitored areas. Continue regular growth assessments.";
    }
    
    return $interpretation;
}

function generateWFLHInterpretation($purok_stats) {
    if (empty($purok_stats)) {
        return "No weight-for-length/height data available. Ensure both weight and height measurements are recorded.";
    }
    
    $interpretation = "Weight-for-length/height analysis detects acute malnutrition and obesity trends. ";
    $wasted_puroks = [];
    $obese_puroks = [];
    $total_records = 0;
    
    foreach ($purok_stats as $purok => $stats) {
        $total = $stats['total'] ?: 1;
        $wasted_percent = ($stats['wflh']['Severely Wasted'] + $stats['wflh']['Moderately Wasted']) / $total * 100;
        $obese_percent = ($stats['wflh']['Overweight'] + $stats['wflh']['Obese']) / $total * 100;
        $total_records += $stats['total'];
        
        if ($wasted_percent > 10) {
            $wasted_puroks[] = "$purok (" . round($wasted_percent, 1) . "% wasted)";
        }
        if ($obese_percent > 10) {
            $obese_puroks[] = "$purok (" . round($obese_percent, 1) . "% overweight/obese)";
        }
    }
    
    if ($total_records == 0) {
        return "No weight-for-length/height data recorded in the selected period.";
    }
    
    if (!empty($wasted_puroks)) {
        $interpretation .= "<strong>Emergency Alert:</strong> Acute malnutrition detected in " . implode(", ", $wasted_puroks) . ". Requires immediate therapeutic feeding programs and medical intervention. ";
    }
    if (!empty($obese_puroks)) {
        $interpretation .= "<strong>Action Required:</strong> Rising childhood obesity in " . implode(", ", $obese_puroks) . ". Implement healthy lifestyle programs and dietary counseling. ";
    }
    if (empty($wasted_puroks) && empty($obese_puroks)) {
        $interpretation .= "<strong>Status: Healthy.</strong> Acute nutritional status within acceptable ranges. Maintain preventive health measures.";
    }
    
    return $interpretation;
}

function generatePrenatalInterpretation($prenatal_stats) {
    if (empty($prenatal_stats)) {
        return "No prenatal checkup data available. Ensure prenatal records are updated and women are accessing care.";
    }
    
    $interpretation = "Prenatal care compliance shows adherence to recommended 1+ antenatal visits. ";
    $low_compliance_puroks = [];
    $total_women = 0;
    
    foreach ($prenatal_stats as $purok => $checkups) {
        $total_women += $checkups;
        if ($checkups < 5) {
            $low_compliance_puroks[] = "$purok ($checkups women)";
        }
    }
    
    if ($total_women == 0) {
        return "No prenatal checkup records found in the selected period.";
    }
    
    if (!empty($low_compliance_puroks)) {
        $interpretation .= "<strong>Action Required:</strong> Low prenatal care utilization in " . implode(", ", $low_compliance_puroks) . " suggests access barriers. Investigate transportation, scheduling, awareness issues. Intensify outreach and mobile clinic services.";
    } else {
        $interpretation .= "<strong>Status: Excellent.</strong> Strong prenatal care compliance indicates good healthcare access and maternal health awareness. Continue promoting regular visits.";
    }
    
    return $interpretation;
}

function interpretBreastfeeding($data) {
    $exclusive_pct = $data['exclusive_pct'] ?? 0;
    if ($exclusive_pct >= 70) return "<span class='text-success'><strong>Excellent</strong></span> ({$exclusive_pct}%): High exclusive breastfeeding rates ensure optimal infant nutrition and immunity.";
    if ($exclusive_pct >= 40) return "<span class='text-warning'><strong>Moderate</strong></span> ({$exclusive_pct}%): Room for improvement. Enhance lactation support and breastfeeding education.";
    return "<span class='text-danger'><strong>Critical</strong></span> ({$exclusive_pct}%): Low rates require urgent intervention—establish lactation counseling and support groups.";
}

function interpretImmunization($data) {
    $full_pct = $data['full_pct'] ?? 0;
    if ($full_pct >= 90) return "<span class='text-success'><strong>Excellent</strong></span> ({$full_pct}%): High coverage provides strong herd immunity.";
    if ($full_pct >= 70) return "<span class='text-warning'><strong>Moderate</strong></span> ({$full_pct}%): Increase outreach to improve vaccine completion rates.";
    return "<span class='text-danger'><strong>Critical</strong></span> ({$full_pct}%): Low coverage increases disease outbreak risk. Urgent immunization campaigns needed.";
}

function interpretChildImmunization($data) {
    $complete_pct = $data['complete_pct'] ?? 0;
    if ($complete_pct >= 90) return "<span class='text-success'><strong>Excellent</strong></span> ({$complete_pct}%): Strong protection against vaccine-preventable diseases.";
    if ($complete_pct >= 70) return "<span class='text-warning'><strong>Moderate</strong></span> ({$complete_pct}%): Target under-immunized children for catch-up services.";
    return "<span class='text-danger'><strong>Critical</strong></span> ({$complete_pct}%): Vulnerability to disease outbreaks. Implement catch-up campaigns immediately.";
}

function interpretChildRisks($risks) {
    if (empty($risks)) return "No significant child health risks reported in the selected period.";
    $top = array_slice($risks, 0, 1, true);
    $risk = key($top);
    $count = current($top);
    return "<strong>Priority Risk:</strong> $risk ($count cases). Focus prevention and treatment efforts on this condition. Monitor for clustering patterns.";
}

function interpretPrenatal($data) {
    $avg_checkups = $data['avg_checkups'] ?? 0;
    if ($avg_checkups >= 4) return "<span class='text-success'><strong>Compliant</strong></span> (avg {$avg_checkups} visits): Meeting WHO recommendations.";
    if ($avg_checkups >= 2) return "<span class='text-warning'><strong>Partial</strong></span> (avg {$avg_checkups} visits): Below target. Strengthen follow-up systems.";
    return "<span class='text-danger'><strong>Poor</strong></span> (avg {$avg_checkups} visits): Critical gap. Urgent outreach needed to improve access and awareness.";
}

function interpretBirthPlan($pct) {
    if ($pct >= 80) return "<span class='text-success'><strong>Comprehensive</strong></span> ({$pct}%): Excellent documentation supports safe delivery planning.";
    if ($pct >= 50) return "<span class='text-warning'><strong>Moderate</strong></span> ({$pct}%): Improve birth preparedness counseling and documentation.";
    return "<span class='text-danger'><strong>Poor</strong></span> ({$pct}%): High risk of unprepared deliveries. Strengthen prenatal education and planning.";
}

function interpretPhilhealth($pct) {
    if ($pct >= 80) return "<span class='text-success'><strong>High Coverage</strong></span> ({$pct}%): Good financial protection for maternal care.";
    if ($pct >= 50) return "<span class='text-warning'><strong>Moderate</strong></span> ({$pct}%): Promote PhilHealth enrollment during prenatal visits.";
    return "<span class='text-danger'><strong>Low Coverage</strong></span> ({$pct}%): Financial barriers to care. Urgent PhilHealth enrollment campaigns needed.";
}

function interpretPostnatal($pct) {
    if ($pct >= 80) return "<span class='text-success'><strong>High Compliance</strong></span> ({$pct}%): Excellent postpartum follow-up reduces complications.";
    if ($pct >= 50) return "<span class='text-warning'><strong>Moderate</strong></span> ({$pct}%): Strengthen postnatal follow-up protocols and reminders.";
    return "<span class='text-danger'><strong>Poor</strong></span> ({$pct}%): Critical gap. Missing follow-up increases maternal and infant mortality risk.";
}

function interpretFamilyPlanning($pct) {
    if ($pct >= 80) return "<span class='text-success'><strong>High Intent</strong></span> ({$pct}%): Strong family planning acceptance.";
    if ($pct >= 50) return "<span class='text-warning'><strong>Moderate</strong></span> ({$pct}%): Increase counseling on FP methods and benefits.";
    return "<span class='text-danger'><strong>Low Intent</strong></span> ({$pct}%): Limited FP acceptance. Address misconceptions and access barriers.";
}

function interpretHealthConditions($pct) {
    if ($pct >= 30) return "<span class='text-danger'><strong>High Prevalence</strong></span> ({$pct}%): Significant chronic and infectious disease burden. Strengthen disease management programs.";
    if ($pct >= 10) return "<span class='text-warning'><strong>Moderate</strong></span> ({$pct}%): Monitor disease trends and enhance preventive care.";
    return "<span class='text-success'><strong>Low</strong></span> ({$pct}%): Healthy population with minimal disease burden.";
}

function interpretPolypharmacy($count) {
    if ($count > 10) return "<span class='text-danger'><strong>Significant Risk</strong></span> ({$count} cases): High polypharmacy prevalence increases adverse drug interactions. Review and optimize medication regimens.";
    if ($count > 0) return "<span class='text-warning'><strong>Monitor</strong></span> ({$count} cases): Some polypharmacy detected. Regular medication reviews recommended.";
    return "<span class='text-success'><strong>Low Risk</strong></span>: Minimal polypharmacy cases detected.";
}

function interpretDocumentation($birth, $immun) {
    if ($birth == 0 && $immun == 0) return "<span class='text-success'><strong>Complete</strong></span>: All records properly documented.";
    $gaps = [];
    if ($birth > 0) $gaps[] = "$birth pregnancies without birth plans";
    if ($immun > 0) $gaps[] = "$immun children with incomplete immunization records";
    return "<span class='text-warning'><strong>Gaps Identified:</strong></span> " . implode(", ", $gaps) . ". Improve record-keeping systems.";
}

function interpretMaternalInfant($count) {
    if ($count > 10) return "<span class='text-success'><strong>Strong Linkage</strong></span> ({$count} pairs): Excellent continuity of care from pregnancy through infancy.";
    if ($count > 0) return "<span class='text-warning'><strong>Partial Linkage</strong></span> ({$count} pairs): Some continuity but room for improvement.";
    return "<span class='text-danger'><strong>Weak Linkage</strong></span>: Poor care continuity. Strengthen integrated maternal-child health services.";
}

function interpretFeedingToLinkage($feeding, $linkage) {
    $exclusive = $feeding['exclusive_pct'] ?? 0;
    if ($exclusive >= 70 && $linkage > 5) return "<span class='text-success'><strong>Excellent Synergy</strong></span>: High breastfeeding ({$exclusive}%) correlates with strong care continuity ({$linkage} complete pairs). Optimal maternal-infant health outcomes.";
    if ($exclusive >= 40 && $linkage > 0) return "<span class='text-warning'><strong>Moderate Synergy</strong></span>: Breastfeeding ({$exclusive}%) and care continuity ({$linkage} pairs) show room for improvement. Strengthen integrated services.";
    return "<span class='text-danger'><strong>Weak Synergy</strong></span>: Low breastfeeding ({$exclusive}%) and care continuity ({$linkage} pairs) indicate fragmented services. Urgent integration needed.";
}

$wfa_interpretation = generateWFAInterpretation($purok_stats);
$hfa_interpretation = generateHFAInterpretation($purok_stats);
$wflh_interpretation = generateWFLHInterpretation($purok_stats);
$prenatal_interpretation = generatePrenatalInterpretation($prenatal_stats);

// Calculate summary statistics
$total_children_screened = array_sum(array_column($purok_stats, 'total'));
$total_at_risk = 0;
foreach ($purok_stats as $stats) {
    $total_at_risk += $stats['wfa']['Severely Underweight'] + $stats['wfa']['Underweight'];
    $total_at_risk += $stats['hfa']['Severely Stunted'] + $stats['hfa']['Stunted'];
    $total_at_risk += $stats['wflh']['Severely Wasted'] + $stats['wflh']['Moderately Wasted'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Health Data Insights & Analytics</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        position: sticky;
        top: 0;
        z-index: 1050;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: 80px;
    }
    .navbar-brand, .nav-link { color: #fff !important; font-weight: 500; }
    .navbar-brand:hover, .nav-link:hover { color: #e2e8f0 !important; }
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
    .sidebar.open {
        transform: translateX(250px);
    }
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
    }
    
    /* Summary Cards */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    .summary-card.primary { border-left-color: #4299e1; }
    .summary-card.success { border-left-color: #48bb78; }
    .summary-card.warning { border-left-color: #f6ad55; }
    .summary-card.danger { border-left-color: #e53e3e; }
    .summary-card .number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .summary-card .label {
        font-size: 0.9rem;
        color: #718096;
        font-weight: 500;
    }
    
    /* Date Filter */
    .date-filter {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .date-filter .form-control {
        border-radius: 8px;
        border: 1px solid #d1d5db;
    }
    .date-filter .btn-primary {
        background: #2b6cb0;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .card {
        background: rgba(255, 255, 255, 0.97);
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        border: none;
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
    .card-header {
        background: linear-gradient(135deg, #2b6cb0, #4299e1);
        color: #fff;
        padding: 15px 20px;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .card-header i {
        margin-right: 10px;
    }
    .card-body {
        padding: 20px;
    }
    .chart-container {
        position: relative;
        width: 100%;
        height: 400px;
    }
    .interpretation {
        margin-top: 15px;
        padding: 15px;
        background: #f7fafc;
        border-left: 4px solid #4299e1;
        border-radius: 8px;
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .interpretation strong {
        color: #2d3748;
    }
    
    .menu-toggle {
        display: none;
        color: #fff;
        font-size: 1.5rem;
        position: absolute;
        left: 15px;
        top: 25px;
        cursor: pointer;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
    }
    .export-buttons .btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    @media (max-width: 768px) {
        .navbar-brand {
            font-size: 1rem;
            padding-left: 55px;
        }
        .menu-toggle {
            display: block;
        }
        .sidebar {
            width: 220px;
            left: -220px;
        }
        .sidebar.open {
            transform: translateX(220px);
        }
        .content {
            padding: 15px;
            margin-left: 0;
        }
        .chart-container {
            height: 300px;
        }
        .summary-cards {
            grid-template-columns: 1fr;
        }
        .summary-card .number {
            font-size: 2rem;
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
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1030;
        display: none;
    }
    .sidebar-overlay.active {
        display: block;
    }
    
    .text-success { color: #48bb78 !important; }
    .text-warning { color: #f6ad55 !important; }
    .text-danger { color: #e53e3e !important; }
    
    .badge-info {
        background: #4299e1;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .loading {
        text-align: center;
        padding: 40px;
        color: #718096;
    }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line"></i> Health Data Insights & Analytics</h2>
                    <?php if ($role_id == 2): ?>
                        <span class="badge badge-info">Purok: <?php echo htmlspecialchars($user_purok); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Date Filter -->
                <div class="date-filter">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="date_from"><i class="fas fa-calendar-alt"></i> From:</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_to"><i class="fas fa-calendar-alt"></i> To:</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Refresh Insights
                            </button>
                        </div>
                    </form>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-info-circle"></i> Analysis period: <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>
                    </small>
                </div>
                
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card primary">
                        <div class="number"><?php echo number_format($total_children_screened); ?></div>
                        <div class="label"><i class="fas fa-child"></i> Children Screened</div>
                    </div>
                    <div class="summary-card danger">
                        <div class="number"><?php echo number_format($total_at_risk); ?></div>
                        <div class="label"><i class="fas fa-exclamation-triangle"></i> At Nutritional Risk</div>
                    </div>
                    <div class="summary-card success">
                        <div class="number"><?php echo round($infant_breastfeeding['exclusive_pct'], 1); ?>%</div>
                        <div class="label"><i class="fas fa-baby"></i> Exclusive Breastfeeding</div>
                    </div>
                    <div class="summary-card warning">
                        <div class="number"><?php echo $missing_birth_plan + $missing_immunization; ?></div>
                        <div class="label"><i class="fas fa-clipboard-list"></i> Documentation Gaps</div>
                    </div>
                </div>
                
                <!-- Weight-for-Age Chart -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-weight"></i> Nutritional Status by Purok (Weight-for-Age)</span>
                        <div class="export-buttons">
                            <button class="btn btn-sm btn-light" onclick="exportChartData('wfaChart', 'Weight-for-Age')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="wfaChart"></canvas>
                        </div>
                        <div class="interpretation">
                            <p><strong><i class="fas fa-lightbulb"></i> Interpretation:</strong> <?php echo $wfa_interpretation; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Height-for-Age Chart -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-ruler-vertical"></i> Height-for-Age Status by Purok</span>
                        <div class="export-buttons">
                            <button class="btn btn-sm btn-light" onclick="exportChartData('hfaChart', 'Height-for-Age')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="hfaChart"></canvas>
                        </div>
                        <div class="interpretation">
                            <p><strong><i class="fas fa-lightbulb"></i> Interpretation:</strong> <?php echo $hfa_interpretation; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Weight-for-Length/Height Chart -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-balance-scale"></i> Weight-for-Length/Height Status by Purok</span>
                        <div class="export-buttons">
                            <button class="btn btn-sm btn-light" onclick="exportChartData('wflhChart', 'Weight-for-Length-Height')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="wflhChart"></canvas>
                        </div>
                        <div class="interpretation">
                            <p><strong><i class="fas fa-lightbulb"></i> Interpretation:</strong> <?php echo $wflh_interpretation; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Prenatal Checkup Compliance Chart -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-user-nurse"></i> Prenatal Checkup Compliance (≥1 Visits)</span>
                        <div class="export-buttons">
                            <button class="btn btn-sm btn-light" onclick="exportChartData('prenatalChart', 'Prenatal-Checkup')">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="prenatalChart"></canvas>
                        </div>
                        <div class="interpretation">
                            <p><strong><i class="fas fa-lightbulb"></i> Interpretation:</strong> <?php echo $prenatal_interpretation; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Two-Column Charts -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-baby-carriage"></i> Infant Feeding Practices</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="breastfeedingChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><strong><i class="fas fa-info-circle"></i> Status:</strong> <?php echo interpretBreastfeeding($infant_breastfeeding); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-syringe"></i> Immunization Coverage</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="immunizationChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><strong><i class="fas fa-baby"></i> Infant:</strong> <?php echo interpretImmunization($infant_immunization); ?></p>
                                    <p><strong><i class="fas fa-child"></i> Child:</strong> <?php echo interpretChildImmunization($child_immunization); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-stethoscope"></i> Child Health Risks</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="childRisksChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><?php echo interpretChildRisks($top_child_risks); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-heartbeat"></i> Prenatal & Postnatal Care Indicators</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="prenatalPostnatalChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><strong>Prenatal:</strong> <?php echo interpretPrenatal($prenatal_care); ?></p>
                                    <p><strong>Birth Plan:</strong> <?php echo interpretBirthPlan($birth_plan_pct); ?></p>
                                    <p><strong>PhilHealth:</strong> <?php echo interpretPhilhealth($philhealth_pct); ?></p>
                                    <p><strong>Postnatal:</strong> <?php echo interpretPostnatal($postnatal_compliance_pct); ?></p>
                                    <p><strong>Family Planning:</strong> <?php echo interpretFamilyPlanning($fp_intent_pct); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-pills"></i> Health Conditions & Polypharmacy</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="healthConditionsChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><?php echo interpretHealthConditions($health_condition_pct); ?></p>
                                    <p><?php echo interpretPolypharmacy($polypharmacy_count); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-link"></i> Documentation & Maternal-Infant Linkage</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="documentationChart"></canvas>
                                </div>
                                <div class="interpretation">
                                    <p><?php echo interpretDocumentation($missing_birth_plan, $missing_immunization); ?></p>
                                    <p><?php echo interpretMaternalInfant($maternal_infant_complete); ?></p>
                                    <p><?php echo interpretFeedingToLinkage($infant_breastfeeding, $maternal_infant_complete); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-overlay"></div>
    </div>

    <script>
        // Chart.js Global Configuration
        Chart.defaults.font.family = "'Poppins', sans-serif";
        Chart.defaults.color = '#2d3748';
        
        // Weight-for-Age Chart
        new Chart(document.getElementById('wfaChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($purok_stats)); ?>,
                datasets: [
                    {
                        label: 'Severely Underweight',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wfa']['Severely Underweight'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#e53e3e',
                        borderRadius: 6
                    },
                    {
                        label: 'Underweight',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wfa']['Underweight'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#f6ad55',
                        borderRadius: 6
                    },
                    {
                        label: 'Normal',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wfa']['Normal'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#48bb78',
                        borderRadius: 6
                    },
                    {
                        label: 'Overweight',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wfa']['Overweight'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#4299e1',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#e2e8f0' } }
                },
                plugins: {
                    title: { display: true, text: 'Weight-for-Age Nutritional Status', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { position: 'top', labels: { padding: 15, usePointStyle: true } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 }
                    }
                }
            }
        });

        // Height-for-Age Chart
        new Chart(document.getElementById('hfaChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($purok_stats)); ?>,
                datasets: [
                    {
                        label: 'Severely Stunted',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['hfa']['Severely Stunted'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#e53e3e',
                        borderRadius: 6
                    },
                    {
                        label: 'Stunted',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['hfa']['Stunted'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#f6ad55',
                        borderRadius: 6
                    },
                    {
                        label: 'Normal',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['hfa']['Normal'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#48bb78',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#e2e8f0' } }
                },
                plugins: {
                    title: { display: true, text: 'Height-for-Age Growth Status', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { position: 'top', labels: { padding: 15, usePointStyle: true } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12
                    }
                }
            }
        });

        // Weight-for-Length/Height Chart
        new Chart(document.getElementById('wflhChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($purok_stats)); ?>,
                datasets: [
                    {
                        label: 'Severely Wasted',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wflh']['Severely Wasted'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#e53e3e',
                        borderRadius: 6
                    },
                    {
                        label: 'Moderately Wasted',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wflh']['Moderately Wasted'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#f6ad55',
                        borderRadius: 6
                    },
                    {
                        label: 'Normal',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wflh']['Normal'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#48bb78',
                        borderRadius: 6
                    },
                    {
                        label: 'Overweight',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wflh']['Overweight'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#4299e1',
                        borderRadius: 6
                    },
                    {
                        label: 'Obese',
                        data: <?php echo json_encode(array_map(function($stats) { return $stats['wflh']['Obese'] ?? 0; }, array_values($purok_stats))); ?>,
                        backgroundColor: '#9f7aea',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#e2e8f0' } }
                },
                plugins: {
                    title: { display: true, text: 'Weight-for-Length/Height Status', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { position: 'top', labels: { padding: 15, usePointStyle: true } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12
                    }
                }
            }
        });

        // Prenatal Checkup Chart
        new Chart(document.getElementById('prenatalChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($prenatal_stats)); ?>,
                datasets: [{
                    label: 'Women with ≥1 Prenatal Checkups',
                    data: <?php echo json_encode(array_values($prenatal_stats)); ?>,
                    backgroundColor: '#48bb78',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    title: { display: true, text: 'Prenatal Care Compliance by Area', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12
                    }
                }
            }
        });

        // Breastfeeding Chart
        new Chart(document.getElementById('breastfeedingChart'), {
            type: 'doughnut',
            data: {
                labels: ['Exclusive Breastfeeding', 'Not Exclusive'],
                datasets: [{
                    data: [
                        <?php echo $infant_breastfeeding['exclusive_pct']; ?>,
                        <?php echo 100 - $infant_breastfeeding['exclusive_pct']; ?>
                    ],
                    backgroundColor: ['#48bb78', '#e53e3e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Exclusive Breastfeeding Rate', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { position: 'bottom', labels: { padding: 15 } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + Math.round(context.parsed) + '%';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Immunization Chart
        new Chart(document.getElementById('immunizationChart'), {
            type: 'pie',
            data: {
                labels: ['Fully Immunized', 'Incomplete'],
                datasets: [{
                    data: [
                        <?php echo $infant_immunization['full_pct']; ?>,
                        <?php echo 100 - $infant_immunization['full_pct']; ?>
                    ],
                    backgroundColor: ['#4299e1', '#e53e3e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Infant Immunization Coverage', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { position: 'bottom', labels: { padding: 15 } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + Math.round(context.parsed) + '%';
                            }
                        }
                    }
                }
            }
        });

        // Child Health Risks Chart
        new Chart(document.getElementById('childRisksChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($top_child_risks)); ?>,
                datasets: [{
                    label: 'Cases',
                    data: <?php echo json_encode(array_values($top_child_risks)); ?>,
                    backgroundColor: '#f6ad55',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Top Child Health Risks', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Prenatal & Postnatal Care Chart
        new Chart(document.getElementById('prenatalPostnatalChart'), {
            type: 'bar',
            data: {
                labels: ['Prenatal\nCheckups', 'Birth\nPlan', 'PhilHealth', 'Postnatal\nCheckups', 'Family\nPlanning'],
                datasets: [{
                    label: 'Percent',
                    data: [
                        <?php echo round($prenatal_care['avg_checkups'], 1) * 25; ?>,
                        <?php echo $birth_plan_pct; ?>,
                        <?php echo $philhealth_pct; ?>,
                        <?php echo $postnatal_compliance_pct; ?>,
                        <?php echo $fp_intent_pct; ?>
                    ],
                    backgroundColor: ['#4299e1', '#48bb78', '#f6ad55', '#9f7aea', '#e53e3e'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Maternal Care Indicators', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Health Conditions Chart
        new Chart(document.getElementById('healthConditionsChart'), {
            type: 'bar',
            data: {
                labels: ['Health Conditions (%)', 'Polypharmacy (cases)'],
                datasets: [{
                    label: 'Value',
                    data: [
                        <?php echo $health_condition_pct; ?>,
                        <?php echo $polypharmacy_count; ?>
                    ],
                    backgroundColor: ['#e53e3e', '#4299e1'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Health Conditions & Polypharmacy', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Documentation Chart
        new Chart(document.getElementById('documentationChart'), {
            type: 'bar',
            data: {
                labels: ['Missing Birth\nPlan', 'Missing\nImmunization', 'Complete\nMaternal-Infant\nCare'],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php echo $missing_birth_plan; ?>,
                        <?php echo $missing_immunization; ?>,
                        <?php echo $maternal_infant_complete; ?>
                    ],
                    backgroundColor: ['#e53e3e', '#f6ad55', '#48bb78'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Documentation & Care Linkage', font: { size: 16, weight: 'bold' }, padding: 20 },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Export function
        function exportChartData(chartId, chartName) {
            const chart = Chart.getChart(chartId);
            const url = chart.toBase64Image();
            const a = document.createElement('a');
            a.href = url;
            a.download = `${chartName}_${new Date().toISOString().slice(0,10)}.png`;
            a.click();
        }

        // Sidebar toggle
        $(document).ready(function() {
            $('.menu-toggle').on('click', function() {
                $('.sidebar').toggleClass('open');
                $('.content').toggleClass('with-sidebar');
                $('.sidebar-overlay').fadeToggle(200);
            });

            $('.sidebar-overlay').on('click', function() {
                $('.sidebar').removeClass('open');
                $('.content').removeClass('with-sidebar');
                $(this).fadeOut(200);
            });

            $('.sidebar').on('click', function(e) {
                e.stopPropagation();
            });

            // Responsive chart resizing
            window.addEventListener('resize', function() {
                Chart.helpers.each(Chart.instances, function(instance) {
                    instance.resize();
                });
            });
        });
    </script>
</body>
</html>
