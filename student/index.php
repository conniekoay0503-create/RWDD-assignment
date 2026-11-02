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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body class="public">
<main class="placeholder card">

    <span class="role-tag">STUDENT</span>

    <h1>Welcome, <?= e($_SESSION['name']) ?>!</h1>

    <h2>My Dashboard</h2>

    <section>
        <h3>My Points</h3>
        <p><?= (int) $totalPoints ?> points</p>
    </section>

    <section>
        <h3>Devices Submitted</h3>
        <p><?= (int) $totalDevices ?> devices</p>
    </section>

    <section>
        <h3>Devices In Repair</h3>
        <p><?= (int) $inRepair ?> devices</p>
    </section>

    <a class="btn btn-primary" href="submit-device.php">
        Submit a Device
    </a>
    <a class="btn btn-primary" href="my-devices.php">
        My Devices
    </a>

    <a class="btn btn-primary" href="rewards.php">
        Points &amp; Cashback
    </a>

    <a class="btn btn-primary" href="challenges.php">
        Challenges &amp; Leaderboard
    </a>

    <a class="btn btn-primary" href="volunteer-request.php">
        Become a Volunteer
    </a>

    <a class="btn btn-primary" href="feedback.php">
        Feedback
    </a>

    <a class="btn btn-primary" href="notifications.php">
        Notifications &amp; Announcements
    </a>

    <a class="btn btn-primary" href="report.php">
        My Report
    </a>

    <a class="btn btn-primary" href="../logout.php">
        Log out
    </a>

</main>
</body>
</html>
