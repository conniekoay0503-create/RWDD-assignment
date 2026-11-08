<?php
/*
 * My profile - 5 tabs (wireframes Figures 53-57)
 * The tabs are buttons handled by main.js (data-tabs / data-tab), so switching tab does not reload the page.
 * Two forms POST here: "details" and "password". The other tabs only show information.
 */
require '../config/db.php';
require '../includes/functions.php';
require_once '../includes/volunteer_logic.php';
require_role($conn, 'volunteer', '../');
$me = $_SESSION['tp_number'];

$errors = [];
$typed  = [];   // what the user typed, shown again when there is an error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if ($form === 'details') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $len   = mb_strlen($name);

        if ($len < 2 || $len > 100)                                            $errors['name']  = 'Name must be 2 to 100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errors['email'] = 'Please enter a valid email address.';
        if (!preg_match('/^[0-9+\- ]{7,15}$/', $phone))                        $errors['phone'] = 'Phone: 7 to 15 characters, digits, +, - or spaces only.';

        if (!isset($errors['email']) && db_value($conn, "SELECT COUNT(*) FROM users WHERE email = ? AND tp_number <> ?", 'ss', [$email, $me]) > 0) {
            $errors['email'] = 'That email is already used by another account.';
        }
        if (!$errors) {
            db_exec($conn, "UPDATE users SET name = ?, email = ?, phone = ? WHERE tp_number = ?", 'ssss', [$name, $email, $phone, $me]);
            $_SESSION['name'] = $name;
            set_flash('success', 'Your details were saved.');
            redirect('profile.php#details');
        }
        $typed = ['name' => $name, 'email' => $email, 'phone' => $phone];

    } elseif ($form === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $con = (string) ($_POST['confirm'] ?? '');
        $hash = (string) db_value($conn, "SELECT password FROM users WHERE tp_number = ?", 's', [$me]);

        if (!password_verify($cur, $hash)) $errors['current'] = 'Current password is not correct.';
        if (mb_strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['new'] = 'New password needs at least 8 characters, with a letter and a number.';
        } elseif ($new === $cur) {
            $errors['new'] = 'The new password must be different from the current one.';
        }
        if ($new !== $con) $errors['confirm'] = 'The two new passwords do not match.';

        if (!$errors) {
            db_exec($conn, "UPDATE users SET password = ? WHERE tp_number = ?", 'ss', [password_hash($new, PASSWORD_DEFAULT), $me]);
            session_regenerate_id(true);
            set_flash('success', 'Password updated.');
            redirect('profile.php#password');
        }
    }
}

