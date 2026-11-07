<?php
/*
 * The top of the printable report (admin, student, officer, volunteer all look the same).
 * Needs: $rp = report_period(), $report_title (e.g. 'My repair & recycling report').
 * Optional: $report_code = a report number shown after the date (the volunteer uses it).
 * It OPENS <article class="report">, so the page must close it with </article> at the end.
 * Only the white "paper" (the <article>) is printed; everything with .no-print is hidden.
 */
?>
<p class="print-area-label no-print"><?= icon('printer') ?> Print area: only the white part below is printed or saved as PDF.</p>
<article class="report" id="report">
    <header class="report-head">
        <div>
            <h2><?= icon('chart') ?> <?= e($report_title) ?></h2>
            <p><strong><?= e($rp['label']) ?></strong> · generated <?= date('j M Y, g:i A') ?> by <?= e($_SESSION['name']) ?> (<?= e($_SESSION['tp_number']) ?>)<?= !empty($report_code) ? ' · Report ' . e($report_code) : '' ?></p>
        </div>
        <button type="button" class="btn btn-sun no-print" data-print><?= icon('printer') ?> Print / Save as PDF</button>
    </header>
