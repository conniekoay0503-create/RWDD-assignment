<?php
/*
 * Feedback - read one message, see the device story, and reply  (Figure 27)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];
$id = trim($_GET['id'] ?? $_POST['feedbackID'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = trim($_POST['admin_reply'] ?? '');
    if ($reply === '' || strlen($reply) > 255) {
        set_flash('error', 'Reply is required (max 255 characters).');
        redirect('feedback_view.php?id=' . $id);
    }
    // "admin_reply IS NULL" = only the first admin to reply succeeds
    $n = db_exec($conn, "UPDATE feedback SET admin_reply = ?, replied_by = ?, replied_at = NOW()
                         WHERE feedbackID = ? AND admin_reply IS NULL", 'sss', [$reply, $me, $id]);
    if ($n === 1) {
        $tp = db_value($conn, "SELECT tp_number FROM feedback WHERE feedbackID = ?", 's', [$id]);
        notify($conn, $tp, 'Admin replied', 'An admin replied to your feedback: "' . substr($reply, 0, 180) . '"', $me);
        set_flash('success', 'Reply sent.');
    } else {
        set_flash('error', 'This feedback has already been replied to.');
    }
    redirect('feedback_view.php?id=' . $id);
}

$view = db_one($conn, "SELECT f.*, u.name, u.email, d.device_name, d.status, c.category_name, r.name AS replier
                       FROM feedback f
                       JOIN users u ON u.tp_number = f.tp_number
                       JOIN devices d ON d.deviceID = f.deviceID
                       JOIN categories c ON c.categoryID = d.categoryID
                       LEFT JOIN users r ON r.tp_number = f.replied_by
                       WHERE f.feedbackID = ?", 's', [$id]);
if (!$view) {
    set_flash('error', 'That feedback was not found.');
    redirect('feedback.php');
}
$timeline = db_all($conn, "SELECT h.new_status, h.remarks, h.changed_at, u.name
                           FROM device_status_history h LEFT JOIN users u ON u.tp_number = h.changed_by
                           WHERE h.deviceID = ? ORDER BY h.changed_at, h.historyID", 's', [$view['deviceID']]);

$page_title = 'Feedback';
$active = 'feedback';
require '../includes/admin_top.php';
page_head('message', 'Feedback from ' . $view['name'], 'About ' . $view['device_name'] . ' (' . $view['category_name'] . ')',
    '<a class="btn btn-outline" href="feedback.php">' . icon('arrow-left', 'icon-nudge') . ' Inbox</a>');
?>
<div class="two-col wide-left">
    <section class="card">
        <div class="chat">
            <div class="bubble from-student">
                <span class="avatar small"><?= e(strtoupper(substr($view['name'], 0, 1))) ?></span>
                <div>
                    <p><?= e($view['message']) ?></p>
                    <small class="muted"><?= e($view['name']) ?> · <?= e($view['email']) ?> · <?= nice_date($view['submitted_at'], true) ?></small>
                </div>
            </div>
            <?php if ($view['admin_reply'] !== null): ?>
                <div class="bubble from-admin">
                    <div>
                        <p><?= e($view['admin_reply']) ?></p>
                        <small class="muted"><?= e($view['replier'] ?? 'Admin') ?> · <?= nice_date($view['replied_at'], true) ?></small>
                    </div>
                    <span class="avatar small"><?= icon('shield') ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($view['admin_reply'] === null): ?>
            <form method="post" novalidate data-validate class="reply-form" data-confirm="Send this reply? It cannot be edited after it is sent.">
                <input type="hidden" name="feedbackID" value="<?= e($view['feedbackID']) ?>">
                <div class="field">
                    <label for="admin_reply"><?= icon('send') ?> Your reply <small class="muted" data-count-for="admin_reply"></small></label>
                    <textarea id="admin_reply" name="admin_reply" maxlength="255" rows="3" required></textarea>
                </div>
                <button class="btn btn-primary"><?= icon('send') ?> Send reply</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="card-title"><?= icon('history') ?> Device timeline</h2>
        <p><span class="badge"><?= icon('device') ?> Now: <?= e($view['status']) ?></span></p>
        <ol class="timeline">
            <?php foreach ($timeline as $t): ?>
                <li><strong><?= e($t['new_status']) ?></strong>
                    <?php if ($t['remarks']): ?><span> – <?= e($t['remarks']) ?></span><?php endif; ?>
                    <small class="muted"><?= e($t['name'] ?? 'System') ?> · <?= nice_date($t['changed_at'], true) ?></small></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php require '../includes/admin_bottom.php'; ?>
