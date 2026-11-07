<?php
/*
 * Challenge & rank (wireframe Figure 62)
 * Challenges are for STUDENTS: each student who reaches target_kg with their own devices gets the bonus
 * (the rule is challenge_kg() / challenge_summary() in functions.php, the same one the admin and student pages use).
 * Volunteers do not get the challenge bonus, so this page only shows how the campus is doing,
 * plus the volunteer leaderboard (lifetime points).
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

$ch  = vol_current_challenge($conn);
$all = vol_leaderboard($conn);

$sum = $ch ? challenge_summary($conn, $ch) : null;   // ['kg', 'reached', 'students']

// Top 10, plus my own row if I am lower down
$top = array_slice($all, 0, 10);
$in_top = false; $mine = null;
foreach ($all as $r) { if ($r['tp_number'] === $me) $mine = $r; }
foreach ($top as $r) { if ($r['tp_number'] === $me) $in_top = true; }

$page_title = 'Challenge & rank';
$active = 'challenge';
require '../includes/volunteer_top.php';

if ($ch) {
    page_head('flag', $ch['title'], $ch['description'],
        $ch['active'] ? '<span class="badge state-running">Running</span>' : '<span class="badge state-ended">Ended ' . e(nice_date($ch['end_date'])) . '</span>');
} else {
    page_head('flag', 'Challenge & rank', 'No challenge has been created yet.');
}
?>

<?php if ($ch): ?>
<section class="stat-grid four">
    <div class="stat-card row"><span class="stat-icon tint-sky"><?= icon('target') ?></span><div><strong class="stat-value"><?= e(vol_kg_number($ch['target_kg'])) ?><small>kg</small></strong><span class="stat-title">Target per student</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('star') ?></span><div><strong class="stat-value">+<?= (int) $ch['bonus_points'] ?></strong><span class="stat-title">Bonus per student</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('users') ?></span><div><strong class="stat-value"><?= (int) $sum['reached'] ?></strong><span class="stat-title">Students reached it</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('leaf') ?></span><div><strong class="stat-value"><?= e(vol_kg_number($sum['kg'])) ?><small>kg</small></strong><span class="stat-title">Campus total</span></div></div>
</section>
<p class="muted">
    This challenge is for students: each student whose own devices reach
    <?= e(vol_kg_number($ch['target_kg'])) ?> kg (repaired or recycled) between <?= e(nice_date($ch['start_date'])) ?>
    and <?= e(nice_date($ch['end_date'])) ?> gets the bonus. Volunteers do not get it, but every device you fix helps a student get there.
</p>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2 class="card-title"><?= icon('star') ?> Leaderboard</h2><small class="muted">Lifetime points earned</small></div>
    <?php if (!$all): ?>
        <?php empty_state('users', 'No volunteers yet.') ?>
    <?php else: ?>
        <ol class="v-leaders">
            <?php foreach ($top as $r): $you = $r['tp_number'] === $me; ?>
                <li class="<?= $you ? 'you' : '' ?>">
                    <span class="v-rank"><?= (int) $r['rank'] ?></span>
                    <span class="v-who"><?= e($r['name']) ?><?= $you ? ' <small class="muted">(you)</small>' : '' ?></span>
                    <strong><?= (int) $r['earned'] ?></strong>
                </li>
            <?php endforeach; ?>
            <?php if ($mine && !$in_top): ?>
                <li class="you"><span class="v-rank"><?= (int) $mine['rank'] ?></span>
                    <span class="v-who"><?= e($mine['name']) ?> <small class="muted">(you)</small></span><strong><?= (int) $mine['earned'] ?></strong></li>
            <?php endif; ?>
        </ol>
    <?php endif; ?>
</section>

<?php require '../includes/volunteer_bottom.php'; ?>
