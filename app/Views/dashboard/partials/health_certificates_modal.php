<?php
$person = $person ?? [];
$certificates = $certificates ?? [];
$serviceLocationOptions = $service_location_options ?? [];
?>
<div class="popup-head">
    <h3 id="dashboard-health-certificates-modal-title">Atualizar atestados</h3>
    <button type="button" class="popup-close-icon" id="dashboard-health-certificates-modal-close" aria-label="Fechar atestados">&times;</button>
</div>
<div class="popup-body admin-popup-body dashboard-certificates-modal-body">
    <div class="dashboard-certificate-sections">
        <div class="alert-inline dashboard-certificate-warning">
            Envie aqui os atestados clinico e dermatologico de <?php echo e((string) ($person['nome_completo'] ?? '')); ?>. Ao enviar um novo PDF do mesmo tipo, o arquivo anterior sera substituido e voltara para status pendente.
        </div>
        <form
            method="POST"
            action="<?php echo e(url('/perfil/atestados/salvar')); ?>"
            class="stack-form dashboard-health-certificate-form"
            id="dashboard-health-certificate-form"
            data-manual-submit="1"
            enctype="multipart/form-data"
        >
            <input type="hidden" name="person_id" value="<?php echo e((string) ($person['id'] ?? '0')); ?>">
            <input type="hidden" name="target_certificate_type" value="">

            <?php foreach ($certificates as $certificate) { ?>
                <?php $record = $certificate['record'] ?? null; ?>
                <section
                    class="content-card compact dashboard-certificate-card"
                    data-health-certificate-section="<?php echo e((string) ($certificate['slug'] ?? '')); ?>"
                >
                    <div class="section-head dashboard-certificate-head">
                        <div>
                            <h4><?php echo e((string) ($certificate['label'] ?? 'Atestado')); ?></h4>
                            <p class="muted"><?php echo e((string) ($certificate['status_label'] ?? 'Sem arquivo enviado')); ?></p>
                            <div class="top-gap">
                                <span class="dashboard-health-status-badge <?php echo e((string) ($certificate['status_class'] ?? 'is-nao-enviado')); ?>">
                                    <span class="dashboard-health-status-icon"><?php echo e((string) ($certificate['status_icon'] ?? '--')); ?></span>
                                    <span><?php echo e((string) ($certificate['status_label'] ?? 'Sem arquivo enviado')); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-certificate-meta">
                        <p><strong>Arquivo atual:</strong> <?php echo e($record ? (string) ($record['nome_arquivo'] ?? '-') : '-'); ?></p>
                        <p><strong>Data de emissao declarada:</strong> <?php echo e(!empty($record['data_emissao']) ? date('d/m/Y', strtotime((string) $record['data_emissao'])) : '-'); ?></p>
                        <p><strong>Data de emissao validada:</strong> <?php echo e(!empty($record['data_emissao_validada']) ? date('d/m/Y', strtotime((string) $record['data_emissao_validada'])) : '-'); ?></p>
                        <p><strong>CRM do medico:</strong> <?php echo e((string) (($record['crm_medico'] ?? '') !== '' ? $record['crm_medico'] : '-')); ?></p>
                        <p><strong>Local do atendimento:</strong> <?php echo e((string) ($serviceLocationOptions[$record['local_atendimento'] ?? ''] ?? '-')); ?></p>
                        <p><strong>Prazo validado:</strong> <?php echo !empty($record['validade_meses']) ? e((string) $record['validade_meses'] . ' meses') : '-'; ?></p>
                        <p><strong>Validade:</strong> <?php echo e(!empty($record['validade_certificado']) ? date('d/m/Y', strtotime((string) $record['validade_certificado'])) : '-'); ?></p>
                    </div>

                    <div class="grid-two">
                        <label>
                            <span>Data de emissao declarada</span>
                            <input type="date" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_data_emissao" value="<?php echo e((string) ($record['data_emissao'] ?? '')); ?>">
                        </label>
                        <label>
                            <span>CRM do medico</span>
                            <input type="text" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_crm_medico" maxlength="40" placeholder="Ex.: CRM 123456" value="<?php echo e((string) ($record['crm_medico'] ?? '')); ?>">
                        </label>
                    </div>

                    <label>
                        <span>Onde o atendimento foi realizado</span>
                        <select name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_local_atendimento">
                            <option value="">Selecione</option>
                            <?php foreach ($serviceLocationOptions as $optionValue => $optionLabel) { ?>
                                <option value="<?php echo e((string) $optionValue); ?>" <?php echo (string) ($record['local_atendimento'] ?? '') === (string) $optionValue ? 'selected' : ''; ?>>
                                    <?php echo e((string) $optionLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label>
                        <span>Observacoes</span>
                        <textarea name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_observacoes" rows="3" placeholder="Informacoes complementares do atestado"><?php echo e((string) ($record['observacoes'] ?? '')); ?></textarea>
                    </label>

                    <label class="dashboard-certificate-upload-highlight">
                        <span>Enviar novo PDF de <?php echo e(strtolower((string) ($certificate['label'] ?? 'atestado'))); ?></span>
                        <input type="file" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_arquivo" accept="application/pdf,.pdf">
                        <small>Somente PDF. Se enviar um novo arquivo, o anterior desse tipo sera substituido.</small>
                    </label>
                </section>
            <?php } ?>
        </form>
    </div>
</div>
<div class="popup-actions">
    <button type="button" class="btn btn-secondary" id="dashboard-health-certificates-modal-close-footer">Fechar</button>
    <button type="submit" class="btn btn-primary" form="dashboard-health-certificate-form">Salvar atestados</button>
</div>
