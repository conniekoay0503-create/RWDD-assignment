<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

$officer = $_SESSION['tp_number'];
$name = explode(' ', trim($_SESSION['name']))[0];

// Dashboard statistics
$waiting = (int) db_value($conn, "SELECT COUNT(*) FROM devices WHERE status='Awaiting Recycle' AND recycle_consent=1");
$consent = (int) db_value($conn, "SELECT COUNT(*) FROM devices WHERE status='Pending Consent'");
$recycled = (int) db_value($conn, "SELECT COUNT(*) FROM devices WHERE status='Recycled'");
$mine = (int) db_value($conn, "SELECT COUNT(*) FROM devices WHERE status='Recycled' AND processed_by=?", 's', [$officer]);
$weight = (float) db_value($conn, "SELECT COALESCE(SUM(weight_kg),0) FROM devices WHERE status='Recycled'");
$points = (int) db_value($conn, "
    SELECT COALESCE(SUM(p.points), 0)
    FROM points_transactions p
    JOIN devices d ON p.deviceID = d.deviceID
    WHERE p.txn_type = 'earn'
      AND d.status = 'Recycled'
      AND p.tp_number = d.submitted_by   -- the owner's points for this recycled device
");


// Queue preview
$queue = db_all($conn, "
    SELECT d.deviceID, d.device_name, d.weight_kg,
           c.category_name, u.name AS student_name
    FROM devices d
    JOIN categories c ON d.categoryID=c.categoryID
    JOIN users u ON d.submitted_by=u.tp_number
    WHERE d.status='Awaiting Recycle' AND d.recycle_consent=1
    ORDER BY d.submitted_at ASC LIMIT 5
");

// Recent recycling
$recent = db_all($conn, "
    SELECT d.device_name, d.status_changed_at, u.name AS officer_name
    FROM devices d
    LEFT JOIN users u ON d.processed_by=u.tp_number
    WHERE d.status='Recycled'
    ORDER BY d.status_changed_at DESC LIMIT 6
");

// Category breakdown
$categories = db_all($conn, "
    SELECT c.category_name, COUNT(d.deviceID) AS total
    FROM categories c
    LEFT JOIN devices d ON d.categoryID=c.categoryID AND d.status='Recycled'
    GROUP BY c.categoryID, c.category_name
    ORDER BY total DESC
");

$max = max(1, ...array_map(fn($c) => (int)$c['total'], $categories));

$page_title = 'Dashboard';
$active = 'home';
require '../includes/officer_top.php';
?>

<section class="hero-card">
    <div class="hero-card-text">
        <h1>Welcome back, <?= e($name) ?>!</h1>
        <p class="serif-line">
            <?= $waiting
                ? "$waiting device(s) are waiting for recycling."
                : "All recycling requests are caught up. Great work!" ?>
        </p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="recycling_queue.php">
            <?= icon('recycle') ?> Recycling Queue
        </a>
    </div>
    <span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span>
</section>

<h2 class="section-title"><?= icon('clock') ?> Action Required</h2>

<section class="stat-grid three">
    <a class="stat-card <?= $waiting ? 'has-work' : '' ?>" href="recycling_queue.php">
        <span class="stat-icon tint-mint"><?= icon('recycle') ?></span>
        <strong class="stat-value"><?= $waiting ?></strong>
        <span class="stat-title">Awaiting Recycling</span>
        <small>Ready to process</small>
    </a>

    <div class="stat-card">
        <span class="stat-icon tint-gold"><?= icon('clock') ?></span>
        <strong class="stat-value"><?= $consent ?></strong>
        <span class="stat-title">Pending Consent</span>
        <small>Waiting for student approval</small>
    </div>

    <div class="stat-card">
        <span class="stat-icon tint-sky"><?= icon('check') ?></span>
        <strong class="stat-value"><?= $mine ?></strong>
        <span class="stat-title">Processed by Me</span>
        <small>My completed recycling</small>
    </div>
</section>

<h2 class="section-title"><?= icon('chart') ?> Recycling Overview</h2>

<section class="stat-grid three">
    <div class="stat-card row">
        <span class="stat-icon tint-mint"><?= icon('device') ?></span>
        <div>
            <strong class="stat-value"><?= $recycled ?></strong>
            <span class="stat-title">Devices Recycled</span>
        </div>
    </div>

    <div class="stat-card row">
        <span class="stat-icon tint-sky"><?= icon('leaf') ?></span>
        <div>
            <strong class="stat-value"><?= number_format($weight, 2) ?><small>kg</small></strong>
            <span class="stat-title">Recycled Weight</span>
        </div>
    </div>

    <div class="stat-card row">
        <span class="stat-icon tint-gold"><?= icon('star') ?></span>
        <div>
            <strong class="stat-value"><?= number_format($points) ?></strong>
            <span class="stat-title">Recycling Points</span>
        </div>
    </div>
</section>

<div class="section-head">
    <h2 class="section-title"><?= icon('list') ?> Recycling Queue</h2>
    <a class="link-arrow" href="recycling_queue.php">
        View all <?= icon('arrow-right') ?>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= icon('clock') ?> Pending Requests</h2>
        <span class="badge status-awaiting-recycle"><?= $waiting ?> pending</span>
    </div>

    <?php if (!$queue): ?>
        <p class="muted">No devices awaiting recycling.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Device</th><th>Category</th>
                        <th>Student</th><th>Weight</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $d): ?>
                        <tr>
                            <td><strong><?= e($d['deviceID']) ?></strong></td>
                            <td><?= e($d['device_name']) ?></td>
                            <td><?= e($d['category_name']) ?></td>
                            <td><?= e($d['student_name']) ?></td>
                            <td><?= $d['weight_kg'] !== null
                                ? number_format((float)$d['weight_kg'], 2) . ' kg'
                                : 'Not recorded' ?></td>
                            <td>
                                <a class="btn btn-primary btn-sm"
                                   href="process_recycling.php?deviceID=<?= urlencode($d['deviceID']) ?>">
                                    <?= icon('recycle') ?> Process
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="two-col">
    <section class="card">
        <h2 class="card-title"><?= icon('chart') ?> Recycling by Category</h2>
        
        <?php if ($recycled === 0): ?>
            <p class="muted">No recycling data available yet.</p>
        <?php else: ?>
            <ul class="bar-list">
                <?php foreach ($categories as $c): ?>
                    <li>
                        <span><?= e($c['category_name']) ?></span>
                        <span class="bar">
                            <span style="width: <?= round($c['total'] / $max * 100) ?>%"></span>
                        </span>
                        <strong><?= (int)$c['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="bar-total">
            <span>All recycled devices</span>
            <strong><?= $recycled ?></strong>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title"><?= icon('history') ?> Recent Activity</h2>

        <?php if (!$recent): ?>
            <p class="muted">No recycling activity yet.</p>
        <?php else: ?>
            <ul class="activity">
                <?php foreach ($recent as $r): ?>
                    <li>
                        <span class="activity-dot"><?= icon('recycle') ?></span>
                        <div>
                            <strong><?= e($r['device_name']) ?></strong> — Recycled
                            <small>
                                <?= e($r['officer_name'] ?? 'System') ?> ·
                                <?= nice_date($r['status_changed_at'], true) ?>
                            </small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php require '../includes/bottom.php'; ?>
