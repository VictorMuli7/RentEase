<?php
session_start();

// Protect page from unauthorized users
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] !== 'manager' && $_SESSION['role_name'] !== 'admin')) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

// Fetch summary counts for the dashboard cards
$prop_count = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$unit_count = $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
$pending_apps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
$pending_maint = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status != 'resolved'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Manager Dashboard - RentEase</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f6f9; }
        .sidebar { width: 250px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar h2 { text-align: center; color: #ffc107; font-size: 22px; margin-bottom: 30px; }
        .sidebar a { padding: 15px 20px; display: block; color: #cfd8dc; text-decoration: none; font-size: 16px; transition: 0.2s; }
        .sidebar a:hover { background: #495057; color: white; }
        .main-content { margin-left: 250px; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .cards { display: flex; gap: 20px; margin-top: 30px; }
        .card { background: white; padding: 25px; border-radius: 8px; flex: 1; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid #007bff; }
        .card.warning { border-left-color: #ffc107; }
        .card.danger { border-left-color: #dc3545; }
        .card.success { border-left-color: #28a745; }
        .card h3 { margin: 0; font-size: 13px; color: #6c757d; text-transform: uppercase; }
        .card p { font-size: 28px; font-weight: bold; margin: 10px 0 0 0; color: #333; }
        .quick-actions { margin-top: 40px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; font-weight: bold; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>RentEase Manager</h2>
        <a href="manager_dashboard.php">🏠 Dashboard</a>
        <a href="add_listing.php">➕ Add Property / Unit</a>
        <a href="applications_list.php">📋 Review Applications</a>
        <a href="manage_maintenance.php">🛠️ Maintenance Tickets</a>
        <a href="payments.php">💳 Rent Payments</a>
        <a href="login.php" style="margin-top: 30px; color: #ff6b6b;">🚪 Logout</a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="header">
            <h2>Welcome Back, Manager</h2>
            <span>System Status: <strong style="color: #28a745;">Online</strong></span>
        </div>

        <!-- Summary Metric Cards -->
        <div class="cards">
            <div class="card">
                <h3>Total Properties</h3>
                <p><?= $prop_count ?></p>
            </div>
            <div class="card success">
                <h3>Total Units</h3>
                <p><?= $unit_count ?></p>
            </div>
            <div class="card warning">
                <h3>Pending Applications</h3>
                <p><?= $pending_apps ?></p>
            </div>
            <div class="card danger">
                <h3>Open Maintenance</h3>
                <p><?= $pending_maint ?></p>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <p>Manage your rental properties, applicant reviews, and maintenance from one place.</p>
            <br>
            <a href="add_listing.php" class="btn">Add New Listing</a>
            <a href="applications_list.php" class="btn" style="background-color: #ffc107; color: #333;">Review Applications</a>
            <a href="manage_maintenance.php" class="btn" style="background-color: #dc3545;">Manage Maintenance</a>
        </div>
    </div>

</body>
</html>