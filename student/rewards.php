
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

if (empty($_SESSION['redeem_csrf']))
    $_SESSION['redeem_csrf'] = bin2hex(random_bytes(32));

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

$page_title = 'Points & Cashback';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">🎁</span>
    <div>
        <h1>Points & Cashback</h1>
        <p>Earn points and redeem cashback rewards.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="index.php" class="btn">&larr; Back to Dashboard</a>
    <h2>My Points Balance</h2>
    <h1><?= number_format($points) ?> points</h1>
    <hr>

    <h2>Available Cashback Rewards</h2>
    <?php if (!$rewards): ?><p>No rewards available.</p><?php endif; ?>

    <?php if ($rewards): ?>
        <div class="reward-grid">
        <?php foreach ($rewards as $r):
            $required = (int)$r['points_required'];
            $stock = (int)$r['stock'];
            $canRedeem = $points >= $required && $stock > 0;
        ?>
            <div class="card reward-card">
                <h3><?= e($r['reward_name']) ?></h3>
                <p><?= e($r['description']) ?></p>
                <h2><?= e(rm($r['cash_value'])) ?></h2>
                <p>Points: <?= number_format($required) ?></p>
                <p>Stock: <?= $stock ?></p>

                <form method="post" action="redeem.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['redeem_csrf']) ?>">
                    <input type="hidden" name="rewardID" value="<?= e($r['rewardID']) ?>">
                    <button class="btn btn-primary" <?= !$canRedeem ? 'disabled' : '' ?>>
                        <?= $canRedeem ? 'Redeem' : "Can't Redeem" ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>My Redemption History</h2>
    <?php if (!$redemptions): ?><p>No redemptions yet.</p><?php endif; ?>

    <div class="table-wrap"><table>
        <tr><th>Reward</th><th>Points Used</th><th>Cashback</th><th>Status</th><th>Date</th></tr>
        <?php foreach ($redemptions as $r): ?>
            <tr>
                <td><?= e($r['reward_name']) ?></td>
                <td><?= (int)$r['points_used'] ?></td>
                <td><?= e(rm($r['cash_value'])) ?></td>
                <td><?= e(ucfirst($r['status'])) ?></td>
                <td><?= e(nice_date($r['requested_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <hr>

    <h2>Points Transaction History</h2>
    <?php if (!$transactions): ?><p>No transactions yet.</p><?php endif; ?>

    <div class="table-wrap"><table>
        <tr><th>Description</th><th>Type</th><th>Points</th><th>Date</th></tr>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= e($t['description'] ?: '-') ?></td>
                <td><?= e(ucfirst($t['txn_type'])) ?></td>
                <td><?= $t['points'] > 0 ? '+' : '' ?><?= (int)$t['points'] ?></td>
                <td><?= e(nice_date($t['created_at'], true)) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</section>

<?php require '../includes/student_bottom.php'; ?>
