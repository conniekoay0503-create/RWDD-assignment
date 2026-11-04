
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

$deviceID = trim($_GET['deviceID'] ?? '');
if ($deviceID === '') {
    http_response_code(400);
    exit('No device selected.');
}

$device = db_one($conn, "
    SELECT d.deviceID, d.device_name, d.weight_kg,
           d.status, d.recycle_consent,
           c.category_name, c.multiplier, c.max_points
    FROM devices d
    JOIN categories c ON d.categoryID = c.categoryID
    WHERE d.deviceID = ?
    LIMIT 1
", 's', [$deviceID]);

if (!$device) {
    http_response_code(404);
    exit('Device not found.');
}

if ($device['status'] !== 'Awaiting Recycle' ||
    (int)$device['recycle_consent'] !== 1) {
    http_response_code(403);
    exit('This device is not eligible for recycling.');
}

$methods = [
    'Certified e-waste recycler',
    'Battery take-back programme',
    'Metal recovery',
    'Harvest parts for repair stock'
];

if (empty($_SESSION['recycle_csrf'])) {
    $_SESSION['recycle_csrf'] = bin2hex(random_bytes(32));
}

$page_title = 'Process Recycling';
$active = 'recycling';
require '../includes/officer_top.php';
?>

<?php
officer_page_head(
    'recycle',
    'Process Recycling',
    'Verify the device information and confirm recycling.',
    '<a href="recycling_queue.php" class="btn btn-outline">'
    . icon('arrow-left') . ' Back to Queue</a>'
);
?>

<div class="two-col">

    <!-- DEVICE DETAILS -->
    <section class="card">
        <h2 class="card-title">
            <?= icon('device') ?> Device Information
        </h2>

        <h3><?= e($device['device_name']) ?></h3>

        <p>
            <strong>Device ID:</strong>
            <?= e($device['deviceID']) ?>
        </p>

        <p>
            <strong>Category:</strong>
            <?= e($device['category_name']) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <span class="badge status-awaiting-recycle">
                <?= e($device['status']) ?>
            </span>
        </p>

        <p>
            <strong>Category Multiplier:</strong>
            <?= e($device['multiplier']) ?>
        </p>

        <p>
            <strong>Maximum Points:</strong>
            <?= e($device['max_points']) ?>
        </p>
    </section>

    <!-- RECYCLING FORM -->
    <section class="card">
        <h2 class="card-title">
            <?= icon('recycle') ?> Recycling Details
        </h2>

        <form id="recyclingForm"
              action="confirm_recycling.php"
              method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['recycle_csrf']) ?>">

            <input type="hidden" name="deviceID"
                   value="<?= e($device['deviceID']) ?>">

            <div class="form-group">
                <label for="weight">
                    Actual Device Weight (kg)
                </label>

                <input type="number" id="weight"
                       name="weight_kg"
                       min="0.01" max="50" step="0.01"
                       value="<?= e($device['weight_kg'] ?? '') ?>"
                       placeholder="e.g. 2.50" required>
            </div>

            <div class="form-group">
                <label for="method">Disposal Method</label>

                <select id="method"
                        name="disposal_method" required>
                    <option value="">
                        Select disposal method
                    </option>

                    <?php foreach ($methods as $method): ?>
                        <option value="<?= e($method) ?>">
                            <?= e($method) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="card" style="margin:20px 0;">
                <p class="muted">Estimated Student Points</p>

                <h2 id="pointsResult">0 points</h2>

                <small class="muted">
                    Weight × <?= e($device['multiplier']) ?>
                    (maximum <?= e($device['max_points']) ?> points)
                </small>
            </div>

            <div class="hero-actions">
                <button type="submit" id="confirmButton"
                        class="btn btn-primary">
                    <?= icon('check') ?> Confirm Recycling
                </button>

                <a href="recycling_queue.php"
                   class="btn btn-outline">
                    Cancel
                </a>
            </div>

            <p class="muted" style="margin-top:16px;">
                Confirming will update the device status,
                record the weight and disposal method,
                award student points, and send a notification.
            </p>
        </form>
    </section>

</div>

<script>
const form = document.getElementById('recyclingForm');
const weight = document.getElementById('weight');
const result = document.getElementById('pointsResult');
const button = document.getElementById('confirmButton');

const multiplier = <?= json_encode((float)$device['multiplier']) ?>;
const maxPoints = <?= json_encode((int)$device['max_points']) ?>;
const deviceID = <?= json_encode($device['deviceID']) ?>;

function calculatePoints() {
    const kg = Number(weight.value);

    if (!weight.value || !Number.isFinite(kg) ||
        kg <= 0 || kg > 50) {
        result.textContent = '0 points';
        return;
    }

    const points = Math.min(
        Math.round(kg * multiplier),
        maxPoints
    );

    result.textContent =
        points.toLocaleString('en-MY') + ' points';
}

weight.addEventListener('input', calculatePoints);
calculatePoints();

form.addEventListener('submit', function(event) {
    if (!form.checkValidity()) return;

    const confirmed = confirm(
        'Confirm recycling?\n\n' +
        'Device: ' + deviceID + '\n' +
        'Weight: ' + weight.value + ' kg\n' +
        'Points: ' + result.textContent + '\n\n' +
        'This action will award student points.'
    );

    if (!confirmed) {
        event.preventDefault();
        return;
    }

    button.disabled = true;
    button.textContent = 'Processing...';
});
</script>

<?php require '../includes/officer_bottom.php'; ?>
