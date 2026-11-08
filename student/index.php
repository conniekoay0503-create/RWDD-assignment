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

<section class="hero-card">
    <div class="hero-card-text">
        <h1>Welcome, <?= e(explode(' ', $_SESSION['name'] ?? 'Student')[0]) ?>!</h1>
        <p class="serif-line">Give your electronics a second life.</p>
    </div>
    <div class="hero-actions">
        <a href="submit-device.php" class="btn btn-primary"><?= icon('plus') ?> Submit Device</a>
        <a href="my-devices.php" class="btn btn-sun"><?= icon('device') ?> My Devices</a>
    </div>
    <!-- Big faint recycle icon in the background, same as the admin and officer dashboards -->
    <span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span>
</section>

<!-- 1. My numbers: coloured icon + number, like the admin "At a glance" cards. Two of them are links. -->
<h2 class="section-title"><?= icon('chart') ?> At a glance</h2>
<section class="stat-grid four">
    <a class="stat-card row" href="rewards.php">
        <span class="stat-icon tint-gold"><?= icon('star') ?></span>
        <div><strong class="stat-value"><?= number_format((int)$points) ?></strong><span class="stat-title">Available points</span></div>
    </a>
    <a class="stat-card row" href="my-devices.php">
        <span class="stat-icon tint-mint"><?= icon('device') ?></span>
        <div><strong class="stat-value"><?= (int)$devices ?></strong><span class="stat-title">Devices submitted</span></div>
    </a>
    <div class="stat-card row">
        <span class="stat-icon tint-sky"><?= icon('wrench') ?></span>
        <div><strong class="stat-value"><?= (int)$repairing ?></strong><span class="stat-title">Currently in repair</span></div>
    </div>
    <a class="stat-card row" href="notifications.php">
        <span class="stat-icon tint-rose"><?= icon('bell') ?></span>
        <div><strong class="stat-value"><?= (int)$unread ?></strong><span class="stat-title">Unread notifications</span></div>
    </a>
</section>

<!-- 2. The heading is OUTSIDE the box (same as admin "To do") -->
<h2 class="section-title"><?= icon('clock') ?> Needs your action (<?= count($actionDevices) ?>)</h2>
<section class="card">
    <?php if (!$actionDevices): ?>
        <?php empty_state('check', 'No devices need your attention. Nice!'); ?>
    <?php else: ?>
        <ul class="activity">
        <?php foreach ($actionDevices as $d): ?>
            <li class="action-row">
                <span class="activity-dot"><?= icon('alert') ?></span>
                <div>
                    <strong><?= e($d['device_name']) ?></strong>
                    <span class="badge <?= e(status_class($d['status'])) ?>"><?= e($d['status']) ?></span>
                </div>
                <a class="btn btn-primary btn-sm" href="device-details.php?id=<?= urlencode($d['deviceID']) ?>"><?= icon('eye') ?> View</a>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<!-- 3. My devices: heading + "See all" link outside the box, the table inside -->
<div class="section-head">
    <h2 class="section-title"><?= icon('device') ?> My devices</h2>
    <a class="link-arrow" href="my-devices.php">See all <?= icon('arrow-right') ?></a>
</div>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Device</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php if (!$recent) empty_row(4, 'device', 'No devices submitted yet.'); ?>
        <?php foreach ($recent as $d): ?>
            <tr>
                <td><strong><?= e($d['device_name']) ?></strong></td>
                <td><span class="badge <?= e(status_class($d['status'])) ?>"><?= e($d['status']) ?></span></td>
                <td><?= e(nice_date($d['submitted_at'])) ?></td>
                <td><a class="link-arrow" href="device-details.php?id=<?= urlencode($d['deviceID']) ?>">View <?= icon('arrow-right') ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<!-- 4. Announcements: same cards as the admin Announcements page -->
<div class="section-head">
    <h2 class="section-title"><?= icon('megaphone') ?> Announcements</h2>
    <a class="link-arrow" href="notifications.php">All news <?= icon('arrow-right') ?></a>
</div>
<?php if (!$announcements) empty_state('megaphone', 'No announcements yet.'); ?>
<section class="post-grid">
    <?php foreach ($announcements as $a): ?>
        <article class="post-card">
            <time datetime="<?= e(date('Y-m-d', strtotime($a['created_at']))) ?>"><?= icon('calendar') ?> <?= e(nice_date($a['created_at'])) ?></time>
            <h2><?= e($a['title']) ?></h2>
            <p><?= nl2br(e($a['message'])) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<?php require '../includes/bottom.php'; ?>
