<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('tenant');

$unit_id = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
}

if (!$unit_id) {
    http_response_code(400);
    exit('A valid unit is required.');
}

$stmt = $pdo->prepare(
    "SELECT un.unit_id, un.unit_label, un.bedrooms, un.rent_amount, un.deposit_amount,
            un.status, p.name, p.location, p.house_type
     FROM units un JOIN properties p ON p.property_id = un.property_id
     WHERE un.unit_id = ? AND p.status = 'active'"
);
$stmt->execute([$unit_id]);
$unit = $stmt->fetch();
if (!$unit) {
    http_response_code(404);
    exit('This home is no longer available.');
}

$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $employer = trim($_POST['employer'] ?? '');
    $income = filter_input(INPUT_POST, 'monthly_income', FILTER_VALIDATE_FLOAT);

    if ($unit['status'] !== 'vacant') {
        $error = 'This unit is no longer vacant.';
    } elseif ($income === false || $income === null || $income <= 0) {
        $error = 'Enter a valid monthly income greater than zero.';
    } elseif (strlen($employer) > 100) {
        $error = 'Employer must be 100 characters or fewer.';
    } else {
        $existing = $pdo->prepare(
            "SELECT COUNT(*) FROM applications
             WHERE tenant_id = ? AND unit_id = ? AND status IN ('pending', 'approved')"
        );
        $existing->execute([$_SESSION['user_id'], $unit_id]);

        if ($existing->fetchColumn() > 0) {
            $error = 'You already have a pending application or lease for this unit.';
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO applications (tenant_id, unit_id, employer, monthly_income)
                 VALUES (?, ?, ?, ?)"
            );
            $insert->execute([
                $_SESSION['user_id'],
                $unit_id,
                $employer === '' ? null : $employer,
                $income,
            ]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply for a home - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:650px;margin:40px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:24px}
        label{display:block;font-weight:600;margin-top:16px}input,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px}
        input{width:100%;box-sizing:border-box}button{background:#0e6b63;color:#fff;border-color:#0e6b63;margin-top:20px;cursor:pointer}
        .error{color:#a52121}.success{color:#176b37}
    </style>
</head>
<body>
<main>
    <p><a href="browse_properties.php">← Back to homes</a></p>
    <section class="card">
        <h1>Apply for <?= e($unit['name']) ?></h1>
        <p><?= e($unit['location']) ?> · Unit <?= e($unit['unit_label']) ?> · <?= (int) $unit['bedrooms'] ?> bedroom<?= (int) $unit['bedrooms'] === 1 ? '' : 's' ?></p>
        <p>Monthly rent: <strong>KES <?= number_format((float) $unit['rent_amount']) ?></strong></p>
        <?php if ($success): ?>
            <p class="success">Application submitted. You can track its status from your <a href="browse_properties.php">tenant account</a>.</p>
        <?php else: ?>
            <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="unit_id" value="<?= (int) $unit['unit_id'] ?>">
                <label for="employer">Employer (optional)</label>
                <input id="employer" name="employer" maxlength="100">
                <label for="monthly_income">Monthly income (KES)</label>
                <input id="monthly_income" name="monthly_income" type="number" min="1" step="0.01" required>
                <button type="submit">Submit application</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
