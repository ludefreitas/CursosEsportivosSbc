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
        <div
            class="alert-inline dashboard-certificate-warning"
            data-health-certificate-guidance="1"
            data-person-name="<?php echo e((string) ($person['nome_completo'] ?? '')); ?>"
        >
            <span class="dashboard-certificate-guidance-fallback" aria-hidden="true">
            Envie e atualize aqui o atestado do tipo de você selecionou (clícnico ou dermatológico) de <?php echo e((string) ($person['nome_completo'] ?? '')); ?>. Ao enviar um novo PDF do mesmo tipo, o arquivo anterior será substituído e voltara para status pendente.
            </span>
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
                <?php
                $record = $certificate['record'] ?? null;
                $importedRecord = $certificate['imported_record'] ?? null;
                $currentFilePath = trim((string) ($record['caminho_arquivo'] ?? ''));
                $currentFileName = trim((string) ($record['nome_arquivo'] ?? ''));
                $currentFileExists = $currentFilePath !== '' && is_file(ROOT_PATH . '/public' . $currentFilePath);
                ?>
                <section
                    class="content-card compact dashboard-certificate-card"
                    data-health-certificate-section="<?php echo e((string) ($certificate['slug'] ?? '')); ?>"
                >
                    <div class="section-head dashboard-certificate-head">
                        <div>
                            <h4>Dados do atestado</h4>
                            <div>
                                <span class="dashboard-health-status-badge <?php echo e((string) ($certificate['status_class'] ?? 'is-nao-enviado')); ?>">
                                    <span class="dashboard-health-status-icon"><?php echo e((string) ($certificate['status_icon'] ?? '--')); ?></span>
                                    <span><?php echo e((string) ($certificate['status_label'] ?? 'Sem arquivo enviado')); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-certificate-meta">
                        <?php if ($importedRecord) { ?>
                            <p class="alert-inline"><strong>Atestado reconhecido do sistema anterior.</strong> Ele continuará válido até <?php echo e(!empty($importedRecord['validade_certificado']) ? date('d/m/Y', strtotime((string) $importedRecord['validade_certificado'])) : 'a data informada na origem'); ?> ou até um novo atestado ser validado neste sistema.</p>
                        <?php } ?>
                        <p class="dashboard-certificate-current-file">
                            <strong>Arquivo atual:</strong>
                            <?php if ($currentFileExists) { ?>
                                <a
                                    href="<?php echo e(url($currentFilePath)); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Abrir <?php echo e($currentFileName !== '' ? $currentFileName : 'atestado em PDF'); ?>"
                                    aria-label="Abrir <?php echo e($currentFileName !== '' ? $currentFileName : 'atestado em PDF'); ?>"
                                >
                                    <svg class="dashboard-certificate-pdf-symbol" viewBox="0 0 32 38" aria-hidden="true" focusable="false">
                                        <path d="M4 1h16l8 8v28H4z" fill="#d64545" />
                                        <path d="M20 1v8h8" fill="#f7b2b2" />
                                        <text x="16" y="27" text-anchor="middle" fill="#fff" font-size="9" font-weight="700" font-family="Arial, sans-serif">PDF</text>
                                    </svg>
                                </a>
                            <?php } else { ?>
                                <span>-</span>
                            <?php } ?>
                        </p>
                        <p><strong>CRM do médico:</strong> <?php echo e((string) (($record['crm_medico'] ?? '') !== '' ? $record['crm_medico'] : '-')); ?></p>
                        <p><strong>Local do atendimento:</strong> <?php echo e((string) ($serviceLocationOptions[$record['local_atendimento'] ?? ''] ?? '-')); ?></p>
                        <p><strong>Validade:</strong> <?php echo e(!empty($record['validade_certificado']) ? date('d/m/Y', strtotime((string) $record['validade_certificado'])) : '-'); ?></p>
                    </div>

                    <div class="grid-three">
                        <label>
                            <span>Data de emissão</span>
                            <input type="date" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_data_emissao" value="<?php echo e((string) ($record['data_emissao'] ?? '')); ?>">
                        </label>
                        <label>
                            <span>CRM do médico</span>
                            <input type="text" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_crm_medico" maxlength="40" placeholder="Ex.: CRM 123456" value="<?php echo e((string) ($record['crm_medico'] ?? '')); ?>">
                        </label>

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

                    </div>

                    <label>
                        <span>Observações</span>
                        <textarea name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_observacoes" rows="3" placeholder="Informações complementares do atestado"><?php echo e((string) ($record['observacoes'] ?? '')); ?></textarea>
                    </label>

                    <label class="dashboard-certificate-upload-highlight">
                        <span>Enviar novo PDF</span>
                        <input type="file" name="<?php echo e((string) ($certificate['slug'] ?? '')); ?>_arquivo" accept="application/pdf,.pdf">
                        <small>Somente PDF. Se enviar um novo arquivo, o anterior desse tipo será substituído.</small>
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
