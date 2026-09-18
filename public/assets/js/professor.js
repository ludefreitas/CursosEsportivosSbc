(function (window, $) {
    const App = window.App || {};

    App.professor = Object.assign(App.professor || {}, {
        init: function () {
            const $host = $('[data-professor-mode="1"]');

            if ($host.length === 0) {
                return;
            }

            $('body').addClass('professor-page');
            $host.find('.admin-section-panel').addClass('professor-view');
            $host.attr('data-professor-ready', '1');

            function hydrateCourseControls($controlsSource) {
                if (!$controlsSource.length) {
                    return;
                }
                const schedules = String($controlsSource.find('[data-course-modality-schedules]').attr('data-course-modality-schedules') || '[]');
                const $freshControls = $controlsSource.find('#course-class-modal, #course-professor-modal, #course-class-status-modal').detach();
                $('#course-class-modal, #course-professor-modal, #course-class-status-modal').remove();
                $freshControls.each(function () {
                    $(this).attr('data-course-modality-schedules', schedules).appendTo('body');
                });
                $('#course-class-modal [data-course-form="class"]').attr('action', '/professor/minhas-turmas/salvar');
                $('#course-professor-modal [data-course-professor-form="1"]').attr('action', '/professor/minhas-turmas/equipe');
                $('#course-class-status-modal [data-course-class-status-form="1"]').attr('action', '/professor/minhas-turmas/status');
                $controlsSource.remove();
                $host.find('[data-course-create="class"]').prop('disabled', false);
            }

            hydrateCourseControls($('[data-professor-course-controls-source="1"]'));

            const $controlsLoader = $host.find('[data-professor-course-controls-loader="1"]');
            if ($controlsLoader.length) {
                $.ajax({
                    url: String($controlsLoader.attr('data-controls-url') || ''),
                    method: 'GET',
                    dataType: 'json',
                    suppressGlobalLoading: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        return;
                    }
                    const $source = $('<div>', { class: 'hidden', 'data-professor-course-controls-source': '1' }).html(String(response.html));
                    $controlsLoader.replaceWith($source);
                    hydrateCourseControls($source);
                }).fail(function (xhr) {
                    if (App.auth && App.auth.tratarFalhaDeAcesso(xhr, function () { App.admin.activateSection('minhas-turmas'); }, '/professor')) return;
                    $host.find('[data-course-create="class"]').prop('disabled', false);
                    $controlsLoader.remove();
                });
            }

            function browserRequest($browser, data, done, failed) {
                const $results = $browser.find('[data-professor-class-results]');
                $browser.find('[data-professor-class-loading="1"]').removeClass('hidden');
                $.ajax({
                    url: String($browser.attr('data-browser-url') || ''), method: 'GET', dataType: 'json', data: data,
                    suppressGlobalLoading: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar as turmas.'));
                        return;
                    }
                    done(response);
                }).fail(function (xhr) {
                    if (App.auth && App.auth.tratarFalhaDeAcesso(xhr, function () { browserRequest($browser, data, done); }, '/professor')) return;
                    if (typeof failed === 'function') { failed(xhr); return; }
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $browser.find('[data-professor-class-loading="1"]').addClass('hidden');
                });
            }

            function resetClassBrowser($browser) {
                browserRequest($browser, {}, function (response) {
                    renderFilterStep($browser, 'season', response.items, 'data-professor-class-season');
                    $browser.removeAttr('data-selected-season data-selected-location');
                    $browser.find('[data-professor-class-step="location"], [data-professor-class-step="modality"], [data-professor-class-result-count], [data-professor-class-results]').addClass('hidden');
                    $browser.find('[data-professor-class-results]').empty();
                });
            }

            function renderFilterStep($browser, step, items, attribute) {
                const $step = $browser.find('[data-professor-class-step="' + step + '"]');
                const $buttons = $step.find('.professor-class-filter-buttons').empty();
                (items || []).forEach(function (item) {
                    $buttons.append($('<button>', { type: 'button', class: 'btn btn-secondary', text: String(item.nome || '') }).attr(attribute, String(item.id || '')));
                });
                $step.toggleClass('hidden', $buttons.children().length === 0);
            }

            $(document).off('.professorClassBrowser')
                .on('click.professorClassBrowser', '[data-professor-class-season]', function () {
                    const $button = $(this); const $browser = $button.closest('[data-professor-class-browser]');
                    const seasonId = String($button.attr('data-professor-class-season') || '');
                    $browser.attr('data-selected-season', seasonId).removeAttr('data-selected-location');
                    $button.addClass('is-active').siblings().removeClass('is-active');
                    $browser.find('[data-professor-class-step="location"], [data-professor-class-step="modality"], [data-professor-class-result-count], [data-professor-class-results]').addClass('hidden');
                    browserRequest($browser, { temporada_id: seasonId }, function (response) {
                        renderFilterStep($browser, 'location', response.items, 'data-professor-class-location');
                        $browser.find('[data-professor-class-results]').addClass('hidden').empty();
                    });
                })
                .on('click.professorClassBrowser', '[data-professor-class-location]', function () {
                    const $button = $(this); const $browser = $button.closest('[data-professor-class-browser]');
                    const locationId = String($button.attr('data-professor-class-location') || '');
                    $browser.attr('data-selected-location', locationId);
                    $button.addClass('is-active').siblings().removeClass('is-active');
                    $browser.find('[data-professor-class-step="modality"], [data-professor-class-result-count], [data-professor-class-results]').addClass('hidden');
                    browserRequest($browser, { temporada_id: $browser.attr('data-selected-season'), local_treino_id: locationId }, function (response) {
                        renderFilterStep($browser, 'modality', response.items, 'data-professor-class-modality');
                        $browser.find('[data-professor-class-results]').addClass('hidden').empty();
                    });
                })
                .on('click.professorClassBrowser', '[data-professor-class-modality]', function () {
                    const $button = $(this); const $browser = $button.closest('[data-professor-class-browser]');
                    $button.addClass('is-active').siblings().removeClass('is-active');
                    browserRequest($browser, { temporada_id: $browser.attr('data-selected-season'), local_treino_id: $browser.attr('data-selected-location'), modalidade_id: $button.attr('data-professor-class-modality') }, function (response) {
                        const $results = $browser.find('[data-professor-class-results]').removeClass('hidden').html(String(response.html || ''));
                        const count = $results.find('[data-course-class-card]').length;
                        $browser.find('[data-professor-class-result-count]').removeClass('hidden').text(count + (count === 1 ? ' turma encontrada.' : ' turmas encontradas.'));
                    });
                })
                .on('professor:classes-refresh.professorClassBrowser', function (event, response) {
                    const $browser = $('[data-professor-class-browser]').first();
                    const removeId = String((response && response.remove_class_id) || '');
                    if (removeId && removeId !== '0') {
                        $browser.find('[data-course-class-card="' + removeId + '"]').remove();
                        const count = $browser.find('[data-course-class-card]').length;
                        $browser.find('[data-professor-class-result-count]').text(count + (count === 1 ? ' turma encontrada.' : ' turmas encontradas.'));
                        if (count === 0) { resetClassBrowser($browser); }
                        return;
                    }
                    const $active = $browser.find('[data-professor-class-modality].is-active').first();
                    if ($active.length) {
                        browserRequest($browser, { temporada_id: $browser.attr('data-selected-season'), local_treino_id: $browser.attr('data-selected-location'), modalidade_id: $active.attr('data-professor-class-modality') }, function (result) {
                            const $results = $browser.find('[data-professor-class-results]').removeClass('hidden').html(String(result.html || ''));
                            const count = $results.find('[data-course-class-card]').length;
                            $browser.find('[data-professor-class-result-count]').removeClass('hidden').text(count + (count === 1 ? ' turma encontrada.' : ' turmas encontradas.'));
                        }, function () { resetClassBrowser($browser); });
                    }
                });
        }
    });
})(window, window.jQuery);
