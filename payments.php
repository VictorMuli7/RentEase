<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $lease_id = filter_input(INPUT_POST, 'lease_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $method = $_POST['method'] ?? '';
    $month = $_POST['period_month'] ?? '';
    $period = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
    $valid_methods = ['mpesa', 'card', 'bank', 'cash', 'cheque'];

    if (!$lease_id || $amount === false || $amount === null || $amount <= 0
        || !in_array($method, $valid_methods, true)
        || !$period || $period->format('Y-m') !== $month) {
        $error = 'Enter a valid lease, positive amount, payment method, and month.';
    } else {
        try {
            $pdo->beginTransaction();
            $lease_sql =
                "SELECT l.lease_id FROM leases l
                 JOIN units un ON un.unit_id = l.unit_id
                 JOIN properties p ON p.property_id = un.property_id
                 WHERE l.lease_id = ? AND l.status IN ('active', 'renewal_requested')";
            $lease_params = [$lease_id];
            if ($_SESSION['role_name'] !== 'admin') {
                $lease_sql .= ' AND p.manager_id = ?';
                $lease_params[] = $_SESSION['user_id'];
            }
            $stmt = $pdo->prepare($lease_sql . ' FOR UPDATE');
            $stmt->execute($lease_params);
            if (!$stmt->fetch()) {
                throw new DomainException('This lease is not active.');
            }
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM payments WHERE lease_id = ? AND period_month = ? AND status = 'completed'"
            );
            $stmt->execute([$lease_id, $period->format('Y-m-d')]);
            if ($stmt->fetchColumn() > 0) {
                throw new DomainException('A completed payment is already recorded for this lease and month.');
            }

            $reference = 'RE-' . strtoupper(bin2hex(random_bytes(6)));
            $stmt = $pdo->prepare(
                "INSERT INTO payments (lease_id, amount, method, reference_no, period_month, status, recorded_by)
                 VALUES (?, ?, ?, ?, ?, 'completed', ?)"
            );
            $stmt->execute([
                $lease_id,
                $amount,
                $method,
                $reference,
                $period->format('Y-m-d'),
                $_SESSION['user_id'],
            ]);
            $pdo->commit();
            $success = 'Payment recorded. Receipt reference: ' . $reference;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RentEase payment recording failed: ' . $e->getMessage());
            $error = 'The payment could not be recorded. Please try again.';
        }
    }
}

$lease_sql =
    "SELECT l.lease_id, l.monthly_rent, u.full_name, p.name AS property_name, un.unit_label
     FROM leases l
     JOIN users u ON u.user_id = l.tenant_id
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE l.status IN ('active', 'renewal_requested')";
if ($_SESSION['role_name'] !== 'admin') {
    $stmt = $pdo->prepare($lease_sql . ' AND p.manager_id = ? ORDER BY p.name, un.unit_label');
    $stmt->execute([$_SESSION['user_id']]);
    $leases = $stmt->fetchAll();
} else {
    $leases = $pdo->query($lease_sql . ' ORDER BY p.name, un.unit_label')->fetchAll();
}
$payment_sql =
    "SELECT py.reference_no, py.period_month, py.amount, py.method, py.status,
            u.full_name, p.name AS property_name, un.unit_label
     FROM payments py
     JOIN leases l ON l.lease_id = py.lease_id
     JOIN users u ON u.user_id = l.tenant_id
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id";
if ($_SESSION['role_name'] !== 'admin') {
    $stmt = $pdo->prepare($payment_sql . ' WHERE p.manager_id = ? ORDER BY py.paid_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll();
} else {
    $payments = $pdo->query($payment_sql . ' ORDER BY py.paid_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rent payments - RentEase</title>
    <style>
        body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:1100px;margin:32px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:20px;margin-bottom:18px}
        label{display:block;font-weight:600;margin-top:12px}input,select,button{font:inherit;padding:9px;border:1px solid #aab7bf;border-radius:6px}
        button{background:#0e6b63;color:#fff;border-color:#0e6b63;margin-top:14px;cursor:pointer}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #dce3e8;padding:9px}
        .error{color:#a52121}.success{color:#176b37}
    </style>
</head>
<body>
<main>
    <p><a href="index.php">← Manager dashboard</a></p>
    <section class="card">
        <h1>Record a rent payment</h1>
        <p>Record payments received through the selected method. Live M-Pesa/card processing is not configured.</p>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= e($success) ?></p><?php endif; ?>
        <?php if ($leases): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="lease_id">Tenant / lease</label>
                <select id="lease_id" name="lease_id" required>
                    <?php foreach ($leases as $lease): ?>
                        <option value="<?= (int) $lease['lease_id'] ?>">
                            <?= e($lease['full_name']) ?> · <?= e($lease['property_name']) ?> <?= e($lease['unit_label']) ?> · KES <?= number_format((float) $lease['monthly_rent']) ?>/month
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="period_month">Rent month</label><input id="period_month" name="period_month" type="month" value="<?= date('Y-m') ?>" required>
                <label for="amount">Amount (KES)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required>
                <label for="method">Payment method</label>
                <select id="method" name="method" required>
                    <?php foreach (['mpesa', 'bank', 'cash', 'card', 'cheque'] as $method): ?>
                        <option value="<?= $method ?>"><?= e(ucfirst($method)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Record payment</button>
            </form>
        <?php else: ?><p>No active leases to collect payments for.</p><?php endif; ?>
    </section>
    <section class="card">
        <h2>Payment history</h2>
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Receipt</th><th>Tenant</th><th>Property / unit</th><th>Month</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['reference_no']) ?></td><td><?= e($payment['full_name']) ?></td>
                    <td><?= e($payment['property_name']) ?> · <?= e($payment['unit_label']) ?></td>
                    <td><?= e(substr($payment['period_month'], 0, 7)) ?></td>
                    <td>KES <?= number_format((float) $payment['amount']) ?></td>
                    <td><?= e(ucfirst($payment['method'])) ?></td><td><?= e(ucfirst($payment['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="7">No payments have been recorded.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
