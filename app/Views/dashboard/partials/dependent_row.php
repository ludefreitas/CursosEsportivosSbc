<?php $dependent = $dependent ?? []; ?>
<tr data-dependent-row-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>">
    <td><?php echo e((string) ($dependent['nome_completo'] ?? '')); ?></td>
    <td><?php echo e(format_cpf((string) ($dependent['cpf'] ?? ''))); ?></td>
    <td><?php echo e(!empty($dependent['data_nascimento']) ? date('d/m/Y', strtotime((string) $dependent['data_nascimento'])) : '-'); ?></td>
    <td>
        <button
            type="button"
            class="btn btn-secondary btn-compact"
            data-open-dependent-modal="1"
            data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
        >Visualizar dados</button>
    </td>
    <td>
        <?php $hasConditionAction = false; ?>
        <div class="chips-wrap">
            <?php if ((int) ($dependent['eh_pcd'] ?? 0) === 1) { ?>
                <?php $hasConditionAction = true; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    data-open-certificates-modal="1"
                    data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                    data-condition-slug="pcd"
                >PCD enviar/atualizar documentacao</button>
            <?php } ?>
            <?php if ((int) ($dependent['eh_pvs'] ?? 0) === 1) { ?>
                <?php $hasConditionAction = true; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    data-open-certificates-modal="1"
                    data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                    data-condition-slug="pvs"
                >PVS enviar/atualizar documentacao</button>
            <?php } ?>
            <?php if ((int) ($dependent['eh_plm'] ?? 0) === 1) { ?>
                <?php $hasConditionAction = true; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    data-open-certificates-modal="1"
                    data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                    data-condition-slug="plm"
                >PLM enviar/atualizar documentacao</button>
            <?php } ?>
        </div>
        <?php if (!$hasConditionAction) { ?>
            <span class="muted">Nao declarado</span>
        <?php } ?>
    </td>
</tr>
