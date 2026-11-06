<?php
/*
 * My profile  (Figure 29)
 * Tabs (switched with JavaScript): Personal details · Password · My role · Preferences · Account
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Personal details ----------
    if ($action === 'details') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || strlen($name) > 100) {
            set_flash('error', 'Name is required (max 100 characters).');
        } elseif (!preg_match('/^[0-9]{9,15}$/', $phone)) {
            set_flash('error', 'Phone number must be 9 to 15 digits, numbers only.');
        } else {
            db_exec($conn, "UPDATE users SET name = ?, phone = ? WHERE tp_number = ?", 'sss', [$name, $phone, $me]);
            $_SESSION['name'] = $name;   // so the sidebar shows the new name straight away
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
        } elseif (strlen($new) < 8) {
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

$user = db_one($conn, "SELECT u.*, a.assigned_at, b.name AS assigned_by_name
                       FROM users u
                       JOIN admins a ON a.tp_number = u.tp_number
                       LEFT JOIN users b ON b.tp_number = a.assigned_by
                       WHERE u.tp_number = ?", 's', [$me]);

$page_title = 'My profile';
$active = '';
require '../includes/admin_top.php';
page_head('user', 'My profile', 'Your details, password and display preferences.');
?>

<div class="profile-layout" data-tabs>
    <nav class="tab-list" role="tablist" aria-label="Profile sections">
        <button type="button" role="tab" data-tab="details"><?= icon('id') ?> Personal details</button>
        <button type="button" role="tab" data-tab="password"><?= icon('key') ?> Password</button>
        <button type="button" role="tab" data-tab="role"><?= icon('shield') ?> My role</button>
        <button type="button" role="tab" data-tab="preferences"><?= icon('sliders') ?> Preferences</button>
        <button type="button" role="tab" data-tab="account"><?= icon('lock') ?> Account</button>
    </nav>

    <section class="card tab-panel" id="details" role="tabpanel">
        <h2>Personal details</h2>
        <form method="post" novalidate data-validate data-confirm="Save your new details?">
            <input type="hidden" name="action" value="details">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" maxlength="100" required value="<?= e($user['name']) ?>">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="tp">TP number</label>
                    <input type="text" id="tp" value="<?= e($user['tp_number']) ?>" disabled>
                </div>
                <div class="field">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" required pattern="[0-9]{9,15}" value="<?= e($user['phone']) ?>" data-error="9 to 15 digits, numbers only">
                </div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                <small class="muted">Your email and TP number identify you, so they cannot be changed here.</small>
            </div>
            <button class="btn btn-primary"><?= icon('check') ?> Save details</button>
        </form>
    </section>

    <section class="card tab-panel" id="password" role="tabpanel">
        <h2>Change password</h2>
        <form method="post" novalidate data-validate data-confirm="Change your password? Use the new one the next time you log in.">
            <input type="hidden" name="action" value="password">
            <div class="field">
                <label for="current_password">Current password</label>
                <div class="password-wrap">
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    <button type="button" class="btn-show" data-toggle-password="current_password">Show</button>
                </div>
            </div>
            <div class="field">
                <label for="new_password">New password</label>
                <div class="password-wrap">
                    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" data-strength>
                    <button type="button" class="btn-show" data-toggle-password="new_password">Show</button>
                </div>
                <div class="strength"><span></span></div>
                <small class="strength-text muted" aria-live="polite">At least 8 characters</small>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required data-match="new_password" data-error="Passwords do not match" autocomplete="new-password">
            </div>
            <button class="btn btn-primary"><?= icon('key') ?> Change password</button>
        </form>
    </section>

    <section class="card tab-panel" id="role" role="tabpanel">
        <h2>My role</h2>
        <dl class="info-list">
            <dt>Role</dt><dd><span class="badge">Admin</span></dd>
            <dt>Assigned by</dt><dd><?= e($user['assigned_by_name'] ?? 'System (first admin)') ?></dd>
            <dt>Assigned on</dt><dd><?= nice_date($user['assigned_at']) ?></dd>
        </dl>
        <p class="muted">Admins manage categories, challenges, cashback, users, announcements and reports.</p>
    </section>

    <section class="card tab-panel" id="preferences" role="tabpanel">
        <h2>Preferences</h2>
        <p class="muted">Saved in this browser only.</p>
        <div class="pref-row">
            <span>Dark mode</span>
            <button type="button" class="btn btn-outline btn-sm" data-theme-toggle><?= icon('moon') ?> Toggle dark mode</button>
        </div>
        <div class="pref-row">
            <span>Text size</span>
            <div class="text-size">
                <button type="button" data-text-size="-1" aria-label="Smaller text">A-</button>
                <span data-text-size-label>100%</span>
                <button type="button" data-text-size="1" aria-label="Larger text">A+</button>
            </div>
        </div>
        <div class="pref-row">
            <span>Reset to default</span>
            <button type="button" class="btn btn-outline btn-sm" data-reset-prefs><?= icon('history') ?> Reset</button>
        </div>
    </section>

    <section class="card tab-panel" id="account" role="tabpanel">
        <h2>Account</h2>
        <dl class="info-list">
            <dt>Status</dt><dd><span class="badge badge-ok">Active</span></dd>
            <dt>Email</dt><dd><?= e($user['email']) ?></dd>
            <dt>Member since</dt><dd><?= nice_date($user['created_at']) ?></dd>
        </dl>
        <a class="btn btn-danger" href="../logout.php" data-confirm="Log out now?"><?= icon('logout') ?> Log out</a>
    </section>
</div>
<?php require '../includes/admin_bottom.php'; ?>
