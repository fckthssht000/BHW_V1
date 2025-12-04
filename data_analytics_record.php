<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user role and purok for filtering
$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT a.purok 
    FROM users u 
    JOIN records r ON u.user_id = r.user_id 
    JOIN person p ON r.person_id = p.person_id 
    JOIN address a ON p.address_id = a.address_id 
    WHERE u.user_id = ? LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user_purok = $stmt->fetchColumn();

// Date range filter (last 12 months by default)
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// ===================== CHILD HEALTH INSIGHTS =====================
function getChildHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. Growth Monitoring - Malnutrition Risk
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_children,
                AVG(CAST(cr.weight AS DECIMAL(10,2))) as avg_weight,
                AVG(CAST(cr.height AS DECIMAL(10,2))) as avg_height,
                COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 10 THEN 1 END) as underweight_count,
                COUNT(CASE WHEN CAST(cr.height AS DECIMAL(10,2)) < 80 THEN 1 END) as stunted_count
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_children,
                AVG(CAST(cr.weight AS DECIMAL(10,2))) as avg_weight,
                AVG(CAST(cr.height AS DECIMAL(10,2))) as avg_height,
                COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 10 THEN 1 END) as underweight_count,
                COUNT(CASE WHEN CAST(cr.height AS DECIMAL(10,2)) < 80 THEN 1 END) as stunted_count
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['growth_monitoring'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Disease Risk Distribution
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                cr.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM child_record cr2 
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE cr2.measurement_date BETWEEN ? AND ? 
                    AND p2.age BETWEEN 1 AND 6 
                    AND a2.purok = ?
                    AND cr2.child_type = 'Child'
                ), 0), 2) as percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            AND cr.risk_observed IS NOT NULL
            AND cr.risk_observed != ''
            AND a.purok = ?
            GROUP BY cr.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                cr.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM child_record cr2 
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE cr2.measurement_date BETWEEN ? AND ? 
                    AND p2.age BETWEEN 1 AND 6
                    AND cr2.child_type = 'Child'
                ), 0), 2) as percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            AND cr.risk_observed IS NOT NULL
            AND cr.risk_observed != ''
            GROUP BY cr.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['disease_risk'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Immunization Coverage
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN cr.immunization_status LIKE '%MMR%' THEN 1 END) as mmr_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%Vitamin A%' THEN 1 END) as vitamin_a_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%FIC%' THEN 1 END) as fic_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%CIC%' THEN 1 END) as cic_count
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN cr.immunization_status LIKE '%MMR%' THEN 1 END) as mmr_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%Vitamin A%' THEN 1 END) as vitamin_a_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%FIC%' THEN 1 END) as fic_count,
                COUNT(CASE WHEN cr.immunization_status LIKE '%CIC%' THEN 1 END) as cic_count
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['immunization_coverage'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. Service Source Utilization
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                cr.service_source,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM child_record cr2 
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE cr2.measurement_date BETWEEN ? AND ? 
                    AND p2.age BETWEEN 1 AND 6 
                    AND a2.purok = ?
                    AND cr2.child_type = 'Child'
                ), 0), 2) as percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            AND a.purok = ?
            GROUP BY cr.service_source
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                cr.service_source,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM child_record cr2 
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE cr2.measurement_date BETWEEN ? AND ? 
                    AND p2.age BETWEEN 1 AND 6
                    AND cr2.child_type = 'Child'
                ), 0), 2) as percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'child_record'
            AND cr.child_type = 'Child'
            AND cr.measurement_date BETWEEN ? AND ?
            AND p.age BETWEEN 1 AND 6
            GROUP BY cr.service_source
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['service_utilization'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $insights;
}

