<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $phone    = trim($_POST['phone'] ?? '');

    if (!empty($email) && !empty($password)) {
        try {
            // Check if email already exists using the email column directly
            $checkStmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                // Securely hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, phone) VALUES (?, ?, ?)");
                $stmt->execute([$email, $password_hash, $phone]);

                $success = "Registration successful! You can now sign in.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentEase - Register Tenant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #ffffff; width: 100%; max-width: 420px; padding: 40px 32px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); }
        .brand-header { text-align: center; margin-bottom: 28px; }
        .brand-header h1 { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 6px; }
        .alert-error { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center; }
        .alert-success { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; }
        .form-group input:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15); }
        .btn-submit { width: 100%; padding: 13px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .footer-link { text-align: center; margin-top: 24px; font-size: 14px; color: #64748b; }
        .footer-link a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="card">
        <div class="brand-header">
            <h1>Register Tenant</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-success">
                <?php echo htmlspecialchars($success); ?><br><br>
                <a href="login.php" style="color: #16a34a; font-weight: bold; text-decoration: underline;">Click here to Sign In</a>
            </div>
        <?php else: ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (Optional)</label>
                    <input type="text" id="phone" name="phone" placeholder="0712345678">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-submit">Register Account</button>
            </form>

        <?php endif; ?>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>

</body>
</html>