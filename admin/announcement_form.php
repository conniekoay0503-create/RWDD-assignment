<?php
/*
 * Announcements - post / edit  (Figure 27, page 2 of 2)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = trim($_POST['announcementID'] ?? '');
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $errors = [];
    if ($title === '' || strlen($title) > 100)     $errors[] = 'Title is required (max 100 characters).';
    if ($message === '' || strlen($message) > 500) $errors[] = 'Message is required (max 500 characters).';
    if ($errors) {
        foreach ($errors as $err) set_flash('error', $err);
        $_SESSION['old'] = $_POST;
        redirect('announcement_form.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_exec($conn, "UPDATE announcements SET title = ?, message = ? WHERE announcementID = ?", 'sss', [$title, $message, $id]);
        set_flash('success', 'Announcement updated.');
    } else {
        $new_id = next_id($conn, 'announcements', 'announcementID', 'A');
        db_exec($conn, "INSERT INTO announcements (announcementID, created_by, title, message) VALUES (?, ?, ?, ?)", 'ssss', [$new_id, $me, $title, $message]);
        set_flash('success', 'Announcement posted. Everyone can see it now.');
    }
    redirect('announcements.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one($conn, "SELECT * FROM announcements WHERE announcementID = ?", 's', [$_GET['edit']]);
}
$form = $_SESSION['old'] ?? $edit ?? [];
unset($_SESSION['old']);

$page_title = $edit ? 'Edit announcement' : 'Post announcement';
$active = 'announcements';
require '../includes/admin_top.php';
page_head($edit ? 'edit' : 'megaphone', $edit ? 'Edit announcement' : 'Post an announcement', 'Keep it short: what is happening, when, and what students should do.');
?>
<section class="card narrow">
    <form method="post" novalidate data-validate
          data-confirm="<?= $edit ? 'Save the changes to this announcement?' : 'Post this announcement? Everyone will see it.' ?>">
        <input type="hidden" name="announcementID" value="<?= e($edit['announcementID'] ?? '') ?>">
        <div class="field">
            <label for="an_title"><?= icon('type') ?> Title</label>
            <input type="text" id="an_title" name="title" maxlength="100" required value="<?= e($form['title'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="an_message"><?= icon('message') ?> Message <small class="muted" data-count-for="an_message"></small></label>
            <textarea id="an_message" name="message" maxlength="500" rows="6" required><?= e($form['message'] ?? '') ?></textarea>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary"><?= icon('send') ?> <?= $edit ? 'Save changes' : 'Post' ?></button>
            <a class="btn btn-outline" href="announcements.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>
<?php require '../includes/admin_bottom.php'; ?>
