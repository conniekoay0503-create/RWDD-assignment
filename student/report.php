<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// count my devices and their total weight
$devices = db_one($conn, "SELECT
    COUNT(*) AS total,
    SUM(status = 'Completed') AS repaired,
    SUM(status = 'Recycled') AS recycled,
    COALESCE(SUM(CASE WHEN status IN ('Completed','Recycled')
        THEN weight_kg ELSE 0 END),0) AS weight
    FROM devices WHERE submitted_by = ?", 's', [$tp]);

// calculate my points
$points = db_one($conn, "SELECT
    COALESCE(SUM(points),0) AS balance,
    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END),0) AS earned,
    COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END),0) AS spent
    FROM points_transactions WHERE tp_number = ?", 's', [$tp]);

// get status counts, cashback and recent devices
$statuses = db_all($conn, "SELECT status, COUNT(*) AS total FROM devices
    WHERE submitted_by = ? GROUP BY status ORDER BY status", 's', [$tp]);

$cashback = db_one($conn, "SELECT COUNT(*) AS total_requests,
    COALESCE(SUM(cash_value),0) AS requested_value
    FROM redemptions WHERE tp_number = ?", 's', [$tp]);

$recent = db_all($conn, "SELECT deviceID, device_name, status, submitted_at
    FROM devices WHERE submitted_by = ?
    ORDER BY submitted_at DESC LIMIT 10", 's', [$tp]);

$page_title = 'My Report';
require '../includes/student_top.php';
page_head('chart', 'My Report', 'Your personal repair and recycling summary.');
$max_status = max(1, ...array_map('intval', array_column($statuses, 'total') ?: [0]));
?>

<h2 class="section-title"><?= icon('device') ?> My devices</h2>
<section class="stat-grid four">
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('device') ?></span><div><strong class="stat-value"><?= (int)$devices['total'] ?></strong><span class="stat-title">Devices submitted</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-sky"><?= icon('wrench') ?></span><div><strong class="stat-value"><?= (int)$devices['repaired'] ?></strong><span class="stat-title">Repaired</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('recycle') ?></span><div><strong class="stat-value"><?= (int)$devices['recycled'] ?></strong><span class="stat-title">Recycled</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('leaf') ?></span><div><strong class="stat-value"><?= number_format((float)$devices['weight'], 2) ?><small>kg</small></strong><span class="stat-title">Kept out of landfill</span></div></div>
</section>

<h2 class="section-title"><?= icon('star') ?> Points &amp; cashback</h2>
<section class="stat-grid four">
    <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('star') ?></span><div><strong class="stat-value"><?= number_format((int)$points['balance']) ?></strong><span class="stat-title">Points balance</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('plus') ?></span><div><strong class="stat-value"><?= number_format((int)$points['earned']) ?></strong><span class="stat-title">Points earned</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-rose"><?= icon('wallet') ?></span><div><strong class="stat-value"><?= number_format((int)$points['spent']) ?></strong><span class="stat-title">Points spent</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-rose"><?= icon('gift') ?></span><div><strong class="stat-value"><?= e(rm($cashback['requested_value'])) ?></strong><span class="stat-title">Cashback requested (<?= (int)$cashback['total_requests'] ?>)</span></div></div>
</section>

<div class="two-col">
    <div>
        <h2 class="section-title"><?= icon('box') ?> Where my devices are now</h2>
        <!-- Same bars as the admin dashboard "What devices are now" -->
        <section class="card">
            <?php if (!$statuses): ?>
                <?php empty_state('device', 'No devices yet.'); ?>
            <?php else: ?>
                <ul class="bar-list">
                <?php foreach ($statuses as $st): ?>
                    <li>
                        <span class="badge <?= e(status_class($st['status'])) ?>"><?= e($st['status']) ?></span>
                        <span class="bar" aria-hidden="true"><span style="width: <?= round($st['total'] / $max_status * 100) ?>%"></span></span>
                        <strong><?= (int)$st['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <div>
        <h2 class="section-title"><?= icon('history') ?> Recent devices</h2>
        <section class="card">
            <div class="table-wrap"><table>
                <thead><tr><th>Device</th><th>Status</th><th>Submitted</th></tr></thead>
                <tbody>
                <?php if (!$recent) empty_row(3, 'device', 'No devices yet.'); ?>
                <?php foreach ($recent as $d): ?>
                    <tr>
                        <td><a href="device-details.php?id=<?= urlencode($d['deviceID']) ?>"><strong><?= e($d['device_name']) ?></strong></a></td>
                        <td><span class="badge <?= e(status_class($d['status'])) ?>"><?= e($d['status']) ?></span></td>
                        <td><?= e(nice_date($d['submitted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    </div>
</div>

<?php require '../includes/student_bottom.php'; ?>
