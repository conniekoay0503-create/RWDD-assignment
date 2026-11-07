<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

// (overdue reminders are sent by require_role() -> check_collection_deadlines())

$tp = $_SESSION['tp_number'];
$error = '';

// Every page that changes a device's status sends its own notification at that moment
// (the rule: whoever changes the status calls notify()), so nothing needs to be "filled in" here.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = trim((string)($_POST['notificationID'] ?? ''));

    // (the CSRF token was already checked by require_role())
    if ($action === 'read_all') {
        db_exec($conn, "UPDATE notifications SET is_read = 1
            WHERE tp_number = ?", 's', [$tp]);

        set_flash('success', 'All notifications marked as read.');
        // Go back to the page the bell was on. Only our own page names are allowed (no open redirect).
        $back = $_POST['back'] ?? 'notifications.php';
        if (!is_string($back) || !preg_match('/^[a-z-]+\.php$/', $back)) $back = 'notifications.php';
        redirect($back);
    } elseif ($action === 'read_one' && preg_match('/^N[0-9]{3,9}$/', $id)) {
        db_exec($conn, "UPDATE notifications SET is_read = 1
            WHERE notificationID = ? AND tp_number = ?", 'ss', [$id, $tp]);
        redirect('notifications.php');
    } else {
        $error = 'Invalid notification action.';
    }
}

// get notifications and announcements
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$unread = (int)db_value($conn, "SELECT COUNT(*) FROM notifications
    WHERE tp_number = ? AND is_read = 0", 's', [$tp]);

$sql = "SELECT * FROM notifications WHERE tp_number = ?";
if ($filter === 'unread') $sql .= " AND is_read = 0";
$sql .= " ORDER BY created_at DESC, notificationID DESC";

$notifications = db_all($conn, $sql, 's', [$tp]);
$announcements = db_all($conn, "SELECT a.*, u.name AS admin_name
    FROM announcements a JOIN users u ON a.created_by = u.tp_number
    ORDER BY a.created_at DESC");

$page_title = 'Notifications';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">🔔</span>
    <div>
        <h1>Notifications</h1>
        <p>Stay updated on your devices and campus activities.</p>
    </div>
</div>

<section class="card student-panel">
    <div class="card-head">
        <h2>My Notifications</h2>
        <nav class="pill-tabs">
            <a href="notifications.php" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="notifications.php?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">
                Unread (<?= $unread ?>)
            </a>
        </nav>
    </div>

    <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

    <?php if ($unread > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="read_all">
            <button class="btn btn-outline btn-sm"><?= icon('check') ?> Mark all as read</button>
        </form>
    <?php endif; ?>

    <?php if (!$notifications): ?><p>No notifications to display.</p><?php endif; ?>

    <?php if ($notifications): ?>
        <ul class="notif-list full">
        <?php foreach ($notifications as $n): ?>
            <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <span><?= e($n['message']) ?></span>
                    <small class="muted"><?= e(nice_date($n['created_at'], true)) ?></small>
                </div>
                <?php if (!$n['is_read']): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="read_one">
                        <input type="hidden" name="notificationID" value="<?= e($n['notificationID']) ?>">
                        <button class="btn btn-outline btn-sm">Mark as Read</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card student-panel">
    <h2>Campus Announcements</h2>
    <?php if (!$announcements): ?><p>No announcements available.</p><?php endif; ?>

    <?php if ($announcements): ?>
        <ul class="notif-list full">
        <?php foreach ($announcements as $a): ?>
            <li>
                <div>
                    <strong><?= e($a['title']) ?></strong>
                    <span><?= e($a['message']) ?></span>
                    <small class="muted">
                        <?= e($a['admin_name']) ?> · <?= e(nice_date($a['created_at'], true)) ?>
                    </small>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require '../includes/student_bottom.php'; ?>
