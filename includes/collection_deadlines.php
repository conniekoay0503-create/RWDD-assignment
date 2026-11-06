
<?php

function check_collection_deadlines($conn)
{
    $devices = db_all($conn,
        "SELECT deviceID, device_name, submitted_by
         FROM devices
         WHERE status IN (
             'Ready for Collection',
             'Returned Unrepaired',
             'Returned Unclaimed'
         )
         AND status_changed_at < NOW() - INTERVAL 7 DAY"
    );

    $staff = db_all($conn,
        "SELECT u.tp_number FROM users u
         WHERE u.is_active = 1
         AND (
             u.tp_number IN (SELECT tp_number FROM admins)
             OR u.tp_number IN (SELECT tp_number FROM officers)
         )"
    );

    foreach ($devices as $d) {
        $title = 'Overdue: ' . $d['deviceID'];
        $message = $d['device_name'] .
            ' has not been collected from Green Hub.';

        $recipients = array_merge(
            [$d['submitted_by']],
            array_column($staff, 'tp_number')
        );

        foreach (array_unique($recipients) as $tp) {
            $exists = db_value($conn,
                "SELECT COUNT(*) FROM notifications
                 WHERE tp_number = ? AND title = ?",
                'ss', [$tp, $title]
            );

            if (!$exists) {
                notify($conn, $tp, $title, $message);
            }
        }
    }
}
