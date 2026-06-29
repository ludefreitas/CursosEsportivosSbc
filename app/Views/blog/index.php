<?php
$selectedCategory = trim((string) ($selectedCategory ?? ''));
$search = trim((string) ($search ?? ''));
?>

<section class="blog-shell">
    <div class="blog-hero">
        <div class="blog-hero-copy">
            <span class="eyebrow">Comunicacao oficial</span>
            <h1>Blog dos Cursos Esportivos SBC</h1>
            <p>
                Noticias, campanhas, avisos e conteudos institucionais em uma pagina inspirada em blog classico,
                mas adaptada ao nosso portal e ao nosso fluxo administrativo.
            </p>
        </div>
        <form method="GET" action="<?php echo e(url('/blog')); ?>" class="blog-search-card">
            <label>
                <span>Buscar no blog</span>
                <input type="text" name="busca" value="<?php echo e($search); ?>" placeholder="Digite um assunto, termo ou campanha">
            </label>
            <label>
                <span>Categoria</span>
                <select name="categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach (($categories ?? []) as $category) { ?>
                        <option value="<?php echo e((string) ($category['categoria'] ?? '')); ?>" <?php echo $selectedCategory === (string) ($category['categoria'] ?? '') ? 'selected' : ''; ?>>
                            <?php echo e((string) ($category['categoria'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary">Atualizar lista</button>
        </form>
    </div>

    <?php if (!empty($featuredPosts)) { ?>
        <section class="blog-featured-strip">
            <?php foreach ($featuredPosts as $featuredPost) { ?>
                <article class="blog-featured-card">
                    <span class="blog-meta-tag">Destaque</span>
                    <h2><?php echo e((string) $featuredPost['titulo']); ?></h2>
                    <p><?php echo e((string) $featuredPost['resumo']); ?></p>
                    <a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $featuredPost['slug']))); ?>" class="btn btn-secondary">Ler postagem</a>
                </article>
            <?php } ?>
        </section>
    <?php } ?>

    <nav class="blog-category-tabs" aria-label="Categorias do blog">
        <a href="<?php echo e(url('/blog')); ?>" class="blog-category-tab<?php echo $selectedCategory === '' ? ' is-active' : ''; ?>">Pagina inicial</a>
        <?php foreach (($categories ?? []) as $category) { ?>
            <?php $categoryName = (string) ($category['categoria'] ?? ''); ?>
            <a
                href="<?php echo e(url('/blog?categoria=' . rawurlencode($categoryName))); ?>"
                class="blog-category-tab<?php echo $selectedCategory === $categoryName ? ' is-active' : ''; ?>"
            >
                <?php echo e($categoryName); ?>
            </a>
        <?php } ?>
    </nav>

    <div class="blog-layout">
        <div class="blog-main-column">
            <section class="blog-section-card">
                <div class="blog-section-head">
                    <div>
                        <h2>Postagens</h2>
                        <p class="muted"><?php echo e((string) count($posts)); ?> resultado(s)<?php if ($search !== '') { ?> para "<?php echo e($search); ?>"<?php } else { ?> publicado(s) no momento<?php } ?>.</p>
                    </div>
                </div>

                <?php if (empty($posts)) { ?>
                    <p class="muted">Nenhuma postagem encontrada com os filtros atuais.</p>
                <?php } ?>

                <div class="blog-post-list">
                    <?php foreach (($posts ?? []) as $post) { ?>
                        <article class="blog-post-card">
                            <?php if (!empty($post['capa_imagem_url'])) { ?>
                                <a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $post['slug']))); ?>" class="blog-post-image-wrap">
                                    <img src="<?php echo e((string) $post['capa_imagem_url']); ?>" alt="<?php echo e((string) $post['titulo']); ?>" class="blog-post-image">
                                </a>
                            <?php } ?>
                            <div class="blog-post-body">
                                <div class="blog-post-meta">
                                    <span><?php echo e(date('d/m/Y H:i', strtotime((string) ($post['data_publica_ordenacao'] ?? $post['created_at'])))); ?></span>
                                    <?php if (!empty($post['categoria'])) { ?>
                                        <span><?php echo e((string) $post['categoria']); ?></span>
                                    <?php } ?>
                                    <span><?php echo e((string) ($post['autor_nome'] ?? 'Equipe')); ?></span>
                                </div>
                                <h3><a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $post['slug']))); ?>"><?php echo e((string) $post['titulo']); ?></a></h3>
                                <p><?php echo e((string) $post['resumo']); ?></p>
                                <?php if (!empty($post['tags_array'])) { ?>
                                    <div class="blog-tag-row">
                                        <?php foreach (($post['tags_array'] ?? []) as $tag) { ?>
                                            <span class="chip"><?php echo e((string) $tag); ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <div class="blog-post-actions">
                                    <a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $post['slug']))); ?>" class="btn btn-primary">Ler postagem</a>
                                    <?php if (!empty($post['share_links'])) { ?>
                                        <div class="blog-share-inline">
                                            <?php if (!empty($post['share_links']['whatsapp'])) { ?><a href="<?php echo e((string) $post['share_links']['whatsapp']); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php } ?>
                                            <?php if (!empty($post['share_links']['facebook'])) { ?><a href="<?php echo e((string) $post['share_links']['facebook']); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php } ?>
                                            <?php if (!empty($post['share_links']['linkedin'])) { ?><a href="<?php echo e((string) $post['share_links']['linkedin']); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a><?php } ?>
                                            <?php if (!empty($post['share_links']['x'])) { ?><a href="<?php echo e((string) $post['share_links']['x']); ?>" target="_blank" rel="noopener noreferrer">X</a><?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            </section>
        </div>

        <aside class="blog-sidebar">
            <section class="blog-section-card">
                <h3>Navegacao rapida</h3>
                <ul class="blog-sidebar-list">
                    <li><a href="<?php echo e(url('/')); ?>">Pagina inicial do portal</a></li>
                    <li><a href="<?php echo e(url('/agenda')); ?>">Agenda publica</a></li>
                    <li><a href="<?php echo e(url('/blog')); ?>">Todas as postagens</a></li>
                </ul>
            </section>

            <section class="blog-section-card">
                <h3>Categorias</h3>
                <ul class="blog-sidebar-list">
                    <?php foreach (($categories ?? []) as $category) { ?>
                        <li>
                            <a href="<?php echo e(url('/blog?categoria=' . rawurlencode((string) $category['categoria']))); ?>">
                                <?php echo e((string) $category['categoria']); ?> (<?php echo e((string) $category['total']); ?>)
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </section>

            <section class="blog-section-card">
                <h3>Arquivo</h3>
                <ul class="blog-sidebar-list">
                    <?php foreach (($archiveMonths ?? []) as $archive) { ?>
                        <li><?php echo e((string) $archive['rotulo']); ?> (<?php echo e((string) $archive['total']); ?>)</li>
                    <?php } ?>
                </ul>
            </section>

            <?php if (!empty($blogSpecialEvents)) { ?>
                <section class="blog-section-card">
                    <h3>Eventos especiais</h3>
                    <div class="blog-sidebar-events">
                        <?php foreach ($blogSpecialEvents as $event) { ?>
                            <article class="blog-mini-event">
                                <strong><?php echo e((string) $event['titulo']); ?></strong>
                                <span><?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_inicio']))); ?></span>
                            </article>
                        <?php } ?>
                    </div>
                </section>
            <?php } ?>
        </aside>
    </div>
</section>
