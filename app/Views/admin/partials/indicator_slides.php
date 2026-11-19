<?php
$pdfModel = $indicatorDomain === 'agenda' ? 'agenda_indicadores' : 'turmas_indicadores';
?>
<div class="people-charts admin-charts indicator-slides" data-people-charts data-indicator-slides="<?php echo e($indicatorDomain); ?>" data-people-manual hidden>
    <p data-people-chart-loading role="status" hidden></p>
    <p data-people-chart-error role="alert" hidden></p>
    <div data-people-chart-results hidden>
        <div class="people-chart-stats" data-people-chart-stats></div>
        <p class="people-chart-summary" data-people-chart-summary></p>
        <p class="people-chart-warning" data-people-chart-unavailable hidden></p>
        <?php require __DIR__ . '/indicator_slide_stage.php'; ?>
        <p class="people-chart-method">Referência em <span data-people-chart-date></span>. As bases, os períodos e as unidades são informados em cada slide. Valores indisponíveis aparecem como “—”.</p>
    </div>
    <template data-people-print-header><div class="people-print-brand"><?php require __DIR__ . '/../../partials/pdf_branding_header.php'; ?></div></template>
    <template data-people-print-footer><div class="people-print-brand"><?php require __DIR__ . '/../../partials/pdf_branding_footer.php'; ?></div></template>
</div>
