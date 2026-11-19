(function (window) {
    'use strict';
    const App = window.App = window.App || {};
    const date = value => String(value || '').slice(0, 10).split('-').reverse().join('/');
    const sum = rows => rows.reduce((total, row) => total + (Number(row.count) || 0), 0);
    const labels = { offered: 'Vagas oferecidas', available: 'Vagas disponíveis', enrollments: 'Inscrições', classes: 'Turmas', reserved: 'Reservas', present: 'Presenças', absent: 'Faltas não justificadas', justified: 'Faltas justificadas', cancelled: 'Cancelamentos', pending: 'Chamadas pendentes', future: 'Agendamentos futuros', other: 'Outros status', unassessed: 'Inscrições passadas sem chamada', occupancy: 'Ocupação', attendance: 'Percentual de presença', enrolled: 'Matrículas realizadas', awaiting_enrollment: 'Aguardando matrícula', waiting_list: 'Fila de espera' };
    const periods = { manha: 'Manhã', tarde: 'Tarde', noite: 'Noite', madrugada: 'Madrugada', sem_horario: 'Sem horário' };
    const weekdays = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
    const states = { nao_iniciada: 'Chamada não iniciada', parcial: 'Chamada parcial', completa: 'Chamada completa', aguardando: 'Aguardando horário', sem_matriculados: 'Sem matriculados', sem_aula: 'Sem aula no intervalo', sem_cronograma: 'Sem cronograma' };

    function report(domain, generated, context) {
        return { domain, generated_at: String(generated).includes('/') ? generated : date(generated) + ' ' + String(generated).slice(11), reference_date: String(generated).includes('/') ? String(generated).slice(0, 10) : date(generated), filters_text: context, summary: { people: 1 }, cards: [], slides: [], unavailable: [] };
    }
    function add(report, id, title, source, metric = 'count', description = '', options = {}) {
        const rows = (source || []).map(row => ({ label: String(row.label ?? row.name ?? row.id ?? 'Não informado'), count: row[metric] == null ? null : Number(row[metric]) }));
        report.slides.push({ id, title, rows, description, unit: options.unit || 'registros', rate: ['occupancy', 'attendance'].includes(metric), ...options, base: options.base ?? sum(rows) });
    }
    function filterText(root, selector) {
        const form = root.querySelector(selector);
        if (!form) return '';
        return [...form.querySelectorAll('select,input')].flatMap(field => {
            if (field.disabled || !field.value || ['max_temporadas','modo','de','ate'].includes(field.name)) return [];
            if (['checkbox','radio'].includes(field.type) && !field.checked) return [];
            if (selector === '[data-agenda-form]' && ['type','from','to'].includes(field.name)) return [];
            if (field.name === 'compare') return ['Comparação com intervalo anterior'];
            if (field.tagName === 'SELECT') {
                return [...field.selectedOptions].filter(option => option.value).map(option => option.textContent.trim());
            }
            if (field.name === 'temporadas[]') return [];
            if (field.type === 'hidden') return [];
            const label = field.closest('label')?.childNodes[0]?.textContent.trim() || field.name;
            return [label + ': ' + (field.type === 'date' ? date(field.value) : field.value)];
        }).join(' · ');
    }
    function courses(data, root) {
        const r = report('Turmas', data.generated_at, data.comparison.map(item => item.label).join(' + ') + ' · ' + filterText(root, '[data-chart-form]'));
        const t = data.totals;
        r.cards = [['Turmas', t.classes], ['Vagas oferecidas', t.offered], ['Vagas disponíveis', t.available], ['Inscrições', t.enrollments], ['Locais', t.locations]];
        if (data.anomalies) r.unavailable.push(data.anomalies + ' turma(s) com divergência entre vagas totais e soma por público; a oferta usa a soma por público');
        const capacity = 'Retrato atual nos filtros selecionados. A disponibilidade considera as reservas e as liberações de vagas de inscrições suspensas.';
        for (const key of ['offered', 'available', 'enrollments']) add(r, 'public-' + key, labels[key] + ' por público', data.publics, key, capacity, { unit: key === 'enrollments' ? 'inscrições' : 'vagas' });
        add(r, 'statuses', 'Situação das inscrições', data.statuses, 'count', 'Cada inscrição aparece uma vez, no status atual. O filtro de status não altera a disponibilidade.', { unit: 'inscrições' });
        for (const [key, label] of [['locations','local'], ['modalities','modalidade']]) {
            add(r, key, 'Turmas por ' + label, data[key], 'count', 'Quantidade de turmas nos filtros selecionados.', { unit: 'turmas' });
            const cross = data[key].flatMap(group => group.parts.map(part => ({ label: group.label + ' · ' + part.label, count: part.count })));
            add(r, key + '-parts', 'Oferta de turmas por ' + label + ' e ' + (key === 'locations' ? 'modalidade' : 'local'), cross, 'count', 'Cada turma é contada uma vez no cruzamento.', { unit: 'turmas' });
        }
        for (const key of ['offered','available','enrollments','classes']) add(r, 'seasons-' + key, 'Temporadas · ' + labels[key], data.comparison, key, 'Retrato atual das temporadas, sem reconstituição diária de mudanças.', { unit: key === 'classes' ? 'turmas' : key === 'enrollments' ? 'inscrições' : 'vagas' });
        const age = data.age_relationships;
        if (age) {
            const bands = new Map();
            for (const row of age.ages) {
                const entry = bands.get(row.band) || { label: row.age, count: 0, offered: row.offered === null ? null : 0, available: row.available === null ? null : 0 };
                entry.count += row.enrollments;
                if (entry.offered !== null) { entry.offered += row.offered || 0; entry.available += row.available || 0; }
                bands.set(row.band, entry);
            }
            for (const key of ['count','offered','available']) add(r, 'age-' + key, 'Idade dos alunos · ' + (labels[key] || 'Inscrições'), [...bands.values()], key,
                'Idade em ' + age.age_reference + '. Vagas compartilhadas entre faixas compatíveis, sem somar as vagas entre faixas. Sem idade válida, vagas não se aplicam.', { unit: key === 'count' ? 'inscrições' : 'vagas', base: key === 'count' ? undefined : t[key], hide_share: key !== 'count' });
            for (const key of ['offered','available','enrollments','occupancy']) add(r, 'age-rule-' + key, 'Regra etária, local e modalidade · ' + labels[key], age.groups.map(row => ({ ...row, label: row.location + ' · ' + row.modality + ' · ' + row.age_rule })), key,
                'Cada turma aparece uma vez na regra cadastrada. Ocupação = reservas ÷ oferta e pode superar 100%. Sem oferta, a taxa não se aplica.', { unit: key === 'enrollments' ? 'inscrições' : 'vagas' });
        }
        let professors = data.professors || [];
        const selectedProfessor = root.querySelector('[data-chart-professor]')?.value;
        if (selectedProfessor) professors = professors.filter(row => String(row.id) === selectedProfessor);
        for (const key of ['classes','enrollments','offered','available','enrolled','awaiting_enrollment','waiting_list']) add(r, 'professor-' + key, (root.hasAttribute('data-professor-charts') ? 'Função na turma' : 'Professor principal') + ' · ' + labels[key], professors, key, 'Os vínculos refletem a equipe atual; cada turma é contabilizada uma vez para seu professor principal ou para a função do professor neste painel.');
        add(r, 'professor-attendance', 'Frequência por professor ou função', professors.map(row => ({ label: row.label, attendance: row.frequency?.records ? row.frequency.attendance : null })), 'attendance', 'Razão dos totais de presenças e chamadas concluídas, sem média simples dos percentuais.');
        const classes = [...new Map((data.professors || []).flatMap(row => row.rows).map(row => [row.id, row])).values()];
        for (const key of ['offered','available','enrollments']) add(r, 'classes-' + key, 'Turmas · ' + labels[key], classes.map(row => ({ ...row, label: '[' + row.id + '] ' + row.name + ' · ' + row.location })), key, capacity);
        const demand = data.demand;
        if (demand) {
            const measure = root.querySelector('[data-chart-demand-measure]')?.value || 'enrollments';
            const context = date(demand.from) + ' a ' + date(demand.to) + (measure === 'clicks' ? '. Cliques anônimos, sem filtro de público ou status; não são visitantes únicos.' : '. Inscrições recebidas no intervalo, com status atual; não são pessoas únicas.');
            for (const [kind, title] of [['locations','Locais'], ['modalities','Modalidades'], ['classes','Turmas'], ['periods','Períodos']]) add(r, 'demand-' + kind, title + ' mais procurados', measure === 'clicks' && !demand.clicks_available ? [] : demand[measure][kind], 'count', context, { unit: measure === 'clicks' ? 'cliques' : 'inscrições' });
            if (!root.hasAttribute('data-professor-charts')) for (const kind of ['locations','modalities']) add(r, 'navigation-' + kind, 'Cliques diretos em ' + (kind === 'locations' ? 'locais' : 'modalidades'), demand.clicks_available ? demand.navigation[kind] : [], 'count', 'Cliques anteriores à escolha da turma, filtrados somente pelo intervalo de procura.', { unit: 'cliques' });
            if (!demand.clicks_available) r.unavailable.push('Contagem de cliques ainda indisponível');
        }
        if (data.movements?.available) {
            const m = data.movements;
            add(r, 'movements', 'Movimentações de inscrições', Object.entries({ 'Inscrições copiadas': m.totals.copias, 'Inscrições substituídas': m.totals.substituicoes, 'Destinos excluídos': m.totals.excluidas, 'Operações de envio': m.totals.operacoes }).map(([label,count]) => ({label,count})), 'count', 'Segue o intervalo de procura e os filtros do envio. Indicadores podem se sobrepor, e uma operação pode incluir várias inscrições.');
            add(r, 'movement-modalities', 'Inscrições enviadas por modalidade de destino', m.modalities, 'count', 'Cópias e substituições no intervalo de procura.', { unit: 'inscrições' });
        }
        enrollmentTrend(r, data.enrollment_trend, root);
        attendance(r, data.attendance);
        return r;
    }
    function enrollmentTrend(r, trend, root) {
        if (!trend) return;
        const grouping = root.querySelector('[data-enrollment-trend-group]')?.value || 'month';
        const modality = root.querySelector('[data-enrollment-trend-modality]')?.value;
        const selectedClass = root.querySelector('[data-enrollment-trend-class]')?.value;
        const entries = trend.modalities.filter(row => !modality || String(row.id) === modality).flatMap(row => row.classes).filter(row => !selectedClass || String(row.id) === selectedClass);
        const buckets = new Map();
        for (let day = new Date(Date.UTC(trend.year, 0, 1)); day.getUTCFullYear() === Number(trend.year); day.setUTCDate(day.getUTCDate() + 1)) {
            const iso = day.toISOString().slice(0, 10);
            let key = grouping === 'month' ? iso.slice(0,7) : iso;
            if (grouping === 'week') { const monday = new Date(day); monday.setUTCDate(monday.getUTCDate() - (monday.getUTCDay() + 6) % 7); key = monday.toISOString().slice(0,10); }
            buckets.set(key, (buckets.get(key) || 0) + entries.reduce((sum, row) => sum + Number(row.days[iso] || 0), 0));
        }
        add(r, 'enrollment-trend', 'Evolução das inscrições recebidas · ' + trend.year, [...buckets].map(([label,count]) => ({label,count})), 'count', 'Pela data de recebimento, com status atual. Agrupamento por ' + ({month:'mês',week:'semana iniciada na segunda-feira',day:'dia'}[grouping]) + '. Nas bordas, somente dias do ano selecionado. Inclui períodos com zero inscrições.', { unit: 'inscrições' });
    }
    function attendance(r, data) {
        if (!data?.available) { r.unavailable.push(data?.message || 'Frequência em turmas indisponível'); return; }
        const context = date(data.from) + ' a ' + date(data.to) + '. Registros por aluno e aula, sem futuros e sem filtro de status atual da inscrição.';
        add(r, 'attendance-status', 'Presenças, faltas e justificativas', ['present','absent','justified'].map(key => ({ label: labels[key], count: data.totals[key] })), 'count', context);
        for (const [kind, title] of [['periods','período'], ['locations','local'], ['modalities','modalidade'], ['classes','turma'], ['weekdays','dia da semana'], ['dates','data da aula']]) {
            for (const metric of ['present','absent','justified','attendance']) add(r, 'attendance-' + kind + '-' + metric, labels[metric] + ' por ' + title, data.groups[kind], metric, context + ' Percentual de presença = presentes ÷ (presentes + ausentes + justificados).');
        }
        add(r, 'monitor-states', 'Monitor das chamadas · situação das aulas', Object.entries(data.states).map(([key,count]) => ({ label: states[key] || key, count })), 'count', date(data.monitor_from) + ' a ' + date(data.monitor_to) + '. Aulas previstas com as matrículas e o calendário atuais; cobertura histórica estimada.');
        add(r, 'monitor-records', 'Monitor das chamadas · registros', ['present','absent','justified','pending'].map(key => ({ label: labels[key], count: data.monitor_totals[key] })), 'count', 'Aulas iniciadas no intervalo do monitor, independente do intervalo estatístico de frequência.');
    }
    function agenda(data, root) {
        const context = date(data.filters.from) + ' a ' + date(data.filters.to) + ' · ' + (data.filters.type === 'special' ? 'Horários especiais' : 'Horários semanais') + ' · ' + filterText(root, '[data-agenda-form]');
        const r = report('Agenda', data.generated_at, context), t = data.totals;
        r.cards = [['Sessões', t.sessions], ['Vagas oferecidas', t.offered], ['Reservas válidas', t.reserved], ['Vagas disponíveis', t.available], ['Presenças', data.attendance_available ? t.present : null]];
        const notes = 'Oferta estimada pela grade e capacidade atuais, sem reconstituição completa das mudanças. Vagas são vagas-sessão, sem contagem de pessoas únicas. ';
        r.unavailable = data.attendance_available ? [] : ['Horários especiais sem chamada equivalente. Presenças, faltas e comparecimento indisponíveis'];
        const metric = root.querySelector('[data-agenda-measure]')?.value || 'reserved';
        const unavailable = !data.attendance_available && ['present','absent','justified','attendance'].includes(metric);
        for (const [kind, title] of [['modality','modalidade'], ['local','local'], ['space','espaço'], ['time','horário inicial']]) {
            add(r, 'ranking-' + kind, (labels[metric] || metric) + ' por ' + title, data.groups[kind].map(row => ({ ...row, [metric]: unavailable ? null : row[metric] })), metric, notes + 'Ranking completo nos filtros selecionados. Taxas são calculadas pela razão dos totais.');
        }
        for (const key of ['offered','reserved','available','occupancy', ...(data.attendance_available ? ['present','absent','justified','attendance'] : [])]) {
            add(r, 'period-' + key, 'Períodos · ' + labels[key], data.groups.period.map(row => ({ ...row, label: periods[row.name] || row.name })), key, notes);
            add(r, 'public-' + key, 'Públicos · ' + labels[key], data.groups.public.map(row => ({ ...row, label: row.name.toUpperCase() })), key, notes);
            add(r, 'date-' + key, 'Evolução no intervalo · ' + labels[key], data.groups.date, key, 'Por data da sessão. ' + notes);
        }
        const statuses = data.attendance_available ? ['present','absent','justified','pending','future','cancelled','other'] : ['unassessed','future','cancelled','other'];
        add(r, 'agenda-status', 'Situação dos agendamentos', statuses.map(key => ({ label: labels[key], count: t[key] })), 'count', 'Cada registro aparece em uma categoria. Sem chamada não significa falta.');
        add(r, 'heatmap', 'Ocupação por dia e período', data.heatmap.map(row => ({ ...row, label: weekdays[row.weekday - 1] + ' · ' + periods[row.period] })), 'occupancy', 'Reservas ÷ vagas oferecidas. Ausência de oferta aparece como “—”, sem converter para zero.');
        if (data.comparison) {
            for (const key of ['offered','reserved','available', ...(data.attendance_available ? ['present','absent','justified'] : [])]) add(r, 'comparison-' + key, 'Comparação com intervalo anterior · ' + labels[key], [{ label: 'Anterior · ' + date(data.comparison.from) + ' a ' + date(data.comparison.to), [key]: data.comparison.totals[key] }, { label: 'Atual · ' + date(data.filters.from) + ' a ' + date(data.filters.to), [key]: t[key] }], key, 'Intervalos de mesma duração. Os dias da semana podem ter distribuições diferentes. ' + notes);
        }
        return r;
    }
    function update(root, data, domain) {
        const deck = root.querySelector('[data-indicator-slides]');
        if (!deck || !App.peopleCharts) return;
        App.peopleCharts.initialize(deck);
        deck.dispatchEvent(new CustomEvent('indicator:report', { detail: domain === 'agenda' ? agenda(data, root) : courses(data, root) }));
        root._indicatorReport = { data, domain };
        if (!root.dataset.indicatorBound) {
            root.dataset.indicatorBound = '1';
            root.addEventListener('change', event => {
                if (!event.target.closest('.indicator-advanced') || !root._indicatorReport) return;
                const {data, domain} = root._indicatorReport;
                update(root, data, domain);
            });
        }
    }
    App.indicatorSlides = { update, courses, agenda, loading(root) { const deck = root.querySelector('[data-indicator-slides]'); if (deck) deck.hidden = true; } };
})(window);
