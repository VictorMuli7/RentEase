```php
<?php
// Include helper functions for security, HTML escaping,
// CSRF protection, and user role verification.
require_once 'app_helpers.php';

// Include the database connection.
require_once 'db_connect.php';

// Allow only managers and administrators to view receipts.
require_role('manager', 'admin');

// Get the receipt reference from the URL.
// Example: receipt.php?ref=RE-ABC123DEF456
$reference = trim($_GET['ref'] ?? '');

// Retrieve the payment and related tenant, property, and lease details.
// The joins connect the payment to the tenant and rented unit.
$sql = "SELECT py.reference_no, py.amount, py.method,
               py.period_month, py.status, py.paid_at,
               u.full_name, p.name AS property_name,
               un.unit_label, l.monthly_rent
        FROM payments py
        JOIN leases l ON l.lease_id = py.lease_id
        JOIN users u ON u.user_id = l.tenant_id
        JOIN units un ON un.unit_id = l.unit_id
        JOIN properties p ON p.property_id = un.property_id
        WHERE py.reference_no = ?";

// Use the receipt reference as the first query parameter.
$params = [$reference];

// If the current user is a manager, restrict access to receipts
// for properties managed by that user.
// Administrators can view receipts for all properties.
if ($_SESSION['role_name'] !== 'admin') {
    $sql .= " AND p.manager_id = ?";
    $params[] = $_SESSION['user_id'];
}

// Prepare and execute the query.
// Prepared statements help protect against SQL injection.
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Retrieve the matching payment record, if one exists.
$payment = $stmt->fetch();

// Return a 404 status if the receipt does not exist
// or the current user is not allowed to view it.
if (!$payment) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Set the character encoding and make the page responsive. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Set the title displayed in the browser tab. -->
    <title>Payment Receipt - RentEase</title>

    <!-- Style the receipt so it is clear and easy to print. -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            padding: 30px;
            color: #17212b;
        }

        /* Centre the receipt and give it a card-like appearance. */
        .receipt {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        /* Highlight the RentEase heading. */
        h1 { color: #0e6b63; }

        /* Add spacing between receipt details. */
        .row { margin: 14px 0; }

        /* Make field labels stand out from their values. */
        .label { font-weight: bold; }

        /* Style the print button. */
        button {
            padding: 10px 16px;
            background: #0e6b63;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        /* When printing, remove the background, borders,
           and buttons that are not needed on the printed receipt. */
        @media print {
            body { background: white; padding: 0; }
            .receipt { border: none; }
            button, .back-link { display: none; }
        }
    </style>
</head>
<body>
<div class="receipt">

    <!-- If no matching payment was found, display an error message. -->
    <?php if (!$payment): ?>
        <h1>Receipt not found</h1>
        <p>The receipt does not exist or you do not have permission to view it.</p>

    <!-- If the payment exists and is accessible, display its details. -->
    <?php else: ?>
        <h1>RentEase</h1>
        <h2>Official Payment Receipt</h2>

        <!-- Display the unique reference generated when the payment was recorded. -->
        <div class="row">
            <span class="label">Receipt reference:</span>
            <?= e($payment['reference_no']) ?>
        </div>

        <!-- Display the name of the tenant who made the payment. -->
        <div class="row">
            <span class="label">Tenant:</span>
            <?= e($payment['full_name']) ?>
        </div>

        <!-- Display the property where the tenant rents a unit. -->
        <div class="row">
            <span class="label">Property:</span>
            <?= e($payment['property_name']) ?>
        </div>

        <!-- Display the specific unit being rented. -->
        <div class="row">
            <span class="label">Unit:</span>
            <?= e($payment['unit_label']) ?>
        </div>

        <!-- Show the month of rent covered by this payment. -->
        <div class="row">
            <span class="label">Rent month:</span>
            <?= e(date('F Y', strtotime($payment['period_month']))) ?>
        </div>

        <!-- Display the payment amount in Kenyan shillings. -->
        <div class="row">
            <span class="label">Amount paid:</span>
            KES <?= number_format((float) $payment['amount'], 2) ?>
        </div>

        <!-- Display how the payment was recorded as having been made. -->
        <div class="row">
            <span class="label">Payment method:</span>
            <?= e(ucfirst($payment['method'])) ?>
        </div>

        <!-- Display the payment's current status in the database. -->
        <div class="row">
            <span class="label">Payment status:</span>
            <?= e(ucfirst($payment['status'])) ?>
        </div>

        <!-- Display the date and time the payment was recorded. -->
        <div class="row">
            <span class="label">Date recorded:</span>
            <?= e($payment['paid_at']) ?>
        </div>

        <!-- Thank the tenant and provide a way to print or save the receipt. -->
        <p>Thank you for your payment.</p>
        <button onclick="window.print()">Print / Save as PDF</button>
    <?php endif; ?>

    <!-- Provide navigation back to the payment management page. -->
    <p class="back-link"><a href="payments.php">Back to payments</a></p>
</div>
</body>
</html>
```