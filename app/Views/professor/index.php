<section class="content-card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Acompanhamento esportivo</span>
            <h1><?php echo e((string) ($professorPageConfig['titulo'] ?? 'Área do professor')); ?></h1>
            <?php if (!empty($professorPageConfig['comunicado'])) { ?><p class="muted"><?php echo nl2br(e((string) $professorPageConfig['comunicado'])); ?></p><?php } ?>
            <?php if (!empty($professorPageConfig['texto_secundario'])) { ?><p class="professor-page-secondary-text"><?php echo nl2br(e((string) $professorPageConfig['texto_secundario'])); ?></p><?php } ?>
            <?php if (!empty($professorPageConfig['imagem_url'])) { ?><div class="professor-page-image"><img src="<?php echo e((string) $professorPageConfig['imagem_url']); ?>" alt="Imagem do quadro Acompanhamento esportivo" loading="lazy"></div><?php } ?>
            <?php if (!empty($professorPageConfig['acoes'])) { ?><div class="hero-actions top-gap"><?php foreach ($professorPageConfig['acoes'] as $action) { ?><a href="<?php echo e((string) $action['url']); ?>" class="<?php echo ($action['tipo'] ?? '') === 'link' ? 'course-season-summary-link' : 'btn btn-secondary'; ?>"<?php echo str_starts_with((string) $action['url'], '/') ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>><?php echo e((string) $action['rotulo']); ?></a><?php } ?></div><?php } ?>
        </div>
    </div>
</section>

<div class="admin-sections-shell">
    <nav class="content-card admin-nav-card" aria-label="Menu da área do professor">
        <div class="admin-nav">
            <button type="button" class="admin-nav-button is-active" data-admin-nav-target="inicio">Início</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="usuarios-pessoas">Pessoas - alunos</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="inscricoes">Inscrições em cursos</button>
            <button type="button" class="admin-nav-button" data-admin-nav-target="minhas-turmas">Minhas turmas</button>
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
