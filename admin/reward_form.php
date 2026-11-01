<?php
/*
 * Cashback - add / edit reward  (Figure 24, page 4 of 4)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['rewardID'] ?? 0);   // 0 = new reward
    $name   = trim($_POST['reward_name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $points = filter_var($_POST['points_required'] ?? '', FILTER_VALIDATE_INT);
    $cash   = filter_var($_POST['cash_value'] ?? '', FILTER_VALIDATE_FLOAT);
    $stock  = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

    $errors = [];
    if ($name === '' || strlen($name) > 100) $errors[] = 'Reward name is required (max 100 characters).';
    if ($desc === '' || strlen($desc) > 255) $errors[] = 'Description is required (max 255 characters).';
    if ($points === false || $points <= 0)   $errors[] = 'Points required must be a whole number above 0.';
    if ($cash === false || $cash <= 0)       $errors[] = 'Cash value must be above 0.';
    if ($stock === false || $stock < 0)      $errors[] = 'Stock must be 0 or more.';

    if ($errors) {
        foreach ($errors as $err) set_flash('error', $err);
        $_SESSION['old'] = $_POST;
        redirect('reward_form.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_exec($conn, "UPDATE rewards SET reward_name = ?, description = ?, points_required = ?, cash_value = ?, stock = ?
                        WHERE rewardID = ?", 'ssidii', [$name, $desc, $points, $cash, $stock, $id]);
        set_flash('success', 'Reward updated.');
    } else {
        db_exec($conn, "INSERT INTO rewards (reward_name, description, points_required, cash_value, stock) VALUES (?, ?, ?, ?, ?)",
            'ssidi', [$name, $desc, $points, $cash, $stock]);
        set_flash('success', 'Reward added.');
    }
    redirect('rewards.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one($conn, "SELECT * FROM rewards WHERE rewardID = ?", 'i', [(int) $_GET['edit']]);
}
$form = $_SESSION['old'] ?? $edit ?? [];
unset($_SESSION['old']);

$page_title = $edit ? 'Edit reward' : 'Add reward';
$active = 'cashback';
require '../includes/admin_top.php';
page_head($edit ? 'edit' : 'plus', $edit ? 'Edit reward' : 'Add a reward', 'Students see this in their rewards list.');
?>
<section class="card narrow">
    <form method="post" novalidate data-validate>
        <input type="hidden" name="rewardID" value="<?= (int) ($edit['rewardID'] ?? 0) ?>">
        <div class="field">
            <label for="reward_name"><?= icon('gift') ?> Reward name</label>
            <input type="text" id="reward_name" name="reward_name" maxlength="100" required value="<?= e($form['reward_name'] ?? '') ?>" placeholder="RM5 Cashback">
        </div>
        <div class="field">
            <label for="description"><?= icon('type') ?> Description</label>
            <input type="text" id="description" name="description" maxlength="255" required value="<?= e($form['description'] ?? 'Paid to the APCard linked to your email') ?>">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="points_required"><?= icon('star') ?> Points needed</label>
                <input type="number" id="points_required" name="points_required" min="1" step="1" required value="<?= e($form['points_required'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="cash_value"><?= icon('wallet') ?> Cash value (RM)</label>
                <input type="number" id="cash_value" name="cash_value" min="0.01" step="0.01" required value="<?= e($form['cash_value'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="stock"><?= icon('box') ?> Stock</label>
                <input type="number" id="stock" name="stock" min="0" step="1" required value="<?= e($form['stock'] ?? '0') ?>">
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Add reward' ?></button>
            <a class="btn btn-outline" href="rewards.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>
<?php require '../includes/admin_bottom.php'; ?>
