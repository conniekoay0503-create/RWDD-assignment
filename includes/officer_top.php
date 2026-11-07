<?php
/*
 * Recycling Officer layout - top section.
 *
 * Each page sets:
 * $page_title = 'Dashboard';
 * $active = 'home';
 */

// ---------- NAVIGATION ----------
$sections = [
    'home' => [
        'Home', 'home', [
            'index.php' => ['Dashboard', 'home']
        ]
    ],

    'recycling' => [
        'Recycling', 'recycle', [
            'recycling_queue.php' => ['Recycling Queue', 'list'],
            'recycling_history.php' => ['Recycling History', 'history']
        ]
    ],

    'devices' => [
        'Device Management', 'list', [
            'abandoned_devices.php' => ['Abandoned Devices', 'clock']
        ]
    ],

    'reports' => [
        'Reports', 'chart', [
            'recycling_statistics.php' => ['Recycling Statistics', 'chart']
        ]
    ]
];

// ---------- NAVIGATION BADGES ----------
$nav_badges = [
    'recycling' => (int) db_value(
        $conn,
        "SELECT COUNT(*) FROM devices
         WHERE status = 'Awaiting Recycle'
         AND recycle_consent = 1"
    )
];

// ---------- NOTIFICATIONS ----------
$unread = (int) db_value(
    $conn,
    "SELECT COUNT(*) FROM notifications
     WHERE tp_number = ? AND is_read = 0",
    's',
    [$_SESSION['tp_number']]
);

$latest = db_all(
    $conn,
    "SELECT title, message, is_read, created_at
     FROM notifications
     WHERE tp_number = ?
     ORDER BY is_read, created_at DESC,
              notificationID DESC
     LIMIT 6",
    's',
    [$_SESSION['tp_number']]
);

$this_page = basename($_SERVER['PHP_SELF']);
$initial = mb_strtoupper(mb_substr($_SESSION['name'], 0, 1));

// Automatically identify the active navigation section
foreach ($sections as $key => $section) {
    if (isset($section[2][$this_page])) {
        $active = $key;
        break;
    }
}

