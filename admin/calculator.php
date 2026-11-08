<?php
/*
 * Categories - points calculator  (Figure 25, page 3 of 3)
 * Only JavaScript: nothing is saved. It uses the same rule as the server.
 */
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'admin', '../');

$categories = db_all($conn, "SELECT categoryID, category_name, multiplier, max_points FROM categories ORDER BY is_active DESC, category_name");

$page_title = 'Points calculator';
$active = 'categories';
require '../includes/admin_top.php';
page_head('calculator', 'Points calculator', 'Try a weight to see how many points a device would earn.');
?>
<section class="calc" data-calculator>
    <div class="card calc-input">
        <div class="field">
            <label for="calc_cat"><?= icon('tag') ?> Category</label>
            <select id="calc_cat" data-calc-category>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c['categoryID']) ?>" data-multiplier="<?= e($c['multiplier'] + 0) ?>" data-max="<?= (int) $c['max_points'] ?>"><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="calc_kg"><?= icon('scale') ?> Weight (kg)</label>
            <input type="number" id="calc_kg" min="0.01" max="50" step="0.01" value="0.20" data-calc-weight>
            <input type="range" min="0.01" max="3" step="0.01" value="0.20" data-calc-slider aria-label="Weight slider">
        </div>
        <p class="calc-rule" data-calc-rule></p>
        <div class="calc-explain">
            <h2><?= icon('info') ?> How points are worked out</h2>
            <ul>
                <li><b>Multiplier</b> = points for 1 kg of this category.</li>
                <li><b>Points</b> = weight (kg) × multiplier, rounded down.</li>
                <li>A <b>repaired</b> device gives the student <b>double</b> (× 2), because repairing is better than recycling.</li>
                <li>No one gets more than the category's <b>max per device</b>.</li>
            </ul>
        </div>
    </div>
    <div class="calc-results">
        <!-- main.js fills data-calc-out (the points) and data-calc-work (how it was worked out) -->
        <div class="result-card tint-mint" data-calc-card="repaired">
            <?= icon('wrench') ?>
            <span>Student · repaired <b>×2</b></span>
            <strong data-calc-out>0</strong>
            <em class="max-tag">Max reached</em>
            <small data-calc-work></small>
        </div>
        <div class="result-card tint-sky" data-calc-card="recycled">
            <?= icon('recycle') ?>
            <span>Student · recycled</span>
            <strong data-calc-out>0</strong>
            <em class="max-tag">Max reached</em>
            <small data-calc-work></small>
        </div>
        <div class="result-card tint-gold" data-calc-card="volunteer">
            <?= icon('heart') ?>
            <span>Volunteer · per repair</span>
            <strong data-calc-out>0</strong>
            <em class="max-tag">Max reached</em>
            <small data-calc-work></small>
        </div>
    </div>
</section>
<?php require '../includes/bottom.php'; ?>
