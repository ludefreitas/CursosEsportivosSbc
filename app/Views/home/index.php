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

<?php if (!empty($officialCommunication)) { ?>
<section class="content-card home-official-card">
    <div class="home-official-content">
        <span class="eyebrow"><?php echo e((string) ($officialCommunication['nome_quadro'] ?? 'Comunicacao oficial')); ?></span>
        <h2><?php echo e((string) ($officialCommunication['titulo'] ?? '')); ?></h2>
        <p><?php echo e((string) ($officialCommunication['texto_breve'] ?? '')); ?></p>
    </div>
    <?php if (!empty($officialCommunication['link_url']) && !empty($officialCommunication['link_titulo'])) { ?>
        <div class="hero-actions home-official-actions">
            <a href="<?php echo e((string) $officialCommunication['link_url']); ?>" class="btn btn-primary">
                <?php echo e((string) $officialCommunication['link_titulo']); ?>
            </a>
        </div>
    <?php } ?>
</section>
<?php } ?>

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
