<?php
/*
 * Categories - list  (Figure 25, page 1 of 3)
 * Points rule:  repaired = MIN(weight x multiplier x 2, max)   recycled = MIN(weight x multiplier, max)
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

// ---------- Delete (only when no device uses the category) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['categoryID'] ?? 0);
    if (db_value($conn, "SELECT COUNT(*) FROM devices WHERE categoryID = ?", 'i', [$id]) > 0) {
        set_flash('error', 'Devices already use this category, so it cannot be deleted. Edit it and untick "Active" to hide it instead.');
    } else {
        db_exec($conn, "DELETE FROM categories WHERE categoryID = ?", 'i', [$id]);
        set_flash('success', 'Category deleted.');
    }
    redirect('categories.php');
}

// Calculated columns come from the devices table
$categories = db_all($conn, "SELECT c.*,
                                    COUNT(d.deviceID)                        AS devices,
                                    AVG(d.weight_kg)                         AS avg_kg,
                                    COALESCE(SUM(d.status = 'Completed'), 0) AS repaired,
                                    COALESCE(SUM(d.status = 'Recycled'), 0)  AS recycled
                             FROM categories c
                             LEFT JOIN devices d ON d.categoryID = c.categoryID
                             GROUP BY c.categoryID
                             ORDER BY c.is_active DESC, c.category_name");

$page_title = 'Categories';
$active = 'categories';
require '../includes/admin_top.php';
page_head('tag', 'Categories & points', 'Repaired = weight × multiplier × 2 · Recycled = weight × multiplier · never more than the max.',
    '<a class="btn btn-primary" href="category_form.php">' . icon('plus') . ' Add category</a>');
?>
<?php if (!$categories) empty_state('tag', 'No categories yet.'); ?>
<section class="tile-grid">
    <?php foreach ($categories as $c): ?>
        <article class="cat-card <?= $c['is_active'] ? '' : 'is-off' ?>">
            <header>
                <span class="stat-icon tint-mint"><?= icon(category_icon_name($c['category_name'])) ?></span>
                <h2><?= e($c['category_name']) ?></h2>
                <span class="badge <?= $c['is_active'] ? 'badge-ok' : 'badge-off' ?>"><?= $c['is_active'] ? 'Active' : 'Hidden' ?></span>
            </header>
            <div class="cat-numbers">
                <div><span class="big-num">×<?= e($c['multiplier'] + 0) ?></span><small>points per kg</small></div>
                <div><span class="big-num"><?= (int) $c['max_points'] ?></span><small>max per device</small></div>
            </div>
            <ul class="mini-stats">
                <li title="Devices submitted"><?= icon('device') ?> <?= (int) $c['devices'] ?> devices</li>
                <li title="Average weight"><?= icon('scale') ?> <?= $c['avg_kg'] !== null ? number_format($c['avg_kg'], 2) . ' kg avg' : 'no weight yet' ?></li>
                <li title="Repaired"><?= icon('wrench') ?> <?= (int) $c['repaired'] ?> repaired</li>
                <li title="Recycled"><?= icon('recycle') ?> <?= (int) $c['recycled'] ?> recycled</li>
            </ul>
            <footer>
                <a class="btn btn-outline btn-sm" href="category_form.php?edit=<?= (int) $c['categoryID'] ?>"><?= icon('edit') ?> Edit</a>
                <?php if ($c['devices'] == 0): ?>
                    <form method="post" data-confirm="Delete <?= e($c['category_name']) ?>?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="categoryID" value="<?= (int) $c['categoryID'] ?>">
                        <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Delete</button>
                    </form>
                <?php else: ?>
                    <span class="lock-note" title="Devices use this category"><?= icon('lock') ?> In use</span>
                <?php endif; ?>
            </footer>
        </article>
    <?php endforeach; ?>
</section>
<?php require '../includes/admin_bottom.php'; ?>
