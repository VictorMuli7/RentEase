<?php
session_start();
require_once 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Automatically generate a clean display name from the email (e.g., "mercymutie@gmail.com" becomes "Mercymutie")
$email_prefix = explode('@', $user_email)[0];
$user_name = ucwords(str_replace(['.', '_'], ' ', $email_prefix));

// Expanded property listings across Nairobi
$properties = [
    [
        'id' => 1,
        'name' => 'Kilimani Heights',
        'location' => 'Kilimani, Nairobi',
        'type' => 'Apartment',
        'unit' => '2 Bedroom',
        'price' => '45,000'
    ],
    [
        'id' => 2,
        'name' => 'Westlands Green',
        'location' => 'Westlands, Nairobi',
        'type' => 'Studio',
        'unit' => '1 Bedroom Studio',
        'price' => '35,000'
    ],
    [
        'id' => 3,
        'name' => 'Lavington Gardens',
        'location' => 'Lavington, Nairobi',
        'type' => 'Townhouse',
        'unit' => '3 Bedroom',
        'price' => '95,000'
    ],
    [
        'id' => 4,
        'name' => 'Kileleshwa View',
        'location' => 'Kileleshwa, Nairobi',
        'type' => 'Apartment',
        'unit' => '2 Bedroom',
        'price' => '55,000'
    ],
    [
        'id' => 5,
        'name' => 'Karen Park Suites',
        'location' => 'Karen, Nairobi',
        'type' => 'House',
        'unit' => '4 Bedroom',
        'price' => '120,000'
    ],
    [
        'id' => 6,
        'name' => 'Parklands Terrace',
        'location' => 'Parklands, Nairobi',
        'type' => 'Apartment',
        'unit' => 'Studio',
        'price' => '28,000'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentEase - Available Properties</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        /* Navbar */
        .navbar { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .logo { font-size: 22px; font-weight: 700; color: #2563eb; text-decoration: none; }
        .nav-right { display: flex; align-items: center; gap: 20px; font-size: 14px; color: #64748b; }
        .logout-btn { background: #fee2e2; color: #dc2626; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: background 0.2s; }
        .logout-btn:hover { background: #fecaca; }

        /* Main Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* Hero Section */
        .hero { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 40px; border-radius: 16px; margin-bottom: 35px; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2); }
        .hero h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .hero p { font-size: 15px; opacity: 0.9; }

        /* Search & Filter Bar */
        .filters { display: flex; gap: 15px; margin-bottom: 30px; }
        .filters input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; background: white; }
        .filters input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        /* Property Grid */
        .section-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
        
        /* Property Card */
        .card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; justify-content: space-between; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -3px rgba(0,0,0,0.1); }
        .card-body { padding: 24px; }
        .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
        .property-detail { display: flex; justify-content: space-between; font-size: 14px; color: #475569; margin-bottom: 8px; }
        .property-detail span.label { font-weight: 600; color: #64748b; }
        .badge { background: #eff6ff; color: #2563eb; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 15px; }
        .price { font-size: 18px; font-weight: 700; color: #16a34a; margin-top: 10px; }
        
        .card-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; }
        .btn-apply { display: block; width: 100%; padding: 12px; background: #2563eb; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        .btn-apply:hover { background: #1d4ed8; }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar">
        <a href="browse_properties.php" class="logo">RentEase</a>
        <div class="nav-right">
            <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>!</span>
            <a href="logout.php" class="logout-btn">Sign Out</a>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Banner -->
        <div class="hero">
            <h1>Find Your Next Home</h1>
            <p>Browse verified apartments, townhouses, and units available across Nairobi.</p>
        </div>

        <!-- Search / Filter Bar -->
        <div class="filters">
            <input type="text" placeholder="Search by location (e.g., Kilimani, Westlands, Karen)...">
        </div>

        <h2 class="section-title">Available Properties & Units (<?php echo count($properties); ?>)</h2>

        <!-- Property Grid -->
        <div class="grid">
            <?php foreach ($properties as $prop): ?>
                <div class="card">
                    <div class="card-body">
                        <span class="badge"><?php echo htmlspecialchars($prop['type']); ?></span>
                        <div class="card-title"><?php echo htmlspecialchars($prop['name']); ?></div>
                        <div class="property-detail">
                            <span class="label">Location:</span>
                            <span><?php echo htmlspecialchars($prop['location']); ?></span>
                        </div>
                        <div class="property-detail">
                            <span class="label">Unit Type:</span>
                            <span><?php echo htmlspecialchars($prop['unit']); ?></span>
                        </div>
                        <div class="price">KES <?php echo htmlspecialchars($prop['price']); ?> <span style="font-size: 12px; font-weight: 400; color: #64748b;">/ month</span></div>
                    </div>
                    <div class="card-footer">
                        <a href="apply.php?unit_id=<?php echo $prop['id']; ?>" class="btn-apply">Apply Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>