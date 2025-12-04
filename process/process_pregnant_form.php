<?php
session_start();
require_once 'db_connect.php';

function processPregnantForm($pdo) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    // Fetch user role and purok
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT a.purok FROM users u JOIN records r ON u.user_id = r.user_id JOIN person p ON r.person_id = p.person_id JOIN address a ON p.address_id = a.address_id WHERE u.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
    if ($user_purok === false) {
        die("Error: Unable to fetch user's purok.");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $person_id = $_POST['person_id'];
        $checkup_date = implode(',', $_POST['checkup_date'] ?? []);
        $months_pregnancy = $_POST['months_pregnancy'];
        $medications = $_POST['medication'] ?? [];
        $risks = implode(',', $_POST['risks'] ?? []);
        $birth_plan = isset($_POST['birth_plan']) ? 'Y' : 'N';
        $lmp = $_POST['lmp'] ?? '';
        $edc = $_POST['edc'] ?? '';
        $preg_count = $_POST['preg_count'] ?? '';
        $child_alive = $_POST['child_alive'] ?? '';
        $philhealth_number = $_POST['philhealth_number'] ?? '';

        // Validate required fields
        if (empty($person_id) || empty($checkup_date) || empty($months_pregnancy) || empty($lmp) || empty($edc) || empty($preg_count) || empty($child_alive)) {
            die("Error: All required fields must be filled.");
        }

        // Validate purok for BHW Staff
        if ($role_id == 2) {
            $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id WHERE p.person_id = ?");
            $stmt->execute([$person_id]);
            $mother_purok = $stmt->fetchColumn();
            if ($mother_purok !== $user_purok) {
                die("Error: BHW Staff can only submit records for their assigned purok ($user_purok).");
            }
        }

        // Check for duplicate submission in prenatal table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM records r 
                               JOIN pregnancy_record pr ON r.records_id = pr.records_id 
                               JOIN prenatal p ON pr.pregnancy_period_id = p.pregnancy_period_id 
                               WHERE r.person_id = ? AND p.checkup_date = ? AND p.months_pregnancy = ? 
                               AND p.last_menstruation = ? AND p.expected_delivery_date = ? 
                               AND p.preg_count = ? AND p.child_alive = ?");
        $stmt->execute([$person_id, $checkup_date, $months_pregnancy, $lmp, $edc, $preg_count, $child_alive]);
        if ($stmt->fetchColumn() > 0) {
            die("Error: This exact submission already exists for the selected mother.");
        }

        // Fetch the user_id associated with the selected person_id
        $stmt = $pdo->prepare("SELECT user_id FROM records WHERE person_id = ? AND record_type = 'pregnancy_record.prenatal' LIMIT 1");
        $stmt->execute([$person_id]);
        $selected_user_id = $stmt->fetchColumn();
        if ($selected_user_id === false) {
            $selected_user_id = $_SESSION['user_id'];
        }

        // Check for existing record in records table
        $stmt = $pdo->prepare("SELECT records_id FROM records WHERE user_id = ? AND person_id = ? AND record_type = ?");
        $stmt->execute([$selected_user_id, $person_id, 'pregnancy_record.prenatal']);
        $records_id = $stmt->fetchColumn();

        // Update person with philhealth_number
        $stmt = $pdo->prepare("UPDATE person SET philhealth_number = ? WHERE person_id = ?");
        $stmt->execute([$philhealth_number, $person_id]);

        // Handle medication
        $medication_id = null;
        if (!empty($medications)) {
            $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
            $stmt->execute([$medications[0]]);
            $medication_id = $stmt->fetchColumn();
            if ($medication_id === false) {
                $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                $stmt->execute([$medications[0]]);
                $medication_id = $pdo->lastInsertId();
            }
            foreach (array_slice($medications, 1) as $medication_name) {
                $stmt = $pdo->prepare("SELECT medication_id FROM medication WHERE medication_name = ?");
                $stmt->execute([$medication_name]);
                $existing_id = $stmt->fetchColumn();
                if ($existing_id === false) {
                    $stmt = $pdo->prepare("INSERT INTO medication (medication_name) VALUES (?)");
                    $stmt->execute([$medication_name]);
                }
            }
        }

        // Check if 'Prenatal' status exists in pregnancy_period
        $stmt = $pdo->prepare("SELECT pregnancy_period_id FROM pregnancy_period WHERE status = 'Prenatal' LIMIT 1");
        $stmt->execute();
        $pregnancy_period_id = $stmt->fetchColumn();
        if ($pregnancy_period_id === false) {
            $stmt = $pdo->prepare("INSERT INTO pregnancy_period (status) VALUES ('Prenatal')");
            $stmt->execute();
            $pregnancy_period_id = $pdo->lastInsertId();
        }

        if ($records_id) {
            // Existing record found, update related tables
            $stmt = $pdo->prepare("UPDATE pregnancy_record SET medication_id = ? WHERE records_id = ? AND pregnancy_period_id = ?");
            $stmt->execute([$medication_id, $records_id, $pregnancy_period_id]);

            $stmt = $pdo->prepare("UPDATE prenatal SET checkup_date = ?, months_pregnancy = ?, risk_observed = ?, birth_plan = ?, last_menstruation = ?, expected_delivery_date = ?, preg_count = ?, child_alive = ? WHERE pregnancy_period_id = ?");
            $stmt->execute([$checkup_date, $months_pregnancy, $risks, $birth_plan, $lmp, $edc, $preg_count, $child_alive, $pregnancy_period_id]);

            $activity = "FORM_UPDATED: pregnant_form for person_id:$person_id, PENDING";
        } else {
            // No existing record, insert new one
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id, record_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$selected_user_id, $person_id, 'pregnancy_record.prenatal', $_SESSION['user_id']]);
            $records_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO pregnancy_record (records_id, pregnancy_period_id, medication_id) VALUES (?, ?, ?)");
            $stmt->execute([$records_id, $pregnancy_period_id, $medication_id]);

            $stmt = $pdo->prepare("INSERT INTO prenatal (pregnancy_period_id, checkup_date, months_pregnancy, risk_observed, birth_plan, last_menstruation, expected_delivery_date, preg_count, child_alive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pregnancy_period_id, $checkup_date, $months_pregnancy, $risks, $birth_plan, $lmp, $edc, $preg_count, $child_alive]);

            $activity = "FORM_SUBMITTED: pregnant_form for person_id:$person_id, PENDING";
        }

        // Log the activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $activity]);
        
        header("Location: pregnant_form.php");
        exit;
    }
}

// Call the processing function
processPregnantForm($pdo);
?>