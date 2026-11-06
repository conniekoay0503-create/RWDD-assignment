
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];

// count my devices and their total weight
$devices = db_one($conn, "SELECT
    COUNT(*) AS total,
    SUM(status = 'Completed') AS repaired,
    SUM(status = 'Recycled') AS recycled,
    COALESCE(SUM(CASE WHEN status IN ('Completed','Recycled')
        THEN weight_kg ELSE 0 END),0) AS weight
    FROM devices WHERE submitted_by = ?", 's', [$tp]);

// calculate my points
$points = db_one($conn, "SELECT
    COALESCE(SUM(points),0) AS balance,
    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END),0) AS earned,
    COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END),0) AS spent
    FROM points_transactions WHERE tp_number = ?", 's', [$tp]);

$stats = [
    'Total Devices' => $devices['total'],
    'Repaired Devices' => $devices['repaired'],
    'Recycled Devices' => $devices['recycled'],
    'Total Weight (kg)' => $devices['weight'],
    'Points Balance' => $points['balance'],
    'Points Earned' => $points['earned'],
    'Points Spent' => $points['spent']
];

// get status counts, cashback and recent devices
$statuses = db_all($conn, "SELECT status, COUNT(*) AS total FROM devices
    WHERE submitted_by = ? GROUP BY status ORDER BY status", 's', [$tp]);

$cashback = db_one($conn, "SELECT COUNT(*) AS total_requests,
    COALESCE(SUM(cash_value),0) AS requested_value
    FROM redemptions WHERE tp_number = ?", 's', [$tp]);

$recent = db_all($conn, "SELECT deviceID, device_name, status, submitted_at
    FROM devices WHERE submitted_by = ?
    ORDER BY submitted_at DESC LIMIT 10", 's', [$tp]);

$page_title = 'My Report';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">📊</span>
    <div>
        <span class="student-eyebrow">STUDENT PORTAL</span>
        <h1>My Report</h1>
        <p>Your personal recycling and repair summary.</p>
    </div>
</div>

<section class="card student-panel">
    <a href="index.php" class="btn">&larr; Back to Dashboard</a>
    <h2>My Impact</h2>
    <div class="stat-grid three">
        <?php foreach ($stats as $name => $value): ?>
            <div class="stat-card">
                <strong class="stat-value"><?= number_format((float)$value, $name === 'Total Weight (kg)' ? 2 : 0) ?></strong>
                <span class="stat-title"><?= e($name) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card student-panel">
    <h2>Device Status Summary</h2>
    <div class="table-wrap"><table>
        <tr><th>Status</th><th>Total</th></tr>
        <?php foreach ($statuses as $s): ?>
            <tr><td><?= e($s['status']) ?></td><td><?= (int)$s['total'] ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</section>

<section class="card student-panel">
    <h2>Cashback Summary</h2>
    <p>Total Requests: <?= (int)$cashback['total_requests'] ?></p>
    <p>Total Cashback Requested: <?= e(rm($cashback['requested_value'])) ?></p>
</section>

<section class="card student-panel">
    <h2>Recent Devices</h2>
    <div class="table-wrap"><table>
        <tr><th>Device</th><th>Status</th><th>Submitted</th></tr>
        <?php foreach ($recent as $d): ?>
            <tr>
                <td><a href="device-details.php?id=<?= urlencode($d['deviceID']) ?>"><?= e($d['device_name']) ?></a></td>
                <td><?= e($d['status']) ?></td>
                <td><?= e(nice_date($d['submitted_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</section>

<?php require '../includes/student_bottom.php'; ?>
