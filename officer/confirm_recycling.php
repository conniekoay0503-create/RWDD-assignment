<?php
/*
 * Confirm recycling (the form is on process_recycling.php).
 * Awaiting Recycle -> Recycled: save the weight and disposal method, give the student points,
 * write the status history and tell the student. All in ONE transaction, so it never stops half way.
 * The CSRF token is checked by require_role().
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('recycling_queue.php');
}

$deviceID  = trim((string) ($_POST['deviceID'] ?? ''));
$weightRaw = trim((string) ($_POST['weight_kg'] ?? ''));
$method    = $_POST['disposal_method'] ?? '';
$officerID = $_SESSION['tp_number'];
$back      = 'process_recycling.php?deviceID=' . urlencode($deviceID);

$methods = DISPOSAL_METHODS;   // the shared list in functions.php

// ---- check the form (the browser checks too, but JavaScript can be switched off) ----
if (!preg_match('/^D[0-9]{3,9}$/', $deviceID)) {
    set_flash('error', 'Invalid device.');
    redirect('recycling_queue.php');
}
if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $weightRaw) || (float) $weightRaw <= 0 || (float) $weightRaw > 50) {
    set_flash('error', 'Weight must be a number between 0.01 and 50 kg (up to 2 decimals).');
    redirect($back);
}
if (!in_array($method, $methods, true)) {
    set_flash('error', 'Please choose a disposal method.');
    redirect($back);
}

$conn->begin_transaction();
try {
    // FOR UPDATE locks this device row, so two officers cannot recycle it at the same time
    $device = db_one($conn,
        "SELECT d.deviceID, d.device_name, d.submitted_by, d.status, d.recycle_consent, c.multiplier, c.max_points
           FROM devices d JOIN categories c ON c.categoryID = d.categoryID
          WHERE d.deviceID = ? FOR UPDATE", 's', [$deviceID]);

    if (!$device || $device['status'] !== 'Awaiting Recycle' || (int) $device['recycle_consent'] !== 1) {
        throw new RuntimeException('This device is not waiting for recycling any more.');
    }
    if ($device['submitted_by'] === $officerID) {
        throw new RuntimeException('You cannot process your own device.');
    }
    $already = db_value($conn, "SELECT COUNT(*) FROM points_transactions WHERE deviceID = ? AND txn_type = 'earn'", 's', [$deviceID]);
    if ($already) {
        throw new RuntimeException('Points have already been given for this device.');
    }

    // Worked out on the server with the shared rule (never trust the number shown in the browser)
    $points = calc_points($weightRaw, $device['multiplier'], $device['max_points']);

    // 1. the device
    $n = db_exec($conn,
        "UPDATE devices SET weight_kg = ?, disposal_method = ?, processed_by = ?, status = 'Recycled', status_changed_at = NOW()
          WHERE deviceID = ? AND status = 'Awaiting Recycle'", 'dsss', [$weightRaw, $method, $officerID, $deviceID]);
    if ($n !== 1) {
        throw new RuntimeException('This device is not waiting for recycling any more.');
    }

    // 2. the student's points
    db_exec($conn,
        "INSERT INTO points_transactions (transactionID, tp_number, deviceID, points, txn_type, description)
         VALUES (?, ?, ?, ?, 'earn', ?)",
        'sssis', [next_id($conn, 'points_transactions', 'transactionID', 'T'), $device['submitted_by'], $deviceID, $points,
                  $device['device_name'] . ' recycled']);

    // 3. the timeline
    db_exec($conn,
        "INSERT INTO device_status_history (historyID, deviceID, changed_by, new_status, remarks) VALUES (?, ?, ?, 'Recycled', ?)",
        'ssss', [next_id($conn, 'device_status_history', 'historyID', 'H'), $deviceID, $officerID,
                 'Recycled; weight: ' . $weightRaw . ' kg; method: ' . $method]);

    // 4. tell the student
    notify($conn, $device['submitted_by'], 'Device recycled',
        'Your ' . $device['device_name'] . ' (' . $deviceID . ') has been recycled. You earned ' . $points . ' points.');

    $conn->commit();
    set_flash('success', $device['device_name'] . ' recycled. The student earned ' . $points . ' points.');
    redirect('recycling_queue.php');

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Recycling failed: ' . $e->getMessage());
    set_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Recycling could not be completed. Please try again.');
    redirect('recycling_queue.php');
}
