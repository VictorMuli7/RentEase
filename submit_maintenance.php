<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_SESSION['user_id'];
    $unit_id = intval($_POST['unit_id']);
    $issue_title = trim($_POST['issue_title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];

    if (!empty($issue_title) && !empty($description) && !empty($unit_id)) {
        $stmt = $pdo->prepare("INSERT INTO maintenance_requests (tenant_id, unit_id, issue_title, description, priority) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$tenant_id, $unit_id, $issue_title, $description, $priority])) {
            $msg = "Maintenance request submitted successfully!";
        } else {
            $msg = "Failed to submit request.";
        }
    } else {
        $msg = "Please fill in all required fields.";
    }
}

// Fetch units assigned to this tenant or all units for demo
$units = $pdo->query("SELECT un.unit_id, un.unit_number, p.name AS property_name FROM units un JOIN properties p ON un.property_id = p.property_id")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Maintenance Request - RentEase</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 30px; }
        .form-card { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #218838; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="form-card">
    <h2>Report Maintenance Issue</h2>
    <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="submit_maintenance.php">
        <div class="form-group">
            <label>Select Unit</label>
            <select name="unit_id" required>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['unit_id'] ?>"><?= htmlspecialchars($u['property_name']) ?> - Unit <?= htmlspecialchars($u['unit_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Issue Title</label>
            <input type="text" name="issue_title" placeholder="e.g. Broken Light Fixture" required>
        </div>
        <div class="form-group">
            <label>Priority</label>
            <select name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>
        </div>
        <div class="form-group">
            <label>Description of Issue</label>
            <textarea name="description" rows="4" placeholder="Describe the problem in detail..." required></textarea>
        </div>
        <button type="submit" class="btn">Submit Ticket</button>
    </form>
</div>

</body>
</html>