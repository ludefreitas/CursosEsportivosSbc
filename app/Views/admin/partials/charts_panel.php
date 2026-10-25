<?php $chartOptions = $chartOptions ?? ['seasons' => [], 'locations' => [], 'modalities' => [], 'publics' => [], 'statuses' => [], 'max_seasons' => 12]; ?>
<section class="admin-section-panel admin-charts" data-admin-section="graficos" data-admin-charts data-url="<?php echo e(url('/admin/graficos/dados')); ?>">
    <script type="application/json" data-chart-options><?php echo json_encode($chartOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <div class="section-head admin-section-head"><div><h2>Gráficos e indicadores</h2><p class="muted">Compare temporadas, locais, modalidades, vagas e inscrições.</p></div></div>
    <button type="button" class="btn btn-outline" data-chart-theme-toggle aria-pressed="false">Usar tema escuro</button>
    <form class="content-card chart-filters" data-chart-form>
        <div class="chart-filter-grid">
            <label><span>Temporadas</span><select name="modo"><option value="multi">Combinar temporadas</option><option value="single">Uma temporada</option><option value="range">Todas no intervalo</option></select></label>
            <label><span>Máximo de temporadas</span><input name="max_temporadas" type="number" min="1" max="<?php echo e((string) $chartOptions['max_seasons']); ?>" value="6" required></label>
            <label><span>Local</span><select name="local_id"><option value="">Todos os locais</option><?php foreach ($chartOptions['locations'] as $item) { ?><option value="<?php echo e((string) $item['id']); ?>"><?php echo e($item['nome']); ?></option><?php } ?></select></label>
        </div>
        <fieldset class="chart-season-fieldset" data-chart-season-fieldset><legend>Selecionar temporadas</legend><div class="chart-season-options" data-chart-seasons>
            <?php $defaultSeasons = array_slice(array_column($chartOptions['seasons'], 'id'), -2); foreach ($chartOptions['seasons'] as $season) { ?>
            <label class="checkbox-chip"><input type="checkbox" name="temporadas[]" value="<?php echo e((string) $season['id']); ?>"<?php echo in_array($season['id'], $defaultSeasons) ? ' checked' : ''; ?>><span><?php echo e($season['nome']); ?></span></label>
            <?php } ?>
        </div></fieldset>
        <div class="chart-filter-grid hidden" data-chart-range>
            <?php foreach (['de' => 'Da temporada', 'ate' => 'Até a temporada'] as $name => $label) { ?><label><span><?php echo e($label); ?></span><select name="<?php echo e($name); ?>"><?php foreach ($chartOptions['seasons'] as $index => $season) { ?><option value="<?php echo e((string) $season['id']); ?>"<?php echo $name === 'ate' && $index === count($chartOptions['seasons']) - 1 ? ' selected' : ''; ?>><?php echo e($season['nome']); ?></option><?php } ?></select></label><?php } ?>
        </div>
        <div class="chart-filter-grid">
            <label><span>Modalidade</span><select name="modalidade_id"><option value="">Todas as modalidades</option><?php foreach ($chartOptions['modalities'] as $item) { ?><option value="<?php echo e((string) $item['id']); ?>"><?php echo e($item['nome']); ?></option><?php } ?></select></label>
            <label><span>Público</span><select name="publico"><option value="">Todos os públicos</option><?php foreach ($chartOptions['publics'] as $value => $label) { ?><option value="<?php echo e($value); ?>"><?php echo e($label); ?></option><?php } ?></select></label>
            <label><span>Status da inscrição</span><select name="status"><option value="">Todos os status</option><?php foreach ($chartOptions['statuses'] as $value => $label) { ?><option value="<?php echo e($value); ?>"><?php echo e($label); ?></option><?php } ?></select></label>
        </div>
        <?php $chartToday = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo')); ?>
        <fieldset class="chart-demand-fieldset"><legend>Intervalo da procura</legend><div class="chart-filter-grid">
            <label><span>Data inicial</span><input type="date" name="procura_de" value="<?php echo e($chartToday->modify('-29 days')->format('Y-m-d')); ?>" required></label>
            <label><span>Data final</span><input type="date" name="procura_ate" value="<?php echo e($chartToday->format('Y-m-d')); ?>" required></label>
            <label><span>Período da aula</span><select name="periodo"><option value="">Todos os períodos</option><option value="manha">Manhã</option><option value="tarde">Tarde</option><option value="noite">Noite</option><option value="sem_horario">Sem horário</option></select></label>
        </div><p class="muted">As datas e o período da aula filtram somente a seção “Mais procurados”. As datas incluem o dia inicial e o final.</p></fieldset>
        <div class="chart-filter-actions"><button type="submit" class="btn btn-primary">Atualizar gráficos</button><span class="muted" data-chart-updated></span></div>
        <p class="chart-error hidden" role="alert" data-chart-error></p>
    </form>
    <p class="muted chart-query-status" role="status" aria-live="polite" data-chart-status>Preparando os gráficos…</p>
    <div data-chart-results class="hidden">
        <p class="muted chart-selection" data-chart-selection></p>
        <div class="chart-stats" data-chart-stats></div>
        <p class="chart-anomaly hidden" data-chart-anomaly></p>
        <div class="chart-panel-grid">
            <article class="content-card chart-panel"><h3>Vagas oferecidas e disponíveis</h3><div class="chart-legend"><span><i style="background:var(--chart-offered)"></i>Oferecidas</span><span><i style="background:var(--chart-available)"></i>Disponíveis</span></div><div class="chart-bars" data-chart-capacity></div><p class="muted">Valores por público: oferecidas / disponíveis.</p></article>
            <article class="content-card chart-panel"><h3>Situação das inscrições</h3><div class="chart-ring-layout"><div class="chart-ring" role="img" data-chart-ring><div class="chart-ring-center"><strong data-chart-ring-total>0</strong><span>inscrições</span></div></div><div class="chart-status-list" data-chart-status-list></div></div><p class="muted">Cada inscrição aparece uma vez, no seu status atual.</p></article>
            <article class="content-card chart-panel"><h3>Turmas, locais e modalidades</h3><label class="chart-group-select"><span>Agrupar por</span><select data-chart-group><option value="locations">Local → modalidades</option><option value="modalities">Modalidade → locais</option></select></label><div class="chart-legend" data-chart-group-legend></div><div class="chart-bars" data-chart-groups></div><p class="muted">Quantidade de turmas. Toque em uma barra para filtrar e detalhar.</p><button type="button" class="link-button hidden" data-chart-clear-group>Limpar filtro do gráfico</button></article>
            <article class="content-card chart-panel"><h3>Comparação das temporadas</h3><div class="chart-legend"><span><i style="background:var(--chart-offered)"></i>Oferecidas</span><span><i style="background:var(--chart-available)"></i>Disponíveis</span><span><i style="background:var(--chart-enrollments)"></i>Inscrições</span></div><div data-chart-comparison></div><div data-chart-season-values class="chart-season-values"></div><p class="muted">Retrato atual das temporadas, não o histórico diário das alterações.</p></article>
            <article class="content-card chart-panel"><h3>Inscrições por público</h3><div data-chart-publics class="chart-bars"></div><p class="muted">Contagem de registros, com o filtro de status aplicado.</p></article>
            <article class="content-card chart-panel"><h3>Oferta de turmas por local</h3><div data-chart-matrix class="chart-matrix"></div><p class="muted">Cada valor indica turmas, não vagas.</p></article>
            <article class="content-card chart-panel chart-panel-full"><details data-chart-details><summary data-chart-details-title>Detalhar turmas</summary><div data-chart-detail-rows class="chart-detail-rows"></div><nav class="chart-pagination" aria-label="Páginas de turmas"><button type="button" class="btn btn-secondary" data-chart-page="previous">Anterior</button><span data-chart-page-label></span><button type="button" class="btn btn-secondary" data-chart-page="next">Próxima</button></nav></details></article>
        </div>
        <section class="chart-demand-section" aria-label="Mais procurados">
            <div class="section-head"><div><h3>Mais procurados</h3><p class="muted" data-chart-demand-range></p></div><label class="chart-group-select"><span>Medir procura por</span><select data-chart-demand-measure><option value="enrollments">Inscrições realizadas</option><option value="clicks">Cliques nas turmas</option></select></label></div>
            <p class="muted" data-chart-demand-note></p>
            <div class="chart-panel-grid">
                <article class="content-card chart-panel"><h3>Locais mais procurados</h3><div data-chart-demand="locations" class="chart-bars"></div></article>
                <article class="content-card chart-panel"><h3>Modalidades mais procuradas</h3><div data-chart-demand="modalities" class="chart-bars"></div></article>
                <article class="content-card chart-panel"><h3>Turmas mais procuradas</h3><div data-chart-demand="classes" class="chart-bars"></div></article>
                <article class="content-card chart-panel"><h3>Períodos de aula mais procurados</h3><div data-chart-demand="periods" class="chart-bars"></div><p class="muted">Pelo início da aula: manhã, das 5h às 11h59; tarde, das 12h às 17h59; noite, nos demais horários. “Sem horário” indica turma sem horário cadastrado.</p></article>
                <article class="content-card chart-panel chart-panel-full"><details><summary>Cliques diretos em locais e modalidades</summary><p class="muted">Seleções anteriores à escolha da turma. Estes totais seguem apenas o intervalo de datas: não pertencem a uma temporada, público, status ou período de aula. São cliques registrados, não visitantes únicos.</p><div class="chart-panel-grid chart-navigation-rankings"><div><h4>Locais selecionados</h4><div data-chart-navigation="locations" class="chart-bars"></div></div><div><h4>Modalidades selecionadas</h4><div data-chart-navigation="modalities" class="chart-bars"></div></div></div></details></article>
            </div>
        </section>
        <section class="chart-demand-section" data-course-attendance>
            <h3>Frequência nas aulas e monitoramento das turmas</h3>
            <div class="chart-filter-grid">
                <label>Frequência: data inicial<input type="date" name="frequencia_de" data-attendance-from></label>
                <label>Frequência: data final<input type="date" name="frequencia_ate" data-attendance-to></label>
                <button type="button" class="btn btn-secondary" data-attendance-update>Atualizar frequência e monitor</button>
            </div>
            <p class="muted">A frequência acompanha temporadas, local, modalidade, público e período da aula. O status da inscrição não filtra os registros históricos. O monitor de aulas tem um intervalo próprio e usa os alunos atualmente matriculados.</p>
            <p role="status" data-attendance-message></p>
            <div data-attendance-content hidden>
                <div class="chart-stats" data-attendance-stats></div>
                <div class="chart-panel-grid">
                    <article class="content-card chart-panel"><h3>Presenças, faltas e justificativas</h3><div data-attendance-statuses></div></article>
                    <article class="content-card chart-panel"><h3>Frequência por período</h3><div data-attendance-group="periods"></div></article>
                    <article class="content-card chart-panel"><h3>Frequência por local</h3><div data-attendance-group="locations"></div></article>
                    <article class="content-card chart-panel"><h3>Frequência por modalidade</h3><div data-attendance-group="modalities"></div></article>
                    <article class="content-card chart-panel"><h3>Frequência por turma</h3><label>Ordenar por<select data-attendance-metric><option value="present">Presenças</option><option value="attendance">Percentual de presença</option><option value="absent">Faltas não justificadas</option><option value="justified">Faltas justificadas</option></select></label><div data-attendance-group="classes"></div></article>
                    <article class="content-card chart-panel"><h3>Frequência por dia da semana</h3><div data-attendance-group="weekdays"></div></article>
                    <article class="content-card chart-panel chart-panel-full"><h3>Evolução da frequência nas aulas</h3><div data-attendance-trend></div></article>
                </div>
                <article class="content-card">
                    <h3 data-attendance-today>Monitor das chamadas</h3>
                    <div class="chart-filter-grid"><label>Monitor: data inicial<input type="date" data-attendance-monitor-from></label><label>Monitor: data final<input type="date" data-attendance-monitor-to></label><div class="chart-filter-actions"><button type="button" class="btn btn-secondary" data-attendance-monitor-update>Consultar aulas</button><button type="button" class="btn btn-outline" data-attendance-today-button>Hoje</button></div></div>
                    <p class="muted">Todas as aulas previstas no intervalo, mesmo sem registros de chamada. Até 366 dias. A cobertura em datas passadas é estimada com a situação atual das matrículas e a data de criação da inscrição; alterações de matrícula e de calendário não têm reconstituição histórica completa.</p>
                    <div class="chart-filter-grid">
                        <label>Turma<select data-attendance-class><option value="">Todas as turmas</option></select></label>
                        <label>Situação da chamada<select data-attendance-state><option value="scheduled">Aulas previstas no intervalo</option><option value="">Todas as situações</option><option value="nao_iniciada">Chamada não iniciada</option><option value="parcial">Chamada parcial</option><option value="completa">Chamada completa</option><option value="aguardando">Aguardando horário</option><option value="sem_matriculados">Sem matriculados</option><option value="sem_cronograma">Sem cronograma</option><option value="sem_aula">Sem aula no intervalo</option></select></label>
                        <label><input type="checkbox" data-attendance-auto checked> Atualizar a cada 60 segundos enquanto o painel estiver aberto</label>
                    </div>
                    <p data-attendance-monitor-summary aria-live="polite"></p><div class="chart-stats" data-attendance-monitor-stats></div>
                    <div data-attendance-monitor></div>
                    <div class="chart-filter-actions"><button type="button" class="btn btn-outline" data-attendance-prev>Anterior</button><span data-attendance-page></span><button type="button" class="btn btn-outline" data-attendance-next>Próxima</button></div>
                    <p class="muted">Chamada completa significa que todos os alunos atualmente matriculados têm presente, ausente ou justificado registrado na data da aula. Não existe confirmação separada de encerramento da chamada. A cobertura usa o público selecionado. Sem registro não é falta; ausência de cronograma não é ausência de chamada confirmada.</p>
                </article>
                <p class="muted">Percentual de presença = presentes ÷ (presentes + ausentes + justificados). São registros por aluno e aula, não pessoas únicas. Registros em datas futuras são excluídos das estatísticas. Nomes e vínculos das turmas refletem o cadastro atual.</p>
            </div>
        </section>
        <p class="muted chart-explanation">Vagas disponíveis seguem a regra atual do sistema: a capacidade por público menos as reservas de inscrições matriculadas, aguardando matrícula e suspensas. Lista de espera e demais status não ocupam essas vagas. O filtro de status altera as inscrições apresentadas, sem alterar artificialmente a disponibilidade. Inscrições são registros, não pessoas únicas. Turmas encerradas também entram quando pertencem às temporadas selecionadas.</p>
    </div>
</section>
