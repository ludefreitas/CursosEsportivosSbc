<?php
$person = $person ?? [];
$conditions = $conditions ?? [];
?>
<div class="popup-head admin-popup-head">
    <div>
        <h3>Condições e documentação</h3>
        <p class="muted">Gerencie a documentação de <?php echo e((string) ($person['nome_completo'] ?? '')); ?> sem sair desta página.</p>
    </div>
    <button type="button" class="popup-close-icon" id="dashboard-certificates-modal-close" aria-label="Fechar documentação">&times;</button>
</div>
<div class="popup-body admin-popup-body dashboard-certificates-modal-body">
    <div class="dashboard-certificate-sections">
        <?php foreach ($conditions as $condition) { ?>
            <?php $certificate = $condition['certificate'] ?? null; ?>
            <section class="content-card compact dashboard-certificate-card" data-condition-section="<?php echo e((string) ($condition['slug'] ?? '')); ?>">
                <div class="section-head dashboard-certificate-head">
                    <div>
                        <h4><?php echo e((string) ($condition['label'] ?? 'Condição')); ?></h4>
                        <p class="muted"><?php echo e((string) ($condition['status_label'] ?? 'Sem status')); ?></p>
                    </div>
                    <span class="chip"><?php echo !empty($condition['declared']) ? 'Declarada' : 'Não declarada'; ?></span>
                </div>

                <?php if (empty($condition['declared'])) { ?>
                    <div class="alert-inline">
                        Esta condição não está marcada no cadastro da pessoa. Para enviar documentação, primeiro edite o cadastro e marque a condição correspondente.
                    </div>
                <?php } else { ?>
                    <div class="dashboard-certificate-meta">
                        <p><strong>Resumo atual:</strong> <?php echo e((string) ($certificate['descricao_resumida'] ?? 'Nenhum resumo enviado.')); ?></p>
                        <?php if (in_array((string) ($condition['slug'] ?? ''), ['pcd', 'plm'], true)) { ?>
                            <p><strong>CID declarado:</strong> <?php echo e((string) ($certificate['codigo_cid_declarado'] ?? '-')); ?></p>
                            <p><strong>Doença declarada:</strong> <?php echo e((string) ($certificate['doenca_declarada'] ?? '-')); ?></p>
                        <?php } ?>
                        <?php if ((string) ($condition['slug'] ?? '') === 'pvs') { ?>
                            <p><strong>Número do CadÚnico (NIS):</strong> <?php echo e((string) ($certificate['numero_nis'] ?? '-')); ?></p>
                        <?php } ?>
                        <?php if ((string) ($condition['slug'] ?? '') === 'pcd') { ?>
                            <?php
                            $selectedTypes = $certificate['tipos_deficiencia_pcd_lista'] ?? [];
                            $typeLabels = [];

                            foreach ($selectedTypes as $selectedType) {
                                if (isset(($condition['disability_type_options'] ?? [])[$selectedType])) {
                                    $typeLabels[] = (string) $condition['disability_type_options'][$selectedType];
                                }
                            }
                            ?>
                            <p><strong>Deficiencias marcadas:</strong> <?php echo e($typeLabels !== [] ? implode(', ', $typeLabels) : '-'); ?></p>
                        <?php } ?>
                        <p><strong>Data de emissao:</strong> <?php echo e(!empty($certificate['data_emissao']) ? date('d/m/Y', strtotime((string) $certificate['data_emissao'])) : '-'); ?></p>
                        <p><strong>Validade:</strong> <?php echo e(!empty($certificate['validade_certificado']) ? date('d/m/Y', strtotime((string) $certificate['validade_certificado'])) : 'Definida na validação pelo professor ou administrador'); ?></p>
                    </div>

                    <?php if (!empty($condition['documents'])) { ?>
                        <div class="dashboard-certificate-files">
                            <strong>Arquivos enviados atualmente</strong>
                            <ul>
                                <?php foreach (($condition['documents'] ?? []) as $document) { ?>
                                    <li>
                                        <a
                                            href="<?php echo e(url('/perfil/certificados/arquivo?document_id=' . (int) ($document['id'] ?? 0))); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        ><span class="dashboard-certificate-file-icon" aria-hidden="true">PDF</span><?php echo e((string) ($document['nome_original'] ?? 'Documento')); ?></a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } else { ?>
                        <p class="muted">Nenhum PDF enviado ainda para esta condição.</p>
                    <?php } ?>

                    <div class="alert-inline dashboard-certificate-warning">
                        Ao atualizar esta condição com novos documentos, o sistema removerá os PDFs que já existem para <?php echo e((string) ($condition['label'] ?? 'esta condição')); ?> e guardará apenas os arquivos selecionados agora.
                    </div>

                    <form
                        method="POST"
                        action="<?php echo e(url('/perfil/certificados/salvar')); ?>"
                        class="stack-form dashboard-certificate-form"
                        data-manual-submit="1"
                        enctype="multipart/form-data"
                    >
                        <input type="hidden" name="person_id" value="<?php echo e((string) ($person['id'] ?? '0')); ?>">
                        <input type="hidden" name="condition_slug" value="<?php echo e((string) ($condition['slug'] ?? '')); ?>">

                        <label>
                            <span>
                                Resumo da documentação
                                <?php if (!empty($certificate['descricao_resumida'])) { ?>
                                    <small class="muted">(o resumo atual foi carregado para esta atualização)</small>
                                <?php } ?>
                            </span>
                            <input type="text" name="descricao_resumida" value="<?php echo e((string) ($certificate['descricao_resumida'] ?? '')); ?>" required>
                        </label>

                        <?php if (in_array((string) ($condition['slug'] ?? ''), ['pcd', 'plm'], true)) { ?>
                            <label>
                                <span>CID declarado</span>
                                <input type="text" name="codigo_cid_declarado" data-cid-code="1" maxlength="5" placeholder="A00.0" value="<?php echo e((string) ($certificate['codigo_cid_declarado'] ?? '')); ?>" required>
                                <small class="muted">Campo obrigatório para <?php echo e(strtoupper((string) ($condition['slug'] ?? ''))); ?> no formato A00.0.</small>
                            </label>

                            <label>
                                <span>Doença declarada</span>
                                <input type="text" name="doenca_declarada" value="<?php echo e((string) ($certificate['doenca_declarada'] ?? '')); ?>" required>
                            </label>
                        <?php } ?>

                        <?php if ((string) ($condition['slug'] ?? '') === 'pvs') { ?>
                            <label>
                                <span>Número do CadÚnico (NIS)</span>
                                <input
                                    type="text"
                                    name="numero_nis"
                                    data-nis-number="1"
                                    maxlength="13"
                                    placeholder="1234 5678 901"
                                    value="<?php echo e((string) ($certificate['numero_nis'] ?? '')); ?>"
                                    required
                                >
                            <small class="muted">Informe obrigatoriamente 11 números do NIS.</small>
                            </label>
                        <?php } ?>

                        <?php if ((string) ($condition['slug'] ?? '') === 'pcd') { ?>
                            <?php
                            $selectedTypes = $certificate['tipos_deficiencia_pcd_lista'] ?? [];
                            $typeOptions = $condition['disability_type_options'] ?? [];
                            ?>
                            <fieldset class="dashboard-certificate-fieldset">
                                <legend>Tipos de deficiencia (PCD)</legend>
                                <div class="dashboard-certificate-checkbox-grid">
                                    <?php foreach ($typeOptions as $typeValue => $typeLabel) { ?>
                                        <label class="checkbox-chip">
                                            <input
                                                type="checkbox"
                                                name="tipos_deficiencia_pcd[]"
                                                value="<?php echo e((string) $typeValue); ?>"
                                                <?php echo in_array((string) $typeValue, $selectedTypes, true) ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo e((string) $typeLabel); ?></span>
                                        </label>
                                    <?php } ?>
                                </div>
                                <small class="muted">Para PCD, marque obrigatoriamente ao menos uma deficiencia.</small>
                            </fieldset>
                        <?php } ?>

                        <label>
                            <span>Data de emissao do documento</span>
                            <input type="date" name="data_emissao" value="<?php echo e((string) ($certificate['data_emissao'] ?? '')); ?>">
                            <small class="muted">A validade não é preenchida por você neste envio. Ela será definida no processo de validação.</small>
                        </label>

                        <label>
                            <span>
                                Observacoes
                                <?php if (!empty($certificate['observacoes'])) { ?>
                                    <small class="muted">(as observações atuais foram carregadas para esta atualização)</small>
                                <?php } ?>
                            </span>
                            <textarea name="observacoes" rows="3" placeholder="Informe detalhes úteis para a análise."><?php echo e((string) ($certificate['observacoes'] ?? '')); ?></textarea>
                        </label>

                        <label class="dashboard-certificate-upload-highlight">
                            <span>Arquivos PDF da condição</span>
                            <input type="file" name="documents[]" accept="application/pdf,.pdf" multiple required>
                            <small>Você pode selecionar mais de um arquivo em PDF neste envio.</small>
                            <small>Todos os arquivos atuais desta condição serão substituidos pelos PDFs selecionados agora.</small>
                        </label>

                        <div class="popup-actions">
                            <button type="button" class="btn btn-secondary" id="dashboard-certificates-modal-close-footer">Fechar/Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $certificate ? 'Atualizar documentação' : 'Enviar documentação'; ?>
                            </button>
                        </div>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
</div>
