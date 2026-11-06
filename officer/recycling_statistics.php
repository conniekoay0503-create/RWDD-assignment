<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// Overall recycling statistics
$stats = db_one($conn, "
    SELECT COUNT(*) AS devices,
           COALESCE(SUM(weight_kg), 0) AS weight
    FROM devices
    WHERE status = 'Recycled'
");

$points = (int) db_value($conn, "
    SELECT COALESCE(SUM(p.points), 0)
    FROM points_transactions p
    JOIN devices d ON p.deviceID = d.deviceID
    WHERE d.status = 'Recycled'
      AND p.txn_type = 'earn'
      AND p.description LIKE 'Recycling reward for device %'
");

$officers = (int) db_value($conn, "
    SELECT COUNT(DISTINCT processed_by)
    FROM devices
    WHERE status = 'Recycled'
      AND processed_by IS NOT NULL
");

// Category breakdown
$categories = db_all($conn, "
    SELECT c.category_name,
           COUNT(d.deviceID) AS total,
           COALESCE(SUM(d.weight_kg), 0) AS weight
    FROM categories c
    LEFT JOIN devices d
      ON d.categoryID = c.categoryID
     AND d.status = 'Recycled'
    GROUP BY c.categoryID, c.category_name
    ORDER BY total DESC
");

// Monthly recycling trends
$monthly = db_all($conn, "
    SELECT DATE_FORMAT(status_changed_at, '%Y-%m') AS month,
           COUNT(*) AS total,
           COALESCE(SUM(weight_kg), 0) AS weight
    FROM devices
    WHERE status = 'Recycled'
      AND status_changed_at IS NOT NULL
    GROUP BY DATE_FORMAT(status_changed_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

$maxCategory = max(1, ...array_map(
    fn($c) => (int)$c['total'], $categories
));

$maxMonth = max(1, ...array_map(
    fn($m) => (int)$m['total'], $monthly
));

$page_title = 'Recycling Statistics';
$active = 'reports';    
require '../includes/officer_top.php';
?>

<?php
officer_page_head(
    'chart',
    'Recycling Statistics',
    'Overview of recycling performance and environmental impact.'
);
?>

<section class="stat-grid four">
    <?php
    $cards = [
        ['recycle', 'tint-mint', $stats['devices'], 'Devices Recycled'],
        ['leaf', 'tint-sky', number_format((float)$stats['weight'], 2) . ' kg', 'Total Weight'],
        ['star', 'tint-gold', number_format($points), 'Points Awarded'],
        ['users', 'tint-rose', $officers, 'Officers Involved']
    ];
    ?>

    <?php foreach ($cards as [$iconName, $tint, $value, $label]): ?>
        <div class="stat-card">
            <span class="stat-icon <?= $tint ?>">
                <?= icon($iconName) ?>
            </span>
            <strong class="stat-value"><?= e($value) ?></strong>
            <span class="stat-title"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<div class="two-col">

    <section class="card">
        <h2 class="card-title">
            <?= icon('chart') ?> Recycling by Category
        </h2>

        <?php if (!$categories || (int)$stats['devices'] === 0): ?>
            <p class="muted">No recycling data available yet.</p>
        <?php else: ?>
            <ul class="bar-list">
                <?php foreach ($categories as $c): ?>
                    <li>
                        <span><?= e($c['category_name']) ?></span>
                        <span class="bar">
                            <span style="width: <?= round($c['total'] / $maxCategory * 100) ?>%"></span>
                        </span>
                        <strong><?= (int)$c['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="card-title">
            <?= icon('calendar') ?> Monthly Recycling
        </h2>

        <?php if (!$monthly): ?>
            <p class="muted">No monthly recycling records yet.</p>
        <?php else: ?>
            <ul class="bar-list">
                <?php foreach (array_reverse($monthly) as $m): ?>
                    <li>
                        <span><?= e(date('M Y', strtotime($m['month'] . '-01'))) ?></span>
                        <span class="bar">
                            <span style="width: <?= round($m['total'] / $maxMonth * 100) ?>%"></span>
                        </span>
                        <strong><?= (int)$m['total'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<section class="card">
    <div class="card-head">
        <h2 class="card-title">
            <?= icon('list') ?> Category Details
        </h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Devices Recycled</th>
                    <th>Total Weight</th>
                    <th>Share of Devices</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= e($c['category_name']) ?></td>
                        <td><?= (int)$c['total'] ?></td>
                        <td><?= number_format((float)$c['weight'], 2) ?> kg</td>
                        <td>
                            <?= $stats['devices'] > 0
                                ? round($c['total'] / $stats['devices'] * 100, 1)
                                : 0 ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require '../includes/officer_bottom.php'; ?>
