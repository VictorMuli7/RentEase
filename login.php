<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . redirect_for_role($_SESSION['role_name'] ?? 'tenant'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== '') {
        try {
            $stmt = $pdo->prepare(
                "SELECT u.user_id, u.role_id, u.full_name, u.email, u.password_hash, u.status, r.role_name
                 FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.email = ?"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['role_id'] = (int) $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                header('Location: ' . redirect_for_role($user['role_name']));
                exit;
            }

            $error = 'Invalid email or password, or this account is inactive.';
        } catch (PDOException $e) {
            error_log('RentEase login failed: ' . $e->getMessage());
            $error = 'Sign-in is temporarily unavailable. Check the local database setup and try again.';
        }
    } else {
        $error = 'Enter a valid email address and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentEase - Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: #ffffff; width: 100%; max-width: 420px; padding: 40px 32px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); }
        .brand-header { text-align: center; margin-bottom: 28px; }
        .brand-header h1 { color: #2563eb; font-size: 30px; font-weight: 700; margin-bottom: 6px; }
        .brand-header p { color: #64748b; font-size: 14px; }
        .alert-error { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center; }
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

    <div class="login-card">
        <div class="brand-header">
            <h1>RentEase</h1>
            <p>Property Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>

        <div class="footer-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>

</body>
</html>