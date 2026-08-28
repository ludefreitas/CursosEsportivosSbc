<section class="admin-section-panel professor-view" data-admin-section="minhas-turmas">
    <div class="section-head admin-section-head">
        <div>
            <h2>Minhas turmas</h2>
            <p class="muted">Turmas atribuídas a você pela administração.</p>
        </div>
    </div>
    <?php if (empty($professorClasses)) { ?>
        <article class="content-card">
            <p class="muted">Nenhuma turma foi atribuída a você.</p>
        </article>
    <?php } else { ?>
        <div class="professor-classes-grid">
            <?php foreach ($professorClasses as $class) { ?>
                <article class="content-card professor-class-card">
                    <span class="eyebrow"><?php echo e((string) $class['temporada_nome']); ?></span>
                    <h3><?php echo e((string) $class['nome']); ?></h3>
                    <div class="professor-class-details">
                        <p><strong>Modalidade:</strong> <?php echo e((string) $class['modalidade_nome']); ?></p>
                        <p><strong>Local:</strong> <?php echo e((string) $class['local_nome']); ?>, <?php echo e((string) $class['espaco_nome']); ?></p>
                        <p><strong>Dias:</strong> <?php echo e((string) ($class['dias_semana_descricao'] ?? $class['dias_semana'] ?? 'Não informado')); ?></p>
                        <p><strong>Horário:</strong> <?php if (!empty($class['hora_inicio']) && !empty($class['hora_fim'])) { ?><?php echo e(substr((string) $class['hora_inicio'], 0, 5)); ?> às <?php echo e(substr((string) $class['hora_fim'], 0, 5)); ?><?php } else { ?>Não informado<?php } ?></p>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } ?>
</section>
