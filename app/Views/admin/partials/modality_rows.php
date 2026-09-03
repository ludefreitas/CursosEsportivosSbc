<?php if (($modalities ?? []) === []) { ?>
    <tr><td colspan="7" class="muted">Nenhuma modalidade encontrada.</td></tr>
<?php } ?>
<?php foreach (($modalities ?? []) as $modality) { ?>
    <?php $modalityPopupRecords = array_values(array_filter(($modalityPopups ?? []), static fn (array $popup): bool => (int) ($popup['modalidade_id'] ?? 0) === (int) ($modality['id'] ?? 0))); ?>
    <?php
        $modalityPopupsByArea = [];
        foreach ($modalityPopupRecords as $popupRecord) {
            $popupArea = (string) ($popupRecord['area'] ?? '');
            if (in_array($popupArea, ['cursos', 'agenda'], true)) $modalityPopupsByArea[$popupArea] = $popupRecord;
        }
    ?>
    <tr>
        <td><strong><?php echo e((string) ($modality['nome'] ?? '')); ?></strong></td>
        <td><?php echo e(($modality['tipo_ambiente'] ?? '') === 'aquatica' ? 'Aquática' : 'Terrestre'); ?></td>
        <td><?php echo e((string) ((int) ($modality['horarios_ativos'] ?? 0))); ?></td>
        <td><?php echo (int) ($modality['ativo'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></td>
        <?php foreach (['cursos' => 'cursos', 'agenda' => 'agenda'] as $popupArea => $popupAreaLabel) { ?>
            <?php $popup = $modalityPopupsByArea[$popupArea] ?? null; ?>
            <td><div class="admin-modality-popup-cell">
                <?php if ($popup) { ?>
                    <button type="button" class="btn btn-secondary admin-modality-popup-manage" data-popup="<?php echo e((string) json_encode($popup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"><?php echo !empty($popup['publico_ativo']) ? 'Pop-ativo na área ' . ($popupArea === 'cursos' ? 'dos cursos' : 'da agenda') : 'Pop-up ' . $popupAreaLabel . ' inativo'; ?></button>
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
                <?php } else { ?>
                    <button type="button" class="link-button admin-modality-popup-create" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>" data-modality-name="<?php echo e((string) ($modality['nome'] ?? '')); ?>" data-popup-area="<?php echo e($popupArea); ?>">Criar pop-up</button>
                <?php } ?>
            </div></td>
        <?php } ?>
        <td>
            <div class="course-row-actions">
                <button type="button" class="btn btn-secondary admin-modality-edit" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>">Editar</button>
                <button type="button" class="btn btn-danger admin-modality-delete" data-modality-id="<?php echo e((string) ($modality['id'] ?? 0)); ?>" data-modality-name="<?php echo e((string) ($modality['nome'] ?? '')); ?>">Excluir</button>
            </div>
        </td>
    </tr>
<?php } ?>
