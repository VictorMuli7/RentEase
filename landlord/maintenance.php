<?php
// landlord/maintenance.php - maintenance request tracking.
//   landlord        : sees every request on their properties (read only)
//   manager / admin : can also assign a vendor, set the cost and update the status
require_once __DIR__ . '/layout.php';
require_role('landlord', 'manager', 'admin');
ensure_expense_table($pdo);

$can_edit = in_array($_SESSION['role_name'], ['manager', 'admin'], true);
$threshold = major_threshold($pdo);
$statuses = ['open', 'assigned', 'in_progress', 'closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$can_edit) {
        http_response_code(403);
        exit('Only the property manager can change maintenance jobs.');
    }

    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $vendor_raw = $_POST['vendor_id'] ?? '';
    $cost_raw = trim((string) ($_POST['cost'] ?? ''));
    $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500);

    try {
        if (!$request_id || !in_array($status, $statuses, true)) {
            throw new DomainException('Choose a valid request and status.');
        }

        // Cost is optional, but if given it must be a sensible amount.
        $cost = null;
        if ($cost_raw !== '') {
            $cost = filter_var($cost_raw, FILTER_VALIDATE_FLOAT);
            if ($cost === false || $cost < 0 || $cost > 99999999) {
                throw new DomainException('Enter the cost as a positive number.');
            }
            $cost = round($cost, 2);
        }

        $pdo->beginTransaction();
        [$where, $params] = scope();
        $stmt = $pdo->prepare(
            "SELECT m.request_id, m.cost, m.closed_at FROM maintenance_requests m
             JOIN units un ON un.unit_id = m.unit_id
             JOIN properties p ON p.property_id = un.property_id
             WHERE m.request_id = ? AND {$where} FOR UPDATE"
        );
        $stmt->execute(array_merge([$request_id], $params));
        $current = $stmt->fetch();
        if (!$current) {
            throw new DomainException('That request was not found on your properties.');
        }

        // Vendor is optional, but if chosen it must exist.
        $vendor_id = null;
        if ($vendor_raw !== '') {
            $vendor_id = filter_var($vendor_raw, FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM vendors WHERE vendor_id = ?');
            $stmt->execute([$vendor_id]);
            if (!$vendor_id || !$stmt->fetchColumn()) {
                throw new DomainException('Choose a vendor from the list.');
            }
        }
        if ($vendor_id === null && in_array($status, ['assigned', 'in_progress'], true)) {
            throw new DomainException('Pick a vendor before marking a job as assigned or in progress.');
        }
        // Picking a vendor on a job that was still "open" assigns it.
        if ($vendor_id !== null && $status === 'open') {
            $status = 'assigned';
        }

        // Major expenses must be approved by the landlord before work starts or the job closes.
        $stmt = $pdo->prepare('SELECT status FROM expense_approvals WHERE request_id = ?');
        $stmt->execute([$request_id]);
        $decision = $stmt->fetchColumn();
        $cost_changed = $current['cost'] !== null && $cost !== null && (float) $current['cost'] !== (float) $cost;

        if ($cost !== null && $cost >= $threshold && in_array($status, ['in_progress', 'closed'], true)) {
            if ($decision === 'rejected') {
                throw new DomainException('The landlord rejected this expense. Lower the cost or ask the landlord.');
            }
            // An approval only counts for the cost that was approved.
            if ($decision !== 'approved' || $cost_changed) {
                throw new DomainException('This job costs ' . kes($cost) . " and needs the landlord's approval first.");
            }
        }
        // If the cost changed after a decision was made, that decision no longer applies.
        if ($decision !== false && $cost_changed) {
            $pdo->prepare('DELETE FROM expense_approvals WHERE request_id = ?')->execute([$request_id]);
        }

        $closed_at = $status === 'closed' ? ($current['closed_at'] ?? date('Y-m-d H:i:s')) : null;
        $stmt = $pdo->prepare(
            'UPDATE maintenance_requests SET status = ?, vendor_id = ?, cost = ?,
                    resolution_notes = ?, closed_at = ? WHERE request_id = ?'
        );
        $stmt->execute([$status, $vendor_id, $cost, $notes !== '' ? $notes : null, $closed_at, $request_id]);
        audit($pdo, 'update_maintenance', 'maintenance_requests', $request_id);
        $pdo->commit();
        flash('ok', "Request #{$request_id} updated.");
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('err', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RentEase maintenance update failed: ' . $e->getMessage());
        flash('err', 'The update could not be saved. Please try again.');
    }
    go('maintenance.php');
}

$filter_status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$filter_property = (int) ($_GET['property'] ?? 0);
$requests = maintenance_list($pdo, $filter_status, $filter_property);
$properties = visible_properties($pdo);
$vendors = $pdo->query('SELECT vendor_id, name, specialty FROM vendors ORDER BY name')->fetchAll();

$counts = array_count_values(array_column($requests, 'status'));
$total_cost = array_sum(array_map(fn($r) => (float) $r['cost'], $requests));

page_start('Maintenance', 'maintenance');
?>
<h1>Maintenance tracking</h1>
<div class="grid">
    <section class="card"><div class="label">Open</div><div class="count"><?= (int) ($counts['open'] ?? 0) ?></div></section>
    <section class="card"><div class="label">Assigned / in progress</div><div class="count"><?= (int) (($counts['assigned'] ?? 0) + ($counts['in_progress'] ?? 0)) ?></div></section>
    <section class="card"><div class="label">Closed</div><div class="count"><?= (int) ($counts['closed'] ?? 0) ?></div></section>
    <section class="card"><div class="label">Total repair cost</div><div class="count"><?= h(kes($total_cost)) ?></div></section>
</div>

<section class="card noprint">
    <form method="get" class="inline">
        <label>Status
            <select name="status"><option value="">All</option>
                <?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= h(pretty($s)) ?></option><?php endforeach; ?>
            </select></label>
        <label>Property
            <select name="property"><option value="0">All</option>
                <?php foreach ($properties as $p): ?><option value="<?= (int) $p['property_id'] ?>" <?= $filter_property === (int) $p['property_id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option><?php endforeach; ?>
            </select></label>
        <button type="submit">Filter</button>
    </form>
</section>

<?php foreach ($requests as $r):
    $major = $r['cost'] !== null && (float) $r['cost'] >= $threshold;
    $approval = $major ? ($r['approval_status'] ?? 'pending') : null; ?>
    <section class="card">
        <div class="job-head">
            <span><strong>#<?= (int) $r['request_id'] ?> · <?= h(pretty($r['category'])) ?></strong>
                · <?= h($r['property_name']) ?>, unit <?= h($r['unit_label']) ?></span>
            <span>
                <?= $r['status'] === 'closed' ? tag('Closed', 'good') : ($r['status'] === 'open' ? tag('Open', 'warn') : tag(pretty($r['status']))) ?>
                <?php if ($approval === 'approved'): echo tag('Expense approved', 'good');
                      elseif ($approval === 'rejected'): echo tag('Expense rejected', 'bad');
                      elseif ($approval === 'pending'): echo tag('Awaiting landlord', 'warn'); endif; ?>
            </span>
        </div>
        <p><?= nl2br(h($r['description'])) ?></p>
        <p class="sub">Reported by <?= h($r['tenant_name']) ?> (<?= h($r['tenant_phone']) ?>) on <?= h(substr((string) $r['created_at'], 0, 10)) ?>
            <?= $r['closed_at'] ? ' · Closed ' . h(substr((string) $r['closed_at'], 0, 10)) : '' ?></p>

        <?php if ($can_edit): ?>
            <form method="post" class="inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                <label>Status
                    <select name="status"><?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= h(pretty($s)) ?></option><?php endforeach; ?></select></label>
                <label>Vendor
                    <select name="vendor_id"><option value="">None</option>
                        <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['vendor_id'] ?>" <?= (int) $r['vendor_id'] === (int) $v['vendor_id'] ? 'selected' : '' ?>><?= h($v['name']) ?><?= $v['specialty'] ? ' (' . h($v['specialty']) . ')' : '' ?></option><?php endforeach; ?>
                    </select></label>
                <label>Cost (KES) <input type="number" name="cost" min="0" step="0.01" value="<?= h($r['cost'] ?? '') ?>" style="width:120px"></label>
                <label style="flex:1;min-width:180px">Resolution notes <input type="text" name="notes" maxlength="500" value="<?= h($r['resolution_notes'] ?? '') ?>"></label>
                <button type="submit">Save</button>
            </form>
        <?php else: ?>
            <p><strong>Vendor:</strong> <?= $r['vendor_name'] ? h($r['vendor_name']) . ' (' . h($r['vendor_phone']) . ')' : 'Not assigned yet' ?>
               · <strong>Cost:</strong> <?= $r['cost'] !== null ? h(kes($r['cost'])) : 'Not quoted yet' ?>
               <?php if ($r['resolution_notes']): ?><br><strong>Resolution:</strong> <?= h($r['resolution_notes']) ?><?php endif; ?>
               <?php if ($approval === 'pending'): ?><br><a href="expenses.php">This expense is waiting for your approval.</a><?php endif; ?></p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php if (!$requests): ?><section class="card"><p>No maintenance requests found.</p></section><?php endif; ?>
<?php page_end(); ?>
