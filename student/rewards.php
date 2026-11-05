
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
if (empty($_SESSION['redeem_csrf'])) {
    $_SESSION['redeem_csrf'] = bin2hex(random_bytes(32));
}

// Points balance is calculated from transaction history.
$pointsBalance = (int) db_value(
    $conn,
    "SELECT COALESCE(SUM(points), 0)
     FROM points_transactions
     WHERE tp_number = ?",
    's',
    [$studentTP]
);

// Retrieve all cashback rewards.
$rewards = db_all(
    $conn,
    "SELECT rewardID, reward_name, description,
            points_required, cash_value, stock
     FROM rewards
     ORDER BY points_required ASC"
);

// Retrieve this student's redemption history.
$redemptions = db_all(
    $conn,
    "SELECT r.redemptionID, rw.reward_name,
            r.points_used, r.cash_value,
            r.status, r.requested_at
     FROM redemptions r
     JOIN rewards rw ON r.rewardID = rw.rewardID
     WHERE r.tp_number = ?
     ORDER BY r.requested_at DESC, r.redemptionID DESC",
    's',
    [$studentTP]
);

// Retrieve points transaction history.
$transactions = db_all(
    $conn,
    "SELECT points, txn_type, description, created_at
     FROM points_transactions
     WHERE tp_number = ?
     ORDER BY created_at DESC, transactionID DESC",
    's',
    [$studentTP]
);
?>

<?php $page_title = 'Points & Cashback'; require '../includes/student_top.php'; ?>
<div class="student-page-head"><span class="page-icon"><?= icon('wallet') ?></span><div><span class="student-eyebrow">STUDENT PORTAL</span><h1>Points &amp; Cashback</h1><p>Manage your e-waste journey in one place.</p></div></div>
<section class="card student-panel">


    <a href="index.php">← Back to Dashboard</a>

    

    <p>Earn points by participating in e-waste repair and recycling.</p>

    

    <section>
        <h2>My Points Balance</h2>
        <h1><?= number_format($pointsBalance) ?> points</h1>
    </section>

    <hr>

    <section>
        <h2>Available Cashback Rewards</h2>

        <?php if (empty($rewards)): ?>
            <p>No cashback rewards are currently available.</p>
        <?php else: ?>

            <?php foreach ($rewards as $reward): ?>

                <?php
                $required = (int) $reward['points_required'];
                $stock = (int) $reward['stock'];

                $enoughPoints = $pointsBalance >= $required;
                $inStock = $stock > 0;
                $canRedeem = $enoughPoints && $inStock;
                ?>

                <div style="
                    border: 1px solid #d7e4dc;
                    border-radius: 12px;
                    padding: 18px;
                    margin: 15px 0;
                ">

                    <h3><?= e($reward['reward_name']) ?></h3>

                    <p><?= e($reward['description']) ?></p>

                    <p>
                        <strong>Cashback:</strong>
                        <?= e(rm($reward['cash_value'])) ?>
                    </p>

                    <p>
                        <strong>Points Required:</strong>
                        <?= $required ?> points
                    </p>

                    <p>
                        <strong>Stock:</strong>
                        <?= $stock ?>
                    </p>

                    <?php if (!$inStock): ?>

                        <p>Out of stock</p>

                    <?php elseif (!$enoughPoints): ?>

                        <p>
                            You need
                            <?= $required - $pointsBalance ?>
                            more points to redeem this reward.
                        </p>

                    <?php endif; ?>

                    <form method="POST" action="redeem.php">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['redeem_csrf']) ?>"
                        >
                        
                        <input
                            type="hidden"
                            name="rewardID"
                            value="<?= e($reward['rewardID']) ?>"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= !$canRedeem ? 'disabled' : '' ?>
                        >
                            Redeem
                        </button>
                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </section>

    <hr>

    <section>
        <h2>My Redemption History</h2>

        <?php if (empty($redemptions)): ?>

            <p>You haven't redeemed any rewards yet.</p>

        <?php else: ?>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Reward</th>
                            <th>Points Used</th>
                            <th>Cashback</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($redemptions as $redemption): ?>
                            <tr>
                                <td><?= e($redemption['reward_name']) ?></td>
                                <td><?= (int) $redemption['points_used'] ?></td>
                                <td><?= e(rm($redemption['cash_value'])) ?></td>
                                <td><?= e(ucfirst($redemption['status'])) ?></td>
                                <td>
                                    <?= e(nice_date(
                                        $redemption['requested_at'],
                                        true
                                    )) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <hr>

    <section>
        <h2>Points Transaction History</h2>

        <?php if (empty($transactions)): ?>

            <p>No points transactions yet.</p>

        <?php else: ?>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <?= e($transaction['description'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e(ucfirst($transaction['txn_type'])) ?>
                                </td>

                                <td>
                                    <?= (int) $transaction['points'] > 0 ? '+' : '' ?>
                                    <?= (int) $transaction['points'] ?>
                                </td>

                                <td>
                                    <?= e(nice_date(
                                        $transaction['created_at'],
                                        true
                                    )) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>


</section>
<?php require '../includes/student_bottom.php'; ?>
