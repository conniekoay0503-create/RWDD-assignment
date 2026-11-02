
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];

$devices = db_all(
    $conn,
    "SELECT
        d.deviceID,
        d.device_name,
        d.fault_description,
        d.status,
        d.submitted_at,
        c.category_name
     FROM devices d
     INNER JOIN categories c
        ON d.categoryID = c.categoryID
     WHERE d.submitted_by = ?
     ORDER BY d.submitted_at DESC, d.deviceID DESC",
    's',
    [$studentTP]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Devices | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="public">

<main style="max-width: 950px; margin: 40px auto; padding: 20px;">

    <h1>My Devices</h1>
    <p>Track your submitted devices and repair progress.</p>

    <p>
        <a class="btn btn-primary" href="submit-device.php">
            Submit New Device
        </a>

        <a class="btn" href="index.php">
            Back to Dashboard
        </a>
    </p>

    <?php if (empty($devices)): ?>

        <p>You haven't submitted any devices yet.</p>

    <?php else: ?>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">

                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td>
                                <?= e($device['device_name']) ?>
                            </td>

                            <td>
                                <?= e($device['category_name']) ?>
                            </td>

                            <td>
                                <?= e($device['status']) ?>
                            </td>

                            <td>
                                <?= e(date(
                                    'd M Y',
                                    strtotime($device['submitted_at'])
                                )) ?>
                            </td>
                            <td>
                                <a
                                    class="btn btn-primary"
                                    href="device-details.php?id=<?= (int) $device['deviceID'] ?>"
                                >
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
