
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

require '../includes/collection_deadlines.php';
check_collection_deadlines($conn);

$tp = $_SESSION['tp_number'];
$error = '';

// check device history for missing notifications
$messages = [
    'Submitted' => 'Your device was submitted successfully.',
    'Claimed' => 'A volunteer has claimed your device.',
    'In Repair' => 'Your device is now being repaired.',
    'Ready for Collection' => 'Your repaired device is ready at Green Hub.',
    'Pending Consent' => 'Your device cannot be repaired. Please choose return or recycling.',
    'Return Requested' => 'Your return request has been received.',
    'Awaiting Recycle' => 'Your device is waiting for recycling.',
    'Returned Unrepaired' => 'Your device is ready for collection at Green Hub.',
    'Returned Unclaimed' => 'Your device was returned to Green Hub.',
    'Completed' => 'You have collected your repaired device.',
    'Collected Unrepaired' => 'Your collection has been confirmed.',
    'Recycled' => 'Your device has been recycled.',
    'Disposed' => 'Your device has been disposed of.',
    'Cancelled' => 'Your device submission was cancelled.'
];

$updates = db_all($conn, "SELECT h.deviceID, h.new_status, d.device_name
    FROM device_status_history h JOIN devices d ON h.deviceID = d.deviceID
    WHERE d.submitted_by = ? ORDER BY h.changed_at, h.historyID", 's', [$tp]);

foreach ($updates as $u) {
    if (!isset($messages[$u['new_status']])) continue;

    $title = 'Device Update: ' . $u['deviceID'];
    $message = $u['device_name'] . ': ' . $messages[$u['new_status']];

    $exists = db_value($conn, "SELECT COUNT(*) FROM notifications
        WHERE tp_number = ? AND title = ? AND message = ?",
        'sss', [$tp, $title, $message]);

    if (!$exists) notify($conn, $tp, $title, $message);
}

// create token for notification buttons
if (empty($_SESSION['notifications_csrf']))
    $_SESSION['notifications_csrf'] = bin2hex(random_bytes(32));

$csrf = $_SESSION['notifications_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $id = trim((string)($_POST['notificationID'] ?? ''));

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        $error = 'Invalid request. Please refresh.';
    } elseif ($action === 'read_all') {
        db_exec($conn, "UPDATE notifications SET is_read = 1
            WHERE tp_number = ?", 's', [$tp]);

        set_flash('success', 'All notifications marked as read.');
        redirect('notifications.php');
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
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="read_all">
            <button class="btn btn-outline btn-sm">Mark All as Read</button>
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
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
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
