<?php
$healthCertificateValidationRows = $healthCertificateValidationRows ?? [];
?>

<article class="content-card top-gap" id="admin-health-certificate-validation-panel">
    <h2>Atestados de saúde para validar</h2>
    <p class="muted">Esta fila mostra os atestados clínicos e dermatológicos enviados pelos usuários e dependentes que ainda aguardam análise administrativa.</p>

    <?php if ($healthCertificateValidationRows !== []) { ?>
        <div class="admin-inline-links top-gap">
            <label class="checkbox-chip">
                <input type="checkbox" data-validation-notify-all>
                <span>Selecionar todos</span>
            </label>
            <button type="button" class="btn btn-primary" data-validation-notify="atestado" disabled>Notificar selecionados</button>
            <span class="muted" data-validation-notify-count aria-live="polite"></span>
        </div>
    <?php } ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Selecionar</th>
                    <th>Nome</th>
                    <th>Responsável</th>
                    <th>Tipo</th>
                    <th>Emissão declarada</th>
                    <th>CRM</th>
                    <th>Atendimento</th>
                    <th>Status</th>
                    <th>Pendência</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($healthCertificateValidationRows === []) { ?>
                    <tr>
                        <td colspan="10" class="muted">Nenhum atestado aguardando validação neste momento.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($healthCertificateValidationRows as $row) { ?>
                    <tr>
                        <td>
                            <input type="checkbox" data-validation-notify-row value="<?php echo e((string) $row['id']); ?>"
                                data-validation-notify-label="<?php echo e($row['nome_completo'] . ' · ' . $row['tipo_label']); ?>"
                                aria-label="<?php echo e('Selecionar ' . $row['nome_completo'] . ' — ' . $row['tipo_label']); ?>">
                        </td>
                        <td>
                            <strong><?php echo e((string) ($row['nome_completo'] ?? '')); ?></strong><br>
                            <span class="muted"><?php echo e(!empty($professorView) ? format_cpf_professor((string) ($row['cpf'] ?? '')) : format_cpf((string) ($row['cpf'] ?? ''))); ?></span>
                        </td>
                        <td><?php echo e((string) (($row['nome_responsavel'] ?? '') !== '' ? $row['nome_responsavel'] : '-')); ?></td>
                        <td><?php echo e((string) ($row['tipo_label'] ?? 'Atestado')); ?></td>
                        <td><?php echo e(!empty($row['data_emissao']) ? date('d/m/Y', strtotime((string) $row['data_emissao'])) : '-'); ?></td>
                        <td><?php echo e((string) (($row['crm_medico'] ?? '') !== '' ? $row['crm_medico'] : '-')); ?></td>
                        <td><?php echo e((string) ($row['local_atendimento_label'] ?? '-')); ?></td>
                        <td><?php echo e(ucfirst((string) ($row['status_validacao'] ?? 'pendente'))); ?></td>
                        <td><?php echo e((string) ($row['pendencia_validacao'] ?? '')); ?></td>
                        <td>
                            <button
                                type="button"
                                class="link-button"
                                data-open-health-certificate-validation="1"
                                data-person-id="<?php echo e((string) ($row['pessoa_id'] ?? '0')); ?>"
                                data-certificate-type="<?php echo e((string) ($row['tipo_atestado'] ?? '')); ?>"
                            >Validar atestado</button>
                            <button type="button" class="link-button" data-notification-send="atestado" data-person-id="<?php echo e((string) ($row['pessoa_id'] ?? '0')); ?>" data-certificate-type="<?php echo e((string) ($row['tipo_atestado'] ?? '')); ?>">Notificar responsável</button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
