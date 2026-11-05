<?php
/*
 * My jobs (flowchart Figure 17 + wireframe "My jobs")
 *
 *   1. Only a logged-in volunteer may enter.
 *   2. If a button was pressed (POST): check CSRF, read the action, do it, redirect.
 *   3. Show every device this volunteer must act on: Claimed, In Repair, Return Requested.
 *
 * The weight is recorded by the officer at the Green Hub (not typed here).
 */
require '../config/db.php';
require '../includes/functions.php';
require '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');

$me = $_SESSION['tp_number'];

// ---- step 2: a button was pressed ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = $_POST['device_id'] ?? '';
    $action    = $_POST['action'] ?? '';
    $note      = trim((string) ($_POST['note'] ?? ''));

    if (!vol_csrf_ok($_POST['csrf'] ?? null)) {
        set_flash('error', 'Session expired. Please try again.');
    } elseif (!vol_valid_device_id($device_id)) {
        set_flash('error', 'Invalid device.');
    } elseif (mb_strlen($note) > VOL_NOTE_MAX) {
        set_flash('error', 'The note is too long (maximum ' . VOL_NOTE_MAX . ' characters).');
    } else {
        try {
            if ($action === 'receive') {
                // Claimed -> In Repair (flowchart: "Repair started")
                [$ok, $r] = vol_change_status($conn, $device_id, $me, 'Claimed', 'In Repair', null,
                    'Repair started', 'A volunteer received your {device} and started the repair.');
                set_flash($ok ? 'success' : 'error', $ok ? 'Receipt confirmed for "' . $r . '". Repair started.' : $r);

            } elseif ($action === 'note') {
                if ($note === '') {
                    set_flash('error', 'Please write a note first.');   // flowchart: a note cannot be empty
                } else {
                    [$ok, $r] = vol_add_note($conn, $device_id, $me, $note);
                    set_flash($ok ? 'success' : 'error', $r);
                }

            } elseif ($action === 'fixed') {
                // The points are worked out from the weight, so the officer must have recorded it first
                $weight = db_value($conn, "SELECT weight_kg FROM devices WHERE deviceID = ? AND claimed_by = ?", 'ss', [$device_id, $me]);
                if ($weight === null) {
                    set_flash('error', 'The weight is not recorded yet. Ask the officer at the Green Hub to weigh the device first.');
                } else {
                    [$ok, $r] = vol_change_status($conn, $device_id, $me, 'In Repair', 'Ready for Collection',
                        $note !== '' ? $note : 'Repair finished',
                        'Ready for collection', 'Your {device} is repaired. Please collect it within 7 days.');
                    set_flash($ok ? 'success' : 'error', $ok ? '"' . $r . '" is marked as fixed. The owner was notified.' : $r);
                }

            } elseif ($action === 'cannot') {
                if ($note === '') {   // the owner must be told WHY
                    set_flash('error', 'Please explain why it cannot be fixed (the owner will read it).');
                } else {
                    [$ok, $r] = vol_change_status($conn, $device_id, $me, 'In Repair', 'Pending Consent', $note,
                        'Decision needed', 'Your {device} cannot be repaired. Please allow recycling or ask for it back.');
                    set_flash($ok ? 'success' : 'error', $ok ? '"' . $r . '" is marked as not fixable. The owner will decide.' : $r);
                }

            } elseif ($action === 'counter') {
                // The owner chose "ask for it back": Return Requested -> Returned Unrepaired
                [$ok, $r] = vol_change_status($conn, $device_id, $me, 'Return Requested', 'Returned Unrepaired',
                    'Ready at the Green Hub counter',
                    'Ready for collection', 'Your {device} is ready at the Green Hub counter.');
                set_flash($ok ? 'success' : 'error', $ok ? '"' . $r . '" is ready at the counter. The owner was notified.' : $r);

            } else {
                set_flash('error', 'Unknown action.');
            }
        } catch (Throwable $ex) {
            error_log('my_jobs failed: ' . $ex->getMessage());
            set_flash('error', 'Something went wrong. Please try again.');
        }
    }
    redirect('my_jobs.php');   // Post/Redirect/Get
}

// ---- step 3: load the jobs ----
$jobs = vol_my_jobs($conn, $me);

$page_title = 'My jobs';
$active = 'jobs';
require '../includes/volunteer_top.php';

page_head('wrench', 'My jobs', "Devices you've claimed. Only you can update them.",
    '<span class="badge badge-ok">' . count($jobs) . ' open</span>');
?>

