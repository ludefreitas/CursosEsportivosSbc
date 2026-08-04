<?php
$homeAdminMode = !empty($homeAdminMode);
$whatsappHomeUrl = (string) ($homeHeaderContent['contato_url'] ?? 'https://wa.me/551126307421');
$homeContentUrl = static function (string $value): string {
    return str_starts_with($value, '/') ? url($value) : $value;
};
?>

<div class="home-banner-visual">
    <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="logotipo">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="logotipo">Publicar</button></div><?php } ?>
    <img style="width: 40%"
        src="<?php echo e($homeContentUrl((string) ($homeHeaderContent['logo_url'] ?? '/assets/img/cursosesportivossbc.jpg'))); ?>"
        alt="<?php echo e((string) ($homeHeaderContent['logo_alt'] ?? 'Cursos Esportivos SBC')); ?>"
        class="img-fluid img-eventos"
    >
</div>

<div class="home-whatsapp-row">
    <span class="home-whatsapp-label"><?php echo e((string) ($homeHeaderContent['contato_rotulo'] ?? 'Dúvidas, sugestões e reclamações:')); ?></span>
    <a href="<?php echo e($whatsappHomeUrl); ?>" class="home-whatsapp-icon-link" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp dos Cursos Esportivos SBC">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="home-whatsapp-icon">
            <path fill="currentColor" d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.61 2 2.2 6.41 2.2 11.83c0 1.73.45 3.42 1.3 4.9L2 22l5.43-1.42a9.8 9.8 0 0 0 4.6 1.17h.01c5.42 0 9.83-4.41 9.83-9.83a9.75 9.75 0 0 0-2.82-7.01ZM12.04 20.1h-.01a8.14 8.14 0 0 1-4.15-1.13l-.3-.18-3.22.84.86-3.14-.2-.32a8.13 8.13 0 0 1-1.25-4.33c0-4.49 3.65-8.14 8.15-8.14a8.1 8.1 0 0 1 5.77 2.4 8.08 8.08 0 0 1 2.37 5.76c0 4.49-3.66 8.14-8.14 8.14Zm4.46-6.11c-.24-.12-1.4-.69-1.62-.77-.22-.08-.38-.12-.54.12-.16.24-.62.77-.76.93-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.93-1.17-.71-.64-1.19-1.43-1.33-1.67-.14-.24-.01-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.31-.74-1.8-.2-.47-.4-.41-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.68 2.56 4.06 3.59.57.25 1.02.4 1.37.51.58.18 1.11.15 1.53.09.47-.07 1.4-.57 1.6-1.12.2-.55.2-1.02.14-1.12-.05-.1-.21-.16-.45-.28Z"/>
        </svg>
    </a>
    <a href="<?php echo e($whatsappHomeUrl); ?>" class="home-whatsapp-text-link" target="_blank" rel="noopener noreferrer"><?php echo e((string) ($homeHeaderContent['contato_texto'] ?? 'Cursos Esportivos SBC no whatsapp')); ?></a>
    <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="contato">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="contato">Publicar</button></div><?php } ?>
</div>

