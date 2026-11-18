<form class="stack-form hidden" data-class-suspension-form>
    <label>
        <span>Prazo da suspensão</span>
        <select name="suspensao_semanas" required>
            <option value="">Selecione</option>
            <?php for ($weeks = 1; $weeks <= 8; $weeks++) { ?>
                <option value="<?php echo $weeks; ?>"><?php echo $weeks . ($weeks === 1 ? ' semana' : ' semanas'); ?></option>
            <?php } ?>
        </select>
    </label>
    <p class="muted" data-suspension-period-help></p>
    <label>
        <span>Motivo</span>
        <select name="suspensao_motivo" required>
            <option value="">Selecione</option>
            <?php foreach (\App\Services\EnrollmentSuspensionService::REASONS as $reason) { ?>
                <option value="<?php echo e($reason); ?>"><?php echo e($reason); ?></option>
            <?php } ?>
        </select>
    </label>
    <label class="hidden" data-suspension-other>
        <span>Descreva o motivo</span>
        <textarea name="suspensao_outro_motivo" maxlength="100" rows="3"></textarea>
    </label>
    <label data-suspension-vacancy>
        <span>A suspensão libera uma vaga na turma?</span>
        <select name="suspensao_libera_vaga" required>
            <option value="">Selecione</option>
            <option value="0">Não, reservar a vaga do aluno</option>
            <option value="1">Sim, liberar a vaga para outra pessoa</option>
        </select>
    </label>
    <small class="muted">A vaga pertence à categoria da inscrição. No retorno, a matrícula volta a contar nessa categoria, mesmo acima da capacidade da turma.</small>
</form>
