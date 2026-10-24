<?php
// landlord/queries.php
// All the database queries for the Landlord module are kept here so that the
// page files stay short and easy to read.

// Lease statuses that count as a tenant currently living in the unit.
const LIVE_LEASE = "('active','renewal_requested','termination_requested')";
// Statuses used when working out how much rent was expected in a month.
const RENT_LEASE = "('active','renewal_requested','termination_requested','expired')";

/* ---------- setup and small helpers ---------- */

// The original schema had nowhere to store a landlord's decision on an expense,
// so this small table is created the first time it is needed.
function ensure_expense_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expense_approvals (
            request_id INT PRIMARY KEY,
            status ENUM('approved','rejected') NOT NULL,
            decided_by INT NOT NULL,
            decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            note VARCHAR(255),
            FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
            FOREIGN KEY (decided_by) REFERENCES users(user_id)
        ) ENGINE=InnoDB"
    );
}

function setting(PDO $pdo, string $key, string $default): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

// Any expense at or above this amount needs the landlord's approval.
function major_threshold(PDO $pdo): float
{
    return (float) setting($pdo, 'major_expense_threshold', '20000');
}

function grace_days(PDO $pdo): int
{
    return (int) setting($pdo, 'grace_period_days', '5');
}

// Turns "2026-10" into [ "2026-10", "2026-10-01", "2026-10-31" ].
// A missing or invalid value falls back to the current month.
function month_range(?string $ym = null): array
{
    $date = $ym ? DateTimeImmutable::createFromFormat('!Y-m', $ym) : false;
    if (!$date || $date->format('Y-m') !== $ym) {
        $date = new DateTimeImmutable('first day of this month midnight');
    }
    return [$date->format('Y-m'), $date->format('Y-m-01'), $date->format('Y-m-t')];
}

// Which properties the logged-in user may see.
// landlord = the ones they own, manager = the ones they manage, admin = all.
function scope(): array
{
    $uid = (int) $_SESSION['user_id'];
    return match ($_SESSION['role_name'] ?? '') {
        'landlord' => ['p.owner_id = ?', [$uid]],
        'manager' => ['p.manager_id = ?', [$uid]],
        default => ['1 = 1', []],
    };
}

// Records who did what in the audit_logs table.
function audit(PDO $pdo, string $action, string $table, int $record_id): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$_SESSION['user_id'], $action, $table, $record_id, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/* ---------- occupancy ---------- */

function occupancy_by_property(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare(
        "SELECT p.property_id, p.name, p.location, COUNT(un.unit_id) AS units,
                COALESCE(SUM(un.status = 'occupied'), 0) AS occupied
         FROM properties p LEFT JOIN units un ON un.property_id = p.property_id
         WHERE p.owner_id = ? AND p.status = 'active'
         GROUP BY p.property_id, p.name, p.location ORDER BY p.name"
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

/* ---------- rent ---------- */

function expected_rent(PDO $pdo, int $uid, string $start, string $end): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(l.monthly_rent), 0)
         FROM leases l JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND l.status IN " . RENT_LEASE . "
           AND l.start_date <= ? AND l.end_date >= ?"
    );
    $stmt->execute([$uid, $end, $start]);
    return (float) $stmt->fetchColumn();
}

function collected_rent(PDO $pdo, int $uid, string $start): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(py.amount), 0)
         FROM payments py JOIN leases l ON l.lease_id = py.lease_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND py.status = 'completed' AND py.period_month = ?"
    );
    $stmt->execute([$uid, $start]);
    return (float) $stmt->fetchColumn();
}

function collected_by_property(PDO $pdo, int $uid, string $start): array
{
    $stmt = $pdo->prepare(
        "SELECT p.name, COALESCE(SUM(py.amount), 0) AS total, COUNT(py.payment_id) AS payments
         FROM payments py JOIN leases l ON l.lease_id = py.lease_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND py.status = 'completed' AND py.period_month = ?
         GROUP BY p.property_id, p.name ORDER BY total DESC"
    );
    $stmt->execute([$uid, $start]);
    return $stmt->fetchAll();
}

