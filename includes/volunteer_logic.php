<?php
/*
 * includes/volunteer_logic.php - everything the Volunteer pages need from the database.
 * The pages (volunteer/*.php) only call these functions, so each page stays short.
 *
 * Written on top of the team helpers in includes/functions.php:
 *   db_all / db_one / db_value / db_exec, next_id, notify, notify_admins, set_flash
 * Every function here starts with "vol_" so it can never clash with a teammate's function.
 *
 * Types string for the helpers: 's' = string, 'i' = integer (e.g. 'ss' for two strings).
 * Our IDs are strings like 'D009', so most of them are 's'.
 */

const VOL_QUEUE_DAYS = 7;   // an unclaimed device goes back to its owner after this many days

// Words used in the statistics (our own definitions - say them in the presentation):
//   fixed     = the repair worked: 'Ready for Collection' or 'Completed'
//   not fixed = 'Recycled', 'Awaiting Recycle', 'Returned Unrepaired', 'Collected Unrepaired', 'Disposed'
//   success rate = fixed / (fixed + not fixed). Jobs still open are not counted yet.
const VOL_FIXED    = ['Ready for Collection', 'Completed'];
const VOL_NOTFIXED = ['Recycled', 'Awaiting Recycle', 'Returned Unrepaired', 'Collected Unrepaired', 'Disposed'];

const VOL_NOTE_MAX = 255;   // device_status_history.remarks is VARCHAR(255)

/* =====================================================================
 * 1. Small helpers
 * ===================================================================== */

/* CSRF: every form carries a secret token that only our own page knows,
 * so a fake form on another website cannot make the volunteer press a button. */
function vol_csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function vol_csrf_ok($token)
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/* A hidden input to paste inside every <form method="post"> */
function vol_csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(vol_csrf_token()) . '">';
}

/* Device IDs look like 'D009'. Anything else typed into a form is rejected. */
function vol_valid_device_id($id)
{
    return is_string($id) && preg_match('/^D\d{3,7}$/', $id) === 1;
}

/* CSS class of a status badge. The team stylesheet already has .status-in-repair etc. */
function vol_status_class($status)
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

/* Weight for display: 0.2 -> "0.2 kg", NULL -> a dash */
function vol_kg($value)
{
    if ($value === null || $value === '') return '–';
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' kg';
}

/* Plain number of kg without the unit ("0" when empty) */
function vol_kg_number($value)
{
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
}

/* The photo of a device, or null when the file is missing (the page then shows an icon). */
function vol_photo_url($path)
{
    if (!$path || strpos($path, '..') !== false) return null;
    return is_file(__DIR__ . '/../' . $path) ? '../' . $path : null;
}

/* "3 days left" */
function vol_days_left_text($days)
{
    $days = max(0, (int) $days);
    return $days . ($days === 1 ? ' day left' : ' days left');
}

/*
 * Run $work inside ONE database transaction (all writes succeed together, or none do).
 * $work returns [true|false, message]. false = a normal "no" (e.g. someone claimed it first),
 * so we roll back and pass the message on.
 *
 * Retry: IDs like 'H082' come from next_id() (biggest number + 1). If two volunteers
 * click at the very same moment they may get the same number, and MySQL answers with
 * "Duplicate entry" (error 1062). Then we simply run the transaction again.
 */
function vol_transaction($conn, callable $work)
{
    for ($attempt = 1; ; $attempt++) {
        $conn->begin_transaction();
        try {
            $result = $work();
            if ($result[0] === true) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
            return $result;
        } catch (Throwable $ex) {
            $conn->rollback();
            $duplicate = $ex->getCode() === 1062 || stripos($ex->getMessage(), 'Duplicate entry') !== false
                      || stripos($ex->getMessage(), 'UNIQUE constraint failed') !== false;
            if (!$duplicate || $attempt >= 3) {
                throw $ex;
            }
        }
    }
}

/* Add one row to device_status_history (every status change is written there) */
function vol_log_status($conn, $device_id, $changed_by, $new_status, $remarks)
{
    $id = next_id($conn, 'device_status_history', 'historyID', 'H');
    db_exec($conn, "INSERT INTO device_status_history (historyID, deviceID, changed_by, new_status, remarks) VALUES (?, ?, ?, ?, ?)",
        'sssss', [$id, $device_id, $changed_by, $new_status, $remarks]);
}

