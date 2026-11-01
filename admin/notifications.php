<?php
/*
 * My notifications (opened from the bell in the top bar)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'read_all') {
        db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE tp_number = ? AND is_read = 0", 's', [$me]);
        set_flash('success', 'All notifications marked as read.');
    }
    if ($action === 'read_one') {
        // "AND tp_number = ?" so an admin can only change their own notifications
        db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE notificationID = ? AND tp_number = ?",
            'ss', [trim($_POST['notificationID'] ?? ''), $me]);
    }
    // Go back to the page the admin was on. Only a plain file name like "users.php" is allowed,
    // so nobody can make this page redirect to another website.
    $back = $_POST['back'] ?? 'notifications.php';
    if (!preg_match('/^[a-z]+\.php$/', $back)) $back = 'notifications.php';
    redirect($back);
}

$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$sql = "SELECT n.*, u.name AS sender FROM notifications n LEFT JOIN users u ON u.tp_number = n.created_by
        WHERE n.tp_number = ?" . ($filter === 'unread' ? " AND n.is_read = 0" : "") . "
        ORDER BY n.created_at DESC, n.notificationID DESC";
$list = db_all($conn, $sql, 's', [$me]);

$page_title = 'Notifications';
$active = '';
require '../includes/admin_top.php';
page_head('bell', 'Notifications', 'Messages sent to you by the system and other admins.');
?>

<section class="card narrow">
    <div class="card-head">
        <nav class="pill-tabs" aria-label="Filter notifications">
            <a href="notifications.php" class="<?= $filter === 'all' ? 'active' : '' ?>"><?= icon('inbox') ?> All</a>
            <a href="notifications.php?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>"><?= icon('bell') ?> Unread · <?= (int) $unread ?></a>
        </nav>
        <?php if ($unread): ?>
            <form method="post">
                <input type="hidden" name="action" value="read_all">
                <button class="btn btn-outline btn-sm"><?= icon('check') ?> Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
    <ul class="notif-list full">
        <?php foreach ($list as $n): ?>
            <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <span><?= e($n['message']) ?></span>
                    <small class="muted"><?= e($n['sender'] ?? 'System') ?> · <?= nice_date($n['created_at'], true) ?></small>
                </div>
                <?php if (!$n['is_read']): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="read_one">
                        <input type="hidden" name="notificationID" value="<?= e($n['notificationID']) ?>">
                        <input type="hidden" name="back" value="notifications.php">
                        <button class="btn-icon" title="Mark as read" aria-label="Mark as read"><?= icon('check') ?></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$list) empty_state('bell', 'Nothing here.'); ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
