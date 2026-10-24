<?php
// landlord/layout.php
// Loads the shared project files, then draws the header, menu and footer
// that every Landlord page uses.

require_once __DIR__ . '/../app_helpers.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/queries.php';

// Safe way to print any value inside HTML (the project's e() only accepts strings).
function h($value): string
{
    return e((string) ($value ?? ''));
}

function kes($amount): string
{
    return 'KES ' . number_format((float) $amount);
}

function pretty(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function go(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// One-time message shown after a form is submitted.
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [$type, $message];
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        [$type, $message] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<p class="flash ' . h($type) . '">' . h($message) . '</p>';
    }
}

// Colour-coded label for statuses.
function tag(string $text, string $kind = ''): string
{
    return '<span class="tag ' . h($kind) . '">' . h($text) . '</span>';
}

function page_start(string $title, string $active): void
{
    global $pdo;
    $role = $_SESSION['role_name'];
    $badge = 0;

    if ($role === 'landlord') {
        $badge = count_pending_expenses($pdo, (int) $_SESSION['user_id']);
        $menu = [
            'dashboard' => ['index.php', 'Dashboard'],
            'tenants' => ['tenants.php', 'Tenants'],
            'rent' => ['rent.php', 'Rent collected'],
            'expenses' => ['expenses.php', 'Expenses'],
            'maintenance' => ['maintenance.php', 'Maintenance'],
            'vendors' => ['vendors.php', 'Vendors'],
            'reports' => ['reports.php', 'Reports'],
        ];
        $home = 'index.php';
    } else {
        // Managers and admins only use the maintenance and vendor pages.
        $menu = [
            'maintenance' => ['maintenance.php', 'Maintenance'],
            'vendors' => ['vendors.php', 'Vendors'],
        ];
        $home = $role === 'admin' ? '../admin_dashboard.php' : '../index.php';
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> - RentEase</title>
    <style>
        *{box-sizing:border-box}
        body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17212b}
        header{background:#0e6b63;color:#fff;padding:14px max(16px,calc((100% - 1100px)/2));display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:space-between}
        header a{color:#fff}
        nav{background:#fff;border-bottom:1px solid #dce3e8;padding:0 max(8px,calc((100% - 1100px)/2));display:flex;flex-wrap:wrap}
        nav a{padding:12px 14px;color:#17212b;text-decoration:none;border-bottom:3px solid transparent}
        nav a.on{border-color:#0e6b63;color:#0e6b63;font-weight:600}
        nav a:hover{background:#f0f4f6}
        .badge{background:#c0392b;color:#fff;border-radius:10px;padding:0 7px;font-size:.8rem;margin-left:4px}
        main{max-width:1100px;margin:24px auto;padding:0 16px}
        h1{margin:0 0 14px;font-size:1.5rem}h2{margin:0 0 10px;font-size:1.1rem}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px}
        .card{background:#fff;border:1px solid #dce3e8;border-radius:10px;padding:16px;margin-bottom:16px}
        .grid .card{margin:0}.label{color:#5b6b78;font-size:.85rem}.count{font-size:1.6rem;font-weight:700}
        .sub{color:#5b6b78;font-size:.85rem}
        .wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}
        th,td{text-align:left;padding:9px;border-bottom:1px solid #e5eaee;vertical-align:top}
        th{font-size:.82rem;color:#5b6b78;text-transform:uppercase;letter-spacing:.03em}
        td.num,th.num{text-align:right;white-space:nowrap}tr.total td{font-weight:700;border-top:2px solid #17212b}
        input,select,textarea,button,.btn{font:inherit;padding:7px 9px;border:1px solid #aab7bf;border-radius:6px;background:#fff}
        button,.btn{background:#0e6b63;color:#fff;border-color:#0e6b63;cursor:pointer;text-decoration:none;display:inline-block}
        button.alt,.btn.alt{background:#fff;color:#0e6b63}button.bad{background:#a52121;border-color:#a52121}
        form.inline{display:flex;flex-wrap:wrap;gap:8px;align-items:end}form.inline label{display:flex;flex-direction:column;font-size:.82rem;color:#5b6b78;gap:3px}
        .flash{padding:10px 14px;border-radius:8px;margin:0 0 16px}.flash.ok{background:#e3f4ea;color:#14633a}.flash.err{background:#fbe6e6;color:#a52121}
        .tag{display:inline-block;padding:1px 9px;border-radius:10px;font-size:.8rem;background:#e8eef2}
        .tag.good{background:#e3f4ea;color:#14633a}.tag.warn{background:#fff3d6;color:#8a5a00}.tag.bad{background:#fbe6e6;color:#a52121}
        .bar{background:#e5eaee;border-radius:6px;height:10px;min-width:90px}.bar span{display:block;height:100%;background:#0e6b63;border-radius:6px}
        .cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}.cols .card{margin-bottom:0}
        .job-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px}
        @media print{header,nav,.noprint{display:none}body{background:#fff}.card{border:0;padding:0}}
    </style>
</head>
<body>
<header>
    <span><strong>RentEase · <?= h(ucfirst($role)) ?></strong> · <?= h($_SESSION['user_name']) ?></span>
    <span><a href="<?= h($home) ?>">Home</a> · <a href="../change_password.php">Change password</a> · <a href="../logout.php">Sign out</a></span>
</header>
<nav>
    <?php foreach ($menu as $key => [$url, $text]): ?>
        <a href="<?= h($url) ?>" class="<?= $key === $active ? 'on' : '' ?>"><?= h($text) ?><?php if ($key === 'expenses' && $badge > 0): ?><span class="badge"><?= (int) $badge ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<main>
<?php
    show_flash();
}

function page_end(): void
{
    echo "</main>\n</body>\n</html>\n";
}
