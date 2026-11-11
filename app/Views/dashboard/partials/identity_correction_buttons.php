<div class="identity-correction-buttons">
    <?php foreach (\App\Services\IdentityCorrectionService::FIELDS as $field => $label) { ?>
        <button type="button" class="<?php echo !empty($ownCorrectionActions) ? 'link-button' : 'btn btn-compact identity-correction-' . e($field); ?> identity-correction-button"
            id="<?php echo e(\App\Services\IdentityCorrectionService::correctionAnchor((int) $correctionPerson['id'], $field)); ?>"
            data-open-identity-correction="<?php echo e($field); ?>"
            data-person-id="<?php echo (int) $correctionPerson['id']; ?>"
            data-person-name="<?php echo e($correctionPerson['nome_completo']); ?>"><?php echo e(!empty($ownCorrectionActions)
                ? ($field === 'cpf' ? 'Corrigir meu CPF' : 'Corrigir minha data de nascimento')
                : 'Solicitar correção de ' . $label); ?></button>
    <?php } ?>
</div>
