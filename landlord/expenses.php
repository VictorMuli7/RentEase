<?php
// landlord/expenses.php - the landlord approves or rejects major maintenance expenses.
// A "major" expense is any repair whose cost is at or above the limit in system_settings.
require_once __DIR__ . '/layout.php';
require_role('landlord');
ensure_expense_table($pdo);

$uid = (int) $_SESSION['user_id'];
$threshold = major_threshold($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);

    if (!$request_id || !in_array($decision, ['approved', 'rejected'], true)) {
        flash('err', 'Choose a valid expense and decision.');
        go('expenses.php');
    }
    if ($decision === 'rejected' && $note === '') {
        flash('err', 'Please write a short reason when rejecting an expense.');
        go('expenses.php');
    }

    try {
        $pdo->beginTransaction();
        // Only the owner of the property may decide, and only on a major expense.
        $stmt = $pdo->prepare(
            "SELECT m.request_id, m.cost, p.manager_id, p.name AS property_name, un.unit_label
             FROM maintenance_requests m
             JOIN units un ON un.unit_id = m.unit_id
             JOIN properties p ON p.property_id = un.property_id
             WHERE m.request_id = ? AND p.owner_id = ? FOR UPDATE"
        );
        $stmt->execute([$request_id, $uid]);
        $expense = $stmt->fetch();
        if (!$expense || $expense['cost'] === null || (float) $expense['cost'] < $threshold) {
            throw new DomainException('That expense was not found or does not need approval.');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM expense_approvals WHERE request_id = ?');
        $stmt->execute([$request_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new DomainException('A decision has already been made on this expense.');
        }

        $stmt = $pdo->prepare('INSERT INTO expense_approvals (request_id, status, decided_by, note) VALUES (?, ?, ?, ?)');
        $stmt->execute([$request_id, $decision, $uid, $note !== '' ? $note : null]);
        audit($pdo, $decision === 'approved' ? 'approve_expense' : 'reject_expense', 'maintenance_requests', $request_id);

        // Tell the property manager so they know whether they can go ahead.
        if ($expense['manager_id']) {
            $message = sprintf(
                'Expense #%d (%s %s, KES %s) was %s by the landlord.',
                $request_id, $expense['property_name'], $expense['unit_label'],
                number_format((float) $expense['cost']), $decision
            );
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, channel, message) VALUES (?, 'in_app', ?)");
            $stmt->execute([(int) $expense['manager_id'], mb_substr($message, 0, 255)]);
        }
        $pdo->commit();
        flash('ok', "Expense #{$request_id} {$decision}.");
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('err', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RentEase expense decision failed: ' . $e->getMessage());
        flash('err', 'The decision could not be saved. Please try again.');
    }
    go('expenses.php');
}

$waiting = major_expenses($pdo, $uid, $threshold, false);
$decided = major_expenses($pdo, $uid, $threshold, true);
$waiting_total = array_sum(array_column($waiting, 'cost'));

page_start('Expenses', 'expenses');
?>
<h1>Major expense approvals</h1>
<p class="sub">Repairs costing <?= h(kes($threshold)) ?> or more need your approval before the manager can start or close the job.</p>

<section class="card">
    <h2>Waiting for your decision (<?= count($waiting) ?><?= $waiting ? ', ' . h(kes($waiting_total)) : '' ?>)</h2>
    <?php foreach ($waiting as $x): ?>
        <div class="card" style="margin-bottom:12px">
            <div class="job-head">
                <strong>#<?= (int) $x['request_id'] ?> · <?= h(pretty($x['category'])) ?> at <?= h($x['property_name']) ?>, unit <?= h($x['unit_label']) ?></strong>
                <strong><?= h(kes($x['cost'])) ?></strong>
            </div>
            <p><?= nl2br(h($x['description'])) ?></p>
            <p class="sub">Vendor: <?= h($x['vendor_name'] ?? 'Not assigned yet') ?> · Reported <?= h(substr((string) $x['created_at'], 0, 10)) ?> · Job: <?= h(pretty($x['job_status'])) ?></p>
            <form method="post" class="inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $x['request_id'] ?>">
                <label style="flex:1;min-width:220px">Note (required if rejecting)
                    <input type="text" name="note" maxlength="255"></label>
                <button type="submit" name="decision" value="approved">Approve</button>
                <button type="submit" name="decision" value="rejected" class="bad">Reject</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$waiting): ?><p>Nothing is waiting for approval.</p><?php endif; ?>
</section>

<section class="card">
    <h2>Decision history</h2>
    <div class="wrap"><table>
        <thead><tr><th>#</th><th>Job</th><th class="num">Cost</th><th>Decision</th><th>Note</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($decided as $x): ?>
            <tr><td><?= (int) $x['request_id'] ?></td>
                <td><?= h(pretty($x['category'])) ?><br><span class="sub"><?= h($x['property_name']) ?> · <?= h($x['unit_label']) ?></span></td>
                <td class="num"><?= h(kes($x['cost'])) ?></td>
                <td><?= $x['decision'] === 'approved' ? tag('Approved', 'good') : tag('Rejected', 'bad') ?></td>
                <td><?= h($x['note'] ?? '') ?></td><td><?= h(substr((string) $x['decided_at'], 0, 10)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$decided): ?><tr><td colspan="6">No decisions yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php page_end(); ?>
