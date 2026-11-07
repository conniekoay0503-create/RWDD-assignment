<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

$records = db_all($conn, "
    SELECT d.deviceID, d.device_name, d.weight_kg,
           d.disposal_method, d.status_changed_at,
           c.category_name, c.multiplier, c.max_points,
           s.name AS student_name,
           o.name AS officer_name,
           COALESCE(p.points, 0) AS points
    FROM devices d
    JOIN categories c ON d.categoryID = c.categoryID
    JOIN users s ON d.submitted_by = s.tp_number
    LEFT JOIN users o ON d.processed_by = o.tp_number
    LEFT JOIN (
        SELECT deviceID, SUM(points) AS points
        FROM points_transactions
        WHERE txn_type = 'earn'
            AND description LIKE 'Recycling reward for device %'
        GROUP BY deviceID
    ) p ON d.deviceID = p.deviceID
    WHERE d.status = 'Recycled'
    ORDER BY d.status_changed_at DESC, d.deviceID DESC
");

$categories = array_unique(array_column($records, 'category_name'));
sort($categories);

$totalWeight = array_sum(array_map(
    fn($r) => (float)$r['weight_kg'],
    $records
));

$totalPoints = array_sum(array_map(
    fn($r) => (int)$r['points'],
    $records
));

$page_title = 'Recycling History';
$active = 'recycling';
require '../includes/officer_top.php';
?>

<?php
officer_page_head(
    'history',
    'Recycling History',
    'View completed recycling records and student rewards.',
    '<a href="recycling_queue.php" class="btn btn-outline">'
    . icon('arrow-left') . ' Recycling Queue</a>'
);
?>

<section class="stat-grid three">
    <div class="stat-card">
        <span class="stat-icon tint-mint"><?= icon('recycle') ?></span>
        <strong class="stat-value"><?= count($records) ?></strong>
        <span class="stat-title">Devices Recycled</span>
    </div>

    <div class="stat-card">
        <span class="stat-icon tint-sky"><?= icon('leaf') ?></span>
        <strong class="stat-value"><?= number_format($totalWeight, 2) ?> kg</strong>
        <span class="stat-title">Total Recycled Weight</span>
    </div>

    <div class="stat-card">
        <span class="stat-icon tint-gold"><?= icon('star') ?></span>
        <strong class="stat-value"><?= number_format($totalPoints) ?></strong>
        <span class="stat-title">Points Awarded</span>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= icon('history') ?> Completed Recycling</h2>

        <label class="search-box">
            <?= icon('search') ?>
            <input type="search" id="historySearch"
                   placeholder="Search records..."
                   aria-label="Search recycling history">
        </label>

        <select id="categoryFilter" aria-label="Filter by category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category) ?>">
                    <?= e($category) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Category</th>
                    <th>Student</th>
                    <th>Weight</th>
                    <th>Disposal Method</th>
                    <th>Points</th>
                    <th>Processed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                    <tr class="history-row"
                        data-category="<?= e($r['category_name']) ?>"
                        data-search="<?= e(strtolower(
                            $r['deviceID'] . ' ' .
                            $r['device_name'] . ' ' .
                            $r['category_name'] . ' ' .
                            $r['student_name'] . ' ' .
                            ($r['officer_name'] ?? '') . ' ' .
                            ($r['disposal_method'] ?? '')
                        )) ?>">

                        <td><strong><?= e($r['deviceID']) ?></strong></td>
                        <td><?= e($r['device_name']) ?></td>
                        <td><?= e($r['category_name']) ?></td>
                        <td><?= e($r['student_name']) ?></td>
                        <td><?= number_format((float)$r['weight_kg'], 2) ?> kg</td>
                        <td><?= e($r['disposal_method'] ?? '—') ?></td>
                        <td>
                            <span class="badge">
                                <?= number_format((int)$r['points']) ?> pts
                            </span>
                        </td>
                        <td><?= e($r['officer_name'] ?? 'Unknown') ?></td>
                        <td><?= nice_date($r['status_changed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$records): ?>
        <p class="muted">No completed recycling records yet.</p>
    <?php endif; ?>

    <p id="historyEmpty" class="muted" hidden>
        No matching records found.
    </p>
</section>

<script>
const search = document.getElementById('historySearch');
const category = document.getElementById('categoryFilter');
const rows = document.querySelectorAll('.history-row');
const empty = document.getElementById('historyEmpty');

function filterHistory() {
    const term = search.value.trim().toLowerCase();
    let visible = 0;

    rows.forEach(row => {
        const match =
            row.dataset.search.includes(term) &&
            (!category.value ||
             row.dataset.category === category.value);

        
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    empty.hidden = visible > 0 || rows.length === 0;
}

search.addEventListener('input', filterHistory);
category.addEventListener('change', filterHistory);
</script>

<?php require '../includes/officer_bottom.php'; ?>
