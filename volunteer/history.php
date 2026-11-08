<?php
/*
 * Repair history
 * Every device this volunteer ever claimed, newest first, with four summary numbers.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

$stats = vol_stats($conn, $me);
$rows  = db_all($conn,
    "SELECT d.deviceID, d.device_name, d.weight_kg, d.status, d.status_changed_at, c.category_name
       FROM devices d JOIN categories c ON c.categoryID = d.categoryID
      WHERE d.claimed_by = ?
      ORDER BY d.status_changed_at DESC, d.deviceID DESC", 's', [$me]);

$page_title = 'Repair history';
$active = 'history';
require '../includes/volunteer_top.php';

page_head('history', 'Repair history', 'Every device you have claimed, newest first.');
?>

<div class="stat-grid four">
    <div class="stat-card"><span class="stat-icon tint-mint"><?= icon('check') ?></span><span class="stat-value"><?= $stats['fixed'] ?></span><span class="stat-title">Total fixed</span></div>
    <div class="stat-card"><span class="stat-icon tint-sky"><?= icon('recycle') ?></span><span class="stat-value"><?= $stats['recycled'] ?></span><span class="stat-title">Total recycled</span><small>Could not fix, then recycled</small></div>
    <div class="stat-card"><span class="stat-icon tint-gold"><?= icon('scale') ?></span><span class="stat-value"><?= e(vol_kg_number($stats['kg'])) ?><small>kg</small></span><span class="stat-title">Total kg handled</span></div>
    <div class="stat-card"><span class="stat-icon tint-rose"><?= icon('percent') ?></span><span class="stat-value"><?= $stats['rate'] === null ? '–' : (int) $stats['rate'] . '<small>%</small>' ?></span><span class="stat-title">Success rate</span></div>
</div>

<section class="card">
    <?php if (!$rows): ?>
        <?php empty_state('history', 'No history yet. Devices you claim will be listed here.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Device</th><th>Category</th><th>Weight</th><th>Current status</th><th>Last update</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= e($r['device_name']) ?></strong><br><small class="muted"><?= e($r['deviceID']) ?></small></td>
                        <td><?= e($r['category_name']) ?></td>
                        <td><?= e(vol_kg($r['weight_kg'])) ?></td>
                        <td><span class="badge <?= e(status_class($r['status'])) ?>"><?= e($r['status']) ?></span></td>
                        <td><?= nice_date($r['status_changed_at'], true) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require '../includes/bottom.php'; ?>
