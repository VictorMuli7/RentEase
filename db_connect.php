<?php
// Database connection configuration
$host = 'sql303.infinityfree.com'; // Replace with your InfinityFree MySQL Hostname
$dbname = 'if0_43253812_rentease_db'; // Replace with your InfinityFree Database Name
$username = 'if0_43253812'; // Replace with your InfinityFree Username
$password = '3tVvschX4G'; // Replace with your InfinityFree Password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>