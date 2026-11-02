
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// Get device ID as a string, e.g. D006
$deviceID = trim($_GET['deviceID'] ?? '');

if ($deviceID === '') {
    http_response_code(400);
    exit('No device selected.');
}

// Fetch the selected device and category settings
$stmt = $conn->prepare(
    "SELECT d.deviceID, d.device_name,
            d.weight_kg, d.status,
            d.recycle_consent,
            c.category_name,
            c.multiplier, c.max_points
     FROM devices d
     JOIN categories c
       ON d.categoryID = c.categoryID
     WHERE d.deviceID = ?
     LIMIT 1"
);

$stmt->bind_param('s', $deviceID);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    http_response_code(404);
    exit('Device not found.');
}

// Only eligible devices can be processed
if (
    $device['status'] !== 'Awaiting Recycle' ||
    (int)$device['recycle_consent'] !== 1
) {
    http_response_code(403);
    exit('This device is not eligible for recycling.');
}

// Exact disposal methods from your database
$methods = [
    'Certified e-waste recycler',
    'Battery take-back programme',
    'Metal recovery',
    'Harvest parts for repair stock'
];

// Generate CSRF token for secure form submission
if (empty($_SESSION['recycle_csrf'])) {
    $_SESSION['recycle_csrf'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Process Recycling</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<style>
.process-container {
    max-width: 750px;
    margin: 40px auto;
    padding: 20px;
}

.process-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 12px;
    padding: 30px;
}

.form-group {
    margin: 20px 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    box-sizing: border-box;
}

.points-preview {
    background: #eaf5ee;
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}

.points-preview h2 {
    color: #247a53;
    margin: 8px 0;
}

.process-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.process-note {
    color: #666;
    font-size: 13px;
    margin-top: 15px;
}
</style>
</head>

<body class="public">
<main class="process-container">

    <a href="recycling_queue.php">
        Back to Recycling Queue
    </a>

    <h1>Process Recycling</h1>

    <div class="process-card">

        <h2><?= e($device['device_name']) ?></h2>

        <p>
            Device ID:
            <?= e($device['deviceID']) ?>
        </p>

        <p>
            Category:
            <?= e($device['category_name']) ?>
        </p>

        <p>
            Status:
            <?= e($device['status']) ?>
        </p>

        <!-- Recycling confirmation form -->
        <form id="recyclingForm"
              action="confirm_recycling.php"
              method="POST">

            <!-- CSRF security token -->
            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['recycle_csrf']) ?>">

            <!-- Device ID -->
            <input type="hidden"
                   name="deviceID"
                   value="<?= e($device['deviceID']) ?>">

            <!-- Device weight -->
            <div class="form-group">
                <label for="weight">
                    Actual Device Weight (kg)
                </label>

                <input type="number"
                       id="weight"
                       name="weight_kg"
                       min="0.01"
                       max="50"
                       step="0.01"
                       value="<?= e($device['weight_kg'] ?? '') ?>"
                       placeholder="e.g. 2.50"
                       required>
            </div>

            <!-- Disposal method -->
            <div class="form-group">
                <label for="method">
                    Disposal Method
                </label>

                <select id="method"
                        name="disposal_method"
                        required>

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

            <!-- Points preview -->
            <div class="points-preview">

                <p>Estimated Student Points</p>

                <h2 id="pointsResult">
                    0 points
                </h2>

                <p>
                    Category Multiplier:
                    <?= e($device['multiplier']) ?>
                </p>

                <p>
                    Maximum Points:
                    <?= e($device['max_points']) ?>
                </p>

            </div>

            <!-- Action buttons -->
            <div class="process-actions">

                <button type="submit"
                        id="confirmButton"
                        class="btn btn-primary">
                    Confirm Recycling
                </button>

                <a href="recycling_queue.php"
                   class="btn">
                    Cancel
                </a>

            </div>

            <p class="process-note">
                Confirming recycling will update the
                device status, record its weight and
                disposal method, award student points,
                and create a notification.
            </p>

        </form>

    </div>
</main>

<script>
// Get form elements
const recyclingForm =
    document.getElementById('recyclingForm');

const weightInput =
    document.getElementById('weight');

const pointsResult =
    document.getElementById('pointsResult');

const confirmButton =
    document.getElementById('confirmButton');

// Values from the database
const multiplier = <?= json_encode(
    (float)$device['multiplier']
) ?>;

const maxPoints = <?= json_encode(
    (int)$device['max_points']
) ?>;

// Calculate estimated points
function calculatePoints() {
    const weight = Number(weightInput.value);

    if (
        !Number.isFinite(weight) ||
        weight <= 0 ||
        weight > 50
    ) {
        pointsResult.textContent = '0 points';
        return;
    }

    const points = Math.round(
        Math.min(weight * multiplier, maxPoints)
    );

    pointsResult.textContent =
        points.toLocaleString('en-MY') + ' points';
}

// Update points when weight changes
weightInput.addEventListener(
    'input',
    calculatePoints
);

// Calculate initial preview
calculatePoints();

// Confirm before submitting
recyclingForm.addEventListener('submit', function(event) {

    if (!recyclingForm.checkValidity()) {
        return;
    }

    const confirmed = confirm(
        'Are you sure you want to recycle this device?\n\n' +
        'Device: <?= e($device['deviceID']) ?>\n' +
        'Weight: ' + weightInput.value + ' kg\n' +
        'Points: ' + pointsResult.textContent + '\n\n' +
        'This will update the database and award points.'
    );

    if (!confirmed) {
        event.preventDefault();
        return;
    }

    // Prevent accidental double-click submissions
    confirmButton.disabled = true;
    confirmButton.textContent = 'Processing...';
});

</script>

</body>
</html>
