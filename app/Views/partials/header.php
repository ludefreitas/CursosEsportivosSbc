<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(($title ?? '') . ' | ' . app_config('name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset_url('img/favicon-cursos-esportivos-sbc.png')); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/core.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/auth.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/agenda.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/admin.css')); ?>">
    <?php if (current_path() === '/professor') { ?><link rel="stylesheet" href="<?php echo e(asset_url('css/professor.css')); ?>"><?php } ?>
    <link rel="stylesheet" href="<?php echo e(asset_url('css/home.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/blog.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/style.css')); ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
    <?php if ((string) app_config('turnstile_site_key', '') !== '') { ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php } ?>
</head>
<body
    class="<?php echo e($pageClass ?? ''); ?>"
    data-profile-completion-required="<?php echo !empty($profileCompletionRequired) ? '1' : '0'; ?>"
    data-admin-access-allowed="<?php echo !empty($headerAdminAccessAllowed) ? '1' : '0'; ?>"
    data-professor-access-allowed="<?php echo !empty($headerProfessorAccessAllowed) ? '1' : '0'; ?>"
    data-profile-completion-message="<?php echo e($profileCompletionBlockMessage ?: 'Antes de acessar esta área, você precisa completar seu cadastro.'); ?>"
>
    <?php $isAuthenticated = \App\Core\Auth::check(); ?>
    <div class="page-shell">
        <header class="site-header">
            <div class="site-header-access">
                <a class="site-header-home" href="<?php echo e(url('/')); ?>" aria-label="Ir para o início" title="Início">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M3 10.9 12 3l9 7.9v9.6a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5v-9.6Z" fill="currentColor"/>
                    </svg>
                </a>
                <?php if (!$isAuthenticated) { ?>
                    <form
                        method="POST"
                        action="<?php echo e(url('/login')); ?>"
                        class="header-login-form"
                        data-ajax-form="1"
                        data-follow-redirect="1"
                    >
                        <input type="hidden" name="return_to" value="<?php echo e(current_path()); ?>">
                        <span class="header-login-title">Entrar:</span>
                        <label>
                            <span class="sr-only">CPF</span>
                            <input type="text" name="cpf" placeholder="CPF" inputmode="numeric" autocomplete="username" required>
                        </label>
                        <label>
                            <span class="sr-only">Senha</span>
                            <input type="password" name="password" placeholder="Senha" autocomplete="current-password" required>
                        </label>
                        <button type="submit" class="header-login-submit" aria-label="Entrar">Ir <span aria-hidden="true">➜</span></button>
                        <a href="<?php echo e(url('/login')); ?>" class="header-login-recovery">Recuperar senha</a>
                    </form>
                <?php } ?>
            </div>
            <button
                type="button"
                class="site-header-menu-toggle"
                aria-expanded="false"
                aria-controls="site-header-navigation"
                aria-label="Abrir menu de navegação"
                title="Abrir menu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="site-nav" id="site-header-navigation">
                <a href="<?php echo e(url('/cursos')); ?>" class="nav-color-orange">Cursos</a>
                <a href="<?php echo e(url('/blog')); ?>" class="nav-color-red">Blog</a>
                <?php if ($isAuthenticated) { ?>
                    <a
                        href="<?php echo e(url('/dashboard')); ?>"
                        class="nav-color-teal"
                        data-profile-completion-link="<?php echo !empty($profileCompletionRequired) ? '1' : '0'; ?>"
                    >Meu painel</a>
                    <?php if (!empty($headerAdminAccessAllowed)) { ?>
                        <a href="<?php echo e(url('/admin')); ?>" class="nav-color-orange">Admin</a>
                    <?php } ?>
                    <?php if (!empty($headerProfessorAccessAllowed)) { ?>
                        <a href="<?php echo e(url('/professor')); ?>" class="nav-color-green">Professor</a>
                    <?php } ?>
                    <form method="POST" action="<?php echo e(url('/logout')); ?>" class="inline-form">
                        <button type="submit" class="link-button nav-color-green">Sair</button>
                    </form>
                <?php } else { ?>
                    <a href="<?php echo e(url('/login?return_to=' . rawurlencode(current_path()))); ?>" class="nav-color-teal">Entrar</a>
                    <a href="<?php echo e(url('/cadastro')); ?>" class="nav-cta nav-color-orange">Cadastrar</a>
                <?php } ?>
            </nav>
        </header>
        <div id="site-header-certificate-alerts-region">
            <?php require ROOT_PATH . '/app/Views/partials/header_certificate_alerts.php'; ?>
        </div>
        <main class="page-content">
            <?php require ROOT_PATH . '/app/Views/partials/flash.php'; ?>
