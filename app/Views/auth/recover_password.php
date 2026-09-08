<section class="auth-wrap modal-page-wrap">
    <div class="auth-card modal-page-card">
        <h1>Recuperar senha</h1>
        <p>Informe os dados da pessoa titular da conta e cadastre uma nova senha.</p>
        <form method="POST" action="<?php echo e(url('/recuperar-senha')); ?>" class="stack-form" data-ajax-form="1" data-password-recovery-form="1" data-confirm-modal-exit="1">
            <label><span>CPF</span><input type="text" name="cpf" placeholder="000.000.000-00" inputmode="numeric" autocomplete="username" maxlength="14" required><small class="form-field-message hidden" data-recovery-cpf-error="1"></small></label>
            <label><span>Data de nascimento</span><input type="text" name="birth_date" placeholder="dd/mm/aaaa" inputmode="numeric" autocomplete="bday" maxlength="10" data-birth-date-mask="1" required><small class="form-field-message hidden" data-birth-date-error="1"></small></label>
            <label><span>Nova senha</span><input type="password" name="password" minlength="6" autocomplete="new-password" required></label>
            <label><span>Repita a nova senha</span><input type="password" name="password_confirmation" minlength="6" autocomplete="new-password" required><small class="form-field-message hidden" data-password-confirmation-error="1"></small></label>
            <div class="human-verification" data-human-verification="1">
                <input type="hidden" name="human_verification_id" value="<?php echo e((string) ($humanVerification['id'] ?? '')); ?>">
                <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label class="checkbox-line"><input type="checkbox" name="human_verification" value="1" required><span>Não sou robô</span></label>
            </div>
            <button type="submit" class="btn btn-primary">Alterar senha</button>
        </form>
    </div>
</section>
