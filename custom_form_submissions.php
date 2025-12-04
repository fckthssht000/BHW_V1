<?php
/**
 * File: custom_form_submissions.php
 * View and manage submissions for custom forms (adapted from household_records.php)
 */
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to refresh JSON file
function refresh_json_file($pdo, $form_id, $year, $json_file) {
    $stmt = $pdo->prepare("
        SELECT 
            cfs.submission_id,
            cfs.submission_data,
            cfs.submitted_at,
            cfs.updated_at,
            p.person_id,
            p.full_name,
            p.age,
            p.gender,
            p.household_number,
            p.relationship_type,
            p.birthdate,
            p.civil_status,
            a.purok,
            a.barangay
        FROM custom_form_submissions cfs
        JOIN person p ON cfs.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE cfs.custom_form_id = ? 
        AND (YEAR(cfs.submitted_at) = ? OR (cfs.updated_at IS NOT NULL AND YEAR(cfs.updated_at) = ?))
        ORDER BY a.purok, p.household_number, p.full_name
    ");
    $stmt->execute([$form_id, $year, $year]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode submission_data JSON for each record
    foreach ($submissions as &$submission) {
        $submission['form_data'] = json_decode($submission['submission_data'], true) ?: [];
    }
    unset($submission);
    
    file_put_contents($json_file, json_encode($submissions));
    return $submissions;
}

// Function to check if JSON needs refresh
function needs_json_refresh($pdo, $form_id, $year, $json_file) {
    if (!file_exists($json_file)) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT MAX(GREATEST(COALESCE(submitted_at, '1970-01-01'), COALESCE(updated_at, '1970-01-01'))) as latest 
        FROM custom_form_submissions 
        WHERE custom_form_id = ? 
        AND (YEAR(submitted_at) = ? OR (updated_at IS NOT NULL AND YEAR(updated_at) = ?))
    ");
    $stmt->execute([$form_id, $year, $year]);
    $db_latest = $stmt->fetchColumn();
    
    $file_time = filemtime($json_file);
    
    if (!$db_latest) return false;
    
    $db_time = strtotime($db_latest);
    return $db_time > $file_time;
}

// Fetch user role
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

// Get user's purok (for Role 2)
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

// Handle POST requests (update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['download'])) {
    $submission_id = $_POST['submission_id'] ?? null;
    if (!$submission_id) {
        $_SESSION['message'] = 'Invalid request.';
        header("Location: custom_form_submissions.php?form_id=" . ($_POST['form_id'] ?? ''));
        exit;
    }

    // Get submission details
    $stmt = $pdo->prepare("
        SELECT cfs.*, p.person_id, a.purok 
        FROM custom_form_submissions cfs
        JOIN person p ON cfs.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE cfs.submission_id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        $_SESSION['message'] = 'Submission not found.';
        header("Location: custom_form_submissions.php?form_id=" . ($_POST['form_id'] ?? ''));
        exit;
    }

    // Check permissions
    $can_access = false;
    if ($role_id == 1 || $role_id == 4) {
        $can_access = true;
    } elseif ($role_id == 2 && $user_purok) {
        $can_access = ($submission['purok'] == $user_purok);
    }

    if (!$can_access) {
        $_SESSION['message'] = 'You do not have permission to perform this action.';
        header("Location: custom_form_submissions.php?form_id=" . $submission['custom_form_id']);
        exit;
    }

    $json_dir = 'data/custom_form_submissions/';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }

    if ($_POST['action'] === 'update') {
        $form_data = json_decode($_POST['form_data'], true);
        
        if (empty($form_data)) {
            $_SESSION['message'] = 'Form data is required.';
            header("Location: custom_form_submissions.php?form_id=" . $submission['custom_form_id']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE custom_form_submissions 
            SET submission_data = ?, updated_at = NOW() 
            WHERE submission_id = ?
        ");
        
        if ($stmt->execute([json_encode($form_data), $submission_id])) {
            $_SESSION['message'] = 'Submission updated successfully.';
        } else {
            $_SESSION['message'] = 'Failed to update submission.';
        }

        // Force JSON refresh
        $current_year = date('Y');
        $current_json = $json_dir . 'form_' . $submission['custom_form_id'] . '_' . $current_year . '.json';
        refresh_json_file($pdo, $submission['custom_form_id'], $current_year, $current_json);
        
    } elseif ($_POST['action'] === 'delete') {
        // Get year before delete
        $year = date('Y', strtotime($submission['updated_at'] ?: $submission['submitted_at']));
        
        $stmt = $pdo->prepare("DELETE FROM custom_form_submissions WHERE submission_id = ?");
        if ($stmt->execute([$submission_id])) {
            $_SESSION['message'] = 'Submission deleted successfully.';
        } else {
            $_SESSION['message'] = 'Failed to delete submission.';
        }

        // Force JSON refresh
        $rec_json = $json_dir . 'form_' . $submission['custom_form_id'] . '_' . $year . '.json';
        refresh_json_file($pdo, $submission['custom_form_id'], $year, $rec_json);
    }

    header("Location: custom_form_submissions.php?form_id=" . $submission['custom_form_id']);
    exit;
}

// Get selected form
$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;

// Get all forms user can access
$allowed_forms_sql = "SELECT * FROM custom_forms WHERE is_active = 1";
if ($role_id == 1 || $role_id == 4) {
    // Full access
    $allowed_forms_sql .= " ORDER BY form_title ASC";
} else {
    // Filter by allowed_roles
    $allowed_forms_sql .= " AND FIND_IN_SET(?, allowed_roles) ORDER BY form_title ASC";
}

$stmt = $pdo->prepare($allowed_forms_sql);
if ($role_id == 1 || $role_id == 4) {
    $stmt->execute();
} else {
    $stmt->execute([$role_id]);
}
$allowed_forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no form selected, show first form or selection page
if (!$form_id && !empty($allowed_forms)) {
    $form_id = $allowed_forms[0]['custom_form_id'];
}
$current_year = date('Y');
if (!$form_id) {
    if (empty($allowed_forms)) {
        $form_id = null;
        $form = null;
        $form_fields = [];
        $submissions = [];
        $filtered_submissions = [];
        $years = [];
        $selected_year = $current_year;
        $is_editable = false;
        $total_submissions = 0;
        $unique_persons = 0;
        $puroks_covered = 0;
        $purok_submissions = [];
        $puroks = [];
        $no_forms_message = true;
        // DON'T EXIT - let it continue to the HTML
    }
} else {
    $no_forms_message = false;
}

// Get selected form details (only if we have a form_id)
if ($form_id) {
    $stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE custom_form_id = ? AND is_active = 1");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) {
        $_SESSION['message'] = 'Form not found or inactive.';
        header("Location: dashboard.php");
        exit;
    }
} else {
    // No form selected and no forms available - set empty form
    $form = null;
}


// Get form fields (only if we have a form_id)
if ($form_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM custom_form_fields 
        WHERE custom_form_id = ? 
        ORDER BY field_order ASC
    ");
    $stmt->execute([$form_id]);
    $form_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get field options for select/checkbox/radio fields
    foreach ($form_fields as &$field) {
        if (in_array($field['field_type'], ['select', 'select2', 'checkbox_group', 'radio'])) {
            $stmt = $pdo->prepare("
                SELECT * FROM custom_form_field_options 
                WHERE field_id = ? 
                ORDER BY option_order ASC
            ");
            $stmt->execute([$field['field_id']]);
            $field['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    unset($field);
} else {
    $form_fields = [];
}

// Directory for JSON files
$json_dir = 'data/custom_form_submissions/';
if (!is_dir($json_dir)) {
    mkdir($json_dir, 0755, true);
}

// Get current year
$current_year = date('Y');

// Query years from DB for this form (only if we have a form_id)
if ($form_id) {
    $stmt_years = $pdo->prepare("
        SELECT DISTINCT YEAR(submitted_at) as year FROM custom_form_submissions WHERE custom_form_id = ?
        UNION
        SELECT DISTINCT YEAR(updated_at) as year FROM custom_form_submissions WHERE custom_form_id = ? AND updated_at IS NOT NULL
        ORDER BY year DESC
    ");
    $stmt_years->execute([$form_id, $form_id]);
    $years_result = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    $years = array_unique($years_result);
} else {
    $years = [$current_year];
}

// Get selected year
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $years) ? $_GET['year'] : $current_year;

// Determine if editable
$is_editable = ($selected_year == $current_year);

if ($form_id) {
    $json_file = $json_dir . 'form_' . $form_id . '_' . $selected_year . '.json';

    // Fetch records
    if (needs_json_refresh($pdo, $form_id, $selected_year, $json_file)) {
        $submissions = refresh_json_file($pdo, $form_id, $selected_year, $json_file);
    } else {
        $submissions = json_decode(file_get_contents($json_file), true) ?: [];
    }
} else {
    $submissions = [];
}

// Filter by role
if ($role_id == 2 && $user_purok) {
    $submissions = array_filter($submissions, function($submission) use ($user_purok) {
        return $submission['purok'] == $user_purok;
    });
}

// Calculate dashboard statistics
$total_submissions = count($submissions);
$unique_persons = count(array_unique(array_column($submissions, 'person_id')));
$puroks_covered = count(array_unique(array_column($submissions, 'purok')));

// Group by purok for Role 1/4
$purok_submissions = [];
if ($role_id == 1 || $role_id == 4) {
    foreach ($submissions as $submission) {
        $purok = $submission['purok'];
        $purok_submissions[$purok][] = $submission;
    }
}

// Apply filters
$filtered_submissions = $submissions;

// Age filter
if (isset($_GET['filter_age']) && $_GET['filter_age'] != 'All') {
    $filtered_submissions = array_filter($filtered_submissions, function($submission) {
        $age = $submission['age'];
        switch ($_GET['filter_age']) {
            case 'Infant': return $age >= 0 && $age < 1;
            case 'Child': return $age >= 1 && $age < 13;
            case 'Teen': return $age >= 13 && $age < 20;
            case 'Adult': return $age >= 20 && $age < 60;
            case 'Elderly': return $age >= 60;
            default: return true;
        }
    });
}

// Gender filter
if (isset($_GET['filter_gender']) && $_GET['filter_gender'] != 'All') {
    $filtered_submissions = array_filter($filtered_submissions, function($submission) {
        return $submission['gender'] == $_GET['filter_gender'];
    });
}

// Search filter
if (!empty($search = isset($_GET['search']) ? trim($_GET['search']) : '')) {
    $filtered_submissions = array_filter($filtered_submissions, function($submission) use ($search) {
        return stripos($submission['full_name'], $search) !== false;
    });
}

$filtered_submissions = array_values($filtered_submissions);

$puroks = array_unique(array_column($filtered_submissions, 'purok'));
sort($puroks);

// Fetch barangay info
$stmt = $pdo->prepare("SELECT barangay, municipality, province FROM address LIMIT 1");
$stmt->execute();
$address = $stmt->fetch(PDO::FETCH_ASSOC);
// Handle PDF download
if (isset($_POST['download'])) {
    $report_type = $_POST['report_type'] ?? 'whole_barangay';
    $selected_purok_pdf = $_POST['purok'] ?? null;
    $download_submissions = $filtered_submissions;
    
    if ($report_type == 'per_purok' && $selected_purok_pdf) {
        $download_submissions = array_filter($download_submissions, function($submission) use ($selected_purok_pdf) {
            return $submission['purok'] == $selected_purok_pdf;
        });
    }

    require_once 'vendor/autoload.php';
    ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($form['form_title']) ?> Report</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; background: #fff; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { width: 100%; padding: 12px; box-sizing: border-box; }
        .header { text-align: left; margin-bottom: 8px; }
        .title h1 { text-align: center; font-size: 16px; margin: 0 0 4px; }
        .meta { text-align: center; font-size: 12px; color: #444; }
        .address-details { text-align: center; margin-bottom: 10px; }
        .address-details span { display: inline-block; margin: 0 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; word-wrap: break-word; vertical-align: middle; }
        th { background: #f2f2f2; font-weight: bold; }
        .grouped-header { background: #e9e9e9; }
        .col-narrow { width: 30px; }
        .col-name { width: 80px; }
        .col-small { width: 40px; }
        .col-medium { width: 60px; }
        tbody td { height: 18px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="header">
            <div class="title">
                <h1><?= htmlspecialchars($form['form_title']) ?></h1>
                <div class="meta">Year: <?= $selected_year; ?> | Record Type: <?= htmlspecialchars($form['record_type']) ?></div>
            </div>
            <div class="address-details">
                <span><strong>Municipality:</strong> <?= htmlspecialchars($address['municipality'] ?? '____________________') ?></span>
                <span><strong>Barangay:</strong> <?= htmlspecialchars($address['barangay'] ?? '____________________') ?></span>
                <span><strong>Province:</strong> <?= htmlspecialchars($address['province'] ?? '____________________') ?></span>
                <span><strong>Purok:</strong> <?= $report_type === 'per_purok' ? htmlspecialchars($selected_purok_pdf) : 'All' ?></span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" class="col-narrow">No.</th>
                    <th colspan="6" class="grouped-header">Personal Information</th>
                    <?php 
                    // Count dynamic fields
                    $dynamic_field_count = count($form_fields);
                    ?>
                    <th colspan="<?= $dynamic_field_count ?>" class="grouped-header">Form Data</th>
                    <th rowspan="2" class="col-medium">Date Submitted</th>
                </tr>
                <tr>
                    <!-- Personal Info Headers -->
                    <th class="col-name">Full Name</th>
                    <th class="col-small">HH#</th>
                    <th class="col-small">Age</th>
                    <th class="col-small">Gender</th>
                    <th class="col-medium">Birthdate</th>
                    <th class="col-medium">Address</th>
                    
                    <!-- Dynamic Field Headers -->
                    <?php foreach ($form_fields as $field): ?>
                        <th class="col-medium"><?= htmlspecialchars($field['field_label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                foreach ($download_submissions as $submission) {
                    $form_data = $submission['form_data'];
                    
                    echo '<tr>';
                    echo '<td>' . $count++ . '</td>';
                    echo '<td style="text-align:left;">' . htmlspecialchars(substr($submission['full_name'], 0, 30)) . '</td>';
                    echo '<td>' . htmlspecialchars($submission['household_number'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($submission['age'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($submission['gender'] ?? 'N/A') . '</td>';
                    echo '<td>' . (!empty($submission['birthdate']) && $submission['birthdate'] != 'N/A'
                            ? date('m/d/Y', strtotime($submission['birthdate']))
                            : 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($submission['purok'] ?? 'N/A') . '</td>';
                    
                    // Dynamic field values
                    foreach ($form_fields as $field) {
                        $field_name = $field['field_name'];
                        $value = $form_data[$field_name] ?? 'N/A';
                        
                        // Handle arrays (checkbox groups)
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        
                        // Truncate long values
                        $value = htmlspecialchars(substr($value, 0, 50));
                        echo '<td>' . $value . '</td>';
                    }
                    
                    echo '<td>' . date('m/d/Y', strtotime($submission['submitted_at'])) . '</td>';
                    echo '</tr>';
                }
                
                // Fill empty rows for printing
                for ($i = count($download_submissions); $i < 30; $i++) {
                    echo '<tr><td>' . ($i + 1) . '</td>';
                    echo str_repeat('<td></td>', 7 + count($form_fields)); // 7 personal info + dynamic fields
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $form['form_title']) . '_' . date('Ymd_His') . '.pdf';
    $html2pdf->output($filename, 'D');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($form['form_title']) ? htmlspecialchars($form['form_title']) . ' - Submissions' : 'Custom Form Submissions' ?> - BRGYCare</title>
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
        
        /* Navbar Styles */
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
        
        /* Sidebar */
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
        
        /* Content */
        .content {
            padding: 20px;
            min-height: calc(100vh - 80px);
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
            z-index: 1030;
        }
        .content.with-sidebar { margin-left: 0; }
        
        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: rgba(43, 108, 176, 0.9);
            color: #fff;
            padding: 15px 20px;
            border-bottom: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.95rem;
        }
        
        /* Stat Cards */
        .stat-card {
            background: white;
            padding: 20px;
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
        
        /* Alerts */
        .alert-custom {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-info-custom {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }
        
        /* Tables */
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
            white-space: nowrap;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .table td {
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        /* Buttons */
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
        .btn-success {
            background: #10b981;
            border: none;
        }
        .btn-success:hover {
            background: #059669;
        }
        
        /* Filter Section */
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
        }
        
        /* Tabs */
        .nav-tabs .nav-link {
            color: #2d3748;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
            background: #edf2f7;
            font-size: 0.9rem;
        }
        .nav-tabs .nav-link.active {
            color: #2b6cb0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            font-weight: 600;
        }
        .tab-content { padding: 15px; background: white; border-radius: 0 0 8px 8px; }
        
        /* Form Selector */
        .form-selector {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-state-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Mobile */
        .menu-toggle { display: none; }
        @media (max-width: 768px) {
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
            .navbar-brand { padding-left: 55px; }
            .stat-card {
                padding: 12px;
                margin-bottom: 10px;
            }
            .stat-value {
                font-size: 1.5rem;
            }
        }
        @media (min-width: 769px) {
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
    
                <?php if (isset($no_forms_message) && $no_forms_message): ?>
                    <!-- No Forms Available -->
                    <div class="empty-state-card" style="background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); padding: 60px 40px; text-align: center; max-width: 600px; margin: 0 auto;">
                        <div class="empty-state-icon" style="font-size: 80px; color: #cbd5e0; margin-bottom: 20px;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h2 class="empty-state-title" style="font-size: 1.8rem; font-weight: 600; color: #2d3748; margin-bottom: 15px;">
                            No Custom Forms Available
                        </h2>
                        <p class="empty-state-text" style="color: #718096; font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6;">
                            There are currently no active custom forms. Please contact your administrator to create forms, or check back later.
                        </p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>

                <!-- Form Selector -->
                <div class="form-selector">
                    <form method="get" class="form-inline">
                        <label for="form_selector" class="mr-2"><i class="fas fa-clipboard-list"></i> <strong>Select Form:</strong></label>
                        <select name="form_id" id="form_selector" class="form-control mr-2" onchange="this.form.submit()">
                            <?php foreach ($allowed_forms as $f): ?>
                                <option value="<?= $f['custom_form_id'] ?>" <?= $f['custom_form_id'] == $form_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['form_title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="custom_form_fill.php?form_id=<?= $form_id ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add New Submission
                        </a>
                    </form>
                </div>
                
                <?php if ($role_id == 1 || $role_id == 4): ?>
                    <!-- Dashboard Stats -->
                    <div class="row mb-3">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Total Submissions</div>
                                <div class="stat-value"><?= number_format($total_submissions) ?></div>
                                <small class="text-muted"><?= number_format($unique_persons) ?> unique persons</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Puroks Covered</div>
                                <div class="stat-value"><?= $puroks_covered ?></div>
                                <small class="text-muted">Total areas</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Viewing Year</div>
                                <div class="stat-value"><?= $selected_year ?></div>
                                <small class="text-muted"><?= $is_editable ? 'Editable' : 'Read-only' ?></small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Record Type</div>
                                <div class="stat-value" style="font-size: 1.2rem;">de><?= htmlspecialchars($form['record_type']) ?></code></div>
                                <small class="text-muted">Form identifier</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_editable): ?>
                        <div class="alert-info-custom alert-custom">
                            <i class="fas fa-info-circle"></i> <strong>Viewing archived data:</strong> Records from <?= $selected_year ?> are read-only. Switch to <?= $current_year ?> to edit records.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-database"></i> <?= htmlspecialchars($form['form_title']) ?> - Submissions 
                        <?php if ($role_id == 2): ?>
                            (<?= htmlspecialchars($user_purok) ?>)
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-info m-3">
                            <?= htmlspecialchars($_SESSION['message']) ?>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <?php if ($role_id == 1 || $role_id == 4): ?>
                            <!-- Year Tabs -->
                            <ul class="nav nav-tabs mb-3" id="yearTabs" role="tablist">
                                <?php foreach ($years as $y): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $y == $selected_year ? 'active' : '' ?>" href="?form_id=<?= $form_id ?>&year=<?= $y ?>">
                                            <i class="fas fa-calendar-alt"></i> <?= $y ?> 
                                            <?php if ($y == $current_year): ?>
                                                <span class="badge badge-success ml-1">Current</span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <!-- PDF Download Form -->
                        <form method="post" class="mb-3">
                            <?php if ($role_id == 1 || $role_id == 4): ?>
                                <div class="form-group d-inline-block mr-2">
                                    <label for="report_type" class="mr-2">Report Type:</label>
                                    <select name="report_type" id="report_type" class="form-control d-inline-block w-auto">
                                        <option value="whole_barangay">Whole Barangay</option>
                                        <option value="per_purok">Per Purok</option>
                                    </select>
                                </div>
                                <div class="form-group d-inline-block mr-2" id="purok_group" style="display:none;">
                                    <label for="purok" class="mr-2">Purok:</label>
                                    <select name="purok" id="purok" class="form-control d-inline-block w-auto">
                                        <?php foreach ($puroks as $purok): ?>
                                            <option value="<?= htmlspecialchars($purok) ?>"><?= htmlspecialchars($purok) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php elseif ($role_id == 2): ?>
                                <input type="hidden" name="report_type" value="per_purok">
                                <input type="hidden" name="purok" value="<?= htmlspecialchars($user_purok) ?>">
                            <?php else: ?>
                                <input type="hidden" name="report_type" value="whole_barangay">
                            <?php endif; ?>
                            <button type="submit" name="download" class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </form>
                        
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="get" class="form-inline">
                                <input type="hidden" name="form_id" value="<?= $form_id ?>">
                                <input type="hidden" name="year" value="<?= $selected_year ?>">
                                
                                <div class="form-group mr-2">
                                    <label for="filter_age" class="mr-2">Age:</label>
                                    <select name="filter_age" id="filter_age" class="form-control">
                                        <option value="All">All</option>
                                        <option value="Infant" <?= (isset($_GET['filter_age']) && $_GET['filter_age'] == 'Infant') ? 'selected' : '' ?>>Infant (0-1)</option>
                                        <option value="Child" <?= (isset($_GET['filter_age']) && $_GET['filter_age'] == 'Child') ? 'selected' : '' ?>>Child (1-12)</option>
                                        <option value="Teen" <?= (isset($_GET['filter_age']) && $_GET['filter_age'] == 'Teen') ? 'selected' : '' ?>>Teen (13-19)</option>
                                        <option value="Adult" <?= (isset($_GET['filter_age']) && $_GET['filter_age'] == 'Adult') ? 'selected' : '' ?>>Adult (20-59)</option>
                                        <option value="Elderly" <?= (isset($_GET['filter_age']) && $_GET['filter_age'] == 'Elderly') ? 'selected' : '' ?>>Elderly (60+)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-2">
                                    <label for="filter_gender" class="mr-2">Gender:</label>
                                    <select name="filter_gender" id="filter_gender" class="form-control">
                                        <option value="All">All</option>
                                        <option value="Male" <?= (isset($_GET['filter_gender']) && $_GET['filter_gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= (isset($_GET['filter_gender']) && $_GET['filter_gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-2">
                                    <label for="search" class="mr-2">Search:</label>
                                    <input type="text" name="search" id="search" class="form-control" placeholder="Name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Apply</button>
                                <a href="?form_id=<?= $form_id ?>&year=<?= $selected_year ?>" class="btn btn-secondary ml-2">Clear</a>
                            </form>
                        </div>
                        
                        <?php if ($role_id == 1 || $role_id == 4): ?>
                            <!-- Purok Tabs -->
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="whole-barangay-tab" data-toggle="tab" href="#whole-barangay" role="tab">
                                        <i class="fas fa-globe"></i> Whole Barangay
                                    </a>
                                </li>
                                <?php foreach ($purok_submissions as $purok => $purok_subs): ?>
                                    <?php $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok); ?>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-<?= $safe_purok ?>" data-toggle="tab" href="#purok-<?= $safe_purok ?>" role="tab">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($purok) ?> 
                                            <span class="badge badge-secondary"><?= count($purok_subs) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="tab-content">
                                <!-- Whole Barangay Tab -->
                                <div class="tab-pane fade show active" id="whole-barangay" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Purok</th>
                                                    <th>Full Name</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <?php foreach ($form_fields as $field): ?>
                                                        <th><?= htmlspecialchars($field['field_label']) ?></th>
                                                    <?php endforeach; ?>
                                                    <th>Date</th>
                                                    <?php if ($is_editable): ?>
                                                        <th>Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($filtered_submissions as $submission): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($submission['purok']) ?></td>
                                                        <td><?= htmlspecialchars($submission['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($submission['age']) ?></td>
                                                        <td><?= htmlspecialchars($submission['gender']) ?></td>
                                                        
                                                        <?php foreach ($form_fields as $field): ?>
                                                            <?php
                                                            $value = $submission['form_data'][$field['field_name']] ?? 'N/A';
                                                            if (is_array($value)) {
                                                                $value = implode(', ', $value);
                                                            }
                                                            ?>
                                                            <td><?= htmlspecialchars($value) ?></td>
                                                        <?php endforeach; ?>
                                                        
                                                        <td><?= date('M d, Y', strtotime($submission['submitted_at'])) ?></td>
                                                        
                                                        <?php if ($is_editable): ?>
                                                            <td>
                                                                <button class="btn btn-sm btn-warning edit-btn" 
                                                                        data-submission-id="<?= $submission['submission_id'] ?>"
                                                                        data-form-data='<?= htmlspecialchars(json_encode($submission['form_data'])) ?>'>
                                                                    <i class='fas fa-edit'></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger delete-btn" 
                                                                        data-submission-id="<?= $submission['submission_id'] ?>">
                                                                    <i class='fas fa-trash'></i>
                                                                </button>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Per-Purok Tabs -->
                                <?php foreach ($purok_submissions as $purok => $purok_subs): ?>
                                    <?php 
                                    $safe_purok = preg_replace('/[^a-zA-Z0-9_-]/', '_', $purok);
                                    $filtered_purok_subs = array_filter($purok_subs, function($sub) use ($filtered_submissions) {
                                        return in_array($sub, $filtered_submissions);
                                    });
                                    ?>
                                    <div class="tab-pane fade" id="purok-<?= $safe_purok ?>" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Full Name</th>
                                                        <th>Age</th>
                                                        <th>Gender</th>
                                                        <?php foreach ($form_fields as $field): ?>
                                                            <th><?= htmlspecialchars($field['field_label']) ?></th>
                                                        <?php endforeach; ?>
                                                        <th>Date</th>
                                                        <?php if ($is_editable): ?>
                                                            <th>Actions</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($filtered_purok_subs as $submission): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($submission['full_name']) ?></td>
                                                            <td><?= htmlspecialchars($submission['age']) ?></td>
                                                            <td><?= htmlspecialchars($submission['gender']) ?></td>
                                                            
                                                            <?php foreach ($form_fields as $field): ?>
                                                                <?php
                                                                $value = $submission['form_data'][$field['field_name']] ?? 'N/A';
                                                                if (is_array($value)) {
                                                                    $value = implode(', ', $value);
                                                                }
                                                                ?>
                                                                <td><?= htmlspecialchars($value) ?></td>
                                                            <?php endforeach; ?>
                                                            
                                                            <td><?= date('M d, Y', strtotime($submission['submitted_at'])) ?></td>
                                                            
                                                            <?php if ($is_editable): ?>
                                                                <td>
                                                                    <button class="btn btn-sm btn-warning edit-btn" 
                                                                            data-submission-id="<?= $submission['submission_id'] ?>"
                                                                            data-form-data='<?= htmlspecialchars(json_encode($submission['form_data'])) ?>'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-danger delete-btn" 
                                                                            data-submission-id="<?= $submission['submission_id'] ?>">
                                                                        <i class='fas fa-trash'></i>
                                                                    </button>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Simple Table for Role 2 -->
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <?php foreach ($form_fields as $field): ?>
                                                <th><?= htmlspecialchars($field['field_label']) ?></th>
                                            <?php endforeach; ?>
                                            <th>Date</th>
                                            <?php if ($is_editable): ?>
                                                <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filtered_submissions as $submission): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($submission['full_name']) ?></td>
                                                <td><?= htmlspecialchars($submission['age']) ?></td>
                                                <td><?= htmlspecialchars($submission['gender']) ?></td>
                                                
                                                <?php foreach ($form_fields as $field): ?>
                                                    <?php
                                                    $value = $submission['form_data'][$field['field_name']] ?? 'N/A';
                                                    if (is_array($value)) {
                                                        $value = implode(', ', $value);
                                                    }
                                                    ?>
                                                    <td><?= htmlspecialchars($value) ?></td>
                                                <?php endforeach; ?>
                                                
                                                <td><?= date('M d, Y', strtotime($submission['submitted_at'])) ?></td>
                                                
                                                <?php if ($is_editable): ?>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning edit-btn" 
                                                                data-submission-id="<?= $submission['submission_id'] ?>"
                                                                data-form-data='<?= htmlspecialchars(json_encode($submission['form_data'])) ?>'>
                                                            <i class='fas fa-edit'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-btn" 
                                                                data-submission-id="<?= $submission['submission_id'] ?>">
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Scripts -->
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

        // Edit button handler
        $(document).on('click', '.edit-btn', function() {
            var submissionId = $(this).data('submission-id');
            var formData = $(this).data('form-data');
            
            $('#edit_submission_id').val(submissionId);
            
            // Populate form fields dynamically
            $.each(formData, function(fieldName, value) {
                var $field = $('[name="edit_' + fieldName + '"]');
                
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        // Handle checkbox groups
                        if (Array.isArray(value)) {
                            value.forEach(function(v) {
                                $('[name="edit_' + fieldName + '[]"][value="' + v + '"]').prop('checked', true);
                            });
                        } else {
                            $field.prop('checked', value == '1' || value == 'true');
                        }
                    } else if ($field.is(':radio')) {
                        $('[name="edit_' + fieldName + '"][value="' + value + '"]').prop('checked', true);
                    } else {
                        $field.val(value);
                    }
                }
            });
            
            $('#editModal').modal('show');
        });

        // Delete button handler
        $(document).on('click', '.delete-btn', function() {
            if (confirm('Are you sure you want to delete this submission? This cannot be undone.')) {
                var submissionId = $(this).data('submission-id');
                $('#delete_submission_id').val(submissionId);
                $('#deleteForm').submit();
            }
        });

        // Report type handler
        $('#report_type').on('change', function() {
            if ($(this).val() == 'per_purok') {
                $('#purok_group').show();
            } else {
                $('#purok_group').hide();
            }
        });
    </script>
    
    <?php if ($is_editable): ?>
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Edit Submission - <?= htmlspecialchars($form['form_title']) ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="submission_id" id="edit_submission_id">
                        <input type="hidden" name="form_id" value="<?= $form_id ?>">
                        
                        <?php foreach ($form_fields as $field): ?>
                            <div class="form-group">
                                <label for="edit_<?= $field['field_name'] ?>">
                                    <?= htmlspecialchars($field['field_label']) ?>
                                    <?php if ($field['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($field['help_text']): ?>
                                    <small class="form-text text-muted"><?= htmlspecialchars($field['help_text']) ?></small>
                                <?php endif; ?>
                                
                                <?php
                                $field_name = $field['field_name'];
                                $placeholder = $field['placeholder'] ?: '';
                                $required = $field['is_required'] ? 'required' : '';
                                
                                switch ($field['field_type']):
                                    case 'text':
                                        echo "<input type='text' class='form-control' name='edit_{$field_name}' id='edit_{$field_name}' placeholder='{$placeholder}' {$required}>";
                                        break;
                                    
                                    case 'textarea':
                                        echo "<textarea class='form-control' name='edit_{$field_name}' id='edit_{$field_name}' rows='3' placeholder='{$placeholder}' {$required}></textarea>";
                                        break;
                                    
                                    case 'number':
                                        $min = $field['validation_rules']['min'] ?? '';
                                        $max = $field['validation_rules']['max'] ?? '';
                                        echo "<input type='number' class='form-control' name='edit_{$field_name}' id='edit_{$field_name}' placeholder='{$placeholder}' min='{$min}' max='{$max}' {$required}>";
                                        break;
                                    
                                    case 'date':
                                        echo "<input type='date' class='form-control' name='edit_{$field_name}' id='edit_{$field_name}' {$required}>";
                                        break;
                                    
                                    case 'select':
                                    case 'select2':
                                        echo "<select class='form-control' name='edit_{$field_name}' id='edit_{$field_name}' {$required}>";
                                        echo "<option value=''>-- Select --</option>";
                                        foreach ($field['options'] as $opt) {
                                            echo "<option value='{$opt['option_value']}'>{$opt['option_label']}</option>";
                                        }
                                        echo "</select>";
                                        break;
                                    
                                    case 'checkbox_group':
                                        foreach ($field['options'] as $opt) {
                                            echo "<div class='form-check'>
                                                    <input class='form-check-input' type='checkbox' name='edit_{$field_name}[]' value='{$opt['option_value']}' id='edit_{$field_name}_{$opt['option_value']}'>
                                                    <label class='form-check-label' for='edit_{$field_name}_{$opt['option_value']}'>{$opt['option_label']}</label>
                                                  </div>";
                                        }
                                        break;
                                    
                                    case 'radio':
                                        foreach ($field['options'] as $opt) {
                                            echo "<div class='form-check'>
                                                    <input class='form-check-input' type='radio' name='edit_{$field_name}' value='{$opt['option_value']}' id='edit_{$field_name}_{$opt['option_value']}' {$required}>
                                                    <label class='form-check-label' for='edit_{$field_name}_{$opt['option_value']}'>{$opt['option_label']}</label>
                                                  </div>";
                                        }
                                        break;
                                    
                                    case 'toggle':
                                        echo "<div class='form-check form-switch'>
                                                <input class='form-check-input' type='checkbox' name='edit_{$field_name}' id='edit_{$field_name}' value='1'>
                                                <label class='form-check-label' for='edit_{$field_name}'>Toggle On/Off</label>
                                              </div>";
                                        break;
                                endswitch;
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form id="deleteForm" method="post" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="submission_id" id="delete_submission_id">
        <input type="hidden" name="form_id" value="<?= $form_id ?>">
    </form>
    
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
        // Handle edit form submission
        $('#saveEditBtn').on('click', function() {
            var formData = {};
            
            // Collect all form field values
            <?php foreach ($form_fields as $field): ?>
                <?php if ($field['field_type'] === 'checkbox_group'): ?>
                    formData['<?= $field['field_name'] ?>'] = [];
                    $('[name="edit_<?= $field['field_name'] ?>[]"]:checked').each(function() {
                        formData['<?= $field['field_name'] ?>'].push($(this).val());
                    });
                <?php elseif ($field['field_type'] === 'toggle'): ?>
                    formData['<?= $field['field_name'] ?>'] = $('[name="edit_<?= $field['field_name'] ?>"]').is(':checked') ? '1' : '0';
                <?php else: ?>
                    formData['<?= $field['field_name'] ?>'] = $('[name="edit_<?= $field['field_name'] ?>"]').val();
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Create hidden input with JSON data
            $('<input>').attr({
                type: 'hidden',
                name: 'form_data',
                value: JSON.stringify(formData)
            }).appendTo('#editForm');
            
            // Submit the form
            $('#editForm').submit();
        });
    </script>
    <?php endif; ?>
    <script>
        // Fix dropdown functionality
        $(document).ready(function() {
            // Handle dropdown toggle click
            $('.dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $menu = $(this).next('.dropdown-menu');
                
                // Close other dropdowns
                $('.dropdown-menu').not($menu).removeClass('show');
                
                // Toggle this dropdown
                $menu.toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            $('.dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
