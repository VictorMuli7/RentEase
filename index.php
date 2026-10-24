<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');

$is_admin = $_SESSION['role_name'] === 'admin';
if ($is_admin) {
    $total_units = (int) $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
    $vacant_units = (int) $pdo->query("SELECT COUNT(*) FROM units WHERE status = 'vacant'")->fetchColumn();
    $pending_apps = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
    $open_maintenance = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status != 'closed'")->fetchColumn();
    $stmt = $pdo->query(
        "SELECT un.unit_id, un.unit_label, un.bedrooms, un.rent_amount, un.status, p.name
         FROM units un JOIN properties p ON p.property_id = un.property_id
         ORDER BY p.name, un.unit_label"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total, SUM(un.status = 'vacant') AS vacant
         FROM units un JOIN properties p ON p.property_id = un.property_id WHERE p.manager_id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $unit_counts = $stmt->fetch();
    $total_units = (int) $unit_counts['total'];
    $vacant_units = (int) $unit_counts['vacant'];
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM applications a
         JOIN units un ON un.unit_id = a.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.manager_id = ? AND a.status = 'pending'"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $pending_apps = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM maintenance_requests m
         JOIN units un ON un.unit_id = m.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.manager_id = ? AND m.status != 'closed'"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $open_maintenance = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT un.unit_id, un.unit_label, un.bedrooms, un.rent_amount, un.status, p.name
         FROM units un JOIN properties p ON p.property_id = un.property_id
         WHERE p.manager_id = ? ORDER BY p.name, un.unit_label"
    );
    $stmt->execute([$_SESSION['user_id']]);
}
$units = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manager dashboard - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        header{display:flex;justify-content:space-between;align-items:center;background:#0e6b63;color:#fff;padding:16px max(20px,calc((100% - 1100px)/2))}
        nav,main{max-width:1100px;margin:auto;padding:16px 20px}nav{display:flex;gap:18px;flex-wrap:wrap}
        a{color:#0e6b63}.navlink{color:#fff}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
        .card{background:white;border:1px solid #dce3e8;border-radius:10px;padding:18px;margin-bottom:16px}
        .count{font-size:1.8rem;font-weight:700}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #dce3e8;padding:10px}
        button{font:inherit;padding:7px 11px;border:1px solid #0e6b63;border-radius:6px;background:#fff;color:#0e6b63;cursor:pointer}
    </style>
</head>
<body>
<header>
    <strong>RentEase · Manager</strong>
    <span><?= e($_SESSION['user_name']) ?> · <a class="navlink" href="change_password.php">Change password</a> · <a class="navlink" href="logout.php">Sign out</a></span>
</header>
<nav>
    <a href="index.php">Overview</a><a href="add_listing.php">Add listing</a>
    <a href="applications_list.php">Applications</a><a href="manage_maintenance.php">Maintenance</a>
    <a href="payments.php">Payments</a>
</nav>
<main>
    <h1>Property overview</h1>
    <div class="cards">
        <section class="card"><div>Total units</div><div class="count"><?= $total_units ?></div></section>
        <section class="card"><div>Vacant units</div><div class="count"><?= $vacant_units ?></div></section>
        <section class="card"><div>Applications to review</div><div class="count"><?= $pending_apps ?></div></section>
        <section class="card"><div>Open maintenance requests</div><div class="count"><?= $open_maintenance ?></div></section>
    </div>
    <section class="card">
        <h2>Units</h2>
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Property / unit</th><th>Bedrooms</th><th>Rent (KES)</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($units as $unit): ?>
                <tr>
                    <td><?= e($unit['name']) ?> · <?= e($unit['unit_label']) ?></td>
                    <td><?= (int) $unit['bedrooms'] ?></td>
                    <td><?= number_format((float) $unit['rent_amount']) ?></td>
                    <td><?= e(ucfirst($unit['status'])) ?></td>
                    <td>
                        <form method="post" action="process_manager.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="unit_id" value="<?= (int) $unit['unit_id'] ?>">
                            <button type="submit">Mark <?= $unit['status'] === 'vacant' ? 'occupied' : 'vacant' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$units): ?><tr><td colspan="5">No units yet. <a href="add_listing.php">Add your first listing</a>.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
