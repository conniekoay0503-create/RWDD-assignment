<?php
/*
 * Deadline checks. XAMPP has no scheduler (cron), so require_role() runs this on every page
 * ("lazy check"). Each notification is only sent once.
 * The rule itself (which statuses, how many days) is in functions.php: overdue_sql(), DEADLINE_DAYS.
 *
 * Throttle: the checks only really run once every 5 minutes per logged-in user.
 * Checking on every page is the right idea, but one check every few minutes is enough
 * (a deadline is counted in days, so a few minutes late changes nothing).
 */
const DEADLINE_CHECK_SECONDS = 300;   // 5 minutes

function check_collection_deadlines($conn)
{
    if (time() - ($_SESSION['deadline_checked'] ?? 0) < DEADLINE_CHECK_SECONDS) {
        return;
    }
    $_SESSION['deadline_checked'] = time();

    // 1. Dropped off, but no volunteer claimed it in 7 days -> 'Returned Unclaimed' (written by the volunteer part)
    require_once __DIR__ . '/volunteer_logic.php';
    vol_return_expired($conn);
    vol_release_stale_claims($conn);   // Claimed but never picked up in 7 days -> back to the queue

    // 2. Waiting at the Green Hub counter for more than 7 days -> tell the owner, the admins and the officers (once)
    $devices = db_all($conn,
        "SELECT deviceID, device_name, submitted_by FROM devices WHERE " . overdue_sql(WAITING_FOR_OWNER));
    if ($devices) {
        $staff = db_all($conn,
            "SELECT u.tp_number FROM users u
              WHERE u.is_active = 1
                AND (u.tp_number IN (SELECT tp_number FROM admins) OR u.tp_number IN (SELECT tp_number FROM officers))");
        foreach ($devices as $d) {
            $recipients = array_unique(array_merge([$d['submitted_by']], array_column($staff, 'tp_number')));
            foreach ($recipients as $tp) {
                notify_once($conn, $tp, 'Overdue: ' . $d['deviceID'],
                    $d['device_name'] . ' has not been collected from the Green Hub.');
            }
        }
    }

    // 3. Someone must act but has not, for more than 7 days (WAITING_FOR_ACTION) -> remind that person (once)
    $devices = db_all($conn,
        "SELECT deviceID, device_name, status, submitted_by, claimed_by FROM devices WHERE " . overdue_sql(WAITING_FOR_ACTION));
    foreach ($devices as $d) {
        if ($d['status'] === 'Pending Consent') {
            // The owner must choose: recycle it, or ask for it back
            notify_once($conn, $d['submitted_by'], 'Decision needed: ' . $d['deviceID'],
                'Your ' . $d['device_name'] . ' could not be repaired. Please open it in My Devices and choose: recycle it, or ask for it back.');
        } elseif ($d['status'] === 'In Repair') {
            // The volunteer has had the device for more than 7 days: remind them (it is NOT taken away)
            notify_once($conn, $d['claimed_by'], 'Repair overdue: ' . $d['deviceID'],
                $d['device_name'] . ' has been in repair for more than ' . DEADLINE_DAYS . ' days. Please finish it, or mark it as "Can\'t be fixed".');
        } elseif ($d['claimed_by']) {
            // 'Return Requested': the volunteer must bring it back to the Green Hub counter
            notify_once($conn, $d['claimed_by'], 'Return overdue: ' . $d['deviceID'],
                'The owner asked for ' . $d['device_name'] . ' back. Please bring it to the Green Hub counter and press "It is ready at the counter" in My jobs.');
        }
    }
}

// Send a notification only if this person never got one with the same title
function notify_once($conn, $tp, $title, $message)
{
    $sent = db_value($conn, "SELECT COUNT(*) FROM notifications WHERE tp_number = ? AND title = ?", 'ss', [$tp, $title]);
    if (!$sent) {
        notify($conn, $tp, $title, $message);
    }
}
