<?php if (empty($trainingSpaces)) { ?>
    <tr>
        <td colspan="5">Nenhum espaço disponível para gestão.</td>
    </tr>
<?php } ?>
<?php foreach (($trainingSpaces ?? []) as $space) { ?>
    <tr>
        <td>
            <strong class="admin-training-space-name"><?php echo e($space['nome']); ?></strong>
            <br>
            <small class="muted admin-training-space-location"><?php echo e($space['local_nome']); ?></small>
        </td>
        <td><?php echo e($space['tipo_espaco']); ?></td>
        <td><?php echo e((int) $space['ativo'] === 1 ? 'Ativo' : 'Inativo'); ?></td>
        <td>
            <div class="admin-training-space-suspensions">
                <?php if ((int) ($space['total_suspensoes_ativas'] ?? 0) > 0) { ?>
                    <span class="admin-training-suspension-status is-active">Existe suspensão ativa</span>
                <?php } ?>
                <?php if ((int) ($space['total_suspensoes'] ?? 0) > 0) { ?>
                    <button
                        type="button"
                        class="btn btn-secondary admin-location-suspensions-link"
                        data-space-id="<?php echo e((string) $space['id']); ?>"
                        data-space-name="<?php echo e((string) ($space['nome'] . ' — ' . $space['local_nome'])); ?>"
                        aria-label="Consultar suspensões do espaço <?php echo e((string) $space['nome']); ?>"
                    >
                        Ver suspensões
                    </button>
                <?php } else { ?>
                    <span class="admin-training-suspension-status is-empty">Sem histórico de suspensões</span>
                <?php } ?>
            </div>
        </td>
        <td>
            <div class="admin-training-space-actions">
                <button
                    type="button"
                    class="btn btn-primary admin-space-suspension-open"
                    data-space-id="<?php echo e((string) $space['id']); ?>"
                    data-space-name="<?php echo e((string) ($space['local_nome'] . ' - ' . $space['nome'])); ?>"
                >
                    Suspender temporariamente
                </button>
            </div>
        </td>
    </tr>
<?php } ?>