// ---------- OFFICER PAGE HEADER ----------
function officer_page_head(
    $icon_name,
    $title,
    $desc = '',
    $actions = ''
) {
    global $sections, $active, $this_page;

    echo '<div class="page-head">';
    echo '<span class="page-icon">' .
         icon($icon_name) . '</span>';

    echo '<div class="page-title">';
    echo '<h1>' . e($title) . '</h1>';

    if ($desc !== '') {
        echo '<p class="page-desc">' .
             e($desc) . '</p>';
    }

    echo '</div>';

    if ($actions !== '') {
        echo '<div class="page-actions">' .
             $actions . '</div>';
    }

    echo '</div>';

    $subs = $sections[$active][2] ?? [];

    if (count($subs) > 1) {
        echo '<nav class="subtabs">';

        foreach ($subs as $file => [$label, $ico]) {
            $on = $file === $this_page;

            echo '<a href="' . e($file) . '"' .
                 ($on ? ' class="active"' : '') . '>';

            echo icon($ico) . e($label);
            echo '</a>';
        }

        echo '</nav>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($page_title) ?> |
        Recycling Officer | E-Waste &amp; Repair Café
    </title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <script src="../assets/js/main.js" defer></script>
</head>

<body class="app">

<a class="skip-link" href="#main">
    Skip to content
</a>

<header class="topbar">

    <!-- BRAND -->
    <a class="brand" href="index.php">
        <span class="brand-mark">
            <?= icon('recycle', 'icon-spin-hover') ?>
        </span>

        <span class="brand-text">
            E-Waste &amp; Repair Café
            <small>Officer</small>
        </span>
    </a>

    <!-- NAVIGATION -->
    <nav class="pillnav" aria-label="Officer menu">
        <ul>
            <?php foreach (
                $sections as $key => [$label, $ico, $subs]
            ): ?>

                <?php $is_on = $active === $key; ?>

                <li class="<?= count($subs) > 1
                    ? 'has-menu' : '' ?>">

                    <a href="<?= e(array_key_first($subs)) ?>"
                       class="pill <?= $is_on ? 'active' : '' ?>"
                       <?= $is_on
                           ? 'aria-current="page"' : '' ?>
                       title="<?= e($label) ?>">

                        <?= icon($ico) ?>

                        <span class="pill-label">
                            <?= e($label) ?>
                        </span>

                        <?php if (!empty($nav_badges[$key])): ?>
                            <b class="nav-badge">
                                <?= (int) $nav_badges[$key] ?>
                            </b>
                        <?php endif; ?>

                    </a>

                    <?php if (count($subs) > 1): ?>
                        <div class="submenu">
                            <strong><?= e($label) ?></strong>

                            <?php foreach (
                                $subs as $file => [$sub_label, $sub_icon]
                            ): ?>
                                <a href="<?= e($file) ?>"
                                   class="<?= $file === $this_page
                                       ? 'current' : '' ?>">

                                    <?= icon($sub_icon) ?>
                                    <?= e($sub_label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </li>

            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- TOPBAR TOOLS -->
    <div class="topbar-tools">

        <!-- NOTIFICATIONS -->
        <div class="dropdown">

            <button type="button"
                    class="icon-btn bell"
                    data-dropdown="notif-panel"
                    aria-expanded="false"
                    aria-label="Notifications">

                <?= icon(
                    'bell',
                    $unread ? 'icon-ring' : ''
                ) ?>

                <?php if ($unread): ?>
                    <span class="bell-count">
                        <?= $unread > 9 ? '9+' : $unread ?>
                    </span>
                <?php endif; ?>

            </button>

            <div class="dropdown-panel notif-panel"
                 id="notif-panel" hidden>

                <div class="dropdown-head">
                    <strong>Notifications</strong>
                    <?php if ($unread): ?>
                        <form method="post" action="notifications.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="read_all">
                            <!-- "back" = the page the bell was on, so the user stays on that page -->
                            <input type="hidden" name="back" value="<?= e($this_page) ?>">
                            <button class="link-btn"><?= icon('check') ?> Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>

                <ul class="notif-list">
                    <?php foreach ($latest as $n): ?>
                        <li class="<?= $n['is_read']
                            ? '' : 'unread' ?>">

                            <strong>
                                <?= e($n['title']) ?>
                            </strong>

                            <span>
                                <?= e($n['message']) ?>
                            </span>

                            <small>
                                <?= nice_date(
                                    $n['created_at'],
                                    true
                                ) ?>
                            </small>
                        </li>
                    <?php endforeach; ?>

                    <?php if (!$latest): ?>
                        <li class="muted">
                            No notifications yet.
                        </li>
                    <?php endif; ?>
                </ul>
                <a href="notifications.php" class="dropdown-foot">
                    <?= icon('bell') ?>
                    View All Notifications
                </a>

            </div>
        </div>

        <!-- USER ACCOUNT -->
        <div class="dropdown">

            <button type="button"
                    class="avatar"
                    data-dropdown="user-panel"
                    aria-expanded="false"
                    aria-label="Account menu">

                <?= e($initial) ?>
            </button>

            <div class="dropdown-panel user-panel"
                 id="user-panel" hidden>

                <div class="dropdown-head user-head">
                    <strong>
                        <?= e($_SESSION['name']) ?>
                    </strong>

                    <small>
                        <?= icon('shield') ?>
                        Recycling Officer ·
                        <?= e($_SESSION['tp_number']) ?>
                    </small>
                </div>

                <a href="../index.php">
                    <?= icon('home') ?>
                    Public homepage
                </a>

            </div>
        </div>

        <!-- LOGOUT -->
        <a class="btn btn-logout"
           href="../logout.php"
           data-confirm="Log out now?">

            <?= icon('logout') ?>
            <span>Log out</span>
        </a>

    </div>
</header>

<!-- MAIN CONTENT -->
<main class="content" id="main">
    <?php show_flash(); ?>
