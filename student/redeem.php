<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$id = trim((string)($_POST['rewardID'] ?? ''));

// check if the redemption request is valid (the CSRF token was already checked by require_role())
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !preg_match('/^RW?[0-9]{3,9}$/', $id)) {
    set_flash('error', 'Invalid redemption request.');
    redirect('rewards.php');
}

try {
    $conn->begin_transaction();

    // lock student and reward so points and stock stay correct
    $student = db_one($conn,
        "SELECT tp_number FROM students WHERE tp_number = ? FOR UPDATE",
        's', [$tp]);

    if (!$student) throw new RuntimeException('Student account not found.');

    $reward = db_one($conn,
        "SELECT reward_name, points_required, cash_value, stock
         FROM rewards WHERE rewardID = ? FOR UPDATE", 's', [$id]);

    if (!$reward) throw new RuntimeException('Reward not found.');
    if ($reward['stock'] <= 0) throw new RuntimeException('Reward is out of stock.');

    $required = (int)$reward['points_required'];
    if ($required <= 0) throw new RuntimeException('Invalid reward points.');

    $balance = (int)db_value($conn,
        "SELECT COALESCE(SUM(points),0) FROM points_transactions WHERE tp_number = ?",
        's', [$tp]);

    if ($balance < $required) throw new RuntimeException('Not enough points.');

    // deduct stock and save the redemption
    $updated = db_exec($conn,
        "UPDATE rewards SET stock = stock - 1 WHERE rewardID = ? AND stock > 0",
        's', [$id]);

    if ($updated !== 1) throw new RuntimeException('Reward unavailable.');

    $rid = next_id($conn, 'redemptions', 'redemptionID', 'R');
    $tid = next_id($conn, 'points_transactions', 'transactionID', 'T');

    db_exec($conn,
        "INSERT INTO redemptions
         (redemptionID, tp_number, rewardID, points_used, cash_value, status)
         VALUES (?,?,?,?,?,'pending')",
        'sssid', [$rid, $tp, $id, $required, (float)$reward['cash_value']]);

    db_exec($conn,
        "INSERT INTO points_transactions
         (transactionID, tp_number, redemptionID, points, txn_type, description)
         VALUES (?,?,?,?,'redeem',?)",
        'sssis', [$tid, $tp, $rid, -$required, 'Redeemed ' . $reward['reward_name']]);

    $user = db_one($conn,
        "SELECT name FROM users WHERE tp_number = ?", 's', [$tp]);

    notify_admins($conn, 'New cashback request',
        ($user['name'] ?? $tp) . ' redeemed ' . $reward['reward_name'] .
        " ($required points). Please process the APCard payment.");

    $conn->commit();
    set_flash('success', 'Redemption submitted. Awaiting Admin approval.');

} catch (Throwable $e) {
    $conn->rollback();
    if (!($e instanceof RuntimeException)) error_log($e->getMessage());

    set_flash('error', $e instanceof RuntimeException
        ? $e->getMessage() : 'Redemption failed. Please try again.');
}

redirect('rewards.php');
