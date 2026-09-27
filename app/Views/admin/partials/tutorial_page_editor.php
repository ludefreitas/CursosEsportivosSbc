<?php $tutorialPage = $tutorialPage ?? []; $tutorialVideos = $tutorialPage['videos'] ?? []; ?>
<section class="admin-section-panel" data-admin-section="pagina-ajuda">
    <div class="section-head admin-section-head"><div><h2>Página pública de ajuda</h2><p class="muted">Edite o título da página e organize os vídeos exibidos em <strong>/tutorial</strong>.</p></div><a class="btn btn-secondary" href="<?php echo e(url('/tutorial')); ?>" target="_blank" rel="noopener noreferrer">Visualizar página pública</a></div>
    <article class="content-card">
        <form method="POST" action="<?php echo e(url('/admin/pagina-ajuda')); ?>" class="stack-form" data-ajax-form="1">
            <label><span>Título principal</span><input type="text" name="titulo" maxlength="180" value="<?php echo e((string) ($tutorialPage['titulo'] ?? 'Ajuda ao usuário')); ?>" required></label>
            <label><span>Texto introdutório</span><textarea name="texto_introdutorio" rows="3" maxlength="1500" placeholder="Explique brevemente como os vídeos podem ajudar o usuário."><?php echo e((string) ($tutorialPage['texto_introdutorio'] ?? '')); ?></textarea></label>
            <fieldset class="site-popup-actions-fieldset">
                <legend>Vídeos de ajuda <button type="button" class="field-help-button" aria-label="Ajuda sobre os campos e botões dos vídeos" data-field-help-title="Ajuda sobre os vídeos" data-field-help-message="Título do vídeo: informe o nome que será exibido na página de ajuda. URL do YouTube: cole o endereço completo do vídeo. As setas para cima e para baixo alteram a ordem de exibição. Remover exclui o vídeo desta lista; para efetivar as alterações na página pública, clique em Salvar página de ajuda. Adicionar outro vídeo cria uma nova linha, até o limite informado.">?</button></legend>
                <p class="muted">Adicione até <?php echo e((string) \App\Services\TutorialPageService::MAX_VIDEOS); ?> vídeos. A ordem abaixo define a numeração apresentada ao usuário.</p>
                <div class="site-popup-actions-list" data-tutorial-videos-list>
                    <?php foreach (($tutorialVideos ?: [[]]) as $video) { ?>
                        <div class="tutorial-admin-video-row" data-tutorial-video-row>
                            <label><span>Título do vídeo</span><input type="text" name="video_titulo[]" maxlength="180" value="<?php echo e((string) ($video['titulo'] ?? '')); ?>" placeholder="Ex.: Como realizar meu cadastro"></label>
                            <label><span>URL do YouTube</span><input type="url" name="video_url[]" maxlength="2048" value="<?php echo e((string) ($video['url'] ?? '')); ?>" placeholder="https://youtu.be/..."></label>
                            <div class="tutorial-admin-video-actions"><button type="button" class="btn btn-secondary tutorial-video-order-button" data-tutorial-video-up aria-label="Mover vídeo para cima" title="Mover vídeo para cima">↑</button><button type="button" class="btn btn-secondary tutorial-video-order-button" data-tutorial-video-down aria-label="Mover vídeo para baixo" title="Mover vídeo para baixo">↓</button><button type="button" class="btn btn-danger" data-tutorial-video-remove>Remover</button></div>
                        </div>
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-secondary" data-tutorial-video-add>Adicionar outro vídeo</button>
            </fieldset>
            <button type="submit" class="btn btn-primary">Salvar página de ajuda</button>
        </form>
    </article>
</section>
