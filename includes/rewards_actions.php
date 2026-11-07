<?php
/*
 * Rewards - the Redeem button + the data (shared by student/rewards.php and volunteer/points.php).
 * Run it BEFORE the page prints anything, because it redirects.
 * Needs: $conn, $me (the logged-in TP number), $self (this page's file name, e.g. 'rewards.php').
 * The CSRF token was already checked by require_role().
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $msg] = redeem_reward($conn, $me, $_POST['rewardID'] ?? '');   // the one shared rule (functions.php)
    set_flash($ok ? 'success' : 'error', $msg);
    redirect($self);   // Post / Redirect / Get
}

$points = (int) db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE tp_number = ?", 's', [$me]);
$rewards = db_all($conn, "SELECT * FROM rewards ORDER BY points_required");
$redemptions = db_all($conn,
    "SELECT r.points_used, r.cash_value, r.status, r.requested_at, w.reward_name
       FROM redemptions r JOIN rewards w ON w.rewardID = r.rewardID
      WHERE r.tp_number = ? ORDER BY r.requested_at DESC, r.redemptionID DESC", 's', [$me]);
$transactions = db_all($conn,
    "SELECT description, txn_type, points, created_at FROM points_transactions
      WHERE tp_number = ? ORDER BY created_at DESC, transactionID DESC", 's', [$me]);
$cats = db_all($conn, "SELECT category_name, multiplier, max_points FROM categories WHERE is_active = 1 ORDER BY multiplier DESC, category_name");

// numbers for the top cards
$paid = 0; $waiting = 0;
foreach ($redemptions as $r) {
    if ($r['status'] === 'completed') $paid += (float) $r['cash_value'];
    if ($r['status'] === 'pending') $waiting++;
}
