<section class="content-card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Gestão inicial</span>
            <h1>Área administrativa</h1>
            <p class="muted">Painel administrativo organizado por seções, sem redirecionamento de página.</p>
        </div>
    </div>
</section>

<div class="admin-sections-shell">
    <nav class="content-card admin-nav-card" aria-label="Menu da área administrativa">
        <div class="admin-nav">
            <button type="button" class="admin-nav-button is-active" data-admin-nav-target="inicio">Início</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="usuarios-pessoas">Usuários e pessoas</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="migracao-cadastros">Migração de cadastros</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="migracao-atestados">Migração de atestados</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="agenda">Agenda</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="pagina-home">Página home</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="pop-ups">Pop-ups</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="blog">Blog</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="locais-espacos">Locais e espaços</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="modalidades">Modalidades</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="configuracoes">Configurações</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="outras-areas">Outras áreas</button>
            <?php if (has_role($user['roles'] ?? [], 'master_admin')) { ?>
                <a class="admin-nav-button" href="<?php echo e(url('/admin-recuperacao-dados')); ?>">Reversão de testes</a>
            <?php } ?>
        </div>
    </nav>

    <div class="admin-section-host" id="admin-section-host" data-admin-section-host="1" data-admin-section-url="<?php echo e(url('/admin/secao')); ?>">
        <?php
        $sectionName = 'inicio';
        require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
        ?>
    </div>
</div>
