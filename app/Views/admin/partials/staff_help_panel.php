<section class="admin-section-panel" data-admin-section="ajuda">
    <?php $tutorialPage = $tutorialHelp ?? []; require ROOT_PATH . '/app/Views/home/tutorial.php'; ?>
    <?php if (!empty($canManageTutorialHelp)) { ?>
        <?php foreach (($tutorialHelpLibraries ?? []) as $tutorialHelpArea => $tutorialPage) { ?>
            <details class="content-card"><summary>Gerenciar vídeos — área do <?php echo e($tutorialHelpArea); ?></summary>
                <?php require ROOT_PATH . '/app/Views/admin/partials/tutorial_page_editor.php'; ?>
            </details>
        <?php } ?>
    <?php } ?>
</section>
