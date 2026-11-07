<?php
/*
 * Rewards - the page (shared by student/rewards.php and volunteer/points.php).
 * Needs: everything from rewards_actions.php, plus
 *   $earn_rules = [[icon, title, text], ...]   how THIS role earns points (students and volunteers earn differently)
 */
?>

<!-- 1. My balance, on its own row -->
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
        <div><strong class="stat-value"><?= (int) $waiting ?></strong><span class="stat-title">Waiting for the admin to pay</span></div>
    </div>
</section>

<!-- 2. Rewards: same cards as the admin Rewards page -->
<h2 class="section-title"><?= icon('gift') ?> Available cashback rewards</h2>
<?php if (!$rewards) empty_state('gift', 'No rewards available yet.'); ?>
<section class="tile-grid small">
    <?php foreach ($rewards as $r):
        $required = (int) $r['points_required'];
        $stock = (int) $r['stock'];
        $rate = (float) $r['cash_value'] > 0 ? (int) round($required / (float) $r['cash_value']) : 0;   // points for RM1
    ?>
        <article class="reward-tile <?= $stock > 0 ? '' : 'is-off' ?>">
            <span class="stat-icon tint-gold"><?= icon('gift') ?></span>
            <div class="money"><?= e(rm($r['cash_value'])) ?></div>
            <h2><?= e($r['reward_name']) ?></h2>
            <p class="muted"><?= e($r['description']) ?></p>
            <ul class="mini-stats">
                <li title="Points needed"><?= icon('star') ?> <?= number_format($required) ?> pts</li>
                <li title="Stock left" class="<?= $stock > 0 ? '' : 'text-danger' ?>"><?= icon('box') ?> <?= $stock ?> left</li>
                <li title="Bigger rewards give more ringgit per point"><?= icon('percent') ?> <?= $rate ?> pts = RM1</li>
            </ul>
            <footer>
                <?php if ($stock > 0 && $points >= $required): ?>
                    <form method="post" data-confirm="Redeem <?= e($r['reward_name']) ?> for <?= number_format($required) ?> points?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="rewardID" value="<?= e($r['rewardID']) ?>">
                        <button class="btn btn-primary btn-sm"><?= icon('check') ?> Redeem</button>
                    </form>
                <?php elseif ($stock <= 0): ?>
                    <span class="lock-note"><?= icon('lock') ?> Out of stock</span>
                <?php else: ?>
                    <!-- Tell the user why they cannot redeem yet -->
                    <span class="lock-note"><?= icon('lock') ?> Need <?= number_format($required - $points) ?> more points</span>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>

<!-- 3. History: "No ... yet" sits inside the table, under the headings -->
<h2 class="section-title"><?= icon('history') ?> My redemption history</h2>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Reward</th><th>Points used</th><th>Cashback</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (!$redemptions) empty_row(5, 'gift', 'No redemptions yet.'); ?>
        <?php foreach ($redemptions as $r):
            $badge = ['pending' => 'badge-warn', 'completed' => 'badge-ok', 'cancelled' => 'badge-off'][$r['status']] ?? '';
            $word  = ['pending' => 'Waiting', 'completed' => 'Paid', 'cancelled' => 'Rejected'][$r['status']] ?? $r['status'];
        ?>
            <tr>
                <td><strong><?= e($r['reward_name']) ?></strong></td>
                <td><?= (int) $r['points_used'] ?></td>
                <td><?= e(rm($r['cash_value'])) ?></td>
                <td><span class="badge <?= $badge ?>"><?= e($word) ?></span></td>
                <td><?= e(nice_date($r['requested_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<div class="two-col">
    <div>
        <h2 class="section-title"><?= icon('list') ?> Points history</h2>
        <section class="card">
            <div class="table-wrap"><table>
                <thead><tr><th>Description</th><th>Type</th><th>Points</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (!$transactions) empty_row(4, 'star', 'No points yet.'); ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= e($t['description'] ?: '-') ?></td>
                        <td><span class="badge <?= $t['txn_type'] === 'redeem' ? 'badge-warn' : 'badge-ok' ?>"><?= e(ucfirst($t['txn_type'])) ?></span></td>
                        <td class="<?= $t['points'] < 0 ? 'text-danger' : 'points-plus' ?>"><strong><?= $t['points'] > 0 ? '+' : '' ?><?= (int) $t['points'] ?></strong></td>
                        <td><?= e(nice_date($t['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    </div>

    <!-- 4. How points work: the same formula for everyone, but each role earns in a different way -->
    <div>
        <h2 class="section-title"><?= icon('calculator') ?> How points work</h2>
        <section class="card">
            <p class="formula">points = weight (kg) × category multiplier<br><span>rounded down, never more than the category maximum</span></p>
            <ul class="activity">
                <?php foreach ($earn_rules as [$ico, $title, $text]): ?>
                    <li><span class="activity-dot"><?= icon($ico) ?></span><div><strong><?= e($title) ?></strong><span class="muted block"><?= e($text) ?></span></div></li>
                <?php endforeach; ?>
            </ul>
            <div class="table-wrap"><table>
                <thead><tr><th>Category</th><th class="v-num">Multiplier</th><th class="v-num">Max per device</th></tr></thead>
                <tbody>
                <?php foreach ($cats as $c): ?>
                    <tr>
                        <td><?= e($c['category_name']) ?></td>
                        <td class="v-num">×<?= e(rtrim(rtrim(number_format((float) $c['multiplier'], 2, '.', ''), '0'), '.')) ?></td>
                        <td class="v-num"><?= (int) $c['max_points'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="muted"><small>Batteries are light but hazardous, so they get a high multiplier. Heavy items are capped to keep it fair.</small></p>
        </section>
    </div>
</div>
