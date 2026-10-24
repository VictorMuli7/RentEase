<?php
require_once 'db_connect.php';

// Fetch pending and past applications with applicant and unit details
$sql = "SELECT a.application_id, a.status, a.created_at, 
               u.full_name AS applicant_name, u.email, u.phone, 
               p.name AS property_name, un.unit_number, un.rent_amount
        FROM applications a
        JOIN users u ON a.tenant_id = u.user_id
        JOIN units un ON a.unit_id = un.unit_id
        JOIN properties p ON un.property_id = p.property_id
        ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Applications - RentEase</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f4f6f9; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #007bff; color: white; }
        .badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .pending { background-color: #ffc107; color: #333; }
        .approved { background-color: #28a745; color: white; }
        .rejected { background-color: #dc3545; color: white; }
        .btn { padding: 6px 12px; text-decoration: none; color: white; border-radius: 4px; font-size: 13px; margin-right: 5px; }
        .btn-approve { background-color: #28a745; }
        .btn-reject { background-color: #dc3545; }
    </style>
</head>
<body>

    <h2>Rental Applications Review</h2>
    
    <table>
        <thead>
            <tr>
                <th>App #</th>
                <th>Applicant</th>
                <th>Contact</th>
                <th>Property & Unit</th>
                <th>Rent (KES)</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applications)): ?>
                <tr><td colspan="7" style="text-align:center;">No applications submitted yet.</td></tr>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($app['application_id']) ?></td>
                        <td><?= htmlspecialchars($app['applicant_name']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?><br><small><?= htmlspecialchars($app['phone']) ?></small></td>
                        <td><strong><?= htmlspecialchars($app['property_name']) ?></strong> (Unit <?= htmlspecialchars($app['unit_number']) ?>)</td>
                        <td><?= number_format($app['rent_amount']) ?></td>
                        <td>
                            <span class="badge <?= strtolower($app['status']) ?>">
                                <?= ucfirst(htmlspecialchars($app['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($app['status'] === 'pending'): ?>
                                <a href="update_application.php?id=<?= $app['application_id'] ?>&action=approve" class="btn btn-approve">Approve</a>
                                <a href="update_application.php?id=<?= $app['application_id'] ?>&action=reject" class="btn btn-reject">Reject</a>
                            <?php else: ?>
                                <em>Processed</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>