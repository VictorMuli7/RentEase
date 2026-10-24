<?php
session_start();
require_once 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'] ?? ucwords(str_replace(['.', '_'], ' ', explode('@', $user_email)[0]));

// Expanded property listings matching browse_properties.php
$properties = [
    1 => ['name' => 'Kilimani Heights', 'location' => 'Kilimani, Nairobi', 'type' => 'Apartment', 'unit' => '2 Bedroom', 'price' => '45,000'],
    2 => ['name' => 'Westlands Green', 'location' => 'Westlands, Nairobi', 'type' => 'Studio', 'unit' => '1 Bedroom Studio', 'price' => '35,000'],
    3 => ['name' => 'Lavington Gardens', 'location' => 'Lavington, Nairobi', 'type' => 'Townhouse', 'unit' => '3 Bedroom', 'price' => '95,000'],
    4 => ['name' => 'Kileleshwa View', 'location' => 'Kileleshwa, Nairobi', 'type' => 'Apartment', 'unit' => '2 Bedroom', 'price' => '55,000'],
    5 => ['name' => 'Karen Park Suites', 'location' => 'Karen, Nairobi', 'type' => 'House', 'unit' => '4 Bedroom', 'price' => '120,000'],
    6 => ['name' => 'Parklands Terrace', 'location' => 'Parklands, Nairobi', 'type' => 'Apartment', 'unit' => 'Studio', 'price' => '28,000']
];

$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 1;
$property = $properties[$unit_id] ?? $properties[1];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $move_in_date = trim($_POST['move_in_date']);
    $employment   = trim($_POST['employment']);
    $notes        = trim($_POST['notes']);

    if (!empty($move_in_date) && !empty($employment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO applications (user_email, property_name, location, unit_type, monthly_rent, move_in_date, employment, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_email,
                $property['name'],
                $property['location'],
                $property['type'] . ' - ' . $property['unit'],
                $property['price'],
                $move_in_date,
                $employment,
                $notes
            ]);
            $success_message = "Application submitted successfully! Management will review your details and contact you shortly.";
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentEase - Rental Application</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        .navbar { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .logo { font-size: 22px; font-weight: 700; color: #2563eb; text-decoration: none; }
        .nav-right { display: flex; align-items: center; gap: 20px; font-size: 14px; color: #64748b; }
        .back-btn { background: #e2e8f0; color: #334155; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: background 0.2s; }
        .back-btn:hover { background: #cbd5e1; }
        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); overflow: hidden; padding: 40px; }
        h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .subtitle { font-size: 14px; color: #64748b; margin-bottom: 30px; }
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
        .summary-title { font-size: 16px; font-weight: 700; color: #2563eb; margin-bottom: 10px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; color: #475569; }
        .summary-row span.label { font-weight: 600; color: #64748b; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; background: #ffffff; }
        input:focus, textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        textarea { resize: vertical; height: 100px; }
        .btn-submit { width: 100%; padding: 14px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert-success { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 16px; border-radius: 8px; font-size: 15px; text-align: center; margin-bottom: 25px; line-height: 1.5; }
        .alert-error { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center; }
        .btn-return { display: inline-block; margin-top: 15px; padding: 10px 20px; background: #16a34a; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-return:hover { background: #15803d; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="browse_properties.php" class="logo">RentEase</a>
        <div class="nav-right">
            <a href="browse_properties.php" class="back-btn">← Back to Properties</a>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1>Rental Application Form</h1>
            <p class="subtitle">Complete your application for review by property management.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert-success">
                    <?php echo $success_message; ?><br>
                    <a href="browse_properties.php" class="btn-return">Return to Browse Properties</a>
                </div>
            <?php else: ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="summary-box">
                    <div class="summary-title">Selected Unit Details</div>
                    <div class="summary-row"><span class="label">Property Name:</span> <span><?php echo htmlspecialchars($property['name']); ?></span></div>
                    <div class="summary-row"><span class="label">Location:</span> <span><?php echo htmlspecialchars($property['location']); ?></span></div>
                    <div class="summary-row"><span class="label">Unit Type:</span> <span><?php echo htmlspecialchars($property['type']); ?> - <?php echo htmlspecialchars($property['unit']); ?></span></div>
                    <div class="summary-row"><span class="label">Monthly Rent:</span> <span style="font-weight: 700; color: #16a34a;">KES <?php echo htmlspecialchars($property['price']); ?></span></div>
                </div>

                <form method="POST" action="apply.php?unit_id=<?php echo $unit_id; ?>">
                    <div class="form-group">
                        <label for="applicant_name">Applicant Name / Email</label>
                        <input type="text" id="applicant_name" value="<?php echo htmlspecialchars($user_email); ?>" disabled style="background: #f1f5f9; color: #64748b;">
                    </div>

                    <div class="form-group">
                        <label for="move_in_date">Desired Move-In Date</label>
                        <input type="date" id="move_in_date" name="move_in_date" required>
                    </div>

                    <div class="form-group">
                        <label for="employment">Employment / Income Source</label>
                        <input type="text" id="employment" name="employment" placeholder="e.g., Software Engineer at TechCorp" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Comments or Requests (Optional)</label>
                        <textarea id="notes" name="notes" placeholder="Mention any specific preferences..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Submit Rental Application</button>
                </form>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>