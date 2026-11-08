<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// get all my submitted devices
$devices = db_all($conn, "SELECT d.*, c.category_name FROM devices d
    JOIN categories c ON d.categoryID = c.categoryID
    WHERE d.submitted_by = ? ORDER BY d.submitted_at DESC", 's', [$tp]);

$page_title = 'My Devices';
require '../includes/student_top.php';
page_head('device', 'My Devices', 'Track every device you have submitted.',
    '<a class="btn btn-primary" href="submit-device.php">' . icon('plus') . ' Submit Device</a>');
?>

<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Device</th><th>Category</th><th>Status</th><th>What next</th><th></th></tr></thead>
        <tbody>
        <?php if (!$devices) empty_row(5, 'device', 'No devices submitted yet. Press "Submit Device" to start.'); ?>
        <?php foreach ($devices as $d):
            $status = $d['status'];
            $message = '-';
            $late = false;

            // Same deadline rule as the rest of the website (WAITING_FOR_OWNER and DEADLINE_DAYS in functions.php)
            if (in_array($status, WAITING_FOR_OWNER, true)) {
                $due = strtotime($d['status_changed_at'] . ' +' . DEADLINE_DAYS . ' days');
                $late = time() > $due;
                $message = $late ? 'Overdue - collect at the Green Hub now' : 'Collect by ' . date('d M Y', $due);
            } elseif ($status === 'Submitted') {
                $message = 'Bring it to the Green Hub, then press "Mark as dropped off"';
            } elseif ($status === 'Pending Consent') {
                $message = 'Choose: recycle it or get it back';
            }
        ?>
            <tr>
                <td><strong><?= e($d['device_name']) ?></strong><small class="muted block"><?= e($d['deviceID']) ?></small></td>
                <td><?= e($d['category_name']) ?></td>
                <td><span class="badge <?= e(status_class($status)) ?>"><?= e($status) ?></span></td>
                <td class="<?= $late ? 'text-danger' : 'muted' ?>"><?= e($message) ?></td>
                <td><a class="btn btn-outline btn-sm" href="device-details.php?id=<?= urlencode($d['deviceID']) ?>"><?= icon('eye') ?> View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<?php require '../includes/bottom.php'; ?>
