<?php
session_start();
require_once 'db_connect.php';

// Handle status updates
if (isset($_GET['id']) && isset($_GET['status'])) {
    $req_id = intval($_GET['id']);
    $new_status = $_GET['status'];
    
    $stmt = $pdo->prepare("UPDATE maintenance_requests SET status = ? WHERE request_id = ?");
    $stmt->execute([$new_status, $req_id]);
    header("Location: manage_maintenance.php");
    exit;
}

// Fetch all maintenance tickets
$sql = "SELECT m.request_id, m.issue_title, m.description, m.priority, m.status, m.created_at,
               u.full_name AS tenant_name, u.phone, p.name AS property_name, un.unit_number
        FROM maintenance_requests m
        JOIN users u ON m.tenant_id = u.user_id
        JOIN units un ON m.unit_id = un.unit_id
        JOIN properties p ON un.property_id = p.property_id
        ORDER BY m.created_at DESC";

$requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Tickets - RentEase</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f4f6f9; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #007bff; color: white; }
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .high { background: #f8d7da; color: #721c24; }
        .medium { background: #fff3cd; color: #856404; }
        .low { background: #e2e3e5; color: #383d41; }
        .pending { background: #ffc107; color: #333; }
        .in_progress { background: #17a2b8; color: white; }
        .resolved { background: #28a745; color: white; }
        .btn { padding: 5px 10px; text-decoration: none; color: white; border-radius: 4px; font-size: 12px; margin-right: 3px; }
        .btn-work { background-color: #17a2b8; }
        .btn-resolve { background-color: #28a745; }
    </style>
</head>
<body>

    <h2>Maintenance Requests Center</h2>
    <a href="manager_dashboard.php" style="text-decoration: none; font-weight: bold; color: #007bff;">← Back to Dashboard</a>
    <br><br>

    <table>
        <thead>
            <tr>
                <th>Ticket #</th>
                <th>Tenant & Unit</th>
                <th>Issue</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="6" style="text-align:center;">No maintenance requests found.</td></tr>
            <?php else: ?>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td>#<?= $r['request_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['tenant_name']) ?></strong><br>
                            <small><?= htmlspecialchars($r['property_name']) ?> (Unit <?= htmlspecialchars($r['unit_number']) ?>)</small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['issue_title']) ?></strong><br>
                            <small color="#666"><?= htmlspecialchars($r['description']) ?></small>
                        </td>
                        <td><span class="badge <?= strtolower($r['priority']) ?>"><?= ucfirst($r['priority']) ?></span></td>
                        <td><span class="badge <?= strtolower($r['status']) ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <a href="manage_maintenance.php?id=<?= $r['request_id'] ?>&status=in_progress" class="btn btn-work">Mark In Progress</a>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'resolved'): ?>
                                <a href="manage_maintenance.php?id=<?= $r['request_id'] ?>&status=resolved" class="btn btn-resolve">Mark Resolved</a>
                            <?php else: ?>
                                <em>Closed</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>