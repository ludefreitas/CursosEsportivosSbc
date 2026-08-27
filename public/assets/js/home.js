(function (window, $) {
    const App = window.App || {};

    App.home = Object.assign(App.home || {}, {
        initScrollAnimations: function () {
            const elements = document.querySelectorAll('.animate-on-scroll');
            if (!elements.length) {
                return;
            }

            if (!('IntersectionObserver' in window)) {
                elements.forEach(function (element) {
                    element.classList.add('animated');
                });
                return;
            }

            const observer = new IntersectionObserver(function (entries, currentObserver) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('animated');
                    currentObserver.unobserve(entry.target);
                });
            }, {
                threshold: 0.18,
                rootMargin: '0px 0px -40px 0px'
            });

            elements.forEach(function (element) {
                observer.observe(element);
            });
        },

        iniciarLocaisSugeridos: function () {
            const $card = $('#home-locations-card');
            const $modal = $('#home-all-locations-modal');
            let locations = [];

            if ($card.length === 0) {
                return;
            }

            try {
                locations = JSON.parse(String($card.attr('data-locations') || '[]'));
            } catch (error) {
                locations = [];
            }

            function renderLocations(records) {
                const $list = $('#home-location-suggestions').empty();
                (Array.isArray(records) ? records : []).slice(0, 3).forEach(function (location) {
                    $list.append($('<article>', {
                        class: 'home-location-suggestion',
                        'data-location-id': String(location.id || '')
                    })
                        .append($('<strong>').text(String(location.apelido_local || location.nome_local || '')))
                        .append($('<small>').text('(' + String(location.nome_local || '') + ')')));
                });
            }

            function hasCoordinates(location) {
                return location.latitude !== null && location.latitude !== ''
                    && location.longitude !== null && location.longitude !== ''
                    && Number.isFinite(Number(location.latitude))
                    && Number.isFinite(Number(location.longitude));
            }

            function distance(latitude, longitude, location) {
                const radius = 6371;
                const toRadians = function (value) { return Number(value) * Math.PI / 180; };
                const deltaLatitude = toRadians(Number(location.latitude) - latitude);
                const deltaLongitude = toRadians(Number(location.longitude) - longitude);
                const value = Math.sin(deltaLatitude / 2) * Math.sin(deltaLatitude / 2)
                    + Math.cos(toRadians(latitude)) * Math.cos(toRadians(Number(location.latitude)))
                    * Math.sin(deltaLongitude / 2) * Math.sin(deltaLongitude / 2);
                return radius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
            }

            $(document).on('click', '#home-all-locations-open', function () {
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-home-all-locations-close="1"]', function () {
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '#home-all-locations-modal', function (event) {
                if (event.target === this) {
                    $modal.addClass('hidden').attr('aria-hidden', 'true');
                }
            });

            $(document).on('click', '[data-home-location-select]', function () {
                const selectedId = String($(this).attr('data-home-location-select') || '');
                const selected = locations.find(function (location) { return String(location.id || '') === selectedId; });
                if (selected) {
                    renderLocations([selected].concat(locations.filter(function (location) {
                        return String(location.id || '') !== selectedId;
                    })));
                }
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            });

            if (!navigator.geolocation || !document.body.classList.contains('pagina-home')) {
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                const latitude = Number(position.coords.latitude);
                const longitude = Number(position.coords.longitude);
                const nearby = locations.slice().sort(function (left, right) {
                    const leftDistance = hasCoordinates(left) ? distance(latitude, longitude, left) : Number.POSITIVE_INFINITY;
                    const rightDistance = hasCoordinates(right) ? distance(latitude, longitude, right) : Number.POSITIVE_INFINITY;
                    return leftDistance - rightDistance;
                });
                renderLocations(nearby);
            }, function () {
            }, {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000
            });
        },

        iniciarNavegacaoDaHome: function () {
            $(document).on('click', '[data-home-scroll-target]', function () {
                let target = document.getElementById(String($(this).attr('data-home-scroll-target') || ''));
                if (target && target.id === 'home-training-agenda') {
                    target = document.getElementById('home-training-locations');
                }
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            const $modalitiesModal = $('#home-course-modalities-modal');

            $(document).on('click', '[data-home-course-modalities-open="1"]', function () {
                $modalitiesModal.removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-home-course-modalities-close="1"]', function () {
                $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '#home-course-modalities-modal', function (event) {
                if (event.target === this) {
                    $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
                }
            });

        },

        iniciarFluxoDeCursosPorLocal: function () {
            const $locationsCard = $('#home-locations-card');
            const $locationsModal = $('#home-all-locations-modal');
            const $allModalitiesModal = $('#home-course-modalities-modal');
            const $modalitiesModal = $('#home-location-modalities-modal');
            const $modalityLocationsModal = $('#home-modality-locations-modal');
            const $classesModal = $('#home-location-classes-modal');
            let locations = [];
            let selectedLocation = null;
            let selectedModality = null;
            let flowOrigin = 'location';
            let classesById = {};

            if ($locationsCard.length === 0 || $modalitiesModal.length === 0 || $classesModal.length === 0) return;
            try { locations = JSON.parse(String($locationsCard.attr('data-locations') || '[]')); } catch (error) { locations = []; }

            function locationName(location) {
                return String((location && (location.apelido_local || location.nome_local)) || 'Local selecionado');
            }

            function closeFlow() {
                $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
                $modalityLocationsModal.addClass('hidden').attr('aria-hidden', 'true');
                $classesModal.addClass('hidden').attr('aria-hidden', 'true');
            }

            function showLoading($target, message) {
                $target.empty().append($('<div>', { class: 'home-course-flow-state' })
                    .append($('<span>', { class: 'home-course-flow-spinner', 'aria-hidden': 'true' }))
                    .append($('<p>', { text: message })));
            }

            function showError($target, message) {
                $target.empty().append($('<div>', { class: 'home-course-flow-state home-course-flow-error' })
                    .append($('<p>', { text: message || 'Não foi possível carregar as informações.' })));
            }

            function loadModalities(locationId) {
                flowOrigin = 'location';
                selectedLocation = locations.find(function (location) { return String(location.id || '') === String(locationId); }) || { id: locationId };
                selectedModality = null;
                $locationsModal.addClass('hidden').attr('aria-hidden', 'true');
                $classesModal.addClass('hidden').attr('aria-hidden', 'true');
                $('#home-location-modalities-subtitle').text('Escolha uma modalidade oferecida em ' + locationName(selectedLocation) + '.');
                $modalitiesModal.removeClass('hidden').attr('aria-hidden', 'false');
                showLoading($('#home-location-modalities-content'), 'Carregando modalidades...');

                $.ajax({
                    url: App.core.buildUrl('/cursos/modalidades-por-local'),
                    method: 'GET', dataType: 'json', data: { local_id: locationId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    const modalities = response && Array.isArray(response.modalities) ? response.modalities : [];
                    const $content = $('#home-location-modalities-content').empty();
                    if (modalities.length === 0) {
                        $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Não há modalidades com inscrições abertas neste local.' })));
                        return;
                    }
                    const $list = $('<div>', { class: 'home-location-modalities-list' });
                    modalities.forEach(function (modality) {
                        const $row = $('<div>', { class: 'home-location-modality-row' });
                        $row.append($('<strong>', { text: String(modality.nome || '') }));
                        $row.append($('<button>', {
                            type: 'button', class: 'btn home-location-modality-classes', text: 'Cursos disponíveis',
                            'data-home-location-modality': String(modality.id || ''),
                            'data-home-location-modality-name': String(modality.nome || '')
                        }));
                        $list.append($row);
                    });
                    $content.append($list);
                }).fail(function (xhr) {
                    showError($('#home-location-modalities-content'), App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            }

            function loadLocations(modalityId, modalityName) {
                flowOrigin = 'modality';
                selectedLocation = null;
                selectedModality = { id: modalityId, nome: modalityName };
                $allModalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
                $classesModal.addClass('hidden').attr('aria-hidden', 'true');
                $('#home-modality-locations-subtitle').text('Escolha o centro esportivo que oferece ' + modalityName + '.');
                $modalityLocationsModal.removeClass('hidden').attr('aria-hidden', 'false');
                showLoading($('#home-modality-locations-content'), 'Carregando centros esportivos...');

                $.ajax({
                    url: App.core.buildUrl('/cursos/locais-por-modalidade'),
                    method: 'GET', dataType: 'json', data: { modalidade_id: modalityId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    const records = response && Array.isArray(response.locations) ? response.locations : [];
                    const $content = $('#home-modality-locations-content').empty();
                    if (records.length === 0) {
                        $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Não há centros esportivos com turmas abertas para esta modalidade.' })));
                        return;
                    }
                    const $list = $('<div>', { class: 'home-modality-locations-list' });
                    records.forEach(function (location) {
                        $list.append($('<button>', {
                            type: 'button', class: 'home-all-location-button',
                            'data-home-modality-location': String(location.id || ''),
                            'data-home-modality-location-name': String(location.apelido_local || location.nome_local || ''),
                            'data-home-modality-location-full-name': String(location.nome_local || '')
                        }).append($('<strong>', { text: String(location.apelido_local || location.nome_local || '') }))
                            .append($('<small>', { text: String(location.nome_local || '') })));
                    });
                    $content.append($list);
                }).fail(function (xhr) {
                    showError($('#home-modality-locations-content'), App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            }

            function classDetails(classId, callback) {
                $.ajax({ url: App.core.buildUrl('/cursos/turma-detalhes'), method: 'GET', dataType: 'json', data: { turma_id: classId }, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) { if (response && response.details) callback(response.details); else App.core.abrirPopup('erro', 'Não foi possível carregar os detalhes da turma.'); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            }

            function classAgeCriterionText(courseClass) {
                const mode = String(courseClass.criterio_faixa_etaria || 'idade_exata');
                if (mode === 'ano_nascimento') {
                    const range = String(courseClass.faixa_etaria_descricao || 'Nascidos em período não informado')
                        .replace(/^Nascidos/i, 'pessoas nascidas');
                    return 'Para ' + range;
                }
                return 'Para pessoas com idade entre '
                    + String(courseClass.idade_minima || 0) + ' e '
                    + String(courseClass.idade_maxima || 0);
            }

            function renderEnrollmentModal(details) {
                const courseClass = Object.assign({}, details.class || {}, classesById[String(details.class.id)] || {});
                const people = Array.isArray(details.people) ? details.people : [];
                const $content = $('#home-course-enrollment-content').empty();
                $('#home-course-enrollment-subtitle').text('Confira os dados e selecione a pessoa que será inscrita.');
                const $summary = $('<div>', { class: 'home-course-detail-summary' });
                const seasonYear = String(courseClass.data_inicio || courseClass.temporada_inicio || '').slice(0, 4) || String(new Date().getFullYear());
                const modalityName = String(courseClass.modalidade_nome || selectedModality.nome || 'Modalidade');
                $summary.append($('<h4>', { class: 'home-course-detail-main-title', text: modalityName + ' - ' + seasonYear }));
                $summary.append($('<p>', { class: 'home-course-detail-class-name' }).append($('<strong>', { text: '[' + String(courseClass.id || '') + '] - ' + String(courseClass.nome || '') })));
                $summary.append($('<p>').append($('<strong>', { text: 'Local da aula: ' })).append(document.createTextNode(String(courseClass.local_nome || ''))));
                if (courseClass.dias_semana && courseClass.hora_inicio && courseClass.hora_fim) {
                    $summary.append($('<p>', { class: 'home-course-detail-schedule' }).append($('<strong>', { text: 'Dias e horário: ' })).append(document.createTextNode(String(courseClass.dias_semana_descricao || courseClass.dias_semana) + ', das ' + String(courseClass.hora_inicio).slice(0, 5) + ' às ' + String(courseClass.hora_fim).slice(0, 5))));
                    if (courseClass.periodo_dia) {
                        $summary.append($('<p>', { class: 'home-course-detail-period' }).append($('<strong>', { text: 'Período: ' })).append(document.createTextNode(String(courseClass.periodo_dia))));
                    }
                }
                $summary.append($('<p>').append($('<strong>', { text: classAgeCriterionText(courseClass) })));
                if (courseClass.sexo) {
                    $summary.append($('<p>').append($('<strong>', { text: 'Sexo permitido: ' })).append(document.createTextNode(String(courseClass.sexo) === 'feminino' ? 'Feminino' : 'Masculino')));
                }
                $content.append($summary);
                if (people.length === 0) {
                    $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Faça login para selecionar você ou uma pessoa vinculada à sua conta.' })));
                } else {
                    const $form = $('<form>', { class: 'stack-form home-course-enrollment-form', method: 'POST', action: App.core.buildUrl('/cursos/inscrever'), 'data-manual-submit': '1' });
                    $form.append($('<input>', { type: 'hidden', name: 'turma_id', value: String(courseClass.id || '') }));
                    $form.append($('<p>', { class: 'home-course-person-instruction', text: 'Selecione abaixo a pessoa para inscrever' }));
                    const $personOptions = $('<div>', { class: 'home-course-person-options' });
                    people.forEach(function (person) {
                        const blocked = !person.elegivel;
                        const $card = $('<label>', { class: 'home-course-person-card' + (blocked ? ' is-disabled' : '') });
                        const $line = $('<span>', { class: 'home-course-person-line' });
                        $line.append($('<input>', { type: 'radio', name: 'pessoa_id', value: String(person.id || ''), required: true, disabled: blocked, 'data-home-course-person-choice': '1', 'data-public': String(person.publico_alvo || 'geral') }));
                        $line.append($('<span>', { class: 'home-course-person-main', text: String(person.nome_completo || '') }));
                        $card.append($line);
                        if (blocked) $card.append($('<small>', { class: 'home-course-person-reason', text: String(person.motivo_bloqueio || 'Pessoa não elegível para esta turma.') }));
                        $personOptions.append($card);
                    });
                    $form.append($personOptions);
                    const $public = $('<select>', { id: 'home-course-person-public', disabled: true })
                        .append($('<option>', { value: 'geral', text: 'Público geral' })).append($('<option>', { value: 'pcd', text: 'PCD' })).append($('<option>', { value: 'plm', text: 'PLM' })).append($('<option>', { value: 'pvs', text: 'PVS' }));
                    $form.append($('<input>', { type: 'hidden', name: 'publico_alvo', id: 'home-course-person-public-value', value: 'geral' }));
                    $form.append($('<label>').append($('<span>', { text: 'Público-alvo da vaga' })).append($public));
                    $form.append($('<label>', { class: 'checkbox-chip' }).append($('<input>', { type: 'checkbox', name: 'aceite_termos', value: '1', required: true })).append($('<span>', { text: 'Aceito os termos da inscrição' })));
                    $form.append($('<button>', { type: 'submit', class: 'btn btn-primary', text: 'Confirmar inscrição' }));
                    $content.append($form);
                }
                $('#home-course-enrollment-modal').removeClass('hidden').attr('aria-hidden', 'false');
            }

            function renderVacanciesModal(details) {
                const record = details.class;
                const $grid = $('<div>', { class: 'home-course-vacancies-grid' });
                [['Público geral', 'vagas_geral_disponiveis', 'espera_geral_disponivel'], ['PCD', 'vagas_pcd_disponiveis', 'espera_pcd_disponivel'], ['PLM', 'vagas_plm_disponiveis', 'espera_plm_disponivel'], ['PVS', 'vagas_pvs_disponiveis', 'espera_pvs_disponivel']].forEach(function (item) {
                    $grid.append($('<article>').append($('<strong>', { text: item[0] })).append($('<span>', { text: String(record[item[1]] || 0) + ' vagas' })).append($('<small>', { text: String(record[item[2]] || 0) + ' lugares na espera' })));
                });
                $('#home-course-vacancies-subtitle').text(String((classesById[String(record.id)] || record).nome || ''));
                $('#home-course-vacancies-content').empty().append($grid);
                $('#home-course-vacancies-modal').removeClass('hidden').attr('aria-hidden', 'false');
            }

            function renderClasses(classes) {
                const $content = $('#home-location-classes-content').empty();
                classesById = {};
                if (classes.length === 0) {
                    $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Não há turmas abertas para esta modalidade neste local.' })));
                    return;
                }
                classes.forEach(function (courseClass) {
                    classesById[String(courseClass.id)] = courseClass;
                    const $card = $('<article>', { class: 'home-course-class-card' });
                    const seasonYear = String(courseClass.data_inicio || courseClass.temporada_inicio || '').slice(0, 4) || String(new Date().getFullYear());
                    $card.append($('<h4>', { text: String(courseClass.modalidade_nome || selectedModality.nome || 'Modalidade') + ' - ' + seasonYear }));
                    $card.append($('<p>', { class: 'home-course-class-name' }).append($('<strong>', { text: '[' + String(courseClass.id || '') + '] - ' + String(courseClass.nome || '') })));
                    $card.append($('<p>').append($('<strong>', { text: 'Local da aula: ' })).append(document.createTextNode(String(courseClass.local_nome || ''))));
                    if (courseClass.dias_semana && courseClass.hora_inicio && courseClass.hora_fim) {
                        $card.append($('<p>').append($('<strong>', { text: 'Dias e horário: ' })).append(document.createTextNode(String(courseClass.dias_semana_descricao || courseClass.dias_semana) + ', das ' + String(courseClass.hora_inicio).slice(0, 5) + ' às ' + String(courseClass.hora_fim).slice(0, 5))));
                    }
                    if (courseClass.periodo_dia) $card.append($('<p>').append($('<strong>', { text: 'Período: ' })).append(document.createTextNode(String(courseClass.periodo_dia))));
                    $card.append($('<p>').append($('<strong>', { text: classAgeCriterionText(courseClass) })));
                    const $actions = $('<div>', { class: 'home-course-class-actions' });
                    $actions.append($('<button>', { type: 'button', class: 'btn btn-primary', text: 'Inscrever-se', 'data-home-course-enroll': String(courseClass.id || '') }));
                    $actions.append($('<button>', { type: 'button', class: 'btn btn-secondary', text: 'Vagas', 'data-home-course-vacancies': String(courseClass.id || '') }));
                    if (Number(courseClass.permitir_inscricao_por_cpf || 0) === 1) $actions.append($('<button>', { type: 'button', class: 'btn btn-secondary', text: 'Inscrição por CPF', 'data-home-course-cpf': String(courseClass.id || '') }));
                    $card.append($actions);
                    $content.append($card);
                });
            }

            function loadClasses() {
                if (!selectedLocation || !selectedModality) return;
                $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
                $modalityLocationsModal.addClass('hidden').attr('aria-hidden', 'true');
                $('[data-home-course-flow-back="1"]').text(flowOrigin === 'modality' ? 'Voltar aos centros esportivos' : 'Voltar às modalidades');
                $('#home-location-classes-subtitle').text(selectedModality.nome + ' em ' + locationName(selectedLocation) + '.');
                $classesModal.removeClass('hidden').attr('aria-hidden', 'false');
                showLoading($('#home-location-classes-content'), 'Carregando turmas...');
                $.ajax({
                    url: App.core.buildUrl('/cursos/turmas-por-local'), method: 'GET', dataType: 'json',
                    data: { local_id: selectedLocation.id, modalidade_id: selectedModality.id },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    renderClasses(response && Array.isArray(response.classes) ? response.classes : []);
                }).fail(function (xhr) { showError($('#home-location-classes-content'), App.core.extrairMensagemErroAjax(xhr).mensagem); });
            }

            $(document).on('click', '.home-location-suggestion[data-location-id]', function () { loadModalities($(this).attr('data-location-id')); });
            $(document).on('click', '[data-home-location-select]', function () { loadModalities($(this).attr('data-home-location-select')); });
            $(document).on('click', '[data-home-location-modality]', function () {
                selectedModality = { id: $(this).attr('data-home-location-modality'), nome: $(this).attr('data-home-location-modality-name') };
                loadClasses();
            });
            $(document).on('click', '[data-home-course-modality-select]', function () {
                $('[data-home-course-modality-select]').removeClass('is-selected');
                $(this).addClass('is-selected');
                loadLocations(String($(this).attr('data-home-course-modality-select') || ''), String($(this).text() || '').trim());
            });
            $(document).on('click', '[data-home-modality-location]', function () {
                selectedLocation = {
                    id: String($(this).attr('data-home-modality-location') || ''),
                    apelido_local: String($(this).attr('data-home-modality-location-name') || ''),
                    nome_local: String($(this).attr('data-home-modality-location-full-name') || '')
                };
                loadClasses();
            });
            $(document).on('click', '[data-home-course-flow-back="1"]', function () {
                $classesModal.addClass('hidden').attr('aria-hidden', 'true');
                (flowOrigin === 'modality' ? $modalityLocationsModal : $modalitiesModal).removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-home-course-flow-close="1"]', closeFlow);
            $(document).on('click', '#home-location-modalities-modal, #home-modality-locations-modal, #home-location-classes-modal', function (event) { if (event.target === this) closeFlow(); });
            $(document).on('click', '[data-home-course-enroll]', function () { classDetails(String($(this).attr('data-home-course-enroll') || ''), renderEnrollmentModal); });
            $(document).on('click', '[data-home-course-vacancies]', function () { classDetails(String($(this).attr('data-home-course-vacancies') || ''), renderVacanciesModal); });
            $(document).on('click', '[data-home-course-cpf]', function () {
                const classId = String($(this).attr('data-home-course-cpf') || '');
                const courseClass = classesById[classId] || {};
                const $form = $('<form>', { class: 'stack-form home-course-enrollment-form', method: 'POST', action: App.core.buildUrl('/cursos/inscrever'), 'data-manual-submit': '1' });
                $form.append($('<input>', { type: 'hidden', name: 'turma_id', value: classId }));
                $form.append($('<label>').append($('<span>', { text: 'CPF da pessoa' })).append($('<input>', { type: 'text', name: 'cpf', placeholder: '000.000.000-00', required: true })));
                $form.append($('<label>', { class: 'checkbox-chip' }).append($('<input>', { type: 'checkbox', name: 'aceite_termos', value: '1', required: true })).append($('<span>', { text: 'Aceito os termos da inscrição' })));
                $form.append($('<button>', { type: 'submit', class: 'btn btn-primary', text: 'Confirmar inscrição por CPF' }));
                $('#home-course-cpf-subtitle').text(String(courseClass.nome || ''));
                $('#home-course-cpf-content').empty().append($form);
                $('#home-course-cpf-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-home-course-detail-close="1"]', function () { $('#home-course-enrollment-modal, #home-course-cpf-modal, #home-course-vacancies-modal').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#home-course-enrollment-modal, #home-course-cpf-modal, #home-course-vacancies-modal', function (event) { if (event.target === this) $(this).addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('change', '[data-home-course-person-choice="1"]', function () {
                const publicValue = String($(this).attr('data-public') || 'geral');
                $('#home-course-person-public').val(publicValue);
                $('#home-course-person-public-value').val(publicValue);
            });
            $(document).on('submit', '.home-course-enrollment-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({ url: $form.attr('action'), method: 'POST', data: new FormData($form[0]), processData: false, contentType: false, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) {
                        if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível concluir a inscrição.')); return; }
                        $('#home-course-enrollment-modal, #home-course-cpf-modal').addClass('hidden').attr('aria-hidden', 'true');
                        App.core.abrirPopup('sucesso', String(response.message || 'Inscrição realizada com sucesso.'), loadClasses);
                    }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });
        },

        iniciarAgendaDeTreinos: function () {
            const calendarElement = document.getElementById('home-training-calendar');
            const $section = $('#home-training-agenda');
            const $calendarModal = $('#home-training-calendar-modal');
            const $locationsCard = $('#home-training-locations');
            const $dayModal = $('#home-training-day-modal');
            let locations = [];
            let selectedLocationId = 0;
            let selectedDate = '';
            let modalitiesRequest = null;

            if (!calendarElement || typeof FullCalendar === 'undefined' || $locationsCard.length === 0) {
                return;
            }

            try {
                locations = JSON.parse(String($locationsCard.attr('data-locations') || '[]'));
            } catch (error) {
                locations = [];
            }

            function renderTrainingLocations(records) {
                const $list = $('#home-training-location-suggestions').empty();
                (Array.isArray(records) ? records : []).slice(0, 3).forEach(function (location) {
                    $list.append($('<button>', {
                        type: 'button',
                        class: 'home-location-suggestion',
                        'data-home-training-location': String(location.id || '')
                    })
                        .append($('<strong>').text(String(location.apelido_local || location.nome_local || '')))
                        .append($('<small>').text('(' + String(location.nome_local || '') + ')')));
                });
            }

            function hasCoordinates(location) {
                return location.latitude !== null && location.latitude !== ''
                    && location.longitude !== null && location.longitude !== ''
                    && Number.isFinite(Number(location.latitude))
                    && Number.isFinite(Number(location.longitude));
            }

            function distance(latitude, longitude, location) {
                const radius = 6371;
                const toRadians = function (value) { return Number(value) * Math.PI / 180; };
                const deltaLatitude = toRadians(Number(location.latitude) - latitude);
                const deltaLongitude = toRadians(Number(location.longitude) - longitude);
                const value = Math.sin(deltaLatitude / 2) * Math.sin(deltaLatitude / 2)
                    + Math.cos(toRadians(latitude)) * Math.cos(toRadians(Number(location.latitude)))
                    * Math.sin(deltaLongitude / 2) * Math.sin(deltaLongitude / 2);
                return radius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
            }

            function dateKey(value) {
                const date = value instanceof Date ? value : new Date(value);
                if (Number.isNaN(date.getTime())) return '';
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            }

            function loadLocationModalities(locationId) {
                const $select = $('#home-training-modality');

                if (modalitiesRequest && typeof modalitiesRequest.abort === 'function') {
                    modalitiesRequest.abort();
                }

                $select
                    .prop('disabled', true)
                    .empty()
                    .append($('<option>', { value: '0', text: 'Carregando modalidades...' }));

                modalitiesRequest = $.getJSON(App.core.buildUrl('/api/agenda/modalidades-por-local'), {
                    local_treino_id: locationId
                }).done(function (response) {
                    const modalities = response && Array.isArray(response.modalities) ? response.modalities : [];

                    if (!response || response.success === false) {
                        $select.empty().append($('<option>', { value: '0', text: 'Todas as modalidades' }));
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar as modalidades deste local.'));
                        return;
                    }

                    $select.empty().append($('<option>', { value: '0', text: 'Todas as modalidades' }));
                    modalities.forEach(function (modality) {
                        $select.append($('<option>', {
                            value: String(modality.id || ''),
                            text: String(modality.nome || '')
                        }));
                    });
                    $select.val('0');
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        $select.empty().append($('<option>', { value: '0', text: 'Todas as modalidades' }));
                        const error = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', error.mensagem);
                    }
                }).always(function (_response, status) {
                    if (status !== 'abort') {
                        $select.prop('disabled', false);
                        modalitiesRequest = null;
                    }
                });
            }

            function calendarAspectRatio() {
                if (window.innerHeight <= 600) {
                    if (window.innerWidth <= 560) return 1.65;
                    if (window.innerWidth <= 820) return 2.2;
                    return 2.9;
                }
                if (window.innerWidth <= 560) return 1.35;
                if (window.innerWidth <= 820) return 1.8;
                if (window.innerHeight <= 800) return 2.65;
                return 2.4;
            }

            function statusFromEvent(event) {
                const classes = Array.isArray(event.classNames) ? event.classNames : [];
                const statuses = ['agendado', 'presente', 'falta', 'justificado', 'cancelado', 'misto'];
                return statuses.find(function (status) {
                    return classes.indexOf('agenda-booking-status-' + status) >= 0;
                }) || '';
            }

            function paintMonthDays(events) {
                const byDate = {};
                const todayKey = dateKey(new Date());
                (events || []).forEach(function (event) {
                    const key = dateKey(event.start);
                    if (!key) return;
                    const props = event.extendedProps || {};
                    const status = statusFromEvent(event);
                    const personalBookings = Array.isArray(props.meus_agendamentos) ? props.meus_agendamentos : [];
                    if (key < todayKey && !status && personalBookings.length === 0) return;
                    byDate[key] = byDate[key] || { available: false, hasOpenSchedule: false, statuses: [] };
                    byDate[key].available = true;
                    if (props.agenda_aberta === true || String(props.agenda_aberta) === '1') {
                        byDate[key].hasOpenSchedule = true;
                    }
                    if (status && byDate[key].statuses.indexOf(status) < 0) {
                        byDate[key].statuses.push(status);
                    }
                });

                $(calendarElement).find('.fc-daygrid-day')
                    .removeClass('home-training-day-state is-available is-agendado is-presente is-falta is-justificado is-cancelado is-misto');
                Object.keys(byDate).forEach(function (key) {
                    const $cell = $(calendarElement).find('.fc-daygrid-day[data-date="' + key + '"]');
                    if ($cell.length === 0) return;
                    let state = byDate[key].available ? (byDate[key].hasOpenSchedule ? 'available' : 'not-open') : '';
                    if (byDate[key].statuses.length === 1) state = byDate[key].statuses[0];
                    if (byDate[key].statuses.length > 1) state = 'misto';
                    if (state) $cell.addClass('home-training-day-state is-' + state);
                });
            }

            function renderDayList(events) {
                const isPastDate = selectedDate !== '' && selectedDate < dateKey(new Date());
                const records = (events || []).filter(function (event) {
                    const props = event.extendedProps || {};
                    const hasPersonalBooking = statusFromEvent(event) !== ''
                        || (Array.isArray(props.meus_agendamentos) && props.meus_agendamentos.length > 0);
                    return !isPastDate || hasPersonalBooking;
                }).sort(function (left, right) { return left.start - right.start; });
                const $list = $('#home-training-day-list').empty();

                if (records.length === 0) {
                    $list.append($('<p>', { class: 'muted', text: 'Nenhum horário encontrado para este dia.' }));
                    return;
                }

                const viewableRecords = records.filter(function (event) {
                    const props = event.extendedProps || {};
                    const hasPersonalBooking = statusFromEvent(event) !== ''
                        || (Array.isArray(props.meus_agendamentos) && props.meus_agendamentos.length > 0);
                    return props.agenda_aberta === true
                        || String(props.agenda_aberta) === '1'
                        || (isPastDate && hasPersonalBooking);
                });
                if (!isPastDate && viewableRecords.length === 0) {
                    const allClosed = records.every(function (event) {
                        return String((event.extendedProps || {}).estado_janela_agendamento || '') === 'fechada';
                    });
                    $list.append($('<p>', {
                        class: 'alert-inline home-training-day-not-open',
                        text: allClosed
                            ? 'A agenda para o dia selecionado já foi fechada.'
                            : 'A agenda para o dia selecionado ainda não foi aberta.'
                    }));
                    return;
                }

                records.forEach(function (event) {
                    const props = event.extendedProps || {};
                    const status = statusFromEvent(event);
                    const personalBookings = Array.isArray(props.meus_agendamentos) ? props.meus_agendamentos : [];
                    const hasPersonalBooking = status !== '' || personalBookings.length > 0;
                    const isAgendaOpen = props.agenda_aberta === true || String(props.agenda_aberta) === '1';
                    if (!isAgendaOpen && !(isPastDate && hasPersonalBooking)) {
                        const isClosed = String(props.estado_janela_agendamento || '') === 'fechada';
                        const timeRange = App.agenda.formatarHoraAgenda(event.start) + ' às ' + App.agenda.formatarHoraAgenda(event.end);
                        const modalityName = String(props.modalidade || event.title || 'Modalidade');
                        $list.append($('<article>', { class: 'home-training-day-item is-not-open' })
                            .append($('<p>', {
                                text: isClosed
                                    ? 'A agenda para o horário das ' + timeRange + ' — ' + modalityName + ' já foi fechada.'
                                    : 'A agenda para o horário das ' + timeRange + ' — ' + modalityName + ' ainda não foi aberta.'
                            })));
                        return;
                    }
                    const $card = $('<button>', {
                        type: 'button',
                        class: 'home-training-day-item home-training-day-item-button' + (status ? ' is-' + status : ''),
                        'aria-label': 'Ver detalhes e opções de agendamento de ' + String(props.modalidade || event.title || 'horário')
                    });
                    $card.append($('<div>', { class: 'home-training-day-time', text: App.agenda.formatarHoraAgenda(event.start) + ' às ' + App.agenda.formatarHoraAgenda(event.end) }));
                    $card.append($('<strong>', { text: String(props.modalidade || event.title || 'Horário') }));
                    $card.append($('<span>', { text: String(props.espaco || 'Espaço a definir') + ' — ' + String(props.tipo_horario || 'treino') }));
                    if (personalBookings.length > 0) {
                        $card.append($('<small>', {
                            class: 'home-training-day-people',
                            text: personalBookings.map(function (booking) {
                                return String(booking.nome_completo || 'Pessoa') + ': ' + String(booking.status_label || booking.status || 'Agendado');
                            }).join(' • ')
                        }));
                    }
                    if (status) {
                        $card.append($('<small>', { class: 'home-training-day-status is-' + status, text: String(props.meu_status_agendamento_label || status) }));
                    } else if (props.disponivel_agendamento === true || String(props.disponivel_agendamento) === '1') {
                        $card.append($('<small>', { class: 'home-training-day-status is-available', text: String(props.vagas_disponiveis || 0) + ' vaga(s) disponível(is)' }));
                    } else {
                        $card.append($('<small>', { class: 'home-training-day-status', text: 'Disponível apenas para consulta' }));
                    }
                    $card.on('click', function () { abrirDetalhesAgenda(event); });
                    $list.append($card);
                });
            }

            function abrirDetalhesAgenda(event) {
                if (!event || !App.agenda || typeof App.agenda.renderizarDetalhesAgenda !== 'function') return;
                if ($('#agenda-details-modal').length === 0) {
                    App.core.abrirPopup('erro', 'Não foi possível carregar os detalhes deste horário.');
                    return;
                }
                $dayModal.addClass('hidden').attr('aria-hidden', 'true');
                $('#agenda-details-modal-back-actions').removeClass('hidden');
                App.agenda.renderizarDetalhesAgenda({ event: event });
            }

            function loadDay() {
                if (!selectedLocationId || !selectedDate) return;
                const end = new Date(selectedDate + 'T12:00:00');
                end.setDate(end.getDate() + 1);
                $.getJSON(App.core.buildUrl('/api/agenda/eventos'), {
                    local_treino_id: selectedLocationId,
                    modalidade_id: Number($('#home-training-modality').val() || 0),
                    start: selectedDate + 'T00:00:00',
                    end: dateKey(end) + 'T00:00:00'
                }).done(function (records) {
                    const eventObjects = (Array.isArray(records) ? records : []).map(function (record) {
                        return {
                            id: String(record.id || ''),
                            title: record.title,
                            start: new Date(record.start),
                            end: new Date(record.end),
                            startStr: String(record.start || ''),
                            endStr: String(record.end || ''),
                            classNames: record.classNames || [],
                            extendedProps: record.extendedProps || {}
                        };
                    });
                    renderDayList(eventObjects);
                }).fail(function () {
                    $('#home-training-day-list').html('<p class="muted">Não foi possível carregar os horários deste dia.</p>');
                });
            }

            const calendar = new FullCalendar.Calendar(calendarElement, {
                locale: 'pt-br',
                initialView: 'dayGridMonth',
                height: 'auto',
                aspectRatio: calendarAspectRatio(),
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                windowResize: function () {
                    calendar.setOption('aspectRatio', calendarAspectRatio());
                },
                events: {
                    url: App.core.buildUrl('/api/agenda/eventos'),
                    extraParams: function () {
                        return {
                            local_treino_id: selectedLocationId,
                            modalidade_id: Number($('#home-training-modality').val() || 0)
                        };
                    }
                },
                eventContent: function () { return { html: '' }; },
                eventDidMount: function (info) { info.el.classList.add('home-training-hidden-event'); },
                eventsSet: function (events) { window.setTimeout(function () { paintMonthDays(events); }, 0); },
                dateClick: function (info) {
                    if (info.dateStr < dateKey(new Date()) && !$(info.dayEl).hasClass('home-training-day-state')) return;
                    selectedDate = info.dateStr;
                    $('#home-training-day-title').text('Horários de ' + App.agenda.formatarDataCompletaAgenda(info.date));
                    const location = locations.find(function (item) { return Number(item.id || 0) === selectedLocationId; });
                    $('#home-training-day-subtitle').text(String((location && (location.apelido_local || location.nome_local)) || 'Local selecionado'));
                    $('#home-training-day-list').html('<p class="muted">Carregando horários...</p>');
                    $dayModal.removeClass('hidden').attr('aria-hidden', 'false');
                    loadDay();
                }
            });
            calendar.render();

            $(document).on('click', '#home-all-training-locations-open', function () {
                $('#home-all-training-locations-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-home-all-training-locations-close="1"]', function () {
                $('#home-all-training-locations-modal').addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '#home-all-training-locations-modal', function (event) {
                if (event.target === this) $(this).addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '[data-home-training-location]', function () {
                selectedLocationId = Number($(this).attr('data-home-training-location') || 0);
                if (!selectedLocationId) return;
                $('#home-all-training-locations-modal').addClass('hidden').attr('aria-hidden', 'true');
                const location = locations.find(function (item) { return Number(item.id || 0) === selectedLocationId; });
                $('#home-training-calendar-location').text(location ? '— ' + String(location.apelido_local || location.nome_local || '') : '');
                loadLocationModalities(selectedLocationId);
                $calendarModal.removeClass('hidden').attr('aria-hidden', 'false');
                calendar.refetchEvents();
                window.setTimeout(function () {
                    calendar.updateSize();
                }, 50);
            });

            $(document).on('change', '#home-training-modality', function () { calendar.refetchEvents(); });
            $(document).on('click', '[data-home-training-day-close="1"]', function () { $dayModal.addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#agenda-details-modal-back', function () {
                if (App.agenda && typeof App.agenda.fecharModalDetalhesHorario === 'function') {
                    App.agenda.fecharModalDetalhesHorario();
                }
                $dayModal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '#home-training-day-modal', function (event) {
                if (event.target === this) $dayModal.addClass('hidden').attr('aria-hidden', 'true');
            });
            $(document).on('click', '[data-home-training-calendar-close="1"]', function () {
                $calendarModal.addClass('hidden').attr('aria-hidden', 'true');
            });
            $(document).on('click', '#home-training-calendar-modal', function (event) {
                if (event.target === this) $calendarModal.addClass('hidden').attr('aria-hidden', 'true');
            });

            const $trainingDescription = $('#home-training-description');
            const $trainingDescriptionParagraph = $trainingDescription.find('p');
            const $trainingDescriptionToggle = $('#home-training-description-toggle');
            window.setTimeout(function () {
                if ($trainingDescriptionParagraph.length === 0) return;
                const lineHeight = parseFloat(window.getComputedStyle($trainingDescriptionParagraph[0]).lineHeight || '0');
                if (lineHeight > 0 && $trainingDescriptionParagraph[0].scrollHeight > (lineHeight * 3) + 2) {
                    $trainingDescription.addClass('is-collapsed');
                    $trainingDescriptionToggle.removeClass('hidden');
                }
            }, 0);
            $trainingDescriptionToggle.on('click', function () {
                const expanded = $trainingDescription.hasClass('is-expanded');
                $trainingDescription.toggleClass('is-expanded', !expanded).toggleClass('is-collapsed', expanded);
                $(this).attr('aria-expanded', expanded ? 'false' : 'true').text(expanded ? 'Ler mais' : 'Ler menos');
            });

            if (navigator.geolocation && document.body.classList.contains('pagina-home')) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const latitude = Number(position.coords.latitude);
                    const longitude = Number(position.coords.longitude);
                    const nearby = locations.slice().sort(function (left, right) {
                        const leftDistance = hasCoordinates(left) ? distance(latitude, longitude, left) : Number.POSITIVE_INFINITY;
                        const rightDistance = hasCoordinates(right) ? distance(latitude, longitude, right) : Number.POSITIVE_INFINITY;
                        return leftDistance - rightDistance;
                    });
                    renderTrainingLocations(nearby);
                }, function () {
                }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
            }
        },

        init: function () {
            App.home.initScrollAnimations();
            App.home.iniciarLocaisSugeridos();
            App.home.iniciarNavegacaoDaHome();
            App.home.iniciarFluxoDeCursosPorLocal();
            App.home.iniciarAgendaDeTreinos();
        }
    });

    window.App = App;
}(window, window.jQuery));
