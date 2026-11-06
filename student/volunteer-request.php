
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$error = '';

if (empty($_SESSION['volunteer_csrf']))
    $_SESSION['volunteer_csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['volunteer_csrf'], $token))
        $error = 'Invalid request.';
    elseif ($reason === '' || mb_strlen($reason) > 255)
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
            $_SESSION['volunteer_csrf'] = bin2hex(random_bytes(32));
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
?>

<div class="student-page-head">
    <span class="page-icon">🤝</span>
    <div>
        <h1>Become a Volunteer</h1>
        <p>Apply to help repair electronic devices.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="index.php" class="btn">&larr; Back to Dashboard</a>
    <h2>Volunteer Application</h2>

    <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

    <?php if ($pending): ?>
        <p>You already have a pending application.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['volunteer_csrf']) ?>">
            <label for="reason">Why do you want to volunteer?</label>
            <textarea id="reason" name="reason" maxlength="255" required><?= e($_POST['reason'] ?? '') ?></textarea>
            <button class="btn btn-primary">Submit Application</button>
        </form>
    <?php endif; ?>
</section>

<section class="card student-panel">
    <h2>Application History</h2>
    <?php if (!$requests): ?><p>No applications yet.</p><?php endif; ?>

    <div class="table-wrap"><table>
        <tr><th>Reason</th><th>Status</th><th>Requested</th><th>Reviewed</th></tr>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?= e($r['reason']) ?></td>
                <td><span class="badge"><?= e(ucfirst($r['status'])) ?></span></td>
                <td><?= e(nice_date($r['requested_at'])) ?></td>
                <td><?= e(nice_date($r['reviewed_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</section>

<?php require '../includes/student_bottom.php'; ?>
