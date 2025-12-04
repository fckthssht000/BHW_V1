<?php
session_start();
require_once 'db_connect.php';
require_once 'tcpdf/tcpdf.php';

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
    $stmt = $pdo->prepare("SELECT a.purok FROM person p JOIN address a ON p.address_id = a.address_id JOIN records r ON p.person_id = r.person_id WHERE r.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_purok = $stmt->fetchColumn();
}

$year = date('Y');

// Fetch total population, gender, and household counts
$purok_condition = ($user_role == 2 && $user_purok) ? "AND a.purok = ?" : "";

$stmt = $pdo->prepare("
    SELECT 
        a.purok,
        COUNT(DISTINCT p.person_id) AS total_population,
        COUNT(DISTINCT CASE WHEN p.gender = 'M' THEN p.person_id END) AS total_male,
        COUNT(DISTINCT CASE WHEN p.gender = 'F' THEN p.person_id END) AS total_female,
        COUNT(DISTINCT CASE WHEN p.relationship_type = 'Head' THEN p.person_id END) AS total_household,
        COUNT(DISTINCT CASE WHEN p.age >= 60 THEN p.person_id END) AS total_senior,
        COUNT(DISTINCT CASE WHEN p.age <= 17 THEN p.person_id END) AS total_children,
        COUNT(DISTINCT CASE WHEN p.age BETWEEN 18 AND 59 THEN p.person_id END) AS total_adults
    FROM person p
    JOIN address a ON p.address_id = a.address_id
    LEFT JOIN records r ON p.person_id = r.person_id
    LEFT JOIN users u ON r.user_id = u.user_id
    WHERE 
        (p.deceased IS NULL OR p.deceased = 0)
        AND (
            u.role_id IS NULL
            OR u.role_id NOT IN (1, 2, 4)
            OR p.related_person_id IN (
                SELECT person_id FROM records WHERE user_id = 3
            )
        )
        $purok_condition
    GROUP BY a.purok
    HAVING 1=1;
");
$params = [];
if ($user_role == 2 && $user_purok) {
    $params = [$user_purok];
}
$stmt->execute($params);
$population_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch water supply and toilet counts
$stmt = $pdo->prepare("
    SELECT 
        a.purok,
        COUNT(DISTINCT CASE 
            WHEN hr.water_source = 'Level 1 (Poso)' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS deep_well,
        COUNT(DISTINCT CASE 
            WHEN hr.water_source = 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS shallow_well,
        COUNT(DISTINCT CASE 
            WHEN hr.water_source = 'Level 3 (Nawasa)' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS level_iii,
        COUNT(DISTINCT CASE 
            WHEN hr.water_source = 'WRS (Water Refilling Station)' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS refilling_station,
        COUNT(DISTINCT CASE 
            WHEN hr.toilet_type = 'De Buhos' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS water_sealed,
        COUNT(DISTINCT CASE 
            WHEN hr.toilet_type = 'Pit Privy' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS pit_privy,
        COUNT(DISTINCT CASE 
            WHEN hr.toilet_type = 'Wala' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS without_toilet,
        COUNT(DISTINCT CASE 
            WHEN hr.toilet_type = 'Sanitary Pit' 
                AND p.relationship_type = 'Head' 
                AND r.record_type = 'household_record' 
            THEN p.person_id END) AS others
    FROM household_record hr
    JOIN records r ON hr.records_id = r.records_id
    JOIN person p ON r.person_id = p.person_id
    JOIN address a ON p.address_id = a.address_id
    JOIN users u ON r.user_id = u.user_id
    WHERE 
        r.record_type = 'household_record'
        AND u.role_id NOT IN (1, 2, 4)
        $purok_condition
    GROUP BY a.purok
    HAVING 1=1;
");
$params = [];
if ($user_role == 2 && $user_purok) {
    $params = [$user_purok];
}
$stmt->execute($params);
$facility_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge data for each purok
$purok_data = [];
foreach ($population_data as $data) {
    $purok = $data['purok'];
    $purok_data[$purok] = $data;
}
foreach ($facility_data as $data) {
    $purok = $data['purok'];
    if (isset($purok_data[$purok])) {
        $purok_data[$purok] = array_merge($purok_data[$purok], $data);
    } else {
        $purok_data[$purok] = $data;
    }
}

// Calculate total for the whole Brgy
$total_data = [
    'total_population' => 0,
    'total_male' => 0,
    'total_female' => 0,
    'total_household' => 0,
    'total_senior' => 0,
    'total_children' => 0,
    'total_adults' => 0,
    'deep_well' => 0,
    'shallow_well' => 0,
    'level_iii' => 0,
    'refilling_station' => 0,
    'water_sealed' => 0,
    'pit_privy' => 0,
    'without_toilet' => 0,
    'others' => 0
];
foreach ($purok_data as $data) {
    $total_data['total_population'] += (int)($data['total_population'] ?? 0);
    $total_data['total_male'] += (int)($data['total_male'] ?? 0);
    $total_data['total_female'] += (int)($data['total_female'] ?? 0);
    $total_data['total_household'] += (int)($data['total_household'] ?? 0);
    $total_data['total_senior'] += (int)($data['total_senior'] ?? 0);
    $total_data['total_children'] += (int)($data['total_children'] ?? 0);
    $total_data['total_adults'] += (int)($data['total_adults'] ?? 0);
    $total_data['deep_well'] += (int)($data['deep_well'] ?? 0);
    $total_data['shallow_well'] += (int)($data['shallow_well'] ?? 0);
    $total_data['level_iii'] += (int)($data['level_iii'] ?? 0);
    $total_data['refilling_station'] += (int)($data['refilling_station'] ?? 0);
    $total_data['water_sealed'] += (int)($data['water_sealed'] ?? 0);
    $total_data['pit_privy'] += (int)($data['pit_privy'] ?? 0);
    $total_data['without_toilet'] += (int)($data['without_toilet'] ?? 0);
    $total_data['others'] += (int)($data['others'] ?? 0);
}

// ---------- ENSURE STATS FOR DASHBOARD ARE ALWAYS DEFINED (but not in the PDF) ----------
$male_percentage = $total_data['total_population'] > 0 ? round(($total_data['total_male'] / $total_data['total_population']) * 100, 1) : 0;
$female_percentage = $total_data['total_population'] > 0 ? round(($total_data['total_female'] / $total_data['total_population']) * 100, 1) : 0;
$without_toilet_percentage = $total_data['total_household'] > 0 ? round(($total_data['without_toilet'] / $total_data['total_household']) * 100, 1) : 0;
$level3_coverage = $total_data['total_household'] > 0 ? round(($total_data['level_iii'] / $total_data['total_household']) * 100, 1) : 0;

// Fallback for missing keys (if user edits SQL)
$total_data['total_children'] = $total_data['total_children'] ?? 0;
$total_data['total_adults'] = $total_data['total_adults'] ?? 0;
$total_data['total_senior'] = $total_data['total_senior'] ?? 0;

// Fetch address details (assuming one address for simplicity)
$stmt = $pdo->query("SELECT barangay, municipality, province FROM address LIMIT 1");
$address = $stmt->fetch(PDO::FETCH_ASSOC);

// ------- PDF REPORT: YOUR ORIGINAL CODE, UNCHANGED -------
if (isset($_POST['download']) && isset($_POST['report_type'])) {
    $report_type = $_POST['report_type'];
    $pdf = new TCPDF('P', 'in', 'LEGAL', true, 'UTF-8', false);
    $pdf->SetCreator('BRGYCare');
    $pdf->SetAuthor('BRGYCare Admin');
    $pdf->SetTitle('Barangay Profile 2025');
    $pdf->SetSubject('Barangay Profile');
    $pdf->SetKeywords('Barangay, Profile, 2025');
    $pdf->setPrintHeader(false); // Disable default header
    $pdf->setPrintFooter(false); // Disable default footer
    $pdf->setHeaderFont(array('times', '', 12));
    $pdf->setFooterFont(array('times', '', 8));
    $pdf->SetDefaultMonospacedFont('times');
    $pdf->SetMargins(0.5, 0.5, 0.5, true);
    $pdf->SetAutoPageBreak(TRUE, 0.5);
    $pdf->SetFont('times', '', 10);
    $pdf->AddPage();

    // Set header for all reports
    $html = '<h1 style="text-align: center; font-size: 12px; color: green;">BARANGAY PROFILE 2025</h1>';
    $html .= '<h1 style="text-align: center; font-size: 11px; color: green;">Barangay: ' . ($address ? htmlspecialchars($address['barangay']) : '____________________') . '</h1>';

    if ($report_type === 'total' || $report_type === 'all') {
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; padding: 2px; vertical-align: top;">';
        $html .= '<table border="0" style="width: 100%;">';
        $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td style="font-weight: bold;">Total Population</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Total # of Male:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Total # of Female:</td></tr>';
        $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Household:</td></tr>';
        $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Water Supply</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Deep Well (60 meters deep):</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Shallow Well (&lt; 60 meters deep):</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Level III (Camiling Water District):</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Refilling Stations:</td></tr>';
        $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Toilets</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Water Sealed:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Pit Privy:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Without Toilet:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Others:</td></tr>';
        $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Food Establishments</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Restaurant:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Carinderia:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Canteens:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Food Chains:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Bakeries:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Talipapa:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;g. Water Refilling Station:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;h. Others (pls Specify):</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Industrial Establishments</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Piggery:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Poultry:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Rice Mill / Baby Cono / Mini Cono:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Sash Factory:</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Sidecar Making:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Others (Pls Indicate type):</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Solid Waste Management</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Waste Segregation:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Backyard Composting:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Recycling / Reuse:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Collected by MENRO:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Others (Burning / Burying):</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td style="font-weight: bold;">Total # of Public Places</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Public Laundry:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. School:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Swimming Pools:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Camps / Picnic Grounds:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Bus Terminal:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Barber Shops / Beauty Parlor:</td></tr>'; $html .= '<tr><td>&nbsp;&nbsp;&nbsp;g. Hotels / Motels:</td></tr>'; $html .= '<tr><td></td></tr>';
        $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '</table>'; $html .= '</td>';
        $html .= '<td style="width: 50%; padding: 2px; vertical-align: top;">';
        $html .= '<table border="0" style="width: 100%;">';
        $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['total_population']) . '</td></tr>';
        $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['total_male']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['total_female']) . '</td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['total_household']) . '</td></tr>';
        $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['deep_well']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['shallow_well']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['level_iii']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['refilling_station']) . '</td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['water_sealed']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['pit_privy']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['without_toilet']) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars($total_data['others']) . '</td></tr>';
        for ($i = 0; $i < 37; $i++) {
            $html .= '<tr><td></td></tr>';
        }
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;"><tr>';
        $html .= '<td style="width: 50%; padding: 2px; text-align: left;">';
        $html .= '<p>Prepared by:<br>______________________________<br>Barangay Health Worker</p>';
        $html .= '</td>';
        $html .= '<td style="width: 50%; padding: 2px; text-align: left;">';
        $html .= '<p>Noted by:<br>______________________________<br>Barangay Captain</p>';
        $html .= '</td></tr></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    if ($report_type === 'per_purok' || $report_type === 'all') {
        foreach ($purok_data as $purok => $data) {
            $pdf->AddPage();
            $html = '<h1 style="text-align: center; font-size: 12px; color: green;">BARANGAY PROFILE 2025</h1>';
            $html .= '<h1 style="text-align: center; font-size: 11px; color: green;">Barangay: ' . ($address ? htmlspecialchars($address['barangay']) : '____________________') . '</h1>';
            $html .= '<h2 style="text-align: center; color: green;">Purok: ' . htmlspecialchars($purok) . '</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr>';
            $html .= '<td style="width: 50%; padding: 2px; vertical-align: top;">';
            $html .= '<table border="0" style="width: 100%;">';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total Population</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Total # of Male:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Total # of Female:</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Household:</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Water Supply</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Deep Well (60 meters deep):</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Shallow Well (&lt; 60 meters deep):</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Level III (Camiling Water District):</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Refilling Stations:</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Toilets</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Water Sealed:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Pit Privy:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Without Toilet:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Others:</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Food Establishments</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Restaurant:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Carinderia:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Canteens:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Food Chains:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Bakeries:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Talipapa:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;g. Water Refilling Station:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;h. Others (pls Specify):</td></tr>';    
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Industrial Establishments</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Piggery:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Poultry:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Rice Mill / Baby Cono / Mini Cono:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Sash Factory:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Sidecar Making:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Others (Pls Indicate type):</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Solid Waste Management</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Waste Segregation:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. Backyard Composting:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Recycling / Reuse:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Collected by MENRO:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Others (Burning / Burying):</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Total # of Public Places</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;a. Public Laundry:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;b. School:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;c. Swimming Pools:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;d. Camps / Picnic Grounds:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;e. Bus Terminal:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;f. Barber Shops / Beauty Parlor:</td></tr>';
            $html .= '<tr><td>&nbsp;&nbsp;&nbsp;g. Hotels / Motels:</td></tr>';
            $html .= '<tr><td></td></tr>';
            $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '</table>'; $html .= '</td>';
            $html .= '<td style="width: 50%; padding: 2px; vertical-align: top;">';
            $html .= '<table border="0" style="width: 100%;">';
            $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['total_population'] ?? 0)) . '</td></tr>';
            $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['total_male'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['total_female'] ?? 0)) . '</td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['total_household'] ?? 0)) . '</td></tr>';
            $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['deep_well'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['shallow_well'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['level_iii'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['refilling_station'] ?? 0)) . '</td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td></td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['water_sealed'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['pit_privy'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['without_toilet'] ?? 0)) . '</td></tr>'; $html .= '<tr><td>' . htmlspecialchars((int)($data['others'] ?? 0)) . '</td></tr>';
            for ($i = 0; $i < 37; $i++) {
                $html .= '<tr><td></td></tr>';
            }
            $html .= '</table>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;"><tr>';
            $html .= '<td style="width: 50%; padding: 2px; text-align: left;">';
            $html .= '<p>Prepared by:<br>______________________________<br>Barangay Health Worker</p>';
            $html .= '</td>';
            $html .= '<td style="width: 50%; padding: 2px; text-align: left;">';
            $html .= '<p>Noted by:<br>______________________________<br>Barangay Captain</p>';
            $html .= '</td></tr></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }
    }

    $pdf->Output('BARANGAY_PROFILE_2025_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Barangay Profile (Enhanced)</title>
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
        .navbar, .navbar * { line-height: 1.2 !important; }
        .navbar .nav-link, .navbar .dropdown-toggle, .search-icon {
            padding-top: 4px !important;
            padding-bottom: 4px !important;
            line-height: 1.2 !important;
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
            font-size: 0.9rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress-bar-custom {
            background: #e2e8f0;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: #2b6cb0;
            transition: width 0.3s ease;
        }
        .alert-custom {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
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
            border-bottom: none;
            font-weight: 500;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f7fafc;
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
        .tab-content { padding: 15px; }
        .nav-scroller {
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .nav-scroller::-webkit-scrollbar {
            display: none;
        }
        .download-container {
            margin-bottom: 15px;
        }
        .download-container .dropdown {
            display: inline-block;
            margin-left: 10px;
        }
        @media (max-width: 768px) {
            .nav-scroller {
                overflow-x: auto;
            }
            .nav-scroller .nav {
                display: inline-flex;
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
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 0;
            }
            .menu-toggle { display: block; color: #fff; font-size: 1.5rem; cursor: pointer; position: absolute; left: 10px; top: 20px; z-index: 1060; }
            .card { margin-bottom: 15px; margin-left: 0; margin-right: 0;}
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
            .nav-scroller { overflow-x: hidden; }
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
                        <i class="fas fa-chart-bar"></i> Barangay Profile <?php echo $year; ?>
                    </div>
                    <div class="card-body p-3">
                        <?php if ($user_role == 1 || $user_role == 4): ?>
                            <!-- Key Insights -->
                            <div class="row mb-3 stats-container">
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Total Population</div>
                                        <div class="stat-value"><?php echo number_format($total_data['total_population']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-male"></i> <?php echo $male_percentage; ?>% Male | 
                                            <i class="fas fa-female"></i> <?php echo $female_percentage; ?>% Female
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Households</div>
                                        <div class="stat-value"><?php echo number_format($total_data['total_household']); ?></div>
                                        <small class="text-muted">Avg: <?php echo $total_data['total_household'] > 0 ? round($total_data['total_population'] / $total_data['total_household'], 1) : 0; ?> persons/household</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Water Coverage</div>
                                        <div class="stat-value"><?php echo $level3_coverage; ?>%</div>
                                        <small class="text-muted">Level III (Nawasa)</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Sanitation</div>
                                        <div class="stat-value"><?php echo $without_toilet_percentage; ?>%</div>
                                        <small class="text-muted">Without toilet facilities</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerts -->
                            <?php if ($without_toilet_percentage > 10): ?>
                                <div class="alert-warning-custom alert-custom">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Sanitation Alert:</strong> <?php echo $total_data['without_toilet']; ?> households (<?php echo $without_toilet_percentage; ?>%) lack toilet facilities. Intervention needed.
                                </div>
                            <?php endif; ?>
                            <?php if ($level3_coverage > 75): ?>
                                <div class="alert-success-custom alert-custom">
                                    <i class="fas fa-check-circle"></i> <strong>Water Coverage:</strong> Good coverage at <?php echo $level3_coverage; ?>% Level III access.
                                </div>
                            <?php endif; ?>

                            <div class="download-container">
                                <form method="post" class="form-inline">
                                    <button type="submit" name="download" value="1" class="btn btn-primary">
                                        <i class="fas fa-download"></i> Download PDF
                                    </button>
                                    <select name="report_type" class="form-control ml-2" required>
                                        <option value="total">Total Brgy</option>
                                        <option value="per_purok">Per Purok</option>
                                        <option value="all">All (Total + Per Purok)</option>
                                    </select>
                                </form>
                            </div>

                            <?php
                            $puroks = array_keys($purok_data);
                            if (empty($puroks)) {
                                echo "<p>No data available for any purok.</p>";
                            } else {
                                echo "<div class='nav-scroller'>";
                                echo "<ul class='nav nav-tabs' id='purokTabs' role='tablist'>";
                                echo "<li class='nav-item'>
                                    <a class='nav-link active' id='tab-total-tab' data-toggle='tab' href='#tab-total' role='tab' aria-controls='tab-total' aria-selected='true'><i class='fas fa-globe'></i> Total Brgy</a>
                                </li>";
                                foreach ($puroks as $index => $purok) {
                                    $tabId = "tab-" . $index;
                                    echo "<li class='nav-item'>
                                        <a class='nav-link' id='$tabId-tab' data-toggle='tab' href='#$tabId' role='tab' aria-controls='$tabId' aria-selected='false'><i class='fas fa-map-marker-alt'></i> $purok</a>
                                    </li>";
                                }
                                echo "</ul>";
                                echo "</div>";
                                echo "<div class='tab-content' id='purokTabContent'>";
                                
                                // Total Brgy tab
                                echo "<div class='tab-pane fade show active' id='tab-total' role='tabpanel' aria-labelledby='tab-total-tab'>
                                    <div class='table-responsive'>
                                    <table class='table table-striped'>
                                        <thead>
                                            <tr>
                                                <th>Indicator</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class='table-info'><td colspan='3'><strong>DEMOGRAPHIC DATA</strong></td></tr>
                                            <tr><td><strong>Total Population</strong></td><td>" . number_format((int)$total_data['total_population']) . "</td><td>100%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Male</td><td>" . number_format((int)$total_data['total_male']) . "</td><td>$male_percentage%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Female</td><td>" . number_format((int)$total_data['total_female']) . "</td><td>$female_percentage%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Children (0-17)</td><td>" . number_format((int)$total_data['total_children']) . "</td><td>" . ($total_data['total_population'] > 0 ? round(($total_data['total_children'] / $total_data['total_population']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Adults (18-59)</td><td>" . number_format((int)$total_data['total_adults']) . "</td><td>" . ($total_data['total_population'] > 0 ? round(($total_data['total_adults'] / $total_data['total_population']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Seniors (60+)</td><td>" . number_format((int)$total_data['total_senior']) . "</td><td>" . ($total_data['total_population'] > 0 ? round(($total_data['total_senior'] / $total_data['total_population']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td><strong>Total Households</strong></td><td>" . number_format((int)$total_data['total_household']) . "</td><td>-</td></tr>
                                            <tr class='table-info'><td colspan='3'><strong>WATER & SANITATION</strong></td></tr>
                                            <tr><td><strong>Water Supply</strong></td><td></td><td></td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Level I (Poso)</td><td>" . number_format((int)$total_data['deep_well']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['deep_well'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Level II (Community)</td><td>" . number_format((int)$total_data['shallow_well']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['shallow_well'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Level III (Nawasa)</td><td>" . number_format((int)$total_data['level_iii']) . "</td><td>$level3_coverage%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Refilling Station</td><td>" . number_format((int)$total_data['refilling_station']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['refilling_station'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td><strong>Toilet Facilities</strong></td><td></td><td></td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Water Sealed</td><td>" . number_format((int)$total_data['water_sealed']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['water_sealed'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Pit Privy</td><td>" . number_format((int)$total_data['pit_privy']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['pit_privy'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Without Toilet</td><td>" . number_format((int)$total_data['without_toilet']) . "</td><td><strong>$without_toilet_percentage%</strong></td></tr>
                                            <tr><td>&nbsp;&nbsp;&nbsp;Others</td><td>" . number_format((int)$total_data['others']) . "</td><td>" . ($total_data['total_household'] > 0 ? round(($total_data['others'] / $total_data['total_household']) * 100, 1) : 0) . "%</td></tr>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>";
                                
                                // Per Purok tabs
                                foreach ($puroks as $index => $purok) {
                                    $data = $purok_data[$purok];
                                    $tabId = "tab-" . $index;
                                    if (is_array($data)) {
                                        $purok_male_pct = (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_male'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0;
                                        $purok_female_pct = (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_female'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0;
                                        
                                        echo "<div class='tab-pane fade' id='$tabId' role='tabpanel' aria-labelledby='$tabId-tab'>
                                            <div class='table-responsive'>
                                            <table class='table table-striped'>
                                                <thead>
                                                    <tr>
                                                        <th>Indicator</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class='table-info'><td colspan='3'><strong>DEMOGRAPHIC DATA</strong></td></tr>
                                                    <tr><td><strong>Total Population</strong></td><td>" . number_format((int)($data['total_population'] ?? 0)) . "</td><td>100%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Male</td><td>" . number_format((int)($data['total_male'] ?? 0)) . "</td><td>$purok_male_pct%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Female</td><td>" . number_format((int)($data['total_female'] ?? 0)) . "</td><td>$purok_female_pct%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Children (0-17)</td><td>" . number_format((int)($data['total_children'] ?? 0)) . "</td><td>" . ((int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_children'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Adults (18-59)</td><td>" . number_format((int)($data['total_adults'] ?? 0)) . "</td><td>" . ((int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_adults'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Seniors (60+)</td><td>" . number_format((int)($data['total_senior'] ?? 0)) . "</td><td>" . ((int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_senior'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td><strong>Total Households</strong></td><td>" . number_format((int)($data['total_household'] ?? 0)) . "</td><td>-</td></tr>
                                                    <tr class='table-info'><td colspan='3'><strong>WATER & SANITATION</strong></td></tr>
                                                    <tr><td><strong>Water Supply</strong></td><td></td><td></td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Level I (Poso)</td><td>" . number_format((int)($data['deep_well'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['deep_well'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Level II (Community)</td><td>" . number_format((int)($data['shallow_well'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['shallow_well'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Level III (Nawasa)</td><td>" . number_format((int)($data['level_iii'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['level_iii'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Refilling Station</td><td>" . number_format((int)($data['refilling_station'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['refilling_station'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td><strong>Toilet Facilities</strong></td><td></td><td></td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Water Sealed</td><td>" . number_format((int)($data['water_sealed'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['water_sealed'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Pit Privy</td><td>" . number_format((int)($data['pit_privy'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['pit_privy'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Without Toilet</td><td>" . number_format((int)($data['without_toilet'] ?? 0)) . "</td><td><strong>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['without_toilet'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</strong></td></tr>
                                                    <tr><td>&nbsp;&nbsp;&nbsp;Others</td><td>" . number_format((int)($data['others'] ?? 0)) . "</td><td>" . ((int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['others'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0) . "%</td></tr>
                                                </tbody>
                                            </table>
                                            </div>
                                        </div>";
                                    }
                                }
                                echo "</div>";
                            }
                            ?>
                        <?php elseif ($user_role == 2 && $user_purok): ?>
                            <h4><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user_purok); ?></h4>
                            <?php $data = $purok_data[$user_purok] ?? []; 
                            $purok_male_pct = (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_male'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0;
                            $purok_female_pct = (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_female'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0;
                            ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-label">Population</div>
                                        <div class="stat-value"><?php echo number_format((int)($data['total_population'] ?? 0)); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-label">Households</div>
                                        <div class="stat-value"><?php echo number_format((int)($data['total_household'] ?? 0)); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-label">Seniors</div>
                                        <div class="stat-value"><?php echo number_format((int)($data['total_senior'] ?? 0)); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Indicator</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class='table-info'><td colspan='3'><strong>DEMOGRAPHIC DATA</strong></td></tr>
                                    <tr><td><strong>Total Population</strong></td><td><?php echo number_format((int)($data['total_population'] ?? 0)); ?></td><td>100%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Male</td><td><?php echo number_format((int)($data['total_male'] ?? 0)); ?></td><td><?php echo $purok_male_pct; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Female</td><td><?php echo number_format((int)($data['total_female'] ?? 0)); ?></td><td><?php echo $purok_female_pct; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Children (0-17)</td><td><?php echo number_format((int)($data['total_children'] ?? 0)); ?></td><td><?php echo (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_children'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Adults (18-59)</td><td><?php echo number_format((int)($data['total_adults'] ?? 0)); ?></td><td><?php echo (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_adults'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Seniors (60+)</td><td><?php echo number_format((int)($data['total_senior'] ?? 0)); ?></td><td><?php echo (int)($data['total_population'] ?? 0) > 0 ? round(((int)($data['total_senior'] ?? 0) / (int)($data['total_population'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td><strong>Total Households</strong></td><td><?php echo number_format((int)($data['total_household'] ?? 0)); ?></td><td>-</td></tr>
                                    <tr class='table-info'><td colspan='3'><strong>WATER & SANITATION</strong></td></tr>
                                    <tr><td><strong>Water Supply</strong></td><td></td><td></td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Level I (Poso)</td><td><?php echo number_format((int)($data['deep_well'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['deep_well'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Level II (Community)</td><td><?php echo number_format((int)($data['shallow_well'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['shallow_well'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Level III (Nawasa)</td><td><?php echo number_format((int)($data['level_iii'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['level_iii'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Refilling Station</td><td><?php echo number_format((int)($data['refilling_station'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['refilling_station'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td><strong>Toilet Facilities</strong></td><td></td><td></td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Water Sealed</td><td><?php echo number_format((int)($data['water_sealed'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['water_sealed'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Pit Privy</td><td><?php echo number_format((int)($data['pit_privy'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['pit_privy'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Without Toilet</td><td><?php echo number_format((int)($data['without_toilet'] ?? 0)); ?></td><td><strong><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['without_toilet'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</strong></td></tr>
                                    <tr><td>&nbsp;&nbsp;&nbsp;Others</td><td><?php echo number_format((int)($data['others'] ?? 0)); ?></td><td><?php echo (int)($data['total_household'] ?? 0) > 0 ? round(((int)($data['others'] ?? 0) / (int)($data['total_household'] ?? 0)) * 100, 1) : 0; ?>%</td></tr>
                                </tbody>
                            </table>
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
