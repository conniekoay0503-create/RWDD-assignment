<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// get all challenges and check if they're active
$challenges = db_all($conn, "SELECT *,
    CASE WHEN CURDATE() < start_date THEN 'Upcoming'
         WHEN CURDATE() > end_date THEN 'Ended'
         ELSE 'Running' END AS state   -- same names as the admin Challenges page
    FROM challenges
    ORDER BY (CURDATE() > end_date), (CURDATE() < start_date), start_date DESC");

// calculate how much weight user's contributed to each challenge
// (CHALLENGE_STATUSES is the shared rule in functions.php - the admin gives the bonus with the same rule)
$progress = db_all($conn, "SELECT c.challengeID,
    COALESCE(SUM(CASE WHEN d.status IN (" . sql_list(CHALLENGE_STATUSES) . ")
    AND d.status_changed_at >= c.start_date
    AND d.status_changed_at < DATE_ADD(c.end_date, INTERVAL 1 DAY)
    THEN d.weight_kg ELSE 0 END),0) AS kg
    FROM challenges c LEFT JOIN devices d ON d.submitted_by = ?
    GROUP BY c.challengeID", 's', [$tp]);

$kg = array_column($progress, 'kg', 'challengeID');

// rank students based on total device weight
$leaders = db_all($conn, "SELECT u.tp_number, u.name,
    COALESCE(SUM(CASE WHEN d.status IN (" . sql_list(CHALLENGE_STATUSES) . ")
    THEN d.weight_kg ELSE 0 END),0) AS kg
    FROM students s JOIN users u ON s.tp_number = u.tp_number
    LEFT JOIN devices d ON d.submitted_by = s.tp_number
    WHERE u.is_active = 1 GROUP BY u.tp_number, u.name
    ORDER BY kg DESC, u.name");

$rank = null;
foreach ($leaders as $i => $s) {
    if ($s['tp_number'] === $tp) {
        $rank = $i + 1;
        break;
    }
}

// show bonus points earned from challenges
$bonuses = db_all($conn, "SELECT c.title, p.points, p.created_at
    FROM points_transactions p JOIN challenges c ON p.challengeID = c.challengeID
    WHERE p.tp_number = ? AND p.txn_type = 'bonus'
    ORDER BY p.created_at DESC", 's', [$tp]);

$page_title = 'Challenges & Leaderboard';
require '../includes/student_top.php';
page_head('flag', 'Challenges & Leaderboard', 'Reach a challenge target with your own devices to earn bonus points.');
$state_icon = ['Upcoming' => 'calendar', 'Running' => 'clock', 'Ended' => 'check'];
$myKg = 0;
foreach ($leaders as $s) if ($s['tp_number'] === $tp) $myKg = (float)$s['kg'];
?>

<section class="stat-grid three">
    <div class="stat-card row">
        <span class="stat-icon tint-gold"><?= icon('star') ?></span>
        <div><strong class="stat-value"><?= $rank ? '#' . $rank : '-' ?></strong><span class="stat-title">My rank (out of <?= count($leaders) ?>)</span></div>
    </div>
    <div class="stat-card row">
        <span class="stat-icon tint-mint"><?= icon('leaf') ?></span>
        <div><strong class="stat-value"><?= number_format($myKg, 2) ?><small>kg</small></strong><span class="stat-title">Repaired or recycled</span></div>
    </div>
    <div class="stat-card row">
        <span class="stat-icon tint-rose"><?= icon('gift') ?></span>
        <div><strong class="stat-value"><?= count($bonuses) ?></strong><span class="stat-title">Bonuses earned</span></div>
    </div>
</section>

<h2 class="section-title"><?= icon('flag') ?> Challenges</h2>
<?php if (!$challenges) empty_state('flag', 'No challenges yet. Check back soon!'); ?>
<section class="tile-grid">
    <?php foreach ($challenges as $c):
        $current = (float)($kg[$c['challengeID']] ?? 0);
        $target = (float)$c['target_kg'];
        $percent = $target > 0 ? min(100, $current / $target * 100) : 0;
    ?>
        <article class="challenge-tile ch-<?= strtolower($c['state']) ?>">
            <header>
                <span class="badge state-<?= strtolower($c['state']) ?>"><?= icon($state_icon[$c['state']]) ?> <?= e($c['state']) ?></span>
                <?php if ($percent >= 100): ?><span class="badge badge-ok"><?= icon('check') ?> Target reached</span><?php endif; ?>
            </header>
            <h2><?= e($c['title']) ?></h2>
            <p class="muted"><?= e($c['description']) ?></p>
            <ul class="mini-stats">
                <li title="Dates"><?= icon('calendar') ?> <?= date('j M', strtotime($c['start_date'])) ?> – <?= e(nice_date($c['end_date'])) ?></li>
                <li title="Target"><?= icon('target') ?> <?= e($c['target_kg'] + 0) ?> kg</li>
                <li title="Bonus points"><?= icon('star') ?> <?= (int)$c['bonus_points'] ?> pts</li>
            </ul>
            <div class="time-bar" title="<?= round($percent) ?>% of the target"><span style="width: <?= round($percent) ?>%"></span></div>
            <small class="muted">My progress: <?= number_format($current, 2) ?> of <?= number_format($target, 2) ?> kg (<?= round($percent) ?>%)</small>
        </article>
    <?php endforeach; ?>
</section>

<div class="two-col">
    <div>
        <h2 class="section-title"><?= icon('chart') ?> Student leaderboard</h2>
        <section class="card">
            <div class="table-wrap"><table>
                <thead><tr><th>Rank</th><th>Student</th><th>Weight</th></tr></thead>
                <tbody>
                <?php if (!$leaders) empty_row(3, 'users', 'No students yet.'); ?>
                <?php foreach ($leaders as $i => $s): ?>
                    <tr class="<?= $s['tp_number'] === $tp ? 'is-me' : '' ?>">
                        <td><span class="rank-num">#<?= $i + 1 ?></span></td>
                        <td><?= e($s['name']) ?><?= $s['tp_number'] === $tp ? ' <span class="badge badge-ok">You</span>' : '' ?></td>
                        <td><?= number_format($s['kg'], 2) ?> kg</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    </div>
    <div>
        <h2 class="section-title"><?= icon('gift') ?> My challenge bonuses</h2>
        <section class="card">
            <div class="table-wrap"><table>
                <thead><tr><th>Challenge</th><th>Points</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (!$bonuses) empty_row(3, 'gift', 'No bonuses earned yet.'); ?>
                <?php foreach ($bonuses as $b): ?>
                    <tr>
                        <td><?= e($b['title']) ?></td>
                        <td class="points-plus"><strong>+<?= (int)$b['points'] ?></strong></td>
                        <td><?= e(nice_date($b['created_at'], true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    </div>
</div>

<?php require '../includes/student_bottom.php'; ?>
