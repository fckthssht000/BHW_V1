<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

session_start();

// Adjust path based on your file structure
// If db_connect.php is in the root directory, use:
require_once '../db_connect.php';
// OR if it's somewhere else, adjust accordingly:
// require_once dirname(__DIR__) . '/db_connect.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$metric = isset($_GET['metric']) ? $_GET['metric'] : 'none';

if ($metric === 'none' || empty($metric)) {
    echo json_encode(['error' => 'No metric selected']);
    exit;
}

// Validate metric format
$parts = explode('.', $metric);
if (count($parts) !== 2) {
    echo json_encode(['error' => 'Invalid metric format. Expected format: category.field']);
    exit;
}

list($category, $field) = $parts;

// Sanitize inputs
$category = preg_replace('/[^a-z_]/', '', $category);
$field = preg_replace('/[^a-z0-9_]/', '', $field);

$purokData = [];
$metricLabel = '';
$sql = '';

try {
    // Build SQL query based on category
    switch ($category) {
        case 'child':
            $metricLabel = getChildLabel($field);
            // Validate field exists in child_immunization table
            $validFields = ['mmr', 'vitamin_a', 'fully_immunized', 'completely_immunized'];
            if (!in_array($field, $validFields)) {
                throw new Exception('Invalid child field: ' . $field);
            }
            $sql = "SELECT a.purok, COUNT(*) as value
                    FROM child_immunization ci
                    JOIN records r ON ci.record_id = r.record_id
                    JOIN person p ON r.person_id = p.person_id
                    JOIN address a ON p.address_id = a.address_id
                    WHERE ci.$field = 'Yes'
                    GROUP BY a.purok
                    ORDER BY a.purok";
            break;
            
        case 'infant':
            $metricLabel = getInfantLabel($field);
            $validFields = ['bcg', 'hepb', 'dtp1', 'dtp2', 'dtp3', 'opv1', 'opv2', 'opv3', 
                           'ipv1', 'ipv2', 'pcv1', 'pcv2', 'pcv3', 'mcv1', 'mcv2'];
            if (!in_array($field, $validFields)) {
                throw new Exception('Invalid infant field: ' . $field);
            }
            $sql = "SELECT a.purok, COUNT(*) as value
                    FROM infant_immunization ii
                    JOIN records r ON ii.record_id = r.record_id
                    JOIN person p ON r.person_id = p.person_id
                    JOIN address a ON p.address_id = a.address_id
                    WHERE ii.$field = 'Yes'
                    GROUP BY a.purok
                    ORDER BY a.purok";
            break;
            
        case 'prenatal':
            $metricLabel = getPrenatalLabel($field);
            $validFields = ['headache_blurred_vision', 'fever', 'vaginal_bleeding', 
                           'convulsion', 'severe_abdominal_pain', 'paleness', 'swelling'];
            if (!in_array($field, $validFields)) {
                throw new Exception('Invalid prenatal field: ' . $field);
            }
            $sql = "SELECT a.purok, COUNT(*) as value
                    FROM prenatal_checkup pc
                    JOIN records r ON pc.record_id = r.record_id
                    JOIN person p ON r.person_id = p.person_id
                    JOIN address a ON p.address_id = a.address_id
                    WHERE pc.$field = 'Yes'
                    GROUP BY a.purok
                    ORDER BY a.purok";
            break;
            
        case 'postnatal':
            $metricLabel = getPostnatalLabel($field);
            // Map field names to database values
            $deliveryPlaceMap = [
                'hospital' => 'Hospital',
                'center' => 'Center',
                'bahay' => 'Bahay',
                'others' => 'Others'
            ];
            
            if (!isset($deliveryPlaceMap[$field])) {
                throw new Exception('Invalid postnatal field: ' . $field);
            }
            
            $deliveryPlace = $deliveryPlaceMap[$field];
            $sql = "SELECT a.purok, COUNT(*) as value
                    FROM postnatal_checkup pc
                    JOIN records r ON pc.record_id = r.record_id
                    JOIN person p ON r.person_id = p.person_id
                    JOIN address a ON p.address_id = a.address_id
                    WHERE pc.place_of_delivery = :deliveryPlace
                    GROUP BY a.purok
                    ORDER BY a.purok";
            break;
            
        case 'senior':
            $metricLabel = getSeniorLabel($field);
            $validFields = ['amlodipine_5mg', 'amlodipine_10mg', 'losartan_100mg', 
                           'metoprolol_50mg', 'metformin_500mg', 'gliclazide_30mg', 
                           'carvidolol_12mg', 'simvastatin_20mg'];
            if (!in_array($field, $validFields)) {
                throw new Exception('Invalid senior field: ' . $field);
            }
            $sql = "SELECT a.purok, COUNT(*) as value
                    FROM senior_checkup sc
                    JOIN records r ON sc.record_id = r.record_id
                    JOIN person p ON r.person_id = p.person_id
                    JOIN address a ON p.address_id = a.address_id
                    WHERE sc.$field = 'Yes'
                    GROUP BY a.purok
                    ORDER BY a.purok";
            break;
            
        default:
            throw new Exception('Invalid category: ' . $category);
    }
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    
    // Bind parameter for postnatal queries
    if ($category === 'postnatal') {
        $stmt->bindParam(':deliveryPlace', $deliveryPlace, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    
    // Fetch all results
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $purokData[$row['purok']] = [
            'value' => (int)$row['value'],
            'label' => $row['purok']
        ];
    }
    
    // Return success response
    $response = [
        'success' => true,
        'purokData' => $purokData,
        'metricLabel' => $metricLabel,
        'category' => $category,
        'field' => $field
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'sql' => $sql
    ]);
} catch (Exception $e) {
    // General error
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

// ============================================
// HELPER FUNCTIONS FOR LABELS
// ============================================

function getChildLabel($field) {
    $labels = [
        'mmr' => 'MMR (12-15 Months)',
        'vitamin_a' => 'Vitamin A (12-59 Months)',
        'fully_immunized' => 'Fully Immunized (FIC)',
        'completely_immunized' => 'Completely Immunized (CIC)'
    ];
    return isset($labels[$field]) ? $labels[$field] : ucfirst(str_replace('_', ' ', $field));
}

function getInfantLabel($field) {
    $fieldUpper = strtoupper($field);
    return $fieldUpper . ' Vaccine Coverage';
}

function getPrenatalLabel($field) {
    $labels = [
        'headache_blurred_vision' => 'Headache & Blurred Vision Cases',
        'fever' => 'Fever Cases',
        'vaginal_bleeding' => 'Vaginal Bleeding Cases',
        'convulsion' => 'Convulsion Cases',
        'severe_abdominal_pain' => 'Severe Abdominal Pain Cases',
        'paleness' => 'Paleness Cases',
        'swelling' => 'Swelling Cases'
    ];
    return isset($labels[$field]) ? $labels[$field] : ucfirst(str_replace('_', ' ', $field));
}

function getPostnatalLabel($field) {
    $labels = [
        'hospital' => 'Hospital Deliveries',
        'center' => 'Health Center Deliveries',
        'bahay' => 'Home Deliveries',
        'others' => 'Other Delivery Locations'
    ];
    return isset($labels[$field]) ? $labels[$field] : ucfirst($field) . ' Deliveries';
}

function getSeniorLabel($field) {
    $labels = [
        'amlodipine_5mg' => 'Amlodipine 5mg Users',
        'amlodipine_10mg' => 'Amlodipine 10mg Users',
        'losartan_100mg' => 'Losartan 100mg Users',
        'metoprolol_50mg' => 'Metoprolol 50mg Users',
        'metformin_500mg' => 'Metformin 500mg Users',
        'gliclazide_30mg' => 'Gliclazide 30mg Users',
        'carvidolol_12mg' => 'Carvidolol 12.5mg Users',
        'simvastatin_20mg' => 'Simvastatin 20mg Users'
    ];
    return isset($labels[$field]) ? $labels[$field] : ucfirst(str_replace('_', ' ', $field));
}
?>
