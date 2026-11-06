<?php
/*
 * Homepage (public, before login).
 * Only one screen: the hero with Log in / Sign up.
 * The 3 numbers come from the database, so they always show what is really happening.
 */
require 'config/db.php';
require 'includes/functions.php';

$logged_in = !empty($_SESSION['tp_number']);

$stats = [
    'devices' => db_value($conn, "SELECT COUNT(*) FROM devices WHERE status <> 'Cancelled'"),
    'points'  => db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE txn_type IN ('earn', 'bonus')"),
    'members' => db_value($conn, "SELECT COUNT(*) FROM users WHERE is_active = 1"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste &amp; Repair Café | Asia Pacific University</title>
    <meta name="description" content="Bring your broken gadgets to the APU Green Hub. Student volunteers repair them, the rest is recycled properly, and you earn points for cashback.">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/main.js" defer></script>
</head>
<body class="landing">
<a class="skip-link" href="#main">Skip to content</a>

<div class="hero-wrap">
    <header class="land-nav">
        <a class="brand" href="index.php"><span class="brand-mark"><?= icon('recycle', 'icon-spin-hover') ?></span><span class="brand-text">E-Waste &amp; Repair Café</span></a>
        <div class="land-actions">
            <?php if ($logged_in): ?>
                <a class="btn btn-primary" href="<?= e(home_for($_SESSION['role'])) ?>"><?= icon('home') ?> My dashboard</a>
            <?php else: ?>
                <a class="land-login" href="login.php"><?= icon('user') ?> Log in</a>
                <a class="btn btn-primary" href="register.php">Sign up</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="hero" id="main">
        <div class="hero-text">
            <p class="hero-place"><?= icon('leaf') ?> Green Hub, Block E, Asia Pacific University</p>
            <h1>Fix it before<br>you bin it.</h1>
            <p class="hero-sub">Repair first. Recycle right. Earn rewards.</p>
            <p class="hero-lead">Bring a broken phone, laptop or charger to the Green Hub. A student volunteer tries to repair it. If it can’t be saved, it’s recycled the proper way, and either way you earn points you can turn into APCard cashback.</p>
            <div class="hero-cta">
                <?php if ($logged_in): ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(home_for($_SESSION['role'])) ?>"><?= icon('home') ?> Open my dashboard</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-lg" href="register.php"><?= icon('user-plus') ?> Create a free account</a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div><span class="stat-icon tint-mint"><?= icon('device') ?></span><dl><dd><?= number_format($stats['devices']) ?></dd><dt>Devices brought in</dt></dl></div>
                <div><span class="stat-icon tint-gold"><?= icon('star') ?></span><dl><dd><?= number_format($stats['points']) ?></dd><dt>Points earned</dt></dl></div>
                <div><span class="stat-icon tint-sky"><?= icon('users') ?></span><dl><dd><?= number_format($stats['members']) ?></dd><dt>Members</dt></dl></div>
            </div>
        </div>
        <div class="hero-phones" aria-hidden="true">
            <img class="phone phone-back" src="assets/img/phone-points.svg" alt="" width="270" height="540">
            <img class="phone phone-front" src="assets/img/phone-track.svg" alt="" width="270" height="540">
        </div>
    </main>
    <!-- The curved bottom edge of the coloured area -->
    <svg class="hero-curve" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70 C 360 140 720 0 1080 50 C 1260 75 1360 60 1440 40 V120 H0 Z"/></svg>
</div>
</body>
</html>
