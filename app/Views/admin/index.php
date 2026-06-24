<section class="content-card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Gestao inicial</span>
            <h1>Area administrativa</h1>
            <p class="muted">Painel administrativo organizado por seções, sem redirecionamento de página.</p>
        </div>
    </div>
</section>

<div class="admin-sections-shell">
    <nav class="content-card admin-nav-card" aria-label="Menu da area administrativa">
        <div class="admin-nav">
            <button type="button" class="admin-nav-button is-active" data-admin-nav-target="inicio">Inicio</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="usuarios-pessoas">Usuarios e pessoas</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="agenda">Agenda</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="pagina-home">Pagina home</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="blog">Blog</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="locais-espacos">Locais e espacos</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="configuracoes">Configuracoes</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="outras-areas">Outras areas</button>
        </div>
    </nav>

    <div class="admin-section-host" id="admin-section-host" data-admin-section-host="1" data-admin-section-url="<?php echo e(url('/admin/secao')); ?>">
        <?php
        $sectionName = 'inicio';
        require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
        ?>
    </div>
</div>
