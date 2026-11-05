
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

$officerTP = $_SESSION['tp_number'];
$error = '';

if (empty($_SESSION['officer_notifications_csrf'])) {
    $_SESSION['officer_notifications_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['officer_notifications_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } else {
        try {
            if ($action === 'read_all') {
                db_exec($conn,
                    "UPDATE notifications SET is_read = 1
                     WHERE tp_number = ? AND is_read = 0",
                    's', [$officerTP]
                );
            } elseif ($action === 'read_one') {
                $id = $_POST['notificationID'] ?? '';

                if (!is_string($id) ||
                    !preg_match('/^N[0-9]{3,9}$/', $id)) {
                    throw new InvalidArgumentException('Invalid notification.');
                }

                db_exec($conn,
                    "UPDATE notifications SET is_read = 1
                     WHERE notificationID = ?
                     AND tp_number = ? AND is_read = 0",
                    'ss', [$id, $officerTP]
                );
            } else {
                throw new InvalidArgumentException('Invalid action.');
            }

            set_flash('success', 'Notifications updated.');
            redirect('notifications.php');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = 'Unable to update notifications.';
        }
    }
}

$notifications = db_all($conn,
    "SELECT notificationID, title, message, is_read, created_at
     FROM notifications
     WHERE tp_number = ?
     ORDER BY is_read ASC, created_at DESC, notificationID DESC",
    's', [$officerTP]
);

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!(int)$n['is_read']) $unreadCount++;
}

$page_title = 'Notifications';
$active = 'home';
require '../includes/officer_top.php';
?>

<?php officer_page_head(
    'bell',
    'My Notifications',
    'Stay updated on recycling activities and device follow-ups.'
); ?>

<section class="stat-grid three">
    <?php
    $cards = [
        ['bell', 'tint-gold', $unreadCount, 'Unread'],
        ['list', 'tint-mint', count($notifications), 'Total Notifications'],
        ['check', 'tint-mint',
         count($notifications) - $unreadCount, 'Read']
    ];
    foreach ($cards as [$ico, $tint, $value, $label]):
    ?>
        <div class="stat-card">
            <span class="stat-icon <?= $tint ?>"><?= icon($ico) ?></span>
            <strong class="stat-value"><?= $value ?></strong>
            <span class="stat-title"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= icon('bell') ?> Notifications</h2>

        <?php if ($unreadCount): ?>
            <form method="post">
                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['officer_notifications_csrf']) ?>">
                <input type="hidden" name="action" value="read_all">
                <button class="btn btn-outline" type="submit">
                    <?= icon('check') ?> Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$notifications): ?>
        <p class="muted">No notifications yet.</p>
    <?php endif; ?>

    <div class="activity">
        <?php foreach ($notifications as $n): ?>
            <div class="activity-item">
                <span class="stat-icon <?= (int)$n['is_read']
                    ? 'tint-mint' : 'tint-gold' ?>">
                    <?= icon((int)$n['is_read'] ? 'check' : 'bell') ?>
                </span>

                <div style="flex:1;min-width:0">
                    <strong><?= e($n['title']) ?></strong>
                    <?php if (!(int)$n['is_read']): ?>
                        <span class="badge">New</span>
                    <?php endif; ?>

                    <p><?= nl2br(e($n['message'])) ?></p>
                    <small class="muted">
                        <?= e(nice_date($n['created_at'], true)) ?>
                    </small>
                </div>

                <?php if (!(int)$n['is_read']): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token"
                               value="<?= e($_SESSION['officer_notifications_csrf']) ?>">
                        <input type="hidden" name="action" value="read_one">
                        <input type="hidden" name="notificationID"
                               value="<?= e($n['notificationID']) ?>">
                        <button type="submit" class="btn btn-outline">
                            Mark Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require '../includes/officer_bottom.php'; ?>
