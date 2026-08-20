<section class="content-card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Acompanhamento esportivo</span>
            <h1>Área do professor</h1>
            <p class="muted">Consulte usuários, pessoas e dependentes, acompanhe a agenda e valide documentos de saúde.</p>
        </div>
    </div>
</section>

<div class="admin-sections-shell">
    <nav class="content-card admin-nav-card" aria-label="Menu da área do professor">
        <div class="admin-nav">
            <button type="button" class="admin-nav-button is-active" data-admin-nav-target="inicio">Início</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="usuarios-pessoas">Usuários e pessoas</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="agenda">Agenda</button>
        </div>
    </nav>

    <div
        class="admin-section-host"
        id="admin-section-host"
        data-admin-section-host="1"
        data-admin-section-url="<?php echo e(url('/professor/secao')); ?>"
        data-admin-base-path="/professor"
        data-professor-mode="1"
    >
        <?php
        $sectionName = 'inicio';
        $professorView = true;
        require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
        ?>
    </div>
</div>
