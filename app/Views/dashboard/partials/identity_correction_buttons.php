<div class="identity-correction-buttons">
    <?php foreach (\App\Services\IdentityCorrectionService::FIELDS as $field => $label) { ?>
        <button type="button" class="btn btn-compact identity-correction-button identity-correction-<?php echo e($field); ?>"
            data-open-identity-correction="<?php echo e($field); ?>"
            data-person-id="<?php echo (int) $correctionPerson['id']; ?>"
            data-person-name="<?php echo e($correctionPerson['nome_completo']); ?>">Solicitar correção de <?php echo e($label); ?></button>
    <?php } ?>
</div>