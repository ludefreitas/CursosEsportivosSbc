<?php
$homeAdminMode = !empty($homeAdminMode);
$whatsappHomeUrl = (string) ($homeHeaderContent['contato_url'] ?? 'https://wa.me/551126307421');
$homeContentUrl = static function (string $value): string {
    return str_starts_with($value, '/') ? url($value) : $value;
};
$homeCoursesLocationsContent = $homeCoursesLocationsContent ?? [];
$homeTrainingLocationsContent = $homeTrainingLocationsContent ?? [];
$homeCourseModalitiesContent = $homeCourseModalitiesContent ?? [];
$homeCoursesLocationsTitle = \App\Core\Auth::check()
    ? (string) ($homeCoursesLocationsContent['titulo_logado'] ?? 'Locais próximos a você')
    : (string) ($homeCoursesLocationsContent['titulo_visitante'] ?? 'Locais dos cursos esportivos');
$weeklyModalityText = implode(', ', $weeklyTrainingModalityNames ?? []);
$homeTrainingLocationsText = str_replace(
    '{modalidades}',
    $weeklyModalityText !== '' ? $weeklyModalityText : 'as modalidades disponíveis',
    (string) ($homeTrainingLocationsContent['texto'] ?? 'Selecione um local para fazer o seu agendamento para treinar {modalidades}.')
);
$suggestedCourseModalities = array_values($courseModalities ?? []);
if (count($suggestedCourseModalities) > 1) {
    shuffle($suggestedCourseModalities);
}
$suggestedCourseModalities = array_slice($suggestedCourseModalities, 0, 3);
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

<nav class="content-card home-section-navigation" aria-label="Atalhos da página inicial">
    <button type="button" class="btn home-navigation-training" data-home-scroll-target="home-training-agenda">Agenda de Treinos Espontâneo</button>
    <button type="button" class="btn home-navigation-centers" data-home-scroll-target="home-locations-card">Cursos por Centro Esportivo</button>
    <button type="button" class="btn home-navigation-modalities" data-home-scroll-target="home-course-modalities-card">Cursos por Modalidade</button>
    <button type="button" class="btn home-navigation-blog" data-home-scroll-target="home-blog">Blog</button>
</nav>

<div id="home-course-modalities-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-all-locations-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-course-modalities-title">
        <div class="popup-head">
            <div>
                <h3 id="home-course-modalities-title">Cursos por modalidade</h3>
                <p class="muted">Selecione uma modalidade para consultar os centros esportivos.</p>
            </div>
            <button type="button" class="popup-close-icon" data-home-course-modalities-close="1" aria-label="Fechar modalidades">&times;</button>
        </div>
        <div class="popup-body home-course-modalities-list">
            <?php foreach (($courseModalities ?? []) as $modality) { ?>
                <button
                    type="button"
                    class="home-course-modality-button"
                    data-home-course-modality-select="<?php echo e((string) ($modality['id'] ?? '')); ?>"
                ><?php echo e((string) ($modality['nome'] ?? '')); ?></button>
            <?php } ?>
        </div>
        <div class="popup-actions">
            <button type="button" class="btn btn-secondary" data-home-course-modalities-close="1">Fechar</button>
        </div>
    </div>
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
        <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="locais_cursos">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="locais_cursos">Publicar</button></div><?php } ?>
        <h2 id="home-locations-title"><?php echo e($homeCoursesLocationsTitle); ?></h2>
        <p class="location-status muted"><?php echo e((string) ($homeCoursesLocationsContent['texto'] ?? '')); ?></p>
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

<section class="content-card home-course-modalities-card" id="home-course-modalities-card">
    <div class="home-course-modalities-copy">
        <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="modalidades_cursos">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="modalidades_cursos">Publicar</button></div><?php } ?>
        <h2><?php echo e((string) ($homeCourseModalitiesContent['titulo'] ?? 'Modalidades dos cursos esportivos')); ?></h2>
        <p><?php echo e((string) ($homeCourseModalitiesContent['texto'] ?? 'Selecione uma modalidade para consultar os centros esportivos que oferecem o curso.')); ?></p>
    </div>
    <div class="home-course-modality-suggestions">
        <?php foreach ($suggestedCourseModalities as $modality) { ?>
            <button
                type="button"
                class="home-course-modality-suggestion"
                data-home-course-modality-select="<?php echo e((string) ($modality['id'] ?? '')); ?>"
            ><?php echo e((string) ($modality['nome'] ?? '')); ?></button>
        <?php } ?>
    </div>
    <div class="home-course-modalities-footer">
        <button type="button" class="btn home-course-modalities-all" data-home-course-modalities-open="1">Todas as modalidades</button>
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

<div id="home-location-modalities-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-location-modalities-title">
        <div class="popup-head">
            <div><span class="eyebrow">Etapa 2 de 3</span><h3 id="home-location-modalities-title">Modalidades disponíveis</h3><p class="muted" id="home-location-modalities-subtitle"></p></div>
            <button type="button" class="popup-close-icon" data-home-course-flow-close="1" aria-label="Fechar modalidades">&times;</button>
        </div>
        <div class="popup-body" id="home-location-modalities-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-home-course-flow-close="1">Fechar</button></div>
    </div>
