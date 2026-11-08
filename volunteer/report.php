<?php
/*
 * Repair report (wireframe Figure 60)
 * The period box (Month / Year / Date range) is shared by every role: report_period() + includes/period_form.php.
 * "Print" opens the browser print dialog (choose "Save as PDF" to get a PDF).
 * The team stylesheet's @media print rules hide the menu and the form, so only the report prints.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

// ---- 1) the period (shared rule: report_period() in functions.php checks the URL) ----
$rp = report_period();
$label = $rp['label'];
$start = $rp['start'];
$end   = $rp['last_day'];   // the last day inside the period
// The database wants "from this moment" and "before this moment"
$from_dt = $rp['start'] . ' 00:00:00';
$to_dt   = $rp['end'] . ' 00:00:00';

// ---- 2) load the data ----
$stats = vol_stats($conn, $me, $from_dt, $to_dt);

$devices = db_all($conn,
    "SELECT d.deviceID, d.device_name, d.status, d.weight_kg, c.category_name,
            (SELECT MIN(h.changed_at) FROM device_status_history h
              WHERE h.deviceID = d.deviceID AND h.new_status = 'Claimed') AS claimed_at,
            (SELECT SUM(p.points) FROM points_transactions p
              WHERE p.deviceID = d.deviceID AND p.tp_number = ? AND p.txn_type = 'earn') AS earned
       FROM devices d JOIN categories c ON c.categoryID = d.categoryID
      WHERE d.claimed_by = ?
        AND EXISTS (SELECT 1 FROM device_status_history h
                     WHERE h.deviceID = d.deviceID AND h.new_status = 'Claimed'
                       AND h.changed_at >= ? AND h.changed_at < ?)
      ORDER BY claimed_at DESC, d.deviceID DESC", 'ssss', [$me, $me, $from_dt, $to_dt]);

$ledger = vol_points_history($conn, $me, $from_dt, $to_dt);
$earned = 0; $redeemed = 0;
foreach ($ledger as $l) {
    if ((int) $l['points'] > 0) $earned += (int) $l['points']; else $redeemed += -((int) $l['points']);
}
$code = 'VOL-' . date('Ymd', strtotime($start)) . '-' . date('Ymd', strtotime($end));

$page_title = 'Repair report';
$active = 'report';
require '../includes/volunteer_top.php';

page_head('chart', 'Repair report', 'Choose a period, preview the report, then print it or save it as a PDF.');
?>

<?php
$form_action = 'report.php';
require '../includes/period_form.php';   // the same period box as the admin Reports page
?>

<?php if ($rp['previewing']): ?>

<?php
$report_title = 'Volunteer repair report';
$report_code = $code;   // shown after the date, e.g. VOL-20260101-20261231
require '../includes/report_head.php';   // same report top as the admin Reports page
?>

    <section class="stat-grid four">
        <div class="stat-card row"><span class="stat-icon tint-sky"><?= icon('device') ?></span><div><strong class="stat-value"><?= $stats['claimed'] ?></strong><span class="stat-title">Devices claimed</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('wrench') ?></span><div><strong class="stat-value"><?= $stats['fixed'] ?></strong><span class="stat-title">Repaired</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('recycle') ?></span><div><strong class="stat-value"><?= $stats['recycled'] ?></strong><span class="stat-title">Recycled</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('check') ?></span><div><strong class="stat-value"><?= $stats['rate'] === null ? '–' : (int) $stats['rate'] . '<small>%</small>' ?></strong><span class="stat-title">Success rate</span></div></div>
    </section>
    <section class="stat-grid three">
        <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('leaf') ?></span><div><strong class="stat-value"><?= e(vol_kg_number($stats['kg'])) ?><small>kg</small></strong><span class="stat-title">Kg handled</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('star') ?></span><div><strong class="stat-value"><?= $earned ?></strong><span class="stat-title">Points earned</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-rose"><?= icon('wallet') ?></span><div><strong class="stat-value"><?= $redeemed ?></strong><span class="stat-title">Points redeemed</span></div></div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('box') ?> Devices handled in this period</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Device</th><th>Category</th><th>Claimed</th><th>Status now</th><th>Weight</th><th class="v-num">Points</th></tr></thead>
            <tbody>
            <?php if (!$devices) empty_row(6, 'device', 'No devices were claimed in this period.'); ?>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><strong><?= e($d['device_name']) ?></strong><br><small class="muted"><?= e($d['deviceID']) ?></small></td>
                    <td><?= e($d['category_name']) ?></td>
                    <td><?= e(nice_date($d['claimed_at'])) ?></td>
                    <td><span class="badge <?= e(status_class($d['status'])) ?>"><?= e($d['status']) ?></span></td>
                    <td><?= e(vol_kg($d['weight_kg'])) ?></td>
                    <td class="v-num"><?= $d['earned'] === null ? '–' : '+' . (int) $d['earned'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('star') ?> Points history</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Description</th><th>Type</th><th class="v-num">Points</th></tr></thead>
            <tbody>
            <?php if (!$ledger) empty_row(4, 'star', 'No points movements in this period.'); ?>
            <?php foreach ($ledger as $l): $pts = (int) $l['points']; ?>
                <tr>
                    <td><?= e(nice_date($l['created_at'])) ?></td>
                    <td><?= e($l['description']) ?></td>
                    <td><?= e(ucfirst($l['txn_type'])) ?></td>
                    <td class="v-num <?= $pts < 0 ? 'v-neg' : '' ?>"><?= $pts > 0 ? '+' . $pts : $pts ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</article>

<?php endif; ?>

<?php require '../includes/bottom.php'; ?>
