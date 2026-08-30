<section class="auth-wrap modal-page-wrap">
    <div class="auth-card auth-card-wide modal-page-card modal-page-card-wide">
        <h1>Cadastro do Responsável</h1>

        <p class="muted" style="font-size: 14px;">Somente pessoas maiores de 18 anos podem criar conta. Menores de 18 anos serão adicionados, no sistema, como dependentes de um responsável maior de idade.</p>
        <p class="muted" style="font-size: 14px;">O nome completo deve ter no mínimo 14 caracteres e pode conter letras, espaços, hífen e apóstrofo. Os espaços no início e no fim são removidos automaticamente.</p>
        <div class="alert-inline dashboard-dependent-attention">
            Preencha o CPF com atenção. Depois de vinculado ao cadastro da pessoa, qualquer correção desse dado pode depender do suporte.
        </div>

        <form method="POST" action="<?php echo e(url('/cadastro')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1">
            <label>
                <span>Nome completo</span>
                <input type="text" name="full_name" value="<?php echo old('full_name'); ?>" required>
            </label>

            <label>
                <span>CPF</span>
                <input type="text" name="cpf" value="<?php echo old('cpf'); ?>" placeholder="000.000.000-00" data-cpf-cadastro="1" required>
            </label>
            <small class="cpf-cadastro-helper muted">Ao informar o CPF, o sistema avisará imediatamente se a conta já existe, se o CPF pertence a um dependente ou se a criação da conta está liberada.</small>

            <div class="grid-two">
                <label>
                    <span>Senha</span>
                    <input type="password" name="password" minlength="6" required>
                </label>
                <label>
                    <span>Confirmação de senha</span>
                    <input type="password" name="password_confirmation" minlength="6" required>
                </label>
            </div>

            <label class="checkbox-line">
                <input type="checkbox" name="adult_ack" value="1" <?php echo old('adult_ack') === '1' ? 'checked' : ''; ?>>
                <span>Confirmo que sou maior de 18 anos e estou criando o meu próprio cadastro como responsável.</span>
            </label>

            <label class="checkbox-line">
                <input type="checkbox" name="accept_terms" value="1" <?php echo old('accept_terms') === '1' ? 'checked' : ''; ?>>
                <span>Li e aceito as politicas de privacidade e os termos de uso do site. O documento será implementado em detalhes depois.</span>
            </label>

            <?php if (!empty($turnstileSiteKey)) { ?>
                <div class="cf-turnstile" data-sitekey="<?php echo e((string) $turnstileSiteKey); ?>" data-action="cadastro"></div>
            <?php } else { ?>
                <div class="alert-inline">A verificação de segurança está temporariamente indisponível.</div>
            <?php } ?>

            <button type="submit" class="btn btn-primary">Criar cadastro</button>
        </form>
    </div>
</section>
