<?php $tutorialPage = $tutorialPage ?? []; $tutorialVideos = $tutorialPage['videos'] ?? []; ?>
<section class="admin-section-panel" data-admin-section="pagina-ajuda">
    <div class="section-head admin-section-head"><div><h2>Página pública de ajuda</h2><p class="muted">Edite o título da página e organize os vídeos exibidos em <strong>/tutorial</strong>.</p></div><a class="btn btn-secondary" href="<?php echo e(url('/tutorial')); ?>" target="_blank" rel="noopener noreferrer">Visualizar página pública</a></div>
    <article class="content-card">
        <form method="POST" action="<?php echo e(url('/admin/pagina-ajuda')); ?>" class="stack-form" data-ajax-form="1">
            <label><span>Título principal</span><input type="text" name="titulo" maxlength="180" value="<?php echo e((string) ($tutorialPage['titulo'] ?? 'Ajuda ao usuário')); ?>" required></label>
            <label><span>Texto introdutório</span><textarea name="texto_introdutorio" rows="3" maxlength="1500" placeholder="Explique brevemente como os vídeos podem ajudar o usuário."><?php echo e((string) ($tutorialPage['texto_introdutorio'] ?? '')); ?></textarea></label>
            <fieldset class="site-popup-actions-fieldset">
                <legend>Vídeos de ajuda</legend>
                <p class="muted">Adicione até <?php echo e((string) \App\Services\TutorialPageService::MAX_VIDEOS); ?> vídeos. A ordem abaixo define a numeração apresentada ao usuário.</p>
                <div class="site-popup-actions-list" data-tutorial-videos-list>
                    <?php foreach (($tutorialVideos ?: [[]]) as $video) { ?>
                        <div class="tutorial-admin-video-row" data-tutorial-video-row>
                            <label><span>Título do vídeo</span><input type="text" name="video_titulo[]" maxlength="180" value="<?php echo e((string) ($video['titulo'] ?? '')); ?>" placeholder="Ex.: Como realizar meu cadastro"></label>
                            <label><span>URL do YouTube</span><input type="url" name="video_url[]" maxlength="2048" value="<?php echo e((string) ($video['url'] ?? '')); ?>" placeholder="https://youtu.be/..."></label>
                            <div class="tutorial-admin-video-actions"><button type="button" class="btn btn-secondary" data-tutorial-video-up aria-label="Mover vídeo para cima">Subir</button><button type="button" class="btn btn-secondary" data-tutorial-video-down aria-label="Mover vídeo para baixo">Descer</button><button type="button" class="btn btn-danger" data-tutorial-video-remove>Remover</button></div>
                        </div>
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-secondary" data-tutorial-video-add>Adicionar outro vídeo</button>
            </fieldset>
            <button type="submit" class="btn btn-primary">Salvar página de ajuda</button>
        </form>
    </article>
</section>
