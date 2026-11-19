<?php /* @pdf-model pessoas_indicadores|Gráficos e indicadores de pessoas */
$peopleChartOptions = $peopleChartOptions ?? ['cities'=>[], 'neighborhoods'=>[]];
$peopleChartPreview = $peopleChartPreview ?? null;
$pdfModel = 'pessoas_indicadores';
?>
<section data-grid-print-model class="admin-section-panel admin-charts people-charts" data-admin-section="graficos-pessoas" data-people-charts data-chart-theme="dark"
    data-url="<?php echo e(url(!empty($professorView) ? '/professor/graficos-pessoas/dados' : '/admin/graficos-pessoas/dados')); ?>">
    <div class="people-charts-heading">
        <div><h2>Gráficos e indicadores de pessoas</h2><p class="muted">Explore os cadastros em uma apresentação com gráficos e tabelas.</p></div>
        <button type="button" class="btn btn-outline" data-chart-theme-toggle aria-pressed="true">Usar tema claro</button>
    </div>
    <details class="content-card people-chart-filters">
        <summary>Filtrar pessoas</summary>
        <form class="stack-form" data-people-chart-form>
            <div class="people-chart-filter-grid">
                <label>Cadastro a partir de<input type="date" name="from"></label>
                <label>Cadastro até<input type="date" name="to"></label>
                <label>Idade mínima<input type="number" name="age_min" min="0" max="130"></label>
                <label>Idade máxima<input type="number" name="age_max" min="0" max="130"></label>
                <label>Sexo<select name="sex"><option value="">Todos</option><option value="feminino">Feminino</option><option value="masculino">Masculino</option><option value="Sexo não declarado">Sexo não declarado</option></select></label>
                <label>Condição declarada<select name="condition"><option value="">Todas as pessoas</option><option value="pcd">PCD</option><option value="plm">PLM</option><option value="pvs">PVS</option></select></label>
                <label>Cidade<select name="city"><option value="">Todas</option><?php foreach ($peopleChartOptions['cities'] as $city) { ?><option value="<?php echo e($city); ?>"><?php echo e($city); ?></option><?php } ?></select></label>
                <label>Bairro<select name="neighborhood"><option value="">Todos</option><?php foreach ($peopleChartOptions['neighborhoods'] as $neighborhood) { ?><option value="<?php echo e($neighborhood); ?>"><?php echo e($neighborhood); ?></option><?php } ?></select></label>
                <label>Cadastro<select name="complete"><option value="">Todos</option><option value="1">Completo</option><option value="0">Incompleto</option></select></label>
                <label>Conta de acesso<select name="account"><option value="">Todas as pessoas</option><option value="with">Com conta própria</option><option value="without">Sem conta própria</option></select></label>
            </div>
            <div class="people-chart-actions"><button type="submit" class="btn btn-primary">Atualizar apresentação</button><button type="reset" class="btn btn-outline">Limpar filtros</button></div>
            <small>As datas filtram a criação do cadastro. Os slides de atividades apresentam o histórico das pessoas selecionadas.</small>
        </form>
    </details>
    <p data-people-chart-loading role="status" aria-live="polite">Carregando indicadores…</p>
    <p data-people-chart-error role="alert" hidden></p>
    <div data-people-chart-results hidden>
        <div class="people-chart-stats" data-people-chart-stats></div>
        <p class="people-chart-summary" data-people-chart-summary></p>
        <p class="people-chart-warning" data-people-chart-unavailable hidden></p>
        <?php require __DIR__ . '/indicator_slide_stage.php'; ?>
        <p class="people-chart-method">Base calculada em <span data-people-chart-date></span>. Os percentuais usam o denominador informado em cada slide. Indicadores com múltiplos vínculos ou condições podem se sobrepor.</p>
    </div>
    <template data-people-print-header><div class="people-print-brand"><?php require __DIR__ . '/../../partials/pdf_branding_header.php'; ?></div></template>
    <template data-people-print-footer><div class="people-print-brand"><?php require __DIR__ . '/../../partials/pdf_branding_footer.php'; ?></div></template>
    <?php if ($peopleChartPreview !== null) { ?><script type="application/json" data-people-chart-preview><?php echo json_encode($peopleChartPreview, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script><?php } ?>
</section>
