<?php $class = (array) ($attendance['class'] ?? []); $students = (array) ($attendance['students'] ?? []); ?>
<div class="class-attendance-heading" data-class-attendance-date="<?php echo e((string) $attendance['date']); ?>">
    <h3>[<?php echo e((string) ($class['id'] ?? '')); ?>] <?php echo e((string) ($class['nome'] ?? 'Turma')); ?></h3>
    <p><strong><?php echo e((new \DateTimeImmutable((string) $attendance['date']))->format('d/m/Y')); ?></strong> · <?php echo e((string) ($class['dias_semana_descricao'] ?? '')); ?><?php if (!empty($class['hora_inicio'])) { ?>, <?php echo e(substr((string) $class['hora_inicio'], 0, 5)); ?> às <?php echo e(substr((string) $class['hora_fim'], 0, 5)); ?><?php } ?></p>
</div>
<?php if ($students === []) { ?><p class="muted">Nenhum aluno matriculado nesta turma.</p><?php } else { ?>
<div class="class-attendance-list"><?php foreach ($students as $student) { $status = (string) ($student['chamada_status'] ?? ''); ?>
    <article class="class-attendance-student" data-class-attendance-row="<?php echo e((string) $student['inscricao_id']); ?>">
        <strong><?php echo e((string) $student['nome_completo']); ?></strong>
        <div class="class-attendance-certificates"><span>Clínico: <?php echo e((string) (($student['atestado_clinico'] ?? '') ?: 'não enviado')); ?></span><span>Dermatológico: <?php echo e((string) (($student['atestado_dermatologico'] ?? '') ?: 'não enviado')); ?></span></div>
        <div class="class-attendance-options">
            <?php foreach (['presente' => 'Presente', 'ausente' => 'Ausente', 'justificado' => 'Justificar'] as $value => $label) { ?><label class="class-attendance-<?php echo e($value); ?>"><input type="checkbox" data-class-attendance-status="<?php echo e($value); ?>" data-enrollment-id="<?php echo e((string) $student['inscricao_id']); ?>" <?php echo $status === $value ? 'checked' : ''; ?>> <?php echo e($label); ?></label><?php } ?>
        </div>
    </article>
<?php } ?></div><?php } ?>
