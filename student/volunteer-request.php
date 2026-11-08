<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    // (the CSRF token was already checked by require_role())
    if ($reason === '' || mb_strlen($reason) > 255)
        $error = 'Reason must be 1 to 255 characters.';
    else {
        try {
            $conn->begin_transaction();

            // lock my account and check for pending requests
            $student = db_one($conn, "SELECT tp_number FROM students
                WHERE tp_number = ? FOR UPDATE", 's', [$tp]);

            if (!$student) throw new RuntimeException('Student account not found.');

            $pending = db_one($conn, "SELECT requestID FROM volunteer_request
                WHERE tp_number = ? AND status = 'pending'", 's', [$tp]);

            if ($pending) throw new RuntimeException('You already have a pending request.');

            // save my application
            $id = next_id($conn, 'volunteer_request', 'requestID', 'VR');
            db_exec($conn, "INSERT INTO volunteer_request
                (requestID, tp_number, reason, status)
                VALUES (?,?,?,'pending')", 'sss', [$id, $tp, $reason]);

            $user = db_one($conn, "SELECT name FROM users WHERE tp_number = ?", 's', [$tp]);

            notify_admins($conn, 'New volunteer request',
                ($user['name'] ?? $tp) . ' submitted a Repair Volunteer application.');

            $conn->commit();
            set_flash('success', 'Volunteer application submitted.');
            redirect('volunteer-request.php');

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof RuntimeException
                ? $e->getMessage() : 'Unable to submit request.';
            if (!($e instanceof RuntimeException)) error_log($e->getMessage());
        }
    }
}

// show my previous applications
$requests = db_all($conn, "SELECT requestID, reason, status, requested_at, reviewed_at
    FROM volunteer_request WHERE tp_number = ?
    ORDER BY requested_at DESC", 's', [$tp]);

$pending = in_array('pending', array_column($requests, 'status'), true);

$page_title = 'Become a Volunteer';
require '../includes/student_top.php';
page_head('heart', 'Become a Volunteer', 'Help repair devices at the Green Hub and earn points for every repair.');
$badge = ['pending' => 'badge-warn', 'approved' => 'badge-ok', 'rejected' => 'badge-off'];
?>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" data-flash="error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<div class="two-col">
    <div>
        <h2 class="section-title"><?= icon('send') ?> Volunteer application</h2>
        <section class="card">
            <?php if ($pending): ?>
                <?php empty_state('clock', 'Your application is waiting for an admin. We will notify you when it is reviewed.'); ?>
            <?php else: ?>
                <form method="post" novalidate data-validate data-confirm="Send your volunteer application?">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="reason"><?= icon('heart') ?> Why do you want to volunteer? <small class="muted" data-count-for="reason"></small></label>
                        <textarea id="reason" name="reason" rows="4" maxlength="255" required
                            placeholder="e.g. I study electronics and I have fixed phones for my family"><?= e($_POST['reason'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary"><?= icon('send') ?> Send application</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <h2 class="section-title"><?= icon('info') ?> What volunteers do</h2>
        <section class="card">
            <ul class="activity">
                <li><span class="activity-dot"><?= icon('device') ?></span><div>Claim a dropped-off device from the repair queue</div></li>
                <li><span class="activity-dot"><?= icon('wrench') ?></span><div>Repair it at the Green Hub and write short notes for the owner</div></li>
                <li><span class="activity-dot"><?= icon('star') ?></span><div>Earn points for every device you repair</div></li>
            </ul>
        </section>
    </div>
</div>

<h2 class="section-title"><?= icon('history') ?> Application history</h2>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Reason</th><th>Status</th><th>Requested</th><th>Reviewed</th></tr></thead>
        <tbody>
        <?php if (!$requests) empty_row(4, 'heart', 'No applications yet.'); ?>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?= e($r['reason']) ?></td>
                <td><span class="badge <?= $badge[$r['status']] ?? '' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td><?= e(nice_date($r['requested_at'])) ?></td>
                <td><?= $r['reviewed_at'] ? e(nice_date($r['reviewed_at'])) : '<span class="muted">-</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<?php require '../includes/bottom.php'; ?>
