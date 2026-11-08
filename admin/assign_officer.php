<?php
/*
 * Users - assign a Recycling Officer
 * Only an existing student can become an officer: delete the student row, insert the officer row.
 * Two steps:
 *   1. "Find student" (GET ?tp=TP095001) checks the TP number first and shows who it is.
 *   2. "Assign as officer" (POST) asks to confirm, then changes the role.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tp = strtoupper(trim($_POST['tp_number'] ?? ''));
    if (!preg_match('/^TP[0-9]{6}$/', $tp)) {
        set_flash('error', 'Please enter a valid TP number, e.g. TP012345.');
    } elseif (db_value($conn, "SELECT COUNT(*) FROM students WHERE tp_number = ?", 's', [$tp]) == 0) {
        set_flash('error', 'Only an existing Student account can be assigned as an Officer.');
    } elseif (($open = open_devices_count($conn, $tp)) > 0) {
        // A student with devices still in progress cannot change role yet (see open_devices_count())
        set_flash('error', $tp . ' still has ' . $open . ' device(s) in progress. Assign them after the devices are finished.');
    } else {
        $conn->begin_transaction();
        try {
            db_exec($conn, "DELETE FROM students WHERE tp_number = ?", 's', [$tp]);
            db_exec($conn, "INSERT INTO officers (tp_number, assigned_by) VALUES (?, ?)", 'ss', [$tp, $me]);
            notify($conn, $tp, 'New role', 'You are now a Recycling Officer. Refresh the page to open your officer dashboard.', $me);
            $conn->commit();
            set_flash('success', $tp . ' is now a Recycling Officer.');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            set_flash('error', 'Could not assign the officer. Please try again.');
        }
    }
    redirect('assign_officer.php');
}

// ---------- Step 1: find the student before anything is changed ----------
$found = null;
$tp_typed = strtoupper(trim($_GET['tp'] ?? ''));
if ($tp_typed !== '') {
    $person = db_one($conn, "SELECT tp_number, name, email, is_active FROM users WHERE tp_number = ?", 's', [$tp_typed]);
    if (!preg_match('/^TP[0-9]{6}$/', $tp_typed)) {
        set_flash('error', 'Please enter a valid TP number, e.g. TP012345.');
    } elseif (!$person) {
        set_flash('error', 'No account has the TP number ' . $tp_typed . '. Please check the number and try again.');
    } elseif (find_role($conn, $tp_typed) !== 'student') {
        set_flash('error', $person['name'] . ' (' . $tp_typed . ') is already a ' . ucfirst(find_role($conn, $tp_typed)) . '. Only a Student can be assigned as an Officer.');
    } elseif ((int) $person['is_active'] !== 1) {
        set_flash('error', $person['name'] . ' (' . $tp_typed . ') is deactivated. Activate the account first.');
    } else {
        $found = $person;   // OK: show the student and the "Assign" button
    }
    if (!$found) redirect('assign_officer.php');   // show the error on a clean page
}

$officers = db_all($conn, "SELECT u.name, u.tp_number, u.email, o.assigned_at, a.name AS by_name
                           FROM officers o
                           JOIN users u ON u.tp_number = o.tp_number
                           LEFT JOIN users a ON a.tp_number = o.assigned_by
                           ORDER BY o.assigned_at DESC");

$page_title = 'Assign officer';
$active = 'users';
require '../includes/admin_top.php';
page_head('user-plus', 'Assign a Recycling Officer', 'Officers handle recycling and disposal. Their student role is replaced.');
?>
<div class="two-col">
    <section class="card">
        <h2 class="card-title"><?= icon('id') ?> Student TP number</h2>
        <?php if (!$found): ?>
            <!-- Step 1: GET form, nothing is changed yet -->
            <form method="get" novalidate data-validate>
                <div class="field">
                    <label for="officer_tp">TP number</label>
                    <input type="text" id="officer_tp" name="tp" maxlength="8" placeholder="TP012345" pattern="[Tt][Pp][0-9]{6}" required
                           data-error="Use TP followed by 6 digits, e.g. TP012345">
                </div>
                <button class="btn btn-primary"><?= icon('search') ?> Find student</button>
            </form>
        <?php else: ?>
            <!-- Step 2: the student exists, so now ask to confirm -->
            <div class="found-person">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($found['name'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($found['name']) ?></strong>
                    <small class="muted"><?= e($found['tp_number']) ?> · <?= e($found['email']) ?> · Student</small>
                </div>
            </div>
            <form method="post" class="btn-row"
                  data-confirm="Assign <?= e($found['name']) ?> (<?= e($found['tp_number']) ?>) as a Recycling Officer? Their student role will be replaced.">
                <?= csrf_field() ?>
                <input type="hidden" name="tp_number" value="<?= e($found['tp_number']) ?>">
                <button class="btn btn-primary"><?= icon('user-plus') ?> Assign as officer</button>
                <a class="btn btn-outline" href="assign_officer.php"><?= icon('x') ?> Cancel</a>
            </form>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2 class="card-title"><?= icon('recycle') ?> Current officers · <?= count($officers) ?></h2>
        <ul class="simple-list">
            <?php foreach ($officers as $o): ?>
                <li>
                    <span class="avatar small"><?= e(mb_strtoupper(mb_substr($o['name'], 0, 1))) ?></span>
                    <div>
                        <strong><?= e($o['name']) ?></strong>
                        <small class="muted"><?= e($o['tp_number']) ?> · since <?= nice_date($o['assigned_at']) ?> · by <?= e($o['by_name'] ?? '-') ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$officers) empty_state('recycle', 'No officers yet.'); ?>
    </section>
</div>
<?php require '../includes/bottom.php'; ?>
