<?php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    ob_start();
    
    // Get household data
    if ($_POST['ajax'] == 'get_household_data') {
        try {
            $stmt = $pdo->prepare("SELECT hr.water_source, hr.toilet_type, hr.visit_months FROM records r JOIN household_record hr ON r.records_id = hr.records_id WHERE r.person_id = ?");
            $stmt->execute([$_POST['person_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $visit_months = !empty($data['visit_months']) ? explode(',', $data['visit_months']) : [];
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'water_source' => $data['water_source'] ?? '',
                    'toilet_type' => $data['toilet_type'] ?? '',
                    'visit_months' => $visit_months
                ]
            ]);
        } catch (Exception $e) {
            error_log("getHouseholdData Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch household data']);
        }
        exit;
    }
    
    // Get household statistics
    if ($_POST['ajax'] == 'get_household_stats') {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_members,
                       SUM(CASE WHEN p.age <= 6 THEN 1 ELSE 0 END) as children,
                       SUM(CASE WHEN p.age >= 60 THEN 1 ELSE 0 END) as elderly,
                       SUM(CASE WHEN p.gender = 'F' AND p.age BETWEEN 15 AND 49 THEN 1 ELSE 0 END) as women_reproductive_age
                FROM person p
                WHERE p.person_id = ? OR p.related_person_id = ?
            ");
            $stmt->execute([$_POST['person_id'], $_POST['person_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Get health conditions with auto-determination
    if ($_POST['ajax'] == 'get_health_conditions') {
        try {
            $current_date = new DateTime();
            $stmt = $pdo->prepare("
                SELECT p.age, p.gender, p.birthdate, p.health_condition,
                       (SELECT COUNT(*) FROM pregnancy_record pr
                        JOIN records r ON pr.records_id = r.records_id
                        WHERE r.person_id = p.person_id AND pr.pregnancy_period = 'Prenatal') as pregnant_count,
                       (SELECT COUNT(*) FROM postnatal pn
                        JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
                        JOIN records r ON pr.records_id = r.records_id
                        WHERE r.person_id = p.person_id) as postnatal_count
                FROM person p WHERE p.person_id = ?");
            $stmt->execute([$_POST['person_id']]);
            $person_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$person_data) {
                throw new Exception("Person not found");
            }

            $age = $person_data['age'] ?? floor($current_date->diff(new DateTime($person_data['birthdate']))->y);
            $auto_health_condition = 'NRP (No Record Provided)';
            
            if ($age >= 1 && $age <= 6) {
                $auto_health_condition = 'C (Child 12-71 months)';
            } elseif ($person_data['gender'] == 'F' && $age >= 15 && $age <= 49) {
                if ($person_data['pregnant_count'] > 0) {
                    $auto_health_condition = 'P (Pregnant)';
                } elseif ($person_data['postnatal_count'] > 0) {
                    $auto_health_condition = 'PP (Post-Partum 6 weeks)';
                } else {
                    $auto_health_condition = 'NP (Non-Pregnant)';
                }
            } elseif ($age >= 60) {
                $auto_health_condition = 'E (Elderly 60+)';
            }

            $current_conditions = !empty($person_data['health_condition']) ? explode(',', $person_data['health_condition']) : [$auto_health_condition];
            
            if (count($current_conditions) > 1 && in_array('NRP (No Record Provided)', $current_conditions)) {
                $current_conditions = array_diff($current_conditions, ['NRP (No Record Provided)']);
                $current_conditions = array_values($current_conditions);
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $current_conditions, 'auto' => $auto_health_condition]);
        } catch (Exception $e) {
            error_log("getHealthConditions Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch health conditions']);
        }
        exit;
    }
    
    // Get additional health conditions for all household members
    if ($_POST['ajax'] == 'get_additional_health_conditions') {
        try {
            $stmt = $pdo->prepare("SELECT p.person_id, p.full_name, p.health_condition FROM person p WHERE p.person_id = ? OR p.related_person_id = ?");
            $stmt->execute([$_POST['person_id'], $_POST['person_id']]);
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            $current_date = new DateTime();

            foreach ($persons as $person) {
                $pid = $person['person_id'];
                $current_conditions = !empty($person['health_condition']) ? explode(',', $person['health_condition']) : [];

                $stmt2 = $pdo->prepare("
                    SELECT p.age, p.gender, p.birthdate,
                           (SELECT COUNT(*) FROM pregnancy_record pr
                            JOIN records r ON pr.records_id = r.records_id
                            WHERE r.person_id = p.person_id AND pr.pregnancy_period = 'Prenatal') as pregnant_count,
                           (SELECT COUNT(*) FROM postnatal pn
                            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
                            JOIN records r ON pr.records_id = r.records_id
                            WHERE r.person_id = p.person_id) as postnatal_count
                    FROM person p WHERE p.person_id = ?");
                $stmt2->execute([$pid]);
                $data = $stmt2->fetch(PDO::FETCH_ASSOC);

                $age = $data['age'] ?? floor($current_date->diff(new DateTime($data['birthdate']))->y);
                $auto = 'NRP (No Record Provided)';
                
                if ($age >= 1 && $age <= 6) {
                    $auto = 'C (Child 12-71 months)';
                } elseif ($data['gender'] == 'F' && $age >= 15 && $age <= 49) {
                    if ($data['pregnant_count'] > 0) {
                        $auto = 'P (Pregnant)';
                    } elseif ($data['postnatal_count'] > 0) {
                        $auto = 'PP (Post-Partum 6 weeks)';
                    } else {
                        $auto = 'NP (Non-Pregnant)';
                    }
                } elseif ($age >= 60) {
                    $auto = 'E (Elderly 60+)';
                }

                $additional = array_diff($current_conditions, [$auto]);
                if (count($current_conditions) > 1 && in_array('NRP (No Record Provided)', $current_conditions)) {
                    $additional = array_diff($additional, ['NRP (No Record Provided)']);
                    $additional = array_values($additional);
                }
                
                $result[] = [
                    'person_id' => $pid,
                    'full_name' => $person['full_name'],
                    'auto' => $auto,
                    'additional' => array_values($additional),
                    'age' => $age,
                    'gender' => $data['gender']
                ];
            }

            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            error_log("getAdditionalHealthConditions Error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to fetch additional health conditions']);
        }
        exit;
    }
    
    // Validate water source based on purok
    if ($_POST['ajax'] == 'validate_water_source') {
        try {
            $water_source = $_POST['water_source'];
            $person_id = $_POST['person_id'];
            
            $warnings = [];
            
            if ($water_source == 'Level 1 (Poso)') {
                $warnings[] = 'Well water (Poso) should be regularly tested for contamination';
                $warnings[] = 'Boil water before drinking if not tested recently';
            } elseif ($water_source == 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)') {
                $warnings[] = 'Shared water sources require proper maintenance and cleaning';
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'warnings' => $warnings]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Validate toilet type and provide recommendations
    if ($_POST['ajax'] == 'validate_toilet_type') {
        try {
            $toilet_type = $_POST['toilet_type'];
            
            $warnings = [];
            $recommendations = [];
            
            if ($toilet_type == 'Wala') {
                $warnings[] = 'No toilet facility increases health risks';
                $recommendations[] = 'Consider building a sanitary pit toilet';
                $recommendations[] = 'Consult barangay for sanitation assistance programs';
            } elseif ($toilet_type == 'Pit Privy') {
                $recommendations[] = 'Upgrade to sanitary pit toilet for better hygiene';
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'warnings' => $warnings,
                'recommendations' => $recommendations
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// Fetch user role
try {
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Error: User not found for user_id: " . $_SESSION['user_id']);
    }
    $role_id = $user['role_id'];

    $stmt = $pdo->prepare("
        SELECT a.purok 
        FROM users u 
        JOIN records r ON u.user_id = r.user_id 
        JOIN person p ON r.person_id = p.person_id 
        JOIN address a ON p.address_id = a.address_id 
        WHERE u.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
    if ($user_purok === false) {
        die("Error: Unable to fetch user's purok.");
    }
} catch (PDOException $e) {
    error_log("User/Purok Fetch Error: " . $e->getMessage());
    die("Database error: Unable to fetch user information.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $person_id = $_POST['person_id'];
    $water_source = $_POST['water_source'];
    $toilet_type = $_POST['toilet_type'];
    $visit_months = $_POST['visit_months'] ?? [];
    $additional_conditions = $_POST['additional_conditions'] ?? [];

    if ($role_id == 2) {
        $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $person_purok = $stmt->fetchColumn();
        if ($person_purok !== $user_purok) {
            die("Error: BHW Staff can only update records for their assigned purok ($user_purok).");
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $stmt = $pdo->prepare("
                SELECT user_id 
                FROM users 
                WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)");
            $stmt->execute([$person_id]);
            $selected_user_id = $stmt->fetchColumn();
            if ($selected_user_id === false) {
                throw new Exception("No user_id found for person_id: $person_id");
            }
        }

        $current_date = new DateTime();
        $stmt = $pdo->prepare("
            SELECT p.age, p.gender, p.birthdate, p.health_condition,
                   (SELECT COUNT(*) FROM pregnancy_record pr 
                    JOIN records r ON pr.records_id = r.records_id 
                    WHERE r.person_id = p.person_id AND pr.pregnancy_period = 'Prenatal') as pregnant_count,
                   (SELECT COUNT(*) FROM postnatal pn 
                    JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id 
                    JOIN records r ON pr.records_id = r.records_id 
                    WHERE r.person_id = p.person_id) as postnatal_count
            FROM person p WHERE p.person_id = ?");
        $stmt->execute([$person_id]);
        $person_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $age = $person_data['age'] ?? floor($current_date->diff(new DateTime($person_data['birthdate']))->y);

        $health_condition = 'NRP (No Record Provided)';
        if ($age >= 1 && $age <= 6) {
            $health_condition = 'C (Child 12-71 months)';
        } elseif ($person_data['gender'] == 'F' && $age >= 15 && $age <= 49) {
            if ($person_data['pregnant_count'] > 0) {
                $health_condition = 'P (Pregnant)';
            } elseif ($person_data['postnatal_count'] > 0) {
                $health_condition = 'PP (Post-Partum 6 weeks)';
            } else {
                $health_condition = 'NP (Non-Pregnant)';
            }
        } elseif ($age >= 60) {
            $health_condition = 'E (Elderly 60+)';
        }

        $current_conditions = !empty($person_data['health_condition']) ? explode(',', $person_data['health_condition']) : [];
        $head_additional_conditions = $additional_conditions[$person_id] ?? [];
        $custom_head = $_POST["custom_condition_{$person_id}"] ?? '';
        if (!empty($custom_head)) {
            $head_additional_conditions[] = trim($custom_head);
        }
        $all_conditions = array_merge([$health_condition], $head_additional_conditions);
        
        if (count($all_conditions) > 1 && in_array('NRP (No Record Provided)', $all_conditions)) {
            $all_conditions = array_diff($all_conditions, ['NRP (No Record Provided)']);
        }
        $all_conditions = array_unique($all_conditions);
        $health_condition_str = implode(',', $all_conditions);

        $stmt = $pdo->prepare("
            SELECT r.records_id 
            FROM records r 
            JOIN household_record hr ON r.records_id = hr.records_id 
            WHERE r.person_id = ? AND r.record_type = 'household_record'");
        $stmt->execute([$person_id]);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_record) {
            $records_id = $existing_record['records_id'];
            $stmt = $pdo->prepare("UPDATE household_record SET water_source = ?, toilet_type = ?, visit_months = ? WHERE records_id = ?");
            $stmt->execute([$water_source, $toilet_type, implode(',', $visit_months), $records_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$selected_user_id, $person_id, 'household_record', $_SESSION['user_id']]);
            $records_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO household_record (records_id, water_source, toilet_type, visit_months) VALUES (?, ?, ?, ?)");
            $stmt->execute([$records_id, $water_source, $toilet_type, implode(',', $visit_months)]);
        }

        $stmt = $pdo->prepare("UPDATE person SET health_condition = ? WHERE person_id = ?");
        $stmt->execute([$health_condition_str, $person_id]);

        // Update related persons
        $stmt = $pdo->prepare("
            SELECT p.person_id, p.age, p.gender, p.birthdate, p.health_condition 
            FROM person p 
            JOIN address a ON p.address_id = a.address_id 
            WHERE p.related_person_id = ? 
            AND a.purok = (SELECT purok FROM address WHERE address_id = (SELECT address_id FROM person WHERE person_id = ?))");
        $stmt->execute([$person_id, $person_id]);
        $related_persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($related_persons as $related_person) {
            $related_person_id = $related_person['person_id'];
            $related_age = $related_person['age'] ?? floor($current_date->diff(new DateTime($related_person['birthdate']))->y);
            $related_health_condition = 'NRP (No Record Provided)';
            
            if ($related_age >= 1 && $related_age <= 6) {
                $related_health_condition = 'C (Child 12-71 months)';
            } elseif ($related_person['gender'] == 'F' && $related_age >= 15 && $related_age <= 49) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM pregnancy_record pr 
                    JOIN records r ON pr.records_id = r.records_id 
                    WHERE r.person_id = ? AND pr.pregnancy_period = 'Prenatal'");
                $stmt->execute([$related_person_id]);
                $pregnant_count = $stmt->fetchColumn();
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM postnatal pn 
                    JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id 
                    JOIN records r ON pr.records_id = r.records_id 
                    WHERE r.person_id = ?");
                $stmt->execute([$related_person_id]);
                $postnatal_count = $stmt->fetchColumn();
                if ($pregnant_count > 0) {
                    $related_health_condition = 'P (Pregnant)';
                } elseif ($postnatal_count > 0) {
                    $related_health_condition = 'PP (Post-Partum 6 weeks)';
                } else {
                    $related_health_condition = 'NP (Non-Pregnant)';
                }
            } elseif ($related_age >= 60) {
                $related_health_condition = 'E (Elderly 60+)';
            }

            $related_current_conditions = !empty($related_person['health_condition']) ? explode(',', $related_person['health_condition']) : [];
            $related_additional_conditions = $additional_conditions[$related_person_id] ?? [];
            $custom_related = $_POST["custom_condition_{$related_person_id}"] ?? '';
            if (!empty($custom_related)) {
                $related_additional_conditions[] = trim($custom_related);
            }
            $all_related_conditions = array_merge([$related_health_condition], $related_additional_conditions);
            
            if (count($all_related_conditions) > 1 && in_array('NRP (No Record Provided)', $all_related_conditions)) {
                $all_related_conditions = array_diff($all_related_conditions, ['NRP (No Record Provided)']);
            }
            $all_related_conditions = array_unique($all_related_conditions);
            $related_health_condition_str = implode(',', $all_related_conditions);

            $stmt = $pdo->prepare("
                SELECT r.records_id 
                FROM records r 
                JOIN household_record hr ON r.records_id = hr.records_id 
                WHERE r.person_id = ? AND r.record_type = 'household_record'");
            $stmt->execute([$related_person_id]);
            $related_existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($related_existing_record) {
                $related_records_id = $related_existing_record['records_id'];
                $stmt = $pdo->prepare("UPDATE household_record SET water_source = ?, toilet_type = ?, visit_months = ? WHERE records_id = ?");
                $stmt->execute([$water_source, $toilet_type, implode(',', $visit_months), $related_records_id]);
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? LIMIT 1");
                $stmt->execute([$related_person_id]);
                $related_user_id = $stmt->fetchColumn();
                if ($related_user_id === false) {
                    $stmt = $pdo->prepare("
                        SELECT user_id 
                        FROM users 
                        WHERE user_id = (SELECT user_id FROM person p JOIN records r ON p.person_id = r.person_id WHERE p.person_id = ? LIMIT 1)");
                    $stmt->execute([$related_person_id]);
                    $related_user_id = $stmt->fetchColumn();
                    if ($related_user_id === false) {
                        $related_user_id = $selected_user_id;
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$related_user_id, $related_person_id, 'household_record', $_SESSION['user_id']]);
                $related_records_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO household_record (records_id, water_source, toilet_type, visit_months) VALUES (?, ?, ?, ?)");
                $stmt->execute([$related_records_id, $water_source, $toilet_type, implode(',', $visit_months)]);
            }

            $stmt = $pdo->prepare("UPDATE person SET health_condition = ? WHERE person_id = ?");
            $stmt->execute([$related_health_condition_str, $related_person_id]);
        }

        $pdo->commit();
        header("Location: household_form.php?success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Form Submission Error: " . $e->getMessage());
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Household Form</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 80px;
        }
        .navbar-brand, .nav-link {
            color: #fff;
            font-weight: 500;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #e2e8f0;
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
        .content.with-sidebar {
            margin-left: 0;
        }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-left: 25px;
            margin-right: 0;
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 15px;
            color: #1a202c;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
            background-color: #f8fafc;
        }
        .form-control::placeholder {
            color: #a0aec0;
            font-style: italic;
        }
        .form-control[readonly], .form-control:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
            height: 46px;
        }
        .select2-container .select2-selection {
            border-radius: 10px;
            border: 1px solid #d1d5db;
            height: 46px;
            background: #ffffff;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            color: #1a202c;
            padding-left: 15px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            right: 10px;
        }
        .select2-container--default .select2-selection--multiple {
            min-height: 46px;
            padding: 5px;
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
        .btn-primary:active {
            transform: translateY(0);
        }
        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
        .checkbox-group {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        .error-message {
            color: #dc3545;
            margin-top: 10px;
        }
        .custom-condition-input {
            margin-top: 10px;
        }
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 15px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #household_stats_card, #water_warnings, #toilet_warnings {
            display: none;
            margin-bottom: 15px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2b6cb0;
        }
        .member-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
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
                padding-left: 10px;
            }
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
            .card {
                margin: 0;
                margin-bottom: 15px;
            }
            .card-body {
                padding: 1rem;
            }
            .checkbox-group {
                gap: 8px;
            }
            .checkbox-group input[type="checkbox"] {
                transform: scale(1.1);
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .navbar-brand {
                padding-left: 55px;
            }
        }
        @media (min-width: 769px) {
            .menu-toggle { 
                display: none; 
            }
            .sidebar {
                left: 0;
                transform: translateX(0);
            }
            .content {
                margin-left: 250px;
            }
            .content.with-sidebar {
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
            z-index: 1035;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
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
                    <div class="card-header">Household Form</div>
                    <div class="card-body p-4">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <i class="fas fa-check-circle"></i> Household record saved successfully!
                            </div>
                        <?php endif; ?>
                        
                        <div id="error-message" class="error-message"></div>
                        
                        <!-- Household Statistics Card -->
                        <div id="household_stats_card" class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-home"></i> Household Statistics</h6>
                                <div class="row">
                                    <div class="col-md-3 col-6">
                                        <div class="stat-box">
                                            <div class="stat-label">Total Members</div>
                                            <div class="stat-value" id="total_members">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stat-box">
                                            <div class="stat-label">Children</div>
                                            <div class="stat-value" id="children">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stat-box">
                                            <div class="stat-label">Elderly</div>
                                            <div class="stat-value" id="elderly">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="stat-box">
                                            <div class="stat-label">Women (15-49)</div>
                                            <div class="stat-value" id="women_reproductive">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Water Warnings -->
                        <div id="water_warnings"></div>

                        <!-- Toilet Warnings -->
                        <div id="toilet_warnings"></div>

                        <form action="household_form.php" method="POST" id="householdForm">
                            <div class="form-group">
                                <label for="person_id">Select Household Head <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="person_id" name="person_id" required>
                                    <option value="">Search and Select Head...</option>
                                    <?php
                                    try {
                                        if ($role_id == 1) {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name 
                                                FROM person p 
                                                JOIN records r ON p.person_id = r.person_id 
                                                JOIN users u ON r.user_id = u.user_id 
                                                WHERE u.role_id = 3 
                                                AND p.relationship_type = 'Head'
                                            ");
                                            $stmt->execute();
                                        } else {
                                            $stmt = $pdo->prepare("
                                                SELECT p.person_id, p.full_name 
                                                FROM person p 
                                                JOIN address a ON p.address_id = a.address_id 
                                                JOIN records r ON p.person_id = r.person_id 
                                                JOIN users u ON r.user_id = u.user_id 
                                                WHERE u.role_id = 3 
                                                AND p.relationship_type = 'Head' 
                                                AND a.purok = ?
                                            ");
                                            $stmt->execute([$user_purok]);
                                        }
                                        $seen = [];
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            if (!isset($seen[$row['person_id']])) {
                                                echo "<option value='{$row['person_id']}'>" . htmlspecialchars($row['full_name']) . "</option>";
                                                $seen[$row['person_id']] = true;
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Person Select Error: " . $e->getMessage());
                                        echo "<option value=''>Error loading household heads</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="water_source">Water Source <span class="text-danger">*</span></label>
                                <select class="form-control" id="water_source" name="water_source" required>
                                    <option value="">Select Water Source</option>
                                    <option value="Level 1 (Poso)">Level 1 (Poso)</option>
                                    <option value="Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)">Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)</option>
                                    <option value="Level 3 (Nawasa)">Level 3 (Nawasa)</option>
                                    <option value="WRS (Water Refilling Station)">WRS (Water Refilling Station)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="toilet_type">Toilet Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="toilet_type" name="toilet_type" required>
                                    <option value="">Select Toilet Type</option>
                                    <option value="De Buhos">De Buhos</option>
                                    <option value="Sanitary Pit">Sanitary Pit</option>
                                    <option value="Pit Privy">Pit Privy</option>
                                    <option value="Wala">Wala</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Visit Months</label>
                                <div class="checkbox-group">
                                    <?php
                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    foreach ($months as $month) {
                                        echo "<label><input type='checkbox' name='visit_months[]' value='$month' class='month-checkbox'> $month</label>";
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div id="householdMembers" class="form-group"></div>
                            
                            <button type="submit" class="btn btn-primary" id="submit_btn">
                                <i class="fas fa-save"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            let currentPersonId = null;
            
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Search for Head of Family",
                allowClear: true
            });

            const healthConditions = [
                'CC (Coughing 2 weeks or more)', 'M (Malaria)',
                'PWD (Person With Disability)', 'DM (Diabetic)', 'HPN (High Blood Pressure)',
                'CA (Cancer)', 'B (Bukol)', 'DG (Dengue)', 'F (Flu)'
            ];

            // Auto-fill form on person selection
            $('#person_id').on('change', function() {
                $('#error-message').empty();
                const personId = $(this).val();
                currentPersonId = personId;
                
                if (!personId) {
                    $('#household_stats_card, #water_warnings, #toilet_warnings').slideUp();
                    $('#householdMembers').empty();
                    return;
                }

                // Fetch household statistics
                $.ajax({
                    url: 'household_form.php',
                    type: 'POST',
                    data: { ajax: 'get_household_stats', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#household_stats_card').slideDown();
                            $('#total_members').text(response.stats.total_members);
                            $('#children').text(response.stats.children);
                            $('#elderly').text(response.stats.elderly);
                            $('#women_reproductive').text(response.stats.women_reproductive_age);
                        }
                    }
                });

                // Fetch household data
                $.ajax({
                    url: 'household_form.php',
                    type: 'POST',
                    data: { ajax: 'get_household_data', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#water_source').val(response.data.water_source).trigger('change');
                            $('#toilet_type').val(response.data.toilet_type).trigger('change');
                            $('.month-checkbox').prop('checked', false);
                            response.data.visit_months.forEach(month => {
                                $(`.month-checkbox[value="${month}"]`).prop('checked', true);
                            });
                        } else {
                            $('#error-message').text(response.error || 'Failed to fetch household data');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        $('#error-message').text('Error fetching household data: ' + error);
                    }
                });

                // Fetch additional health conditions for household members
                $.ajax({
                    url: 'household_form.php',
                    type: 'POST',
                    data: { ajax: 'get_additional_health_conditions', person_id: personId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const membersDiv = $('#householdMembers');
                            membersDiv.empty();
                            
                            response.data.forEach(member => {
                                const isHead = member.person_id == personId;
                                const memberDiv = $('<div class="member-card">');
                                
                                let badge = '';
                                if (member.age <= 6) {
                                    badge = '<span class="badge badge-info">Child</span>';
                                } else if (member.age >= 60) {
                                    badge = '<span class="badge badge-warning">Elderly</span>';
                                } else if (member.gender == 'F' && member.age >= 15 && member.age <= 49) {
                                    badge = '<span class="badge badge-success">Reproductive Age</span>';
                                }
                                
                                memberDiv.append(`<h6>${member.full_name}${isHead ? ' <span class="badge badge-primary">Household Head</span>' : ''} ${badge}</h6>`);
                                memberDiv.append(`<p class="mb-2"><strong>Auto-determined:</strong> ${member.auto}</p>`);
                                memberDiv.append('<label>Additional Health Conditions:</label>');
                                
                                const select = $(`<select class="form-control select2-multiple" name="additional_conditions[${member.person_id}][]" multiple></select>`);
                                healthConditions.forEach(condition => {
                                    const selected = member.additional.includes(condition) ? 'selected' : '';
                                    select.append(`<option value="${condition}" ${selected}>${condition}</option>`);
                                });
                                select.append('<option value="Others">Others</option>');
                                
                                memberDiv.append(select);
                                
                                const customInput = $(`<input type="text" class="form-control custom-condition-input" name="custom_condition_${member.person_id}" placeholder="Specify other condition" style="display:none;">`);
                                memberDiv.append(customInput);
                                
                                membersDiv.append(memberDiv);
                                
                                select.select2({
                                    placeholder: "Select additional health conditions",
                                    allowClear: true
                                });
                                
                                // Handle Others selection
                                select.on('select2:select', function(e) {
                                    if (e.params.data.text === 'Others') {
                                        customInput.slideDown();
                                    }
                                });
                                
                                select.on('select2:unselect', function(e) {
                                    if (e.params.data.text === 'Others') {
                                        customInput.slideUp().val('');
                                    }
                                });
                            });
                        } else {
                            $('#error-message').text(response.error || 'Failed to fetch household members');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        $('#error-message').text('Error fetching household members: ' + error);
                    }
                });
            });

            // Validate water source
            $('#water_source').on('change', function() {
                if (!currentPersonId) return;
                
                const waterSource = $(this).val();
                
                $.ajax({
                    url: 'household_form.php',
                    type: 'POST',
                    data: { ajax: 'validate_water_source', water_source: waterSource, person_id: currentPersonId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.warnings.length > 0) {
                            let html = '<div class="alert alert-warning alert-dismissible fade show">';
                            html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                            html += '<strong><i class="fas fa-exclamation-triangle"></i> Water Source Advisory:</strong><ul class="mb-0 mt-2">';
                            response.warnings.forEach(warning => {
                                html += `<li>${warning}</li>`;
                            });
                            html += '</ul></div>';
                            $('#water_warnings').html(html).slideDown();
                        } else {
                            $('#water_warnings').slideUp().empty();
                        }
                    }
                });
            });

            // Validate toilet type
            $('#toilet_type').on('change', function() {
                if (!currentPersonId) return;
                
                const toiletType = $(this).val();
                
                $.ajax({
                    url: 'household_form.php',
                    type: 'POST',
                    data: { ajax: 'validate_toilet_type', toilet_type: toiletType },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            
                            if (response.warnings.length > 0) {
                                html += '<div class="alert alert-danger alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-exclamation-circle"></i> Critical Issue:</strong><ul class="mb-0 mt-2">';
                                response.warnings.forEach(warning => {
                                    html += `<li>${warning}</li>`;
                                });
                                html += '</ul></div>';
                            }
                            
                            if (response.recommendations && response.recommendations.length > 0) {
                                html += '<div class="alert alert-info alert-dismissible fade show">';
                                html += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                                html += '<strong><i class="fas fa-lightbulb"></i> Recommendations:</strong><ul class="mb-0 mt-2">';
                                response.recommendations.forEach(rec => {
                                    html += `<li>${rec}</li>`;
                                });
                                html += '</ul></div>';
                            }
                            
                            if (html) {
                                $('#toilet_warnings').html(html).slideDown();
                            } else {
                                $('#toilet_warnings').slideUp().empty();
                            }
                        }
                    }
                });
            });

            // Form submission with loading state
            $('#householdForm').on('submit', function(e) {
                $('#submit_btn').prop('disabled', true).html('<span class="loading-spinner"></span>Submitting...');
            });

            // Sidebar toggle
            $('.menu-toggle').on('click', toggleSidebar);

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
                        }).addClass('active');
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
            // Initialize accordion (if any)
            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });
        });
    </script>
</body>
</html>
