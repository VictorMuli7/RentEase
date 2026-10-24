<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
        $error = 'Enter your full name (2–100 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $error = 'Enter a valid email address.';
    } elseif ($phone === '' || strlen($phone) > 15) {
        $error = 'Enter a phone number no longer than 15 characters.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password with at least 8 characters.';
    } else {
        try {
            $role = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'tenant'");
            $role->execute();
            $role_id = $role->fetchColumn();
            if (!$role_id) {
                throw new RuntimeException('The tenant role is missing from the database.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO users (role_id, full_name, email, phone, password_hash)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$role_id, $full_name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $success = 'Registration successful. You can now sign in.';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'An account already uses that email address or phone number.';
            } else {
                error_log('RentEase registration failed: ' . $e->getMessage());
                $error = 'Registration is temporarily unavailable. Check the local database setup and try again.';
            }
        } catch (RuntimeException $e) {
            error_log('RentEase registration configuration error: ' . $e->getMessage());
            $error = 'Registration is unavailable because the tenant role is missing. Import the database schema.';
        }
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" maxlength="15" autocomplete="tel" placeholder="0712345678" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
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