<?php if (($modalities ?? []) === []) { ?>
    <tr><td colspan="<?php echo !empty($professorView) ? '2' : '5'; ?>" class="muted">Nenhuma modalidade encontrada.</td></tr>
<?php } ?>
<?php foreach (($modalities ?? []) as $modality) { ?>
    <?php $modalityPopupRecords = array_values(array_filter(($modalityPopups ?? []), static fn (array $popup): bool => (int) ($popup['modalidade_id'] ?? 0) === (int) ($modality['id'] ?? 0))); ?>
    <?php
        $modalityPopupsByArea = ['cursos' => [], 'agenda' => []];
        foreach ($modalityPopupRecords as $popupRecord) {
            $popupArea = (string) ($popupRecord['area'] ?? '');
            if (in_array($popupArea, ['cursos', 'agenda'], true)) $modalityPopupsByArea[$popupArea][] = $popupRecord;
        }
    ?>
    <tr>
        <td class="admin-modality-summary"><strong><?php echo e((string) ($modality['nome'] ?? '')); ?></strong> - <?php echo e(($modality['tipo_ambiente'] ?? '') === 'aquatica' ? 'Aquática' : 'Terrestre'); ?> - <strong><?php echo (int) ($modality['ativo'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></strong></td>
        <?php if (empty($professorView)) { ?><td><?php echo e((string) ($modality['criador_nome'] ?? 'Não informado')); ?></td><?php } ?>
        <?php if (empty($professorView)) { ?>
        <?php foreach (['cursos' => 'cursos', 'agenda' => 'agenda'] as $popupArea => $popupAreaLabel) { ?>
            <?php $areaPopups = $modalityPopupsByArea[$popupArea] ?? []; ?>
            <td><div class="admin-modality-popup-cell">
                <?php foreach ($areaPopups as $popup) { ?>
                    <button type="button" class="btn btn-secondary admin-modality-popup-manage" data-popup="<?php echo e((string) json_encode($popup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"><?php echo !empty($popup['publico_ativo']) ? 'Pop-ativo na área ' . ($popupArea === 'cursos' ? 'dos cursos' : 'da agenda') : 'Pop-up ' . $popupAreaLabel . ' inativo'; ?></button>
                    <small class="admin-modality-popup-scope"><?php echo !empty($popup['local_treino_id']) ? 'Local: ' . e((string) ($popup['local_apelido'] ?: $popup['nome_local'])) : 'Todos os locais'; ?></small>
                    <small class="admin-modality-popup-instruction">Clique para excluir ou editar.</small>
                    <button
                        type="button"
                        class="link-button popup-preview-trigger"
                        data-preview-mode="stored"
                        data-titulo="<?php echo e((string) ($popup['titulo'] ?? '')); ?>"
                        data-texto-principal="<?php echo e((string) ($popup['texto_principal'] ?? '')); ?>"
                        data-texto-secundario="<?php echo e((string) ($popup['texto_secundario'] ?? '')); ?>"
                        data-imagem-url="<?php echo e((string) ($popup['imagem_url'] ?? '')); ?>"
                        data-rotulo-acao="<?php echo e((string) ($popup['rotulo_acao'] ?? '')); ?>"
                        data-url-acao="<?php echo e((string) ($popup['url_acao'] ?? '')); ?>"
                    >Ver prévia</button>
                <?php } ?>
                <button type="button" class="link-button admin-modality-popup-create" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>" data-modality-name="<?php echo e((string) ($modality['nome'] ?? '')); ?>" data-popup-area="<?php echo e($popupArea); ?>"><?php echo $areaPopups === [] ? 'Criar pop-up' : 'Criar para outro local'; ?></button>
            </div></td>
        <?php } ?>
        <?php } ?>
        <td class="admin-modality-actions-cell">
            <div class="course-row-actions admin-modality-actions">
                <?php if (empty($professorView) || ((int) ($modality['criador_conta_id'] ?? 0) > 0 && (int) ($modality['criador_conta_id'] ?? 0) === (int) ($professorAccountId ?? 0))) { ?>
                <button type="button" class="btn btn-secondary admin-modality-edit" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>">Editar</button>
                <?php } ?>
                <?php if (empty($professorView)) { ?>
                <button type="button" class="btn btn-danger admin-modality-delete" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>" data-modality-name="<?php echo e((string) ($modality['nome'] ?? '')); ?>">Excluir</button>
                <?php } ?>
            </div>
        </td>
    </tr>
<?php } ?>
