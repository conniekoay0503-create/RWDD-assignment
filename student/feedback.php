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
page_head('message', 'Feedback', 'Tell us how your repair went, or ask the admins a question.');
$me = mb_strtoupper(mb_substr($_SESSION['name'] ?? 'S', 0, 1));
?>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" data-flash="error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<div class="two-col">
    <div>
        <h2 class="section-title"><?= icon('send') ?> Send feedback</h2>
        <section class="card">
            <?php if (!$devices): ?>
                <?php empty_state('device', 'Submit a device first, then you can send feedback about it.',
                    '<a class="btn btn-primary btn-sm" href="submit-device.php">' . icon('plus') . ' Submit Device</a>'); ?>
            <?php else: ?>
                <form method="post" novalidate data-validate data-confirm="Send this feedback to the admins?">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="deviceID"><?= icon('device') ?> Device</label>
                        <select id="deviceID" name="deviceID" required>
                            <option value="">Select a device</option>
                            <?php foreach ($devices as $d): ?>
                                <option value="<?= e($d['deviceID']) ?>"
                                    <?= ($_POST['deviceID'] ?? '') === $d['deviceID'] ? 'selected' : '' ?>>
                                    <?= e($d['device_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="message"><?= icon('message') ?> Your feedback <small class="muted" data-count-for="message"></small></label>
                        <textarea id="message" name="message" rows="4" maxlength="255" required><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary"><?= icon('send') ?> Send feedback</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <h2 class="section-title"><?= icon('inbox') ?> My feedback (<?= count($feedback) ?>)</h2>
        <?php if (!$feedback): ?>
            <section class="card"><?php empty_state('message', 'No feedback sent yet.'); ?></section>
        <?php endif; ?>
        <?php foreach ($feedback as $f): ?>
            <section class="card">
                <div class="card-head">
                    <h3 class="card-title"><?= icon('device') ?> <?= e($f['device_name']) ?></h3>
                    <span class="badge <?= $f['admin_reply'] ? 'badge-ok' : 'badge-warn' ?>"><?= $f['admin_reply'] ? 'Replied' : 'Waiting for reply' ?></span>
                </div>
                <!-- Same chat bubbles as the admin feedback page -->
                <div class="chat">
                    <div class="bubble from-student">
                        <span class="avatar small"><?= e($me) ?></span>
                        <div>
                            <p><?= e($f['message']) ?></p>
                            <small class="muted">You · <?= e(nice_date($f['submitted_at'], true)) ?></small>
                        </div>
                    </div>
                    <?php if ($f['admin_reply']): ?>
                        <div class="bubble from-admin">
                            <div>
                                <p><?= e($f['admin_reply']) ?></p>
                                <small class="muted">Admin · <?= e(nice_date($f['replied_at'], true)) ?></small>
                            </div>
                            <span class="avatar small"><?= icon('shield') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?php require '../includes/bottom.php'; ?>
