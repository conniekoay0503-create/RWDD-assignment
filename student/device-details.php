
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
$deviceID = trim((string) ($_GET['id'] ?? ''));

if (!preg_match('/^D[0-9]{3,9}$/', $deviceID)) {
    http_response_code(400);
    exit('Invalid device ID.');
}

// CSRF protection for status-changing requests.
if (empty($_SESSION['device_csrf'])) {
    $_SESSION['device_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['device_csrf'];
$error = '';

// Students may only view and change their own devices.
$device = db_one(
    $conn,
    "SELECT d.*, c.category_name
     FROM devices d
     JOIN categories c ON d.categoryID = c.categoryID
     WHERE d.deviceID = ? AND d.submitted_by = ?",
    'ss',
    [$deviceID, $studentTP]
);

if (!$device) {
    http_response_code(404);
    exit('Device not found.');
}

// Handle actions using POST, never GET.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    $transitions = [
        'cancel' => [
            'from' => 'Submitted',
            'to' => 'Cancelled',
            'remark' => 'Submission cancelled by student.'
        ],
        'allow_recycling' => [
            'from' => 'Pending Consent',
            'to' => 'Awaiting Recycle',
            'remark' => 'Student consented to recycling.'
        ],
        'request_return' => [
            'from' => 'Pending Consent',
            'to' => 'Return Requested',
            'remark' => 'Student requested device return; awaiting volunteer drop-off at Green Hub.'
        ],
        'collect_repaired' => [
            'from' => 'Ready for Collection',
            'to' => 'Completed',
            'remark' => 'Student confirmed repaired device collection.'
        ],
        'collect_unrepaired' => [
            'from' => 'Returned Unrepaired',
            'to' => 'Collected Unrepaired',
            'remark' => 'Student confirmed unrepaired device collection.'
        ]
    ];

    if (!is_string($token) ||
        !hash_equals($csrfToken, $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif (!isset($transitions[$action])) {
        $error = 'Invalid action.';
    } else {
        $transition = $transitions[$action];

        try {
            $conn->begin_transaction();

            // Lock the device to prevent conflicting status changes.
            $current = db_one(
                $conn,
                "SELECT status FROM devices
                 WHERE deviceID = ? AND submitted_by = ?
                 FOR UPDATE",
                'ss',
                [$deviceID, $studentTP]
            );

            if (!$current ||
                $current['status'] !== $transition['from']) {
                throw new RuntimeException(
                    'This action is no longer available for the device.'
                );
            }

            $newStatus = $transition['to'];

            $consent = null;
            if ($action === 'allow_recycling') {
                $consent = 1;
            } elseif ($action === 'request_return') {
                $consent = 0;
            }

            $affected = db_exec(
                $conn,
                "UPDATE devices
                 SET status = ?,
                     recycle_consent = COALESCE(?, recycle_consent),
                     status_changed_at = NOW()
                 WHERE deviceID = ?
                   AND submitted_by = ?
                   AND status = ?",
                'sisss',
                [
                    $newStatus,
                    $consent,
                    $deviceID,
                    $studentTP,
                    $transition['from']
                ]
            );

            if ($affected !== 1) {
                throw new RuntimeException(
                    'Unable to update device status.'
                );
            }

            $historyID = next_id($conn, 'device_status_history', 'historyID', 'H');
            db_exec(
                $conn,
                "INSERT INTO device_status_history
                 (historyID, deviceID, changed_by, new_status, remarks)
                 VALUES (?, ?, ?, ?, ?)",
                'sssss',
                [
                    $historyID,
                    $deviceID,
                    $studentTP,
                    $newStatus,
                    $transition['remark']
                ]
            );

            $conn->commit();

            set_flash('success', 'Device status updated successfully.');
            redirect('device-details.php?id=' . $deviceID);

        } catch (Throwable $exception) {
            $conn->rollback();
            error_log($exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to update the device. Please try again.';
        }
    }
}

// Refresh device information after any action.
$device = db_one(
    $conn,
    "SELECT d.*, c.category_name
     FROM devices d
     JOIN categories c ON d.categoryID = c.categoryID
     WHERE d.deviceID = ? AND d.submitted_by = ?",
    'ss',
    [$deviceID, $studentTP]
);

$history = db_all(
    $conn,
    "SELECT new_status, remarks, changed_at
     FROM device_status_history
     WHERE deviceID = ?
     ORDER BY changed_at DESC, historyID DESC",
    's',
    [$deviceID]
);

// Only display photo paths stored in the expected upload folder.
$photoPath = $device['photo_path'] ?? '';

$photoValid = is_string($photoPath) &&
    preg_match(
        '~^uploads/devices/[a-zA-Z0-9_.-]+$~',
        $photoPath
    ) === 1;

$photoURL = $photoValid ? '../' . $photoPath : null;


?>
<?php $page_title = 'Device Details'; require '../includes/student_top.php'; ?>
<div class="student-page-head"><span class="page-icon"><?= icon('device') ?></span><div><span class="student-eyebrow">STUDENT PORTAL</span><h1>Device Details</h1><p>Manage your e-waste journey in one place.</p></div></div>
<section class="card student-panel">


    <a href="my-devices.php">← Back to My Devices</a>

    

    

    <?php if ($error !== ''): ?>
        <p role="alert" style="color: #b42318;">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <h2>Device Information</h2>

    <?php if ($photoURL && is_file(__DIR__ . '/../' . $photoPath)): ?>
        <img
            src="<?= e($photoURL) ?>"
            alt="Submitted device photo"
            style="max-width: 260px; max-height: 220px; object-fit: contain;"
        >
    <?php else: ?>
        <p>No device photo available.</p>
    <?php endif; ?>

    <p><strong>Device ID:</strong>
        #<?= e($device['deviceID']) ?></p>

    <p><strong>Category:</strong>
        <?= e($device['category_name']) ?></p>

    <p><strong>Problem:</strong>
        <?= e($device['fault_description']) ?></p>

    <p><strong>Current Status:</strong>
        <?= e($device['status']) ?></p>

    <p><strong>Submitted:</strong>
        <?= e(nice_date($device['submitted_at'], true)) ?></p>

    <hr>

    <h2>Available Actions</h2>

    <?php
    $status = $device['status'];

    $availableActions = [];

    if ($status === 'Submitted') {
        $availableActions['cancel'] = 'Cancel Submission';
    }

    if ($status === 'Pending Consent') {
        $availableActions['allow_recycling'] = 'Allow Recycling';
        $availableActions['request_return'] = 'Request Device Return';
    }

    if ($status === 'Ready for Collection') {
        $availableActions['collect_repaired'] =
            'Confirm Repaired Device Collection';
    }

    if ($status === 'Return Requested') {
        echo '<p class="alert alert-info">Your return has been requested. Please wait until the volunteer brings your device to the Green Hub before collecting it.</p>';
    }

    if ($status === 'Returned Unrepaired') {
        $availableActions['collect_unrepaired'] =
            'Confirm Unrepaired Device Collection';
    }
    ?>

    <?php if (empty($availableActions)): ?>
        <p>No action is required at this stage.</p>
    <?php else: ?>
        <?php foreach ($availableActions as $action => $label): ?>
            <form
                method="POST"
                action="device-details.php?id=<?= $deviceID ?>"
                style="margin: 12px 0;"
                onsubmit="return confirm('Are you sure you want to proceed?');"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="<?= e($action) ?>"
                >
                <button type="submit" class="btn btn-primary">
                    <?= e($label) ?>
                </button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <hr>

    <h2>Status History</h2>

    <?php if (empty($history)): ?>
        <p>No status history available.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?= e($record['new_status']) ?></td>
                        <td><?= e($record['remarks'] ?: '-') ?></td>
                        <td>
                            <?= e(nice_date(
                                $record['changed_at'],
                                true
                            )) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>


</section>
<?php require '../includes/student_bottom.php'; ?>
