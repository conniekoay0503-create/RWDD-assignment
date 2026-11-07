<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// get my points and available rewards
$points = (int)db_value($conn, "SELECT COALESCE(SUM(points),0)
    FROM points_transactions WHERE tp_number = ?", 's', [$tp]);

$rewards = db_all($conn, "SELECT * FROM rewards ORDER BY points_required ASC");

// get my redemption and points history
$redemptions = db_all($conn, "SELECT r.*, rw.reward_name FROM redemptions r
    JOIN rewards rw ON r.rewardID = rw.rewardID
    WHERE r.tp_number = ? ORDER BY r.requested_at DESC, r.redemptionID DESC", 's', [$tp]);

$transactions = db_all($conn, "SELECT * FROM points_transactions
    WHERE tp_number = ? ORDER BY created_at DESC, transactionID DESC", 's', [$tp]);

// numbers for the top cards (worked out from the lists above, no extra query)
$paid = 0; $waiting = 0;
foreach ($redemptions as $r) {
    if ($r['status'] === 'completed') $paid += (float)$r['cash_value'];
    if ($r['status'] === 'pending') $waiting++;
}

$page_title = 'Points & Cashback';
require '../includes/student_top.php';
page_head('wallet', 'Points & Cashback', 'Earn points for every device, then swap them for APCard cashback.');
?>

<!-- 7. My balance on its own, separate from the rewards -->
<section class="stat-grid three">
    <div class="stat-card row">
        <span class="stat-icon tint-gold"><?= icon('star') ?></span>
        <div><strong class="stat-value"><?= number_format($points) ?><small>pts</small></strong><span class="stat-title">My points balance</span></div>
    </div>
    <div class="stat-card row">
        <span class="stat-icon tint-mint"><?= icon('wallet') ?></span>
        <div><strong class="stat-value"><?= e(rm($paid)) ?></strong><span class="stat-title">Cashback received</span></div>
    </div>
    <div class="stat-card row">
        <span class="stat-icon tint-sky"><?= icon('clock') ?></span>
        <div><strong class="stat-value"><?= (int)$waiting ?></strong><span class="stat-title">Waiting for the admin to pay</span></div>
    </div>
</section>

<!-- 5. Rewards: same cards as the admin Rewards page -->
<h2 class="section-title"><?= icon('gift') ?> Available cashback rewards</h2>
<?php if (!$rewards) empty_state('gift', 'No rewards available yet.'); ?>
<section class="tile-grid small">
    <?php foreach ($rewards as $r):
        $required = (int)$r['points_required'];
        $stock = (int)$r['stock'];
        $canRedeem = $points >= $required && $stock > 0;
    ?>
        <article class="reward-tile <?= $stock > 0 ? '' : 'is-off' ?>">
            <span class="stat-icon tint-gold"><?= icon('gift') ?></span>
            <div class="money"><?= e(rm($r['cash_value'])) ?></div>
            <h2><?= e($r['reward_name']) ?></h2>
            <p class="muted"><?= e($r['description']) ?></p>
            <ul class="mini-stats">
                <li title="Points needed"><?= icon('star') ?> <?= number_format($required) ?> pts</li>
                <li title="Stock left" class="<?= $stock > 0 ? '' : 'text-danger' ?>"><?= icon('box') ?> <?= $stock ?> left</li>
            </ul>
            <footer>
                <?php if ($canRedeem): ?>
                    <form method="post" action="redeem.php" data-confirm="Redeem <?= e($r['reward_name']) ?> for <?= number_format($required) ?> points?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="rewardID" value="<?= e($r['rewardID']) ?>">
                        <button class="btn btn-primary btn-sm"><?= icon('check') ?> Redeem</button>
                    </form>
                <?php elseif ($stock <= 0): ?>
                    <span class="lock-note"><?= icon('lock') ?> Out of stock</span>
                <?php else: ?>
                    <!-- Tell the student why they cannot redeem yet -->
                    <span class="lock-note"><?= icon('lock') ?> Need <?= number_format($required - $points) ?> more points</span>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>

<!-- 6. "No ... yet" sits inside the table, under the headings -->
<h2 class="section-title"><?= icon('history') ?> My redemption history</h2>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Reward</th><th>Points used</th><th>Cashback</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (!$redemptions) empty_row(5, 'gift', 'No redemptions yet.'); ?>
        <?php foreach ($redemptions as $r):
            $badge = ['pending' => 'badge-warn', 'completed' => 'badge-ok', 'cancelled' => 'badge-off'][$r['status']] ?? '';
        ?>
            <tr>
                <td><strong><?= e($r['reward_name']) ?></strong></td>
                <td><?= (int)$r['points_used'] ?></td>
                <td><?= e(rm($r['cash_value'])) ?></td>
                <td><span class="badge <?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td><?= e(nice_date($r['requested_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<h2 class="section-title"><?= icon('list') ?> Points history</h2>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Description</th><th>Type</th><th>Points</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (!$transactions) empty_row(4, 'star', 'No transactions yet. Submit a device to start earning points.'); ?>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= e($t['description'] ?: '-') ?></td>
                <td><span class="badge <?= $t['txn_type'] === 'redeem' ? 'badge-warn' : 'badge-ok' ?>"><?= e(ucfirst($t['txn_type'])) ?></span></td>
                <td class="<?= $t['points'] < 0 ? 'text-danger' : 'points-plus' ?>"><strong><?= $t['points'] > 0 ? '+' : '' ?><?= (int)$t['points'] ?></strong></td>
                <td><?= e(nice_date($t['created_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<?php require '../includes/student_bottom.php'; ?>
