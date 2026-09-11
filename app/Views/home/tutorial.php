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
                        <iframe src="<?php echo e((string) ($video['embed_url'] ?? '')); ?>" title="Vídeo <?php echo e((string) ($index + 1)); ?> — <?php echo e((string) ($video['titulo'] ?? '')); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } ?>
</section>
