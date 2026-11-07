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
        <table class="data-table admin-validation-table">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Seleção</span></th>
                    <th scope="col"><span class="sr-only">Dados do atestado</span></th>
                    <th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($healthCertificateValidationRows === []) { ?>
                    <tr>
                        <td colspan="3" class="muted">Nenhum atestado aguardando validação neste momento.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($healthCertificateValidationRows as $row) { ?>
                    <tr>
                        <td class="responsive-table-full-cell" data-responsive-full-cell>
                            <label class="checkbox-chip"><span>Selecionar</span><input type="checkbox" data-validation-notify-row value="<?php echo e((string) $row['id']); ?>"
                                data-validation-notify-label="<?php echo e($row['nome_completo'] . ' · ' . $row['tipo_label']); ?>"
                                aria-label="<?php echo e('Selecionar ' . $row['nome_completo'] . ' — ' . $row['tipo_label']); ?>"></label>
                        </td>
                        <td class="responsive-table-full-cell" data-responsive-full-cell>
                            <div class="admin-validation-details">
                                <span>Nome: <strong><?php echo e((string) ($row['nome_completo'] ?? '')); ?></strong></span>
                                <strong><?php echo e(!empty($professorView) ? format_cpf_professor((string) ($row['cpf'] ?? '')) : format_cpf((string) ($row['cpf'] ?? ''))); ?></strong>
                                <span>Responsável: <strong><?php echo e((string) (($row['nome_responsavel'] ?? '') !== '' ? $row['nome_responsavel'] : '-')); ?></strong></span>
                                <span>Tipo: <strong><?php echo e((string) ($row['tipo_label'] ?? 'Atestado')); ?></strong></span>
                                <span>Emissão declarada: <strong><?php echo e(!empty($row['data_emissao']) ? date('d/m/Y', strtotime((string) $row['data_emissao'])) : '-'); ?></strong></span>
                                <span>CRM: <strong><?php echo e((string) (($row['crm_medico'] ?? '') !== '' ? $row['crm_medico'] : '-')); ?></strong></span>
                                <span>Atendimento: <strong><?php echo e((string) ($row['local_atendimento_label'] ?? '-')); ?></strong></span>
                                <span>Status: <strong><?php echo e(ucfirst((string) ($row['status_validacao'] ?? 'pendente'))); ?></strong></span>
                                <span>Pendência: <strong><?php echo e((string) ($row['pendencia_validacao'] ?? '')); ?></strong></span>
                            </div>
                        </td>
                        <td>
                            <div class="admin-validation-actions">
                                <button
                                    type="button"
                                    class="link-button"
                                    data-open-health-certificate-validation="1"
                                    data-person-id="<?php echo e((string) ($row['pessoa_id'] ?? '0')); ?>"
                                    data-certificate-type="<?php echo e((string) ($row['tipo_atestado'] ?? '')); ?>"
                                >Validar atestado</button>
                                <button type="button" class="link-button" data-notification-send="atestado" data-person-id="<?php echo e((string) ($row['pessoa_id'] ?? '0')); ?>" data-certificate-type="<?php echo e((string) ($row['tipo_atestado'] ?? '')); ?>">Notificar responsável</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
