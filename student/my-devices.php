<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// get all my submitted devices
$devices = db_all($conn, "SELECT d.*, c.category_name FROM devices d
    JOIN categories c ON d.categoryID = c.categoryID
    WHERE d.submitted_by = ? ORDER BY d.submitted_at DESC", 's', [$tp]);

$page_title = 'My Devices';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">📱</span>
    <div>
        <h1>My Devices</h1>
        <p>Track your submitted devices.</p>
    </div>
</div>

<section class="card student-panel">
    <p>
        <a class="btn btn-primary" href="submit-device.php">Submit Device</a>
        <a class="btn" href="index.php">Back</a>
    </p>

    <?php if (!$devices): ?>
        <p>No devices submitted yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Device</th><th>Category</th><th>Status</th><th>Deadline</th><th>Action</th></tr>

            <?php foreach ($devices as $d):
                $status = $d['status'];
                $class = 'status-' . strtolower(str_replace(' ', '-', $status));
                $message = '-';

                // only show overdue after 7 days for unclaimed devices
                if (in_array($status, ['Submitted', 'Dropped Off'])) {
                    $due = strtotime($d['submitted_at'] . ' +7 days');
                    if (time() > $due) $message = 'Overdue - Collect at Green Hub';
                }

                // collection deadline after repair or return
                if (in_array($status, ['Ready for Collection', 'Returned Unrepaired'])) {
                    $due = strtotime($d['status_changed_at'] . ' +7 days');
                    $message = time() > $due
                        ? 'Overdue - Collect at Green Hub'
                        : 'Collect by ' . date('d M Y', $due);
                }

                if ($status === 'Returned Unclaimed')
                    $message = 'Collect at Green Hub';
            ?>
                <tr>
                    <td><?= e($d['device_name']) ?></td>
                    <td><?= e($d['category_name']) ?></td>
                    <td><span class="badge <?= e($class) ?>"><?= e($status) ?></span></td>
                    <td><?= e($message) ?></td>
                    <td>
                        <a class="btn btn-primary" href="device-details.php?id=<?= urlencode($d['deviceID']) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    <?php endif; ?>
</section>

<?php require '../includes/student_bottom.php'; ?>
