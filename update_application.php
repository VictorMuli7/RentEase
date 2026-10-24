<?php
require_once 'db_connect.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $app_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
    } else {
        die('Invalid action.');
    }

    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
    if ($stmt->execute([$new_status, $app_id])) {
        header("Location: applications_list.php?msg=updated");
        exit;
    } else {
        echo "Failed to update status.";
    }
}
?>