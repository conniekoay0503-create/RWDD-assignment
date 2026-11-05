<?php
/*
 * Student Dashboard
 * Displays the student's points balance,
 * device statistics and module navigation.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');
$studentTP = $_SESSION['tp_number'];

// Calculate the student's points balance
$totalPoints = db_value(
    $conn,
    "SELECT COALESCE(SUM(points), 0)
     FROM points_transactions
     WHERE tp_number = ?",
    's',
    [$studentTP]
);

// Count all devices submitted by this student
$totalDevices = db_value(
    $conn,
    "SELECT COUNT(*)
     FROM devices
     WHERE submitted_by = ?",
    's',
    [$studentTP]
);

// Count devices currently being repaired
$inRepair = db_value(
    $conn,
    "SELECT COUNT(*)
     FROM devices
     WHERE submitted_by = ?
     AND status = 'In Repair'",
    's',
    [$studentTP]
);
?>
<?php $page_title = 'Dashboard'; require '../includes/student_top.php'; ?>
<section class="hero-card student-hero"><div class="hero-card-text"><span class="student-eyebrow">STUDENT DASHBOARD</span><h1>Welcome, <?= e(explode(' ', $_SESSION['name'])[0]) ?>!</h1><p class="serif-line">Give your electronics a second life. Track repairs, earn points and make an impact.</p></div><div class="hero-actions"><a class="btn btn-primary" href="submit-device.php"><?= icon('plus') ?> Submit a device</a><a class="btn btn-sun" href="my-devices.php"><?= icon('device') ?> Track my devices</a></div><span class="hero-art" aria-hidden="true"><?= icon('recycle') ?></span></section>
<h2 class="section-title"><?= icon('chart') ?> Your impact at a glance</h2>
<section class="stat-grid three">
<a class="stat-card" href="rewards.php"><span class="stat-icon tint-gold"><?= icon('star') ?></span><strong class="stat-value"><?= number_format((int) $totalPoints) ?></strong><span class="stat-title">Available points</span><small>Redeem cashback rewards</small></a>
<a class="stat-card" href="my-devices.php"><span class="stat-icon tint-mint"><?= icon('device') ?></span><strong class="stat-value"><?= (int) $totalDevices ?></strong><span class="stat-title">Devices submitted</span><small>View your device history</small></a>
<a class="stat-card" href="my-devices.php"><span class="stat-icon tint-sky"><?= icon('wrench') ?></span><strong class="stat-value"><?= (int) $inRepair ?></strong><span class="stat-title">Currently in repair</span><small>Follow their progress</small></a>
</section>
<h2 class="section-title"><?= icon('home') ?> Quick access</h2>
<section class="student-quick-grid">
<?php foreach ([['rewards.php','gift','Points & Cashback','Use your points for rewards'],['challenges.php','flag','Challenges','Join sustainability challenges'],['volunteer-request.php','heart','Become a Volunteer','Apply to help repair devices'],['feedback.php','message','Feedback','Share your experience'],['notifications.php','bell','Notifications','Read messages and announcements'],['report.php','chart','My Report','See your personal impact']] as [$url,$ico,$name,$desc]): ?>
<a href="<?= e($url) ?>" class="card student-quick"><span class="stat-icon tint-mint"><?= icon($ico) ?></span><span><strong><?= e($name) ?></strong><small><?= e($desc) ?></small></span><?= icon('arrow-right') ?></a>
<?php endforeach; ?>
</section>
<?php require '../includes/student_bottom.php'; ?>
