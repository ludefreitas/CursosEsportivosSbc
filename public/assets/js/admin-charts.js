(function (window, $) {
    'use strict';
    const App = window.App = window.App || {};
    const number = value => Number(value || 0).toLocaleString('pt-BR');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const series = ['var(--chart-offered)', 'var(--chart-available)', 'var(--chart-enrollments)', 'var(--chart-purple)', 'var(--chart-teal)', 'var(--chart-red)'];
    const statusColors = { matriculada: series[0], aguardando_matricula: series[2], lista_espera: series[3], suspensa: series[4], cancelada: series[5], excluida: 'var(--chart-neutral)', excluida_por_falta: '#a25730', desistente: '#a75088' };
    let active = null;

    function Controller(root) {
        this.root = root;
        App.chartThemes?.initialize(root);
        this.attendanceUI=App.courseAttendanceCharts?.create(root, background=>{if(!background||!this.request)this.load(1);});
        this.find = selector => root.querySelector(selector);
        this.form = this.find('[data-chart-form]');
        this.options = JSON.parse(this.find('[data-chart-options]').textContent);
        this.data = null; this.request = null; this.sequence = 0; this.timer = null; this.resizeTimer = null;
        this.detailRequested = false;
        root.addEventListener('change', event => this.change(event));
        this.form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(this.timer); this.load(1); });
        root.addEventListener('click', event => this.click(event));
        this.lastWidth = 0;
        if (window.ResizeObserver) {
            this.observer = new ResizeObserver(entries => {
                const width = entries[0].contentRect.width;
                if (Math.abs(width - this.lastWidth) < 1) return;
                this.lastWidth = width;
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => { if (this.data && this.root.isConnected) this.drawComparison(); }, 80);
            });
            this.observer.observe(root);
        }
        this.lastQuery = '';
        this.load(1);
    }

    Controller.prototype.values = function (page) {
        const form = this.form;
        const get = name => form.elements.namedItem(name).value;
        return { modo: get('modo'), max_temporadas: get('max_temporadas'), temporadas: Array.from(form.querySelectorAll('[name="temporadas[]"]:checked'), input => input.value), de: get('de'), ate: get('ate'), local_id: get('local_id'), modalidade_id: get('modalidade_id'), publico: get('publico'), status: get('status'), procura_de: get('procura_de'), procura_ate: get('procura_ate'), periodo: get('periodo'), pagina: page || 1, ...(this.find('[data-attendance-from]').value ? {frequencia_de:this.find('[data-attendance-from]').value}:{}), ...(this.find('[data-attendance-to]').value ? {frequencia_ate:this.find('[data-attendance-to]').value}:{}), ...(this.find('[data-attendance-monitor-from]').value ? {monitor_de:this.find('[data-attendance-monitor-from]').value}:{}), ...(this.find('[data-attendance-monitor-to]').value ? {monitor_ate:this.find('[data-attendance-monitor-to]').value}:{}), monitor_turma:this.find('[data-attendance-class]').value, monitor_situacao:this.find('[data-attendance-state]').value, monitor_pagina:this.attendanceUI?.page||1 };
    };

    Controller.prototype.validate = function (values) {
        const limit = Number(values.max_temporadas);
        if (!Number.isInteger(limit) || limit < 1 || limit > this.options.max_seasons) return 'Informe um máximo de 1 a ' + this.options.max_seasons + ' temporadas.';
        let count = values.temporadas.length;
        if (values.modo === 'range') {
            const ids = this.options.seasons.map(season => String(season.id));
            const from = ids.indexOf(values.de), to = ids.indexOf(values.ate);
            if (from < 0 || to < from) return 'Selecione um intervalo válido, da temporada mais antiga à mais recente.';
            count = to - from + 1;
        }
        if (!count) return 'Selecione pelo menos uma temporada.';
        if (values.modo === 'single' && count !== 1) return 'Selecione uma única temporada.';
        if (count > limit) return 'A seleção contém ' + count + ' temporadas. Aumente o limite ou reduza a seleção.';
        if (!values.procura_de || !values.procura_ate || values.procura_de > values.procura_ate) return 'Selecione um intervalo válido de datas para a procura.';
        return '';
    };

    Controller.prototype.change = function (event) {
        if (event.target.matches('[data-chart-group]')) { if (this.data) this.drawGroups(); return; }
        if (event.target.matches('[data-chart-demand-measure]')) { if (this.data) this.drawDemand(); return; }
        if (!this.form.contains(event.target)) return;
        const mode = this.form.elements.namedItem('modo').value;
        const checks = Array.from(this.form.querySelectorAll('[name="temporadas[]"]'));
        if (mode === 'single') {
            const chosen = event.target.name === 'temporadas[]' && event.target.checked ? event.target : checks.filter(input => input.checked).pop();
            checks.forEach(input => { input.checked = input === chosen; });
        }
        this.find('[data-chart-season-fieldset]').classList.toggle('hidden', mode === 'range');
        this.find('[data-chart-range]').classList.toggle('hidden', mode !== 'range');
        const signature = JSON.stringify(this.values(1));
        // O blur de um campo já aplicado não deve recriar os gráficos durante um clique.
        if (signature === this.lastQuery) return;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(1), 180);
    };

    Controller.prototype.error = function (message) {
        const element = this.find('[data-chart-error]');
        element.textContent = message;
        element.classList.toggle('hidden', !message);
    };

    Controller.prototype.load = function (page) {
        clearTimeout(this.timer);
        const values = this.values(page);
        for (const [start,end,label] of [['frequencia_de','frequencia_ate','frequência'],['monitor_de','monitor_ate','monitoramento']]) {
            if(values[start]&&values[end]&&(values[start]>values[end]||(Date.parse(values[end])-Date.parse(values[start]))/86400000>365)){this.error('Selecione um intervalo válido de até 366 dias para '+label+'.');return;}
        }
        const validation = this.validate(values), sequence = ++this.sequence;
        if (this.request) this.request.abort();
        this.error(validation);
        this.lastQuery = JSON.stringify(this.values(1));
        if (validation) {
            this.find('[data-chart-results]').classList.add('hidden');
            this.find('[data-chart-status]').textContent = this.options.seasons.length ? 'Ajuste os filtros para visualizar os dados.' : 'Ainda não há temporadas cadastradas.';
            return;
        }
        const results = this.find('[data-chart-results]');
        results.setAttribute('aria-busy', 'true');
        this.find('[data-chart-status]').textContent = 'Carregando gráficos…';
        this.request = $.ajax({ url: this.root.dataset.url, method: 'GET', dataType: 'json', data: values, suppressGlobalLoading: true, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .done(response => {
                if (sequence !== this.sequence || !this.root.isConnected) return;
                if (!response || response.success === false || !response.data) {
                    this.error(String(response && response.message || 'Não foi possível carregar os gráficos.'));
                    results.classList.add('hidden');
                    this.find('[data-chart-status]').textContent = 'Consulta não concluída.';
                    return;
                }
                this.data = response.data;
                results.classList.remove('hidden');
                this.render();
                this.find('[data-chart-status]').textContent = this.data.totals.classes ? 'Gráficos atualizados.' : 'Nenhuma turma encontrada nos filtros selecionados.';
                this.find('[data-chart-updated]').textContent = 'Consulta: ' + this.data.generated_at;
                if (this.detailRequested) { this.find('[data-chart-details]').open = true; this.detailRequested = false; }
            })
            .fail((xhr, reason) => {
                if (sequence !== this.sequence || reason === 'abort' || !this.root.isConnected) return;
                if (App.auth && App.auth.tratarFalhaDeAcesso(xhr, () => this.load(page), '/admin#admin-graficos')) return;
                this.error(App.core && App.core.extrairMensagemErroAjax ? App.core.extrairMensagemErroAjax(xhr).mensagem : 'Não foi possível carregar os gráficos. Tente novamente.');
                results.classList.add('hidden');
                this.find('[data-chart-status]').textContent = 'Consulta não concluída.';
            })
            .always(() => { if (sequence === this.sequence) { results.removeAttribute('aria-busy'); this.request = null; } });
    };

    Controller.prototype.color = function (kind, id) {
        const list = kind === 'locations' ? this.options.locations : this.options.modalities;
        const index = list.findIndex(item => Number(item.id) === Number(id));
        return series[Math.max(0, index) % series.length];
    };

    Controller.prototype.click = function (event) {
        const group = event.target.closest('[data-chart-drill]');
        if (group) {
            const kind = this.find('[data-chart-group]').value;
            this.form.elements.namedItem(kind === 'locations' ? 'local_id' : 'modalidade_id').value = group.dataset.chartDrill;
            this.find('[data-chart-clear-group]').classList.remove('hidden');
            this.detailRequested = true;
            this.load(1); return;
        }
        if (event.target.closest('[data-chart-clear-group]')) {
            this.form.elements.namedItem('local_id').value = '';
            this.form.elements.namedItem('modalidade_id').value = '';
            this.find('[data-chart-clear-group]').classList.add('hidden');
            this.load(1); return;
        }
        const pagination = event.target.closest('[data-chart-page]');
        if (pagination && this.data) this.load(this.data.details.page + (pagination.dataset.chartPage === 'next' ? 1 : -1));
    };

    Controller.prototype.render = function () {
        const data = this.data, totals = data.totals;
        this.attendanceUI?.render(data.attendance);
        this.find('[data-chart-selection]').textContent = data.comparison.map(item => item.label).join(' + ') + ' · ' + number(totals.classes) + ' turmas · ' + number(totals.locations) + ' locais · ' + number(totals.modalities) + ' modalidades';
        this.find('[data-chart-stats]').innerHTML = [['Vagas oferecidas', totals.offered, 'Capacidade por público'], ['Vagas disponíveis', totals.available, 'Saldo atual de vagas'], ['Inscrições totais', totals.enrollments, data.filters.status ? this.options.statuses[data.filters.status] : 'Todos os status'], ['Turmas', totals.classes, 'Nos filtros selecionados']].map(([label, value, note]) => '<div class="chart-stat"><span>' + escape(label) + '</span><strong>' + number(value) + '</strong><span>' + escape(note) + '</span></div>').join('');
        const warning = this.find('[data-chart-anomaly]');
        warning.classList.toggle('hidden', !data.anomalies);
        warning.textContent = number(data.anomalies) + ' turma(s) têm o total cadastrado diferente da soma de Geral, PCD, PLM e PVS. A oferta apresentada usa a soma por público. Consulte o detalhamento para identificar essas turmas.';
        const maximum = Math.max(1, ...data.publics.map(item => item.offered));
        this.find('[data-chart-capacity]').innerHTML = data.publics.map(item => '<div class="chart-public-row"><span>' + escape(item.label) + '</span><div class="chart-public-tracks" role="img" aria-label="' + escape(item.label + ': ' + number(item.offered) + ' vagas oferecidas e ' + number(item.available) + ' disponíveis') + '"><div class="chart-track"><span class="chart-bar" style="background:var(--chart-offered);width:' + item.offered / maximum * 100 + '%"></span></div><div class="chart-track"><span class="chart-bar" style="background:var(--chart-available);width:' + item.available / maximum * 100 + '%"></span></div></div><span class="chart-numeric">' + number(item.offered) + ' / ' + number(item.available) + '</span></div>').join('');
        let angle = 0;
        const slices = data.statuses.map((item, index) => { const start = angle; angle += totals.enrollments ? item.count / totals.enrollments * 100 : 0; return (statusColors[item.id] || series[index % series.length]) + ' ' + start + '% ' + angle + '%'; });
        const ring = this.find('[data-chart-ring]');
        ring.style.background = totals.enrollments ? 'conic-gradient(' + slices.join(',') + ')' : 'var(--chart-track)';
        ring.setAttribute('aria-label', 'Distribuição de ' + number(totals.enrollments) + ' inscrições por status. Valores na legenda ao lado.');
        this.find('[data-chart-ring-total]').textContent = number(totals.enrollments);
        this.find('[data-chart-status-list]').innerHTML = data.statuses.map((item, index) => '<div class="chart-status-row"><i class="chart-status-dot" style="background:' + (statusColors[item.id] || series[index % series.length]) + '"></i><span>' + escape(item.label) + '</span><span class="chart-numeric">' + number(item.count) + '</span></div>').join('');
        const maxEnrollments = Math.max(1, ...data.publics.map(item => item.enrollments));
        this.find('[data-chart-publics]').innerHTML = data.publics.map(item => '<div class="chart-bar-row"><div class="chart-bar-label"><span>' + escape(item.label) + '</span><span class="chart-numeric">' + number(item.enrollments) + '</span></div><div class="chart-track"><span class="chart-bar" style="background:var(--chart-enrollments);width:' + item.enrollments / maxEnrollments * 100 + '%"></span></div></div>').join('');
        this.drawGroups(); this.drawComparison(); this.drawMatrix(); this.drawDetails(); this.drawDemand();
    };

    Controller.prototype.drawDemand = function () {
        const demand = this.data.demand;
        if (!demand) return;
        const measure = this.find('[data-chart-demand-measure]').value;
        const date = value => value.split('-').reverse().join('/');
        this.find('[data-chart-demand-range]').textContent = date(demand.from) + ' a ' + date(demand.to) + ' · até 10 resultados por ranking';
        this.find('[data-chart-demand-note]').textContent = measure === 'clicks'
            ? (demand.clicks_available ? 'Cliques registrados nas turmas, agrupados por local, modalidade e período de aula. Seguem os filtros de temporadas, local e modalidade. Como os cliques são anônimos, os filtros de público e status não se aplicam. A contagem começa com a ativação deste recurso; não há histórico anterior nem contagem de visitantes únicos.' : 'A contagem de cliques ainda não está disponível. Nenhum histórico de cliques foi estimado.')
            : 'Inscrições realizadas dentro do intervalo de datas, com os filtros de temporada, local, modalidade, público, status atual e período da aula. Não representa pessoas únicas.';
        const bars = (items, unit, color, scale) => {
            const maximum = scale || Math.max(1, ...items.map(item => item.count));
            return items.map(item => '<div class="chart-bar-row"><div class="chart-bar-label"><span>' + escape(item.label) + '</span><span class="chart-numeric">' + number(item.count) + ' ' + (item.count === 1 ? (unit === 'cliques' ? 'clique' : 'inscrição') : unit) + '</span></div>' + (item.context ? '<small class="muted">' + escape(item.context) + '</small>' : '') + '<div class="chart-track"><span class="chart-bar" style="width:' + item.count / maximum * 100 + '%;background:' + color + '"></span></div></div>').join('') || '<p class="muted">Nenhuma procura registrada neste intervalo.</p>';
        };
        for (const kind of ['locations','modalities','classes','periods']) {
            const unit = measure === 'clicks' ? 'cliques' : 'inscrições';
            const color = measure === 'clicks' ? 'var(--chart-purple)' : 'var(--chart-enrollments)';
            this.find('[data-chart-demand="' + kind + '"]').innerHTML = kind === 'periods'
                ? demand[measure][kind].map(item => bars([item], unit, color, Math.max(1, ...demand[measure][kind].map(period => period.count))) + '<details class="chart-period-modalities"><summary>Ver modalidades de ' + escape(item.label.toLocaleLowerCase('pt-BR')) + '</summary>' + bars(item.modalities || [], unit, color) + '</details>').join('')
                : bars(demand[measure][kind], unit, color);
        }
        for (const kind of ['locations','modalities']) this.find('[data-chart-navigation="' + kind + '"]').innerHTML = demand.clicks_available ? bars(demand.navigation[kind], 'cliques', 'var(--chart-teal)') : '<p class="muted">Contagem de cliques ainda não disponível.</p>';
    };

    Controller.prototype.drawGroups = function () {
        const kind = this.find('[data-chart-group]').value, secondary = kind === 'locations' ? 'modalities' : 'locations';
        const groups = this.data[kind], labels = this.data[secondary], maximum = Math.max(1, ...groups.map(group => group.count));
        this.find('[data-chart-group-legend]').innerHTML = labels.map(item => '<span><i style="background:' + this.color(secondary, item.id) + '"></i>' + escape(item.label) + '</span>').join('');
        this.find('[data-chart-groups]').innerHTML = groups.map(group => '<button type="button" class="chart-group-button" data-chart-drill="' + group.id + '" aria-label="Filtrar ' + escape(group.label) + ', ' + number(group.count) + ' turmas"><span class="chart-bar-label"><span>' + escape(group.label) + '</span><span class="chart-numeric">' + number(group.count) + ' turmas</span></span><span class="chart-stack" style="width:' + group.count / maximum * 100 + '%">' + group.parts.map(part => '<span style="background:' + this.color(secondary, part.id) + ';width:' + part.count / group.count * 100 + '%" aria-label="' + escape(part.label + ': ' + part.count + ' turmas') + '"></span>').join('') + '</span></button>').join('') || '<p class="muted">Nenhuma turma nos filtros selecionados.</p>';
    };

    Controller.prototype.drawComparison = function () {
        const target = this.find('[data-chart-comparison]'), items = this.data.comparison;
        const width = Math.max(160, target.getBoundingClientRect().width), height = 220, left = 43, right = 18, top = 20, bottom = 39;
        const definitions = [{ key: 'offered', color: series[0], label: 'Oferecidas' }, { key: 'available', color: series[1], label: 'Disponíveis' }, { key: 'enrollments', color: series[2], label: 'Inscrições' }];
        const max = Math.max(1, ...items.flatMap(item => definitions.map(series => item[series.key])));
        const x = index => items.length === 1 ? (width + left - right) / 2 : left + index * (width - left - right) / Math.max(1, items.length - 1);
        const y = value => height - bottom - value / max * (height - top - bottom);
        const tickCount = Math.min(4, items.length);
        const ticks = new Set(Array.from({ length: tickCount }, (_, index) => Math.round(index * (items.length - 1) / Math.max(1, tickCount - 1))));
        let svg = '<svg class="chart-svg" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-label="Comparação das temporadas; valores completos na lista abaixo"><title>Vagas e inscrições por temporada</title>';
        for (let index = 0; index <= 3; index++) {
            const value = max * index / 3;
            svg += '<line class="chart-grid-line" x1="' + left + '" x2="' + (width - right) + '" y1="' + y(value) + '" y2="' + y(value) + '"/><text x="' + (left - 7) + '" y="' + (y(value) + 4) + '" text-anchor="end">' + number(Math.round(value)) + '</text>';
        }
        items.forEach((item, index) => { if (ticks.has(index)) svg += '<text x="' + x(index) + '" y="' + (height - 18) + '" text-anchor="middle">' + (index + 1) + '</text>'; });
        definitions.forEach((series, seriesIndex) => {
            svg += '<polyline fill="none" stroke="' + series.color + '" stroke-width="2" ' + (seriesIndex === 1 ? 'stroke-dasharray="5 4"' : '') + ' points="' + items.map((item, index) => x(index) + ',' + y(item[series.key])).join(' ') + '"/>';
            items.forEach((item, index) => { svg += '<circle cx="' + x(index) + '" cy="' + y(item[series.key]) + '" r="4" fill="' + series.color + '"><title>' + escape(item.label + ' · ' + series.label + ': ' + number(item[series.key])) + '</title></circle>'; });
        });
        svg += '<text x="' + left + '" y="12">Quantidade</text><text x="' + (width - right) + '" y="' + (height - 1) + '" text-anchor="end">Temporada</text></svg>';
        target.innerHTML = svg;
        this.find('[data-chart-season-values]').innerHTML = items.map((item, index) => '<div class="chart-season-value"><strong>' + (index + 1) + '. ' + escape(item.label) + '</strong><span>' + number(item.offered) + ' oferecidas · ' + number(item.available) + ' disponíveis · ' + number(item.enrollments) + ' inscrições</span></div>').join('');
    };

    Controller.prototype.drawMatrix = function () {
        const { locations, modalities } = this.data;
        const maximum = Math.max(1, ...locations.flatMap(location => location.parts.map(part => part.count)));
        this.find('[data-chart-matrix]').innerHTML = locations.map(location => '<details class="chart-matrix-row" open><summary>' + escape(location.label) + '</summary><div class="chart-matrix-cells">' + modalities.map(modality => {
            const part = location.parts.find(part => part.id === modality.id), count = part ? part.count : 0;
            return '<div class="chart-matrix-cell" style="--cell-alpha:' + (count ? .1 + count / maximum * .35 : .03) + '"><span>' + escape(modality.label) + '</span><strong>' + number(count) + '</strong></div>';
        }).join('') + '</div></details>').join('') || '<p class="muted">Nenhuma turma nos filtros selecionados.</p>';
    };

    Controller.prototype.drawDetails = function () {
        const details = this.data.details;
        this.find('[data-chart-details-title]').textContent = 'Detalhar ' + number(details.total) + ' turmas';
        this.find('[data-chart-detail-rows]').innerHTML = details.rows.map(row => '<div class="chart-detail-row"><span><strong>[' + row.id + '] ' + escape(row.name) + '</strong><small>' + escape(row.location) + ' · ' + escape(row.modality) + ' · ' + escape(row.season) + '</small>' + (row.capacity_mismatch ? '<small class="chart-detail-warning">Conferir vagas totais cadastradas.</small>' : '') + '</span><span>' + number(row.offered) + ' oferecidas<small>' + number(row.available) + ' disponíveis</small></span><span>' + number(row.enrollments) + ' inscrições</span></div>').join('') || '<p class="muted">Nenhuma turma encontrada.</p>';
        this.find('[data-chart-page-label]').textContent = 'Página ' + details.page + ' de ' + details.pages;
        this.find('[data-chart-page="previous"]').disabled = details.page <= 1;
        this.find('[data-chart-page="next"]').disabled = details.page >= details.pages;
    };

    Controller.prototype.destroy = function () {
        this.attendanceUI?.destroy();
        ++this.sequence;
        clearTimeout(this.timer); clearTimeout(this.resizeTimer);
        if (this.request) this.request.abort();
        if (this.observer) this.observer.disconnect();
    };

    App.adminCharts = {
        initialize: function (scope) {
            const root = (scope || document).querySelector('[data-admin-charts]');
            if (active && active.root !== root) { active.destroy(); active = null; }
            if (root && !active) active = new Controller(root);
        }
    };
}(window, window.jQuery));
