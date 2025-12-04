<?php
session_start();
require_once 'db_connect.php';

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// Redirect to index.php if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$role_id = $user['role_id'];


// ========== MAP SETTINGS (PER USER) ==========
$stmtSettings = $pdo->prepare("SELECT * FROM custom_map_settings WHERE user_id = ?");
$stmtSettings->execute([$_SESSION['user_id']]);
$rows = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);

// Fallback to admin (user_id = 1) if no settings for this user
if (empty($rows)) {
    $stmtSettings = $pdo->prepare("SELECT * FROM custom_map_settings WHERE user_id = 1");
    $stmtSettings->execute();
    $rows = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);
}

$mapSettings = [];
foreach ($rows as $row) {
    $mapSettings[$row['metric_key']] = $row;
}

// Determine assigned purok for BHW Staff (role_id == 2)
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

// Fetch household heads
$sql = "SELECT p.person_id, p.household_number, p.full_name, a.purok 
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        WHERE p.relationship_type = 'Head' 
        AND p.household_number IS NOT NULL 
        AND p.household_number != 0";
$params = [];
if ($role_id == 2 && $assigned_purok) {
    $sql .= " AND a.purok = :assigned_purok";
    $params['assigned_purok'] = $assigned_purok;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$heads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build households array with flags
$households = [];
$puroks = ['All', 'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4A', 'Purok 5', 'Purok 4B', 'Purok 6', 'Purok 7'];

// WHO LMS helper functions and caches (reuse from your existing code)
$wfa_value_columns = ['SUW' => 4, 'UW' => 5, 'Normal' => 7, 'OW' => 9];
$hfa_value_columns = ['SSt' => 4, 'St' => 5, 'Normal' => 7];
$wflh_value_columns = ['SW' => 4, 'MW' => 5, 'Normal' => 7, 'OW' => 9, 'Ob' => 10];
$who_data_cache_hh = [];
$current_date_hh = new DateTime('now');

foreach ($heads as $h) {
    $stmt = $pdo->prepare("SELECT full_name, relationship_type, health_condition 
                           FROM person 
                           WHERE household_number = ? AND person_id != ?");
    $stmt->execute([$h['household_number'], $h['person_id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hhNo = $h['household_number'];

    $flags = [
        'underweight_rate'             => false,
        'stunted_rate'                 => false,
        'wasted_rate'                  => false,
        'immunization_coverage'        => false,
        'exclusive_breastfeeding_rate' => false,
        'low_birth_weight_rate'        => false,
        'prenatal_coverage'            => false,
        'home_birth_rate'              => false,
        'fp_intent_rate'               => false,
        'hypertensive_rate'            => false
    ];

    // CHILD HEALTH with WHO z-scores
    $stmtChild = $pdo->prepare("
        SELECT p.person_id, p.gender, p.birthdate,
               cr.weight, cr.height, cr.immunization_status
        FROM child_record cr
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p  ON r.person_id = p.person_id
        WHERE r.record_type = 'child_record'
          AND cr.child_type = 'Child'
          AND p.age BETWEEN 1 AND 6
          AND p.household_number = ?
    ");
    $stmtChild->execute([$hhNo]);
    $childrenHH = $stmtChild->fetchAll(PDO::FETCH_ASSOC);

    foreach ($childrenHH as $child) {
        if (!$child['birthdate']) continue;

        $birthdate = new DateTime($child['birthdate']);
        $age_in_days = $current_date_hh->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;

        if ($age_in_months < 0 || $age_in_months > 59) continue;

        $gender = $child['gender'] === 'Male' ? 'M' : 'F';
        $weight = floatval($child['weight']);
        $height = floatval($child['height']);
        $age_in_weeks = $age_in_days / 7;

        // WFA (Underweight)
        $wfa_file = getDatasetForAge($gender, $age_in_months, 'wfa');
        if (!isset($who_data_cache_hh[$wfa_file])) {
            $who_data_cache_hh[$wfa_file] = loadCsvData($wfa_file, 0, $wfa_value_columns);
        }
        $wfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $wfa_values = getNutritionalValues($who_data_cache_hh[$wfa_file], $wfa_key, ['SUW', 'UW', 'Normal', 'OW']);
        if ($wfa_values && $weight > 0) {
            $wfa_z = calculateZScore($weight, $wfa_values['L'], $wfa_values['M'], $wfa_values['S']);
            if ($wfa_z !== null && $wfa_z < -2) {
                $flags['underweight_rate'] = true;
            }
        }

        // HFA (Stunted)
        $hfa_file = getDatasetForAge($gender, $age_in_months, 'hfa');
        if (!isset($who_data_cache_hh[$hfa_file])) {
            $who_data_cache_hh[$hfa_file] = loadCsvData($hfa_file, 0, $hfa_value_columns);
        }
        $hfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $hfa_values = getNutritionalValues($who_data_cache_hh[$hfa_file], $hfa_key, ['SSt', 'St', 'Normal']);
        if ($hfa_values && $height > 0) {
            $hfa_z = calculateZScore($height, $hfa_values['L'], $hfa_values['M'], $hfa_values['S']);
            if ($hfa_z !== null && $hfa_z < -2) {
                $flags['stunted_rate'] = true;
            }
        }

        // WFLH (Wasted)
        $wflh_file = getDatasetForAge($gender, $age_in_months, 'wflh');
        if (!isset($who_data_cache_hh[$wflh_file])) {
            $who_data_cache_hh[$wflh_file] = loadCsvData($wflh_file, 0, $wflh_value_columns);
        }
        $height_key = $height;
        $height_keys = array_keys($who_data_cache_hh[$wflh_file]);
        if (!empty($height_keys)) {
            $max_height = max($height_keys);
            $min_height = min($height_keys);
            if ($height_key < $min_height || $height_key > $max_height) {
                $height_key = $height < $min_height ? $min_height : $max_height;
            }
            $wflh_values = getNutritionalValues($who_data_cache_hh[$wflh_file], $height_key, ['SW', 'MW', 'Normal', 'OW', 'Ob']);
            if ($wflh_values && $weight > 0) {
                $wflh_z = calculateZScore($weight, $wflh_values['L'], $wflh_values['M'], $wflh_values['S']);
                if ($wflh_z !== null && $wflh_z < -2) {
                    $flags['wasted_rate'] = true;
                }
            }
        }

        // Immunization
        $immun_status = $child['immunization_status'] ?? '';
        if (strpos($immun_status, 'MMR') === false ||
            strpos($immun_status, 'Vitamin A') === false ||
            strpos($immun_status, 'FIC') === false) {
            $flags['immunization_coverage'] = true;
        }
    }

    // INFANT HEALTH
    $stmtInfant = $pdo->prepare("
        SELECT ir.exclusive_breastfeeding, cr.weight
        FROM infant_record ir
        JOIN child_record cr ON ir.child_record_id = cr.child_record_id
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p  ON r.person_id = p.person_id
        WHERE r.record_type LIKE '%infant_record%'
          AND p.household_number = ?
    ");
    $stmtInfant->execute([$hhNo]);
    $infantsHH = $stmtInfant->fetchAll(PDO::FETCH_ASSOC);

    foreach ($infantsHH as $infant) {
        if ($infant['exclusive_breastfeeding'] === 'Y') {
            $flags['exclusive_breastfeeding_rate'] = true;
        }
        if (floatval($infant['weight']) < 2.5) {
            $flags['low_birth_weight_rate'] = true;
        }
    }

    // PRENATAL
    $stmtPrenatal = $pdo->prepare("
        SELECT pn.checkup_date, pn.risk_observed
        FROM prenatal pn
        JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p  ON r.person_id = p.person_id
        WHERE r.record_type LIKE '%prenatal%'
          AND prr.pregnancy_period = 'Prenatal'
          AND p.household_number = ?
    ");
    $stmtPrenatal->execute([$hhNo]);
    $prenatalHH = $stmtPrenatal->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prenatalHH as $row) {
        if (!empty($row['checkup_date']) && strpos($row['checkup_date'], 'None') === false) {
            $flags['prenatal_coverage'] = true;
        }
    }

    // POSTNATAL
    $stmtPost = $pdo->prepare("
        SELECT delivery_location, postnatal_checkups, family_planning_intent
        FROM postnatal pn
        JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p  ON r.person_id = p.person_id
        WHERE r.record_type LIKE '%postnatal%'
          AND p.household_number = ?
    ");
    $stmtPost->execute([$hhNo]);
    $postHH = $stmtPost->fetchAll(PDO::FETCH_ASSOC);

    foreach ($postHH as $row) {
        if (stripos($row['delivery_location'], 'Bahay') !== false) {
            $flags['home_birth_rate'] = true;
        }
        if ($row['family_planning_intent'] === 'Y') {
            $flags['fp_intent_rate'] = true;
        }
    }

    // SENIOR HEALTH
    $stmtSenior = $pdo->prepare("
        SELECT p.health_condition
        FROM senior_record sr
        JOIN records r ON sr.records_id = r.records_id
        JOIN person p  ON r.person_id = p.person_id
        WHERE p.age >= 60
          AND p.household_number = ?
    ");
    $stmtSenior->execute([$hhNo]);
    $seniorsHH = $stmtSenior->fetchAll(PDO::FETCH_ASSOC);

    foreach ($seniorsHH as $row) {
        if (stripos($row['health_condition'], 'HPN') !== false) {
            $flags['hypertensive_rate'] = true;
        }
    }

    $households[] = [
        "household_number" => $hhNo,
        "head_name"        => $h['full_name'],
        "purok"            => $h['purok'],
        "members"          => $members,
        "flags"            => $flags
    ];
}

// ==================== WHO LMS Z-SCORE FUNCTIONS ====================

function loadCsvData($filename, $keyColumn, $valueColumns) {
    if (!file_exists($filename)) {
        error_log("CSV file not found: $filename");
        return [];
    }
    $rows = array_map('str_getcsv', file($filename));
    if (empty($rows)) return [];
    $headers = array_shift($rows);
    $data = [];
    foreach ($rows as $row) {
        if (!isset($row[$keyColumn]) || count($row) < 4) continue;
        $key = floatval($row[$keyColumn]);
        $values = [
            'L' => isset($row[1]) ? floatval($row[1]) : 0,
            'M' => isset($row[2]) ? floatval($row[2]) : 0,
            'S' => isset($row[3]) ? floatval($row[3]) : 0
        ];
        if ($values['M'] <= 0 || $values['S'] <= 0) continue;
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
        return "who_datasets/wfl_{$gender_prefix}_0-to-2-years_zscores.csv";
    }
    return null;
}

function interpolate($x0, $y0, $x1, $y1, $x) {
    if ($x0 == $x1) return $y0;
    return $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
}

function calculateZScore($x, $L, $M, $S) {
    if ($x <= 0 || $M <= 0 || $S <= 0) return null;
    if (abs($L) < 0.0001) {
        return log($x / $M) / $S;
    }
    return (pow($x / $M, $L) - 1) / ($L * $S);
}

function getNutritionalValues($data, $key, $statuses) {
    $keys = array_keys($data);
    if (empty($keys)) return null;
    sort($keys);
    if (isset($data[$key])) return $data[$key];
    
    $lower_key = null;
    $upper_key = null;
    foreach ($keys as $k) {
        if ($k <= $key && ($lower_key === null || $k > $lower_key)) $lower_key = $k;
        if ($k >= $key && ($upper_key === null || $k < $upper_key)) $upper_key = $k;
    }
    
    if ($lower_key === null || $upper_key === null || $lower_key == $upper_key) return null;
    
    $result = [];
    $fields = array_merge(['L', 'M', 'S'], $statuses);
    foreach ($fields as $field) {
        if (!isset($data[$lower_key][$field]) || !isset($data[$upper_key][$field])) return null;
        $result[$field] = interpolate($lower_key, $data[$lower_key][$field], $upper_key, $data[$upper_key][$field], $key);
    }
    return $result;
}

$wfa_value_columns = ['SUW' => 4, 'UW' => 5, 'Normal' => 7, 'OW' => 9];
$hfa_value_columns = ['SSt' => 4, 'St' => 5, 'Normal' => 7];
$wflh_value_columns = ['SW' => 4, 'MW' => 5, 'Normal' => 7, 'OW' => 9, 'Ob' => 10];

$who_data_cache = [];
$current_date = new DateTime('now');

// ==================== FETCH COMPREHENSIVE HEALTH METRICS BY PUROK ====================

$health_data = [];

foreach ($puroks as $purok) {
    if ($purok === 'All') continue;
    
    // Skip if BHW Staff and not their assigned purok
    if ($role_id == 2 && $assigned_purok && $purok !== $assigned_purok) {
        continue;
    }
    
    $metrics = [];
    
    // ========== CHILD HEALTH (with WHO z-scores) ==========
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.gender, p.birthdate, 
               cr.weight, cr.height, cr.measurement_date,
               cr.immunization_status, cr.risk_observed
        FROM child_record cr
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'child_record'
        AND cr.child_type = 'Child'
        AND p.age BETWEEN 1 AND 6
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $underweight_count = 0;
    $stunted_count = 0;
    $wasted_count = 0;
    $overweight_count = 0;
    $fully_immunized = 0;
    $total_children = count($children);
    
    foreach ($children as $child) {
        if (!$child['birthdate']) continue;
        
        $birthdate = new DateTime($child['birthdate']);
        $age_in_days = $current_date->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;
        
        if ($age_in_months < 0 || $age_in_months > 59) continue;
        
        $gender = $child['gender'] === 'Male' ? 'M' : 'F';
        $weight = floatval($child['weight']);
        $height = floatval($child['height']);
        $age_in_weeks = $age_in_days / 7;
        
        // WFA (Underweight)
        $wfa_file = getDatasetForAge($gender, $age_in_months, 'wfa');
        if (!isset($who_data_cache[$wfa_file])) {
            $who_data_cache[$wfa_file] = loadCsvData($wfa_file, 0, $wfa_value_columns);
        }
        $wfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $wfa_values = getNutritionalValues($who_data_cache[$wfa_file], $wfa_key, ['SUW', 'UW', 'Normal', 'OW']);
        if ($wfa_values && $weight > 0) {
            $wfa_z = calculateZScore($weight, $wfa_values['L'], $wfa_values['M'], $wfa_values['S']);
            if ($wfa_z !== null && $wfa_z < -2) {
                $underweight_count++;
            }
        }
        
        // HFA (Stunted)
        $hfa_file = getDatasetForAge($gender, $age_in_months, 'hfa');
        if (!isset($who_data_cache[$hfa_file])) {
            $who_data_cache[$hfa_file] = loadCsvData($hfa_file, 0, $hfa_value_columns);
        }
        $hfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $hfa_values = getNutritionalValues($who_data_cache[$hfa_file], $hfa_key, ['SSt', 'St', 'Normal']);
        if ($hfa_values && $height > 0) {
            $hfa_z = calculateZScore($height, $hfa_values['L'], $hfa_values['M'], $hfa_values['S']);
            if ($hfa_z !== null && $hfa_z < -2) {
                $stunted_count++;
            }
        }
        
        // WFLH (Wasted/Overweight)
        $wflh_file = getDatasetForAge($gender, $age_in_months, 'wflh');
        if (!isset($who_data_cache[$wflh_file])) {
            $who_data_cache[$wflh_file] = loadCsvData($wflh_file, 0, $wflh_value_columns);
        }
        $height_key = $height;
        $height_keys = array_keys($who_data_cache[$wflh_file]);
        if (!empty($height_keys)) {
            $max_height = max($height_keys);
            $min_height = min($height_keys);
            if ($height_key < $min_height || $height_key > $max_height) {
                $height_key = $height < $min_height ? $min_height : $max_height;
            }
            $wflh_values = getNutritionalValues($who_data_cache[$wflh_file], $height_key, ['SW', 'MW', 'Normal', 'OW', 'Ob']);
            if ($wflh_values && $weight > 0) {
                $wflh_z = calculateZScore($weight, $wflh_values['L'], $wflh_values['M'], $wflh_values['S']);
                if ($wflh_z !== null) {
                    if ($wflh_z < -2) $wasted_count++;
                    if ($wflh_z > 2) $overweight_count++;
                }
            }
        }
        
        // Immunization
        $immun_status = $child['immunization_status'] ?? '';
        if (strpos($immun_status, 'MMR') !== false && 
            strpos($immun_status, 'Vitamin A') !== false && 
            strpos($immun_status, 'FIC') !== false) {
            $fully_immunized++;
        }
    }
    
    $metrics['total_children'] = $total_children;
    $metrics['underweight_count'] = $underweight_count;
    $metrics['underweight_rate'] = $total_children > 0 ? round(($underweight_count / $total_children) * 100, 1) : 0;
    $metrics['stunted_count'] = $stunted_count;
    $metrics['stunted_rate'] = $total_children > 0 ? round(($stunted_count / $total_children) * 100, 1) : 0;
    $metrics['wasted_count'] = $wasted_count;
    $metrics['wasted_rate'] = $total_children > 0 ? round(($wasted_count / $total_children) * 100, 1) : 0;
    $metrics['overweight_count'] = $overweight_count;
    $metrics['fully_immunized_count'] = $fully_immunized;
    $metrics['immunization_coverage'] = $total_children > 0 ? round(($fully_immunized / $total_children) * 100, 1) : 0;
    
    // ========== INFANT HEALTH ==========
    $stmt = $pdo->prepare("
        SELECT ir.exclusive_breastfeeding, cr.weight
        FROM infant_record ir
        JOIN child_record cr ON ir.child_record_id = cr.child_record_id
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type LIKE '%infant_record%'
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $infants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $exclusive_bf_count = 0;
    $low_birth_weight_count = 0;
    $total_infants = count($infants);
    
    foreach ($infants as $infant) {
        if ($infant['exclusive_breastfeeding'] === 'Y') $exclusive_bf_count++;
        if (floatval($infant['weight']) < 2.5) $low_birth_weight_count++;
    }
    
    $metrics['total_infants'] = $total_infants;
    $metrics['exclusive_breastfeeding_count'] = $exclusive_bf_count;
    $metrics['exclusive_breastfeeding_rate'] = $total_infants > 0 ? round(($exclusive_bf_count / $total_infants) * 100, 1) : 0;
    $metrics['low_birth_weight_count'] = $low_birth_weight_count;
    $metrics['low_birth_weight_rate'] = $total_infants > 0 ? round(($low_birth_weight_count / $total_infants) * 100, 1) : 0;
    
    // ========== PRENATAL/MATERNAL HEALTH ==========
    $stmt = $pdo->prepare("
        SELECT prr.pregnancy_record_id, pn.checkup_date, pn.risk_observed
        FROM prenatal pn
        JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type LIKE '%prenatal%'
        AND prr.pregnancy_period = 'Prenatal'
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $prenatal_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $unique_pregnancies = [];
    $prenatal_coverage_count = 0;
    $maternal_risk_count = 0;
    
    foreach ($prenatal_records as $record) {
        $pregnancy_id = $record['pregnancy_record_id'];
        if (!isset($unique_pregnancies[$pregnancy_id])) {
            $unique_pregnancies[$pregnancy_id] = true;
            if (!empty($record['checkup_date']) && strpos($record['checkup_date'], 'None') === false) {
                $prenatal_coverage_count++;
            }
        }
        if (!empty($record['risk_observed'])) {
            $maternal_risk_count++;
        }
    }
    
    $total_pregnant = count($unique_pregnancies);
    $metrics['total_pregnant'] = $total_pregnant;
    $metrics['prenatal_coverage_count'] = $prenatal_coverage_count;
    $metrics['prenatal_coverage'] = $total_pregnant > 0 ? round(($prenatal_coverage_count / $total_pregnant) * 100, 1) : 0;
    $metrics['maternal_risk_count'] = $maternal_risk_count;
    
    // ========== POSTNATAL HEALTH ==========
    $stmt = $pdo->prepare("
        SELECT delivery_location, postnatal_checkups, family_planning_intent
        FROM postnatal pn
        JOIN pregnancy_record prr ON pn.pregnancy_record_id = prr.pregnancy_record_id
        JOIN records r ON prr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type LIKE '%postnatal%'
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $postnatal_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $home_birth_count = 0;
    $postnatal_coverage_count = 0;
    $fp_intent_count = 0;
    $total_postnatal = count($postnatal_records);
    
    foreach ($postnatal_records as $record) {
        if (stripos($record['delivery_location'], 'Bahay') !== false) $home_birth_count++;
        if (!empty($record['postnatal_checkups']) && strpos($record['postnatal_checkups'], 'No') === false) {
            $postnatal_coverage_count++;
        }
        if ($record['family_planning_intent'] === 'Y') $fp_intent_count++;
    }
    
    $metrics['total_postnatal'] = $total_postnatal;
    $metrics['home_birth_count'] = $home_birth_count;
    $metrics['home_birth_rate'] = $total_postnatal > 0 ? round(($home_birth_count / $total_postnatal) * 100, 1) : 0;
    $metrics['postnatal_coverage_count'] = $postnatal_coverage_count;
    $metrics['fp_intent_count'] = $fp_intent_count;
    $metrics['fp_intent_rate'] = $total_postnatal > 0 ? round(($fp_intent_count / $total_postnatal) * 100, 1) : 0;
    
    // ========== FAMILY PLANNING ==========
    $stmt = $pdo->prepare("
        SELECT uses_fp_method
        FROM family_planning_record fpr
        JOIN records r ON fpr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'family_planning_record'
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $fp_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fp_using_count = 0;
    $total_fp = count($fp_records);
    
    foreach ($fp_records as $record) {
        if ($record['uses_fp_method'] === 'Y') $fp_using_count++;
    }
    
    $metrics['total_fp_eligible'] = $total_fp;
    $metrics['fp_using_count'] = $fp_using_count;
    $metrics['fp_using_rate'] = $total_fp > 0 ? round(($fp_using_count / $total_fp) * 100, 1) : 0;
    
    // ========== SENIOR HEALTH ==========
$stmt = $pdo->prepare("
    SELECT sr.bp_reading, p.health_condition, COUNT(sm.medication_id) as med_count
    FROM senior_record sr
    JOIN records r ON sr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
    WHERE p.age >= 60
    AND a.purok = ?
    GROUP BY sr.senior_record_id, p.health_condition
");
$stmt->execute([$purok]);
$seniors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hypertensive_count = 0;
$polypharmacy_count = 0;
$total_seniors = count($seniors);

foreach ($seniors as $senior) {
    // Check if health_condition contains 'HPN' (case insensitive)
    if (stripos($senior['health_condition'], 'HPN') !== false) {
        $hypertensive_count++;
    }
    if ($senior['med_count'] >= 5) {
        $polypharmacy_count++;
    }
}

$metrics['total_seniors'] = $total_seniors;
$metrics['hypertensive_count'] = $hypertensive_count;
$metrics['hypertensive_rate'] = $total_seniors > 0 ? round(($hypertensive_count / $total_seniors) * 100, 1) : 0;
$metrics['polypharmacy_count'] = $polypharmacy_count;

    
    // ========== TOTAL POPULATION (CORRECTED) ==========
    $stmt = $pdo->prepare("
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
        AND a.purok = ?
    ");
    $stmt->execute([$purok]);
    $pop_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $metrics['total_population'] = $pop_data['total_population'] ?? 0;
    $metrics['total_household'] = $pop_data['total_household'] ?? 0;
    $metrics['total_male'] = $pop_data['total_male'] ?? 0;
    $metrics['total_female'] = $pop_data['total_female'] ?? 0;
    $metrics['infant_count'] = $pop_data['infant_count'] ?? 0;
    $metrics['early_childhood_count'] = $pop_data['early_childhood_count'] ?? 0;
    $metrics['middle_childhood_count'] = $pop_data['middle_childhood_count'] ?? 0;
    $metrics['teen_count'] = $pop_data['teen_count'] ?? 0;
    $metrics['adult_count'] = $pop_data['adult_count'] ?? 0;
    $metrics['elderly_count'] = $pop_data['elderly_count'] ?? 0;

    // ========== COMPOSITE HEALTH SCORE ==========
    $health_score = 0;
    $health_score += $metrics['underweight_rate'] * 2;
    $health_score += $metrics['stunted_rate'] * 1.5;
    $health_score += (100 - $metrics['immunization_coverage']);
    $health_score += (100 - $metrics['prenatal_coverage']);
    $health_score += $metrics['maternal_risk_count'] * 3;
    $metrics['health_score'] = min(100, $health_score);
    
    $health_data[$purok] = $metrics;
}

$health_data_json = json_encode($health_data);
$map_settings_json = json_encode($mapSettings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Health Map</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-layers.tree@latest/dist/L.Control.Layers.Tree.css">
    <link rel="stylesheet" href="css/qgis2web.css">
    <link rel="stylesheet" href="css/fontawesome-all.min.css">
    <link rel="stylesheet" href="css/leaflet.photon.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 0;
            overflow-x: auto !important;
            overflow-y: auto !important;
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
            position: relative;
            z-index: 1030;
            margin-top: 0;
        }
        .content.with-sidebar { margin-left: 250px; }
        .card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: visible;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #2b6cb0, #4299e1);
            color: #fff;
            padding: 15px 20px;
            border-bottom: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 15px 15px 0 0;
        }
        .card-body {
            padding: 0;
            height: auto;
            max-height: 400px;
            overflow: visible;
        }
        #map {
            height: 70vh;
            width: 100%;
            min-height: 400px;
            max-width: 100%;
        }
        
        /* Heatmap Controls */
        .heatmap-controls {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .heatmap-controls label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }
        .heatmap-controls select {
            width: 100%;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 0.9rem;
        }
        
        /* Legend */
        .legend {
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            font-size: 0.7rem;
            max-width: 130px;
            max-height: 180px;
        }
        .legend h4 {
            margin: 0 0 10px 0;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }
        .legend-color {
            width: 25px;
            height: 18px;
            margin-right: 8px;
            border-radius: 3px;
            border: 1px solid #999;
        }
        
        /* Enhanced Tooltip */
        .custom-tooltip {
            background: white !important;
            border: 2px solid #2b6cb0 !important;
            border-radius: 8px !important;
            padding: 12px !important;
            font-family: 'Poppins', sans-serif !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            min-width: 200px !important;
        }
        .custom-tooltip h4 {
            margin: 0 0 8px 0;
            color: #2b6cb0;
            font-size: 1.1rem;
            font-weight: 700;
        }
        .custom-tooltip .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin: 8px 0;
        }
        .custom-tooltip .metric-label {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 8px;
        }
        .custom-tooltip .count-detail {
            font-size: 0.9rem;
            color: #4a5568;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
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
                padding: 10px;
            }
            .content.with-sidebar {
                margin-left: 0;
            }
            #map { height: 50vh; min-height: 300px; }
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
            .navbar-brand { padding-left: 55px; }
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
            #map { height: 70vh; }
            .menu-toggle { display: none; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col content">
                <!-- Heatmap Controls -->
                <div class="heatmap-controls">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="heatmap-metric">
                                <i class="fas fa-layer-group"></i> Health Metric:
                            </label>
                            <select id="heatmap-metric" class="form-control">
                                <option value="none">None (Original Map)</option>
                                <optgroup label="Composite Scores">
                                    <option value="health_score">Overall Health Risk Score</option>
                                </optgroup>
                                <optgroup label="Child Nutrition (WHO Standards)">
                                    <option value="underweight_rate">Underweight Rate (WFA)</option>
                                    <option value="stunted_rate">Stunted Rate (HFA)</option>
                                    <option value="wasted_rate">Wasted Rate (WFLH)</option>
                                    <option value="immunization_coverage">Immunization Coverage</option>
                                </optgroup>
                                <optgroup label="Infant Health">
                                    <option value="exclusive_breastfeeding_rate">Exclusive Breastfeeding Rate</option>
                                    <option value="low_birth_weight_rate">Low Birth Weight Rate</option>
                                </optgroup>
                                <optgroup label="Maternal Health">
                                    <option value="prenatal_coverage">Prenatal Care Coverage</option>
                                    <option value="home_birth_rate">Home Birth Rate</option>
                                    <option value="fp_intent_rate">Family Planning Intent</option>
                                </optgroup>
                                <optgroup label="Senior Health">
                                    <option value="hypertensive_rate">Hypertension Rate</option>
                                </optgroup>
                                <optgroup label="Population">
                                    <option value="total_population">Total Population</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="purok-filter">Filter by Purok:</label>
                            <select class="form-control" id="purok-filter">
                                <?php foreach ($puroks as $purok): ?>
                                    <option value="<?php echo htmlspecialchars($purok); ?>"><?php echo htmlspecialchars($purok); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div class="d-flex justify-content-between align-items-center" style="padding-top: 8px;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="show-markers" checked>
                                    <label class="form-check-label" for="show-markers">
                                        Show Household Markers
                                    </label>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#mapSettingsModal">
                                    <i class="fas fa-cog"></i> Map Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-map-marked-alt"></i> Health Map
                        </div>
                        <div class="card-body">
                            <div id="map"></div>
                            <div id="metric-summary" style="
                                margin-top: 15px;
                                max-height: 220px;
                                overflow-y: auto;
                                padding: 12px 16px;
                                background: #ffffff;
                                border-radius: 10px;
                                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                                font-size: 0.9rem;
                                margin-bottom: 20px;
                            ">
                                <strong>Health Metric Summary</strong>
                                <p style="margin: 6px 0 0 0; color:#4a5568; font-size:0.85rem;">
                                    Select a health metric and optionally a purok to see an interpretation of the results here.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side report panel -->
                <div class="col-md-4">
                    <div class="card" style="height: 100%;">
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-list-alt"></i> Case Report
                        </div>
                        <div class="card-body" style="max-height: 70vh; overflow-y: auto;">
                            <h6 id="report-title" class="text-center text-muted mt-3">
                                Select a metric to view case details.
                            </h6>
                            <div id="report-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="mapSettingsModal" tabindex="-1" role="dialog" aria-labelledby="mapSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="mapSettingsModalLabel"><i class="fas fa-sliders-h"></i> Map Metric Settings</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                <table class="table table-sm table-bordered text-center">
                    <thead class="thead-light">
                    <tr>
                        <th>Metric</th>
                        <th>Safe Color</th>
                        <th>Warning Range (Low–High)</th>
                        <th>Alarm Color</th>
                        <th>Save</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mapSettings as $key => $s): ?>
                    <tr>
                        <form method="POST">
                        <input type="hidden" name="update_settings" value="1">
                        <input type="hidden" name="metric_key" value="<?php echo htmlspecialchars($key); ?>">
                        <td class="align-middle font-weight-bold">
                            <?php echo htmlspecialchars($s['metric_label']); ?>
                        </td>
                        <td class="align-middle">
                            <input type="color" name="color_low" value="<?php echo htmlspecialchars($s['color_low']); ?>" style="width:40px;height:30px;border:none;">
                        </td>
                        <td class="align-middle">
                            <div class="input-group input-group-sm" style="max-width: 180px; margin: 0 auto;">
                            <input type="number" step="0.1" class="form-control" name="low_threshold" value="<?php echo htmlspecialchars($s['low_threshold']); ?>">
                            <div class="input-group-prepend">
                                <span class="input-group-text">–</span>
                            </div>
                            <input type="number" step="0.1" class="form-control" name="high_threshold" value="<?php echo htmlspecialchars($s['high_threshold']); ?>">
                            </div>
                        </td>
                        <td class="align-middle">
                            <input type="color" name="color_high" value="<?php echo htmlspecialchars($s['color_high']); ?>" style="width:40px;height:30px;border:none;">
                            <input type="hidden" name="color_medium" value="<?php echo htmlspecialchars($s['color_medium']); ?>">
                        </td>
                        <td class="align-middle">
                            <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save"></i>
                            </button>
                        </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
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
        // Health data from PHP
        var healthData = <?php echo $health_data_json; ?>;
        var mapSettings = <?php echo $map_settings_json; ?>;
        
        // Metric display names and units
        var metricInfo = {
            'health_score': {
                name: 'Overall Health Risk Index',
                unit: '/100',
                count_key: null,
                description: '0–100 index combining child nutrition, immunization, maternal and senior health risks. Higher values mean more health problems in this purok.'
            },
            'underweight_rate': {
                name: 'Underweight Rate (WFA)',
                unit: '%',
                count_key: 'underweight_count',
                description: 'Percent of children 1–6 years whose weight is too low for their age.'
            },
            'stunted_rate': {
                name: 'Stunted Rate (HFA)',
                unit: '%',
                count_key: 'stunted_count',
                description: 'Percent of children 1–6 years whose height is too short for their age.'
            },
            'wasted_rate': {
                name: 'Wasted Rate (WFLH)',
                unit: '%',
                count_key: 'wasted_count',
                description: 'Percent of children 1–6 years whose weight is too low for their height.'
            },
            'immunization_coverage': {
                name: 'Immunization Coverage',
                unit: '%',
                count_key: 'fully_immunized_count',
                description: 'Percent of children 1–6 years who completed key vaccines (FIC, MMR and Vitamin A).'
            },
            'exclusive_breastfeeding_rate': {
                name: 'Exclusive Breastfeeding Rate',
                unit: '%',
                count_key: 'exclusive_breastfeeding_count',
                description: 'Percent of infants recorded as exclusively breastfed.'
            },
            'low_birth_weight_rate': {
                name: 'Low Birth Weight Rate',
                unit: '%',
                count_key: 'low_birth_weight_count',
                description: 'Percent of infants born with weight below 2.5 kg.'
            },
            'prenatal_coverage': {
                name: 'Prenatal Care Coverage',
                unit: '%',
                count_key: 'prenatal_coverage_count',
                description: 'Percent of pregnant women with prenatal checkups.'
            },
            'home_birth_rate': {
                name: 'Home Birth Rate',
                unit: '%',
                count_key: 'home_birth_count',
                description: 'Percent of deliveries that happened at home.'
            },
            'fp_intent_rate': {
                name: 'Family Planning Intent',
                unit: '%',
                count_key: 'fp_intent_count',
                description: 'Percent of postnatal women who expressed intent to use family planning.'
            },
            'hypertensive_rate': {
                name: 'Hypertension Rate',
                unit: '%',
                count_key: 'hypertensive_count',
                description: 'Percent of seniors (60+) with high blood pressure readings.'
            },
            'total_population': {
                name: 'Total Population',
                unit: '',
                count_key: null,
                description: 'Total number of residents living in this purok.'
            }
        };
        
        function updateMetricSummary(selectedMetric, selectedPurok) {
            var summaryDiv = document.getElementById('metric-summary');
            if (!summaryDiv) return;
        
            if (selectedMetric === 'none') {
                summaryDiv.innerHTML =
                    '<strong>Health Metric Summary</strong>' +
                    '<p style="margin:6px 0 0 0; color:#4a5568; font-size:0.85rem;">' +
                    'No health metric is selected. Choose one above to see an interpretation of the situation per purok.' +
                    '</p>';
                return;
            }
        
            var info = metricInfo[selectedMetric];
            if (!info) return;
        
            var purokNames = Object.keys(healthData);
            var focusPurok = (selectedPurok && selectedPurok !== 'All') ? selectedPurok : null;
        
            // Collect values
            var values = [];
            purokNames.forEach(function(name) {
                var d = healthData[name];
                if (!d || d[selectedMetric] == null) return;
                if (focusPurok && name !== focusPurok) return;
                values.push({ name: name, value: d[selectedMetric], data: d });
            });
        
            if (values.length === 0) {
                summaryDiv.innerHTML =
                    '<strong>' + info.name + '</strong>' +
                    '<p style="margin:6px 0 0 0; color:#4a5568; font-size:0.85rem;">' +
                    'No data is available for the selected metric and purok.' +
                    '</p>';
                return;
            }
        
            values.sort(function(a, b) { return b.value - a.value; }); // highest first
            var top = values[0];
            var avg = values.reduce(function(sum, v){ return sum + Number(v.value || 0); }, 0) / values.length;
            avg = Math.round(avg * 10) / 10;
        
            var scopeText = focusPurok ? ('Purok ' + focusPurok) : 'all puroks';
        
            var html = '';
            html += '<strong>' + info.name + '</strong>';
            if (info.description) {
                html += '<p style="margin:4px 0 8px 0; color:#4a5568; font-size:0.85rem;">' + info.description + '</p>';
            }
        
            html += '<p style="margin:0 0 4px 0; color:#2d3748;">';
            html += 'For ' + scopeText + ', the average value is <strong>' + avg + info.unit + '</strong>.';
            html += '</p>';
        
            if (!focusPurok && values.length > 1) {
                html += '<p style="margin:0 0 4px 0; color:#2d3748;">';
                html += 'The highest value is in <strong>' + top.name + '</strong> at ';
                html += '<strong>' + top.value + info.unit + '</strong>.';
                html += '</p>';
            } else if (focusPurok) {
                html += '<p style="margin:0 0 4px 0; color:#2d3748;">';
                html += 'In <strong>' + focusPurok + '</strong>, the current value is ';
                html += '<strong>' + top.value + info.unit + '</strong>.';
                html += '</p>';
            }
        
            // Optional risk/coverage sentence
            var higherIsBetter = ['immunization_coverage','prenatal_coverage','exclusive_breastfeeding_rate','fp_intent_rate'].includes(selectedMetric);
            html += '<p style="margin:0; color:#4a5568; font-size:0.85rem;">';
            if (selectedMetric === 'health_score') {
                html += 'Values closer to 0 mean fewer problems; values closer to 100 signal more combined nutrition, maternal or senior health risks.';
            } else if (higherIsBetter) {
                html += 'Higher percentages indicate better coverage or service use; lower values signal gaps that may need attention.';
            } else if (selectedMetric === 'total_population') {
                html += 'Higher numbers indicate more residents and potentially higher service demand in that purok.';
            } else {
                html += 'Higher percentages mean more residents are affected by this condition and may require follow-up or intervention.';
            }
            html += '</p>';
        
            summaryDiv.innerHTML = html;
        }
        function getListKey(metric) {
            if (metric === 'underweight_rate') return 'underweight';
            if (metric === 'stunted_rate') return 'stunted';
            if (metric === 'wasted_rate') return 'wasted';
            if (metric === 'immunization_coverage') return 'unimmunized';
            if (metric === 'hypertensive_rate') return 'hypertensive';
            // add more if you build more lists in PHP
            return null;
        }

        function updateReportPanel(selectedMetric, selectedPurok) {
            var reportTitle = document.getElementById('report-title');
            var reportContent = document.getElementById('report-content');
            if (!reportContent) return;

            if (selectedMetric === 'none') {
                if (reportTitle) reportTitle.style.display = 'block';
                reportContent.innerHTML = '';
                return;
            }
            if (reportTitle) reportTitle.style.display = 'none';

            var listKey = getListKey(selectedMetric);
            if (!listKey) {
                reportContent.innerHTML = '<div class="alert alert-info">No detailed case list is defined for this metric.</div>';
                return;
            }

            var purokNames = Object.keys(healthData);
            var totalCases = 0;
            var totalBase = 0;
            var html = '';

            purokNames.forEach(function(name) {
                if (selectedPurok && selectedPurok !== 'All' && name !== selectedPurok) return;
                var data = healthData[name];
                if (!data || !data.lists || !data.lists[listKey]) return;

                var list = data.lists[listKey];
                if (!list.length) return;

                var base = 0;
                if (listKey === 'underweight' || listKey === 'stunted' || listKey === 'wasted' || listKey === 'unimmunized') {
                    base = data.total_children || 0;
                } else if (listKey === 'hypertensive') {
                    base = data.total_seniors || 0;
                }

                totalCases += list.length;
                totalBase += base;

                html += '<div class="mb-3 border rounded p-2 bg-light">';
                html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                html += '<strong>' + name + '</strong>';
                html += '<span class="badge badge-dark">' + list.length + ' / ' + base + '</span>';
                html += '</div>';
                html += '<ul class="list-group list-group-flush">';
                list.forEach(function(item) {
                    html += '<li class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center">';
                    html += '<span>' + item.name + '</span>';
                    html += '<span class="badge badge-danger badge-pill">' + item.val + '</span>';
                    html += '</li>';
                });
                html += '</ul></div>';
            });

            var summary = '<div class="alert alert-info p-2 small mb-2">' +
                'Total cases: <strong>' + totalCases + '</strong>' +
                (totalBase ? ' out of <strong>' + totalBase + '</strong> monitored individuals.' : '.') +
                '</div>';

            if (!html) {
                html = '<div class="alert alert-success">No cases found for the selected metric and purok.</div>';
            }

            reportContent.innerHTML = summary + html;
        }
        
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
        map.attributionControl.setPrefix('<a href="https://github.com/tomchadwin/qgis2web" target="_blank">qgis2web</a> &middot; <a href="https://leafletjs.com">Leaflet</a>');
        var autolinker = new Autolinker({ truncate: { length: 30, location: 'smart' } });
        var zoomControl = L.control.zoom({ position: 'topleft' }).addTo(map);
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
                { "type": "Feature", "properties": { "name": "Barangay Hall" }, 
                  "geometry": { "type": "Point", "coordinates": [120.425852712125973, 15.641971867806623] } }
            ]
        };

        L.geoJSON(brgyHallGeoJSON, {
            pointToLayer: function(feature, latlng) {
                return L.marker(latlng, { icon: hallIcon });
            },
            onEachFeature: function(feature, layer) {
                layer.bindPopup("<b>Barangay Hall - Sta. Maria</b><br>Camiling, Tarlac");
                bounds_group.addLayer(layer);
            }
        }).addTo(map);

        // [KEEPING ALL YOUR ORIGINAL LAYER CODE - sta_maria_0, Purokcenters_1, stamariapurok_2, stamaria_3]
        // Layer: sta_maria_0
        function pop_sta_maria_0(feature, layer) {
            var popupContent = '<table><tr><td colspan="2">' + (feature.properties['ADM4_EN'] !== null ? autolinker.link(String(feature.properties['ADM4_EN'])) : '') + '</td></tr></table>';
            layer.bindPopup(popupContent, { maxHeight: 400 });
        }

        function style_sta_maria_0_0() {
            return {
                pane: 'pane_sta_maria_0',
                opacity: 1,
                color: 'rgba(77,175,74,1.0)',
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
            attribution: '',
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
                radius: 4.0,
                opacity: 1,
                color: 'rgba(35,35,35,1.0)',
                weight: 1,
                fill: true,
                fillOpacity: 1,
                fillColor: 'rgba(255,255,255,1.0)',
                interactive: true,
            };
        }
        map.createPane('pane_Purokcenters_1');
        map.getPane('pane_Purokcenters_1').style.zIndex = 401;
        var layer_Purokcenters_1 = new L.geoJson(json_Purokcenters_1, {
            pane: 'pane_Purokcenters_1',
            pointToLayer: function(feature, latlng) {
                return L.circleMarker(latlng, style_Purokcenters_1_0());
            },
        });
        bounds_group.addLayer(layer_Purokcenters_1);
        map.addLayer(layer_Purokcenters_1);

        // Layer: stamariapurok_2 (BASE)
        function style_stamariapurok_2_0() {
            return {
                pane: 'pane_stamariapurok_2',
                opacity: 1,
                color: 'rgba(35,35,35,1.0)',
                weight: 1.0,
                fill: true,
                fillOpacity: 0.3,
                fillColor: 'rgba(190,178,151,1.0)',
                interactive: true,
            };
        }
        map.createPane('pane_stamariapurok_2');
        map.getPane('pane_stamariapurok_2').style.zIndex = 402;
        var layer_stamariapurok_2 = new L.geoJson(json_stamariapurok_2, {
            pane: 'pane_stamariapurok_2',
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

        // Labels for stamariapurok_2
        var i = 0;
        layer_stamariapurok_2.eachLayer(function(layer) {
            layer.bindTooltip(
                '<div style="color: #ffffff; font-size: 11pt; font-weight: bold;">' + layer.feature.properties['Purok'] + '</div>',
                { permanent: true, offset: [-0, -16], className: 'css_stamariapurok_2', direction: 'center' }
            );
            if (typeof labels !== 'undefined') {
                labels.push(layer);
                totalMarkers += 1;
                layer.added = true;
                addLabel(layer, i, { avoidOtherLabels: false });
            }
            i++;
        });

        // Layer: stamaria_3
        function style_stamaria_3_0() {
            return {
                pane: 'pane_stamaria_3',
                opacity: 1,
                color: 'rgba(35,35,35,1.0)',
                weight: 1.0,
                fill: true,
                fillOpacity: 1,
                fillColor: 'rgba(114,155,111,1.0)',
                interactive: true,
            };
        }
        map.createPane('pane_stamaria_3');
        map.getPane('pane_stamaria_3').style.zIndex = 403;
        var layer_stamaria_3 = new L.geoJson(json_stamaria_3, {
            pane: 'pane_stamaria_3',
            style: style_stamaria_3_0,
        });
        bounds_group.addLayer(layer_stamaria_3);
        map.addLayer(layer_stamaria_3);

        // Group random points by purok
        var randomPointsByPurok = {};
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

        if (typeof json_Randompointsinpolygons_3 !== 'undefined') {
            var randomPointsGeoJSON = json_Randompointsinpolygons_3.features || [];
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
        }

        // Household markers
        var households = <?php echo json_encode($households); ?>;
        var purokFilter = document.getElementById('purok-filter');
        var householdPointIndex = {};

        Object.keys(randomPointsByPurok).forEach(function(purok) {
            householdPointIndex[purok] = 0;
        });
        
        function householdAffectedByMetric(h, metric) {
            if (!h.flags) {
                // if no flags provided, fall back to always show
                return true;
            }
        
            // When no metric or pure population is selected, show all households
            if (metric === 'none' || metric === 'total_population') {
                return true;
            }
        
            if (metric === 'health_score') {
                // Any risk flag means the household contributes to health risk
                return !!(
                    h.flags.underweight_rate ||
                    h.flags.stunted_rate ||
                    h.flags.wasted_rate ||
                    h.flags.low_birth_weight_rate ||
                    h.flags.hypertensive_rate ||
                    // service-related: treat lacking coverage or intent as risk
                    h.flags.home_birth_rate ||
                    !h.flags.immunization_coverage ||
                    !h.flags.prenatal_coverage ||
                    !h.flags.fp_intent_rate
                );
            }
        
            // Direct one-to-one mapping metric -> flag key
            return !!h.flags[metric];
        }


        function updateHouseholdMarkers() {
            Object.keys(householdMarkers).forEach(function(key) {
                if (householdMarkers[key]) {
                    map.removeLayer(householdMarkers[key]);
                }
            });
            householdMarkers = {};
            
            var showMarkers = document.getElementById('show-markers').checked;
            if (!showMarkers) return;

            var selectedPurok = purokFilter.value.trim();
            var selectedMetric = document.getElementById('heatmap-metric').value;
            
            var filteredHouseholds = households.filter(function(h) {
                var inPurok  = (selectedPurok === 'All' || h.purok.trim() === selectedPurok);
                var affected = householdAffectedByMetric(h, selectedMetric);
                return inPurok && affected;
            });

            var householdsByPurok = {};
            filteredHouseholds.forEach(function(h) {
                var purokKey = h.purok.trim();
                if (!householdsByPurok[purokKey]) householdsByPurok[purokKey] = [];
                householdsByPurok[purokKey].push(h);
            });

            Object.keys(householdsByPurok).forEach(function(purok) {
                householdsByPurok[purok].sort(function(a, b) {
                    return a.household_number - b.household_number;
                });
            });

            var dotIcon = L.divIcon({
                html: '<div style="width: 12px; height: 12px; background-color: #007bff; border-radius: 50%; border: 2px solid white;"></div>',
                className: 'dot-marker',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
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
                            var content = '<b>Household ' + h.household_number + ' - ' + h.head_name + '</b><br>Purok: ' + h.purok;
                            var marker = L.marker([lat, lng], {icon: dotIcon}).addTo(map).bindPopup(content);
                            householdMarkers[h.household_number] = marker;
                        });
                    }
                    return;
                }

                householdsByPurok[purokKey].forEach(function(h, householdIndex) {
                    var pointIndex = householdIndex % purokPoints.length;
                    var point = purokPoints[pointIndex];
                    var content = '<b>Household ' + h.household_number + ' - ' + h.head_name + '</b><br>Purok: ' + h.purok;
                    var marker = L.marker([point.lat, point.lng], {icon: dotIcon}).addTo(map).bindPopup(content);
                    householdMarkers[h.household_number] = marker;
                });
            });
        }

        // ==================== HEATMAP OVERLAY ====================
        var heatmapLayer = null;
        var currentMetric = 'none';
        
        function getColor(value, metric) {
            var cfg = mapSettings[metric];
            if (!cfg) {
                // fallback to neutral if no config
                return '#cccccc';
            }

            var low = parseFloat(cfg.low_threshold);
            var high = parseFloat(cfg.high_threshold);
            var colorLow = cfg.color_low || '#28a745';
            var colorMed = cfg.color_medium || '#ffc107';
            var colorHigh = cfg.color_high || '#dc3545';
            var isHigherBetter = parseInt(cfg.is_higher_better); // 1 or 0

            if (isNaN(value)) value = 0;

            if (isHigherBetter) {
                // Example: coverage metrics (higher is better)
                if (value >= high) return colorHigh;      // excellent coverage
                if (value >= low)  return colorMed;       // okay
                return colorLow;                          // poor
            } else {
                // Example: risk metrics (higher is bad)
                if (value >= high) return colorHigh;      // worst
                if (value >= low)  return colorMed;       // moderate
                return colorLow;                          // good/safe
            }
        }
        
        function styleHeatmap(feature, selectedMetric) {
            var purokName = feature.properties['Purok'];
            var value = healthData[purokName] ? healthData[purokName][selectedMetric] : 0;
            
            return {
                fillColor: getColor(value, selectedMetric),
                weight: 2,
                opacity: 1,
                color: '#fff',
                fillOpacity: 0.65
            };
        }
        
        function updateHeatmap(selectedMetric) {
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
                heatmapLayer = null;
            }
            
            currentMetric = selectedMetric;
            
            if (selectedMetric === 'none') {
                if (legend && legend._map) map.removeControl(legend);
                updateMetricSummary('none', document.getElementById('purok-filter').value);
                return;
            }
            
            map.createPane('pane_heatmap');
            map.getPane('pane_heatmap').style.zIndex = 405;
            
            heatmapLayer = L.geoJSON(json_stamariapurok_2, {
                pane: 'pane_heatmap',
                style: function(feature) {
                    return styleHeatmap(feature, selectedMetric);
                },
                onEachFeature: function(feature, layer) {
                    var purokName = feature.properties['Purok'];
                    var data = healthData[purokName];
                    
                    if (data) {
                        // Enhanced tooltip on hover
                        layer.on('mouseover', function(e) {
                            var tooltipContent = getTooltipContent(purokName, selectedMetric, data);
                            layer.bindTooltip(tooltipContent, {
                                permanent: false,
                                sticky: true,
                                className: 'custom-tooltip',
                                direction: 'top'
                            }).openTooltip();
                            
                            e.target.setStyle({
                                weight: 4,
                                fillOpacity: 0.85
                            });
                        });
                        
                        layer.on('mouseout', function(e) {
                            layer.closeTooltip();
                            heatmapLayer.resetStyle(e.target);
                        });
                        
                        // Popup on click with full details
                        layer.on('click', function(e) {
                            var popupContent = '<div style="font-family: Poppins; min-width: 250px;">';
                            popupContent += '<h4 style="margin: 0 0 12px 0; color: #2b6cb0; border-bottom: 2px solid #2b6cb0; padding-bottom: 8px;">' + purokName + '</h4>';
                            
                            var info = metricInfo[selectedMetric];

                            popupContent += '<div style="margin-bottom: 12px; padding: 10px; background: #f7fafc; border-radius: 6px;">';
                            popupContent += '<div style="font-size: 0.9rem; color: #718096; margin-bottom: 4px;">' + info.name + '</div>';
                        
                            var displayValue = selectedMetric === 'health_score'
                                ? Math.round(data[selectedMetric])
                                : data[selectedMetric];
                        
                            popupContent += '<div style="font-size: 2rem; font-weight: 700; color: #2d3748;">' + displayValue + info.unit + '</div>';
                        
                            if (info.description) {
                                popupContent += '<div style="margin-top: 6px; font-size: 0.8rem; color: #4a5568;">' + info.description + '</div>';
                            }
                            popupContent += '</div>';
                            
                            if (metricInfo[selectedMetric].count_key && data[metricInfo[selectedMetric].count_key] !== undefined) {
                                popupContent += '<div style="margin-top: 8px; font-size: 0.95rem; color: #4a5568;">';
                                popupContent += '<strong>' + data[metricInfo[selectedMetric].count_key] + '</strong> individuals affected';
                                popupContent += '</div>';
                            }
                            popupContent += '</div>';
                            
                            // Additional stats
                            popupContent += '<div style="font-size: 0.85rem; line-height: 1.8;">';
                            popupContent += '<strong>Total Population:</strong> ' + data.total_population + '<br>';
                            popupContent += '<strong>Total Households:</strong> ' + (data.total_household || 0) + '<br>';
                            
                            if (selectedMetric === 'total_population') {
                                popupContent += '<hr style="margin: 8px 0; border-color: #e2e8f0;">';
                                popupContent += '<div style="font-weight: 600; margin-bottom: 4px;">Age Distribution:</div>';
                                popupContent += '<strong>Adult (20-59):</strong> ' + (data.adult_count || 0) + '<br>';
                                popupContent += '<strong>Teen (13-19):</strong> ' + (data.teen_count || 0) + '<br>';
                                popupContent += '<strong>Middle Child (6-12):</strong> ' + (data.middle_childhood_count || 0) + '<br>';
                                popupContent += '<strong>Early Child (1-5):</strong> ' + (data.early_childhood_count || 0) + '<br>';
                                popupContent += '<strong>Infant (0-1):</strong> ' + (data.infant_count || 0) + '<br>';
                                popupContent += '<strong>Elderly (60+):</strong> ' + (data.elderly_count || 0) + '<br>';
                                popupContent += '<hr style="margin: 8px 0; border-color: #e2e8f0;">';
                                popupContent += '<strong>Male:</strong> ' + (data.total_male || 0) + ' | ';
                                popupContent += '<strong>Female:</strong> ' + (data.total_female || 0);
                            } else {
                                popupContent += '<strong>Children (1-6 yrs):</strong> ' + data.total_children + '<br>';
                                popupContent += '<strong>Infants:</strong> ' + data.total_infants + '<br>';
                                popupContent += '<strong>Pregnant Women:</strong> ' + data.total_pregnant + '<br>';
                                popupContent += '<strong>Seniors (60+):</strong> ' + data.total_seniors;
                            }
                            popupContent += '</div>';
                            popupContent += '</div>';
                            
                            layer.bindPopup(popupContent, { maxWidth: 300 }).openPopup();
                        });
                    }
                }
            }).addTo(map);
            
            updateLegend(selectedMetric);
        }
        
        // Legend control
        var legend = L.control({position: 'bottomright'});
        
        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'legend');
            div.id = 'legend-content';
            return div;
        };
        
        function updateLegend(metric) {
            if (!legend._map) {
                legend.addTo(map);
            }

            var div = document.getElementById('legend-content');
            var cfg = mapSettings[metric];
            if (!cfg) {
                div.innerHTML = '<span>No legend</span>';
                return;
            }

            var low = parseFloat(cfg.low_threshold);
            var high = parseFloat(cfg.high_threshold);
            var colorLow = cfg.color_low || '#28a745';
            var colorMed = cfg.color_medium || '#ffc107';
            var colorHigh = cfg.color_high || '#dc3545';
            var info = metricInfo[metric];
            var title = info ? info.name : metric;

            var html = '<h4>' + title + '</h4>';
            html += '<div class="legend-item"><div class="legend-color" style="background:' + colorLow + '"></div><span>< ' + low + (info && info.unit ? info.unit : '') + '</span></div>';
            html += '<div class="legend-item"><div class="legend-color" style="background:' + colorMed + '"></div><span>' + low + '–' + high + (info && info.unit ? info.unit : '') + '</span></div>';
            html += '<div class="legend-item"><div class="legend-color" style="background:' + colorHigh + '"></div><span>> ' + high + (info && info.unit ? info.unit : '') + '</span></div>';
            div.innerHTML = html;
        }

        // Event listeners
        document.getElementById('heatmap-metric').addEventListener('change', function() {
            var metric = this.value;
            var purok = document.getElementById('purok-filter').value;
            updateHeatmap(metric);
            updateHouseholdMarkers();
            updateMetricSummary(metric, purok);
            updateReportPanel(metric, purok);
        });
        
        document.getElementById('show-markers').addEventListener('change', function() {
            updateHouseholdMarkers();
        });
        
        purokFilter.addEventListener('change', function() {
            var metric = document.getElementById('heatmap-metric').value;
            var purok = this.value;
            updateHouseholdMarkers();
            updateMetricSummary(metric, purok);
            updateReportPanel(metric, purok);
        });

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = $('.sidebar');
            const content = $('.content');
            sidebar.toggleClass('open');
            if (sidebar.hasClass('open') && window.innerWidth <= 768) {
                $('<div class="sidebar-overlay active" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1035;"></div>')
                    .appendTo('body').on('click', function() {
                        sidebar.removeClass('open');
                        $(this).remove();
                    });
            }
            map.invalidateSize();
        }

        window.addEventListener('resize', function() {
            map.invalidateSize();
        });

        // Initialize accordion
        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });

        // Initial load
        updateHouseholdMarkers();
        updateMetricSummary(document.getElementById('heatmap-metric').value, document.getElementById('purok-filter').value);
        updateReportPanel(document.getElementById('heatmap-metric').value, document.getElementById('purok-filter').value);
    </script>
</body>
</html>
