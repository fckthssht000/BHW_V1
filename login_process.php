<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header("Location: index.php?error=Username and password are required");
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9]{5,}$/', $username)) {
        header("Location: index.php?error=Username must be at least 5 alphanumeric characters");
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id, role_id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role_id'] = $user['role_id'];
        header("Location: dashboard.php?success=Login successful");
    } else {
        header("Location: index.php?error=Invalid credentials");
    }
}
?>