<?php
$error = $errorData ?? [];
$statusCode = (int) ($error['status_code'] ?? 500);
$titleText = (string) ($error['title'] ?? 'Erro');
$headline = (string) ($error['headline'] ?? 'Ocorreu um problema ao carregar a pagina.');
$message = (string) ($error['message'] ?? 'Tente novamente em alguns instantes.');
$hint = trim((string) ($error['hint'] ?? ''));
?>

<section class="error-shell">
    <article class="error-card">
        <div class="error-code-badge">Erro <?php echo e((string) $statusCode); ?></div>
        <span class="eyebrow">Pagina de suporte</span>
        <h1><?php echo e($headline); ?></h1>
        <p class="error-title"><?php echo e($titleText); ?></p>
        <p class="error-message"><?php echo e($message); ?></p>
        <?php if ($hint !== '') { ?>
            <div class="alert-inline error-hint"><?php echo e($hint); ?></div>
        <?php } ?>
        <div class="error-actions">
            <a href="<?php echo e(url('/')); ?>" class="btn btn-primary">Voltar para a home</a>
            <a href="<?php echo e(url('/agenda')); ?>" class="btn btn-secondary">Abrir agenda publica</a>
            <a href="<?php echo e(url('/blog')); ?>" class="btn btn-secondary">Abrir blog</a>
        </div>
        <p class="muted error-help-text">Se o problema continuar, tente novamente mais tarde ou informe a equipe responsavel com o codigo do erro e o horario do ocorrido.</p>
    </article>
</section>
