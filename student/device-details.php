<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$id = trim((string)($_GET['id'] ?? ''));
$error = '';

// A wrong or someone else's device: go back to My Devices with a message (not a blank page)
if (!preg_match('/^D[0-9]{3,9}$/', $id)) {
    set_flash('error', 'Invalid device ID.');
    redirect('my-devices.php');
}

$sql = "SELECT d.*, c.category_name
        FROM devices d
        JOIN categories c ON d.categoryID = c.categoryID
        WHERE d.deviceID = ? AND d.submitted_by = ?";

$device = db_one($conn, $sql, 'ss', [$id, $tp]);
if (!$device) {
    set_flash('error', 'Device not found.');
    redirect('my-devices.php');
}

// action => [from status, to status, button text]. The page shows a button only when the device is in the "from" status.
$actions = [
    'drop_off' => ['Submitted', 'Dropped Off', 'Mark as dropped off'],   // the student has brought it to the Green Hub
    'cancel' => ['Submitted', 'Cancelled', 'Cancel'],
    'allow_recycling' => ['Pending Consent', 'Awaiting Recycle', 'Recycle'],
    'request_return' => ['Pending Consent', 'Return Requested', 'Request Return'],
    'collect_repaired' => ['Ready for Collection', 'Completed', 'Confirm Collection'],
    'collect_unrepaired' => ['Returned Unrepaired', 'Collected Unrepaired', 'Confirm Collection'],
    'collect_unclaimed' => ['Returned Unclaimed', 'Collected Unrepaired', 'Confirm Collection']   // nobody claimed it in 7 days
];

// How each button looks: [icon, button class, the question in the confirm pop-up]
$action_look = [
    'drop_off' => ['check', 'btn-primary', 'Have you brought this device to the Green Hub?'],
    'cancel' => ['x', 'btn-danger', 'Cancel this submission? This cannot be undone.'],
    'allow_recycling' => ['recycle', 'btn-primary', 'Allow the Green Hub to recycle this device? You will earn points.'],
    'request_return' => ['arrow-left', 'btn-outline', 'Ask for this device back? A volunteer will take it to the counter.'],
    'collect_repaired' => ['check', 'btn-primary', 'Have you collected this device from the Green Hub?'],
    'collect_unrepaired' => ['check', 'btn-primary', 'Have you collected this device from the Green Hub?'],
    'collect_unclaimed' => ['check', 'btn-primary', 'Have you collected this device from the Green Hub?'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // (the CSRF token was already checked by require_role())
    if (!isset($actions[$action])) {
        $error = 'Invalid request.';
    } else {
        [$from, $to] = $actions[$action];

        try {
            $conn->begin_transaction();

            $current = db_one($conn,
                "SELECT status FROM devices
                 WHERE deviceID = ? AND submitted_by = ?
                 FOR UPDATE", 'ss', [$id, $tp]);

            if (!$current || $current['status'] !== $from) {
                throw new RuntimeException('Action unavailable.');
            }

            $consent = null;
            if ($action === 'allow_recycling') $consent = 1;
            if ($action === 'request_return') $consent = 0;

            $updated = db_exec($conn,
                "UPDATE devices SET status = ?,
                 recycle_consent = COALESCE(?, recycle_consent),
                 status_changed_at = NOW()
                 WHERE deviceID = ? AND submitted_by = ?
                 AND status = ?",
                'sisss', [$to, $consent, $id, $tp, $from]);

            if ($updated !== 1) {
                throw new RuntimeException('Update failed.');
            }

            $historyID = next_id(
                $conn, 'device_status_history', 'historyID', 'H');

            db_exec($conn,
                "INSERT INTO device_status_history
                 (historyID, deviceID, changed_by, new_status, remarks)
                 VALUES (?, ?, ?, ?, ?)",
                'sssss', [
                    $historyID, $id, $tp, $to,
                    'Student action: ' . $actions[$action][2]
                ]);

            $conn->commit();
            set_flash('success', 'Device updated.');
            redirect('device-details.php?id=' . urlencode($id));
        } catch (Throwable $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $error = $e instanceof RuntimeException
                ? $e->getMessage() : 'Update failed.';
        }
    }
}

$device = db_one($conn, $sql, 'ss', [$id, $tp]);
$history = db_all($conn,
    "SELECT new_status, remarks, changed_at
     FROM device_status_history WHERE deviceID = ?
     ORDER BY changed_at DESC, historyID DESC",
    's', [$id]);

$status = $device['status'];
$class = 'status-' . strtolower(str_replace(' ', '-', $status));
$photo = $device['photo_path'] ?? '';

$page_title = 'Device Details';
require '../includes/student_top.php';
page_head('device', $device['device_name'], $id . ' · ' . $device['category_name'],
    '<a class="btn btn-outline" href="my-devices.php">' . icon('arrow-left') . ' Back to My Devices</a>');
?>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" data-flash="error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<div class="two-col">
    <section class="card">
        <h2 class="card-title"><?= icon('info') ?> About this device</h2>
        <?php if ($photo && preg_match('~^uploads/devices/[a-zA-Z0-9_.-]+$~', $photo)): ?>
            <img class="device-photo" src="../<?= e($photo) ?>" alt="Photo of <?= e($device['device_name']) ?>">
        <?php endif; ?>
        <dl class="info-list">
            <dt>Status</dt><dd><span class="badge <?= e($class) ?>"><?= e($status) ?></span></dd>
            <dt>Category</dt><dd><?= e($device['category_name']) ?></dd>
            <dt>Problem</dt><dd><?= e($device['fault_description']) ?></dd>
            <dt>Submitted</dt><dd><?= e(nice_date($device['submitted_at'])) ?></dd>
        </dl>
    </section>

    <div>
        <section class="card">
            <h2 class="card-title"><?= icon('clock') ?> What you can do</h2>
            <?php $shown = 0; ?>
            <div class="btn-row">
            <?php foreach ($actions as $key => $a): ?>
                <?php if ($status !== $a[0]) continue; $shown++; [$ico, $btn, $ask] = $action_look[$key]; ?>
                <form method="post" data-confirm="<?= e($ask) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= e($key) ?>">
                    <button class="btn <?= $btn ?>"><?= icon($ico) ?> <?= e($a[2]) ?></button>
                </form>
            <?php endforeach; ?>
            </div>
            <?php if (!$shown): ?>
                <p class="muted"><?= icon('info') ?> Nothing to do right now. We will send you a notification when the status changes.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2 class="card-title"><?= icon('history') ?> Timeline</h2>
            <?php if (!$history): ?>
                <p class="muted">No history yet.</p>
            <?php else: ?>
                <ol class="timeline">
                <?php foreach ($history as $h): ?>
                    <li>
                        <strong><?= e($h['new_status']) ?></strong>
                        <?php if ($h['remarks']): ?><span class="muted"> · <?= e($h['remarks']) ?></span><?php endif; ?>
                        <small class="muted"><?= e(nice_date($h['changed_at'], true)) ?></small>
                    </li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require '../includes/student_bottom.php'; ?>
