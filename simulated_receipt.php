
<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';

require_role('tenant');

$reference = trim($_GET['ref'] ?? '');
$tenant_id = (int) $_SESSION['user_id'];

$sql = "SELECT sp.reference_no, sp.amount, sp.method,
               sp.period_month, sp.status, sp.created_at,
               u.full_name, p.name AS property_name,
               un.unit_label, l.monthly_rent
        FROM simulated_payments sp
        JOIN leases l ON l.lease_id = sp.lease_id
        JOIN users u ON u.user_id = sp.tenant_id
        JOIN units un ON un.unit_id = l.unit_id
        JOIN properties p ON p.property_id = un.property_id
        WHERE sp.reference_no = ?
          AND sp.tenant_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$reference, $tenant_id]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulated Receipt - RentEase</title>
    <style>
        body {
            font: 16px/1.5 Arial, sans-serif;
            background: #f5f7fa;
            color: #17212b;
            padding: 24px;
        }
        .receipt {
            max-width: 600px;
            margin: auto;
            padding: 28px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        h1 { color: #0e6b63; }
        .warning {
            padding: 12px;
            background: #fff1cc;
            border: 1px solid #e3c66b;
            font-weight: bold;
            text-align: center;
        }
        .row { margin: 14px 0; }
        .label { font-weight: bold; }
        button {
            padding: 10px 16px;
            background: #0e6b63;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        @media print {
            body { background: white; padding: 0; }
            .receipt { border: none; }
            button, .back-link { display: none; }
        }
    </style>
</head>
<body>
<div class="receipt">
    <?php if (!$payment): ?>
        <h1>Receipt not found</h1>
        <p>This simulated receipt does not exist or does not belong to your account.</p>
    <?php else: ?>
        <h1>RentEase</h1>
        <h2>Simulated Payment Receipt</h2>

        <p class="warning">
            SIMULATED PAYMENT — NOT AN OFFICIAL PAYMENT RECEIPT
        </p>

        <div class="row">
            <span class="label">Simulation reference:</span>
            <?= e($payment['reference_no']) ?>
        </div>

        <div class="row">
            <span class="label">Tenant:</span>
            <?= e($payment['full_name']) ?>
        </div>

        <div class="row">
            <span class="label">Property:</span>
            <?= e($payment['property_name']) ?>
        </div>

        <div class="row">
            <span class="label">Unit:</span>
            <?= e($payment['unit_label']) ?>
        </div>

        <div class="row">
            <span class="label">Rent month:</span>
            <?= e(date('F Y', strtotime($payment['period_month']))) ?>
        </div>

        <div class="row">
            <span class="label">Simulated amount:</span>
            KES <?= number_format((float) $payment['amount'], 2) ?>
        </div>

        <div class="row">
            <span class="label">Simulated method:</span>
            <?= e(strtoupper($payment['method'])) ?>
        </div>

        <div class="row">
            <span class="label">Simulation status:</span>
            <?= e(ucfirst($payment['status'])) ?>
        </div>

        <div class="row">
            <span class="label">Date simulated:</span>
            <?= e($payment['created_at']) ?>
        </div>

        <p>No real money was transferred. This record does not confirm payment of rent.</p>
        <button onclick="window.print()">Print / Save as PDF</button>
    <?php endif; ?>

    <p class="back-link">
        <a href="my_account.php">Back to My Account</a>
    </p>
</div>
</body>
</html>
