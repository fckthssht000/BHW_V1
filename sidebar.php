<?php
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$role_id = $user['role_id'];

// Fetch user's privileges
$stmt = $pdo->prepare("SELECT p.privilege_id, p.privilege_name 
                       FROM role_privilege rp 
                       JOIN privilege p ON rp.privilege_id = p.privilege_id 
                       WHERE rp.role_id = ?");
$stmt->execute([$role_id]);
$privileges = [];
while ($row = $stmt->fetch()) {
    $privileges[$row['privilege_id']] = $row['privilege_name'];
}
?>

<div class="col-md-3 sidebar">
    <div class="list-group">
        <?php if (isset($privileges[9]) && $privileges[9] == 'access_dashboard'): ?>
            <a href="dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
        <?php endif; ?>
        <?php if (isset($privileges[13]) && $privileges[13] == 'access_register'): ?>
            <a href="register_member.php" class="list-group-item list-group-item-action">Register Family Member</a>
        <?php endif; ?>
        <?php if (in_array('access_household', $privileges) || in_array('access_infant', $privileges) || 
                  in_array('access_child_health', $privileges) || in_array('access_pregnant', $privileges) || 
                  in_array('access_postnatal', $privileges) || in_array('access_family_planning', $privileges) || 
                  in_array('access_patient_medication', $privileges)): ?>
            <div class="accordion-item">
                <div class="accordion-header">Health Forms</div>
                <div class="accordion-content">
                    <?php if (in_array('access_household', $privileges)): ?>
                        <a href="household_form.php">Household Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_infant', $privileges)): ?>
                        <a href="infant_form.php">Infant Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_child_health', $privileges)): ?>
                        <a href="child_health_form.php">Child Health Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_pregnant', $privileges)): ?>
                        <a href="pregnant_form.php">Pregnant Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_postnatal', $privileges)): ?>
                        <a href="postnatal_form.php">Postnatal Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_family_planning', $privileges)): ?>
                        <a href="family_planning_form.php">Family Planning Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_patient_medication', $privileges)): ?>
                        <a href="patient_medication_form.php">Senior Medication Form</a>
                    <?php endif; ?>
                    <?php if (in_array('access_custom_forms', $privileges)): ?>
                        <a href="custom_form_fill.php">Custom Forms</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($privileges[16]) && $privileges[16] == 'access_records'): ?>
            <div class="accordion-item">
                <div class="accordion-header">Health Form Records</div>
                <div class="accordion-content">
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="household_records.php">Household Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="infant_records.php">Infant Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="child_health_records.php">Child Health Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="pregnant_records.php">Pregnant Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="postnatal_records.php">Postnatal Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="fp_records.php">Family Planning Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_records', $privileges)): ?>
                        <a href="patient_medication_records.php">Senior Medication Records</a>
                    <?php endif; ?>
                    <?php if (in_array('access_custom_records', $privileges)): ?>
                        <a href="custom_form_submissions.php">Custom Form Submissions</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($privileges[17]) && $privileges[17] == 'access_reports'): ?>
            <div class="accordion-item">
                <div class="accordion-header">Reports</div>
                <div class="accordion-content">
                    <a href="environmental_health_sanitation.php">Environmental Health Sanitation Barangay Profile</a>
                    <a href="barangay_profile.php">Barangay Profile</a>
                    <a href="masterlist_hypertension_diabetes.php">Masterlist Hypertension and Diabetes</a>
                    <a href="community_level_eopt_plus.php">Community Level e-OPT Plus Tool</a>
                    <a href="data_insight_record.php">Data Insight</a>
                    <a href="data_analytics_record.php">Data Analytics</a>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($privileges[19]) && $privileges[19] == 'access_custom_builder'): ?>
            <a href="custom_form_builder.php" class="list-group-item list-group-item-action">Form Builder</a>
        <?php endif; ?>
        <!-- Keys Monitor Link - Only for BHW Head (1), BHW (2), and Super Admin (4) -->
        <?php if (in_array($role_id, [1, 2, 4])): ?>
            <a href="keys_monitor.php" class="list-group-item list-group-item-action">Keys Monitor</a>
        <?php endif; ?>
        <?php if (isset($privileges[12]) && $privileges[12] == 'access_notice'): ?>
            <a href="send_notices.php" class="list-group-item list-group-item-action">Send Notices</a>
        <?php endif; ?>
        <?php if (isset($privileges[14]) && $privileges[14] == 'access_privileges'): ?>
            <a href="manage_privileges.php" class="list-group-item list-group-item-action">Manage Privileges</a>
        <?php endif; ?>
        <?php if (isset($privileges[15]) && $privileges[15] == 'access_puroks'): ?>
            <a href="manage_puroks.php" class="list-group-item list-group-item-action">Manage Puroks</a>
        <?php endif; ?>
        <?php if (isset($privileges[11]) && $privileges[11] == 'access_map'): ?>
            <a href="health_map.php" class="list-group-item list-group-item-action">Health Map</a>
        <?php endif; ?>
        <?php if (isset($privileges[8]) && $privileges[8] == 'access_manage_account'): ?>
            <a href="superadmin_manage_accounts.php" class="list-group-item list-group-item-action">Manage Accounts</a>
        <?php endif; ?>
    </div>
</div>

<style>
            .list-group-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 12px;
            padding: 15px;
            color: #2d3748;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .list-group-item:hover {
            background: #f7fafc;
            transform: translateX(5px);
        }
        .accordion-item {
            margin-bottom: 5px;
        }
        .accordion-header {
            background: #edf2f7;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            color: #2b6cb0;
            font-weight: 500;
        }
        .accordion-content {
            display: none;
            padding: 10px 20px 0;
        }
        .accordion-content.active { display: block; }
        .accordion-content a {
            display: block;
            padding: 8px 0;
            color: #2d3748;
            text-decoration: none;
        }
        .accordion-content a:hover { color: #2b6cb0; }
</style>