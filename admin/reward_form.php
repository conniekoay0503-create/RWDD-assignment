<?php
/*
 * Cashback - add / edit reward  (Figure 24, page 4 of 4)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = trim($_POST['rewardID'] ?? '');   // '' = new reward
    $name   = trim($_POST['reward_name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $points = filter_var($_POST['points_required'] ?? '', FILTER_VALIDATE_INT);
    $cash   = filter_var($_POST['cash_value'] ?? '', FILTER_VALIDATE_FLOAT);
    $stock  = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Reward name is required (max 100 characters).';
    if ($desc === '' || mb_strlen($desc) > 255) $errors[] = 'Description is required (max 255 characters).';
    if ($points === false || $points <= 0 || $points > MAX_NUMBER_INPUT) $errors[] = 'Points required must be a whole number from 1 to ' . MAX_NUMBER_INPUT . '.';
    if ($cash === false || $cash <= 0 || $cash > 10000) $errors[] = 'Cash value must be from RM 0.01 to RM 10,000.';
    if ($stock === false || $stock < 0 || $stock > MAX_NUMBER_INPUT) $errors[] = 'Stock must be a whole number from 0 to ' . MAX_NUMBER_INPUT . '.';
    // Only one reward of the same cash value can be on offer at a time.
    // If another one still has stock, the admin must wait until it runs out (or add stock to that one instead).
    if ($cash !== false && $cash > 0) {
        $same = db_one($conn, "SELECT rewardID, reward_name, stock FROM rewards WHERE cash_value = ? AND stock > 0 AND rewardID <> ?",
            'ds', [$cash, $id]);
        if ($same) {
            $errors[] = $same['reward_name'] . ' (' . $same['rewardID'] . ') is still on offer with ' . $same['stock'] . ' left. '
                      . 'You can add another ' . rm($cash) . ' reward only after its stock runs out, or edit ' . $same['rewardID'] . ' to add more stock.';
        }
    }

    if ($errors) {
        foreach ($errors as $err) set_flash('error', $err);
        $_SESSION['old'] = $_POST;
        redirect('reward_form.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_exec($conn, "UPDATE rewards SET reward_name = ?, description = ?, points_required = ?, cash_value = ?, stock = ?
                        WHERE rewardID = ?", 'ssidis', [$name, $desc, $points, $cash, $stock, $id]);
        set_flash('success', 'Reward updated.');
    } else {
        $new_id = next_id($conn, 'rewards', 'rewardID', 'RW');
        db_exec($conn, "INSERT INTO rewards (rewardID, reward_name, description, points_required, cash_value, stock) VALUES (?, ?, ?, ?, ?, ?)",
            'sssidi', [$new_id, $name, $desc, $points, $cash, $stock]);
        set_flash('success', 'Reward added.');
    }
    redirect('rewards.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one($conn, "SELECT * FROM rewards WHERE rewardID = ?", 's', [$_GET['edit']]);
}
$form = $_SESSION['old'] ?? $edit ?? [];
unset($_SESSION['old']);

$page_title = $edit ? 'Edit reward' : 'Add reward';
$active = 'cashback';
require '../includes/admin_top.php';
page_head($edit ? 'edit' : 'plus', $edit ? 'Edit reward' : 'Add a reward', 'Students see this in their rewards list.');
?>
<section class="card narrow">
    <form method="post" novalidate data-validate
          data-confirm="<?= $edit ? 'Save the changes to ' . e($edit['reward_name']) . '?' : 'Add this new reward? Students will see it straight away.' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="rewardID" value="<?= e($edit['rewardID'] ?? '') ?>">
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
                <input type="number" id="points_required" name="points_required" min="1" max="<?= MAX_NUMBER_INPUT ?>" step="1" required value="<?= e($form['points_required'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="cash_value"><?= icon('wallet') ?> Cash value (RM)</label>
                <input type="number" id="cash_value" name="cash_value" min="0.01" max="10000" step="0.01" required value="<?= e($form['cash_value'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="stock"><?= icon('box') ?> Stock</label>
                <input type="number" id="stock" name="stock" min="0" max="<?= MAX_NUMBER_INPUT ?>" step="1" required value="<?= e($form['stock'] ?? '0') ?>">
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Add reward' ?></button>
            <a class="btn btn-outline" href="rewards.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>
<?php require '../includes/bottom.php'; ?>
