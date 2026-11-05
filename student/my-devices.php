
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

<?php $page_title = 'My Devices'; require '../includes/student_top.php'; ?>
<div class="student-page-head"><span class="page-icon"><?= icon('device') ?></span><div><span class="student-eyebrow">STUDENT PORTAL</span><h1>My Devices</h1><p>Manage your e-waste journey in one place.</p></div></div>
<section class="card student-panel">


    
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
                                    href="device-details.php?id=<?= e($device['deviceID']) ?>"
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


</section>
<?php require '../includes/student_bottom.php'; ?>
