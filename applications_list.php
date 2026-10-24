<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    $comment = trim($_POST['review_comment'] ?? '');

    if (!$application_id || !in_array($decision, ['approved', 'rejected'], true) || strlen($comment) > 255) {
        $error = 'The application decision is invalid.';
    } else {
        try {
            $pdo->beginTransaction();
            $sql =
                "SELECT a.application_id, a.status, a.tenant_id, a.unit_id,
                        un.status AS unit_status, un.rent_amount, un.deposit_amount
                 FROM applications a JOIN units un ON un.unit_id = a.unit_id
                 JOIN properties p ON p.property_id = un.property_id
                 WHERE a.application_id = ?";
            $params = [$application_id];
            if ($_SESSION['role_name'] !== 'admin') {
                $sql .= ' AND p.manager_id = ?';
                $params[] = $_SESSION['user_id'];
            }
            $stmt = $pdo->prepare($sql . ' FOR UPDATE');
            $stmt->execute($params);
            $application = $stmt->fetch();

            if (!$application || $application['status'] !== 'pending') {
                throw new DomainException('This application has already been reviewed or no longer exists.');
            }
            if ($decision === 'approved' && $application['unit_status'] !== 'vacant') {
                throw new DomainException('The unit is no longer vacant, so this application cannot be approved.');
            }

            $stmt = $pdo->prepare(
                'UPDATE applications SET status = ?, reviewed_by = ?, review_comment = ? WHERE application_id = ?'
            );
            $stmt->execute([
                $decision,
                $_SESSION['user_id'],
                $comment === '' ? null : $comment,
                $application_id,
            ]);

            if ($decision === 'approved') {
                $start = new DateTimeImmutable('today');
                $end = $start->modify('+1 year');
                $stmt = $pdo->prepare(
                    "INSERT INTO leases
                     (application_id, unit_id, tenant_id, start_date, end_date, monthly_rent, deposit_paid)
                     VALUES (?, ?, ?, ?, ?, ?, 0)"
                );
                $stmt->execute([
                    $application_id,
                    $application['unit_id'],
                    $application['tenant_id'],
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                    $application['rent_amount'],
                ]);
                $pdo->prepare("UPDATE units SET status = 'occupied' WHERE unit_id = ?")
                    ->execute([$application['unit_id']]);
            }

            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RentEase application review failed: ' . $e->getMessage());
            $error = 'The decision could not be saved. Please try again.';
        }
    }
}

$sql =
    "SELECT a.application_id, a.status, a.monthly_income, a.employer, a.review_comment, a.submitted_at,
            u.full_name, u.email, u.phone, un.unit_label, un.rent_amount, p.name AS property_name
     FROM applications a
     JOIN users u ON u.user_id = a.tenant_id
     JOIN units un ON un.unit_id = a.unit_id
     JOIN properties p ON p.property_id = un.property_id";
if ($_SESSION['role_name'] !== 'admin') {
    $stmt = $pdo->prepare($sql . ' WHERE p.manager_id = ? ORDER BY a.submitted_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll();
} else {
    $applications = $pdo->query($sql . ' ORDER BY a.submitted_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applications - RentEase</title>
    <style>
        body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:1200px;margin:32px auto;padding:0 20px}.card{background:white;border:1px solid #dce3e8;border-radius:10px;padding:18px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #dce3e8;padding:10px;vertical-align:top}
        input,button{font:inherit;padding:7px;border:1px solid #aab7bf;border-radius:5px}button{cursor:pointer;background:#0e6b63;color:#fff}
        .error{color:#a52121}
    </style>
</head>
<body>
<main>
    <p><a href="index.php">← Manager dashboard</a></p>
    <section class="card">
        <h1>Rental applications</h1>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Applicant</th><th>Property / unit</th><th>Income</th><th>Submitted</th><th>Status / decision</th></tr></thead>
            <tbody>
            <?php foreach ($applications as $application): ?>
                <tr>
                    <td><?= e($application['full_name']) ?><br><?= e($application['email']) ?><br><?= e($application['phone']) ?></td>
                    <td><?= e($application['property_name']) ?> · <?= e($application['unit_label']) ?><br>KES <?= number_format((float) $application['rent_amount']) ?>/month</td>
                    <td>KES <?= number_format((float) $application['monthly_income']) ?><br><?= e($application['employer'] ?? '') ?></td>
                    <td><?= e($application['submitted_at']) ?></td>
                    <td>
                        <strong><?= e(ucfirst($application['status'])) ?></strong>
                        <?php if ($application['review_comment']): ?><p><?= e($application['review_comment']) ?></p><?php endif; ?>
                        <?php if ($application['status'] === 'pending'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="application_id" value="<?= (int) $application['application_id'] ?>">
                                <input name="review_comment" maxlength="255" placeholder="Optional review comment">
                                <button name="decision" value="approved">Approve & create lease</button>
                                <button name="decision" value="rejected">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$applications): ?><tr><td colspan="5">There are no applications yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
