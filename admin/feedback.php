<?php
/*
 * Feedback - inbox  (Figure 27)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

$filter = $_GET['filter'] ?? 'all';
$where  = ['all' => '1 = 1', 'new' => 'f.admin_reply IS NULL', 'replied' => 'f.admin_reply IS NOT NULL'];
if (!isset($where[$filter])) $filter = 'all';   // only the 3 known filters reach the SQL

$feedback = db_all($conn, "SELECT f.feedbackID, f.message, f.admin_reply, f.submitted_at, u.name, d.device_name
                           FROM feedback f
                           JOIN users u ON u.tp_number = f.tp_number
                           JOIN devices d ON d.deviceID = f.deviceID
                           WHERE " . $where[$filter] . " ORDER BY (f.admin_reply IS NULL) DESC, f.submitted_at DESC");

$page_title = 'Feedback';
$active = 'feedback';
require '../includes/admin_top.php';
page_head('message', 'Feedback inbox', 'What students say about their repair. Open a message to reply.');
?>
<nav class="pill-tabs" aria-label="Filter feedback">
    <?php foreach (['all' => ['All', 'inbox'], 'new' => ['Needs reply', 'clock'], 'replied' => ['Replied', 'check']] as $k => [$label, $ico]): ?>
        <a href="feedback.php?filter=<?= $k ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= icon($ico) ?> <?= $label ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$feedback) empty_state('inbox', 'No feedback here.'); ?>
<ul class="inbox">
    <?php foreach ($feedback as $f): ?>
        <li>
            <a href="feedback_view.php?id=<?= e($f['feedbackID']) ?>" class="<?= $f['admin_reply'] === null ? 'is-new' : '' ?>">
                <span class="avatar small"><?= e(mb_strtoupper(mb_substr($f['name'], 0, 1))) ?></span>
                <span class="inbox-text">
                    <strong><?= e($f['name']) ?> <small class="muted">· <?= e($f['device_name']) ?></small></strong>
                    <span class="clip"><?= e($f['message']) ?></span>
                </span>
                <span class="inbox-meta">
                    <span class="badge <?= $f['admin_reply'] === null ? 'badge-warn' : 'badge-ok' ?>"><?= $f['admin_reply'] === null ? 'New' : 'Replied' ?></span>
                    <small class="muted"><?= nice_date($f['submitted_at']) ?></small>
                </span>
                <?= icon('arrow-right', 'inbox-arrow') ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php require '../includes/bottom.php'; ?>