/* =====================================================================
 * 2. REPAIR QUEUE  (flowchart Figure 15)
 * ===================================================================== */

/*
 * AUTO-RETURN: a device that has been 'Dropped Off' for more than 7 days and still has
 * no volunteer goes back to its owner (status 'Returned Unclaimed').
 * XAMPP has no scheduler (cron), so this check runs every time a volunteer opens the
 * queue or Home ("lazy check"). Returns how many devices were returned.
 */
function vol_return_expired($conn)
{
    $days = (int) VOL_QUEUE_DAYS;   // our own constant, not user input, so it is safe inside the SQL text
    $expired = db_all($conn,
        "SELECT deviceID, submitted_by, device_name FROM devices
          WHERE status = 'Dropped Off' AND claimed_by IS NULL
            AND DATEDIFF(NOW(), status_changed_at) > $days");

    $returned = 0;
    foreach ($expired as $d) {
        $result = vol_transaction($conn, function () use ($conn, $d, $days) {
            // The WHERE repeats the conditions: if someone claimed it a moment ago, 0 rows change.
            $changed = db_exec($conn,
                "UPDATE devices SET status = 'Returned Unclaimed', status_changed_at = NOW()
                  WHERE deviceID = ? AND status = 'Dropped Off' AND claimed_by IS NULL
                    AND DATEDIFF(NOW(), status_changed_at) > $days", 's', [$d['deviceID']]);
            if ($changed !== 1) return [false, ''];

            vol_log_status($conn, $d['deviceID'], null, 'Returned Unclaimed', 'Not claimed within ' . $days . ' days');   // NULL = the system
            notify($conn, $d['submitted_by'], 'Device not claimed',
                'No volunteer claimed your ' . $d['device_name'] . ' within ' . $days . ' days. Please collect it from the Green Hub.');
            return [true, ''];
        });
        if ($result[0]) $returned++;
    }
    return $returned;
}

/* Dropped-off devices nobody has claimed, oldest first. A volunteer never sees their own device. */
function vol_queue($conn, $me)
{
    $days = (int) VOL_QUEUE_DAYS;
    return db_all($conn,
        "SELECT d.deviceID, d.device_name, d.fault_description, d.photo_path, d.weight_kg,
                d.status_changed_at, c.category_name,
                $days - DATEDIFF(NOW(), d.status_changed_at) AS days_left
           FROM devices d JOIN categories c ON c.categoryID = d.categoryID
          WHERE d.status = 'Dropped Off' AND d.claimed_by IS NULL AND d.submitted_by <> ?
            AND DATEDIFF(NOW(), d.status_changed_at) <= $days
          ORDER BY d.status_changed_at ASC, d.deviceID ASC", 's', [$me]);
}

/*
 * CLAIM A DEVICE. Two volunteers may press "Claim" on the same device at the same moment.
 * Trick: ONE UPDATE that only works while the device is STILL unclaimed. MySQL runs the two
 * UPDATEs one after the other, so the second finds status = 'Claimed', changes 0 rows and
 * gets "Someone else claimed it first".
 * Returns [true|false, message].
 */
function vol_claim($conn, $device_id, $me)
{
    $days = (int) VOL_QUEUE_DAYS;
    return vol_transaction($conn, function () use ($conn, $device_id, $me, $days) {
        $changed = db_exec($conn,
            "UPDATE devices SET status = 'Claimed', claimed_by = ?, status_changed_at = NOW()
              WHERE deviceID = ? AND status = 'Dropped Off' AND claimed_by IS NULL AND submitted_by <> ?
                AND DATEDIFF(NOW(), status_changed_at) <= $days", 'sss', [$me, $device_id, $me]);
        if ($changed !== 1) {
            return [false, 'Someone else claimed it first, or it is no longer available.'];
        }
        $dev = db_one($conn, "SELECT submitted_by, device_name FROM devices WHERE deviceID = ?", 's', [$device_id]);
        vol_log_status($conn, $device_id, $me, 'Claimed', 'Claimed for repair');
        notify($conn, $dev['submitted_by'], 'Device claimed', 'A volunteer claimed your ' . $dev['device_name'] . '.');
        return [true, 'You claimed "' . $dev['device_name'] . '". Collect it from the Green Hub, then confirm receipt in My jobs.'];
    });
}

/* Numbers for the red badges on the navigation bar */
function vol_nav_counts($conn, $me)
{
    $days = (int) VOL_QUEUE_DAYS;
    return [
        'queue' => (int) db_value($conn,
            "SELECT COUNT(*) FROM devices WHERE status = 'Dropped Off' AND claimed_by IS NULL AND submitted_by <> ?
                AND DATEDIFF(NOW(), status_changed_at) <= $days", 's', [$me]),
        'jobs'  => (int) db_value($conn,
            "SELECT COUNT(*) FROM devices WHERE claimed_by = ? AND status IN ('Claimed', 'In Repair', 'Return Requested')", 's', [$me]),
    ];
}

/* =====================================================================
 * 3. MY JOBS  (flowchart Figure 17)
 *
 *   Claimed --(Confirm receipt)--> In Repair --(Mark as fixed)--> Ready for Collection
 *                                          \--(Can't be fixed)--> Pending Consent
 *   Return Requested --(Ready at counter)--> Returned Unrepaired
 *       (the owner chose "ask for it back" after "can't be fixed")
 *
 * "Repair notes" are rows in device_status_history that keep the status 'In Repair' and
 * carry the text in `remarks` (no extra table needed).
 * ===================================================================== */

/* The volunteer's open jobs: the ones that need an action from the volunteer */
function vol_my_jobs($conn, $me)
{
    return db_all($conn,
        "SELECT d.deviceID, d.device_name, d.fault_description, d.photo_path, d.weight_kg,
                d.status, d.status_changed_at, c.category_name, u.name AS owner_name
           FROM devices d
           JOIN categories c ON c.categoryID = d.categoryID
           JOIN users u ON u.tp_number = d.submitted_by
          WHERE d.claimed_by = ? AND d.status IN ('Claimed', 'In Repair', 'Return Requested')
          ORDER BY d.status_changed_at ASC, d.deviceID ASC", 's', [$me]);
}

/* All repair notes of one device, oldest first (the owner can read them too) */
function vol_job_notes($conn, $device_id)
{
    return db_all($conn,
        "SELECT remarks, changed_at FROM device_status_history
          WHERE deviceID = ? AND new_status = 'In Repair' AND remarks IS NOT NULL
          ORDER BY changed_at ASC, historyID ASC", 's', [$device_id]);
}

/*
 * Change the status of ONE job, safely. Same trick as vol_claim(): the UPDATE only works
 * if the device is still mine AND still in the status we expect ($from).
 * Returns [true, device name] or [false, message].
 */
function vol_change_status($conn, $device_id, $me, $from, $to, $remark, $notif_title, $notif_text)
{
    return vol_transaction($conn, function () use ($conn, $device_id, $me, $from, $to, $remark, $notif_title, $notif_text) {
        $changed = db_exec($conn,
            "UPDATE devices SET status = ?, status_changed_at = NOW()
              WHERE deviceID = ? AND claimed_by = ? AND status = ?", 'ssss', [$to, $device_id, $me, $from]);
        if ($changed !== 1) {
            return [false, 'This job is not available any more. Please refresh the page.'];
        }
        $dev = db_one($conn, "SELECT submitted_by, device_name, weight_kg FROM devices WHERE deviceID = ?", 's', [$device_id]);

        if ($to === 'In Repair' && $remark === null) {          // Confirm receipt writes the first note
            $remark = 'Received at the Green Hub.';
            if ($dev['weight_kg'] !== null) {
                $remark .= ' Weight ' . vol_kg($dev['weight_kg']) . '.';
            }
        }
        vol_log_status($conn, $device_id, $me, $to, $remark);
        notify($conn, $dev['submitted_by'], $notif_title, str_replace('{device}', $dev['device_name'], $notif_text));
        return [true, $dev['device_name']];
    });
}

/* Add a repair note (only while the device is In Repair and mine) */
function vol_add_note($conn, $device_id, $me, $note)
{
    // INSERT ... SELECT: the row is only created if the device is still mine and In Repair
    $id = next_id($conn, 'device_status_history', 'historyID', 'H');
    $added = db_exec($conn,
        "INSERT INTO device_status_history (historyID, deviceID, changed_by, new_status, remarks)
         SELECT ?, deviceID, ?, 'In Repair', ? FROM devices
          WHERE deviceID = ? AND claimed_by = ? AND status = 'In Repair'", 'sssss', [$id, $me, $note, $device_id, $me]);
    return $added === 1 ? [true, 'Note added. The owner can read it.']
                        : [false, 'You can only add notes to a device that is In Repair.'];
}

/* =====================================================================
 * 4. NUMBERS for Home, History, Report, Points, Challenge
 *    (kept here so every page shows the SAME numbers)
 * ===================================================================== */

/* Balance is never stored: it is the SUM of the points ledger. */
function vol_points_balance($conn, $me)
{
    return (int) db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE tp_number = ?", 's', [$me]);
}

/*
 * Counts for the volunteer's claimed devices.
 * $from / $to_exclusive (e.g. '2026-09-01 00:00:00', '2026-10-01 00:00:00') limit it to devices
 * CLAIMED in that period. Leave both null for "all time".
 */
function vol_stats($conn, $me, $from = null, $to_exclusive = null)
{
    $sql = "SELECT d.status, d.weight_kg FROM devices d WHERE d.claimed_by = ?";
    $types = 's'; $args = [$me];
    if ($from !== null && $to_exclusive !== null) {
        $sql .= " AND EXISTS (SELECT 1 FROM device_status_history h
                               WHERE h.deviceID = d.deviceID AND h.new_status = 'Claimed'
                                 AND h.changed_at >= ? AND h.changed_at < ?)";
        $types .= 'ss'; array_push($args, $from, $to_exclusive);
    }
    $r = ['claimed' => 0, 'fixed' => 0, 'recycled' => 0, 'notfixed' => 0, 'kg' => 0.0, 'rate' => null];
    foreach (db_all($conn, $sql, $types, $args) as $row) {
        $r['claimed']++;
        $r['kg'] += (float) $row['weight_kg'];
        if (in_array($row['status'], VOL_FIXED, true))    $r['fixed']++;
        if ($row['status'] === 'Recycled')                $r['recycled']++;
        if (in_array($row['status'], VOL_NOTFIXED, true)) $r['notfixed']++;
    }
    $done = $r['fixed'] + $r['notfixed'];
    $r['rate'] = $done > 0 ? (int) round($r['fixed'] * 100 / $done) : null;   // null = nothing finished yet
    return $r;
}

/* "Fixed this month" / "recycled this month" for the Home tiles (read from the status history) */
function vol_month_counts($conn, $me)
{
    $from = date('Y-m-01 00:00:00');
    $to   = date('Y-m-01 00:00:00', strtotime('first day of next month'));
    return [
        'fixed' => (int) db_value($conn,
            "SELECT COUNT(*) FROM device_status_history
              WHERE changed_by = ? AND new_status = 'Ready for Collection' AND changed_at >= ? AND changed_at < ?",
            'sss', [$me, $from, $to]),
        'recycled' => (int) db_value($conn,
            "SELECT COUNT(*) FROM device_status_history h JOIN devices d ON d.deviceID = h.deviceID
              WHERE d.claimed_by = ? AND h.new_status = 'Recycled' AND h.changed_at >= ? AND h.changed_at < ?",
            'sss', [$me, $from, $to]),
    ];
}

/* The ranking: every active volunteer with lifetime points EARNED (earn + bonus, never the redeem rows) */
function vol_leaderboard($conn)
{
    $rows = db_all($conn,
        "SELECT u.tp_number, u.name,
                COALESCE(SUM(CASE WHEN p.points > 0 THEN p.points ELSE 0 END), 0) AS earned
           FROM volunteers v
           JOIN users u ON u.tp_number = v.tp_number
           LEFT JOIN points_transactions p ON p.tp_number = u.tp_number
          WHERE u.is_active = 1
          GROUP BY u.tp_number, u.name
          ORDER BY earned DESC, u.name ASC");
    $rank = 0; $prev = null; $pos = 0;
    foreach ($rows as &$r) {
        $pos++;
        if ($prev === null || (int) $r['earned'] !== $prev) $rank = $pos;   // same points = same rank
        $r['rank'] = $rank;
        $prev = (int) $r['earned'];
    }
    return $rows;
}

/* The challenge to show: the one running today, otherwise the most recent one */
function vol_current_challenge($conn)
{
    $today = date('Y-m-d');
    $c = db_one($conn, "SELECT * FROM challenges WHERE start_date <= ? AND end_date >= ? ORDER BY start_date DESC LIMIT 1", 'ss', [$today, $today]);
    if ($c) { $c['active'] = true; return $c; }
    $c = db_one($conn, "SELECT * FROM challenges ORDER BY end_date DESC LIMIT 1");
    if ($c) { $c['active'] = false; return $c; }
    return null;
}

/* kg of finished devices (repaired = Completed, or Recycled) inside the challenge dates. $me = only my devices */
function vol_challenge_kg($conn, $ch, $me = null)
{
    $sql = "SELECT COALESCE(SUM(weight_kg), 0) FROM devices
             WHERE status IN ('Completed', 'Recycled') AND weight_kg IS NOT NULL
               AND DATE(status_changed_at) BETWEEN ? AND ?";
    $types = 'ss'; $args = [$ch['start_date'], $ch['end_date']];
    if ($me !== null) { $sql .= " AND claimed_by = ?"; $types .= 's'; $args[] = $me; }
    return (float) db_value($conn, $sql, $types, $args);
}

/* Reward tiers for the Points page, cheapest first */
function vol_rewards($conn)
{
    return db_all($conn, "SELECT rewardID, reward_name, description, points_required, cash_value, stock FROM rewards ORDER BY points_required ASC");
}

/*
 * Redeem a reward.
 * Danger: two clicks at once could spend the same points twice. Fix: lock this user's row first
 * (FOR UPDATE), so the second request waits and then sees the NEW balance.
 * Everything is one transaction: redemption + points deduction + stock, all or nothing.
 */
function vol_redeem($conn, $me, $reward_id)
{
    return vol_transaction($conn, function () use ($conn, $me, $reward_id) {
        db_one($conn, "SELECT tp_number FROM users WHERE tp_number = ? FOR UPDATE", 's', [$me]);

        $reward = db_one($conn, "SELECT rewardID, reward_name, points_required, cash_value FROM rewards WHERE rewardID = ?", 's', [$reward_id]);
        if (!$reward) {
            return [false, 'That reward does not exist.'];
        }
        if (vol_points_balance($conn, $me) < (int) $reward['points_required']) {
            return [false, 'You do not have enough points for ' . $reward['reward_name'] . '.'];
        }
        if (db_exec($conn, "UPDATE rewards SET stock = stock - 1 WHERE rewardID = ? AND stock > 0", 's', [$reward_id]) !== 1) {
            return [false, $reward['reward_name'] . ' is out of stock.'];
        }

        $red_id = next_id($conn, 'redemptions', 'redemptionID', 'R');
        db_exec($conn, "INSERT INTO redemptions (redemptionID, tp_number, rewardID, points_used, cash_value, status) VALUES (?, ?, ?, ?, ?, 'pending')",
            'sssid', [$red_id, $me, $reward_id, (int) $reward['points_required'], (float) $reward['cash_value']]);

        $txn_id = next_id($conn, 'points_transactions', 'transactionID', 'T');
        db_exec($conn, "INSERT INTO points_transactions (transactionID, tp_number, redemptionID, points, txn_type, description) VALUES (?, ?, ?, ?, 'redeem', ?)",
            'sssis', [$txn_id, $me, $red_id, -((int) $reward['points_required']), 'Redeemed ' . $reward['reward_name']]);

        // Same message the student side sends, so the admin sees one style of request
        $who = db_value($conn, "SELECT name FROM users WHERE tp_number = ?", 's', [$me]);
        notify_admins($conn, 'New cashback request',
            $who . ' redeemed ' . $reward['reward_name'] . ' (' . (int) $reward['points_required'] . ' points). Please pay it to the APCard.');

        return [true, $reward['reward_name'] . ' requested. It will be paid to your APCard after the admin approves it.'];
    });
}

/* Points ledger lines of one volunteer, newest first. Optional date window and limit. */
function vol_points_history($conn, $me, $from = null, $to_exclusive = null, $limit = 0)
{
    $sql = "SELECT created_at, description, txn_type, points FROM points_transactions WHERE tp_number = ?";
    $types = 's'; $args = [$me];
    if ($from !== null && $to_exclusive !== null) {
        $sql .= " AND created_at >= ? AND created_at < ?";
        $types .= 'ss'; array_push($args, $from, $to_exclusive);
    }
    $sql .= " ORDER BY created_at DESC, transactionID DESC";
    if ($limit > 0) $sql .= " LIMIT " . (int) $limit;
    return db_all($conn, $sql, $types, $args);
}
