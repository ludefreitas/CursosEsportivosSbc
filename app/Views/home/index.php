<?php $whatsappHomeUrl = 'https://wa.me/551126307421'; ?>

<div class="home-whatsapp-row">
    <span class="home-whatsapp-label">Duvidas, sugestoes e reclamacoes:</span>
    <a href="<?php echo e($whatsappHomeUrl); ?>" class="home-whatsapp-icon-link" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp dos Cursos Esportivos SBC">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="home-whatsapp-icon">
            <path fill="currentColor" d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.61 2 2.2 6.41 2.2 11.83c0 1.73.45 3.42 1.3 4.9L2 22l5.43-1.42a9.8 9.8 0 0 0 4.6 1.17h.01c5.42 0 9.83-4.41 9.83-9.83a9.75 9.75 0 0 0-2.82-7.01ZM12.04 20.1h-.01a8.14 8.14 0 0 1-4.15-1.13l-.3-.18-3.22.84.86-3.14-.2-.32a8.13 8.13 0 0 1-1.25-4.33c0-4.49 3.65-8.14 8.15-8.14a8.1 8.1 0 0 1 5.77 2.4 8.08 8.08 0 0 1 2.37 5.76c0 4.49-3.66 8.14-8.14 8.14Zm4.46-6.11c-.24-.12-1.4-.69-1.62-.77-.22-.08-.38-.12-.54.12-.16.24-.62.77-.76.93-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.93-1.17-.71-.64-1.19-1.43-1.33-1.67-.14-.24-.01-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.31-.74-1.8-.2-.47-.4-.41-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.68 2.56 4.06 3.59.57.25 1.02.4 1.37.51.58.18 1.11.15 1.53.09.47-.07 1.4-.57 1.6-1.12.2-.55.2-1.02.14-1.12-.05-.1-.21-.16-.45-.28Z"/>
        </svg>
    </a>
    <a href="<?php echo e($whatsappHomeUrl); ?>" class="home-whatsapp-text-link" target="_blank" rel="noopener noreferrer">Cursos Esportivos SBC no whatsapp</a>
</div>

<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">Primeira fase funcional !!!</span>
        <h1>Um sistema esportivo pensado para crescer sem excesso de redirecionamentos e reloads.</h1>
        <p>
            Esta entrega ja organiza cadastro por CPF, autenticacao segura, dependentes, area administrativa inicial,
            blog institucional e agenda visual com FullCalendar para avaliacoes, treinos e aulas.
        </p>
        <div class="hero-actions">
            <?php if (\App\Core\Auth::check()) { ?>
                <a href="<?php echo e(url('/dashboard')); ?>" class="btn btn-primary">Abrir meu painel</a>
            <?php } else { ?>
                <a href="<?php echo e(url('/cadastro')); ?>" class="btn btn-primary">Criar conta</a>
            <?php } ?>
            <a href="<?php echo e(url('/agenda')); ?>" class="btn btn-secondary">Ver agenda publica</a>
        </div>
    </div>
    <div class="hero-card">
        <h2><?php echo e($homeInfoBox['titulo'] ?? 'O que voce precisa saber:'); ?></h2>
        <div class="home-info-list">
            <?php foreach (($homeInfoBox['paragrafos'] ?? []) as $paragraph) { ?>
                <p class="home-info-item">
                    <span class="home-info-bullet"><strong>&bull;</strong></span>
                    <span class="home-info-text">
                        <?php echo e((string) ($paragraph['texto'] ?? '')); ?>
                        <?php if (!empty($paragraph['link_rotulo']) && !empty($paragraph['link_url'])) { ?>
                            <a href="<?php echo e((string) $paragraph['link_url']); ?>" class="home-info-link"><?php echo e((string) $paragraph['link_rotulo']); ?></a>
                        <?php } ?>
                    </span>
                </p>
            <?php } ?>
        </div>
    </div>
</section>

<section class="section-grid">
    <article class="info-card">
        <h3>Locais e espacos</h3>
        <p>Os locais de treino podem conter varios espacos, como quadras, piscinas e salas, vinculados a modalidades terrestres ou aquaticas.</p>
    </article>
    <article class="info-card">
        <h3>Perfis de acesso</h3>
        <p>Administrador Master, Administrador, Supervisor, Coordenador, Professor, Estagiario e usuario comum podem coexistir na mesma conta.</p>
    </article>
    <article class="info-card">
        <h3>Fluxo centrado no usuario</h3>
        <p>A agenda publica continua visivel sem login. O acesso autenticado entra apenas quando a pessoa decide agendar ou administrar dados.</p>
    </article>
