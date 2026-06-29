<?php
$healthCertificateValidationRows = $healthCertificateValidationRows ?? [];
?>

<article class="content-card top-gap" id="admin-health-certificate-validation-panel">
    <h2>Atestados de saude para validar</h2>
    <p class="muted">Esta fila mostra os atestados clinicos e dermatologicos enviados pelos usuarios e dependentes que ainda aguardam analise administrativa.</p>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Responsavel</th>
                    <th>Tipo</th>
                    <th>Emissao declarada</th>
                    <th>CRM</th>
                    <th>Atendimento</th>
                    <th>Status</th>
                    <th>Pendencia</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($healthCertificateValidationRows === []) { ?>
                    <tr>
                        <td colspan="9" class="muted">Nenhum atestado aguardando validacao neste momento.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($healthCertificateValidationRows as $row) { ?>
                    <tr>
                        <td>
                            <strong><?php echo e((string) ($row['nome_completo'] ?? '')); ?></strong><br>
                            <span class="muted"><?php echo e(format_cpf((string) ($row['cpf'] ?? ''))); ?></span>
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
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</article>
