
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];

// Total devices submitted
$totalDevices = (int) db_value(
    $conn,
    "SELECT COUNT(*)
     FROM devices
     WHERE submitted_by = ?",
    's',
    [$studentTP]
);

// Device totals grouped by current status
$statusSummary = db_all(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM devices
     WHERE submitted_by = ?
     GROUP BY status
     ORDER BY status",
    's',
    [$studentTP]
);

// Repaired devices collected by the student
$repairedDevices = (int) db_value(
    $conn,
    "SELECT COUNT(*)
     FROM devices
     WHERE submitted_by = ?
       AND status = 'Completed'",
    's',
    [$studentTP]
);

// Devices currently marked as recycled
$recycledDevices = (int) db_value(
    $conn,
    "SELECT COUNT(*)
     FROM devices
     WHERE submitted_by = ?
       AND status = 'Recycled'",
    's',
    [$studentTP]
);

// Total recorded weight for completed or recycled devices
$totalWeight = (float) db_value(
    $conn,
    "SELECT COALESCE(SUM(weight_kg), 0)
     FROM devices
     WHERE submitted_by = ?
       AND status IN ('Completed', 'Recycled')",
    's',
    [$studentTP]
);

// Current points balance
$pointsBalance = (int) db_value(
    $conn,
    "SELECT COALESCE(SUM(points), 0)
     FROM points_transactions
     WHERE tp_number = ?",
    's',
    [$studentTP]
);

// Total positive points recorded
$pointsEarned = (int) db_value(
    $conn,
    "SELECT COALESCE(SUM(points), 0)
     FROM points_transactions
     WHERE tp_number = ?
       AND points > 0",
    's',
    [$studentTP]
);

// Total points deducted
$pointsSpent = (int) db_value(
    $conn,
    "SELECT COALESCE(SUM(-points), 0)
     FROM points_transactions
     WHERE tp_number = ?
       AND points < 0",
    's',
    [$studentTP]
);

// Cashback redemption requests
$redemptionSummary = db_one(
    $conn,
    "SELECT COUNT(*) AS total_requests,
            COALESCE(SUM(cash_value), 0) AS requested_value
     FROM redemptions
     WHERE tp_number = ?",
    's',
    [$studentTP]
);

// Recent submitted devices
$recentDevices = db_all(
    $conn,
    "SELECT deviceID, device_name, status, submitted_at
     FROM devices
     WHERE submitted_by = ?
     ORDER BY submitted_at DESC, deviceID DESC
     LIMIT 10",
    's',
    [$studentTP]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Report | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="public">

<main style="max-width: 950px; margin: 40px auto; padding: 20px;">

    <a href="index.php">← Back to Dashboard</a>

    <h1>My E-Waste Report</h1>

    <p>
        View your personal repair, recycling,
        points and cashback activity.
    </p>

    <hr>

    <h2>Device Summary</h2>

    <p>
        <strong>Total Devices Submitted:</strong>
        <?= $totalDevices ?>
    </p>

    <p>
        <strong>Repaired &amp; Collected:</strong>
        <?= $repairedDevices ?>
    </p>

    <p>
        <strong>Recycled Devices:</strong>
        <?= $recycledDevices ?>
    </p>

    <p>
        <strong>Recorded Processed Weight:</strong>
        <?= number_format($totalWeight, 2) ?> kg
    </p>

    <h3>Devices by Current Status</h3>

    <?php if (empty($statusSummary)): ?>
        <p>No devices submitted yet.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Number of Devices</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusSummary as $row): ?>
                    <tr>
                        <td><?= e($row['status']) ?></td>
                        <td><?= (int) $row['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>

    <h2>Points Summary</h2>

    <p>
        <strong>Current Balance:</strong>
        <?= number_format($pointsBalance) ?> points
    </p>

    <p>
        <strong>Total Positive Points:</strong>
        <?= number_format($pointsEarned) ?> points
    </p>

    <p>
        <strong>Total Points Deducted:</strong>
        <?= number_format($pointsSpent) ?> points
    </p>

    <p>
        <a href="rewards.php">View Points &amp; Cashback</a>
    </p>

    <hr>

    <h2>Cashback Summary</h2>

    <p>
        <strong>Redemption Requests:</strong>
        <?= (int) $redemptionSummary['total_requests'] ?>
    </p>

    <p>
        <strong>Total Cashback Requested:</strong>
        <?= e(rm($redemptionSummary['requested_value'])) ?>
    </p>

    <p>
        <small>
            Requested cashback includes pending requests.
            It does not necessarily mean payment has been made.
        </small>
    </p>

    <hr>

    <h2>Recent Devices</h2>

    <?php if (empty($recentDevices)): ?>
        <p>No device activity available.</p>
    <?php else: ?>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($recentDevices as $device): ?>
                        <tr>
                            <td><?= e($device['device_name']) ?></td>

                            <td><?= e($device['status']) ?></td>

                            <td>
                                <?= e(nice_date(
                                    $device['submitted_at'],
                                    false
                                )) ?>
                            </td>

                            <td>
                                <a href="device-details.php?id=<?= (int) $device['deviceID'] ?>">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

</body>
</html>
