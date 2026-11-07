<?php
/*
 * Notifications - opened from the bell icon in the top bar.
 * POST action=read_all marks everything as read (the bell panel and this page both use it).
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'read_all') {
    db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE tp_number = ? AND is_read = 0", 's', [$me]);
    set_flash('success', 'All notifications marked as read.');
    // "back" says which page the bell was on. Only our own page names are allowed (no open redirect).
    $back = $_POST['back'] ?? '';
    $own_pages = ['index.php', 'my_jobs.php', 'repair_queue.php', 'report.php', 'points.php', 'challenge.php', 'history.php', 'profile.php', 'notifications.php'];
    redirect(in_array($back, $own_pages, true) ? $back : 'notifications.php');
}

$items = db_all($conn, "SELECT title, message, is_read, created_at FROM notifications
                        WHERE tp_number = ? ORDER BY created_at DESC, notificationID DESC LIMIT 50", 's', [$me]);

$page_title = 'Notifications';
$active = '';
require '../includes/volunteer_top.php';

page_head('bell', 'Notifications', 'The latest 50 messages for you.',
    $unread ? '<form method="post" action="notifications.php">' . csrf_field()
              . '<input type="hidden" name="action" value="read_all"><button class="btn btn-outline">' . icon('check') . ' Mark all as read</button></form>' : '');
?>

<section class="card">
    <?php if (!$items): ?>
        <?php empty_state('bell', 'No notifications yet.') ?>
    <?php else: ?>
        <ul class="notif-list full">
            <?php foreach ($items as $n): ?>
                <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                    <div><strong><?= e($n['title']) ?></strong><span><?= e($n['message']) ?></span></div>
                    <small><?= nice_date($n['created_at'], true) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require '../includes/volunteer_bottom.php'; ?>
