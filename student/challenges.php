
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];

// Retrieve all challenges and classify them by date.
$challenges = db_all(
    $conn,
    "SELECT challengeID, title, description, target_kg,
            start_date, end_date, bonus_points,
            CASE
                WHEN CURDATE() < start_date THEN 'Upcoming'
                WHEN CURDATE() > end_date THEN 'Ended'
                ELSE 'Active'
            END AS challenge_status
     FROM challenges
     ORDER BY
        CASE
            WHEN CURDATE() BETWEEN start_date AND end_date THEN 0
            WHEN CURDATE() < start_date THEN 1
            ELSE 2
        END,
        start_date DESC"
);

// Calculate each challenge's progress for the current student.
// This counts devices processed as Completed or Recycled
// during the challenge's date range.
$progress = db_all(
    $conn,
    "SELECT
        c.challengeID,
        COALESCE(SUM(
            CASE
                WHEN d.status IN ('Completed', 'Recycled')
                 AND d.status_changed_at >= c.start_date
                 AND d.status_changed_at < DATE_ADD(c.end_date, INTERVAL 1 DAY)
                THEN d.weight_kg
                ELSE 0
            END
        ), 0) AS progress_kg
     FROM challenges c
     LEFT JOIN devices d ON d.submitted_by = ?
     GROUP BY c.challengeID",
    's',
    [$studentTP]
);

$progressByChallenge = [];
foreach ($progress as $row) {
    $progressByChallenge[$row['challengeID']] =
        (float) $row['progress_kg'];
}

// Retrieve students' total processed e-waste weight.
$leaderboard = db_all(
    $conn,
    "SELECT
        u.tp_number,
        u.name,
        COALESCE(SUM(
            CASE
                WHEN d.status IN ('Completed', 'Recycled')
                THEN d.weight_kg
                ELSE 0
            END
        ), 0) AS total_kg
     FROM students s
     JOIN users u ON s.tp_number = u.tp_number
     LEFT JOIN devices d ON d.submitted_by = s.tp_number
     WHERE u.is_active = 1
     GROUP BY u.tp_number, u.name
     ORDER BY total_kg DESC, u.name ASC"
);

$myRank = null;
foreach ($leaderboard as $index => $student) {
    if ($student['tp_number'] === $studentTP) {
        $myRank = $index + 1;
        break;
    }
}

// Retrieve challenge bonuses earned by the student.
$bonuses = db_all(
    $conn,
    "SELECT c.title, p.points, p.created_at
     FROM points_transactions p
     JOIN challenges c ON p.challengeID = c.challengeID
     WHERE p.tp_number = ?
       AND p.txn_type = 'bonus'
     ORDER BY p.created_at DESC",
    's',
    [$studentTP]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Challenges &amp; Leaderboard | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="public">

<main style="max-width: 950px; margin: 40px auto; padding: 20px;">

    <a href="index.php">← Back to Dashboard</a>

    <h1>Challenges &amp; Leaderboard</h1>
    <p>Track your e-waste contributions and see your ranking.</p>

    <h2>My Leaderboard Rank</h2>

    <p>
        <strong>
            <?= $myRank !== null ? '#' . $myRank : 'Not ranked' ?>
        </strong>
        out of <?= count($leaderboard) ?> active students
    </p>

    <hr>

    <h2>Challenges</h2>

    <?php if (empty($challenges)): ?>
        <p>No challenges available.</p>
    <?php else: ?>

        <?php foreach ($challenges as $challenge): ?>

            <?php
            $challengeID = (int) $challenge['challengeID'];
            $currentKg = $progressByChallenge[$challengeID] ?? 0;
            $targetKg = (float) $challenge['target_kg'];

            $percentage = $targetKg > 0
                ? min(100, ($currentKg / $targetKg) * 100)
                : 0;
            ?>

            <section style="
                border: 1px solid #d7e4dc;
                border-radius: 12px;
                padding: 18px;
                margin: 15px 0;
            ">

                <h3><?= e($challenge['title']) ?></h3>

                <p>
                    <strong>Status:</strong>
                    <?= e($challenge['challenge_status']) ?>
                </p>

                <p><?= e($challenge['description']) ?></p>

                <p>
                    <strong>Period:</strong>
                    <?= e($challenge['start_date']) ?>
                    to
                    <?= e($challenge['end_date']) ?>
                </p>

                <p>
                    <strong>Progress:</strong>
                    <?= number_format($currentKg, 2) ?> /
                    <?= number_format($targetKg, 2) ?> kg
                </p>

                <progress
                    value="<?= $percentage ?>"
                    max="100"
                    style="width: 100%;"
                ></progress>

                <p>
                    <?= number_format($percentage, 1) ?>% completed
                </p>

                <p>
                    <strong>Bonus:</strong>
                    <?= (int) $challenge['bonus_points'] ?> points
                </p>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

    <hr>

    <h2>Student Leaderboard</h2>

    <?php if (empty($leaderboard)): ?>
        <p>No leaderboard data available.</p>
    <?php else: ?>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Processed Weight</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($leaderboard as $index => $student): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= e($student['name']) ?>

                                <?php if ($student['tp_number'] === $studentTP): ?>
                                    <strong>(You)</strong>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float) $student['total_kg'],
                                    2
                                ) ?> kg
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <hr>

    <h2>My Challenge Bonuses</h2>

    <?php if (empty($bonuses)): ?>
        <p>No challenge bonuses earned yet.</p>
    <?php else: ?>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Challenge</th>
                    <th>Bonus Points</th>
                    <th>Date Earned</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($bonuses as $bonus): ?>
                    <tr>
                        <td><?= e($bonus['title']) ?></td>
                        <td>+<?= (int) $bonus['points'] ?></td>
                        <td>
                            <?= e(nice_date(
                                $bonus['created_at'],
                                true
                            )) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</main>

</body>
</html>
