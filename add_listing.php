<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = $_POST['house_type'] ?? '';
    $landmarks = trim($_POST['landmarks'] ?? '');
    $label = trim($_POST['unit_label'] ?? '');
    $bedrooms = filter_input(INPUT_POST, 'bedrooms', FILTER_VALIDATE_INT);
    $rent = filter_input(INPUT_POST, 'rent_amount', FILTER_VALIDATE_FLOAT);
    $deposit = filter_input(INPUT_POST, 'deposit_amount', FILTER_VALIDATE_FLOAT);
    $available = trim($_POST['available_from'] ?? '');

    if ($name === '' || strlen($name) > 100 || $location === '' || strlen($location) > 150) {
        $error = 'Enter a property name and location within the allowed lengths.';
    } elseif (!in_array($type, ['apartment', 'bungalow', 'maisonette'], true)) {
        $error = 'Choose a valid house type.';
    } elseif ($label === '' || strlen($label) > 20 || !$bedrooms || $bedrooms < 1 || $bedrooms > 255) {
        $error = 'Enter a unit label and a valid bedroom count.';
    } elseif ($rent === false || $rent <= 0 || $deposit === false || $deposit < 0) {
        $error = 'Rent must be greater than zero and deposit cannot be negative.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO properties (owner_id, manager_id, name, location, house_type, landmarks)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['user_id'],
                $name,
                $location,
                $type,
                $landmarks === '' ? null : $landmarks,
            ]);
            $property_id = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare(
                "INSERT INTO units (property_id, unit_label, bedrooms, rent_amount, deposit_amount, available_from)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $property_id,
                $label,
                $bedrooms,
                $rent,
                $deposit,
                $available === '' ? null : $available,
            ]);
            $pdo->commit();
            $success = 'Property and first unit were added.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RentEase listing creation failed: ' . $e->getMessage());
            $error = $e->getCode() === '23000'
                ? 'That unit label is already in use for this property.'
                : 'The listing could not be saved. Check the entered values and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add listing - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:650px;margin:35px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:24px}
        label{display:block;font-weight:600;margin-top:14px}input,select,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px}
        input,select{width:100%;box-sizing:border-box}button{background:#0e6b63;color:#fff;border-color:#0e6b63;margin-top:20px;cursor:pointer}
        .error{color:#a52121}.success{color:#176b37}
    </style>
</head>
<body>
<main>
    <p><a href="index.php">← Manager dashboard</a></p>
    <section class="card">
        <h1>Add a property and unit</h1>
        <p>New listings will be owned and managed by your account.</p>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= e($success) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="name">Property name</label><input id="name" name="name" maxlength="100" required>
            <label for="location">Location</label><input id="location" name="location" maxlength="150" required>
            <label for="house_type">House type</label>
            <select id="house_type" name="house_type" required>
                <option value="apartment">Apartment</option><option value="bungalow">Bungalow</option><option value="maisonette">Maisonette</option>
            </select>
            <label for="landmarks">Nearby landmarks (optional)</label><input id="landmarks" name="landmarks" maxlength="255">
            <label for="unit_label">Unit label</label><input id="unit_label" name="unit_label" maxlength="20" required>
            <label for="bedrooms">Bedrooms</label><input id="bedrooms" name="bedrooms" type="number" min="1" max="255" required>
            <label for="rent_amount">Monthly rent (KES)</label><input id="rent_amount" name="rent_amount" type="number" min="0.01" step="0.01" required>
            <label for="deposit_amount">Deposit (KES)</label><input id="deposit_amount" name="deposit_amount" type="number" min="0" step="0.01" required>
            <label for="available_from">Available from (optional)</label><input id="available_from" name="available_from" type="date">
            <button type="submit">Save listing</button>
        </form>
    </section>
</main>
</body>
</html>
