
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
$error = '';

if (empty($_SESSION['feedback_csrf'])) {
    $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
}

// Only devices belonging to this student can be selected.
$devices = db_all(
    $conn,
    "SELECT deviceID, device_name, status
     FROM devices
     WHERE submitted_by = ?
     ORDER BY submitted_at DESC, deviceID DESC",
    's',
    [$studentTP]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $deviceID = filter_var(
        $_POST['deviceID'] ?? null,
        FILTER_VALIDATE_INT
    );
    $message = trim($_POST['message'] ?? '');

    if (!is_string($token) ||
        !hash_equals($_SESSION['feedback_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif (!$deviceID) {
        $error = 'Please select a device.';
    } elseif ($message === '' || mb_strlen($message) > 255) {
        $error = 'Feedback must be between 1 and 255 characters.';
    } else {
        $ownedDevice = db_one(
            $conn,
            "SELECT deviceID, device_name
             FROM devices
             WHERE deviceID = ? AND submitted_by = ?",
            'is',
            [$deviceID, $studentTP]
        );

        if (!$ownedDevice) {
            $error = 'Invalid device selection.';
        } else {
            try {
                $conn->begin_transaction();

                db_exec(
                    $conn,
                    "INSERT INTO feedback
                     (tp_number, deviceID, message)
                     VALUES (?, ?, ?)",
                    'sis',
                    [$studentTP, $deviceID, $message]
                );

                $user = db_one(
                    $conn,
                    "SELECT name FROM users WHERE tp_number = ?",
                    's',
                    [$studentTP]
                );

                notify_admins(
                    $conn,
                    'New feedback',
                    ($user['name'] ?? $studentTP) .
                    ' sent feedback about ' .
                    $ownedDevice['device_name'] . '.'
                );

                $conn->commit();

                $_SESSION['feedback_csrf'] =
                    bin2hex(random_bytes(32));

                set_flash(
                    'success',
                    'Your feedback has been submitted successfully.'
                );

                redirect('feedback.php');

            } catch (Throwable $exception) {
                $conn->rollback();
                error_log($exception->getMessage());
                $error = 'Unable to submit feedback. Please try again.';
            }
        }
    }
}

// Retrieve feedback and Admin replies.
$feedbackList = db_all(
    $conn,
    "SELECT
        f.feedbackID,
        f.message,
        f.admin_reply,
        f.submitted_at,
        f.replied_at,
        d.device_name
     FROM feedback f
     JOIN devices d ON f.deviceID = d.deviceID
     WHERE f.tp_number = ?
       AND d.submitted_by = ?
     ORDER BY f.submitted_at DESC, f.feedbackID DESC",
    'ss',
    [$studentTP, $studentTP]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="public">

<main style="max-width: 900px; margin: 40px auto; padding: 20px;">

    <a href="index.php">← Back to Dashboard</a>

    <h1>Student Feedback</h1>
    <p>
        Share your experience or ask a question
        about one of your submitted devices.
    </p>

    <?php show_flash(); ?>

    <?php if ($error !== ''): ?>
        <p role="alert" style="color: #b42318;">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <h2>Submit Feedback</h2>

    <?php if (empty($devices)): ?>
        <p>
            You need to submit a device before
            you can send device-related feedback.
        </p>
    <?php else: ?>

        <form method="POST" action="feedback.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['feedback_csrf']) ?>"
            >

            <p>
                <label for="deviceID">Select Device</label>
                <select name="deviceID" id="deviceID" required>
                    <option value="">Choose a device</option>

                    <?php foreach ($devices as $device): ?>
                        <option
                            value="<?= (int) $device['deviceID'] ?>"
                            <?= (string) ($_POST['deviceID'] ?? '') ===
                                (string) $device['deviceID']
                                ? 'selected' : '' ?>
                        >
                            <?= e($device['device_name']) ?>
                            (<?= e($device['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="message">Your Feedback</label>
                <textarea
                    id="message"
                    name="message"
                    rows="5"
                    maxlength="255"
                    required
                    placeholder="Tell us about your experience or question"
                ><?= e($_POST['message'] ?? '') ?></textarea>
            </p>

            <button type="submit" class="btn btn-primary">
                Submit Feedback
            </button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>My Feedback History</h2>

    <?php if (empty($feedbackList)): ?>

        <p>You haven't submitted any feedback yet.</p>

    <?php else: ?>

        <?php foreach ($feedbackList as $feedback): ?>

            <section style="
                border: 1px solid #d7e4dc;
                border-radius: 12px;
                padding: 18px;
                margin: 15px 0;
            ">

                <h3><?= e($feedback['device_name']) ?></h3>

                <p>
                    <strong>Submitted:</strong>
                    <?= e(nice_date(
                        $feedback['submitted_at'],
                        true
                    )) ?>
                </p>

                <p>
                    <strong>My Feedback:</strong>
                    <?= nl2br(e($feedback['message'])) ?>
                </p>

                <?php if ($feedback['admin_reply'] !== null): ?>

                    <div style="
                        background: #e5f6ed;
                        border-radius: 10px;
                        padding: 14px;
                    ">

                        <strong>Admin Reply</strong>

                        <p>
                            <?= nl2br(e($feedback['admin_reply'])) ?>
                        </p>

                        <?php if ($feedback['replied_at']): ?>
                            <small>
                                Replied:
                                <?= e(nice_date(
                                    $feedback['replied_at'],
                                    true
                                )) ?>
                            </small>
                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <p>Awaiting Admin reply.</p>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

</body>
</html>
