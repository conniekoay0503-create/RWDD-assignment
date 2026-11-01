<?php
/*
 * Challenges - list  (Figure 26, page 1 of 2)
 * Each challenge is an individual target: a student whose own devices reach target_kg
 * (repaired or recycled between start_date and end_date) earns bonus_points once.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---------- Delete (only before the challenge starts) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = trim($_POST['challengeID'] ?? '');
    // "start_date > CURDATE()" inside the DELETE itself, so a started challenge is never deleted
    $n = db_exec($conn, "DELETE FROM challenges WHERE challengeID = ? AND start_date > CURDATE()", 's', [$id]);
    set_flash($n === 1 ? 'success' : 'error', $n === 1 ? 'Challenge deleted.' : 'Only challenges that have not started can be deleted.');
    redirect('challenges.php');
}

// Status is worked out from today's date, so nothing needs to run on a timer
$challenges = db_all($conn, "SELECT c.*, u.name AS creator,
                                    CASE WHEN CURDATE() < c.start_date THEN 'Upcoming'
                                         WHEN CURDATE() > c.end_date   THEN 'Ended'
                                         ELSE 'Running' END AS state,
                                    DATEDIFF(c.end_date, c.start_date) + 1 AS total_days,
                                    DATEDIFF(CURDATE(), c.start_date) + 1  AS day_now,
                                    (SELECT COUNT(*) FROM points_transactions p WHERE p.challengeID = c.challengeID) AS winners
                             FROM challenges c JOIN users u ON u.tp_number = c.created_by
                             ORDER BY (CURDATE() > c.end_date), (CURDATE() < c.start_date), c.start_date DESC   -- running, then upcoming, then ended");

$state_icon = ['Running' => 'flag', 'Upcoming' => 'calendar', 'Ended' => 'check'];

$page_title = 'Challenges';
$active = 'challenges';
require '../includes/admin_top.php';
page_head('flag', 'Challenges', 'Each student who reaches the target with their own devices earns the bonus once.',
    '<a class="btn btn-primary" href="challenge_form.php">' . icon('plus') . ' Create challenge</a>');
?>
<?php if (!$challenges) empty_state('flag', 'No challenges yet. Create the first one!'); ?>
<section class="tile-grid">
    <?php foreach ($challenges as $c): ?>
        <article class="challenge-tile ch-<?= strtolower($c['state']) ?>">
            <header>
                <span class="badge state-<?= strtolower($c['state']) ?>"><?= icon($state_icon[$c['state']]) ?> <?= $c['state'] ?></span>
                <small class="muted">by <?= e($c['creator']) ?></small>
            </header>
            <h2><?= e($c['title']) ?></h2>
            <p class="muted"><?= e($c['description']) ?></p>
            <ul class="mini-stats">
                <li title="Dates"><?= icon('calendar') ?> <?= date('j M', strtotime($c['start_date'])) ?> – <?= nice_date($c['end_date']) ?></li>
                <li title="Target per student"><?= icon('target') ?> <?= e($c['target_kg'] + 0) ?> kg</li>
                <li title="Bonus points"><?= icon('star') ?> <?= (int) $c['bonus_points'] ?> pts</li>
                <li title="Students who earned the bonus"><?= icon('users') ?> <?= (int) $c['winners'] ?> earned</li>
            </ul>
            <?php if ($c['state'] === 'Running'): ?>
                <?php $pct = min(100, round($c['day_now'] / $c['total_days'] * 100)); ?>
                <div class="time-bar" title="Day <?= (int) $c['day_now'] ?> of <?= (int) $c['total_days'] ?>">
                    <span style="width: <?= $pct ?>%"></span>
                </div>
                <small class="muted">Day <?= (int) $c['day_now'] ?> of <?= (int) $c['total_days'] ?></small>
            <?php endif; ?>
            <footer>
                <?php if ($c['state'] === 'Upcoming'): ?>
                    <a class="btn btn-outline btn-sm" href="challenge_form.php?edit=<?= e($c['challengeID']) ?>"><?= icon('edit') ?> Edit</a>
                    <form method="post" data-confirm="Delete <?= e($c['title']) ?>?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="challengeID" value="<?= e($c['challengeID']) ?>">
                        <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Delete</button>
                    </form>
                <?php else: ?>
                    <span class="lock-note"><?= icon('lock') ?> Locked after it starts</span>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
