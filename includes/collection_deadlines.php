<?php
/*
 * Deadline checks. XAMPP has no scheduler (cron), so require_role() runs this on every page
 * ("lazy check"). It is cheap: a few small SELECTs, and each notification is only sent once.
 * The rule itself (which statuses, how many days) is in functions.php: overdue_sql(), DEADLINE_DAYS.
 */
function check_collection_deadlines($conn)
{
    // 1. Dropped off, but no volunteer claimed it in 7 days -> 'Returned Unclaimed' (written by the volunteer part)
    require_once __DIR__ . '/volunteer_logic.php';
    vol_return_expired($conn);

    // 2. Waiting at the Green Hub counter for more than 7 days -> tell the owner, the admins and the officers (once)
    $devices = db_all($conn,
        "SELECT deviceID, device_name, submitted_by FROM devices WHERE " . overdue_sql(WAITING_FOR_OWNER));
    if (!$devices) return;

    $staff = db_all($conn,
        "SELECT u.tp_number FROM users u
          WHERE u.is_active = 1
            AND (u.tp_number IN (SELECT tp_number FROM admins) OR u.tp_number IN (SELECT tp_number FROM officers))");

    foreach ($devices as $d) {
        $title = 'Overdue: ' . $d['deviceID'];
        $message = $d['device_name'] . ' has not been collected from the Green Hub.';
        $recipients = array_unique(array_merge([$d['submitted_by']], array_column($staff, 'tp_number')));
        foreach ($recipients as $tp) {
            $sent = db_value($conn, "SELECT COUNT(*) FROM notifications WHERE tp_number = ? AND title = ?", 'ss', [$tp, $title]);
            if (!$sent) {
                notify($conn, $tp, $title, $message);
            }
        }
    }
}
