<section class="auth-wrap modal-page-wrap">
    <div class="auth-card modal-page-card">
        <h1>Entrar</h1>
        <p>Use seu CPF como login. Menores de idade nao podem acessar com a propria conta.</p>
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
            <div class="alert-inline">
                Ao final do projeto, lembrem-se de incluir aqui a verificacao "nao sou robo" ou "sou humano".
            </div>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
</section>
