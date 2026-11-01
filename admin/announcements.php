<?php
/*
 * Announcements - list  (Figure 27, page 1 of 2)
 * Everyone sees announcements on their dashboard and on the homepage.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    db_exec($conn, "DELETE FROM announcements WHERE announcementID = ?", 's', [trim($_POST['announcementID'] ?? '')]);
    set_flash('success', 'Announcement deleted.');
    redirect('announcements.php');
}

// Search + "posted by me" are done in SQL (LIKE with ? keeps it safe)
$q      = trim($_GET['q'] ?? '');
$mine   = isset($_GET['mine']);
$sql    = "SELECT a.*, u.name FROM announcements a JOIN users u ON u.tp_number = a.created_by
           WHERE (a.title LIKE ? OR a.message LIKE ?)";
$types  = 'ss';
$params = ['%' . $q . '%', '%' . $q . '%'];
if ($mine) {
    $sql .= " AND a.created_by = ?";
    $types .= 's';
    $params[] = $me;
}
$announcements = db_all($conn, $sql . " ORDER BY a.created_at DESC", $types, $params);

$page_title = 'Announcements';
$active = 'announcements';
require '../includes/admin_top.php';
page_head('megaphone', 'Announcements', 'News for everyone. They appear on every dashboard and on the homepage.',
    '<a class="btn btn-primary" href="announcement_form.php">' . icon('plus') . ' Post announcement</a>');
?>
<form method="get" class="toolbar">
    <label class="search-box"><?= icon('search') ?><input type="search" name="q" placeholder="Search announcements" value="<?= e($q) ?>" aria-label="Search announcements"></label>
    <label class="check"><input type="checkbox" name="mine" value="1" <?= $mine ? 'checked' : '' ?>> Posted by me</label>
    <button class="btn btn-outline btn-sm"><?= icon('filter') ?> Apply</button>
</form>

<?php if (!$announcements) empty_state('megaphone', 'No announcements found.'); ?>
<section class="post-grid">
    <?php foreach ($announcements as $a): ?>
        <article class="post-card">
            <time datetime="<?= e(date('Y-m-d', strtotime($a['created_at']))) ?>"><?= icon('calendar') ?> <?= nice_date($a['created_at']) ?></time>
            <h2><?= e($a['title']) ?></h2>
            <p><?= nl2br(e($a['message'])) ?></p>
            <footer>
                <small class="muted"><?= icon('user') ?> <?= e($a['name']) ?></small>
                <span class="icon-actions">
                    <a class="btn-icon" href="announcement_form.php?edit=<?= e($a['announcementID']) ?>" title="Edit" aria-label="Edit <?= e($a['title']) ?>"><?= icon('edit') ?></a>
                    <form method="post" data-confirm="Delete this announcement?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="announcementID" value="<?= e($a['announcementID']) ?>">
                        <button class="btn-icon danger" title="Delete" aria-label="Delete <?= e($a['title']) ?>"><?= icon('trash') ?></button>
                    </form>
                </span>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
