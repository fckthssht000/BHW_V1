<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['file'])) {
    $_SESSION['restore_error'] = "No backup file specified.";
    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - No backup file specified.\n", FILE_APPEND);
    header("Location: settings.php");
    exit;
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

// Get the file parameter and decode it
$backup_file = urldecode($_GET['file']);
$base_dirs = [
    $role_id == 2 ? realpath(__DIR__ . '/backup/automatic/' . $user_purok . '/all/') : realpath(__DIR__ . '/backup/automatic/all/'),
    $role_id == 2 ? realpath(__DIR__ . '/backup/manual/' . $user_purok . '/all/')   : realpath(__DIR__ . '/backup/manual/all/')
];
$absolute_file = realpath($backup_file);
$valid_path = false;

foreach ($base_dirs as $base_dir) {
    if ($absolute_file && $base_dir && strpos($absolute_file, $base_dir) === 0 && file_exists($absolute_file)) {
        $valid_path = true;
        break;
    }
}

if (!$valid_path) {
    $error_msg = "Backup file not found or invalid path: " . htmlspecialchars($backup_file);
    $_SESSION['restore_error'] = $error_msg;
    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - " . strip_tags($error_msg) . "\n", FILE_APPEND);
    header("Location: settings.php");
    exit;
}

try {
    if (!is_readable($absolute_file)) {
        throw new Exception("Backup file is not readable: $absolute_file");
    }

    $file_content = file_get_contents($absolute_file);
    if ($file_content === false) {
        throw new Exception("Failed to read backup file: $absolute_file");
    }

    $data = json_decode($file_content, true);
    if ($data === null) {
        throw new Exception("Invalid JSON in backup file: " . json_last_error_msg());
    }

    $expected_keys = [
        'patient_medication_records',
        'infant_records',
        'postnatal_records',
        'child_health_records',
        'pregnant_records',
        'fp_records',
        'household_records'
    ];

    $processed_types = [];
    foreach ($expected_keys as $key) {
        if (isset($data[$key]) && is_array($data[$key]) && !empty($data[$key])) {
            $processed_types[] = $key;
        } else {
            file_put_contents(
                'restore_errors.log',
                date('Y-m-d H:i:s') . " - Skipped $key: " . (isset($data[$key]) ? 'empty data' : 'missing key') . "\n",
                FILE_APPEND
            );
        }
    }

    if (empty($processed_types)) {
        throw new Exception("No valid non-empty record types found in backup file.");
    }

    // Helper: get or create address_id for a purok (extend with more fields if needed)
    function getAddressIdForPurok(PDO $pdo, string $purok): int {
        // Try to find existing address with same purok
        $stmt = $pdo->prepare("SELECT address_id FROM address WHERE purok = ? LIMIT 1");
        $stmt->execute([$purok]);
        $address_id = $stmt->fetchColumn();
        if ($address_id) {
            return (int)$address_id;
        }
        // If none, insert one minimal row
        $stmt = $pdo->prepare("INSERT INTO address (purok) VALUES (?)");
        $stmt->execute([$purok]);
        return (int)$pdo->lastInsertId();
    }

    $pdo->beginTransaction();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Clear existing by record_type (respect purok for BHW)
    foreach ($processed_types as $record_type) {
        $record_type_map = [
            'patient_medication_records' => 'senior_record.medication',
            'infant_records'            => 'child_record.infant_record',
            'postnatal_records'         => 'pregnancy_record.postnatal',
            'child_health_records'      => 'child_record',
            'pregnant_records'          => 'pregnancy_record.prenatal',
            'fp_records'                => 'family_planning_record',
            'household_records'         => 'household_record'
        ];
        $type = $record_type_map[$record_type];

        if ($role_id == 2 && $user_purok) {
            $stmt = $pdo->prepare("
                DELETE r FROM records r
                JOIN person p ON r.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id
                WHERE r.record_type = ? AND a.purok = ?
            ");
            $stmt->execute([$type, $user_purok]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM records WHERE record_type = ?");
            $stmt->execute([$type]);
        }
    }

    // Restore
    foreach ($data as $record_type => $records) {
        if (!in_array($record_type, $expected_keys) || !is_array($records) || empty($records)) {
            continue;
        }

        if ($record_type == 'patient_medication_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['person_id'], $record['age'], $record['gender'], $record['household_number'],
                           $record['purok'], $record['bp_reading'], $record['bp_date_taken'],
                           $record['medication_name'], $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped patient_medication_record for person_id {$record['person_id']}: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped patient_medication_record for person_id {$record['person_id']}: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (person_id, full_name, age, gender, household_number, address_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            age = VALUES(age),
                                            gender = VALUES(gender),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['person_id'], $record['full_name'], $record['age'],
                    $record['gender'], $record['household_number'], $address_id
                ]);

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$record['person_id'], 'senior_record.medication', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO senior_record (records_id, bp_reading, bp_date_taken) VALUES (?, ?, ?)");
                $stmt->execute([$records_id, $record['bp_reading'], $record['bp_date_taken']]);
                $senior_record_id = $pdo->lastInsertId();

                $medications = explode(', ', $record['medication_name']);
                foreach ($medications as $med) {
                    $med = trim($med);
                    if ($med === '') continue;

                    $stmt = $pdo->prepare("
                        INSERT INTO medication (medication_name) VALUES (?)
                        ON DUPLICATE KEY UPDATE medication_name = VALUES(medication_name)
                    ");
                    $stmt->execute([$med]);

                    $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                    $stmt->execute([$med]);
                    $medication_id = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO senior_medication (senior_record_id, medication_id) VALUES (?, ?)");
                    $stmt->execute([$senior_record_id, $medication_id]);
                }
            }

        } elseif ($record_type == 'infant_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['person_id'], $record['gender'], $record['birthdate'], $record['household_number'],
                           $record['purok'], $record['weight'], $record['height'], $record['measurement_date'],
                           $record['service_source'], $record['child_type'], $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped infant_record for person_id {$record['person_id']}: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped infant_record for person_id {$record['person_id']}: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (person_id, full_name, gender, birthdate, household_number, address_id, related_person_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            gender = VALUES(gender),
                                            birthdate = VALUES(birthdate),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['person_id'], $record['full_name'], $record['gender'],
                    $record['birthdate'], $record['household_number'], $address_id, 1
                ]);

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$record['person_id'], 'child_record.infant_record', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO child_record (records_id, weight, height, measurement_date, service_source, child_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $records_id, $record['weight'], $record['height'],
                    $record['measurement_date'], $record['service_source'], $record['child_type']
                ]);
                $child_record_id = $pdo->lastInsertId();

                // New immunization mapping: multiple immunization types possible
                if (!empty($record['immunization_type'])) {
                    $immList = array_map('trim', explode(',', $record['immunization_type']));
                    foreach ($immList as $imm) {
                        if ($imm === '') continue;
                        $stmt = $pdo->prepare("
                            INSERT INTO immunization (immunization_type) VALUES (?)
                            ON DUPLICATE KEY UPDATE immunization_type = VALUES(immunization_type)
                        ");
                        $stmt->execute([$imm]);
                        $stmt = $pdo->prepare("SELECT immunization_id FROM immunization WHERE immunization_type = ?");
                        $stmt->execute([$imm]);
                        $immunization_id = $stmt->fetchColumn();

                        $stmt = $pdo->prepare("INSERT INTO child_immunization (child_record_id, immunization_id) VALUES (?, ?)");
                        $stmt->execute([$child_record_id, $immunization_id]);
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO infant_record (child_record_id, breastfeeding_months, solid_food_start)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$child_record_id, $record['breastfeeding_months'] ?? null, $record['solid_food_start'] ?? null]);
            }

        } elseif ($record_type == 'postnatal_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['person_id'], $record['age'], $record['gender'], $record['household_number'],
                           $record['purok'], $record['date_delivered'], $record['delivery_location'],
                           $record['attendant'], $record['risk_observed'], $record['postnatal_checkups'],
                           $record['service_source'], $record['family_planning_intent'],
                           $record['pregnancy_period'], $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped postnatal_record for person_id {$record['person_id']}: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped postnatal_record for person_id {$record['person_id']}: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (person_id, full_name, age, gender, household_number, address_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            age = VALUES(age),
                                            gender = VALUES(gender),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['person_id'], $record['full_name'], $record['age'],
                    $record['gender'], $record['household_number'], $address_id
                ]);

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$record['person_id'], 'pregnancy_record.postnatal', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period) VALUES (?, ?)");
                $stmt->execute([$records_id, $record['pregnancy_period']]);
                $pregnancy_record_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO postnatal (pregnancy_record_id, date_delivered, delivery_location, attendant,
                                           risk_observed, postnatal_checkups, service_source, family_planning_intent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pregnancy_record_id, $record['date_delivered'], $record['delivery_location'],
                    $record['attendant'], $record['risk_observed'], $record['postnatal_checkups'],
                    $record['service_source'], $record['family_planning_intent']
                ]);

                if (!empty($record['medication_name'])) {
                    $meds = array_map('trim', explode(',', $record['medication_name']));
                    foreach ($meds as $med) {
                        if ($med === '') continue;
                        $stmt = $pdo->prepare("
                            INSERT INTO medication (medication_name) VALUES (?)
                            ON DUPLICATE KEY UPDATE medication_name = VALUES(medication_name)
                        ");
                        $stmt->execute([$med]);
                        $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                        $stmt->execute([$med]);
                        $medication_id = $stmt->fetchColumn();

                        $stmt = $pdo->prepare("
                            INSERT INTO pregnancy_medication (pregnancy_record_id, medication_id)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$pregnancy_record_id, $medication_id]);
                    }
                }
            }

        } elseif ($record_type == 'child_health_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['birthdate'], $record['gender'], $record['household_number'],
                           $record['purok'], $record['weight'], $record['height'], $record['measurement_date'],
                           $record['risk_observed'], $record['immunization_status'], $record['child_type'],
                           $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped child_health_record: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped child_health_record: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (full_name, birthdate, gender, household_number, address_id)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            birthdate = VALUES(birthdate),
                                            gender = VALUES(gender),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['full_name'], $record['birthdate'], $record['gender'],
                    $record['household_number'], $address_id
                ]);

                $stmt = $pdo->prepare("SELECT person_id FROM person WHERE full_name = ? AND household_number = ? LIMIT 1");
                $stmt->execute([$record['full_name'], $record['household_number']]);
                $person_id = $stmt->fetchColumn();

                if (!$person_id) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped child_health_record: could not resolve person_id\n", FILE_APPEND);
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$person_id, 'child_record', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO child_record (records_id, weight, height, measurement_date, risk_observed, immunization_status, child_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $records_id, $record['weight'], $record['height'], $record['measurement_date'],
                    $record['risk_observed'], $record['immunization_status'], $record['child_type']
                ]);
            }

        } elseif ($record_type == 'pregnant_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['philhealth_number'], $record['age'], $record['birthdate'], $record['household_number'],
                           $record['purok'], $record['months_pregnancy'], $record['checkup_date'],
                           $record['risk_observed'], $record['birth_plan'], $record['last_menstruation'],
                           $record['expected_delivery_date'], $record['pregnancy_period'],
                           $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped pregnant_record: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped pregnant_record: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (full_name, philhealth_number, age, birthdate, household_number, address_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            philhealth_number = VALUES(philhealth_number),
                                            age = VALUES(age),
                                            birthdate = VALUES(birthdate),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['full_name'], $record['philhealth_number'], $record['age'],
                    $record['birthdate'], $record['household_number'], $address_id
                ]);

                $stmt = $pdo->prepare("SELECT person_id FROM person WHERE full_name = ? AND household_number = ? LIMIT 1");
                $stmt->execute([$record['full_name'], $record['household_number']]);
                $person_id = $stmt->fetchColumn();

                if (!$person_id) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped pregnant_record: could not resolve person_id\n", FILE_APPEND);
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$person_id, 'pregnancy_record.prenatal', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period) VALUES (?, ?)");
                $stmt->execute([$records_id, $record['pregnancy_period']]);
                $pregnancy_record_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO prenatal (pregnancy_record_id, months_pregnancy, checkup_date, risk_observed,
                                          birth_plan, last_menstruation, expected_delivery_date, preg_count, child_alive)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pregnancy_record_id, $record['months_pregnancy'], $record['checkup_date'],
                    $record['risk_observed'], $record['birth_plan'], $record['last_menstruation'],
                    $record['expected_delivery_date'], $record['preg_count'] ?? null, $record['child_alive'] ?? null
                ]);

                if (!empty($record['medication_name'])) {
                    $meds = array_map('trim', explode(',', $record['medication_name']));
                    foreach ($meds as $med) {
                        if ($med === '') continue;
                        $stmt = $pdo->prepare("
                            INSERT INTO medication (medication_name) VALUES (?)
                            ON DUPLICATE KEY UPDATE medication_name = VALUES(medication_name)
                        ");
                        $stmt->execute([$med]);
                        $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                        $stmt->execute([$med]);
                        $medication_id = $stmt->fetchColumn();

                        $stmt = $pdo->prepare("
                            INSERT INTO pregnancy_medication (pregnancy_record_id, medication_id)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$pregnancy_record_id, $medication_id]);
                    }
                }
            }

        } elseif ($record_type == 'fp_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['person_id'], $record['age'], $record['gender'], $record['birthdate'],
                           $record['household_number'], $record['purok'], $record['uses_fp_method'],
                           $record['fp_method'], $record['months_used'], $record['reason_not_using'],
                           $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped fp_record for person_id {$record['person_id']}: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped fp_record for person_id {$record['person_id']}: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (person_id, full_name, age, gender, birthdate, household_number, address_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            age = VALUES(age),
                                            gender = VALUES(gender),
                                            birthdate = VALUES(birthdate),
                                            household_number = VALUES(household_number),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['person_id'], $record['full_name'], $record['age'],
                    $record['gender'], $record['birthdate'], $record['household_number'], $address_id
                ]);

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$record['person_id'], 'family_planning_record', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO family_planning_record (records_id, uses_fp_method, fp_method, months_used, reason_not_using)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE uses_fp_method = VALUES(uses_fp_method),
                                            fp_method = VALUES(fp_method),
                                            months_used = VALUES(months_used),
                                            reason_not_using = VALUES(reason_not_using)
                ");
                $stmt->execute([
                    $records_id, $record['uses_fp_method'], $record['fp_method'],
                    $record['months_used'], $record['reason_not_using']
                ]);
            }

        } elseif ($record_type == 'household_records') {
            foreach ($records as $record) {
                if (empty($record['full_name']) ||
                    !isset($record['person_id'], $record['age'], $record['gender'], $record['birthdate'],
                           $record['household_number'], $record['purok'], $record['water_source'],
                           $record['toilet_type'], $record['visit_months'], $record['health_condition'],
                           $record['user_id'], $record['created_by'])) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped household_record for person_id {$record['person_id']}: missing or invalid required fields\n", FILE_APPEND);
                    continue;
                }
                if ($role_id == 2 && $record['purok'] != $user_purok) {
                    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - Skipped household_record for person_id {$record['person_id']}: purok {$record['purok']} != user purok {$user_purok}\n", FILE_APPEND);
                    continue;
                }

                $address_id = getAddressIdForPurok($pdo, $record['purok']);

                $stmt = $pdo->prepare("
                    INSERT INTO person (person_id, full_name, age, gender, birthdate, household_number, health_condition, address_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name),
                                            age = VALUES(age),
                                            gender = VALUES(gender),
                                            birthdate = VALUES(birthdate),
                                            household_number = VALUES(household_number),
                                            health_condition = VALUES(health_condition),
                                            address_id = VALUES(address_id)
                ");
                $stmt->execute([
                    $record['person_id'], $record['full_name'], $record['age'],
                    $record['gender'], $record['birthdate'], $record['household_number'],
                    $record['health_condition'], $address_id
                ]);

                $stmt = $pdo->prepare("INSERT INTO records (person_id, record_type, user_id, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$record['person_id'], 'household_record', $record['user_id'], $record['created_by']]);
                $records_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO household_record (records_id, water_source, toilet_type, visit_months)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $records_id, $record['water_source'], $record['toilet_type'], $record['visit_months']
                ]);
            }
        }
    }

    $pdo->commit();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $_SESSION['restore_success'] = "Database restored and replaced with data from $backup_file. Processed record types: " . implode(', ', $processed_types) . ".";
    header("Location: settings.php");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    $error_msg = "Restore failed: " . $e->getMessage();
    $_SESSION['restore_error'] = $error_msg;
    file_put_contents('restore_errors.log', date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND);
    header("Location: settings.php");
    exit;
}
?>
