(function (window, $) {
    const App = window.App || {};

    App.agenda = Object.assign(App.agenda || {}, {
        escapeHtml: function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        formatarSexoHorario: function (sexo) {
            const value = String(sexo || '').trim();

            if (value === 'masculino') {
                return 'Masculino';
            }

            if (value === 'feminino') {
                return 'Feminino';
            }

            return 'Livre';
        },

        formatarHoraAgenda: function (value) {
            if (!value) {
                return '';
            }

            const dateValue = value instanceof Date ? value : new Date(value);

            if (!(dateValue instanceof Date) || Number.isNaN(dateValue.getTime())) {
                return '';
            }

            const hours = String(dateValue.getHours()).padStart(2, '0');
            const minutes = String(dateValue.getMinutes()).padStart(2, '0');

            return hours + ':' + minutes;
        },

        formatarVagasAgenda: function (available, total) {
            const totalValue = Math.max(0, Number(total || 0));
            const availableValue = Math.max(0, Number(available || 0));

            return String(availableValue).padStart(2, '0') + '/' + String(totalValue).padStart(2, '0');
        },

        renderizarEventoCalendario: function (event) {
            const title = App.agenda.escapeHtml(String((event && event.title) || '').trim());
            const time = App.agenda.escapeHtml(App.agenda.formatarHoraAgenda(event && event.start ? event.start : null));

            return {
                html: ''
                    + '<div class="agenda-calendar-event-content">'
                    + '<span class="agenda-calendar-event-time">' + time + '</span>'
                    + '<span class="agenda-calendar-event-title">' + title + '</span>'
                    + '</div>'
            };
        },

        resolverEstiloEventoCalendario: function (event) {
            const classNames = Array.isArray((event && event.classNames) || []) ? event.classNames : [];

            if (classNames.indexOf('agenda-booking-status-presente') >= 0) {
                return { bg: '#d9f3df', border: '#1f7a35', color: '#1f7a35', stripe: '#1f7a35' };
            }

            if (classNames.indexOf('agenda-booking-status-falta') >= 0) {
                return { bg: '#f8d7da', border: '#b42318', color: '#8a1c12', stripe: '#b42318' };
            }

            if (classNames.indexOf('agenda-booking-status-justificado') >= 0) {
                return { bg: '#dbeafe', border: '#175cd3', color: '#175cd3', stripe: '#175cd3' };
            }

            if (classNames.indexOf('agenda-booking-status-cancelado') >= 0) {
                return { bg: '#4b5563', border: '#374151', color: '#f9fafb', stripe: '#1f2937' };
            }

            if (classNames.indexOf('agenda-booking-status-misto') >= 0) {
                return { bg: '#dde6f7', border: '#365487', color: '#365487', stripe: '#365487' };
            }

            if (classNames.indexOf('agenda-booking-status-agendado') >= 0) {
                return { bg: '#f1ddb0', border: '#d4a017', color: '#6c4e00', stripe: '#d4a017' };
            }

            if (classNames.indexOf('agenda-schedule-inactive') >= 0) {
                return { bg: '#d0d5dd', border: '#98a2b3', color: '#344054', stripe: null };
            }

            return null;
        },

        encontrarEventoAgendaPorOcorrencia: function (scheduleId, occurrenceStart) {
            if (!App.state.agendaCalendar || typeof App.state.agendaCalendar.getEvents !== 'function') {
                return null;
            }

            const normalizedId = String(scheduleId || '');
            const normalizedStart = String(occurrenceStart || '');
            const events = App.state.agendaCalendar.getEvents();

            for (let i = 0; i < events.length; i += 1) {
                const event = events[i];

                if (
                    String(event.id || '') === normalizedId &&
                    String((((event || {}).extendedProps || {}).occurrence_start) || '') === normalizedStart
                ) {
                    return event;
                }
            }

            return null;
        },

        isPastOccurrence: function (eventInfo) {
            if (!eventInfo || !eventInfo.event || !eventInfo.event.start) {
                return false;
            }

            return eventInfo.event.start.getTime() < Date.now();
        },

        abrirModalDetalhesHorario: function () {
            $('#agenda-details-modal').removeClass('hidden').attr('aria-hidden', 'false');
        },

        fecharModalDetalhesHorario: function () {
            $('#agenda-details-modal').addClass('hidden').attr('aria-hidden', 'true');
        },

        preencherPessoaEventoEspecial: function ($option) {
            if (!$option || $option.length === 0) {
                return;
            }

            const hasLinkedPerson = String($option.val() || '').trim() !== '';

            $('#agenda-special-name')
                .val(String($option.data('nome') || ''))
                .prop('readonly', hasLinkedPerson);
            $('#agenda-special-cpf')
                .val(String($option.data('cpf') || ''))
                .prop('readonly', hasLinkedPerson);
            $('#agenda-special-schedule-birth-date')
                .val(String($option.data('nascimento') || ''))
                .prop('readonly', hasLinkedPerson);
        },

        resetarDetalhesAgenda: function () {
            $('#painel-evento').html('<p class="muted">Clique em um horário no calendário para ver local, vagas e regras.</p>');
            $('#horario_id').val('');
            $('#data_hora_inicio').val('');
            $('#form-agendamento').addClass('hidden');
            $('#form-agenda-horario-especial').addClass('hidden');
            $('#agenda_horario_especial_id').val('');
            $('#agenda-special-schedule-linked-person').val('');
            $('#agenda-special-name').val('').prop('readonly', false);
            $('#agenda-special-cpf').val('').prop('readonly', false);
            $('#agenda-special-schedule-birth-date').val('').prop('readonly', false);
            $('#agenda-special-schedule-publico').val('geral');
            $('#form-agenda-horario-especial').find('input[name="aceite_termos"]').prop('checked', false);
            $('#agenda-cancel-bookings').addClass('hidden').html('');
            $('#agenda-person-options').addClass('hidden').html('');
            const $accessWarning = $('#agenda-access-warning');
            const defaultWarningText = String($accessWarning.data('defaultText') || '').trim();

            if (defaultWarningText !== '') {
                $accessWarning.text(defaultWarningText);
            }

            App.state.agendaPendingEventData = null;
        },

        atualizarPessoasAgendamento: function (forceLoad) {
            const $calendar = $('#calendario-treinos');
            const $options = $('#agenda-person-options');
            const $helper = $('#agenda-person-helper');
            const shouldForceLoad = forceLoad === true;

            if ($options.length === 0) {
                return;
            }

            if (!shouldForceLoad && String($options.data('agendaAuthenticated') || '') !== '1') {
                return;
            }

            $.getJSON(App.core.buildUrl('/agenda/pessoas'))
                .done(function (response) {
                    const people = response && Array.isArray(response.people) ? response.people : [];
                    const needsProfileCompletion = !!(response && response.needs_profile_completion);

                    $options.html('');
                    $options.toggleClass('hidden', people.length === 0);
                    $options.data('agendaAuthenticated', '1');
                    $calendar.attr('data-agenda-authenticated', '1');
                    $calendar.attr('data-agenda-needs-profile-completion', needsProfileCompletion ? '1' : '0');

                    if ($helper.length > 0) {
                        $helper.text(String((response && response.message) || 'Complete seu cadastro para liberar os nomes disponíveis para agendamento.'));
                        $helper.toggleClass('hidden', !needsProfileCompletion);
                    }

                    App.core.fecharPopupCustomizado('#agenda-login-reminder');

                    if (!needsProfileCompletion) {
                        App.core.fecharPopupCustomizado('#agenda-profile-reminder');

                        if (App.state.agendaPendingEventData) {
                            App.agenda.renderizarDetalhesAgenda(App.state.agendaPendingEventData);
                        }
                    }
                })
                .fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);

                    if (xhr.status === 401 && erro.redirectUrl !== '') {
                        App.core.abrirPopup('erro', erro.mensagem, function () {
                            window.location.href = erro.redirectUrl;
                        });
                    }
                });
        },

        podeAbrirDetalhesAgenda: function () {
            const $calendar = $('#calendario-treinos');

            if ($calendar.length === 0) {
                return true;
            }

            if (String($calendar.attr('data-agenda-authenticated') || '') !== '1') {
                return false;
            }

            return String($calendar.attr('data-agenda-needs-profile-completion') || '') !== '1';
        },

        abrirLembreteAgenda: function () {
            const $calendar = $('#calendario-treinos');
            const $loginReminder = $('#agenda-login-reminder');
            const $profileReminder = $('#agenda-profile-reminder');

            if ($calendar.length === 0) {
                return;
            }

            if (String($calendar.attr('data-agenda-authenticated') || '') !== '1') {
                if ($loginReminder.length > 0) {
                    App.core.abrirPopupCustomizado('#agenda-login-reminder');
                }
                return;
            }

            if (String($calendar.attr('data-agenda-needs-profile-completion') || '') === '1') {
                if ($profileReminder.length > 0) {
                    App.core.abrirPopupCustomizado('#agenda-profile-reminder');
                    return;
                }

                App.core.abrirConfirmacaoCompletarCadastro('/agenda');
            }
        },

        atualizarElegibilidadeAgenda: function (eventInfo) {
            const $container = $('#agenda-person-options');
            const $helper = $('#agenda-person-helper');

            if ($container.length === 0 || !eventInfo || !eventInfo.event) {
                return;
            }

            $container.html('<p class="muted">Validando pessoas para este horário...</p>').removeClass('hidden');
            $helper.addClass('hidden');

            $.getJSON(App.core.buildUrl('/agenda/elegibilidade'), {
                horario_id: eventInfo.event.id,
                data_hora_inicio: eventInfo.event.startStr
            }).done(function (response) {
                const items = response && Array.isArray(response.items) ? response.items : [];
                let html = '';

                if (items.length === 0) {
                    html = '<p class="muted">Nenhuma pessoa vinculada foi encontrada para agendamento.</p>';
                } else {
                    items.forEach(function (item) {
                        const checkedAttr = item.elegivel ? '' : ' disabled';
                        const cardClass = item.elegivel ? 'agenda-person-card' : 'agenda-person-card is-disabled';
                        let reasonsHtml = '';

                        if (Array.isArray(item.motivos) && item.motivos.length > 0) {
                            item.motivos.forEach(function (reason) {
                                reasonsHtml += '<small class="agenda-person-reason">' + String(reason) + '</small>';
                            });
                        }

                        html += ''
                            + '<label class="' + cardClass + '" data-person-choice-card="1">'
                            + '<span class="agenda-person-line">'
                            + '<input type="radio" name="person_id" data-person-choice="1" value="' + String(item.id) + '"' + checkedAttr + '>'
                            + '<span class="agenda-person-main">' + String(item.nome_completo || '') + '</span>'
                            + '</span>'
                            + reasonsHtml
                            + '</label>';
                    });
                }

                $container.html(html).removeClass('hidden');
            }).fail(function (xhr) {
                const erro = App.core.extrairMensagemErroAjax(xhr);

                $container.addClass('hidden').html('');
                $helper.removeClass('hidden').text(erro.mensagem);
            });
        },

        renderizarDetalhesAgenda: function (eventInfo) {
            const props = eventInfo.event.extendedProps || {};
            const painelEvento = $('#painel-evento');
            const formAgendamento = $('#form-agendamento');
            const cancelBookings = $('#agenda-cancel-bookings');
            const accessWarning = $('#agenda-access-warning');
            const personOptions = $('#agenda-person-options');
            const canSchedule = App.agenda.podeAbrirDetalhesAgenda();
            const isPast = App.agenda.isPastOccurrence(eventInfo);
            const isSpecial = props.is_special === true;
            const isInactiveSchedule = props.horario_ativo === false || String(props.horario_ativo) === '0';
            const meusAgendamentos = Array.isArray(props.meus_agendamentos) ? props.meus_agendamentos : [];
            let bookingStatusHtml = '';

            $('#horario_id').val(eventInfo.event.id);
            $('#data_hora_inicio').val(eventInfo.event.startStr);
            $('input[data-person-choice="1"]').prop('checked', false);

            if (meusAgendamentos.length > 0) {
                let itemsHtml = '';

                meusAgendamentos.forEach(function (item) {
                    let cancelAction = '';

                    if (item.pode_cancelar) {
                        cancelAction = '<button type="button" class="btn btn-secondary btn-compact" data-agenda-cancel-booking="' + String(item.id || 0) + '">Cancelar agendamento</button>';
                    }

                    itemsHtml += ''
                        + '<li class="agenda-booking-status-item agenda-booking-status-item-' + App.agenda.escapeHtml(item.status) + '">'
                        + '<span class="agenda-booking-status-name">' + App.agenda.escapeHtml(item.nome_completo) + '</span>'
                        + '<span class="agenda-booking-status-chip agenda-booking-status-chip-' + App.agenda.escapeHtml(item.status) + '">' + App.agenda.escapeHtml(item.status_label) + '</span>'
                        + cancelAction
                        + '</li>';
                });

                bookingStatusHtml = ''
                    + '<div class="agenda-booking-status-panel">'
                    + '<p><strong>Situação deste horário na sua conta:</strong></p>'
                    + '<ul class="agenda-booking-status-list">' + itemsHtml + '</ul>'
                    + '</div>';
            }

            if (isSpecial) {
                const specialDescription = String(props.special_description || '').trim();
                const specialUrl = String(props.special_cta_url || '').trim();
                const specialLabel = String(props.special_cta_label || 'Abrir detalhes').trim();

                painelEvento.html(
                    '<div class="event-card">'
                    + '<h3>' + eventInfo.event.title + '</h3>'
                    + '<p><strong>Horário:</strong> ' + App.agenda.formatarHoraAgenda(eventInfo.event.start) + ' as ' + App.agenda.formatarHoraAgenda(eventInfo.event.end) + '</p>'
                    + '<p><strong>Local:</strong> ' + (props.local || 'A definir') + '</p>'
                    + '<p><strong>Espaço:</strong> ' + (props.espaco || 'A definir') + '</p>'
                    + '<p><strong>Modalidade:</strong> ' + (props.modalidade || 'Horário especial') + '</p>'
                    + '<p><strong>Tipo:</strong> Horário especial</p>'
                    + (String(props.criterio_faixa_etaria || '') === 'ano_nascimento' && String(props.ano_nascimento_intervalo || '').trim() !== ''
                        ? '<p><strong>Faixa permitida:</strong> ( para ' + String(props.ano_nascimento_intervalo).replace('Nascidos entre ', 'nascidos entre ') + ' )</p>'
                        : '<p><strong>Faixa etária:</strong> ( para ' + String(props.special_age_min || 0) + ' a ' + String(props.special_age_max || 120) + ' anos de idade )</p>')
                    + '<p><strong>Vagas:</strong> Geral ' + String(props.vagas_geral || 0) + ' | PCD ' + String(props.vagas_pcd || 0) + ' | PVS ' + String(props.vagas_pvs || 0) + ' | PLM ' + String(props.vagas_plm || 0) + '</p>'
                    + '<p><strong>Inscrições:</strong> ' + String(props.vagas_ocupadas || 0) + ' de ' + String(props.vagas_total || 0) + '</p>'
                    + (String(props.special_image_url || '').trim() !== '' ? '<p><img src="' + App.agenda.escapeHtml(String(props.special_image_url || '')) + '" alt="' + App.agenda.escapeHtml(eventInfo.event.title) + '" class="agenda-special-event-image"></p>' : '')
                    + (specialDescription !== '' ? '<p><strong>Descrição:</strong> ' + App.agenda.escapeHtml(specialDescription) + '</p>' : '')
                    + (specialUrl !== '' ? '<p><a class="btn btn-primary" href="' + App.agenda.escapeHtml(specialUrl) + '">' + App.agenda.escapeHtml(specialLabel) + '</a></p>' : '')
                    + '</div>'
                );

                cancelBookings.toggleClass('hidden', true).html('');
                formAgendamento.addClass('hidden');
                accessWarning.addClass('hidden');
                personOptions.addClass('hidden').html('');
                $('#agenda_horario_especial_id').val(String(props.special_schedule_id || '').trim() !== '' ? String(props.special_schedule_id) : String(String(eventInfo.event.id || '').replace('special-schedule-', '').replace('special-', '')));
                $('#form-agenda-horario-especial').removeClass('hidden');
                App.state.agendaPendingEventData = eventInfo;
                App.agenda.abrirModalDetalhesHorario();
                return;
            }

            painelEvento.html(
                '<div class="event-card">'
                + '<h3>' + eventInfo.event.title + '</h3>'
                + '<p><strong>Horário:</strong> ' + App.agenda.formatarHoraAgenda(eventInfo.event.start) + ' ás ' + App.agenda.formatarHoraAgenda(eventInfo.event.end) + '</p>'
                + '<p><strong>Local:</strong> ' + props.local + '</p>'
                + '<p><strong>Espaço:</strong> ' + props.espaco + '</p>'
                + '<p><strong>Modalidade:</strong> ' + props.modalidade + '</p>'
                + '<p><strong>Tipo:</strong> ' + props.tipo_horario + '</p>'
                + (String(props.criterio_faixa_etaria || '') === 'ano_nascimento' && String(props.ano_nascimento_intervalo || '').trim() !== ''
                    ? '<p><strong>Ano de nascimento permitido:</strong> ( para ' + String(props.ano_nascimento_intervalo).replace('Nascidos entre ', 'nascidos entre ') + ' )</p>'
                    : '<p><strong>Faixa etária:</strong> ( para ' + props.idade_minima + ' a ' + props.idade_maxima + ' anos de idade )</p>')
                + '<p><strong>Sexo permitido:</strong> ' + App.agenda.formatarSexoHorario(props.sexo) + '</p>'
                + '<p><strong>Vagas:</strong> ' + App.agenda.formatarVagasAgenda(props.vagas_disponiveis, props.vagas_total) + ' disponíveis</p>'
                + '<p><strong>Ocupacao:</strong> ' + String(props.vagas_ocupadas || 0).padStart(2, '0') + ' agendamento(s) de ' + String(props.vagas_total || 0).padStart(2, '0') + ' vaga(s)</p>'
                + (isInactiveSchedule ? '<p><strong>Aviso:</strong> Este horário semanal está inativo e não aceita novos agendamentos.</p>' : '')
                + (isPast ? '<p><strong>Aviso:</strong> Não é possível agendar para data passada.</p>' : '')
                + bookingStatusHtml
                + '</div>'
            );

            cancelBookings.toggleClass('hidden', true).html('');

            if (!isPast && !isInactiveSchedule && canSchedule) {
                formAgendamento.removeClass('hidden');
                accessWarning.addClass('hidden');
                personOptions.removeClass('hidden');
                App.agenda.atualizarElegibilidadeAgenda(eventInfo);
            } else {
                formAgendamento.addClass('hidden');

                if (isInactiveSchedule) {
                    accessWarning.removeClass('hidden').text('Este horário semanal está inativo e não aceita novos agendamentos.');
                    personOptions.addClass('hidden').html('');
                } else if (isPast) {
                    accessWarning.removeClass('hidden').text('Não é possível agendar para uma data passada.');
                    personOptions.addClass('hidden').html('');
                } else {
                    accessWarning.removeClass('hidden');
                    if (String(accessWarning.data('defaultText') || '').trim() !== '') {
                        accessWarning.text(String(accessWarning.data('defaultText') || '').trim());
                    }
                    personOptions.addClass('hidden');
                }
            }

            App.state.agendaPendingEventData = eventInfo;
            App.agenda.abrirModalDetalhesHorario();
        },

        iniciarCalendario: function () {
            const calendarEl = document.getElementById('calendario-treinos');
            const $filterForm = $('#agenda-calendar-filter-form');

            if (!calendarEl || typeof FullCalendar === 'undefined') {
                return;
            }

            const calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,listWeek'
                },
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                height: 760,
                events: {
                    url: App.core.buildUrl('/api/agenda/eventos'),
                    extraParams: function () {
                        return {
                            local_treino_id: String($filterForm.find('input[name="local_treino_id"]').val() || '0'),
                            modalidade_id: String($filterForm.find('input[name="modalidade_id"]').val() || '0')
                        };
                    }
                },
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                displayEventEnd: false,
                eventContent: function (info) {
                    return App.agenda.renderizarEventoCalendario(info.event);
                },
                eventDidMount: function (info) {
                    if (!info || !info.el) {
                        return;
                    }

                    info.el.style.opacity = '1';
                    info.el.style.filter = 'none';

                    const harness = info.el.closest('.fc-timegrid-event-harness');
                    if (harness) {
                        harness.style.opacity = '1';
                        harness.style.filter = 'none';
                    }

                    const insetHarness = info.el.closest('.fc-timegrid-event-harness-inset');
                    if (insetHarness) {
                        insetHarness.style.opacity = '1';
                        insetHarness.style.filter = 'none';
                    }

                    const listRow = info.el.closest('.fc-list-event');
                    if (listRow) {
                        listRow.style.opacity = '1';
                        listRow.style.filter = 'none';
                    }

                    const main = info.el.querySelector('.fc-event-main');
                    if (main) {
                        main.style.opacity = '1';
                        main.style.filter = 'none';
                    }

                    const visualStyle = App.agenda.resolverEstiloEventoCalendario(info.event);

                    if (visualStyle) {
                        info.el.style.background = visualStyle.bg;
                        info.el.style.backgroundColor = visualStyle.bg;
                        info.el.style.backgroundImage = 'none';
                        info.el.style.borderColor = visualStyle.border;
                        info.el.style.color = visualStyle.color;
                        info.el.style.setProperty('--fc-event-bg-color', visualStyle.bg);
                        info.el.style.setProperty('--fc-event-border-color', visualStyle.border);
                        info.el.style.boxShadow = visualStyle.stripe
                            ? ('inset 5px 0 0 ' + visualStyle.stripe)
                            : 'none';

                        if (main) {
                            main.style.background = 'transparent';
                            main.style.backgroundColor = 'transparent';
                            main.style.backgroundImage = 'none';
                            main.style.color = visualStyle.color;
                        }
                    }
                },
                eventClick: function (info) {
                    App.state.agendaPendingEventData = info;

                    if (info.event.extendedProps && info.event.extendedProps.is_special === true) {
                        App.agenda.renderizarDetalhesAgenda(info);
                        return;
                    }

                    if (!App.agenda.isPastOccurrence(info) && !App.agenda.podeAbrirDetalhesAgenda()) {
                        App.agenda.abrirLembreteAgenda();
                        return;
                    }

                    App.agenda.renderizarDetalhesAgenda(info);
                }
            });

            calendar.render();
            App.state.agendaCalendar = calendar;
        },

        atualizarFiltrosCalendario: function () {
            if (!App.state.agendaCalendar || typeof App.state.agendaCalendar.refetchEvents !== 'function') {
                return;
            }

            App.agenda.resetarDetalhesAgenda();
            App.state.agendaCalendar.refetchEvents();
        },

        init: function () {
            const $accessWarning = $('#agenda-access-warning');

            if ($accessWarning.length > 0 && String($accessWarning.data('defaultText') || '').trim() === '') {
                $accessWarning.data('defaultText', $.trim($accessWarning.text()));
            }

            $(document).on('click', '#agenda-profile-reminder', function (event) {
                if (event.target === this) {
                    App.core.fecharPopupCustomizado('#agenda-profile-reminder');

                    if (App.state.agendaPendingEventData) {
                        App.agenda.renderizarDetalhesAgenda(App.state.agendaPendingEventData);
                    }
                }
            });

            $(document).on('click', '#agenda-login-reminder', function (event) {
                if (event.target === this) {
                    App.core.fecharPopupCustomizado('#agenda-login-reminder');

                    if (App.state.agendaPendingEventData) {
                        App.agenda.renderizarDetalhesAgenda(App.state.agendaPendingEventData);
                    }
                }
            });

            $(document).on('click', '[data-close-popup="#agenda-login-reminder"], [data-close-popup="#agenda-profile-reminder"]', function () {
                const selector = String($(this).data('closePopup') || '');

                App.core.fecharPopupCustomizado(selector);

                if (App.state.agendaPendingEventData) {
                    App.agenda.renderizarDetalhesAgenda(App.state.agendaPendingEventData);
                }
            });

            $(document).on('change', 'input[data-person-choice="1"]', function () {
                if (!$(this).is(':checked')) {
                    return;
                }
            });

            $(document).on('click', '#agenda-details-modal-close', function () {
                App.agenda.fecharModalDetalhesHorario();
            });

            $(document).on('click', '#agenda-details-modal', function (event) {
                if (event.target === this) {
                    App.agenda.fecharModalDetalhesHorario();
                }
            });

            $(document).on('change', '#agenda-special-schedule-linked-person', function () {
                const $selected = $(this).find('option:selected');

                if (!$selected.length || String($selected.val() || '').trim() === '') {
                    $('#agenda-special-name').val('').prop('readonly', false);
                    $('#agenda-special-cpf').val('').prop('readonly', false);
                    $('#agenda-special-schedule-birth-date').val('').prop('readonly', false);
                    return;
                }

                App.agenda.preencherPessoaEventoEspecial($selected);
            });

            $(document).on('click', '[data-agenda-cancel-booking]', function () {
                const bookingId = String($(this).data('agendaCancelBooking') || '0');

                if (bookingId === '0') {
                    return;
                }

                 if (!window.confirm('Deseja realmente cancelar este agendamento?')) {
                    return;
                }

                $.ajax({
                    url: App.core.buildUrl('/agenda/cancelar'),
                    method: 'POST',
                    dataType: 'json',
                    data: { agendamento_id: bookingId },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível cancelar o agendamento.'));
                        return;
                    }

                    if (App.state.agendaCalendar && typeof App.state.agendaCalendar.refetchEvents === 'function') {
                        App.state.agendaCalendar.refetchEvents();
                    }

                    if (App.state.agendaPendingEventData) {
                        const refreshInfo = App.state.agendaPendingEventData;
                        window.setTimeout(function () {
                            const refreshedEvent = App.agenda.encontrarEventoAgendaPorOcorrencia(
                                String(refreshInfo.event.id || ''),
                                String((((refreshInfo.event || {}).extendedProps || {}).occurrence_start) || '')
                            );

                            if (refreshedEvent) {
                                App.agenda.renderizarDetalhesAgenda({ event: refreshedEvent });
                            }
                        }, 350);
                    }
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                });
            });

            $(document).on('click', '[data-agenda-filter-mode]', function () {
                const $button = $(this);
                const $form = $('#agenda-calendar-filter-form');
                const $branch = $button.closest('[data-agenda-filter-branch]');
                const mode = String($(this).data('agendaFilterMode') || '').trim().toLowerCase();

                if (mode !== 'local' && mode !== 'modalidade') {
                    return;
                }

                const shouldCollapse = $button.hasClass('is-active');
                $form.find('[data-agenda-filter-mode]').removeClass('is-active').attr('aria-expanded', 'false');
                $form.find('[data-agenda-filter-panel]').addClass('hidden');
                $form.find('[data-agenda-filter-kind]').removeClass('is-active').prop('hidden', false);
                $('#agenda-local-filter, #agenda-modality-filter').val('0');

                if (shouldCollapse) {
                    $('#agenda-filter-mode').val('');
                    $('[data-agenda-filter-status]').removeClass('is-selection-complete').text('Selecione por onde deseja começar.');
                } else {
                    $('#agenda-filter-mode').val(mode);
                    $button.addClass('is-active').attr('aria-expanded', 'true');
                    $branch.find('[data-agenda-filter-panel="' + mode + '"]').first().removeClass('hidden');
                    $('[data-agenda-filter-status]').removeClass('is-selection-complete').text(mode === 'local' ? 'Escolha um local.' : 'Escolha uma modalidade.');
                }

                App.agenda.atualizarFiltrosCalendario();
            });

            $(document).on('click', '[data-agenda-filter-kind]', function () {
                const $button = $(this);
                const $branch = $button.closest('[data-agenda-filter-branch]');
                const branchMode = String($branch.data('agendaFilterBranch') || '');
                const kind = String($(this).data('agendaFilterKind') || '').trim().toLowerCase();
                const value = String($(this).data('agendaFilterValue') || '0');

                if (kind !== 'local' && kind !== 'modalidade') {
                    return;
                }

                $branch.find('[data-agenda-filter-kind="' + kind + '"]').removeClass('is-active');
                $button.addClass('is-active');

                let combinations = [];
                try {
                    combinations = JSON.parse($('#agenda-schedule-filter-combinations').text() || '[]');
                } catch (error) {
                    combinations = [];
                }

                if (kind === 'local') {
                    $('#agenda-local-filter').val(value);
                    const compatible = combinations.filter(function (item) {
                        return String(item.location_id) === value;
                    }).map(function (item) { return String(item.modality_id); });
                    $branch.find('[data-agenda-filter-kind="modalidade"]').each(function () {
                        $(this).prop('hidden', compatible.indexOf(String($(this).data('agendaFilterValue'))) === -1);
                    });
                    if (branchMode === 'local' || compatible.indexOf(String($('#agenda-modality-filter').val())) === -1) {
                        $('#agenda-modality-filter').val('0');
                        $branch.find('[data-agenda-filter-kind="modalidade"]').removeClass('is-active');
                    }
                    $branch.find('.agenda-filter-dependent[data-agenda-filter-panel="modalidade"]').removeClass('hidden');
                    $('[data-agenda-filter-status]').removeClass('is-selection-complete').text('Escolha uma modalidade disponível neste local.');
                } else {
                    $('#agenda-modality-filter').val(value);
                    const compatible = combinations.filter(function (item) {
                        return String(item.modality_id) === value;
                    }).map(function (item) { return String(item.location_id); });
                    $branch.find('[data-agenda-filter-kind="local"]').each(function () {
                        $(this).prop('hidden', compatible.indexOf(String($(this).data('agendaFilterValue'))) === -1);
                    });
                    if (branchMode === 'modalidade' || compatible.indexOf(String($('#agenda-local-filter').val())) === -1) {
                        $('#agenda-local-filter').val('0');
                        $branch.find('[data-agenda-filter-kind="local"]').removeClass('is-active');
                    }
                    $branch.find('.agenda-filter-dependent[data-agenda-filter-panel="local"]').removeClass('hidden');
                    $('[data-agenda-filter-status]').removeClass('is-selection-complete').text('Escolha um local que ofereça esta modalidade.');
                }

                if (Number($('#agenda-local-filter').val()) > 0 && Number($('#agenda-modality-filter').val()) > 0) {
                    const locationLabel = String($branch.find('[data-agenda-filter-kind="local"].is-active').first().data('agendaFilterLabel') || '').trim();
                    const modalityLabel = String($branch.find('[data-agenda-filter-kind="modalidade"].is-active').first().data('agendaFilterLabel') || '').trim();
                    $('[data-agenda-filter-status]')
                        .addClass('is-selection-complete')
                        .text((modalityLabel + ' - ' + locationLabel).toLocaleUpperCase('pt-BR'));
                }
                App.agenda.atualizarFiltrosCalendario();
            });

            App.agenda.iniciarCalendario();
            App.agenda.atualizarPessoasAgendamento();
        }
    });

    window.App = App;
}(window, window.jQuery));
