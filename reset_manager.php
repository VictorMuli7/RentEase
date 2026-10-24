<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$accounts = [];
$pdo->beginTransaction();
try {
    foreach ([
        'manager' => ['manager@rentease.local', 'RentEase Demo Manager'],
        'landlord' => ['landlord@rentease.local', 'RentEase Demo Landlord'],
        'admin' => ['admin@rentease.local', 'RentEase Demo Admin'],
    ] as $role_name => [$email, $name]) {
        $role_stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ?');
        $role_stmt->execute([$role_name]);
        $role_id = $role_stmt->fetchColumn();
        if (!$role_id) {
            throw new RuntimeException("The {$role_name} role is missing. Import rentease_db.sql first.");
        }

        $phone = '07' . random_int(10000000, 99999999);
        $password = bin2hex(random_bytes(8));
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user_id = $stmt->fetchColumn();
        if ($user_id) {
            $stmt = $pdo->prepare(
                "UPDATE users SET role_id = ?, full_name = ?, phone = ?, password_hash = ?, status = 'active'
                 WHERE user_id = ?"
            );
            $stmt->execute([$role_id, $name, $phone, password_hash($password, PASSWORD_DEFAULT), $user_id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, full_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$role_id, $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $user_id = (int) $pdo->lastInsertId();
        }
        $accounts[$role_name] = ['user_id' => (int) $user_id, 'email' => $email, 'password' => $password];
    }

    $manager_id = $accounts['manager']['user_id'];
    $landlord_id = $accounts['landlord']['user_id'];
    $stmt = $pdo->prepare(
        'SELECT property_id FROM properties WHERE owner_id = ? AND name = ? LIMIT 1'
    );
    $stmt->execute([$landlord_id, 'RentEase Demo Homes']);
    $property_id = $stmt->fetchColumn();
    if (!$property_id) {
        $stmt = $pdo->prepare(
            "INSERT INTO properties (owner_id, manager_id, name, location, house_type, landmarks)
             VALUES (?, ?, 'RentEase Demo Homes', 'Kilimani, Nairobi', 'apartment', 'Near Yaya Centre')"
        );
        $stmt->execute([$landlord_id, $manager_id]);
        $property_id = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM units WHERE property_id = ?');
    $stmt->execute([$property_id]);
    if ((int) $stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO units (property_id, unit_label, bedrooms, rent_amount, deposit_amount, available_from)
             VALUES (?, 'A1', 2, 45000, 45000, CURDATE()), (?, 'B2', 1, 28000, 28000, CURDATE())"
        );
        $stmt->execute([$property_id, $property_id]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo "Local demonstration accounts are ready. These credentials are regenerated each time this script runs.\n";
foreach ($accounts as $role => $account) {
    echo ucfirst($role) . " email: {$account['email']}\n";
    echo ucfirst($role) . " temporary password: {$account['password']}\n";
}
