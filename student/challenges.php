<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// get all challenges and check if they're active
$challenges = db_all($conn, "SELECT *,
    CASE WHEN CURDATE() < start_date THEN 'Upcoming'
         WHEN CURDATE() > end_date THEN 'Ended'
         ELSE 'Active' END AS state
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
?>

<div class="student-page-head">
    <span class="page-icon">🏆</span>
    <div>
        <h1>Challenges & Leaderboard</h1>
        <p>Track your progress and compete with other students.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="index.php" class="btn">&larr; Back to Dashboard</a>
    <h2>My Rank: <?= $rank ? "#$rank" : 'Not ranked' ?></h2>
    <p>Out of <?= count($leaders) ?> active students</p>
</section>

<section class="card student-panel">
    <h2>Available Challenges</h2>
    <?php if (!$challenges): ?><p>No challenges available.</p><?php endif; ?>

    <div class="tile-grid">
    <?php foreach ($challenges as $c):
        $current = (float)($kg[$c['challengeID']] ?? 0);
        $target = (float)$c['target_kg'];
        $percent = $target > 0 ? min(100, $current / $target * 100) : 0;
    ?>
        <article class="challenge-tile">
            <span class="badge state-<?= strtolower($c['state']) ?>"><?= e($c['state']) ?></span>
            <h3><?= e($c['title']) ?></h3>
            <p><?= e($c['description']) ?></p>
            <p><?= e(nice_date($c['start_date'])) ?> – <?= e(nice_date($c['end_date'])) ?></p>
            <p><?= number_format($current, 2) ?> / <?= number_format($target, 2) ?> kg</p>
            <progress value="<?= $percent ?>" max="100" style="width:100%"></progress>
            <p><?= round($percent) ?>% completed · <?= (int)$c['bonus_points'] ?> bonus points</p>
        </article>
    <?php endforeach; ?>
    </div>
</section>

<section class="card student-panel">
    <h2>Student Leaderboard</h2>
    <div class="table-wrap"><table>
        <tr><th>Rank</th><th>Student</th><th>Weight</th></tr>
        <?php foreach ($leaders as $i => $s): ?>
            <tr>
                <td>#<?= $i + 1 ?></td>
                <td><?= e($s['name']) ?><?= $s['tp_number'] === $tp ? ' (You)' : '' ?></td>
                <td><?= number_format($s['kg'], 2) ?> kg</td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</section>

<section class="card student-panel">
    <h2>My Challenge Bonuses</h2>
    <?php if (!$bonuses): ?><p>No bonuses earned yet.</p><?php endif; ?>

    <div class="table-wrap"><table>
        <tr><th>Challenge</th><th>Points</th><th>Date</th></tr>
        <?php foreach ($bonuses as $b): ?>
            <tr>
                <td><?= e($b['title']) ?></td>
                <td>+<?= (int)$b['points'] ?></td>
                <td><?= e(nice_date($b['created_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</section>

<?php require '../includes/student_bottom.php'; ?>
