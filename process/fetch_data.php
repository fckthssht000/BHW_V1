<?php
// Suppress PHP warnings in output to prevent JSON corruption
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Log errors for debugging

// Start output buffering to capture any unintended output
ob_start();

session_start();
require_once '../db_connect.php'; // Adjust path for process/ directory

header('Content-Type: application/json; charset=utf-8');

// Check database connection
if (!$pdo) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$purok = isset($_GET['purok']) ? $_GET['purok'] : null;

function fetchImmunizationData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN cr.immunization_status LIKE '%MMR (12-15 Months)%' THEN 1 ELSE 0 END), 0) as mmr,
                    COALESCE(SUM(CASE WHEN cr.immunization_status LIKE '%Vitamin A (12-59 Months)%' THEN 1 ELSE 0 END), 0) as vitamin_a,
                    COALESCE(SUM(CASE WHEN cr.immunization_status LIKE '%Fully Immunized%' THEN 1 ELSE 0 END), 0) as fully_immunized,
                    COALESCE(SUM(CASE WHEN cr.immunization_status LIKE '%Completely Immunized%' THEN 1 ELSE 0 END), 0) as completely_immunized
                FROM child_record cr
                JOIN records r ON cr.records_id = r.records_id
                JOIN person p ON r.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " WHERE a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'mmr' => 0,
            'vitamin_a' => 0,
            'fully_immunized' => 0,
            'completely_immunized' => 0
        ];
    } catch (Exception $e) {
        error_log('fetchImmunizationData error: ' . $e->getMessage());
        return [
            'mmr' => 0,
            'vitamin_a' => 0,
            'fully_immunized' => 0,
            'completely_immunized' => 0
        ];
    }
}

function fetchInfantVaccinationData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%BCG%' THEN 1 ELSE 0 END), 0) as bcg,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%HepB%' THEN 1 ELSE 0 END), 0) as hepb,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%DTP1%' THEN 1 ELSE 0 END), 0) as dtp1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%DTP2%' THEN 1 ELSE 0 END), 0) as dtp2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%DTP3%' THEN 1 ELSE 0 END), 0) as dtp3,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%OPV1%' THEN 1 ELSE 0 END), 0) as opv1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%OPV2%' THEN 1 ELSE 0 END), 0) as opv2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%OPV3%' THEN 1 ELSE 0 END), 0) as opv3,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%IPV1%' THEN 1 ELSE 0 END), 0) as ipv1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%IPV2%' THEN 1 ELSE 0 END), 0) as ipv2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%PCV1%' THEN 1 ELSE 0 END), 0) as pcv1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%PCV2%' THEN 1 ELSE 0 END), 0) as pcv2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%PCV3%' THEN 1 ELSE 0 END), 0) as pcv3,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%MCV1%' THEN 1 ELSE 0 END), 0) as mcv1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%MCV2%' THEN 1 ELSE 0 END), 0) as mcv2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%TT1%' THEN 1 ELSE 0 END), 0) as tt1,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%TT2%' THEN 1 ELSE 0 END), 0) as tt2,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%TT3%' THEN 1 ELSE 0 END), 0) as tt3,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%TT4%' THEN 1 ELSE 0 END), 0) as tt4,
                    COALESCE(SUM(CASE WHEN ir.vaccines LIKE '%TT5%' THEN 1 ELSE 0 END), 0) as tt5
                FROM infant_record ir
                JOIN person p ON ir.head_person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id
                WHERE ir.vaccines IS NOT NULL";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " AND a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'bcg' => 0, 'hepb' => 0, 'dtp1' => 0, 'dtp2' => 0, 'dtp3' => 0,
            'opv1' => 0, 'opv2' => 0, 'opv3' => 0, 'ipv1' => 0, 'ipv2' => 0,
            'pcv1' => 0, 'pcv2' => 0, 'pcv3' => 0, 'mcv1' => 0, 'mcv2' => 0,
            'tt1' => 0, 'tt2' => 0, 'tt3' => 0, 'tt4' => 0, 'tt5' => 0
        ];
    } catch (Exception $e) {
        error_log('fetchInfantVaccinationData error: ' . $e->getMessage());
        return [
            'bcg' => 0, 'hepb' => 0, 'dtp1' => 0, 'dtp2' => 0, 'dtp3' => 0,
            'opv1' => 0, 'opv2' => 0, 'opv3' => 0, 'ipv1' => 0, 'ipv2' => 0,
            'pcv1' => 0, 'pcv2' => 0, 'pcv3' => 0, 'mcv1' => 0, 'mcv2' => 0,
            'tt1' => 0, 'tt2' => 0, 'tt3' => 0, 'tt4' => 0, 'tt5' => 0
        ];
    }
}

