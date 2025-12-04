<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch person_id, household details, and gender of the head from records and person table
$stmt = $pdo->prepare("SELECT r.person_id, p.household_number, a.purok, p.gender AS head_gender FROM records r JOIN person p ON r.person_id = p.person_id JOIN address a ON p.address_id = a.address_id WHERE r.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_person = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user_person === false) {
    die("Error: No person record found for this user.");
}
$user_person_id = $user_person['person_id'];
$household_number = $user_person['household_number'];
$user_purok = $user_person['purok'];
$head_gender = $user_person['head_gender'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and capitalize input
    $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
    $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
    $middle_name = filter_var($_POST['middle_name'], FILTER_SANITIZE_STRING);
    $relationship = filter_var($_POST['relationship'], FILTER_SANITIZE_STRING);
    $custom_relationship = isset($_POST['custom_relationship']) ? filter_var($_POST['custom_relationship'], FILTER_SANITIZE_STRING) : '';

    // Validate input: only letters, spaces, dots, commas, and hyphens allowed
    if (!preg_match('/^[a-zA-Z\s,.-]*$/', $last_name) || !preg_match('/^[a-zA-Z\s,.-]*$/', $first_name) || ($middle_name && !preg_match('/^[a-zA-Z\s,.-]*$/', $middle_name))) {
        die("Error: Name fields can only contain letters, spaces, dots, commas, and hyphens.");
    }
    if ($relationship === 'Others' && (!preg_match('/^[a-zA-Z\s,.-]*$/', $custom_relationship) || empty($custom_relationship))) {
        die("Error: Custom relationship can only contain letters, spaces, dots, commas, and hyphens and cannot be empty.");
    }

    // Capitalize first letter of each word
    $last_name = ucwords(strtolower($last_name));
    $first_name = ucwords(strtolower($first_name));
    $middle_name = $middle_name ? ucwords(strtolower($middle_name)) : '';
    $relationship = $relationship === 'Others' ? ucwords(strtolower($custom_relationship)) : ucwords(strtolower($relationship));

    $birthdate = $_POST['birthdate'];
    $phone = $_POST['phone'];

    // Calculate age from birthdate
    try {
        $birth_date = new DateTime($birthdate);
        $current_date = new DateTime();
        $age = $current_date->diff($birth_date)->y;
    } catch (Exception $e) {
        die("Error: Invalid birthdate format.");
    }

    // Determine gender and civil status based on relationship
    $gender = $_POST['gender'] ?? null;
    $civil_status = $_POST['civil_status'] ?? '';
    $custom_civil_status = $_POST['custom_civil_status'] ?? '';

    if ($civil_status === 'Others' && !empty($custom_civil_status)) {
        $civil_status = ucwords(strtolower($custom_civil_status));
    }

    if ($relationship === 'Spouse') {
        $gender = ($head_gender === 'M') ? 'F' : 'M';
        $civil_status = 'Married';
    } elseif (in_array($relationship, ['Son', 'Father', 'Brother', 'Grandson', 'Brother-in-Law', 'Son-in-Law', 'Nephew', 'Uncle'])) {
        $gender = 'M';
    } elseif (in_array($relationship, ['Daughter', 'Mother', 'Sister', 'Granddaughter', 'Sister-in-Law', 'Daughter-in-Law', 'Mother-in-Law', 'Niece', 'Aunt'])) {
        $gender = 'F';
    }

    // Use existing address_id for the user's purok
    $stmt = $pdo->prepare("SELECT address_id FROM address WHERE purok = ? AND barangay = 'Sta. Maria' AND municipality = 'Camiling' AND province = 'Tarlac' LIMIT 1");
    $stmt->execute([$user_purok]);
    $address_id = $stmt->fetchColumn();
    if (!$address_id) {
        die("Error: User's purok not found in address table.");
    }

    // Insert person with related_person_id set to user's person_id
    $stmt = $pdo->prepare("INSERT INTO person (household_number, full_name, relationship_type, gender, birthdate, age, civil_status, contact_number, address_id, related_person_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $full_name = "$last_name, $first_name $middle_name";
    $stmt->execute([$household_number, $full_name, $relationship, $gender, $birthdate, $age, $civil_status, $phone, $address_id, $user_person_id]);

    header("Location: dashboard.php?success=Family member registered");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>CommuniCare - Register Family Member</title>
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
            margin-left: 25px;
            margin-right: 0;
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
            margin-bottom: 8px;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 15px;
            color: #1a202c;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
            background-color: #f8fafc;
        }
        .form-control::placeholder {
            color: #a0aec0;
            font-style: italic;
        }
        .form-control[readonly], .form-control:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
            height: 46px;
        }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 12px 20px;
            font-size: 0.95rem;
            border-radius: 10px;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
            transform: translateY(-2px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        input[type="date"] {
            position: relative;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            cursor: pointer;
            padding: 10px;
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
                padding: 15px;
            }
            .content.with-sidebar {
                margin-left: 0;
                padding-left: 15px;
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
            .card {
                margin-bottom: 15px;
                margin-left: 15px;
                margin-right: 15px;
            }
            .navbar-brand {
                padding-left: 55px;
            }
        }
        @media (min-width: 769px) {
            .menu-toggle { display: none; }
            .sidebar {
                left: 0;
                transform: translateX(0);
            }
            .content {
                margin-left: 250px;
            }
            .content.with-sidebar {
                margin-left: 250px;
            }
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
                    <div class="card-header">Register Family Member</div>
                    <div class="card-body p-4">
                        <form action="register_member.php" method="POST" onsubmit="return validateForm()">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required pattern="[A-Za-z\s,.-]*" title="Only letters, spaces, dots, commas, and hyphens are allowed" placeholder="Enter last name">
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required pattern="[A-Za-z\s,.-]*" title="Only letters, spaces, dots, commas, and hyphens are allowed" placeholder="Enter first name">
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Initial</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" pattern="[A-Za-z\s,.-]*" title="Only letters, spaces, dots, commas, and hyphens are allowed" placeholder="Enter middle initial">
                            </div>
                            <div class="form-group">
                                <label for="relationship">Relationship to Head</label>
                                <select class="form-control" id="relationship" name="relationship" required onchange="updateGenderAndStatus()">
                                    <option value="Spouse">Spouse (Asawa)</option>
                                    <option value="Son">Son (Anak na lalaki)</option>
                                    <option value="Daughter">Daughter (Anak na babae)</option>
                                    <option value="Mother">Mother (Nanay)</option>
                                    <option value="Father">Father (Tatay)</option>
                                    <option value="Brother">Brother (Kapatid na lalaki)</option>
                                    <option value="Sister">Sister (Kapatid na babae)</option>
                                    <option value="Mother-in-Law">Mother-in-Law (Biyenan)</option>
                                    <option value="Father-in-Law">Father-in-Law (Biyenan)</option>
                                    <option value="Sister-in-Law">Sister-in-Law (Hipag)</option>
                                    <option value="Brother-in-Law">Brother-in-Law (Bayaw)</option>
                                    <option value="Daughter-in-Law">Daughter-in-Law (Manugang na babae)</option>
                                    <option value="Son-in-Law">Son-in-Law (Manugang na lalaki)</option>
                                    <option value="Grandson">Grandson (Apo na lalaki)</option>
                                    <option value="Granddaughter">Granddaughter (Apo na babae)</option>
                                    <option value="Others">Others</option>
                                    <option value="None">None (Wala)</option>
                                </select>
                            </div>
                            <div class="form-group" id="custom_relationship_group" style="display: none;">
                                <label for="custom_relationship">Custom Relationship</label>
                                <input type="text" class="form-control" id="custom_relationship" name="custom_relationship" pattern="[A-Za-z\s,.-]*" title="Only letters, spaces, dots, commas, and hyphens are allowed" placeholder="Enter custom relationship">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="birthdate">Birthdate</label>
                                <input type="date" class="form-control" id="birthdate" name="birthdate" required max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="civil_status">Civil Status</label>
                                <select class="form-control" id="civil_status" name="civil_status" required onchange="updateCivilStatus()">
                                    <option value="">Select Civil Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>

                            <div class="form-group" id="custom_civil_status_group" style="display:none;">
                                <label for="custom_civil_status">Custom Civil Status</label>
                                <input type="text" class="form-control" id="custom_civil_status" name="custom_civil_status"
                                    pattern="[A-Za-z\s,.-]*"
                                    title="Only letters, spaces, dots, commas, and hyphens are allowed"
                                    placeholder="Enter custom civil status">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="+63 912 345 6789">
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/inputmask/5.0.6/jquery.inputmask.min.js"></script>
    <script>
        // Initialize Inputmask for phone number
        $(document).ready(function() {
            $('#phone').inputmask('+63 999 999 9999', {
                placeholder: "+63 ___ ___ ____",
                clearMaskOnLostFocus: false
            });

            // Initialize gender and civil status on page load
            updateGenderAndStatus();
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

        function updateGenderAndStatus() {
            const relationship = document.getElementById('relationship').value;
            const genderSelect = document.getElementById('gender');
            const civilStatusSelect = document.getElementById('civil_status');
            const customRelationshipGroup = document.getElementById('custom_relationship_group');
            const headGender = '<?php echo $head_gender; ?>';

            // Show/hide custom relationship input
            if (relationship === 'Others') {
                customRelationshipGroup.style.display = 'block';
                document.getElementById('custom_relationship').required = true;
            } else {
                customRelationshipGroup.style.display = 'none';
                document.getElementById('custom_relationship').required = false;
            }

            // Update gender and civil status based on relationship
            if (relationship === 'Spouse') {
                genderSelect.value = headGender === 'M' ? 'F' : 'M';
                civilStatusSelect.value = 'Married';
            } else if (['Son', 'Father', 'Brother', 'Grandson', 'Brother-in-Law', 'Son-in-Law'].includes(relationship)) {
                genderSelect.value = 'M';
                civilStatusSelect.value = '';
            } else if (['Daughter', 'Mother', 'Sister', 'Granddaughter', 'Sister-in-Law', 'Daughter-in-Law', 'Mother-in-Law'].includes(relationship)) {
                genderSelect.value = 'F';
                civilStatusSelect.value = '';
            } else {
                genderSelect.value = 'M';
                civilStatusSelect.value = '';
            }
        }
        
        function updateCivilStatus() {
            const civilStatus = document.getElementById('civil_status').value;
            const customCivilStatusGroup = document.getElementById('custom_civil_status_group');
            const customCivilStatusInput = document.getElementById('custom_civil_status');

            if (civilStatus === 'Others') {
                customCivilStatusGroup.style.display = 'block';
                customCivilStatusInput.required = true;
            } else {
                customCivilStatusGroup.style.display = 'none';
                customCivilStatusInput.required = false;
                customCivilStatusInput.value = '';
            }
        }

        // Auto-capitalize first letter of each word in name fields and custom relationship
        function capitalizeWords(input) {
            return input.replace(/(^|\s|[-,.])([a-z])/g, (match, separator, letter) => separator + letter.toUpperCase());
        }

        // Validate and capitalize input on keyup
        ['last_name', 'first_name', 'middle_name', 'custom_relationship', 'custom_civil_status'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function() {
                    // Restrict to letters, spaces, dots, commas, and hyphens
                    this.value = this.value.replace(/[^a-zA-Z\s,.-]/g, '');
                    // Capitalize first letter of each word
                    this.value = capitalizeWords(this.value);
                });
            }
        });

        // Form validation before submission
        function validateForm() {
            const lastName = document.getElementById('last_name').value;
            const firstName = document.getElementById('first_name').value;
            const middleName = document.getElementById('middle_name').value;
            const relationship = document.getElementById('relationship').value;
            const customRelationship = document.getElementById('custom_relationship').value;
            const birthdate = document.getElementById('birthdate').value;

            if (!/^[a-zA-Z\s,.-]*$/.test(lastName) || !/^[a-zA-Z\s,.-]*$/.test(firstName) || (middleName && !/^[a-zA-Z\s,.-]*$/.test(middleName))) {
                alert('Name fields can only contain letters, spaces, dots, commas, and hyphens.');
                return false;
            }
            if (relationship === 'Others' && (!/^[a-zA-Z\s,.-]*$/.test(customRelationship) || customRelationship.trim() === '')) {
                alert('Custom relationship can only contain letters, spaces, dots, commas, and hyphens and cannot be empty.');
                return false;
            }
            if (!birthdate) {
                alert('Please select a valid birthdate.');
                return false;
            }
            if (civilStatus === 'Others' && (!/^[a-zA-Z\s,.-]*$/.test(customCivilStatus) || customCivilStatus.trim() === '')) {
                alert('Custom civil status can only contain letters, spaces, dots, commas, and hyphens and cannot be empty.');
                return false;
            }
            return true;
        }

        $('.accordion-header').on('click', function() {
            const content = $(this).next('.accordion-content');
            content.toggleClass('active');
        });
    </script>
    <style>
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
    </style>
</body>
</html>