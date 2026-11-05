<?php
/*
 * Cashback - history of reviewed requests  (Figure 24, page 2 of 4)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

$filter = $_GET['filter'] ?? 'all';
$where  = ['all' => "r.status <> 'pending'", 'paid' => "r.status = 'completed'", 'rejected' => "r.status = 'cancelled'"];
if (!isset($where[$filter])) $filter = 'all';   // only the 3 known filters reach the SQL

$history = db_all($conn, "SELECT r.status, r.cash_value, r.points_used, r.reviewed_at, u.name, u.tp_number, w.reward_name, a.name AS admin_name
                          FROM redemptions r
                          JOIN users u ON u.tp_number = r.tp_number
                          JOIN rewards w ON w.rewardID = r.rewardID
                          LEFT JOIN users a ON a.tp_number = r.reviewed_by
                          WHERE " . $where[$filter] . " ORDER BY r.reviewed_at DESC");

$page_title = 'Cashback history';
$active = 'cashback';
require '../includes/admin_top.php';
page_head('history', 'Cashback history', 'Every request that has been paid or rejected.');
?>
<section class="card">
    <div class="card-head">
        <nav class="pill-tabs" aria-label="Filter history">
            <?php foreach (['all' => ['All', 'list'], 'paid' => ['Paid', 'check'], 'rejected' => ['Rejected', 'x']] as $k => [$label, $ico]): ?>
                <a href="cashback_history.php?filter=<?= $k ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= icon($ico) ?> <?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php if (!$history): ?>
        <?php empty_state('history', 'No requests here yet.'); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Student</th><th>Reward</th><th>Amount</th><th>Result</th><th>Reviewed by</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h['name']) ?> <small class="muted"><?= e($h['tp_number']) ?></small></td>
                        <td><?= e($h['reward_name']) ?> <small class="muted">(<?= (int) $h['points_used'] ?> pts)</small></td>
                        <td><strong><?= rm($h['cash_value']) ?></strong></td>
                        <td><?php if ($h['status'] === 'completed'): ?><span class="badge badge-ok"><?= icon('check') ?> Paid</span><?php else: ?><span class="badge badge-off"><?= icon('x') ?> Rejected</span><?php endif; ?></td>
                        <td><?= e($h['admin_name'] ?? '-') ?></td>
                        <td><?= nice_date($h['reviewed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