$user = db_one($conn, "SELECT name, tp_number, email, phone, is_active FROM users WHERE tp_number = ?", 's', [$me]);
$val  = $typed ?: ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']];
$role = db_one($conn, "SELECT v.assigned_at, a.name AS admin_name FROM volunteers v
                         LEFT JOIN users a ON a.tp_number = v.assigned_by WHERE v.tp_number = ?", 's', [$me]);
$approved_at = db_value($conn, "SELECT reviewed_at FROM volunteer_request WHERE tp_number = ? AND status = 'approved'
                                 ORDER BY reviewed_at DESC LIMIT 1", 's', [$me]);

$tabs = ['details' => ['Personal details', 'user'], 'password' => ['Password', 'lock'], 'roles' => ['My roles', 'shield'], 'account' => ['Account', 'id']];

$page_title = 'My profile';
$active = 'none';   // the profile is not a menu item, so nothing is highlighted
require '../includes/volunteer_top.php';

page_head('user', 'My profile', 'Your details and password.');

// Short helper: the error text under a field
function vol_err($errors, $key) { return isset($errors[$key]) ? '<small class="field-error">' . e($errors[$key]) . '</small>' : ''; }
?>

<div class="profile-layout" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Profile sections">
        <?php foreach ($tabs as $key => [$text, $ico]): ?>
            <button type="button" role="tab" data-tab="<?= $key ?>" aria-selected="false"><?= icon($ico) ?> <?= e($text) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <section class="tab-panel" id="details" hidden>
            <h2 class="card-title"><?= icon('user') ?> Personal details</h2>
            <form method="post" action="profile.php#details" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="details">
                <div class="field <?= isset($errors['name']) && ($_POST['form'] ?? '') === 'details' ? 'has-error' : '' ?>">
                    <label for="p-name">Full name</label>
                    <input id="p-name" type="text" name="name" value="<?= e($val['name']) ?>" maxlength="100" required minlength="2">
                    <?= ($_POST['form'] ?? '') === 'details' ? vol_err($errors, 'name') : '' ?>
                </div>
                <div class="field">
                    <label for="p-tp">TP number</label>
                    <input id="p-tp" type="text" value="<?= e($user['tp_number']) ?>" readonly>
                    <small class="muted">Your TP number cannot be changed.</small>
                </div>
                <div class="field">
                    <label for="p-email">Email</label>
                    <input id="p-email" type="email" name="email" value="<?= e($val['email']) ?>" maxlength="100" required>
                    <?= ($_POST['form'] ?? '') === 'details' ? vol_err($errors, 'email') : '' ?>
                </div>
                <div class="field">
                    <label for="p-phone">Phone</label>
                    <input id="p-phone" type="tel" name="phone" value="<?= e($val['phone']) ?>" maxlength="15" required pattern="[0-9+\- ]{7,15}"
                           data-error="Phone: 7 to 15 characters, digits, +, - or spaces only.">
                    <?= ($_POST['form'] ?? '') === 'details' ? vol_err($errors, 'phone') : '' ?>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save changes</button>
                    <a class="btn btn-outline" href="profile.php#details">Discard</a>
                </div>
            </form>
        </section>

        <section class="tab-panel" id="password" hidden>
            <h2 class="card-title"><?= icon('lock') ?> Change password</h2>
            <form method="post" action="profile.php#password" autocomplete="off" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">
                <?php foreach (['current' => 'Current password', 'new' => 'New password', 'confirm' => 'Confirm new password'] as $k => $t): ?>
                    <div class="field">
                        <label for="pw-<?= $k ?>"><?= $t ?></label>
                        <div class="password-wrap">
                            <input id="pw-<?= $k ?>" type="password" name="<?= $k ?>" required
                                   <?= $k === 'new' ? 'minlength="8" data-strength' : '' ?>
                                   <?= $k === 'confirm' ? 'data-match="pw-new" data-error="The two new passwords do not match."' : '' ?>>
                            <button type="button" class="link-btn" data-toggle-password="pw-<?= $k ?>">Show</button>
                        </div>
                        <?php if ($k === 'new'): ?><div class="strength"><span></span></div><small class="strength-text muted">At least 8 characters</small><?php endif; ?>
                        <?= ($_POST['form'] ?? '') === 'password' ? vol_err($errors, $k) : '' ?>
                    </div>
                <?php endforeach; ?>
                <p class="muted">At least 8 characters, with a letter and a number.</p>
                <div class="btn-row"><button type="submit" class="btn btn-primary"><?= icon('lock') ?> Update password</button></div>
            </form>
        </section>

        <section class="tab-panel" id="roles" hidden>
            <h2 class="card-title"><?= icon('shield') ?> My roles</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Role</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                <?php if ($role): ?>
                    <tr>
                        <td>Repair Volunteer</td>
                        <td><span class="badge <?= $user['is_active'] ? 'badge-ok' : 'badge-off' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>Since <?= e(nice_date($role['assigned_at'])) ?><?= $role['admin_name'] ? ', assigned by ' . e($role['admin_name']) : '' ?>.
                            <?= $approved_at ? 'Request approved on ' . e(nice_date($approved_at)) . '.' : '' ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="3" class="muted">No role found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>
        </section>

        <section class="tab-panel" id="account" hidden>
            <h2 class="card-title"><?= icon('id') ?> Account</h2>
            <div class="pref-row">
                <div><strong>Status</strong><p class="muted m-0"><?= $user['is_active'] ? 'Active volunteer, can claim devices' : 'Inactive account, cannot claim devices' ?></p></div>
            </div>
            <div class="pref-row">
                <div><strong>Signed in as</strong><p class="muted m-0"><?= e($user['email']) ?></p></div>
                <a class="btn btn-outline" href="../logout.php" data-confirm="Log out now?"><?= icon('logout') ?> Log out</a>
            </div>
        </section>
    </div>
</div>

<?php require '../includes/bottom.php'; ?>
