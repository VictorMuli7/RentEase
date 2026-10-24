<?php
// 1. Database Connection Setup
require_once 'db_connect.php'; 

// Fetch dynamic data from MySQL tables using Victor's exact schema columns
$units_query = "SELECT * FROM units";
$units_result = mysqli_query($conn, $units_query);

$apps_query = "SELECT applications.*, users.full_name as tenant_name, units.unit_label, units.bedrooms 
              FROM applications 
              JOIN users ON applications.tenant_id = users.user_id 
              JOIN units ON applications.unit_id = units.unit_id";
$apps_result = mysqli_query($conn, $apps_query);

$leases_query = "SELECT leases.*, users.full_name as tenant_name, units.unit_label 
                FROM leases 
                JOIN users ON leases.tenant_id = users.user_id 
                JOIN units ON leases.unit_id = units.unit_id";
$leases_result = mysqli_query($conn, $leases_query);

// Summary metrics for Manager Overview
$total_units = mysqli_num_rows($units_result);
$pending_apps_query = "SELECT COUNT(*) as count FROM applications WHERE status = 'pending'";
$pending_apps = mysqli_fetch_assoc(mysqli_query($conn, $pending_apps_query))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>RentEase - Property Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#eef2f4;--sf:#fff;--ink:#16252b;--mu:#5b6b72;--ac:#0e6b63;--ac2:#e6f2f0;--wa:#b4570a;--er:#b3261e;--ok:#1d7a3e;--bd:#d3dce0;box-sizing:border-box}
        @media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#0f1a1e;--sf:#18262b;--ink:#e7eef0;--mu:#9bb0b7;--ac:#4fc3b6;--ac2:#173a38;--wa:#f0a35e;--er:#ff8a80;--ok:#6fd08c;--bd:#2c4047}}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
        h1,h2,h3{font-family:"Bricolage Grotesque",system-ui,sans-serif;margin:0 0 .5rem}
        header{display:flex;align-items:center;gap:1rem;padding:.7rem 1rem;background:var(--ac);color:#fff;flex-wrap:wrap}
        header b{font:700 1.3rem "Bricolage Grotesque",system-ui}header span{opacity:.85;margin-left:auto}
        header a{color:#fff;text-decoration:none;background:#fff3;padding:.3rem .6rem;border-radius:4px}
        nav{display:flex;gap:.25rem;padding:.5rem 1rem;overflow-x:auto;background:var(--sf);border-bottom:1px solid var(--bd)}
        nav button{background:none;border:0;color:var(--mu);padding:.5rem .8rem;border-radius:6px;white-space:nowrap;cursor:pointer}
        nav button.on{background:var(--ac2);color:var(--ac);font-weight:600}
        main{max-width:1000px;margin:0 auto;padding:1rem}
        .card{background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:1rem;margin-bottom:.8rem}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:.8rem}
        .stat{font:700 1.7rem "Bricolage Grotesque",system-ui}
        button,.btn{font:inherit;cursor:pointer;border:1px solid var(--ac);background:var(--ac);color:#fff;border-radius:6px;padding:.4rem .8rem;text-decoration:none;display:inline-block}
        button.g,.btn.g{background:none;color:var(--ac)}
        button.d,.btn.d{background:var(--er);border-color:var(--er);color:#fff}
        input,select,textarea{font:inherit;padding:.4rem;border:1px solid var(--bd);border-radius:6px;background:var(--bg);color:var(--ink);max-width:100%}
        .row{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
        .tag{display:inline-block;padding:.05rem .5rem;border-radius:99px;font-size:.8rem;background:var(--ac2);color:var(--ac)}
        .tag.w{background:#f7e3d0;color:var(--wa)}.tag.e{background:#f9dcda;color:var(--er)}.tag.o{background:#d9f0e0;color:var(--ok)}
        .tbl{overflow-x:auto}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:.4rem .5rem;border-bottom:1px solid var(--bd);vertical-align:top}
        .tab-content{display:none}
        .tab-content.active{display:block}
    </style>
</head>
<body>

<header>
    <b>RentEase</b>
    <span>Property Manager Dashboard</span>
    <a href="logout.php">Sign out</a>
</header>

<nav>
    <button class="nav-btn on" onclick="switchTab('overview')">Overview</button>
    <button class="nav-btn" onclick="switchTab('listings')">Listings</button>
    <button class="nav-btn" onclick="switchTab('applications')">Applications</button>
    <button class="nav-btn" onclick="switchTab('messages')">Bulk Messages</button>
</nav>

<main>
    <!-- 1. OVERVIEW TAB -->
    <div id="overview" class="tab-content active">
        <h1>Manager Overview</h1>
        <p class="mu">Property operations at a glance.</p>
        <div class="grid">
            <div class="card">
                <p class="mu">Total Properties</p>
                <p class="stat"><?php echo $total_units; ?></p>
            </div>
            <div class="card">
                <p class="mu">Applications to Review</p>
                <p class="stat"><?php echo $pending_apps; ?></p>
            </div>
        </div>
    </div>

    <!-- 2. LISTINGS TAB -->
    <div id="listings" class="tab-content">
        <h1>Property Listings</h1>
        <div class="tbl">
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Rent (KES)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($units_result, 0);
                    while($unit = mysqli_fetch_assoc($units_result)): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($unit['unit_label'] . ' - ' . $unit['bedrooms'] . ' BR'); ?></td>
                        <td>KES <?php echo number_format($unit['rent_amount']); ?></td>
                        <td><span class="tag <?php echo $unit['status'] == 'vacant' ? 'o' : 'w'; ?>"><?php echo ucfirst($unit['status']); ?></span></td>
                        <td>
                            <form action="process_manager.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="unit_id" value="<?php echo $unit['unit_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $unit['status']; ?>">
                                <button type="submit" class="g">Mark <?php echo $unit['status'] == 'vacant' ? 'Occupied' : 'Vacant'; ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. APPLICATIONS TAB -->
    <div id="applications" class="tab-content">
        <h1>Tenant Applications</h1>
        <div class="tbl">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($app = mysqli_fetch_assoc($apps_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['tenant_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['unit_label']); ?></td>
                        <td>
                            <span class="tag <?php echo $app['status']=='approved'?'o':($app['status']=='rejected'?'e':'w'); ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($app['status'] == 'pending'): ?>
                                <form action="process_manager.php" method="POST" style="display:flex; gap:0.3rem;">
                                    <input type="hidden" name="app_id" value="<?php echo $app['application_id'] ?? $app['id']; ?>">
                                    <input type="hidden" name="unit_id" value="<?php echo $app['unit_id']; ?>">
                                    <input type="hidden" name="tenant_id" value="<?php echo $app['tenant_id']; ?>">
                                    <input type="text" name="comment" placeholder="Comment" style="width:120px;">
                                    <button type="submit" name="action" value="approve">Approve</button>
                                    <button type="submit" name="action" value="reject" class="d">Reject</button>
                                </form>
                            <?php else: ?>
                                <?php echo htmlspecialchars($app['comment'] ?? '-'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. MESSAGES TAB -->
    <div id="messages" class="tab-content">
        <h1>Send Bulk Message</h1>
        <div class="card">
            <form action="process_manager.php" method="POST">
                <input type="hidden" name="action" value="bulk_message">
                <textarea name="message" rows="3" style="width:100%" placeholder="Type message to all tenants..." required></textarea>
                <p><button type="submit">Send to all tenants</button></p>
            </form>
        </div>
    </div>
</main>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('on'));
    
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('on');
}
</script>

</body>
</html>