// ===================== FAMILY PLANNING INSIGHTS =====================
function getFamilyPlanningInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. FP Method Uptake
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN fpr.uses_fp_method = 'Y' THEN 1 END) as using_fp,
                COUNT(CASE WHEN fpr.uses_fp_method = 'N' THEN 1 END) as not_using_fp,
                ROUND(COUNT(CASE WHEN fpr.uses_fp_method = 'Y' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as uptake_percentage
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'family_planning_record'
            AND r.created_by IS NOT NULL
            AND a.purok = ?
        ");
        $stmt->execute([$user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN fpr.uses_fp_method = 'Y' THEN 1 END) as using_fp,
                COUNT(CASE WHEN fpr.uses_fp_method = 'N' THEN 1 END) as not_using_fp,
                ROUND(COUNT(CASE WHEN fpr.uses_fp_method = 'Y' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as uptake_percentage
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'family_planning_record'
            AND r.created_by IS NOT NULL
        ");
        $stmt->execute();
    }
    $insights['fp_uptake'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. FP Method Distribution
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                fpr.fp_method,
                COUNT(*) as count
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'Y'
            AND fpr.fp_method IS NOT NULL
            AND fpr.fp_method != ''
            AND a.purok = ?
            GROUP BY fpr.fp_method
            ORDER BY count DESC
        ");
        $stmt->execute([$user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                fpr.fp_method,
                COUNT(*) as count
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'Y'
            AND fpr.fp_method IS NOT NULL
            AND fpr.fp_method != ''
            GROUP BY fpr.fp_method
            ORDER BY count DESC
        ");
        $stmt->execute();
    }
    $insights['fp_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Reasons for Non-Use
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                fpr.reason_not_using,
                COUNT(*) as count
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'N'
            AND fpr.reason_not_using IS NOT NULL
            AND fpr.reason_not_using != ''
            AND a.purok = ?
            GROUP BY fpr.reason_not_using
            ORDER BY count DESC
        ");
        $stmt->execute([$user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                fpr.reason_not_using,
                COUNT(*) as count
            FROM family_planning_record fpr
            JOIN records r ON fpr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type = 'family_planning_record'
            AND fpr.uses_fp_method = 'N'
            AND fpr.reason_not_using IS NOT NULL
            AND fpr.reason_not_using != ''
            GROUP BY fpr.reason_not_using
            ORDER BY count DESC
        ");
        $stmt->execute();
    }
    $insights['non_use_reasons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $insights;
}

// ===================== INFANT HEALTH INSIGHTS =====================
function getInfantHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. Birth Weight Distribution
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_infants,
                AVG(CAST(cr.weight AS DECIMAL(10,2))) as avg_birth_weight,
                COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 2.5 THEN 1 END) as low_birth_weight,
                ROUND(COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 2.5 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as lbw_percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%infant_record%'
            AND cr.child_type = 'Infant'
            AND cr.measurement_date BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_infants,
                AVG(CAST(cr.weight AS DECIMAL(10,2))) as avg_birth_weight,
                COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 2.5 THEN 1 END) as low_birth_weight,
                ROUND(COUNT(CASE WHEN CAST(cr.weight AS DECIMAL(10,2)) < 2.5 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as lbw_percentage
            FROM child_record cr
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%infant_record%'
            AND cr.child_type = 'Infant'
            AND cr.measurement_date BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['birth_weight'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Breastfeeding Practices
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                ir.exclusive_breastfeeding,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM infant_record ir2 
                    JOIN child_record cr2 ON ir2.child_record_id = cr2.child_record_id
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE cr2.measurement_date BETWEEN ? AND ? 
                    AND a2.purok = ?
                ), 0), 2) as percentage
            FROM infant_record ir
            JOIN child_record cr ON ir.child_record_id = cr.child_record_id
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%infant_record%'
            AND cr.measurement_date BETWEEN ? AND ?
            AND a.purok = ?
            GROUP BY ir.exclusive_breastfeeding
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                ir.exclusive_breastfeeding,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM infant_record ir2 
                    JOIN child_record cr2 ON ir2.child_record_id = cr2.child_record_id
                    JOIN records r2 ON cr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE cr2.measurement_date BETWEEN ? AND ?
                ), 0), 2) as percentage
            FROM infant_record ir
            JOIN child_record cr ON ir.child_record_id = cr.child_record_id
            JOIN records r ON cr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%infant_record%'
            AND cr.measurement_date BETWEEN ? AND ?
            GROUP BY ir.exclusive_breastfeeding
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['breastfeeding'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($role_id == 2 && $user_purok) {
    $stmt = $pdo->prepare("
        SELECT 
            i.immunization_type,
            COUNT(*) as count
        FROM child_immunization ci
        JOIN immunization i ON ci.immunization_id = i.immunization_id
        JOIN child_record cr ON ci.child_record_id = cr.child_record_id
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        JOIN address a ON p.address_id = a.address_id
        WHERE r.record_type LIKE '%infant_record%'
        AND cr.measurement_date BETWEEN ? AND ?
        AND i.immunization_type IS NOT NULL
        AND a.purok = ?
        GROUP BY i.immunization_type
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to, $user_purok]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            i.immunization_type,
            COUNT(*) as count
        FROM child_immunization ci
        JOIN immunization i ON ci.immunization_id = i.immunization_id
        JOIN child_record cr ON ci.child_record_id = cr.child_record_id
        JOIN records r ON cr.records_id = r.records_id
        JOIN person p ON r.person_id = p.person_id
        WHERE r.record_type LIKE '%infant_record%'
        AND cr.measurement_date BETWEEN ? AND ?
        AND i.immunization_type IS NOT NULL
        GROUP BY i.immunization_type
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
}
$insights['infant_vaccination'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

return $insights;

}

// ===================== SENIOR MEDICATION INSIGHTS =====================
function getSeniorMedicationInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. Hypertension Monitoring
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_readings,
                COUNT(DISTINCT r.person_id) as unique_seniors,
                AVG(CASE WHEN sr.bp_reading REGEXP '^[0-9]+/[0-9]+' 
                    THEN CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) END) as avg_systolic,
                COUNT(CASE WHEN sr.bp_reading REGEXP '^[0-9]+/[0-9]+' 
                    AND CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) > 140 THEN 1 END) as hypertensive_readings
            FROM senior_record sr
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_readings,
                COUNT(DISTINCT r.person_id) as unique_seniors,
                AVG(CASE WHEN sr.bp_reading REGEXP '^[0-9]+/[0-9]+' 
                    THEN CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) END) as avg_systolic,
                COUNT(CASE WHEN sr.bp_reading REGEXP '^[0-9]+/[0-9]+' 
                    AND CAST(SUBSTRING_INDEX(sr.bp_reading, '/', 1) AS UNSIGNED) > 140 THEN 1 END) as hypertensive_readings
            FROM senior_record sr
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['hypertension_monitoring'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Medication Distribution
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                m.medication_name,
                COUNT(*) as prescription_count,
                COUNT(DISTINCT sm.senior_record_id) as unique_prescriptions
            FROM medication m
            JOIN senior_medication sm ON m.medication_id = sm.medication_id
            JOIN senior_record sr ON sm.senior_record_id = sr.senior_record_id
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
            AND a.purok = ?
            GROUP BY m.medication_name
            ORDER BY prescription_count DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                m.medication_name,
                COUNT(*) as prescription_count,
                COUNT(DISTINCT sm.senior_record_id) as unique_prescriptions
            FROM medication m
            JOIN senior_medication sm ON m.medication_id = sm.medication_id
            JOIN senior_record sr ON sm.senior_record_id = sr.senior_record_id
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
            GROUP BY m.medication_name
            ORDER BY prescription_count DESC
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['medication_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Senior Care Activity
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(sr.bp_date_taken, '%Y-%m') as month,
                COUNT(*) as visits,
                COUNT(DISTINCT r.person_id) as unique_seniors
            FROM senior_record sr
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
            AND a.purok = ?
            GROUP BY DATE_FORMAT(sr.bp_date_taken, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(sr.bp_date_taken, '%Y-%m') as month,
                COUNT(*) as visits,
                COUNT(DISTINCT r.person_id) as unique_seniors
            FROM senior_record sr
            JOIN records r ON sr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%medication%'
            AND sr.bp_date_taken BETWEEN ? AND ?
            AND p.age >= 60
            GROUP BY DATE_FORMAT(sr.bp_date_taken, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['senior_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $insights;
}

// ===================== POSTNATAL INSIGHTS =====================
function getPostnatalInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. Delivery Statistics
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_deliveries,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Hospital%' THEN 1 END) as hospital_births,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Center%' THEN 1 END) as center_births,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Bahay%' THEN 1 END) as home_births,
                COUNT(CASE WHEN pn.attendant LIKE '%Doctor%' THEN 1 END) as doctor_attended,
                COUNT(CASE WHEN pn.attendant LIKE '%Midwife%' THEN 1 END) as midwife_attended
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_deliveries,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Hospital%' THEN 1 END) as hospital_births,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Center%' THEN 1 END) as center_births,
                COUNT(CASE WHEN pn.delivery_location LIKE '%Bahay%' THEN 1 END) as home_births,
                COUNT(CASE WHEN pn.attendant LIKE '%Doctor%' THEN 1 END) as doctor_attended,
                COUNT(CASE WHEN pn.attendant LIKE '%Midwife%' THEN 1 END) as midwife_attended
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['delivery_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Postnatal Risk Analysis
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                pn.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM postnatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE pn2.date_delivered BETWEEN ? AND ? 
                    AND a2.purok = ?
                ), 0), 2) as percentage
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            AND pn.risk_observed IS NOT NULL
            AND pn.risk_observed != ''
            AND a.purok = ?
            GROUP BY pn.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                pn.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM postnatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE pn2.date_delivered BETWEEN ? AND ?
                ), 0), 2) as percentage
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            AND pn.risk_observed IS NOT NULL
            AND pn.risk_observed != ''
            GROUP BY pn.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['postnatal_risk'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Postnatal Follow-up
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_mothers,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 24 Hours%' THEN 1 END) as checkup_24h,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 72 Hours%' THEN 1 END) as checkup_72h,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 7 Days%' THEN 1 END) as checkup_7days,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%No Checkup%' THEN 1 END) as no_checkup
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_mothers,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 24 Hours%' THEN 1 END) as checkup_24h,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 72 Hours%' THEN 1 END) as checkup_72h,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%First 7 Days%' THEN 1 END) as checkup_7days,
                COUNT(CASE WHEN pn.postnatal_checkups LIKE '%No Checkup%' THEN 1 END) as no_checkup
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['postnatal_followup'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. Family Planning Intent
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                pn.family_planning_intent,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM postnatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE pn2.date_delivered BETWEEN ? AND ? 
                    AND a2.purok = ?
                ), 0), 2) as percentage
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            AND a.purok = ?
            GROUP BY pn.family_planning_intent
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                pn.family_planning_intent,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM postnatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE pn2.date_delivered BETWEEN ? AND ?
                ), 0), 2) as percentage
            FROM postnatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE '%postnatal%'
            AND pn.date_delivered BETWEEN ? AND ?
            GROUP BY pn.family_planning_intent
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['fp_intent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $insights;
}

