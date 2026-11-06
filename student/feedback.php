<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$error = '';

// get my submitted devices
$devices = db_all($conn,
    "SELECT deviceID, device_name FROM devices
     WHERE submitted_by = ? ORDER BY submitted_at DESC",
    's', [$tp]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['deviceID'] ?? '';
    $message = trim((string)($_POST['message'] ?? ''));
    // (the CSRF token was already checked by require_role())
    if ($message === '' || mb_strlen($message) > 255)
        $error = 'Feedback must be 1 to 255 characters.';
    else {
        // check if the device belongs to user
        $device = null;
        foreach ($devices as $d) {
            if ($d['deviceID'] === $id) $device = $d;
        }

        if (!$device) {
            $error = 'Please select a valid device.';
        } else {
            try {
                $conn->begin_transaction();

                $fid = next_id($conn, 'feedback', 'feedbackID', 'F');
                db_exec($conn,
                    "INSERT INTO feedback (feedbackID, tp_number, deviceID, message)
                     VALUES (?,?,?,?)",
                    'ssss', [$fid, $tp, $id, $message]);

                $user = db_one($conn,
                    "SELECT name FROM users WHERE tp_number = ?", 's', [$tp]);

                notify_admins($conn, 'New feedback',
                    ($user['name'] ?? $tp) . ' sent feedback about ' . $device['device_name'] . '.');

                $conn->commit();
                set_flash('success', 'Feedback submitted successfully.');
                redirect('feedback.php');

            } catch (Throwable $e) {
                $conn->rollback();
                error_log($e->getMessage());
                $error = 'Unable to submit feedback.';
            }
        }
    }
}

// show my feedback and admin replies
$feedback = db_all($conn,
    "SELECT f.*, d.device_name FROM feedback f
     JOIN devices d ON f.deviceID = d.deviceID
     WHERE f.tp_number = ? AND d.submitted_by = ?
     ORDER BY f.submitted_at DESC", 'ss', [$tp, $tp]);

$page_title = 'Feedback';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">💬</span>
    <div>
        <h1>Feedback</h1>
        <p>Share your experience or ask a question.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="index.php" class="btn">&larr; Back to Dashboard</a>
    <h2>Submit Feedback</h2>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$devices): ?>
        <p>Submit a device before sending feedback.</p>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>

            <label for="deviceID">Device</label>
            <select id="deviceID" name="deviceID" required>
                <option value="">Select a device</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= e($d['deviceID']) ?>"
                        <?= ($_POST['deviceID'] ?? '') === $d['deviceID'] ? 'selected' : '' ?>>
                        <?= e($d['device_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="message">Your Feedback</label>
            <textarea id="message" name="message" maxlength="255" required><?= e($_POST['message'] ?? '') ?></textarea>

            <button class="btn btn-primary">Submit Feedback</button>
        </form>
    <?php endif; ?>
</section>

<section class="card student-panel">
    <h2>My Feedback</h2>
    <?php if (!$feedback): ?><p>No feedback submitted yet.</p><?php endif; ?>

    <?php foreach ($feedback as $f): ?>
        <div class="card">
            <h3><?= e($f['device_name']) ?></h3>
            <p><?= e($f['message']) ?></p>
            <small><?= e(nice_date($f['submitted_at'], true)) ?></small>
            <p><strong>Admin Reply:</strong> <?= e($f['admin_reply'] ?: 'Awaiting reply') ?></p>
        </div>
    <?php endforeach; ?>
</section>

<?php require '../includes/student_bottom.php'; ?>
