<?php if (empty($courseSeasonOrigins)) { ?>
    <tr><td colspan="3" class="muted">Nenhuma origem da temporada cadastrada.</td></tr>
<?php } else { ?>
    <?php foreach ($courseSeasonOrigins as $origin) { ?>
        <tr>
            <td><?php echo e((string) ($origin['nome'] ?? '')); ?></td>
            <td><span class="chip"><?php echo (int) ($origin['ativo'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></span></td>
            <td>
                <div class="course-row-actions">
                    <button
                        type="button"
                        class="btn btn-secondary admin-season-origin-edit"
                        data-origin-id="<?php echo e((string) ($origin['id'] ?? '')); ?>"
                        data-origin-name="<?php echo e((string) ($origin['nome'] ?? '')); ?>"
                        data-origin-active="<?php echo (int) ($origin['ativo'] ?? 0) === 1 ? '1' : '0'; ?>"
                    >Editar</button>
                    <button
                        type="button"
                        class="btn btn-secondary admin-season-origin-delete"
                        data-origin-id="<?php echo e((string) ($origin['id'] ?? '')); ?>"
                        data-origin-name="<?php echo e((string) ($origin['nome'] ?? '')); ?>"
                    >Excluir</button>
                </div>
            </td>
        </tr>
    <?php } ?>
<?php } ?>
