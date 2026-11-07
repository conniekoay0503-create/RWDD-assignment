<?php
/*
 * Repair report (wireframe Figure 60)
 * A GET form picks Month / Year / Date range; the report below is rebuilt from the URL.
 * "Print" opens the browser print dialog (choose "Save as PDF" to get a PDF).
 * The team stylesheet's @media print rules hide the menu and the form, so only the report prints.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

// ---- 1) read + VALIDATE the period (never trust the URL) ----
$period = $_GET['period'] ?? 'month';
if (!in_array($period, ['month', 'year', 'range'], true)) $period = 'month';

$month = $_GET['month'] ?? date('Y-m');
$year  = $_GET['year']  ?? date('Y');
$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-d');

function vol_valid_date($d)
{
    return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

$error = '';
if ($period === 'month') {
    if (!is_string($month) || !preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || !checkdate((int) $m[2], 1, (int) $m[1])) {
        $error = 'Please choose a valid month.';
    } else {
        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));
        $label = date('F Y', strtotime($start));
    }
} elseif ($period === 'year') {
    if (!is_string($year) || !preg_match('/^\d{4}$/', $year) || (int) $year < 2000 || (int) $year > 2100) {
        $error = 'Please enter a valid year (2000 to 2100).';
    } else {
        $start = $year . '-01-01';
        $end   = $year . '-12-31';
        $label = $year;
    }
} else {
    if (!vol_valid_date($from) || !vol_valid_date($to)) {
        $error = 'Please choose a valid start and end date.';
    } elseif ($from > $to) {
        $error = 'The start date must not be after the end date.';
    } elseif (strtotime($to) - strtotime($from) > 366 * 86400 * 3) {
        $error = 'Please choose a period of 3 years or less.';
    } else {
        $start = $from;
        $end   = $to;
        $label = date('j M Y', strtotime($from)) . ' to ' . date('j M Y', strtotime($to));
    }
}
if ($error !== '') {   // fall back to this month so the page still works
    set_flash('error', $error . ' Showing this month instead.');
    $period = 'month'; $month = date('Y-m');
    $start = date('Y-m-01'); $end = date('Y-m-t'); $label = date('F Y');
}

// The database wants "from this moment" and "before this moment"
$from_dt = $start . ' 00:00:00';
$to_dt   = date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00';

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

page_head('chart', 'Repair report', 'Choose a period, check the report, then print it or save it as a PDF.');
?>

<form method="get" action="report.php" class="card no-print" data-report-form>
    <div class="field-row">
        <div class="field">
            <label>Period</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap">
                <?php foreach (['month' => 'Month', 'year' => 'Year', 'range' => 'Date range'] as $k => $t): ?>
                    <label class="seg"><input type="radio" name="period" value="<?= $k ?>" <?= $period === $k ? 'checked' : '' ?>><span><?= $t ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="field" data-period-field="month"><label for="f-month">Month</label><input id="f-month" type="month" name="month" value="<?= e($period === 'month' ? substr($start, 0, 7) : date('Y-m')) ?>"></div>
        <div class="field" data-period-field="year"><label for="f-year">Year</label><input id="f-year" type="number" name="year" min="2000" max="2100" value="<?= e($period === 'year' ? $year : date('Y')) ?>"></div>
        <div class="field" data-period-field="range"><label for="f-from">From</label><input id="f-from" type="date" name="from" value="<?= e($start) ?>"></div>
        <div class="field" data-period-field="range"><label for="f-to">To</label><input id="f-to" type="date" name="to" value="<?= e($end) ?>"></div>
    </div>
    <div class="btn-row">
        <button type="submit" class="btn btn-primary"><?= icon('search') ?> Show report</button>
        <button type="button" class="btn btn-outline" data-print><?= icon('printer') ?> Print / Save as PDF</button>
    </div>
</form>

<section class="card report">
    <div class="report-head">
        <div>
            <h2><?= icon('chart') ?> Volunteer repair report</h2>
            <p><?= e($label) ?> · <?= e($_SESSION['name']) ?> (<?= e($me) ?>)</p>
        </div>
        <small class="muted">Report <?= e($code) ?> · generated <?= e(date('j M Y, g:i A')) ?></small>
    </div>

    <div class="stat-grid four">
        <div class="stat-card"><span class="stat-value"><?= $stats['claimed'] ?></span><span class="stat-title">Devices claimed</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['fixed'] ?></span><span class="stat-title">Repaired</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['recycled'] ?></span><span class="stat-title">Recycled</span></div>
        <div class="stat-card"><span class="stat-value"><?= $stats['rate'] === null ? '–' : (int) $stats['rate'] . '<small>%</small>' ?></span><span class="stat-title">Success rate</span></div>
        <div class="stat-card"><span class="stat-value"><?= e(vol_kg_number($stats['kg'])) ?><small>kg</small></span><span class="stat-title">Kg handled</span></div>
        <div class="stat-card"><span class="stat-value"><?= $earned ?></span><span class="stat-title">Points earned</span></div>
        <div class="stat-card"><span class="stat-value"><?= $redeemed ?></span><span class="stat-title">Points redeemed</span></div>
    </div>

    <h3>Devices handled in this period</h3>
    <?php if (!$devices): ?>
        <p class="muted">No devices were claimed in this period.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Device</th><th>Category</th><th>Claimed</th><th>Status now</th><th>Weight</th><th class="v-num">Points</th></tr></thead>
            <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><strong><?= e($d['device_name']) ?></strong><br><small class="muted"><?= e($d['deviceID']) ?></small></td>
                    <td><?= e($d['category_name']) ?></td>
                    <td><?= e(nice_date($d['claimed_at'])) ?></td>
                    <td><span class="badge <?= e(vol_status_class($d['status'])) ?>"><?= e($d['status']) ?></span></td>
                    <td><?= e(vol_kg($d['weight_kg'])) ?></td>
                    <td class="v-num"><?= $d['earned'] === null ? '–' : '+' . (int) $d['earned'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    <h3>Points history</h3>
    <?php if (!$ledger): ?>
        <p class="muted">No points movements in this period.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Description</th><th>Type</th><th class="v-num">Points</th></tr></thead>
            <tbody>
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
    <?php endif; ?>
</section>

<?php require '../includes/volunteer_bottom.php'; ?>
