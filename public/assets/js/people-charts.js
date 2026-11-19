(function (window, document) {
    'use strict';
    const App = window.App = window.App || {};
    const number = value => Number(value || 0).toLocaleString('pt-BR');
    const percent = (value, base) => base > 0
        ? (100 * value / base).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'
        : '—';

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function initialize(scope) {
        const roots = scope.matches && scope.matches('[data-people-charts]')
            ? [scope] : scope.querySelectorAll('[data-people-charts]');
        roots.forEach(root => {
            if (root.dataset.initialized) return;
            root.dataset.initialized = '1';
            if (App.chartThemes) App.chartThemes.initialize(root);
            const form = root.querySelector('[data-people-chart-form]');
            const stage = root.querySelector('[data-people-slide-stage]');
            const thumbnails = root.querySelector('[data-people-slide-thumbnails]');
            const loading = root.querySelector('[data-people-chart-loading]');
            const error = root.querySelector('[data-people-chart-error]');
            const results = root.querySelector('[data-people-chart-results]');
            const presentation = root.querySelector('[data-people-presentation]');
            let slides = [];
            let selected = 0;
            let request = null;
            let requestVersion = 0;
            let report = null;
            const chartTypes = new Map();
            const chartTypeFor = slide => chartTypes.get(slide?.key) || (slide?.rate ? 'bar' : 'pie');
            const palette = ['#168b80', '#d69738', '#467ac4', '#b35e91', '#718c39', '#9c6bd0', '#db7154', '#328da6', '#a59055', '#687c92'];
            function drawChart(chart, slide, type) {
                chart.replaceChildren();
                const valueLabel = row => row.count == null ? '—' : number(row.count) + (slide.rate ? '%' : '');
                if (slide.rows.length && slide.rows.every(row => row.count == null)) {
                    chart.append(element('p', 'people-chart-empty', 'Valores indisponíveis para este indicador. Consulte as observações e a tabela.'));
                    return;
                }
                if (!slide.rows.length) {
                    chart.append(element('p', 'people-chart-empty', 'Sem registros para os filtros selecionados.'));
                    return;
                }
                const svgNode = (name, attrs) => {
                    const node = document.createElementNS('http://www.w3.org/2000/svg', name);
                    Object.entries(attrs || {}).forEach(([key, value]) => node.setAttribute(key, value));
                    return node;
                };
                if (type !== 'bar') {
                    const svg = svgNode('svg', { viewBox: '0 0 500 280', role: 'img', 'aria-label': slide.title });
                    const total = slide.rows.reduce((sum, row) => sum + Number(row.count), 0);
                    if (type === 'pie' && total > 0) {
                        let angle = -Math.PI / 2;
                        slide.rows.forEach((row, index) => {
                            const delta = Number(row.count) / total * Math.PI * 2;
                            if (!delta) return;
                            const end = angle + delta;
                            const shape = delta >= Math.PI * 2 - .00001
                                ? svgNode('circle', { cx: 250, cy: 140, r: 120, fill: palette[index % palette.length] })
                                : svgNode('path', { d: `M250 140 L${250 + 120 * Math.cos(angle)} ${140 + 120 * Math.sin(angle)} A120 120 0 ${delta > Math.PI ? 1 : 0} 1 ${250 + 120 * Math.cos(end)} ${140 + 120 * Math.sin(end)} Z`, fill: palette[index % palette.length] });
                            const title = svgNode('title'); title.textContent = row.label + ': ' + number(row.count) + ' (' + percent(row.count, total) + ')';
                            shape.append(title); svg.append(shape); angle = end;
                        });
                    } else if (type === 'line') {
                        svg.append(svgNode('path', { d: 'M35 15 V245 H480', fill: 'none', stroke: 'currentColor' }));
                        const points = slide.rows.map((row, index) => [35 + index * 445 / Math.max(1, slide.rows.length - 1), 245 - (slide.maximum ? row.count / slide.maximum * 220 : 0)]);
                        let segment = [];
                        const flush = () => { if (segment.length) svg.append(svgNode('polyline', { points: segment.join(' '), fill: 'none', stroke: 'var(--people-accent)', 'stroke-width': 3 })); segment = []; };
                        points.forEach((point, index) => { if (slide.rows[index].count == null) flush(); else segment.push(point.join(',')); });
                        flush();
                        points.forEach(([x, y], index) => {
                            const tick = svgNode('text', { x, y: 265, 'text-anchor': 'middle', fill: 'currentColor', 'font-size': 12 }); tick.textContent = index + 1; svg.append(tick);
                            if (slide.rows[index].count == null) return;
                            const dot = svgNode('circle', { cx: x, cy: y, r: 5, fill: palette[index % palette.length] });
                            const title = svgNode('title'); title.textContent = slide.rows[index].label + ': ' + valueLabel(slide.rows[index]); dot.append(title); svg.append(dot);
                            const value = svgNode('text', { x, y: Math.max(12, y - 10), 'text-anchor': 'middle', fill: 'currentColor', 'font-size': 12 }); value.textContent = valueLabel(slide.rows[index]); svg.append(value);
                        });
                    }
                    chart.append(svg);
                    if (type === 'pie' && !total) chart.append(element('p', 'people-chart-empty', 'Todas as categorias deste slide têm valor zero.'));
                    const legend = element('div', 'people-chart-legend');
                    slide.rows.forEach((row, index) => {
                        const item = element('div'); const swatch = element('i'); swatch.style.backgroundColor = palette[index % palette.length];
                        item.append(swatch, element('span', '', (type === 'line' ? (index + 1) + '. ' : '') + row.label + ' · ' + valueLabel(row))); legend.append(item);
                    });
                    chart.append(legend);
                    if (type === 'pie') chart.append(element('small', '', 'As fatias representam a soma das categorias deste slide. Categorias podem se sobrepor.'));
                    return;
                }
                slide.rows.forEach(row => {
                    const line = element('div', 'people-chart-bar'); const label = element('div', 'people-chart-bar-label');
                    label.append(element('span', '', row.label), element('strong', '', valueLabel(row)));
                    const track = element('div', 'people-chart-bar-track'); const fill = element('span', 'people-chart-bar-fill');
                    fill.style.width = (slide.maximum ? 100 * row.count / slide.maximum : 0) + '%'; track.append(fill); line.append(label, track); chart.append(line);
                });
            }

            function selectSlide(index, moveThumbnail = false) {
                selected = Math.min(Math.max(0, index), slides.length - 1);
                stage.querySelectorAll('.people-slide').forEach((slide, position) => {
                    slide.hidden = position !== selected;
                    slide.classList.toggle('is-active', position === selected);
                });
                thumbnails.querySelectorAll('button').forEach((button, position) => {
                    button.setAttribute('aria-current', position === selected ? 'true' : 'false');
                    if (position === selected && moveThumbnail) button.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                });
                root.querySelector('[data-people-slide-position]').textContent = slides.length ? (selected + 1) + ' de ' + slides.length : 'Sem slides';
                root.querySelector('[data-people-slide-prev]').disabled = selected <= 0;
                root.querySelector('[data-people-slide-next]').disabled = selected >= slides.length - 1;
                root.querySelector('[data-people-chart-type]').value = chartTypeFor(slides[selected]);
                root.querySelector('[data-people-chart-type] option[value="pie"]').disabled = !!slides[selected]?.rate;
            }

            function updateThumbnail(index) {
                const svg = stage.children[index]?.querySelector('.people-slide-chart svg');
                const mini = thumbnails.children[index]?.querySelector('.people-thumbnail-chart');
                if (svg && mini) {
                    const clone = svg.cloneNode(true);
                    clone.removeAttribute('role');
                    clone.removeAttribute('aria-label');
                    mini.replaceChildren(clone);
                }
            }

            function renderSlide(slide, index) {
                const article = element('article', 'people-slide');
                article.dataset.slideId = slide.key;
                article.setAttribute('aria-label', slide.title);
                article.append(root.querySelector('[data-people-print-header]').content.cloneNode(true));
                const heading = element('header', 'people-slide-heading');
                heading.append(element('span', 'people-slide-category', (report.domain || 'Pessoas') + ' · ' + report.reference_date));
                heading.append(element('h3', '', slide.title));
                heading.append(element('p', '', slide.description));
                article.append(heading);
                const content = element('div', 'people-slide-content');
                const chart = element('figure', 'people-slide-chart');
                chart.setAttribute('aria-label', 'Gráfico de ' + slide.title);
                const tableWrap = element('div', 'people-slide-table');
                const table = element('table', 'people-data-table');
                const caption = element('caption', 'sr-only', slide.title);
                const head = element('thead');
                const headRow = element('tr');
                ['Indicador', slide.value_label || (slide.rate ? 'Percentual' : 'Quantidade'), slide.rate ? 'Unidade' : '% da base'].forEach(label => {
                    const th = element('th', '', label);
                    th.scope = 'col';
                    headRow.append(th);
                });
                head.append(headRow);
                const body = element('tbody');
                if (!slide.rows.length) {
                    chart.append(element('p', 'people-chart-empty', report.summary.people
                        ? 'Sem registros para este indicador.' : 'Nenhuma pessoa encontrada para os filtros selecionados.'));
                    const tr = element('tr');
                    const td = element('td', '', 'Sem registros');
                    td.colSpan = 3;
                    tr.append(td);
                    body.append(tr);
                }
                slide.rows.forEach(row => {
                    const tr = element('tr');
                    const th = element('th', '', row.label);
                    th.scope = 'row';
                    tr.append(th, element('td', '', row.count == null ? '—' : number(row.count)), element('td', '', slide.rate ? (row.count == null ? '—' : '%') : (slide.hide_share || row.count == null ? '—' : percent(row.count, slide.base))));
                    body.append(tr);
                });
                table.append(caption, head, body);
                drawChart(chart, slide, chartTypeFor(slide));
                tableWrap.append(table);
                content.append(chart, tableWrap);
                article.append(content);
                const footer = element('footer', 'people-slide-footer');
                footer.append(element('span', '', slide.rate ? 'Unidade · percentual; taxas calculadas pelos totais de cada categoria' : 'Base · ' + number(slide.base) + ' ' + slide.unit));
                footer.append(element('span', '', report.filters_text));
                footer.append(element('span', '', 'Slide ' + (index + 1) + ' de ' + slides.length));
                article.append(footer, root.querySelector('[data-people-print-footer]').content.cloneNode(true));
                stage.append(article);

                const button = element('button', 'people-slide-thumbnail');
                button.type = 'button';
                button.setAttribute('aria-label', 'Abrir slide ' + (index + 1) + ' — ' + slide.title);
                const mini = element('span', 'people-thumbnail-chart');
                mini.setAttribute('aria-hidden', 'true');
                slide.rows.slice(0, 5).forEach(row => {
                    const bar = element('i');
                    bar.style.width = (slide.maximum > 0 ? Math.max(4, 100 * row.count / slide.maximum) : 4) + '%';
                    mini.append(bar);
                });
                button.append(mini, element('span', 'people-thumbnail-title', slide.title));
                button.addEventListener('click', () => selectSlide(index, true));
                thumbnails.append(button);
                updateThumbnail(index);
            }

            function render(data) {
                const previousKey = slides[selected] && slides[selected].key;
                report = data;
                slides = [];
                data.slides.forEach(slide => {
                    const pages = Math.max(1, Math.ceil(slide.rows.length / 10));
                    const maximum = Math.max(0, ...slide.rows.map(row => row.count));
                    for (let page = 0; page < pages; page++) {
                        slides.push(Object.assign({}, slide, {
                            key: slide.id + '-' + page,
                            title: slide.title + (pages > 1 ? ' · ' + (page + 1) + '/' + pages : ''),
                            rows: slide.rows.slice(page * 10, page * 10 + 10),
                            maximum
                        }));
                    }
                });
                stage.replaceChildren();
                thumbnails.replaceChildren();
                slides.forEach(renderSlide);
                const stats = root.querySelector('[data-people-chart-stats]');
                stats.replaceChildren();
                (data.cards || [
                    ['Pessoas cadastradas', data.summary.people],
                    ['Cadastros completos', data.summary.complete],
                    ['Com conta própria', data.summary.accounts],
                    ['Com matrícula ativa', data.summary.active],
                    ['Com histórico de atividades', data.summary.participants]
                ]).forEach(([label, value]) => {
                    const card = element('div', 'people-chart-stat');
                    card.append(element('span', '', label), element('strong', '', value == null ? '—' : number(value)));
                    stats.append(card);
                });
                root.querySelector('[data-people-chart-summary]').textContent = data.filters_text + ' · Atualizado em ' + data.generated_at;
                root.querySelector('[data-people-chart-date]').textContent = data.reference_date;
                const unavailable = root.querySelector('[data-people-chart-unavailable]');
                unavailable.hidden = !data.unavailable.length;
                unavailable.textContent = data.unavailable.length ? (data.domain ? 'Observações: ' : 'Fontes indisponíveis nesta instalação: ') + data.unavailable.join(' · ') + '.' : '';
                results.hidden = false;
                selectSlide(Math.max(0, slides.findIndex(slide => slide.key === previousKey)));
            }

            async function load() {
                if (!form.reportValidity()) return;
                const version = ++requestVersion;
                if (request) request.abort();
                request = new AbortController();
                loading.hidden = false;
                loading.textContent = 'Atualizando indicadores…';
                error.hidden = true;
                root.querySelectorAll('[data-people-print]').forEach(button => { button.disabled = true; });
                try {
                    const url = new URL(root.dataset.url, window.location.href);
                    new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: request.signal
                    });
                    let payload;
                    try { payload = await response.json(); } catch (parseError) {
                        throw new Error('Não foi possível consultar os indicadores agora. Tente novamente.');
                    }
                    if (!response.ok || !payload.success) throw new Error(payload.message || 'Não foi possível consultar os indicadores.');
                    if (version === requestVersion && root.isConnected) render(payload.data);
                } catch (problem) {
                    if (problem.name === 'AbortError' || version !== requestVersion || !root.isConnected) return;
                    error.textContent = problem.message;
                    error.hidden = false;
                } finally {
                    if (version === requestVersion) {
                        loading.hidden = true;
                        root.querySelectorAll('[data-people-print]').forEach(button => { button.disabled = !report; });
                    }
                }
            }

            form?.addEventListener('submit', event => { event.preventDefault(); load(); });
            form?.addEventListener('reset', () => window.setTimeout(load, 0));
            root.addEventListener('indicator:report', event => {
                render(event.detail);
                loading.hidden = true;
                root.hidden = false;
            });
            root.querySelector('[data-people-slide-prev]').addEventListener('click', () => selectSlide(selected - 1, true));
            root.querySelector('[data-people-slide-next]').addEventListener('click', () => selectSlide(selected + 1, true));
            root.querySelector('[data-people-chart-view]').addEventListener('change', event => {
                presentation.dataset.view = event.target.value;
            });
            root.querySelector('[data-people-chart-type]').addEventListener('change', event => {
                const slide = slides[selected];
                if (!slide) return;
                chartTypes.set(slide.key, event.target.value);
                drawChart(stage.querySelector('.people-slide.is-active .people-slide-chart'), slide, event.target.value);
                if (event.target.value === 'bar') {
                    const mini = thumbnails.children[selected].querySelector('.people-thumbnail-chart');
                    mini.replaceChildren();
                    slide.rows.slice(0, 5).forEach(row => {
                        const bar = element('i');
                        bar.style.width = (slide.maximum > 0 ? Math.max(4, 100 * row.count / slide.maximum) : 4) + '%';
                        mini.append(bar);
                    });
                } else updateThumbnail(selected);
            });
            presentation.addEventListener('keydown', event => {
                if (event.target.closest('input,select,textarea,button')) return;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    selectSlide(selected + (event.key === 'ArrowRight' ? 1 : -1), true);
                }
                if (event.key === 'Home') { event.preventDefault(); selectSlide(0, true); }
                if (event.key === 'End') { event.preventDefault(); selectSlide(slides.length - 1, true); }
            });
            root.querySelector('[data-people-fullscreen]').addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement) await document.exitFullscreen();
                    else await presentation.requestFullscreen();
                } catch (problem) {
                    error.textContent = 'Não foi possível abrir a apresentação em tela cheia neste navegador.';
                    error.hidden = false;
                }
            });
            root.querySelectorAll('[data-people-print]').forEach(button => button.addEventListener('click', async () => {
                root.dataset.printMode = button.dataset.peoplePrint;
                document.body.classList.add('printing-people-charts');
                const images = Array.from(stage.querySelectorAll('img'));
                await Promise.all(images.map(img => img.complete ? Promise.resolve() : new Promise(resolve => {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                })));
                window.print();
            }));
            const resetPrint = () => {
                document.body.classList.remove('printing-people-charts');
                delete root.dataset.printMode;
            };
            window.addEventListener('afterprint', resetPrint);
            window.addEventListener('beforeprint', () => {
                if (!root.isConnected || root.hidden || results.hidden) return;
                document.body.classList.add('printing-people-charts');
                if (!root.dataset.printMode) root.dataset.printMode = 'current';
            });
            const preview = root.querySelector('[data-people-chart-preview]');
            if (preview) { loading.hidden = true; render(JSON.parse(preview.textContent)); }
            else if (!root.hasAttribute('data-people-manual')) load();
        });
    }

    App.peopleCharts = { initialize };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initialize(document));
    else initialize(document);
})(window, document);
