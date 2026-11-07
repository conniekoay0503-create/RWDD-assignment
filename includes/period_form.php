<?php
/*
 * The "choose a period" box at the top of every report (admin, student, officer, volunteer).
 * Needs: $rp = report_period(), $form_action (this page, e.g. 'report.php').
 * Optional: $extra_field = HTML for one more field (the admin's "Report type").
 * main.js ([data-report-form]) shows only the boxes for the chosen period.
 */
$extra_field = $extra_field ?? '';
?>
<?php if ($rp['error']): ?><div class="alert alert-error no-print" role="alert" data-flash="error"><?= e($rp['error']) ?></div><?php endif; ?>

<section class="card no-print">
    <form method="get" action="<?= e($form_action) ?>#report" class="report-form" data-report-form>
        <fieldset class="radio-row">
            <legend>Period</legend>
            <label class="seg"><input type="radio" name="period" value="month" <?= $rp['period'] === 'month' ? 'checked' : '' ?>><span><?= icon('calendar') ?> Month</span></label>
            <label class="seg"><input type="radio" name="period" value="year" <?= $rp['period'] === 'year' ? 'checked' : '' ?>><span><?= icon('flag') ?> Year</span></label>
            <label class="seg"><input type="radio" name="period" value="range" <?= $rp['period'] === 'range' ? 'checked' : '' ?>><span><?= icon('sliders') ?> Date range</span></label>
        </fieldset>
        <div class="field-row">
            <div class="field" data-period-field="month">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?= e($rp['month']) ?>">
            </div>
            <div class="field" data-period-field="year">
                <label for="year">Year</label>
                <input type="number" id="year" name="year" min="2000" max="2100" value="<?= (int) $rp['year'] ?>">
            </div>
            <div class="field" data-period-field="range">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="<?= e($rp['from']) ?>" data-start-for="to">
            </div>
            <div class="field" data-period-field="range">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="<?= e($rp['to']) ?>">
            </div>
            <?= $extra_field ?>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" name="preview" value="1"><?= icon('eye') ?> Preview</button>
        </div>
    </form>
</section>

<?php if (!$rp['previewing']): ?>
    <div class="empty-state"><?= icon('chart', 'icon-float') ?><p>Choose a period and press Preview to see the report here.</p></div>
<?php endif; ?>
