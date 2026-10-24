<section class="content-card tutorial-page">
    <div class="section-head tutorial-page-heading">
        <div>
            <span class="eyebrow">Central de ajuda</span>
            <h1><?php echo e((string) ($tutorialPage['titulo'] ?? 'Ajuda ao usuário')); ?></h1>
            <?php if (!empty($tutorialPage['texto_introdutorio'])) { ?><p class="muted"><?php echo nl2br(e((string) $tutorialPage['texto_introdutorio'])); ?></p><?php } ?>
        </div>
    </div>

    <?php if (empty($tutorialPage['videos'])) { ?>
        <div class="empty-state"><p>Os vídeos de ajuda serão publicados em breve.</p></div>
    <?php } else { ?>
        <div class="tutorial-video-list">
            <?php foreach ($tutorialPage['videos'] as $index => $video) { ?>
                <article class="tutorial-video-card">
                    <div class="tutorial-video-title"><span>Vídeo <?php echo e((string) ($index + 1)); ?></span><h2><?php echo e((string) ($video['titulo'] ?? '')); ?></h2></div>
                    <div class="tutorial-video-frame">
                        <?php if (($video['tipo'] ?? 'youtube') === 'local') { ?>
                        <video controls playsinline preload="metadata" aria-label="<?php echo e((string) ($video['titulo'] ?? '')); ?>">
                            <source src="<?php echo e(url((string) ($video['arquivo'] ?? ''))); ?>" type="<?php echo str_ends_with((string) ($video['arquivo'] ?? ''), '.webm') ? 'video/webm' : 'video/mp4'; ?>">
                            Seu navegador não suporta a reprodução deste vídeo. <a href="<?php echo e(url((string) ($video['arquivo'] ?? ''))); ?>">Baixar vídeo</a>.
                        </video>
                        <?php } else { ?>
                        <iframe src="<?php echo e((string) ($video['embed_url'] ?? '')); ?>" title="Vídeo <?php echo e((string) ($index + 1)); ?> — <?php echo e((string) ($video['titulo'] ?? '')); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        <?php } ?>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } ?>
</section>
