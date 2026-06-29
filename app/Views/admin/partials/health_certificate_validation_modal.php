<?php
$person = $person ?? [];
$certificateType = $certificate_type ?? [];
$certificate = $certificate ?? [];
$pendingReason = $pending_reason ?? '';
$serviceLocationLabel = $service_location_label ?? '-';
$validityMonthOptions = $validity_month_options ?? [6, 12, 18, 24];
$currentStatus = (string) ($certificate['status_validacao'] ?? 'pendente');
$currentValidatedIssueDate = (string) ($certificate['data_emissao_validada'] ?? '');
$currentValidityMonths = (int) ($certificate['validade_meses'] ?? 0);
$currentValidationNote = (string) ($certificate['observacao_validacao'] ?? '');
?>
<div class="popup-head admin-popup-head">
    <div>
        <h3 id="admin-health-certificate-validation-title">Validar atestado de saude</h3>
        <p class="muted">Analise o <?php echo e(strtolower((string) ($certificateType['label'] ?? 'atestado'))); ?> de <?php echo e((string) ($person['nome_completo'] ?? '')); ?> sem sair desta pagina.</p>
    </div>
    <button type="button" class="popup-close-icon" id="admin-health-certificate-validation-close" aria-label="Fechar validacao do atestado">&times;</button>
</div>

<div class="popup-body admin-popup-body admin-condition-validation-body">
    <div class="popup-meta-list">
        <p><strong>Nome:</strong> <?php echo e((string) ($person['nome_completo'] ?? '-')); ?></p>
        <p><strong>CPF:</strong> <?php echo e(format_cpf((string) ($person['cpf'] ?? ''))); ?></p>
        <p><strong>Responsavel:</strong> <?php echo e((string) (($person['nome_responsavel'] ?? '') !== '' ? $person['nome_responsavel'] : '-')); ?></p>
        <p><strong>Tipo de atestado:</strong> <?php echo e((string) ($certificateType['label'] ?? '-')); ?></p>
        <p><strong>Status atual:</strong> <?php echo e(ucfirst((string) ($certificate['status_validacao'] ?? 'pendente'))); ?></p>
        <p><strong>Data de emissao declarada:</strong> <?php echo e(!empty($certificate['data_emissao']) ? date('d/m/Y', strtotime((string) $certificate['data_emissao'])) : '-'); ?></p>
        <p><strong>CRM do medico:</strong> <?php echo e((string) (($certificate['crm_medico'] ?? '') !== '' ? $certificate['crm_medico'] : '-')); ?></p>
        <p><strong>Local do atendimento:</strong> <?php echo e((string) $serviceLocationLabel); ?></p>
        <p><strong>Arquivo enviado:</strong> <?php echo e((string) (($certificate['nome_arquivo'] ?? '') !== '' ? $certificate['nome_arquivo'] : '-')); ?></p>
        <p><strong>Pendencia atual:</strong> <?php echo e((string) ($pendingReason !== '' ? $pendingReason : 'Nenhuma')); ?></p>
        <p><strong>Observacoes da pessoa:</strong> <?php echo e((string) (($certificate['observacoes'] ?? '') !== '' ? $certificate['observacoes'] : '-')); ?></p>
    </div>

    <div class="admin-condition-validation-documents">
        <strong>Arquivo enviado</strong>
        <div class="admin-document-links">
            <a
                href="<?php echo e(url((string) ($certificate['caminho_arquivo'] ?? '#'))); ?>"
                target="_blank"
                rel="noopener noreferrer"
            ><span class="dashboard-certificate-file-icon" aria-hidden="true">PDF</span><?php echo e((string) ($certificate['nome_arquivo'] ?? 'atestado.pdf')); ?></a>
        </div>
    </div>

    <form method="POST" action="<?php echo e(url('/admin/atestados/validacao/salvar')); ?>" class="stack-form" id="admin-health-certificate-validation-form">
        <input type="hidden" name="person_id" value="<?php echo e((string) ($person['id'] ?? '0')); ?>">
        <input type="hidden" name="certificate_type" value="<?php echo e((string) ($certificateType['slug'] ?? '')); ?>">

        <div class="grid-two">
            <label>
                <span>Status do atestado</span>
                <select name="status" id="admin-health-certificate-validation-status" required>
                    <option value="pendente" <?php echo $currentStatus === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="reprovado" <?php echo $currentStatus === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                    <option value="validado" <?php echo $currentStatus === 'validado' ? 'selected' : ''; ?>>Validado</option>
                </select>
            </label>
            <label>
                <span>Data de emissao validada</span>
                <input type="date" name="data_emissao_validada" id="admin-health-certificate-validation-issued-at" value="<?php echo e($currentValidatedIssueDate); ?>">
                <small class="muted">Essa data sera usada para calcular a validade final do atestado.</small>
            </label>
        </div>

        <label>
            <span>Prazo de validade apos a emissao validada</span>
            <select name="validade_meses" id="admin-health-certificate-validation-months">
                <option value="">Selecione</option>
                <?php foreach ($validityMonthOptions as $monthOption) { ?>
                    <option value="<?php echo e((string) $monthOption); ?>" <?php echo $currentValidityMonths === (int) $monthOption ? 'selected' : ''; ?>>
                        <?php echo e((string) $monthOption); ?> meses
                    </option>
                <?php } ?>
            </select>
            <small class="muted">Opcoes disponiveis: 6, 12, 18 ou 24 meses.</small>
        </label>

        <label>
            <span>Observacao da validacao</span>
            <textarea name="observacao_validacao" id="admin-health-certificate-validation-note" rows="4" placeholder="Explique a decisao tomada nesta validacao."><?php echo e($currentValidationNote); ?></textarea>
            <small class="muted">Ao reprovar um atestado, informe o motivo para orientar o novo envio.</small>
        </label>

        <div class="popup-builder-actions">
            <button type="button" class="btn btn-secondary" id="admin-health-certificate-validation-cancel">Fechar/Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar validacao</button>
        </div>
    </form>
</div>
