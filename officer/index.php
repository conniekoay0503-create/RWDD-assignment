
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// Dashboard statistics
$totalDevices = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM devices"
);

$awaitingRecycle = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM devices
     WHERE status = 'Awaiting Recycle'"
);

$recycledDevices = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM devices
     WHERE status = 'Recycled'"
);

$pendingConsent = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM devices
     WHERE status = 'Pending Consent'"
);

// Recycling queue
$queue = db_all(
    $conn,
    "SELECT d.deviceID, d.device_name,
            d.status, d.submitted_by,
            d.submitted_at, c.category_name
     FROM devices d
     JOIN categories c
       ON d.categoryID = c.categoryID
     WHERE d.status = 'Awaiting Recycle'
     ORDER BY d.submitted_at ASC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Recycling Officer Dashboard</title>
    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>
        .officer-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .officer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .officer-stats {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .officer-stat {
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 24px;
        }

        .officer-stat h2 {
            font-size: 32px;
            margin: 10px 0;
            color: #247a53;
        }

        .officer-table-wrap {
            overflow-x: auto;
        }

        .officer-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .officer-table th,
        .officer-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .officer-table th {
            background: #eaf5ee;
        }
    </style>
</head>

<body class="public">
<main class="officer-container">

    <div class="officer-header">
        <div>
            <span class="role-tag">
                RECYCLING OFFICER
            </span>
            <h1>
                Welcome,
                <?= e($_SESSION['name']) ?>
            </h1>
            <p class="muted">
                Manage recycling operations and devices.
            </p>
        </div>

        <a class="btn btn-primary"
           href="../logout.php">
            Log out
        </a>
    </div>

    <section class="officer-stats">

        <div class="officer-stat">
            <p>Total Devices</p>
            <h2><?= $totalDevices ?></h2>
        </div>

        <div class="officer-stat">
            <p>Awaiting Recycling</p>
            <h2><?= $awaitingRecycle ?></h2>
        </div>

        <div class="officer-stat">
            <p>Recycled Devices</p>
            <h2><?= $recycledDevices ?></h2>
        </div>

        <div class="officer-stat">
            <p>Pending Consent</p>
            <h2><?= $pendingConsent ?></h2>
        </div>

    </section>

    <section class="card">
        <h2>Recycling Queue</h2>
        
        <a href="recycling_queue.php" class="btn btn-primary">
            View Full Recycling Queue
        </a>

        <p class="muted">
            Devices currently awaiting recycling.
        </p>

        <div class="officer-table-wrap">
            <table class="officer-table">
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Device Name</th>
                        <th>Category</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($queue)): ?>
                    <tr>
                        <td colspan="6">
                            No devices awaiting recycling.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue as $device): ?>
                        <tr>
                        <td>
                            <?= e($device['deviceID']) ?>
                        </td>

                            <td>
                                <?= e($device['device_name']) ?>
                            </td>
                            <td>
                                <?= e($device['category_name']) ?>
                            </td>
                            <td>
                                <?= e($device['submitted_by']) ?>
                            </td>
                            <td>
                                <?= nice_date(
                                    $device['submitted_at']
                                ) ?>
                            </td>
                            <td>
                                <?= e($device['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</body>
</html>
