<section class="content-card">
    <div class="section-head"><div><h2>Minhas inscrições em cursos</h2><p class="muted">A inscrição aguarda matrícula do professor ou permanece na lista de espera quando não há vaga.</p></div><a class="btn btn-secondary" href="<?php echo e(url('/cursos')); ?>">Ver cursos</a></div>
    <?php if (($courseEnrollments ?? []) === []) { ?>
        <p class="muted">Nenhuma inscrição encontrada.</p>
    <?php } else { ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Pessoa</th><th>Turma</th><th>Temporada</th><th>Status</th><th>Ação</th></tr></thead><tbody>
            <?php foreach ($courseEnrollments as $enrollment) { ?>
                <tr><td><?php echo e($enrollment['nome_completo']); ?></td><td><?php echo e($enrollment['turma_nome']); ?><br><small><?php echo e($enrollment['modalidade_nome']); ?></small></td><td><?php echo e($enrollment['temporada_nome']); ?></td><td><?php echo e($enrollment['status_label']); ?></td><td><?php if (in_array($enrollment['status'], ['aguardando_matricula', 'lista_espera', 'matriculada'], true)) { ?><form method="POST" action="<?php echo e(url('/cursos/inscricoes/cancelar')); ?>" data-ajax-form="1" data-follow-redirect="1"><input type="hidden" name="inscricao_id" value="<?php echo e((string) $enrollment['id']); ?>"><button type="submit" class="btn btn-secondary">Cancelar</button></form><?php } else { ?><span class="muted">-</span><?php } ?></td></tr>
            <?php } ?>
        </tbody></table></div>
    <?php } ?>
</section>
