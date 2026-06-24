<section class="auth-wrap modal-page-wrap">
    <div class="auth-card auth-card-wide modal-page-card modal-page-card-wide">
        <h1>Cadastro do Responsavel</h1>

        <p class="muted" style="font-size: 14px;">Somente pessoas maiores de 18 anos podem criar conta. Menores de 18 anos serao adicionados, no sistema, como dependentes de um responsavel maior de idade.</p>
        <p class="muted" style="font-size: 14px;">O nome completo deve ter no minimo 14 caracteres, sem caracteres especiais, e os espacos no inicio e no fim sao removidos automaticamente.</p>
        <div class="alert-inline dashboard-dependent-attention">
            Preencha o CPF com atencao. Depois de vinculado ao cadastro da pessoa, qualquer correcao desse dado pode depender do suporte.
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
            <small class="cpf-cadastro-helper muted">Ao informar o CPF, o sistema avisara imediatamente se a conta ja existe, se o CPF pertence a um dependente ou se a criacao da conta esta liberada.</small>

            <div class="grid-two">
                <label>
                    <span>Senha</span>
                    <input type="password" name="password" minlength="6" required>
                </label>
                <label>
                    <span>Confirmacao de senha</span>
                    <input type="password" name="password_confirmation" minlength="6" required>
                </label>
            </div>

            <label class="checkbox-line">
                <input type="checkbox" name="adult_ack" value="1" <?php echo old('adult_ack') === '1' ? 'checked' : ''; ?>>
                <span>Confirmo que sou maior de 18 anos e estou criando o meu proprio cadastro como responsavel.</span>
            </label>

            <label class="checkbox-line">
                <input type="checkbox" name="accept_terms" value="1" <?php echo old('accept_terms') === '1' ? 'checked' : ''; ?>>
                <span>Li e aceito as politicas de privacidade e os termos de uso do site. O documento sera implementado em detalhes depois.</span>
            </label>

            <div class="alert-inline">
                Ao final do projeto, lembrem-se de incluir aqui a verificacao "nao sou robo" ou "sou humano".
            </div>

            <button type="submit" class="btn btn-primary">Criar cadastro</button>
        </form>
    </div>
</section>
