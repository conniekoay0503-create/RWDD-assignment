
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

if (empty($_SESSION['followup_csrf']))
    $_SESSION['followup_csrf'] = bin2hex(random_bytes(32));

$csrf = $_SESSION['followup_csrf'];
$error = '';

$volunteers = db_all($conn, "
    SELECT u.tp_number, u.name
    FROM volunteers v
    JOIN users u ON v.tp_number = u.tp_number
    WHERE u.is_active = 1
    ORDER BY u.name
");

$where = "((d.status IN ('Dropped Off', 'Returned Unrepaired',
          'Ready for Collection')
          AND d.status_changed_at <= NOW() - INTERVAL 7 DAY)
          OR d.status = 'Returned Unclaimed')";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['deviceID'] ?? '';
    $tp = $_POST['volunteerTP'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        $error = 'Invalid request.';
    } else {
        $device = db_one($conn, "
            SELECT d.deviceID, d.device_name, d.status
            FROM devices d WHERE d.deviceID = ? AND $where
        ", 's', [$id]);

        $volunteer = db_one($conn, "
            SELECT v.tp_number
            FROM volunteers v
            JOIN users u ON v.tp_number = u.tp_number
            WHERE v.tp_number = ? AND u.is_active = 1
        ", 's', [$tp]);

        if (!$device || !$volunteer) {
            $error = 'Invalid device or Volunteer.';
        } else {
            $title = 'Device Follow-up: ' . $id;
            $message = "Please follow up on {$device['device_name']} ($id). "
                     . "Current status: {$device['status']}.";

            $duplicate = db_value($conn, "
                SELECT COUNT(*) FROM notifications
                WHERE tp_number = ? AND title = ?
                AND created_at >= NOW() - INTERVAL 1 DAY
            ", 'ss', [$tp, $title]);

            if ($duplicate) {
                $error = 'Volunteer already notified within 24 hours.';
            } else {
                try {
                    $next = db_value($conn, "
                        SELECT COALESCE(MAX(CAST(SUBSTRING(notificationID, 2)
                        AS UNSIGNED)), 0) + 1 FROM notifications
                    ");
                    $nid = 'N' . str_pad($next, 3, '0', STR_PAD_LEFT);

                    db_exec($conn, "
                        INSERT INTO notifications
                        (notificationID, tp_number, created_by,
                        title, message, is_read, created_at)
                        VALUES (?, ?, NULL, ?, ?, 0, NOW())
                    ", 'ssss', [$nid, $tp, $title, $message]);

                    set_flash('success', 'Volunteer notified successfully.');
                    redirect('abandoned_devices.php');
                } catch (Throwable $e) {
                    error_log($e->getMessage());                    
                    $error = 'Unable to send notification. Please try again.';
                }
            }
        }
    }
}

$devices = db_all($conn, "
    SELECT d.deviceID, d.device_name, d.status, d.recycle_consent,
           d.claimed_by, u.name AS student_name,
           DATE_ADD(d.status_changed_at, INTERVAL 7 DAY) AS deadline
    FROM devices d
    JOIN users u ON d.submitted_by = u.tp_number
    WHERE $where
    ORDER BY d.status_changed_at
");

$unclaimed = count(array_filter($devices, fn($d) =>
    in_array($d['status'], ['Dropped Off', 'Returned Unclaimed'])));
$uncollected = count($devices) - $unclaimed;

$page_title = 'Abandoned Devices';
$active = 'devices';
require '../includes/officer_top.php';
officer_page_head('clock', 'Abandoned Devices',
    'Monitor overdue devices and notify Volunteers for follow-up.');
?>

<?php if ($error): ?>
    <p class="muted" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<section class="stat-grid three">
    <?php foreach ([
        ['alert', 'tint-rose', count($devices), 'Total Flagged'],
        ['clock', 'tint-gold', $unclaimed, 'Unclaimed Repairs'],
        ['device', 'tint-mint', $uncollected, 'Uncollected Devices']
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
                    <th>Status</th><th>Deadline</th><th>Consent</th>
                    <th>Required Follow-up</th><th>Notify Volunteer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $d):
                    $action = match ($d['status']) {
                        'Dropped Off' => 'Review unclaimed repair',
                        'Returned Unclaimed' => 'Arrange device return',
                        default => 'Follow up on collection'
                    };
                ?>
                <tr class="device-row"
                    data-search="<?= e(strtolower(implode(' ', [
                        $d['deviceID'], $d['device_name'],
                        $d['student_name'], $d['status']
                    ]))) ?>">
                    <td><?= e($d['deviceID']) ?></td>
                    <td><?= e($d['device_name']) ?></td>
                    <td><?= e($d['student_name']) ?></td>
                    <td><span class="badge"><?= e($d['status']) ?></span></td>
                    <td><?= $d['status'] === 'Returned Unclaimed'
                        ? 'Deadline passed' : nice_date($d['deadline']) ?></td>
                    <td><span class="badge">
                        <?= (int)$d['recycle_consent'] === 1 ? 'Yes' : 'No' ?>
                    </span></td>
                    <td><?= e($action) ?></td>
                    <td>
                        <form method="post" data-confirm="Notify this Volunteer?">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="deviceID" value="<?= e($d['deviceID']) ?>">
                            <select name="volunteerTP" required>
                                <option value="">Select Volunteer</option>
                                <?php foreach ($volunteers as $v): ?>
                                    <option value="<?= e($v['tp_number']) ?>"
                                        <?= $v['tp_number'] === $d['claimed_by'] ? 'selected' : '' ?>>
                                        <?= e($v['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit">
                                <?= icon('bell') ?> Notify
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
    Volunteers handle overdue follow-ups. Officers send reminders
    and process approved recycling. No automatic status changes.
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
