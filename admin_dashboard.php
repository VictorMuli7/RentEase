<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('admin');

$applications = $pdo->query(
    "SELECT a.application_id, a.status, a.submitted_at, a.monthly_income,
            u.full_name, u.email, p.name AS property_name, un.unit_label
     FROM applications a
     JOIN users u ON u.user_id = a.tenant_id
     JOIN units un ON un.unit_id = a.unit_id
     JOIN properties p ON p.property_id = un.property_id
     ORDER BY a.submitted_at DESC"
)->fetchAll();
$user_count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin dashboard - RentEase</title>
    <style>
        body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        header{display:flex;justify-content:space-between;background:#fff;padding:16px 24px;border-bottom:1px solid #dce3e8}
        main{max-width:1100px;margin:30px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:20px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #dce3e8}
    </style>
</head>
<body>
<header><strong>RentEase · Admin</strong><span><?= e($_SESSION['user_email']) ?> · <a href="change_password.php">Change password</a> · <a href="logout.php">Sign out</a></span></header>
<main>
    <h1>System overview</h1>
    <section class="card"><strong><?= $user_count ?></strong> registered users · <strong><?= count($applications) ?></strong> applications</section>
    <section class="card">
        <h2>Applications</h2>
        <div style="overflow-x:auto"><table><thead><tr><th>Applicant</th><th>Property / unit</th><th>Income</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
        <?php foreach ($applications as $application): ?>
            <tr><td><?= e($application['full_name']) ?><br><?= e($application['email']) ?></td>
                <td><?= e($application['property_name']) ?> · <?= e($application['unit_label']) ?></td>
                <td>KES <?= number_format((float) $application['monthly_income']) ?></td>
                <td><?= e(ucfirst($application['status'])) ?></td><td><?= e($application['submitted_at']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$applications): ?><tr><td colspan="5">No applications.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>
