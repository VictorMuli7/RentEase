<?php
// landlord/seed_demo.php
// Adds sample tenants, leases, payments, vendors and repair requests so the
// Landlord pages have something to show. Run it once from the command line,
// AFTER reset_manager.php (which creates the demo landlord and manager):
//
//   php landlord/seed_demo.php
//
// It refuses to run twice, and only for local demonstration data.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/queries.php';

function one(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

$landlord_id = one($pdo, "SELECT user_id FROM users WHERE email = 'landlord@rentease.local'");
$manager_id = one($pdo, "SELECT user_id FROM users WHERE email = 'manager@rentease.local'");
$tenant_role = one($pdo, "SELECT role_id FROM roles WHERE role_name = 'tenant'");
if (!$landlord_id || !$manager_id || !$tenant_role) {
    exit("Run reset_manager.php first so the demo landlord and manager exist.\n");
}
if (one($pdo, "SELECT COUNT(*) FROM users WHERE email = 'amina@demo.local'") > 0) {
    exit("The sample data is already there. Nothing was changed.\n");
}

ensure_expense_table($pdo);
$today = new DateTimeImmutable('today');
$month = fn(int $offset) => $today->modify('first day of this month')->modify(($offset >= 0 ? '+' : '') . "{$offset} months");

$pdo->beginTransaction();
try {
    // Properties and units. The first property and its units come from reset_manager.php.
    $home_id = (int) one($pdo, "SELECT property_id FROM properties WHERE owner_id = ? AND name = 'RentEase Demo Homes'", [$landlord_id]);
    $add_property = function (string $name, string $location, string $type) use ($pdo, $landlord_id, $manager_id): int {
        $pdo->prepare('INSERT INTO properties (owner_id, manager_id, name, location, house_type) VALUES (?, ?, ?, ?, ?)')
            ->execute([$landlord_id, $manager_id, $name, $location, $type]);
        return (int) $pdo->lastInsertId();
    };
    $west_id = $add_property('Westlands Heights', 'Westlands, Nairobi', 'apartment');
    $karen_id = $add_property('Karen Gardens', 'Karen, Nairobi', 'maisonette');

    $add_unit = function (int $property_id, string $label, int $beds, int $rent) use ($pdo): int {
        $found = one($pdo, 'SELECT unit_id FROM units WHERE property_id = ? AND unit_label = ?', [$property_id, $label]);
        if ($found) {
            return (int) $found;
        }
        $pdo->prepare('INSERT INTO units (property_id, unit_label, bedrooms, rent_amount, deposit_amount, available_from) VALUES (?, ?, ?, ?, ?, CURDATE())')
            ->execute([$property_id, $label, $beds, $rent, $rent]);
        return (int) $pdo->lastInsertId();
    };
    $a1 = $add_unit($home_id, 'A1', 2, 45000);
    $add_unit($home_id, 'B2', 1, 28000);
    $w1 = $add_unit($west_id, 'W1', 2, 52000);
    $w2 = $add_unit($west_id, 'W2', 3, 65000);
    $w3 = $add_unit($west_id, 'W3', 1, 30000);
    $add_unit($west_id, 'W4', 1, 30000);
    $k1 = $add_unit($karen_id, 'K1', 4, 120000);
    $add_unit($karen_id, 'K2', 4, 120000);

    // Tenants (they cannot sign in: their password is random and never shown).
    $tenants = [
        ['Amina Wanjiru', 'amina@demo.local', '0713000001', $a1, 45000, 'mpesa'],
        ['Brian Otieno', 'brian@demo.local', '0713000002', $w1, 52000, 'mpesa'],
        ['Cynthia Mwangi', 'cynthia@demo.local', '0713000003', $w2, 65000, 'bank'],
        ['Daniel Kiptoo', 'daniel@demo.local', '0713000004', $k1, 120000, 'mpesa'],
        ['Esther Akinyi', 'esther@demo.local', '0713000005', $w3, 30000, 'cash'],
    ];
    $lease_start = $month(-6)->format('Y-m-d');
    $lease_end = $month(5)->format('Y-m-t');
    $leases = [];
    foreach ($tenants as [$name, $email, $phone, $unit_id, $rent, $method]) {
        $pdo->prepare('INSERT INTO users (role_id, full_name, email, phone, password_hash, is_verified) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$tenant_role, $name, $email, $phone, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
        $tenant_id = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO applications (tenant_id, unit_id, status, reviewed_by) VALUES (?, ?, 'approved', ?)")
            ->execute([$tenant_id, $unit_id, $manager_id]);
        $application_id = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO leases (application_id, unit_id, tenant_id, start_date, end_date, monthly_rent, deposit_paid) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$application_id, $unit_id, $tenant_id, $lease_start, $lease_end, $rent, $rent]);
        $lease_id = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE units SET status = 'occupied' WHERE unit_id = ?")->execute([$unit_id]);
        $leases[] = ['id' => $lease_id, 'tenant' => $tenant_id, 'unit' => $unit_id, 'rent' => $rent, 'method' => $method];
    }

    // Payments: everyone paid the last two months. This month Cynthia and Daniel have not paid yet.
    $n = 1;
    foreach ([-2, -1, 0] as $offset) {
        foreach ($leases as $index => $lease) {
            if ($offset === 0 && in_array($index, [2, 3], true)) {
                continue;
            }
            $period = $month($offset);
            $day_of_month = min((int) $today->format('j'), 2 + $index); // never in the future
            $paid_on = $period->modify('+' . ($day_of_month - 1) . ' days');
            $pdo->prepare("INSERT INTO payments (lease_id, amount, method, reference_no, period_month, status, recorded_by, paid_at) VALUES (?, ?, ?, ?, ?, 'completed', ?, ?)")
                ->execute([$lease['id'], $lease['rent'], $lease['method'], sprintf('DEMO-%02d-%s', $n, $paid_on->format('Ymd')),
                           $period->format('Y-m-d'), $manager_id, $paid_on->format('Y-m-d') . ' 10:15:00']);
            $n++;
        }
    }

    // Vendors.
    $vendor_ids = [];
    foreach ([['Mwangi Plumbing Works', '0722100100', 'plumbing', 4.5], ['BrightSpark Electricals', '0733200200', 'electrical', 4.2],
              ['Solid Build Contractors', '0711300300', 'structural', 3.9], ['HandyFix Services', '0700400400', 'general repairs', 4.0]] as $v) {
        $pdo->prepare('INSERT INTO vendors (name, phone, specialty, rating) VALUES (?, ?, ?, ?)')->execute($v);
        $vendor_ids[] = (int) $pdo->lastInsertId();
    }
    [$plumber, $electrician, $builder] = $vendor_ids;

    // Repair requests: some small, two over the KES 20,000 limit and waiting for the landlord, one already approved.
    $ago = fn(int $days) => $today->modify("-{$days} days")->format('Y-m-d 09:30:00');
    $requests = [
        [$a1, 0, $plumber, 'plumbing', 'Kitchen tap is leaking.', 'closed', 3500, 'Washer replaced.', $ago(40), $ago(37)],
        [$w1, 1, $electrician, 'electrical', 'Socket in the living room sparks when used.', 'in_progress', 6800, null, $ago(6), null],
        [$w2, 2, $builder, 'structural', 'Crack in the bedroom ceiling is getting wider.', 'assigned', 85000, null, $ago(9), null],
        [$k1, 3, $plumber, 'plumbing', 'Burst pipe under the bathroom sink.', 'assigned', 32000, null, $ago(3), null],
        [$w3, 4, null, 'other', 'Front gate lock is broken.', 'open', null, null, $ago(1), null],
        [$a1, 0, $builder, 'structural', 'Roof leaks during heavy rain.', 'closed', 120000, 'Roof sheets replaced.', $ago(75), $ago(60)],
    ];
    $request_ids = [];
    foreach ($requests as [$unit_id, $tenant_index, $vendor_id, $category, $description, $status, $cost, $notes, $created, $closed]) {
        $pdo->prepare('INSERT INTO maintenance_requests (unit_id, tenant_id, vendor_id, category, description, status, cost, resolution_notes, created_at, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$unit_id, $leases[$tenant_index]['tenant'], $vendor_id, $category, $description, $status, $cost, $notes, $created, $closed]);
        $request_ids[] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO expense_approvals (request_id, status, decided_by, decided_at, note) VALUES (?, 'approved', ?, ?, 'Go ahead, the leak is urgent.')")
        ->execute([$request_ids[5], $landlord_id, $ago(70)]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo "Sample data added: 2 more properties, 5 tenants with leases, 3 months of payments, 4 vendors and 6 repair requests.\n";
echo "Sign in as landlord@rentease.local (password printed by reset_manager.php) and open /landlord/index.php\n";
