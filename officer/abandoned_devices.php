<?php
/*
 * Abandoned devices: waiting at the Green Hub counter for more than 7 days.
 * The rule is the same on every page: overdue_sql(WAITING_FOR_OWNER) in functions.php.
 * (A device nobody claimed in 7 days is turned into 'Returned Unclaimed' automatically by require_role().)
 *
 * Two actions:
 *   remind  - send the OWNER a reminder to collect the device (at most once a day per device).
 *             The owner is the one who must come, so the reminder goes to the owner, not to a volunteer.
 *   dispose - the owner never came: the officer disposes of it (status 'Disposed').
 *             Allowed only for devices in this list, so a 'Return Requested' device can never be disposed:
 *             that owner has asked for it back.
 * The CSRF token is checked by require_role().
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

$me = $_SESSION['tp_number'];
$error = '';
$where = overdue_sql(WAITING_FOR_OWNER, 'd.');
$methods = ['Certified e-waste recycler', 'Battery take-back programme', 'Metal recovery', 'Harvest parts for repair stock'];
$remind_title = 'Collection reminder: ';   // + deviceID. The same title is used to find the last reminder.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['deviceID'] ?? '';

    // The device must still be in the overdue list (checked again on the server, not only in the browser)
    $device = db_one($conn, "
        SELECT d.deviceID, d.device_name, d.status, d.submitted_by
        FROM devices d WHERE d.deviceID = ? AND $where
    ", 's', [$id]);

    if (!$device) {
        $error = 'This device is not in the overdue list any more.';

    } elseif ($action === 'remind') {
        $owner = $device['submitted_by'];
        $title = $remind_title . $id;

        // Only one reminder per device every 24 hours, so the owner is not spammed
        $duplicate = db_value($conn, "
            SELECT COUNT(*) FROM notifications
            WHERE tp_number = ? AND title = ?
            AND created_at >= NOW() - INTERVAL 1 DAY
        ", 'ss', [$owner, $title]);

        if ($duplicate) {
            $error = 'The owner was already reminded in the last 24 hours.';
        } else {
            notify($conn, $owner, $title, "Please collect your {$device['device_name']} ($id) from the Green Hub. "
                . "It is past the " . DEADLINE_DAYS . "-day collection deadline. If it is not collected, it may be disposed of.");
            set_flash('success', 'Reminder sent to the owner.');
            redirect('abandoned_devices.php');
        }

    } elseif ($action === 'dispose') {
        $method = $_POST['disposal_method'] ?? '';
        if (!in_array($method, $methods, true)) {
            $error = 'Please choose a disposal method.';
        } else {
            $conn->begin_transaction();
            try {
                // The WHERE repeats the overdue rule: if the owner collected it a moment ago, 0 rows change
                $n = db_exec($conn, "
                    UPDATE devices d SET d.status = 'Disposed', d.disposal_method = ?, d.processed_by = ?, d.status_changed_at = NOW()
                    WHERE d.deviceID = ? AND $where
                ", 'sss', [$method, $me, $id]);
                if ($n !== 1) {
                    throw new RuntimeException('This device is not in the overdue list any more.');
                }
                db_exec($conn, "INSERT INTO device_status_history (historyID, deviceID, changed_by, new_status, remarks) VALUES (?, ?, ?, 'Disposed', ?)",
                    'ssss', [next_id($conn, 'device_status_history', 'historyID', 'H'), $id, $me,
                             'Not collected within ' . DEADLINE_DAYS . ' days; method: ' . $method]);
                notify($conn, $device['submitted_by'], 'Device disposed',
                    'Your ' . $device['device_name'] . ' was not collected within ' . DEADLINE_DAYS . ' days, so it has been disposed of (' . $method . ').');
                $conn->commit();
                set_flash('success', $device['device_name'] . ' has been disposed of. The owner was notified.');
                redirect('abandoned_devices.php');
            } catch (Throwable $e) {
                $conn->rollback();
                error_log($e->getMessage());
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to dispose of the device. Please try again.';
            }
        }

    } else {
        $error = 'Unknown action.';
    }
}

// last_reminded = when the officer last pressed "Remind owner" for this device (NULL = never)
$devices = db_all($conn, "
    SELECT d.deviceID, d.device_name, d.status, d.recycle_consent,
           d.claimed_by, u.name AS student_name,
           DATE_ADD(d.status_changed_at, INTERVAL " . DEADLINE_DAYS . " DAY) AS deadline,
           (SELECT MAX(n.created_at) FROM notifications n
             WHERE n.tp_number = d.submitted_by AND n.title = CONCAT(?, d.deviceID)) AS last_reminded
    FROM devices d
    JOIN users u ON d.submitted_by = u.tp_number
    WHERE $where
    ORDER BY d.status_changed_at
", 's', [$remind_title]);

$unclaimed = count(array_filter($devices, fn($d) => $d['status'] === 'Returned Unclaimed'));
$uncollected = count($devices) - $unclaimed;

$page_title = 'Abandoned Devices';
$active = 'devices';
require '../includes/officer_top.php';
officer_page_head('clock', 'Abandoned Devices',
    'Devices waiting at the Green Hub for more than ' . DEADLINE_DAYS . ' days. Remind the owner, or dispose of the device.');
?>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" data-flash="error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<section class="stat-grid three">
    <?php foreach ([
        ['alert', 'tint-rose', count($devices), 'Total Flagged'],
        ['clock', 'tint-gold', $unclaimed, 'Never Claimed'],
        ['device', 'tint-mint', $uncollected, 'Not Collected']
    ] as [$ico, $tint, $value, $label]): ?>
        <div class="stat-card">
            <span class="stat-icon <?= $tint ?>"><?= icon($ico) ?></span>
            <strong class="stat-value"><?= $value ?></strong>
            <span class="stat-title"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= icon('list') ?> Devices Requiring Follow-up</h2>
        <label class="search-box">
            <?= icon('search') ?>
            <input id="search" type="search" placeholder="Search devices...">
        </label>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Device</th><th>Student</th>
                    <th>Status</th><th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $d): ?>
                <tr class="device-row"
                    data-search="<?= e(strtolower(implode(' ', [
                        $d['deviceID'], $d['device_name'],
                        $d['student_name'], $d['status']
                    ]))) ?>">
                    <td><?= e($d['deviceID']) ?></td>
                    <td><?= e($d['device_name']) ?></td>
                    <td><?= e($d['student_name']) ?></td>
                    <td><span class="badge <?= e('status-' . strtolower(str_replace(' ', '-', $d['status']))) ?>"><?= e($d['status']) ?></span></td>
                    <td>Passed (<?= nice_date($d['deadline']) ?>)</td>
                    <td class="action-forms">
                        <!-- Each action = one line: choose from the list, then press the button -->
                        <form method="post" data-confirm="Send <?= e($d['student_name']) ?> a reminder to collect <?= e($d['device_name']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remind">
                            <input type="hidden" name="deviceID" value="<?= e($d['deviceID']) ?>">
                            <!-- Where the other line has its list, this line shows when the owner was last reminded -->
                            <small class="remind-note muted">
                                <?= $d['last_reminded'] ? 'Last reminded ' . e(nice_date($d['last_reminded'], true)) : 'Not reminded yet' ?>
                            </small>
                            <button class="btn btn-primary" type="submit">
                                <?= icon('bell') ?> Remind owner
                            </button>
                        </form>
                        <form method="post" data-confirm="Dispose of <?= e($d['device_name']) ?>? The owner did not collect it. This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="dispose">
                            <input type="hidden" name="deviceID" value="<?= e($d['deviceID']) ?>">
                            <select name="disposal_method" required>
                                <option value="">Select method</option>
                                <?php foreach ($methods as $m): ?>
                                    <option value="<?= e($m) ?>"><?= e($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-danger" type="submit">
                                <?= icon('trash') ?> Dispose
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$devices): ?>
        <p class="muted">No devices require follow-up. All caught up!</p>
    <?php endif; ?>
    <p id="empty" class="muted" hidden>No matching devices.</p>
</section>

<p class="muted">
    A device whose owner asked for it back ("Return Requested") never appears here, so it can never be disposed of.
</p>

<script>
const search = document.getElementById('search');
const rows = document.querySelectorAll('.device-row');
search.addEventListener('input', () => {
    let found = 0;
    rows.forEach(row => {
        const show = row.dataset.search.includes(search.value.toLowerCase().trim());
        row.hidden = !show;
        if (show) found++;
    });
    document.getElementById('empty').hidden = found > 0 || !rows.length;
});
</script>

<?php require '../includes/officer_bottom.php'; ?>
