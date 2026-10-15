<?php if (empty($internView) && (int) ($evaluationPersonId ?? 0) > 0 && (int) ($evaluationModalityId ?? 0) > 0) { ?>
    <button type="button" class="link-button" data-evaluation-open="view" data-evaluation-person="<?php echo e((string) $evaluationPersonId); ?>" data-evaluation-modality="<?php echo e((string) $evaluationModalityId); ?>">Aptidão e nível</button>
    <?php if (!empty($evaluationAllowEdit)) { ?><button type="button" class="link-button" data-evaluation-open="edit" data-evaluation-person="<?php echo e((string) $evaluationPersonId); ?>" data-evaluation-modality="<?php echo e((string) $evaluationModalityId); ?>">Atualizar aptidão/nível</button><?php } ?>
<?php } ?>
