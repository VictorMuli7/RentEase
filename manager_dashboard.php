<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('landlord');

$property_count = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE owner_id = ?');
$property_count->execute([$_SESSION['user_id']]);
$properties = (int) $property_count->fetchColumn();
$unit_count = $pdo->prepare(
    'SELECT COUNT(*) FROM units un JOIN properties p ON p.property_id = un.property_id WHERE p.owner_id = ?'
);
$unit_count->execute([$_SESSION['user_id']]);
$units = (int) $unit_count->fetchColumn();
$rent = $pdo->prepare(
    "SELECT COALESCE(SUM(py.amount), 0)
     FROM payments py
     JOIN leases l ON l.lease_id = py.lease_id
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE p.owner_id = ? AND py.status = 'completed'
       AND py.paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
$rent->execute([$_SESSION['user_id']]);
$collected = (float) $rent->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT p.name, p.location, un.unit_label, un.status, un.rent_amount
     FROM properties p JOIN units un ON un.property_id = p.property_id
     WHERE p.owner_id = ? ORDER BY p.name, un.unit_label"
);
$stmt->execute([$_SESSION['user_id']]);
$listings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Landlord dashboard - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        header{background:#0e6b63;color:#fff;padding:16px max(20px,calc((100% - 1000px)/2))}
        main{max-width:1000px;margin:30px auto;padding:0 20px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
        .card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:18px;margin-bottom:16px}.count{font-size:1.8rem;font-weight:700}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:9px;border-bottom:1px solid #dce3e8}
    </style>
</head>
<body>
<header><strong>RentEase · Landlord</strong> · <?= e($_SESSION['user_name']) ?> · <a style="color:white" href="change_password.php">Change password</a> · <a style="color:white" href="logout.php">Sign out</a></header>
<main>
    <h1>Portfolio overview</h1>
    <div class="grid">
        <section class="card">Properties<div class="count"><?= $properties ?></div></section>
        <section class="card">Units<div class="count"><?= $units ?></div></section>
        <section class="card">Rent collected this month<div class="count">KES <?= number_format($collected) ?></div></section>
    </div>
    <section class="card">
        <h2>Your units</h2>
        <div style="overflow-x:auto"><table><thead><tr><th>Property</th><th>Location</th><th>Unit</th><th>Rent</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($listings as $listing): ?>
            <tr><td><?= e($listing['name']) ?></td><td><?= e($listing['location']) ?></td>
                <td><?= e($listing['unit_label']) ?></td><td>KES <?= number_format((float) $listing['rent_amount']) ?></td>
                <td><?= e(ucfirst($listing['status'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$listings): ?><tr><td colspan="5">No properties are assigned to this account.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
</body>
</html>
