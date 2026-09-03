<?php if (($courseClassGroups ?? []) === []) { ?>
    <span class="muted">Esta temporada ainda não possui turmas cadastradas.</span>
<?php } else { ?>
    <?php foreach ($courseClassGroups as $groupId => $groupName) { ?>
        <button type="button" class="btn btn-secondary admin-class-filter-button" data-class-group="<?php echo e((string) $groupId); ?>"><?php echo e((string) $groupName); ?></button>
    <?php } ?>
<?php } ?>
