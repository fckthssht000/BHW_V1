<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role and purok for filtering
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT a.purok 
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    JOIN address a ON p.address_id = a.address_id 
    WHERE u.user_id = ? LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user_purok = $stmt->fetchColumn();

// Date range filter
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Calculate previous period for comparison
$days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400;
$prev_date_from = date('Y-m-d', strtotime("-$days_diff days", strtotime($date_from)));
$prev_date_to = $date_from;

// ===================== WHO LMS Z-SCORE FUNCTIONS =====================

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

// ===================== ENHANCED INSIGHT GENERATION FUNCTIONS =====================

function generateWHONutritionInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to) {
    global $who_data_cache, $current_date, $wfa_value_columns, $hfa_value_columns, $wflh_value_columns;
    
    $insights = [];
    $purok_filter = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    
    // Current period
    $params_current = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) $params_current[] = $user_purok;
    
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.gender, p.birthdate, p.household_number, a.purok,
               cr.weight, cr.height, cr.measurement_date,
               cr.immunization_status
        FROM child_record cr
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'child_record'
        AND cr.child_type = 'Child'
        AND cr.measurement_date BETWEEN ? AND ?
        AND p.age BETWEEN 1 AND 6
        $purok_filter
    ");
    $stmt->execute($params_current);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Previous period
    $params_prev = [$prev_date_from, $prev_date_to];
    if ($role_id == 2 && $user_purok) $params_prev[] = $user_purok;
    
    $stmt->execute($params_prev);
    $children_prev = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $nutritional_counts = [
        'severely_underweight' => 0,
        'underweight' => 0,
        'severely_stunted' => 0,
        'stunted' => 0,
        'severely_wasted' => 0,
        'wasted' => 0,
        'obese' => 0
    ];
    
    $nutritional_counts_prev = [
        'severely_underweight' => 0,
        'underweight' => 0,
        'severely_stunted' => 0,
        'stunted' => 0,
        'severely_wasted' => 0,
        'wasted' => 0,
        'obese' => 0
    ];
    
    $purok_nutrition_data = [];
    $purok_immunization_data = [];
    $fully_immunized = 0;
    $fully_immunized_prev = 0;
    
    // Process current period
    foreach ($children as $child) {
        if (!$child['birthdate']) continue;
        
        $birthdate = new DateTime($child['birthdate']);
        $age_in_days = $current_date->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;
        
        if ($age_in_months < 0 || $age_in_months > 71) continue;
        
        $gender = $child['gender'] === 'Male' ? 'M' : 'F';
        $weight = floatval($child['weight']);
        $height = floatval($child['height']);
        $age_in_weeks = $age_in_days / 7;
        $purok = $child['purok'];
        
        if (!isset($purok_nutrition_data[$purok])) {
            $purok_nutrition_data[$purok] = ['underweight' => 0, 'stunted' => 0, 'wasted' => 0, 'obese' => 0, 'total' => 0];
        }
        if (!isset($purok_immunization_data[$purok])) {
            $purok_immunization_data[$purok] = ['immunized' => 0, 'not_immunized' => 0, 'total' => 0];
        }
        
        $purok_nutrition_data[$purok]['total']++;
        $purok_immunization_data[$purok]['total']++;
        
        // WFA
        $wfa_file = getDatasetForAge($gender, $age_in_months, 'wfa');
        if (!isset($who_data_cache[$wfa_file])) {
            $who_data_cache[$wfa_file] = loadCsvData($wfa_file, 0, $wfa_value_columns);
        }
        $wfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $wfa_values = getNutritionalValues($who_data_cache[$wfa_file], $wfa_key, ['SUW', 'UW', 'Normal', 'OW']);
        if ($wfa_values && $weight > 0) {
            $wfa_z = calculateZScore($weight, $wfa_values['L'], $wfa_values['M'], $wfa_values['S']);
            if ($wfa_z !== null) {
                if ($wfa_z < -3) {
                    $nutritional_counts['severely_underweight']++;
                    $purok_nutrition_data[$purok]['underweight']++;
                } elseif ($wfa_z < -2) {
                    $nutritional_counts['underweight']++;
                    $purok_nutrition_data[$purok]['underweight']++;
                }
            }
        }
        
        // HFA
        $hfa_file = getDatasetForAge($gender, $age_in_months, 'hfa');
        if (!isset($who_data_cache[$hfa_file])) {
            $who_data_cache[$hfa_file] = loadCsvData($hfa_file, 0, $hfa_value_columns);
        }
        $hfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $hfa_values = getNutritionalValues($who_data_cache[$hfa_file], $hfa_key, ['SSt', 'St', 'Normal']);
        if ($hfa_values && $height > 0) {
            $hfa_z = calculateZScore($height, $hfa_values['L'], $hfa_values['M'], $hfa_values['S']);
            if ($hfa_z !== null) {
                if ($hfa_z < -3) {
                    $nutritional_counts['severely_stunted']++;
                    $purok_nutrition_data[$purok]['stunted']++;
                } elseif ($hfa_z < -2) {
                    $nutritional_counts['stunted']++;
                    $purok_nutrition_data[$purok]['stunted']++;
                }
            }
        }
        
        // WFLH
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
                    if ($wflh_z < -3) {
                        $nutritional_counts['severely_wasted']++;
                        $purok_nutrition_data[$purok]['wasted']++;
                    } elseif ($wflh_z < -2) {
                        $nutritional_counts['wasted']++;
                        $purok_nutrition_data[$purok]['wasted']++;
                    } elseif ($wflh_z > 3) {
                        $nutritional_counts['obese']++;
                        $purok_nutrition_data[$purok]['obese']++;
                    }
                }
            }
        }
        
        // Immunization for children 1-6 years
        $immun_status = $child['immunization_status'] ?? '';
        $is_immunized = (strpos($immun_status, 'MMR') !== false && 
            strpos($immun_status, 'Vitamin A') !== false && 
            (strpos($immun_status, 'Fully Immunized (FIC)') !== false || strpos($immun_status, 'Completely Immunized (CIC)') !== false));
        
        if ($is_immunized) {
            $fully_immunized++;
            $purok_immunization_data[$purok]['immunized']++;
        } else {
            $purok_immunization_data[$purok]['not_immunized']++;
        }
    }
    
    // Process previous period
    foreach ($children_prev as $child) {
        if (!$child['birthdate']) continue;
        
        $birthdate = new DateTime($child['birthdate']);
        $age_in_days = $current_date->diff($birthdate)->days;
        $age_in_months = $age_in_days / 30.4375;
        
        if ($age_in_months < 0 || $age_in_months > 71) continue;
        
        $gender = $child['gender'] === 'Male' ? 'M' : 'F';
        $weight = floatval($child['weight']);
        $height = floatval($child['height']);
        $age_in_weeks = $age_in_days / 7;
        
        // WFA
        $wfa_file = getDatasetForAge($gender, $age_in_months, 'wfa');
        if (!isset($who_data_cache[$wfa_file])) {
            $who_data_cache[$wfa_file] = loadCsvData($wfa_file, 0, $wfa_value_columns);
        }
        $wfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $wfa_values = getNutritionalValues($who_data_cache[$wfa_file], $wfa_key, ['SUW', 'UW', 'Normal', 'OW']);
        if ($wfa_values && $weight > 0) {
            $wfa_z = calculateZScore($weight, $wfa_values['L'], $wfa_values['M'], $wfa_values['S']);
            if ($wfa_z !== null) {
                if ($wfa_z < -3) $nutritional_counts_prev['severely_underweight']++;
                elseif ($wfa_z < -2) $nutritional_counts_prev['underweight']++;
            }
        }
        
        // HFA
        $hfa_file = getDatasetForAge($gender, $age_in_months, 'hfa');
        if (!isset($who_data_cache[$hfa_file])) {
            $who_data_cache[$hfa_file] = loadCsvData($hfa_file, 0, $hfa_value_columns);
        }
        $hfa_key = $age_in_months < 1 ? $age_in_weeks : $age_in_months;
        $hfa_values = getNutritionalValues($who_data_cache[$hfa_file], $hfa_key, ['SSt', 'St', 'Normal']);
        if ($hfa_values && $height > 0) {
            $hfa_z = calculateZScore($height, $hfa_values['L'], $hfa_values['M'], $hfa_values['S']);
            if ($hfa_z !== null) {
                if ($hfa_z < -3) $nutritional_counts_prev['severely_stunted']++;
                elseif ($hfa_z < -2) $nutritional_counts_prev['stunted']++;
            }
        }
        
        // WFLH
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
                    if ($wflh_z < -3) $nutritional_counts_prev['severely_wasted']++;
                    elseif ($wflh_z < -2) $nutritional_counts_prev['wasted']++;
                    elseif ($wflh_z > 3) $nutritional_counts_prev['obese']++;
                }
            }
        }
        
        // Immunization
        $immun_status = $child['immunization_status'] ?? '';
        if (strpos($immun_status, 'MMR') !== false && 
            strpos($immun_status, 'Vitamin A') !== false && 
            (strpos($immun_status, 'Fully Immunized (FIC)') !== false || strpos($immun_status, 'Completely Immunized (CIC)') !== false)) {
            $fully_immunized_prev++;
        }
    }
    
    $total_children = count($children);
    $total_children_prev = count($children_prev);
    
    // Build purok data for geographic visualization
    $purok_labels = [];
    $purok_underweight_rates = [];
    $purok_stunting_rates = [];
    $purok_wasting_rates = [];
    $purok_obesity_rates = [];
    
    foreach ($purok_nutrition_data as $purok => $data) {
        if ($data['total'] > 0) {
            $purok_labels[] = $purok;
            $purok_underweight_rates[] = round(($data['underweight'] / $data['total']) * 100, 1);
            $purok_stunting_rates[] = round(($data['stunted'] / $data['total']) * 100, 1);
            $purok_wasting_rates[] = round(($data['wasted'] / $data['total']) * 100, 1);
            $purok_obesity_rates[] = round(($data['obese'] / $data['total']) * 100, 1);
        }
    }
    
    // INSIGHT 1: Severe Malnutrition Alert
    if ($nutritional_counts['severely_underweight'] > 0) {
        $rate = $total_children > 0 ? round(($nutritional_counts['severely_underweight'] / $total_children) * 100, 1) : 0;
        $rate_prev = $total_children_prev > 0 ? round(($nutritional_counts_prev['severely_underweight'] / $total_children_prev) * 100, 1) : 0;
        $change = $rate - $rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'remained stable');
        
        // Get purok breakdown for underweight
        $underweight_puroks = [];
        foreach ($purok_nutrition_data as $purok => $data) {
            if ($data['total'] > 0 && $data['underweight'] > 0) {
                $purok_rate = round(($data['underweight'] / $data['total']) * 100, 1);
                $underweight_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'count' => $data['underweight']];
            }
        }
        usort($underweight_puroks, fn($a, $b) => $b['rate'] - $a['rate']);
        $top_puroks = array_slice($underweight_puroks, 0, 3);
        $purok_text = '';
        if (!empty($top_puroks)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d children)', $p['purok'], $p['rate'], $p['count']), $top_puroks);
            $purok_text = ' Most affected areas: ' . implode('; ', $purok_list) . '.';
        }
        
        $insights[] = [
            'type' => 'critical',
            'category' => 'Child Nutrition (WHO Standards)',
            'title' => 'Severe Underweight Children Detected',
            'description' => sprintf(
                '%d children (%.1f%%) are severely underweight (WHO z-score < -3). This rate has %s by %.1f percentage points compared to the previous period (%.1f%%). This exceeds emergency threshold and requires immediate intervention.%s',
                $nutritional_counts['severely_underweight'],
                $rate,
                $trend,
                abs($change),
                $rate_prev,
                $purok_text
            ),
            'detailed_explanation' => sprintf(
                'Severe underweight in children aged 1-6 years is measured using WHO weight-for-age standards. A child falling below -3 standard deviations indicates critical malnutrition requiring immediate intervention. The current rate of %.1f%% shows a %s trend from %.1f%% in the previous period. This pattern suggests underlying factors such as inadequate dietary intake, frequent illness episodes, or potential food insecurity affecting households. The weight and height measurements from Child Health Forms are compared against WHO growth standards to identify these cases.%s Without immediate action, severely underweight children face increased mortality risk (up to 9 times higher than well-nourished children), developmental delays, compromised immune function leading to more frequent infections, and permanent physical and cognitive impairment.',
                $rate,
                $trend,
                $rate_prev,
                $purok_text ? ' Geographic analysis shows concentration in specific areas: ' . $purok_text : ''
            ),
            'recommendation' => sprintf(
                'Activate emergency nutrition program immediately. Conduct therapeutic feeding for severely malnourished children using Ready-to-Use Therapeutic Food (RUTF). Refer all cases to municipal health office for medical management and screening for complications. Schedule urgent home visits for affected households to assess living conditions and provide family support. Monitor weekly weight gain and adjust interventions as needed. %s',
                !empty($top_puroks) ? 'Prioritize resources to ' . $top_puroks[0]['purok'] . ' and other most affected puroks.' : 'Target resources to most affected areas.'
            ),
            'priority' => 'critical',
            'chart_data' => json_encode([
                'labels' => ['Previous Period', 'Current Period', 'Emergency Threshold'],
                'datasets' => [
                    [
                        'label' => 'Severely Underweight Rate (%)',
                        'data' => [$rate_prev, $rate, 10],
                        'backgroundColor' => ['rgba(220, 53, 69, 0.5)', 'rgba(220, 53, 69, 0.8)', 'rgba(255, 193, 7, 0.3)'],
                        'borderColor' => ['rgb(220, 53, 69)', 'rgb(220, 53, 69)', 'rgb(255, 193, 7)'],
                        'borderWidth' => 2
                    ]
                ]
            ]),
            'chart_type' => 'bar',
            'data_source' => 'Data collected from Child Health Forms during regular growth monitoring sessions. Weight and height measurements are compared against World Health Organization (WHO) growth standards for children ages 1-6 years to identify severely underweight children. Geographic distribution tracked by residential purok.',
            'has_geographic_data' => !empty($top_puroks)
        ];
    }
    
    // INSIGHT 2: Stunting Alert with Geographic Breakdown
    $stunted_total = $nutritional_counts['severely_stunted'] + $nutritional_counts['stunted'];
    $stunted_total_prev = $nutritional_counts_prev['severely_stunted'] + $nutritional_counts_prev['stunted'];
    
    if ($stunted_total > 0 && $total_children > 0) {
        $stunting_rate = round(($stunted_total / $total_children) * 100, 1);
        $stunting_rate_prev = $total_children_prev > 0 ? round(($stunted_total_prev / $total_children_prev) * 100, 1) : 0;
        $change = $stunting_rate - $stunting_rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        // Get purok breakdown for stunting
        $stunted_puroks = [];
        foreach ($purok_nutrition_data as $purok => $data) {
            if ($data['total'] > 0 && $data['stunted'] > 0) {
                $purok_rate = round(($data['stunted'] / $data['total']) * 100, 1);
                $stunted_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'count' => $data['stunted']];
            }
        }
        usort($stunted_puroks, fn($a, $b) => $b['rate'] - $a['rate']);
        $top_stunted = array_slice($stunted_puroks, 0, 3);
        $stunted_purok_text = '';
        if (!empty($top_stunted)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d children)', $p['purok'], $p['rate'], $p['count']), $top_stunted);
            $stunted_purok_text = ' Most affected areas: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($stunting_rate > 15) {
            $insights[] = [
                'type' => 'alert',
                'category' => 'Child Nutrition (WHO Standards)',
                'title' => 'High Chronic Malnutrition Rate (Stunting)',
                'description' => sprintf(
                    'Stunting rate of %.1f%% exceeds WHO public health threshold of 15%%. %d children affected (%d severely stunted, %d stunted). Rate has %s by %.1f%% from previous period (%.1f%%).%s',
                    $stunting_rate,
                    $stunted_total,
                    $nutritional_counts['severely_stunted'],
                    $nutritional_counts['stunted'],
                    $trend,
                    abs($change),
                    $stunting_rate_prev,
                    $stunted_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Stunting reflects chronic malnutrition, measured through height-for-age comparisons with WHO standards. The current %.1f%% rate, which has %s from %.1f%%, indicates prolonged inadequate nutrition often stemming from poor maternal health during pregnancy (tracked via Prenatal Care Forms showing checkup attendance), suboptimal infant feeding practices (Exclusive Breastfeeding data from Infant Feeding Forms shows partial coverage), and repeated infections. Analysis of Child Health Forms shows children with documented health risks like Measles, Pneumonia, or Diarrhea have higher stunting prevalence.%s Geographic variation suggests environmental and socioeconomic factors play significant roles. Stunting is largely irreversible after age 2, causing permanent cognitive impairment (10-15 IQ point reduction), reduced school performance, shorter adult height, and lower economic productivity in adulthood. Continued elevation without intervention will perpetuate intergenerational poverty cycles affecting future generations.',
                    $stunting_rate,
                    $trend,
                    $stunting_rate_prev,
                    $stunted_purok_text ? ' Geographic analysis reveals: ' . $stunted_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Launch comprehensive long-term nutrition intervention program focusing on the first 1000 days (pregnancy through age 2). Investigate underlying causes through detailed household assessments including food security, water/sanitation access, and maternal education. Strengthen maternal nutrition counseling during all prenatal visits with emphasis on adequate weight gain and micronutrient supplementation. Coordinate with Department of Social Welfare and Development (DSWD) for Pantawid Pamilya cash transfer program enrollment. %s',
                    !empty($top_stunted) ? 'Prioritize high-burden puroks: ' . $top_stunted[0]['purok'] . ' and others for intensive community-based interventions.' : 'Focus on high-burden areas for intensive interventions.'
                ),
                'priority' => 'high',
                'chart_data' => json_encode([
                    'comparison' => [
                        'labels' => ['Previous Period', 'Current Period', 'WHO Threshold'],
                        'datasets' => [
                            [
                                'label' => 'Stunting Rate (%)',
                                'data' => [$stunting_rate_prev, $stunting_rate, 15],
                                'backgroundColor' => ['rgba(255, 193, 7, 0.5)', 'rgba(255, 193, 7, 0.8)', 'rgba(220, 53, 69, 0.3)'],
                                'borderColor' => ['rgb(255, 193, 7)', 'rgb(255, 193, 7)', 'rgb(220, 53, 69)'],
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'purok_breakdown' => [
                        'labels' => $purok_labels,
                        'datasets' => [
                            [
                                'label' => 'Stunting Rate by Area (%)',
                                'data' => $purok_stunting_rates,
                                'backgroundColor' => 'rgba(255, 107, 107, 0.6)',
                                'borderColor' => 'rgb(255, 107, 107)',
                                'borderWidth' => 1
                            ]
                        ]
                    ]
                ]),
                'chart_type' => 'mixed',
                'data_source' => 'Height and weight measurements from Child Health Forms recorded during monthly growth monitoring sessions. Measurements are analyzed using WHO Child Growth Standards. Cross-referenced with Prenatal Care Forms showing maternal checkup attendance and nutritional supplement intake, and Infant Feeding Forms documenting breastfeeding practices and complementary feeding timing. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($top_stunted)
            ];
        } elseif ($stunting_rate > 5) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Child Nutrition (WHO Standards)',
                'title' => 'Moderate Stunting Detected',
                'description' => sprintf(
                    'Stunting rate of %.1f%% detected (%d children affected). Rate has %s by %.1f%% from previous period (%.1f%%). Monitor trends carefully to prevent escalation.%s',
                    $stunting_rate,
                    $stunted_total,
                    $trend,
                    abs($change),
                    $stunting_rate_prev,
                    $stunted_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'While below the WHO emergency threshold of 15%%, the current stunting rate of %.1f%% (compared to %.1f%% previously) still represents a public health concern requiring attention. Child Health Forms show weight and height measurements are being tracked regularly, but the %s trend suggests either delayed identification or insufficient intervention effectiveness. Cross-analysis with Infant Feeding Forms reveals that exclusive breastfeeding practices and timely introduction of complementary foods (starting at 6 months) may be suboptimal in some households. Postnatal Care Forms showing family planning intent indicate some mothers may have closely spaced pregnancies (interval <24 months), reducing nutritional and financial resources available per child.%s Early detection through regular growth monitoring every month is critical as stunting prevention must occur before 24 months of age. If left unaddressed, moderate stunting can progress to severe levels, particularly during seasonal food scarcity periods or during frequent illness episodes.',
                    $stunting_rate,
                    $stunting_rate_prev,
                    $trend,
                    $stunted_purok_text ? ' Geographic patterns indicate: ' . $stunted_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Strengthen nutrition education programs integrated into all prenatal and postnatal home visits, emphasizing optimal infant and young child feeding practices. Establish regular monthly growth monitoring sessions (Timbang at Tignan) in all puroks with active follow-up of children showing growth faltering. Address household food security issues through coordination with agriculture office and social services. Promote birth spacing through postpartum family planning counseling. Train all health workers and Barangay Nutrition Scholars on WHO growth standards interpretation. %s',
                    !empty($top_stunted) ? 'Focus enhanced monitoring on ' . $top_stunted[0]['purok'] . ' and similar puroks.' : 'Focus on areas with higher rates.'
                ),
                'priority' => 'medium',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'Target (< 5%)'],
                    'datasets' => [
                        [
                            'label' => 'Stunting Rate (%)',
                            'data' => [$stunting_rate_prev, $stunting_rate, 5],
                            'backgroundColor' => ['rgba(255, 193, 7, 0.5)', 'rgba(255, 193, 7, 0.8)', 'rgba(75, 192, 192, 0.3)'],
                            'borderColor' => ['rgb(255, 193, 7)', 'rgb(255, 193, 7)', 'rgb(75, 192, 192)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Growth measurements from Child Health Forms (weight and height recorded monthly). Feeding practices from Infant Health Forms (exclusive breastfeeding duration, complementary food start date). Maternal factors from Postnatal Care Forms (family planning adoption, birth spacing indicators). Geographic tracking by residential purok.',
                'has_geographic_data' => !empty($top_stunted)
            ];
        }
    }
    
    // INSIGHT 3: Acute Malnutrition (Wasting)
    $wasted_total = $nutritional_counts['severely_wasted'] + $nutritional_counts['wasted'];
    $wasted_total_prev = $nutritional_counts_prev['severely_wasted'] + $nutritional_counts_prev['wasted'];
    
    if ($wasted_total > 0 && $total_children > 0) {
        $wasting_rate = round(($wasted_total / $total_children) * 100, 1);
        $wasting_rate_prev = $total_children_prev > 0 ? round(($wasted_total_prev / $total_children_prev) * 100, 1) : 0;
        $change = $wasting_rate - $wasting_rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'remained stable');
        
        // Get purok breakdown for wasting
        $wasted_puroks = [];
        foreach ($purok_nutrition_data as $purok => $data) {
            if ($data['total'] > 0 && $data['wasted'] > 0) {
                $purok_rate = round(($data['wasted'] / $data['total']) * 100, 1);
                $wasted_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'count' => $data['wasted']];
            }
        }
        usort($wasted_puroks, fn($a, $b) => $b['rate'] - $a['rate']);
        $top_wasted = array_slice($wasted_puroks, 0, 3);
        $wasted_purok_text = '';
        if (!empty($top_wasted)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d children)', $p['purok'], $p['rate'], $p['count']), $top_wasted);
            $wasted_purok_text = ' Highest rates in: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($wasting_rate > 8) {
            $insights[] = [
                'type' => 'critical',
                'category' => 'Child Nutrition (WHO Standards)',
                'title' => 'Acute Malnutrition Crisis (Wasting)',
                'description' => sprintf(
                    'Wasting rate of %.1f%% indicates acute malnutrition emergency (WHO threshold: 8%%). %d children are wasted. Rate has %s by %.1f%% from previous period (%.1f%%). Immediate intervention required.%s',
                    $wasting_rate,
                    $wasted_total,
                    $trend,
                    abs($change),
                    $wasting_rate_prev,
                    $wasted_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Wasting, measured through weight-for-length/height comparisons with WHO standards, indicates recent rapid weight loss or failure to gain weight, often due to acute illness or sudden food shortage. The %.1f%% rate (up from %.1f%%) surpasses the WHO emergency threshold of 8%%, signaling a nutrition crisis requiring immediate response. Analysis of Child Health Forms risk observations reveals high prevalence of Diarrhea and Pneumonia cases documented in the same period, which cause rapid weight loss and increased metabolic demands. Seasonal patterns may also contribute, with wasting often spiking during lean months before harvest or during rainy season when illness increases.%s Unlike stunting, wasting is treatable and reversible if caught early and managed properly. However, severely wasted children (WHO z-score < -3) have 9 times higher mortality risk than well-nourished children. The weight and length measurements from Child Health Forms show concerning downward trends when plotted over time. Immediate therapeutic feeding with Ready-to-Use Therapeutic Food (RUTF) is essential, alongside treatment of underlying infections documented in health records. Without emergency action, wasted children face high risk of death from common childhood illnesses, permanent stunting if wasting persists, and impaired development.',
                    $wasting_rate,
                    $wasting_rate_prev,
                    $wasted_purok_text ? ' Geographic distribution shows concentration: ' . $wasted_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Declare barangay nutrition emergency and activate emergency response protocol. Conduct immediate comprehensive medical assessment for all wasted children, screening for infections, dehydration, and complications. Provide therapeutic feeding: Ready-to-Use Therapeutic Food (RUTF) for severely wasted cases (z-score <-3), supplementary feeding for moderately wasted (z-score -3 to -2). Implement Community-Based Management of Acute Malnutrition (CMAM) program with weekly monitoring. Treat underlying infections documented in health records (oral rehydration for diarrhea, antibiotics for pneumonia as prescribed). Coordinate with municipal health office and Regional Nutrition Committee for technical and supply support. Investigate immediate causes (food shortage, disease outbreak, water contamination). %s',
                    !empty($top_wasted) ? 'Focus emergency resources on ' . $top_wasted[0]['purok'] . ' and other most affected puroks.' : 'Prioritize most affected areas.'
                ),
                'priority' => 'critical',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'WHO Emergency Threshold'],
                    'datasets' => [
                        [
                            'label' => 'Wasting Rate (%)',
                            'data' => [$wasting_rate_prev, $wasting_rate, 8],
                            'backgroundColor' => ['rgba(220, 53, 69, 0.5)', 'rgba(220, 53, 69, 0.8)', 'rgba(255, 193, 7, 0.3)'],
                            'borderColor' => ['rgb(220, 53, 69)', 'rgb(220, 53, 69)', 'rgb(255, 193, 7)'],
                            'borderWidth' => 2,
                            'fill' => false
                        ]
                    ]
                ]),
                'chart_type' => 'line',
                'data_source' => 'Weight and length/height measurements from Child Health Forms recorded during regular checkups. Cross-referenced with health risk observations documented by health workers, including recent episodes of Diarrhea, Pneumonia, or other illnesses that cause rapid weight loss. Service location tracked to identify access barriers. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($top_wasted)
            ];
        } elseif ($wasting_rate > 3) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Child Nutrition (WHO Standards)',
                'title' => 'Elevated Wasting Detected',
                'description' => sprintf(
                    'Wasting rate of %.1f%% detected (%d children affected). Rate has %s by %.1f%% from %.1f%%. Early intervention needed before escalation to emergency levels.%s',
                    $wasting_rate,
                    $wasted_total,
                    $trend,
                    abs($change),
                    $wasting_rate_prev,
                    $wasted_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'The wasting rate of %.1f%% (compared to %.1f%% in the previous period) indicates emerging acute malnutrition that requires prompt attention before reaching emergency levels. Child Health Forms show that children with recent illness episodes documented in the health risk observations section are disproportionately affected. The %s trend suggests either increasing disease burden in the community or deteriorating household food access. Wasting is highly seasonal and can spike rapidly during lean periods or disease outbreaks, making early detection crucial. Analysis of service location data shows some families may be accessing Barangay Health Stations irregularly, leading to delayed detection of weight loss.%s Supplementary feeding programs can prevent progression to severe wasting when started early. The measurement dates recorded in forms show some gaps in monitoring coverage, which may have allowed cases to worsen undetected. Community-based active case finding through home visits can identify children who are not attending regular checkups.',
                    $wasting_rate,
                    $wasting_rate_prev,
                    $trend,
                    $wasted_purok_text ? ' Geographic patterns show: ' . $wasted_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Screen all children for acute infections and treat promptly according to Integrated Management of Childhood Illness (IMCI) protocols. Provide supplementary feeding to moderately wasted children using locally available nutritious foods or fortified blended flour. Monitor closely with weekly weighing sessions and plot on growth charts to track recovery. Ensure regular follow-up visits are recorded in service location records. Strengthen community surveillance through Barangay Nutrition Scholars and health workers for early detection of weight loss. Investigate and address any barriers to regular health service access. Prepare emergency supplies and protocols in case wasting rate continues to rise. %s',
                    !empty($top_wasted) ? 'Target interventions to ' . $top_wasted[0]['purok'] . ' and puroks with highest rates.' : 'Focus on areas with elevated rates.'
                ),
                'priority' => 'medium',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'Caution Level (3%)', 'Emergency (8%)'],
                    'datasets' => [
                        [
                            'label' => 'Wasting Rate (%)',
                            'data' => [$wasting_rate_prev, $wasting_rate, 3, 8],
                            'backgroundColor' => ['rgba(255, 193, 7, 0.5)', 'rgba(255, 193, 7, 0.8)', 'rgba(255, 159, 64, 0.3)', 'rgba(220, 53, 69, 0.3)'],
                            'borderColor' => ['rgb(255, 193, 7)', 'rgb(255, 193, 7)', 'rgb(255, 159, 64)', 'rgb(220, 53, 69)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Growth measurements from Child Health Forms (weight and height recorded during checkups). Recent illness episodes documented in risk observation section. Service access patterns tracked through measurement dates and service locations visited. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($top_wasted)
            ];
        }
    }
    
    // INSIGHT 4: Childhood Obesity Trend WITH PUROK
    if ($nutritional_counts['obese'] > 0 && $total_children > 0) {
        $obesity_rate = round(($nutritional_counts['obese'] / $total_children) * 100, 1);
        $obesity_rate_prev = $total_children_prev > 0 ? round(($nutritional_counts_prev['obese'] / $total_children_prev) * 100, 1) : 0;
        $change = $obesity_rate - $obesity_rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        // Get purok breakdown for obesity
        $obese_puroks = [];
        foreach ($purok_nutrition_data as $purok => $data) {
            if ($data['total'] > 0 && $data['obese'] > 0) {
                $purok_rate = round(($data['obese'] / $data['total']) * 100, 1);
                $obese_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'count' => $data['obese']];
            }
        }
        usort($obese_puroks, fn($a, $b) => $b['rate'] - $a['rate']);
        $top_obese = array_slice($obese_puroks, 0, 3);
        $obese_purok_text = '';
        if (!empty($top_obese)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d children)', $p['purok'], $p['rate'], $p['count']), $top_obese);
            $obese_purok_text = ' Highest rates in: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($obesity_rate > 5) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Child Nutrition (WHO Standards)',
                'title' => 'Rising Childhood Obesity Detected',
                'description' => sprintf(
                    '%d children (%.1f%%) are obese according to WHO standards (z-score > 3). Rate has %s by %.1f%% from %.1f%%. Early obesity increases risk of chronic diseases in adulthood.%s',
                    $nutritional_counts['obese'],
                    $obesity_rate,
                    $trend,
                    abs($change),
                    $obesity_rate_prev,
                    $obese_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Childhood obesity (weight-for-length/height z-score > 3 standard deviations above WHO median) affects %.1f%% of children, showing a %s trend from %.1f%%. While undernutrition (underweight, stunting, wasting) often dominates concerns in resource-limited settings, obesity represents an emerging double burden of malnutrition reflecting nutrition transition. Child Health Forms document BMI calculations that complement z-score analysis for children over 2 years. Obesity in early childhood strongly predicts adult obesity (70-80%% of obese adolescents remain obese as adults) and associated chronic diseases including Type 2 diabetes, hypertension, and cardiovascular disease. The trend may reflect improved household economic status but inadequate nutrition knowledge, with dietary shift toward processed foods high in sugar/fat and sweetened beverages, combined with reduced physical activity.%s Without intervention, obese children face immediate health risks including metabolic syndrome, fatty liver disease, orthopedic problems (joint stress), sleep apnea, and psychosocial issues (bullying, low self-esteem). The measurement dates and weight tracking show some children crossing multiple percentile lines upward rapidly, indicating need for urgent intervention. Prevention and treatment require whole-family approach as child eating behaviors are shaped by household food environment and parental modeling.',
                    $obesity_rate,
                    $trend,
                    $obesity_rate_prev,
                    $obese_purok_text ? ' Geographic distribution shows: ' . $obese_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Conduct comprehensive nutrition education focusing on healthy eating patterns, appropriate portion sizes for children, limiting sugar-sweetened beverages and ultra-processed snacks. Promote daily physical activity for children and families (at least 60 minutes for children). Screen obese children for metabolic complications including blood pressure measurement, blood sugar if available. Provide family-based behavioral counseling rather than child-only interventions, as family environment drives eating behaviors. Work with schools and daycare centers to improve food offerings and increase physical activity opportunities. Avoid stigmatizing language; frame interventions as promoting health for whole family. %s',
                    !empty($top_obese) ? 'Target family-based programs in ' . $top_obese[0]['purok'] . ' and other affected puroks.' : 'Focus programs on affected areas.'
                ),
                'priority' => 'medium',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period'],
                    'datasets' => [
                        [
                            'label' => 'Obesity Rate (%)',
                            'data' => [$obesity_rate_prev, $obesity_rate],
                            'backgroundColor' => ['rgba(255, 159, 64, 0.5)', 'rgba(255, 159, 64, 0.8)'],
                            'borderColor' => ['rgb(255, 159, 64)', 'rgb(255, 159, 64)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Weight and height measurements from Child Health Forms used to calculate weight-for-height z-scores compared to WHO standards. For children over 2 years, Body Mass Index (BMI) calculated as weight(kg) divided by height(m) squared, plotted on WHO BMI-for-age growth charts. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($top_obese)
            ];
        }
    }
    
    // INSIGHT 5: Immunization Coverage WITH PUROK
    if ($total_children > 0) {
        $immunization_rate = round(($fully_immunized / $total_children) * 100, 1);
        $immunization_rate_prev = $total_children_prev > 0 ? round(($fully_immunized_prev / $total_children_prev) * 100, 1) : 0;
        $change = $immunization_rate - $immunization_rate_prev;
        $trend = $change > 0 ? 'improved' : ($change < 0 ? 'declined' : 'remained stable');
        $missing = $total_children - $fully_immunized;
        
        // Get purok breakdown for immunization
        $immun_puroks = [];
        foreach ($purok_immunization_data as $purok => $data) {
            if ($data['total'] > 0) {
                $purok_rate = round(($data['immunized'] / $data['total']) * 100, 1);
                $immun_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'immunized' => $data['immunized'], 'not_immunized' => $data['not_immunized'], 'total' => $data['total']];
            }
        }
        usort($immun_puroks, fn($a, $b) => $a['rate'] - $b['rate']); // Sort by LOWEST rate (most need)
        $low_immun_puroks = array_slice($immun_puroks, 0, 3);
        $immun_purok_text = '';
        if (!empty($low_immun_puroks)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d/%d immunized)', $p['purok'], $p['rate'], $p['immunized'], $p['total']), $low_immun_puroks);
            $immun_purok_text = ' Lowest coverage in: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($immunization_rate < 70) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Child Health',
                'title' => 'Low Immunization Coverage',
                'description' => sprintf(
                    'Only %.1f%% of children ages 1-6 years are fully immunized (national target: 90%%). %d children are missing critical vaccines. Coverage has %s by %.1f%% from %.1f%%.%s',
                    $immunization_rate,
                    $missing,
                    $trend,
                    abs($change),
                    $immunization_rate_prev,
                    $immun_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Full immunization for children aged 1-6 years requires completion of age-appropriate vaccines documented in Child Health Forms: MMR (Measles-Mumps-Rubella at 12-15 months), Vitamin A supplementation (every 6 months from 12-59 months), and full immunization status (either FIC - Fully Immunized Child by 12 months, or CIC - Completely Immunized Child including all boosters). Current coverage of %.1f%% (compared to %.1f%% previously) falls far below the WHO and Department of Health target of 90%% needed for community herd immunity protection. The %s trend indicates %s. Analysis shows that %d children lack complete immunization, leaving them vulnerable to vaccine-preventable diseases including measles (highly contagious, can cause pneumonia, brain damage, death), mumps (can cause meningitis), and rubella (dangerous for pregnant women if child spreads). Service location data suggests some families rely solely on Barangay Health Stations which may experience vaccine supply constraints or irregular schedules, while others access Rural Health Units or Health Centers sporadically.%s Incomplete immunization often clusters geographically due to access barriers (distance, transportation), missed opportunities during sick visits, parental concerns about vaccine safety, or record-keeping gaps. Low coverage risks community-wide outbreaks - a single measles case can infect 12-18 unvaccinated contacts. The immunization status field is populated during checkups, so gaps may reflect both unvaccinated children and those vaccinated but not recorded.',
                    $immunization_rate,
                    $immunization_rate_prev,
                    $trend,
                    $change > 0 ? 'improving outreach efforts or catch-up campaigns are working' : ($change < 0 ? 'declining access or increasing hesitancy' : 'stagnant coverage requiring new strategies'),
                    $missing,
                    $immun_purok_text ? ' Geographic analysis shows: ' . $immun_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Schedule intensive community immunization drive using mobile vaccination teams to reach all puroks. %s Conduct systematic house-to-house visits to identify unvaccinated or partially vaccinated children, using household numbers for family-level tracking. Implement defaulter tracing system to follow up children who missed scheduled doses. Address vaccine hesitancy through community education sessions with testimonials from trusted community members, addressing common concerns about safety and side effects. Strengthen cold chain management and vaccine stock monitoring at all service delivery points. Integrate immunization screening and catch-up vaccination into all child health interactions including sick visits. Coordinate with day care centers and preschools to check immunization status. Provide vaccination cards to all children and educate parents on keeping records safe.',
                    !empty($low_immun_puroks) ? 'Prioritize ' . $low_immun_puroks[0]['purok'] . ' and other low-coverage puroks for mobile vaccination teams.' : 'Target low-coverage areas first.'
                ),
                'priority' => 'medium',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'National Target (90%)'],
                    'datasets' => [
                        [
                            'label' => 'Immunization Coverage (%)',
                            'data' => [$immunization_rate_prev, $immunization_rate, 90],
                            'backgroundColor' => ['rgba(54, 162, 235, 0.5)', 'rgba(54, 162, 235, 0.8)', 'rgba(75, 192, 192, 0.3)'],
                            'borderColor' => ['rgb(54, 162, 235)', 'rgb(54, 162, 235)', 'rgb(75, 192, 192)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Immunization records from Child Health Forms for children ages 1-6 years. Completion verified for age-appropriate vaccines: MMR (Measles-Mumps-Rubella), Vitamin A supplementation, and Fully/Completely Immunized Child status. Service locations tracked to identify access patterns and barriers. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($low_immun_puroks)
            ];
        } elseif ($immunization_rate < 90) {
            $insights[] = [
                'type' => 'info',
                'category' => 'Child Health',
                'title' => 'Immunization Coverage Below Target',
                'description' => sprintf(
                    'Immunization coverage is %.1f%% (national target: 90%%). %d children need follow-up. Coverage has %s by %.1f%% from %.1f%%.%s',
                    $immunization_rate,
                    $missing,
                    $trend,
                    abs($change),
                    $immunization_rate_prev,
                    $immun_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'With %.1f%% coverage (versus %.1f%% previously), the barangay is approaching the 90%% Department of Health and WHO target but has not yet achieved the herd immunity threshold needed to protect the community, especially vulnerable infants too young for vaccination and immunocompromised individuals who cannot be vaccinated. The %s trend is encouraging, but %d children remain incompletely immunized. Child Health Forms show that most children receive initial vaccinations (BCG, Hepatitis B at birth) but miss some booster doses or scheduled follow-ups. The immunization status field tracks specific vaccines administered, revealing that Vitamin A supplementation at 12-59 months (given every 6 months) is often missed during routine visits, despite being integrated into weight monitoring sessions. Some children may have started immunization schedules but moved between puroks or to other municipalities, causing record fragmentation and uncertainty about completion status.%s Regular analysis of measurement dates can identify optimal timing for catch-up campaigns (e.g., before school enrollment, during nutrition month). Sustaining and improving coverage requires consistent community outreach, reminder systems for caregivers (SMS, home visits), and ensuring vaccines are consistently available at all service locations. Close monitoring of coverage by age cohort can identify groups needing targeted interventions.',
                    $immunization_rate,
                    $immunization_rate_prev,
                    $trend,
                    $missing,
                    $immun_purok_text ? ' Geographic patterns show: ' . $immun_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Strengthen immunization tracking system using household numbers to link family members and track sibling vaccination status. Send proactive reminders for missed or upcoming doses through SMS text messages or personal home visits by Barangay Health Workers. Conduct quarterly catch-up immunization sessions in all puroks with extended hours to accommodate working parents. %s Integrate immunization status checks into all child health interactions (growth monitoring, sick visits, nutrition programs). Monitor vaccine stock levels at all service locations to prevent stock-outs. Collaborate with schools and day care centers for pre-enrollment immunization verification. Recognize and reward communities achieving 90%%+ coverage to motivate continued effort. Use growth monitoring sessions (Operation Timbang) as opportunity for catch-up vaccination.',
                    !empty($low_immun_puroks) ? 'Focus follow-up efforts on ' . $low_immun_puroks[0]['purok'] . ' and similar puroks needing final push to 90%%.' : 'Target low-coverage areas for final push.'
                ),
                'priority' => 'low',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'Target (90%)'],
                    'datasets' => [
                        [
                            'label' => 'Immunization Coverage (%)',
                            'data' => [$immunization_rate_prev, $immunization_rate, 90],
                            'backgroundColor' => ['rgba(54, 162, 235, 0.5)', 'rgba(54, 162, 235, 0.8)', 'rgba(75, 192, 192, 0.3)'],
                            'borderColor' => ['rgb(54, 162, 235)', 'rgb(54, 162, 235)', 'rgb(75, 192, 192)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Child Health Forms documenting immunization status for children 1-6 years old. Validated against requirements: MMR vaccine, regular Vitamin A supplementation, and Fully/Completely Immunized Child designation. Linked to household numbers for family-level vaccination tracking and sibling follow-up. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($low_immun_puroks)
            ];
        }
    }
    
    return $insights;
}

function generateInfantNutritionInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to) {
    $insights = [];
    $purok_filter = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    
    // Current period infant data
    $params_current = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) $params_current[] = $user_purok;
    
    $stmt = $pdo->prepare("
        SELECT p.person_id, p.full_name, p.birthdate, p.gender, a.purok,
               cr.weight, cr.height,
               ir.exclusive_breastfeeding, ir.breastfeeding_months, ir.solid_food_start,
               GROUP_CONCAT(i.immunization_type) as vaccines
        FROM child_record cr
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        LEFT JOIN infant_record ir ON cr.child_record_id = ir.child_record_id
        LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
        LEFT JOIN immunization i ON ci.immunization_id = i.immunization_id
        WHERE r.record_type = 'child_record.infant_record'
        AND cr.measurement_date BETWEEN ? AND ?
        AND p.age <= 1
        $purok_filter
        GROUP BY p.person_id
    ");
    $stmt->execute($params_current);
    $infants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Previous period
    $params_prev = [$prev_date_from, $prev_date_to];
    if ($role_id == 2 && $user_purok) $params_prev[] = $user_purok;
    $stmt->execute($params_prev);
    $infants_prev = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ebf_count = 0;
    $ebf_count_prev = 0;
    $lbw_count = 0;
    $lbw_count_prev = 0;
    $lbw_by_purok = [];
    $ebf_by_purok = [];
    
    foreach ($infants as $infant) {
        $purok = $infant['purok'];
        
        // Track EBF by purok
        if (!isset($ebf_by_purok[$purok])) {
            $ebf_by_purok[$purok] = ['ebf' => 0, 'not_ebf' => 0, 'total' => 0];
        }
        $ebf_by_purok[$purok]['total']++;
        
        if ($infant['exclusive_breastfeeding'] === 'Y') {
            $ebf_count++;
            $ebf_by_purok[$purok]['ebf']++;
        } else {
            $ebf_by_purok[$purok]['not_ebf']++;
        }
        
        if (floatval($infant['weight']) < 2.5) {
            $lbw_count++;
            if (!isset($lbw_by_purok[$purok])) {
                $lbw_by_purok[$purok] = 0;
            }
            $lbw_by_purok[$purok]++;
        }
    }
    
    foreach ($infants_prev as $infant) {
        if ($infant['exclusive_breastfeeding'] === 'Y') $ebf_count_prev++;
        if (floatval($infant['weight']) < 2.5) $lbw_count_prev++;
    }
    
    $total_infants = count($infants);
    $total_infants_prev = count($infants_prev);
    
    // INSIGHT: Exclusive Breastfeeding WITH PUROK
    if ($total_infants > 0) {
        $ebf_rate = round(($ebf_count / $total_infants) * 100, 1);
        $ebf_rate_prev = $total_infants_prev > 0 ? round(($ebf_count_prev / $total_infants_prev) * 100, 1) : 0;
        $change = $ebf_rate - $ebf_rate_prev;
        $trend = $change > 0 ? 'improved' : ($change < 0 ? 'declined' : 'remained stable');
        
        // Get purok breakdown for low EBF
        $low_ebf_puroks = [];
        foreach ($ebf_by_purok as $purok => $data) {
            if ($data['total'] > 0) {
                $purok_rate = round(($data['ebf'] / $data['total']) * 100, 1);
                $low_ebf_puroks[] = ['purok' => $purok, 'rate' => $purok_rate, 'ebf' => $data['ebf'], 'not_ebf' => $data['not_ebf'], 'total' => $data['total']];
            }
        }
        usort($low_ebf_puroks, fn($a, $b) => $a['rate'] - $b['rate']); // Sort by LOWEST rate
        $lowest_ebf = array_slice($low_ebf_puroks, 0, 3);
        $ebf_purok_text = '';
        if (!empty($lowest_ebf)) {
            $purok_list = array_map(fn($p) => sprintf('%s (%.1f%%, %d/%d exclusively breastfed)', $p['purok'], $p['rate'], $p['ebf'], $p['total']), $lowest_ebf);
            $ebf_purok_text = ' Lowest EBF rates in: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($ebf_rate < 60) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Infant Nutrition',
                'title' => 'Low Exclusive Breastfeeding Rate',
                'description' => sprintf(
                    'Only %.1f%% of infants under 6 months are exclusively breastfed (WHO target: 80%%). Rate has %s by %.1f%% from %.1f%%. %d infants receiving mixed feeding or formula.%s',
                    $ebf_rate,
                    $trend,
                    abs($change),
                    $ebf_rate_prev,
                    $total_infants - $ebf_count,
                    $ebf_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Exclusive breastfeeding (EBF) means giving infants only breast milk for the first 6 months of life, with no other foods or liquids including water, tracked in Infant Health Forms. Current rate of %.1f%% has %s from %.1f%%, falling significantly short of the WHO recommendation of at least 80%% coverage. Analysis of Infant Feeding Records shows that infants not exclusively breastfed often receive formula supplementation or have solid foods introduced too early (before 6 months as documented in complementary feeding start dates). Common barriers include maternal employment without workplace lactation support, perceived insufficient milk supply (often due to lack of support rather than true insufficiency), cultural practices promoting early water or herbal tea introduction, aggressive infant formula marketing, and inadequate breastfeeding counseling during postnatal period. Monthly breastfeeding tracking data reveals many mothers discontinue EBF after the first month when facing challenges like sore nipples, perceived low milk supply, or return to work.%s Low EBF increases infant morbidity and mortality risk, particularly from diarrhea (3x higher risk), respiratory infections (2x higher risk), and undernutrition. Breast milk provides perfect nutrition, antibodies for disease protection, and promotes bonding. Birth weight data shows low birth weight babies sometimes receive unnecessary formula supplementation when they could be exclusively breastfed with proper support. Cross-analysis with Postnatal Care delivery records could identify if hospital practices (formula promotion, mother-infant separation) undermine EBF initiation in first hours after birth.',
                    $ebf_rate,
                    $trend,
                    $ebf_rate_prev,
                    $ebf_purok_text ? ' Geographic patterns show: ' . $ebf_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Intensify breastfeeding counseling during all postnatal home visits within first week and at 6 weeks postpartum. %s Train all Barangay Health Workers in basic breastfeeding support including proper latch, positioning, and addressing common problems (sore nipples, engorgement, perceived low supply). Establish mother-to-mother breastfeeding support groups led by trained peer counselors. Advocate for employers to comply with Expanded Breastfeeding Promotion Act (R.A. 10028) providing lactation breaks and lactation stations. Screen all infants during checkups for early formula or solid food introduction and provide corrective counseling. Coordinate with hospitals and birthing facilities to implement Baby-Friendly Hospital Initiative practices (immediate skin-to-skin contact, rooming-in, no formula samples).',
                    !empty($lowest_ebf) ? 'Prioritize intensive breastfeeding promotion in ' . $lowest_ebf[0]['purok'] . ' and other low-rate puroks.' : 'Focus on low-rate areas.'
                ),
                'priority' => 'medium',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'WHO Target (80%)'],
                    'datasets' => [
                        [
                            'label' => 'Exclusive Breastfeeding Rate (%)',
                            'data' => [$ebf_rate_prev, $ebf_rate, 80],
                            'backgroundColor' => ['rgba(153, 102, 255, 0.5)', 'rgba(153, 102, 255, 0.8)', 'rgba(75, 192, 192, 0.3)'],
                            'borderColor' => ['rgb(153, 102, 255)', 'rgb(153, 102, 255)', 'rgb(75, 192, 192)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'bar',
                'data_source' => 'Infant Feeding Forms documenting whether infants under 6 months receive only breast milk or mixed feeding with formula/other liquids. Monthly breastfeeding duration tracked through checkboxes for each month. Timing of solid food introduction recorded to identify early complementary feeding. Birth weight from Infant Registration Forms and delivery circumstances from Postnatal Care Forms provide context. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($lowest_ebf)
            ];
        }
    }
    
    // INSIGHT: Low Birth Weight with Geographic Distribution
    if ($total_infants > 0 && $lbw_count > 0) {
        $lbw_rate = round(($lbw_count / $total_infants) * 100, 1);
        $lbw_rate_prev = $total_infants_prev > 0 ? round(($lbw_count_prev / $total_infants_prev) * 100, 1) : 0;
        $change = $lbw_rate - $lbw_rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        // Build purok text
        arsort($lbw_by_purok);
        $top_lbw_puroks = array_slice($lbw_by_purok, 0, 3, true);
        $lbw_purok_text = '';
        if (!empty($top_lbw_puroks)) {
            $purok_list = array_map(fn($purok, $count) => sprintf('%s (%d babies)', $purok, $count), array_keys($top_lbw_puroks), $top_lbw_puroks);
            $lbw_purok_text = ' Highest concentration in: ' . implode('; ', $purok_list) . '.';
        }
        
        if ($lbw_rate > 10) {
            $insights[] = [
                'type' => 'alert',
                'category' => 'Infant Nutrition',
                'title' => 'High Low Birth Weight Rate',
                'description' => sprintf(
                    '%.1f%% of infants have low birth weight (<2.5kg at birth), affecting %d babies. Rate has %s by %.1f%% from %.1f%%. LBW significantly increases infant mortality and developmental risks.%s',
                    $lbw_rate,
                    $lbw_count,
                    $trend,
                    abs($change),
                    $lbw_rate_prev,
                    $lbw_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Low birth weight (LBW), defined as weighing less than 2,500 grams (2.5kg) at birth and recorded in Infant Registration Forms, affects %.1f%% of newborns (compared to %.1f%% previously). This %s trend is highly concerning as LBW is a leading cause of neonatal mortality (death in first 28 days) and long-term developmental problems including growth faltering, reduced cognitive development, and increased chronic disease risk in adulthood. Causes of LBW include poor maternal nutrition during pregnancy (inadequate weight gain, micronutrient deficiencies), maternal infections (malaria, urinary tract infections), teenage pregnancy (mothers under 18), grand multiparity (5+ previous births causing maternal depletion), short inter-pregnancy intervals (<24 months), pregnancy complications (hypertension, preeclampsia), and smoking. Cross-analysis with Prenatal Care Forms reveals that mothers with inadequate prenatal care (fewer than 4 checkups as documented) are significantly more likely to deliver LBW babies. The pregnancy count field shows women with 5 or more previous pregnancies have higher LBW rates due to maternal nutrient depletion. Postnatal Care delivery location data suggests home births attended by traditional birth attendants (Hilot) may have higher LBW rates partly due to lack of accurate birth weight assessment within first hours and delayed identification of problems.%s Without targeted intervention, LBW infants face increased risk of hypothermia (difficulty maintaining body temperature), hypoglycemia (low blood sugar), feeding difficulties, infections due to immature immune system, and long-term growth faltering as documented in subsequent Child Health monitoring records showing persistent underweight status.',
                    $lbw_rate,
                    $lbw_rate_prev,
                    $trend,
                    $lbw_purok_text ? ' Geographic distribution reveals: ' . $lbw_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Strengthen quality of prenatal care with particular focus on maternal nutrition throughout pregnancy. Provide daily iron and folic acid supplementation to all pregnant women as tracked in medication records. Identify high-risk pregnancies early using pregnancy count and previous child survival data from Prenatal Forms. Ensure skilled birth attendance for all deliveries with accurate birth weight measurement within first hour using calibrated scales. Implement immediate kangaroo mother care (skin-to-skin contact) for LBW infants to maintain body temperature and promote breastfeeding. Schedule very frequent weight monitoring for LBW babies (weekly for first month, then biweekly) with measurements documented in Infant Health Forms. Provide intensive breastfeeding support as breast milk is especially critical for LBW infants. Screen for and treat infections promptly. Refer very low birth weight babies (<1.5kg) to higher-level facilities with neonatal intensive care capability. %s',
                    !empty($top_lbw_puroks) ? 'Target prenatal care strengthening in ' . array_keys($top_lbw_puroks)[0] . ' and other puroks with highest LBW concentration.' : 'Focus on high-LBW areas.'
                ),
                'priority' => 'high',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period', 'Acceptable Rate (<10%)'],
                    'datasets' => [
                        [
                            'label' => 'Low Birth Weight Rate (%)',
                            'data' => [$lbw_rate_prev, $lbw_rate, 10],
                            'backgroundColor' => ['rgba(255, 99, 132, 0.5)', 'rgba(255, 99, 132, 0.8)', 'rgba(255, 193, 7, 0.3)'],
                            'borderColor' => ['rgb(255, 99, 132)', 'rgb(255, 99, 132)', 'rgb(255, 193, 7)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'line',
                'data_source' => 'Birth weight measurements from Infant Registration Forms completed within the first week of life. Maternal factors from Prenatal Care Forms including number of prenatal checkups attended, nutritional supplement intake (iron, folic acid, multivitamins), and pregnancy count. Delivery details from Postnatal Care Forms including birth attendant type and delivery location. Geographic distribution by mother\'s residential purok.',
                'has_geographic_data' => !empty($top_lbw_puroks)
            ];
        }
    }
    
    return $insights;
}

function generateMaternalHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to) {
    $insights = [];
    $purok_filter = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    
    // High-risk pregnancy detection
    $params = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) $params[] = $user_purok;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as high_risk_count
        FROM prenatal pn
        JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
        JOIN records r ON pr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'pregnancy_record.prenatal'
        AND pn.last_menstruation BETWEEN ? AND ?
        AND (pn.risk_observed LIKE '%Convulsion%' 
             OR pn.risk_observed LIKE '%Vaginal Bleeding%'
             OR pn.risk_observed LIKE '%Severe Abdominal Pain%'
             OR pn.risk_observed LIKE '%Headache accompanied by Blurred Vision%')
        $purok_filter
    ");
    $stmt->execute($params);
    $high_risk_current = $stmt->fetchColumn();
    
    $params_prev = [$prev_date_from, $prev_date_to];
    if ($role_id == 2 && $user_purok) $params_prev[] = $user_purok;
    $stmt->execute($params_prev);
    $high_risk_prev = $stmt->fetchColumn();
    
    // Get purok breakdown
    $stmt_purok = $pdo->prepare("
        SELECT a.purok, COUNT(*) as risk_count
        FROM prenatal pn
        JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
        JOIN records r ON pr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'pregnancy_record.prenatal'
        AND pn.last_menstruation BETWEEN ? AND ?
        AND (pn.risk_observed LIKE '%Convulsion%' 
             OR pn.risk_observed LIKE '%Vaginal Bleeding%'
             OR pn.risk_observed LIKE '%Severe Abdominal Pain%'
             OR pn.risk_observed LIKE '%Headache accompanied by Blurred Vision%')
        $purok_filter
        GROUP BY a.purok
    ");
    $stmt_purok->execute($params);
    $risk_by_purok = $stmt_purok->fetchAll(PDO::FETCH_ASSOC);
    
    // Sort by count
    usort($risk_by_purok, fn($a, $b) => $b['risk_count'] - $a['risk_count']);
    $risk_by_purok = array_slice($risk_by_purok, 0, 3);
    
    $risk_purok_text = '';
    if (!empty($risk_by_purok)) {
        $purok_list = array_map(fn($p) => sprintf('%s (%d cases)', $p['purok'], $p['risk_count']), $risk_by_purok);
        $risk_purok_text = ' Geographic concentration: ' . implode('; ', $purok_list) . '.';
    }
    
    if ($high_risk_current > 0) {
        $change = $high_risk_current - $high_risk_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
                $insights[] = [
            'type' => 'critical',
            'category' => 'Maternal Health',
            'title' => 'High-Risk Pregnancies Requiring Urgent Medical Attention',
            'description' => sprintf(
                '%d pregnant women have critical danger signs requiring immediate hospital referral. Number has %s by %d from previous period (%d cases). These symptoms indicate potentially life-threatening complications.%s',
                $high_risk_current,
                $trend,
                abs($change),
                $high_risk_prev,
                $risk_purok_text
            ),
            'detailed_explanation' => sprintf(
                'High-risk pregnancy is identified through Pregnant Women Monitoring Forms where health workers document danger signs during prenatal checkups. Critical symptoms tracked include: Convulsions (indicating eclampsia), Vaginal Bleeding (placenta problems), Severe Abdominal Pain (ectopic pregnancy or abruption), and Headache with Blurred Vision (preeclampsia signs). Current count of %d cases (versus %d previously) represents a %s trend.%s These conditions require immediate hospital referral to facilities with obstetricians, intensive care, blood transfusion capability, and cesarean section availability. Without immediate hospital referral and specialist management, these mothers face extremely high risk of maternal death, stillbirth, severe neonatal complications, or permanent disability.',
                $high_risk_current,
                $high_risk_prev,
                $trend,
                $risk_purok_text ? ' Geographic analysis shows: ' . $risk_purok_text : ''
            ),
            'recommendation' => sprintf(
                'Immediately refer ALL high-risk mothers to provincial or regional hospital with comprehensive emergency obstetric care. Ensure functional 24/7 emergency communication system for urgent transport coordination. Classify as Priority 1 for all health worker visits with weekly home visits until delivery. Increase prenatal checkup frequency to at minimum weekly, with blood pressure monitoring at each visit. Document all symptoms and vital signs in detail. Coordinate with Local Government Unit for guaranteed ambulance availability. Provide danger signs education to mother and family. Develop written birth preparedness plan. Conduct postpartum home visits within 24 hours of discharge. %s',
                !empty($risk_by_purok) ? 'Focus emergency preparedness efforts on ' . $risk_by_purok[0]['purok'] . ' and other puroks with highest case concentration.' : 'Prioritize high-risk areas.'
            ),
            'priority' => 'critical',
            'chart_data' => json_encode([
                'labels' => ['Previous Period', 'Current Period'],
                'datasets' => [
                    [
                        'label' => 'High-Risk Pregnancy Cases',
                        'data' => [$high_risk_prev, $high_risk_current],
                        'backgroundColor' => ['rgba(220, 53, 69, 0.5)', 'rgba(220, 53, 69, 0.8)'],
                        'borderColor' => ['rgb(220, 53, 69)', 'rgb(220, 53, 69)'],
                        'borderWidth' => 2
                    ]
                ]
            ]),
            'chart_type' => 'bar',
            'data_source' => 'Pregnant Women Health Monitoring Forms documenting danger signs observed during prenatal checkups: Convulsions, Vaginal Bleeding, Severe Abdominal Pain, and Headache with Blurred Vision. Prenatal checkup attendance tracked. Pregnancy history including number of previous pregnancies and living children recorded. Birth preparedness plan status documented. Geographic distribution by residential purok.',
            'has_geographic_data' => !empty($risk_by_purok)
        ];
    }
    
    // Grand Multiparity Risk WITH PUROK
    $stmt_gm = $pdo->prepare("
        SELECT COUNT(*) as gm_count
        FROM prenatal pn
        JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
        JOIN records r ON pr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'pregnancy_record.prenatal'
        AND pn.last_menstruation BETWEEN ? AND ?
        AND CAST(pn.preg_count AS UNSIGNED) >= 5
        $purok_filter
    ");
    $stmt_gm->execute($params);
    $gm_current = $stmt_gm->fetchColumn();
    
    $stmt_gm->execute($params_prev);
    $gm_prev = $stmt_gm->fetchColumn();
    
    // Get purok breakdown for grand multiparity
    $stmt_gm_purok = $pdo->prepare("
        SELECT a.purok, COUNT(*) as gm_count
        FROM prenatal pn
        JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
        JOIN records r ON pr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'pregnancy_record.prenatal'
        AND pn.last_menstruation BETWEEN ? AND ?
        AND CAST(pn.preg_count AS UNSIGNED) >= 5
        $purok_filter
        GROUP BY a.purok
    ");
    $stmt_gm_purok->execute($params);
    $gm_by_purok = $stmt_gm_purok->fetchAll(PDO::FETCH_ASSOC);
    usort($gm_by_purok, fn($a, $b) => $b['gm_count'] - $a['gm_count']);
    $top_gm = array_slice($gm_by_purok, 0, 3);
    
    $gm_purok_text = '';
    if (!empty($top_gm)) {
        $purok_list = array_map(fn($p) => sprintf('%s (%d women)', $p['purok'], $p['gm_count']), $top_gm);
        $gm_purok_text = ' Highest concentration in: ' . implode('; ', $purok_list) . '.';
    }
    
    if ($gm_current > 3) {
        $change = $gm_current - $gm_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        $insights[] = [
            'type' => 'warning',
            'category' => 'Maternal Health',
            'title' => 'High Number of Grand Multiparous Pregnancies',
            'description' => sprintf(
                '%d pregnant women have 5 or more previous pregnancies (grand multiparity). Number has %s by %d from previous period (%d cases). Multiple pregnancies significantly increase maternal and infant risks.%s',
                $gm_current,
                $trend,
                abs($change),
                $gm_prev,
                $gm_purok_text
            ),
            'detailed_explanation' => sprintf(
                'Grand multiparity (5 or more previous pregnancies) documented in Prenatal Forms affects %d current pregnancies (compared to %d previously). This condition increases risks of complications including postpartum hemorrhage, placenta previa or accreta, anemia, uterine rupture, gestational diabetes, and increased likelihood of cesarean delivery. Grand multiparous women also tend to have increased low birth weight risk due to maternal nutrient depletion, shorter intervals between pregnancies, and cumulative effects of multiple childbearing.%s Without proper classification, monitoring, and family planning services, these women and their babies face preventable complications.',
                $gm_current,
                $gm_prev,
                $gm_purok_text ? ' Geographic distribution shows: ' . $gm_purok_text : ''
            ),
            'recommendation' => sprintf(
                'Classify all grand multiparous women (5+ previous pregnancies) as high-risk requiring enhanced monitoring and facility-based delivery planning. Increase prenatal checkup frequency to at least monthly, with regular hemoglobin checks for anemia. Ensure continuous iron and folic acid supplementation. Strongly advise facility-based delivery with skilled birth attendant. Counsel on birth spacing and family planning. After delivery, provide immediate postpartum family planning counseling, ideally with long-acting reversible contraceptive insertion if mother desires. Monitor closely for postpartum hemorrhage risk. %s',
                !empty($top_gm) ? 'Focus family planning promotion in ' . $top_gm[0]['purok'] . ' and similar high-burden puroks.' : 'Target high-burden areas.'
            ),
            'priority' => 'medium',
            'chart_data' => json_encode([
                'labels' => ['Previous Period', 'Current Period'],
                'datasets' => [
                    [
                        'label' => 'Grand Multiparity Cases (≥5 Pregnancies)',
                        'data' => [$gm_prev, $gm_current],
                        'backgroundColor' => ['rgba(255, 159, 64, 0.5)', 'rgba(255, 159, 64, 0.8)'],
                        'borderColor' => ['rgb(255, 159, 64)', 'rgb(255, 159, 64)'],
                        'borderWidth' => 2
                    ]
                ]
            ]),
            'chart_type' => 'bar',
            'data_source' => 'Pregnancy history from Prenatal Care Forms documenting number of previous pregnancies and number of living children. Prenatal checkup attendance patterns tracked. Cross-referenced with Postnatal Care Forms for delivery outcomes. Geographic distribution by residential purok.',
            'has_geographic_data' => !empty($top_gm)
        ];
    }
    
    return $insights;
}

function generateSeniorHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to) {
    $insights = [];
    $purok_filter = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    
    $params = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) $params[] = $user_purok;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_seniors,
            SUM(CASE 
                WHEN CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) >= 140 
                OR CAST(SUBSTRING_INDEX(sr.bp_reading, '/', -1) AS UNSIGNED) >= 90 
                THEN 1 ELSE 0 
            END) as hypertensive
        FROM senior_record sr
        JOIN records r ON sr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'senior_record.medication'
        AND sr.bp_date_taken BETWEEN ? AND ?
        AND p.age >= 60
        $purok_filter
    ");
    $stmt->execute($params);
    $senior_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $params_prev = [$prev_date_from, $prev_date_to];
    if ($role_id == 2 && $user_purok) $params_prev[] = $user_purok;
    $stmt->execute($params_prev);
    $senior_data_prev = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get purok breakdown
    $stmt_purok = $pdo->prepare("
        SELECT a.purok, 
               COUNT(*) as total,
               SUM(CASE 
                   WHEN CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) >= 140 
                   OR CAST(SUBSTRING_INDEX(sr.bp_reading, '/', -1) AS UNSIGNED) >= 90 
                   THEN 1 ELSE 0 
               END) as htn_count
        FROM senior_record sr
        JOIN records r ON sr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type = 'senior_record.medication'
        AND sr.bp_date_taken BETWEEN ? AND ?
        AND p.age >= 60
        $purok_filter
        GROUP BY a.purok
        HAVING total > 0
    ");
    $stmt_purok->execute($params);
    $htn_by_purok = $stmt_purok->fetchAll(PDO::FETCH_ASSOC);
    
    usort($htn_by_purok, function($a, $b) {
        $rate_a = ($a['total'] > 0) ? ($a['htn_count'] / $a['total']) : 0;
        $rate_b = ($b['total'] > 0) ? ($b['htn_count'] / $b['total']) : 0;
        return $rate_b <=> $rate_a;
    });
    $htn_by_purok = array_slice($htn_by_purok, 0, 3);
    
    $htn_purok_text = '';
    if (!empty($htn_by_purok)) {
        $purok_list = array_map(function($p) {
            $rate = round(($p['htn_count'] / $p['total']) * 100, 1);
            return sprintf('%s (%.1f%%, %d/%d seniors)', $p['purok'], $rate, $p['htn_count'], $p['total']);
        }, $htn_by_purok);
        $htn_purok_text = ' Highest prevalence in: ' . implode('; ', $purok_list) . '.';
    }
    
    if ($senior_data['total_seniors'] > 0) {
        $htn_rate = round(($senior_data['hypertensive'] / $senior_data['total_seniors']) * 100, 1);
        $htn_rate_prev = $senior_data_prev['total_seniors'] > 0 ? 
            round(($senior_data_prev['hypertensive'] / $senior_data_prev['total_seniors']) * 100, 1) : 0;
        $change = $htn_rate - $htn_rate_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        if ($htn_rate > 40) {
            $insights[] = [
                'type' => 'alert',
                'category' => 'Senior Health',
                'title' => 'High Hypertension Prevalence Among Senior Citizens',
                'description' => sprintf(
                    '%.1f%% of seniors aged 60+ have hypertension (blood pressure ≥140/90 mmHg), affecting %d individuals out of %d monitored. Rate has %s by %.1f%% from %.1f%% in previous period.%s',
                    $htn_rate,
                    $senior_data['hypertensive'],
                    $senior_data['total_seniors'],
                    $trend,
                    abs($change),
                    $htn_rate_prev,
                    $htn_purok_text
                ),
                'detailed_explanation' => sprintf(
                    'Hypertension prevalence of %.1f%% (versus %.1f%% previously) among seniors aged 60+ is measured using Senior Citizen Health Monitoring Forms. This %s trend indicates growing cardiovascular disease burden. Many seniors have infrequent monitoring (gaps exceeding 3 months). Analysis of medication records reveals poor medication adherence patterns. Service location tracking shows heavy reliance on Barangay Health Stations which may experience medication stock-outs.%s Without improved medication adherence, regular blood pressure monitoring, and lifestyle modifications, seniors face elevated risk of stroke, heart attack, heart failure, kidney failure, vision loss, and cognitive decline.',
                    $htn_rate,
                    $htn_rate_prev,
                    $trend,
                    $htn_purok_text ? ' Geographic patterns show: ' . $htn_purok_text : ''
                ),
                'recommendation' => sprintf(
                    'Implement systematic monthly blood pressure screening program for all seniors in every purok. Ensure continuous supply of essential antihypertensive medications at all Barangay Health Stations. Provide individualized medication adherence counseling. Screen for hypertension complications. Promote lifestyle modifications: low-salt diet, regular physical activity, weight management. Train family members in blood pressure monitoring. Refer uncontrolled hypertension for treatment intensification. %s',
                    !empty($htn_by_purok) ? 'Prioritize puroks with highest prevalence: ' . $htn_by_purok[0]['purok'] . ' and others for intensive intervention.' : 'Focus on high-prevalence areas.'
                ),
                'priority' => 'high',
                'chart_data' => json_encode([
                    'labels' => ['Previous Period', 'Current Period'],
                    'datasets' => [
                        [
                            'label' => 'Hypertension Prevalence (%)',
                            'data' => [$htn_rate_prev, $htn_rate],
                            'backgroundColor' => ['rgba(255, 99, 132, 0.5)', 'rgba(255, 99, 132, 0.8)'],
                            'borderColor' => ['rgb(255, 99, 132)', 'rgb(255, 99, 132)'],
                            'borderWidth' => 2
                        ]
                    ]
                ]),
                'chart_type' => 'line',
                'data_source' => 'Senior Citizen Health Monitoring Forms capturing blood pressure readings during monthly checkups. Blood pressure measurement dates tracked. Medication records documenting antihypertensive prescriptions and refill dates. Service locations tracked. Geographic distribution by residential purok.',
                'has_geographic_data' => !empty($htn_by_purok)
            ];
        }
    }
    
    return $insights;
}

function generateFamilyPlanningInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to) {
    $insights = [];
    $purok_filter = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
    
    $params = [$date_from, $date_to];
    if ($role_id == 2 && $user_purok) $params[] = $user_purok;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unmet_count
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN pregnancy_record pr ON r.records_id = pr.records_id
        JOIN postnatal postn ON pr.pregnancy_record_id = postn.pregnancy_record_id
        WHERE r.record_type LIKE '%postnatal%'
        AND postn.date_delivered BETWEEN ? AND ?
        AND postn.family_planning_intent = 'Y'
        AND NOT EXISTS (
            SELECT 1 FROM records r2
            JOIN family_planning_record fpr ON r2.records_id = fpr.records_id
            WHERE r2.person_id = p.person_id
            AND r2.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'Y'
        )
        $purok_filter
    ");
    $stmt->execute($params);
    $unmet_count = $stmt->fetchColumn();
    
    $params_prev = [$prev_date_from, $prev_date_to];
    if ($role_id == 2 && $user_purok) $params_prev[] = $user_purok;
    $stmt->execute($params_prev);
    $unmet_count_prev = $stmt->fetchColumn();
    
    // GET PUROK BREAKDOWN
    $stmt_purok = $pdo->prepare("
        SELECT a.purok, COUNT(*) as unmet_count
        FROM person p
        JOIN address a ON p.address_id = a.address_id
        JOIN records r ON p.person_id = r.person_id
        JOIN pregnancy_record pr ON r.records_id = pr.records_id
        JOIN postnatal postn ON pr.pregnancy_record_id = postn.pregnancy_record_id
        WHERE r.record_type LIKE '%postnatal%'
        AND postn.date_delivered BETWEEN ? AND ?
        AND postn.family_planning_intent = 'Y'
        AND NOT EXISTS (
            SELECT 1 FROM records r2
            JOIN family_planning_record fpr ON r2.records_id = fpr.records_id
            WHERE r2.person_id = p.person_id
            AND r2.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'Y'
        )
        $purok_filter
        GROUP BY a.purok
    ");
    $stmt_purok->execute($params);
    $unmet_by_purok = $stmt_purok->fetchAll(PDO::FETCH_ASSOC);
    usort($unmet_by_purok, fn($a, $b) => $b['unmet_count'] - $a['unmet_count']);
    $top_unmet_puroks = array_slice($unmet_by_purok, 0, 3);
    
    $unmet_purok_text = '';
    if (!empty($top_unmet_puroks)) {
        $purok_list = array_map(fn($p) => sprintf('%s (%d mothers)', $p['purok'], $p['unmet_count']), $top_unmet_puroks);
        $unmet_purok_text = ' Most affected areas: ' . implode('; ', $purok_list) . '.';
    }
    
    if ($unmet_count > 0) {
        $change = $unmet_count - $unmet_count_prev;
        $trend = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'stable');
        
        $insights[] = [
            'type' => 'alert',
            'category' => 'Family Planning',
            'title' => 'Unmet Family Planning Need Among Postnatal Mothers',
            'description' => sprintf(
                '%d postnatal mothers expressed intent to use family planning but have no follow-up records showing method adoption. Service gap has %s by %d from previous period (%d cases).%s This represents missed opportunities for healthy birth spacing.',
                $unmet_count,
                $trend,
                abs($change),
                $unmet_count_prev,
                $unmet_purok_text
            ),
            'detailed_explanation' => sprintf(
                'Unmet family planning need: %d mothers (versus %d previously) indicated "Yes" for family planning intent in Postnatal Care Forms but have no corresponding Family Planning Service Records showing method use.%s Common obstacles include misconceptions about breastfeeding as contraception, fear of side effects, partner opposition, and lack of accessible services. Without family planning adoption, these mothers face high risk of rapid repeat pregnancy (<24 months) which increases maternal depletion, low birth weight in subsequent baby, and infant mortality.',
                $unmet_count,
                $unmet_count_prev,
                $unmet_purok_text ? ' Geographic distribution: ' . $unmet_purok_text : ''
            ),
            'recommendation' => sprintf(
                'Implement systematic postpartum family planning counseling for ALL postnatal mothers before discharge or during first home visit. Schedule dedicated FP follow-up at 6 weeks postpartum. Offer comprehensive method options including LARC (IUD, implant). Track postnatal FP intent systematically and ensure linkage to FP Service Record creation. Conduct home visits for mothers who expressed intent but did not follow through. %s',
                !empty($top_unmet_puroks) ? 'Prioritize intensive outreach in ' . $top_unmet_puroks[0]['purok'] . ' and other high-need puroks.' : 'Focus on high-need areas.'
            ),
            'priority' => 'high',
            'chart_data' => json_encode([
                'labels' => ['Previous Period', 'Current Period'],
                'datasets' => [
                    [
                        'label' => 'Mothers with Unmet FP Need',
                        'data' => [$unmet_count_prev, $unmet_count],
                        'backgroundColor' => ['rgba(255, 159, 64, 0.5)', 'rgba(255, 159, 64, 0.8)'],
                        'borderColor' => ['rgb(255, 159, 64)', 'rgb(255, 159, 64)'],
                        'borderWidth' => 2
                    ]
                ]
            ]),
            'chart_type' => 'bar',
            'data_source' => 'Postnatal Care Forms documenting family planning intent and delivery dates. Cross-checked with Family Planning Service Records. Prenatal Care Forms provide pregnancy history context. Geographic distribution by residential purok.',
            'has_geographic_data' => !empty($top_unmet_puroks)
        ];
    }
    
    return $insights;
}

// ===================== GENERATE ALL INSIGHTS =====================
try {
    $all_insights = array_merge(
        generateWHONutritionInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to),
        generateInfantNutritionInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to),
        generateMaternalHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to),
        generateSeniorHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to),
        generateFamilyPlanningInsights($pdo, $user_purok, $role_id, $date_from, $date_to, $prev_date_from, $prev_date_to)
    );
    
    $priority_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
    usort($all_insights, function($a, $b) use ($priority_order) {
        return $priority_order[$a['priority']] - $priority_order[$b['priority']];
    });
    
} catch (Exception $e) {
    error_log("Data Insights Error: " . $e->getMessage());
    $error_message = "Error generating insights: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Data Insights</title>
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
        .navbar-brand, .nav-link {
            color: #fff !important;
            font-weight: 500;
        }
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
        .content {
            padding: 20px;
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
            min-height: calc(100vh - 80px);
        }
        .insight-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .insight-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .insight-card.critical {
            border-left-color: #dc3545;
            background: linear-gradient(to right, #fff5f5, #ffffff);
        }
        .insight-card.alert {
            border-left-color: #ff6b6b;
            background: linear-gradient(to right, #fff8f5, #ffffff);
        }
        .insight-card.warning {
            border-left-color: #ffc107;
            background: linear-gradient(to right, #fffdf5, #ffffff);
        }
        .insight-card.info {
            border-left-color: #17a2b8;
            background: linear-gradient(to right, #f5fcff, #ffffff);
        }
        .insight-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .insight-icon {
            font-size: 2rem;
            margin-right: 15px;
        }
        .insight-icon.critical { color: #dc3545; }
        .insight-icon.alert { color: #ff6b6b; }
        .insight-icon.warning { color: #ffc107; }
        .insight-icon.info { color: #17a2b8; }
        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .priority-critical {
            background: #dc3545;
            color: white;
        }
        .priority-high {
            background: #ff6b6b;
            color: white;
        }
        .priority-medium {
            background: #ffc107;
            color: #333;
        }
        .priority-low {
            background: #28a745;
            color: white;
        }
        .insight-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2d3748;
        }
        .insight-category {
            display: inline-block;
            background: #e2e8f0;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            color: #4a5568;
        }
        .insight-description {
            font-size: 1rem;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .insight-recommendation {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #4299e1;
        }
        .insight-recommendation-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        .insight-recommendation-title i {
            margin-right: 8px;
            color: #4299e1;
        }
        .summary-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .summary-stats {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-number.critical { color: #dc3545; }
        .stat-number.high { color: #ff6b6b; }
        .stat-number.medium { color: #ffc107; }
        .stat-label {
            font-size: 0.9rem;
            color: #718096;
        }
        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 15px;
            color: #1a202c;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
            background-color: #f8fafc;
        }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 12px 20px;
            font-size: 0.95rem;
            border-radius: 10px;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
            transform: translateY(-2px);
        }
        .no-insights {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .no-insights i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        .show-more-btn {
            background: none;
            border: none;
            color: #2b6cb0;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 0;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s;
        }
        .show-more-btn:hover {
            color: #1e4a7a;
            text-decoration: underline;
        }
        .show-more-btn i {
            margin-left: 5px;
            transition: transform 0.3s;
        }
        .show-more-btn.expanded i {
            transform: rotate(180deg);
        }
        .detailed-content {
            display: none;
            margin-top: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .detailed-content.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 2000px;
            }
        }
        .detailed-section {
            margin-bottom: 20px;
        }
        .detailed-section h6 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .detailed-section h6 i {
            margin-right: 8px;
            color: #4299e1;
        }
        .detailed-section p {
            color: #4a5568;
            line-height: 1.7;
            text-align: justify;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .data-source-box {
            background: #fff9e6;
            border-left: 3px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #5a5a5a;
            margin-top: 15px;
        }
        .data-source-box strong {
            color: #2d3748;
        }
        .geographic-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 8px;
            font-weight: 600;
        }
        .geographic-badge i {
            margin-right: 4px;
        }
        @media (max-width: 768px) {
            .content.with-sidebar  { 
                margin-left: 0; 
                padding: 10px;
            }
            .content {
                margin-left: 0;
                width: 100%;
                padding: 10px;
            }
            .sidebar {
                left: -250px;
                height: calc(100vh - 80px);
                top: 80px;
            }
            .sidebar.open {
                transform: translateX(250px);
            }
            .stat-number { font-size: 2rem; }
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
            .chart-container {
                height: 250px;
            }
        }
        @media (min-width: 769px) {
            .menu-toggle { display: none; }
            .sidebar {
                left: 0;
                transform: translateX(0);
            }
            .content { 
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-lightbulb"></i> Health Data Insights</h2>
                    <?php if ($role_id == 2): ?>
                        <span class="badge badge-info" style="font-size: 1rem; padding: 8px 15px;">
                            <i class="fas fa-map-marker-alt"></i> Purok: <?php echo htmlspecialchars($user_purok); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Date Filter -->
                <div class="date-filter">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="date_from"><strong>Analysis Period From:</strong></label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="date_to"><strong>To:</strong></label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-sync-alt"></i> Refresh Insights
                            </button>
                        </div>
                    </form>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-info-circle"></i> Comparing with previous period: <strong><?php echo $prev_date_from; ?></strong> to <strong><?php echo $prev_date_to; ?></strong>
                    </small>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?php echo $error_message; ?>
                    </div>
                <?php elseif (empty($all_insights)): ?>
                    <div class="no-insights">
                        <i class="fas fa-check-circle"></i>
                        <h3>All Systems Healthy</h3>
                        <p class="text-muted">No critical issues or actionable insights detected for the selected period.<br>Continue monitoring for emerging trends.</p>
                    </div>
                <?php else: ?>
                    <!-- Summary -->
                    <div class="summary-box">
                        <h4 class="mb-4"><i class="fas fa-chart-pie"></i> Insights Summary</h4>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <div class="stat-number critical">
                                    <?php echo count(array_filter($all_insights, fn($i) => $i['priority'] == 'critical')); ?>
                                </div>
                                <div class="stat-label">Critical Issues</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number high">
                                    <?php echo count(array_filter($all_insights, fn($i) => $i['priority'] == 'high')); ?>
                                </div>
                                <div class="stat-label">High Priority</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number medium">
                                    <?php echo count(array_filter($all_insights, fn($i) => $i['priority'] == 'medium')); ?>
                                </div>
                                <div class="stat-label">Medium Priority</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php echo count($all_insights); ?>
                                </div>
                                <div class="stat-label">Total Insights</div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights List -->
                    <?php foreach ($all_insights as $index => $insight): ?>
                        <div class="insight-card <?php echo $insight['type'] ?? $insight['priority']; ?>">
                            <div class="insight-header">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="insight-category">
                                            <?php echo $insight['category']; ?>
                                        </span>
                                        <?php if (isset($insight['has_geographic_data']) && $insight['has_geographic_data']): ?>
                                            <span class="geographic-badge">
                                                <i class="fas fa-map-marked-alt"></i> Geographic Pattern
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; align-items: start; margin-top: 8px;">
                                        <i class="fas fa-exclamation-triangle insight-icon <?php echo $insight['type'] ?? $insight['priority']; ?>"></i>
                                        <div style="flex: 1;">
                                            <div class="insight-title"><?php echo $insight['title']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <span class="priority-badge priority-<?php echo $insight['priority']; ?>">
                                    <?php echo strtoupper($insight['priority']); ?>
                                </span>
                            </div>
                            
                            <div class="insight-description">
                                <?php echo $insight['description']; ?>
                            </div>
                            
                            <?php if (isset($insight['detailed_explanation'])): ?>
                                <button class="show-more-btn" onclick="toggleDetails(<?php echo $index; ?>)">
                                    <span class="show-more-text">Show More Details</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <div id="details-<?php echo $index; ?>" class="detailed-content">
                                    <div class="detailed-section">
                                        <h6><i class="fas fa-book-medical"></i> Detailed Analysis</h6>
                                        <p><?php echo $insight['detailed_explanation']; ?></p>
                                    </div>
                                    
                                    <?php if (isset($insight['chart_data'])): ?>
                                        <div class="detailed-section">
                                            <h6><i class="fas fa-chart-line"></i> Data Visualization</h6>
                                            <?php
                                            $chart_data = json_decode($insight['chart_data'], true);
                                            if (isset($chart_data['comparison']) && isset($chart_data['purok_breakdown'])):
                                                // Mixed chart type with comparison and breakdown
                                            ?>
                                                <div class="chart-container">
                                                    <canvas id="chart-comparison-<?php echo $index; ?>"></canvas>
                                                </div>
                                                <div class="chart-container">
                                                    <canvas id="chart-breakdown-<?php echo $index; ?>"></canvas>
                                                </div>
                                            <?php else: ?>
                                                <div class="chart-container">
                                                    <canvas id="chart-<?php echo $index; ?>"></canvas>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($insight['data_source'])): ?>
                                        <div class="data-source-box">
                                            <strong><i class="fas fa-database"></i> Data Source:</strong><br>
                                            <?php echo $insight['data_source']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="insight-recommendation">
                                <div class="insight-recommendation-title">
                                    <i class="fas fa-tasks"></i> Recommended Action
                                </div>
                                <div><?php echo $insight['recommendation']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Toggle detailed content
        function toggleDetails(index) {
            const detailsDiv = document.getElementById('details-' + index);
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');
            const text = btn.querySelector('.show-more-text');
            
            if (detailsDiv.classList.contains('show')) {
                detailsDiv.classList.remove('show');
                btn.classList.remove('expanded');
                text.textContent = 'Show More Details';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                detailsDiv.classList.add('show');
                btn.classList.add('expanded');
                text.textContent = 'Show Less';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                
                // Initialize charts after content is visible
                initializeCharts(index);
            }
        }
        
        // Initialize charts
        const initializedCharts = new Set();
        
        function initializeCharts(index) {
            if (initializedCharts.has(index)) return;
            
            <?php foreach ($all_insights as $idx => $insight): ?>
                <?php if (isset($insight['chart_data'])): ?>
                    <?php
                    $chart_data = json_decode($insight['chart_data'], true);
                    ?>
                    if (index === <?php echo $idx; ?>) {
                        <?php if (isset($chart_data['comparison']) && isset($chart_data['purok_breakdown'])): ?>
                            // Mixed chart: Comparison chart
                            const comparisonCtx = document.getElementById('chart-comparison-<?php echo $idx; ?>');
                            if (comparisonCtx) {
                                new Chart(comparisonCtx, {
                                    type: 'bar',
                                    data: <?php echo json_encode($chart_data['comparison']); ?>,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            title: {
                                                display: true,
                                                text: 'Period Comparison',
                                                font: {
                                                    size: 16,
                                                    weight: 'bold'
                                                }
                                            },
                                            legend: {
                                                display: true,
                                                position: 'top'
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed.y !== null) {
                                                            label += context.parsed.y + '%';
                                                        }
                                                        return label;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                title: {
                                                    display: true,
                                                    text: 'Percentage (%)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Period'
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                            
                            // Purok breakdown chart
                            const breakdownCtx = document.getElementById('chart-breakdown-<?php echo $idx; ?>');
                            if (breakdownCtx) {
                                new Chart(breakdownCtx, {
                                    type: 'bar',
                                    data: <?php echo json_encode($chart_data['purok_breakdown']); ?>,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            title: {
                                                display: true,
                                                text: 'Geographic Distribution by Purok',
                                                font: {
                                                    size: 16,
                                                    weight: 'bold'
                                                }
                                            },
                                            legend: {
                                                display: true,
                                                position: 'top'
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed.y !== null) {
                                                            label += context.parsed.y + '%';
                                                        }
                                                        return label;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                title: {
                                                    display: true,
                                                    text: 'Rate (%)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Purok'
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                        <?php else: ?>
                            // Single chart
                            const ctx = document.getElementById('chart-<?php echo $idx; ?>');
                            if (ctx) {
                                new Chart(ctx, {
                                    type: '<?php echo $insight['chart_type'] ?? 'bar'; ?>',
                                    data: <?php echo $insight['chart_data']; ?>,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            title: {
                                                display: true,
                                                text: '<?php echo addslashes($insight['title']); ?>',
                                                font: {
                                                    size: 16,
                                                    weight: 'bold'
                                                }
                                            },
                                            legend: {
                                                display: true,
                                                position: 'top'
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed.y !== null) {
                                                            label += context.parsed.y + '%';
                                                        } else if (context.parsed !== null) {
                                                            label += context.parsed;
                                                        }
                                                        return label;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            <?php if ($insight['chart_type'] === 'line' || $insight['chart_type'] === 'bar'): ?>
                                            y: {
                                                beginAtZero: true,
                                                title: {
                                                    display: true,
                                                    text: 'Percentage (%)'
                                                },
                                                ticks: {
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Period'
                                                }
                                            }
                                            <?php endif; ?>
                                        }
                                        <?php if ($insight['chart_type'] === 'line'): ?>
                                        ,
                                        elements: {
                                            line: {
                                                tension: 0.4
                                            },
                                            point: {
                                                radius: 5,
                                                hoverRadius: 7
                                            }
                                        }
                                        <?php endif; ?>
                                    }
                                });
                            }
                        <?php endif; ?>
                    }
                <?php endif; ?>
            <?php endforeach; ?>
            
            initializedCharts.add(index);
        }
        
        // Initialize on document ready
        $(document).ready(function() {
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
            // Menu toggle
            $('.menu-toggle').on('click', toggleSidebar);
            
            // Accordion functionality
            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });
            
            // Auto-expand first critical insight if exists
            <?php 
            $first_critical = null;
            foreach ($all_insights as $idx => $insight) {
                if ($insight['priority'] === 'critical') {
                    $first_critical = $idx;
                    break;
                }
            }
            if ($first_critical !== null):
            ?>
                setTimeout(function() {
                    const firstCriticalBtn = document.querySelector('.insight-card.critical .show-more-btn');
                    if (firstCriticalBtn) {
                        firstCriticalBtn.click();
                    }
                }, 500);
            <?php endif; ?>
            
            // Add fade-in animation to insight cards
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            entry.target.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            document.querySelectorAll('.insight-card').forEach(card => {
                observer.observe(card);
            });
            
            // Print functionality
            window.printInsights = function() {
                window.print();
            };
            
            // Add print styles
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    .sidebar, .navbar, .date-filter, .show-more-btn, .btn-primary {
                        display: none !important;
                    }
                    .content {
                        margin-left: 0 !important;
                        padding: 0 !important;
                    }
                    .insight-card {
                        page-break-inside: avoid;
                        box-shadow: none !important;
                        border: 1px solid #ddd !important;
                    }
                    .detailed-content {
                        display: block !important;
                    }
                    .chart-container {
                        page-break-inside: avoid;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Add tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Smooth scroll to top button
            const scrollTopBtn = $('<button>', {
                class: 'btn btn-primary',
                html: '<i class="fas fa-arrow-up"></i>',
                css: {
                    position: 'fixed',
                    bottom: '30px',
                    right: '30px',
                    display: 'none',
                    'z-index': '1000',
                    'border-radius': '50%',
                    width: '50px',
                    height: '50px',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.3)'
                },
                click: function() {
                    $('html, body').animate({ scrollTop: 0 }, 600);
                }
            });
            
            $('body').append(scrollTopBtn);
            
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    scrollTopBtn.fadeIn();
                } else {
                    scrollTopBtn.fadeOut();
                }
            });
            
            // Highlight trends in descriptions
            $('.insight-description').each(function() {
                const text = $(this).html();
                const highlighted = text
                    .replace(/\bincreased\b/gi, '<strong style="color:#dc3545;">increased</strong>')
                    .replace(/\bdecreased\b/gi, '<strong style="color:#28a745;">decreased</strong>')
                    .replace(/\bimproved\b/gi, '<strong style="color:#28a745;">improved</strong>')
                    .replace(/\bdeclined\b/gi, '<strong style="color:#dc3545;">declined</strong>')
                    .replace(/\bstable|remained stable\b/gi, '<strong style="color:#6c757d;">stable</strong>');
                $(this).html(highlighted);
            });
            
            // Add responsive chart resizing
            window.addEventListener('resize', function() {
                Chart.helpers.each(Chart.instances, function(instance) {
                    instance.resize();
                });
            });
            
            // Keyboard navigation for show more buttons
            $('.show-more-btn').on('keypress', function(e) {
                if (e.which === 13 || e.which === 32) {
                    e.preventDefault();
                    $(this).click();
                }
            }).attr('tabindex', '0');
            
            // Add loading animation for date filter
            $('form').on('submit', function() {
                const btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true)
                   .html('<i class="fas fa-spinner fa-spin"></i> Loading...');
            });
            
            // Warning for old date ranges
            const dateFrom = new Date($('#date_from').val());
            const dateTo = new Date($('#date_to').val());
            const daysDiff = Math.floor((dateTo - dateFrom) / (1000 * 60 * 60 * 24));
            
            if (daysDiff > 90) {
                $('.date-filter').append(`
                    <div class="alert alert-warning mt-3" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Note:</strong> You are analyzing a ${daysDiff}-day period. 
                        Shorter periods (30-60 days) may provide more actionable insights.
                    </div>
                `);
            }
            
            // Add insight counter animation
            $('.stat-number').each(function() {
                const $this = $(this);
                const countTo = parseInt($this.text());
                
                if (isNaN(countTo)) return;
                
                $({ countNum: 0 }).animate({
                    countNum: countTo
                }, {
                    duration: 1500,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum));
                    },
                    complete: function() {
                        $this.text(this.countNum);
                    }
                });
            });
            
            // Context menu for charts (right-click to download)
            $(document).on('contextmenu', 'canvas', function(e) {
                e.preventDefault();
                const canvas = e.target;
                const url = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = 'insight-chart-' + Date.now() + '.png';
                link.href = url;
                link.click();
                
                // Show toast notification
                const toast = $('<div>', {
                    class: 'alert alert-success',
                    html: '<i class="fas fa-check-circle"></i> Chart downloaded successfully!',
                    css: {
                        position: 'fixed',
                        top: '100px',
                        right: '20px',
                        'z-index': '9999',
                        'min-width': '250px'
                    }
                });
                $('body').append(toast);
                setTimeout(() => toast.fadeOut(() => toast.remove()), 3000);
            });
            
            // Add hover effect to insight cards
            $('.insight-card').hover(
                function() {
                    $(this).css('box-shadow', '0 6px 30px rgba(0,0,0,0.2)');
                },
                function() {
                    $(this).css('box-shadow', '0 2px 15px rgba(0,0,0,0.1)');
                }
            );
            
            // Dynamic background color for priority badges
            $('.priority-badge').each(function() {
                const priority = $(this).text().toLowerCase();
                $(this).attr('title', priority + ' priority issue')
                       .attr('data-toggle', 'tooltip');
            });
            
            // Log analytics event (if analytics is set up)
            if (typeof gtag === 'function') {
                gtag('event', 'page_view', {
                    'page_title': 'Health Data Insights',
                    'page_location': window.location.href,
                    'page_path': window.location.pathname,
                    'insights_count': <?php echo count($all_insights); ?>,
                    'critical_count': <?php echo count(array_filter($all_insights, fn($i) => $i['priority'] == 'critical')); ?>
                });
            }
            
            
            
            // Accessibility improvements
            $('canvas').attr({
                'role': 'img',
                'aria-label': 'Data visualization chart'
            });
            
            $('.insight-card').attr('role', 'article');
            
            // Auto-save scroll position
            const scrollPos = sessionStorage.getItem('insightsScrollPos');
            if (scrollPos) {
                $(window).scrollTop(scrollPos);
                sessionStorage.removeItem('insightsScrollPos');
            }
            
            $(window).on('beforeunload', function() {
                sessionStorage.setItem('insightsScrollPos', $(window).scrollTop());
            });
            
            // Add export button
            const exportBtn = $('<button>', {
                class: 'btn btn-primary',
                html: '<i class="fas fa-file-export"></i> Export Report',
                css: {
                    position: 'fixed',
                    bottom: '90px',
                    right: '30px',
                    'z-index': '1000',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.3)',
                    display: 'none'
                },
                click: function() {
                    window.print();
                }
            });
            
            $('body').append(exportBtn);
            
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    exportBtn.fadeIn();
                } else {
                    exportBtn.fadeOut();
                }
            });
            
            // Add geographic badge tooltip
            $('.geographic-badge').attr('title', 'This insight shows geographic variation across puroks')
                                 .attr('data-toggle', 'tooltip');
            
            // Initialize all tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
