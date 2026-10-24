<?php
// landlord/index.php - the landlord's occupancy dashboard
require_once __DIR__ . '/layout.php';
require_role('landlord');
ensure_expense_table($pdo);

$uid = (int) $_SESSION['user_id'];
[$ym, $start, $end] = month_range();

$properties = occupancy_by_property($pdo, $uid);
$total_units = array_sum(array_column($properties, 'units'));
$occupied = array_sum(array_column($properties, 'occupied'));
$vacant = $total_units - $occupied;
$occupancy = $total_units > 0 ? (int) round($occupied / $total_units * 100) : 0;

$expected = expected_rent($pdo, $uid, $start, $end);
$collected = collected_rent($pdo, $uid, $start);
$outstanding = max(0, $expected - $collected);
$rate = $expected > 0 ? min(100, (int) round($collected / $expected * 100)) : 0;

$pending = count_pending_expenses($pdo, $uid);
$open_jobs = open_maintenance_count($pdo, $uid);
$recent = recent_payments($pdo, $uid);

page_start('Dashboard', 'dashboard');
?>
<h1>Occupancy dashboard</h1>
<div class="grid">
    <section class="card"><div class="label">Properties</div><div class="count"><?= count($properties) ?></div></section>
    <section class="card"><div class="label">Units</div><div class="count"><?= (int) $total_units ?></div>
        <div class="sub"><?= (int) $occupied ?> occupied · <?= (int) $vacant ?> vacant</div></section>
    <section class="card"><div class="label">Occupancy rate</div><div class="count"><?= $occupancy ?>%</div>
        <div class="bar"><span style="width:<?= $occupancy ?>%"></span></div></section>
    <section class="card"><div class="label">Rent collected, <?= h(date('F Y', strtotime($start))) ?></div>
        <div class="count"><?= h(kes($collected)) ?></div><div class="sub">of <?= h(kes($expected)) ?> expected (<?= $rate ?>%)</div></section>
    <section class="card"><div class="label">Still outstanding</div><div class="count"><?= h(kes($outstanding)) ?></div>
        <div class="sub"><a href="rent.php">See who has not paid</a></div></section>
    <section class="card"><div class="label">Expenses to approve</div><div class="count"><?= (int) $pending ?></div>
        <div class="sub"><a href="expenses.php">Review now</a> · <?= (int) $open_jobs ?> open repair jobs</div></section>
</div>

<div class="cols">
    <section class="card">
        <h2>Occupancy by property</h2>
        <div class="wrap"><table>
            <thead><tr><th>Property</th><th class="num">Occupied</th><th>Rate</th></tr></thead>
            <tbody>
            <?php foreach ($properties as $p):
                $pct = (int) $p['units'] > 0 ? (int) round($p['occupied'] / $p['units'] * 100) : 0; ?>
                <tr>
                    <td><?= h($p['name']) ?><br><span class="sub"><?= h($p['location']) ?></span></td>
                    <td class="num"><?= (int) $p['occupied'] ?> / <?= (int) $p['units'] ?></td>
                    <td><div class="bar"><span style="width:<?= $pct ?>%"></span></div><span class="sub"><?= $pct ?>%</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$properties): ?><tr><td colspan="3">No active properties are registered under your account yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Latest payments</h2>
        <div class="wrap"><table>
            <thead><tr><th>Tenant</th><th>For</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= h($r['full_name']) ?><br><span class="sub"><?= h($r['property_name']) ?> · <?= h($r['unit_label']) ?></span></td>
                    <td><?= h(date('M Y', strtotime($r['period_month']))) ?><br><span class="sub"><?= h(strtoupper($r['method'])) ?></span></td>
                    <td class="num"><?= h(kes($r['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="3">No payments yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>
<?php page_end(); ?>
