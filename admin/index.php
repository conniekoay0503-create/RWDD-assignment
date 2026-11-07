<?php
/*
 * Admin Dashboard  (Figure 22)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---- "To do" cards ----
$cash      = db_one($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(cash_value), 0) AS total FROM redemptions WHERE status = 'pending'");
$fb_new    = db_value($conn, "SELECT COUNT(*) FROM feedback WHERE admin_reply IS NULL");
$fb_total  = db_value($conn, "SELECT COUNT(*) FROM feedback");
$vr_new    = db_value($conn, "SELECT COUNT(*) FROM volunteer_request WHERE status = 'pending'");
// Overdue = waiting more than 7 days for the owner or for a decision (same rule as every other page, see overdue_sql())
$overdue   = db_value($conn, "SELECT COUNT(*) FROM devices WHERE " . overdue_sql(array_merge(WAITING_FOR_OWNER, WAITING_FOR_ACTION)));
$recycle   = db_value($conn, "SELECT COUNT(*) FROM devices WHERE status = 'Awaiting Recycle'");

// ---- At a glance (all time) ----
$diverted  = db_one($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(weight_kg), 0) AS kg FROM devices
                            WHERE status IN ('Completed', 'Recycled', 'Disposed')");
$points    = db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE txn_type IN ('earn', 'bonus')");
// Repair success = devices that were ever "Ready for Collection" / devices that got an outcome
$outcome   = db_one($conn, "SELECT COUNT(DISTINCT CASE WHEN new_status = 'Ready for Collection' THEN deviceID END) AS fixed,
                                   COUNT(DISTINCT CASE WHEN new_status = 'Pending Consent' THEN deviceID END) AS unfixable
                            FROM device_status_history");
$decided   = $outcome['fixed'] + $outcome['unfixable'];
$success   = $decided > 0 ? round($outcome['fixed'] / $decided * 100) : 0;

// ---- What devices are now (all 15 statuses, even when the count is 0) ----
$statuses = ['Submitted', 'Dropped Off', 'Claimed', 'In Repair', 'Ready for Collection', 'Completed', 'Pending Consent',
             'Return Requested', 'Awaiting Recycle', 'Recycled', 'Returned Unrepaired', 'Collected Unrepaired', 'Disposed',
             'Returned Unclaimed', 'Cancelled'];
$counts = array_fill_keys($statuses, 0);
foreach (db_all($conn, "SELECT status, COUNT(*) AS n FROM devices GROUP BY status") as $row) {
    $counts[$row['status']] = (int) $row['n'];
}
$max_count = max(1, max($counts));

// ---- Recent activity ----
$recent = db_all($conn, "SELECT h.changed_at, h.new_status, h.remarks, d.device_name, u.name AS by_name
                         FROM device_status_history h
                         JOIN devices d ON d.deviceID = h.deviceID
                         LEFT JOIN users u ON u.tp_number = h.changed_by
                         ORDER BY h.changed_at DESC, h.historyID DESC LIMIT 15");

$todo_total = $cash['n'] + $fb_new + $vr_new;

$page_title = 'Dashboard';
$active = 'home';
require '../includes/admin_top.php';
?>
<section class="hero-card">
    <div class="hero-card-text">
        <h1>Welcome, <?= e(explode(' ', $_SESSION['name'])[0]) ?></h1>
        <p class="serif-line"><?= $todo_total ? $todo_total . ' thing(s) are waiting for you today.' : 'Nothing is waiting for you. Nice work!' ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="announcement_form.php"><?= icon('megaphone') ?> Post announcement</a>
        <a class="btn btn-sun" href="challenge_form.php"><?= icon('flag') ?> New challenge</a>
    </div>
    <span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span>
</section>

<h2 class="section-title"><?= icon('clock') ?> To do</h2>
<section class="stat-grid">
    <a class="stat-card todo <?= $cash['n'] ? 'has-work' : '' ?>" href="cashback.php">
        <span class="stat-icon tint-gold"><?= icon('wallet') ?></span>
        <strong class="stat-value"><?= (int) $cash['n'] ?></strong>
        <span class="stat-title">Cashback to pay</span>
        <small><?= rm($cash['total']) ?> in total</small>
    </a>
    <a class="stat-card todo <?= $fb_new ? 'has-work' : '' ?>" href="feedback.php?filter=new">
        <span class="stat-icon tint-sky"><?= icon('message') ?></span>
        <strong class="stat-value"><?= (int) $fb_new ?></strong>
        <span class="stat-title">Feedback to reply</span>
        <small><?= (int) $fb_total ?> message(s) in total</small>
    </a>
    <a class="stat-card todo <?= $vr_new ? 'has-work' : '' ?>" href="volunteer_requests.php">
        <span class="stat-icon tint-rose"><?= icon('heart') ?></span>
        <strong class="stat-value"><?= (int) $vr_new ?></strong>
        <span class="stat-title">Volunteer requests</span>
        <small>Approve or reject</small>
    </a>
    <div class="stat-card">
        <span class="stat-icon tint-red"><?= icon('alert') ?></span>
        <strong class="stat-value"><?= (int) $overdue ?></strong>
        <span class="stat-title">Overdue devices</span>
        <small>Officer handles · view only</small>
    </div>
    <div class="stat-card">
        <span class="stat-icon tint-mint"><?= icon('recycle') ?></span>
        <strong class="stat-value"><?= (int) $recycle ?></strong>
        <span class="stat-title">Waiting to recycle</span>
        <small>Officer handles · view only</small>
    </div>
</section>

<div class="section-head">
    <h2 class="section-title"><?= icon('chart') ?> At a glance</h2>
    <a class="link-arrow" href="reports.php">Open reports <?= icon('arrow-right') ?></a>
</div>
<section class="stat-grid four">
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('device') ?></span><div><strong class="stat-value"><?= (int) $diverted['n'] ?></strong><span class="stat-title">Devices processed</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('leaf') ?></span><div><strong class="stat-value"><?= number_format($diverted['kg'], 2) ?><small>kg</small></strong><span class="stat-title">Kept out of landfill</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('star') ?></span><div><strong class="stat-value"><?= number_format($points) ?></strong><span class="stat-title">Points issued</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-sky"><?= icon('wrench') ?></span><div><strong class="stat-value"><?= $success ?><small>%</small></strong><span class="stat-title">Repair success</span></div></div>
</section>

<div class="two-col">
    <section class="card">
        <h2 class="card-title"><?= icon('box') ?> What devices are now</h2>
        <ul class="bar-list">
            <?php foreach ($counts as $status => $n): ?>
                <li>
                    <span class="badge status-<?= e(strtolower(str_replace(' ', '-', $status))) ?>"><?= e($status) ?></span>
                    <span class="bar" aria-hidden="true"><span style="width: <?= round($n / $max_count * 100) ?>%"></span></span>
                    <strong><?= $n ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="bar-total"><span><?= icon('device') ?> All devices</span><strong><?= array_sum($counts) ?></strong></div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title"><?= icon('history') ?> Recent activity</h2>
            <label class="search-box"><?= icon('search') ?><input type="search" placeholder="Search" data-filter-list="activity" aria-label="Search recent activity"></label>
        </div>
        <ul class="activity" id="activity" data-show-limit="7">
            <?php foreach ($recent as $r): ?>
                <li>
                    <span class="activity-dot"><?= icon('arrow-right') ?></span>
                    <div>
                        <strong><?= e($r['device_name']) ?></strong> → <?= e($r['new_status']) ?>
                        <?php if ($r['remarks']): ?><em>(<?= e($r['remarks']) ?>)</em><?php endif; ?>
                        <small><?= e($r['by_name'] ?? 'System') ?> · <?= nice_date($r['changed_at'], true) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$recent) empty_state('history', 'No activity yet.'); ?>
        <p class="no-results" data-no-results-for="activity" hidden><?= icon('search') ?> No activity matches your search.</p>
        <div class="card-foot">
            <button type="button" class="btn btn-outline btn-sm" data-show-more="activity"><?= icon('plus') ?> Show more</button>
            <button type="button" class="btn btn-outline btn-sm" data-show-less="activity">Show less</button>
        </div>
    </section>
</div>
<?php require '../includes/admin_bottom.php'; ?>
