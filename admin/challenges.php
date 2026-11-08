<?php
/*
 * Challenges - list  (Figure 26, page 1 of 2)
 * Each challenge is an individual target: a student whose own devices reach target_kg
 * (repaired or recycled between start_date and end_date) earns bonus_points once.
 * The kg rule is challenge_kg() in functions.php, the same one the student page shows.
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

// ---------- Award bonus (only after the challenge has ended) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'award') {
    $id = trim($_POST['challengeID'] ?? '');
    $ch = db_one($conn, "SELECT * FROM challenges WHERE challengeID = ? AND end_date < CURDATE()", 's', [$id]);
    if (!$ch) {
        set_flash('error', 'You can only give the bonus after the challenge has ended.');
        redirect('challenges.php');
    }
    $students = db_all($conn, "SELECT s.tp_number FROM students s JOIN users u ON u.tp_number = s.tp_number WHERE u.is_active = 1");
    $given = 0;
    $conn->begin_transaction();
    try {
        foreach ($students as $s) {
            if (challenge_kg($conn, $ch, $s['tp_number']) < (float) $ch['target_kg']) continue;
            // Already got it? (the UNIQUE key on challengeID + tp_number would also stop a second row)
            $has = db_value($conn, "SELECT COUNT(*) FROM points_transactions WHERE challengeID = ? AND tp_number = ?", 'ss', [$id, $s['tp_number']]);
            if ($has) continue;
            db_exec($conn, "INSERT INTO points_transactions (transactionID, tp_number, challengeID, points, txn_type, description) VALUES (?, ?, ?, ?, 'bonus', ?)",
                'sssis', [next_id($conn, 'points_transactions', 'transactionID', 'T'), $s['tp_number'], $id, (int) $ch['bonus_points'],
                          'Challenge bonus: ' . $ch['title']]);
            notify($conn, $s['tp_number'], 'Challenge bonus', 'You reached the target of ' . $ch['title'] . ' and earned ' . (int) $ch['bonus_points'] . ' bonus points!', $_SESSION['tp_number']);
            $given++;
        }
        $conn->commit();
        set_flash('success', $given ? 'Bonus given to ' . $given . ' student(s).' : 'No new student reached the target, so no bonus was given.');
    } catch (Throwable $ex) {
        $conn->rollback();
        set_flash('error', 'Something went wrong. Please try again.');
    }
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
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="challengeID" value="<?= e($c['challengeID']) ?>">
                        <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Delete</button>
                    </form>
                <?php elseif ($c['state'] === 'Ended'): ?>
                    <form method="post" data-confirm="Give <?= (int) $c['bonus_points'] ?> bonus points to every student who reached <?= e($c['target_kg'] + 0) ?> kg?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="award">
                        <input type="hidden" name="challengeID" value="<?= e($c['challengeID']) ?>">
                        <button class="btn btn-sun btn-sm"><?= icon('star') ?> Award bonus</button>
                    </form>
                    <span class="lock-note"><?= icon('lock') ?> Ended</span>
                <?php else: ?>
                    <span class="lock-note"><?= icon('lock') ?> Locked after it starts</span>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/bottom.php'; ?>
