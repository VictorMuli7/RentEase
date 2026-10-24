<?php
// landlord/reports.php - reports the landlord can view, download as CSV, or print / save as PDF.
require_once __DIR__ . '/layout.php';
require_role('landlord');
ensure_expense_table($pdo);

$uid = (int) $_SESSION['user_id'];
$types = [
    'occupancy' => 'Occupancy by property',
    'rent' => 'Rent collected',
    'arrears' => 'Unpaid rent (arrears)',
    'expenses' => 'Maintenance expenses',
    'tenants' => 'Tenant list',
];
$type = array_key_exists($_GET['type'] ?? '', $types) ? $_GET['type'] : 'occupancy';
$from = month_range($_GET['from'] ?? date('Y-m', strtotime('first day of -2 months')))[0];
$to = month_range($_GET['to'] ?? null)[0];

$report = build_report($pdo, $uid, $type, $from, $to);
$money = $report['money'];

// CSV download. Cells starting with = + - @ get an apostrophe in front so Excel
// cannot run them as a formula (CSV injection).
if (($_GET['format'] ?? '') === 'csv') {
    $safe = function ($value): string {
        $text = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rentease_' . $type . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // lets Excel read the file as UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, $report['headers'], ',', '"', '');
    foreach ($report['rows'] as $row) {
        fputcsv($out, array_map($safe, $row), ',', '"', '');
    }
    if ($report['total'] !== null) {
        $line = array_fill(0, count($report['headers']), '');
        $line[0] = 'Total';
        $line[$report['total']] = array_sum(array_column($report['rows'], $report['total']));
        fputcsv($out, $line, ',', '"', '');
    }
    fclose($out);
    exit;
}

$csv_url = 'reports.php?' . http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'format' => 'csv']);

page_start('Reports', 'reports');
?>
<h1>Reports</h1>
<section class="card noprint">
    <form method="get" class="inline">
        <label>Report
            <select name="type"><?php foreach ($types as $key => $text): ?><option value="<?= h($key) ?>" <?= $key === $type ? 'selected' : '' ?>><?= h($text) ?></option><?php endforeach; ?></select></label>
        <label>From month <input type="month" name="from" value="<?= h($from) ?>" required></label>
        <label>To month <input type="month" name="to" value="<?= h($to) ?>" required></label>
        <button type="submit">Show report</button>
    </form>
</section>

<section class="card">
    <div class="job-head">
        <h2><?= h($report['title']) ?></h2>
        <span class="noprint"><a class="btn alt" href="<?= h($csv_url) ?>">Download CSV</a>
            <button type="button" onclick="window.print()">Print / save as PDF</button></span>
    </div>
    <p class="sub">Generated on <?= h(date('d/m/Y')) ?></p>
    <div class="wrap"><table>
        <thead><tr><?php foreach ($report['headers'] as $i => $head): ?><th class="<?= in_array($i, $money, true) ? 'num' : '' ?>"><?= h($head) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($report['rows'] as $row): ?>
            <tr><?php foreach ($row as $i => $cell): ?><td class="<?= in_array($i, $money, true) ? 'num' : '' ?>"><?= in_array($i, $money, true) ? h(kes($cell)) : h($cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$report['rows']): ?><tr><td colspan="<?= count($report['headers']) ?>">No data for this report.</td></tr>
        <?php elseif ($report['total'] !== null): ?>
            <tr class="total"><?php foreach ($report['headers'] as $i => $head): ?><td class="<?= in_array($i, $money, true) ? 'num' : '' ?>"><?= $i === 0 ? 'Total' : ($i === $report['total'] ? h(kes(array_sum(array_column($report['rows'], $report['total'])))) : '') ?></td><?php endforeach; ?></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php page_end(); ?>
