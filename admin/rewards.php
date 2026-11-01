<?php
/*
 * Cashback - reward list  (Figure 24, page 3 of 4)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---------- Delete (only if nobody has redeemed it, so the history stays complete) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = trim($_POST['rewardID'] ?? '');
    if (db_value($conn, "SELECT COUNT(*) FROM redemptions WHERE rewardID = ?", 's', [$id]) > 0) {
        set_flash('error', 'This reward has been redeemed before, so it cannot be deleted. Set its stock to 0 instead.');
    } else {
        db_exec($conn, "DELETE FROM rewards WHERE rewardID = ?", 's', [$id]);
        set_flash('success', 'Reward deleted.');
    }
    redirect('rewards.php');
}

$rewards = db_all($conn, "SELECT w.*, (SELECT COUNT(*) FROM redemptions r WHERE r.rewardID = w.rewardID) AS times_used
                          FROM rewards w ORDER BY w.points_required");

$page_title = 'Rewards';
$active = 'cashback';
require '../includes/admin_top.php';
page_head('gift', 'Rewards', 'What students can swap their points for.',
    '<a class="btn btn-primary" href="reward_form.php">' . icon('plus') . ' Add reward</a>');
?>
<?php if (!$rewards) empty_state('gift', 'No rewards yet.'); ?>
<section class="tile-grid small">
    <?php foreach ($rewards as $w): ?>
        <article class="reward-tile <?= $w['stock'] > 0 ? '' : 'is-off' ?>">
            <span class="stat-icon tint-gold"><?= icon('gift') ?></span>
            <div class="money"><?= rm($w['cash_value']) ?></div>
            <h2><?= e($w['reward_name']) ?></h2>
            <p class="muted"><?= e($w['description']) ?></p>
            <ul class="mini-stats">
                <li title="Points needed"><?= icon('star') ?> <?= (int) $w['points_required'] ?> pts</li>
                <li title="Stock left" class="<?= $w['stock'] > 0 ? '' : 'text-danger' ?>"><?= icon('box') ?> <?= (int) $w['stock'] ?> left</li>
                <li title="Times redeemed"><?= icon('history') ?> <?= (int) $w['times_used'] ?> used</li>
            </ul>
            <footer>
                <a class="btn btn-outline btn-sm" href="reward_form.php?edit=<?= e($w['rewardID']) ?>"><?= icon('edit') ?> Edit</a>
                <?php if ($w['times_used'] == 0): ?>
                    <form method="post" data-confirm="Delete <?= e($w['reward_name']) ?>?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="rewardID" value="<?= e($w['rewardID']) ?>">
                        <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Delete</button>
                    </form>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
