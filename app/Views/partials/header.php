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
    <link rel="stylesheet" href="<?php echo e(asset_url('css/blog.css')); ?>">
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
    <?php $whatsappHomeUrl = 'https://wa.me/551126307421'; ?>
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
<?php if (($pageClass ?? '') === 'pagina-home') { ?>
                <div class="brand-whatsapp-callout">
                    <span class="brand-whatsapp-label">Duvidas, sugestoes e reclamacoes:</span>
                    <a href="<?php echo e($whatsappHomeUrl); ?>" class="brand-whatsapp-icon-link" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp dos Cursos Esportivos SBC">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="brand-whatsapp-icon">
                            <path fill="currentColor" d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.61 2 2.2 6.41 2.2 11.83c0 1.73.45 3.42 1.3 4.9L2 22l5.43-1.42a9.8 9.8 0 0 0 4.6 1.17h.01c5.42 0 9.83-4.41 9.83-9.83a9.75 9.75 0 0 0-2.82-7.01ZM12.04 20.1h-.01a8.14 8.14 0 0 1-4.15-1.13l-.3-.18-3.22.84.86-3.14-.2-.32a8.13 8.13 0 0 1-1.25-4.33c0-4.49 3.65-8.14 8.15-8.14a8.1 8.1 0 0 1 5.77 2.4 8.08 8.08 0 0 1 2.37 5.76c0 4.49-3.66 8.14-8.14 8.14Zm4.46-6.11c-.24-.12-1.4-.69-1.62-.77-.22-.08-.38-.12-.54.12-.16.24-.62.77-.76.93-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.93-1.17-.71-.64-1.19-1.43-1.33-1.67-.14-.24-.01-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.31-.74-1.8-.2-.47-.4-.41-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.68 2.56 4.06 3.59.57.25 1.02.4 1.37.51.58.18 1.11.15 1.53.09.47-.07 1.4-.57 1.6-1.12.2-.55.2-1.02.14-1.12-.05-.1-.21-.16-.45-.28Z"/>
                        </svg>
                    </a>
                    <a href="<?php echo e($whatsappHomeUrl); ?>" class="brand-whatsapp-text-link" target="_blank" rel="noopener noreferrer">Cursos Esportivos SBC no whatsapp</a>
                </div>
<?php } ?>
            </div>
            <nav class="site-nav">
                <a href="<?php echo e(url('/')); ?>">Inicio</a>
                <a href="<?php echo e(url('/agenda')); ?>">Agenda</a>
                <a href="<?php echo e(url('/blog')); ?>">Blog</a>
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
