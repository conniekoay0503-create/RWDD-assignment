<?php
/*
 * Points & cashback (wireframe Figure 61)
 * Redeem = a POST. The real work is in vol_redeem() (includes/volunteer_logic.php).
 */
require '../config/db.php';
require '../includes/functions.php';
require '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reward_id = $_POST['reward_id'] ?? '';
    if (!vol_csrf_ok($_POST['csrf'] ?? null)) {
        set_flash('error', 'Session expired. Please try again.');
    } elseif (!is_string($reward_id) || !preg_match('/^RW\d{3,7}$/', $reward_id)) {
        set_flash('error', 'Invalid reward.');
    } else {
        try {
            [$ok, $msg] = vol_redeem($conn, $me, $reward_id);
            set_flash($ok ? 'success' : 'error', $msg);
        } catch (Throwable $ex) {
            error_log('vol_redeem failed: ' . $ex->getMessage());
            set_flash('error', 'Something went wrong. Please try again.');
        }
    }
    redirect('points.php');   // Post/Redirect/Get
}

$balance  = vol_points_balance($conn, $me);
$rewards  = vol_rewards($conn);
$history  = vol_points_history($conn, $me, null, null, 10);
$requests = db_all($conn,
    "SELECT r.requested_at, r.points_used, r.status, w.reward_name
       FROM redemptions r JOIN rewards w ON w.rewardID = r.rewardID
      WHERE r.tp_number = ? ORDER BY r.requested_at DESC, r.redemptionID DESC", 's', [$me]);
$cats = db_all($conn, "SELECT category_name, multiplier, max_points FROM categories WHERE is_active = 1 ORDER BY multiplier DESC, category_name");

$status_text  = ['pending' => 'Pending', 'completed' => 'Paid', 'cancelled' => 'Cancelled'];
$status_class = ['pending' => 'badge-warn', 'completed' => 'badge-ok', 'cancelled' => 'badge-off'];

$page_title = 'Points & cashback';
$active = 'points';
require '../includes/volunteer_top.php';

page_head('wallet', 'Points & cashback', 'Turn your points into cash. Bigger tiers give you more ringgit per point.',
    '<span class="badge badge-ok" style="font-size: 1rem; padding: 0.4rem 1rem">' . $balance . ' pts</span>');
?>

<div class="tile-grid small">
    <?php foreach ($rewards as $rw):
        $need = (int) $rw['points_required'] - $balance;
    ?>
        <article class="v-card">
            <div class="money">RM<?= number_format((float) $rw['cash_value'], 0) ?></div>
            <p style="margin: 0"><strong><?= (int) $rw['points_required'] ?> pts</strong>
                <small class="muted"><?= (int) round($rw['points_required'] / (float) $rw['cash_value']) ?> pts = RM1</small></p>
            <small class="muted"><?= e($rw['description']) ?></small>
            <footer>
                <?php if ((int) $rw['stock'] <= 0): ?>
                    <button type="button" class="btn btn-outline btn-block" disabled>Out of stock</button>
                <?php elseif ($need > 0): ?>
                    <button type="button" class="btn btn-outline btn-block" disabled>Need <?= $need ?> more pts</button>
                <?php else: ?>
                    <form method="post" action="points.php" style="width: 100%"
                          data-confirm="Redeem <?= e($rw['reward_name']) ?> for <?= (int) $rw['points_required'] ?> points?">
                        <?= vol_csrf_field() ?>
                        <input type="hidden" name="reward_id" value="<?= e($rw['rewardID']) ?>">
                        <button type="submit" class="btn btn-primary btn-block">Redeem</button>
                    </form>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</div>

<section class="card">
    <div class="card-head"><h2 class="card-title"><?= icon('clock') ?> My cashback requests</h2></div>
    <?php if (!$requests): ?>
        <?php empty_state('wallet', 'You have not requested any cashback yet.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Requested</th><th>Reward</th><th>Points</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= nice_date($r['requested_at']) ?></td>
                        <td><?= e($r['reward_name']) ?></td>
                        <td><?= (int) $r['points_used'] ?></td>
                        <td><span class="badge <?= e($status_class[$r['status']] ?? '') ?>"><?= e($status_text[$r['status']] ?? $r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="two-col">
    <section class="card">
        <div class="card-head"><h2 class="card-title"><?= icon('history') ?> History</h2></div>
        <?php if (!$history): ?>
            <?php empty_state('star', 'No points yet.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>What</th><th class="v-num">Points</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): $pts = (int) $h['points']; ?>
                        <tr>
                            <td><?= nice_date($h['created_at']) ?></td>
                            <td><?= e($h['description']) ?></td>
                            <td class="v-num <?= $pts < 0 ? 'v-neg' : '' ?>"><?= $pts > 0 ? '+' . $pts : $pts ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title"><?= icon('calculator') ?> How points work</h2></div>
        <p class="formula">points = weight (kg) × category multiplier<br><span>capped at the category maximum, rounded down</span></p>
        <p class="muted">The owner of a repaired device earns double. A volunteer earns the basic amount for each device that is repaired.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Category</th><th class="v-num">Multiplier</th><th class="v-num">Max</th></tr></thead>
                <tbody>
                <?php foreach ($cats as $c): ?>
                    <tr><td><?= e($c['category_name']) ?></td><td class="v-num">×<?= e(rtrim(rtrim(number_format((float) $c['multiplier'], 2, '.', ''), '0'), '.')) ?></td><td class="v-num"><?= (int) $c['max_points'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><small>Batteries are light but hazardous, so they get a high multiplier. Heavy items are capped to keep it fair.</small></p>
    </section>
</div>

<?php require '../includes/volunteer_bottom.php'; ?>
