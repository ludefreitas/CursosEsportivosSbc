<section class="auth-wrap modal-page-wrap">
    <div class="auth-card auth-card-wide modal-page-card modal-page-card-wide">
        <h1>Cadastro do Responsável</h1>

        <p class="muted" style="font-size: 14px;">Somente pessoas maiores de 18 anos podem criar conta. Menores de 18 anos serão adicionados, no sistema, como dependentes de um responsável maior de idade.</p>
        <p class="muted" style="font-size: 14px;">O nome completo deve ter no mínimo 14 caracteres e pode conter letras, espaços, hífen e apóstrofo. Os espaços no início e no fim são removidos automaticamente.</p>
        <div class="alert-inline dashboard-dependent-attention">
            Preencha o CPF com atenção. Depois de vinculado ao cadastro da pessoa, qualquer correção desse dado pode depender do suporte.
        </div>

        <form method="POST" action="<?php echo e(url('/cadastro')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1" data-confirm-modal-exit="1">
            <label>
                <span>Nome completo</span>
                <input type="text" name="full_name" value="<?php echo old('full_name'); ?>" required>
            </label>

            <label>
                <span>CPF</span>
                <input type="text" name="cpf" value="<?php echo old('cpf'); ?>" placeholder="000.000.000-00" data-cpf-cadastro="1" required>
            </label>
            <label>
                <span>Data de nascimento</span>
                <input type="date" name="birth_date" value="<?php echo old('birth_date'); ?>" autocomplete="bday" data-responsible-birth-date="1" required>
                <small class="muted">Confira a data de nascimento antes de criar o cadastro. Após o envio, qualquer correção somente poderá ser realizada pela equipe de suporte do site.</small>
            </label>

            <div class="grid-two">
                <label>
                    <span>Criar Senha</span>
                    <input type="password" name="password" minlength="6" required>
                </label>
                <label>
                    <span>Repetir Senha Criada</span>
                    <input type="password" name="password_confirmation" minlength="6" required>
                </label>
            </div>

            <label class="checkbox-line">
                <input type="checkbox" name="adult_ack" value="1" <?php echo old('adult_ack') === '1' ? 'checked' : ''; ?>>
                <span>Confirmo que sou maior de 18 anos e estou criando o meu próprio cadastro como responsável.</span>
            </label>

            <label class="checkbox-line">
                <input type="checkbox" name="accept_terms" value="1" <?php echo old('accept_terms') === '1' ? 'checked' : ''; ?>>
                <span>Li e aceito as políticas de privacidade e os termos de uso do site.</span>
            </label>

            <div class="human-verification" data-human-verification="1">
                <input type="hidden" name="human_verification_id" value="<?php echo e((string) ($humanVerification['id'] ?? '')); ?>">
                <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label class="checkbox-line"><input type="checkbox" name="human_verification" value="1" required><span>Não sou robô</span></label>
            </div>

            <button type="submit" class="btn btn-primary">Criar cadastro</button>
        </form>
    </div>
</section>
