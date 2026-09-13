<section class="admin-section-panel professor-view" data-admin-section="minhas-turmas" data-professor-class-browser="1" data-browser-url="<?php echo e(url('/professor/minhas-turmas/filtro')); ?>">
    <div class="section-head admin-section-head"><div><h2>Minhas turmas</h2></div><button type="button" class="btn btn-primary" data-course-create="class">Criar turma</button></div>
    <?php if (empty($professorClassSeasons)) { ?>
        <article class="content-card"><p class="muted">Nenhuma turma está atribuída a você como professor principal ou auxiliar.</p></article>
    <?php } else { ?>
        <div class="professor-class-filter-step" data-professor-class-step="season"><div class="professor-class-filter-buttons"><?php foreach ($professorClassSeasons as $season) { ?><button type="button" class="btn btn-secondary" data-professor-class-season="<?php echo e((string) $season['id']); ?>"><?php echo e((string) $season['nome']); ?></button><?php } ?></div></div>
        <div class="professor-class-filter-step hidden" data-professor-class-step="location"><div class="professor-class-filter-buttons"></div></div>
        <div class="professor-class-filter-step hidden" data-professor-class-step="modality"><div class="professor-class-filter-buttons"></div></div>
        <p class="muted hidden professor-class-loading" data-professor-class-loading="1">Carregando...</p>
        <p class="muted hidden" data-professor-class-result-count></p>
        <div class="professor-classes-grid hidden" data-professor-class-results></div>
    <?php } ?>
</section>
<?php
$courseManagementView = 'professor-turmas';
$courseClasses = [];
ob_start();
require ROOT_PATH . '/app/Views/admin/partials/course_management_panel.php';
$professorCourseControls = (string) ob_get_clean();
?>
<div class="hidden" data-professor-course-controls-source="1"><?php echo $professorCourseControls; ?></div>
