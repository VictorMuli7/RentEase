<?php
// landlord/rent.php - how much rent was collected for a chosen month
require_once __DIR__ . '/layout.php';
require_role('landlord');
ensure_expense_table($pdo);

$uid = (int) $_SESSION['user_id'];
[$ym, $start, $end] = month_range($_GET['month'] ?? null);

$expected = expected_rent($pdo, $uid, $start, $end);
$collected = collected_rent($pdo, $uid, $start);
$outstanding = max(0, $expected - $collected);
$rate = $expected > 0 ? min(100, (int) round($collected / $expected * 100)) : 0;

$by_property = collected_by_property($pdo, $uid, $start);
$by_method = collected_by_method($pdo, $uid, $start);
$payments = payments_in_month($pdo, $uid, $start);
$unpaid = unpaid_leases($pdo, $uid, $start, $end);
$overdue = is_overdue($start, grace_days($pdo));

page_start('Rent collected', 'rent');
?>
<h1>Rent collected</h1>
<section class="card noprint">
    <form method="get" class="inline">
        <label>Month <input type="month" name="month" value="<?= h($ym) ?>" required></label>
        <button type="submit">Show</button>
    </form>
</section>

<div class="grid">
    <section class="card"><div class="label">Expected</div><div class="count"><?= h(kes($expected)) ?></div></section>
    <section class="card"><div class="label">Collected</div><div class="count"><?= h(kes($collected)) ?></div>
        <div class="bar"><span style="width:<?= $rate ?>%"></span></div></section>
    <section class="card"><div class="label">Outstanding</div><div class="count"><?= h(kes($outstanding)) ?></div></section>
    <section class="card"><div class="label">Collection rate</div><div class="count"><?= $rate ?>%</div></section>
</div>

<div class="cols">
    <section class="card">
        <h2>By property</h2>
        <div class="wrap"><table>
            <thead><tr><th>Property</th><th class="num">Payments</th><th class="num">Collected</th></tr></thead>
            <tbody>
            <?php foreach ($by_property as $r): ?>
                <tr><td><?= h($r['name']) ?></td><td class="num"><?= (int) $r['payments'] ?></td><td class="num"><?= h(kes($r['total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$by_property): ?><tr><td colspan="3">No rent collected for this month.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <section class="card">
        <h2>By payment method</h2>
        <div class="wrap"><table>
            <thead><tr><th>Method</th><th class="num">Payments</th><th class="num">Collected</th></tr></thead>
            <tbody>
            <?php foreach ($by_method as $r): ?>
                <tr><td><?= h(strtoupper($r['method'])) ?></td><td class="num"><?= (int) $r['payments'] ?></td><td class="num"><?= h(kes($r['total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$by_method): ?><tr><td colspan="3">Nothing to show.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>

<section class="card" style="margin-top:16px">
    <h2>Not yet paid for <?= h(date('F Y', strtotime($start))) ?></h2>
    <div class="wrap"><table>
        <thead><tr><th>Tenant</th><th>Unit</th><th>Phone</th><th class="num">Rent due</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($unpaid as $r): ?>
            <tr><td><?= h($r['full_name']) ?></td><td><?= h($r['property_name']) ?> · <?= h($r['unit_label']) ?></td>
                <td><?= h($r['phone']) ?></td><td class="num"><?= h(kes($r['monthly_rent'])) ?></td>
                <td><?= $overdue ? tag('Overdue', 'bad') : tag('Due', 'warn') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$unpaid): ?><tr><td colspan="5">Everyone has paid for this month.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Payments received</h2>
    <div class="wrap"><table>
        <thead><tr><th>Date</th><th>Tenant</th><th>Unit</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $r): ?>
            <tr><td><?= h(substr((string) $r['paid_at'], 0, 10)) ?></td><td><?= h($r['full_name']) ?></td>
                <td><?= h($r['property_name']) ?> · <?= h($r['unit_label']) ?></td><td><?= h(strtoupper($r['method'])) ?></td>
                <td><?= h($r['reference_no']) ?></td><td class="num"><?= h(kes($r['amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="6">No payments for this month.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php page_end(); ?>
