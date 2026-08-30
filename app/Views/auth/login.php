<section class="auth-wrap modal-page-wrap">
    <div class="auth-card modal-page-card">
        <h1>Entrar</h1>
        <p>Use seu CPF como login. Menores de idade não podem acessar com a própria conta.</p>
        <form method="POST" action="<?php echo e(url('/login')); ?>" class="stack-form" data-ajax-form="1" data-follow-redirect="1">
            <input type="hidden" name="return_to" value="<?php echo e($returnTo ?? '/dashboard'); ?>">
            <label>
                <span>CPF</span>
                <input type="text" name="cpf" value="<?php echo old('cpf'); ?>" placeholder="000.000.000-00" required>
            </label>
            <label>
                <span>Senha</span>
                <input type="password" name="password" required>
            </label>
            <?php if (!empty($humanVerificationRequired)) { ?>
                <div class="human-verification" data-human-verification="1">
                    <input type="hidden" name="human_verification_id" value="<?php echo e((string) ($humanVerification['id'] ?? '')); ?>">
                    <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <label class="checkbox-line"><input type="checkbox" name="human_verification" value="1" required><span>Não sou robô</span></label>
                </div>
            <?php } ?>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
</section>
