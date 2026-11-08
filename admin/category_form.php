<?php
/*
 * Categories - add / edit form
 * category_form.php           -> add a new category
 * category_form.php?edit=3    -> edit category 3
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = trim($_POST['categoryID'] ?? '');   // '' = new category
    $name   = trim($_POST['category_name'] ?? '');
    $mult   = filter_var($_POST['multiplier'] ?? '', FILTER_VALIDATE_FLOAT);
    $max    = filter_var($_POST['max_points'] ?? '', FILTER_VALIDATE_INT);
    $active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if ($name === '' || mb_strlen($name) > 50)             $errors[] = 'Category name is required (max 50 characters).';
    if ($mult === false || $mult <= 0 || $mult > 99999) $errors[] = 'Multiplier must be a number above 0.';
    if ($max === false || $max <= 0 || $max > MAX_NUMBER_INPUT) $errors[] = 'Max points must be a whole number from 1 to ' . MAX_NUMBER_INPUT . '.';
    // Name must be unique (ignore the category we are editing)
    if (db_value($conn, "SELECT COUNT(*) FROM categories WHERE category_name = ? AND categoryID <> ?", 'ss', [$name, $id]) > 0) {
        $errors[] = 'A category with this name already exists.';
    }

    if ($errors) {
        foreach ($errors as $err) set_flash('error', $err);
        $_SESSION['old'] = $_POST;   // keep what the admin typed
        redirect('category_form.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        db_exec($conn, "UPDATE categories SET category_name = ?, multiplier = ?, max_points = ?, is_active = ? WHERE categoryID = ?",
            'sdiis', [$name, $mult, $max, $active, $id]);
        set_flash('success', 'Category updated. The new points apply to devices finished from now on.');
    } else {
        $new_id = next_id($conn, 'categories', 'categoryID', 'C');
        db_exec($conn, "INSERT INTO categories (categoryID, category_name, multiplier, max_points, is_active) VALUES (?, ?, ?, ?, ?)",
            'ssdii', [$new_id, $name, $mult, $max, $active]);
        set_flash('success', 'Category added.');
    }
    redirect('categories.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one($conn, "SELECT * FROM categories WHERE categoryID = ?", 's', [$_GET['edit']]);
}
$form = $_SESSION['old'] ?? $edit ?? [];
unset($_SESSION['old']);

$page_title = $edit ? 'Edit category' : 'Add category';
$active = 'categories';
require '../includes/admin_top.php';
page_head($edit ? 'edit' : 'plus', $edit ? 'Edit ' . $edit['category_name'] : 'Add a category', 'Students choose a category when they submit a device.');
?>
<section class="card narrow">
    <form method="post" novalidate data-validate
          data-confirm="<?= $edit ? 'Save the changes to ' . e($edit['category_name']) . '?' : 'Add this new category?' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="categoryID" value="<?= e($edit['categoryID'] ?? '') ?>">
        <div class="field">
            <label for="category_name"><?= icon('tag') ?> Category name</label>
            <input type="text" id="category_name" name="category_name" maxlength="50" required value="<?= e($form['category_name'] ?? '') ?>" placeholder="e.g. Smartwatch">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="multiplier"><?= icon('percent') ?> Multiplier (points per kg)</label>
                <input type="number" id="multiplier" name="multiplier" min="0.01" step="0.01" required value="<?= e($form['multiplier'] ?? '') ?>">
                <small class="field-hint">How many points <b>1 kg</b> of this type is worth.
                    e.g. 500 → a 0.2 kg phone gets 0.2 × 500 = <b>100</b> points (repaired: × 2 = 200).</small>
            </div>
            <div class="field">
                <label for="max_points"><?= icon('star') ?> Max points per device</label>
                <input type="number" id="max_points" name="max_points" min="1" max="<?= MAX_NUMBER_INPUT ?>" step="1" required value="<?= e($form['max_points'] ?? '') ?>">
                <small class="field-hint">The most one device can earn, so a very heavy device cannot earn too many points.</small>
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" <?= ($form['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="toggle" aria-hidden="true"></span>
            <span>Active <small class="muted">(students can choose it)</small></span>
        </label>
        <div class="btn-row">
            <button class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Add category' ?></button>
            <a class="btn btn-outline" href="categories.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>
<?php require '../includes/bottom.php'; ?>