<?php if (!$jobs): ?>
    <div class="card"><?php empty_state('check', 'No open jobs. Claim a device from the Repair queue to get started.', '<a class="btn btn-primary" href="repair_queue.php">Open repair queue</a>') ?></div>
<?php else: ?>
    <?php foreach ($jobs as $j):
        $status = $j['status'];
        $photo  = vol_photo_url($j['photo_path']);
        $notes  = $status === 'In Repair' ? vol_job_notes($conn, $j['deviceID']) : [];
        $dom    = e($j['deviceID']);
    ?>
        <article class="card v-job" id="job-<?= $dom ?>">
            <header class="v-job-head">
                <div>
                    <small class="muted"><?= $dom ?> · <?= e($j['category_name']) ?></small>
                    <h2><?= e($j['device_name']) ?></h2>
                    <p style="margin: 0"><?= e($j['fault_description']) ?></p>
                </div>
                <span class="badge <?= e(vol_status_class($status)) ?>"><?= e($status) ?></span>
            </header>

            <div class="v-job-body">
                <div>
                    <div class="v-thumb v-thumb-big">
                        <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="Photo of <?= e($j['device_name']) ?>" loading="lazy"><?php else: ?><?= icon('device') ?><?php endif; ?>
                    </div>
                    <small class="muted">Owner: <?= e($j['owner_name']) ?></small>
                </div>

                <div>
                <?php if ($status === 'Claimed'): ?>

                    <p class="hint"><?= icon('alert') ?> Collect it from the Green Hub, then confirm that you received it.</p>
                    <p class="muted">
                        <?= $j['weight_kg'] !== null ? 'Weight recorded by the officer: <strong>' . e(vol_kg($j['weight_kg'])) . '</strong>'
                                                     : 'Weight not recorded yet. The officer at the Green Hub records it, and it is used for the points.' ?>
                    </p>
                    <form method="post" action="my_jobs.php">
                        <?= vol_csrf_field() ?>
                        <input type="hidden" name="device_id" value="<?= $dom ?>">
                        <input type="hidden" name="action" value="receive">
                        <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> Confirm receipt &amp; start repair</button>
                    </form>

                <?php elseif ($status === 'In Repair'): ?>

                    <h3>Repair notes</h3>
                    <?php if (!$notes): ?><p class="muted">No notes yet.</p><?php endif; ?>
                    <?php foreach ($notes as $n): ?>
                        <div class="v-note">
                            <p><?= e($n['remarks']) ?></p>
                            <small class="muted"><?= e(date('j M \a\t H:i', strtotime($n['changed_at']))) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <p class="muted">
                        <?= $j['weight_kg'] !== null ? 'Weight: <strong>' . e(vol_kg($j['weight_kg'])) . '</strong>'
                                                     : 'Weight not recorded yet. It is needed before you can mark the device as fixed.' ?>
                    </p>

                    <form method="post" action="my_jobs.php" class="v-note-form">
                        <?= vol_csrf_field() ?>
                        <input type="hidden" name="device_id" value="<?= $dom ?>">
                        <div class="field">
                            <label for="note-<?= $dom ?>">New note</label>
                            <textarea id="note-<?= $dom ?>" name="note" rows="3" maxlength="<?= VOL_NOTE_MAX ?>"
                                      placeholder="What did you find out or try? The owner can read this."></textarea>
                        </div>
                        <div class="btn-row" style="margin-top: 0">
                            <button type="submit" name="action" value="note" class="btn btn-outline"><?= icon('edit') ?> Add note</button>
                            <button type="submit" name="action" value="fixed" class="btn btn-primary"
                                    onclick="return confirm('Mark this device as fixed? The owner will be told to collect it.');"><?= icon('check') ?> Mark as fixed</button>
                            <button type="submit" name="action" value="cannot" class="btn btn-danger"
                                    onclick="return confirm('Mark as NOT fixable? Your note is sent to the owner as the reason.');"><?= icon('x') ?> Can't be fixed</button>
                        </div>
                        <small class="muted">"Can't be fixed" needs a note that explains why.</small>
                    </form>

                <?php else: /* Return Requested */ ?>

                    <p class="hint"><?= icon('alert') ?> The owner asked for this device back without recycling. Put it at the Green Hub counter, then press the button.</p>
                    <form method="post" action="my_jobs.php">
                        <?= vol_csrf_field() ?>
                        <input type="hidden" name="device_id" value="<?= $dom ?>">
                        <input type="hidden" name="action" value="counter">
                        <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> It is ready at the counter</button>
                    </form>

                <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require '../includes/volunteer_bottom.php'; ?>
