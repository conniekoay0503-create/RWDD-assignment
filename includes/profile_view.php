<?php
/*
 * My profile - the page (shared by student/profile.php and officer/profile.php). Same layout as admin/profile.php.
 * Needs: $user (from profile_actions.php), $role_name, $role_rows (['Assigned by' => '...', ...]), $role_text.
 * Tabs are switched by main.js (data-tabs).
 */
?>
<div class="profile-layout" data-tabs>
    <nav class="tab-list" role="tablist" aria-label="Profile sections">
        <button type="button" role="tab" data-tab="details"><?= icon('id') ?> Personal details</button>
        <button type="button" role="tab" data-tab="password"><?= icon('key') ?> Password</button>
        <button type="button" role="tab" data-tab="role"><?= icon('shield') ?> My role</button>
        <button type="button" role="tab" data-tab="account"><?= icon('lock') ?> Account</button>
    </nav>

    <section class="card tab-panel" id="details" role="tabpanel">
        <h2>Personal details</h2>
        <form method="post" novalidate data-validate data-confirm="Save your new details?">
            <?= csrf_field() ?>
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
            <?= csrf_field() ?>
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
            <dt>Role</dt><dd><span class="badge"><?= e($role_name) ?></span></dd>
            <?php foreach ($role_rows as $label => $value): ?>
                <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
            <?php endforeach; ?>
        </dl>
        <p class="muted"><?= e($role_text) ?></p>
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
