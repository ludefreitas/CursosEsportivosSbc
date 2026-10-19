<?php
$statusFilter = \App\Services\CourseClassStatusFilter::filter($courseClasses ?? [], (int) ($_GET['temporada_id'] ?? 0), (string) ($_GET['status_turma'] ?? ''), (int) ($_GET['modalidade_id'] ?? 0), (int) ($_GET['local_treino_id'] ?? 0));
$statusSteps = [
    ['Temporadas', 'temporada_id', $statusFilter['seasons'], $statusFilter['season_id'], true],
    ['Status', 'status_turma', $statusFilter['statuses'], $statusFilter['status'], $statusFilter['season_id'] > 0],
    ['Modalidades', 'modalidade_id', $statusFilter['modalities'], $statusFilter['modality_id'], $statusFilter['status'] !== ''],
    ['Locais', 'local_treino_id', $statusFilter['locations'], $statusFilter['location_id'], $statusFilter['modality_id'] > 0],
];
?>
<article class="content-card class-timeline" data-class-status-browser data-season="<?php echo e((string) $statusFilter['season_id']); ?>" data-status="<?php echo e($statusFilter['status']); ?>" data-modality="<?php echo e((string) $statusFilter['modality_id']); ?>" data-location="<?php echo e((string) $statusFilter['location_id']); ?>">
    <div><h2>Status turmas</h2><p class="muted">Selecione a temporada, o status, a modalidade e o local para consultar as turmas.</p></div>
    <?php if ($statusFilter['seasons'] === []) { ?><p class="muted">Nenhuma turma disponível para consulta.</p><?php } else { ?>
        <?php foreach ($statusSteps as [$statusStepTitle, $statusStepName, $statusStepOptions, $statusStepSelected, $statusStepVisible]) { if (!$statusStepVisible) continue; ?>
            <div class="admin-class-filter-block"><strong><?php echo e($statusStepTitle); ?></strong>
                <div class="class-timeline-options">
                    <?php foreach ($statusStepOptions as $statusOptionId => $statusOptionName) { ?>
                        <button type="button" class="btn btn-secondary admin-class-filter-button<?php echo (string) $statusOptionId === (string) $statusStepSelected ? ' is-active' : ''; ?>" data-status-filter="<?php echo e($statusStepName); ?>" data-status-value="<?php echo e((string) $statusOptionId); ?>"><?php echo e($statusOptionName); ?></button>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <?php if ($statusFilter['location_id'] <= 0) { ?>
            <p class="muted">Selecione <?php echo $statusFilter['status'] === '' ? 'um status' : ($statusFilter['modality_id'] <= 0 ? 'uma modalidade' : 'um local'); ?> para continuar.</p>
        <?php } else { ?>
            <p class="muted"><?php echo count($statusFilter['classes']); ?> turma(s) encontrada(s).</p>
            <div class="professor-classes-grid admin-classes-grid">
                <?php $courseClasses = $statusFilter['classes']; require ROOT_PATH . '/app/Views/admin/partials/course_class_card_list.php'; ?>
            </div>
        <?php } ?>
    <?php } ?>
</article>
