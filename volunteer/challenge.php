<?php
/*
 * Challenge & rank (wireframe Figure 62)
 * A challenge is a CAMPUS-WIDE kg target (as drawn in the wireframe). The bonus itself is paid
 * by the admin / officer side; this page only shows the progress and the leaderboard.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

$ch  = vol_current_challenge($conn);
$all = vol_leaderboard($conn);

$campus_kg = $my_kg = 0.0; $pct_me = $pct_others = 0;
if ($ch) {
    $campus_kg  = vol_challenge_kg($conn, $ch);
    $my_kg      = vol_challenge_kg($conn, $ch, $me);
    $target     = max(0.01, (float) $ch['target_kg']);
    $pct_me     = (int) min(100, round($my_kg * 100 / $target));
    $pct_others = (int) min(100 - $pct_me, round(max(0, $campus_kg - $my_kg) * 100 / $target));
}

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
<section class="card">
    <small class="muted">Campus total</small>
    <p class="big-num"><?= e(vol_kg_number($campus_kg)) ?> <small class="muted">/ <?= e(vol_kg_number($ch['target_kg'])) ?> kg</small></p>
    <div class="v-multibar" role="img" aria-label="Campus progress: you <?= $pct_me ?>%, everyone else <?= $pct_others ?>%">
        <span class="v-seg-me" style="width: <?= $pct_me ?>%"></span><span class="v-seg-others" style="width: <?= $pct_others ?>%"></span>
    </div>
    <p class="v-legend">
        <span><i class="v-dot v-dot-me"></i> You: <strong><?= e(vol_kg_number($my_kg)) ?> kg</strong></span>
        <span><i class="v-dot v-dot-others"></i> Everyone else</span>
    </p>
    <p style="margin: 0">Reach <?= e(vol_kg_number($ch['target_kg'])) ?> kg by <?= e(nice_date($ch['end_date'])) ?> and everyone with a repaired or recycled device in this window gets <strong>+<?= (int) $ch['bonus_points'] ?> bonus points</strong>.</p>
</section>
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
