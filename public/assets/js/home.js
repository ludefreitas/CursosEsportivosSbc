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
            let modalityNoticeContinuation = null;
            let modalityNoticeSourceModal = null;

            if ($locationsCard.length === 0 || $modalitiesModal.length === 0 || $classesModal.length === 0) return;
            try { locations = JSON.parse(String($locationsCard.attr('data-locations') || '[]')); } catch (error) { locations = []; }

            function locationName(location) {
                return String((location && (location.apelido_local || location.nome_local)) || 'Local selecionado');
            }

            function suspendModalBehindNotice() {
                const selector = [
                    '#home-all-locations-modal',
                    '#home-course-modalities-modal',
                    '#home-location-modalities-modal',
                    '#home-modality-locations-modal',
                    '#home-location-classes-modal',
                    '#home-all-training-locations-modal',
                    '#home-training-modalities-modal',
                    '#home-training-calendar-modal',
                    '#home-training-day-modal'
                ].join(', ');
                modalityNoticeSourceModal = $(selector).filter(function () {
                    return !$(this).hasClass('hidden') && $(this).attr('aria-hidden') !== 'true';
                }).last();
                if (modalityNoticeSourceModal.length) {
                    modalityNoticeSourceModal.addClass('hidden').attr('aria-hidden', 'true');
                }
            }

            function closeModalityNotice(restoreSource) {
                $('#home-modality-notice-modal').addClass('hidden').attr('aria-hidden', 'true');
                if (restoreSource && modalityNoticeSourceModal && modalityNoticeSourceModal.length) {
                    modalityNoticeSourceModal.removeClass('hidden').attr('aria-hidden', 'false');
                }
                modalityNoticeSourceModal = null;
            }

            function openModalityNotice(modalityId, area, locationId, continuation) {
                if (typeof locationId === 'function') { continuation = locationId; locationId = 0; }
                $.getJSON(App.core.buildUrl('/api/modalidades/popup'), { modalidade_id: modalityId, area: area, local_treino_id: Number(locationId || 0) }).done(function (response) {
                    const popup = response && response.popup;
                    if (!popup) { continuation(); return; }
                    modalityNoticeContinuation = continuation;
                    $('#home-modality-notice-title').text(String(popup.titulo || 'Aviso da modalidade'));
                    $('#home-modality-notice-main').text(String(popup.texto_principal || ''));
                    $('#home-modality-notice-secondary').text(String(popup.texto_secundario || '')).toggleClass('hidden', !popup.texto_secundario);
                    const image = String(popup.imagem_url || '');
                    $('#home-modality-notice-media').toggleClass('hidden', !image);
                    $('#home-modality-notice-image').attr('src', image).attr('alt', String(popup.titulo || 'Aviso'));
                    const label=String(popup.rotulo_acao||''), actionUrl=String(popup.url_acao||'');
                    $('#home-modality-notice-action').toggleClass('hidden', !label || !actionUrl).text(label).attr('href', actionUrl || '#');
                    suspendModalBehindNotice();
                    $('#home-modality-notice-modal').removeClass('hidden').attr('aria-hidden','false');
                }).fail(function () { continuation(); });
            }
            function openLocationNotice(locationId, area, continuation) {
                $.getJSON(App.core.buildUrl('/api/locais/popup'), { local_treino_id: Number(locationId || 0), area: area }).done(function (response) {
                    const popup = response && response.popup;
                    if (!popup) { continuation(); return; }
                    modalityNoticeContinuation = continuation;
                    $('#home-modality-notice-title').text(String(popup.titulo || 'Aviso do local'));
                    $('#home-modality-notice-main').text(String(popup.texto_principal || ''));
                    $('#home-modality-notice-secondary').text(String(popup.texto_secundario || '')).toggleClass('hidden', !popup.texto_secundario);
                    const image=String(popup.imagem_url||''); $('#home-modality-notice-media').toggleClass('hidden',!image); $('#home-modality-notice-image').attr('src',image).attr('alt',String(popup.titulo||'Aviso do local'));
                    const label=String(popup.rotulo_acao||''), actionUrl=String(popup.url_acao||''); $('#home-modality-notice-action').toggleClass('hidden',!label||!actionUrl).text(label).attr('href',actionUrl||'#');
                    suspendModalBehindNotice();
                    $('#home-modality-notice-modal').removeClass('hidden').attr('aria-hidden','false');
                }).fail(continuation);
            }
            App.home.openModalityNotice = openModalityNotice;
            App.home.openLocationNotice = openLocationNotice;
            $(document).on('click', '#home-modality-notice-continue', function () {
                const continuation=modalityNoticeContinuation; modalityNoticeContinuation=null;
                closeModalityNotice(false); if(typeof continuation==='function') continuation();
            });
            $(document).on('click', '[data-home-modality-notice-close="1"]', function () { modalityNoticeContinuation=null; closeModalityNotice(true); });

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

            function appendClassAgeExceptions($container, courseClass) {
                const descriptions = Array.isArray(courseClass.excecoes_idade_descricao) ? courseClass.excecoes_idade_descricao : [];
                if (descriptions.length === 0) return;
                const $notice = $('<div>', { class: 'home-course-age-exception-notice' })
                    .append($('<strong>', { text: 'Exceções de idade com documentação válida:' }));
                const $list = $('<ul>');
                descriptions.forEach(function (description) { $list.append($('<li>', { text: String(description) })); });
                $container.append($notice.append($list));
            }

            function appendClassPublicNotices($container, courseClass) {
                const guidance = String(courseClass.orientacao_matricula || '').trim();
                const classesStart = String(courseClass.previsao_inicio_aulas || '').trim();
                const observation = String(courseClass.observacao || '').trim();
                if (guidance !== '' || classesStart !== '') {
                    const $scheduleNotice = $('<div>', { class: 'home-course-class-calendar-notice' });
                    if (guidance !== '') {
                        $scheduleNotice.append($('<p>').append($('<strong>', { text: 'Após concluir a inscrição: ' })).append(document.createTextNode('se houver vaga disponível, confirme a matrícula presencialmente. ' + guidance)));
                    }
                    if (classesStart !== '') {
                        $scheduleNotice.append($('<p>').append($('<strong>', { text: 'Previsão de início das aulas, após a confirmação da matrícula: ' })).append(document.createTextNode(classesStart + '.')));
                    }
                    $container.append($scheduleNotice);
                }
                if (observation !== '') {
                    $container.append($('<div>', { class: 'home-course-class-observation' })
                        .append($('<strong>', { text: 'Observação importante: ' }))
                        .append($('<span>', { text: observation })));
                }
            }

            function isMinorByBirthDate(value) {
                const parts = String(value || '').slice(0, 10).split('-');
                if (parts.length !== 3) return false;
                const birth = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) age -= 1;
                return age < 18;
            }

            function openEnrollmentTerms($form) {
                const $person = $form.find('[name="pessoa_id"]:checked');
                if (!$person.length) {
                    App.core.abrirPopup('erro', 'Selecione a pessoa que será inscrita antes de consultar os termos.');
                    return;
                }
                const personName = String($person.attr('data-person-name') || '').trim();
                const className = String($form.attr('data-class-name') || '').trim();
                const minor = isMinorByBirthDate($person.attr('data-birth-date'));
                let $modal = $('#home-course-enrollment-terms-modal');
                if (!$modal.length) {
                    $modal = $('<div>', { id: 'home-course-enrollment-terms-modal', class: 'popup-overlay hidden', 'aria-hidden': 'true' })
                        .append($('<div>', { class: 'popup-card home-course-flow-modal-card course-terms-modal-card', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'home-course-enrollment-terms-title' })
                            .append($('<div>', { class: 'popup-head' })
                                .append($('<h3>', { id: 'home-course-enrollment-terms-title', text: 'Termos da inscrição' }))
                                .append($('<button>', { type: 'button', class: 'popup-close-icon', 'data-enrollment-terms-close': '1', 'aria-label': 'Fechar termos', text: '×' })))
                            .append($('<div>', { class: 'popup-body course-terms-content' }))
                            .append($('<div>', { class: 'popup-actions' }).append($('<button>', { type: 'button', class: 'btn btn-secondary', 'data-enrollment-terms-close': '1', text: 'Fechar' }))));
                    $('body').append($modal);
                }
                const actionLabel = minor ? 'FINALIZAR' : 'CONFIRMAR INSCRIÇÃO';
                const $body = $modal.find('.course-terms-content').empty();
                $body.append($('<p>').append(document.createTextNode('Ao clicar no botão ')).append($('<strong>', { text: actionLabel })).append(document.createTextNode(', você está ciente de que:')));
                $body.append($('<ul>')
                    .append($('<li>').append(document.createTextNode('inscreve o(a) ')).append($('<strong>', { text: personName })).append(document.createTextNode(' na turma ')).append($('<strong>', { text: className })).append(document.createTextNode(';')))
                    .append($('<li>').append(document.createTextNode('a inscrição ')).append($('<strong>', { text: 'NÃO GARANTE' })).append(document.createTextNode(' vaga na respectiva turma;')))
                    .append($('<li>').append(document.createTextNode('deverá confirmar a matrícula ')).append($('<strong>', { text: 'PRESENCIALMENTE' })).append(document.createTextNode(', caso haja vaga disponível, no dia e horário da aula.'))));
                $body.append($('<p>', { text: 'Declara a precisão das informações prestadas neste site, assim como que ' + (minor ? 'o(a) menor se encontra' : 'se encontra') + ' APTO(A) À PRÁTICA DE ATIVIDADES FÍSICAS, isentando o professor e a Secretaria de Esportes e Lazer do Município de São Bernardo do Campo de qualquer responsabilidade.' }));
                $body.append($('<p>').append(document.createTextNode('Declara, ainda, estar ciente do art. 5º da Lei nº 10.848/2001, que trata da obrigatoriedade da apresentação de ')).append($('<strong>', { text: 'ATESTADO MÉDICO' })).append(document.createTextNode(', bem como autoriza a divulgação de eventuais imagens registradas em momentos de aula para arquivo e divulgação institucional.')));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function appendEnrollmentNoticeAcceptance($form, courseClass) {
                const season = String(courseClass.temporada_nome || String(courseClass.data_inicio || '').slice(0, 4) || new Date().getFullYear()).trim();
                const modality = String(courseClass.modalidade_nome || selectedModality.nome || 'modalidade selecionada').trim();
                const specific = courseClass.edital_especifico_modalidade === true || String(courseClass.edital_especifico_modalidade) === '1';
                const noticeLabel = String(courseClass.edital_rotulo || 'edital da temporada').trim();
                const noticeUrl = String(courseClass.edital_link || '').trim();
                const $text = $('<span>').append(document.createTextNode('Li e concordo com os termos do processo de inscrições para os cursos esportivos '));
                $text.append($('<strong>', { text: season + (specific ? ' para a modalidade ' + modality : '') }));
                $text.append(document.createTextNode(', conforme '));
                if (noticeUrl !== '') {
                    $text.append($('<a>', { href: noticeUrl, target: '_blank', rel: 'noopener noreferrer', text: noticeLabel }));
                } else {
                    $text.append($('<strong>', { text: noticeLabel }));
                }
                $text.append(document.createTextNode('.'));
                $form.append($('<label>', { class: 'checkbox-chip enrollment-notice-acceptance' })
                    .append($('<input>', { type: 'checkbox', name: 'aceite_edital', value: '1', required: true }))
                    .append($text));
            }

            function renderEnrollmentModal(details) {
                const courseClass = Object.assign({}, details.class || {}, classesById[String(details.class.id)] || {});
                const people = Array.isArray(details.people) ? details.people : [];
                const $content = $('#home-course-enrollment-content').empty();
                $('#home-course-enrollment-subtitle').text('Confira os dados, selecione a pessoa que deseja inscrever, aceite os termos e clique no botão “Confirmar inscrição”.');
                const $summary = $('<div>', { class: 'home-course-detail-summary' });
                const seasonYear = String(courseClass.data_inicio || courseClass.temporada_inicio || '').slice(0, 4) || String(new Date().getFullYear());
                const modalityName = String(courseClass.modalidade_nome || selectedModality.nome || 'Modalidade');
                $summary.append($('<h4>', { class: 'home-course-detail-main-title', text: modalityName + ' - ' + seasonYear }));
                $summary.append($('<p>', { class: 'home-course-detail-class-name' }).append($('<strong>', { text: '[' + String(courseClass.id || '') + '] - ' + String(courseClass.nome || '') })));
                $summary.append($('<p>').append($('<strong>', { text: 'Programa: ' })).append(document.createTextNode(String(courseClass.programa || 'Sem programa definido'))));
                $summary.append($('<p>').append($('<strong>', { text: 'Local da aula: ' })).append(document.createTextNode(String(courseClass.local_nome || ''))));
                if (courseClass.dias_semana && courseClass.hora_inicio && courseClass.hora_fim) {
                    $summary.append($('<p>', { class: 'home-course-detail-schedule' }).append($('<strong>', { text: 'Dias e horário: ' })).append(document.createTextNode(String(courseClass.dias_semana_descricao || courseClass.dias_semana) + ', das ' + String(courseClass.hora_inicio).slice(0, 5) + ' às ' + String(courseClass.hora_fim).slice(0, 5))));
                    if (courseClass.periodo_dia) {
                        $summary.append($('<p>', { class: 'home-course-detail-period' }).append($('<strong>', { text: 'Período: ' })).append(document.createTextNode(String(courseClass.periodo_dia))));
                    }
                }
                $summary.append($('<p>').append($('<strong>', { text: classAgeCriterionText(courseClass) })));
                appendClassAgeExceptions($summary, courseClass);
                $summary.append($('<p>').append($('<strong>', { text: 'Níveis aceitos: ' })).append(document.createTextNode(String(courseClass.niveis_aceitos_descricao || 'Sem limitação de nível'))));
                if (courseClass.sexo) {
                    $summary.append($('<p>').append($('<strong>', { text: 'Sexo permitido: ' })).append(document.createTextNode(String(courseClass.sexo) === 'feminino' ? 'Feminino' : 'Masculino')));
                }
                appendClassPublicNotices($summary, courseClass);
                $content.append($summary);
                if (people.length === 0) {
                    $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Faça login para selecionar você ou uma pessoa vinculada à sua conta.' })));
                } else {
                    const $form = $('<form>', { class: 'stack-form home-course-enrollment-form', method: 'POST', action: App.core.buildUrl('/cursos/inscrever'), 'data-manual-submit': '1', 'data-class-name': String(courseClass.nome || '') });
                    $form.append($('<input>', { type: 'hidden', name: 'turma_id', value: String(courseClass.id || '') }));
                    $form.append($('<p>', { class: 'home-course-person-instruction', text: 'Selecione abaixo a pessoa para inscrever' }));
                    const $personOptions = $('<div>', { class: 'home-course-person-options' });
                    people.forEach(function (person) {
                        const blocked = !person.elegivel;
                        const $card = $('<label>', { class: 'home-course-person-card' + (blocked ? ' is-disabled' : '') });
                        const $line = $('<span>', { class: 'home-course-person-line' });
                        $line.append($('<input>', { type: 'radio', name: 'pessoa_id', value: String(person.id || ''), required: true, disabled: blocked, 'data-home-course-person-choice': '1', 'data-public': String(person.publico_alvo || 'geral'), 'data-person-name': String(person.nome_completo || ''), 'data-birth-date': String(person.data_nascimento || '') }));
                        $line.append($('<span>', { class: 'home-course-person-main', text: String(person.nome_completo || '') }));
                        $card.append($line);
                        $card.append($('<small>', { class: 'muted', text: App.core.formatBirthDateWithAge(person.data_nascimento) }));
                        if (person.condicao_excecao_idade) {
                            const conditionLabels = { pcd: 'PCD (Pessoa Com Deficiência)', plm: 'PLM (Pessoa com Laudo Médico de Doença)', pvs: 'PVS (Pessoa em situação de Vulnerabilidade Social)' };
                            $card.append($('<small>', { class: 'home-course-person-exception', text: 'Esta inscrição será classificada como público geral e utilizará a exceção etária autorizada pela condição ' + String(conditionLabels[String(person.condicao_excecao_idade)] || String(person.condicao_excecao_idade).toUpperCase()) + '.' }));
                        }
                        if (blocked) $card.append($('<small>', { class: 'home-course-person-reason', text: String(person.motivo_bloqueio || 'Pessoa não elegível para esta turma.') }));
                        $personOptions.append($card);
                    });
                    $form.append($personOptions);
                    const $public = $('<select>', { id: 'home-course-person-public', disabled: true })
                        .append($('<option>', { value: 'geral', text: 'Público geral' })).append($('<option>', { value: 'pcd', text: 'PCD (Pessoa Com Deficiência)' })).append($('<option>', { value: 'plm', text: 'PLM (Pessoa com Laudo Médico de Doença)' })).append($('<option>', { value: 'pvs', text: 'PVS (Pessoa em situação de Vulnerabilidade Social)' }));
                    $form.append($('<input>', { type: 'hidden', name: 'publico_alvo', id: 'home-course-person-public-value', value: 'geral' }));
                    $form.append($('<label>').append($('<span>', { text: 'Público-alvo da vaga' })).append($public));
                    $form.append($('<label>', { class: 'checkbox-chip' }).append($('<input>', { type: 'checkbox', name: 'aceite_termos', value: '1', required: true })).append($('<span>').append(document.createTextNode('Li e aceito os termos da inscrição, disponíveis ')).append($('<button>', { type: 'button', class: 'link-button course-terms-link', 'data-enrollment-terms-open': '1', text: 'neste link' })).append(document.createTextNode('.'))));
                    appendEnrollmentNoticeAcceptance($form, courseClass);
                    $form.append($('<button>', { type: 'submit', class: 'btn btn-primary', text: 'Confirmar inscrição' }));
                    $content.append($form);
                }
                $('#home-course-enrollment-modal').removeClass('hidden').attr('aria-hidden', 'false');
            }

            function renderVacanciesModal(details) {
                const record = details.class;
                const $grid = $('<div>', { class: 'home-course-vacancies-grid' });
                const registrationsAreOpen = String(record.status || '') === 'inscricoes_abertas';
                [['Público geral', 'vagas_geral_disponiveis', 'espera_geral_disponivel'], ['PCD (Pessoa Com Deficiência)', 'vagas_pcd_disponiveis', 'espera_pcd_disponivel'], ['PLM (Pessoa com Laudo Médico de Doença)', 'vagas_plm_disponiveis', 'espera_plm_disponivel'], ['PVS (Pessoa em situação de Vulnerabilidade Social)', 'vagas_pvs_disponiveis', 'espera_pvs_disponivel']].forEach(function (item) {
                    const regularVacancies = Math.max(0, Number(record[item[1]] || 0));
                    const waitlistVacancies = Math.max(0, Number(record[item[2]] || 0));
                    const displayedVacancies = registrationsAreOpen
                        ? waitlistVacancies
                        : (regularVacancies > 0 ? regularVacancies : waitlistVacancies);
                    const vacanciesLabel = displayedVacancies <= 0
                        ? 'Não há vagas'
                        : String(displayedVacancies) + (displayedVacancies === 1 ? ' vaga' : ' vagas') + (registrationsAreOpen ? ' na lista de espera' : '');
                    $grid.append($('<article>').append($('<strong>', { text: item[0] })).append($('<span>', { text: vacanciesLabel })));
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
                    appendClassAgeExceptions($card, courseClass);
                    appendClassPublicNotices($card, courseClass);
                    const $actions = $('<div>', { class: 'home-course-class-actions' });
                    if (courseClass.permite_inscricao) $actions.append($('<button>', { type: 'button', class: 'btn btn-primary', text: 'Inscrever-se', 'data-home-course-enroll': String(courseClass.id || '') }));
                    $actions.append($('<button>', { type: 'button', class: 'btn btn-secondary', text: 'Vagas', 'data-home-course-vacancies': String(courseClass.id || '') }));
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

            $(document).on('click', '.home-location-suggestion[data-location-id]', function () { const id=$(this).attr('data-location-id'); openLocationNotice(id,'cursos',function(){loadModalities(id);}); });
            $(document).on('click', '[data-home-location-select]', function () { const id=$(this).attr('data-home-location-select'); openLocationNotice(id,'cursos',function(){loadModalities(id);}); });
            $(document).on('click', '[data-home-location-modality]', function () {
                const button=this; openModalityNotice($(button).attr('data-home-location-modality'), 'cursos', selectedLocation && selectedLocation.id, function () {
                    selectedModality = { id: $(button).attr('data-home-location-modality'), nome: $(button).attr('data-home-location-modality-name') }; loadClasses();
                });
            });
            $(document).on('click', '[data-home-course-modality-select]', function () {
                const button=this;
                $('[data-home-course-modality-select]').removeClass('is-selected'); $(button).addClass('is-selected');
                loadLocations(String($(button).attr('data-home-course-modality-select') || ''), String($(button).text() || '').trim());
            });
            $(document).on('click', '[data-home-modality-location]', function () {
                selectedLocation = {
                    id: String($(this).attr('data-home-modality-location') || ''),
                    apelido_local: String($(this).attr('data-home-modality-location-name') || ''),
                    nome_local: String($(this).attr('data-home-modality-location-full-name') || '')
                };
                openLocationNotice(selectedLocation.id, 'cursos', function(){ openModalityNotice(selectedModality.id, 'cursos', selectedLocation.id, loadClasses); });
            });
            $(document).on('click', '[data-home-course-flow-back="1"]', function () {
                $classesModal.addClass('hidden').attr('aria-hidden', 'true');
                (flowOrigin === 'modality' ? $modalityLocationsModal : $modalitiesModal).removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-home-course-flow-close="1"]', closeFlow);
            $(document).on('click', '#home-location-modalities-modal, #home-modality-locations-modal, #home-location-classes-modal', function (event) { if (event.target === this) closeFlow(); });
            $(document).on('click', '[data-home-course-enroll]', function () { classDetails(String($(this).attr('data-home-course-enroll') || ''), renderEnrollmentModal); });
            $(document).on('click', '[data-home-course-vacancies]', function () { classDetails(String($(this).attr('data-home-course-vacancies') || ''), renderVacanciesModal); });
            const cpfFlow = { stage: 'form', cpf: '', condition: 'geral', options: null, locationId: '', token: '' };

            function cpfVerificationBlock() {
                return $('<div>', { class: 'human-verification', 'data-human-verification': '1' })
                    .append($('<input>', { type: 'hidden', name: 'human_verification_id' }))
                    .append($('<input>', { type: 'text', name: 'website', value: '', class: 'hidden', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' }))
                    .append($('<label>', { class: 'checkbox-line' })
                        .append($('<input>', { type: 'checkbox', name: 'human_verification', value: '1', required: true }))
                        .append($('<span>', { text: 'Não sou robô' })));
            }

            function setCpfStep(step, title, subtitle) {
                $('#home-course-cpf-step').text(step);
                $('#home-course-cpf-title').text(title);
                $('#home-course-cpf-subtitle').text(subtitle || '');
                $('[data-home-cpf-back="1"]').toggleClass('hidden', cpfFlow.stage === 'form' || cpfFlow.stage === 'introduction');
                $('#home-cpf-lookup-submit').toggleClass('hidden', cpfFlow.stage !== 'form');
            }

            function renderCpfIntroduction() {
                cpfFlow.stage = 'introduction';
                setCpfStep('Inscrição disponível', 'Faça sua inscrição', 'Inscrição rápida por CPF.');
                $('#home-course-cpf-content').empty().append(
                    $('<div>', { class: 'home-course-cpf-introduction' })
                        .append($('<p>', { text: 'Faça a sua inscrição ou de seu dependente com apenas alguns cliques, informando seu CPF ou o CPF de seu dependente, se já existe cadastro em nosso site.' }))
                        .append($('<p>').append(document.createTextNode('Caso ainda não exista cadastro, clique no botão ')).append($('<strong>', { text: 'Cadastre-se' })).append(document.createTextNode('.')))
                        .append($('<div>', { class: 'popup-actions' })
                            .append($('<button>', { type: 'button', class: 'btn btn-primary', text: 'Continuar', 'data-home-cpf-start': '1' }))
                            .append($('<button>', { type: 'button', class: 'btn btn-secondary', text: 'Cadastre-se', 'data-open-route-modal': App.core.buildUrl('/cadastro') })))
                );
                $('[data-home-cpf-back="1"], #home-cpf-lookup-submit').addClass('hidden');
            }

            function renderCpfStart() {
                cpfFlow.stage = 'form'; cpfFlow.options = null; cpfFlow.locationId = ''; cpfFlow.token = '';
                setCpfStep('Etapa 1 de 4', 'Inscreva-se', 'Veja quais cursos estão disponíveis para inscrição, digite o CPF.');
                const $form = $('<form>', { id: 'home-cpf-lookup-form', class: 'stack-form', 'data-home-cpf-lookup-form': '1' });
                $form.append($('<div>', { class: 'home-course-cpf-guidance' })
                    .append($('<strong>', { text: 'Verifique se o cadastro existe' }))
                    .append($('<p>', { text: 'Informe seu CPF se você for fazer a inscrição para você. Informe o CPF de seu dependente se você for fazer a inscrição para seu dependente.' })));
                $form.append($('<label>').append($('<span>', { text: 'CPF da pessoa que irá se inscrever' })).append($('<input>', { type: 'text', name: 'cpf', value: cpfFlow.cpf, placeholder: '000.000.000-00', required: true, inputmode: 'numeric' })));
                const $conditions = $('<fieldset>', { class: 'home-course-cpf-conditions' }).append($('<legend>', { text: 'Condição Física/Social' }));
                [
                    ['geral', 'Inscrição para vaga de ampla concorrência'],
                    ['pcd', 'Pessoa Com Deficiência (PCD)'],
                    ['plm', 'Pessoa Com Laudo Médico de Doença'],
                    ['pvs', 'Pessoa em Vulnerabilidade Social']
                ].forEach(function (item) {
                    $conditions.append($('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'radio', name: 'condicao_inscricao', value: item[0], required: true, checked: cpfFlow.condition === item[0] }))
                        .append($('<span>', { text: item[1] })));
                });
                const $submit = $('#home-cpf-lookup-submit').prop('disabled', true).removeClass('hidden');
                $form.append($conditions, cpfVerificationBlock());
                $('#home-course-cpf-content').empty().append($form);
                App.core.renovarVerificacaoHumana($form).always(function () { $submit.prop('disabled', false); });
            }

            function renderCpfNotRegistered(message) {
                cpfFlow.stage = 'not-registered';
                setCpfStep('Cadastro necessário', 'CPF não cadastrado', message);
                $('#home-course-cpf-content').empty().append(
                    $('<div>', { class: 'home-course-flow-state' })
                        .append($('<p>', { text: message }))
                        .append($('<div>', { class: 'popup-actions' })
                            .append($('<button>', { type: 'button', class: 'btn btn-primary', text: 'Cadastrar-se', 'data-open-route-modal': App.core.buildUrl('/cadastro') }))
                            .append($('<button>', { type: 'button', class: 'btn btn-secondary', text: 'Entrar', 'data-open-route-modal': App.core.buildUrl('/login?return_to=%2F') })))
                );
            }

            function renderCpfLocations() {
                cpfFlow.stage = 'locations';
                const person = cpfFlow.options.person || {};
                setCpfStep('Etapa 2 de 4', 'Escolha o local', 'Cursos compatíveis com ' + String(person.nome_completo || 'a pessoa informada') + '.');
                const $content = $('#home-course-cpf-content').empty();
                const records = Array.isArray(cpfFlow.options.locations) ? cpfFlow.options.locations : [];
                if (!records.length) { $content.append($('<div>', { class: 'home-course-flow-state' }).append($('<p>', { text: 'Não há locais com turmas e vagas compatíveis com este perfil no momento.' }))); return; }
                const $list = $('<div>', { class: 'home-modality-locations-list' });
                records.forEach(function (location) {
                    $list.append($('<button>', { type: 'button', class: 'home-all-location-button', 'data-home-cpf-location': String(location.id || '') })
                        .append($('<strong>', { text: String(location.apelido_local || location.nome_local || '') }))
                        .append($('<small>', { text: String(location.nome_local || '') })));
                });
                $content.append($list);
            }

            function renderCpfClasses() {
                cpfFlow.stage = 'classes';
                const locations = cpfFlow.options.locations || [];
                const location = locations.find(function (item) { return String(item.id) === cpfFlow.locationId; }) || {};
                setCpfStep('Etapa 3 de 4', 'Turmas disponíveis', 'Turmas compatíveis em ' + String(location.apelido_local || location.nome_local || 'local selecionado') + '.');
                const classes = (cpfFlow.options.classes || []).filter(function (item) { return String(item.local_treino_id) === cpfFlow.locationId; });
                const $content = $('#home-course-cpf-content').empty();
                classes.forEach(function (courseClass) {
                    const $card = $('<article>', { class: 'home-course-class-card' });
                    $card.append($('<h4>', { text: String(courseClass.modalidade_nome || 'Modalidade') + ' - ' + String(courseClass.temporada_nome || '') }));
                    $card.append($('<p>').append($('<strong>', { text: '[' + String(courseClass.id || '') + '] - ' + String(courseClass.nome || '') })));
                    $card.append($('<p>').append($('<strong>', { text: 'Programa: ' })).append(document.createTextNode(String(courseClass.programa || 'Sem programa definido'))));
                    $card.append($('<p>').append($('<strong>', { text: 'Local da aula: ' })).append(document.createTextNode(String(courseClass.local_nome || ''))));
                    if (courseClass.dias_semana_descricao) $card.append($('<p>').append($('<strong>', { text: 'Dias e horário: ' })).append(document.createTextNode(String(courseClass.dias_semana_descricao) + ', das ' + String(courseClass.hora_inicio || '').slice(0, 5) + ' às ' + String(courseClass.hora_fim || '').slice(0, 5))));
                    if (courseClass.periodo_dia) $card.append($('<p>').append($('<strong>', { text: 'Período: ' })).append(document.createTextNode(String(courseClass.periodo_dia))));
                    $card.append($('<p>').append($('<strong>', { text: classAgeCriterionText(courseClass) })));
                    appendClassAgeExceptions($card, courseClass);
                    $card.append($('<p>').append($('<strong>', { text: 'Níveis aceitos: ' })).append(document.createTextNode(String(courseClass.niveis_aceitos_descricao || 'Sem limitação de nível'))));
                    if (courseClass.sexo) $card.append($('<p>').append($('<strong>', { text: 'Sexo permitido: ' })).append(document.createTextNode(String(courseClass.sexo) === 'feminino' ? 'Feminino' : 'Masculino')));
                    appendClassPublicNotices($card, courseClass);
                    if (courseClass.cpf_aviso_excecao) $card.append($('<p>', { class: 'home-course-age-exception-notice', text: String(courseClass.cpf_aviso_excecao) }));
                    $card.append($('<button>', { type: 'button', class: 'btn btn-primary', text: 'Inscrever-se', 'data-home-cpf-class': String(courseClass.id || '') }));
                    $content.append($card);
                });
            }

            function renderCpfConfirmation(details) {
                cpfFlow.stage = 'details';
                const courseClass = details.class || {};
                setCpfStep('Etapa 4 de 4', 'Detalhes da turma', 'Confira os dados e confirme a inscrição de ' + String((details.person || {}).nome_completo || '') + '.');
                const $content = $('#home-course-cpf-content').empty();
                const $summary = $('<div>', { class: 'home-course-detail-summary' })
                    .append($('<h4>', { text: String(courseClass.modalidade_nome || 'Modalidade') + ' - ' + String(courseClass.temporada_nome || '') }))
                    .append($('<p>').append($('<strong>', { text: '[' + String(courseClass.id || '') + '] - ' + String(courseClass.nome || '') })))
                    .append($('<p>').append($('<strong>', { text: 'Programa: ' })).append(document.createTextNode(String(courseClass.programa || 'Sem programa definido'))))
                    .append($('<p>').append($('<strong>', { text: 'Local da aula: ' })).append(document.createTextNode(String(courseClass.local_nome || ''))))
                    .append($('<p>').append($('<strong>', { text: 'Pessoa: ' })).append(document.createTextNode(String((details.person || {}).nome_completo || ''))));
                if (courseClass.dias_semana_descricao) $summary.append($('<p>').append($('<strong>', { text: 'Dias e horário: ' })).append(document.createTextNode(String(courseClass.dias_semana_descricao) + ', das ' + String(courseClass.hora_inicio || '').slice(0, 5) + ' às ' + String(courseClass.hora_fim || '').slice(0, 5))));
                if (courseClass.periodo_dia) $summary.append($('<p>').append($('<strong>', { text: 'Período: ' })).append(document.createTextNode(String(courseClass.periodo_dia))));
                $summary.append($('<p>').append($('<strong>', { text: classAgeCriterionText(courseClass) })));
                appendClassAgeExceptions($summary, courseClass);
                $summary.append($('<p>').append($('<strong>', { text: 'Níveis aceitos: ' })).append(document.createTextNode(String(courseClass.niveis_aceitos_descricao || 'Sem limitação de nível'))));
                if (courseClass.sexo) $summary.append($('<p>').append($('<strong>', { text: 'Sexo permitido: ' })).append(document.createTextNode(String(courseClass.sexo) === 'feminino' ? 'Feminino' : 'Masculino')));
                appendClassPublicNotices($summary, courseClass);
                if (courseClass.cpf_aviso_excecao) $summary.append($('<p>', { class: 'home-course-age-exception-notice', text: String(courseClass.cpf_aviso_excecao) }));
                const $form = $('<form>', { class: 'stack-form home-course-enrollment-form', method: 'POST', action: App.core.buildUrl('/cursos/inscrever'), 'data-manual-submit': '1' });
                $form.append($('<input>', { type: 'hidden', name: 'turma_id', value: String(courseClass.id || '') }));
                $form.append($('<input>', { type: 'hidden', name: 'cpf', value: cpfFlow.cpf }));
                $form.append($('<input>', { type: 'hidden', name: 'condicao_inscricao', value: cpfFlow.condition }));
                $form.append($('<input>', { type: 'hidden', name: 'flow_token', value: cpfFlow.token }));
                $form.append($('<label>', { class: 'checkbox-chip' }).append($('<input>', { type: 'checkbox', name: 'aceite_termos', value: '1', required: true })).append($('<span>').append(document.createTextNode('Li e aceito os termos da inscrição, disponíveis ')).append($('<button>', { type: 'button', class: 'link-button course-terms-link', 'data-enrollment-terms-open': '1', text: 'neste link' })).append(document.createTextNode('.'))));
                appendEnrollmentNoticeAcceptance($form, courseClass);
                const $submit = $('<button>', { type: 'submit', class: 'btn btn-primary', text: 'Confirmar inscrição', disabled: true });
                $form.append(cpfVerificationBlock(), $submit);
                $content.append($summary, $form);
                App.core.renovarVerificacaoHumana($form).always(function () { $submit.prop('disabled', false); });
            }

            $(document).on('click', '[data-home-cpf-start="1"]', renderCpfStart);
            $(document).on('submit', '[data-home-cpf-lookup-form="1"]', function (event) {
                event.preventDefault(); const $form = $(this); const $button = $('#home-cpf-lookup-submit').prop('disabled', true);
                cpfFlow.cpf = String($form.find('[name="cpf"]').val() || ''); cpfFlow.condition = String($form.find('[name="condicao_inscricao"]:checked').val() || 'geral');
                $.ajax({ url: App.core.buildUrl('/cursos/inscricao-cpf/opcoes'), method: 'POST', data: new FormData($form[0]), processData: false, contentType: false, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) { cpfFlow.options = response.options || {}; cpfFlow.token = String(cpfFlow.options.flow_token || ''); if (!cpfFlow.options.registered) renderCpfNotRegistered(String(cpfFlow.options.message || 'CPF não cadastrado.')); else renderCpfLocations(); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); App.core.renovarVerificacaoHumana($form); })
                    .always(function () { $button.prop('disabled', false); });
            });
            $(document).on('click', '[data-home-cpf-location]', function () { cpfFlow.locationId = String($(this).attr('data-home-cpf-location') || ''); renderCpfClasses(); });
            $(document).on('click', '[data-home-cpf-class]', function () {
                $.ajax({ url: App.core.buildUrl('/cursos/inscricao-cpf/turma-detalhes'), method: 'POST', dataType: 'json', data: { turma_id: $(this).attr('data-home-cpf-class'), cpf: cpfFlow.cpf, condicao_inscricao: cpfFlow.condition, flow_token: cpfFlow.token }, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) { renderCpfConfirmation(response.details || {}); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            });
            $(document).on('click', '[data-home-cpf-back="1"]', function () { if (cpfFlow.stage === 'details') renderCpfClasses(); else if (cpfFlow.stage === 'classes') renderCpfLocations(); else if (cpfFlow.stage === 'locations') renderCpfStart(); else renderCpfIntroduction(); });
            $(document).on('click', '[data-home-cpf-close="1"]', function () { $('#home-course-cpf-modal').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#home-course-cpf-modal', function (event) { if (event.target === this) $(this).addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '[data-home-course-detail-close="1"]', function () { $('#home-course-enrollment-modal, #home-course-vacancies-modal').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#home-course-enrollment-modal, #home-course-vacancies-modal', function (event) { if (event.target === this) $(this).addClass('hidden').attr('aria-hidden', 'true'); });
            if ($('#home-course-cpf-modal').attr('data-cpf-enrollment-enabled') === '1') {
                renderCpfIntroduction();
                $('#home-course-cpf-modal').removeClass('hidden').attr('aria-hidden', 'false');
            }
            $(document).on('change', '[data-home-course-person-choice="1"]', function () {
                const publicValue = String($(this).attr('data-public') || 'geral');
                $('#home-course-person-public').val(publicValue);
                $('#home-course-person-public-value').val(publicValue);
                $(this).closest('form').find('button[type="submit"]').text(isMinorByBirthDate($(this).attr('data-birth-date')) ? 'Finalizar' : 'Confirmar inscrição');
            });
            $(document).on('click', '[data-enrollment-terms-open="1"]', function (event) { event.preventDefault(); openEnrollmentTerms($(this).closest('form')); });
            $(document).on('click', '[data-enrollment-terms-close="1"], #home-course-enrollment-terms-modal', function (event) {
                if ($(event.target).is('#home-course-enrollment-terms-modal') || $(event.target).is('[data-enrollment-terms-close="1"]')) $('#home-course-enrollment-terms-modal').addClass('hidden').attr('aria-hidden', 'true');
            });
            $(document).on('submit', '.home-course-enrollment-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({ url: $form.attr('action'), method: 'POST', data: new FormData($form[0]), processData: false, contentType: false, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) {
                        if (!response || response.success === false) {
                            if (response && response.human_verification_refresh) App.core.renovarVerificacaoHumana($form);
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível concluir a inscrição.'));
                            return;
                        }
                        $('#home-course-enrollment-modal, #home-course-cpf-modal').addClass('hidden').attr('aria-hidden', 'true');
                        const redirect = String(response.redirect || '').trim();
                        App.core.abrirPopup('sucesso', String(response.message || 'Inscrição realizada com sucesso.'), function () {
                            if (redirect !== '') return;
                            loadClasses();
                        }, redirect);
                    }).fail(function (xhr) {
                        App.core.renovarVerificacaoHumana($form);
                        App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                    })
                    .always(function () { $button.prop('disabled', false); });
            });
        },

        iniciarAgendaDeTreinos: function () {
            const calendarElement = document.getElementById('home-training-calendar');
            const $section = $('#home-training-agenda');
            const $calendarModal = $('#home-training-calendar-modal');
            const $modalitiesModal = $('#home-training-modalities-modal');
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
                const $list = $('#home-training-modalities-list');

                if (modalitiesRequest && typeof modalitiesRequest.abort === 'function') {
                    modalitiesRequest.abort();
                }

                $list.html('<p class="muted">Carregando modalidades...</p>');

                modalitiesRequest = $.getJSON(App.core.buildUrl('/api/agenda/modalidades-por-local'), {
                    local_treino_id: locationId
                }).done(function (response) {
                    const modalities = response && Array.isArray(response.modalities) ? response.modalities : [];

                    if (!response || response.success === false) {
                        $list.html('<p class="muted">Não foi possível carregar as modalidades deste local.</p>');
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar as modalidades deste local.'));
                        return;
                    }

                    $list.empty().append($('<button>', {
                        type: 'button',
                        class: 'home-all-location-button',
                        'data-home-training-modality': '0'
                    }).append($('<strong>', { text: 'Todas as modalidades' })));
                    modalities.forEach(function (modality) {
                        $list.append($('<button>', {
                            type: 'button',
                            class: 'home-all-location-button',
                            'data-home-training-modality': String(modality.id || '')
                        }).append($('<strong>', { text: String(modality.nome || '') })));
                    });
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        $list.html('<p class="muted">Não foi possível carregar as modalidades deste local.</p>');
                        const error = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', error.mensagem);
                    }
                }).always(function (_response, status) {
                    if (status !== 'abort') {
                        modalitiesRequest = null;
                    }
                });
            }

            function calendarAvailableHeight() {
                const card = $calendarModal.find('.home-training-calendar-card').get(0);
                const head = card ? card.querySelector(':scope > .popup-head') : null;
                const body = card ? card.querySelector(':scope > .popup-body') : null;
                const actions = card ? card.querySelector(':scope > .popup-actions') : null;
                const summary = document.getElementById('home-training-calendar-filter-summary');
                const viewportGap = window.innerWidth <= 720 ? 8 : 16;
                let reserved = 220;

                if (card && !$calendarModal.hasClass('hidden')) {
                    const bodyStyle = body ? window.getComputedStyle(body) : null;
                    const bodyPadding = bodyStyle
                        ? (parseFloat(bodyStyle.paddingTop) || 0) + (parseFloat(bodyStyle.paddingBottom) || 0)
                        : 0;
                    const summaryStyle = summary ? window.getComputedStyle(summary) : null;
                    const summaryMargin = summaryStyle
                        ? (parseFloat(summaryStyle.marginTop) || 0) + (parseFloat(summaryStyle.marginBottom) || 0)
                        : 0;
                    reserved = (head ? head.offsetHeight : 0)
                        + (actions ? actions.offsetHeight : 0)
                        + (summary ? summary.offsetHeight + summaryMargin : 0)
                        + bodyPadding;
                }

                return Math.max(220, window.innerHeight - viewportGap - reserved);
            }

            function fitTrainingCalendar() {
                calendar.setOption('height', calendarAvailableHeight());
                calendar.updateSize();
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
                height: Math.max(220, window.innerHeight - 220),
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                windowResize: function () {
                    fitTrainingCalendar();
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
                const requestedLocationId = Number($(this).attr('data-home-training-location') || 0);
                if (!requestedLocationId) return;
                const proceed=function(){
                    selectedLocationId = requestedLocationId;
                    $('#home-all-training-locations-modal').addClass('hidden').attr('aria-hidden', 'true');
                    const location = locations.find(function (item) { return Number(item.id || 0) === selectedLocationId; });
                    const locationLabel = location ? String(location.apelido_local || location.nome_local || '') : 'Local selecionado';
                    $('#home-training-calendar-location').text('— ' + locationLabel);
                    $('#home-training-modalities-subtitle').text('Escolha uma modalidade disponível em ' + locationLabel + '.');
                    $('#home-training-modality').val('0').data('label', 'Todas as modalidades');
                    loadLocationModalities(selectedLocationId);
                    $modalitiesModal.removeClass('hidden').attr('aria-hidden', 'false');
                };
                if(App.home.openLocationNotice) App.home.openLocationNotice(requestedLocationId,'agenda',proceed); else proceed();
            });

            $(document).on('click', '[data-home-training-modality]', function () {
                const modalityId = Number($(this).attr('data-home-training-modality') || 0);
                const modalityLabel = String($(this).find('strong').first().text() || 'Todas as modalidades').trim();
                const location = locations.find(function (item) { return Number(item.id || 0) === selectedLocationId; });
                const locationLabel = location ? String(location.apelido_local || location.nome_local || '') : 'Local selecionado';
                const proceed=function(){
                    $('#home-training-modality').val(String(modalityId)).data('label', modalityLabel);
                    $('#home-training-calendar-filter-summary').text(locationLabel + ' — ' + modalityLabel + '.');
                    $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true'); $calendarModal.removeClass('hidden').attr('aria-hidden', 'false');
                    calendar.refetchEvents(); window.setTimeout(function () { fitTrainingCalendar(); }, 50);
                };
                if(modalityId>0 && App.home.openModalityNotice) App.home.openModalityNotice(modalityId,'agenda',selectedLocationId,proceed); else proceed();
            });

            $(document).on('click', '[data-home-training-modalities-back="1"]', function () {
                $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
                $('#home-all-training-locations-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-home-training-modalities-close="1"]', function () {
                $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
            });
            $(document).on('click', '#home-training-modalities-modal', function (event) {
                if (event.target === this) $modalitiesModal.addClass('hidden').attr('aria-hidden', 'true');
            });
            $(document).on('click', '[data-home-training-calendar-back="1"]', function () {
                $calendarModal.addClass('hidden').attr('aria-hidden', 'true');
                $modalitiesModal.removeClass('hidden').attr('aria-hidden', 'false');
            });
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
