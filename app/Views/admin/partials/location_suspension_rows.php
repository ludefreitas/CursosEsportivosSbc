<?php foreach (($spaceSuspensions ?? []) as $suspension) { ?>
    <tr data-space-suspension-row="<?php echo e((string) $suspension['espaco_treino_id']); ?>" class="hidden">
        <td><?php echo e($suspension['espaco_nome']); ?></td>
        <td><?php echo e(date('d/m/Y', strtotime((string) $suspension['data_inicio']))); ?> até <?php echo e(date('d/m/Y', strtotime((string) $suspension['data_fim']))); ?></td>
        <td><?php echo e($suspension['motivo'] ?: '-'); ?></td>
        <td><?php echo e((int) $suspension['ativo'] === 1 ? 'Ativa' : 'Inativa'); ?></td>
        <td><?php echo e($suspension['criado_por_nome'] ?: 'Sistema ou registro anterior'); ?></td>
        <td>
            <?php if ((int) ($suspension['pode_inativar'] ?? 0) === 1) { ?>
                <form method="POST" action="<?php echo e(url('/admin/espacos/suspensoes/inativar')); ?>" class="inline-form admin-space-suspension-deactivate-form" data-manual-submit="1">
                    <input type="hidden" name="suspensao_espaco_id" value="<?php echo e((string) $suspension['id']); ?>">
                    <button type="submit" class="btn btn-warning">Inativar</button>
                </form>
            <?php } elseif ((int) $suspension['ativo'] === 1) { ?>
                <div class="admin-training-space-actions">
                    <span class="muted">Aguardando início</span>
                    <form method="POST" action="<?php echo e(url('/admin/espacos/suspensoes/excluir')); ?>" class="inline-form admin-space-suspension-delete-form" data-manual-submit="1" data-confirm-delete="1" data-confirm-delete-message="Tem certeza de que deseja excluir esta suspensão futura? Esta ação não poderá ser desfeita.">
                        <input type="hidden" name="suspensao_espaco_id" value="<?php echo e((string) $suspension['id']); ?>">
                        <button type="submit" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            <?php } else { ?>
                <span class="muted">Sem ação</span>
            <?php } ?>
        </td>
    </tr>
<?php } ?>
<tr id="admin-location-suspensions-empty" class="hidden">
    <td colspan="6">Nenhuma suspensão cadastrada para este espaço.</td>
</tr>
