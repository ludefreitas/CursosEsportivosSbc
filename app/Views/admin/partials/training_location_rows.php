<?php if (empty($trainingLocations)) { ?>
    <tr><td colspan="9">Nenhum local encontrado.</td></tr>
<?php } ?>
<?php foreach (($trainingLocations ?? []) as $location) { ?>
    <?php
    $locationEditPayload = [
        'id' => (int) $location['id'],
        'nome_local' => (string) $location['nome_local'],
        'apelido_local' => (string) ($location['apelido_local'] ?? ''),
        'admin_local' => (int) ($location['admin_local'] ?? 0),
        'coord_local' => (int) ($location['coord_local'] ?? 0),
        'cep' => (string) ($location['cep'] ?? ''),
        'logradouro' => (string) ($location['logradouro'] ?? ''),
        'numero_endereco' => (string) ($location['numero_endereco'] ?? ''),
        'complemento' => (string) ($location['complemento'] ?? ''),
        'bairro' => (string) ($location['bairro'] ?? ''),
        'cidade' => (string) ($location['cidade'] ?? ''),
        'uf' => (string) ($location['uf'] ?? ''),
        'ativo' => (int) $location['ativo'],
    ];
    $locationPopupRecords = array_values(array_filter(($locationPopups ?? []), static fn (array $popup): bool => (int) ($popup['local_treino_id'] ?? 0) === (int) $location['id']));
    $locationPopupsByArea = [];
    foreach ($locationPopupRecords as $popupRecord) $locationPopupsByArea[(string) $popupRecord['area']] = $popupRecord;
    ?>
    <tr>
        <td>
            <strong><?php echo e(trim((string) ($location['apelido_local'] ?? '')) !== '' ? (string) $location['apelido_local'] : (string) $location['nome_local']); ?></strong>
            <?php if (trim((string) ($location['apelido_local'] ?? '')) !== '') { ?>
                <br><small class="muted"><?php echo e((string) $location['nome_local']); ?></small>
            <?php } ?>
        </td>
        <td><?php echo e(!empty($location['cep']) ? format_cep((string) $location['cep']) : '-'); ?></td>
        <td><?php echo e(format_training_location_address($location)); ?></td>
        <td><?php echo e((string) ($location['cidade'] . '/' . $location['uf'])); ?></td>
        <td><?php echo e(trim((string) ($location['admin_local_nome'] ?? '')) !== '' ? (string) $location['admin_local_nome'] : '-'); ?></td>
        <td><?php echo e(trim((string) ($location['coord_local_nome'] ?? '')) !== '' ? (string) $location['coord_local_nome'] : '-'); ?></td>
        <td><?php echo (int) $location['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
        <td><div class="admin-location-popup-areas">
            <?php foreach (['cursos' => 'cursos', 'agenda' => 'agenda'] as $popupArea => $popupAreaLabel) { $popup = $locationPopupsByArea[$popupArea] ?? null; ?>
                <div class="admin-modality-popup-cell">
                    <?php if ($popup) { ?>
                        <button type="button" class="btn btn-secondary admin-location-popup-manage" data-popup="<?php echo e((string) json_encode($popup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"><?php echo !empty($popup['publico_ativo']) ? 'Pop-up ' . $popupAreaLabel . ' ativo' : 'Pop-up ' . $popupAreaLabel . ' inativo'; ?></button>
                        <small class="admin-modality-popup-instruction">Clique para excluir ou editar.</small>
                        <button type="button" class="link-button popup-preview-trigger" data-preview-mode="stored" data-titulo="<?php echo e((string) ($popup['titulo'] ?? '')); ?>" data-texto-principal="<?php echo e((string) ($popup['texto_principal'] ?? '')); ?>" data-texto-secundario="<?php echo e((string) ($popup['texto_secundario'] ?? '')); ?>" data-imagem-url="<?php echo e((string) ($popup['imagem_url'] ?? '')); ?>" data-rotulo-acao="<?php echo e((string) ($popup['rotulo_acao'] ?? '')); ?>" data-url-acao="<?php echo e((string) ($popup['url_acao'] ?? '')); ?>">Ver prévia</button>
                    <?php } else { ?>
                        <button type="button" class="link-button admin-location-popup-create" data-location-id="<?php echo e((string) $location['id']); ?>" data-location-name="<?php echo e((string) ($location['apelido_local'] ?: $location['nome_local'])); ?>" data-popup-area="<?php echo e($popupArea); ?>">Criar pop-up <?php echo e($popupAreaLabel); ?></button>
                    <?php } ?>
                </div>
            <?php } ?>
        </div></td>
        <td>
            <button
                type="button"
                class="btn btn-warning admin-training-location-edit"
                data-location="<?php echo e((string) json_encode($locationEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
            >Editar</button>
        </td>
    </tr>
<?php } ?>
