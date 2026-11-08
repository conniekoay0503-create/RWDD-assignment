<?php
/*
 * Reports
 * Pick a period (month / year / date range) and a report type, preview it, then print or save as PDF.
 * Every query uses   date >= $start AND date < $end   so the last day is fully included.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---------- 1. The period from the form (shared by every role's report: report_period() in functions.php) ----------
$rp = report_period();
$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'devices', 'points', 'cashback'])) $type = 'all';
$start = $rp['start'];
$end = $rp['end'];
$period_label = $rp['label'];
$previewing = $rp['previewing'];
$p = [$start, $end];   // the two parameters every query uses

// ---------- 2. Queries ----------
$summary = [
    'submitted' => db_value($conn, "SELECT COUNT(*) FROM devices WHERE submitted_at >= ? AND submitted_at < ?", 'ss', $p),
    'kg'        => db_value($conn, "SELECT COALESCE(SUM(weight_kg), 0) FROM devices
                                    WHERE status IN ('Completed', 'Recycled', 'Disposed') AND status_changed_at >= ? AND status_changed_at < ?", 'ss', $p),
    'points'    => db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions
                                    WHERE txn_type IN ('earn', 'bonus') AND created_at >= ? AND created_at < ?", 'ss', $p),
    'cash'      => db_value($conn, "SELECT COALESCE(SUM(cash_value), 0) FROM redemptions
                                    WHERE status = 'completed' AND reviewed_at >= ? AND reviewed_at < ?", 'ss', $p),
];

// Devices submitted in the period, grouped by category, with what happened to them
$by_category = db_all($conn, "SELECT c.category_name,
                                     COUNT(d.deviceID) AS submitted,
                                     COALESCE(SUM(d.status = 'Completed'), 0) AS repaired,
                                     COALESCE(SUM(d.status = 'Recycled'), 0)  AS recycled,
                                     COALESCE(SUM(d.status = 'Disposed'), 0)  AS disposed,
                                     COALESCE(SUM(d.status = 'Cancelled'), 0) AS cancelled,
                                     COALESCE(SUM(d.weight_kg), 0)            AS kg
                              FROM categories c
                              LEFT JOIN devices d ON d.categoryID = c.categoryID AND d.submitted_at >= ? AND d.submitted_at < ?
                              GROUP BY c.categoryID ORDER BY submitted DESC, c.category_name", 'ss', $p);

// Repairs finished per volunteer = how many times they set a device to "Ready for Collection"
$volunteers = db_all($conn, "SELECT u.name, COUNT(*) AS repairs
                             FROM device_status_history h JOIN users u ON u.tp_number = h.changed_by
                             WHERE h.new_status = 'Ready for Collection' AND h.changed_at >= ? AND h.changed_at < ?
                             GROUP BY h.changed_by, u.name ORDER BY repairs DESC", 'ss', $p);

// Points: totals by type, and the top 5 earners
$points_by_type = db_all($conn, "SELECT txn_type, COUNT(*) AS n, SUM(points) AS total FROM points_transactions
                                 WHERE created_at >= ? AND created_at < ? GROUP BY txn_type", 'ss', $p);
$top_earners = db_all($conn, "SELECT u.name, u.tp_number, SUM(t.points) AS earned
                              FROM points_transactions t JOIN users u ON u.tp_number = t.tp_number
                              WHERE t.txn_type IN ('earn', 'bonus') AND t.created_at >= ? AND t.created_at < ?
                              GROUP BY t.tp_number, u.name, u.tp_number ORDER BY earned DESC LIMIT 5", 'ss', $p);

// Cashback paid, grouped by reward
$cashback = db_all($conn, "SELECT w.reward_name, COUNT(*) AS n, SUM(r.cash_value) AS total, SUM(r.points_used) AS points
                           FROM redemptions r JOIN rewards w ON w.rewardID = r.rewardID
                           WHERE r.status = 'completed' AND r.reviewed_at >= ? AND r.reviewed_at < ?
                           GROUP BY w.rewardID, w.reward_name ORDER BY total DESC", 'ss', $p);

$show = function ($section) use ($type) { return $type === 'all' || $type === $section; };

$page_title = 'Reports';
$active = 'reports';
require '../includes/admin_top.php';
page_head('chart', 'Reports', 'Choose a period, preview the report, then print it or save it as a PDF.');
?>

<?php
// The "choose a period" box is shared by every role (includes/period_form.php). The admin adds "Report type".
$form_action = 'reports.php';
$extra_field = '<div class="field"><label for="type">' . icon('filter') . ' Report type</label><select id="type" name="type">';
foreach (['all' => 'Everything', 'devices' => 'Devices & repairs', 'points' => 'Points', 'cashback' => 'Cashback'] as $k => $v) {
    $extra_field .= '<option value="' . $k . '"' . ($type === $k ? ' selected' : '') . '>' . $v . '</option>';
}
$extra_field .= '</select></div>';
require '../includes/period_form.php';
?>

<?php if ($previewing): ?>
<!-- The white "paper" below is exactly what gets printed / saved as PDF -->
<?php
$report_title = 'E-Waste & Repair Café report';
require '../includes/report_head.php';   // same report top as the student, officer and volunteer reports
?>

    <section class="stat-grid four">
        <div class="stat-card row"><span class="stat-icon tint-sky"><?= icon('device') ?></span><div><strong class="stat-value"><?= (int) $summary['submitted'] ?></strong><span class="stat-title">Devices submitted</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('leaf') ?></span><div><strong class="stat-value"><?= number_format($summary['kg'], 2) ?><small>kg</small></strong><span class="stat-title">Kept out of landfill</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('star') ?></span><div><strong class="stat-value"><?= number_format($summary['points']) ?></strong><span class="stat-title">Points issued</span></div></div>
        <div class="stat-card row"><span class="stat-icon tint-rose"><?= icon('wallet') ?></span><div><strong class="stat-value"><?= rm($summary['cash']) ?></strong><span class="stat-title">Cashback paid</span></div></div>
    </section>

    <?php if ($show('devices')): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('box') ?> Devices submitted, by category</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Category</th><th>Submitted</th><th>Repaired</th><th>Recycled</th><th>Disposed</th><th>Cancelled</th><th>Weight</th></tr></thead>
                    <tbody>
                    <?php $tot = ['submitted' => 0, 'repaired' => 0, 'recycled' => 0, 'disposed' => 0, 'cancelled' => 0, 'kg' => 0];
                    foreach ($by_category as $c):
                        foreach ($tot as $k => $v) $tot[$k] += $c[$k]; ?>
                        <tr>
                            <td><?= e($c['category_name']) ?></td>
                            <td><?= (int) $c['submitted'] ?></td><td><?= (int) $c['repaired'] ?></td><td><?= (int) $c['recycled'] ?></td>
                            <td><?= (int) $c['disposed'] ?></td><td><?= (int) $c['cancelled'] ?></td><td><?= number_format($c['kg'], 2) ?> kg</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th>Total</th><th><?= $tot['submitted'] ?></th><th><?= $tot['repaired'] ?></th><th><?= $tot['recycled'] ?></th>
                            <th><?= $tot['disposed'] ?></th><th><?= $tot['cancelled'] ?></th><th><?= number_format($tot['kg'], 2) ?> kg</th></tr>
                    </tfoot>
                </table>
            </div>
            <p class="muted small-note">"Repaired / Recycled / ..." show what those devices are now.</p>

            <h3 class="card-title"><?= icon('wrench') ?> Repairs finished by volunteers</h3>
            <?php if (!$volunteers): ?>
                <p class="muted">No repairs finished in this period.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Volunteer</th><th>Repairs finished</th></tr></thead>
                        <tbody>
                        <?php foreach ($volunteers as $v): ?>
                            <tr><td><?= e($v['name']) ?></td><td><?= (int) $v['repairs'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($show('points')): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('star') ?> Points</h3>
            <?php if (!$points_by_type): ?>
                <p class="muted">No points activity in this period.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Type</th><th>Transactions</th><th>Points</th></tr></thead>
                        <tbody>
                        <?php foreach ($points_by_type as $t): ?>
                            <tr><td><?= e(ucfirst($t['txn_type'])) ?></td><td><?= (int) $t['n'] ?></td><td><?= number_format($t['total']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <h3 class="card-title"><?= icon('users') ?> Top earners</h3>
                <?php if (!$top_earners): ?><p class="muted">No points earned in this period.</p><?php endif; ?>
                <ol class="rank-list">
                    <?php foreach ($top_earners as $t): ?>
                        <li><span><?= e($t['name']) ?> <small class="muted"><?= e($t['tp_number']) ?></small></span><strong><?= number_format($t['earned']) ?> pts</strong></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($show('cashback')): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('wallet') ?> Cashback paid</h3>
            <?php if (!$cashback): ?>
                <p class="muted">No cashback paid in this period.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Reward</th><th>Times paid</th><th>Points used</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($cashback as $c): ?>
                            <tr><td><?= e($c['reward_name']) ?></td><td><?= (int) $c['n'] ?></td><td><?= number_format($c['points']) ?></td><td><?= rm($c['total']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</article>
<?php endif; ?>
<?php require '../includes/bottom.php'; ?>
