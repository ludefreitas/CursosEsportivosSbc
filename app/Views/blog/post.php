<section class="blog-shell">
    <article class="blog-post-detail-page">
        <header class="blog-post-feature-hero" style="--blog-hero-image: url('<?php echo e((string) ($post['hero_background_url'] ?? $post['capa_imagem_url'] ?? '')); ?>');">
            <div class="blog-post-feature-backdrop"></div>
            <div class="blog-post-feature-content">
                <a href="<?php echo e(url('/blog')); ?>" class="blog-back-link">Voltar para o blog</a>
                <div class="blog-post-meta">
                    <span><?php echo e(date('d/m/Y H:i', strtotime((string) ($post['data_publica_ordenacao'] ?? $post['created_at'])))); ?></span>
                    <?php if (!empty($post['categoria'])) { ?><span><?php echo e((string) $post['categoria']); ?></span><?php } ?>
                    <span><?php echo e((string) ($post['autor_nome'] ?? 'Equipe')); ?></span>
                </div>
                <h1><?php echo e((string) $post['titulo']); ?></h1>
                <p class="blog-post-summary"><?php echo e((string) $post['resumo']); ?></p>
                <?php if (!empty($post['tags_array'])) { ?>
                    <div class="blog-tag-row">
                        <?php foreach (($post['tags_array'] ?? []) as $tag) { ?>
                            <span class="chip"><?php echo e((string) $tag); ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </header>

        <section class="blog-section-card blog-post-article blog-post-single-column">
            <div class="blog-rich-text">
                <?php foreach (preg_split("/\r\n|\r|\n{2,}/", (string) ($post['conteudo'] ?? '')) as $paragraph) { ?>
                    <?php if (trim((string) $paragraph) === '') { continue; } ?>
                    <p><?php echo nl2br(e(trim((string) $paragraph))); ?></p>
                <?php } ?>
            </div>
        </section>

        <?php if (!empty($post['gallery_images'])) { ?>
            <section class="blog-section-card blog-gallery-section">
                <div class="blog-section-head">
                    <div>
                        <h2>Imagens da postagem</h2>
                        <p class="muted">Galeria livre organizada em sequencia, uma imagem abaixo da outra.</p>
                    </div>
                </div>
                <div class="blog-gallery-stack">
                    <?php foreach (($post['gallery_images'] ?? []) as $galleryImage) { ?>
                        <figure class="blog-gallery-item">
                            <img src="<?php echo e((string) ($galleryImage['imagem_url'] ?? '')); ?>" alt="<?php echo e(trim((string) ($galleryImage['legenda'] ?? '')) !== '' ? (string) $galleryImage['legenda'] : (string) $post['titulo']); ?>" class="blog-gallery-image">
                            <?php if (trim((string) ($galleryImage['legenda'] ?? '')) !== '') { ?>
                                <figcaption><?php echo e((string) $galleryImage['legenda']); ?></figcaption>
                            <?php } ?>
                        </figure>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <?php if (!empty($post['share_links'])) { ?>
            <section class="blog-section-card">
                <h2>Compartilhar esta postagem</h2>
                <div class="blog-share-grid">
                    <?php if (!empty($post['share_links']['whatsapp'])) { ?><a href="<?php echo e((string) $post['share_links']['whatsapp']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">WhatsApp</a><?php } ?>
                    <?php if (!empty($post['share_links']['facebook'])) { ?><a href="<?php echo e((string) $post['share_links']['facebook']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Facebook</a><?php } ?>
                    <?php if (!empty($post['share_links']['linkedin'])) { ?><a href="<?php echo e((string) $post['share_links']['linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">LinkedIn</a><?php } ?>
                    <?php if (!empty($post['share_links']['x'])) { ?><a href="<?php echo e((string) $post['share_links']['x']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">X</a><?php } ?>
                    <?php if (!empty($post['share_links']['copiar'])) { ?><a href="<?php echo e((string) $post['share_links']['copiar']); ?>" class="btn btn-primary">Link direto</a><?php } ?>
                </div>
            </section>
        <?php } ?>

        <?php if (!empty($relatedPosts)) { ?>
            <section class="blog-section-card">
                <h2>Leia tambem</h2>
                <div class="blog-related-grid">
                    <?php foreach ($relatedPosts as $relatedPost) { ?>
                        <article class="blog-related-card">
                            <h3><a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $relatedPost['slug']))); ?>"><?php echo e((string) $relatedPost['titulo']); ?></a></h3>
                            <p><?php echo e((string) $relatedPost['resumo']); ?></p>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>
    </article>
</section>
