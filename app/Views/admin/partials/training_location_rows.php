<?php if (empty($trainingLocations)) { ?>
    <tr><td colspan="8">Nenhum local encontrado.</td></tr>
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
        <td>
            <button
                type="button"
                class="btn btn-warning admin-training-location-edit"
                data-location="<?php echo e((string) json_encode($locationEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
            >Editar</button>
        </td>
    </tr>
<?php } ?>