// ===================== PRENATAL INSIGHTS =====================
function getPrenatalInsights($pdo, $user_purok, $role_id, $date_from, $date_to) {
    $insights = [];
    
    // 1. Prenatal Coverage
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_pregnant,
                COUNT(CASE WHEN pn.checkup_date LIKE '%First Trimester%' THEN 1 END) as first_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%Second Trimester%' THEN 1 END) as second_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%Third Trimester%' THEN 1 END) as third_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%None%' THEN 1 END) as no_checkup,
                COUNT(CASE WHEN pn.birth_plan = 'Y' THEN 1 END) as with_birth_plan
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE '%prenatal%'
            AND DATE(pr.created_at) BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_pregnant,
                COUNT(CASE WHEN pn.checkup_date LIKE '%First Trimester%' THEN 1 END) as first_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%Second Trimester%' THEN 1 END) as second_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%Third Trimester%' THEN 1 END) as third_trimester,
                COUNT(CASE WHEN pn.checkup_date LIKE '%None%' THEN 1 END) as no_checkup,
                COUNT(CASE WHEN pn.birth_plan = 'Y' THEN 1 END) as with_birth_plan
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['prenatal_coverage'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Maternal Risk Distribution
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                pn.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM prenatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    JOIN address a2 ON p2.address_id = a2.address_id
                    WHERE DATE(pr2.created_at) BETWEEN ? AND ? 
                    AND a2.purok = ?
                ), 0), 2) as percentage
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
            AND pn.risk_observed IS NOT NULL
            AND pn.risk_observed != ''
            AND a.purok = ?
            GROUP BY pn.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $user_purok, $date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                pn.risk_observed,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM prenatal pn2 
                    JOIN pregnancy_record pr2 ON pn2.pregnancy_record_id = pr2.pregnancy_record_id
                    JOIN records r2 ON pr2.records_id = r2.records_id 
                    JOIN person p2 ON r2.person_id = p2.person_id 
                    WHERE DATE(pr2.created_at) BETWEEN ? AND ? 
                ), 0), 2) as percentage
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
            AND pn.risk_observed IS NOT NULL
            AND pn.risk_observed != ''
            GROUP BY pn.risk_observed
            ORDER BY count DESC
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
    }
    $insights['maternal_risk'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Pregnancy Demographics
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                AVG(pn.preg_count) as avg_pregnancies,
                AVG(pn.child_alive) as avg_living_children,
                COUNT(CASE WHEN pn.preg_count = 1 THEN 1 END) as first_pregnancy,
                COUNT(CASE WHEN pn.preg_count > 4 THEN 1 END) as high_parity
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                AVG(pn.preg_count) as avg_pregnancies,
                AVG(pn.child_alive) as avg_living_children,
                COUNT(CASE WHEN pn.preg_count = 1 THEN 1 END) as first_pregnancy,
                COUNT(CASE WHEN pn.preg_count > 4 THEN 1 END) as high_parity
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['pregnancy_demographics'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. PhilHealth Coverage
    if ($role_id == 2 && $user_purok) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_pregnant,
                COUNT(CASE WHEN p.philhealth_number IS NOT NULL AND p.philhealth_number != '' THEN 1 END) as with_philhealth,
                ROUND(COUNT(CASE WHEN p.philhealth_number IS NOT NULL AND p.philhealth_number != '' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as philhealth_percentage
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            JOIN address a ON p.address_id = a.address_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
            AND a.purok = ?
        ");
        $stmt->execute([$date_from, $date_to, $user_purok]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_pregnant,
                COUNT(CASE WHEN p.philhealth_number IS NOT NULL AND p.philhealth_number != '' THEN 1 END) as with_philhealth,
                ROUND(COUNT(CASE WHEN p.philhealth_number IS NOT NULL AND p.philhealth_number != '' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as philhealth_percentage
            FROM prenatal pn
            JOIN pregnancy_record pr ON pn.pregnancy_record_id = pr.pregnancy_record_id
            JOIN records r ON pr.records_id = r.records_id
            JOIN person p ON r.person_id = p.person_id
            WHERE r.record_type LIKE 'pregnancy_record.prenatal'
            AND DATE(pr.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
    }
    $insights['philhealth_coverage'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $insights;
}

// Fetch all insights
try {
    $child_health = getChildHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
    $family_planning = getFamilyPlanningInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
    $infant_health = getInfantHealthInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
    $senior_medication = getSeniorMedicationInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
    $postnatal = getPostnatalInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
    $prenatal = getPrenatalInsights($pdo, $user_purok, $role_id, $date_from, $date_to);
} catch (Exception $e) {
    error_log("Data Insights Error: " . $e->getMessage());
    $error_message = "Error loading data insights: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Data Analytics</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Poppins', sans-serif;
            color: #1a202c;
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
        .navbar, .navbar * {
            line-height: 1.2 !important;
        }
        .navbar .nav-link,
        .navbar .dropdown-toggle,
        .search-icon {
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
        .sidebar.open {
            transform: translateX(250px);
        }
        .content {
            padding: 20px;
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: rgba(43, 108, 176, 0.8);
            color: #fff;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .insight-number {
            font-size: 2.5rem;
            font-weight: 700;
            display: block;
        }
        .insight-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .risk-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
        }
        .risk-high {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .date-filter {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
            .navbar-brand {
                padding-left: 55px;
            }
        }
        @media (min-width: 769px) {
            .menu-toggle { display: none; }
            .sidebar { left: 0; transform: translateX(0); }
            .content { margin-left: 250px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line"></i> Health Data Analytics</h2>
                    <?php if ($role_id == 2): ?>
                        <span class="badge badge-info">Purok: <?php echo htmlspecialchars($user_purok); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Date Filter -->
                <div class="date-filter">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label for="date_from">From:</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_to">To:</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php else: ?>

                <!-- Child Health Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-child"></i> Child Health (Ages 1-6)</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $child_health['growth_monitoring']['total_children'] ?? 0; ?></span>
                                    <span class="insight-label">Total Children</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $child_health['growth_monitoring']['underweight_count'] ?? 0; ?></span>
                                    <span class="insight-label">Underweight (< 10kg)</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $child_health['growth_monitoring']['stunted_count'] ?? 0; ?></span>
                                    <span class="insight-label">Stunted (< 80cm)</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo round($child_health['growth_monitoring']['avg_weight'] ?? 0, 1); ?>kg</span>
                                    <span class="insight-label">Average Weight</span>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-4">Disease Risk Distribution</h5>
                        <?php if (empty($child_health['disease_risk'])): ?>
                            <p class="text-muted">No disease risk recorded in this period.</p>
                        <?php else: ?>
                            <?php foreach ($child_health['disease_risk'] as $risk): ?>
                                <div class="risk-item <?php echo ($risk['percentage'] ?? 0) > 20 ? 'risk-high' : ''; ?>">
                                    <strong><?php echo htmlspecialchars($risk['risk_observed']); ?></strong>: 
                                    <?php echo $risk['count']; ?> cases (<?php echo $risk['percentage'] ?? 0; ?>%)
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Immunization Coverage</h5>
                                <div class="chart-container">
                                    <canvas id="immunizationChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Service Source Utilization</h5>
                                <div class="chart-container">
                                    <canvas id="serviceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Family Planning Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-users"></i> Family Planning</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $family_planning['fp_uptake']['total_records'] ?? 0; ?></span>
                                    <span class="insight-label">Total Records</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $family_planning['fp_uptake']['using_fp'] ?? 0; ?></span>
                                    <span class="insight-label">Using FP Methods</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $family_planning['fp_uptake']['uptake_percentage'] ?? 0; ?>%</span>
                                    <span class="insight-label">FP Uptake Rate</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $family_planning['fp_uptake']['not_using_fp'] ?? 0; ?></span>
                                    <span class="insight-label">Not Using FP</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>FP Method Distribution</h5>
                                <?php if (empty($family_planning['fp_methods'])): ?>
                                    <p class="text-muted">No FP methods recorded.</p>
                                <?php else: ?>
                                    <?php foreach ($family_planning['fp_methods'] as $method): ?>
                                        <div class="risk-item">
                                            <strong><?php echo htmlspecialchars($method['fp_method']); ?></strong>: <?php echo $method['count']; ?> users
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5>Reasons for Non-Use</h5>
                                <?php if (empty($family_planning['non_use_reasons'])): ?>
                                    <p class="text-muted">No non-use reasons recorded.</p>
                                <?php else: ?>
                                    <?php foreach ($family_planning['non_use_reasons'] as $reason): ?>
                                        <div class="risk-item">
                                            <strong><?php echo htmlspecialchars($reason['reason_not_using']); ?></strong>: <?php echo $reason['count']; ?> cases
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Infant Health Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-baby"></i> Infant Health (0-12 months)</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $infant_health['birth_weight']['total_infants'] ?? 0; ?></span>
                                    <span class="insight-label">Total Infants</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo round($infant_health['birth_weight']['avg_birth_weight'] ?? 0, 2); ?>kg</span>
                                    <span class="insight-label">Avg Birth Weight</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $infant_health['birth_weight']['low_birth_weight'] ?? 0; ?></span>
                                    <span class="insight-label">Low Birth Weight</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $infant_health['birth_weight']['lbw_percentage'] ?? 0; ?>%</span>
                                    <span class="insight-label">LBW Percentage</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Breastfeeding Practices</h5>
                                <div class="chart-container">
                                    <canvas id="breastfeedingChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Vaccination Coverage</h5>
                                <?php if (empty($infant_health['infant_vaccination'])): ?>
                                    <p class="text-muted">No vaccination data recorded.</p>
                                <?php else: ?>
                                    <?php foreach ($infant_health['infant_vaccination'] as $vaccine): ?>
                                        <div class="risk-item">
                                            <strong><?php echo htmlspecialchars($vaccine['immunization_type']); ?></strong>: <?php echo $vaccine['count']; ?> doses
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Senior Medication Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-pills"></i> Senior Care (60+ years)</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $senior_medication['hypertension_monitoring']['unique_seniors'] ?? 0; ?></span>
                                    <span class="insight-label">Unique Seniors</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $senior_medication['hypertension_monitoring']['total_readings'] ?? 0; ?></span>
                                    <span class="insight-label">BP Readings</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo round($senior_medication['hypertension_monitoring']['avg_systolic'] ?? 0); ?></span>
                                    <span class="insight-label">Avg Systolic BP</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $senior_medication['hypertension_monitoring']['hypertensive_readings'] ?? 0; ?></span>
                                    <span class="insight-label">High BP Readings</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Top Medications</h5>
                                <?php if (empty($senior_medication['medication_distribution'])): ?>
                                    <p class="text-muted">No medication data recorded.</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($senior_medication['medication_distribution'], 0, 8) as $med): ?>
                                        <div class="risk-item">
                                            <strong><?php echo htmlspecialchars($med['medication_name']); ?></strong>: <?php echo $med['prescription_count']; ?> prescriptions
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5>Monthly Activity</h5>
                                <div class="chart-container">
                                    <canvas id="seniorActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Postnatal Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-baby-carriage"></i> Postnatal Care</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $postnatal['delivery_stats']['total_deliveries'] ?? 0; ?></span>
                                    <span class="insight-label">Total Deliveries</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $postnatal['delivery_stats']['hospital_births'] ?? 0; ?></span>
                                    <span class="insight-label">Hospital Births</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $postnatal['delivery_stats']['home_births'] ?? 0; ?></span>
                                    <span class="insight-label">Home Births</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $postnatal['delivery_stats']['doctor_attended'] ?? 0; ?></span>
                                    <span class="insight-label">Doctor Attended</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Postnatal risk</h5>
                                <?php if (empty($postnatal['postnatal_risk'])): ?>
                                    <p class="text-muted">No postnatal risk recorded.</p>
                                <?php else: ?>
                                    <?php foreach ($postnatal['postnatal_risk'] as $risk): ?>
                                        <div class="risk-item <?php echo ($risk['percentage'] ?? 0) > 15 ? 'risk-high' : ''; ?>">
                                            <strong><?php echo htmlspecialchars($risk['risk_observed']); ?></strong>: 
                                            <?php echo $risk['count']; ?> cases (<?php echo $risk['percentage'] ?? 0; ?>%)
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5>Follow-up Coverage</h5>
                                <div class="chart-container">
                                    <canvas id="postnatalFollowupChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prenatal Insights -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-pregnant"></i> Prenatal Care</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $prenatal['prenatal_coverage']['total_pregnant'] ?? 0; ?></span>
                                    <span class="insight-label">Total Pregnant</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $prenatal['prenatal_coverage']['with_birth_plan'] ?? 0; ?></span>
                                    <span class="insight-label">With Birth Plan</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo round($prenatal['pregnancy_demographics']['avg_pregnancies'] ?? 0, 1); ?></span>
                                    <span class="insight-label">Avg Pregnancies</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="insight-card">
                                    <span class="insight-number"><?php echo $prenatal['philhealth_coverage']['philhealth_percentage'] ?? 0; ?>%</span>
                                    <span class="insight-label">PhilHealth Coverage</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Prenatal Checkup Coverage</h5>
                                <div class="chart-container">
                                    <canvas id="prenatalCheckupChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Maternal risk</h5>
                                <?php if (empty($prenatal['maternal_risk'])): ?>
                                    <p class="text-muted">No maternal risk recorded.</p>
                                <?php else: ?>
                                    <?php foreach ($prenatal['maternal_risk'] as $risk): ?>
                                        <div class="risk-item <?php echo ($risk['percentage'] ?? 0) > 10 ? 'risk-high' : ''; ?>">
                                            <strong><?php echo htmlspecialchars($risk['risk_observed']); ?></strong>: 
                                            <?php echo $risk['count']; ?> cases (<?php echo $risk['percentage'] ?? 0; ?>%)
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
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
        // Immunization Chart
        const immunizationData = {
            labels: ['MMR', 'Vitamin A', 'FIC', 'CIC'],
            datasets: [{
                data: [
                    <?php echo $child_health['immunization_coverage']['mmr_count'] ?? 0; ?>,
                    <?php echo $child_health['immunization_coverage']['vitamin_a_count'] ?? 0; ?>,
                    <?php echo $child_health['immunization_coverage']['fic_count'] ?? 0; ?>,
                    <?php echo $child_health['immunization_coverage']['cic_count'] ?? 0; ?>
                ],
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
            }]
        };
        new Chart(document.getElementById('immunizationChart'), {
            type: 'doughnut',
            data: immunizationData,
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Service Utilization Chart
        <?php if (!empty($child_health['service_utilization'])): ?>
        const serviceData = {
            labels: [<?php echo "'" . implode("','", array_column($child_health['service_utilization'], 'service_source')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($child_health['service_utilization'], 'count')); ?>],
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
            }]
        };
        new Chart(document.getElementById('serviceChart'), {
            type: 'pie',
            data: serviceData,
            options: { responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        // Breastfeeding Chart
        <?php if (!empty($infant_health['breastfeeding'])): ?>
        const breastfeedingData = {
            labels: [<?php echo "'" . implode("','", array_column($infant_health['breastfeeding'], 'exclusive_breastfeeding')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($infant_health['breastfeeding'], 'count')); ?>],
                backgroundColor: ['#4BC0C0', '#FF6384', '#FFCE56']
            }]
        };
        new Chart(document.getElementById('breastfeedingChart'), {
            type: 'doughnut',
            data: breastfeedingData,
            options: { responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        // Senior Activity Chart
        <?php if (!empty($senior_medication['senior_activity'])): ?>
        const seniorActivityData = {
            labels: [<?php echo "'" . implode("','", array_column($senior_medication['senior_activity'], 'month')) . "'"; ?>],
            datasets: [{
                label: 'Visits',
                data: [<?php echo implode(',', array_column($senior_medication['senior_activity'], 'visits')); ?>],
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true
            }]
        };
        new Chart(document.getElementById('seniorActivityChart'), {
            type: 'line',
            data: seniorActivityData,
            options: { responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        // Postnatal Follow-up Chart
        const postnatalFollowupData = {
            labels: ['24 Hours', '72 Hours', '7 Days', 'No Checkup'],
            datasets: [{
                data: [
                    <?php echo $postnatal['postnatal_followup']['checkup_24h'] ?? 0; ?>,
                    <?php echo $postnatal['postnatal_followup']['checkup_72h'] ?? 0; ?>,
                    <?php echo $postnatal['postnatal_followup']['checkup_7days'] ?? 0; ?>,
                    <?php echo $postnatal['postnatal_followup']['no_checkup'] ?? 0; ?>
                ],
                backgroundColor: ['#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384']
            }]
        };
        new Chart(document.getElementById('postnatalFollowupChart'), {
            type: 'doughnut',
            data: postnatalFollowupData,
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Prenatal Checkup Chart
        const prenatalCheckupData = {
            labels: ['1st Trimester', '2nd Trimester', '3rd Trimester', 'No Checkup'],
            datasets: [{
                data: [
                    <?php echo $prenatal['prenatal_coverage']['first_trimester'] ?? 0; ?>,
                    <?php echo $prenatal['prenatal_coverage']['second_trimester'] ?? 0; ?>,
                    <?php echo $prenatal['prenatal_coverage']['third_trimester'] ?? 0; ?>,
                    <?php echo $prenatal['prenatal_coverage']['no_checkup'] ?? 0; ?>
                ],
                backgroundColor: ['#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384']
            }]
        };
        new Chart(document.getElementById('prenatalCheckupChart'), {
            type: 'bar',
            data: prenatalCheckupData,
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>