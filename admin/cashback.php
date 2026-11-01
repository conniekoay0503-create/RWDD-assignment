<?php
/*
 * Cashback - pending requests  (Figure 24, page 1 of 4)
 * Approve & pay: the admin then pays the amount to the student's APCard linked to their email.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['redemptionID'] ?? 0);

    // ---------- Approve & pay ----------
    // Stock was already taken when the student made the request, so approving only marks it as paid.
    if ($action === 'approve') {
        $red = db_one($conn, "SELECT r.tp_number, r.cash_value, u.email FROM redemptions r
                              JOIN users u ON u.tp_number = r.tp_number WHERE r.redemptionID = ?", 'i', [$id]);
        $conn->begin_transaction();
        try {
            // Mark completed, but only if it is still pending (stops two admins approving it twice)
            $n = db_exec($conn, "UPDATE redemptions SET status = 'completed', reviewed_by = ?, reviewed_at = NOW()
                                 WHERE redemptionID = ? AND status = 'pending'", 'si', [$me, $id]);
            if ($n !== 1) {
                throw new Exception('This request has already been reviewed.');
            }
            notify($conn, $red['tp_number'], 'Cashback paid', rm($red['cash_value']) . ' has been paid to the APCard linked to your email.', $me);
            $conn->commit();
            set_flash('success', 'Approved. Please pay ' . rm($red['cash_value']) . ' to the APCard linked to ' . $red['email'] . '.');
        } catch (Exception $ex) {
            $conn->rollback();   // undo everything if any step failed
            set_flash('error', $ex instanceof mysqli_sql_exception ? 'Something went wrong. Please try again.' : $ex->getMessage());
        }
        redirect('cashback.php');
    }

    // ---------- Reject (refund the points and give the stock back) ----------
    if ($action === 'reject') {
        $red = db_one($conn, "SELECT tp_number, points_used, rewardID FROM redemptions WHERE redemptionID = ?", 'i', [$id]);
        $conn->begin_transaction();
        try {
            $n = db_exec($conn, "UPDATE redemptions SET status = 'cancelled', reviewed_by = ?, reviewed_at = NOW()
                                 WHERE redemptionID = ? AND status = 'pending'", 'si', [$me, $id]);
            if ($n !== 1) {
                throw new Exception('This request has already been reviewed.');
            }
            // Ledger rule: never delete a transaction. Add a "+points" refund that cancels the "-points" redeem.
            db_exec($conn, "INSERT INTO points_transactions (tp_number, redemptionID, points, txn_type, description)
                            VALUES (?, ?, ?, 'refund', 'Refund: cashback request rejected')",
                'sii', [$red['tp_number'], $id, $red['points_used']]);
            // The request reserved one item when it was made, so give it back
            db_exec($conn, "UPDATE rewards SET stock = stock + 1 WHERE rewardID = ?", 'i', [$red['rewardID']]);
            notify($conn, $red['tp_number'], 'Cashback rejected', 'Your cashback request was rejected and your points have been returned.', $me);
            $conn->commit();
            set_flash('success', 'Request rejected. Points refunded and stock returned.');
        } catch (Exception $ex) {
            $conn->rollback();
            set_flash('error', $ex instanceof mysqli_sql_exception ? 'Something went wrong. Please try again.' : $ex->getMessage());
        }
        redirect('cashback.php');
    }
}

$waiting = db_one($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(cash_value), 0) AS total FROM redemptions WHERE status = 'pending'");
$paid    = db_value($conn, "SELECT COALESCE(SUM(cash_value), 0) FROM redemptions WHERE status = 'completed'
                            AND reviewed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$pending = db_all($conn, "SELECT r.redemptionID, r.points_used, r.cash_value, r.requested_at,
                                 u.name, u.tp_number, u.email, w.reward_name, w.stock
                          FROM redemptions r
                          JOIN users u ON u.tp_number = r.tp_number
                          JOIN rewards w ON w.rewardID = r.rewardID
                          WHERE r.status = 'pending' ORDER BY r.requested_at");

$page_title = 'Cashback';
$active = 'cashback';
require '../includes/admin_top.php';
page_head('wallet', 'Cashback requests', 'Approve a request, then pay the amount to the APCard linked to the student’s email.');
?>
<section class="stat-grid three">
    <div class="stat-card row"><span class="stat-icon tint-gold"><?= icon('clock') ?></span><div><strong class="stat-value"><?= rm($waiting['total']) ?></strong><span class="stat-title">Waiting · <?= (int) $waiting['n'] ?> request(s)</span></div></div>
    <div class="stat-card row"><span class="stat-icon tint-mint"><?= icon('check') ?></span><div><strong class="stat-value"><?= rm($paid) ?></strong><span class="stat-title">Paid this month</span></div></div>
    <a class="stat-card row" href="rewards.php"><span class="stat-icon tint-sky"><?= icon('gift') ?></span><div><strong class="stat-value"><?= (int) db_value($conn, "SELECT COUNT(*) FROM rewards") ?></strong><span class="stat-title">Rewards on offer</span></div></a>
</section>

<?php if (!$pending) empty_state('wallet', 'Nothing to pay right now.'); ?>
<section class="tile-grid">
    <?php foreach ($pending as $p): ?>
        <article class="request-card">
            <header>
                <span class="avatar small"><?= e(strtoupper(substr($p['name'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($p['name']) ?></strong>
                    <small class="muted"><?= e($p['tp_number']) ?> · <?= e($p['email']) ?></small>
                </div>
            </header>
            <div class="money"><?= rm($p['cash_value']) ?></div>
            <ul class="mini-stats">
                <li><?= icon('gift') ?> <?= e($p['reward_name']) ?></li>
                <li><?= icon('star') ?> <?= (int) $p['points_used'] ?> pts</li>
                <li><?= icon('box') ?> Stock <?= (int) $p['stock'] ?></li>
                <li><?= icon('calendar') ?> <?= nice_date($p['requested_at']) ?></li>
            </ul>
            <footer>
                <form method="post" data-confirm="Approve and pay <?= rm($p['cash_value']) ?> to <?= e($p['email']) ?>?">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="redemptionID" value="<?= (int) $p['redemptionID'] ?>">
                    <button class="btn btn-primary btn-sm"><?= icon('check') ?> Approve &amp; pay</button>
                </form>
                <form method="post" data-confirm="Reject this request? The points will be returned to the student.">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="redemptionID" value="<?= (int) $p['redemptionID'] ?>">
                    <button class="btn btn-outline btn-sm"><?= icon('x') ?> Reject</button>
                </form>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>