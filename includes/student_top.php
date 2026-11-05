
<?php
// Student header based on the Admin layout.

$student_nav = [
    'index.php' => ['Dashboard', 'home'],
    'submit-device.php' => ['Submit Device', 'plus'],
    'my-devices.php' => ['My Devices', 'device'],
    'rewards.php' => ['Rewards', 'wallet'],
    'challenges.php' => ['Challenges', 'flag'],
    'volunteer-request.php' => ['Volunteer', 'heart'],
    'feedback.php' => ['Feedback', 'message'],
    'report.php' => ['My Report', 'chart'],
];

$student_page = basename($_SERVER['PHP_SELF']);

$nav_active = $student_page === 'device-details.php'
    ? 'my-devices.php'
    : $student_page;

$studentTP = $_SESSION['tp_number'];

if (empty($_SESSION['notifications_csrf'])) {
    $_SESSION['notifications_csrf'] = bin2hex(random_bytes(32));
}

$student_unread = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM notifications
     WHERE tp_number = ? AND is_read = 0",
    's',
    [$studentTP]
);

$student_latest = db_all(
    $conn,
    "SELECT title, message, is_read, created_at
     FROM notifications
     WHERE tp_number = ?
     ORDER BY is_read ASC, created_at DESC,
              notificationID DESC
     LIMIT 6",
    's',
    [$studentTP]
);

$initial = strtoupper(substr($_SESSION['name'] ?? 'S', 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($page_title ?? 'Student') ?>
        | Student | E-Waste &amp; Repair Café
    </title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">

    <script src="../assets/js/main.js" defer></script>
</head>

<body class="app student-app">

<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">

    <!-- BRAND -->
    <a class="brand" href="index.php">
        <span class="brand-mark">
            <?= icon('recycle', 'icon-spin-hover') ?>
        </span>

        <span class="brand-text">
            E-Waste &amp; Repair Café
            <small>Student</small>
        </span>
    </a>

    <!-- STUDENT NAVIGATION -->
    <nav class="pillnav student-nav"
         aria-label="Student navigation">

        <ul>
            <?php foreach ($student_nav as $file => [$label, $ico]): ?>

                <?php $is_on = $nav_active === $file; ?>

                <li>
                    <a
                        href="<?= e($file) ?>"
                        class="pill <?= $is_on ? 'active' : '' ?>"
                        title="<?= e($label) ?>"
                        <?= $is_on ? 'aria-current="page"' : '' ?>
                    >
                        <?= icon($ico) ?>

                        <span class="pill-label">
                            <?= e($label) ?>
                        </span>
                    </a>
                </li>

            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- SAME TOPBAR TOOLS AS ADMIN -->
    <div class="topbar-tools">

        <!-- TEXT SIZE -->
        <div class="text-size" aria-label="Text size">
            <button
                type="button"
                data-text-size="-1"
                aria-label="Smaller text"
            >A-</button>

            <span data-text-size-label>100%</span>

            <button
                type="button"
                data-text-size="1"
                aria-label="Larger text"
            >A+</button>
        </div>

        <!-- DARK MODE -->
        <button
            type="button"
            class="icon-btn"
            data-theme-toggle
            aria-label="Toggle dark mode"
            title="Dark mode"
        >
            <?= icon('moon', 'theme-moon') ?>
            <?= icon('sun', 'theme-sun') ?>
        </button>

        <!-- NOTIFICATION DROPDOWN -->
        <div class="dropdown">

            <button
                type="button"
                class="icon-btn bell"
                data-dropdown="student-notif-panel"
                aria-expanded="false"
                aria-controls="student-notif-panel"
                title="Notifications"
                aria-label="Notifications<?= $student_unread
                    ? ', ' . $student_unread . ' unread'
                    : '' ?>"
            >
                <?= icon(
                    'bell',
                    $student_unread ? 'icon-ring' : ''
                ) ?>

                <?php if ($student_unread): ?>
                    <span class="bell-count">
                        <?= $student_unread > 9
                            ? '9+'
                            : $student_unread ?>
                    </span>
                <?php endif; ?>
            </button>

            <div
                class="dropdown-panel notif-panel"
                id="student-notif-panel"
                hidden
            >

                <div class="dropdown-head">
                    <strong>Notifications</strong>

                    <?php if ($student_unread): ?>
                        <form
                            method="post"
                            action="notifications.php"
                        >
                            <input
                                type="hidden"
                                name="action"
                                value="read_all"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(
                                    $_SESSION['notifications_csrf']
                                ) ?>"
                            >

                            <button
                                type="submit"
                                class="link-btn"
                            >
                                <?= icon('check') ?>
                                Mark all as read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <ul class="notif-list">
                    <?php foreach ($student_latest as $n): ?>
                        <li class="<?= $n['is_read']
                            ? ''
                            : 'unread' ?>">

                            <strong>
                                <?= e($n['title']) ?>
                            </strong>

                            <span>
                                <?= e($n['message']) ?>
                            </span>

                            <small>
                                <?= e(nice_date(
                                    $n['created_at'],
                                    true
                                )) ?>
                            </small>
                        </li>
                    <?php endforeach; ?>

                    <?php if (!$student_latest): ?>
                        <li class="muted">
                            No notifications yet.
                        </li>
                    <?php endif; ?>
                </ul>

                <a
                    class="dropdown-foot"
                    href="notifications.php"
                >
                    View all notifications
                </a>
            </div>
        </div>

        <!-- ACCOUNT DROPDOWN -->
        <div class="dropdown">

            <button
                type="button"
                class="avatar"
                data-dropdown="student-user-panel"
                aria-expanded="false"
                aria-controls="student-user-panel"
                aria-label="Account menu"
                title="My account"
            >
                <?= e($initial) ?>
            </button>

            <div
                class="dropdown-panel user-panel"
                id="student-user-panel"
                hidden
            >
                <div class="dropdown-head user-head">
                    <strong>
                        <?= e($_SESSION['name'] ?? 'Student') ?>
                    </strong>

                    <small>
                        <?= icon('user') ?>
                        Student · <?= e($studentTP) ?>
                    </small>
                </div>

                <a href="index.php">
                    <?= icon('home') ?>
                    Dashboard
                </a>

                <a href="notifications.php">
                    <?= icon('bell') ?>
                    Notifications
                </a>

                <a href="../index.php">
                    <?= icon('home') ?>
                    Public homepage
                </a>
            </div>
        </div>

        <!-- LOG OUT -->
        <a
            class="btn btn-logout"
            href="../logout.php"
            data-confirm="Log out now?"
            title="Log out"
        >
            <?= icon('logout') ?>
            <span>Log out</span>
        </a>

    </div>
</header>

<main class="content student-content" id="main">
    <?php show_flash(); ?>
