
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
$error = '';

if (empty($_SESSION['volunteer_csrf'])) {
    $_SESSION['volunteer_csrf'] = bin2hex(random_bytes(32));
}

$latestRequest = db_one(
    $conn,
    "SELECT requestID, reason, status, requested_at, reviewed_at
     FROM volunteer_request
     WHERE tp_number = ?
     ORDER BY requested_at DESC, requestID DESC
     LIMIT 1",
    's',
    [$studentTP]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!is_string($token) ||
        !hash_equals($_SESSION['volunteer_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif ($reason === '' || strlen($reason) > 255) {
        $error = 'Please enter a reason (maximum 255 characters).';
    } else {
        try {
            $conn->begin_transaction();

            // Lock the student row to prevent duplicate
            // requests from simultaneous submissions.
            $student = db_one(
                $conn,
                "SELECT tp_number FROM students
                 WHERE tp_number = ?
                 FOR UPDATE",
                's',
                [$studentTP]
            );

            if (!$student) {
                throw new RuntimeException('Student account not found.');
            }

            $pending = db_one(
                $conn,
                "SELECT requestID FROM volunteer_request
                 WHERE tp_number = ? AND status = 'pending'
                 LIMIT 1",
                's',
                [$studentTP]
            );

            if ($pending) {
                throw new RuntimeException(
                    'You already have a pending volunteer request.'
                );
            }

            $requestID = next_id($conn, 'volunteer_request', 'requestID', 'VR');
            db_exec(
                $conn,
                "INSERT INTO volunteer_request
                 (requestID, tp_number, reason, status)
                 VALUES (?, ?, ?, 'pending')",
                'sss',
                [$requestID, $studentTP, $reason]
            );

            $user = db_one(
                $conn,
                "SELECT name FROM users WHERE tp_number = ?",
                's',
                [$studentTP]
            );

            notify_admins(
                $conn,
                'New volunteer request',
                ($user['name'] ?? $studentTP) .
                ' submitted a Repair Volunteer application.'
            );

            $conn->commit();

            $_SESSION['volunteer_csrf'] = bin2hex(random_bytes(32));

            set_flash(
                'success',
                'Your volunteer application has been submitted.'
            );

            redirect('volunteer-request.php');

        } catch (Throwable $exception) {
            $conn->rollback();

            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log($exception->getMessage());
                $error = 'Unable to submit your request. Please try again.';
            }
        }
    }
}

// Refresh latest request for display.
$latestRequest = db_one(
    $conn,
    "SELECT requestID, reason, status, requested_at, reviewed_at
     FROM volunteer_request
     WHERE tp_number = ?
     ORDER BY requested_at DESC, requestID DESC
     LIMIT 1",
    's',
    [$studentTP]
);

$hasPending = $latestRequest &&
    $latestRequest['status'] === 'pending';
?>

<?php $page_title = 'Become a Volunteer'; require '../includes/student_top.php'; ?>
<div class="student-page-head"><span class="page-icon"><?= icon('heart') ?></span><div><span class="student-eyebrow">STUDENT PORTAL</span><h1>Become a Volunteer</h1><p>Manage your e-waste journey in one place.</p></div></div>
<section class="card student-panel">


    <a href="index.php">← Back to Dashboard</a>

    

    <p>
        Interested in repairing electronic devices?
        Apply to join our Repair Volunteer team.
    </p>

    

    <?php if ($error !== ''): ?>
        <p role="alert" style="color: #b42318;">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <?php if ($latestRequest): ?>
        <section>
            <h2>My Latest Application</h2>

            <p>
                <strong>Status:</strong>
                <?= e(ucfirst($latestRequest['status'])) ?>
            </p>

            <p>
                <strong>Reason:</strong>
                <?= e($latestRequest['reason']) ?>
            </p>

            <p>
                <strong>Submitted:</strong>
                <?= e(nice_date(
                    $latestRequest['requested_at'],
                    true
                )) ?>
            </p>
        </section>
        <hr>
    <?php endif; ?>

    <?php if ($hasPending): ?>

        <p>
            Your application is being reviewed by an Admin.
            You cannot submit another application while it is pending.
        </p>

    <?php else: ?>

        <h2>Volunteer Application</h2>

        <form method="POST" action="volunteer-request.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['volunteer_csrf']) ?>"
            >

            <label for="reason">
                Why do you want to become a Repair Volunteer?
            </label>

            <textarea
                id="reason"
                name="reason"
                maxlength="255"
                rows="5"
                required
                placeholder="Tell us about your interest or repair experience"
            ><?= e($_POST['reason'] ?? '') ?></textarea>

            <p>
                <button type="submit" class="btn btn-primary">
                    Submit Application
                </button>
            </p>

        </form>

    <?php endif; ?>


</section>
<?php require '../includes/student_bottom.php'; ?>
