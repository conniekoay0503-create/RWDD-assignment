<?php
/*
 * My profile - saving (shared by student/profile.php and officer/profile.php).
 * Same rules as admin/profile.php. Run it BEFORE the page prints anything, because it redirects.
 * Needs: $conn, $me (the logged-in TP number). The CSRF token was already checked by require_role().
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Personal details ----------
    if ($action === 'details') {
        $name  = trim($_POST['name'] ?? '');
        $phone = clean_phone($_POST['phone'] ?? '');   // removes spaces and dashes (functions.php)
        if ($name === '' || mb_strlen($name) > 100) {
            set_flash('error', 'Name is required (max 100 characters).');
        } elseif (!valid_phone($phone)) {   // same rule as register.php
            set_flash('error', PHONE_ERROR);
        } else {
            db_exec($conn, "UPDATE users SET name = ?, phone = ? WHERE tp_number = ?", 'sss', [$name, $phone, $me]);
            $_SESSION['name'] = $name;   // so the top bar shows the new name straight away
            set_flash('success', 'Your details have been saved.');
        }
        redirect('profile.php#details');
    }

    // ---------- Change password ----------
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $hash    = db_value($conn, "SELECT password FROM users WHERE tp_number = ?", 's', [$me]);

        if (!password_verify($current, $hash)) {
            set_flash('error', 'Your current password is wrong.');
        } elseif (mb_strlen($new) < 8) {
            set_flash('error', 'The new password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            set_flash('error', 'The new passwords do not match.');
        } elseif ($new === $current) {
            set_flash('error', 'The new password must be different from the current one.');
        } else {
            db_exec($conn, "UPDATE users SET password = ? WHERE tp_number = ?", 'ss', [password_hash($new, PASSWORD_DEFAULT), $me]);
            set_flash('success', 'Password changed.');
        }
        redirect('profile.php#password');
    }
}

$user = db_one($conn, "SELECT * FROM users WHERE tp_number = ?", 's', [$me]);
