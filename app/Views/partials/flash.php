<?php $success = flash('success'); ?>
<?php $error = flash('error'); ?>
<?php if ($success) { ?>
    <div class="flash flash-success"><?php echo e($success); ?></div>
<?php } ?>
<?php if ($error) { ?>
    <div class="flash flash-error"><?php echo e($error); ?></div>
<?php } ?>

