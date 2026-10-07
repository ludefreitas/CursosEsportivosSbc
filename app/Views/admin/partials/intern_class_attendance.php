<?php
$class = (array) ($attendance['class'] ?? []);
$students = array_values(array_filter((array) ($attendance['students'] ?? []), static fn (array $student): bool => ($student['matricula_status'] ?? '') === 'matriculada'));
?>
<div class="class-attendance-heading" data-class-attendance-date="<?php echo e((string) $attendance['date']); ?>">
    <h3>[<?php echo e((string) ($class['id'] ?? '')); ?>] <?php echo e((string) ($class['nome'] ?? 'Turma')); ?></h3>
    <p><?php echo e((new DateTimeImmutable((string) $attendance['date']))->format('d/m/Y')); ?></p>
</div>
<?php if ($students === []) { ?>
    <p class="muted">Nenhum aluno para chamada nesta turma.</p>
<?php } else { ?>
    <div class="class-attendance-list">
        <?php foreach ($students as $student) { $status = (string) ($student['chamada_status'] ?? ''); ?>
            <article class="class-attendance-student" data-class-attendance-row="<?php echo e((string) $student['inscricao_id']); ?>">
                <div class="class-attendance-student-head">
                    <span class="class-attendance-mark is-<?php echo e($status ?: 'pending'); ?>" data-class-attendance-mark><?php echo ['presente' => '✓', 'ausente' => '×', 'justificado' => 'J'][$status] ?? '−'; ?></span>
                    <strong><?php echo e((string) $student['nome_completo']); ?></strong>
                </div>
                <div class="class-attendance-options">
                    <?php foreach (['presente' => 'Presente', 'ausente' => 'Ausente', 'justificado' => 'Justificar'] as $value => $label) { ?>
                        <label class="class-attendance-<?php echo e($value); ?>"><input type="checkbox" data-class-attendance-status="<?php echo e($value); ?>" data-enrollment-id="<?php echo e((string) $student['inscricao_id']); ?>" data-person-name="<?php echo e((string) $student['nome_completo']); ?>" data-birth-date="<?php echo e((string) ($student['data_nascimento'] ?? '')); ?>" data-current-justification="<?php echo e((string) ($student['justificativa'] ?? '')); ?>" <?php echo $status === $value ? 'checked' : ''; ?>> <?php echo e($label); ?></label>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>
<?php } ?>
