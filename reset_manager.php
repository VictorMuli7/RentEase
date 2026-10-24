<?php
require_once 'db_connect.php';

$email = 'judy@example.com';
$new_password = 'password123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

// Check if judy@example.com exists, update or insert
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $update->execute([$hashed, $email]);
    echo "Manager password successfully reset to: <strong>password123</strong> for " . $email;
} else {
    $insert = $pdo->prepare("INSERT INTO users (email, password_hash, phone) VALUES (?, ?, ?)");
    $insert->execute([$email, $hashed, '0700000000']);
    echo "Manager account created! Email: <strong>judy@example.com</strong>, Password: <strong>password123</strong>";
}
?>
<br><br>
<a href="login.php" style="padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px;">Go to Login</a>