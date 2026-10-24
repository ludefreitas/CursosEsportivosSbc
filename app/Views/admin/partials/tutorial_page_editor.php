<?php $tutorialPage = $tutorialPage ?? []; $tutorialVideos = $tutorialPage['videos'] ?? []; ?>
<section class="admin-section-panel" data-admin-section="pagina-ajuda">
    <div class="section-head admin-section-head"><div><h2>Página pública de ajuda</h2><p class="muted">Edite o título da página e organize os vídeos exibidos em <strong>/tutorial</strong>.</p></div><a class="btn btn-secondary" href="<?php echo e(url('/tutorial')); ?>" target="_blank" rel="noopener noreferrer">Visualizar página pública</a></div>
    <article class="content-card">
        <form method="POST" action="<?php echo e(url('/admin/pagina-ajuda')); ?>" enctype="multipart/form-data" class="stack-form" data-tutorial-page-form data-max-upload-bytes="<?php echo e((string) \App\Services\TutorialPageService::uploadLimitBytes()); ?>" data-max-request-bytes="<?php echo e((string) \App\Services\TutorialPageService::requestLimitBytes()); ?>">
            <label><span>Título principal</span><input type="text" name="titulo" maxlength="180" value="<?php echo e((string) ($tutorialPage['titulo'] ?? 'Ajuda ao usuário')); ?>" required></label>
            <label><span>Texto introdutório</span><textarea name="texto_introdutorio" rows="3" maxlength="1500" placeholder="Explique brevemente como os vídeos podem ajudar o usuário."><?php echo e((string) ($tutorialPage['texto_introdutorio'] ?? '')); ?></textarea></label>
            <fieldset class="site-popup-actions-fieldset">
                <legend>Vídeos de ajuda <button type="button" class="field-help-button" aria-label="Ajuda sobre os campos e botões dos vídeos" data-field-help-title="Ajuda sobre os vídeos" data-field-help-message="Informe o título e escolha a origem: YouTube, para colar a URL, ou Arquivo local, para enviar um MP4 ou WebM do seu dispositivo. Um arquivo já salvo pode ser mantido sem novo envio. As setas alteram a ordem. Remover retira o vídeo da lista. As mudanças só entram na página pública depois de Salvar página de ajuda.">?</button></legend>
                <p class="muted">Adicione até <?php echo e((string) \App\Services\TutorialPageService::MAX_VIDEOS); ?> vídeos. A ordem abaixo define a numeração apresentada ao usuário.</p>
                <?php if (\App\Services\TutorialPageService::requestLimitBytes() > 0) { ?><p class="muted">Limite total por envio: <?php echo e((string) round(\App\Services\TutorialPageService::requestLimitBytes() / 1048576, 1)); ?> MB, incluindo os campos do formulário. Para adicionar muitos arquivos, salve em etapas ou use versões menores.</p><?php } ?>
                <div class="site-popup-actions-list" data-tutorial-videos-list>
                    <?php foreach (($tutorialVideos ?: [[]]) as $videoIndex => $video) { require ROOT_PATH . '/app/Views/admin/partials/tutorial_video_row.php'; } ?>
                </div>
                <button type="button" class="btn btn-secondary" data-tutorial-video-add>Adicionar outro vídeo</button>
                <template data-tutorial-video-template><?php $video = []; $videoIndex = 0; require ROOT_PATH . '/app/Views/admin/partials/tutorial_video_row.php'; ?></template>
            </fieldset>
            <button type="submit" class="btn btn-primary">Salvar página de ajuda</button>
        </form>
    </article>
</section>
