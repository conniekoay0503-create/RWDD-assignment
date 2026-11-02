
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request.');
}

if (
    empty($_SESSION['recycle_csrf']) ||
    !hash_equals(
        $_SESSION['recycle_csrf'],
        $_POST['csrf_token'] ?? ''
    )
) {
    http_response_code(403);
    exit('Invalid security token.');
}

$deviceID = trim($_POST['deviceID'] ?? '');
$weightRaw = trim((string)($_POST['weight_kg'] ?? ''));
$method = $_POST['disposal_method'] ?? '';
$officerID = $_SESSION['tp_number'];

$methods = [
    'Certified e-waste recycler',
    'Battery take-back programme',
    'Metal recovery',
    'Harvest parts for repair stock'
];

if (
    !preg_match('/^[A-Za-z0-9_-]{1,10}$/', $deviceID) ||
    !preg_match('/^\d{1,2}(\.\d{1,2})?$/', $weightRaw) ||
    (float)$weightRaw <= 0 ||
    (float)$weightRaw > 50 ||
    !in_array($method, $methods, true)
) {
    http_response_code(400);
    exit('Invalid device, weight or disposal method.');
}

// Generate prefixed IDs (T, H, N).
// Recycling requests use a named MySQL lock to
// coordinate ID generation within this workflow.
function nextRecordID($conn, $table, $column, $prefix) {
    $sql = "SELECT COALESCE(
        MAX(CAST(SUBSTRING($column, 2) AS UNSIGNED)), 0
    ) AS last_number
    FROM $table
    WHERE $column REGEXP '^{$prefix}[0-9]+$'";

    $result = $conn->query($sql);
    $number = (int)$result->fetch_assoc()['last_number'] + 1;

    $id = $prefix . str_pad((string)$number, 3, '0', STR_PAD_LEFT);

    if (strlen($id) > 10) {
        throw new Exception('ID capacity exceeded.');
    }

    return $id;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$locked = false;
$inTransaction = false;

try {
    // Serialize ID generation with other cooperative
    // recycling requests.
    $lock = $conn->query(
        "SELECT GET_LOCK('rwdd_recycling_ids', 10) AS acquired"
    )->fetch_assoc();

    if ((int)$lock['acquired'] !== 1) {
        throw new Exception('System busy. Please retry.');
    }

    $locked = true;
    $conn->begin_transaction();
    $inTransaction = true;

    // Lock device to prevent concurrent processing.
    $stmt = $conn->prepare(
        "SELECT d.deviceID, d.submitted_by,
                d.status, d.recycle_consent,
                c.multiplier, c.max_points
         FROM devices d
         JOIN categories c ON d.categoryID = c.categoryID
         WHERE d.deviceID = ?
         FOR UPDATE"
    );
    $stmt->bind_param('s', $deviceID);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$device ||
        $device['status'] !== 'Awaiting Recycle' ||
        (int)$device['recycle_consent'] !== 1
    ) {
        throw new Exception('Device is not eligible for recycling.');
    }

    if ($device['submitted_by'] === $officerID) {
        throw new Exception('You cannot process your own device.');
    }

    // Check for any existing device earning transaction.
    $stmt = $conn->prepare(
        "SELECT transactionID
         FROM points_transactions
         WHERE deviceID = ? AND txn_type = 'earn'
         LIMIT 1"
    );
    $stmt->bind_param('s', $deviceID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        throw new Exception('Points have already been awarded.');
    }

    // Calculate on the server, never trust browser preview.
    $points = (int)round(
        min(
            (float)$weightRaw * (float)$device['multiplier'],
            (int)$device['max_points']
        )
    );

    $studentID = $device['submitted_by'];

    // Save device processing details.
    $stmt = $conn->prepare(
        "UPDATE devices
         SET weight_kg = ?,
             disposal_method = ?,
             processed_by = ?,
             status = 'Recycled',
             status_changed_at = NOW()
         WHERE deviceID = ? AND status = 'Awaiting Recycle'"
    );
    $stmt->bind_param(
        'dsss',
        $weightRaw, $method, $officerID, $deviceID
    );
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception('Device update failed.');
    }
    $stmt->close();

    $transactionID = nextRecordID(
        $conn, 'points_transactions', 'transactionID', 'T'
    );
    $historyID = nextRecordID(
        $conn, 'device_status_history', 'historyID', 'H'
    );
    $notificationID = nextRecordID(
        $conn, 'notifications', 'notificationID', 'N'
    );

    // Award points.
    $description = "Recycling reward for device $deviceID";

    $stmt = $conn->prepare(
        "INSERT INTO points_transactions
         (transactionID, tp_number, deviceID,
          points, txn_type, description)
         VALUES (?, ?, ?, ?, 'earn', ?)"
    );
    $stmt->bind_param(
        'sssis',
        $transactionID, $studentID,
        $deviceID, $points, $description
    );
    $stmt->execute();
    $stmt->close();

    // Record status history.
    $remarks = "Recycled; weight: {$weightRaw} kg; method: {$method}";

    $stmt = $conn->prepare(
        "INSERT INTO device_status_history
         (historyID, deviceID, changed_by, new_status, remarks)
         VALUES (?, ?, ?, 'Recycled', ?)"
    );
    $stmt->bind_param(
        'ssss',
        $historyID, $deviceID, $officerID, $remarks
    );
    $stmt->execute();
    $stmt->close();


    // Notify student.
    // created_by is NULL because this notification is
    // generated automatically by the Recycling Officer workflow.
    // The database only allows admin TP numbers in created_by.

    $title = 'Device Recycled';

    $message = "Your device $deviceID has been recycled. "
            . "You earned $points points.";

    $stmt = $conn->prepare(
        "INSERT INTO notifications
        (notificationID, tp_number, created_by,
        title, message, is_read)
        VALUES (?, ?, NULL, ?, ?, 0)"
    );

    $stmt->bind_param(
        'ssss',
        $notificationID,
        $studentID,
        $title,
        $message
    );

    $stmt->execute();
    $stmt->close();


    $conn->commit();
    $inTransaction = false;

    unset($_SESSION['recycle_csrf']);

    header('Location: recycling_queue.php?success=1');
    exit;

} catch (Throwable $e) {

    if ($inTransaction) {
        $conn->rollback();
    }

    // Record the actual error in the PHP error log.
    error_log('Recycling failed: ' . $e->getMessage());

    http_response_code(409);

    exit(
        'Recycling could not be completed. ' .
        'Please check the records or contact the developer.'
    );

} finally {

    if ($locked) {
        $conn->query(
            "SELECT RELEASE_LOCK('rwdd_recycling_ids')"
        );
    }
}

