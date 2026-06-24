<?php
$person = $person ?? [];
$condition = $condition ?? [];
$certificate = $certificate ?? null;
$documents = $documents ?? [];
$pendingReason = $pending_reason ?? null;
$currentStatus = (string) ($certificate['status'] ?? 'pendente');
$currentValidationNote = (string) ($certificate['observacao_validacao'] ?? '');
$currentValidityDate = (string) ($certificate['validade_certificado'] ?? '');
$currentValidatedCid = (string) ($certificate['codigo_cid_validado'] ?? '');
$currentValidatedDisease = (string) ($certificate['doenca_validada'] ?? '');
$disabilityTypeOptions = $disability_type_options ?? [];
$selectedDisabilityTypes = $certificate['tipos_deficiencia_pcd_lista'] ?? [];
$selectedDisabilityLabels = [];

foreach ($selectedDisabilityTypes as $selectedType) {
    if (isset($disabilityTypeOptions[$selectedType])) {
        $selectedDisabilityLabels[] = (string) $disabilityTypeOptions[$selectedType];
    }
}
?>
<div class="popup-head admin-popup-head">
    <div>
        <h3 id="admin-condition-validation-title">Validar condicao especial</h3>
        <p class="muted">Analise a documentacao de <?php echo e((string) ($person['nome_completo'] ?? '')); ?> sem sair desta pagina.</p>
    </div>
    <button type="button" class="popup-close-icon" id="admin-condition-validation-close" aria-label="Fechar validacao">&times;</button>
</div>

<div class="popup-body admin-popup-body admin-condition-validation-body">
    <div class="popup-meta-list">
        <p><strong>Nome:</strong> <?php echo e((string) ($person['nome_completo'] ?? '-')); ?></p>
        <p><strong>CPF:</strong> <?php echo e(format_cpf((string) ($person['cpf'] ?? ''))); ?></p>
        <p><strong>Responsavel:</strong> <?php echo e((string) (($person['nome_responsavel'] ?? '') !== '' ? $person['nome_responsavel'] : '-')); ?></p>
        <p><strong>Condicao:</strong> <?php echo e((string) ($condition['label'] ?? '-')); ?></p>
        <p><strong>Status atual:</strong> <?php echo e((string) ($certificate['status'] ?? 'Sem certificado enviado')); ?></p>
        <p><strong>Resumo informado pela pessoa:</strong> <?php echo e((string) ($certificate['descricao_resumida'] ?? '-')); ?></p>
        <?php if (in_array((string) ($condition['slug'] ?? ''), ['pcd', 'plm'], true)) { ?>
            <p><strong>CID declarado:</strong> <?php echo e((string) ($certificate['codigo_cid_declarado'] ?? '-')); ?></p>
            <p><strong>Doenca declarada:</strong> <?php echo e((string) ($certificate['doenca_declarada'] ?? '-')); ?></p>
        <?php } ?>
        <?php if ((string) ($condition['slug'] ?? '') === 'pcd') { ?>
            <p><strong>Deficiencias declaradas:</strong> <?php echo e($selectedDisabilityLabels !== [] ? implode(', ', $selectedDisabilityLabels) : '-'); ?></p>
        <?php } ?>
        <p><strong>Observacoes informadas pela pessoa:</strong> <?php echo e((string) ($certificate['observacoes'] ?? '-')); ?></p>
        <p><strong>Data de emissao:</strong> <?php echo e(!empty($certificate['data_emissao']) ? date('d/m/Y', strtotime((string) $certificate['data_emissao'])) : '-'); ?></p>
        <p><strong>Pendencia atual:</strong> <?php echo e((string) ($pendingReason ?? 'Nenhuma')); ?></p>
    </div>

    <div class="admin-condition-validation-documents">
        <strong>Documentos enviados</strong>
        <?php if ($documents === []) { ?>
            <p class="muted">Nenhum PDF foi enviado para esta condicao ainda.</p>
        <?php } else { ?>
            <div class="admin-document-links">
                <?php foreach ($documents as $document) { ?>
                    <a
                        href="<?php echo e(url('/admin/certificados/arquivo?document_id=' . (int) ($document['id'] ?? 0))); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    ><span class="dashboard-certificate-file-icon" aria-hidden="true">PDF</span><?php echo e((string) ($document['nome_original'] ?? 'documento.pdf')); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <form method="POST" action="<?php echo e(url('/admin/certificados/validacao/salvar')); ?>" class="stack-form" id="admin-condition-validation-form" data-ajax-form="1">
        <input type="hidden" name="person_id" value="<?php echo e((string) ($person['id'] ?? '0')); ?>">
        <input type="hidden" name="condition_slug" value="<?php echo e((string) ($condition['slug'] ?? '')); ?>">

        <div class="grid-two">
            <label>
                <span>Status do certificado</span>
                <select name="status" id="admin-condition-validation-status" <?php echo $documents === [] ? 'disabled' : ''; ?> required>
                    <option value="pendente" <?php echo $currentStatus === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="reprovado" <?php echo $currentStatus === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                    <option value="validado" <?php echo $currentStatus === 'validado' ? 'selected' : ''; ?>>Validado</option>
                    <option value="validado_parcial" <?php echo $currentStatus === 'validado_parcial' ? 'selected' : ''; ?>>Validado parcial</option>
                </select>
            </label>
            <label>
                <span>Validade do certificado</span>
                <input type="date" name="validade_certificado" id="admin-condition-validation-validity" value="<?php echo e($currentValidityDate); ?>" <?php echo $documents === [] ? 'disabled' : ''; ?>>
                <small class="muted">Preencha quando a analise resultar em certificado valido ou validado parcialmente.</small>
            </label>
        </div>

        <?php if (in_array((string) ($condition['slug'] ?? ''), ['pcd', 'plm'], true)) { ?>
            <label>
                <span>CID validado</span>
                <input type="text" name="codigo_cid_validado" id="admin-condition-validation-cid" data-cid-code="1" maxlength="5" placeholder="A00.0" value="<?php echo e($currentValidatedCid); ?>" <?php echo $documents === [] ? 'disabled' : ''; ?>>
                <small class="muted">Para PCD e PLM, informe o CID validado no formato A00.0 quando o status for validado ou validado parcial.</small>
            </label>
            <label>
                <span>Doenca validada</span>
                <input type="text" name="doenca_validada" value="<?php echo e($currentValidatedDisease); ?>" <?php echo $documents === [] ? 'disabled' : ''; ?>>
            </label>
        <?php } ?>

        <label>
            <span>Observacao da validacao</span>
            <textarea name="observacao_validacao" id="admin-condition-validation-note" rows="4" placeholder="Explique a decisao tomada nesta validacao." <?php echo $documents === [] ? 'disabled' : ''; ?>><?php echo e($currentValidationNote); ?></textarea>
            <small class="muted">Ao marcar como validado parcial, esta observacao passa a ser obrigatoria.</small>
        </label>

        <?php if ($documents === []) { ?>
            <p class="muted">Enquanto nao houver PDF enviado para esta condicao, nao ha como concluir a validacao administrativa.</p>
        <?php } ?>

        <div class="popup-builder-actions">
            <button type="button" class="btn btn-secondary" id="admin-condition-validation-cancel">Fechar/Cancelar</button>
            <button type="submit" class="btn btn-primary" <?php echo $documents === [] ? 'disabled' : ''; ?>>Salvar validacao</button>
        </div>
    </form>
</div>
