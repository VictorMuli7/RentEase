<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('tenant', 'admin');

$search = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare(
    "SELECT un.unit_id, un.unit_label, un.bedrooms, un.rent_amount, un.deposit_amount,
            un.available_from, p.name, p.location, p.house_type, p.landmarks
     FROM units un
     JOIN properties p ON p.property_id = un.property_id
     WHERE p.status = 'active' AND un.status = 'vacant'
       AND (un.available_from IS NULL OR un.available_from <= CURDATE())
       AND (? = '' OR p.name LIKE ? OR p.location LIKE ?)
     ORDER BY p.name, un.rent_amount"
);
$term = '%' . $search . '%';
$stmt->execute([$search, $term, $term]);
$units = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Available homes - RentEase</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        header{display:flex;justify-content:space-between;align-items:center;padding:16px max(20px,calc((100% - 1100px)/2));background:#fff;border-bottom:1px solid #dce3e8}
        a{color:#14685f}main{max-width:1100px;margin:32px auto;padding:0 20px}
        .bar,.card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:20px;margin-bottom:16px}
        .bar{display:flex;gap:10px;flex-wrap:wrap}.bar input{flex:1;min-width:200px}
        input,button{font:inherit;padding:10px;border:1px solid #aab7bf;border-radius:6px}
        button{background:#0e6b63;color:#fff;border-color:#0e6b63;cursor:pointer}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
        .muted{color:#5b6b72}.price{font-size:1.3rem;font-weight:700}
    </style>
</head>
<body>
<header>
    <strong>RentEase · Homes</strong>
    <span><?= e($_SESSION['user_name']) ?> · <a href="my_account.php">My account</a> · <a href="change_password.php">Change password</a> · <a href="logout.php">Sign out</a></span>
</header>
<main>
    <h1>Find your next home</h1>
    <form class="bar" method="get">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search property or location">
        <button type="submit">Search</button>
        <a href="browse_properties.php">Clear</a>
    </form>
    <div class="grid">
        <?php foreach ($units as $unit): ?>
            <article class="card">
                <h2><?= e($unit['name']) ?></h2>
                <p class="muted"><?= e($unit['location']) ?> · <?= e(ucfirst($unit['house_type'])) ?></p>
                <p>Unit <?= e($unit['unit_label']) ?> · <?= (int) $unit['bedrooms'] ?> bedroom<?= (int) $unit['bedrooms'] === 1 ? '' : 's' ?></p>
                <?php if ($unit['landmarks']): ?><p class="muted"><?= e($unit['landmarks']) ?></p><?php endif; ?>
                <p class="price">KES <?= number_format((float) $unit['rent_amount']) ?> / month</p>
                <p>Deposit: KES <?= number_format((float) $unit['deposit_amount']) ?></p>
                <a href="apply.php?unit_id=<?= (int) $unit['unit_id'] ?>">Apply for this unit</a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$units): ?><p class="card">No vacant homes match your search yet.</p><?php endif; ?>
</main>
</body>
</html>