<section class="hero">
    <div class="hero-copy">
        <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="apresentacao">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="apresentacao">Publicar</button></div><?php } ?>
        <span class="eyebrow"><?php echo e((string) ($homeHeroContent['selo'] ?? '')); ?></span>
        <h1><?php echo e((string) ($homeHeroContent['titulo'] ?? '')); ?></h1>
        <p><?php echo e((string) ($homeHeroContent['texto'] ?? '')); ?></p>
        <?php $homeHeroButtonCount = max(0, min(2, (int) ($homeHeroContent['quantidade_botoes'] ?? 0))); ?>
        <?php if ($homeHeroButtonCount > 0) { ?>
            <div class="hero-actions">
                <?php for ($buttonIndex = 1; $buttonIndex <= $homeHeroButtonCount; $buttonIndex++) { ?>
                    <?php
                    $buttonLabel = trim((string) ($homeHeroContent['botao_' . $buttonIndex . '_rotulo'] ?? ''));
                    $buttonUrl = trim((string) ($homeHeroContent['botao_' . $buttonIndex . '_url'] ?? ''));
                    ?>
                    <?php if ($buttonLabel !== '' && $buttonUrl !== '') { ?>
                        <a href="<?php echo e($homeContentUrl($buttonUrl)); ?>" class="btn <?php echo $buttonIndex === 1 ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo e($buttonLabel); ?></a>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <div class="hero-card">
        <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="quadro_informativo">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="quadro_informativo">Publicar</button></div><?php } ?>
        <h2><?php echo e($homeInfoBox['titulo'] ?? 'O que você precisa saber:'); ?></h2>
        <div class="home-info-list">
            <?php foreach (($homeInfoBox['paragrafos'] ?? []) as $paragraph) { ?>
                <p class="home-info-item">
                    <span class="home-info-bullet"><strong>&bull;</strong></span>
                    <span class="home-info-text">
                        <?php echo e((string) ($paragraph['texto'] ?? '')); ?>
                        <?php if (!empty($paragraph['link_rotulo'])) { ?>
                            <?php if (!empty($paragraph['link_url'])) { ?>
                                <a href="<?php echo e($homeContentUrl((string) $paragraph['link_url'])); ?>" class="home-info-link"><?php echo e((string) $paragraph['link_rotulo']); ?></a>
                            <?php } else { ?>
                                <span class="home-info-link"><?php echo e((string) $paragraph['link_rotulo']); ?></span>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </p>
            <?php } ?>
        </div>
    </div>
</section>

<section class="section-grid">
    <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions admin-home-highlight-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="destaques">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="destaques">Publicar</button></div><?php } ?>
    <?php foreach (($homeHighlightCards ?? []) as $highlightCard) { ?>
        <article class="info-card">
            <h3><?php echo e((string) ($highlightCard['titulo'] ?? '')); ?></h3>
            <p><?php echo e((string) ($highlightCard['texto'] ?? '')); ?></p>
            <?php if (!empty($highlightCard['link_rotulo'])) { ?>
                <?php if (!empty($highlightCard['link_url'])) { ?>
                    <a href="<?php echo e($homeContentUrl((string) $highlightCard['link_url'])); ?>" class="home-info-link"><?php echo e((string) $highlightCard['link_rotulo']); ?></a>
                <?php } else { ?>
                    <span class="home-info-link"><?php echo e((string) $highlightCard['link_rotulo']); ?></span>
                <?php } ?>
            <?php } ?>
        </article>
    <?php } ?>
</section>

<section
    class="content-card home-locations-card"
    id="home-locations-card"
    data-locations="<?php echo e((string) json_encode($locations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
>
    <div class="home-locations-copy">
        <h2 id="home-locations-title">Locais sugeridos do sistema</h2>
        <p class="location-status muted">Compartilhe sua localização para ver os locais mais próximos. Se preferir não compartilhar, exibiremos três sugestões aleatórias.</p>
    </div>
    <div class="home-location-suggestions" id="home-location-suggestions">
        <?php foreach (($suggestedLocations ?? []) as $location) { ?>
            <article class="home-location-suggestion" data-location-id="<?php echo e((string) $location['id']); ?>">
                <strong><?php echo e((string) ($location['apelido_local'] ?: $location['nome_local'])); ?></strong>
                <small>(<?php echo e((string) $location['nome_local']); ?>)</small>
            </article>
        <?php } ?>
    </div>
    <div class="home-locations-footer">
        <button type="button" class="btn btn-primary" id="home-all-locations-open">Todos os locais</button>
    </div>
</section>

<div id="home-all-locations-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-all-locations-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-all-locations-title">
        <div class="popup-head">
            <div>
                <h3 id="home-all-locations-title">Todos os locais</h3>
                <p class="muted">Locais ordenados pelo apelido.</p>
            </div>
            <button type="button" class="popup-close-icon" data-home-all-locations-close="1" aria-label="Fechar todos os locais">&times;</button>
        </div>
        <div class="popup-body home-all-locations-list">
            <?php foreach ($locations as $location) { ?>
                <button type="button" class="home-all-location-button" data-home-location-select="<?php echo e((string) $location['id']); ?>">
                    <strong><?php echo e((string) ($location['apelido_local'] ?: $location['nome_local'])); ?></strong>
                    <small><?php echo e((string) $location['nome_local']); ?></small>
                </button>
            <?php } ?>
        </div>
        <div class="popup-actions">
            <button type="button" class="btn btn-secondary" data-home-all-locations-close="1">Fechar</button>
        </div>
    </div>
</div>

<?php if (!empty($homeSpecialEvents)) { ?>
<section class="content-card">
    <div class="section-head">
        <div>
            <h2>Horários especiais em destaque</h2>
            <p class="muted">Inscrições e avaliações especiais em evidência na página inicial.</p>
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
                    <span class="eyebrow eyebrow-soft">Horário especial</span>
                    <h3><?php echo e((string) $event['titulo']); ?></h3>
                    <p><?php echo e((string) ($event['descricao'] ?? '')); ?></p>
                    <small><?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_inicio']))); ?> até <?php echo e(date('d/m/Y H:i', strtotime((string) $event['data_fim']))); ?></small>
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
            <p class="muted">Notícias, campanhas, destaques esportivos e informações institucionais organizadas em uma página própria do blog.</p>
        </div>
        <a href="<?php echo e(url('/blog')); ?>" class="btn btn-secondary">Abrir blog</a>
    </div>
    <div class="post-grid">
        <?php foreach (($blogSpecialEvents ?? []) as $event) { ?>
            <article class="post-card post-card-special-event">
                <span class="eyebrow eyebrow-soft">Horário especial</span>
                <h3><?php echo e((string) $event['titulo']); ?></h3>
                <p><?php echo e(trim((string) ($event['descricao'] ?? '')) !== '' ? (string) $event['descricao'] : 'Horário especial publicado na agenda.'); ?></p>
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
