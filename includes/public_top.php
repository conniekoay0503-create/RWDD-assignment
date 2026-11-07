<?php
// Shared top part for the public pages (login / register). Set $page_title before including.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/main.js" defer></script>
</head>
<body class="landing auth">
<header class="land-nav">
    <a class="brand" href="index.php"><span class="brand-mark"><?= icon('recycle', 'icon-spin-hover') ?></span><span class="brand-text">E-Waste &amp; Repair Café</span></a>
    <div class="land-actions">
        <?php if ($page_title === 'Log In'): ?>
            <a class="btn btn-primary" href="register.php"><?= icon('user-plus') ?> Sign up</a>
        <?php else: ?>
            <a class="btn btn-primary" href="login.php"><?= icon('user') ?> Log in</a>
        <?php endif; ?>
    </div>
</header>
<main class="auth-page" id="main">
    <section class="auth-intro">
        <a class="back-home" href="index.php"><?= icon('arrow-left') ?> Back to homepage</a>
        <h1>Broken gadget?<br>Give it a second life.</h1>
        <p class="hero-sub">Repair first. Recycle right.</p>
        <p>Drop it at the Green Hub. A student volunteer tries to fix it. If it can’t be saved, it’s recycled properly and you still earn points.</p>
        <ol class="steps">
            <li><span class="stat-icon tint-mint"><?= icon('box') ?></span><div><strong>Submit &amp; drop off</strong><span>Tell us what’s wrong, then bring it to Block E.</span></div></li>
            <li><span class="stat-icon tint-gold"><?= icon('wrench') ?></span><div><strong>Track the repair</strong><span>See every step and every note from your volunteer.</span></div></li>
            <li><span class="stat-icon tint-sky"><?= icon('recycle') ?></span><div><strong>Collect or recycle</strong><span>Earn ×2 points for repairs, ×1 for recycling.</span></div></li>
        </ol>
    </section>
