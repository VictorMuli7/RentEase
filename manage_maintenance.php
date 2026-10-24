<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    if (!$request_id || !in_array($status, ['assigned', 'in_progress', 'closed'], true)) {
        $error = 'Choose a valid request and status.';
    } else {
        $check_sql =
            'SELECT m.request_id FROM maintenance_requests m
             JOIN units un ON un.unit_id = m.unit_id
             JOIN properties p ON p.property_id = un.property_id
             WHERE m.request_id = ?';
        $check_params = [$request_id];
        if ($_SESSION['role_name'] !== 'admin') {
            $check_sql .= ' AND p.manager_id = ?';
            $check_params[] = $_SESSION['user_id'];
        }
        $check = $pdo->prepare($check_sql);
        $check->execute($check_params);
        if (!$check->fetch()) {
            $error = 'That request was not found or is not assigned to your properties.';
        } else {
            $sql =
                'UPDATE maintenance_requests m
                 JOIN units un ON un.unit_id = m.unit_id
                 JOIN properties p ON p.property_id = un.property_id
                 SET m.status = ?, m.closed_at = ? WHERE m.request_id = ?';
            $params = [$status, $status === 'closed' ? date('Y-m-d H:i:s') : null, $request_id];
            if ($_SESSION['role_name'] !== 'admin') {
                $sql .= ' AND p.manager_id = ?';
                $params[] = $_SESSION['user_id'];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }
}

$sql =
    "SELECT m.request_id, m.category, m.description, m.status, m.created_at,
            u.full_name, u.phone, p.name AS property_name, un.unit_label
     FROM maintenance_requests m
     JOIN users u ON u.user_id = m.tenant_id
     JOIN units un ON un.unit_id = m.unit_id
     JOIN properties p ON p.property_id = un.property_id";
if ($_SESSION['role_name'] !== 'admin') {
    $stmt = $pdo->prepare($sql . ' WHERE p.manager_id = ? ORDER BY m.created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll();
} else {
    $requests = $pdo->query($sql . ' ORDER BY m.created_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance requests - RentEase</title>
    <style>
        body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:1200px;margin:32px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:18px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #dce3e8;padding:10px;vertical-align:top}
        select,button{font:inherit;padding:7px;border:1px solid #aab7bf;border-radius:5px}button{background:#0e6b63;color:#fff;cursor:pointer}
        .error{color:#a52121}
    </style>
</head>
<body>
<main>
    <p><a href="index.php">← Manager dashboard</a></p>
    <section class="card">
        <h1>Maintenance requests</h1>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Tenant / unit</th><th>Issue</th><th>Reported</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['full_name']) ?><br><?= e($request['phone']) ?><br><?= e($request['property_name']) ?> · <?= e($request['unit_label']) ?></td>
                    <td><?= e(ucfirst($request['category'])) ?><br><?= nl2br(e($request['description'])) ?></td>
                    <td><?= e($request['created_at']) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                            <select name="status">
                                <?php foreach (['open', 'assigned', 'in_progress', 'closed'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $request['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?><tr><td colspan="4">There are no maintenance requests.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
