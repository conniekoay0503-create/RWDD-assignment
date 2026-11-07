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

// An unclaimed device goes back to its owner after DEADLINE_DAYS (functions.php), the same 7 days as every other deadline.

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

/* CSRF: the shared csrf_field() / check_csrf() in functions.php protect every form (checked by require_role()). */

/* Device IDs look like 'D009'. Anything else typed into a form is rejected. */
function vol_valid_device_id($id)
{
    return is_string($id) && preg_match('/^D\d{3,7}$/', $id) === 1;
}

/* The badge colour class is status_class() in functions.php (shared by every role). */

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
 * so we roll back and pass the message on. Same begin / commit / rollback as the other team pages.
 */
function vol_transaction($conn, callable $work)
{
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
        throw $ex;
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
    $days = (int) DEADLINE_DAYS;   // our own constant, not user input, so it is safe inside the SQL text
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
    $days = (int) DEADLINE_DAYS;
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
    $days = (int) DEADLINE_DAYS;
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
    $days = (int) DEADLINE_DAYS;
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
function vol_change_status($conn, $device_id, $me, $from, $to, $remark, $notif_title, $notif_text, $weight = null)
{
    return vol_transaction($conn, function () use ($conn, $device_id, $me, $from, $to, $remark, $notif_title, $notif_text, $weight) {
        $changed = db_exec($conn,
            "UPDATE devices SET status = ?, status_changed_at = NOW()
              WHERE deviceID = ? AND claimed_by = ? AND status = ?", 'ssss', [$to, $device_id, $me, $from]);
        if ($changed !== 1) {
            return [false, 'This job is not available any more. Please refresh the page.'];
        }
        // Confirm receipt: the volunteer weighs the device at the Green Hub (the points are worked out from it)
        if ($weight !== null) {
            db_exec($conn, "UPDATE devices SET weight_kg = ? WHERE deviceID = ?", 'ds', [$weight, $device_id]);
        }
        $dev = db_one($conn,
            "SELECT d.submitted_by, d.device_name, d.weight_kg, c.multiplier, c.max_points
               FROM devices d JOIN categories c ON c.categoryID = d.categoryID WHERE d.deviceID = ?", 's', [$device_id]);

        if ($to === 'In Repair' && $remark === null) {          // Confirm receipt writes the first note
            $remark = 'Received at the Green Hub.';
            if ($dev['weight_kg'] !== null) {
                $remark .= ' Weight ' . vol_kg($dev['weight_kg']) . '.';
            }
        }
        vol_log_status($conn, $device_id, $me, $to, $remark);

        // Repaired: the VOLUNTEER earns x1 now, in the SAME transaction as the status change (the repair is done).
        // The OWNER earns x2 only when they collect it ("Confirm Collection" in student/device-details.php):
        // a device that is never collected (and is disposed of) earns the owner nothing, like the challenge rule.
        // The UNIQUE key (deviceID, tp_number) in points_transactions stops anyone being paid twice for one device.
        $text = str_replace('{device}', $dev['device_name'], $notif_text);
        if ($to === 'Ready for Collection') {
            $my_points = calc_points($dev['weight_kg'], $dev['multiplier'], $dev['max_points'], false);
            vol_add_points($conn, $me, $device_id, $my_points, 'Repaired ' . $dev['device_name']);
            $text .= ' Collect it from the Green Hub and press "Confirm Collection" to earn '
                   . calc_points($dev['weight_kg'], $dev['multiplier'], $dev['max_points'], true) . ' points.';
        }
        notify($conn, $dev['submitted_by'], $notif_title, $text);
        return [true, $dev['device_name']];
    });
}

/* One 'earn' row in the points ledger */
function vol_add_points($conn, $tp, $device_id, $points, $description)
{
    db_exec($conn,
        "INSERT INTO points_transactions (transactionID, tp_number, deviceID, points, txn_type, description) VALUES (?, ?, ?, ?, 'earn', ?)",
        'sssis', [next_id($conn, 'points_transactions', 'transactionID', 'T'), $tp, $device_id, $points, $description]);
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

/* The challenge kg rule is NOT here: it is challenge_kg() / challenge_summary() in functions.php (one rule for everyone). */

/* Reward tiers for the Points page, cheapest first */
function vol_rewards($conn)
{
    return db_all($conn, "SELECT rewardID, reward_name, description, points_required, cash_value, stock FROM rewards ORDER BY points_required ASC");
}

/* Redeem a reward: the shared redeem_reward() in functions.php (students use the same one). */

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
