<?php if (empty($internView) && (int) ($evaluationPersonId ?? 0) > 0 && (int) ($evaluationModalityId ?? 0) > 0) { ?>
    <button type="button" class="link-button modality-evaluation-link" data-evaluation-open="view" data-evaluation-person="<?php echo e((string) $evaluationPersonId); ?>" data-evaluation-modality="<?php echo e((string) $evaluationModalityId); ?>">Aptidão e nível</button>
<?php } ?>
