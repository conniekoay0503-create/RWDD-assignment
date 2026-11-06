<?php
/*
 * Users - all users  (Figure 23, page 1 of 3)
 * Activate / deactivate accounts. Inactive users cannot log in.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');
$me = $_SESSION['tp_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_active') {
    $tp     = $_POST['tp_number'] ?? '';
    $active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
    if ($tp === $me) {
        set_flash('error', 'You cannot deactivate your own account.');
    } else {
        db_exec($conn, "UPDATE users SET is_active = ? WHERE tp_number = ?", 'is', [$active, $tp]);
        set_flash('success', $tp . ($active ? ' has been activated.' : ' has been deactivated and can no longer log in.'));
    }
    redirect('users.php');
}

// The role comes from whichever role table has the user's TP number
$users = db_all($conn, "SELECT u.tp_number, u.name, u.email, u.phone, u.is_active, u.created_at,
                               CASE WHEN a.tp_number IS NOT NULL THEN 'Admin'
                                    WHEN o.tp_number IS NOT NULL THEN 'Officer'
                                    WHEN v.tp_number IS NOT NULL THEN 'Volunteer'
                                    WHEN s.tp_number IS NOT NULL THEN 'Student'
                                    ELSE 'No role' END AS role
                        FROM users u
                        LEFT JOIN admins a     ON a.tp_number = u.tp_number
                        LEFT JOIN officers o   ON o.tp_number = u.tp_number
                        LEFT JOIN volunteers v ON v.tp_number = u.tp_number
                        LEFT JOIN students s   ON s.tp_number = u.tp_number
                        ORDER BY u.name");
$role_count = array_count_values(array_column($users, 'role'));
$role_icon  = ['Student' => 'user', 'Volunteer' => 'heart', 'Officer' => 'recycle', 'Admin' => 'shield', 'No role' => 'user'];

$page_title = 'Users';
$active = 'users';
require '../includes/admin_top.php';
page_head('users', 'All users', 'Activate or deactivate accounts. Inactive users cannot log in.');
?>
<section class="stat-grid four">
    <?php foreach (['Student' => 'tint-sky', 'Volunteer' => 'tint-rose', 'Officer' => 'tint-mint', 'Admin' => 'tint-gold'] as $r => $tint): ?>
        <div class="stat-card row"><span class="stat-icon <?= $tint ?>"><?= icon($role_icon[$r]) ?></span><div><strong class="stat-value"><?= $role_count[$r] ?? 0 ?></strong><span class="stat-title"><?= $r ?>s</span></div></div>
    <?php endforeach; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= icon('list') ?> <?= count($users) ?> accounts</h2>
        <div class="filters">
            <label class="search-box"><?= icon('search') ?><input type="search" placeholder="Name, TP or email" data-filter-table="user-table" aria-label="Search users"></label>
            <label class="select-box"><?= icon('filter') ?>
                <select data-filter-role="user-table" aria-label="Filter by role">
                    <option value="">All roles</option>
                    <option>Student</option><option>Volunteer</option><option>Officer</option><option>Admin</option>
                </select>
            </label>
        </div>
    </div>
    <div class="table-wrap">
        <table id="user-table">
            <thead><tr><th>Name</th><th>TP number</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr data-role="<?= e($u['role']) ?>">
                    <td><span class="name-cell"><span class="avatar small"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><?= e($u['name']) ?></span></td>
                    <td><?= e($u['tp_number']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge role-<?= strtolower($u['role']) ?>"><?= icon($role_icon[$u['role']]) ?> <?= e($u['role']) ?></span></td>
                    <td><span class="badge <?= $u['is_active'] ? 'badge-ok' : 'badge-off' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= nice_date($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['tp_number'] === $me): ?>
                            <small class="muted">You</small>
                        <?php else: ?>
                            <form method="post" data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($u['name']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="tp_number" value="<?= e($u['tp_number']) ?>">
                                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                                <?php if ($u['is_active']): ?>
                                    <button class="btn btn-danger btn-sm"><?= icon('lock') ?> Deactivate</button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm"><?= icon('key') ?> Activate</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted empty-msg" hidden data-empty-for="user-table">No users match your search.</p>
</section>
<?php require '../includes/admin_bottom.php'; ?>
