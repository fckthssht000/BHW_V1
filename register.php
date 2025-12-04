<?php
// Start output buffering to prevent stray output from breaking JSON
ob_start();
session_start();
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Function to send OTP
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'koin.koinbeef0909@gmail.com';
        $mail->Password = 'lvne scoz ucjw lrng';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('your_email@gmail.com', 'BRGYCare');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Registration';
        $mail->Body = "Your OTP is: <strong>$otp</strong>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to validate confirmation key
function validateConfirmationKey($key, $purok) {
    $purok_clean = str_replace('Purok ', 'P', $purok);
    $file = "keys/{$purok_clean}_confirmation_key.json";
    error_log("Validating key: $key for purok: $purok_clean, file: $file");
    
    if (!file_exists($file)) {
        error_log("File does not exist: $file");
        return false;
    }
    
    $keys = json_decode(file_get_contents($file), true);
    if (!$keys || !isset($keys[$key])) {
        error_log("Key not found or JSON invalid. Keys: " . print_r($keys, true));
        return false;
    }
    
    if ($keys[$key]['used']) {
        error_log("Key already used: $key");
        return false;
    }
    
    error_log("Key is valid, marking as used: $key");
    $keys[$key]['used'] = true;
    file_put_contents($file, json_encode($keys, JSON_PRETTY_PRINT));
    return true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $household_number = $_POST['household_number'] ?? '';
    $last_name = filter_var($_POST['last_name'] ?? '', FILTER_SANITIZE_STRING);
    $first_name = filter_var($_POST['first_name'] ?? '', FILTER_SANITIZE_STRING);
    $middle_initial = filter_var($_POST['middle_initial'] ?? '', FILTER_SANITIZE_STRING);
    $relationship = 'Head';
    $gender = $_POST['gender'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $custom_civil_status = filter_var($_POST['custom_civil_status'] ?? '', FILTER_SANITIZE_STRING);
    $phone = $_POST['phone'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $confirmation_key = $_POST['confirmation_key'] ?? '';
    $email = $_POST['email'] ?? '';

    // Store form data in session
    $_SESSION['form_data'] = [
        'username' => $username,
        'last_name' => $last_name,
        'first_name' => $first_name,
        'middle_initial' => $middle_initial,
        'gender' => $gender,
        'birthdate' => $birthdate,
        'civil_status' => $civil_status,
        'custom_civil_status' => $custom_civil_status,
        'phone' => $phone,
        'purok' => $purok,
        'confirmation_key' => $confirmation_key,
        'email' => $email
    ];

    // Validate inputs
    $errors = [];
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($household_number)) $errors[] = "Household number is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($birthdate)) $errors[] = "Birthdate is required.";
    if (empty($civil_status)) $errors[] = "Civil status is required.";
    if (empty($purok)) $errors[] = "Purok is required.";
    if (empty($confirmation_key)) $errors[] = "Confirmation key is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    // Validate confirmation key
    if (!validateConfirmationKey($confirmation_key, $purok)) {
        $errors[] = "Invalid or already used confirmation key.";
    }

    // Validate name fields
    if (!empty($last_name) && !preg_match('/^[a-zA-Z\s,.]*$/', $last_name)) {
        $errors[] = "Last name can only contain letters, spaces, dots, and commas.";
    }
    if (!empty($first_name) && !preg_match('/^[a-zA-Z\s,.]*$/', $first_name)) {
        $errors[] = "First name can only contain letters, spaces, dots, and commas.";
    }
    if (!empty($middle_initial) && !preg_match('/^[a-zA-Z\s,.]*$/', $middle_initial)) {
        $errors[] = "Middle initial can only contain letters, spaces, dots, and commas.";
    }
    // Custom civil status validation
    if ($civil_status === 'Others' && (empty($custom_civil_status) || !preg_match('/^[a-zA-Z\s,.\-]*$/', $custom_civil_status))) {
        $errors[] = "Custom civil status can only contain letters, spaces, dots, commas, and hyphens and cannot be empty.";
    }

    // Calculate age
    try {
        if (!empty($birthdate)) {
            $birth_date = new DateTime($birthdate);
            $current_date = new DateTime();
            $age = $current_date->diff($birth_date)->y;
        }
    } catch (Exception $e) {
        $errors[] = "Invalid birthdate format.";
    }

    // Fetch address_id
    if (!empty($purok)) {
        $stmt = $pdo->prepare("SELECT address_id FROM address WHERE purok = ? AND barangay = 'Sta. Maria' AND municipality = 'Camiling' AND province = 'Tarlac' LIMIT 1");
        $stmt->execute([$purok]);
        $address_id = $stmt->fetchColumn();
        if (!$address_id) {
            $errors[] = "Selected purok not found in address table.";
        }
    }

    // Handle errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' | ', $errors);
        header("Location: register.php");
        exit;
    }

    // Capitalize names
    $last_name = ucwords(strtolower($last_name));
    $first_name = ucwords(strtolower($first_name));
    $middle_initial = $middle_initial ? ucwords(strtolower($middle_initial)) : '';
    
    // Capitalize custom civil status if present
    if ($civil_status === 'Others' && !empty($custom_civil_status)) {
        $civil_status = ucwords(strtolower($custom_civil_status));
    }

    // Generate and send OTP
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['registration_data'] = [
        'username' => $username,
        'password' => $password,
        'household_number' => $household_number,
        'last_name' => $last_name,
        'first_name' => $first_name,
        'middle_initial' => $middle_initial,
        'relationship' => $relationship,
        'gender' => $gender,
        'birthdate' => $birthdate,
        'age' => $age,
        'civil_status' => $civil_status,
        'phone' => $phone,
        'address_id' => $address_id,
        'full_name' => "$last_name, $first_name $middle_initial",
        'email' => $email
    ];

    if (sendOTP($email, $otp)) {
        $_SESSION['show_otp_modal'] = true;
        header("Location: register.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to send OTP email.";
        header("Location: register.php");
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] == 'get_household_number') {
        $purok = $_POST['purok'] ?? '';
        if ($purok) {
            if (preg_match('/Purok (\d+)([A-B]?)/i', $purok, $matches)) {
                $number = (int)$matches[1];
                $letter = strtoupper($matches[2] ?? '');

                switch ($number) {
                    case 1: $prefix = '100'; break;
                    case 2: $prefix = '200'; break;
                    case 3: $prefix = '300'; break;
                    case 4:
                        if ($letter === 'A') $prefix = '410';
                        elseif ($letter === 'B') $prefix = '420';
                        else $prefix = '400';
                        break;
                    case 5: $prefix = '500'; break;
                    case 6: $prefix = '600'; break;
                    case 7: $prefix = '700'; break;
                    default: $prefix = '0';
                }

                $likePattern = $prefix . '%';
                $stmt = $pdo->prepare("SELECT household_number FROM person WHERE household_number LIKE ? ORDER BY CAST(household_number AS UNSIGNED) DESC LIMIT 1");
                $stmt->execute([$likePattern]);
                $max_number = $stmt->fetchColumn();

                if (!$max_number) {
                    $next_number = $prefix . '01';
                } else {
                    if (substr($max_number, -2) === '99') {
                        $next_number = $prefix . '100';
                    } else {
                        $next_number = (string)((int)$max_number + 1);
                    }
                }

                ob_end_clean();
                echo json_encode(['success' => true, 'household_number' => $next_number]);
                exit;
            }
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No purok selected']);
        exit;
    }
    if ($_POST['action'] == 'verify_otp') {
        $otp = $_POST['otp'] ?? '';
        if (!isset($_SESSION['otp'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Session expired']);
            exit;
        }
        if ($otp == $_SESSION['otp']) {
            ob_end_clean();
            echo json_encode(['success' => true]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
        }
        exit;
    }
    if ($_POST['action'] == 'complete_registration') {
        if (!isset($_SESSION['registration_data'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Registration data not found']);
            exit;
        }
        $data = $_SESSION['registration_data'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO person (household_number, full_name, relationship_type, gender, birthdate, age, civil_status, contact_number, address_id, related_person_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");
            $stmt->execute([$data['household_number'], $data['full_name'], $data['relationship'], $data['gender'], $data['birthdate'], $data['age'], $data['civil_status'], $data['phone'],$data['address_id']]);
            $person_id = $pdo->lastInsertId();
            
            $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role_id, email) VALUES (?, ?, 3, ?)");
            $stmt->execute([$data['username'], $hashed_password, $data['email']]);
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO records (user_id, person_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $person_id]);
            
            unset($_SESSION['registration_data'], $_SESSION['otp'], $_SESSION['otp_email']);
            ob_end_clean();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Registration Error: " . $e->getMessage());
            $_SESSION['error'] = "Registration failed. Please try again.";
            header("Location: register.php");
            exit;
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRGYCare - Register</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: url('sta_maria_hall.jpg') no-repeat center center fixed;
            background-size: cover;
            background-color: #f4f6f9;
            backdrop-filter: blur(5px);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .register-card {
            max-width: 600px;
            margin: auto;
            background: #ffffffa6;
            backdrop-filter: opacity(50%);
            -webkit-backdrop-filter: opacity(50%);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .logo {
            display: block;
            margin: 0 auto 15px auto;
            max-width: 120px;
            border-radius: 100px;
        }
        .card-body {
            padding: 20px;
        }
        .card-title {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2b6cb0;
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 500;
            color: #2d3748;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0 15px;
            color: #1a202c;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            border-color: #2b6cb0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.3);
        }
        .form-control::placeholder { color: #718096; }
        .auto-filled {
            background-color: #e9ecef;
        }
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
        #toast {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1050;
            display: none;
        }
        .toast-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .toast-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .toast-error ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card register-card">
            <div class="card-body">
                <img src="logo.png" alt="BRGYCare Logo" class="logo">
                <h3 class="card-title text-center">BRGYCare Registration</h3>
                <form action="register.php" method="POST" id="registrationForm" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label for="confirmation_key">Confirmation Key</label>
                        <input type="text" class="form-control" id="confirmation_key" name="confirmation_key" required placeholder="e.g., SMCT-P1-XXXXXX" value="<?php echo htmlspecialchars($_SESSION['form_data']['confirmation_key'] ?? ''); ?>">
                    </div>                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required value="<?php echo htmlspecialchars($_SESSION['form_data']['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <h5>Personal Information</h5>
                    <div class="form-group">
                        <label for="purok">Purok</label>
                        <select class="form-control" id="purok" name="purok" required onchange="updateHouseholdNumber()">
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT purok FROM address WHERE barangay = 'Sta. Maria' AND municipality = 'Camiling' AND province = 'Tarlac' ORDER BY purok");
                            while ($row = $stmt->fetch()) {
                                $selected = ($_SESSION['form_data']['purok'] ?? '') === $row['purok'] ? 'selected' : '';
                                echo "<option value='{$row['purok']}' $selected>{$row['purok']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="household_number">Household Number</label>
                        <input type="text" class="form-control auto-filled" id="household_number" name="household_number" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required pattern="[A-Za-z\s,.]*" title="Only letters, spaces, dots, and commas are allowed" value="<?php echo htmlspecialchars($_SESSION['form_data']['last_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required pattern="[A-Za-z\s,.]*" title="Only letters, spaces, dots, and commas are allowed" value="<?php echo htmlspecialchars($_SESSION['form_data']['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_initial">Middle Initial</label>
                        <input type="text" class="form-control" id="middle_initial" name="middle_initial" pattern="[A-Za-z\s,.]*" title="Only letters, spaces, dots, and commas are allowed" value="<?php echo htmlspecialchars($_SESSION['form_data']['middle_initial'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select class="form-control" id="gender" name="gender" required>
                            <option value="M" <?php echo ($_SESSION['form_data']['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo ($_SESSION['form_data']['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birthdate">Birthdate</label>
                        <input type="date" class="form-control" id="birthdate" name="birthdate" required value="<?php echo htmlspecialchars($_SESSION['form_data']['birthdate'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="civil_status">Civil Status</label>
                        <select class="form-control" id="civil_status" name="civil_status" required onchange="updateCivilStatus()">
                            <option value="">Select Status</option>
                            <option value="Single" <?php echo ($_SESSION['form_data']['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo ($_SESSION['form_data']['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                            <option value="Widowed" <?php echo ($_SESSION['form_data']['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            <option value="Separated" <?php echo ($_SESSION['form_data']['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                            <option value="Others" <?php echo ($_SESSION['form_data']['civil_status'] ?? '') === 'Others' ? 'selected' : ''; ?>>Others</option>
                        </select>
                    </div>
                    <div class="form-group" id="custom_civil_status_group" style="display: none;">
                        <label for="custom_civil_status">Custom Civil Status</label>
                        <input type="text" class="form-control" id="custom_civil_status" name="custom_civil_status"
                             pattern="[A-Za-z\s,.\-]*"
                             title="Only letters, spaces, dots, commas, and hyphens are allowed"
                             value="<?php echo htmlspecialchars($_SESSION['form_data']['custom_civil_status'] ?? ''); ?>"
                             placeholder="Enter custom civil status">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['form_data']['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary btn-block">Register</button>
                    <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>
                </form>
            </div>
        </div>
        <div id="toast"></div>
    </div>
    <!-- OTP Modal -->
    <div class="modal fade" id="otpModal" tabindex="-1" role="dialog" aria-labelledby="otpModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="otpModalLabel">Verify Your Email</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>An OTP has been sent to your email. Please enter the 6-digit code below to complete your registration.</p>
                    <input type="text" class="form-control" id="otp_input" placeholder="Enter OTP" maxlength="6">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="verifyOTP()">Verify</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function updateHouseholdNumber() {
            const purok = $('#purok').val();
            if (purok) {
                $.ajax({
                    url: 'register.php',
                    type: 'POST',
                    data: { action: 'get_household_number', purok: purok },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#household_number').val(response.household_number || '');
                        } else {
                            $('#household_number').val('');
                            showToast(response.error || 'Error fetching household number', 'error');
                        }
                    },
                    error: function(xhr) {
                        console.error('AJAX Error:', xhr.responseText);
                        $('#household_number').val('');
                        showToast('Error fetching household number', 'error');
                    }
                });
            } else {
                $('#household_number').val('');
            }
        }
        
        function updateCivilStatus() {
            const cstat = document.getElementById('civil_status').value;
            const customGroup = document.getElementById('custom_civil_status_group');
            const customInput = document.getElementById('custom_civil_status');
            if (cstat === 'Others') {
                customGroup.style.display = 'block';
                customInput.required = true;
            } else {
                customGroup.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }
        // --- Ensure initialization on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCivilStatus();
        });

        // Show toast notification
        function showToast(message, type) {
            const toast = $('#toast');
            toast.html(message).removeClass('toast-success toast-error').addClass(`toast-${type}`).show();
            setTimeout(() => { toast.hide().removeClass(`toast-${type}`).html(''); }, 7000);
        }

        // Auto-capitalize name fields
        function capitalizeWords(input) {
            return input.replace(/(^|\s|[-,.])([a-z])/g, (match, separator, letter) => separator + letter.toUpperCase());
        }

        ['last_name', 'first_name', 'middle_initial', 'custom_civil_status'].forEach(id => {
            const input = document.getElementById(id);
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s,.]/g, '');
                this.value = capitalizeWords(this.value);
            });
        });

        // Auto-select purok based on confirmation key
        $('#confirmation_key').on('input', function() {
            const key = $(this).val().toUpperCase();
            const purokMap = {
                'SMCT-P1': 'Purok 1',
                'SMCT-P2': 'Purok 2',
                'SMCT-P3': 'Purok 3',
                'SMCT-P4A': 'Purok 4A',
                'SMCT-P4B': 'Purok 4B',
                'SMCT-P5': 'Purok 5',
                'SMCT-P6': 'Purok 6',
                'SMCT-P7': 'Purok 7'
            };
            const prefix = key.split('-')[0] + '-' + key.split('-')[1];
            if (purokMap[prefix]) {
                $('#purok').val(purokMap[prefix]);
                updateHouseholdNumber();
            }
        });

        // Form validation
        function validateForm() {
            const errors = [];
            const lastName = document.getElementById('last_name').value;
            const firstName = document.getElementById('first_name').value;
            const middleInitial = document.getElementById('middle_initial').value;
            const confirmationKey = document.getElementById('confirmation_key').value;
            const purok = document.getElementById('purok').value;
            const expectedPrefix = `SMCT-${purok.replace('Purok ', 'P').replace(' ', '')}-`;
            const civilStatus = document.getElementById('civil_status').value;
            const customCivilStatus = document.getElementById('custom_civil_status').value;

            if (!/^[a-zA-Z\s,.]*$/.test(lastName)) {
                errors.push('Last name can only contain letters, spaces, dots, and commas.');
            }
            if (!/^[a-zA-Z\s,.]*$/.test(firstName)) {
                errors.push('First name can only contain letters, spaces, dots, and commas.');
            }
            if (middleInitial && !/^[a-zA-Z\s,.]*$/.test(middleInitial)) {
                errors.push('Middle initial can only contain letters, spaces, dots, and commas.');
            }
            if (civilStatus === 'Others' && (!/^[a-zA-Z\s,.\-]*$/.test(customCivilStatus) || customCivilStatus.trim() === '')) {
                errors.push('Custom civil status can only contain letters, spaces, dots, commas, and hyphens and cannot be empty.');
            }
            if (!confirmationKey.match(/^SMCT-P[1-7][A-B]?-[A-Z0-9]{6}$/)) {
                errors.push('Invalid confirmation key format. It should be like SMCT-P1-XXXXXX.');
            }
            if (!confirmationKey.startsWith(expectedPrefix)) {
                errors.push(`Confirmation key must start with ${expectedPrefix} for selected Purok ${purok}.`);
            }

            if (errors.length > 0) {
                showToast(`<ul>${errors.map(error => `<li>${error}</li>`).join('')}</ul>`, 'error');
                return false;
            }
            return true;
        }

        // Verify OTP
        function verifyOTP() {
            const otp = $('#otp_input').val();
            if (!otp || otp.length !== 6 || !/^\d{6}$/.test(otp)) {
                showToast('Please enter a valid 6-digit numeric OTP.', 'error');
                return;
            }
            $.ajax({
                url: 'register.php',
                type: 'POST',
                data: { action: 'verify_otp', otp: otp },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#otpModal').modal('hide');
                        completeRegistration();
                    } else {
                        showToast(response.error || 'Invalid OTP. Please try again.', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX Error:', xhr.responseText);
                    showToast('Error verifying OTP. Please try again.', 'error');
                }
            });
        }

        // Complete registration
        function completeRegistration() {
            $.ajax({
                url: 'register.php',
                type: 'POST',
                data: { action: 'complete_registration' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Registration completed successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        showToast(response.error || 'Error completing registration. Please try again.', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX Error:', xhr.responseText);
                    showToast('Error completing registration. Please try again.', 'error');
                }
            });
        }

        // Initialize page
        $(document).ready(function() {
            updateHouseholdNumber();
            $('#purok').change(updateHouseholdNumber);

            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            if (success) {
                showToast(decodeURIComponent(success), 'success');
            } else if (error) {
                const errors = decodeURIComponent(error).split(' | ');
                showToast(`<ul>${errors.map(err => `<li>${err}</li>`).join('')}</ul>`, 'error');
            }

            <?php if (isset($_SESSION['show_otp_modal']) && $_SESSION['show_otp_modal']) { ?>
                $('#otpModal').modal('show');
                <?php unset($_SESSION['show_otp_modal']); ?>
            <?php } ?>

            <?php unset($_SESSION['error']); ?>
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>