<?php
$host = '127.0.0.1:3306';
$dbname = 'u941584027_healthbuddy';
$username = 'u941584027_healthbuddy';
$password = '@BrgyStaMaria2025^^';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>