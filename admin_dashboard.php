<?php
session_start();
require_once 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Fetch all submitted applications from the database
try {
    $stmt = $pdo->query("SELECT * FROM applications ORDER BY submitted_at DESC");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $applications = [];
    $error = "Could not fetch applications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentEase - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        .navbar { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .logo { font-size: 22px; font-weight: 700; color: #2563eb; text-decoration: none; }
        .nav-right { display: flex; align-items: center; gap: 20px; font-size: 14px; color: #64748b; }
        .btn-link { background: #eff6ff; color: #2563eb; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; }
        .btn-link:hover { background: #dbeafe; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h1 { font-size: 24px; font-weight: 700; color: #0f172a; }
        .table-card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; color: #475569; font-weight: 600; padding: 16px; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 12px; }
        td { padding: 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        tr:hover { background: #f8fafc; }
        .badge { background: #eff6ff; color: #2563eb; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .empty-state { text-align: center; padding: 50px; color: #64748b; font-size: 15px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="admin_dashboard.php" class="logo">RentEase <span style="font-size: 12px; background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 4px; margin-left: 8px;">Admin</span></a>
        <div class="nav-right">
            <span>Signed in as: <strong><?php echo htmlspecialchars($user_email); ?></strong></span>
            <a href="browse_properties.php" class="btn-link">View Tenant Portal</a>
        </div>
    </nav>

    <div class="container">
        <div class="header-section">
            <h1>Submitted Rental Applications</h1>
            <span class="badge" style="font-size: 14px; padding: 6px 14px;">Total Applications: <?php echo count($applications); ?></span>
        </div>

        <div class="table-card">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    No rental applications have been submitted yet. Try submitting one from the tenant portal!
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Applicant Email</th>
                            <th>Property / Unit</th>
                            <th>Location</th>
                            <th>Rent</th>
                            <th>Move-In Date</th>
                            <th>Employment</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($app['user_email']); ?></strong></td>
                                <td><?php echo htmlspecialchars($app['property_name']); ?> <br><span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($app['unit_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($app['location']); ?></td>
                                <td style="font-weight: 600; color: #16a34a;">KES <?php echo htmlspecialchars($app['monthly_rent']); ?></td>
                                <td><?php echo htmlspecialchars($app['move_in_date']); ?></td>
                                <td><?php echo htmlspecialchars($app['employment']); ?></td>
                                <td style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($app['submitted_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>