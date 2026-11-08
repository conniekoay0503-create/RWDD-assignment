<?php

require '../config/db.php'; // to connect database , get $conn
require '../includes/functions.php'; 
require_once '../includes/volunteer_logic.php'; 
require_role($conn, 'volunteer', '../');   // not volunteer kick 

$me = $_SESSION['tp_number'];//record who am i during log in
// (devices not claimed in 7 days are returned by require_role() -> check_collection_deadlines())

// the volunteer pressed Claim 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = $_POST['device_id'] ?? '';
    // (the CSRF token - a random password inside every form - is already checked by require_role())
    if (!vol_valid_device_id($device_id)) {        // if form or style not correct will display invalid device 
        set_flash('error', 'Invalid device.');
    } else {
        try {
            [$ok, $message] = vol_claim($conn, $device_id, $me);
            set_flash($ok ? 'success' : 'error', $message);
        } catch (Throwable $ex) {
            error_log('vol_claim failed: ' . $ex->getMessage());
            set_flash('error', 'Something went wrong. Please try again.');
        }
    }
    redirect('repair_queue.php');   // Post/Redirect/Get: pressing F5 cannot claim twice
}

// ---- step 4: load the queue ----
$devices = vol_queue($conn, $me);

$page_title = 'Repair queue';
$active = 'queue';
require '../includes/volunteer_top.php';

page_head('inbox', 'Repair queue',
    'Oldest first. Unclaimed devices go back to their owner after ' . DEADLINE_DAYS . ' days.',
    '<span class="badge badge-ok">' . count($devices) . ' waiting</span>');
?>

<?php if (!$devices): ?>
    <div class="card"><?php empty_state('check', 'No devices awaiting repair. New drop-offs at the Green Hub will show up here.') ?></div>
<?php else: ?>
    <div class="tile-grid">
        <?php foreach ($devices as $d):
            $left  = (int) $d['days_left'];
            $badge = $left <= 1 ? 'badge-urgent' : ($left <= 3 ? 'badge-warn' : 'badge-ok');
            $photo = vol_photo_url($d['photo_path']);
        ?>
            <article class="v-card">
                <div class="v-device-top">
                    <div class="v-thumb">
                        <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="Photo of <?= e($d['device_name']) ?>" loading="lazy"><?php else: ?><?= icon('device') ?><?php endif; ?>
                    </div>
                    <div>
                        <h2><?= e($d['device_name']) ?></h2>
                        <small class="muted"><?= e($d['deviceID']) ?> · <?= e($d['category_name']) ?></small>
                    </div>
                </div>
                <span class="badge <?= $badge ?>"><?= icon('clock') ?> <?= e(vol_days_left_text($left)) ?></span>
                <p class="m-0"><?= e($d['fault_description']) ?></p>
                <footer>
                    <small class="muted"><?= icon('calendar') ?> Dropped off <?= e(date('j M', strtotime($d['status_changed_at']))) ?></small>
                    <form method="post" action="repair_queue.php" data-confirm="Claim this device? You must collect it from the Green Hub." class="ml-auto">
                        <?= csrf_field() ?>
                        <input type="hidden" name="device_id" value="<?= e($d['deviceID']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Claim</button>
                    </form>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require '../includes/bottom.php'; ?>
