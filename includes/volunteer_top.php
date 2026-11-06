<?php
/*
 * Volunteer layout - top part (top bar + navigation bar).
 * Same structure as includes/admin_top.php, so every role looks like one website.
 * Before including, a page sets:
 *   $page_title  - text for the browser tab
 *   $active      - which item of the navigation bar is highlighted (a key of $sections)
 * The page must already have loaded config/db.php, functions.php and volunteer_logic.php.
 */

// Navigation: section => [label, icon, [page file => [tab label, icon]]]
$sections = [
    'home'      => ['Home', 'home', ['index.php' => ['Home', 'home']]],
    'jobs'      => ['My jobs', 'wrench', ['my_jobs.php' => ['My jobs', 'wrench']]],
    'queue'     => ['Repair queue', 'inbox', ['repair_queue.php' => ['Repair queue', 'inbox']]],
    'report'    => ['Repair report', 'chart', ['report.php' => ['Repair report', 'chart']]],
    'points'    => ['Points & cashback', 'wallet', ['points.php' => ['Points & cashback', 'wallet']]],
    'challenge' => ['Challenge & rank', 'flag', ['challenge.php' => ['Challenge & rank', 'flag']]],
    'history'   => ['Repair history', 'history', ['history.php' => ['Repair history', 'history']]],
];

// Small red numbers on the navigation bar = things waiting for this volunteer
$counts     = vol_nav_counts($conn, $_SESSION['tp_number']);
$nav_badges = ['queue' => $counts['queue'], 'jobs' => $counts['jobs']];

// Notification bell: how many are unread + the latest 6 (unread ones first)
$unread = db_value($conn, "SELECT COUNT(*) FROM notifications WHERE tp_number = ? AND is_read = 0", 's', [$_SESSION['tp_number']]);
$latest = db_all($conn, "SELECT title, message, is_read, created_at FROM notifications
                         WHERE tp_number = ? ORDER BY is_read, created_at DESC, notificationID DESC LIMIT 6", 's', [$_SESSION['tp_number']]);
$this_page = basename($_SERVER['PHP_SELF']);
$initial   = strtoupper(substr($_SESSION['name'], 0, 1));

// Page title with an icon (same function name and look as the admin pages)
function page_head($icon_name, $title, $desc = '', $actions = '')
{
    echo '<div class="page-head"><span class="page-icon">' . icon($icon_name) . '</span><div class="page-title">';
    echo '<h1>' . e($title) . '</h1>';
    if ($desc !== '') echo '<p class="page-desc">' . e($desc) . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="page-actions">' . $actions . '</div>';
    echo '</div>';
}

// Friendly message when a list is empty
function empty_state($icon_name, $text, $action = '')
{
    echo '<div class="empty-state">' . icon($icon_name, 'icon-float') . '<p>' . e($text) . '</p>' . $action . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Volunteer | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body class="app">
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark"><?= icon('recycle', 'icon-spin-hover') ?></span><span class="brand-text">E-Waste &amp; Repair Café <small>Volunteer</small></span></a>

    <nav class="pillnav" aria-label="Volunteer menu">
        <ul>
            <?php foreach ($sections as $nav_key => [$nav_label, $nav_ico, $nav_subs]): ?>
                <?php $is_on = $active === $nav_key; ?>
                <li>
                    <a href="<?= array_key_first($nav_subs) ?>" class="pill <?= $is_on ? 'active' : '' ?>" <?= $is_on ? 'aria-current="page"' : '' ?> title="<?= e($nav_label) ?>">
                        <?= icon($nav_ico) ?><span class="pill-label"><?= e($nav_label) ?></span>
                        <?php if (!empty($nav_badges[$nav_key])): ?><b class="nav-badge" aria-label="<?= (int) $nav_badges[$nav_key] ?> waiting"><?= (int) $nav_badges[$nav_key] ?></b><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="topbar-tools">

        <!-- Notification bell -->
        <div class="dropdown">
            <button type="button" class="icon-btn bell" data-dropdown="notif-panel" aria-expanded="false" title="Notifications"
                    aria-label="Notifications<?= $unread ? ', ' . (int) $unread . ' unread' : '' ?>"><?= icon('bell', $unread ? 'icon-ring' : '') ?><?php if ($unread): ?><span class="bell-count"><?= $unread > 9 ? '9+' : (int) $unread ?></span><?php endif; ?></button>
            <div class="dropdown-panel notif-panel" id="notif-panel" hidden>
                <div class="dropdown-head">
                    <strong>Notifications</strong>
                    <?php if ($unread): ?>
                        <form method="post" action="notifications.php">
                            <?= vol_csrf_field() ?>
                            <input type="hidden" name="action" value="read_all">
                            <input type="hidden" name="back" value="<?= e($this_page) ?>">
                            <button class="link-btn"><?= icon('check') ?> Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>
                <ul class="notif-list">
                    <?php foreach ($latest as $notif): ?>
                        <li class="<?= $notif['is_read'] ? '' : 'unread' ?>">
                            <strong><?= e($notif['title']) ?></strong>
                            <span><?= e($notif['message']) ?></span>
                            <small><?= nice_date($notif['created_at'], true) ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$latest): ?><li class="muted">No notifications yet.</li><?php endif; ?>
                </ul>
                <a class="dropdown-foot" href="notifications.php">View all notifications</a>
            </div>
        </div>

        <!-- Account menu -->
        <div class="dropdown">
            <button type="button" class="avatar" data-dropdown="user-panel" aria-expanded="false" aria-label="Account menu" title="My account"><?= e($initial) ?></button>
            <div class="dropdown-panel user-panel" id="user-panel" hidden>
                <div class="dropdown-head user-head">
                    <strong><?= e($_SESSION['name']) ?></strong>
                    <small><?= icon('wrench') ?> Repair Volunteer · <?= e($_SESSION['tp_number']) ?></small>
                </div>
                <a href="profile.php"><?= icon('user') ?> My profile</a>
                <a href="notifications.php"><?= icon('bell') ?> Notifications</a>
                <a href="../index.php"><?= icon('home') ?> Public homepage</a>
            </div>
        </div>

        <!-- Log out is always visible in the top bar -->
        <a class="btn btn-logout" href="../logout.php" data-confirm="Log out now?" title="Log out"><?= icon('logout') ?><span>Log out</span></a>
    </div>
</header>

<main class="content" id="main">
    <?php show_flash(); ?>

