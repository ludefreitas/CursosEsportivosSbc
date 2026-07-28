<?php $dependent = $dependent ?? []; ?>
<?php $healthSummary = $dependent['health_certificates_summary'] ?? []; ?>
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
        <?php $status = $healthSummary['clinico'] ?? ['class' => 'is-nao-enviado', 'icon' => '--', 'label' => 'Não enviado']; ?>
        <div class="dashboard-health-status-item">
            <span class="dashboard-health-status-badge <?php echo e((string) ($status['class'] ?? 'is-nao-enviado')); ?>">
                <span class="dashboard-health-status-icon"><?php echo e((string) ($status['icon'] ?? '--')); ?></span>
                <span><?php echo e((string) ($status['label'] ?? 'Não enviado')); ?></span>
            </span>
            <button
                type="button"
                class="btn btn-secondary btn-compact"
                data-open-health-certificates-modal="1"
                data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                data-certificate-type="clinico"
            >Atualizar atestado clínico</button>
        </div>
    </td>
    <td>
        <?php $status = $healthSummary['dermatologico'] ?? ['class' => 'is-nao-enviado', 'icon' => '--', 'label' => 'Não enviado']; ?>
        <div class="dashboard-health-status-item">
            <span class="dashboard-health-status-badge <?php echo e((string) ($status['class'] ?? 'is-nao-enviado')); ?>">
                <span class="dashboard-health-status-icon"><?php echo e((string) ($status['icon'] ?? '--')); ?></span>
                <span><?php echo e((string) ($status['label'] ?? 'Não enviado')); ?></span>
            </span>
            <button
                type="button"
                class="btn btn-secondary btn-compact"
                data-open-health-certificates-modal="1"
                data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                data-certificate-type="dermatologico"
            >Atualizar atestado dermatológico</button>
        </div>
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
                >PCD enviar/atualizar documentação</button>
            <?php } ?>
            <?php if ((int) ($dependent['eh_pvs'] ?? 0) === 1) { ?>
                <?php $hasConditionAction = true; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    data-open-certificates-modal="1"
                    data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                    data-condition-slug="pvs"
                >PVS enviar/atualizar documentação</button>
            <?php } ?>
            <?php if ((int) ($dependent['eh_plm'] ?? 0) === 1) { ?>
                <?php $hasConditionAction = true; ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    data-open-certificates-modal="1"
                    data-person-id="<?php echo e((string) ($dependent['id'] ?? '0')); ?>"
                    data-condition-slug="plm"
                >PLM enviar/atualizar documentação</button>
            <?php } ?>
        </div>
        <?php if (!$hasConditionAction) { ?>
            <span class="muted">Não declarado</span>
        <?php } ?>
    </td>
</tr>
