<?php
/*
 * Officer home - PLACEHOLDER.
 * Teammate replaces this page. Keep the first 3 lines so only a officer can open it.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer | E-Waste &amp; Repair Café</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body class="public">
<main class="placeholder card">
    <span class="role-tag">OFFICER</span>
    <h1>Welcome, <?= e($_SESSION['name']) ?></h1>
    <p class="muted">The officer pages are being built by a teammate.</p>
    <a class="btn btn-primary" href="../logout.php">Log out</a>
</main>
</body>
</html>
