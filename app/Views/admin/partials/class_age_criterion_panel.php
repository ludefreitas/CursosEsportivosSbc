<?php
$ageClasses = (array) ($ageCriterionClasses ?? []);
$ageSeasons = [];
foreach ($ageClasses as $class) { $ageSeasons[(int) $class['temporada_id']] = (string) $class['temporada_nome']; }
?>
<section class="content-card" data-age-criterion-panel data-save-url="<?php echo e(url(!empty($professorView) ? '/professor/turmas/criterio-etario' : '/admin/turmas/criterio-etario')); ?>" data-csrf="<?php echo e((string) ($_SESSION['class_age_criterion_csrf'] ?? '')); ?>">
    <div class="section-head"><div><h2>Turmas por critério etário</h2></div></div>
    <div class="age-criterion-filters">
        <div class="admin-class-filter-block"><strong>Temporadas</strong><div class="professor-class-filter-buttons" data-age-filter="temporada" role="group" aria-label="Temporadas">
            <?php foreach ($ageSeasons as $id => $name) { ?><button type="button" class="btn btn-secondary admin-class-filter-button" data-age-filter-value="<?php echo (int) $id; ?>" aria-pressed="false"><?php echo e($name); ?></button><?php } ?>
        </div></div>
        <div class="admin-class-filter-block"><strong>Locais</strong><div class="professor-class-filter-buttons" data-age-filter="local" role="group" aria-label="Locais"><span class="muted">Selecione uma temporada.</span></div></div>
        <div class="admin-class-filter-block"><strong>Modalidades do local</strong><div class="professor-class-filter-buttons" data-age-filter="modalidade" role="group" aria-label="Modalidades do local"><span class="muted">Selecione um local.</span></div></div>
        <div class="admin-class-filter-block"><strong>Critério etário</strong><div class="professor-class-filter-buttons" data-age-filter="criterio" role="group" aria-label="Critério etário">
            <button type="button" class="btn btn-secondary admin-class-filter-button is-active" data-age-filter-value="" aria-pressed="true">Todos os critérios</button>
            <button type="button" class="btn btn-secondary admin-class-filter-button" data-age-filter-value="ano_nascimento" aria-pressed="false">Ano de nascimento</button>
            <button type="button" class="btn btn-secondary admin-class-filter-button" data-age-filter-value="idade_exata" aria-pressed="false">Idade exata</button>
        </div></div>
    </div>
    <p class="muted" data-age-list-status role="status">Selecione a temporada, o local e a modalidade para consultar as turmas.</p>
    <div class="course-class-grid" data-age-class-list>
    <?php foreach ($ageClasses as $class) { $criterion = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata')); ?>
        <article class="content-card professor-class-card admin-class-card" hidden data-age-class="<?php echo (int) $class['id']; ?>" data-season="<?php echo (int) $class['temporada_id']; ?>" data-location="<?php echo (int) $class['local_treino_id']; ?>" data-location-name="<?php echo e((string) $class['local_nome']); ?>" data-modality="<?php echo (int) $class['modalidade_id']; ?>" data-modality-name="<?php echo e((string) $class['modalidade_nome']); ?>" data-criterion="<?php echo e($criterion); ?>">
            <span class="eyebrow"><?php echo e((string) $class['temporada_nome']); ?></span>
            <h3><span class="course-class-id">[<?php echo (int) $class['id']; ?>]</span> <?php echo e((string) $class['nome']); ?></h3>
            <div class="professor-class-details"><p class="course-class-summary">
                <strong><?php echo e((string) $class['modalidade_nome']); ?></strong> -
                <?php echo e((string) $class['local_nome']); ?>, <?php echo e((string) ($class['espaco_nome'] ?? '')); ?> -
                <strong><?php echo e((string) ($class['dias_semana_descricao'] ?? 'Dias não informados')); ?><?php if (!empty($class['hora_inicio']) && !empty($class['hora_fim'])) { echo e(', ' . substr($class['hora_inicio'], 0, 5) . ' às ' . substr($class['hora_fim'], 0, 5)); } ?></strong> -
                <?php if ($criterion === 'ano_nascimento') { ?>Ano de nascimento: <?php } ?><?php echo e((string) ($class['faixa_etaria_descricao'] ?? 'Faixa etária não informada')); ?> -
                <strong>Professor: <?php echo e((string) (($class['professor_principal_nome'] ?? '') ?: 'Sem professor principal')); ?></strong> -
                Status: <span class="class-status-badge class-status-<?php echo e((string) ($class['status'] ?? 'planejada')); ?>"><?php echo e((string) ($class['status_label'] ?? $class['status'] ?? 'Planejada')); ?></span> -
                <button type="button" class="course-class-details-link" data-course-class-details='<?php echo e((string) json_encode($class, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'>Detalhes</button>
            </p></div>
            <fieldset class="age-criterion-radios"><legend>Critério etário</legend>
                <label><input type="radio" name="criterio_turma_<?php echo (int) $class['id']; ?>" value="ano_nascimento" data-age-change <?php echo $criterion === 'ano_nascimento' ? 'checked' : ''; ?>> Ano de nascimento</label>
                <label><input type="radio" name="criterio_turma_<?php echo (int) $class['id']; ?>" value="idade_exata" data-age-change <?php echo $criterion === 'idade_exata' ? 'checked' : ''; ?>> Idade exata</label>
            </fieldset>
            <span class="muted" data-age-save-status role="status" aria-live="polite"></span>
        </article>
    <?php } ?>
    </div>
</section>
<div class="popup-overlay hidden" id="course-class-details-modal" aria-hidden="true"><div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="course-class-details-title"><div class="popup-head"><div><h3 id="course-class-details-title">Detalhes da turma</h3><p class="muted" data-course-details-subtitle></p></div><button type="button" class="popup-close-icon" data-course-details-close="1" aria-label="Fechar">&times;</button></div><div class="popup-body"><div class="course-class-details-modal-content" data-course-details-content></div><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-details-close="1">Fechar</button></div></div></div></div>
