<?php
$options = $agendaChartOptions ?? ['locations'=>[], 'modalities'=>[], 'spaces'=>[]];
$today = new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
?>
<section class="admin-section-panel admin-charts agenda-charts" data-admin-section="graficos-agendamento" data-agenda-charts data-url="<?php echo e(url('/admin/graficos-agendamento/dados')); ?>">
    <div class="section-head"><div><h2>Gráficos e indicadores do agendamento</h2><p class="muted">Compare a procura por reservas, a frequência registrada e a ocupação das vagas.</p></div></div>
    <button type="button" class="btn btn-outline" data-chart-theme-toggle aria-pressed="false">Usar tema escuro</button>
    <form class="content-card chart-filters" data-agenda-form>
        <div class="chart-filter-grid">
            <label>Tipo de agenda<select name="type"><option value="weekly">Horários semanais</option><option value="special">Horários especiais</option></select></label>
            <label>Data inicial da sessão<input type="date" name="from" value="<?php echo $today->format('Y-m-01'); ?>" required></label>
            <label>Data final da sessão<input type="date" name="to" value="<?php echo $today->format('Y-m-d'); ?>" required></label>
            <?php foreach (['locations'=>'Locais', 'modalities'=>'Modalidades', 'spaces'=>'Espaços'] as $key=>$label) { ?>
                <label><?php echo $label; ?><select name="<?php echo $key; ?>[]" multiple size="4" aria-describedby="agenda-multiple-help"><?php foreach ($options[$key] as $item) { $name = $key==='locations' ? (trim((string)($item['apelido_local']??'')) ?: $item['nome']) : $item['nome']; ?><option value="<?php echo (int)$item['id']; ?>"><?php echo e($name); ?></option><?php } ?></select></label>
            <?php } ?>
            <label>Público<select name="public"><option value="">Todos os públicos</option><?php foreach (\App\Services\AgendaChartService::PUBLICS as $value=>$label) { ?><option value="<?php echo $value; ?>"><?php echo $label; ?></option><?php } ?></select></label>
            <label>Período<select name="period"><option value="">Todos os períodos</option><option value="manha">Manhã (5h às 11h59)</option><option value="tarde">Tarde (12h às 17h59)</option><option value="noite">Noite (demais horários)</option></select></label>
            <label>Dia da semana<select name="weekday"><option value="">Todos os dias</option><?php foreach ([1=>'Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo'] as $value=>$label) { ?><option value="<?php echo $value; ?>"><?php echo $label; ?></option><?php } ?></select></label>
            <label>Horário inicial<input type="time" name="hour"></label>
            <label>Status para contagem separada<select name="status"><option value="">Todos os registros</option><option value="agendado">Agendado</option><option value="presente">Presente</option><option value="falta">Falta</option><option value="justificado">Justificado</option><option value="cancelado">Cancelado</option><option value="inscrito">Inscrito (especial)</option></select></label>
        </div>
        <p id="agenda-multiple-help" class="muted">Sem seleção: todos. Nos campos de seleção múltipla, use Ctrl no computador ou a seleção nativa do celular. Intervalo máximo: 366 dias.</p>
        <div class="chart-filter-actions"><label><input type="checkbox" name="compare" value="1"> Comparar com o intervalo anterior de mesma duração</label><button type="submit" class="btn btn-primary">Atualizar indicadores</button><button type="reset" class="btn btn-outline">Limpar filtros</button></div>
        <p class="muted">O status altera somente a contagem separada; mantém a oferta, a ocupação e os denominadores de frequência.</p>
    </form>
    <p data-agenda-loading role="status" aria-live="polite">Carregando indicadores…</p>
    <p class="chart-error" data-agenda-error role="alert" hidden></p>
    <div data-agenda-results hidden>
        <p data-agenda-summary aria-live="polite"></p>
        <p class="chart-anomaly">Oferta estimada pela grade e capacidade atuais. Alterações antigas de horários e vagas não têm histórico completo.</p>
        <p class="chart-anomaly" data-agenda-special hidden>Horários especiais têm inscrições e cancelamentos, sem chamada equivalente aos horários semanais. Presenças, faltas e comparecimento não estão disponíveis.</p>
        <div class="chart-stats" data-agenda-stats></div>
        <div class="chart-anomaly" data-agenda-comparison hidden></div>
        <p data-agenda-highlights></p>
        <label class="agenda-measure">Comparar rankings por<select data-agenda-measure><option value="reserved">Reservas válidas</option><option value="present">Presenças</option><option value="occupancy">Ocupação (%)</option><option value="absent">Faltas não justificadas</option><option value="justified">Faltas justificadas</option><option value="attendance">Comparecimento (%)</option></select></label>
        <div class="chart-panel-grid">
            <?php foreach (['modality'=>'Modalidades','local'=>'Locais','space'=>'Espaços','time'=>'Horários iniciais'] as $key=>$label) { ?><section class="content-card chart-panel"><h3><?php echo $label; ?> mais procurados e frequentados</h3><div data-agenda-rank="<?php echo $key; ?>"></div><p class="muted">Até 10 resultados. Selecione uma barra para filtrar.</p></section><?php } ?>
            <section class="content-card chart-panel"><h3>Manhã, tarde e noite</h3><div data-agenda-periods></div></section>
            <section class="content-card chart-panel"><h3>Situação dos agendamentos</h3><div data-agenda-statuses></div></section>
            <section class="content-card chart-panel"><h3>Ocupação por dia e período</h3><div data-agenda-heatmap></div><p class="muted">Reservas ÷ vagas oferecidas. “—” indica ausência de oferta.</p></section>
            <section class="content-card chart-panel"><h3>Vagas por público</h3><div data-agenda-publics></div><p class="muted">Oferta e reservas por categoria.</p></section>
            <section class="content-card chart-panel agenda-trend-panel"><h3>Evolução no intervalo</h3><div data-agenda-trend></div><p class="muted">Totais por data de realização da sessão.</p></section>
        </div>
        <details class="content-card agenda-details" data-agenda-details><summary>Detalhamento das sessões</summary><div data-agenda-detail-rows></div><div class="chart-filter-actions"><button type="button" class="btn btn-outline" data-agenda-prev>Anterior</button><span data-agenda-page></span><button type="button" class="btn btn-outline" data-agenda-next>Próxima</button></div></details>
        <p data-agenda-coverage></p>
        <details class="content-card"><summary>Como os indicadores são calculados</summary><ul data-agenda-notes></ul><p>Comparecimento = presenças ÷ (presenças + faltas + justificativas). Futuros e chamadas pendentes ficam separados. Ocupação é a razão dos totais, sem média simples dos percentuais. Vagas são vagas-sessão, não pessoas únicas. A procura representa reservas válidas; não mede cliques ou tentativas sem vaga.</p></details>
    </div>
</section>