function fetchMaternalRiskData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Headache & Blurred Vision%' THEN 1 ELSE 0 END), 0) as headache_blurred_vision,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Fever%' THEN 1 ELSE 0 END), 0) as fever,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Vaginal Bleeding%' THEN 1 ELSE 0 END), 0) as vaginal_bleeding,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Convulsion%' THEN 1 ELSE 0 END), 0) as convulsion,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Severe Abdominal Pain%' THEN 1 ELSE 0 END), 0) as severe_abdominal_pain,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Paleness%' THEN 1 ELSE 0 END), 0) as paleness,
                    COALESCE(SUM(CASE WHEN p.risk_observed LIKE '%Swelling of the Foot/Feet%' THEN 1 ELSE 0 END), 0) as swelling
                FROM prenatal p
                JOIN pregnancy_record pr ON p.pregnancy_period_id = pr.pregnancy_period_id
                JOIN records r ON pr.records_id = r.records_id
                JOIN person pe ON r.person_id = pe.person_id
                JOIN address a ON pe.address_id = a.address_id
                WHERE p.risk_observed IS NOT NULL";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " AND a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'headache_blurred_vision' => 0,
            'fever' => 0,
            'vaginal_bleeding' => 0,
            'convulsion' => 0,
            'severe_abdominal_pain' => 0,
            'paleness' => 0,
            'swelling' => 0
        ];
    } catch (Exception $e) {
        error_log('fetchMaternalRiskData error: ' . $e->getMessage());
        return [
            'headache_blurred_vision' => 0,
            'fever' => 0,
            'vaginal_bleeding' => 0,
            'convulsion' => 0,
            'severe_abdominal_pain' => 0,
            'paleness' => 0,
            'swelling' => 0
        ];
    }
}

function fetchPostnatalDeliveryData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN delivery_location = 'Center' THEN 1 ELSE 0 END), 0) as center,
                    COALESCE(SUM(CASE WHEN delivery_location = 'Hospital' THEN 1 ELSE 0 END), 0) as hospital,
                    COALESCE(SUM(CASE WHEN delivery_location = 'Bahay' THEN 1 ELSE 0 END), 0) as bahay,
                    COALESCE(SUM(CASE WHEN delivery_location = 'Others' THEN 1 ELSE 0 END), 0) as others
                FROM postnatal p
                JOIN pregnancy_record pr ON p.pregnancy_period_id = pr.pregnancy_period_id
                JOIN records r ON pr.records_id = r.records_id
                JOIN person pe ON r.person_id = pe.person_id
                JOIN address a ON pe.address_id = a.address_id
                WHERE p.delivery_location IS NOT NULL";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " AND a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'center' => 0,
            'hospital' => 0,
            'bahay' => 0,
            'others' => 0
        ];
    } catch (Exception $e) {
        error_log('fetchPostnatalDeliveryData error: ' . $e->getMessage());
        return [
            'center' => 0,
            'hospital' => 0,
            'bahay' => 0,
            'others' => 0
        ];
    }
}

function fetchSeniorMedicationData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Amlodipine 5mg' THEN 1 ELSE 0 END), 0) as amlodipine_5mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Amlodipine 10mg' THEN 1 ELSE 0 END), 0) as amlodipine_10mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Losartan 100mg' THEN 1 ELSE 0 END), 0) as losartan_100mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Metoprolol 50mg' THEN 1 ELSE 0 END), 0) as metoprolol_50mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Metformin 500mg' THEN 1 ELSE 0 END), 0) as metformin_500mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Gliclazide 30mg' THEN 1 ELSE 0 END), 0) as gliclazide_30mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Carvidolol 12.5mg' THEN 1 ELSE 0 END), 0) as carvidolol_12mg,
                    COALESCE(SUM(CASE WHEN m.medication_name = 'Simvastatin 20mg' THEN 1 ELSE 0 END), 0) as simvastatin_20mg
                FROM senior_medication sm
                JOIN medication m ON sm.medication_id = m.medication_id
                JOIN senior_record sr ON sm.senior_record_id = sr.senior_record_id
                JOIN records r ON sr.records_id = r.records_id
                JOIN person p ON r.person_id = p.person_id
                JOIN address a ON p.address_id = a.address_id";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " WHERE a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'amlodipine_5mg' => 0,
            'amlodipine_10mg' => 0,
            'losartan_100mg' => 0,
            'metoprolol_50mg' => 0,
            'metformin_500mg' => 0,
            'gliclazide_30mg' => 0,
            'carvidolol_12mg' => 0,
            'simvastatin_20mg' => 0
        ];
    } catch (Exception $e) {
        error_log('fetchSeniorMedicationData error: ' . $e->getMessage());
        return [
            'amlodipine_5mg' => 0,
            'amlodipine_10mg' => 0,
            'losartan_100mg' => 0,
            'metoprolol_50mg' => 0,
            'metformin_500mg' => 0,
            'gliclazide_30mg' => 0,
            'carvidolol_12mg' => 0,
            'simvastatin_20mg' => 0
        ];
    }
}

