<?php
require_once 'db_connect.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$backup_dir = "backup/automatic/";
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Fetch user role and purok
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM person p 
        JOIN address a ON p.address_id = a.address_id 
        JOIN records r ON p.person_id = r.person_id 
        WHERE r.user_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

$timestamp = date('Ymd_His');
$backup_data = [
    'patient_medication_records' => [],
    'infant_records'            => [],
    'postnatal_records'         => [],
    'child_health_records'      => [],
    'pregnant_records'          => [],
    'fp_records'                => [],
    'household_records'         => []
];
$record_types = [
    'patient_medication_records' => 'senior_record.medication',
    'infant_records'             => 'child_record.infant_record',
    'postnatal_records'          => 'pregnancy_record.postnatal',
    'child_health_records'       => 'child_record',
    'pregnant_records'           => 'pregnancy_record.prenatal',
    'fp_records'                 => 'family_planning_record',
    'household_records'          => 'household_record'
];

// Define base directory based on role
$base_dir = $role_id == 2 && $user_purok ? $backup_dir . $user_purok . '/all/' : $backup_dir . 'all/';
if (!is_dir($base_dir)) {
    mkdir($base_dir, 0777, true);
}

// Queries from provided files
try {
    foreach ($record_types as $file => $record_type) {
        $data = [];

        if ($file == 'patient_medication_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            $query = "
                SELECT p.person_id, p.full_name, p.age, p.gender, p.household_number,
                       sr.bp_reading, sr.bp_date_taken,
                       GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medication_name,
                       a.purok, r.user_id, r.created_by
                FROM senior_record sr
                JOIN records r        ON r.records_id        = sr.records_id
                JOIN person  p        ON r.person_id         = p.person_id
                JOIN address a        ON p.address_id        = a.address_id
                JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
                JOIN medication m     ON sm.medication_id    = m.medication_id
                WHERE r.record_type = 'senior_record.medication'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.age IS NOT NULL
                  AND p.gender IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND sr.bp_reading IS NOT NULL
                  AND sr.bp_date_taken IS NOT NULL
                  AND m.medication_name IS NOT NULL AND m.medication_name != ''
                  AND a.purok IS NOT NULL AND a.purok != ''
                GROUP BY p.person_id, p.full_name, p.age, p.gender, p.household_number,
                         sr.bp_reading, sr.bp_date_taken, a.purok, r.user_id, r.created_by
                HAVING COUNT(m.medication_name) > 0
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Patient medication records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'infant_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            // UPDATED: use child_immunization + immunization junction
            $query = "
                SELECT DISTINCT
                       p.person_id,
                       p.full_name,
                       p.gender,
                       p.birthdate,
                       p.household_number,
                       cr.weight,
                       cr.height,
                       cr.measurement_date,
                       cr.service_source,
                       GROUP_CONCAT(DISTINCT i.immunization_type) AS immunization_type,
                       ir.breastfeeding_months,
                       ir.solid_food_start,
                       a.purok,
                       r.user_id,
                       r.created_by
                FROM person p
                JOIN address a         ON p.address_id = a.address_id
                JOIN records r         ON p.person_id  = r.person_id
                JOIN child_record cr   ON r.records_id = cr.records_id
                JOIN infant_record ir  ON cr.child_record_id = ir.child_record_id
                LEFT JOIN child_immunization ci ON cr.child_record_id = ci.child_record_id
                LEFT JOIN immunization i        ON ci.immunization_id  = i.immunization_id
                WHERE r.record_type = 'child_record.infant_record'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.gender IS NOT NULL
                  AND p.birthdate IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND cr.weight IS NOT NULL
                  AND cr.height IS NOT NULL
                  AND cr.measurement_date IS NOT NULL
                  AND cr.service_source IS NOT NULL AND cr.service_source != ''
                  AND a.purok IS NOT NULL AND a.purok != ''
                GROUP BY p.person_id, p.full_name, p.gender, p.birthdate, p.household_number,
                         cr.weight, cr.height, cr.measurement_date, cr.service_source,
                         ir.breastfeeding_months, ir.solid_food_start,
                         a.purok, r.user_id, r.created_by
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Infant records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'postnatal_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            // UPDATED: use pregnancy_medication junction
            $query = "
                SELECT p.person_id,
                       p.full_name,
                       p.age,
                       p.gender,
                       p.household_number,
                       po.date_delivered,
                       po.delivery_location,
                       po.attendant,
                       po.risk_observed,
                       po.postnatal_checkups,
                       po.service_source,
                       po.family_planning_intent,
                       GROUP_CONCAT(DISTINCT m.medication_name SEPARATOR ', ') AS medication_name,
                       pr.pregnancy_period,
                       a.purok,
                       r.user_id,
                       r.created_by
                FROM postnatal po
                JOIN pregnancy_record pr   ON po.pregnancy_record_id = pr.pregnancy_record_id
                JOIN records r             ON pr.records_id = r.records_id
                JOIN person p              ON r.person_id  = p.person_id
                JOIN address a             ON p.address_id = a.address_id
                LEFT JOIN pregnancy_medication pm ON pr.pregnancy_record_id = pm.pregnancy_record_id
                LEFT JOIN medication m            ON pm.medication_id      = m.medication_id
                WHERE r.record_type = 'pregnancy_record.postnatal'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.age IS NOT NULL
                  AND p.gender IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND po.date_delivered IS NOT NULL
                  AND po.delivery_location IS NOT NULL AND po.delivery_location != ''
                  AND po.attendant IS NOT NULL AND po.attendant != ''
                  AND a.purok IS NOT NULL AND a.purok != ''
                GROUP BY p.person_id, p.full_name, p.age, p.gender, p.household_number,
                         po.date_delivered, po.delivery_location, po.attendant,
                         po.risk_observed, po.postnatal_checkups, po.service_source,
                         po.family_planning_intent,
                         pr.pregnancy_period, a.purok, r.user_id, r.created_by
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Postnatal records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'child_health_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            $query = "
                SELECT p.person_id, p.full_name, p.birthdate, p.gender, p.household_number,
                       cr.weight, cr.height,
                       cr.measurement_date, cr.risk_observed, cr.immunization_status, cr.child_type,
                       a.purok, r.user_id, r.created_by
                FROM child_record cr
                JOIN records r  ON r.records_id = cr.records_id
                JOIN person p   ON r.person_id  = p.person_id
                JOIN address a  ON p.address_id = a.address_id
                WHERE r.record_type = 'child_record'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.birthdate IS NOT NULL
                  AND p.gender IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND cr.weight IS NOT NULL
                  AND cr.height IS NOT NULL
                  AND cr.measurement_date IS NOT NULL
                  AND a.purok IS NOT NULL AND a.purok != ''
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Child health records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'pregnant_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            // UPDATED: use pregnancy_medication junction and pregnancy_period='Prenatal'
            $query = "
                SELECT p.person_id,
                       p.full_name,
                       p.philhealth_number,
                       p.age,
                       p.birthdate,
                       p.household_number,
                       pn.months_pregnancy,
                       pn.checkup_date,
                       pn.risk_observed,
                       pn.birth_plan,
                       pn.last_menstruation,
                       pn.expected_delivery_date,
                       pn.preg_count,
                       pn.child_alive,
                       GROUP_CONCAT(DISTINCT m.medication_name SEPARATOR ', ') AS medication_name,
                       pr.pregnancy_period,
                       a.purok,
                       r.user_id,
                       r.created_by
                FROM prenatal pn
                JOIN pregnancy_record pr   ON pn.pregnancy_record_id = pr.pregnancy_record_id
                JOIN records r             ON pr.records_id = r.records_id
                JOIN person p              ON r.person_id  = p.person_id
                JOIN address a             ON p.address_id = a.address_id
                LEFT JOIN pregnancy_medication pm ON pr.pregnancy_record_id = pm.pregnancy_record_id
                LEFT JOIN medication m            ON pm.medication_id      = m.medication_id
                WHERE r.record_type = 'pregnancy_record.prenatal'
                  AND pr.pregnancy_period = 'Prenatal'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.philhealth_number IS NOT NULL AND p.philhealth_number != ''
                  AND p.age IS NOT NULL
                  AND p.birthdate IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND pn.months_pregnancy IS NOT NULL
                  AND pn.checkup_date IS NOT NULL
                  AND a.purok IS NOT NULL AND a.purok != ''
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Pregnant records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'fp_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            $query = "
                SELECT p.person_id, p.full_name, p.age, p.gender, p.birthdate, p.household_number,
                       fpr.uses_fp_method, fpr.fp_method, fpr.months_used, fpr.reason_not_using,
                       a.purok, r.user_id, r.created_by
                FROM family_planning_record fpr
                JOIN records r  ON fpr.records_id = r.records_id
                JOIN person p   ON r.person_id    = p.person_id
                JOIN address a  ON p.address_id   = a.address_id
                WHERE r.record_type = 'family_planning_record'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.age IS NOT NULL
                  AND p.gender IS NOT NULL
                  AND p.birthdate IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND a.purok IS NOT NULL AND a.purok != ''
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - FP records query executed, found " . count($data) . " records\n", FILE_APPEND);

        } elseif ($file == 'household_records' && ($role_id == 1 || $role_id == 4 || $role_id == 2)) {
            $query = "
                SELECT p.person_id,
                       p.full_name,
                       p.age,
                       p.gender,
                       p.birthdate,
                       p.household_number,
                       hr.water_source,
                       hr.toilet_type,
                       hr.visit_months,
                       p.health_condition,
                       a.purok,
                       r.user_id,
                       r.created_by
                FROM person p
                JOIN address a        ON p.address_id  = a.address_id
                JOIN records r        ON p.person_id   = r.person_id
                JOIN household_record hr ON r.records_id = hr.records_id
                WHERE r.record_type = 'household_record'
                  AND p.person_id IS NOT NULL
                  AND p.full_name IS NOT NULL AND p.full_name != ''
                  AND p.age IS NOT NULL
                  AND p.gender IS NOT NULL
                  AND p.birthdate IS NOT NULL
                  AND p.household_number IS NOT NULL
                  AND hr.water_source IS NOT NULL
                  AND hr.toilet_type IS NOT NULL
                  AND a.purok IS NOT NULL AND a.purok != ''
            ";
            if ($role_id == 2 && $user_purok) {
                $query .= " AND a.purok = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_purok]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Household records query executed, found " . count($data) . " records\n", FILE_APPEND);
        }

        $backup_data[$file] = $data;
        $record_dir = $base_dir . $file . '/';
        if (!is_dir($record_dir)) {
            mkdir($record_dir, 0777, true);
        }
        file_put_contents($record_dir . "backup_$timestamp.json", json_encode($data, JSON_PRETTY_PRINT));
        file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Backed up $file with " . count($data) . " records\n", FILE_APPEND);
    }

    // Create consolidated all.json
    file_put_contents($base_dir . "all_$timestamp.json", json_encode($backup_data, JSON_PRETTY_PRINT));

    // Update backup/automatic/all/ if purok-specific backup
    if ($role_id == 2 && $user_purok) {
        $all_dir = $backup_dir . 'all/';
        if (!is_dir($all_dir)) {
            mkdir($all_dir, 0777, true);
        }
        foreach ($record_types as $file => $record_type) {
            $all_record_dir = $all_dir . $file . '/';
            if (!is_dir($all_record_dir)) {
                mkdir($all_record_dir, 0777, true);
            }
            file_put_contents($all_record_dir . "backup_$timestamp.json", json_encode($backup_data[$file], JSON_PRETTY_PRINT));
        }
        file_put_contents($all_dir . "all_$timestamp.json", json_encode($backup_data, JSON_PRETTY_PRINT));
    }

    // Log success
    file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Automatic backup created successfully in $base_dir\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents('backup_errors.log', date('Y-m-d H:i:s') . " - Automatic backup failed: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
