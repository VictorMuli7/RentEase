<?php
include 'db_connect.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Insert into properties table
        $stmt = $pdo->prepare("INSERT INTO properties (owner_id, manager_id, name, location, house_type, landmarks, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $_POST['owner_id'],
            $_POST['manager_id'],
            $_POST['name'],
            $_POST['location'],
            $_POST['house_type'],
            $_POST['landmarks']
        ]);
        $property_id = $pdo->lastInsertId();

        // 2. Insert into units table linked to this property
        $stmt_unit = $pdo->prepare("INSERT INTO units (property_id, unit_label, bedrooms, rent_amount, deposit_amount, available_from, status) VALUES (?, ?, ?, ?, ?, ?, 'vacant')");
        $stmt_unit->execute([
            $property_id,
            $_POST['unit_label'],
            $_POST['bedrooms'],
            $_POST['rent_amount'],
            $_POST['deposit_amount'],
            $_POST['available_from']
        ]);

        $pdo->commit();
        $message = "Listing and unit added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RentEase - Property Manager: Add Listing</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2, h3 { color: #0f5132; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #333; }
        input, select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #0f5132; color: white; border: none; padding: 12px 20px; margin-top: 20px; cursor: pointer; border-radius: 4px; font-size: 16px; width: 100%; }
        button:hover { background-color: #198754; }
        .msg { margin-bottom: 20px; padding: 10px; background: #d1e7dd; color: #0f5132; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Property Manager Portal</h2>
        <h3>Add New Property & Unit Listing</h3>
        
        <?php if($message): ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <h3>Property Details</h3>
            <label>Property Name:</label>
            <input type="text" name="name" required placeholder="e.g. Kilimani Heights">

            <label>Location:</label>
            <input type="text" name="location" required placeholder="e.g. Kilimani, Nairobi">

            <label>House Type:</label>
            <select name="house_type" required>
                <option value="apartment">Apartment</option>
                <option value="bungalow">Bungalow</option>
                <option value="maisonette">Maisonette</option>
            </select>

            <label>Landmarks:</label>
            <input type="text" name="landmarks" placeholder="e.g. Near Yaya Centre">

            <label>Owner ID (Landlord User ID):</label>
            <input type="number" name="owner_id" required placeholder="e.g. 3">

            <label>Manager ID (Your User ID):</label>
            <input type="number" name="manager_id" required value="2">

            <h3>Initial Unit Details</h3>
            <label>Unit Label / Number:</label>
            <input type="text" name="unit_label" required placeholder="e.g. B4">

            <label>Bedrooms:</label>
            <input type="number" name="bedrooms" required placeholder="e.g. 2">

            <label>Rent Amount (KES):</label>
            <input type="number" step="0.01" name="rent_amount" required placeholder="45000.00">

            <label>Deposit Amount (KES):</label>
            <input type="number" step="0.01" name="deposit_amount" required placeholder="45000.00">

            <label>Available From:</label>
            <input type="date" name="available_from" required>

            <button type="submit">Save Listing</button>
        </form>
    </div>
</body>
</html>