<section class="admin-section-panel" data-admin-section="inscricoes">
    <div class="section-head admin-section-head"><div><h2>Inscrições em cursos</h2><p class="muted">Matricule, exclua ou registre a desistência conforme a situação de cada aluno.</p></div></div>
    <?php if (($courseEnrollmentsManagement ?? []) === []) { ?><p class="muted">Nenhuma inscrição pendente ou matriculada.</p><?php } else { ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Aluno</th><th>Turma</th><th>Temporada</th><th>Status</th><th>Alterar</th></tr></thead><tbody>
        <?php foreach ($courseEnrollmentsManagement as $enrollment) { ?>
            <tr><td><?php echo e($enrollment['nome_completo']); ?><br><small><?php echo e(format_cpf_professor((string) $enrollment['cpf'])); ?></small></td><td><?php echo e($enrollment['turma_nome']); ?><br><small><?php echo e($enrollment['modalidade_nome']); ?></small></td><td><?php echo e($enrollment['temporada_nome']); ?></td><td><?php echo e($enrollment['status_label']); ?></td><td><form method="POST" action="<?php echo e(url('/professor/inscricoes/status')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1"><input type="hidden" name="inscricao_id" value="<?php echo e((string) $enrollment['id']); ?>"><select name="status" required><option value="">Novo status</option><option value="matriculada">Matriculada</option><option value="excluida">Excluída</option><option value="excluida_por_falta">Excluída por falta</option><option value="desistente">Desistente</option><option value="encerrada">Encerrada</option></select><input type="text" name="motivo" placeholder="Motivo obrigatório" required><button type="submit" class="btn btn-primary">Salvar</button></form></td></tr>
        <?php } ?>
        </tbody></table></div>
    <?php } ?>
</section>
