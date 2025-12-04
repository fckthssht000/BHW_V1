<?php
session_start();
require_once 'db_connect.php';
use Spipu\Html2Pdf\Html2Pdf;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role and person_id
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();

$user_purok = null;
if ($user_role == 2) {
    $stmt = $pdo->prepare("SELECT a.purok FROM person p 
        JOIN address a ON p.address_id = a.address_id 
        JOIN records r ON p.person_id = r.person_id 
        WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

$year = date('Y');

// Query based on role
$purok_condition = ($user_role == 2 && $user_purok) ? "AND a.purok = ?" : "";
$stmt = $pdo->prepare("
    SELECT p.full_name, p.gender, a.purok, hr.water_source, hr.toilet_type
    FROM household_record hr
    JOIN records r ON hr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN users u ON r.user_id = u.user_id
    JOIN address a ON p.address_id = a.address_id
    WHERE r.record_type = 'household_record'
    AND p.relationship_type = 'Head'
    AND u.role_id = 3
    $purok_condition
    GROUP BY p.full_name, a.purok
    ORDER BY a.purok, p.full_name
");
$params = [];
if ($user_role == 2 && $user_purok) {
    $params = [$user_purok];
}
$stmt->execute($params);
$household_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics for dashboard
$total_households = count($household_heads);
$level3_water = count(array_filter($household_heads, fn($h) => $h['water_source'] === 'Level 3 (Nawasa)'));
$no_toilet = count(array_filter($household_heads, fn($h) => strpos($h['toilet_type'], 'Wala') !== false));
$sanitary_toilet = count(array_filter($household_heads, fn($h) => strpos($h['toilet_type'], 'De Buhos') !== false || strpos($h['toilet_type'], 'Septic') !== false));

$water_coverage = $total_households > 0 ? round(($level3_water / $total_households) * 100, 1) : 0;
$no_toilet_pct = $total_households > 0 ? round(($no_toilet / $total_households) * 100, 1) : 0;
$sanitary_pct = $total_households > 0 ? round(($sanitary_toilet / $total_households) * 100, 1) : 0;

// Fetch address details
$stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
$address = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle PDF download - KEPT EXACTLY AS YOUR ORIGINAL
if (isset($_POST['download']) && isset($_POST['report_type'])) {
    require_once 'vendor/autoload.php';

    $report_type = $_POST['report_type'];
    ob_start();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Master List of Households - Environmental Health Sanitation</title>
        
        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                margin: 0;
                padding: 0;
                background: #fff;
            }

            @page {
                size: legal landscape;
                margin: 10mm;
            }

            @media print {
                html, body {
                    width: 14in;
                    height: 8.5in;
                }
                .paper {
                    border: none;
                    margin: 0;
                    padding: 0;
                    width: 100%;
                    height: 100%;
                }
            }

            .paper {
                width: 100%;
                height: 100%;
                padding: 12px;
                box-sizing: border-box;
            }

            .header {
                text-align: left;
                margin-bottom: 10px;
            }
            .title {
                text-align: left;
                margin-bottom: 10px;
            }
            .title h1 {
                text-align: center;
                font-size: 16px;
                margin: 0 0 4px;
            }
            .meta {
                text-align: center;
                font-size: 12px;
                color: #444;
            }
            .address-details {
                text-align: center;
                margin-bottom: 10px;
            }
            .address-details span {
                display: inline-block;
                margin: 0 15px;
                vertical-align: middle;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 9px;
            }
            th, td {
                border: 1px solid #000;
                padding: 2px;
                vertical-align: middle;
                text-align: center;
                word-wrap: break-word;
            }
            th {
                background: #f2f2f2;
            }

            .grouped-header {
                background: #e9e9e9;
                text-align: center;
                word-wrap: break-word;
            }

            .col-narrow { width: 30px; }
            .col-name { width: 90px; }
            .col-small { width: 35px; }
            .col-medium { width: 45px; font-size: 7px;}

            tbody td {
                height: 24px;
            }
        </style>
    </head>
    <body>
        <?php if ($report_type === 'total' || $report_type === 'all') { ?>
            <div class="paper">
                <div class="header">
                    <div class="title">
                        <h1>Master List of Households on Environmental Health / Sanitation</h1>
                        <div class="meta">Quarter / Year: <?php echo $year; ?></div>
                    </div>
                    <div class="address-details">
                        <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                        <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                        <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                        <span><strong>Purok:</strong> <?php echo htmlspecialchars($user_purok ?? 'All'); ?></span>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="3" class="col-narrow">No.</th>
                            <th rowspan="3" class="col-name">Name of HH Heads</th>
                            <th rowspan="3" class="col-small">Sex</th>
                            <th rowspan="3" class="col-small">SE Status</th>
                            <th colspan="4" class="grouped-header">Type of Water Supply</th>
                            <th colspan="8" class="grouped-header">Toilet Facilities</th>
                            <th colspan="5" class="grouped-header">Solid Waste Management</th>
                        </tr>
                        <tr>
                            <th rowspan="2" class="col-medium">Level I (Point Source)</th>
                            <th rowspan="2" class="col-medium">Level II (Communal Faucet)</th>
                            <th rowspan="2" class="col-medium">Level III (Individual Connection)</th>
                            <th rowspan="2" class="col-medium">Others</th>
                            <th colspan="3" class="grouped-header">Sanitary Toilet</th>
                            <th colspan="5" class="grouped-header">Unsanitary Toilet</th>
                            <th rowspan="2" class="col-medium">Waste Segregation</th>
                            <th rowspan="2" class="col-medium">Backyard Composting</th>
                            <th rowspan="2" class="col-medium">Recycling/ Reuse</th>
                            <th rowspan="2" class="col-medium">Collected by MENRO</th>
                            <th rowspan="2" class="col-medium">Others 
                                (Burning/ Burying)</th>
                        </tr>
                        <tr>
                            <th class="col-medium">Pour/Flush w/ Septic Tank</th>
                            <th class="col-medium">Pour/Flush w/ Sewerage</th>
                            <th class="col-medium">Ventilated Pit (VIP)</th>
                            <th class="col-medium">Water sealed w/o Septic</th>
                            <th class="col-medium">Over-hung Latrine</th>
                            <th class="col-medium">Open Pit</th>
                            <th class="col-medium">w/o Toilet</th>
                            <th class="col-medium">Shared Toilet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        foreach ($household_heads as $head) {
                            $sex = $head['gender'] === 'Male' ? 'M' : 'F';
                            $status = 'Non-NHTS';
                            $water_source = $head['water_source'] ?? 'N/A';
                            $toilet_type = $head['toilet_type'] ?? 'N/A';
                            $level1 = ($water_source == 'Level 1 (Poso)') ? '✓' : '';
                            $level2 = ($water_source == 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)') ? '✓' : '';
                            $level3 = ($water_source == 'Level 3 (Nawasa)') ? '✓' : '';
                            $others_water = ($water_source == 'WRS (Water Refilling Station)') ? '✓' : '';
                            $septic = (strpos($toilet_type, 'Pour/Flush w/ Septic Tank') !== false || strpos($toilet_type, 'De Buhos') !== false) ? '✓' : '';
                            $sewerage = (strpos($toilet_type, 'Pour/Flush w/ Sewerage') !== false) ? '✓' : '';
                            $vip = (strpos($toilet_type, 'Ventilated Pit (VIP)') !== false) ? '✓' : '';
                            $water_sealed = (strpos($toilet_type, 'Water-sealed w/o Septic') !== false) ? '✓' : '';
                            $over_hung = (strpos($toilet_type, 'Over-hung Latrine') !== false) ? '✓' : '';
                            $open_pit = (strpos($toilet_type, 'Open Pit') !== false) ? '✓' : '';
                            $no_toilet = (strpos($toilet_type, 'w/o Toilet') !== false || strpos($toilet_type, 'Wala') !== false) ? '✓' : '';
                            $shared = (strpos($toilet_type, 'Shared Toilet') !== false) ? '✓' : '';
                            echo '<tr>
                                <td>' . $count++ . '</td>
                                <td>' . htmlspecialchars($head['full_name']) . '</td>
                                <td>' . $sex . '</td>
                                <td>' . $status . '</td>
                                <td>' . $level1 . '</td>
                                <td>' . $level2 . '</td>
                                <td>' . $level3 . '</td>
                                <td>' . $others_water . '</td>
                                <td>' . $septic . '</td>
                                <td>' . $sewerage . '</td>
                                <td>' . $vip . '</td>
                                <td>' . $water_sealed . '</td>
                                <td>' . $over_hung . '</td>
                                <td>' . $open_pit . '</td>
                                <td>' . $no_toilet . '</td>
                                <td>' . $shared . '</td>
                                <td></td><td></td><td></td><td></td><td></td>
                            </tr>';
                        }
                        for ($i = count($household_heads); $i < 50; $i++) {
                            echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php if ($report_type === 'per_purok' || $report_type === 'all') {
            $unique_puroks = array_unique(array_column($household_heads, 'purok'));
            foreach ($unique_puroks as $purok) {
                $filtered_heads = array_filter($household_heads, fn($head) => $head['purok'] == $purok);
                ?>
                <div class="paper">
                    <div class="header">
                        <div class="title">
                            <h1>Master List of Households on Environmental Health / Sanitation</h1>
                            <div class="meta">Quarter / Year: <?php echo $year; ?></div>
                        </div>
                        <div class="address-details">
                            <span><strong>Municipality:</strong> <?php echo htmlspecialchars($address['municipality'] ?? '____________________'); ?></span>
                            <span><strong>Barangay:</strong> <?php echo htmlspecialchars($address['barangay'] ?? '____________________'); ?></span>
                            <span><strong>Province:</strong> <?php echo htmlspecialchars($address['province'] ?? '____________________'); ?></span>
                            <span><strong>Purok:</strong> <?php echo htmlspecialchars($purok); ?></span>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="3" class="col-narrow">No.</th>
                                <th rowspan="3" class="col-name">Name of HH Heads</th>
                                <th rowspan="3" class="col-small">Sex</th>
                                <th rowspan="3" class="col-small">SE Status</th>
                                <th colspan="4" class="grouped-header">Type of Water Supply</th>
                                <th colspan="8" class="grouped-header">Toilet Facilities</th>
                                <th colspan="5" class="grouped-header">Solid Waste Management</th>
                            </tr>
                            <tr>
                                <th rowspan="2" class="col-medium">Level I (Point Source)</th>
                                <th rowspan="2" class="col-medium">Level II (Communal Faucet)</th>
                                <th rowspan="2" class="col-medium">Level III (Individual Connection)</th>
                                <th rowspan="2" class="col-medium">Others</th>
                                <th colspan="3" class="grouped-header">Sanitary Toilet</th>
                                <th colspan="5" class="grouped-header">Unsanitary Toilet</th>
                                <th rowspan="2" class="col-medium">Waste Segregation</th>
                                <th rowspan="2" class="col-medium">Backyard Composting</th>
                                <th rowspan="2" class="col-medium">Recycling/Reuse</th>
                                <th rowspan="2" class="col-medium">Collected by MENRO</th>
                                <th rowspan="2" class="col-medium">Others (Burning/Burying)</th>
                            </tr>
                            <tr>
                                <th class="col-medium">Pour/Flush w/ Septic Tank</th>
                                <th class="col-medium">Pour/Flush w/ Sewerage</th>
                                <th class="col-medium">Ventilated Pit (VIP)</th>
                                <th class="col-medium">Water-sealed w/o Septic</th>
                                <th class="col-medium">Over-hung Latrine</th>
                                <th class="col-medium">Open Pit</th>
                                <th class="col-medium">w/o Toilet</th>
                                <th class="col-medium">Shared Toilet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 1;
                            foreach ($filtered_heads as $head) {
                                $sex = $head['gender'] === 'Male' ? 'M' : 'F';
                                $status = 'Non-NHTS';
                                $water_source = $head['water_source'] ?? 'N/A';
                                $toilet_type = $head['toilet_type'] ?? 'N/A';
                                $level1 = ($water_source == 'Level 1 (Poso)') ? '✓' : '';
                                $level2 = ($water_source == 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)') ? '✓' : '';
                                $level3 = ($water_source == 'Level 3 (Nawasa)') ? '✓' : '';
                                $others_water = ($water_source == 'WRS (Water Refilling Station)') ? '✓' : '';
                                $septic = (strpos($toilet_type, 'Pour/Flush w/ Septic Tank') !== false || strpos($toilet_type, 'De Buhos') !== false) ? '✓' : '';
                                $sewerage = (strpos($toilet_type, 'Pour/Flush w/ Sewerage') !== false) ? '✓' : '';
                                $vip = (strpos($toilet_type, 'Ventilated Pit (VIP)') !== false) ? '✓' : '';
                                $water_sealed = (strpos($toilet_type, 'Water-sealed w/o Septic') !== false) ? '✓' : '';
                                $over_hung = (strpos($toilet_type, 'Over-hung Latrine') !== false) ? '✓' : '';
                                $open_pit = (strpos($toilet_type, 'Open Pit') !== false) ? '✓' : '';
                                $no_toilet = (strpos($toilet_type, 'w/o Toilet') !== false || strpos($toilet_type, 'Wala') !== false) ? '✓' : '';
                                $shared = (strpos($toilet_type, 'Shared Toilet') !== false) ? '✓' : '';
                                echo '<tr>
                                    <td>' . $count++ . '</td>
                                    <td>' . htmlspecialchars($head['full_name']) . '</td>
                                    <td>' . $sex . '</td>
                                    <td>' . $status . '</td>
                                    <td>' . $level1 . '</td>
                                    <td>' . $level2 . '</td>
                                    <td>' . $level3 . '</td>
                                    <td>' . $others_water . '</td>
                                    <td>' . $septic . '</td>
                                    <td>' . $sewerage . '</td>
                                    <td>' . $vip . '</td>
                                    <td>' . $water_sealed . '</td>
                                    <td>' . $over_hung . '</td>
                                    <td>' . $open_pit . '</td>
                                    <td>' . $no_toilet . '</td>
                                    <td>' . $shared . '</td>
                                    <td></td><td></td><td></td><td></td><td></td>
                                </tr>';
                            }
                            for ($i = count($filtered_heads); $i < 50; $i++) {
                                echo '<tr><td>' . ($i + 1) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php }
        } ?>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $html2pdf = new Html2Pdf('L', 'LEGAL', 'en', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->setDefaultFont('dejavusans');
    $html2pdf->writeHTML($html);
    $html2pdf->output('Master_List_Environmental_Health_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Environmental Health Sanitation (Enhanced)</title>
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
        .alert-custom {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .alert-danger-custom {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        .alert-warning-custom {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .alert-success-custom {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        .table {
            background: #ffffff;
            border-radius: 10px;
        }
        .table thead th {
            background: rgba(43, 108, 176, 0.9);
            color: #fff;
            border: 1px solid #e2e8f0;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        .table thead th.hh-head {
            padding-left: 30px;
            padding-right: 30px;
        }
        .table thead th.col-mid {
            padding: 0 10px;
        }
        .table thead th.col-sec {
            padding-left: 10px;
            padding-right: 10px;
            padding-top: 5px;
            padding-bottom: 5px;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
        }
        .table td {
            font-size: 0.85rem;
            padding: 8px;
            text-align: center;
        }
        .nav-tabs .nav-link {
            color: #2d3748;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
            background: #edf2f7;
        }
        .nav-tabs .nav-link.active {
            color: #2b6cb0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-bottom: none;
        }
        .tab-content { padding: 0; padding-top:10px;}
        .grouped-header { background-color: #e9ecef; font-weight: 500; }
        .form-control {
            display: inline-block;
            width: auto;
            vertical-align: middle;
        }
        .download-btn {
            margin-bottom: 15px;
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
            .card { margin-bottom: 15px; margin-left: 0; margin-right: 0 }
            .table-responsive { overflow-x: auto; }
            .tab-content{font-size: 12px;}
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
                    <div class="card-header">
                        <i class="fas fa-home"></i> Environmental Health & Sanitation (Enhanced)
                    </div>
                    <div class="card-body p-3">
                        <!-- Dashboard Statistics -->
                        <div class="row mb-3 stats-container">
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Total Households</div>
                                    <div class="stat-value"><?php echo number_format($total_households); ?></div>
                                    <small class="text-muted">Registered household heads</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Level III Water</div>
                                    <div class="stat-value <?php echo $water_coverage > 75 ? 'text-success' : 'text-warning'; ?>"><?php echo $water_coverage; ?>%</div>
                                    <small class="text-muted"><?php echo $level3_water; ?> households with Nawasa</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Sanitary Toilets</div>
                                    <div class="stat-value text-success"><?php echo $sanitary_pct; ?>%</div>
                                    <small class="text-muted"><?php echo $sanitary_toilet; ?> with proper sanitation</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="stat-card">
                                    <div class="stat-label">Without Toilet</div>
                                    <div class="stat-value <?php echo $no_toilet_pct > 10 ? 'text-danger' : 'text-success'; ?>"><?php echo $no_toilet_pct; ?>%</div>
                                    <small class="text-muted"><?php echo $no_toilet; ?> households need intervention</small>
                                </div>
                            </div>
                        </div>

                        <!-- Health Alerts -->
                        <?php if ($no_toilet_pct > 10): ?>
                            <div class="alert-danger-custom alert-custom">
                                <i class="fas fa-exclamation-circle"></i> <strong>Critical Issue:</strong> <?php echo $no_toilet; ?> households (<?php echo $no_toilet_pct; ?>%) lack toilet facilities. Immediate action needed.
                            </div>
                        <?php endif; ?>
                        <?php if ($water_coverage < 60): ?>
                            <div class="alert-warning-custom alert-custom">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Water Coverage Low:</strong> Only <?php echo $water_coverage; ?>% have Level III water access. Expand coverage.
                            </div>
                        <?php endif; ?>
                        <?php if ($sanitary_pct > 80): ?>
                            <div class="alert-success-custom alert-custom">
                                <i class="fas fa-check-circle"></i> <strong>Good Progress:</strong> <?php echo $sanitary_pct; ?>% have sanitary toilets. Keep up the good work!
                            </div>
                        <?php endif; ?>

                        <h5 class="mt-3">Year: <?php echo $year; ?> | Barangay: <?php echo htmlspecialchars($address['barangay'] ?? 'N/A'); ?>
                        | Municipality: <?php echo htmlspecialchars($address['municipality'] ?? 'N/A'); ?>
                        | Province: <?php echo htmlspecialchars($address['province'] ?? 'N/A'); ?></h5>

                        <form method="post" class="download-btn">
                            <select class="form-control" name="report_type">
                                <option value="total">Total Brgy</option>
                                <option value="per_purok">Per Purok</option>
                                <option value="all">All</option>
                            </select>
                            <button type="submit" name="download" class="btn btn-primary ml-2">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </form>

                        <?php if ($user_role == 1 || $user_role == 2 || $user_role == 4): ?>
                            <ul class="nav nav-tabs" id="purokTabs" role="tablist">
                                <?php
                                $unique_puroks = array_unique(array_column($household_heads, 'purok'));
                                foreach ($unique_puroks as $index => $purok) {
                                    $active = ($index === 0) ? 'active' : '';
                                    $safe_id = 'purok-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($purok));
                                    $purok_count = count(array_filter($household_heads, fn($h) => $h['purok'] == $purok));
                                    echo "<li class='nav-item'>
                                        <a class='nav-link $active' id='{$safe_id}-tab' data-toggle='tab' href='#{$safe_id}' role='tab' aria-controls='{$safe_id}' aria-selected='" . ($index === 0 ? 'true' : 'false') . "'>
                                            <i class='fas fa-map-marker-alt'></i> $purok <span class='badge badge-secondary ml-1'>$purok_count</span>
                                        </a>
                                    </li>";
                                }
                                ?>
                            </ul>
                            <div class="tab-content" id="purokTabContent">
                                <?php foreach ($unique_puroks as $index => $purok) {
                                    $active = ($index === 0) ? 'show active' : '';
                                    $safe_id = 'purok-' . preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($purok));
                                ?>
                                    <div class="tab-pane fade <?php echo $active; ?>" id="<?php echo $safe_id; ?>" role="tabpanel" aria-labelledby="<?php echo $safe_id; ?>-tab">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="3" class="hh-head">Name of HH Heads</th>
                                                        <th rowspan="3" class="col-sec">SE Status</th>
                                                        <th colspan="4" class="grouped-header">Type of Water Supply</th>
                                                        <th colspan="8" class="grouped-header">Toilet Facilities</th>
                                                        <th colspan="5" class="grouped-header">Solid Waste Management</th>
                                                    </tr>
                                                    <tr>
                                                        <th rowspan="2" class="col-mid">Level I</th>
                                                        <th rowspan="2" class="col-sec">Level II</th>
                                                        <th rowspan="2" class="col-sec">Level III</th>
                                                        <th rowspan="2" class="col-sec">Others</th>
                                                        <th colspan="3" class="col-mid">Sanitary Toilet</th>
                                                        <th colspan="5" class="col-mid">Unsanitary Toilet</th>
                                                        <th rowspan="2" class="col-sec">Waste Seg.</th>
                                                        <th rowspan="2" class="col-sec">Composting</th>
                                                        <th rowspan="2" class="col-sec">Recycling</th>
                                                        <th rowspan="2" class="col-sec">MENRO</th>
                                                        <th rowspan="2" class="col-sec">Others</th>
                                                    </tr>
                                                    <tr>
                                                        <th class="col-mid">w/ Septic</th>
                                                        <th class="col-mid">w/ Sewerage</th>
                                                        <th class="col-mid">VIP</th>
                                                        <th class="col-mid">w/o Septic</th>
                                                        <th class="col-mid">Over-hung</th>
                                                        <th class="col-mid">Open Pit</th>
                                                        <th class="col-mid">w/o Toilet</th>
                                                        <th class="col-mid">Shared</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                    $filtered_heads = array_filter($household_heads, fn($head) => $head['purok'] == $purok);
                                                    foreach ($filtered_heads as $head) {
                                                        $water_source = htmlspecialchars($head['water_source'] ?? 'N/A');
                                                        $toilet_type = htmlspecialchars($head['toilet_type'] ?? 'N/A');
                                                        $others_water = ($water_source == 'WRS (Water Refilling Station)') ? 'checked' : '';
                                                        $others_water_value = ($water_source == 'WRS (Water Refilling Station)') ? 'WRS' : '';
                                                        echo "<tr>
                                                            <td style='text-align:left; padding-left:10px;'>" . htmlspecialchars($head['full_name']) . "</td>
                                                            <td>
                                                                <select class='form-control form-control-sm' style='font-size:0.8rem;'>
                                                                    <option value='1'>NHTS</option>
                                                                    <option value='2' selected>Non-NHTS</option>
                                                                </select>
                                                            </td>
                                                            <td>" . ($water_source == 'Level 1 (Poso)' ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . ($water_source == 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)' ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . ($water_source == 'Level 3 (Nawasa)' ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . ($others_water ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'De Buhos') !== false ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Pour/Flush w/ Sewerage') !== false ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Sanitary Pit') !== false ? '<i class="fas fa-check text-success"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Water-sealed w/o Septic') !== false ? '<i class="fas fa-check text-warning"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Over-hung') !== false ? '<i class="fas fa-check text-warning"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Pit Privy') !== false ? '<i class="fas fa-check text-warning"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Wala') !== false ? '<i class="fas fa-times text-danger"></i>' : '') . "</td>
                                                            <td>" . (strpos($toilet_type, 'Shared') !== false ? '<i class="fas fa-check text-warning"></i>' : '') . "</td>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                        </tr>";
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php endif; ?>
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