</div>

<div id="home-modality-locations-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-modality-locations-title">
        <div class="popup-head">
            <div><span class="eyebrow">Etapa 2 de 3</span><h3 id="home-modality-locations-title">Centros esportivos disponíveis</h3><p class="muted" id="home-modality-locations-subtitle"></p></div>
            <button type="button" class="popup-close-icon" data-home-course-flow-close="1" aria-label="Fechar centros esportivos">&times;</button>
        </div>
        <div class="popup-body" id="home-modality-locations-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-home-course-flow-close="1">Fechar</button></div>
    </div>
</div>

<div id="home-location-classes-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card home-course-classes-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-location-classes-title">
        <div class="popup-head">
            <div><span class="eyebrow">Etapa 3 de 3</span><h3 id="home-location-classes-title">Turmas disponíveis</h3><p class="muted" id="home-location-classes-subtitle"></p></div>
            <button type="button" class="popup-close-icon" data-home-course-flow-close="1" aria-label="Fechar turmas">&times;</button>
        </div>
        <div class="popup-body home-course-classes-list" id="home-location-classes-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-home-course-flow-back="1">Voltar</button><button type="button" class="btn btn-secondary" data-home-course-flow-close="1">Fechar</button></div>
    </div>
</div>

<div id="home-course-enrollment-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-course-enrollment-title">
        <div class="popup-head"><div><span class="eyebrow">Inscrição na turma</span><h3 id="home-course-enrollment-title">Detalhes da turma</h3><p class="muted" id="home-course-enrollment-subtitle"></p></div><button type="button" class="popup-close-icon" data-home-course-detail-close="1" aria-label="Fechar inscrição">&times;</button></div>
        <div class="popup-body" id="home-course-enrollment-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-home-course-detail-close="1">Fechar</button></div>
    </div>
</div>

<div id="home-course-cpf-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card home-course-small-modal" role="dialog" aria-modal="true" aria-labelledby="home-course-cpf-title">
        <div class="popup-head"><div><span class="eyebrow">Inscrição por CPF</span><h3 id="home-course-cpf-title">Informar CPF</h3><p class="muted" id="home-course-cpf-subtitle"></p></div><button type="button" class="popup-close-icon" data-home-course-detail-close="1" aria-label="Fechar inscrição por CPF">&times;</button></div>
        <div class="popup-body" id="home-course-cpf-content"></div>
    </div>
</div>

<div id="home-course-vacancies-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-course-flow-modal-card home-course-small-modal" role="dialog" aria-modal="true" aria-labelledby="home-course-vacancies-title">
        <div class="popup-head"><div><span class="eyebrow">Disponibilidade</span><h3 id="home-course-vacancies-title">Vagas da turma</h3><p class="muted" id="home-course-vacancies-subtitle"></p></div><button type="button" class="popup-close-icon" data-home-course-detail-close="1" aria-label="Fechar vagas">&times;</button></div>
        <div class="popup-body" id="home-course-vacancies-content"></div>
        <div class="popup-actions"><button type="button" class="btn btn-secondary" data-home-course-detail-close="1">Fechar</button></div>
    </div>
</div>

<section
    class="content-card home-locations-card home-training-locations-card"
    id="home-training-locations"
    data-locations="<?php echo e((string) json_encode($trainingLocations ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
>
    <div class="home-locations-copy">
        <?php if ($homeAdminMode) { ?><div class="admin-home-inline-actions"><button type="button" class="btn btn-secondary admin-home-small-button" data-home-edit="locais_treinos">Editar</button><button type="button" class="btn btn-primary admin-home-small-button" data-home-publish="locais_treinos">Publicar</button></div><?php } ?>
        <h2><?php echo e((string) ($homeTrainingLocationsContent['titulo'] ?? 'Locais de treinos')); ?></h2>
        <div class="home-training-description" id="home-training-description">
            <p><?php echo e($homeTrainingLocationsText); ?></p>
            <button type="button" class="home-training-description-toggle hidden" id="home-training-description-toggle" aria-expanded="false">Ler mais</button>
        </div>
    </div>
    <div class="home-location-suggestions" id="home-training-location-suggestions">
        <?php foreach (($suggestedTrainingLocations ?? []) as $location) { ?>
            <button type="button" class="home-location-suggestion" data-home-training-location="<?php echo e((string) $location['id']); ?>">
                <strong><?php echo e((string) ($location['apelido_local'] ?: $location['nome_local'])); ?></strong>
                <small>(<?php echo e((string) $location['nome_local']); ?>)</small>
            </button>
        <?php } ?>
    </div>
    <div class="home-locations-footer">
        <button type="button" class="btn btn-primary" id="home-all-training-locations-open">Todos os locais de treino</button>
    </div>
</section>

