<?php
$timeline = \App\Services\CourseClassTimelineService::build($courseClasses ?? [], $modalitySchedules ?? [], (int) ($_GET['temporada_id'] ?? 0), (string) ($_GET['periodo'] ?? ''), (int) ($_GET['modalidade_id'] ?? 0));
$timelineDate = static function (string $value): string {
    if ($value === '') return 'Não informada';
    return date(strlen($value) > 10 ? 'd/m/Y H:i' : 'd/m/Y', strtotime($value));
};
?>
<article class="content-card class-timeline" data-class-timeline data-timeline-season="<?php echo e((string) $timeline['season_id']); ?>">
    <div class="course-table-heading"><div><h2>Cronograma turmas</h2><p class="muted">Selecione a temporada, o período e a modalidade para consultar as turmas.</p></div></div>
    <?php if ($timeline['seasons'] === []) { ?>
        <p class="muted">Nenhuma turma disponível para consulta.</p>
    <?php } else { ?>
        <div class="admin-class-filter-block"><strong>Temporadas</strong><div class="class-timeline-options">
            <?php foreach ($timeline['seasons'] as $timelineSeasonId => $timelineSeasonName) { ?>
                <button type="button" class="btn btn-secondary admin-class-filter-button<?php echo $timelineSeasonId === $timeline['season_id'] ? ' is-active' : ''; ?>" data-timeline-season-select="<?php echo e((string) $timelineSeasonId); ?>"><?php echo e($timelineSeasonName); ?></button>
            <?php } ?>
        </div></div>
        <div class="admin-class-filter-block"><strong>Períodos dos cronogramas</strong><div class="class-timeline-options">
            <?php foreach ($timeline['groups'] as $timelineKey => $timelineGroup) { ?>
                <button type="button" class="btn btn-secondary admin-class-filter-button class-timeline-period<?php echo $timelineKey === $timeline['period_key'] ? ' is-active' : ''; ?>" data-timeline-period="<?php echo e($timelineKey); ?>"><strong><?php echo e($timelineGroup['label']); ?></strong><span><?php echo e($timelineDate($timelineGroup['start']) . ' a ' . $timelineDate($timelineGroup['end'])); ?></span><small><?php echo count($timelineGroup['classes']); ?> turma(s)</small></button>
            <?php } ?>
        </div><?php if ($timeline['groups'] === []) { ?><p class="muted">Nenhum período cadastrado nos cronogramas das turmas desta temporada.</p><?php } ?></div>
        <?php if ($timeline['period_key'] === '') { ?>
            <p class="muted">Selecione um período para consultar as turmas.</p>
        <?php } else { ?>
            <div class="admin-class-filter-block"><strong>Modalidades do período</strong><div class="class-timeline-options">
                <?php foreach ($timeline['modalities'] as $timelineModalityId => $timelineModalityName) { ?>
                    <button type="button" class="btn btn-secondary admin-class-filter-button<?php echo $timelineModalityId === $timeline['modality_id'] ? ' is-active' : ''; ?>" data-timeline-modality="<?php echo e((string) $timelineModalityId); ?>"><?php echo e($timelineModalityName); ?></button>
                <?php } ?>
            </div></div>
            <?php if ($timeline['modality_id'] === 0) { ?>
                <p class="muted">Selecione uma modalidade para consultar as turmas.</p>
            <?php } else { ?>
            <p class="muted"><?php echo count($timeline['classes']); ?> turma(s) encontrada(s).</p>
            <div class="professor-classes-grid admin-classes-grid">
                <?php $courseClasses = $timeline['classes']; require ROOT_PATH . '/app/Views/admin/partials/course_class_card_list.php'; ?>
            </div>
            <?php } ?>
        <?php } ?>
    <?php } ?>
</article>
