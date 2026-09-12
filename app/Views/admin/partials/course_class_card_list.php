<?php if (($courseClasses ?? []) === []) { ?>
    <p class="muted">Selecione <?php echo ($courseManagementView ?? 'turmas') === 'turmas-locais' ? 'um local' : 'uma modalidade'; ?> para consultar as turmas.</p>
<?php } else { ?>
    <?php foreach ($courseClasses as $class) { ?>
        <article class="content-card professor-class-card admin-class-card" data-course-class-card="<?php echo e((string) $class['id']); ?>">
            <span class="eyebrow"><?php echo e((string) ($class['temporada_nome'] ?? '')); ?></span>
            <h3><span class="course-class-id">[<?php echo e((string) ($class['id'] ?? '')); ?>]</span> <?php echo e((string) ($class['nome'] ?? '')); ?></h3>
            <div class="professor-class-details">
                <p><strong>Modalidade:</strong> <?php echo e((string) ($class['modalidade_nome'] ?? '')); ?></p>
                <p><strong>Local:</strong> <?php echo e((string) ($class['local_nome'] ?? '')); ?>, <?php echo e((string) ($class['espaco_nome'] ?? '')); ?></p>
                <p><strong>Dias:</strong> <?php echo e((string) ($class['dias_semana_descricao'] ?? 'Não informado')); ?><?php if (!empty($class['hora_inicio']) && !empty($class['hora_fim'])) { echo e(', ' . substr((string) $class['hora_inicio'], 0, 5) . ' às ' . substr((string) $class['hora_fim'], 0, 5)); } ?></p>
                <p><strong><?php echo ($class['criterio_faixa_etaria'] ?? '') === 'ano_nascimento' ? 'Ano de nascimento' : 'Faixa etária'; ?>:</strong> <?php echo e((string) ($class['faixa_etaria_descricao'] ?? 'Não informada')); ?></p>
                <?php if (!empty($class['excecoes_idade_descricao'])) { ?><p><strong>Exceções de idade:</strong> <?php echo e(implode('; ', (array) $class['excecoes_idade_descricao'])); ?></p><?php } ?>
                <p><strong>Professor principal:</strong> <?php echo e((string) (($class['professor_principal_nome'] ?? '') ?: 'Sem professor principal')); ?></p>
                <p><strong>Status:</strong> <span class="class-status-badge class-status-<?php echo e((string) ($class['status'] ?? 'planejada')); ?>" data-course-class-status-label="1"><?php echo e((string) ($class['status_label'] ?? (!empty($class['ativo']) ? 'Planejada' : 'Inscrições suspensas'))); ?></span></p>
                <p><button type="button" class="course-class-details-link" data-course-class-details='<?php echo e((string) json_encode($class, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'>Detalhes</button></p>
            </div>
            <div class="course-row-actions admin-class-card-actions">
                <button type="button" class="btn btn-primary" data-course-edit="class" data-course-record="<?php echo e((string) json_encode($class, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">Editar</button>
                <?php if (($courseManagementView ?? 'turmas') !== 'professor-turmas') { ?><button type="button" class="btn btn-secondary" data-course-assign-professor="<?php echo e((string) $class['id']); ?>" data-course-main-professor="<?php echo e((string) ($class['professor_conta_id'] ?? '')); ?>" data-course-current-professors="<?php echo e((string) json_encode($class['professores_ids'] ?? [], JSON_UNESCAPED_UNICODE)); ?>" data-course-current-interns="<?php echo e((string) json_encode($class['estagiarios_ids'] ?? [], JSON_UNESCAPED_UNICODE)); ?>">Equipe</button><?php } ?>
                <button type="button" class="btn btn-secondary" data-course-class-status-open="1" data-course-class-id="<?php echo e((string) $class['id']); ?>" data-course-class-name="<?php echo e((string) ($class['nome'] ?? '')); ?>" data-course-class-status="<?php echo e((string) ($class['status'] ?? 'planejada')); ?>" data-course-class-schedule-status="<?php echo e((string) ($class['status_cronograma'] ?? 'planejada')); ?>" data-course-management-view="<?php echo e((string) $courseManagementView); ?>">Alterar status</button>
            </div>
        </article>
    <?php } ?>
<?php } ?>
<div class="popup-overlay hidden" id="course-class-details-modal" aria-hidden="true"><div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="course-class-details-title"><div class="popup-head"><div><h3 id="course-class-details-title">Detalhes da turma</h3><p class="muted" data-course-details-subtitle></p></div><button type="button" class="popup-close-icon" data-course-details-close="1" aria-label="Fechar">&times;</button></div><div class="popup-body"><div class="course-class-details-modal-content" data-course-details-content></div><div class="popup-actions"><button type="button" class="btn btn-secondary" data-course-details-close="1">Fechar</button></div></div></div></div>
