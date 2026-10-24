<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('tenant');

$error = '';
$success = false;
$leases_stmt = $pdo->prepare(
    "SELECT l.unit_id, un.unit_label, p.name
     FROM leases l
     JOIN units un ON un.unit_id = l.unit_id
     JOIN properties p ON p.property_id = un.property_id
     WHERE l.tenant_id = ? AND l.status = 'active'"
);
$leases_stmt->execute([$_SESSION['user_id']]);
$leases = $leases_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $valid_units = array_column($leases, 'unit_id');
    $valid_categories = ['plumbing', 'electrical', 'structural', 'other'];

    if (!$unit_id || !in_array($unit_id, array_map('intval', $valid_units), true)) {
        $error = 'Choose one of your current leased units.';
    } elseif (!in_array($category, $valid_categories, true)) {
        $error = 'Choose a valid maintenance category.';
    } elseif ($description === '' || mb_strlen($description) > 5000) {
        $error = 'Describe the issue (up to 5,000 characters).';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO maintenance_requests (unit_id, tenant_id, category, description) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$unit_id, $_SESSION['user_id'], $category, $description]);
        $success = true;
    }
}

$requests_stmt = $pdo->prepare(
    "SELECT category, description, status, created_at
     FROM maintenance_requests WHERE tenant_id = ? ORDER BY created_at DESC"
);
$requests_stmt->execute([$_SESSION['user_id']]);
$requests = $requests_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        main{max-width:800px;margin:32px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:22px;margin-bottom:16px}
        label{display:block;font-weight:600;margin-top:14px}input,select,textarea,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px}
        select,textarea{width:100%;box-sizing:border-box}button{margin-top:16px;background:#0e6b63;color:#fff;border-color:#0e6b63;cursor:pointer}
        .error{color:#a52121}.success{color:#176b37}
    </style>
</head>
<body>
<main>
    <p><a href="my_account.php">← My account</a></p>
    <section class="card">
        <h1>Report a maintenance issue</h1>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success">Your maintenance request was submitted.</p><?php endif; ?>
        <?php if (!$leases): ?>
            <p>You need an active lease to submit a maintenance request.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="unit_id">Unit</label>
                <select id="unit_id" name="unit_id" required>
                    <?php foreach ($leases as $lease): ?>
                        <option value="<?= (int) $lease['unit_id'] ?>"><?= e($lease['name']) ?> · <?= e($lease['unit_label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="plumbing">Plumbing</option><option value="electrical">Electrical</option>
                    <option value="structural">Structural</option><option value="other">Other</option>
                </select>
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" maxlength="5000" required></textarea>
                <button type="submit">Submit request</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Your requests</h2>
        <?php foreach ($requests as $request): ?>
            <article>
                <strong><?= e(ucfirst($request['category'])) ?> · <?= e(ucwords(str_replace('_', ' ', $request['status']))) ?></strong>
                <p><?= nl2br(e($request['description'])) ?></p>
                <small><?= e($request['created_at']) ?></small>
            </article>
        <?php endforeach; ?>
        <?php if (!$requests): ?><p>No maintenance requests yet.</p><?php endif; ?>
    </section>
</main>
</body>
</html>
