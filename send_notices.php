<?php
session_start();
require_once 'db_connect.php';
require('fpdf.php'); // Include FPDF library
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$role_id = $user['role_id'];

if (!in_array($role_id, [1, 2, 4])) {
    header("Location: dashboard.php");
    exit;
}

// Set sender name to BRGYCare system
$sender_name = 'BRGYCare';
$sender_role = $role_id == 1 ? 'BHW Head' : ($role_id == 4 ? 'Super Admin' : 'BHW Staff');

// Restrict purok for BHW Staff
$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

$eligible_recipients = [];
$vaccination_eligible = []; // Separate array for vaccination eligible
$medication_eligible = []; // Separate array for medication eligible
$vaccinations = [];
$medications = [];
$notice_type = '';
$custom_notice = '';
$notice_date = date('Y-m-d');
$schedule = 'immediate';
$purok = '';
$required_ages = [];
$age_types = [];
$custom_vaccination_ages = [];

// Add error flag
$has_errors = false;

// Add flag to track if PDF was generated
$pdf_generated = false;

// Function to generate PDF
function generatePDF($data, $title, $notice_type, $details, $notice_date, $emails_sent, $emails_failed) {
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'BRGYCare - ' . $title, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Notice Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Notice Details:', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, 'Type: ' . ucfirst($notice_type), 0, 1);
    $pdf->Cell(0, 8, 'Details: ' . $details, 0, 1);
    $pdf->Cell(0, 8, 'Date: ' . $notice_date, 0, 1);
    $pdf->Cell(0, 8, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
    $pdf->Cell(0, 8, 'Emails Sent: ' . $emails_sent, 0, 1);
    $pdf->Cell(0, 8, 'Emails Failed: ' . $emails_failed, 0, 1);
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Age', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Purok', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Email', 1, 1, 'C');
    
    // Table Data
    $pdf->SetFont('Arial', '', 10);
    foreach ($data as $recipient) {
        $pdf->Cell(60, 8, $recipient['full_name'], 1);
        $pdf->Cell(30, 8, $recipient['age'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(40, 8, $recipient['purok'], 1, 0, 'C');
        $pdf->Cell(60, 8, $recipient['email'] ?? 'No email', 1, 1);
    }
    
    // Summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Total Eligible Recipients: ' . count($data), 0, 1);
    
    return $pdf;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice'])) {
    $purok = $_POST['purok'];
    $notice_type = $_POST['notice_type'];
    $schedule = $_POST['schedule'];
    $vaccinations = $_POST['vaccination'] ?? [];
    $medications = $_POST['medication'] ?? [];
    $custom_notice = $_POST['custom_notice'] ?? '';
    $notice_date = $_POST['notice_date'] ?? date('Y-m-d');
    $place_selection = $_POST['place'] ?? '';
    $custom_place_text = $_POST['custom_place'] ?? '';
    $place = ($place_selection === 'custom') ? $custom_place_text : $place_selection;

    // Validate required fields
    if (empty($notice_type) && empty($custom_notice)) {
        $has_errors = true;
        echo "<div class='alert alert-danger'>Please select a notice type or enter a custom notice.</div>";
    }

    // Collect required ages and age types
    $required_ages = array_filter($_POST['required_ages'] ?? [], 'is_numeric');
    if (isset($_POST['age_type']) && is_array($_POST['age_type'])) {
        foreach ($_POST['age_type'] as $index => $age_type) {
            if ($age_type === 'single' && isset($_POST['single_age'][$index]) && is_numeric($_POST['single_age'][$index])) {
                $age_types[] = ['type' => 'single', 'value' => (int)$_POST['single_age'][$index]];
            } elseif ($age_type === 'range' && isset($_POST['range_min'][$index], $_POST['range_max'][$index]) && is_numeric($_POST['range_min'][$index]) && is_numeric($_POST['range_max'][$index]) && $_POST['range_min'][$index] <= $_POST['range_max'][$index]) {
                $age_types[] = ['type' => 'range', 'min' => (int)$_POST['range_min'][$index], 'max' => (int)$_POST['range_max'][$index]];
            } elseif ($age_type === 'eligible' && isset($_POST['eligible_min'][$index], $_POST['eligible_max'][$index]) && is_numeric($_POST['eligible_min'][$index]) && is_numeric($_POST['eligible_max'][$index]) && $_POST['eligible_min'][$index] <= $_POST['eligible_max'][$index]) {
                $age_types[] = ['type' => 'eligible', 'min' => (int)$_POST['eligible_min'][$index], 'max' => (int)$_POST['eligible_max'][$index]];
            }
        }
    }

    // Handle custom vaccination age requirements
    if (isset($_POST['custom_vaccination']) && !empty($_POST['custom_vaccination'])) {
        $custom_vaccination = $_POST['custom_vaccination'];
        $vaccinations[] = $custom_vaccination;
        if (isset($_POST['age_type']) && is_array($_POST['age_type'])) {
            foreach ($_POST['age_type'] as $index => $age_type) {
                if ($age_type === 'single' && isset($_POST['single_age'][$index]) && is_numeric($_POST['single_age'][$index])) {
                    $custom_vaccination_ages[$custom_vaccination][] = ['type' => 'single', 'value' => (int)$_POST['single_age'][$index]];
                } elseif ($age_type === 'range' && isset($_POST['range_min'][$index], $_POST['range_max'][$index]) && is_numeric($_POST['range_min'][$index]) && is_numeric($_POST['range_max'][$index]) && $_POST['range_min'][$index] <= $_POST['range_max'][$index]) {
                    $custom_vaccination_ages[$custom_vaccination][] = ['type' => 'range', 'min' => (int)$_POST['range_min'][$index], 'max' => (int)$_POST['range_max'][$index]];
                } elseif ($age_type === 'eligible' && isset($_POST['eligible_min'][$index], $_POST['eligible_max'][$index]) && is_numeric($_POST['eligible_min'][$index]) && is_numeric($_POST['eligible_max'][$index]) && $_POST['eligible_min'][$index] <= $_POST['eligible_max'][$index]) {
                    $custom_vaccination_ages[$custom_vaccination][] = ['type' => 'eligible', 'min' => (int)$_POST['eligible_min'][$index], 'max' => (int)$_POST['eligible_max'][$index]];
                }
            }
        }
    }

    // Join multiple selections with commas
    $vaccination_details = is_array($vaccinations) && !empty($vaccinations) ? implode(', ', $vaccinations) : '';
    $medication_details = is_array($medications) && !empty($medications) ? implode(', ', $medications) : '';
    $details = $vaccination_details ?: ($medication_details ?: "$custom_notice");

    $notice_content = "NOTICE: We would like to inform you that there's going to be a $notice_type" .
                  (!empty($details) ? " in $details" : " $custom_notice") .
                  " this $notice_date" .
                  (!empty($place) ? " at $place" : "") .
                  ". Please be there.";

    // Current date for age and record checks
    $current_date = new DateTime('2025-09-11 07:47:00', new DateTimeZone('America/Los_Angeles'));

    // Fetch all unique household heads
    $purok_condition = ($role_id == 2 && $user_purok && $purok === '') ? "AND a.purok = ?" : ($purok && $purok !== '' ? "AND a.purok = ?" : "");
    $params = [];
    if ($purok_condition) {
        $params = [$purok ?: $user_purok];
    }
    $stmt = $pdo->prepare("SELECT DISTINCT u.user_id, p.person_id, p.full_name, p.age, p.birthdate, p.relationship_type, a.purok, u.email
                           FROM users u
                           JOIN records r ON u.user_id = r.user_id
                           JOIN person p ON r.person_id = p.person_id
                           JOIN address a ON p.address_id = a.address_id
                           WHERE u.role_id = 3 AND p.relationship_type = 'Head' $purok_condition");
    $stmt->execute($params);
    $household_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only proceed with processing if no errors
    if (!$has_errors) {
        foreach ($household_users as $recipient) {
            $eligible = false;
            $age = $recipient['age'] ?? 0;
            $birthdate = new DateTime($recipient['birthdate'] ?? '1970-01-01', new DateTimeZone('America/Los_Angeles'));
            $age_in_years = floor($current_date->diff($birthdate)->days / 365.25);

            // Check age requirements first
            if (!empty($age_types)) {
                $age_eligible = false;
                foreach ($age_types as $age_rule) {
                    if ($age_rule['type'] === 'single' && $age_in_years == $age_rule['value']) {
                        $age_eligible = true;
                    } elseif ($age_rule['type'] === 'range' && $age_in_years >= $age_rule['min'] && $age_in_years <= $age_rule['max']) {
                        $age_eligible = true;
                    } elseif ($age_rule['type'] === 'eligible' && $age_in_years >= $age_rule['min'] && $age_in_years <= $age_rule['max']) {
                        $age_eligible = true;
                    }
                }
                if ($age_eligible) {
                    $eligible = true;
                }
            }

            // Check vaccination eligibility if not already eligible from age requirements
            if (!$eligible && $notice_type == 'vaccination' && !empty($vaccinations)) {
                foreach ($vaccinations as $vaccine) {
                    $stmt = $pdo->prepare("SELECT MAX(cr.measurement_date) as last_date 
                                           FROM person p
                                           LEFT JOIN records r ON p.person_id = r.person_id AND r.record_type = 'child_record.infant_record'
                                           LEFT JOIN child_record cr ON r.records_id = cr.records_id
                                           LEFT JOIN immunization i ON cr.immunization_id = i.immunization_id
                                           WHERE p.person_id = ? AND i.immunization_type = ?");
                    $stmt->execute([$recipient['person_id'], $vaccine]);
                    $last_date = $stmt->fetchColumn();
                    $five_years_ago = clone $current_date;
                    $five_years_ago->modify('-5 years');
                    $eligible_age = false;

                    // Default age ranges based on immunization guide
                    $age_ranges = [
                        'BCG' => [0, 1], 'HepB' => [0, 19], 'DTP1' => [0.114, 7], 'DTP2' => [0.23, 7], 'DTP3' => [0.322, 7],
                        'OPV1' => [0.114, 5], 'OPV2' => [0.23, 5], 'OPV3' => [0.322, 5], 'IPV1' => [0.322, 5], 'IPV2' => [0.322, 5],
                        'PCV1' => [0.114, 5], 'PCV2' => [0.23, 5], 'PCV3' => [0.322, 5], 'MCV1' => [0.75, 100], 'MCV2' => [1, 100]
                    ];

                    if (isset($age_ranges[$vaccine])) {
                        $min_age = $age_ranges[$vaccine][0];
                        $max_age = $age_ranges[$vaccine][1];
                        $eligible_age = ($age_in_years >= $min_age && $age_in_years <= $max_age);
                    } elseif (isset($custom_vaccination_ages[$vaccine])) {
                        foreach ($custom_vaccination_ages[$vaccine] as $age_rule) {
                            if ($age_rule['type'] === 'single' && $age_in_years == $age_rule['value']) {
                                $eligible_age = true;
                            } elseif ($age_rule['type'] === 'range' && $age_in_years >= $age_rule['min'] && $age_in_years <= $age_rule['max']) {
                                $eligible_age = true;
                            } elseif ($age_rule['type'] === 'eligible' && $age_in_years >= $age_rule['min'] && $age_in_years <= $age_rule['max']) {
                                $eligible_age = true;
                            }
                        }
                    }

                    if ($eligible_age) {
                        // If age requirement is set, override the 5-year gap check and make eligible
                        if (!empty($age_types) || !empty($required_ages)) {
                            $eligible = true;
                        } elseif (!$last_date || new DateTime($last_date) < $five_years_ago) {
                            $eligible = true;
                        }
                    }
                }
            } elseif ($notice_type == 'medication' && !empty($medications)) {
                // FIXED: Medication eligibility - include all seniors 60 and above
                // First check if person is 60 or older
                if ($age_in_years >= 60) {
                    $eligible = true;
                    
                    // If specific medications are selected, check last medication date
                    foreach ($medications as $medication) {
                        $stmt = $pdo->prepare("SELECT MAX(sr.bp_date_taken) as last_date 
                                               FROM senior_record sr
                                               JOIN records r ON sr.records_id = r.records_id
                                               JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
                                               JOIN medication m ON sm.medication_id = m.medication_id
                                               WHERE r.person_id = ? AND m.medication_name = ?");
                        $stmt->execute([$recipient['person_id'], $medication]);
                        $last_date = $stmt->fetchColumn();
                        $one_year_ago = clone $current_date;
                        $one_year_ago->modify('-1 year');
                        
                        // If they recently had this medication, they might not be eligible
                        if ($last_date && new DateTime($last_date) >= $one_year_ago) {
                            $eligible = false;
                            break;
                        }
                    }
                }
            } elseif ($notice_type == '' && !empty($custom_notice)) {
                $eligible = true;
            }

            if ($eligible) {
                $eligible_recipients[] = $recipient;
                
                // Categorize recipients for PDF
                if ($notice_type == 'vaccination') {
                    $vaccination_eligible[] = $recipient;
                } elseif ($notice_type == 'medication') {
                    $medication_eligible[] = $recipient;
                }
                
                // Only log to activity if no errors and we're actually sending
                if (!$has_errors && isset($_POST['send_notice'])) {
                    $activity_msg = $notice_content . " to_user_id:{$recipient['user_id']} schedule:$schedule";
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], $activity_msg]);
                }
            }
        }

        // Send emails to eligible recipients only if no errors
        if (!$has_errors && !empty($eligible_recipients) && isset($_POST['send_notice'])) {
            $emails_sent = 0;
            $emails_failed = 0;
            foreach ($eligible_recipients as $recipient) {
                if (!empty($recipient['email'])) {
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP host
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'koin.koinbeef0909@gmail.com'; // Replace with your email
                        $mail->Password   = 'lvne scoz ucjw lrng'; // Replace with your app password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        // Recipients - Changed to BRGYCare system
                        $mail->setFrom('your-email@gmail.com', 'BRGYCare System');
                        $mail->addAddress($recipient['email'], $recipient['full_name']);

                        // Content - Updated subject to reflect system name
                        $mail->isHTML(false);
                        $mail->Subject = 'Health Notice from BRGYCare System';
                        $mail->Body    = $notice_content . "\n\nRecipient: " . $recipient['full_name'] . "\nPurok: " . $recipient['purok'] . "\n\nThis notice was sent by the BRGYCare automated system.";

                        $mail->send();
                        $emails_sent++;
                    } catch (Exception $e) {
                        $emails_failed++;
                        // Log error if needed
                    }
                }
            }

            // Generate and download PDF after sending emails
            if ($notice_type == 'vaccination' && !empty($vaccination_eligible)) {
                $title = 'Vaccination Notice Report';
                $details = implode(', ', $vaccinations);
                $pdf = generatePDF($vaccination_eligible, $title, $notice_type, $details, $notice_date, $emails_sent, $emails_failed);
                
                // Set flag to indicate PDF was generated
                $pdf_generated = true;
                
                // Output PDF for download
                $pdf->Output('D', 'Vaccination_Notice_Report_' . date('Y-m-d_H-i-s') . '.pdf');
                
                // After PDF download, set session flag for reload
                $_SESSION['reload_after_download'] = true;
                exit;
                
            } elseif ($notice_type == 'medication' && !empty($medication_eligible)) {
                $title = 'Medication Notice Report';
                $details = implode(', ', $medications);
                $pdf = generatePDF($medication_eligible, $title, $notice_type, $details, $notice_date, $emails_sent, $emails_failed);
                
                // Set flag to indicate PDF was generated
                $pdf_generated = true;
                
                // Output PDF for download
                $pdf->Output('D', 'Medication_Notice_Report_' . date('Y-m-d_H-i-s') . '.pdf');
                
                // After PDF download, set session flag for reload
                $_SESSION['reload_after_download'] = true;
                exit;
                
            } elseif (!empty($eligible_recipients)) {
                $title = 'Custom Notice Report';
                $pdf = generatePDF($eligible_recipients, $title, $notice_type, $custom_notice, $notice_date, $emails_sent, $emails_failed);
                
                // Set flag to indicate PDF was generated
                $pdf_generated = true;
                
                // Output PDF for download
                $pdf->Output('D', 'Custom_Notice_Report_' . date('Y-m-d_H-i-s') . '.pdf');
                
                // After PDF download, set session flag for reload
                $_SESSION['reload_after_download'] = true;
                exit;
            }
        }
    }
}

// Check if we need to reload after download
if (isset($_SESSION['reload_after_download']) && $_SESSION['reload_after_download']) {
    unset($_SESSION['reload_after_download']);
    
    // Clear form data by redirecting to same page without POST data
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Send Notices</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
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
            position: relative;
            z-index: 1030;
            margin-top: 0;
        }
        .content.with-sidebar { margin-left: 0; }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
        .form-group label {
            font-weight: 500;
            color: #2d3748;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 15px;
            color: #1a202c;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
        }
        .form-control::placeholder { color: #718096; }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 12px 20px;
            font-size: 1rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
            width: 100%;
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
        .custom-field { display: none; margin-top: 10px; }
        .show { display: block !important; }
        .checkbox-group { margin-bottom: 10px; }
        .checkbox-group label { margin-left: 5px; }
        .eligible-list { margin-top: 20px; padding: 10px; background: #fff; border-radius: 8px; }
        .age-required-group { margin-bottom: 10px; }
        .custom-age-group { margin-top: 10px; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; }
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
            .card { margin-bottom: 15px; margin-left: 15px; margin-right: 0; }
            .checkbox-group { display: flex; flex-wrap: wrap; }
            .checkbox-group input[type="checkbox"] { margin-right: 10px; }
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Your existing JavaScript functions remain the same
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

        function toggleNoticeFields() {
            const noticeTypeSelect = document.getElementById('notice_type');
            const vaccinationGroup = document.getElementById('vaccination_group');
            const medicationGroup = document.getElementById('medication_group');
            const customNoticeGroup = document.getElementById('custom_notice_group');
            const customVaccinationField = document.getElementById('custom_vaccination_field');
            const customMedicationField = document.getElementById('custom_medication_field');
            const vaccinationSelect = document.getElementById('vaccination_select');
            const medicationSelect = document.getElementById('medication_select');
            const customNoticeInput = document.querySelector('input[name="custom_notice"]');

            if (!noticeTypeSelect) {
                console.error('Element with id "notice_type" not found');
                return;
            }
            if (!vaccinationGroup) {
                console.error('Element with id "vaccination_group" not found');
                return;
            }
            if (!medicationGroup) {
                console.error('Element with id "medication_group" not found');
                return;
            }
            if (!customNoticeGroup) {
                console.error('Element with id "custom_notice_group" not found');
                return;
            }
            if (!customVaccinationField) {
                console.error('Element with id "custom_vaccination_field" not found');
                return;
            }
            if (!customMedicationField) {
                console.error('Element with id "custom_medication_field" not found');
                return;
            }
            if (!vaccinationSelect) {
                console.error('Element with id "vaccination_select" not found');
                return;
            }
            if (!medicationSelect) {
                console.error('Element with id "medication_select" not found');
                return;
            }
            if (!customNoticeInput) {
                console.error('Element with name "custom_notice" not found');
                return;
            }

            const noticeType = noticeTypeSelect.value;
            vaccinationGroup.style.display = noticeType === 'vaccination' ? 'block' : 'none';
            medicationGroup.style.display = noticeType === 'medication' ? 'block' : 'none';
            customNoticeGroup.style.display = noticeType === '' && customNoticeInput.value.trim() !== '' ? 'block' : 'none';
            customVaccinationField.classList.remove('show');
            customMedicationField.classList.remove('show');
            vaccinationSelect.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
            medicationSelect.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
        }

        function addAgeRequired() {
            const container = document.getElementById('age-required-container');
            if (container) {
                // Clear existing groups before adding a new one to prevent duplication
                container.innerHTML = '';
                const newGroup = document.createElement('div');
                newGroup.className = 'custom-age-group';
                newGroup.innerHTML = `
                    <div class="form-group">
                        <label>Age Requirement Type</label>
                        <select class="form-control age-type-select" onchange="toggleAgeFields(this)" name="age_type[]">
                            <option value="single">Single Age</option>
                            <option value="range">Age Range</option>
                            <option value="eligible">Age Eligible Range</option>
                        </select>
                    </div>
                    <div class="form-group single-age" style="display: none;">
                        <input type="number" class="form-control" name="single_age[]" min="0" max="120" placeholder="Enter single age">
                    </div>
                    <div class="form-group age-range" style="display: none;">
                        <input type="number" class="form-control" name="range_min[]" min="0" max="120" placeholder="Min Age">
                        <input type="number" class="form-control" name="range_max[]" min="0" max="120" placeholder="Max Age">
                    </div>
                    <div class="form-group eligible-range" style="display: none;">
                        <input type="number" class="form-control" name="eligible_min[]" min="0" max="120" placeholder="Min Eligible Age">
                        <input type="number" class="form-control" name="eligible_max[]" min="0" max="120" placeholder="Max Eligible Age">
                    </div>
                `;
                container.appendChild(newGroup);
            }
        }

        function toggleAgeFields(select) {
            const parent = select.parentElement.parentElement;
            const singleAge = parent.querySelector('.single-age');
            const ageRange = parent.querySelector('.age-range');
            const eligibleRange = parent.querySelector('.eligible-range');
            if (singleAge && ageRange && eligibleRange) {
                singleAge.style.display = select.value === 'single' ? 'block' : 'none';
                ageRange.style.display = select.value === 'range' ? 'block' : 'none';
                eligibleRange.style.display = select.value === 'eligible' ? 'block' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const noticeTypeElement = document.getElementById('notice_type');
            if (noticeTypeElement) {
                noticeTypeElement.addEventListener('change', toggleNoticeFields);
                // Initial call to set visibility
                toggleNoticeFields();
            } else {
                console.error('Element with id "notice_type" not found');
            }

            const addVaccinationBtn = document.getElementById('add_vaccination_btn');
            if (addVaccinationBtn) {
                addVaccinationBtn.addEventListener('click', function() {
                    const field = document.getElementById('custom_vaccination_field');
                    console.log('Add Vaccination clicked, field:', field);
                    if (field) {
                        field.classList.toggle('show');
                        console.log('Field classList:', field.classList);
                        const vaccinationSelect = document.getElementById('vaccination_select');
                        if (vaccinationSelect) {
                            vaccinationSelect.style.display = field.classList.contains('show') ? 'none' : 'block';
                            if (!field.classList.contains('show')) {
                                vaccinationSelect.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
                            } else {
                                const customInput = field.querySelector('input[name="custom_notice"]');
                                if (customInput) customInput.value = '';
                            }
                        } else {
                            console.error('vaccination_select not found');
                        }
                    } else {
                        console.error('custom_vaccination_field not found');
                    }
                });
            } else {
                console.error('add_vaccination_btn not found');
            }

            const addMedicationBtn = document.getElementById('add_medication_btn');
            if (addMedicationBtn) {
                addMedicationBtn.addEventListener('click', function() {
                    const field = document.getElementById('custom_medication_field');
                    if (field) {
                        field.classList.toggle('show');
                        const medicationSelect = document.getElementById('medication_select');
                        if (medicationSelect) {
                            medicationSelect.style.display = field.classList.contains('show') ? 'none' : 'block';
                            if (!field.classList.contains('show')) {
                                medicationSelect.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
                            }
                        }
                    }
                });
            }

            const addNoticeBtn = document.getElementById('add_notice_btn');
            if (addNoticeBtn) {
                addNoticeBtn.addEventListener('click', function() {
                    const group = document.getElementById('custom_notice_group');
                    if (group) {
                        group.style.display = 'block';
                        const noticeTypeSelect = document.getElementById('notice_type');
                        if (noticeTypeSelect) noticeTypeSelect.value = '';
                        const vaccinationGroup = document.getElementById('vaccination_group');
                        if (vaccinationGroup) vaccinationGroup.style.display = 'none';
                        const medicationGroup = document.getElementById('medication_group');
                        if (medicationGroup) medicationGroup.style.display = 'none';
                        const customVaccinationField = document.getElementById('custom_vaccination_field');
                        if (customVaccinationField) customVaccinationField.classList.remove('show');
                        const customMedicationField = document.getElementById('custom_medication_field');
                        if (customMedicationField) customMedicationField.classList.remove('show');
                    }
                });
            }

            const addAgeBtn = document.getElementById('add-age-btn');
            if (addAgeBtn) {
                addAgeBtn.addEventListener('click', addAgeRequired);
            }

            $('.accordion-header').on('click', function() {
                const content = $(this).next('.accordion-content');
                content.toggleClass('active');
            });

            const placeSelect = document.getElementById('place_select');
            const customPlaceField = document.getElementById('custom_place_field');

            if (placeSelect && customPlaceField) {
                placeSelect.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customPlaceField.style.display = 'block';
                        customPlaceField.querySelector('input').focus();
                    } else {
                        customPlaceField.style.display = 'none';
                        customPlaceField.querySelector('input').value = '';
                    }
                });
            }

            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice'])): ?>
                const vaccinationGroup = document.getElementById('vaccination_group');
                const medicationGroup = document.getElementById('medication_group');
                const customNoticeGroup = document.getElementById('custom_notice_group');
                if (vaccinationGroup && medicationGroup && customNoticeGroup) {
                    if ('<?php echo $notice_type; ?>' === 'vaccination') {
                        vaccinationGroup.style.display = 'block';
                    } else if ('<?php echo $notice_type; ?>' === 'medication') {
                        medicationGroup.style.display = 'block';
                    } else if ('<?php echo $custom_notice; ?>' !== '') {
                        customNoticeGroup.style.display = 'block';
                    }
                }
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 content">
                <div class="card">
                    <div class="card-header">Send Notices</div>
                    <div class="card-body p-3">
                        <form action="send_notices.php" method="POST" id="noticeForm">
                            <div class="form-group">
                                <label>Purok</label>
                                <select class="form-control" name="purok" required>
                                    <?php
                                    $stmt = $pdo->query("SELECT DISTINCT purok FROM address ORDER BY purok");
                                    while ($row = $stmt->fetch()) {
                                        $selected = ($role_id == 2 && $user_purok && $row['purok'] == $user_purok) || (!$user_purok && $row['purok'] == $purok) ? 'selected' : '';
                                        echo "<option value='{$row['purok']}' $selected>{$row['purok']}</option>";
                                    }
                                    if ($role_id == 1 || $role_id == 4) {
                                        echo "<option value='' " . (!$purok ? 'selected' : '') . ">All Puroks</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notice Type</label>
                                <select class="form-control" name="notice_type" id="notice_type">
                                    <option value="">Select Notice Type</option>
                                    <option value="vaccination" <?php echo isset($notice_type) && $notice_type == 'vaccination' ? 'selected' : ''; ?>>Vaccination/Immunization</option>
                                    <option value="medication" <?php echo isset($notice_type) && $notice_type == 'medication' ? 'selected' : ''; ?>>Medication</option>
                                </select>
                                <button type="button" class="btn btn-secondary mt-2" id="add_notice_btn">Add a Notice</button>
                            </div>
                            <div class="form-group" id="vaccination_group" style="display: none;">
                                <label>Vaccination/Immunization</label>
                                <div class="checkbox-group" id="vaccination_select">
                                    <?php
                                    $vaccines = [
                                        'BCG' => [0, 1], 'HepB' => [0, 19], 'DTP1' => [0.114, 7], 'DTP2' => [0.23, 7], 'DTP3' => [0.322, 7],
                                        'OPV1' => [0.114, 5], 'OPV2' => [0.23, 5], 'OPV3' => [0.322, 5], 'IPV1' => [0.322, 5], 'IPV2' => [0.322, 5],
                                        'PCV1' => [0.114, 5], 'PCV2' => [0.23, 5], 'PCV3' => [0.322, 5], 'MCV1' => [0.75, 100], 'MCV2' => [1, 100]
                                    ];
                                    foreach ($vaccines as $vaccine => $age_range) {
                                        $checked = in_array($vaccine, $vaccinations) ? 'checked' : '';
                                        echo "<div class='checkbox-group'><input type='checkbox' name='vaccination[]' value='$vaccine' $checked> <label>$vaccine (Age: {$age_range[0]}-{$age_range[1]} years)</label></div>";
                                    }
                                    ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" id="add_vaccination_btn">Add a Vaccination/Immunization</button>
                                <div class="custom-field" id="custom_vaccination_field" style="display: <?php echo in_array('custom', $vaccinations) ? 'block' : 'none'; ?>;">
                                    <input type="text" class="form-control" name="custom_notice" placeholder="Enter custom vaccination/immunization" value="<?php echo htmlspecialchars($custom_notice ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group" id="medication_group" style="display: none;">
                                <label>Medication</label>
                                <div class="checkbox-group" id="medication_select">
                                    <?php
                                    $medications_list = [
                                        'Amlodipine 5mg' => [60, 120], 'Amlodipine 10mg' => [60, 120], 'Losartan 100mg' => [60, 120],
                                        'Metoprolol 50mg' => [60, 120], 'Carvidolol 12.5mg' => [60, 120], 'Simvastatin 20mg' => [60, 120],
                                        'Metformin 500mg' => [60, 120], 'Gliclazide 30mg' => [60, 120]
                                    ];
                                    foreach ($medications_list as $medication => $age_range) {
                                        $checked = in_array($medication, $medications) ? 'checked' : '';
                                        echo "<div class='checkbox-group'><input type='checkbox' name='medication[]' value='$medication' $checked> <label>$medication (Age: {$age_range[0]}-{$age_range[1]} years)</label></div>";
                                    }
                                    ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" id="add_medication_btn">Add a Medication</button>
                                <div class="custom-field" id="custom_medication_field" style="display: <?php echo in_array('custom', $medications) ? 'block' : 'none'; ?>;">
                                    <input type="text" class="form-control" name="custom_notice" placeholder="Enter custom medication" value="<?php echo htmlspecialchars($custom_notice ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group" id="custom_notice_group" style="display: none;">
                                <label>Custom Notice</label>
                                <input type="text" class="form-control" name="custom_notice" placeholder="Enter custom notice" value="<?php echo htmlspecialchars($custom_notice ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Age Requirements</label>
                                <div id="age-required-container">
                                    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice']) && !empty($required_ages)): ?>
                                        <?php foreach ($required_ages as $index => $age): ?>
                                            <div class="age-required-group">
                                                <input type="number" class="form-control" name="required_ages[]" value="<?php echo htmlspecialchars($age); ?>" min="0" max="120" placeholder="Enter required age">
                                                <button type="button" class="btn btn-danger btn-sm ml-2 remove-age" onclick="this.parentElement.remove()">Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['age_type']) && is_array($_POST['age_type'])): ?>
                                        <?php foreach ($_POST['age_type'] as $index => $age_type): ?>
                                            <div class="custom-age-group">
                                                <div class="form-group">
                                                    <label>Age Requirement Type</label>
                                                    <select class="form-control age-type-select" name="age_type[]" onchange="toggleAgeFields(this)">
                                                        <option value="single" <?php echo $age_type == 'single' ? 'selected' : ''; ?>>Single Age</option>
                                                        <option value="range" <?php echo $age_type == 'range' ? 'selected' : ''; ?>>Age Range</option>
                                                        <option value="eligible" <?php echo $age_type == 'eligible' ? 'selected' : ''; ?>>Age Eligible Range</option>
                                                    </select>
                                                </div>
                                                <div class="form-group single-age" style="display: <?php echo $age_type == 'single' ? 'block' : 'none'; ?>;">
                                                    <input type="number" class="form-control" name="single_age[]" value="<?php echo isset($_POST['single_age'][$index]) ? htmlspecialchars($_POST['single_age'][$index]) : ''; ?>" min="0" max="120" placeholder="Enter single age">
                                                </div>
                                                <div class="form-group age-range" style="display: <?php echo $age_type == 'range' ? 'block' : 'none'; ?>;">
                                                    <input type="number" class="form-control" name="range_min[]" value="<?php echo isset($_POST['range_min'][$index]) ? htmlspecialchars($_POST['range_min'][$index]) : ''; ?>" min="0" max="120" placeholder="Min Age">
                                                    <input type="number" class="form-control" name="range_max[]" value="<?php echo isset($_POST['range_max'][$index]) ? htmlspecialchars($_POST['range_max'][$index]) : ''; ?>" min="0" max="120" placeholder="Max Age">
                                                </div>
                                                <div class="form-group eligible-range" style="display: <?php echo $age_type == 'eligible' ? 'block' : 'none'; ?>;">
                                                    <input type="number" class="form-control" name="eligible_min[]" value="<?php echo isset($_POST['eligible_min'][$index]) ? htmlspecialchars($_POST['eligible_min'][$index]) : ''; ?>" min="0" max="120" placeholder="Min Eligible Age">
                                                    <input type="number" class="form-control" name="eligible_max[]" value="<?php echo isset($_POST['eligible_max'][$index]) ? htmlspecialchars($_POST['eligible_max'][$index]) : ''; ?>" min="0, max="120" placeholder="Max Eligible Age">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" id="add-age-btn" onclick="addAgeRequired()">Add Age Required</button>
                            </div>
                            <div class="form-group">
                                <label>Schedule</label>
                                <select class="form-control" name="schedule">
                                    <option value="immediate" <?php echo $schedule == 'immediate' ? 'selected' : ''; ?>>Once</option>
                                    <option value="monthly" <?php echo $schedule == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="yearly" <?php echo $schedule == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date of Notice</label>
                                <input type="date" class="form-control" name="notice_date" value="<?php echo $notice_date; ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Place</label>
                                <select class="form-control" name="place" id="place_select">
                                    <option value="">Select place</option>
                                    <option value="Barangay Hall">Barangay Hall</option>
                                    <option value="Health Center">Health Center</option>
                                    <option value="custom">Add new place...</option>
                                </select>
                                <div class="custom-field" id="custom_place_field">
                                    <input type="text" class="form-control" name="custom_place" placeholder="Enter custom place name">
                                </div>
                            </div>
                            <button type="submit" name="send_notice" class="btn btn-primary">Send Notices & Download PDF</button>
                        </form>
                        
                        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice']) && empty($eligible_recipients)): ?>
                            <div class="alert alert-warning" role="alert">
                                No eligible recipients found based on the selected criteria. Please check your filters (e.g., Purok, Age Requirements, Notice Type).
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice']) && !empty($eligible_recipients)): ?>
                            <div class="eligible-list">
                                <h5>Eligible Recipients:</h5>
                                <ul>
                                    <?php foreach ($eligible_recipients as $recipient): ?>
                                        <li><?php echo htmlspecialchars("{$recipient['full_name']} (Age: {$recipient['age']}, Purok: {$recipient['purok']})"); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .menu-toggle { display: none; }
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
        .sidebar-overlay.active { display: block; }
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
                padding-left: 0;
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 0;
            }
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
            .card { margin-bottom: 15px; }
            .navbar-brand { padding-left: 55px;}
        }
        @media (min-width: 769px) {
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
            .content.with-sidebar { margin-left: 250px; }
        }
    </style>
</body>
</html>