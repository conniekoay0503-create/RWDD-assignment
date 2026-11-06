<?php
/*
 * Users - volunteer requests  (Figure 23, page 2 of 3)
 * Approve = remove the student role and add the volunteer role, in one transaction.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_request') {
    $requestID = trim($_POST['requestID'] ?? '');
    $decision  = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';

    $conn->begin_transaction();
    try {
        // Only a request that is still pending can be reviewed (stops two admins reviewing it twice)
        $n = db_exec($conn, "UPDATE volunteer_request SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                             WHERE requestID = ? AND status = 'pending'", 'sss', [$decision, $me, $requestID]);
        if ($n !== 1) {
            throw new Exception('This request has already been reviewed.');
        }
        $student = db_value($conn, "SELECT tp_number FROM volunteer_request WHERE requestID = ?", 's', [$requestID]);

        if ($decision === 'approved') {
            // A user has exactly one role: remove the student role, then add the volunteer role
            $deleted = db_exec($conn, "DELETE FROM students WHERE tp_number = ?", 's', [$student]);
            if ($deleted !== 1) {
                throw new Exception('This user is no longer a student, so the request cannot be approved.');
            }
            db_exec($conn, "INSERT INTO volunteers (tp_number, assigned_by) VALUES (?, ?)", 'ss', [$student, $me]);
            notify($conn, $student, 'Request approved', 'You are now a Repair Volunteer. Please log in again to open your volunteer dashboard.', $me);
        } else {
            notify($conn, $student, 'Request rejected', 'Your volunteer request was not approved this time.', $me);
        }
        $conn->commit();
        set_flash('success', 'Request ' . $decision . '.');
    } catch (Exception $ex) {
        $conn->rollback();
        set_flash('error', $ex instanceof mysqli_sql_exception ? 'Something went wrong. Please try again.' : $ex->getMessage());
    }
    redirect('volunteer_requests.php');
}

$pending  = db_all($conn, "SELECT r.requestID, r.tp_number, r.reason, r.requested_at, u.name, u.email
                           FROM volunteer_request r JOIN users u ON u.tp_number = r.tp_number
                           WHERE r.status = 'pending' ORDER BY r.requested_at");
$reviewed = db_all($conn, "SELECT r.status, r.reviewed_at, u.name, a.name AS admin_name
                           FROM volunteer_request r
                           JOIN users u ON u.tp_number = r.tp_number
                           LEFT JOIN users a ON a.tp_number = r.reviewed_by
                           WHERE r.status <> 'pending' ORDER BY r.reviewed_at DESC LIMIT 10");

$page_title = 'Volunteer requests';
$active = 'users';
require '../includes/admin_top.php';
page_head('heart', 'Volunteer requests', 'Students who want to repair devices. Approving replaces their student role.');
?>
<?php if (!$pending) empty_state('heart', 'No pending requests.'); ?>
<section class="tile-grid">
    <?php foreach ($pending as $r): ?>
        <article class="request-card">
            <header>
                <span class="avatar small"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($r['name']) ?></strong>
                    <small class="muted"><?= e($r['tp_number']) ?> · <?= e($r['email']) ?></small>
                </div>
            </header>
            <blockquote><?= e($r['reason']) ?></blockquote>
            <small class="muted"><?= icon('calendar') ?> Asked on <?= nice_date($r['requested_at']) ?></small>
            <footer>
                <form method="post" data-confirm="Approve <?= e($r['name']) ?> as a Repair Volunteer?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review_request">
                    <input type="hidden" name="requestID" value="<?= e($r['requestID']) ?>">
                    <input type="hidden" name="decision" value="approved">
                    <button class="btn btn-primary btn-sm"><?= icon('check') ?> Approve</button>
                </form>
                <form method="post" data-confirm="Reject this request?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review_request">
                    <input type="hidden" name="requestID" value="<?= e($r['requestID']) ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <button class="btn btn-outline btn-sm"><?= icon('x') ?> Reject</button>
                </form>
            </footer>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($reviewed): ?>
    <section class="card">
        <h2 class="card-title"><?= icon('history') ?> Recently reviewed</h2>
        <ul class="simple-list">
            <?php foreach ($reviewed as $v): ?>
                <li>
                    <span class="badge <?= $v['status'] === 'approved' ? 'badge-ok' : 'badge-off' ?>"><?= icon($v['status'] === 'approved' ? 'check' : 'x') ?> <?= ucfirst($v['status']) ?></span>
                    <strong><?= e($v['name']) ?></strong>
                    <small class="muted">by <?= e($v['admin_name'] ?? '-') ?> · <?= nice_date($v['reviewed_at']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php require '../includes/admin_bottom.php'; ?>
