<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// The period from the form (same box as the admin Reports page).
// "Recycled in the period" = the date it became Recycled (status_changed_at). Every query: date >= start AND date < end
$rp = report_period();
$p = [$rp['start'], $rp['end']];

// Overall recycling statistics
$stats = db_one($conn, "
    SELECT COUNT(*) AS devices,
           COALESCE(SUM(weight_kg), 0) AS weight
    FROM devices
    WHERE status = 'Recycled'
      AND status_changed_at >= ? AND status_changed_at < ?
", 'ss', $p);

$points = (int) db_value($conn, "
    SELECT COALESCE(SUM(p.points), 0)
    FROM points_transactions p
    JOIN devices d ON p.deviceID = d.deviceID
    WHERE d.status = 'Recycled'
      AND p.txn_type = 'earn'
      AND p.tp_number = d.submitted_by   -- the owner's points for this recycled device
      AND d.status_changed_at >= ? AND d.status_changed_at < ?
", 'ss', $p);

$officers = (int) db_value($conn, "
    SELECT COUNT(DISTINCT processed_by)
    FROM devices
    WHERE status = 'Recycled'
      AND processed_by IS NOT NULL
      AND status_changed_at >= ? AND status_changed_at < ?
", 'ss', $p);

// Category breakdown
$categories = db_all($conn, "
    SELECT c.category_name,
           COUNT(d.deviceID) AS total,
           COALESCE(SUM(d.weight_kg), 0) AS weight
    FROM categories c
    LEFT JOIN devices d
      ON d.categoryID = c.categoryID
     AND d.status = 'Recycled'
     AND d.status_changed_at >= ? AND d.status_changed_at < ?
    GROUP BY c.categoryID, c.category_name
    ORDER BY total DESC
", 'ss', $p);

// Monthly recycling trends
$monthly = db_all($conn, "
    SELECT DATE_FORMAT(status_changed_at, '%Y-%m') AS month,
           COUNT(*) AS total,
           COALESCE(SUM(weight_kg), 0) AS weight
    FROM devices
    WHERE status = 'Recycled'
      AND status_changed_at >= ? AND status_changed_at < ?
    GROUP BY DATE_FORMAT(status_changed_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
", 'ss', $p);

// array_merge([1], ...) so max() still works when the list is empty (a period with no recycling)
$maxCategory = max(array_merge([1], array_map(fn($c) => (int)$c['total'], $categories)));

// array_merge([1], ...) so max() still works when the list is empty (a period with no recycling)
$maxMonth = max(array_merge([1], array_map(fn($m) => (int)$m['total'], $monthly)));

$page_title = 'Recycling Statistics';
$active = 'reports';    
require '../includes/officer_top.php';
?>

<?php
officer_page_head(
    'chart',
    'Recycling Statistics',
    'Choose a period, then see recycling performance and environmental impact.'
);
$form_action = 'recycling_statistics.php';
require '../includes/period_form.php';   // the same period box as the admin Reports page
?>

<?php if ($rp['previewing']): ?>
<?php
$report_title = 'Recycling statistics report';
require '../includes/report_head.php';   // same report top as the admin Reports page
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
        <!-- "row" = icon on the left, number + title on the right (same as the admin report) -->
        <div class="stat-card row">
            <span class="stat-icon <?= $tint ?>">
                <?= icon($iconName) ?>
            </span>
            <div>
                <strong class="stat-value"><?= e($value) ?></strong>
                <span class="stat-title"><?= e($label) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<div class="two-col">

    <section class="card">
        <h3 class="card-title">
            <?= icon('chart') ?> Recycling by Category
        </h3>

        <?php if (!$categories || (int)$stats['devices'] === 0): ?>
            <p class="muted">No devices were recycled in this period.</p>
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
        <h3 class="card-title">
            <?= icon('calendar') ?> Monthly Recycling
        </h3>

        <?php if (!$monthly): ?>
            <p class="muted">No devices were recycled in this period.</p>
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
        <h3 class="card-title">
            <?= icon('list') ?> Category Details
        </h3>
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
                <?php if (!$categories) empty_row(4, 'recycle', 'No devices were recycled in this period.'); ?>
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
</article>

<?php endif; ?>

<?php require '../includes/officer_bottom.php'; ?>
