<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CommuniCare - Login</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background: url('sta_maria_hall.jpg') no-repeat center center fixed;
            background-size: cover;
            background-color: #f4f6f9; /* Fallback background */
            backdrop-filter: blur(5px);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            max-width: 400px;   
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.65); /* Fixed translucency */
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        .card-body {
            padding: 30px;
        }
        .logo {
            display: block;
            margin: 0 auto 15px auto;
            max-width: 120px;
            border-radius: 100px;
        }
        .card-title {
            color: #2b6cb0;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group label {
            color: #2d3748;
            font-weight: 500;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            transition: border-color 0.2s ease;
            padding: 12px 15px;
            font-size: 1rem;
        }
        .form-control::placeholder {
            color: #a0aec0;
            opacity: 1;
        }
        .form-control:focus {
            border-color: #2b6cb0;
            box-shadow: 0 0 5px rgba(43, 108, 176, 0.3);
        }
        .btn-primary {
            background: #2b6cb0;
            border: none;
            padding: 10px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #2c5282;
            transform: translateY(-1px);
        }
        .text-center a {
            color: #2b6cb0;
            text-decoration: none;
            font-weight: 500;
        }
        .text-center a:hover {
            color: #2c5282;
            text-decoration: underline;
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
        @media (max-width: 768px) {
            .login-card {
                margin: 20px;
                width: 90%;
            }
            #toast {
                right: 10px;
                min-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card">
            <div class="card-body">
                <img src="logo.png" alt="CommuniCare Logo" class="logo">
                <h3 class="card-title">CommuniCare Login</h3>
                <form action="login_process.php" method="POST" novalidate>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username" 
                               required 
                               maxlength="50"
                               pattern="[a-zA-Z0-9_]+"
                               title="Username should only contain letters, numbers, and underscores.">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required 
                               minlength="8"
                               title="Password must be at least 8 characters long.">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                    <p class="text-center mt-3">Don't have an account? <a href="register.php">Register</a></p>
                </form>
            </div>
        </div>
        <div id="toast"></div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle URL error parameter for toast
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            if (error) {
                showToast(error, 'error');
            }

            // Form validation
            $('form').on('submit', function(e) {
                const username = $('#username').val().trim();
                const password = $('#password').val();

                if (username.length === 0) {
                    e.preventDefault();
                    showToast('Please enter a username.', 'error');
                    return false;
                }

                // Validate username format
                if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                    e.preventDefault();
                    showToast('Username should only contain letters, numbers, and underscores.', 'error');
                    return false;
                }

                if (password.length < 5) {
                    e.preventDefault();
                    showToast('Invalid username or password', 'error');
                    return false;
                }

                // Format username to lowercase and replace spaces with underscores
                $('#username').val(username.toLowerCase().replace(/\s+/g, '_'));
            });

            function showToast(message, type) {
                $('#toast')
                    .text(message)
                    .removeClass('toast-error toast-success')
                    .addClass(`toast-${type}`)
                    .show();
                setTimeout(() => {
                    $('#toast').hide().removeClass(`toast-${type}`);
                }, 3000);
            }
        });
    </script>
</body>
</html>