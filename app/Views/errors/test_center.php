<section class="error-shell">
    <article class="error-card error-test-card">
        <span class="eyebrow">Arquivo de testes</span>
        <h1>Central de testes das páginas de erro</h1>
        <p class="error-message">Use esta página para verificar rapidamente como os erros mais comuns aparecem para o usuário final.</p>
        <div class="error-test-grid">
            <?php foreach (($errors ?? []) as $error) { ?>
                <article class="error-test-item">
                    <div class="error-code-badge">Erro <?php echo e((string) ($error['status_code'] ?? '')); ?></div>
                    <h2><?php echo e((string) ($error['title'] ?? 'Erro')); ?></h2>
                    <p><?php echo e((string) ($error['headline'] ?? '')); ?></p>
                    <a href="<?php echo e((string) ($error['preview_url'] ?? '#')); ?>" class="btn btn-primary">Testar página</a>
                </article>
            <?php } ?>
        </div>
    </article>
</section>