function collected_by_method(PDO $pdo, int $uid, string $start): array
{
    $stmt = $pdo->prepare(
        "SELECT py.method, SUM(py.amount) AS total, COUNT(*) AS payments
         FROM payments py JOIN leases l ON l.lease_id = py.lease_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND py.status = 'completed' AND py.period_month = ?
         GROUP BY py.method ORDER BY total DESC"
    );
    $stmt->execute([$uid, $start]);
    return $stmt->fetchAll();
}

function payments_in_month(PDO $pdo, int $uid, string $start, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT py.payment_id, py.amount, py.method, py.reference_no, py.paid_at,
                u.full_name, p.name AS property_name, un.unit_label
         FROM payments py JOIN leases l ON l.lease_id = py.lease_id
         JOIN users u ON u.user_id = l.tenant_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND py.status = 'completed' AND py.period_month = ?
         ORDER BY py.paid_at DESC LIMIT " . (int) $limit
    );
    $stmt->execute([$uid, $start]);
    return $stmt->fetchAll();
}

function recent_payments(PDO $pdo, int $uid, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT py.amount, py.method, py.period_month, py.paid_at, u.full_name, p.name AS property_name, un.unit_label
         FROM payments py JOIN leases l ON l.lease_id = py.lease_id
         JOIN users u ON u.user_id = l.tenant_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND py.status = 'completed'
         ORDER BY py.paid_at DESC, py.payment_id DESC LIMIT " . (int) $limit
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

// Tenants who should have paid for the month but have no completed payment.
function unpaid_leases(PDO $pdo, int $uid, string $start, string $end): array
{
    $stmt = $pdo->prepare(
        "SELECT l.lease_id, l.monthly_rent, u.full_name, u.phone, p.name AS property_name, un.unit_label
         FROM leases l JOIN users u ON u.user_id = l.tenant_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND l.status IN " . LIVE_LEASE . "
           AND l.start_date <= ? AND l.end_date >= ?
           AND NOT EXISTS (
               SELECT 1 FROM payments py
               WHERE py.lease_id = l.lease_id AND py.period_month = ? AND py.status = 'completed')
         ORDER BY p.name, un.unit_label"
    );
    $stmt->execute([$uid, $end, $start, $start]);
    return $stmt->fetchAll();
}

// A month counts as overdue once the grace period after its first day is over.
function is_overdue(string $month_start, int $grace): bool
{
    $due = (new DateTimeImmutable($month_start))->modify("+{$grace} days");
    return new DateTimeImmutable('today') > $due;
}

/* ---------- tenants (read only) ---------- */

function tenants_for_landlord(PDO $pdo, int $uid, string $month_start, string $search = ''): array
{
    $sql =
        "SELECT u.full_name, u.phone, u.email, p.name AS property_name, un.unit_label,
                l.start_date, l.end_date, l.monthly_rent, l.status,
                (SELECT COUNT(*) FROM payments py WHERE py.lease_id = l.lease_id
                   AND py.period_month = ? AND py.status = 'completed') AS paid_this_month,
                (SELECT MAX(py.period_month) FROM payments py WHERE py.lease_id = l.lease_id
                   AND py.status = 'completed') AS last_paid
         FROM leases l JOIN users u ON u.user_id = l.tenant_id
         JOIN units un ON un.unit_id = l.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND l.status IN " . LIVE_LEASE;
    $params = [$month_start, $uid];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $sql .= ' AND (u.full_name LIKE ? OR un.unit_label LIKE ? OR p.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY p.name, un.unit_label');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ---------- maintenance, vendors and expenses ---------- */

// All maintenance requests the current user may see, with vendor and approval info.
function maintenance_list(PDO $pdo, string $status = '', int $property_id = 0): array
{
    [$where, $params] = scope();
    $sql =
        "SELECT m.request_id, m.category, m.description, m.status, m.cost, m.resolution_notes,
                m.created_at, m.closed_at, m.vendor_id,
                v.name AS vendor_name, v.phone AS vendor_phone,
                t.full_name AS tenant_name, t.phone AS tenant_phone,
                p.property_id, p.name AS property_name, un.unit_label,
                ea.status AS approval_status, ea.note AS approval_note
         FROM maintenance_requests m
         JOIN units un ON un.unit_id = m.unit_id
         JOIN properties p ON p.property_id = un.property_id
         JOIN users t ON t.user_id = m.tenant_id
         LEFT JOIN vendors v ON v.vendor_id = m.vendor_id
         LEFT JOIN expense_approvals ea ON ea.request_id = m.request_id
         WHERE {$where}";
    if ($status !== '') {
        $sql .= ' AND m.status = ?';
        $params[] = $status;
    }
    if ($property_id > 0) {
        $sql .= ' AND p.property_id = ?';
        $params[] = $property_id;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY m.created_at DESC, m.request_id DESC');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Properties the user can see (used for filter drop-downs).
function visible_properties(PDO $pdo): array
{
    [$where, $params] = scope();
    $stmt = $pdo->prepare("SELECT p.property_id, p.name FROM properties p WHERE {$where} ORDER BY p.name");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function vendor_list(PDO $pdo): array
{
    return $pdo->query(
        "SELECT v.vendor_id, v.name, v.phone, v.specialty, v.rating,
                COUNT(m.request_id) AS jobs, COALESCE(SUM(m.cost), 0) AS total_cost
         FROM vendors v LEFT JOIN maintenance_requests m ON m.vendor_id = v.vendor_id
         GROUP BY v.vendor_id, v.name, v.phone, v.specialty, v.rating ORDER BY v.name"
    )->fetchAll();
}

// Expenses at or above the threshold, split into waiting and already decided.
function major_expenses(PDO $pdo, int $uid, float $threshold, bool $decided): array
{
    $sql =
        "SELECT m.request_id, m.category, m.description, m.cost, m.status AS job_status, m.created_at,
                v.name AS vendor_name, p.name AS property_name, un.unit_label,
                ea.status AS decision, ea.note, ea.decided_at
         FROM maintenance_requests m
         JOIN units un ON un.unit_id = m.unit_id
         JOIN properties p ON p.property_id = un.property_id
         LEFT JOIN vendors v ON v.vendor_id = m.vendor_id
         LEFT JOIN expense_approvals ea ON ea.request_id = m.request_id
         WHERE p.owner_id = ? AND m.cost >= ? AND ea.request_id IS " . ($decided ? 'NOT NULL' : 'NULL') .
         ' ORDER BY ' . ($decided ? 'ea.decided_at DESC' : 'm.created_at ASC');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $threshold]);
    return $stmt->fetchAll();
}

function count_pending_expenses(PDO $pdo, int $uid): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM maintenance_requests m
         JOIN units un ON un.unit_id = m.unit_id
         JOIN properties p ON p.property_id = un.property_id
         LEFT JOIN expense_approvals ea ON ea.request_id = m.request_id
         WHERE p.owner_id = ? AND m.cost >= ? AND ea.request_id IS NULL"
    );
    $stmt->execute([$uid, major_threshold($pdo)]);
    return (int) $stmt->fetchColumn();
}

