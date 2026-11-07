<?php
/*
 * Volunteer Home (wireframe Figure 52). It only READS data.
 * Every number comes from the vol_* functions, so Home, History and Report always agree.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');

$me = $_SESSION['tp_number'];

$jobs    = vol_my_jobs($conn, $me);
$queue   = vol_queue($conn, $me);
$balance = vol_points_balance($conn, $me);
$stats   = vol_stats($conn, $me);
$month   = vol_month_counts($conn, $me);

// My place on the leaderboard
$rank = null;
foreach (vol_leaderboard($conn) as $row) {
    if ($row['tp_number'] === $me) { $rank = $row['rank']; break; }
}

// The biggest cashback the balance can pay for, and the next tier to aim at
$affordable = null; $next = null;
foreach (vol_rewards($conn) as $rw) {
    if ($balance >= (int) $rw['points_required']) $affordable = $rw;
    elseif ($next === null) $next = $rw;
}
$bar = $next ? min(100, (int) round($balance * 100 / (int) $next['points_required'])) : 100;

$announcements = db_all($conn, "SELECT title, message, created_at FROM announcements ORDER BY created_at DESC, announcementID DESC LIMIT 2");
$first_name = explode(' ', trim($_SESSION['name']))[0];

$page_title = 'Home';
$active = 'home';
require '../includes/volunteer_top.php';
?>

<section class="hero-card">
    <div>
        <p class="muted"><?= e(date('l, j F')) ?></p>
        <h1>Hi <?= e($first_name) ?>,</h1>
        <p class="serif-line">
            <?php if (!$jobs): ?>
                You have no jobs in progress. Pick a device from the queue.
            <?php else: ?>
                <?= count($jobs) ?> job<?= count($jobs) === 1 ? '' : 's' ?> need<?= count($jobs) === 1 ? 's' : '' ?> your action.
            <?php endif; ?>
        </p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="repair_queue.php"><?= icon('inbox') ?> Open repair queue</a>
        <a class="btn btn-outline" href="my_jobs.php"><?= icon('wrench') ?> My jobs</a>
    </div>
    <!-- Big faint recycle icon in the background, same as the admin and officer dashboards -->
    <span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span>
</section>

<div class="stat-grid mt-lg">
    <a class="stat-card" href="points.php">
        <span class="stat-icon tint-gold"><?= icon('wallet') ?></span>
        <span class="stat-value"><?= $balance ?><small>pts</small></span>
        <span class="stat-title">Points balance</span>
        <div class="bar my-xs"><span style="width: <?= $bar ?>%"></span></div>
        <small><?= $affordable ? 'Enough for RM' . number_format((float) $affordable['cash_value'], 0) . ' cashback' : 'Keep earning to unlock cashback' ?></small>
    </a>
    <!-- A link: press the card to open My jobs (same as the Points balance card) -->
    <a class="stat-card <?= $jobs ? 'has-work' : '' ?>" href="my_jobs.php">
        <span class="stat-icon tint-sky"><?= icon('wrench') ?></span>
        <span class="stat-value"><?= count($jobs) ?></span>
        <span class="stat-title">Open jobs</span>
        <small>waiting for you</small>
    </a>
    <div class="stat-card">
        <span class="stat-icon tint-mint"><?= icon('check') ?></span>
        <span class="stat-value"><?= $stats['rate'] === null ? '–' : (int) $stats['rate'] . '<small>%</small>' ?></span>
        <span class="stat-title">Success rate</span>
        <small><?= $stats['rate'] === null ? 'no finished jobs yet' : 'of finished jobs' ?></small>
    </div>
    <div class="stat-card">
        <span class="stat-icon tint-rose"><?= icon('recycle') ?></span>
        <span class="stat-value"><?= $month['fixed'] ?></span>
        <span class="stat-title">Fixed this month</span>
        <small><?= $month['recycled'] ?> recycled</small>
    </div>
    <a class="stat-card" href="challenge.php">
        <span class="stat-icon tint-gold"><?= icon('flag') ?></span>
        <span class="stat-value"><?= $rank !== null ? '#' . (int) $rank : '–' ?></span>
        <span class="stat-title">Leaderboard</span>
        <small><?= e(vol_kg_number($stats['kg'])) ?> kg handled</small>
    </a>
</div>

<div class="two-col">
    <section class="card">
        <div class="card-head"><h2 class="card-title"><?= icon('wrench') ?> Needs your action</h2><a class="link-arrow" href="my_jobs.php">My jobs <?= icon('arrow-right') ?></a></div>
        <?php if (!$jobs): ?>
            <?php empty_state('check', 'Nothing needs your action. Claim a device from the repair queue to start a job.') ?>
        <?php else: ?>
            <ul class="simple-list">
                <?php foreach (array_slice($jobs, 0, 4) as $j): ?>
                    <li>
                        <div>
                            <strong><?= e($j['device_name']) ?> <small class="muted"><?= e($j['deviceID']) ?></small></strong>
                            <small class="muted">
                                <?= $j['status'] === 'Claimed' ? 'Collect it from the Green Hub, then confirm receipt.'
                                  : ($j['status'] === 'In Repair' ? 'In repair. Add a note or mark the outcome.'
                                  : 'The owner wants it back. Put it at the counter.') ?>
                            </small>
                        </div>
                        <a class="btn btn-primary btn-sm ml-auto" href="my_jobs.php#job-<?= e($j['deviceID']) ?>">Open</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title"><?= icon('inbox') ?> Repair queue · <?= count($queue) ?> waiting</h2><a class="link-arrow" href="repair_queue.php">See all <?= icon('arrow-right') ?></a></div>
        <?php if (!$queue): ?>
            <?php empty_state('inbox', 'No devices are waiting right now.') ?>
        <?php else: ?>
            <ul class="simple-list">
                <?php foreach (array_slice($queue, 0, 4) as $d): ?>
                    <li>
                        <div>
                            <strong><?= e($d['device_name']) ?></strong>
                            <small class="muted"><?= e($d['deviceID']) ?> · <?= e($d['category_name']) ?></small>
                        </div>
                        <span class="badge <?= (int) $d['days_left'] <= 1 ? 'badge-urgent' : ((int) $d['days_left'] <= 3 ? 'badge-warn' : 'badge-ok') ?> ml-auto"><?= e(vol_days_left_text($d['days_left'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2 class="card-title"><?= icon('megaphone') ?> Announcements</h2></div>
    <?php if (!$announcements): ?>
        <?php empty_state('megaphone', 'No announcements yet.') ?>
    <?php else: ?>
        <div class="two-col">
            <?php foreach ($announcements as $a): ?>
                <div>
                    <strong><?= e($a['title']) ?></strong>
                    <p class="muted my-xs"><?= e($a['message']) ?></p>
                    <small class="muted"><?= nice_date($a['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require '../includes/volunteer_bottom.php'; ?>
