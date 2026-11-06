<?php
/*
 * Challenges - create / edit form  (Figure 26, page 2 of 2)
 * challenge_form.php          -> create (also posts an announcement)
 * challenge_form.php?edit=3   -> edit challenge 3 (only before it starts)
 * Only one challenge can run at a time, so the dates must not overlap.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

// A challenge can only be edited before it starts (students may already be working on it)
function not_started($conn, $id)
{
    return db_value($conn, "SELECT COUNT(*) FROM challenges WHERE challengeID = ? AND start_date > CURDATE()", 's', [$id]) == 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = trim($_POST['challengeID'] ?? '');   // '' = new challenge
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $target = filter_var($_POST['target_kg'] ?? '', FILTER_VALIDATE_FLOAT);
    $bonus  = filter_var($_POST['bonus_points'] ?? '', FILTER_VALIDATE_INT);
    $start  = $_POST['start_date'] ?? '';
    $end    = $_POST['end_date'] ?? '';

    // checkdate() makes sure a date such as 2026-02-31 is rejected
    $valid_date = function ($d) {
        $p = explode('-', $d);
        return count($p) === 3 && checkdate((int) $p[1], (int) $p[2], (int) $p[0]);
    };

    if ($id && !not_started($conn, $id)) {
        set_flash('error', 'This challenge has already started, so it can no longer be edited.');
        redirect('challenges.php');
    }

    $errors = [];
    if ($title === '' || strlen($title) > 100)                 $errors[] = 'Title is required (max 100 characters).';
    if ($desc === '' || strlen($desc) > 255)                   $errors[] = 'Description is required (max 255 characters).';
    if ($target === false || $target <= 0 || $target > 999999) $errors[] = 'Target must be above 0 kg.';
    if ($bonus === false || $bonus <= 0)                       $errors[] = 'Bonus points must be a whole number above 0.';
    if (!$valid_date($start) || !$valid_date($end)) {
        $errors[] = 'Please choose a valid start and end date.';
    } else {
        if ($start < date('Y-m-d')) $errors[] = 'The start date cannot be in the past.';   // 'Y-m-d' strings compare correctly
        if ($end < $start)          $errors[] = 'The end date must be on or after the start date.';
        // Two date ranges overlap when: existing.start <= new.end AND existing.end >= new.start
        $clash = db_one($conn, "SELECT title FROM challenges WHERE start_date <= ? AND end_date >= ? AND challengeID <> ?",
            'sss', [$end, $start, $id]);
        if ($clash) $errors[] = 'These dates overlap with "' . $clash['title'] . '". Only one challenge can run at a time.';
    }

    if ($errors) {
        foreach ($errors as $err) set_flash('error', $err);
        $_SESSION['old'] = $_POST;   // keep what the admin typed
        redirect('challenge_form.php' . ($id ? '?edit=' . $id : ''));
    }

    if ($id) {
        db_exec($conn, "UPDATE challenges SET title = ?, description = ?, target_kg = ?, start_date = ?, end_date = ?, bonus_points = ?
                        WHERE challengeID = ?", 'ssdssis', [$title, $desc, $target, $start, $end, $bonus, $id]);
        set_flash('success', 'Challenge updated.');
    } else {
        $conn->begin_transaction();
        try {
            $new_id = next_id($conn, 'challenges', 'challengeID', 'CH');
            db_exec($conn, "INSERT INTO challenges (challengeID, created_by, title, description, target_kg, start_date, end_date, bonus_points)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 'ssssdssi', [$new_id, $me, $title, $desc, $target, $start, $end, $bonus]);
            // Tell everyone about it on the announcement board
            $msg = $desc . ' Runs ' . nice_date($start) . ' to ' . nice_date($end) . '. Reach ' . ($target + 0) . ' kg to earn ' . $bonus . ' bonus points.';
            $ann_id = next_id($conn, 'announcements', 'announcementID', 'A');
            db_exec($conn, "INSERT INTO announcements (announcementID, created_by, title, message) VALUES (?, ?, ?, ?)",
                'ssss', [$ann_id, $me, 'New challenge: ' . $title, substr($msg, 0, 500)]);
            $conn->commit();
            set_flash('success', 'Challenge created and announced to everyone.');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            set_flash('error', 'Could not create the challenge. Please try again.');
        }
    }
    redirect('challenges.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one($conn, "SELECT * FROM challenges WHERE challengeID = ? AND start_date > CURDATE()", 's', [$_GET['edit']]);
}
// After a failed save, show what the admin typed instead of the saved values
$form = $_SESSION['old'] ?? $edit ?? [];
unset($_SESSION['old']);

$page_title = $edit ? 'Edit challenge' : 'Create challenge';
$active = 'challenges';
require '../includes/admin_top.php';
page_head($edit ? 'edit' : 'plus', $edit ? 'Edit challenge' : 'Create a challenge',
    $edit ? 'You can change it until the start date.' : 'It is posted on the announcement board automatically.');
?>
<section class="card narrow">
    <form method="post" novalidate data-validate
          data-confirm="<?= $edit ? 'Save the changes to this challenge?' : 'Create this challenge and post it on the announcement board?' ?>">
        <input type="hidden" name="challengeID" value="<?= e($edit['challengeID'] ?? '') ?>">
        <div class="field">
            <label for="title"><?= icon('flag') ?> Title</label>
            <input type="text" id="title" name="title" maxlength="100" required value="<?= e($form['title'] ?? '') ?>" placeholder="e.g. Battery Round-Up">
        </div>
        <div class="field">
            <label for="ch_desc"><?= icon('type') ?> Description <small class="muted" data-count-for="ch_desc"></small></label>
            <textarea id="ch_desc" name="description" maxlength="255" rows="2" required><?= e($form['description'] ?? '') ?></textarea>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="start_date"><?= icon('calendar') ?> Start date</label>
                <input type="date" id="start_date" name="start_date" required min="<?= date('Y-m-d') ?>" value="<?= e($form['start_date'] ?? '') ?>" data-start-for="end_date">
            </div>
            <div class="field">
                <label for="end_date"><?= icon('calendar') ?> End date</label>
                <input type="date" id="end_date" name="end_date" required min="<?= date('Y-m-d') ?>" value="<?= e($form['end_date'] ?? '') ?>">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="target_kg"><?= icon('target') ?> Target per student (kg)</label>
                <input type="number" id="target_kg" name="target_kg" min="0.01" step="0.01" required value="<?= e($form['target_kg'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="bonus_points"><?= icon('star') ?> Bonus points</label>
                <input type="number" id="bonus_points" name="bonus_points" min="1" step="1" required value="<?= e($form['bonus_points'] ?? '') ?>">
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary"><?= icon($edit ? 'check' : 'megaphone') ?> <?= $edit ? 'Save changes' : 'Create & announce' ?></button>
            <a class="btn btn-outline" href="challenges.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>
<?php require '../includes/admin_bottom.php'; ?>
