<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$user_purok = null;
if ($role_id == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

$purok_condition = ($role_id == 2 && $user_purok) ? "AND a.purok = ?" : "";
$stmt = $pdo->prepare("
    SELECT p.person_id, p.full_name, p.age, p.birthdate, p.gender, a.purok, GROUP_CONCAT(m.medication_name SEPARATOR ', ') AS medications
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    JOIN records r ON p.person_id = r.person_id
    JOIN senior_record sr ON r.records_id = sr.records_id
    JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
    JOIN medication m ON sm.medication_id = m.medication_id
    WHERE r.record_type = 'senior_record.medication' $purok_condition
    GROUP BY p.person_id, p.full_name, p.age, p.birthdate, p.gender, a.purok
    ORDER BY p.full_name ASC
");
$params = [];
if ($role_id == 2 && $user_purok) {
    $params = [$user_purok];
}
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboard stats
$total_patients = count($records);
$males = count(array_filter($records, fn($r) => strtoupper($r['gender']) === 'M'));
$females = count(array_filter($records, fn($r) => strtoupper($r['gender']) === 'F'));
$total_per_med = [];
$listed_meds = [
    "Amlodipine 5mg", "Amlodipine 10mg", "Losartan 50mg", "Losartan 100mg",
    "Metoprolol 50mg", "Carvidolol 12.5mg", "Simvastatin 20mg",
    "Metformin 500mg", "Gliclazide 30mg"
];
foreach ($listed_meds as $m) $total_per_med[$m] = 0;
foreach ($records as $r) {
    $meds = explode(', ', $r['medications']);
    foreach ($listed_meds as $med) if (in_array($med, $meds)) $total_per_med[$med]++;
}

// PDF Report - as is
if (isset($_POST['download']) && isset($_POST['report_type'])) {
    require_once 'vendor/autoload.php';
    $report_type = $_POST['report_type'];
    ob_start();
    ?>
    <!doctype html>
    <html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Masterlist Hypertension & Diabetes Health Club</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; }
        @page { size: legal landscape; margin: 10mm; }
        .paper { padding: 12px; }
        .header div { text-align: center; margin: 2px 0;}
        .header h1 { text-align: center; font-size: 16px; margin: 4px 0;}
        .meta { font-size: 12px;margin:2px 0;}
        table { width: 100%; border-collapse: collapse;font-size: 11px;}
        th, td { border: 1px solid #000; padding: 4px; text-align: center;vertical-align:middle;}
        th { background: #f2f2f2; }
        th.group-header { background: #e9ecef;text-align:center;}
        .medication { width: 80px; }
    </style>
    </head><body>
    <?php
    if ($report_type === 'total' || $report_type === 'all') {
        $all_records = $records;
    ?>
        <div class="paper">
            <div class="header">
                <div>Republic of the Philippines</div>
                <div>Municipality of Camiling</div>
                <div>Office of Health Services</div>
                <h1>MASTERLIST HYPERTENSION & DIABETES</h1>
                <h1>HEALTH CLUB</h1>
            </div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">Name of Patient</th>
                        <th rowspan="2">Barangay</th>
                        <th rowspan="2">Age</th>
                        <th rowspan="2">Date of Birth</th>
                        <th colspan="2">Sex</th>
                        <th colspan="9">Maintenance Medications</th>
                    </tr>
                    <tr>
                        <th>M</th>
                        <th>F</th>
                        <th class="medication">Amlodipine 5mg</th>
                        <th class="medication">Amlodipine 10mg</th>
                        <th class="medication">Losartan 50mg</th>
                        <th class="medication">Losartan 100mg</th>
                        <th class="medication">Metoprolol 50mg</th>
                        <th class="medication">Carvidolol 12.5mg</th>
                        <th class="medication">Simvastatin 20mg</th>
                        <th class="medication">Metformin 500mg</th>
                        <th class="medication">Gliclazide 30mg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_records as $record) {
                        $meds = explode(', ', $record['medications']);
                        $birthdate = new DateTime($record['birthdate'] ?? '1970-01-01');
                        $formatted_birthdate = $birthdate->format('F d, Y');
                        $parts = explode(' ', $formatted_birthdate);
                        $month = $parts[0];
                        $day = $parts[1];
                        $year = $parts[2];
                        $display_birthdate = "$month $day $year";
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($record['full_name'] ?? '') . "</td>";
                        echo "<td>Sta. Maria</td>";
                        echo "<td>" . htmlspecialchars($record['age'] ?? 'N/A') . "</td>";
                        echo "<td>$display_birthdate</td>";
                        echo "<td>" . ($record['gender'] === 'M' ? '✓' : '') . "</td>";
                        echo "<td>" . ($record['gender'] === 'F' ? '✓' : '') . "</td>";
                        foreach ($listed_meds as $med)
                            echo "<td class='medication'>" . (in_array($med, $meds) ? '✓' : '') . "</td>";
                        echo "</tr>";
                    }
                    for ($i = count($all_records); $i < 50; $i++)
                        echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    ?>
                </tbody>
            </table>
        </div>
    <?php }

    if ($report_type === 'per_purok' || $report_type === 'all') {
        $puroks = array_unique(array_column($records, 'purok'));
        foreach ($puroks as $purok) {
            $filtered_records = array_filter($records, fn($r) => $r['purok'] == $purok);
            ?>
            <div class="paper">
                <div class="header">
                    <div>Republic of the Philippines</div>
                    <div>Municipality of Camiling</div>
                    <div>Office of Health Services</div>
                    <h1>MASTERLIST HYPERTENSION & DIABETES</h1>
                    <h1>HEALTH CLUB - Purok <?php echo htmlspecialchars($purok); ?></h1>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">Name of Patient</th>
                            <th rowspan="2">Barangay</th>
                            <th rowspan="2">Age</th>
                            <th rowspan="2">Date of Birth</th>
                            <th colspan="2">Sex</th>
                            <th colspan="9">Maintenance Medications</th>
                        </tr>
                        <tr>
                            <th>M</th>
                            <th>F</th>
                            <th class="medication">Amlodipine 5mg</th>
                            <th class="medication">Amlodipine 10mg</th>
                            <th class="medication">Losartan 50mg</th>
                            <th class="medication">Losartan 100mg</th>
                            <th class="medication">Metoprolol 50mg</th>
                            <th class="medication">Carvidolol 12.5mg</th>
                            <th class="medication">Simvastatin 20mg</th>
                            <th class="medication">Metformin 500mg</th>
                            <th class="medication">Gliclazide 30mg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($filtered_records as $record) {
                            $meds = explode(', ', $record['medications']);
                            $birthdate = new DateTime($record['birthdate'] ?? '1970-01-01');
                            $formatted_birthdate = $birthdate->format('F d, Y');
                            $parts = explode(' ', $formatted_birthdate);
                            $month = $parts[0];
                            $day = $parts[1];
                            $year = $parts[2];
                            $display_birthdate = "$month $day $year";
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($record['full_name'] ?? '') . "</td>";
                            echo "<td>Sta. Maria</td>";
                            echo "<td>" . htmlspecialchars($record['age'] ?? 'N/A') . "</td>";
                            echo "<td>$display_birthdate</td>";
                            echo "<td>" . ($record['gender'] === 'M' ? '✓' : '') . "</td>";
                            echo "<td>" . ($record['gender'] === 'F' ? '✓' : '') . "</td>";
                            foreach ($listed_meds as $med)
                                echo "<td class='medication'>" . (in_array($med, $meds) ? '✓' : '') . "</td>";
                            echo "</tr>";
                        }
                        for ($i = count($filtered_records); $i < 50; $i++)
                            echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                        ?>
                    </tbody>
                </table>
            </div>
        <?php }
    }
    ?>
    </body></html>
    <?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $filename = 'Masterlist_Hypertension_Diabetes_' . date('Ymd_His') . '.pdf';
    $html2pdf->output($filename, 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BRGYCare - Hypertension/Diabetes Masterlist (Enhanced)</title>
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
    .content.with-sidebar { margin-left: 250px; }
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
    .table {
        background: #ffffff;
        border-radius: 10px;
    }
    .table th {
        background: rgba(43, 108, 176, 0.9);
        color: #fff;
        border-bottom: none;
        font-weight: 500;
        text-align: center;
        vertical-align: middle;
        padding: 1px 25px;
    }
    .table th.group-header {
        background: #e9ecef;
        color: #2d3748;
        font-weight: bold;
        text-align: center;
    }
    .table th.medication {
        width: 100px;
        text-align: center;
    }
    .table td.medication {
        font-size: 1.1em;
        text-align: center;
    }
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f7fafc;
    }
    .download-btn {
        margin-bottom: 10px;
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
        }
        .content.with-sidebar {
            margin-left: 0;
            padding-left: 0;
        }
        .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
        .card { margin-bottom: 15px; }
        .card { margin-left: 0; margin-right: 0; }
        .table-responsive { overflow-x: auto; }
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
                    <div class="card-header"><i class="fas fa-file-medical"></i> Hypertension/Diabetes Masterlist (Enhanced)</div>
                    <div class="card-body">
                    <!-- Statistics -->
                    <div class="row mb-3 stats-container">
                        <div class="col-md-<?php echo $total_patients > 0 && max($total_per_med) > 0 ? '3' : '4'; ?> col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Total Patients</div>
                                <div class="stat-value"><?php echo number_format($total_patients); ?></div>
                                <small class="text-muted">Active in club</small>
                            </div>
                        </div>
                        <div class="col-md-<?php echo $total_patients > 0 && max($total_per_med) > 0 ? '3' : '4'; ?> col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Male</div>
                                <div class="stat-value text-info"><i class="fas fa-mars"></i> <?php echo $males; ?></div>
                                <small class="text-muted"><?php echo $total_patients > 0 ? round(($males / $total_patients)*100,1) : 0; ?>%</small>
                            </div>
                        </div>
                        <div class="col-md-<?php echo $total_patients > 0 && max($total_per_med) > 0 ? '3' : '4'; ?> col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Female</div>
                                <div class="stat-value text-pink"><i class="fas fa-venus"></i> <?php echo $females; ?></div>
                                <small class="text-muted"><?php echo $total_patients > 0 ? round(($females / $total_patients)*100,1) : 0; ?>%</small>
                            </div>
                        </div>
                        <?php if ($total_patients > 0 && max($total_per_med) > 0): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Most Used Med</div>
                                <div class="stat-value text-success">
                                    <?php
                                    arsort($total_per_med);
                                    $top = array_key_first($total_per_med);
                                    // Shorten medication name to fit nicely
                                    $short_name = str_replace(['Amlodipine', 'Losartan', 'Metoprolol', 'Carvidolol', 'Simvastatin', 'Metformin', 'Gliclazide'], 
                                                            ['Amlod.', 'Los.', 'Metop.', 'Carv.', 'Simv.', 'Metf.', 'Glic.'], $top);
                                    echo $short_name;
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo $total_per_med[$top]; ?> patients</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                        <form method="post" class="download-btn">
                            <div class="form-group">
                                <label for="report_type">Select Report Type:</label>
                                <select name="report_type" id="report_type" class="form-control" required>
                                    <option value="total">Total Brgy</option>
                                    <option value="per_purok">Per Purok</option>
                                    <option value="all">All (Total Brgy + All Puroks)</option>
                                </select>
                            </div>
                            <button type="submit" name="download" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Download PDF</button>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th rowspan="2">Purok</th>
                                        <th rowspan="2">Patient</th>
                                        <th rowspan="2">Age</th>
                                        <th rowspan="2">Birthdate</th>
                                        <th rowspan="2">Gender</th>
                                        <th colspan="9" class="group-header">Maintenance Medication</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($listed_meds as $m): ?>
                                        <th class="medication"><?php echo $m; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($records as $record) {
                                        $meds = explode(', ', $record['medications']);
                                        $birthdate = new DateTime($record['birthdate'] ?? '1970-01-01');
                                        $display_birthdate = $birthdate->format('M d, Y');
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($record['purok']) . "</td>";
                                        echo "<td>" . htmlspecialchars($record['full_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($record['age'] ?? 'N/A') . "</td>";
                                        echo "<td>$display_birthdate</td>";
                                        echo "<td>";
                                        echo $record['gender'] === 'M'
                                              ? '<i class="fas fa-mars text-info" title="Male"></i>'
                                              : ($record['gender'] === 'F'
                                                  ? '<i class="fas fa-venus text-pink" title="Female"></i>'
                                                  : htmlspecialchars($record['gender']));
                                        echo "</td>";
                                        foreach ($listed_meds as $med)
                                            echo "<td class='medication'>" . (in_array($med, $meds) ? '<i class="fas fa-check text-success"></i>' : '') . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
        if (window.innerWidth > 768) {
            content.css('margin-left', sidebar.hasClass('open') ? '250px' : '0');
        } else {
            content.css('margin-left', '0');
        }
    }
    $('.accordion-header').on('click', function() {
        const content = $(this).next('.accordion-content');
        content.toggleClass('active');
    });
</script>
<style>
    .text-pink { color: #e04a87 !important; }
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
        .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
        .navbar-brand { padding-left: 55px;}
    }
</style>
</body>
</html>