function fetchPurokDisparitiesData($pdo, $purok = null) {
    try {
        $sql = "SELECT 
                    a.purok,
                    COALESCE(SUM(CASE WHEN sm.medication_id IN (SELECT medication_id FROM medication WHERE medication_name IN ('Amlodipine 5mg', 'Amlodipine 10mg', 'Losartan 100mg', 'Metoprolol 50mg')) THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT sr.person_id), 0), 0) as purok_hypertension,
                    COALESCE(SUM(CASE WHEN prn.risk_observed IS NOT NULL AND prn.risk_observed != '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT prn.person_id), 0), 0) as purok_high_risk_pregnancy,
                    COALESCE(SUM(CASE WHEN cr.immunization_status NOT LIKE '%Fully Immunized%' AND cr.immunization_status NOT LIKE '%Completely Immunized%' AND cr.immunization_status != '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT cr.person_id), 0), 0) as purok_low_immunization
                FROM address a
                LEFT JOIN person p ON a.address_id = p.address_id
                LEFT JOIN records r ON p.person_id = r.person_id
                LEFT JOIN senior_record sr ON r.records_id = sr.records_id
                LEFT JOIN senior_medication sm ON sr.senior_record_id = sm.senior_record_id
                LEFT JOIN pregnancy_record pr ON r.records_id = pr.records_id
                LEFT JOIN prenatal prn ON pr.pregnancy_period_id = prn.pregnancy_period_id
                LEFT JOIN child_record cr ON r.records_id = cr.records_id
                GROUP BY a.purok";
        $params = [];
        if ($purok && $purok !== 'All') {
            $sql .= " HAVING a.purok = :purok";
            $params['purok'] = $purok;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [
            'purok1_hypertension' => 0, 'purok2_hypertension' => 0, 'purok3_hypertension' => 0, 'purok4_hypertension' => 0,
            'purok1_high_risk_pregnancy' => 0, 'purok2_high_risk_pregnancy' => 0, 'purok3_high_risk_pregnancy' => 0, 'purok4_high_risk_pregnancy' => 0,
            'purok1_low_immunization' => 0, 'purok2_low_immunization' => 0, 'purok3_low_immunization' => 0, 'purok4_low_immunization' => 0
        ];
        foreach ($results as $row) {
            if ($row['purok'] == 'Purok 1') {
                $data['purok1_hypertension'] = $row['purok_hypertension'];
                $data['purok1_high_risk_pregnancy'] = $row['purok_high_risk_pregnancy'];
                $data['purok1_low_immunization'] = $row['purok_low_immunization'];
            } elseif ($row['purok'] == 'Purok 2') {
                $data['purok2_hypertension'] = $row['purok_hypertension'];
                $data['purok2_high_risk_pregnancy'] = $row['purok_high_risk_pregnancy'];
                $data['purok2_low_immunization'] = $row['purok_low_immunization'];
            } elseif ($row['purok'] == 'Purok 3') {
                $data['purok3_hypertension'] = $row['purok_hypertension'];
                $data['purok3_high_risk_pregnancy'] = $row['purok_high_risk_pregnancy'];
                $data['purok3_low_immunization'] = $row['purok_low_immunization'];
            } elseif ($row['purok'] == 'Purok 4') {
                $data['purok4_hypertension'] = $row['purok_hypertension'];
                $data['purok4_high_risk_pregnancy'] = $row['purok_high_risk_pregnancy'];
                $data['purok4_low_immunization'] = $row['purok_low_immunization'];
            }
        }
        return $data;
    } catch (Exception $e) {
        error_log('fetchPurokDisparitiesData error: ' . $e->getMessage());
        return [
            'purok1_hypertension' => 0, 'purok2_hypertension' => 0, 'purok3_hypertension' => 0, 'purok4_hypertension' => 0,
            'purok1_high_risk_pregnancy' => 0, 'purok2_high_risk_pregnancy' => 0, 'purok3_high_risk_pregnancy' => 0, 'purok4_high_risk_pregnancy' => 0,
            'purok1_low_immunization' => 0, 'purok2_low_immunization' => 0, 'purok3_low_immunization' => 0, 'purok4_low_immunization' => 0
        ];
    }
}

try {
    $data = [
        'child' => fetchImmunizationData($pdo, $purok),
        'infant' => fetchInfantVaccinationData($pdo, $purok),
        'prenatal' => fetchMaternalRiskData($pdo, $purok),
        'postnatal' => fetchPostnatalDeliveryData($pdo, $purok),
        'senior' => fetchSeniorMedicationData($pdo, $purok),
        'purok' => fetchPurokDisparitiesData($pdo, $purok)
    ];
    ob_end_clean();
    echo json_encode($data, JSON_NUMERIC_CHECK);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}
?>