<?php
/*
 * Register page  (Figure 1 - register branch)
 * Every new account becomes a Student. Volunteers / Officers are promoted by an Admin later.
 */
require 'config/db.php';
require 'includes/functions.php';

if (!empty($_SESSION['tp_number'])) {
    redirect(home_for($_SESSION['role']));
}

// This page does not use require_role(), so it checks the CSRF token itself
check_csrf();

$errors = [];
$old = ['name' => '', 'tp_number' => '', 'phone' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read and tidy the inputs
    $old['name']      = trim($_POST['name'] ?? '');
    $old['tp_number'] = strtoupper(trim($_POST['tp_number'] ?? ''));
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['email']     = strtolower(trim($_POST['email'] ?? ''));
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm_password'] ?? '';
    $terms            = !empty($_POST['terms']);

    // 1. All fields filled?
    if ($old['name'] === '' || $old['tp_number'] === '' || $old['phone'] === '' || $old['email'] === '' || $password === '' || $confirm === '') {
        $errors[] = 'Please fill in every field.';
    }
    // 2. Format checks
    if ($old['tp_number'] !== '' && !preg_match('/^TP[0-9]{6}$/', $old['tp_number'])) {
        $errors[] = 'TP number must be "TP" followed by 6 digits, e.g. TP012345.';
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9]{9,15}$/', $old['phone'])) {
        $errors[] = 'Phone number must contain 9 to 15 digits only.';
    }
    if (mb_strlen($old['name']) > 100) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }
    if ($password !== '' && mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the terms of use.';
    }

    // 3. Unique checks in the database (only if the format is already fine)
    if (!$errors) {
        if (db_value($conn, "SELECT COUNT(*) FROM users WHERE tp_number = ?", 's', [$old['tp_number']]) > 0) {
            $errors[] = 'This TP number is already registered.';
        }
        if (db_value($conn, "SELECT COUNT(*) FROM users WHERE email = ?", 's', [$old['email']]) > 0) {
            $errors[] = 'This email is already registered.';
        }
    }

    // 4. Save: users + students must both succeed, so use a transaction
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);   // never store the plain password
        $conn->begin_transaction();
        try {
            db_exec($conn, "INSERT INTO users (tp_number, name, email, password, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                'sssss', [$old['tp_number'], $old['name'], $old['email'], $hash, $old['phone']]);
            db_exec($conn, "INSERT INTO students (tp_number) VALUES (?)", 's', [$old['tp_number']]);
            $conn->commit();
            set_flash('success', 'Registration successful, please log in.');
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$page_title = 'Sign Up';
require 'includes/public_top.php';
?>
    <section class="auth-card" aria-labelledby="register-title">
        <h2 id="register-title">Create your account</h2>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate data-validate data-register>
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" maxlength="100" value="<?= e($old['name']) ?>" required autocomplete="name">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="tp_number">TP Number</label>
                    <input type="text" id="tp_number" name="tp_number" maxlength="8" placeholder="TP012345" pattern="[Tt][Pp][0-9]{6}"
                           value="<?= e($old['tp_number']) ?>" required data-error="Use TP followed by 6 digits">
                </div>
                <div class="field">
                    <label for="phone">Phone <small>For pickup and payout</small></label>
                    <input type="tel" id="phone" name="phone" maxlength="15" pattern="[0-9]{9,15}" value="<?= e($old['phone']) ?>"
                           required data-error="Digits only (9-15)" autocomplete="tel">
                </div>
            </div>
            <div class="field">
                <label for="email">Student email</label>
                <input type="email" id="email" name="email" maxlength="100" value="<?= e($old['email']) ?>" required autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Password <small>At least 8 characters</small></label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" data-strength>
                    <button type="button" class="btn-show" data-toggle-password="password">Show</button>
                </div>
                <div class="strength"><span></span></div>
                <small class="strength-text muted" aria-live="polite"></small>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" data-match="password" data-error="Passwords do not match">
                    <button type="button" class="btn-show" data-toggle-password="confirm_password">Show</button>
                </div>
            </div>
            <label class="check">
                <input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
                I agree to the terms of use and privacy notice, including that uncollected devices may be recycled after 7 days.
            </label>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>
        <p class="switch">Already have an account? <a href="login.php">Sign in</a></p>
        <p class="hint"><?= icon('heart') ?> <span>Want to volunteer? Sign up as a student first, then request the volunteer role from your dashboard. An admin approves it.</span></p>
    </section>
</main>
</body>
</html>
