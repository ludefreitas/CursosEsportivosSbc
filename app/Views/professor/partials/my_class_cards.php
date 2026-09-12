<?php foreach (($professorClasses ?? []) as $class) { ?>
    <article class="content-card professor-class-card admin-class-card" data-course-class-card="<?php echo e((string) $class['id']); ?>">
        <span class="eyebrow"><?php echo e((string) ($class['temporada_nome'] ?? '')); ?></span>
        <h3><?php echo e((string) ($class['nome'] ?? '')); ?></h3>
        <div class="professor-class-details">
            <p><strong>Modalidade:</strong> <?php echo e((string) ($class['modalidade_nome'] ?? '')); ?></p>
            <p><strong>Local:</strong> <?php echo e((string) ($class['local_nome'] ?? '')); ?>, <?php echo e((string) ($class['espaco_nome'] ?? '')); ?></p>
            <p><strong>Dias:</strong> <?php echo e((string) ($class['dias_semana_descricao'] ?? 'Não informado')); ?></p>
            <p><strong>Horário:</strong> <?php echo !empty($class['hora_inicio']) && !empty($class['hora_fim']) ? e(substr((string) $class['hora_inicio'], 0, 5) . ' às ' . substr((string) $class['hora_fim'], 0, 5)) : 'Não informado'; ?></p>
            <p><strong><?php echo ($class['criterio_faixa_etaria'] ?? '') === 'ano_nascimento' ? 'Ano de nascimento' : 'Faixa etária'; ?>:</strong> <?php echo e((string) ($class['faixa_etaria_descricao'] ?? 'Não informada')); ?></p>
            <?php if (!empty($class['excecoes_idade_descricao'])) { ?><p><strong>Exceções de idade:</strong> <?php echo e(implode('; ', (array) $class['excecoes_idade_descricao'])); ?></p><?php } ?>
            <p><strong>Níveis aceitos:</strong> <?php echo e((string) ($class['niveis_aceitos_descricao'] ?? 'Sem limitação de nível')); ?></p>
            <p><strong>Professor principal:</strong> <?php echo e((string) (($class['professor_principal_nome'] ?? '') ?: 'Sem professor principal')); ?></p>
            <p><strong>Professores auxiliares:</strong> <?php echo e((string) (($class['professores_auxiliares_nomes'] ?? '') ?: 'Sem professor auxiliar')); ?></p>
            <p><strong>Estagiários:</strong> <?php echo e((string) (($class['estagiarios_nomes'] ?? '') ?: 'Sem estagiário')); ?></p>
            <p><strong>Status:</strong> <span data-course-class-status-label="1"><?php echo e((string) ($class['status_label'] ?? '')); ?></span></p>
        </div>
        <div class="course-row-actions admin-class-card-actions">
            <button type="button" class="btn btn-primary" data-professor-class-edit="<?php echo e((string) $class['id']); ?>">Editar</button>
            <button type="button" class="btn btn-secondary" data-professor-class-team="<?php echo e((string) $class['id']); ?>">Equipe</button>
            <button type="button" class="btn btn-secondary" data-professor-class-status="<?php echo e((string) $class['id']); ?>">Alterar status</button>
        </div>
    </article>
<?php } ?>
