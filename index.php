<?php
/*
 * Homepage (public, before login).
 * Numbers, categories, rewards, the running challenge and announcements come from the database,
 * so the page always shows what is really happening.
 */
require 'config/db.php';
require 'includes/functions.php';

$logged_in = !empty($_SESSION['tp_number']);

$stats = [
    'devices' => db_value($conn, "SELECT COUNT(*) FROM devices WHERE status <> 'Cancelled'"),
    'points'  => db_value($conn, "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE txn_type IN ('earn', 'bonus')"),
    'members' => db_value($conn, "SELECT COUNT(*) FROM users WHERE is_active = 1"),
];
$categories = db_all($conn, "SELECT category_name, max_points FROM categories WHERE is_active = 1 ORDER BY max_points DESC");
$rewards    = db_all($conn, "SELECT points_required, cash_value FROM rewards WHERE stock > 0 ORDER BY points_required LIMIT 3");
$challenge  = db_one($conn, "SELECT title, description, target_kg, bonus_points, end_date FROM challenges
                             WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1");
$news       = db_all($conn, "SELECT title, message, created_at FROM announcements ORDER BY created_at DESC LIMIT 2");
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
        <!-- On phones these links are hidden; "Log in" and "Sign up" stay visible -->
        <nav class="land-links" aria-label="Homepage sections">
            <a href="#how"><?= icon('wrench') ?> How it works</a>
            <a href="#bring"><?= icon('box') ?> What to bring</a>
            <a href="#rewards"><?= icon('gift') ?> Rewards</a>
            <a href="#news"><?= icon('megaphone') ?> News</a>
        </nav>
        <div class="land-actions">
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode" title="Dark mode"><?= icon('moon', 'theme-moon') ?><?= icon('sun', 'theme-sun') ?></button>
            <?php if ($logged_in): ?>
                <a class="btn btn-primary" href="<?= e(home_for($_SESSION['role'])) ?>"><?= icon('home') ?> My dashboard</a>
            <?php else: ?>
                <a class="land-login" href="login.php"><?= icon('user') ?> Log in</a>
                <a class="btn btn-primary" href="register.php">Sign up</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="hero">
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
                    <a class="btn btn-outline btn-lg" href="#how"><?= icon('eye') ?> See how it works</a>
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
    </section>
    <!-- The curved bottom edge of the coloured area -->
    <svg class="hero-curve" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70 C 360 140 720 0 1080 50 C 1260 75 1360 60 1440 40 V120 H0 Z"/></svg>
</div>

<main id="main">
    <section class="land-section" id="how">
        <div class="section-intro">
            <h2>Three steps from broken to sorted</h2>
            <p>Everything happens on campus, and you can follow every step from your phone.</p>
        </div>
        <ol class="step-tiles">
            <li class="tile tile-mint">
                <span class="stat-icon"><?= icon('box') ?></span>
                <h3>Submit &amp; drop off</h3>
                <p>Describe the fault and add a photo, then bring the device to the Green Hub in Block E.</p>
            </li>
            <li class="tile tile-gold">
                <span class="stat-icon"><?= icon('wrench') ?></span>
                <h3>A volunteer repairs it</h3>
                <p>Trained student volunteers open it up, and every note they write appears on your timeline.</p>
            </li>
            <li class="tile tile-sky">
                <span class="stat-icon"><?= icon('recycle') ?></span>
                <h3>Collect or recycle</h3>
                <p>Pick it up within 7 days. If it can’t be fixed, you choose: take it back or let us recycle it.</p>
            </li>
        </ol>
    </section>

    <section class="land-section split" id="bring">
        <div>
            <h2>What you can bring</h2>
            <p class="muted">Points depend on the weight and type of the device. A repair earns double, because keeping a device in use is better than recycling it.</p>
            <p class="formula">points = weight (kg) × rate <span>× 2 if repaired</span></p>
        </div>
        <ul class="cat-list">
            <?php foreach ($categories as $c): ?>
                <li>
                    <span class="stat-icon tint-mint"><?= icon(category_icon_name($c['category_name'])) ?></span>
                    <span class="cat-name"><?= e($c['category_name']) ?></span>
                    <span class="cat-max">up to <?= (int) $c['max_points'] ?> pts</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="land-section rewards" id="rewards">
        <div class="reward-card">
            <h2>Points become cashback</h2>
            <p>Swap your points for cashback on the APCard linked to your student email. Volunteers earn points for every repair too.</p>
            <ul class="reward-chips">
                <?php foreach ($rewards as $r): ?>
                    <li><?= icon('star') ?> <?= (int) $r['points_required'] ?> pts = <?= rm($r['cash_value']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="challenge-card">
            <?php if ($challenge): ?>
                <p class="challenge-tag"><?= icon('flag') ?> Challenge running now</p>
                <h3><?= e($challenge['title']) ?></h3>
                <p><?= e($challenge['description']) ?></p>
                <p class="challenge-meta">Reach <?= e($challenge['target_kg'] + 0) ?> kg to earn <strong><?= (int) $challenge['bonus_points'] ?> bonus points</strong>. Ends <?= nice_date($challenge['end_date']) ?>.</p>
            <?php else: ?>
                <p class="challenge-tag"><?= icon('flag') ?> Challenges</p>
                <h3>No challenge is running right now</h3>
                <p>New challenges are posted on the announcement board. Everyone who reaches the target gets bonus points.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="land-section" id="news">
        <div class="section-intro">
            <h2>From the Green Hub</h2>
        </div>
        <div class="news-grid">
            <?php foreach ($news as $n): ?>
                <article class="news-item">
                    <time datetime="<?= e(date('Y-m-d', strtotime($n['created_at']))) ?>"><?= icon('calendar') ?> <?= nice_date($n['created_at']) ?></time>
                    <h3><?= e($n['title']) ?></h3>
                    <p><?= e($n['message']) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$news): ?><p class="muted">No announcements yet.</p><?php endif; ?>
        </div>
    </section>

    <section class="final-cta">
        <h2>Got a drawer full of dead gadgets?</h2>
        <p>Sign up with your APU email and submit your first device in two minutes.</p>
        <?php if (!$logged_in): ?>
            <a class="btn btn-primary btn-lg" href="register.php"><?= icon('user-plus') ?> Create a free account</a>
        <?php endif; ?>
    </section>
</main>

<footer class="land-footer">
    <p><strong>E-Waste &amp; Repair Café</strong>, Green Hub, Block E, Asia Pacific University</p>
    <p class="muted">RWDD Group 52, <?= date('Y') ?></p>
</footer>
</body>
</html>
