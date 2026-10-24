<?php
// landlord/vendors.php - the list of repair vendors (plumbers, electricians and so on).
require_once __DIR__ . '/layout.php';
require_role('landlord', 'manager', 'admin');
ensure_expense_table($pdo);

// Reads and checks the add / edit form. Returns [clean values, error message].
function read_vendor_form(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = preg_replace('/[\s-]/', '', (string) ($_POST['phone'] ?? ''));
    $specialty = trim((string) ($_POST['specialty'] ?? ''));
    $rating_raw = trim((string) ($_POST['rating'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) {
        return [null, 'Enter the vendor name (up to 100 characters).'];
    }
    if (!preg_match('/^\+?[0-9]{9,14}$/', $phone)) {
        return [null, 'Enter a valid phone number, for example 0712345678.'];
    }
    if (mb_strlen($specialty) > 50) {
        return [null, 'The specialty is too long (50 characters at most).'];
    }
    $rating = null;
    if ($rating_raw !== '') {
        $rating = filter_var($rating_raw, FILTER_VALIDATE_FLOAT);
        if ($rating === false || $rating < 0 || $rating > 5) {
            return [null, 'The rating must be a number from 0 to 5.'];
        }
        $rating = round($rating, 1);
    }
    return [[$name, $phone, $specialty !== '' ? $specialty : null, $rating], null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $vendor_id = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);

    try {
        if ($action === 'add' || $action === 'edit') {
            [$values, $error] = read_vendor_form();
            if ($error) {
                throw new DomainException($error);
            }
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO vendors (name, phone, specialty, rating) VALUES (?, ?, ?, ?)');
                $stmt->execute($values);
                audit($pdo, 'add_vendor', 'vendors', (int) $pdo->lastInsertId());
                flash('ok', 'Vendor added.');
            } else {
                if (!$vendor_id) {
                    throw new DomainException('Choose a vendor to edit.');
                }
                $stmt = $pdo->prepare('UPDATE vendors SET name = ?, phone = ?, specialty = ?, rating = ? WHERE vendor_id = ?');
                $stmt->execute([...$values, $vendor_id]);
                audit($pdo, 'edit_vendor', 'vendors', $vendor_id);
                flash('ok', 'Vendor updated.');
            }
        } elseif ($action === 'delete') {
            if (!$vendor_id) {
                throw new DomainException('Choose a vendor to remove.');
            }
            // A vendor with past jobs must stay, otherwise the job history would break.
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE vendor_id = ?');
            $stmt->execute([$vendor_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new DomainException('This vendor has maintenance jobs on record, so it cannot be removed.');
            }
            $pdo->prepare('DELETE FROM vendors WHERE vendor_id = ?')->execute([$vendor_id]);
            audit($pdo, 'delete_vendor', 'vendors', $vendor_id);
            flash('ok', 'Vendor removed.');
        } else {
            throw new DomainException('Unknown action.');
        }
    } catch (DomainException $e) {
        flash('err', $e->getMessage());
    } catch (PDOException $e) {
        error_log('RentEase vendor change failed: ' . $e->getMessage());
        flash('err', 'The change could not be saved. Please try again.');
    }
    go('vendors.php');
}

$vendors = vendor_list($pdo);
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($vendors as $v) {
        if ((int) $v['vendor_id'] === (int) $_GET['edit']) {
            $edit = $v;
        }
    }
}

page_start('Vendors', 'vendors');
?>
<h1>Vendors</h1>

<section class="card noprint">
    <h2><?= $edit ? 'Edit vendor' : 'Add a vendor' ?></h2>
    <form method="post" class="inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
        <?php if ($edit): ?><input type="hidden" name="vendor_id" value="<?= (int) $edit['vendor_id'] ?>"><?php endif; ?>
        <label>Name <input name="name" required maxlength="100" value="<?= h($edit['name'] ?? '') ?>"></label>
        <label>Phone <input name="phone" required maxlength="16" value="<?= h($edit['phone'] ?? '') ?>"></label>
        <label>Specialty <input name="specialty" maxlength="50" placeholder="plumbing, electrical..." value="<?= h($edit['specialty'] ?? '') ?>"></label>
        <label>Rating (0 to 5) <input type="number" name="rating" min="0" max="5" step="0.1" style="width:90px" value="<?= h($edit['rating'] ?? '') ?>"></label>
        <button type="submit"><?= $edit ? 'Save changes' : 'Add vendor' ?></button>
        <?php if ($edit): ?><a class="btn alt" href="vendors.php">Cancel</a><?php endif; ?>
    </form>
</section>

<section class="card">
    <div class="wrap"><table>
        <thead><tr><th>Vendor</th><th>Specialty</th><th>Rating</th><th class="num">Jobs</th><th class="num">Total billed</th><th class="noprint"></th></tr></thead>
        <tbody>
        <?php foreach ($vendors as $v): ?>
            <tr>
                <td><?= h($v['name']) ?><br><span class="sub"><?= h($v['phone']) ?></span></td>
                <td><?= h($v['specialty'] ?? '') ?></td>
                <td><?= $v['rating'] !== null ? h(number_format((float) $v['rating'], 1)) . ' / 5' : 'Not rated' ?></td>
                <td class="num"><?= (int) $v['jobs'] ?></td>
                <td class="num"><?= h(kes($v['total_cost'])) ?></td>
                <td class="noprint">
                    <a class="btn alt" href="vendors.php?edit=<?= (int) $v['vendor_id'] ?>">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Remove this vendor?')">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vendor_id" value="<?= (int) $v['vendor_id'] ?>">
                        <button type="submit" class="bad">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$vendors): ?><tr><td colspan="6">No vendors yet. Add the first one above.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php page_end(); ?>