</section>

<section class="content-card split-card">
    <div>
        <h2>Locais sugeridos no sistema</h2>
        <p class="muted">A etapa seguinte pode usar geolocalizacao do navegador para ordenar esta lista por proximidade do aluno.</p>
        <p class="location-status muted">Se o navegador permitir, o sistema pode usar sua localizacao apenas para sugerir locais mais proximos.</p>
    </div>
    <div class="chips-wrap">
        <?php foreach ($locations as $location) { ?>
            <span class="chip"><?php echo e($location['nome'] . ' - ' . $location['cidade'] . '/' . $location['uf']); ?></span>
        <?php } ?>
    </div>
</section>

<?php if (!empty($homeSpecialEvents)) { ?>
<section class="content-card">
    <div class="section-head">
        <div>
            <h2>Eventos especiais em destaque</h2>
            <p class="muted">Inscricoes e avaliacoes especiais em evidenca na pagina inicial.</p>
        </div>
        <a href="<?php echo e(url('/agenda')); ?>" class="btn btn-secondary">Abrir agenda</a>
    </div>
    <div class="special-event-grid">
        <?php foreach ($homeSpecialEvents as $event) { ?>
            <article class="special-event-card">
                <?php if (!empty($event['imagem_url'])) { ?>
                    <img src="<?php echo e((string) $event['imagem_url']); ?>" alt="<?php echo e((string) $event['titulo']); ?>" class="special-event-card-image">
                <?php } ?>
                <div class="special-event-card-body">
                    <span class="eyebrow eyebrow-soft">Evento especial</span>
                    <h3><?php echo e((string) $event['titulo']); ?></h3>
                    <p><?php echo e((string) ($event['descricao'] ?? '')); ?></p>
                    <small><?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_inicio']))); ?> ate <?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_fim']))); ?></small>
                    <div class="hero-actions top-gap">
                        <a href="<?php echo e((string) (!empty($event['url_destino']) ? $event['url_destino'] : url('/agenda'))); ?>" class="btn btn-primary"><?php echo e((string) (!empty($event['rotulo_acao']) ? $event['rotulo_acao'] : 'Ver detalhes')); ?></a>
                    </div>
                </div>
            </article>
        <?php } ?>
    </div>
</section>
<?php } ?>

<section class="content-card">
    <div class="section-head">
        <div>
            <h2>Blog institucional</h2>
            <p class="muted">Noticias, campanhas, destaques esportivos e informacoes institucionais organizadas em uma pagina propria do blog.</p>
        </div>
        <a href="<?php echo e(url('/blog')); ?>" class="btn btn-secondary">Abrir blog</a>
    </div>
    <div class="post-grid">
        <?php foreach (($blogSpecialEvents ?? []) as $event) { ?>
            <article class="post-card post-card-special-event">
                <span class="eyebrow eyebrow-soft">Evento especial</span>
                <h3><?php echo e((string) $event['titulo']); ?></h3>
                <p><?php echo e(trim((string) ($event['descricao'] ?? '')) !== '' ? (string) $event['descricao'] : 'Evento especial publicado na agenda.'); ?></p>
                <small><?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_inicio']))); ?></small>
            </article>
        <?php } ?>
        <?php foreach ($posts as $post) { ?>
            <article class="post-card">
                <?php if (!empty($post['capa_imagem_url'])) { ?>
                    <img src="<?php echo e((string) $post['capa_imagem_url']); ?>" alt="<?php echo e((string) $post['titulo']); ?>" class="special-event-card-image">
                <?php } ?>
                <span class="eyebrow eyebrow-soft"><?php echo e((string) ($post['categoria'] ?: $post['autor_nome'])); ?></span>
                <h3><?php echo e($post['titulo']); ?></h3>
                <p><?php echo e($post['resumo']); ?></p>
                <small><?php echo e(date('d/m/Y H:i', strtotime((string) ($post['data_publica_ordenacao'] ?? $post['created_at'])))); ?></small>
                <div class="hero-actions top-gap">
                    <a href="<?php echo e(url('/blog/post?slug=' . rawurlencode((string) $post['slug']))); ?>" class="btn btn-primary">Ler postagem</a>
                </div>
            </article>
        <?php } ?>
    </div>
</section>
