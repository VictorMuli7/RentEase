<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('tenant', 'manager', 'landlord', 'admin');

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $current_hash = $stmt->fetchColumn();

    if (!$current_hash || !password_verify($current, $current_hash)) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'Choose a new password with at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'The new password confirmation does not match.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        $success = 'Your password has been changed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:500px;margin:40px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:24px}
        label{display:block;font-weight:600;margin-top:14px}input,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px}
        input{width:100%;box-sizing:border-box}button{background:#0e6b63;color:#fff;border-color:#0e6b63;margin-top:18px;cursor:pointer}
        .error{color:#a52121}.success{color:#176b37}
    </style>
</head>
<body>
<main>
    <p><a href="<?= e(redirect_for_role($_SESSION['role_name'])) ?>">← Dashboard</a></p>
    <section class="card">
        <h1>Change password</h1>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= e($success) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Update password</button>
        </form>
    </section>
</main>
</body>
</html>
