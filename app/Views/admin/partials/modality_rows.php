<?php if (($modalities ?? []) === []) { ?>
    <tr><td colspan="5" class="muted">Nenhuma modalidade encontrada.</td></tr>
<?php } ?>
<?php foreach (($modalities ?? []) as $modality) { ?>
    <tr>
        <td><strong><?php echo e((string) ($modality['nome'] ?? '')); ?></strong></td>
        <td><?php echo e(($modality['tipo_ambiente'] ?? '') === 'aquatica' ? 'Aquática' : 'Terrestre'); ?></td>
        <td><?php echo e((string) ((int) ($modality['horarios_ativos'] ?? 0))); ?></td>
        <td><?php echo (int) ($modality['ativo'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></td>
        <td><button type="button" class="btn btn-secondary admin-modality-edit" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>">Editar</button></td>
    </tr>
<?php } ?>
