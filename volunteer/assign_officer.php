<?php
/*
 * Users - assign a Recycling Officer  (Figure 23, page 3 of 3)
 * Only an existing student can become an officer: delete the student row, insert the officer row.
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
    } else {
        $conn->begin_transaction();
        try {
            db_exec($conn, "DELETE FROM students WHERE tp_number = ?", 's', [$tp]);
            db_exec($conn, "INSERT INTO officers (tp_number, assigned_by) VALUES (?, ?)", 'ss', [$tp, $me]);
            notify($conn, $tp, 'New role', 'You are now a Recycling Officer. Please log in again to open your officer dashboard.', $me);
            $conn->commit();
            set_flash('success', $tp . ' is now a Recycling Officer.');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            set_flash('error', 'Could not assign the officer. Please try again.');
        }
    }
    redirect('assign_officer.php');
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
        <form method="post" novalidate data-validate data-confirm="Assign this user as a Recycling Officer?">
            <div class="field">
                <label for="officer_tp">TP number</label>
                <input type="text" id="officer_tp" name="tp_number" maxlength="8" placeholder="TP012345" pattern="[Tt][Pp][0-9]{6}" required
                       data-error="Use TP followed by 6 digits">
            </div>
            <button class="btn btn-primary"><?= icon('user-plus') ?> Assign officer</button>
        </form>
    </section>
    <section class="card">
        <h2 class="card-title"><?= icon('recycle') ?> Current officers · <?= count($officers) ?></h2>
        <ul class="simple-list">
            <?php foreach ($officers as $o): ?>
                <li>
                    <span class="avatar small"><?= e(strtoupper(substr($o['name'], 0, 1))) ?></span>
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
<?php require '../includes/admin_bottom.php'; ?>
