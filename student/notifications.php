
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
$error = '';

// Generate CSRF token.
if (empty($_SESSION['notifications_csrf'])) {
    $_SESSION['notifications_csrf'] = bin2hex(random_bytes(32));
}

// Handle marking a notification as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $notificationID = filter_var(
        $_POST['notificationID'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['notifications_csrf'], $token)
    ) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif (!$notificationID || $notificationID < 1) {
        $error = 'Invalid notification.';
    } else {
        try {
            // The TP number check ensures students cannot
            // update notifications belonging to other users.
            db_exec(
                $conn,
                "UPDATE notifications
                 SET is_read = 1
                 WHERE notificationID = ?
                   AND tp_number = ?
                   AND is_read = 0",
                'is',
                [$notificationID, $studentTP]
            );

            set_flash('success', 'Notification marked as read.');
            redirect('notifications.php');

        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Unable to update the notification.';
        }
    }
}

// Retrieve the student's personal notifications.
$notifications = db_all(
    $conn,
    "SELECT notificationID, title, message,
            is_read, created_at
     FROM notifications
     WHERE tp_number = ?
     ORDER BY is_read ASC,
              created_at DESC,
              notificationID DESC",
    's',
    [$studentTP]
);

// Count unread notifications.
$unreadCount = (int) db_value(
    $conn,
    "SELECT COUNT(*)
     FROM notifications
     WHERE tp_number = ?
       AND is_read = 0",
    's',
    [$studentTP]
);

// Announcements are visible to all students.
$announcements = db_all(
    $conn,
    "SELECT a.announcementID,
            a.title,
            a.message,
            a.created_at,
            u.name AS admin_name
     FROM announcements a
     JOIN users u ON a.created_by = u.tp_number
     ORDER BY a.created_at DESC,
              a.announcementID DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifications &amp; Announcements | E-Waste &amp; Repair Café</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="public">

<main style="max-width: 900px; margin: 40px auto; padding: 20px;">

    <a href="index.php">← Back to Dashboard</a>

    <h1>Notifications &amp; Announcements</h1>

    <p>
        Stay updated about your devices, rewards,
        volunteer applications and campus activities.
    </p>

    <?php show_flash(); ?>

    <?php if ($error !== ''): ?>
        <p role="alert" style="color: #b42318;">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <section>
        <h2>My Notifications</h2>

        <p>
            <strong><?= $unreadCount ?></strong>
            unread notification<?= $unreadCount === 1 ? '' : 's' ?>
        </p>

        <?php if (empty($notifications)): ?>

            <p>You don't have any notifications yet.</p>

        <?php else: ?>

            <?php foreach ($notifications as $notification): ?>

                <div style="
                    border: 1px solid #d7e4dc;
                    border-radius: 12px;
                    padding: 18px;
                    margin: 15px 0;
                    background: <?= (int) $notification['is_read'] === 0
                        ? '#e5f6ed'
                        : 'transparent' ?>;
                ">

                    <h3>
                        <?= e($notification['title']) ?>

                        <?php if ((int) $notification['is_read'] === 0): ?>
                            <small>(New)</small>
                        <?php endif; ?>
                    </h3>

                    <p><?= nl2br(e($notification['message'])) ?></p>

                    <p>
                        <small>
                            <?= e(nice_date(
                                $notification['created_at'],
                                true
                            )) ?>
                        </small>
                    </p>

                    <?php if ((int) $notification['is_read'] === 0): ?>

                        <form method="POST" action="notifications.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['notifications_csrf']) ?>"
                            >

                            <input
                                type="hidden"
                                name="notificationID"
                                value="<?= (int) $notification['notificationID'] ?>"
                            >

                            <button type="submit" class="btn btn-primary">
                                Mark as Read
                            </button>

                        </form>

                    <?php else: ?>

                        <small>Read</small>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <hr>

    <section>
        <h2>Campus Announcements</h2>

        <?php if (empty($announcements)): ?>

            <p>No announcements available.</p>

        <?php else: ?>

            <?php foreach ($announcements as $announcement): ?>

                <article style="
                    border: 1px solid #d7e4dc;
                    border-radius: 12px;
                    padding: 18px;
                    margin: 15px 0;
                ">

                    <h3><?= e($announcement['title']) ?></h3>

                    <p>
                        <?= nl2br(e($announcement['message'])) ?>
                    </p>

                    <p>
                        <small>
                            Posted by <?= e($announcement['admin_name']) ?>
                            on
                            <?= e(nice_date(
                                $announcement['created_at'],
                                true
                            )) ?>
                        </small>
                    </p>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
