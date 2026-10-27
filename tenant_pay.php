
<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';

require_role('tenant');

$error = '';
$success = '';
$reference = $_GET['ref'] ?? '';

$tenant_id = (int) $_SESSION['user_id'];

// Verify that a requested receipt belongs to this tenant.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $reference !== '') {
    $stmt = $pdo->prepare(
        "SELECT reference_no
         FROM simulated_payments
         WHERE reference_no = ?
           AND tenant_id = ?
           AND status = 'completed'
         LIMIT 1"
    );
    $stmt->execute([$reference, $tenant_id]);

    $verified_reference = $stmt->fetchColumn();

    $reference = $verified_reference ?: '';
}

// Get only this tenant's active leases.
$stmt = $pdo->prepare(
    "SELECT l.lease_id, l.monthly_rent, l.start_date, l.end_date,
            p.name AS property_name, un.unit_label
     FROM leases l
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE l.tenant_id = ?
       AND l.status IN ('active', 'renewal_requested')
     ORDER BY l.start_date DESC"
);
$stmt->execute([$tenant_id]);
$leases = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $lease_id = filter_input(INPUT_POST, 'lease_id', FILTER_VALIDATE_INT);
    $method = $_POST['method'] ?? '';
    $month = $_POST['period_month'] ?? '';
    $period = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');

    if (
        !$lease_id ||
        !in_array($method, ['mpesa', 'card'], true) ||
        !$period ||
        $period->format('Y-m') !== $month ||
        $month > date('Y-m')
    ) {
        $error = 'Please select a valid lease, payment method and rent month.';
    } else {
        try {
            // Verify that this lease belongs to the logged-in tenant.
            $stmt = $pdo->prepare(
                "SELECT lease_id, monthly_rent
                 FROM leases
                 WHERE lease_id = ?
                   AND tenant_id = ?
                   AND status IN ('active', 'renewal_requested')"
            );
            $stmt->execute([$lease_id, $tenant_id]);
            $lease = $stmt->fetch();

            if (!$lease) {
                throw new DomainException('The selected lease is not available.');
            }

            // Keep simulated records separate from genuine payments.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM simulated_payments
                 WHERE lease_id = ?
                   AND period_month = ?
                   AND status = 'completed'"
            );
            $stmt->execute([$lease_id, $period->format('Y-m-d')]);

            if ((int) $stmt->fetchColumn() > 0) {
                throw new DomainException(
                    'A successful simulated payment already exists for this rent month.'
                );
            }

            // Demonstration only: no real money is transferred.
            $status = random_int(1, 100) <= 85 ? 'completed' : 'failed';
            $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));

            $stmt = $pdo->prepare(
                "INSERT INTO simulated_payments
                 (lease_id, tenant_id, amount, method, period_month,
                  reference_no, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $lease_id,
                $tenant_id,
                $lease['monthly_rent'],
                $method,
                $period->format('Y-m-d'),
                $reference,
                $status,
            ]);

            if ($status === 'completed') {
                $success = 'Simulation successful! No real payment was made.';
            } else {
                $error = 'Simulation declined. No real payment was made. You can try again.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('RentEase simulated payment failed: ' . $e->getMessage());
            $error = 'The simulation could not be saved. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulated Rent Payment - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:700px;margin:32px auto;padding:0 20px}
        .card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:20px;margin-bottom:16px}
        label{display:block;margin:14px 0 6px}
        select,input,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px;width:100%;box-sizing:border-box}
        button{background:#0e6b63;color:#fff;border-color:#0e6b63;cursor:pointer;margin-top:18px}
        .notice{background:#fff5d6;padding:12px;border-radius:6px}
        .success{color:#146534}.error{color:#a32121}
        .muted{color:#5b6b72}
    </style>
</head>
<body>
<main>
    <p><a href="my_account.php">← Back to My Account</a></p>
    <h1>Pay Rent</h1>

    <div class="card">
        <p class="notice">
            <strong>DEMO MODE:</strong> This is a simulated transaction.
            No money will be transferred, and it will not affect your actual rent balance.
        </p>

        <?php if ($error !== ''): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success !== '' || $reference !== ''): ?>
    <p class="success"><strong><?= e($success) ?></strong></p>

    <p>
        Simulation reference:
        <strong><?= e($reference) ?></strong>
    </p>

    <p>
        <strong>SIMULATED PAYMENT — NOT AN OFFICIAL PAYMENT RECEIPT</strong>
    </p>

    <p>
        <a href="simulated_receipt.php?ref=<?= urlencode($reference) ?>">
            View / Print Simulated Receipt
        </a>
    </p>
<?php endif; ?>

        <?php if ($leases): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="lease_id">Lease</label>
                <select name="lease_id" id="lease_id" required>
                    <?php foreach ($leases as $lease): ?>
                        <option value="<?= (int) $lease['lease_id'] ?>">
                            <?= e($lease['property_name']) ?>
                            — Unit <?= e($lease['unit_label']) ?>
                            — KES <?= number_format((float) $lease['monthly_rent'], 2) ?>/month
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="period_month">Rent month</label>
                <input type="month" name="period_month" id="period_month"
                       max="<?= e(date('Y-m')) ?>" value="<?= e(date('Y-m')) ?>" required>

                <label for="method">Simulated payment method</label>
                <select name="method" id="method" required>
                    <option value="mpesa">M-Pesa (simulation)</option>
                    <option value="card">Card (simulation)</option>
                </select>

                <button type="submit">Simulate Payment</button>
            </form>
        <?php else: ?>
            <p>You do not have an active lease available for payment.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
