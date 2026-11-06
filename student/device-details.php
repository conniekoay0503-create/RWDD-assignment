
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$id = trim((string)($_GET['id'] ?? ''));
$error = '';

if (!preg_match('/^D[0-9]{3,9}$/', $id)) {
    exit('Invalid device ID.');
}

if (empty($_SESSION['device_csrf'])) {
    $_SESSION['device_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['device_csrf'];

$sql = "SELECT d.*, c.category_name
        FROM devices d
        JOIN categories c ON d.categoryID = c.categoryID
        WHERE d.deviceID = ? AND d.submitted_by = ?";

$device = db_one($conn, $sql, 'ss', [$id, $tp]);
if (!$device) exit('Device not found.');

$actions = [
    'cancel' => ['Submitted', 'Cancelled', 'Cancel'],
    'allow_recycling' => ['Pending Consent', 'Awaiting Recycle', 'Recycle'],
    'request_return' => ['Pending Consent', 'Return Requested', 'Request Return'],
    'collect_repaired' => ['Ready for Collection', 'Completed', 'Confirm Collection'],
    'collect_unrepaired' => ['Returned Unrepaired', 'Collected Unrepaired', 'Confirm Collection']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($csrf, $token) ||
        !isset($actions[$action])) {
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
?>

<div class="student-page-head">
    <span class="page-icon">📱</span>
    <div>
        <h1>Device Details</h1>
        <p>Track your device status.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="my-devices.php" class="btn">&larr; Back</a>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <h2><?= e($device['device_name']) ?></h2>
    <?php if ($photo &&
        preg_match('~^uploads/devices/[a-zA-Z0-9_.-]+$~', $photo)): ?>
        <img src="../<?= e($photo) ?>" width="240" alt="Device">
    <?php endif; ?>

    <p>ID: <?= e($id) ?></p>
    <p>Category: <?= e($device['category_name']) ?></p>
    <p>Problem: <?= e($device['fault_description']) ?></p>
    <p>Submitted: <?= e(nice_date($device['submitted_at'])) ?></p>
    <p>Status:
        <span class="badge <?= e($class) ?>"><?= e($status) ?></span>
    </p>

    <h2>Actions</h2>
    <?php foreach ($actions as $key => $a): ?>
        <?php if ($status !== $a[0]) continue; ?>
        <form method="post"
              onsubmit="return confirm('Are you sure?')">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= e($key) ?>">
            <button class="btn btn-primary"><?= e($a[2]) ?></button>
        </form>
    <?php endforeach; ?>

    <h2>Status History</h2>
    <div class="table-wrap">
        <table>
            <tr><th>Status</th><th>Remarks</th><th>Date</th></tr>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['new_status']) ?></td>
                    <td><?= e($h['remarks'] ?: '-') ?></td>
                    <td><?= e(nice_date($h['changed_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>

<?php require '../includes/student_bottom.php'; ?>
