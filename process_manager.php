<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Approve or Reject Applications
    if ($action === 'approve' || $action === 'reject') {
        $app_id = intval($_POST['app_id']);
        $unit_id = intval($_POST['unit_id']);
        $tenant_id = intval($_POST['tenant_id']);
        $comment = mysqli_real_escape_string($conn, $_POST['comment']);
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        // Update application status using application_id
        $update_app = "UPDATE applications SET status = '$status', comment = '$comment' WHERE application_id = $app_id";
        mysqli_query($conn, $update_app);

        // If approved, create active lease and mark unit occupied
        if ($action === 'approve') {
            // Fetch rent_amount directly from the unit if not posted
            $unit_res = mysqli_query($conn, "SELECT rent_amount FROM units WHERE unit_id = $unit_id");
            $unit_row = mysqli_fetch_assoc($unit_res);
            $rent = isset($_POST['rent']) && !empty($_POST['rent']) ? intval($_POST['rent']) : intval($unit_row['rent_amount']);

            $start = date('Y-m-d');
            $end = date('Y-m-d', strtotime('+1 year'));

            // Insert into leases using rent_amount
            $create_lease = "INSERT INTO leases (unit_id, tenant_id, rent_amount, start_date, end_date, status) 
                            VALUES ($unit_id, $tenant_id, $rent, '$start', '$end', 'active')";
            mysqli_query($conn, $create_lease);

            // Update unit status using unit_id
            $update_unit = "UPDATE units SET status = 'occupied' WHERE unit_id = $unit_id";
            mysqli_query($conn, $update_unit);
        }

        header("Location: index.php?msg=application_updated");
        exit();
    }

    // 2. Toggle Unit Vacant / Occupied Status
    if ($action === 'toggle_status') {
        $unit_id = intval($_POST['unit_id']);
        $new_status = ($_POST['current_status'] === 'vacant') ? 'occupied' : 'vacant';

        // Update using unit_id primary key
        $query = "UPDATE units SET status = '$new_status' WHERE unit_id = $unit_id";
        mysqli_query($conn, $query);

        header("Location: index.php?msg=unit_updated");
        exit();
    }

    // 3. Send Bulk Message
    if ($action === 'bulk_message') {
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        
        $query = "INSERT INTO messages (sender_role, message, created_at) VALUES ('manager', '$message', NOW())";
        mysqli_query($conn, $query);

        header("Location: index.php?msg=message_sent");
        exit();
    }
}
?>