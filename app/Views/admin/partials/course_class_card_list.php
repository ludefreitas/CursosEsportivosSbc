<?php if (($courseClasses ?? []) === []) { ?>
    <p class="muted">Selecione <?php echo ($courseManagementView ?? 'turmas') === 'turmas-locais' ? 'um local' : 'uma modalidade'; ?> para consultar as turmas.</p>
<?php } else { ?>
    <?php foreach ($courseClasses as $class) { ?>
        <article class="content-card professor-class-card admin-class-card">
            <span class="eyebrow"><?php echo e((string) ($class['temporada_nome'] ?? '')); ?></span>
            <h3><?php echo e((string) ($class['nome'] ?? '')); ?></h3>
            <div class="professor-class-details">
                <p><strong>Modalidade:</strong> <?php echo e((string) ($class['modalidade_nome'] ?? '')); ?></p>
                <p><strong>Local:</strong> <?php echo e((string) ($class['local_nome'] ?? '')); ?>, <?php echo e((string) ($class['espaco_nome'] ?? '')); ?></p>
                <p><strong>Dias:</strong> <?php echo e((string) ($class['dias_semana_descricao'] ?? 'Não informado')); ?></p>
                <p><strong>Horário:</strong> <?php if (!empty($class['hora_inicio']) && !empty($class['hora_fim'])) { echo e(substr((string) $class['hora_inicio'], 0, 5) . ' às ' . substr((string) $class['hora_fim'], 0, 5)); } else { echo 'Não informado'; } ?></p>
                <p><strong>Professor:</strong> <?php echo e((string) ($class['professor_nome'] ?? 'Sem professor')); ?></p>
                <p><strong>Status:</strong> <?php echo !empty($class['ativo']) ? 'Ativa' : 'Inativa'; ?></p>
            </div>
            <div class="course-row-actions admin-class-card-actions">
                <button type="button" class="btn btn-primary" data-course-edit="class" data-course-record="<?php echo e((string) json_encode($class, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">Editar</button>
                <button type="button" class="btn btn-secondary" data-course-assign-professor="<?php echo e((string) $class['id']); ?>" data-course-current-professor="<?php echo e((string) ($class['professor_conta_id'] ?? '')); ?>">Professor</button>
                <?php if (!empty($class['ativo'])) { ?><form method="POST" action="<?php echo e(url('/admin/temporadas-turmas/inativar')); ?>" data-course-deactivate="1"><input type="hidden" name="entidade" value="turma"><input type="hidden" name="id" value="<?php echo e((string) $class['id']); ?>"><input type="hidden" name="course_management_view" value="<?php echo e((string) $courseManagementView); ?>"><button type="submit" class="btn btn-secondary">Inativar</button></form><?php } ?>
            </div>
        </article>
    <?php } ?>
<?php } ?>
