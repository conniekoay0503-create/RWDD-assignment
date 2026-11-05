
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('rewards.php');
}

$studentTP = $_SESSION['tp_number'];
$rewardID = trim((string) ($_POST['rewardID'] ?? ''));
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !preg_match('/^RW[0-9]{3,8}$/', $rewardID) ||
    !is_string($csrfToken) ||
    empty($_SESSION['redeem_csrf']) ||
    !hash_equals($_SESSION['redeem_csrf'], $csrfToken)
) {
    set_flash('error', 'Invalid redemption request.');
    redirect('rewards.php');
}

try {
    $conn->begin_transaction();

    // Lock the student row to serialize redemption requests
    // from the same account.
    $student = db_one(
        $conn,
        "SELECT tp_number FROM students
         WHERE tp_number = ?
         FOR UPDATE",
        's',
        [$studentTP]
    );

    if (!$student) {
        throw new RuntimeException('Student account not found.');
    }

    // Lock the selected reward and retrieve its current values.
    $reward = db_one(
        $conn,
        "SELECT rewardID, reward_name, points_required,
                cash_value, stock
         FROM rewards
         WHERE rewardID = ?
         FOR UPDATE",
        's',
        [$rewardID]
    );

    if (!$reward) {
        throw new RuntimeException('Reward not found.');
    }

    $pointsRequired = (int) $reward['points_required'];

    if ((int) $reward['stock'] <= 0) {
        throw new RuntimeException('This reward is out of stock.');
    }

    // Recalculate points INSIDE the transaction.
    $balance = (int) db_value(
        $conn,
        "SELECT COALESCE(SUM(points), 0)
         FROM points_transactions
         WHERE tp_number = ?",
        's',
        [$studentTP]
    );

    if ($balance < $pointsRequired) {
        throw new RuntimeException(
            'You do not have enough points to redeem this reward.'
        );
    }

    // Stock is reserved on submission and released if Admin rejects.
    // Admin must NOT deduct stock a second time on approval.
    // Conditional stock decrement.
    $affected = db_exec(
        $conn,
        "UPDATE rewards
         SET stock = stock - 1
         WHERE rewardID = ? AND stock > 0",
        's',
        [$rewardID]
    );

    if ($affected !== 1) {
        throw new RuntimeException('Reward is no longer available.');
    }

    // Create the pending redemption request.
    $redemptionID = next_id($conn, 'redemptions', 'redemptionID', 'R');
    $transactionID = next_id($conn, 'points_transactions', 'transactionID', 'T');
    db_exec(
        $conn,
        "INSERT INTO redemptions
         (redemptionID, tp_number, rewardID, points_used, cash_value, status)
         VALUES (?, ?, ?, ?, ?, 'pending')",
        'sssid',
        [
            $redemptionID,
            $studentTP,
            $rewardID,
            $pointsRequired,
            (float) $reward['cash_value']
        ]
    );


    // Record the negative points transaction.
    db_exec(
        $conn,
        "INSERT INTO points_transactions
         (transactionID, tp_number, redemptionID, points, txn_type, description)
         VALUES (?, ?, ?, ?, 'redeem', ?)",
        'sssis',
        [
            $transactionID,
            $studentTP,
            $redemptionID,
            -$pointsRequired,
            'Redeemed ' . $reward['reward_name']
        ]
    );

    // Retrieve the student's name for the Admin notification.
    $user = db_one(
        $conn,
        "SELECT name FROM users WHERE tp_number = ?",
        's',
        [$studentTP]
    );

    $studentName = $user['name'] ?? $studentTP;

    notify_admins(
        $conn,
        'New cashback request',
        $studentName . ' redeemed ' .
        $reward['reward_name'] . ' (' .
        $pointsRequired . ' points). Please process the APCard payment.'
    );

    $conn->commit();

    // Rotate token after a successful redemption.
    $_SESSION['redeem_csrf'] = bin2hex(random_bytes(32));

    set_flash(
        'success',
        'Redemption successful! Your cashback request is pending Admin approval.'
    );

} catch (Throwable $exception) {
    $conn->rollback();

    if ($exception instanceof RuntimeException) {
        set_flash('error', $exception->getMessage());
    } else {
        error_log($exception->getMessage());
        set_flash('error', 'Redemption failed. Please try again.');
    }
}

redirect('rewards.php');
