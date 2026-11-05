<?php
/*
 * Login page  (Figure 1 - login branch, Figure 2 - role routing)
 */
require 'config/db.php';
require 'includes/functions.php';

// Already logged in? Go straight to the dashboard
if (!empty($_SESSION['tp_number'])) {
    redirect(home_for($_SESSION['role']));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // Figure 1: find the account by email
        $user = db_one($conn, "SELECT tp_number, name, password, is_active FROM users WHERE email = ?", 's', [$email]);

        // Authenticated? password_verify() compares the typed password with the stored hash
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ((int) $user['is_active'] !== 1) {
            // Figure 2: is_active = 1 ?
            $error = 'Account deactivated. Please contact an Admin.';
        } else {
            // Figure 2: find the role (admins -> officers -> volunteers -> students)
            $role = find_role($conn, $user['tp_number']);
            if ($role === null) {
                $error = 'No role assigned to this account. Please contact an Admin.';
            } else {
                // Create the session only after every check has passed
                session_regenerate_id(true);           // new session ID prevents session fixation
                $_SESSION['tp_number'] = $user['tp_number'];
                $_SESSION['name']      = $user['name'];
                $_SESSION['role']      = $role;

                // "Keep me signed in": make the session cookie last 30 days instead of until the browser closes
                if (!empty($_POST['remember'])) {
                    setcookie(session_name(), session_id(), [
                        'expires'  => time() + 60 * 60 * 24 * 30,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
                redirect(home_for($role));
            }
        }
    }
}

$page_title = 'Log In';
require 'includes/public_top.php';
?>
    <section class="auth-card" aria-labelledby="login-title">
        <h2 id="login-title">Welcome back!</h2>
        <p class="quote"><em>"Don't replace it. Repair it."</em><br>Start repairing today and join the movement for a cleaner future.</p>

        <?php show_flash(); ?>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate data-validate>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="btn-show" data-toggle-password="password">Show</button>
                </div>
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <p class="switch">Not a member? <a href="register.php">Register now</a></p>
    </section>
</main>
</body>
</html>
