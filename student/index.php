
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// dashboard numbers
$points = db_value($conn, "SELECT COALESCE(SUM(points),0) FROM points_transactions WHERE tp_number = ?", 's', [$tp]);
$devices = db_value($conn, "SELECT COUNT(*) FROM devices WHERE submitted_by = ?", 's', [$tp]);
$repairing = db_value($conn, "SELECT COUNT(*) FROM devices WHERE submitted_by = ? AND status = 'In Repair'", 's', [$tp]);
$unread = db_value($conn, "SELECT COUNT(*) FROM notifications WHERE tp_number = ? AND is_read = 0", 's', [$tp]);

// devices that need my attention
$actionDevices = db_all($conn, "SELECT deviceID, device_name, status FROM devices
    WHERE submitted_by = ? AND status IN ('Pending Consent','Ready for Collection','Returned Unrepaired')
    ORDER BY status_changed_at DESC LIMIT 4", 's', [$tp]);

// latest devices and announcements
$recent = db_all($conn, "SELECT deviceID, device_name, status, submitted_at FROM devices
    WHERE submitted_by = ? ORDER BY submitted_at DESC LIMIT 5", 's', [$tp]);

$announcements = db_all($conn, "SELECT title, message, created_at FROM announcements
    ORDER BY created_at DESC LIMIT 3");

$page_title = 'Dashboard';
require '../includes/student_top.php';
?>

<section class="hero-card student-hero">
    <div class="hero-card-text">
        <span class="student-eyebrow">STUDENT DASHBOARD</span>
        <h1>Welcome, <?= e(explode(' ', $_SESSION['name'] ?? 'Student')[0]) ?>!</h1>
        <p class="serif-line">Give your electronics a second life.</p>
    </div>
    <div class="hero-actions">
        <a href="submit-device.php" class="btn btn-primary">Submit Device</a>
        <a href="my-devices.php" class="btn btn-sun">My Devices</a>
    </div>
    <!-- Big faint recycle icon in the background, same as the admin and officer dashboards -->
    <span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span>
</section>

<section class="dashboard-stats">
    <div class="card"><strong><?= number_format((int)$points) ?></strong><span>Available Points</span></div>
    <div class="card"><strong><?= (int)$devices ?></strong><span>Devices Submitted</span></div>
    <div class="card"><strong><?= (int)$repairing ?></strong><span>Currently in Repair</span></div>
    <div class="card"><strong><?= (int)$unread ?></strong><span>Unread Notifications</span></div>
</section>

<section class="card student-panel">
    <h2>Needs Your Action (<?= count($actionDevices) ?>)</h2>
    <?php if (!$actionDevices): ?>
        <p>No devices need your attention.</p>
    <?php else: ?>
        <div class="dashboard-list">
        <?php foreach ($actionDevices as $d): ?>
            <div class="dashboard-item">
                <div>
                    <strong><?= e($d['device_name']) ?></strong>
                    <p><?= e($d['status']) ?></p>
                </div>
                <a class="btn btn-primary btn-sm" href="device-details.php?id=<?= urlencode($d['deviceID']) ?>">View</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card student-panel">
    <div class="card-head">
        <h2>My Devices</h2>
        <a href="my-devices.php" class="btn btn-sm">See All</a>
    </div>

    <?php if (!$recent): ?>
        <p>No devices submitted yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Device</th><th>Status</th><th>Date</th><th></th></tr>
            <?php foreach ($recent as $d):
                $class = 'status-' . strtolower(str_replace(' ', '-', $d['status']));
            ?>
                <tr>
                    <td><?= e($d['device_name']) ?></td>
                    <td><span class="badge <?= e($class) ?>"><?= e($d['status']) ?></span></td>
                    <td><?= e(nice_date($d['submitted_at'])) ?></td>
                    <td><a href="device-details.php?id=<?= urlencode($d['deviceID']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    <?php endif; ?>
</section>

<section class="card student-panel">
    <h2>Announcements</h2>
    <?php if (!$announcements): ?>
        <p>No announcements available.</p>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="dashboard-announcement">
                <strong><?= e($a['title']) ?></strong>
                <p><?= e($a['message']) ?></p>
                <small><?= e(nice_date($a['created_at'])) ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require '../includes/student_bottom.php'; ?>
