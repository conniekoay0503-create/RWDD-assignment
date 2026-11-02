
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// Only devices with student recycling consent
// and an eligible recycling status enter this queue.
$devices = db_all(
    $conn,
    "SELECT
        d.deviceID,
        d.device_name,
        d.fault_description,
        d.weight_kg,
        d.status,
        d.submitted_by,
        d.submitted_at,
        c.category_name,
        u.name AS student_name
     FROM devices d
     JOIN categories c
       ON d.categoryID = c.categoryID
     JOIN users u
       ON d.submitted_by = u.tp_number
     WHERE d.status = 'Awaiting Recycle'
       AND d.recycle_consent = 1
     ORDER BY d.submitted_at ASC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Recycling Queue</title>
    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>
        .queue-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .queue-table-wrap {
            overflow-x: auto;
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .queue-table th,
        .queue-table td {
            padding: 14px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .queue-table th {
            background: #eaf5ee;
        }

        .queue-status {
            color: #247a53;
            font-weight: 600;
        }

        .queue-empty {
            padding: 25px;
            text-align: center;
        }
        
        .recycling-success {
            background-color: #e8f8ef;
            border: 1px solid #a3dfbc;
            border-left: 5px solid #239b63;
            color: #176b46;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
        }

        .recycling-success strong {
            display: block;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .recycling-success p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

    </style>
</head>


<body class="public">
<main class="queue-container">

    <?php if (($_GET['success'] ?? '') === '1'): ?>
        <div class="recycling-success" role="status">
            <strong>
                Recycling Completed Successfully!
            </strong>
            <p>
                The device has been recycled,
                student points have been awarded,
                and a notification has been created.
            </p>
        </div>
    <?php endif; ?>

    <div class="queue-header">

        <div>
            <h1>Recycling Queue</h1>
            <p class="muted">
                Devices awaiting recycling processing.
            </p>
        </div>

        <a href="index.php" class="btn btn-primary">
            Back to Dashboard
        </a>
    </div>

    <section class="card">
        <h2>
            Pending Devices (<?= count($devices) ?>)
        </h2>

        <div class="queue-table-wrap">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Device</th>
                        <th>Category</th>
                        <th>Student</th>
                        <th>Weight</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="8" class="queue-empty">
                            No devices awaiting recycling.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices as $device): ?>
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
                                <?= e($device['student_name']) ?>
                            </td>
                            <td>
                                <?= $device['weight_kg'] !== null
                                    ? e($device['weight_kg']) . ' kg'
                                    : 'Not recorded' ?>
                            </td>
                            <td>
                                <?= nice_date(
                                    $device['submitted_at']
                                ) ?>
                            </td>
                            <td class="queue-status">
                                <?= e($device['status']) ?>
                            </td>                           
                            <td>
                                <a class="btn btn-primary"
                                href="process_recycling.php?deviceID=<?= urlencode($device['deviceID']) ?>">
                                    Process Device
                                </a>
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
