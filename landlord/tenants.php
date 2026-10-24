<?php
// landlord/tenants.php - READ-ONLY list of the tenants living in the landlord's properties.
// There are no forms that change data here on purpose: landlords can look but not edit tenants.
require_once __DIR__ . '/layout.php';
require_role('landlord');
ensure_expense_table($pdo);

$uid = (int) $_SESSION['user_id'];
[, $start] = month_range();
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
$overdue_now = is_overdue($start, grace_days($pdo));

$tenants = tenants_for_landlord($pdo, $uid, $start, $search);

page_start('Tenants', 'tenants');
?>
<h1>Tenants</h1>
<section class="card noprint">
    <form method="get" class="inline">
        <label>Search by name, unit or property
            <input type="search" name="q" value="<?= h($search) ?>" maxlength="60"></label>
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?><a class="btn alt" href="tenants.php">Clear</a><?php endif; ?>
    </form>
</section>

<section class="card">
    <p class="sub">View only. Tenant details are managed by your property manager.
       Payment status is for <?= h(date('F Y', strtotime($start))) ?>.</p>
    <div class="wrap"><table>
        <thead><tr><th>Tenant</th><th>Unit</th><th>Lease</th><th class="num">Rent</th><th>This month</th><th>Last paid month</th></tr></thead>
        <tbody>
        <?php foreach ($tenants as $t): ?>
            <tr>
                <td><?= h($t['full_name']) ?><br><span class="sub"><?= h($t['phone']) ?> · <?= h($t['email']) ?></span></td>
                <td><?= h($t['property_name']) ?><br><span class="sub">Unit <?= h($t['unit_label']) ?></span></td>
                <td><?= h(date('d M Y', strtotime($t['start_date']))) ?> to <?= h(date('d M Y', strtotime($t['end_date']))) ?><br>
                    <?= $t['status'] === 'active' ? tag('Active', 'good') : tag(pretty($t['status']), 'warn') ?></td>
                <td class="num"><?= h(kes($t['monthly_rent'])) ?></td>
                <td><?php if ((int) $t['paid_this_month'] > 0): echo tag('Paid', 'good');
                          elseif ($overdue_now): echo tag('Overdue', 'bad');
                          else: echo tag('Due', 'warn'); endif; ?></td>
                <td><?= $t['last_paid'] ? h(date('M Y', strtotime($t['last_paid']))) : 'None yet' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tenants): ?><tr><td colspan="6">No tenants found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php page_end(); ?>
