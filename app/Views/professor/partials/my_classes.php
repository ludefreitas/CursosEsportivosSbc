<section class="admin-section-panel professor-view" data-admin-section="minhas-turmas">
    <div class="section-head admin-section-head">
        <div>
            <h2>Minhas turmas</h2>
            <p class="muted">Turmas atribuídas a você pela administração.</p>
        </div>
    </div>
    <article class="content-card">
        <?php if (empty($professorClasses)) { ?>
            <p class="muted">Nenhuma turma foi atribuída a você.</p>
        <?php } else { ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Turma</th><th>Modalidade</th><th>Temporada</th><th>Local</th><th>Dias e horário</th></tr></thead><tbody>
                <?php foreach ($professorClasses as $class) { ?><tr><td><?php echo e($class['nome']); ?></td><td><?php echo e($class['modalidade_nome']); ?></td><td><?php echo e($class['temporada_nome']); ?></td><td><?php echo e($class['local_nome']); ?> / <?php echo e($class['espaco_nome']); ?></td><td><?php echo e((string) ($class['dias_semana_descricao'] ?? $class['dias_semana'] ?? '-')); ?><?php if (!empty($class['hora_inicio']) && !empty($class['hora_fim'])) { ?><br><small><?php echo e(substr((string) $class['hora_inicio'], 0, 5)); ?> às <?php echo e(substr((string) $class['hora_fim'], 0, 5)); ?></small><?php } ?></td></tr><?php } ?>
            </tbody></table></div>
        <?php } ?>
    </article>
</section>
