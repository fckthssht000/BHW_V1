<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$role_id = $user['role_id'];

if (!in_array($role_id, [1, 2])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_forms'])) {
    $household_users = $_POST['household_users'] ?? [];
    $forms = isset($_POST['all_forms']) ? ['household', 'infant', 'child_health', 'pregnant', 'postnatal', 'family_planning', 'patient_medication'] : $_POST['selected_forms'] ?? [];
    $schedule = $_POST['schedule'];

    foreach ($household_users as $recipient_id) {
        $activity_msg = "SENT_FORM: to_user_id:$recipient_id forms:" . implode(',', $forms) . " schedule:$schedule";
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $activity_msg]);

        // Grant privilege to access forms
        foreach ($forms as $form) {
            $privilege_stmt = $pdo->prepare("INSERT INTO user_privileges (user_id, privilege) VALUES (?, ?) ON DUPLICATE KEY UPDATE privilege = privilege");
            $privilege_stmt->execute([$recipient_id, "access_$form"]);
        }
    }

    // Placeholder for actual notification logic
    // Implement based on your notification system
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Send Health Forms</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #007bff; }
        .navbar-brand, .nav-link { color: white !important; }
        .card { margin-top: 20px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <h3>Send Health Forms</h3>
                        <form action="send_health_forms.php" method="POST">
                            <div class="form-group">
                                <label>Household Users (Head)</label>
                                <select class="form-control" name="household_users[]" multiple>
                                    <?php
                                    $stmt = $pdo->query("SELECT u.user_id, p.full_name FROM users u JOIN records r ON u.user_id = r.user_id JOIN person p ON r.person_id = p.person_id WHERE u.role_id = 3 AND p.relationship_type = 'Head'");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='{$row['user_id']}'>{$row['full_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Forms</label>
                                <div>
                                    <input type="checkbox" name="all_forms" value="1"> All Forms
                                    <div class="mt-2" id="selected_forms_group" style="display: none;">
                                        <input type="checkbox" name="selected_forms[]" value="household"> Household Form
                                        <input type="checkbox" name="selected_forms[]" value="infant"> Infant Form
                                        <input type="checkbox" name="selected_forms[]" value="child_health"> Child Health Form
                                        <input type="checkbox" name="selected_forms[]" value="pregnant"> Pregnant Form
                                        <input type="checkbox" name="selected_forms[]" value="postnatal"> Postnatal Form
                                        <input type="checkbox" name="selected_forms[]" value="family_planning"> Family Planning Form
                                        <input type="checkbox" name="selected_forms[]" value="patient_medication"> Patient Medication Form
                                    </div>
                                </div>
                                <script>
                                    document.querySelector('input[name="all_forms"]').addEventListener('change', function() {
                                        document.getElementById('selected_forms_group').style.display = this.checked ? 'none' : 'block';
                                    });
                                </script>
                            </div>
                            <div class="form-group">
                                <label>Schedule</label>
                                <select class="form-control" name="schedule">
                                    <option value="immediate">Immediate</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <button type="submit" name="send_forms" class="btn btn-primary">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>