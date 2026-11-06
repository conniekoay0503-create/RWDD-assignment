<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'officer', '../');

// Devices eligible for recycling
$devices = db_all($conn, "
    SELECT d.deviceID, d.device_name, d.weight_kg,
           d.status, d.submitted_at, d.submitted_by,
           c.category_name, u.name AS student_name
    FROM devices d
    JOIN categories c ON d.categoryID = c.categoryID
    JOIN users u ON d.submitted_by = u.tp_number
    WHERE d.status = 'Awaiting Recycle'
      AND d.recycle_consent = 1
    ORDER BY d.submitted_at ASC
");

// Categories for dropdown
$categories = array_unique(
    array_column($devices, 'category_name')
);
sort($categories);

$page_title = 'Recycling Queue';
$active = 'recycling';
require '../includes/officer_top.php';
?>

<?php
officer_page_head(
    'recycle',
    'Recycling Queue',
    'Devices awaiting recycling with student consent.'
);
?>

<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon tint-mint">
            <?= icon('recycle') ?>
        </span>
        <strong class="stat-value">
            <?= count($devices) ?>
        </strong>
        <span class="stat-title">Pending Devices</span>
        <small>Ready for processing</small>
    </div>
</section>

<section class="card">

    <div class="card-head">
        <h2 class="card-title">
            <?= icon('list') ?> Pending Requests
        </h2>

        <label class="search-box">
            <?= icon('search') ?>
            <input type="search" id="queueSearch"
                   placeholder="Search devices..."
                   aria-label="Search devices">
        </label>

        <select id="categoryFilter"
                aria-label="Filter by category">
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
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($devices as $device): ?>
                    <tr class="device-row"
                        data-category="<?= e($device['category_name']) ?>"
                        data-search="<?= e(strtolower(
                            $device['deviceID'] . ' ' .
                            $device['device_name'] . ' ' .
                            $device['category_name'] . ' ' .
                            $device['student_name'] . ' ' .
                            $device['submitted_by']
                        )) ?>">

                        <td><strong><?= e($device['deviceID']) ?></strong></td>
                        <td><?= e($device['device_name']) ?></td>
                        <td><?= e($device['category_name']) ?></td>
                        <td>
                            <?= e($device['student_name']) ?>
                            <small class="muted">
                                <?= e($device['submitted_by']) ?>
                            </small>
                        </td>
                        <td>
                            <?= $device['weight_kg'] !== null
                                ? number_format((float)$device['weight_kg'], 2) . ' kg'
                                : 'Not recorded' ?>
                        </td>
                        <td><?= nice_date($device['submitted_at']) ?></td>
                        <td>
                            <span class="badge status-awaiting-recycle">
                                <?= e($device['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-primary btn-sm"
                               href="process_recycling.php?deviceID=<?= urlencode($device['deviceID']) ?>">
                                <?= icon('recycle') ?> Process
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p id="queueEmpty" class="muted" hidden>
        No matching devices found.
    </p>

    <?php if (!$devices): ?>
        <p class="muted">No devices awaiting recycling.</p>
    <?php endif; ?>

</section>

<script>
const search = document.getElementById('queueSearch');
const category = document.getElementById('categoryFilter');
const rows = document.querySelectorAll('.device-row');
const empty = document.getElementById('queueEmpty');

function filterQueue() {
    const term = search.value.trim().toLowerCase();
    let visible = 0;

    rows.forEach(row => {
        const match =
            row.dataset.search.includes(term) &&
            (!category.value ||
             row.dataset.category === category.value);

        row.hidden = !match;
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    empty.hidden = visible > 0 || rows.length === 0;
}

search.addEventListener('input', filterQueue);
category.addEventListener('change', filterQueue);
</script>

<?php require '../includes/officer_bottom.php'; ?>
