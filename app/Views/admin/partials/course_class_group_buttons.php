<?php if (($courseClassGroups ?? []) === []) { ?>
    <span class="muted">Esta temporada ainda não possui turmas cadastradas.</span>
<?php } else { ?>
    <?php foreach ($courseClassGroups as $classGroupId => $groupName) { ?>
        <button type="button" class="btn btn-secondary admin-class-filter-button" <?php echo !empty($secondaryGroupsView) ? 'data-class-secondary' : 'data-class-group'; ?>="<?php echo e((string) $classGroupId); ?>"><?php echo e((string) $groupName); ?></button>
    <?php } ?>
<?php } ?>