function open_maintenance_count(PDO $pdo, int $uid): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM maintenance_requests m
         JOIN units un ON un.unit_id = m.unit_id
         JOIN properties p ON p.property_id = un.property_id
         WHERE p.owner_id = ? AND m.status <> 'closed'"
    );
    $stmt->execute([$uid]);
    return (int) $stmt->fetchColumn();
}

/* ---------- reports ---------- */

// Builds one report as plain data (headers + rows) so the same result can be
// shown on screen or downloaded as CSV. 'money' lists the amount columns and
// 'total' is the column to add up (or null for no total row).
function build_report(PDO $pdo, int $uid, string $type, string $from_ym, string $to_ym): ?array
{
    $from_start = month_range($from_ym)[1];
    $to_start = month_range($to_ym)[1];
    if ($from_start > $to_start) {
        [$from_start, $to_start] = [$to_start, $from_start];
    }
    $to_end = (new DateTimeImmutable($to_start))->format('Y-m-t');
    $range = date('M Y', strtotime($from_start)) . ' to ' . date('M Y', strtotime($to_start));

    switch ($type) {
        case 'occupancy':
            $rows = [];
            foreach (occupancy_by_property($pdo, $uid) as $r) {
                $units = (int) $r['units'];
                $occupied = (int) $r['occupied'];
                $pct = $units > 0 ? round($occupied / $units * 100) : 0;
                $rows[] = [$r['name'], $r['location'], $units, $occupied, $units - $occupied, $pct . '%'];
            }
            return ['title' => 'Occupancy report', 'headers' => ['Property', 'Location', 'Units', 'Occupied', 'Vacant', 'Occupancy'],
                    'rows' => $rows, 'money' => [], 'total' => null];

        case 'rent':
            $stmt = $pdo->prepare(
                "SELECT py.period_month, p.name, un.unit_label, u.full_name, py.amount, py.method, py.reference_no, py.paid_at
                 FROM payments py JOIN leases l ON l.lease_id = py.lease_id
                 JOIN users u ON u.user_id = l.tenant_id
                 JOIN units un ON un.unit_id = l.unit_id
                 JOIN properties p ON p.property_id = un.property_id
                 WHERE p.owner_id = ? AND py.status = 'completed' AND py.period_month BETWEEN ? AND ?
                 ORDER BY py.period_month, p.name, un.unit_label"
            );
            $stmt->execute([$uid, $from_start, $to_start]);
            $rows = [];
            foreach ($stmt->fetchAll() as $r) {
                $rows[] = [date('M Y', strtotime($r['period_month'])), $r['name'], $r['unit_label'], $r['full_name'],
                           (float) $r['amount'], $r['method'], $r['reference_no'], substr((string) $r['paid_at'], 0, 10)];
            }
            return ['title' => "Rent collected ({$range})",
                    'headers' => ['Month', 'Property', 'Unit', 'Tenant', 'Amount (KES)', 'Method', 'Reference', 'Paid on'],
                    'rows' => $rows, 'money' => [4], 'total' => 4];

        case 'arrears':
            $rows = [];
            foreach (unpaid_leases($pdo, $uid, $to_start, $to_end) as $r) {
                $rows[] = [$r['full_name'], $r['phone'], $r['property_name'], $r['unit_label'], (float) $r['monthly_rent']];
            }
            return ['title' => 'Unpaid rent for ' . date('F Y', strtotime($to_start)),
                    'headers' => ['Tenant', 'Phone', 'Property', 'Unit', 'Rent due (KES)'],
                    'rows' => $rows, 'money' => [4], 'total' => 4];

        case 'expenses':
            $stmt = $pdo->prepare(
                "SELECT m.request_id, p.name, un.unit_label, m.category, v.name AS vendor, m.cost, m.status, ea.status AS decision
                 FROM maintenance_requests m
                 JOIN units un ON un.unit_id = m.unit_id
                 JOIN properties p ON p.property_id = un.property_id
                 LEFT JOIN vendors v ON v.vendor_id = m.vendor_id
                 LEFT JOIN expense_approvals ea ON ea.request_id = m.request_id
                 WHERE p.owner_id = ? AND m.cost IS NOT NULL AND DATE(m.created_at) BETWEEN ? AND ?
                 ORDER BY m.created_at"
            );
            $stmt->execute([$uid, $from_start, $to_end]);
            $threshold = major_threshold($pdo);
            $rows = [];
            foreach ($stmt->fetchAll() as $r) {
                $major = (float) $r['cost'] >= $threshold;
                $approval = $major ? ($r['decision'] ?? 'pending') : 'not needed';
                $rows[] = ['#' . $r['request_id'], $r['name'], $r['unit_label'], $r['category'], $r['vendor'] ?? 'Not assigned',
                           (float) $r['cost'], $r['status'], $approval];
            }
            return ['title' => "Maintenance expenses ({$range})",
                    'headers' => ['Request', 'Property', 'Unit', 'Category', 'Vendor', 'Cost (KES)', 'Job status', 'Approval'],
                    'rows' => $rows, 'money' => [5], 'total' => 5];

        case 'tenants':
            $rows = [];
            foreach (tenants_for_landlord($pdo, $uid, month_range()[1]) as $r) {
                $rows[] = [$r['full_name'], $r['phone'], $r['email'], $r['property_name'], $r['unit_label'],
                           $r['start_date'], $r['end_date'], (float) $r['monthly_rent'], str_replace('_', ' ', $r['status'])];
            }
            return ['title' => 'Tenant list',
                    'headers' => ['Tenant', 'Phone', 'Email', 'Property', 'Unit', 'Lease start', 'Lease end', 'Rent (KES)', 'Lease status'],
                    'rows' => $rows, 'money' => [7], 'total' => null];
    }
    return null;
}
