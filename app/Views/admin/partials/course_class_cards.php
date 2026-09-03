<?php
$groupByLocation = ($courseManagementView ?? 'turmas') === 'turmas-locais';
$filterType = $groupByLocation ? 'location' : 'modality';
$filterLabel = $groupByLocation ? 'local' : 'modalidade';
$availableSeasons = [];
foreach (($courseSeasons ?? []) as $season) {
    $seasonId = (string) ($season['id'] ?? '');
    if ($seasonId === '') continue;
    $availableSeasons[$seasonId] = (string) ($season['nome'] ?? 'Temporada');
}
foreach (($courseClasses ?? []) as $class) {
    $seasonId = (string) ($class['temporada_id'] ?? '');
    if ($seasonId === '') continue;
    if (!isset($availableSeasons[$seasonId])) $availableSeasons[$seasonId] = (string) ($class['temporada_nome'] ?? 'Temporada');
}
?>
<article class="content-card admin-class-browser" data-admin-class-browser="<?php echo e($filterType); ?>" data-class-filter-url="<?php echo e(url('/admin/turmas/filtro')); ?>" data-class-management-view="<?php echo e((string) $courseManagementView); ?>">
    <div class="course-table-heading">
        <div>
            <h2><?php echo $groupByLocation ? 'Turmas por local' : 'Turmas por modalidade'; ?></h2>
            <p class="muted">Selecione uma temporada e depois <?php echo $groupByLocation ? 'um local' : 'uma modalidade'; ?>.</p>
        </div>
        <button type="button" class="btn btn-primary" data-course-create="class">Criar turma</button>
    </div>

    <?php if ($availableSeasons === []) { ?>
        <p class="muted">Nenhuma temporada cadastrada.</p>
    <?php } else { ?>
        <div class="admin-class-filter-block">
            <strong>Temporadas</strong>
            <div class="admin-class-filter-line" data-class-filter-line="season">
                <div class="admin-class-filter-options" data-class-filter-options="season">
                    <?php $firstSeason = true; foreach ($availableSeasons as $seasonId => $seasonName) { ?>
                        <button type="button" class="btn btn-secondary admin-class-filter-button<?php echo $firstSeason ? ' is-active' : ''; ?>" data-class-season="<?php echo e($seasonId); ?>"><?php echo e($seasonName); ?></button>
                    <?php $firstSeason = false; } ?>
                </div>
                <button type="button" class="link-button admin-class-filter-more hidden" data-class-filter-more="season" aria-expanded="false">Mais...</button>
            </div>
        </div>

        <div class="admin-class-filter-block">
            <strong><?php echo $groupByLocation ? 'Locais' : 'Modalidades'; ?></strong>
            <div class="admin-class-filter-line" data-class-group-line>
                <div class="admin-class-filter-options" data-class-filter-options="group"><span class="muted">Selecione uma temporada.</span></div>
                <button type="button" class="link-button admin-class-filter-more hidden" data-class-filter-more="group" aria-expanded="false">Mais...</button>
            </div>
        </div>

        <p class="muted admin-class-result-summary" data-class-result-summary></p>
        <div class="professor-classes-grid admin-classes-grid" data-class-results><p class="muted">Selecione <?php echo $groupByLocation ? 'um local' : 'uma modalidade'; ?> para consultar as turmas.</p></div>
    <?php } ?>
</article>
