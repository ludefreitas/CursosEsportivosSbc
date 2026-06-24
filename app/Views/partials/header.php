<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(($title ?? '') . ' | ' . app_config('name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset_url('img/favicon-cursos-esportivos-sbc.png')); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/core.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/auth.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/agenda.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/admin.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/home.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/style.css')); ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
</head>
<body
    class="<?php echo e($pageClass ?? ''); ?>"
    data-profile-completion-required="<?php echo !empty($profileCompletionRequired) ? '1' : '0'; ?>"
    data-profile-completion-message="<?php echo e($profileCompletionBlockMessage ?: 'Antes de acessar esta area, voce precisa completar seu cadastro.'); ?>"
>
    <?php $isAuthenticated = \App\Core\Auth::check(); ?>
    <div class="page-shell">
        <header class="site-header">
            <div>
                <a class="brand" href="<?php echo e(url('/')); ?>">
                    <img
                        src="<?php echo e(asset_url('img/cursosesportivossbc.jpg')); ?>"
                        alt="Cursos Esportivos SBC"
                        class="brand-logo"
                    >
                    <span class="brand-text">Cursos Esportivos SBC</span>
                </a>
                <p class="brand-subtitle">Agendamento para treinos e inscrições para cursos esportivos para você e seus dependentes em uma só experiência.</p>
            </div>
            <nav class="site-nav">
                <a href="<?php echo e(url('/')); ?>">Inicio</a>
                <a href="<?php echo e(url('/agenda')); ?>">Agenda</a>
                <?php if ($isAuthenticated) { ?>
                    <a
                        href="<?php echo e(url('/dashboard')); ?>"
                        data-profile-completion-link="<?php echo !empty($profileCompletionRequired) ? '1' : '0'; ?>"
                    >Meu painel</a>
                    <a
                        href="<?php echo e(url('/admin')); ?>"
                        data-profile-completion-link="<?php echo !empty($profileCompletionRequired) ? '1' : '0'; ?>"
                    >Admin</a>
                    <form method="POST" action="<?php echo e(url('/logout')); ?>" class="inline-form">
                        <button type="submit" class="link-button">Sair</button>
                    </form>
                <?php } else { ?>
                    <a href="<?php echo e(url('/login?return_to=' . rawurlencode(current_path()))); ?>">Entrar</a>
                    <a href="<?php echo e(url('/cadastro')); ?>" class="nav-cta">Cadastrar</a>
                <?php } ?>
            </nav>
        </header>
        <div id="site-header-certificate-alerts-region">
            <?php require ROOT_PATH . '/app/Views/partials/header_certificate_alerts.php'; ?>
        </div>
        <main class="page-content">
            <?php require ROOT_PATH . '/app/Views/partials/flash.php'; ?>
