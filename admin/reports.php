<?php
/*
 * Reports  (Figure 28)
 * Pick a period (month / year / date range) and a report type, preview it, then print or save as PDF.
 * Every query uses   date >= $start AND date < $end   so the last day is fully included.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---------- 1. Work out the period from the form (GET, so the report can be bookmarked) ----------
$period = $_GET['period'] ?? 'month';
$type   = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'devices', 'points', 'cashback'])) $type = 'all';

$month = $_GET['month'] ?? date('Y-m');
$year  = (int) ($_GET['year'] ?? date('Y'));
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to'] ?? date('Y-m-d');
$error = '';
$previewing = isset($_GET['preview']);   // the report only appears after the admin presses "Preview"

if ($period === 'year') {
    if ($year < 2000 || $year > 2100) $year = (int) date('Y');
    $start = $year . '-01-01';
    $end   = ($year + 1) . '-01-01';
    $period_label = 'Year ' . $year;
} elseif ($period === 'range') {
    if (!strtotime($from) || !strtotime($to) || $to < $from) {
        $error = 'Please choose a valid date range (the "to" date must be on or after the "from" date).';
        $from = date('Y-m-01');
        $to   = date('Y-m-d');
    }
    $start = date('Y-m-d', strtotime($from));
    $end   = date('Y-m-d', strtotime($to . ' +1 day'));   // +1 day so the "to" date is included
    $period_label = nice_date($start) . ' – ' . nice_date($to);
} else {
    $period = 'month';
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
    $start = $month . '-01';
    $end   = date('Y-m-d', strtotime($start . ' +1 month'));
    $period_label = date('F Y', strtotime($start));
}
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

<?php if ($error): ?><div class="alert alert-error no-print" role="alert" data-flash="error"><?= e($error) ?></div><?php endif; ?>

<section class="card no-print">
    <form method="get" action="reports.php#report" class="report-form" data-report-form>
        <fieldset class="radio-row">
            <legend>Period</legend>
            <label class="seg"><input type="radio" name="period" value="month" <?= $period === 'month' ? 'checked' : '' ?>><span><?= icon('calendar') ?> Month</span></label>
            <label class="seg"><input type="radio" name="period" value="year" <?= $period === 'year' ? 'checked' : '' ?>><span><?= icon('flag') ?> Year</span></label>
            <label class="seg"><input type="radio" name="period" value="range" <?= $period === 'range' ? 'checked' : '' ?>><span><?= icon('sliders') ?> Date range</span></label>
        </fieldset>
        <div class="field-row">
            <div class="field" data-period-field="month">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?= e($month) ?>">
            </div>
            <div class="field" data-period-field="year">
                <label for="year">Year</label>
                <input type="number" id="year" name="year" min="2000" max="2100" value="<?= (int) $year ?>">
            </div>
            <div class="field" data-period-field="range">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="<?= e($from) ?>" data-start-for="to">
            </div>
            <div class="field" data-period-field="range">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field">
                <label for="type"><?= icon('filter') ?> Report type</label>
                <select id="type" name="type">
                    <?php foreach (['all' => 'Everything', 'devices' => 'Devices & repairs', 'points' => 'Points', 'cashback' => 'Cashback'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" name="preview" value="1"><?= icon('eye') ?> Preview</button>
        </div>
    </form>
</section>

<?php if (!$previewing): ?>
    <?php empty_state('chart', 'Choose a period and press Preview to see the report here.'); ?>
<?php else: ?>
<!-- The white "paper" below is exactly what gets printed / saved as PDF -->
<p class="print-area-label no-print"><?= icon('printer') ?> Print area: only the white part below is printed or saved as PDF.</p>
<article class="report" id="report">
    <header class="report-head">
        <div>
            <h2><?= icon('chart') ?> E-Waste &amp; Repair Café report</h2>
            <p><strong><?= e($period_label) ?></strong> · generated <?= date('j M Y, g:i A') ?> by <?= e($_SESSION['name']) ?></p>
        </div>
        <button type="button" class="btn btn-sun no-print" data-print><?= icon('printer') ?> Print / Save as PDF</button>
    </header>

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
<?php require '../includes/admin_bottom.php'; ?>
