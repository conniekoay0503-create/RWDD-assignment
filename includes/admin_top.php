<?php
/*
 * Admin layout - top part (top bar + navigation bar).
 * Before including, a page sets:
 *   $page_title  - text for the browser tab
 *   $active      - which section of the navigation bar is highlighted (a key of $sections)
 */

// Navigation: section => [label, icon, [sub-page file => [tab label, icon]]]
// A section with more than one sub-page shows tabs under the page title.
$sections = [
    'home'          => ['Home', 'home', ['index.php' => ['Dashboard', 'home']]],
    'categories'    => ['Categories', 'tag', [
        'categories.php'    => ['All categories', 'list'],
        'category_form.php' => ['Add category', 'plus'],
        'calculator.php'    => ['Points calculator', 'calculator'],
    ]],
    'challenges'    => ['Challenges', 'flag', [
        'challenges.php'     => ['All challenges', 'list'],
        'challenge_form.php' => ['Create challenge', 'plus'],
    ]],
    'cashback'      => ['Cashback', 'wallet', [
        'cashback.php'         => ['Pending requests', 'clock'],
        'cashback_history.php' => ['History', 'history'],
        'rewards.php'          => ['Rewards', 'gift'],
        'reward_form.php'      => ['Add reward', 'plus'],
    ]],
    'users'         => ['Users', 'users', [
        'users.php'              => ['All users', 'users'],
        'volunteer_requests.php' => ['Volunteer requests', 'heart'],
        'assign_officer.php'     => ['Assign officer', 'user-plus'],
    ]],
    'announcements' => ['Announcements', 'megaphone', [
        'announcements.php'     => ['All announcements', 'list'],
        'announcement_form.php' => ['Post announcement', 'plus'],
    ]],
    'feedback'      => ['Feedback', 'message', ['feedback.php' => ['Inbox', 'inbox']]],
    'reports'       => ['Reports', 'chart', ['reports.php' => ['Reports', 'chart']]],
];

// Small red numbers on the navigation bar = things waiting for an admin
$nav_badges = [
    'cashback' => db_value($conn, "SELECT COUNT(*) FROM redemptions WHERE status = 'pending'"),
    'users'    => db_value($conn, "SELECT COUNT(*) FROM volunteer_request WHERE status = 'pending'"),
    'feedback' => db_value($conn, "SELECT COUNT(*) FROM feedback WHERE admin_reply IS NULL"),
];

// Notification bell: how many are unread + the latest 6 (unread ones first)
$unread = db_value($conn, "SELECT COUNT(*) FROM notifications WHERE tp_number = ? AND is_read = 0", 's', [$_SESSION['tp_number']]);
$latest = db_all($conn, "SELECT title, message, is_read, created_at FROM notifications
                         WHERE tp_number = ? ORDER BY is_read, created_at DESC, notificationID DESC LIMIT 6", 's', [$_SESSION['tp_number']]);
$this_page = basename($_SERVER['PHP_SELF']);   // e.g. "users.php"
$initial = mb_strtoupper(mb_substr($_SESSION['name'], 0, 1));

// Page title with an icon, followed by the tabs of the current section
function page_head($icon_name, $title, $desc = '', $actions = '')
{
    global $sections, $active, $this_page;
    echo '<div class="page-head"><span class="page-icon">' . icon($icon_name) . '</span><div class="page-title">';
    echo '<h1>' . e($title) . '</h1>';
    if ($desc !== '') echo '<p class="page-desc">' . e($desc) . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="page-actions">' . $actions . '</div>';
    echo '</div>';

    $subs = $sections[$active][2] ?? [];
    if (count($subs) > 1) {
        echo '<nav class="subtabs" aria-label="' . e($sections[$active][0]) . ' pages">';
        foreach ($subs as $file => [$label, $ico]) {
            $on = $file === $this_page;
            echo '<a href="' . $file . '"' . ($on ? ' class="active" aria-current="page"' : '') . '>' . icon($ico) . '<span>' . e($label) . '</span></a>';
        }
        echo '</nav>';
    }
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
    <title><?= e($page_title) ?> | Admin | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body class="app">
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark"><?= icon('recycle', 'icon-spin-hover') ?></span><span class="brand-text">E-Waste &amp; Repair Café <small>Admin</small></span></a>

    <!-- Navigation: icons in a pill bar. The current section opens up to show its name.
         Hover (or Tab to) a section to see its pages in a drop-down menu. -->
    <nav class="pillnav" aria-label="Admin menu">
        <ul>
            <?php foreach ($sections as $key => [$label, $ico, $subs]): ?>
                <?php $is_on = $active === $key; ?>
                <li class="<?= count($subs) > 1 ? 'has-menu' : '' ?>">
                    <a href="<?= array_key_first($subs) ?>" class="pill <?= $is_on ? 'active' : '' ?>" <?= $is_on ? 'aria-current="page"' : '' ?> title="<?= e($label) ?>">
                        <?= icon($ico) ?><span class="pill-label"><?= e($label) ?></span>
                        <?php if (!empty($nav_badges[$key])): ?><b class="nav-badge" aria-label="<?= (int) $nav_badges[$key] ?> waiting"><?= (int) $nav_badges[$key] ?></b><?php endif; ?>
                    </a>
                    <?php if (count($subs) > 1): ?>
                        <div class="submenu">
                            <strong><?= e($label) ?></strong>
                            <?php foreach ($subs as $file => [$sub_label, $sub_icon]): ?>
                                <a href="<?= $file ?>" class="<?= $file === $this_page ? 'current' : '' ?>"><?= icon($sub_icon) ?> <?= e($sub_label) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="read_all">
                            <input type="hidden" name="back" value="<?= e($this_page) ?>">
                            <button class="link-btn"><?= icon('check') ?> Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>
                <ul class="notif-list">
                    <?php foreach ($latest as $n): ?>
                        <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                            <strong><?= e($n['title']) ?></strong>
                            <span><?= e($n['message']) ?></span>
                            <small><?= nice_date($n['created_at'], true) ?></small>
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
                    <small><?= icon('shield') ?> Admin · <?= e($_SESSION['tp_number']) ?></small>
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
