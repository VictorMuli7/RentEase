<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('tenant');

$stmt = $pdo->prepare(
    "SELECT a.status, a.review_comment, a.submitted_at, p.name AS property_name,
            un.unit_label, un.rent_amount
     FROM applications a
     JOIN units un ON un.unit_id = a.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE a.tenant_id = ?
     ORDER BY a.submitted_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT l.lease_id, l.start_date, l.end_date, l.monthly_rent, l.status,
            p.name AS property_name, un.unit_label
     FROM leases l
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE l.tenant_id = ?
     ORDER BY l.start_date DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$leases = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT py.reference_no, py.period_month, py.amount, py.method, py.status
     FROM payments py JOIN leases l ON l.lease_id = py.lease_id
     WHERE l.tenant_id = ? ORDER BY py.period_month DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My account - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:950px;margin:32px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:20px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #dce3e8;padding:9px}
    </style>
</head>
<body>
<main>
    <p>
    <a href="browse_properties.php">← Browse homes</a> ·
    <a href="tenant_pay.php">Pay Rent (Simulation)</a> ·
    <a href="submit_maintenance.php">Report maintenance</a> ·
    <a href="change_password.php">Change password</a> ·
    <a href="logout.php">Sign out</a>
</p>
    <h1>My RentEase account</h1>
    <section class="card">
        <h2>Applications</h2>
        <table><thead><tr><th>Property / unit</th><th>Rent</th><th>Status</th><th>Submitted</th><th>Review note</th></tr></thead><tbody>
            <?php foreach ($applications as $application): ?>
                <tr><td><?= e($application['property_name']) ?> · <?= e($application['unit_label']) ?></td>
                    <td>KES <?= number_format((float) $application['rent_amount']) ?></td>
                    <td><?= e(ucfirst($application['status'])) ?></td>
                    <td><?= e($application['submitted_at']) ?></td>
                    <td><?= e($application['review_comment'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$applications): ?><tr><td colspan="5">You have not applied for a unit yet.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
    <section class="card">
        <h2>Leases</h2>
        <table><thead><tr><th>Property / unit</th><th>Monthly rent</th><th>Dates</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($leases as $lease): ?>
                <tr><td><?= e($lease['property_name']) ?> · <?= e($lease['unit_label']) ?></td>
                    <td>KES <?= number_format((float) $lease['monthly_rent']) ?></td>
                    <td><?= e($lease['start_date']) ?> – <?= e($lease['end_date']) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $lease['status']))) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$leases): ?><tr><td colspan="4">You do not have an active lease yet.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
    <section class="card">
        <h2>Payment history</h2>
        <p>For payment processing, contact your property manager; online payment integrations are not configured.</p>
        <table><thead><tr><th>Receipt</th><th>Month</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($payments as $payment): ?>
                <tr><td><?= e($payment['reference_no']) ?></td><td><?= e(substr($payment['period_month'], 0, 7)) ?></td>
                    <td>KES <?= number_format((float) $payment['amount']) ?></td><td><?= e(ucfirst($payment['method'])) ?></td>
                    <td><?= e(ucfirst($payment['status'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="5">No payments recorded.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
</main>
</body>
</html>