<div id="home-all-training-locations-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-all-locations-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-all-training-locations-title">
        <div class="popup-head">
            <div>
                <h3 id="home-all-training-locations-title">Todos os locais de treino</h3>
                <p class="muted">Somente locais que possuem horários cadastrados.</p>
            </div>
            <button type="button" class="popup-close-icon" data-home-all-training-locations-close="1" aria-label="Fechar locais de treino">&times;</button>
        </div>
        <div class="popup-body home-all-locations-list">
            <?php foreach (($trainingLocations ?? []) as $location) { ?>
                <button type="button" class="home-all-location-button" data-home-training-location="<?php echo e((string) $location['id']); ?>">
                    <strong><?php echo e((string) ($location['apelido_local'] ?: $location['nome_local'])); ?></strong>
                    <small><?php echo e((string) $location['nome_local']); ?></small>
                </button>
            <?php } ?>
        </div>
        <div class="popup-actions">
            <button type="button" class="btn btn-secondary" data-home-all-training-locations-close="1">Fechar</button>
        </div>
    </div>
</div>

<div id="home-training-modalities-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-all-locations-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-training-modalities-title">
        <div class="popup-head">
            <div>
                <span class="eyebrow">Agenda de treinos</span>
                <h3 id="home-training-modalities-title">Modalidades disponíveis</h3>
                <p class="muted" id="home-training-modalities-subtitle"></p>
            </div>
            <button type="button" class="popup-close-icon" data-home-training-modalities-close="1" aria-label="Fechar modalidades">&times;</button>
        </div>
        <div class="popup-body home-all-locations-list" id="home-training-modalities-list"></div>
        <div class="popup-actions">
            <button type="button" class="btn btn-secondary" data-home-training-modalities-back="1">Voltar aos locais</button>
            <button type="button" class="btn btn-secondary" data-home-training-modalities-close="1">Fechar</button>
        </div>
    </div>
</div>

<div id="home-training-calendar-modal" class="popup-overlay hidden" aria-hidden="true">
<section class="popup-card home-training-calendar-card" id="home-training-agenda" data-home-authenticated="<?php echo \App\Core\Auth::check() ? '1' : '0'; ?>" role="dialog" aria-modal="true" aria-labelledby="home-training-calendar-title">
    <div class="popup-head section-head">
        <div>
            <h2 id="home-training-calendar-title">Agenda de treinos <span id="home-training-calendar-location"></span></h2>
            <p class="muted">Os dias destacados possuem horários ou agendamentos vinculados à sua conta.</p>
            <p class="home-training-residency-notice">
                As inscrições para os cursos esportivos e os agendamentos para treinos são exclusivos para moradores de São Bernardo do Campo. Será exigido comprovante de endereço ao se matricular e também no dia do agendamento.
            </p>
        </div>
        <button type="button" class="popup-close-icon" data-home-training-calendar-close="1" aria-label="Fechar agenda de treinos">&times;</button>
    </div>
    <div class="popup-body">
        <input type="hidden" id="home-training-modality" value="0">
        <p class="home-training-calendar-filter-summary" id="home-training-calendar-filter-summary"></p>
        <div id="home-training-calendar"></div>
    </div>
    <div class="popup-actions">
        <button type="button" class="btn btn-secondary" data-home-training-calendar-back="1">Voltar às modalidades</button>
        <button type="button" class="btn btn-secondary" data-home-training-calendar-close="1">Fechar</button>
    </div>
</section>
</div>

<div id="home-training-day-modal" class="popup-overlay hidden" aria-hidden="true">
    <div class="popup-card home-training-day-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-training-day-title">
        <div class="popup-head">
            <div>
                <h3 id="home-training-day-title">Horários do dia</h3>
                <p class="muted" id="home-training-day-subtitle"></p>
                <p class="home-training-day-instruction">Clique na descrição completa do horário para agendar.</p>
            </div>
            <button type="button" class="popup-close-icon" data-home-training-day-close="1" aria-label="Fechar horários do dia">&times;</button>
        </div>
        <div class="popup-body">
            <div id="home-training-day-list" class="home-training-day-list"></div>
        </div>
        <div class="popup-actions">
            <button type="button" class="btn btn-secondary" data-home-training-day-close="1">Fechar</button>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/agenda/partials/booking_modals.php'; ?>

<?php if (!empty($homeSpecialEvents)) { ?>
<section class="content-card">
    <div class="section-head">
        <div>
            <h2>Horários especiais em destaque</h2>
            <p class="muted">Inscrições e avaliações especiais em evidência na página inicial.</p>
        </div>
        <button type="button" class="btn btn-secondary" data-home-scroll-target="home-training-agenda">Abrir agenda</button>
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
                        <a href="<?php echo e((string) (!empty($event['url_destino']) ? $event['url_destino'] : url('/#home-training-locations'))); ?>" class="btn btn-primary"><?php echo e((string) (!empty($event['rotulo_acao']) ? $event['rotulo_acao'] : 'Ver detalhes')); ?></a>
                    </div>
                </div>
            </article>
        <?php } ?>
    </div>
</section>
<?php } ?>

<section class="content-card" id="home-blog">
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
