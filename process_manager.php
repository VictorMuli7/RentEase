<?php
require_once 'app_helpers.php';
require_once 'db_connect.php';
require_role('manager', 'admin');
verify_csrf();

if (($_POST['action'] ?? '') !== 'toggle_status') {
    http_response_code(400);
    exit('Unknown action.');
}

$unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
if (!$unit_id) {
    http_response_code(400);
    exit('A valid unit is required.');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT un.status, p.manager_id FROM units un JOIN properties p ON p.property_id = un.property_id
         WHERE un.unit_id = ? FOR UPDATE'
    );
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch();
    if (!$unit || ($_SESSION['role_name'] !== 'admin' && (int) $unit['manager_id'] !== (int) $_SESSION['user_id'])) {
        throw new DomainException('The requested unit was not found.');
    }

    if ($unit['status'] === 'occupied') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM leases WHERE unit_id = ?
             AND status IN ('active', 'renewal_requested', 'termination_requested')"
        );
        $stmt->execute([$unit_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new DomainException('A unit with an active lease cannot be marked vacant.');
        }
        $new_status = 'vacant';
    } else {
        $new_status = 'occupied';
    }

    $pdo->prepare('UPDATE units SET status = ? WHERE unit_id = ?')->execute([$new_status, $unit_id]);
    $pdo->commit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(409);
    exit(e($e->getMessage()));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

header('Location: index.php');
exit;
