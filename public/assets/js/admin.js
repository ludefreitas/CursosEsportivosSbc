(function (window, $) {
    const App = window.App || {};
    const adminUrl = function (path) {
        const $host = $('[data-admin-section-host]');
        const basePath = String($host.data('adminBasePath') || '/admin').replace(/\/$/, '');
        const normalizedPath = String(path || '');

        if (normalizedPath.indexOf('/api/admin/') === 0) {
            const apiPath = basePath === '/professor'
                ? normalizedPath.replace('/api/admin/', '/api/professor/')
                : normalizedPath;

            return App.core.buildUrl(apiPath);
        }

        return App.core.buildUrl(basePath + normalizedPath.replace(/^\/admin/, ''));
    };

    App.admin = Object.assign(App.admin || {}, {
        iniciarSecoesAdmin: function () {
            const $buttons = $('[data-admin-nav-target]');
            const defaultSection = 'inicio';
            const $host = $('[data-admin-section-host]');
            const sectionsUrl = String($host.data('adminSectionUrl') || '');

            if ($buttons.length === 0 || $host.length === 0 || sectionsUrl === '') {
                return;
            }

            function hydrateDynamicSection() {
                $('#popup-todas-paginas').trigger('change');
                $('select[data-sexo-select="1"]').trigger('change');
                syncDailyBookingSpaceOptions();
                initAdminAgendaCalendar();
                if (typeof App.admin.montarPreviaConteudoHome === 'function') {
                    App.admin.montarPreviaConteudoHome();
                }
            }

            function syncActiveButton(target) {
                const normalizedTarget = String(target || '').trim();

                $buttons.each(function () {
                    const isActive = String($(this).data('adminNavTarget') || '') === normalizedTarget;
                    $(this).toggleClass('is-active', isActive);
                });
            }

            function updateHash(target) {
                if (window.history && typeof window.history.replaceState === 'function') {
                    window.history.replaceState({}, document.title, '#admin-' + target);
                }
            }

            function currentAgendaFilters() {
                const $weeklyForm = $('#admin-agenda-filter-form');
                const $dailyForm = $('#admin-daily-bookings-filter-form');

                return {
                    local_treino_id: String($weeklyForm.find('select[name="local_treino_id"]').val() || '0'),
                    modalidade_id: String($weeklyForm.find('select[name="modalidade_id"]').val() || '0'),
                    data_agendamento: String($dailyForm.find('input[name="data_agendamento"]').val() || ''),
                    agendamento_local_treino_id: String($dailyForm.find('select[name="agendamento_local_treino_id"]').val() || '0'),
                    agendamento_espaco_treino_id: String($dailyForm.find('select[name="agendamento_espaco_treino_id"]').val() || '0')
                };
            }

            function currentAdminAgendaCalendarFilters() {
                const $form = $('#admin-agenda-calendar-filter-form');

                return {
                    local_treino_id: String($form.find('input[name="local_treino_id"]').val() || '0'),
                    modalidade_id: String($form.find('input[name="modalidade_id"]').val() || '0')
                };
            }

            function syncDailyBookingSpaceOptions() {
                const $form = $('#admin-daily-bookings-filter-form');
                const $location = $form.find('select[name="agendamento_local_treino_id"]');
                const $space = $form.find('select[name="agendamento_espaco_treino_id"]');
                const locationId = String($location.val() || '0');

                if ($form.length === 0 || $space.length === 0) {
                    return;
                }

                $space.find('option[data-local-treino-id]').each(function () {
                    const optionLocationId = String($(this).data('localTreinoId') || '0');
                    const isAvailable = locationId === '0' || optionLocationId === locationId;

                    $(this).prop('disabled', !isAvailable).prop('hidden', !isAvailable);
                });

                const $selectedOption = $space.find('option:selected');

                if ($selectedOption.is(':disabled')) {
                    $space.val('0');
                }
            }

            function getDailyBookingsModal() {
                return $('#admin-daily-bookings-modal');
            }

            function openDailyBookingsModal() {
                const $modal = getDailyBookingsModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeDailyBookingsModal() {
                getDailyBookingsModal().addClass('hidden').attr('aria-hidden', 'true');
            }

            function getOccurrenceModal() {
                return $('#admin-booking-occurrence-modal');
            }

            function getOccurrenceModalContent() {
                return $('#admin-booking-occurrence-modal-content');
            }

            function closeOccurrenceModal() {
                const $modal = getOccurrenceModal();
                const $content = getOccurrenceModalContent();

                if ($modal.length === 0) {
                    return;
                }

                $content.html('');
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            }

            function openOccurrenceModal() {
                const $modal = getOccurrenceModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function loadOccurrenceAttendance(scheduleId, startDateTime) {
                const $content = getOccurrenceModalContent();

                if ($content.length === 0) {
                    App.core.abrirPopup('erro', 'O modal da chamada administrativa não está disponível nesta tela.');
                    return;
                }

                $content.html('<p class="muted">Carregando chamada da ocorrência...</p>');
                openOccurrenceModal();

                $.ajax({
                    url: adminUrl('/admin/agendamentos/ocorrencia'),
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        horario_id: String(scheduleId || '0'),
                        data_hora_inicio: String(startDateTime || '')
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar a chamada desta ocorrência.'));
                        closeOccurrenceModal();
                        return;
                    }

                    $content.html(String(response.html || ''));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    closeOccurrenceModal();
                    App.core.abrirPopup('erro', erro.mensagem);
                });
            }

            function initAdminAgendaCalendar() {
                const calendarEl = document.getElementById('admin-agenda-calendar');
                const $filterForm = $('#admin-agenda-calendar-filter-form');

                if (!calendarEl || $filterForm.length === 0 || typeof FullCalendar === 'undefined') {
                    if (App.state.adminAgendaCalendar && typeof App.state.adminAgendaCalendar.destroy === 'function') {
                        App.state.adminAgendaCalendar.destroy();
                        App.state.adminAgendaCalendar = null;
                    }

                    return;
                }

                if (App.state.adminAgendaCalendar && typeof App.state.adminAgendaCalendar.destroy === 'function') {
                    App.state.adminAgendaCalendar.destroy();
                }

                App.state.adminAgendaCalendar = new FullCalendar.Calendar(calendarEl, {
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
                        url: adminUrl('/api/admin/agenda/eventos'),
                        extraParams: function () {
                            return currentAdminAgendaCalendarFilters();
                        }
                    },
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
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
                    },
                    eventClick: function (info) {
                        const props = info.event.extendedProps || {};

                        if (props.is_special === true) {
                            let details = ''
                                + '<strong>Horário especial:</strong> ' + App.core.escapeHtml(String(info.event.title || 'Horário especial'))
                                + '<br><strong>Período:</strong> ' + App.core.escapeHtml(formatCalendarDateTime(String(info.event.startStr || '')))
                                + ' até ' + App.core.escapeHtml(formatCalendarDateTime(String(info.event.endStr || '')))
                                + '<br><strong>Local:</strong> ' + App.core.escapeHtml(String(props.local || 'A definir'))
                                + '<br><strong>Espaço:</strong> ' + App.core.escapeHtml(String(props.espaco || 'A definir'))
                                + '<br><strong>Modalidade:</strong> ' + App.core.escapeHtml(String(props.modalidade || 'Sem modalidade'))
                                + '<br><strong>Vagas:</strong> Geral ' + App.core.escapeHtml(String(props.vagas_geral || 0))
                                + ' | PCD ' + App.core.escapeHtml(String(props.vagas_pcd || 0))
                                + ' | PVS ' + App.core.escapeHtml(String(props.vagas_pvs || 0))
                                + ' | PLM ' + App.core.escapeHtml(String(props.vagas_plm || 0))
                                + '<br><strong>Agenda para inscrições abre em:</strong> ' + App.core.escapeHtml(formatCalendarDateTime(String(props.data_publicacao_inicio || '')))
                                + '<br><strong>Agenda para inscrições fecha em:</strong> ' + App.core.escapeHtml(formatCalendarDateTime(String(props.data_publicacao_fim || '')))
                                + '<br><strong>Status:</strong> ' + (Number(props.ativo || 0) === 1 ? 'Ativo' : 'Inativo');

                            if (String(props.special_description || '').trim() !== '') {
                                details += '<br><strong>Descrição:</strong> ' + App.core.escapeHtml(String(props.special_description || ''));
                            }

                            App.core.abrirPopupHtml('info', details);
                            return;
                        }

                        loadOccurrenceAttendance(
                            info.event.id,
                            String(props.occurrence_start || info.event.startStr || '')
                        );
                    }
                });

                App.state.adminAgendaCalendar.render();
            }

            function refetchAdminAgendaCalendar() {
                if (!App.state.adminAgendaCalendar || typeof App.state.adminAgendaCalendar.refetchEvents !== 'function') {
                    return;
                }

                App.state.adminAgendaCalendar.refetchEvents();
            }

            function getJustificationModal() {
                return $('#admin-booking-justification-modal');
            }

            function getJustificationForm() {
                return $('#admin-booking-justification-form');
            }

            function getBookingStatusGroup(bookingId) {
                return $('[data-booking-status-group="' + String(bookingId || '') + '"]');
            }

            function getBookingRow(bookingId) {
                return $('[data-booking-row="' + String(bookingId || '') + '"]');
            }

            function getCurrentAdminName() {
                const $panel = $('[data-admin-section="agenda"]').first();
                return String($panel.data('adminCurrentCaller') || '').trim();
            }

            function formatCalendarDateTime(value) {
                const raw = String(value || '').trim();

                if (raw === '') {
                    return '-';
                }

                const date = new Date(raw);

                if (Number.isNaN(date.getTime())) {
                    return raw;
                }

                return date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function getStatusMeta(status) {
                const normalizedStatus = String(status || '').trim();

                if (normalizedStatus === 'presente') {
                    return { short: 'P', label: 'Compareceu', chipClass: 'admin-booking-status-presente' };
                }

                if (normalizedStatus === 'falta') {
                    return { short: 'X', label: 'Ausente', chipClass: 'admin-booking-status-falta' };
                }

                if (normalizedStatus === 'justificado') {
                    return { short: 'J', label: 'Justificado', chipClass: 'admin-booking-status-justificado' };
                }

                return { short: '-', label: 'Agendado', chipClass: 'admin-booking-status-agendado' };
            }

            function renderBookingRowUpdate(bookingId, status, justificationReason) {
                const $row = getBookingRow(bookingId);
                const meta = getStatusMeta(status);
                const normalizedReason = String(justificationReason || '').trim();
                const callerName = getCurrentAdminName();

                if ($row.length === 0) {
                    return;
                }

                $row.find('[data-booking-short-status="1"] strong').text(meta.short);
                $row.find('[data-booking-status-chip="1"]')
                    .removeClass('admin-booking-status-agendado admin-booking-status-presente admin-booking-status-falta admin-booking-status-justificado admin-booking-status-cancelado')
                    .addClass(meta.chipClass)
                    .text(meta.label);
                $row.find('[data-booking-caller-cell="1"]').text(callerName !== '' ? callerName : '-');
                $row.find('[data-booking-justification-cell="1"]').text(normalizedReason);
                getBookingStatusGroup(bookingId).find('[data-status="justificado"]').attr('data-current-justification', normalizedReason);
            }

            function captureBookingRowVisualState(bookingId) {
                const $row = getBookingRow(bookingId);
                const $chip = $row.find('[data-booking-status-chip="1"]').first();

                return {
                    short: String($row.find('[data-booking-short-status="1"] strong').text() || ''),
                    chipText: String($chip.text() || ''),
                    chipClass: String($chip.attr('class') || ''),
                    caller: String($row.find('[data-booking-caller-cell="1"]').text() || ''),
                    justification: String($row.find('[data-booking-justification-cell="1"]').text() || ''),
                    justificationData: String(getBookingStatusGroup(bookingId).find('[data-status="justificado"]').attr('data-current-justification') || '')
                };
            }

            function restoreBookingRowVisualState(bookingId, previousVisual) {
                const $row = getBookingRow(bookingId);
                const $chip = $row.find('[data-booking-status-chip="1"]').first();

                if ($row.length === 0 || !previousVisual) {
                    return;
                }

                $row.find('[data-booking-short-status="1"] strong').text(previousVisual.short || '');
                $chip.attr('class', previousVisual.chipClass || 'chip admin-booking-status-chip');
                $chip.text(previousVisual.chipText || '');
                $row.find('[data-booking-caller-cell="1"]').text(previousVisual.caller || '');
                $row.find('[data-booking-justification-cell="1"]').text(previousVisual.justification || '');
                getBookingStatusGroup(bookingId).find('[data-status="justificado"]').attr('data-current-justification', previousVisual.justificationData || '');
            }

            function syncBookingStatusGroup(bookingId, activeStatus) {
                const $group = getBookingStatusGroup(bookingId);

                if ($group.length === 0) {
                    return;
                }

                $group.find('.admin-booking-status-checkbox').each(function () {
                    const $input = $(this);
                    $input.prop('checked', String($input.data('status') || '') === String(activeStatus || ''));
                });
                $group.attr('data-current-status', String(activeStatus || ''));
            }

            function getCurrentBookingStatus(bookingId) {
                const $group = getBookingStatusGroup(bookingId);
                return String($group.attr('data-current-status') || '').trim();
            }

            function disableBookingStatusGroup(bookingId, disabled) {
                const $group = getBookingStatusGroup(bookingId);

                if ($group.length === 0) {
                    return;
                }

                $group.toggleClass('is-busy', Boolean(disabled));
                $group.find('.admin-booking-status-checkbox').prop('disabled', Boolean(disabled));
            }

            function closeJustificationModal() {
                const $modal = getJustificationModal();
                const $form = getJustificationForm();

                if ($modal.length === 0 || $form.length === 0) {
                    return;
                }

                $form[0].reset();
                $form.find('input[name="agendamento_id"]').val('');
                $('#admin-booking-justification-person, #admin-booking-justification-date').text('-');
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            }

            function openJustificationModal(bookingId, reason, personName, bookingDate) {
                const $modal = getJustificationModal();
                const $form = getJustificationForm();

                if ($modal.length === 0 || $form.length === 0) {
                    App.core.abrirPopup('erro', 'O modal de justificativa não está disponível nesta tela.');
                    return;
                }

                $form.find('input[name="agendamento_id"]').val(String(bookingId || ''));
                $form.find('input[name="justificativa_motivo"]').val(String(reason || ''));
                $('#admin-booking-justification-person').text(String(personName || '-'));
                $('#admin-booking-justification-date').text(String(bookingDate || '-'));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                $form.find('input[name="justificativa_motivo"]').trigger('focus');
            }

            function submitBookingAttendanceStatus(payload) {
                const formData = new FormData();
                const bookingId = String(payload.bookingId || '0');
                const status = String(payload.status || '');
                const previousStatus = getCurrentBookingStatus(bookingId);
                const previousVisual = captureBookingRowVisualState(bookingId);

                formData.append('agendamento_id', bookingId);
                formData.append('status', status);

                if (payload.justificationReason) {
                    formData.append('justificativa_motivo', String(payload.justificationReason));
                }

                syncBookingStatusGroup(bookingId, status);
                disableBookingStatusGroup(bookingId, true);

                $.ajax({
                    url: adminUrl('/admin/agendamentos/presenca'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    suppressGlobalLoading: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a chamada.'));
                        return;
                    }

                    renderBookingRowUpdate(bookingId, status, payload.justificationReason || '');
                }).fail(function (xhr) {
                    syncBookingStatusGroup(bookingId, previousStatus);
                    restoreBookingRowVisualState(bookingId, previousVisual);
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    disableBookingStatusGroup(bookingId, false);
                });
            }

            function activateSection(target, extraParams, options) {
                const normalizedTarget = String(target || '').trim();
                const requestData = Object.assign({ nome: normalizedTarget }, extraParams || {});
                const requestOptions = Object.assign({ suppressGlobalLoading: false }, options || {});

                if (normalizedTarget === '') {
                    return;
                }

                syncActiveButton(normalizedTarget);
                $host.attr('data-admin-loading', '1');
                if (requestOptions.suppressGlobalLoading !== true) {
                    $host.html('<section class="admin-section-panel"><article class="content-card"><p class="muted">Carregando conteúdo...</p></article></section>');
                }

                $.ajax({
                    url: sectionsUrl,
                    method: 'GET',
                    dataType: 'json',
                    data: requestData,
                    suppressGlobalLoading: requestOptions.suppressGlobalLoading === true
                })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar esta seção agora.'));
                            return;
                        }

                        $host.html(String(response.html || ''));
                        hydrateDynamicSection();
                        updateHash(normalizedTarget);

                        if (normalizedTarget === 'migracao-atestados' && App.state.healthMigrationFocus) {
                            const focusState = App.state.healthMigrationFocus;
                            const $focusField = $host.find('[name="' + String(focusState.name || '') + '"]').filter(':visible').last();

                            if ($focusField.length > 0) {
                                const input = $focusField[0];
                                input.focus();
                                if (typeof input.setSelectionRange === 'function') {
                                    const position = Math.min(Number(focusState.position || 0), String($focusField.val() || '').length);
                                    input.setSelectionRange(position, position);
                                }
                            }
                            App.state.healthMigrationFocus = null;
                        }

                        if (normalizedTarget === 'agenda' && String(requestData.abrir_resultado_agendamentos || '0') === '1') {
                            openDailyBookingsModal();
                        }
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    })
                    .always(function () {
                        $host.removeAttr('data-admin-loading');
                    });
            }

            App.admin.activateSection = activateSection;

            $(document).on('click', '[data-admin-nav-target]', function () {
                activateSection($(this).data('adminNavTarget'));
            });

            $(document).on('submit', '#admin-agenda-filter-form', function (event) {
                event.preventDefault();

                activateSection('agenda', currentAgendaFilters());
            });

            $(document).on('change', '#admin-agenda-filter-form select[name="local_treino_id"], #admin-agenda-filter-form select[name="modalidade_id"]', function () {
                const $form = $('#admin-agenda-filter-form');

                if ($form.length === 0) {
                    return;
                }

                activateSection('agenda', currentAgendaFilters());
            });

            $(document).on('submit', '#admin-daily-bookings-filter-form', function (event) {
                event.preventDefault();
                const filters = currentAgendaFilters();
                filters.abrir_resultado_agendamentos = '1';
                activateSection('agenda', filters);
            });

            $(document).on('change', '#admin-daily-bookings-filter-form select[name="agendamento_local_treino_id"]', function () {
                $('#admin-daily-bookings-filter-form select[name="agendamento_espaco_treino_id"]').val('0');
                syncDailyBookingSpaceOptions();
            });

            $(document).on('click', '[data-admin-agenda-filter-mode]', function () {
                const $button = $(this);
                const $form = $('#admin-agenda-calendar-filter-form');
                const $branch = $button.closest('[data-admin-agenda-filter-branch]');
                const mode = String($(this).data('adminAgendaFilterMode') || '').trim().toLowerCase();

                if (mode !== 'local' && mode !== 'modalidade') {
                    return;
                }

                const shouldCollapse = $button.hasClass('is-active');
                $form.find('[data-admin-agenda-filter-mode]').removeClass('is-active').attr('aria-expanded', 'false');
                $form.find('[data-admin-agenda-filter-panel]').addClass('hidden');
                $form.find('[data-admin-agenda-filter-kind]').removeClass('is-active').prop('hidden', false);
                $('#admin-agenda-calendar-local-filter, #admin-agenda-calendar-modality-filter').val('0');

                if (shouldCollapse) {
                    $('#admin-agenda-calendar-filter-mode').val('');
                    $('[data-admin-agenda-filter-status]').removeClass('is-selection-complete').text('Selecione por onde deseja começar.');
                } else {
                    $('#admin-agenda-calendar-filter-mode').val(mode);
                    $button.addClass('is-active').attr('aria-expanded', 'true');
                    $branch.find('[data-admin-agenda-filter-panel="' + mode + '"]').first().removeClass('hidden');
                    $('[data-admin-agenda-filter-status]').removeClass('is-selection-complete').text(mode === 'local' ? 'Escolha um local.' : 'Escolha uma modalidade.');
                }

                refetchAdminAgendaCalendar();
            });

            $(document).on('click', '[data-admin-agenda-filter-kind]', function () {
                const $button = $(this);
                const $branch = $button.closest('[data-admin-agenda-filter-branch]');
                const branchMode = String($branch.data('adminAgendaFilterBranch') || '');
                const kind = String($(this).data('adminAgendaFilterKind') || '').trim().toLowerCase();
                const value = String($(this).data('adminAgendaFilterValue') || '0');

                if (kind !== 'local' && kind !== 'modalidade') {
                    return;
                }

                $branch.find('[data-admin-agenda-filter-kind="' + kind + '"]').removeClass('is-active');
                $button.addClass('is-active');

                let combinations = [];
                try {
                    combinations = JSON.parse($('#admin-agenda-schedule-filter-combinations').text() || '[]');
                } catch (error) {
                    combinations = [];
                }

                if (kind === 'local') {
                    $('#admin-agenda-calendar-local-filter').val(value);
                    const compatible = combinations.filter(function (item) { return String(item.location_id) === value; })
                        .map(function (item) { return String(item.modality_id); });
                    $branch.find('[data-admin-agenda-filter-kind="modalidade"]').each(function () {
                        $(this).prop('hidden', compatible.indexOf(String($(this).data('adminAgendaFilterValue'))) === -1);
                    });
                    if (branchMode === 'local' || compatible.indexOf(String($('#admin-agenda-calendar-modality-filter').val())) === -1) {
                        $('#admin-agenda-calendar-modality-filter').val('0');
                        $branch.find('[data-admin-agenda-filter-kind="modalidade"]').removeClass('is-active');
                    }
                    $branch.find('.agenda-filter-dependent[data-admin-agenda-filter-panel="modalidade"]').removeClass('hidden');
                    $('[data-admin-agenda-filter-status]').removeClass('is-selection-complete').text('Escolha uma modalidade disponível neste local.');
                } else {
                    $('#admin-agenda-calendar-modality-filter').val(value);
                    const compatible = combinations.filter(function (item) { return String(item.modality_id) === value; })
                        .map(function (item) { return String(item.location_id); });
                    $branch.find('[data-admin-agenda-filter-kind="local"]').each(function () {
                        $(this).prop('hidden', compatible.indexOf(String($(this).data('adminAgendaFilterValue'))) === -1);
                    });
                    if (branchMode === 'modalidade' || compatible.indexOf(String($('#admin-agenda-calendar-local-filter').val())) === -1) {
                        $('#admin-agenda-calendar-local-filter').val('0');
                        $branch.find('[data-admin-agenda-filter-kind="local"]').removeClass('is-active');
                    }
                    $branch.find('.agenda-filter-dependent[data-admin-agenda-filter-panel="local"]').removeClass('hidden');
                    $('[data-admin-agenda-filter-status]').removeClass('is-selection-complete').text('Escolha um local que ofereça esta modalidade.');
                }

                if (Number($('#admin-agenda-calendar-local-filter').val()) > 0 && Number($('#admin-agenda-calendar-modality-filter').val()) > 0) {
                    const locationLabel = String($branch.find('[data-admin-agenda-filter-kind="local"].is-active').first().data('adminAgendaFilterLabel') || '').trim();
                    const modalityLabel = String($branch.find('[data-admin-agenda-filter-kind="modalidade"].is-active').first().data('adminAgendaFilterLabel') || '').trim();
                    $('[data-admin-agenda-filter-status]')
                        .addClass('is-selection-complete')
                        .text((modalityLabel + ' - ' + locationLabel).toLocaleUpperCase('pt-BR'));
                }
                refetchAdminAgendaCalendar();
            });

            $(document).on('change', '.admin-booking-status-checkbox', function () {
                const $checkbox = $(this);

                if ($checkbox.is(':disabled')) {
                    return;
                }

                const bookingId = String($checkbox.data('bookingId') || '0');
                const status = String($checkbox.data('status') || '');
                const previousStatus = getCurrentBookingStatus(bookingId);

                if (status === 'justificado') {
                    syncBookingStatusGroup(bookingId, previousStatus);
                    openJustificationModal(
                        bookingId,
                        String($checkbox.attr('data-current-justification') || ''),
                        String($checkbox.attr('data-booking-person') || ''),
                        String($checkbox.attr('data-booking-date') || '')
                    );
                    return;
                }

                syncBookingStatusGroup(bookingId, status);
                submitBookingAttendanceStatus({
                    bookingId: bookingId,
                    status: status
                });
            });

            $(document).on('click', '#admin-booking-justification-close, #admin-booking-justification-cancel', function () {
                closeJustificationModal();
            });

            $(document).on('click', '#admin-booking-justification-modal', function (event) {
                if ($(event.target).is('#admin-booking-justification-modal')) {
                    closeJustificationModal();
                }
            });

            $(document).on('click', '#admin-booking-occurrence-close', function () {
                closeOccurrenceModal();
            });

            $(document).on('click', '#admin-booking-occurrence-modal', function (event) {
                if ($(event.target).is('#admin-booking-occurrence-modal')) {
                    closeOccurrenceModal();
                }
            });

            $(document).on('click', '#admin-daily-bookings-modal-close', function () {
                closeDailyBookingsModal();
            });

            $(document).on('click', '#admin-daily-bookings-modal', function (event) {
                if ($(event.target).is('#admin-daily-bookings-modal')) {
                    closeDailyBookingsModal();
                }
            });

            $(document).on('submit', '#admin-booking-justification-form', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const $form = $(this);
                const bookingId = String($form.find('input[name="agendamento_id"]').val() || '0');
                const reason = String($form.find('input[name="justificativa_motivo"]').val() || '').trim();

                if (reason === '') {
                    App.core.abrirPopup('erro', 'Informe o motivo da justificativa.');
                    return;
                }

                closeJustificationModal();
                submitBookingAttendanceStatus({
                    bookingId: bookingId,
                    status: 'justificado',
                    justificationReason: reason
                });
            });

            const hash = String(window.location.hash || '').replace(/^#admin-/, '').trim();

            if (hash !== '') {
                activateSection(hash);
                return;
            }

            syncActiveButton(defaultSection);
            hydrateDynamicSection();
        },

        iniciarEditorPessoaAdmin: function () {
            let currentPerson = null;

            function getDetailsPanel() {
                return $('#admin-person-details');
            }

            function getPanel() {
                return $('#admin-person-editor');
            }

            function getForm() {
                return $('#admin-person-form');
            }

            function setValue(selector, value) {
                $(selector).val(value == null ? '' : String(value));
            }

            function formatSex(value) {
                const normalized = String(value || '').trim();

                if (normalized === 'masculino') {
                    return 'Masculino';
                }

                if (normalized === 'feminino') {
                    return 'Feminino';
                }

                if (normalized !== '') {
                    return normalized;
                }

                return '-';
            }

            function formatRegistration(value) {
                return Number(value || 0) === 1 ? 'Completo' : 'Pendente';
            }

            function formatAccountStatus(person) {
                if (!person || !person.conta_id) {
                    return 'Sem conta vinculada';
                }

                return Number(person.conta_ativa || 0) === 1 ? 'Conta ativa' : 'Conta inativa';
            }

            function formatAddress(person) {
                const parts = [
                    String(person.logradouro || '').trim(),
                    String(person.numero_endereco || '').trim(),
                    String(person.complemento || '').trim(),
                    String(person.bairro || '').trim(),
                    String(person.cidade || '').trim(),
                    String(person.uf || '').trim(),
                    person.cep ? String(person.cep).replace(/(\d{5})(\d{3})/, '$1-$2') : ''
                ].filter(function (item) {
                    return item !== '';
                });

                return parts.length > 0 ? parts.join(', ') : '-';
            }

            function formatEmergency(person) {
                const parts = [
                    String(person.contato_emergencia_nome || '').trim(),
                    String(person.contato_emergencia_telefone || '').trim()
                ].filter(function (item) {
                    return item !== '';
                });

                return parts.length > 0 ? parts.join(' - ') : '-';
            }

            function formatResponsible(name, cpf) {
                const parts = [
                    String(name || '').trim(),
                    cpf ? String(cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : ''
                ].filter(function (item) {
                    return item !== '';
                });

                return parts.length > 0 ? parts.join(' - ') : '-';
            }

            function formatDeclaredConditions(person) {
                const conditions = [];

                if (Number(person.eh_pcd || 0) === 1) {
                    conditions.push('PCD');
                }

                if (Number(person.eh_pvs || 0) === 1) {
                    conditions.push('PVS');
                }

                if (Number(person.eh_plm || 0) === 1) {
                    conditions.push('PLM');
                }

                return conditions.length > 0 ? conditions.join(', ') : 'Nenhuma';
            }

            function fillDetails(person) {
                const $detailsPanel = getDetailsPanel();

                if ($detailsPanel.length === 0) {
                    App.core.abrirPopup('erro', 'O modal de consulta de pessoa não está disponível nesta tela.');
                    return;
                }

                currentPerson = person;
                $('#admin-person-details-subtitle').text('Consultando ' + String(person.nome_completo || '') + ' sem sair desta página.');
                $('#admin-person-details-full-name').text(String(person.nome_completo || '-'));
                $('#admin-person-details-cpf').text(person.cpf ? String(person.cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '-');
                $('#admin-person-details-sex').text(formatSex(person.sexo));
                $('#admin-person-details-birth-date').text(String(person.data_nascimento || '-'));
                $('#admin-person-details-registration').text(formatRegistration(person.cadastro_completo));
                $('#admin-person-details-account').text(formatAccountStatus(person));
                $('#admin-person-details-conditions').text(formatDeclaredConditions(person));
                $('#admin-person-details-certificates').text(String(person.situacao_certificados || '-'));
                $('#admin-person-details-responsible').text(String(person.nome_responsavel || '-'));
                $('#admin-person-details-phone').text(String(person.telefone_whatsapp || '-'));
                $('#admin-person-details-email').text(String(person.email || '-'));
                $('#admin-person-details-sus-card').text(String(person.numero_cartao_sus || '-'));
                $('#admin-person-details-address').text(formatAddress(person));
                $('#admin-person-details-emergency').text(formatEmergency(person));
                $('#admin-person-details-parent1').text(formatResponsible(person.responsavel1_nome, person.responsavel1_cpf));
                $('#admin-person-details-parent2').text(formatResponsible(person.responsavel2_nome, person.responsavel2_cpf));
                $detailsPanel.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeDetails() {
                const $detailsPanel = getDetailsPanel();

                if ($detailsPanel.length === 0) {
                    return;
                }

                $detailsPanel.addClass('hidden').attr('aria-hidden', 'true');
            }

            function preencherFormulario(person) {
                const $panel = getPanel();

                if ($panel.length === 0) {
                    App.core.abrirPopup('erro', 'O formulário de edição de pessoa não está disponível nesta tela.');
                    return;
                }

                setValue('#admin-person-id', person.id);
                setValue('#admin-person-full-name', person.nome_completo);
                setValue('#admin-person-cpf', person.cpf ? String(person.cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '');
                setValue('#admin-person-sexo', person.sexo || '');
                setValue('#admin-person-birth-date', person.data_nascimento || '');
                setValue('#admin-person-cadastro-completo', Number(person.cadastro_completo || 0) === 1 ? '1' : '0');
                setValue('#admin-person-phone-whatsapp', person.telefone_whatsapp || '');
                setValue('#admin-person-email', person.email || '');
                setValue('#admin-person-numero-cartao-sus', person.numero_cartao_sus || '');
                setValue('#admin-person-zip-code', person.cep ? String(person.cep).replace(/(\d{5})(\d{3})/, '$1-$2') : '');
                setValue('#admin-person-street', person.logradouro || '');
                setValue('#admin-person-address-number', person.numero_endereco || '');
                setValue('#admin-person-address-complement', person.complemento || '');
                setValue('#admin-person-neighborhood', person.bairro || '');
                setValue('#admin-person-city', person.cidade || '');
                setValue('#admin-person-state', person.uf || '');
                setValue('#admin-person-current-responsible', person.nome_responsavel || '-');
                setValue('#admin-person-emergency-contact-name', person.contato_emergencia_nome || '');
                setValue('#admin-person-emergency-contact-phone', person.contato_emergencia_telefone || '');
                setValue('#admin-person-responsavel1-nome', person.responsavel1_nome || '');
                setValue('#admin-person-responsavel1-cpf', person.responsavel1_cpf ? String(person.responsavel1_cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '');
                setValue('#admin-person-responsavel2-nome', person.responsavel2_nome || '');
                setValue('#admin-person-responsavel2-cpf', person.responsavel2_cpf ? String(person.responsavel2_cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '');
                $('#admin-person-eh-pcd').prop('checked', Number(person.eh_pcd || 0) === 1);
                $('#admin-person-eh-pvs').prop('checked', Number(person.eh_pvs || 0) === 1);
                $('#admin-person-eh-plm').prop('checked', Number(person.eh_plm || 0) === 1);
                setValue('#admin-person-reason', '');

                const hasAccount = !!person.conta_id;
                const $contaAtiva = $('#admin-person-conta-ativa');
                const $accountHint = $('#admin-person-account-hint');

                if (hasAccount) {
                    setValue('#admin-person-conta-ativa', Number(person.conta_ativa || 0) === 1 ? '1' : '0');
                    $contaAtiva.prop('disabled', false);
                    $accountHint.text('Conta vinculada encontrada. Você pode ativar ou inativar este usuário aqui.');
                } else {
                    setValue('#admin-person-conta-ativa', '0');
                    $contaAtiva.prop('disabled', true);
                    $accountHint.text('Esta pessoa ainda não possui conta de usuário vinculada.');
                }

                $('#admin-person-editor-subtitle').text('Editando ' + String(person.nome_completo || '') + ' sem sair desta página.');
                $panel.removeClass('hidden').attr('aria-hidden', 'false');
                $('#admin-person-sexo').trigger('change');
            }

            $(document).on('click', '[data-person-edit="1"]', function () {
                const personId = Number($(this).data('personId') || 0);

                if (!personId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar a pessoa selecionada.');
                    return;
                }

                $.getJSON(adminUrl('/admin/pessoas/detalhe'), { id: personId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.person) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os dados desta pessoa.'));
                            return;
                        }

                        fillDetails(response.person);
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-person-details-close, #admin-person-details-dismiss', function () {
                closeDetails();
            });

            $(document).on('click', '#admin-person-details', function (event) {
                if (event.target === this) {
                    closeDetails();
                }
            });

            $(document).on('click', '#admin-person-details-edit', function () {
                if (!currentPerson) {
                    App.core.abrirPopup('erro', 'Não foi possível localizar os dados desta pessoa para edição.');
                    return;
                }

                closeDetails();
                preencherFormulario(currentPerson);
            });

            $(document).on('click', '#admin-person-editor-close, #admin-person-editor-cancel', function () {
                const $panel = getPanel();
                const $form = getForm();

                if ($panel.length === 0 || $form.length === 0) {
                    return;
                }

                $panel.addClass('hidden').attr('aria-hidden', 'true');
                $form[0].reset();
            });

            $(document).on('click', '#admin-person-editor', function (event) {
                const $panel = getPanel();
                const $form = getForm();

                if ($panel.length === 0 || $form.length === 0) {
                    return;
                }

                if (event.target === this) {
                    $panel.addClass('hidden').attr('aria-hidden', 'true');
                    $form[0].reset();
                }
            });

            $(document).on('submit', '#admin-person-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                if ($('#admin-person-conta-ativa').is(':disabled')) {
                    formData.set('conta_ativa', '0');
                }

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.person) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar as alterações.'));
                        return;
                    }

                    const person = response.person;
                    const $row = $('tr[data-person-row="1"][data-person-id="' + String(person.id) + '"]');

                    if ($row.length > 0) {
                        $row.find('[data-person-edit="1"]').text(String(person.nome_completo || ''));
                        $row.find('td').eq(1).text(person.cpf ? String(person.cpf).replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : '');
                        $row.find('[data-person-cadastro]').text(Number(person.cadastro_completo || 0) === 1 ? 'Completo' : 'Pendente');
                    }

                    currentPerson = person;
                    $('#admin-person-editor').addClass('hidden').attr('aria-hidden', 'true');
                    if ($form.length > 0 && $form[0]) {
                        $form[0].reset();
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Dados atualizados com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('click', '[data-certificate-status-alert="1"]', function (event) {
                event.preventDefault();
                event.stopPropagation();

                App.core.abrirPopup(
                    String($(this).data('alertLevel') || 'erro'),
                    String($(this).data('alertMessage') || 'Não foi possível carregar o aviso deste certificado.')
                );
            });
        },

        iniciarConsultaUsuariosAdmin: function () {
            function formatCpf(value) {
                const digits = String(value || '').replace(/\D+/g, '');

                if (digits.length !== 11) {
                    return digits !== '' ? digits : '-';
                }

                return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }

            function formatSex(value) {
                const normalized = String(value || '').trim();

                if (normalized === 'masculino') {
                    return 'Masculino';
                }

                if (normalized === 'feminino') {
                    return 'Feminino';
                }

                return normalized !== '' ? normalized : '-';
            }

            function formatRegistration(value) {
                return Number(value || 0) === 1 ? 'Completo' : 'Pendente';
            }

            function formatDateTime(value) {
                const raw = String(value || '').trim();

                if (raw === '') {
                    return '-';
                }

                const normalized = raw.replace(' ', 'T');
                const date = new Date(normalized);

                if (Number.isNaN(date.getTime())) {
                    return raw;
                }

                return date.toLocaleString('pt-BR');
            }

            function formatRoles(roles) {
                if (!Array.isArray(roles) || roles.length === 0) {
                    return 'Sem papel';
                }

                return roles.map(function (role) {
                    return String((role && role.nome) || '').trim();
                }).filter(function (name) {
                    return name !== '';
                }).join(', ') || 'Sem papel';
            }

            function getDetailsModal() {
                return $('#admin-user-details-modal');
            }

            function getDependentsModal() {
                return $('#admin-user-dependents-modal');
            }

            function closeDetailsModal() {
                getDetailsModal().addClass('hidden').attr('aria-hidden', 'true');
            }

            function closeDependentsModal() {
                getDependentsModal().addClass('hidden').attr('aria-hidden', 'true');
            }

            function openDetailsModal() {
                getDetailsModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            function openDependentsModal() {
                getDependentsModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            function fillDetails(user) {
                $('#admin-user-details-subtitle').text('Consultando os dados de ' + String(user.nome_completo || '') + ' sem sair desta página.');
                $('#admin-user-details-name').text(String(user.nome_completo || '-'));
                $('#admin-user-details-cpf').text(formatCpf(user.cpf));
                $('#admin-user-details-email').text(String(user.email || '-'));
                $('#admin-user-details-phone').text(String(user.telefone_whatsapp || '-'));
                $('#admin-user-details-sex').text(formatSex(user.sexo));
                $('#admin-user-details-birth-date').text(String(user.data_nascimento || '-'));
                $('#admin-user-details-registration').text(formatRegistration(user.cadastro_completo));
                $('#admin-user-details-account-status').text(Number(user.conta_ativa || 0) === 1 ? 'Conta ativa' : 'Conta inativa');
                $('#admin-user-details-roles').text(formatRoles(user.roles));
                $('#admin-user-details-dependents-count').text(String(user.total_dependentes || 0));
                $('#admin-user-details-created-at').text(formatDateTime(user.conta_criada_em));
                $('#admin-user-details-last-access').text(formatDateTime(user.ultimo_acesso_em));
                $('#admin-user-details-last-ip').text(String(user.ultimo_acesso_ip || '-'));
            }

            function renderDependents(payload) {
                const user = payload && payload.user ? payload.user : {};
                const dependents = Array.isArray(payload && payload.dependents) ? payload.dependents : [];
                const $content = $('#admin-user-dependents-content');

                $('#admin-user-dependents-subtitle').text('Dependentes vinculados a ' + String(user.nome_completo || 'este usuário') + '.');

                if (dependents.length === 0) {
                    $content.html('<p class="muted">Este usuário não possui dependentes vinculados no momento.</p>');
                    return;
                }

                const rows = dependents.map(function (dependent) {
                    const registration = Number(dependent.cadastro_completo || 0) === 1 ? 'Completo' : 'Pendente';
                    const since = String(dependent.data_inicio || '').trim() || '-';
                    const note = String(dependent.observacoes || '').trim() || '-';

                    return '' +
                        '<tr>' +
                            '<td>' + App.core.escapeHtml(String(dependent.nome_completo || '-')) + '</td>' +
                            '<td>' + App.core.escapeHtml(formatCpf(dependent.cpf)) + '</td>' +
                            '<td>' + App.core.escapeHtml(String(dependent.data_nascimento || '-')) + '</td>' +
                            '<td>' + App.core.escapeHtml(registration) + '</td>' +
                            '<td>' + App.core.escapeHtml(since) + '</td>' +
                            '<td>' + App.core.escapeHtml(note) + '</td>' +
                        '</tr>';
                }).join('');

                $content.html('' +
                    '<div class="admin-user-dependent-summary">' +
                        '<p><strong>Usuário:</strong> ' + App.core.escapeHtml(String(user.nome_completo || '-')) + '</p>' +
                        '<p><strong>Total de dependentes:</strong> ' + App.core.escapeHtml(String(dependents.length)) + '</p>' +
                    '</div>' +
                    '<div class="table-wrap">' +
                        '<table class="data-table">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th>Nome</th>' +
                                    '<th>CPF</th>' +
                                    '<th>Nascimento</th>' +
                                    '<th>Cadastro</th>' +
                                    '<th>Vínculo desde</th>' +
                                    '<th>Observações</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>' + rows + '</tbody>' +
                        '</table>' +
                    '</div>');
            }

            $(document).on('click', '[data-admin-user-view="1"]', function () {
                const accountId = Number($(this).data('accountId') || 0);

                if (!accountId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o usuário selecionado.');
                    return;
                }

                $.getJSON(adminUrl('/admin/usuarios/detalhe'), { id: accountId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.user) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os dados deste usuário.'));
                            return;
                        }

                        fillDetails(response.user);
                        openDetailsModal();
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '[data-admin-user-dependents="1"]', function () {
                const accountId = Number($(this).data('accountId') || 0);

                if (!accountId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o usuário selecionado.');
                    return;
                }

                $('#admin-user-dependents-content').html('<p class="muted">Carregando dependentes...</p>');
                openDependentsModal();

                $.getJSON(adminUrl('/admin/usuarios/dependentes'), { conta_id: accountId })
                    .done(function (response) {
                        if (!response || response.success === false) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os dependentes deste usuário.'));
                            closeDependentsModal();
                            return;
                        }

                        renderDependents(response);
                    })
                    .fail(function (xhr) {
                        closeDependentsModal();
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-user-details-close, #admin-user-details-dismiss', function () {
                closeDetailsModal();
            });

            $(document).on('click', '#admin-user-dependents-close, #admin-user-dependents-dismiss', function () {
                closeDependentsModal();
            });

            $(document).on('click', '#admin-user-details-modal', function (event) {
                if (event.target === this) {
                    closeDetailsModal();
                }
            });

            $(document).on('click', '#admin-user-dependents-modal', function (event) {
                if (event.target === this) {
                    closeDependentsModal();
                }
            });
        },

        iniciarGerenciamentoPapeisAdmin: function () {
            function formatDateTime(value) {
                const raw = String(value || '').trim();

                if (raw === '') {
                    return '-';
                }

                const normalized = raw.replace(' ', 'T');
                const date = new Date(normalized);

                if (Number.isNaN(date.getTime())) {
                    return raw;
                }

                return date.toLocaleString('pt-BR');
            }

            function formatRolesSummary(roles) {
                if (!Array.isArray(roles) || roles.length === 0) {
                    return 'Sem papel';
                }

                return roles.map(function (role) {
                    return String((role && role.nome) || '').trim();
                }).filter(function (value) {
                    return value !== '';
                }).join(', ') || 'Sem papel';
            }

            function getModal() {
                return $('#admin-user-roles-modal');
            }

            function getForm() {
                return $('#admin-user-roles-form');
            }

            function closeModal() {
                const $modal = getModal();
                const $form = getForm();

                if ($modal.length === 0 || $form.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $form[0].reset();
                $form.find('input[type="checkbox"][data-role-id]').prop('checked', false).prop('disabled', false).closest('label').removeClass('is-disabled');
            }

            function openModal() {
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            function fillForm(user) {
                const roleIds = Array.isArray(user.roles) ? user.roles.map(function (role) {
                    return String((role && role.id) || '');
                }) : [];
                const blockReason = String(user.role_assignment_block_reason || '').trim();

                $('#admin-user-roles-account-id').val(String(user.conta_id || ''));
                $('#admin-user-roles-account-name').text(String(user.nome_completo || '-'));
                $('#admin-user-roles-last-access').text(formatDateTime(user.ultimo_acesso_em));
                $('#admin-user-roles-subtitle').text('Defina os papéis ativos de ' + String(user.nome_completo || 'este usuário') + '.');
                $('#admin-user-roles-status').text(blockReason !== '' ? 'Bloqueado: ' + blockReason : 'Liberado para atribuição');
                $('#admin-user-roles-reason').val('');

                $('#admin-user-roles-form input[type="checkbox"][data-role-id]').each(function () {
                    const $input = $(this);
                    const roleId = String($input.data('roleId') || '');
                    const shouldCheck = roleIds.indexOf(roleId) >= 0;

                    $input.prop('checked', shouldCheck);
                    $input.prop('disabled', false);
                    $input.closest('label').removeClass('is-disabled');
                });
            }

            function updateUserRow(user) {
                const $row = $('tr[data-admin-user-row="1"][data-account-id="' + String(user.conta_id || '') + '"]');

                if ($row.length === 0) {
                    return;
                }

                $row.find('[data-admin-user-roles-summary] span').first().text(formatRolesSummary(user.roles));
                $row.find('[data-admin-user-role-assignment-date]').text(
                    user.ultima_atribuicao_papel_em ? formatDateTime(user.ultima_atribuicao_papel_em) : '-'
                );
            }

            $(document).on('click', '[data-admin-user-roles="1"]', function () {
                const accountId = Number($(this).data('accountId') || 0);

                if (!accountId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o usuário selecionado para gerenciar os papéis.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/usuarios/detalhe'), { id: accountId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.user) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os papéis deste usuário.'));
                            return;
                        }

                        if (Number(response.user.role_assignment_allowed || 0) !== 1) {
                            App.core.abrirPopup(
                                'erro',
                                String(response.user.role_assignment_block_reason || 'Este usuário não pode receber papéis no momento.')
                            );
                            return;
                        }

                        fillForm(response.user);
                        openModal();
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-user-roles-close, #admin-user-roles-dismiss', function () {
                closeModal();
            });

            $(document).on('click', '#admin-user-roles-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('submit', '#admin-user-roles-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.user) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar os papéis deste usuário.'));
                        return;
                    }

                    updateUserRow(response.user);
                    closeModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Papéis do usuário atualizados com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

        },

        iniciarFiltroPessoasAdmin: function () {
            let peopleFilterTimer = null;
            let peopleFilterRequest = null;
            let peopleFilterSequence = 0;

            function refreshPeoplePanel($form, options) {
                const settings = Object.assign({
                    preserveSearchFocus: false
                }, options || {});
                const $peopleForm = $('#admin-people-filter-form');
                const $usersForm = $('#admin-users-filter-form');
                const peopleLimit = String($peopleForm.find('input[name="people_limit"]').val() || '').trim();
                const usersLimit = String($usersForm.find('input[name="users_limit"]').val() || '').trim();
                const peopleSearch = String($peopleForm.find('input[name="people_search"]').val() || '');
                const usersSearch = String($usersForm.find('input[name="users_search"]').val() || '');
                const $searchField = $form.find('.admin-people-search-input').first();
                const selectionStart = settings.preserveSearchFocus ? Number($searchField[0] && $searchField[0].selectionStart) : null;
                const selectionEnd = settings.preserveSearchFocus ? Number($searchField[0] && $searchField[0].selectionEnd) : null;
                const focusFormId = settings.preserveSearchFocus ? String($form.attr('id') || '') : '';
                const requestSequence = ++peopleFilterSequence;

                if (peopleFilterRequest) {
                    peopleFilterRequest.abort();
                }

                peopleFilterRequest = $.ajax({
                    url: App.core.buildUrl('/admin/pessoas/lista'),
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        people_limit: peopleLimit,
                        people_search: peopleSearch,
                        users_limit: usersLimit,
                        users_search: usersSearch
                    },
                    suppressGlobalLoading: settings.preserveSearchFocus
                })
                    .done(function (response) {
                        if (requestSequence !== peopleFilterSequence) {
                            return;
                        }

                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a lista agora.'));
                            return;
                        }

                        $('#admin-people-panel-shell').replaceWith(String(response.html));

                        if (settings.preserveSearchFocus) {
                            window.requestAnimationFrame(function () {
                                let $searchInput = $();

                                if (focusFormId !== '') {
                                    $searchInput = $('#' + focusFormId).find('.admin-people-search-input').first();
                                }

                                if ($searchInput.length === 0) {
                                    $searchInput = $('.admin-people-search-input').first();
                                }

                                if ($searchInput.length === 0) {
                                    return;
                                }

                                $searchInput.trigger('focus');

                                if ($searchInput[0] && typeof $searchInput[0].setSelectionRange === 'function') {
                                    const currentSearch = focusFormId === 'admin-users-filter-form' ? usersSearch : peopleSearch;
                                    const start = Number.isFinite(selectionStart) ? selectionStart : currentSearch.length;
                                    const end = Number.isFinite(selectionEnd) ? selectionEnd : currentSearch.length;
                                    $searchInput[0].setSelectionRange(start, end);
                                }
                            });
                        }
                    })
                    .fail(function (xhr, status) {
                        if (status !== 'abort') {
                            const erro = App.core.extrairMensagemErroAjax(xhr);
                            App.core.abrirPopup('erro', erro.mensagem);
                        }
                    }).always(function () {
                        if (requestSequence === peopleFilterSequence) {
                            peopleFilterRequest = null;
                        }
                    });
            }

            $(document).on('submit', '[data-admin-people-filter="1"]', function (event) {
                event.preventDefault();

                const $form = $(this);
                refreshPeoplePanel($form);
            });

            $(document).on('input', '.admin-people-search-input', function () {
                const $form = $(this).closest('form');

                if ($form.length === 0) {
                    return;
                }

                if (peopleFilterTimer) {
                    window.clearTimeout(peopleFilterTimer);
                }

                peopleFilterTimer = window.setTimeout(function () {
                    refreshPeoplePanel($form, {
                        preserveSearchFocus: true
                    });
                }, 250);
            });

            $(document).on('change', '[data-admin-people-filter="1"] input[name="people_limit"], [data-admin-people-filter="1"] input[name="users_limit"]', function () {
                const $form = $(this).closest('form');

                if ($form.length === 0) {
                    return;
                }

                refreshPeoplePanel($form);
            });
        },

        iniciarEditorHorariosSemanais: function () {
            function normalizeInteger(value, fallback) {
                const parsed = Number.parseInt(String(value || ''), 10);

                return Number.isFinite(parsed) ? parsed : fallback;
            }

            function syncWeeklyScheduleAgePreview($scope) {
                const $container = $scope && $scope.length ? $scope : $(document);
                const $ageMin = $container.find('input[name="idade_minima"]').first();
                const $ageMax = $container.find('input[name="idade_maxima"]').first();
                const $mode = $container.find('select[name="criterio_faixa_etaria"]').first();
                const $agePreview = $container.find('[data-weekly-age-preview="1"], #admin-weekly-schedule-age-preview').first();
                const $birthYearPreview = $container.find('[data-weekly-birth-year-preview="1"], #admin-weekly-schedule-birth-year-preview').first();
                const $validationMessage = $container.find('[data-weekly-age-validation-message="1"], #admin-weekly-schedule-age-validation-message').first();
                const currentYear = new Date().getFullYear();
                const minAge = normalizeInteger($ageMin.val(), 0);
                const maxAge = normalizeInteger($ageMax.val(), 120);
                const mode = String($mode.val() || 'idade_exata').trim().toLowerCase();
                const birthYearFrom = currentYear - maxAge;
                const birthYearTo = currentYear - minAge;

                if ($agePreview.length > 0) {
                    $agePreview.text('Faixa etária: para ' + String(minAge) + ' a ' + String(maxAge) + ' anos de idade.');
                }

                if ($birthYearPreview.length > 0) {
                    $birthYearPreview.text(
                        'Ano de nascimento correspondente em ' + String(currentYear) + ': para nascidos entre ' + String(birthYearFrom) + ' a ' + String(birthYearTo) + '.'
                    );
                    $birthYearPreview.removeClass('hidden');
                }

                if ($validationMessage.length > 0) {
                    $validationMessage.toggleClass('hidden', maxAge >= minAge);
                }
            }

            function getModal() {
                return $('#admin-weekly-schedule-editor');
            }

            function getCreateModal() {
                return $('#admin-weekly-schedule-create-modal');
            }

            function getForm() {
                return $('#admin-weekly-schedule-form');
            }

            function syncWeeklyScheduleWindowFields($form) {
                if (!$form || $form.length === 0) {
                    return;
                }

                const type = String($form.find('select[name="janela_agendamento_tipo"]').val() || 'semana_atual_proxima');
                const helpMessages = {
                    semana_atual_proxima: 'Disponibiliza somente ocorrências da semana atual e da próxima, até o domingo, sem usar dias fixos.',
                    janela_semanal_fixa: 'Use os dias e horários semanais abaixo para definir quando a agenda abre e fecha.',
                    antecedencia: 'A agenda abre a quantidade informada de dias antes de cada ocorrência e fecha nas horas indicadas antes do início.'
                };

                $form.find('[data-window-fields]').each(function () {
                    const $group = $(this);
                    const visible = String($group.attr('data-window-fields') || '') === type;
                    $group.toggleClass('hidden', !visible);
                    $group.find('input, select, textarea').prop('disabled', !visible);
                });
                $form.find('[data-window-rule-help]').text(helpMessages[type] || '');
            }

            function currentAgendaFilters() {
                const $filterForm = $('#admin-agenda-filter-form');
                const $dailyForm = $('#admin-daily-bookings-filter-form');

                if ($filterForm.length === 0) {
                    return {
                        local_treino_id: '0',
                        modalidade_id: '0',
                        data_agendamento: String($dailyForm.find('input[name="data_agendamento"]').val() || ''),
                        agendamento_local_treino_id: String($dailyForm.find('select[name="agendamento_local_treino_id"]').val() || '0'),
                        agendamento_espaco_treino_id: String($dailyForm.find('select[name="agendamento_espaco_treino_id"]').val() || '0')
                    };
                }

                return {
                    local_treino_id: String($filterForm.find('select[name="local_treino_id"]').val() || '0'),
                    modalidade_id: String($filterForm.find('select[name="modalidade_id"]').val() || '0'),
                    data_agendamento: String($dailyForm.find('input[name="data_agendamento"]').val() || ''),
                    agendamento_local_treino_id: String($dailyForm.find('select[name="agendamento_local_treino_id"]').val() || '0'),
                    agendamento_espaco_treino_id: String($dailyForm.find('select[name="agendamento_espaco_treino_id"]').val() || '0')
                };
            }

            function closeEditor() {
                const $modal = getModal();
                const $form = getForm();

                if ($modal.length === 0 || $form.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $form[0].reset();
            }

            function openEditor() {
                const $modal = getModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeCreateModal() {
                const $modal = getCreateModal();
                const $form = $('#admin-weekly-schedule-create-form');

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                if ($form.length > 0) {
                    $form[0].reset();
                    syncWeeklyScheduleAgePreview($form);
                    syncWeeklyScheduleWindowFields($form);
                }
            }

            function openCreateModal() {
                const $modal = getCreateModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                syncWeeklyScheduleWindowFields($('#admin-weekly-schedule-create-form'));
                window.setTimeout(function () {
                    $modal.find('select, input').filter(':visible').first().trigger('focus');
                }, 0);
            }

            function setValue(selector, value) {
                $(selector).val(value == null ? '' : String(value));
            }

            function fillForm(schedule) {
                setValue('#admin-weekly-schedule-id', schedule.id);
                setValue('#admin-weekly-schedule-space', schedule.espaco_treino_id);
                setValue('#admin-weekly-schedule-modality', schedule.modalidade_id);
                setValue('#admin-weekly-schedule-type', schedule.tipo_horario || 'avaliacao');
                setValue('#admin-weekly-schedule-weekday', schedule.dia_semana);
                setValue('#admin-weekly-schedule-sex', schedule.sexo || '');
                setValue('#admin-weekly-schedule-start', String(schedule.hora_inicio || '').slice(0, 5));
                setValue('#admin-weekly-schedule-end', String(schedule.hora_fim || '').slice(0, 5));
                setValue('#admin-weekly-schedule-age-min', schedule.idade_minima);
                setValue('#admin-weekly-schedule-age-max', schedule.idade_maxima);
                setValue('#admin-weekly-schedule-age-rule-mode', schedule.criterio_faixa_etaria || 'idade_exata');
                setValue('#admin-weekly-schedule-clinical-rule', schedule.regra_atestado_clinico || 'global');
                setValue('#admin-weekly-schedule-dermatological-rule', schedule.regra_atestado_dermatologico || 'global');
                setValue('#admin-weekly-schedule-slots-general', schedule.vagas_geral);
                setValue('#admin-weekly-schedule-slots-pcd', schedule.vagas_pcd);
                setValue('#admin-weekly-schedule-slots-plm', schedule.vagas_plm);
                setValue('#admin-weekly-schedule-slots-pvs', schedule.vagas_pvs);
                setValue('#admin-weekly-schedule-window-type', schedule.janela_agendamento_tipo || 'semana_atual_proxima');
                setValue('#admin-weekly-schedule-window-open-weekday', schedule.janela_abertura_dia_semana || '');
                setValue('#admin-weekly-schedule-window-open-time', String(schedule.janela_abertura_hora || '').slice(0, 5));
                setValue('#admin-weekly-schedule-window-close-weekday', schedule.janela_fechamento_dia_semana || '');
                setValue('#admin-weekly-schedule-window-close-time', String(schedule.janela_fechamento_hora || '').slice(0, 5));
                setValue('#admin-weekly-schedule-window-days-before', schedule.janela_dias_antecedencia || 7);
                setValue('#admin-weekly-schedule-window-hours-before-close', schedule.janela_horas_antes_fechamento || 2);
                setValue('#admin-weekly-schedule-active', Number(schedule.ativo || 0) === 1 ? '1' : '0');
                syncWeeklyScheduleAgePreview(getForm());
                syncWeeklyScheduleWindowFields(getForm());

                $('#admin-weekly-schedule-editor-subtitle').text(
                    'Editando ' + String(schedule.modalidade_nome || '') + ' em ' + String(schedule.local_nome || '') + ' sem sair da agenda administrativa.'
                );
            }

            $(document).on('click', '[data-weekly-schedule-edit="1"]', function () {
                const scheduleId = Number($(this).data('weeklyScheduleId') || 0);

                if (!scheduleId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o horário selecionado.');
                    return;
                }

                $.getJSON(adminUrl('/admin/horarios-semanais/detalhe'), { id: scheduleId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.schedule) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar este horário.'));
                            return;
                        }

                        fillForm(response.schedule);
                        openEditor();
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-weekly-schedule-editor-close, #admin-weekly-schedule-cancel', function () {
                closeEditor();
            });

            $(document).on('click', '[data-weekday-toggle="1"]', function () {
                const $toggle = $(this);
                const contentId = String($toggle.attr('aria-controls') || '');
                const $content = contentId === '' ? $() : $('#' + contentId);
                const willOpen = String($toggle.attr('aria-expanded') || 'false') !== 'true';

                $toggle.attr('aria-expanded', willOpen ? 'true' : 'false');
                $content.toggleClass('hidden', !willOpen);
            });

            $(document).on('click', '#admin-weekly-schedule-create-open', function () {
                openCreateModal();
            });

            $(document).on('click', '#admin-weekly-schedule-create-close, #admin-weekly-schedule-create-cancel', function () {
                closeCreateModal();
            });

            $(document).on('input change', '#admin-weekly-schedule-create-form input[name="idade_minima"], #admin-weekly-schedule-create-form input[name="idade_maxima"], #admin-weekly-schedule-create-form select[name="criterio_faixa_etaria"], #admin-weekly-schedule-form input[name="idade_minima"], #admin-weekly-schedule-form input[name="idade_maxima"], #admin-weekly-schedule-form select[name="criterio_faixa_etaria"]', function () {
                syncWeeklyScheduleAgePreview($(this).closest('form'));
            });

            $(document).on('change', '#admin-weekly-schedule-create-form select[name="janela_agendamento_tipo"], #admin-weekly-schedule-form select[name="janela_agendamento_tipo"]', function () {
                syncWeeklyScheduleWindowFields($(this).closest('form'));
            });

            $(document).on('click', '#admin-weekly-schedule-editor', function (event) {
                if (event.target === this) {
                    closeEditor();
                }
            });

            $(document).on('click', '#admin-weekly-schedule-create-modal', function (event) {
                if (event.target === this) {
                    closeCreateModal();
                }
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeEditor();
                    closeCreateModal();
                }
            });

            $(document).on('submit', '#admin-weekly-schedule-create-form', function (event) {
                event.preventDefault();

                const $createForm = $(this);
                const $submitButton = $createForm.find('button[type="submit"]').first();
                const formData = new FormData($createForm[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($createForm.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível criar o horário semanal.'));
                        return;
                    }

                    closeCreateModal();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário semanal criado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '#admin-weekly-schedule-form', function (event) {
                event.preventDefault();

                const $editForm = $(this);
                const $submitButton = $editForm.find('button[type="submit"]').first();
                const formData = new FormData($editForm[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($editForm.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar o horário semanal.'));
                        return;
                    }

                    closeEditor();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário semanal atualizado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.admin-weekly-schedule-deactivate-form', function (event) {
                event.preventDefault();

                const $deactivateForm = $(this);
                const $submitButton = $deactivateForm.find('button[type="submit"]').first();
                const formData = new FormData($deactivateForm[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($deactivateForm.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível inativar o horário semanal.'));
                        return;
                    }

                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário semanal inativado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.admin-weekly-schedule-activate-form', function (event) {
                event.preventDefault();

                const $activateForm = $(this);
                const $submitButton = $activateForm.find('button[type="submit"]').first();
                const formData = new FormData($activateForm[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($activateForm.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível ativar o horário semanal.'));
                        return;
                    }

                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário semanal ativado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            syncWeeklyScheduleAgePreview($('#admin-weekly-schedule-create-form'));
            syncWeeklyScheduleAgePreview(getForm());
            syncWeeklyScheduleWindowFields($('#admin-weekly-schedule-create-form'));
            syncWeeklyScheduleWindowFields(getForm());
        },

        iniciarEditorEventosEspeciais: function () {
            function getModal() {
                return $('#admin-special-schedule-editor');
            }

            function getCreateModal() {
                return $('#admin-special-schedule-create-modal');
            }

            function getForm() {
                return $('#admin-special-schedule-form');
            }

            function currentAgendaFilters() {
                const $filterForm = $('#admin-agenda-filter-form');
                const $dailyForm = $('#admin-daily-bookings-filter-form');

                if ($filterForm.length === 0) {
                    return {
                        local_treino_id: '0',
                        modalidade_id: '0',
                        data_agendamento: String($dailyForm.find('input[name="data_agendamento"]').val() || ''),
                        agendamento_local_treino_id: String($dailyForm.find('select[name="agendamento_local_treino_id"]').val() || '0'),
                        agendamento_espaco_treino_id: String($dailyForm.find('select[name="agendamento_espaco_treino_id"]').val() || '0')
                    };
                }

                return {
                    local_treino_id: String($filterForm.find('select[name="local_treino_id"]').val() || '0'),
                    modalidade_id: String($filterForm.find('select[name="modalidade_id"]').val() || '0'),
                    data_agendamento: String($dailyForm.find('input[name="data_agendamento"]').val() || ''),
                    agendamento_local_treino_id: String($dailyForm.find('select[name="agendamento_local_treino_id"]').val() || '0'),
                    agendamento_espaco_treino_id: String($dailyForm.find('select[name="agendamento_espaco_treino_id"]').val() || '0')
                };
            }

            function closeEditor() {
                const $modal = getModal();
                const $form = getForm();

                if ($modal.length === 0 || $form.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $form[0].reset();
            }

            function openEditor() {
                const $modal = getModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeCreateModal() {
                const $modal = getCreateModal();
                const $form = $('#admin-special-schedule-create-form');
                $modal.addClass('hidden').attr('aria-hidden', 'true');
                if ($form.length > 0) {
                    $form[0].reset();
                }
            }

            function openCreateModal() {
                const $modal = getCreateModal();
                if ($modal.length === 0) return;
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                window.setTimeout(function () {
                    $modal.find('input, textarea, select').filter(':visible').first().trigger('focus');
                }, 0);
            }

            function setValue(selector, value) {
                $(selector).val(value == null ? '' : String(value));
            }

            function formatDateTimeLocal(value) {
                return String(value || '').replace(' ', 'T').slice(0, 16);
            }

            function fillForm(eventData) {
                setValue('#admin-special-schedule-id', eventData.id);
                setValue('#admin-special-schedule-title', eventData.titulo || '');
                setValue('#admin-special-schedule-description', eventData.descricao || '');
                setValue('#admin-special-schedule-start', formatDateTimeLocal(eventData.data_inicio));
                setValue('#admin-special-schedule-end', formatDateTimeLocal(eventData.data_fim));
                setValue('#admin-special-schedule-publish-start', formatDateTimeLocal(eventData.data_publicacao_inicio));
                setValue('#admin-special-schedule-publish-end', formatDateTimeLocal(eventData.data_publicacao_fim));
                setValue('#admin-special-schedule-age-min', eventData.idade_minima);
                setValue('#admin-special-schedule-age-max', eventData.idade_maxima);
                setValue('#admin-special-schedule-vagas-geral', eventData.vagas_geral);
                setValue('#admin-special-schedule-vagas-pcd', eventData.vagas_pcd);
                setValue('#admin-special-schedule-vagas-pvs', eventData.vagas_pvs);
                setValue('#admin-special-schedule-vagas-plm', eventData.vagas_plm);
                setValue('#admin-special-schedule-space', eventData.espaco_treino_id || '');
                setValue('#admin-special-schedule-modality', eventData.modalidade_id || '');
                setValue('#admin-special-schedule-image-url', eventData.imagem_url || '');
                setValue('#admin-special-schedule-url', eventData.url_destino || '');
                setValue('#admin-special-schedule-label', eventData.rotulo_acao || '');
                setValue('#admin-special-schedule-active', Number(eventData.ativo || 0) === 1 ? '1' : '0');
                $('#admin-special-schedule-home').prop('checked', Number(eventData.publicar_pagina_inicial || 0) === 1);
                $('#admin-special-schedule-blog').prop('checked', Number(eventData.publicar_blog || 0) === 1);

                $('#admin-special-schedule-editor-subtitle').text(
                    'Editando ' + String(eventData.titulo || 'horário especial') + ' sem sair da agenda administrativa.'
                );
            }

            $(document).on('click', '[data-special-schedule-edit="1"]', function () {
                const eventId = Number($(this).data('specialScheduleId') || 0);

                if (!eventId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o horário especial selecionado.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/agenda-horarios-especiais/detalhe'), { id: eventId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.event) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar este horário especial.'));
                            return;
                        }

                        fillForm(response.event);
                        openEditor();
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-special-schedule-editor-close, #admin-special-schedule-cancel', function () {
                closeEditor();
            });

            $(document).on('click', '#admin-special-schedule-create-open', function () {
                openCreateModal();
            });

            $(document).on('click', '#admin-special-schedule-create-close, #admin-special-schedule-create-cancel', function () {
                closeCreateModal();
            });

            $(document).on('click', '#admin-special-schedule-editor', function (event) {
                if (event.target === this) {
                    closeEditor();
                }
            });

            $(document).on('click', '#admin-special-schedule-create-modal', function (event) {
                if (event.target === this) closeCreateModal();
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') closeCreateModal();
            });

            $(document).on('submit', '#admin-special-schedule-create-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $button = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);
                $button.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''), method: 'POST', data: formData,
                    processData: false, contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível criar o horário especial.'));
                        return;
                    }
                    closeCreateModal();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário especial criado com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $(document).on('submit', '#admin-special-schedule-form', function (event) {
                event.preventDefault();

                const $editForm = $(this);
                const $submitButton = $editForm.find('button[type="submit"]').first();
                const formData = new FormData($editForm[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($editForm.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar o horário especial.'));
                        return;
                    }

                    closeEditor();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horário especial atualizado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        iniciarValidacaoCondicoesAdmin: function () {
            function getModal() {
                return $('#admin-condition-validation-modal');
            }

            function getModalContent() {
                return $('#admin-condition-validation-modal-content');
            }

            function closeModal() {
                const $modal = getModal();
                const $content = getModalContent();

                if ($modal.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $content.empty();
            }

            function openModal() {
                const $modal = getModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function syncValidationNoteRequirement() {
                const status = String($('#admin-condition-validation-status').val() || '').trim();
                const $note = $('#admin-condition-validation-note');

                if ($note.length === 0) {
                    return;
                }

                $note.prop('required', status === 'validado_parcial');
            }

            $(document).on('click', '[data-open-condition-validation="1"]', function () {
                const personId = Number($(this).data('personId') || 0);
                const conditionSlug = String($(this).data('conditionSlug') || '').trim();

                if (!personId || conditionSlug === '') {
                    App.core.abrirPopup('erro', 'Não foi possível identificar a condição selecionada para validação.');
                    return;
                }

                $.getJSON(adminUrl('/admin/certificados/validacao/modal'), {
                    person_id: personId,
                    condition_slug: conditionSlug
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível abrir a validação deste certificado.'));
                        return;
                    }

                    getModalContent().html(String(response.html || ''));
                    openModal();
                    syncValidationNoteRequirement();
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                });
            });

            $(document).on('change', '#admin-condition-validation-status', function () {
                syncValidationNoteRequirement();
            });

            $(document).on('click', '#admin-condition-validation-close, #admin-condition-validation-cancel', function () {
                closeModal();
            });

            $(document).on('click', '#admin-condition-validation-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('submit', '#admin-condition-validation-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar a validação do certificado.'));
                        return;
                    }

                    if (response.panel_html) {
                        const $currentPanel = $('#admin-condition-validation-panel');

                        if ($currentPanel.length > 0) {
                            $currentPanel.replaceWith(String(response.panel_html));
                        }
                    }

                    if (response.html) {
                        getModalContent().html(String(response.html || ''));
                        syncValidationNoteRequirement();
                    }

                    closeModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Validação atualizada com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        iniciarValidacaoAtestadosSaudeAdmin: function () {
            function getModal() {
                return $('#admin-health-certificate-validation-modal');
            }

            function getModalContent() {
                return $('#admin-health-certificate-validation-modal-content');
            }

            function closeModal() {
                const $modal = getModal();
                const $content = getModalContent();

                if ($modal.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $content.empty();
            }

            function openModal() {
                const $modal = getModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function syncValidationFields() {
                const status = String($('#admin-health-certificate-validation-status').val() || '').trim();
                const requireValidatedFields = status === 'validado';
                const requireNote = status === 'reprovado';

                $('#admin-health-certificate-validation-issued-at').prop('required', requireValidatedFields);
                $('#admin-health-certificate-validation-months').prop('required', requireValidatedFields);
                $('#admin-health-certificate-validation-note').prop('required', requireNote);
            }

            $(document).on('click', '[data-open-health-certificate-validation="1"]', function () {
                const personId = Number($(this).data('personId') || 0);
                const certificateType = String($(this).data('certificateType') || '').trim().toLowerCase();

                if (!personId || certificateType === '') {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o atestado selecionado para validação.');
                    return;
                }

                $.getJSON(adminUrl('/admin/atestados/validacao/modal'), {
                    person_id: personId,
                    certificate_type: certificateType
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível abrir a validação deste atestado.'));
                        return;
                    }

                    getModalContent().html(String(response.html || ''));
                    openModal();
                    syncValidationFields();
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                });
            });

            $(document).on('change', '#admin-health-certificate-validation-status', function () {
                syncValidationFields();
            });

            $(document).on('click', '#admin-health-certificate-validation-close, #admin-health-certificate-validation-cancel', function () {
                closeModal();
            });

            $(document).on('click', '#admin-health-certificate-validation-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('submit', '#admin-health-certificate-validation-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar a validação do atestado.'));
                        return;
                    }

                    if (response.panel_html) {
                        const $currentPanel = $('#admin-health-certificate-validation-panel');

                        if ($currentPanel.length > 0) {
                            $currentPanel.replaceWith(String(response.panel_html));
                        }
                    }

                    closeModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Validação do atestado atualizada com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        iniciarEditorPostagensBlog: function () {
            let pendingDeleteForm = null;

            function getModal() {
                return $('#admin-blog-post-modal');
            }

            function getForm() {
                return $('#admin-blog-post-form');
            }

            function getGalleryList() {
                return $('#admin-blog-gallery-list');
            }

            function setCoverCurrent(imageUrl) {
                $('#admin-blog-post-image-current').val(String(imageUrl || ''));
                $('#admin-blog-post-image-current-text').text(
                    String(imageUrl || '').trim() !== ''
                        ? 'Imagem atual: ' + String(imageUrl)
                        : 'Se nenhuma imagem for enviada, o sistema usa a imagem padrão da home como capa e fundo da postagem.'
                );
            }

            function addGalleryRow(imageUrl, caption) {
                const template = document.getElementById('admin-blog-gallery-item-template');
                const $list = getGalleryList();

                if (!template || $list.length === 0) {
                    return;
                }

                const clone = template.content.firstElementChild.cloneNode(true);
                const $item = $(clone);
                $item.find('input[name="galeria_imagem_atual[]"]').val(String(imageUrl || ''));
                $item.find('input[name="galeria_imagem_legenda[]"]').val(String(caption || ''));
                $item.find('[data-admin-blog-gallery-current-text="1"]').text(
                    String(imageUrl || '').trim() !== ''
                        ? 'Imagem atual: ' + String(imageUrl)
                        : 'Nenhuma imagem atual nesta linha.'
                );
                $list.append($item);
            }

            function resetForm() {
                const $form = getForm();

                if ($form.length === 0) {
                    return;
                }

                $form[0].reset();
                $('#admin-blog-post-id').val('');
                if ($form.find('[name="operacao"]').length === 0) {
                    $form.append($('<input>', { type: 'hidden', name: 'operacao' }));
                }
                $form.find('[name="operacao"]').val('criar');
                $('#admin-blog-post-modal-title').text('Nova postagem do blog');
                $('#admin-blog-post-submit').text('Salvar postagem');
                $('#admin-blog-post-deactivate').addClass('hidden').removeAttr('data-post-id');
                setCoverCurrent('');
                getGalleryList().empty();
                addGalleryRow('', '');
                syncShareOptions();
            }

            function openModal() {
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeModal() {
                getModal().addClass('hidden').attr('aria-hidden', 'true');
                resetForm();
            }

            function reloadBlogSection() {
                $('[data-admin-nav-target="blog"]').trigger('click');
            }

            function getDeleteConfirmModal() {
                return $('#admin-blog-delete-confirm-modal');
            }

            function closeDeleteConfirmModal() {
                pendingDeleteForm = null;
                getDeleteConfirmModal().addClass('hidden').attr('aria-hidden', 'true');
                $('#admin-blog-delete-confirm-text').text('Tem certeza que deseja remover esta postagem?');
            }

            function openDeleteConfirmModal($form) {
                const postTitle = String($form.data('postTitle') || '').trim();
                pendingDeleteForm = $form;
                $('#admin-blog-delete-confirm-text').text(
                    postTitle !== ''
                        ? 'Tem certeza que deseja remover a postagem "' + postTitle + '"?'
                        : 'Tem certeza que deseja remover esta postagem?'
                );
                getDeleteConfirmModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            function submitDeleteForm($form) {
                const formData = new FormData($form[0]);
                const $submitButton = $form.find('button[type="submit"]').first();

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível remover a postagem.'));
                        return;
                    }

                    reloadBlogSection();
                    App.core.abrirPopup('sucesso', String(response.message || 'Postagem removida com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            }

            function setCheckbox(selector, value) {
                $(selector).prop('checked', Number(value || 0) === 1);
            }

            function syncShareOptions() {
                const enabled = $('#admin-blog-post-allow-share').is(':checked');
                const $scope = $('[data-admin-blog-share-options="1"]');

                $scope.toggleClass('is-disabled', !enabled);
                $scope.find('input[type="checkbox"]').prop('disabled', !enabled);
            }

            function fillForm(post) {
                $('#admin-blog-post-id').val(String(post.id || ''));
                getForm().find('[name="operacao"]').val('editar');
                $('#admin-blog-post-title').val(String(post.titulo || ''));
                $('#admin-blog-post-slug').val(String(post.slug || ''));
                $('#admin-blog-post-category').val(String(post.categoria || ''));
                $('#admin-blog-post-tags').val(String(post.tags || ''));
                $('#admin-blog-post-summary').val(String(post.resumo || ''));
                $('#admin-blog-post-content').val(String(post.conteudo || ''));
                setCoverCurrent(String(post.capa_imagem_url || ''));
                $('#admin-blog-post-status').val(String(post.status || 'rascunho'));
                $('#admin-blog-post-share-text').val(String(post.texto_compartilhamento || ''));
                getGalleryList().empty();

                if (post.data_publicacao) {
                    $('#admin-blog-post-publish-at').val(String(post.data_publicacao).replace(' ', 'T').slice(0, 16));
                } else {
                    $('#admin-blog-post-publish-at').val('');
                }

                setCheckbox('#admin-blog-post-featured', post.destaque);
                setCheckbox('#admin-blog-post-home', post.publicar_na_home);
                setCheckbox('#admin-blog-post-allow-share', post.permitir_compartilhamento);
                setCheckbox('#admin-blog-post-share-whatsapp', post.compartilhar_whatsapp);
                setCheckbox('#admin-blog-post-share-facebook', post.compartilhar_facebook);
                setCheckbox('#admin-blog-post-share-linkedin', post.compartilhar_linkedin);
                setCheckbox('#admin-blog-post-share-x', post.compartilhar_x);

                if (Array.isArray(post.gallery_images) && post.gallery_images.length > 0) {
                    post.gallery_images.forEach(function (item) {
                        addGalleryRow(item.imagem_url || '', item.legenda || '');
                    });
                } else {
                    addGalleryRow('', '');
                }

                $('#admin-blog-post-modal-title').text('Editar postagem do blog');
                $('#admin-blog-post-submit').text('Salvar alterações');
                $('#admin-blog-post-deactivate')
                    .toggleClass('hidden', Number(post.ativo || 0) !== 1)
                    .attr('data-post-id', String(post.id || ''));
                syncShareOptions();
            }

            $(document).on('click', '[data-admin-blog-create="1"]', function () {
                resetForm();
                openModal();
            });

            $(document).on('click', '[data-admin-blog-edit="1"]', function () {
                const postId = Number($(this).data('postId') || 0);

                if (!postId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar a postagem selecionada.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/postagens/detalhe'), { id: postId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.post) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar a postagem.'));
                            return;
                        }

                        resetForm();
                        fillForm(response.post);
                        openModal();
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#admin-blog-post-close, #admin-blog-post-cancel', function () {
                closeModal();
            });

            $(document).on('click', '[data-admin-blog-gallery-add="1"]', function () {
                addGalleryRow('', '');
            });

            $(document).on('click', '[data-admin-blog-gallery-remove="1"]', function () {
                const $items = $('.admin-blog-gallery-item');

                if ($items.length <= 1) {
                    $(this).closest('.admin-blog-gallery-item').find('input').val('');
                    return;
                }

                $(this).closest('.admin-blog-gallery-item').remove();
            });

            $(document).on('click', '[data-close-popup="#admin-blog-post-modal"]', function () {
                window.setTimeout(function () {
                    resetForm();
                }, 0);
            });

            $(document).on('click', '#admin-blog-post-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('change', '#admin-blog-post-allow-share', function () {
                syncShareOptions();
            });

            $(document).on('click', '#admin-blog-post-deactivate', function () {
                const $button = $(this);
                const postId = String($button.attr('data-post-id') || '');
                if (postId === '' || !window.confirm('Deseja desativar esta postagem?')) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/postagens/remover'), method: 'POST', dataType: 'json', data: { post_id: postId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível desativar a postagem.'));
                        return;
                    }
                    closeModal();
                    if (typeof App.admin.activateSection === 'function') App.admin.activateSection('blog');
                    App.core.abrirPopup('sucesso', 'Postagem desativada com sucesso.');
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });

            $(document).on('click', '#admin-blog-delete-confirm-close, #admin-blog-delete-confirm-cancel', function () {
                closeDeleteConfirmModal();
            });

            $(document).on('click', '#admin-blog-delete-confirm-modal', function (event) {
                if (event.target === this) {
                    closeDeleteConfirmModal();
                }
            });

            $(document).on('click', '#admin-blog-delete-confirm-submit', function () {
                if (!pendingDeleteForm || pendingDeleteForm.length === 0) {
                    closeDeleteConfirmModal();
                    return;
                }

                const $form = pendingDeleteForm;
                closeDeleteConfirmModal();
                submitDeleteForm($form);
            });

            $(document).on('submit', '#admin-blog-post-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                if (String($form.find('[name="operacao"]').val() || '') === 'editar' && Number($('#admin-blog-post-id').val() || 0) <= 0) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar a postagem que será editada. Feche o modal e tente novamente.');
                    return;
                }
                const $submitButton = $('#admin-blog-post-submit');
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar a postagem.'));
                        return;
                    }

                    closeModal();
                    reloadBlogSection();
                    App.core.abrirPopup('sucesso', String(response.message || 'Postagem salva com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', 'form[data-admin-blog-delete-form="1"]', function (event) {
                event.preventDefault();
                openDeleteConfirmModal($(this));
            });

            function filterBlogPreview(search, category) {
                const normalizedSearch = String(search || '').trim().toLocaleLowerCase('pt-BR');
                const normalizedCategory = String(category || '').trim().toLocaleLowerCase('pt-BR');
                let visible = 0;
                $('[data-admin-blog-preview-post="1"]').each(function () {
                    const postSearch = String($(this).attr('data-post-search') || '').toLocaleLowerCase('pt-BR');
                    const postCategory = String($(this).attr('data-post-category') || '').trim().toLocaleLowerCase('pt-BR');
                    const show = (normalizedSearch === '' || postSearch.indexOf(normalizedSearch) >= 0) && (normalizedCategory === '' || postCategory === normalizedCategory);
                    $(this).toggleClass('hidden', !show);
                    if (show) visible += 1;
                });
                $('[data-admin-blog-result-count="1"]').text(visible + (visible === 1 ? ' resultado publicado.' : ' resultados publicados.'));
            }

            $(document).on('submit', '[data-admin-blog-preview-filter="1"]', function (event) {
                event.preventDefault();
                filterBlogPreview($(this).find('[name="busca"]').val(), $(this).find('[name="categoria"]').val());
            });

            $(document).on('click', '[data-admin-blog-category]', function (event) {
                event.preventDefault();
                const category = String($(this).attr('data-admin-blog-category') || '');
                const $form = $('[data-admin-blog-preview-filter="1"]');
                $form.find('[name="categoria"]').val(category);
                $('[data-admin-blog-category]').removeClass('is-active');
                $(this).addClass('is-active');
                filterBlogPreview($form.find('[name="busca"]').val(), category);
            });

            $(document).on('click', '[data-admin-blog-publish="1"]', function () {
                const $button = $(this).prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/postagens/publicar'),
                    method: 'POST',
                    dataType: 'json',
                    data: { post_id: String($button.attr('data-post-id') || '') },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível publicar a postagem.'));
                        return;
                    }
                    if (typeof App.admin.activateSection === 'function') {
                        App.admin.activateSection('blog');
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Postagem publicada com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            function closeInactivePreview() {
                $('#admin-blog-inactive-preview-modal').addClass('hidden').attr('aria-hidden', 'true').removeData('post');
                $('#admin-blog-inactive-preview-body').empty();
            }

            function renderInactivePreview(post) {
                const $body = $('#admin-blog-inactive-preview-body').empty();
                if (String(post.capa_imagem_url || '').trim() !== '') {
                    $body.append($('<img>', { class: 'admin-blog-inactive-cover', src: String(post.capa_imagem_url), alt: String(post.titulo || '') }));
                }
                $body.append($('<div>', { class: 'blog-post-meta' })
                    .append($('<span>').text(String(post.categoria || 'Sem categoria')))
                    .append($('<span>').text(String(post.status || 'rascunho'))));
                $body.append($('<h2>').text(String(post.titulo || 'Postagem sem título')));
                $body.append($('<p>', { class: 'blog-post-summary' }).text(String(post.resumo || '')));
                const $content = $('<div>', { class: 'blog-rich-text' });
                String(post.conteudo || '').split(/\r?\n(?:\s*\r?\n)*/).forEach(function (paragraph) {
                    if (paragraph.trim() !== '') $content.append($('<p>').text(paragraph.trim()));
                });
                $body.append($content);
                if (Array.isArray(post.gallery_images)) {
                    post.gallery_images.forEach(function (item) {
                        if (String(item.imagem_url || '').trim() === '') return;
                        const $figure = $('<figure>', { class: 'blog-gallery-item' })
                            .append($('<img>', { class: 'blog-gallery-image', src: String(item.imagem_url), alt: String(item.legenda || post.titulo || '') }));
                        if (String(item.legenda || '').trim() !== '') $figure.append($('<figcaption>').text(String(item.legenda)));
                        $body.append($figure);
                    });
                }
            }

            $(document).on('click', '[data-admin-blog-inactive-preview="1"]', function () {
                const postId = String($(this).attr('data-post-id') || '');
                $.ajax({
                    url: App.core.buildUrl('/admin/postagens/detalhe'), method: 'GET', dataType: 'json', data: { id: postId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false || !response.post) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível abrir a postagem.'));
                        return;
                    }
                    renderInactivePreview(response.post);
                    $('#admin-blog-inactive-preview-modal').data('post', response.post).removeClass('hidden').attr('aria-hidden', 'false');
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            });

            $(document).on('click', '[data-admin-blog-inactive-preview-close="1"], #admin-blog-inactive-preview-modal', function (event) {
                if ($(event.target).is('#admin-blog-inactive-preview-modal') || $(event.target).is('[data-admin-blog-inactive-preview-close="1"]')) closeInactivePreview();
            });

            $(document).on('click', '[data-admin-blog-inactive-edit="1"]', function () {
                const post = $('#admin-blog-inactive-preview-modal').data('post');
                if (!post) return;
                closeInactivePreview();
                fillForm(post);
                openModal();
            });

            $(document).on('click', '[data-admin-blog-inactive-activate="1"]', function () {
                const post = $('#admin-blog-inactive-preview-modal').data('post');
                if (!post || !post.id) return;
                const $button = $(this).prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/postagens/ativar'), method: 'POST', dataType: 'json', data: { post_id: String(post.id) },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível ativar a postagem.'));
                        return;
                    }
                    closeInactivePreview();
                    if (typeof App.admin.activateSection === 'function') App.admin.activateSection('blog');
                    App.core.abrirPopup('sucesso', String(response.message || 'Postagem ativada com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });
        },

        iniciarEditorComunicacaoOficialAdmin: function () {
            function getForm() {
                return $('#admin-official-communication-form');
            }

            function openModal() {
                if ($('#admin-official-communication-modal').length === 0) {
                    App.core.abrirPopup('erro', 'O editor de comunicação oficial não está disponível nesta tela.');
                    return;
                }

                App.core.abrirPopupCustomizado('#admin-official-communication-modal');
            }

            function closeModal() {
                App.core.fecharPopupCustomizado('#admin-official-communication-modal');
            }

            function syncForm(data) {
                const $form = getForm();

                if ($form.length === 0 || !data) {
                    return;
                }

                $form.find('input[name="nome_quadro"]').val(String(data.nome_quadro || ''));
                $form.find('input[name="titulo"]').val(String(data.titulo || ''));
                $form.find('textarea[name="texto_breve"]').val(String(data.texto_breve || ''));
                $form.find('input[name="link_titulo"]').val(String(data.link_titulo || ''));
                $form.find('input[name="link_url"]').val(String(data.link_url || ''));
            }

            $(document).on('click', '[data-admin-official-communication-open="1"]', function () {
                openModal();
            });

            $(document).on('click', '#admin-official-communication-close, #admin-official-communication-cancel', function () {
                closeModal();
            });

            $(document).on('click', '#admin-official-communication-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('submit', '#admin-official-communication-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $('#admin-official-communication-submit');

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar a comunicação oficial.'));
                        return;
                    }

                    if (response.communication) {
                        syncForm(response.communication);
                    }

                    closeModal();
                    if (typeof App.admin.activateSection === 'function') {
                        App.admin.activateSection('blog');
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Rascunho salvo com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('click', '[data-admin-blog-communication-publish="1"]', function () {
                const $button = $(this).prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/comunicacao-oficial/publicar'),
                    method: 'POST',
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível publicar o quadro.'));
                        return;
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Quadro publicado com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });
        },

        iniciarBuscaEnderecoCep: function () {
            let request = null;
            let debounceTimer = null;

            function getLocationModal() {
                return $('#admin-training-location-modal');
            }

            function prepareCreateLocationForm() {
                const $form = $('#admin-training-location-form');

                if ($form.length === 0) {
                    return;
                }

                $form[0].reset();
                $form.attr('action', String($form.data('createAction') || ''));
                $form.find('input[name="local_treino_id"]').val('');
                $form.find('input[name="local_externo_migracao_id"]').val('');
                $form.find('[data-address-field]').val('');
                $form.find('.cep-address-results').addClass('hidden').empty();
                $form.find('.cep-address-status').text('Digite os 8 números do CEP.');
                $form.find('[data-cep-address-search="1"]').attr('aria-expanded', 'false');
                $('#admin-training-location-modal-title').text('Cadastrar local de treino');
                $('#admin-training-location-submit').text('Cadastrar local');
            }

            function closeLocationModal() {
                const $modal = getLocationModal();

                window.clearTimeout(debounceTimer);

                if (request) {
                    request.abort();
                    request = null;
                }

                prepareCreateLocationForm();
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            }

            let externalLocationTimer = null;
            let externalLocationRequest = null;

            function openBlankLocationForm() {
                const $modal = getLocationModal();

                prepareCreateLocationForm();
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                window.setTimeout(function () {
                    $('#admin-training-location-form input[name="nome_local"]').trigger('focus');
                }, 0);
            }

            function renderExternalLocations(locations) {
                const $body = $('#admin-external-location-list').empty();
                const records = Array.isArray(locations) ? locations : [];

                if (records.length === 0) {
                    $body.append($('<tr>').append($('<td>', { colspan: 4, text: 'Nenhum local pendente foi encontrado.' })));
                    return;
                }

                records.forEach(function (location) {
                    const $button = $('<button>', { type: 'button', class: 'btn btn-primary admin-external-location-select', text: 'Usar dados' });
                    $button.data('location', location);
                    $body.append($('<tr>')
                        .append($('<td>').text(String(location.apelido_local || '')))
                        .append($('<td>').text(String(location.nome_local || '')))
                        .append($('<td>').text([location.cidade, location.uf].filter(Boolean).join(' - ')))
                        .append($('<td>').append($button)));
                });
            }

            function loadExternalLocations(search) {
                const $modal = $('#admin-external-location-modal');
                const url = String($modal.data('listUrl') || '');
                $('#admin-external-location-status').text('Carregando locais...');
                if (externalLocationRequest) {
                    externalLocationRequest.abort();
                }
                externalLocationRequest = $.getJSON(url, { search: String(search || '') }).done(function (response) {
                    if (!response || !response.success) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os locais anteriores.'));
                        return;
                    }
                    renderExternalLocations(response.locations);
                    const count = Array.isArray(response.locations) ? response.locations.length : 0;
                    $('#admin-external-location-status').text(count + (count === 1 ? ' local disponível.' : ' locais disponíveis.'));
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    }
                }).always(function () {
                    externalLocationRequest = null;
                });
            }

            $(document).on('click', '#admin-training-location-open', function () {
                const $chooser = $('#admin-external-location-modal');
                $('#admin-external-location-search').val('');
                $chooser.removeClass('hidden').attr('aria-hidden', 'false');
                loadExternalLocations('');
            });

            $(document).on('input', '#admin-external-location-search', function () {
                const value = String($(this).val() || '');
                window.clearTimeout(externalLocationTimer);
                externalLocationTimer = window.setTimeout(function () { loadExternalLocations(value); }, 250);
            });

            $(document).on('click', '.admin-external-location-select', function () {
                const location = $(this).data('location') || {};
                const $form = $('#admin-training-location-form');
                prepareCreateLocationForm();
                $form.find('input[name="local_externo_migracao_id"]').val(String(location.id || ''));
                $form.find('input[name="nome_local"]').val(String(location.nome_local || ''));
                $form.find('input[name="apelido_local"]').val(String(location.apelido_local || ''));
                $form.find('input[name="cep"]').val(String(location.cep || '').replace(/(\d{5})(\d{3})/, '$1-$2'));
                $form.find('input[name="logradouro"]').val(String(location.logradouro || ''));
                $form.find('input[name="numero_endereco"]').val(String(location.numero_endereco || ''));
                $form.find('input[name="complemento"]').val(String(location.complemento || ''));
                $form.find('input[name="bairro"]').val(String(location.bairro || ''));
                $form.find('input[name="cidade"]').val(String(location.cidade || ''));
                $form.find('input[name="uf"]').val(String(location.uf || ''));
                $form.find('select[name="ativo"]').val(String(Number(location.ativo || 0)));
                $form.find('.cep-address-status').text('Dados do sistema anterior carregados. Confira antes de cadastrar.');
                $('#admin-external-location-modal').addClass('hidden').attr('aria-hidden', 'true');
                getLocationModal().removeClass('hidden').attr('aria-hidden', 'false');
                $form.find('input[name="nome_local"]').trigger('focus');
            });

            $(document).on('click', '#admin-external-location-manual', function () {
                $('#admin-external-location-modal').addClass('hidden').attr('aria-hidden', 'true');
                openBlankLocationForm();
            });

            $(document).on('click', '#admin-external-location-close, #admin-external-location-cancel', function () {
                $('#admin-external-location-modal').addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '.admin-training-location-edit', function () {
                const $button = $(this);
                const $modal = getLocationModal();
                const $form = $('#admin-training-location-form');
                let location = {};

                try {
                    location = JSON.parse(String($button.attr('data-location') || '{}'));
                } catch (error) {
                    App.core.abrirPopup('erro', 'Não foi possível carregar os dados deste local.');
                    return;
                }

                prepareCreateLocationForm();
                $form.attr('action', String($form.data('updateAction') || ''));
                $form.find('input[name="local_treino_id"]').val(String(location.id || ''));
                $form.find('input[name="nome_local"]').val(String(location.nome_local || ''));
                $form.find('input[name="apelido_local"]').val(String(location.apelido_local || ''));
                $form.find('select[name="admin_local"]').val(String(Number(location.admin_local || 0) || ''));
                $form.find('select[name="coord_local"]').val(String(Number(location.coord_local || 0) || ''));
                $form.find('input[name="cep"]').val(String(location.cep || '').replace(/(\d{5})(\d{3})/, '$1-$2'));
                $form.find('input[name="logradouro"]').val(String(location.logradouro || ''));
                $form.find('input[name="numero_endereco"]').val(String(location.numero_endereco || ''));
                $form.find('input[name="complemento"]').val(String(location.complemento || ''));
                $form.find('input[name="bairro"]').val(String(location.bairro || ''));
                $form.find('input[name="cidade"]').val(String(location.cidade || ''));
                $form.find('input[name="uf"]').val(String(location.uf || ''));
                $form.find('select[name="ativo"]').val(String(Number(location.ativo || 0)));
                $form.find('.cep-address-status').text('Endereço atual carregado. Digite outro CEP para substituir.');
                $('#admin-training-location-modal-title').text('Editar local de treino');
                $('#admin-training-location-submit').text('Salvar alterações');
                $modal.removeClass('hidden').attr('aria-hidden', 'false');

                window.setTimeout(function () {
                    $form.find('input[name="nome_local"]').trigger('focus');
                }, 0);
            });

            $(document).on('click', '#admin-training-location-close, #admin-training-location-cancel', function () {
                closeLocationModal();
            });

            $(document).on('click', '#admin-training-location-modal', function (event) {
                if (event.target === this) {
                    closeLocationModal();
                }
            });

            $(document).on('submit', '#admin-training-location-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $button = $form.find('button[type="submit"]').first();
                const $filter = $('#admin-training-location-filter-form');
                const isCreate = Number($form.find('input[name="local_treino_id"]').val() || 0) === 0;
                const data = $form.serialize() + '&' + $.param({
                    location_search: String($filter.find('input[name="location_search"]').val() || ''),
                    location_limit: String($filter.find('input[name="location_limit"]').val() || '10').trim()
                });

                $button.prop('disabled', true);
                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    dataType: 'json',
                    data: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o local.'));
                        return;
                    }
                    if (typeof response.locations_html === 'string') {
                        $('#admin-training-location-list-body').html(response.locations_html);
                    }
                    if (isCreate && response.location && response.location.id) {
                        const location = response.location;
                        const value = String(location.id);
                        const label = String(location.apelido_local || location.nome_local || '') + ' — ' + String(location.nome_local || '');
                        const $select = $('#admin-training-space-form select[name="local_treino_id"]');
                        if ($select.find('option[value="' + value + '"]').length === 0) {
                            $select.append($('<option>', { value: value, text: label }));
                        }
                    }
                    closeLocationModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Local salvo com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape' && !getLocationModal().hasClass('hidden')) {
                    closeLocationModal();
                }
            });

            function clearAddress($form) {
                $form.find('[data-address-field]').val('');
            }

            function closeResults($input, $results) {
                $results.addClass('hidden').empty();
                $input.attr('aria-expanded', 'false');
            }

            $(document).on('input', '[data-cep-address-search="1"]', function () {
                const $input = $(this);
                const $form = $input.closest('form');
                const $results = $form.find('.cep-address-results').first();
                const $status = $form.find('.cep-address-status').first();
                const digits = String($input.val() || '').replace(/\D/g, '').slice(0, 8);
                const formatted = digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;

                $input.val(formatted);
                clearAddress($form);
                closeResults($input, $results);
                window.clearTimeout(debounceTimer);

                if (request) {
                    request.abort();
                    request = null;
                }

                if (digits.length < 8) {
                    $status.text('Digite os 8 números do CEP. Faltam ' + String(8 - digits.length) + '.');
                    return;
                }

                $status.text('Consultando endereço...');
                debounceTimer = window.setTimeout(function () {
                    request = $.ajax({
                        url: App.core.buildUrl('/api/ceps/endereco'),
                        method: 'GET',
                        dataType: 'json',
                        data: { cep: digits },
                        suppressGlobalLoading: true
                    })
                        .done(function (response) {
                            if (!response || response.success !== true || !response.address) {
                                $status.text(String((response && response.message) || 'CEP não encontrado.'));
                                return;
                            }

                            const address = response.address;
                            const label = [
                                String(address.logradouro || ''),
                                String(address.bairro || ''),
                                String(address.cidade || '') + '/' + String(address.uf || ''),
                                String(address.cep || '').replace(/(\d{5})(\d{3})/, '$1-$2')
                            ].filter(function (item) {
                                return item.replace('/', '').trim() !== '';
                            }).join(' — ');
                            const $option = $('<button type="button" class="cep-address-option" role="option"></button>');

                            $option.text(label);
                            $option.data('address', address);
                            $results.empty().append($option).removeClass('hidden');
                            $input.attr('aria-expanded', 'true');
                            $status.text('Selecione o endereço encontrado.');
                        })
                        .fail(function (xhr, status) {
                            if (status !== 'abort') {
                                $status.text('Não foi possível consultar o CEP neste momento.');
                            }
                        })
                        .always(function () {
                            request = null;
                        });
                }, 250);
            });

            $(document).on('click', '.cep-address-option', function () {
                const $option = $(this);
                const $form = $option.closest('form');
                const $input = $form.find('[data-cep-address-search="1"]').first();
                const address = $option.data('address') || {};

                $input.val(String(address.cep || '').replace(/(\d{5})(\d{3})/, '$1-$2'));
                $form.find('[data-address-field="logradouro"]').val(String(address.logradouro || ''));
                $form.find('[data-address-field="bairro"]').val(String(address.bairro || ''));
                $form.find('[data-address-field="cidade"]').val(String(address.cidade || ''));
                $form.find('[data-address-field="uf"]').val(String(address.uf || ''));
                $form.find('.cep-address-status').text('Endereço selecionado.');
                closeResults($input, $form.find('.cep-address-results').first());
            });

            $(document).on('click', function (event) {
                if ($(event.target).closest('.cep-autocomplete-field').length === 0) {
                    $('.cep-address-results').addClass('hidden');
                    $('[data-cep-address-search="1"]').attr('aria-expanded', 'false');
                }
            });
        },

        iniciarFiltroLocaisTreino: function () {
            let filterTimer = null;
            let filterRequest = null;

            function refreshTrainingLocations($form) {
                const search = String($form.find('input[name="location_search"]').val() || '');
                const $limitInput = $form.find('input[name="location_limit"]').first();
                const requestedLimit = Number.parseInt(String($limitInput.val() || '10'), 10);
                const limit = Math.max(1, Math.min(20, Number.isFinite(requestedLimit) ? requestedLimit : 10));

                $limitInput.val(String(limit));

                if (filterRequest) {
                    filterRequest.abort();
                }

                filterRequest = $.ajax({
                    url: App.core.buildUrl('/admin/locais/lista'),
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        location_search: search,
                        location_limit: limit
                    },
                    suppressGlobalLoading: true
                })
                    .done(function (response) {
                        if (!response || response.success === false || typeof response.html !== 'string') {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a lista de locais.'));
                            return;
                        }

                        $('#admin-training-location-list-body').html(response.html);
                    })
                    .fail(function (xhr, status) {
                        if (status !== 'abort') {
                            const erro = App.core.extrairMensagemErroAjax(xhr);
                            App.core.abrirPopup('erro', erro.mensagem);
                        }
                    })
                    .always(function () {
                        filterRequest = null;
                    });
            }

            $(document).on('submit', '#admin-training-location-filter-form', function (event) {
                event.preventDefault();
                refreshTrainingLocations($(this));
            });

            $(document).on('input', '#admin-training-location-search', function () {
                const $form = $(this).closest('form');

                window.clearTimeout(filterTimer);
                filterTimer = window.setTimeout(function () {
                    refreshTrainingLocations($form);
                }, 250);
            });

            $(document).on('input', '#admin-training-location-filter-form input[name="location_limit"]', function () {
                const $form = $(this).closest('form');

                window.clearTimeout(filterTimer);
                filterTimer = window.setTimeout(function () {
                    refreshTrainingLocations($form);
                }, 250);
            });

        },

        iniciarFiltroEspacosTreino: function () {
            let filterTimer = null;
            let filterRequest = null;

            function refreshTrainingSpaces($form) {
                const search = String($form.find('input[name="space_search"]').val() || '');
                const $limitInput = $form.find('input[name="space_limit"]').first();
                const requestedLimit = Number.parseInt(String($limitInput.val() || '10'), 10);
                const limit = Math.max(1, Math.min(20, Number.isFinite(requestedLimit) ? requestedLimit : 10));

                $limitInput.val(String(limit));

                if (filterRequest) {
                    filterRequest.abort();
                }

                filterRequest = $.ajax({
                    url: App.core.buildUrl('/admin/espacos/lista'),
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        space_search: search,
                        space_limit: limit
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false || typeof response.html !== 'string') {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a lista de espaços.'));
                        return;
                    }

                    $('#admin-training-space-list-body').html(response.html);
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    }
                }).always(function () {
                    filterRequest = null;
                });
            }

            $(document).on('submit', '#admin-training-space-filter-form', function (event) {
                event.preventDefault();
                refreshTrainingSpaces($(this));
            });

            $(document).on('input', '#admin-training-space-search, #admin-training-space-filter-form input[name="space_limit"]', function () {
                const $form = $(this).closest('form');

                window.clearTimeout(filterTimer);
                filterTimer = window.setTimeout(function () {
                    refreshTrainingSpaces($form);
                }, 250);
            });
        },

        iniciarEditorEspacosTreino: function () {
            let externalSpaceTimer = null;
            let externalSpaceRequest = null;

            function getModal() {
                return $('#admin-training-space-modal');
            }

            function renderExternalSpaces(spaces) {
                const $body = $('#admin-external-space-list').empty();
                const records = Array.isArray(spaces) ? spaces : [];
                if (records.length === 0) {
                    $body.append($('<tr>').append($('<td>', { colspan: 5, text: 'Nenhum espaço pendente foi encontrado.' })));
                    return;
                }
                records.forEach(function (space) {
                    const area = Number(space.area_espaco || 0);
                    const $button = $('<button>', { type: 'button', class: 'btn btn-primary admin-external-space-select', text: 'Usar dados' });
                    $button.data('space', space);
                    $body.append($('<tr>')
                        .append($('<td>').text(String(space.nome_espaco || '')))
                        .append($('<td>').text(String(space.apelido_local || space.nome_local || '')))
                        .append($('<td>').text(String(space.descricao || '')))
                        .append($('<td>').text(area > 0 ? area.toLocaleString('pt-BR') + ' m²' : 'Não informada'))
                        .append($('<td>').append($button)));
                });
            }

            function loadExternalSpaces(search) {
                const $modal = $('#admin-external-space-modal');
                $('#admin-external-space-status').text('Carregando espaços...');
                if (externalSpaceRequest) {
                    externalSpaceRequest.abort();
                }
                externalSpaceRequest = $.getJSON(String($modal.data('listUrl') || ''), { search: String(search || '') }).done(function (response) {
                    if (!response || !response.success) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os espaços anteriores.'));
                        return;
                    }
                    renderExternalSpaces(response.spaces);
                    const count = Array.isArray(response.spaces) ? response.spaces.length : 0;
                    $('#admin-external-space-status').text(count + (count === 1 ? ' espaço encontrado.' : ' espaços encontrados.'));
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    }
                }).always(function () {
                    externalSpaceRequest = null;
                });
            }

            function openBlankSpaceForm() {
                prepareCreate();
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
            }

            $(document).on('click', '#admin-training-space-create', function () {
                $('#admin-external-space-search').val('');
                $('#admin-external-space-modal').removeClass('hidden').attr('aria-hidden', 'false');
                loadExternalSpaces('');
            });

            $(document).on('input', '#admin-external-space-search', function () {
                const value = String($(this).val() || '');
                window.clearTimeout(externalSpaceTimer);
                externalSpaceTimer = window.setTimeout(function () { loadExternalSpaces(value); }, 250);
            });

            $(document).on('click', '#admin-external-space-close, #admin-external-space-cancel', function () {
                $('#admin-external-space-modal').addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('click', '#admin-external-space-manual', function () {
                $('#admin-external-space-modal').addClass('hidden').attr('aria-hidden', 'true');
                openBlankSpaceForm();
            });

            $(document).on('click', '.admin-external-space-select', function () {
                const space = $(this).data('space') || {};
                const $form = $('#admin-training-space-form');
                prepareCreate();
                $form.find('input[name="espaco_externo_migracao_id"]').val(String(space.id || ''));
                $form.find('select[name="local_treino_id"]').val(String(space.local_treino_id || ''));
                $form.find('input[name="nome"]').val(String(space.nome_espaco || ''));
                $form.find('input[name="tipo_espaco"]').val(String(space.descricao || 'Espaço esportivo').slice(0, 80));
                $form.find('input[name="capacidade_base"]').val('0');
                $('#admin-external-space-modal').addClass('hidden').attr('aria-hidden', 'true');
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
                if (!space.local_treino_id) {
                    App.core.abrirPopup('erro', 'O local “' + String(space.apelido_local || space.nome_local || '') + '” ainda não está vinculado. Selecione o local correspondente antes de salvar.');
                }
                $form.find('input[name="nome"]').trigger('focus');
            });

            $(document).on('click', '#admin-external-space-modal', function (event) {
                if (event.target === this) {
                    $(this).addClass('hidden').attr('aria-hidden', 'true');
                }
            });

            function closeModal() {
                getModal().addClass('hidden').attr('aria-hidden', 'true');
            }

            function prepareCreate() {
                const $form = $('#admin-training-space-form');

                if ($form[0]) {
                    $form[0].reset();
                }
                $form.attr('action', String($form.data('createAction') || ''));
                $form.find('input[name="espaco_treino_id"]').val('');
                $form.find('input[name="espaco_externo_migracao_id"]').val('');
                $('#admin-training-space-modal-title').text('Criar espaço de treino');
                $('#admin-training-space-submit').text('Cadastrar espaço');
            }

            $(document).on('click', '.admin-training-space-edit', function () {
                const $form = $('#admin-training-space-form');
                let space;

                try {
                    space = JSON.parse(String($(this).attr('data-space') || '{}'));
                } catch (error) {
                    App.core.abrirPopup('erro', 'Não foi possível carregar os dados deste espaço.');
                    return;
                }

                prepareCreate();
                $form.attr('action', String($form.data('updateAction') || ''));
                $form.find('input[name="espaco_treino_id"]').val(String(space.id || ''));
                $form.find('select[name="local_treino_id"]').val(String(space.local_treino_id || ''));
                $form.find('input[name="nome"]').val(String(space.nome || ''));
                $form.find('input[name="tipo_espaco"]').val(String(space.tipo_espaco || ''));
                $form.find('input[name="capacidade_base"]').val(String(space.capacidade_base || 0));
                $form.find('select[name="supervisor_espaco"]').val(String(space.supervisor_espaco || ''));
                const unavailableAccessibility = Array.isArray(space.acessibilidade_deficiencias_indisponiveis)
                    ? space.acessibilidade_deficiencias_indisponiveis.map(String)
                    : [];
                $form.find('input[name="acessibilidade_deficiencias_indisponiveis[]"]').each(function () {
                    $(this).prop('checked', unavailableAccessibility.indexOf(String($(this).val())) !== -1);
                });
                $form.find('select[name="ativo"]').val(String(Number(space.ativo || 0)));
                $('#admin-training-space-modal-title').text('Editar espaço de treino');
                $('#admin-training-space-submit').text('Salvar alterações');
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('submit', '#admin-training-space-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $button = $form.find('button[type="submit"]').first();
                const $filterForm = $('#admin-training-space-filter-form');
                const data = $form.serialize() + '&' + $.param({
                    space_search: String($filterForm.find('input[name="space_search"]').val() || ''),
                    space_limit: String($filterForm.find('input[name="space_limit"]').val() || '10').trim()
                });

                $button.prop('disabled', true);
                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    dataType: 'json',
                    data: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o espaço.'));
                        return;
                    }

                    if (typeof response.spaces_html === 'string') {
                        $('#admin-training-space-list-body').html(response.spaces_html);
                    }
                    closeModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Espaço salvo com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $(document).on('click', '#admin-training-space-close, #admin-training-space-cancel', closeModal);
            $(document).on('click', '#admin-training-space-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });
        },

        iniciarModalSuspensoesLocal: function () {
            function currentSpaceFilters() {
                const $filterForm = $('#admin-training-space-filter-form');

                return {
                    space_search: String($filterForm.find('input[name="space_search"]').val() || ''),
                    space_limit: String($filterForm.find('input[name="space_limit"]').val() || '10').trim()
                };
            }

            function getModal() {
                return $('#admin-location-suspensions-modal');
            }

            function getSuspensionFormModal() {
                return $('#admin-space-suspension-modal');
            }

            function closeSuspensionFormModal() {
                const $form = $('#admin-space-suspension-form');

                if ($form.length > 0) {
                    $form[0].reset();
                }

                getSuspensionFormModal().addClass('hidden').attr('aria-hidden', 'true');
            }

            function showSpaceSuspensions(spaceId, spaceName) {
                const $rows = $('[data-space-suspension-row="' + String(spaceId || '') + '"]');

                $('[data-space-suspension-row]').addClass('hidden');
                $rows.removeClass('hidden');
                $('#admin-location-suspensions-empty').toggleClass('hidden', $rows.length > 0);
                $('#admin-location-suspensions-subtitle').text(String(spaceName || ''));
                getModal()
                    .attr('data-current-space-id', String(spaceId || ''))
                    .attr('data-current-space-name', String(spaceName || ''))
                    .removeClass('hidden')
                    .attr('aria-hidden', 'false');
            }

            function updateManagementFragments(response) {
                if (response && typeof response.spaces_html === 'string') {
                    $('#admin-training-space-list-body').html(response.spaces_html);
                }

                if (response && typeof response.suspensions_html === 'string') {
                    $('#admin-location-suspensions-body').html(response.suspensions_html);
                }
            }

            function closeModal() {
                getModal()
                    .removeAttr('data-current-space-id data-current-space-name')
                    .addClass('hidden')
                    .attr('aria-hidden', 'true');
                $('[data-space-suspension-row]').addClass('hidden');
                $('#admin-location-suspensions-empty').addClass('hidden');
            }

            $(document).on('click', '.admin-location-suspensions-link', function () {
                const $button = $(this);
                const spaceId = String($button.attr('data-space-id') || '');
                const spaceName = String($button.attr('data-space-name') || '');
                showSpaceSuspensions(spaceId, spaceName);
            });

            $(document).on('click', '.admin-space-suspension-open', function () {
                const $button = $(this);
                const $form = $('#admin-space-suspension-form');
                const spaceId = String($button.attr('data-space-id') || '');
                const spaceName = String($button.attr('data-space-name') || '');

                $form[0].reset();
                $form.find('input[name="espaco_treino_id"]').val(spaceId);
                $form.find('input[name="espaco_treino_nome"]').val(spaceName);
                $('#admin-space-suspension-subtitle').text(spaceName);
                getSuspensionFormModal().removeClass('hidden').attr('aria-hidden', 'false');

                window.setTimeout(function () {
                    $form.find('input[name="data_inicio"]').trigger('focus');
                }, 0);
            });

            $(document).on('click', '#admin-space-suspension-close, #admin-space-suspension-cancel', function () {
                closeSuspensionFormModal();
            });

            $(document).on('click', '#admin-space-suspension-modal', function (event) {
                if (event.target === this) {
                    closeSuspensionFormModal();
                }
            });

            $(document).on('submit', '#admin-space-suspension-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    dataType: 'json',
                    data: $form.serialize() + '&' + $.param(currentSpaceFilters()),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar a suspensão.'));
                        return;
                    }

                    updateManagementFragments(response);
                    closeSuspensionFormModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Suspensão de espaço salva com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.admin-space-suspension-deactivate-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const $modal = getModal();
                const spaceId = String($modal.attr('data-current-space-id') || '');
                const spaceName = String($modal.attr('data-current-space-name') || '');

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    dataType: 'json',
                    data: $form.serialize() + '&' + $.param(currentSpaceFilters()),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível inativar a suspensão.'));
                        return;
                    }

                    updateManagementFragments(response);
                    showSpaceSuspensions(spaceId, spaceName);
                    App.core.abrirPopup('sucesso', String(response.message || 'Suspensão de espaço inativada com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.admin-space-suspension-delete-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const $modal = getModal();
                const spaceId = String($modal.attr('data-current-space-id') || '');
                const spaceName = String($modal.attr('data-current-space-name') || '');

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    dataType: 'json',
                    data: $form.serialize() + '&' + $.param(currentSpaceFilters()),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir a suspensão.'));
                        return;
                    }

                    updateManagementFragments(response);
                    showSpaceSuspensions(spaceId, spaceName);
                    App.core.abrirPopup('sucesso', String(response.message || 'Suspensão futura excluída com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('click', '#admin-location-suspensions-close, #admin-location-suspensions-cancel', function () {
                closeModal();
            });

            $(document).on('click', '#admin-location-suspensions-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape' && !getSuspensionFormModal().hasClass('hidden')) {
                    closeSuspensionFormModal();
                    return;
                }

                if (event.key === 'Escape' && !getModal().hasClass('hidden')) {
                    closeModal();
                }
            });
        },

        iniciarEditorConteudoHome: function () {
            function renderPreview(key, $form, $target) {
                $target.empty();
                if (key === 'apresentacao') {
                    $target.append($('<span>', { class: 'eyebrow', text: String($form.find('[name="selo"]').val() || '') }));
                    $target.append($('<h2>').text(String($form.find('[name="titulo"]').val() || '')));
                    $target.append($('<p>').text(String($form.find('[name="texto"]').val() || '')));
                } else if (key === 'destaques') {
                    const $grid = $('<div>', { class: 'section-grid' });
                    for (let index = 1; index <= 3; index += 1) {
                        $grid.append($('<article>', { class: 'info-card' })
                            .append($('<h3>').text(String($form.find('[name="destaque_' + index + '_titulo"]').val() || '')))
                            .append($('<p>').text(String($form.find('[name="destaque_' + index + '_texto"]').val() || ''))));
                    }
                    $target.append($grid);
                } else {
                    $target.append($('<h2>').text(String($form.find('[name="titulo"]').val() || '')));
                    const $list = $('<div>', { class: 'home-info-list' });
                    for (let index = 1; index <= 5; index += 1) {
                        const text = String($form.find('[name="paragrafo_' + index + '"]').val() || '').trim();
                        if (text !== '') {
                            $list.append($('<p>').text('• ' + text));
                        }
                    }
                    $target.append($list);
                }
            }

            App.admin.montarPreviaConteudoHome = function () {
                const configs = [
                    { selector: '#admin-home-footer-form', key: 'rodape', title: 'Rodapé' },
                    { selector: '#admin-home-logo-form', key: 'logotipo', title: 'Logotipo' },
                    { selector: '#admin-home-contact-form', key: 'contato', title: 'Faixa de contato' },
                    { selector: '#admin-home-hero-form', key: 'apresentacao', title: 'Quadro principal' },
                    { selector: '#admin-home-highlights-form', key: 'destaques', title: 'Quadros destacados' },
                    { selector: '#admin-home-info-form', key: 'quadro_informativo', title: 'Quadro informativo' },
                    { selector: '#admin-home-courses-locations-form', key: 'locais_cursos', title: 'Locais dos cursos esportivos' },
                    { selector: '#admin-home-training-locations-form', key: 'locais_treinos', title: 'Locais de treinos' },
                    { selector: '#admin-home-course-modalities-form', key: 'modalidades_cursos', title: 'Modalidades dos cursos esportivos' }
                ];
                configs.forEach(function (config) {
                    const $form = $(config.selector);
                    if ($form.length === 0 || $form.attr('data-preview-mounted') === '1') return;
                    $form.attr('data-preview-mounted', '1').attr('data-manual-submit', '1').removeAttr('data-ajax-form');
                    const $container = $form.closest('section').first();
                    const modalId = 'admin-home-editor-' + config.key.replace('_', '-');
                    const $modal = $('<div>', { id: modalId, class: 'popup-overlay hidden', 'aria-hidden': 'true' });
                    const $card = $('<div>', { class: 'popup-card popup-admin-card', role: 'dialog', 'aria-modal': 'true' });
                    $card.append($('<div>', { class: 'popup-head admin-popup-head' })
                        .append($('<h3>').text('Editar ' + config.title.toLowerCase()))
                        .append($('<button>', { type: 'button', class: 'popup-close-icon', 'data-home-editor-close': '1', text: '×' })));
                    $card.append($('<div>', { class: 'popup-body admin-popup-body' }).append($form.detach()));
                    $modal.append($card);

                    const directPreview = $('[data-home-admin-preview="1"]').length > 0;
                    const $previewBody = $('<div>', { class: 'admin-home-content-preview', 'data-home-preview-body': config.key });
                    const $preview = $('<section>', { class: 'content-card admin-home-preview-card', 'data-home-preview': config.key })
                        .append($('<div>', { class: 'section-head' })
                            .append($('<h2>').text(config.title))
                            .append($('<div>', { class: 'admin-home-preview-actions' })
                                .append($('<button>', { type: 'button', class: 'btn btn-secondary admin-home-small-button', 'data-home-edit': config.key, text: 'Editar' }))
                                .append($('<button>', { type: 'button', class: 'btn btn-primary admin-home-small-button', 'data-home-publish': config.key, text: 'Publicar' }))))
                        .append($previewBody);
                    $('[data-admin-section="pagina-home"]').append($modal);
                    if (directPreview) {
                        $container.remove();
                    } else {
                        $container.replaceWith($preview);
                        renderPreview(config.key, $form, $previewBody);
                    }
                });
            };

            $(document).on('click', '[data-home-edit]', function () {
                const key = String($(this).attr('data-home-edit') || '').replace('_', '-');
                $('#admin-home-editor-' + key).removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-home-editor-close]', function () {
                $(this).closest('.popup-overlay').addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('change', '#admin-home-logo-form [name="logo_arquivo"]', function () {
                const file = this.files && this.files[0];
                const preview = document.getElementById('admin-home-logo-preview');
                if (!file || !preview) return;
                const temporaryUrl = URL.createObjectURL(file);
                preview.addEventListener('load', function releaseTemporaryUrl() {
                    URL.revokeObjectURL(temporaryUrl);
                    preview.removeEventListener('load', releaseTemporaryUrl);
                });
                preview.src = temporaryUrl;
            });

            $(document).on('submit', '#admin-home-footer-form, #admin-home-logo-form, #admin-home-contact-form, #admin-home-info-form, #admin-home-highlights-form, #admin-home-hero-form, #admin-home-courses-locations-form, #admin-home-training-locations-form, #admin-home-course-modalities-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const formKeys = {
                    'admin-home-footer-form': 'rodape',
                    'admin-home-logo-form': 'logotipo',
                    'admin-home-contact-form': 'contato',
                    'admin-home-info-form': 'quadro_informativo',
                    'admin-home-highlights-form': 'destaques',
                    'admin-home-hero-form': 'apresentacao',
                    'admin-home-courses-locations-form': 'locais_cursos',
                    'admin-home-training-locations-form': 'locais_treinos',
                    'admin-home-course-modalities-form': 'modalidades_cursos'
                };
                const key = String(formKeys[String($form.attr('id') || '')] || '');
                const isUpload = $form.is('#admin-home-logo-form');
                const request = { url: String($form.attr('action')), method: 'POST', dataType: 'json', data: isUpload ? new FormData(this) : $form.serialize(), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
                if (isUpload) {
                    request.processData = false;
                    request.contentType = false;
                }
                $.ajax(request)
                    .done(function (response) {
                        if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o rascunho.')); return; }
                        if ($('[data-home-admin-preview="1"]').length > 0 && typeof App.admin.activateSection === 'function') {
                            App.admin.activateSection('pagina-home');
                        } else {
                            renderPreview(key, $form, $('[data-home-preview-body="' + key + '"]'));
                            $form.closest('.popup-overlay').addClass('hidden').attr('aria-hidden', 'true');
                        }
                        App.core.abrirPopup('sucesso', String(response.message || 'Rascunho salvo.'));
                    }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            });

            $(document).on('click', '[data-home-publish]', function () {
                const $button = $(this);
                $.ajax({ url: App.core.buildUrl('/admin/home-publicar'), method: 'POST', dataType: 'json', data: { chave: String($button.attr('data-home-publish') || '') }, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (response) { if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível publicar.')); return; } App.core.abrirPopup('sucesso', String(response.message || 'Conteúdo publicado.')); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            });

            function linkPairs($form) {
                const type = String($form.attr('data-conditional-links-form') || '');
                const pairs = [];
                if (type === 'popup') {
                    pairs.push([$form.find('[name="rotulo_acao"]'), $form.find('[name="url_acao"]')]);
                } else if (type === 'home-info') {
                    for (let index = 1; index <= 5; index += 1) {
                        pairs.push([$form.find('[name="paragrafo_' + index + '_link_rotulo"]'), $form.find('[name="paragrafo_' + index + '_link_url"]')]);
                    }
                } else if (type === 'highlights') {
                    for (let index = 1; index <= 3; index += 1) {
                        pairs.push([$form.find('[name="destaque_' + index + '_link_rotulo"]'), $form.find('[name="destaque_' + index + '_link_url"]')]);
                    }
                }
                return pairs;
            }

            function validatePair($label, $url, forceMessage) {
                if ($label.length === 0 || $url.length === 0) {
                    return true;
                }
                const labelFilled = String($label.val() || '').trim() !== '';
                const urlFilled = String($url.val() || '').trim() !== '';
                const active = labelFilled || urlFilled;
                $label.prop('required', active);
                $url.prop('required', active);
                App.core.validarCampoInline($label[0], forceMessage === true);
                App.core.validarCampoInline($url[0], forceMessage === true);
                return !active || (labelFilled && urlFilled);
            }

            function validateConditionalForm(form, forceMessage) {
                const $form = $(form);
                let valid = true;
                linkPairs($form).forEach(function (pair) {
                    valid = validatePair(pair[0], pair[1], forceMessage) && valid;
                });

                if (String($form.attr('data-conditional-links-form') || '') === 'hero') {
                    const count = Math.max(0, Math.min(2, Number($form.find('[name="quantidade_botoes"]').val() || 0)));
                    for (let index = 1; index <= 2; index += 1) {
                        const enabled = index <= count;
                        const $label = $form.find('[name="botao_' + index + '_rotulo"]');
                        const $url = $form.find('[name="botao_' + index + '_url"]');
                        $label.prop('required', enabled);
                        $url.prop('required', enabled);
                        if (enabled) {
                            App.core.validarCampoInline($label[0], forceMessage === true);
                            App.core.validarCampoInline($url[0], forceMessage === true);
                            valid = (String($label.val() || '').trim() !== '' && String($url.val() || '').trim() !== '') && valid;
                        }
                    }
                }
                return valid;
            }

            $(document).on('change', '#admin-home-hero-button-count', function () {
                const count = Math.max(0, Math.min(2, Number($(this).val() || 0)));
                $('[data-home-hero-button-fields]').each(function () {
                    const index = Number($(this).attr('data-home-hero-button-fields') || 0);
                    const enabled = index <= count;
                    $(this).toggleClass('hidden', !enabled);
                    $(this).find('input').prop('required', enabled);
                });
            });

            $(document).on('input change', '[data-conditional-links-form] input, [data-conditional-links-form] select', function () {
                validateConditionalForm($(this).closest('form')[0], false);
            });

            if (!document.documentElement.hasAttribute('data-home-conditional-validation')) {
                document.documentElement.setAttribute('data-home-conditional-validation', '1');
                document.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (!form || !form.matches || !form.matches('[data-conditional-links-form]')) {
                        return;
                    }
                    if (!validateConditionalForm(form, true)) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        const invalid = form.querySelector('.field-invalid');
                        if (invalid) {
                            invalid.focus();
                        }
                    }
                }, true);
            }
        },

        iniciarGerenciamentoModalidades: function () {
            let filterTimer = null;
            let filterRequest = null;

            function $modal() { return $('#admin-modality-modal'); }
            function closeModal() { $modal().addClass('hidden').attr('aria-hidden', 'true'); }
            function clearErrors() {
                $('[data-modality-error]').addClass('hidden').text('');
                $('#admin-modality-form-error').addClass('hidden').text('');
                $('#admin-modality-form [name]').removeClass('is-invalid');
            }
            function showFieldError(name, message) {
                $('#admin-modality-form [name="' + name + '"]').addClass('is-invalid');
                $('[data-modality-error="' + name + '"]').removeClass('hidden').text(message);
            }
            function refreshList($form) {
                if (!$form || $form.length === 0) return;
                const $limit = $form.find('[name="modality_limit"]');
                const parsed = Number.parseInt(String($limit.val() || '10'), 10);
                const limit = Math.max(1, Math.min(50, Number.isFinite(parsed) ? parsed : 10));
                $limit.val(String(limit));
                if (filterRequest) filterRequest.abort();
                filterRequest = $.ajax({
                    url: App.core.buildUrl('/admin/modalidades/lista'), method: 'GET', dataType: 'json', suppressGlobalLoading: true,
                    data: { modality_search: String($form.find('[name="modality_search"]').val() || ''), modality_limit: limit }
                }).done(function (response) {
                    if (!response || response.success === false || typeof response.html !== 'string') {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar as modalidades.'));
                        return;
                    }
                    $('#admin-modality-list-body').html(response.html);
                }).fail(function (xhr, status) {
                    if (status !== 'abort') App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { filterRequest = null; });
            }
            function openCreateModal() {
                const $form = $('#admin-modality-form');
                $form[0].reset();
                $form.find('[name="modalidade_id"]').val('');
                $form.find('[name="ativo"]').val('1');
                clearErrors();
                $('#admin-modality-modal-title').text('Criar modalidade');
                $modal().removeClass('hidden').attr('aria-hidden', 'false');
                $form.find('[name="nome"]').trigger('focus');
            }

            $(document).on('submit', '#admin-modality-filter-form', function (event) { event.preventDefault(); refreshList($(this)); });
            $(document).on('input', '#admin-modality-search, #admin-modality-filter-form [name="modality_limit"]', function () {
                const $form = $(this).closest('form');
                window.clearTimeout(filterTimer);
                filterTimer = window.setTimeout(function () { refreshList($form); }, 300);
            });
            $(document).on('click', '#admin-modality-create', openCreateModal);
            $(document).on('click', '[data-admin-modality-close="1"], #admin-modality-modal', function (event) {
                if ($(event.target).is('#admin-modality-modal') || $(event.target).is('[data-admin-modality-close="1"]')) closeModal();
            });
            $(document).on('click', '.admin-modality-edit', function () {
                const id = Number($(this).data('modalityId') || 0);
                $.getJSON(App.core.buildUrl('/admin/modalidades/detalhe'), { id: id }).done(function (response) {
                    if (!response || !response.success) { App.core.abrirPopup('erro', String((response && response.message) || 'Modalidade não encontrada.')); return; }
                    const item = response.modality || {};
                    const $form = $('#admin-modality-form');
                    clearErrors();
                    $form.find('[name="modalidade_id"]').val(String(item.id || ''));
                    $form.find('[name="nome"]').val(String(item.nome || ''));
                    $form.find('[name="tipo_ambiente"]').val(String(item.tipo_ambiente || ''));
                    $form.find('[name="ativo"]').val(Number(item.ativo || 0) === 1 ? '1' : '0');
                    $('#admin-modality-modal-title').text('Editar modalidade');
                    $modal().removeClass('hidden').attr('aria-hidden', 'false');
                }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            });
            $(document).on('click', '.admin-modality-delete', function () {
                const $button = $(this);
                const id = Number($button.data('modalityId') || 0);
                const name = String($button.data('modalityName') || 'esta modalidade');
                if (id <= 0 || !window.confirm('Deseja realmente excluir "' + name + '"? Esta ação não poderá ser desfeita.')) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/modalidades/excluir'),
                    method: 'POST', dataType: 'json', data: { modalidade_id: id },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir a modalidade.'));
                        return;
                    }
                    refreshList($('#admin-modality-filter-form'));
                    App.core.abrirPopup('sucesso', String(response.message || 'Modalidade excluída com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });
            function scheduleModal() { return $('#admin-modality-schedule-modal'); }
            function closeScheduleModal() { scheduleModal().addClass('hidden').attr('aria-hidden', 'true'); }
            function scheduleData(attribute) {
                try { return JSON.parse(String($('[data-admin-section="modalidades"]').attr(attribute) || '[]')); } catch (error) { return []; }
            }
            function toLocalDateTime(value) { return String(value || '').replace(' ', 'T').slice(0, 16); }
            function fillScheduleForm(record) {
                const $form = $('#admin-modality-schedule-form');
                $form[0].reset();
                Object.keys(record || {}).forEach(function (key) {
                    const $field = $form.find('[name="' + key + '"]');
                    if (!$field.length) return;
                    if ($field.attr('type') === 'checkbox') $field.prop('checked', Number(record[key] || 0) === 1);
                    else if ($field.attr('type') === 'datetime-local') $field.val(toLocalDateTime(record[key]));
                    else $field.val(record[key] == null ? '' : String(record[key]));
                });
                const hasNotice = $form.find('[name="possui_edital"]').is(':checked');
                $form.find('[data-modality-schedule-notice-fields="1"]').toggleClass('hidden', !hasNotice);
                $form.find('[name="numero_edital"], [name="link_edital"]').prop('required', hasNotice);
            }
            $(document).on('click', '#admin-modality-schedule-create', function () {
                fillScheduleForm({});
                $('#admin-modality-schedule-modal-title').text('Criar cronograma');
                scheduleModal().removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '.admin-modality-schedule-edit', function () {
                let record = {}; try { record = JSON.parse(String($(this).attr('data-schedule') || '{}')); } catch (error) { record = {}; }
                fillScheduleForm(record);
                $('#admin-modality-schedule-modal-title').text('Editar cronograma');
                scheduleModal().removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-modality-schedule-close="1"], #admin-modality-schedule-modal', function (event) {
                if ($(event.target).is('#admin-modality-schedule-modal') || $(event.target).is('[data-modality-schedule-close="1"]')) closeScheduleModal();
            });
            $(document).on('change', '#admin-modality-schedule-form [name="temporada_id"]', function () {
                const season = scheduleData('data-modality-seasons').find(function (item) { return String(item.id) === String($(this).val()); }.bind(this));
                if (!season || Number($('#admin-modality-schedule-form [name="cronograma_modalidade_id"]').val() || 0) > 0) return;
                ['inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim'].forEach(function (field) { $('#admin-modality-schedule-form [name="' + field + '"]').val(toLocalDateTime(season[field])); });
                ['data_inicio', 'data_fim', 'aulas_inicio', 'aulas_fim'].forEach(function (field) { $('#admin-modality-schedule-form [name="' + field + '"]').val(String(season[field] || '')); });
                const modalityText = $('#admin-modality-schedule-form [name="modalidade_id"] option:selected').text();
                $('#admin-modality-schedule-form [name="nome"]').val((modalityText && modalityText !== 'Selecione' ? modalityText + ' - ' : '') + String(season.nome || ''));
            });
            $(document).on('change', '#admin-modality-schedule-form [name="modalidade_id"]', function () { $('#admin-modality-schedule-form [name="temporada_id"]').trigger('change'); });
            $(document).on('change', '[data-modality-schedule-notice-toggle="1"]', function () {
                const enabled = $(this).is(':checked');
                $('#admin-modality-schedule-form [data-modality-schedule-notice-fields="1"]').toggleClass('hidden', !enabled).find('input').prop('required', enabled);
            });
            $(document).on('submit', '#admin-modality-schedule-form', function (event) {
                event.preventDefault(); const $form = $(this); const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({ url: App.core.buildUrl('/admin/modalidades/cronogramas'), method: 'POST', dataType: 'json', data: $form.serialize() })
                    .done(function (response) { if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o cronograma.')); return; } closeScheduleModal(); App.admin.activateSection('modalidades'); App.core.abrirPopup('sucesso', String(response.message)); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); }).always(function () { $button.prop('disabled', false); });
            });
            $(document).on('click', '.admin-modality-schedule-delete', function () {
                const $button = $(this); const id = Number($button.data('scheduleId') || 0); const name = String($button.data('scheduleName') || 'este cronograma');
                if (!window.confirm('Deseja realmente excluir "' + name + '"?')) return;
                $.post(App.core.buildUrl('/admin/modalidades/cronogramas/excluir'), { cronograma_modalidade_id: id }, function (response) { if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir o cronograma.')); return; } App.admin.activateSection('modalidades'); App.core.abrirPopup('sucesso', String(response.message)); }, 'json').fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            });
            $(document).on('submit', '#admin-modality-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                clearErrors();
                const name = String($form.find('[name="nome"]').val() || '').trim();
                const environment = String($form.find('[name="tipo_ambiente"]').val() || '');
                let valid = true;
                if (!name) { showFieldError('nome', 'Informe o nome da modalidade.'); valid = false; }
                if (!environment) { showFieldError('tipo_ambiente', 'Selecione o tipo de ambiente.'); valid = false; }
                if (!valid) return;
                const editing = Number($form.find('[name="modalidade_id"]').val() || 0) > 0;
                $.ajax({
                    url: App.core.buildUrl(editing ? '/admin/modalidades/atualizar' : '/admin/modalidades'),
                    method: 'POST', dataType: 'json', data: $form.serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        $('#admin-modality-form-error').removeClass('hidden').text(String((response && response.message) || 'Não foi possível salvar a modalidade.'));
                        return;
                    }
                    closeModal();
                    refreshList($('#admin-modality-filter-form'));
                    App.core.abrirPopup('sucesso', String(response.message || 'Modalidade salva com sucesso.'));
                }).fail(function (xhr) {
                    $('#admin-modality-form-error').removeClass('hidden').text(App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            });
        },

        iniciarMigracaoCadastrosExternos: function () {
            let filterTimer = null;
            let filterRequest = null;
            let filterSequence = 0;

            function currentFilters($form, skipSummary) {
                const $panel = $('#admin-external-migration-panel');
                return {
                    migration_search: String($form.find('[name="migration_search"]').val() || ''),
                    migration_limit: String($form.find('[name="migration_limit"]').val() || '20'),
                    skip_summary: skipSummary === true ? '1' : '0',
                    summary_total: String($panel.data('summaryTotal') || '0'),
                    summary_cpfs: String($panel.data('summaryCpfs') || '0'),
                    summary_pendentes: String($panel.data('summaryPendentes') || '0'),
                    summary_migrados: String($panel.data('summaryMigrados') || '0')
                };
            }

            function refreshPanel($form, preserveFocus) {
                const filters = currentFilters($form, preserveFocus === true);
                const requestSequence = ++filterSequence;
                const selectionStart = $form.find('[name="migration_search"]')[0]
                    ? $form.find('[name="migration_search"]')[0].selectionStart
                    : null;

                if (filterRequest) {
                    filterRequest.abort();
                }

                filterRequest = $.ajax({
                    url: App.core.buildUrl('/admin/migracao-cadastros/lista'),
                    method: 'GET',
                    dataType: 'json',
                    data: filters,
                    suppressGlobalLoading: preserveFocus === true
                }).done(function (response) {
                    if (requestSequence !== filterSequence) {
                        return;
                    }

                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a lista.'));
                        return;
                    }

                    $('#admin-external-migration-panel').replaceWith(String(response.html));
                    if (preserveFocus === true) {
                        window.requestAnimationFrame(function () {
                            const $search = $('.admin-external-migration-search').first();
                            $search.trigger('focus');
                            if ($search[0] && typeof $search[0].setSelectionRange === 'function') {
                                const position = Number.isFinite(selectionStart) ? selectionStart : filters.migration_search.length;
                                $search[0].setSelectionRange(position, position);
                            }
                        });
                    }
                }).fail(function (xhr, status) {
                    if (status !== 'abort') {
                        App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                    }
                }).always(function () {
                    if (requestSequence === filterSequence) {
                        filterRequest = null;
                    }
                });
            }

            function closeDetailsModal() {
                $('#admin-external-migration-modal').addClass('hidden').attr('aria-hidden', 'true');
                $('[data-external-migration-modal-content="1"]').empty();
            }

            function importMigrationBatch($button, cursor, totalProcessed, batchNumber, baseMaxExternalId, changedSince) {
                $button.prop('disabled', true).text('Importando lote ' + String(batchNumber) + '...');

                $.ajax({
                    url: App.core.buildUrl('/admin/migracao-cadastros/importar'),
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        cursor: String(cursor || 0),
                        base_max_id_externo: baseMaxExternalId == null ? '' : String(baseMaxExternalId),
                        alterado_desde: changedSince == null ? '' : String(changedSince)
                    },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        $button.prop('disabled', false).text('Importar ou atualizar dados');
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível importar os dados.'));
                        return;
                    }

                    const processed = Number(response.processados || 0);
                    const accumulated = totalProcessed + (Number.isFinite(processed) ? processed : 0);

                    if (response.tem_mais === true && Number(response.proximo_cursor || 0) > Number(cursor || 0)) {
                        importMigrationBatch(
                            $button,
                            Number(response.proximo_cursor),
                            accumulated,
                            batchNumber + 1,
                            Number(response.base_max_id_externo || 0),
                            String(response.alterado_desde || '')
                        );
                        return;
                    }

                    $button.prop('disabled', false).text('Importar ou atualizar dados');
                    App.core.abrirPopup('sucesso', String(accumulated) + ' registros externos foram processados em lotes de até 100 linhas.');
                    refreshPanel($('[data-external-migration-filter="1"]').first(), false);
                }).fail(function (xhr) {
                    $button.prop('disabled', false).text('Importar ou atualizar dados');
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            }

            $(document).on('submit', '[data-external-migration-filter="1"]', function (event) {
                event.preventDefault();
                refreshPanel($(this), false);
            });

            $(document).on('input', '.admin-external-migration-search', function () {
                const $form = $(this).closest('form');
                window.clearTimeout(filterTimer);
                filterTimer = window.setTimeout(function () {
                    refreshPanel($form, true);
                }, 400);
            });

            $(document).on('change', '[data-external-migration-filter="1"] [name="migration_limit"]', function () {
                refreshPanel($(this).closest('form'), false);
            });

            $(document).on('click', '[data-external-migration-details="1"]', function () {
                const id = String($(this).data('migrationId') || '0');
                const $modal = $('#admin-external-migration-modal');
                const $content = $('[data-external-migration-modal-content="1"]');

                $content.html('<p class="muted">Carregando dados...</p>');
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                $.getJSON(App.core.buildUrl('/admin/migracao-cadastros/detalhe'), { id: id })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            closeDetailsModal();
                            App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar os dados.'));
                            return;
                        }
                        $content.html(String(response.html));
                    }).fail(function (xhr) {
                        closeDetailsModal();
                        App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                    });
            });

            $(document).on('click', '[data-external-migration-modal-close="1"], #admin-external-migration-modal', function (event) {
                if ($(event.target).is('#admin-external-migration-modal') || $(event.target).is('[data-external-migration-modal-close="1"]')) {
                    closeDetailsModal();
                }
            });

            $(document).on('click', '[data-external-migration-import="1"]', function () {
                const $button = $(this);
                importMigrationBatch($button, 0, 0, 1, null, null);
            });

            function importHealthCertificateBatch($button, type, cursorDate, cursorId, total, snapshotDate, snapshotId) {
                $button.prop('disabled', true).text('Importando... ' + total);
                $.ajax({
                    url: App.core.buildUrl('/admin/migracao-atestados/importar'),
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        tipo_atestado: type, cursor_data: cursorDate, cursor_id: cursorId,
                        snapshot_data: snapshotDate == null ? '' : snapshotDate,
                        snapshot_id: snapshotId == null ? '' : snapshotId
                    },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        $button.prop('disabled', false).text(type === 'clinico' ? 'Importar clínicos' : 'Importar dermatológicos');
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível importar os atestados.'));
                        return;
                    }
                    const accumulated = total + Number(response.processados || 0);
                    const nextDate = String(response.proxima_data || '');
                    const nextId = Number(response.proximo_id || 0);
                    if (response.tem_mais && (nextDate !== String(cursorDate || '') || nextId !== Number(cursorId || 0))) {
                        importHealthCertificateBatch(
                            $button, type, nextDate, nextId, accumulated,
                            String(response.snapshot_data || ''), Number(response.snapshot_id || 0)
                        );
                        return;
                    }
                    $button.prop('disabled', false).text(type === 'clinico' ? 'Importar clínicos' : 'Importar dermatológicos');
                    const completionMessage = accumulated > 0
                        ? 'Importação concluída. ' + accumulated + ' linhas válidas foram analisadas em lotes de até 100.'
                        : 'Importação concluída. Não há novos atestados para importar.';
                    App.core.abrirPopup('sucesso', completionMessage);
                    $('[data-admin-nav-target="migracao-atestados"]').trigger('click');
                }).fail(function (xhr) {
                    $button.prop('disabled', false).text(type === 'clinico' ? 'Importar clínicos' : 'Importar dermatológicos');
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            }

            $(document).on('click', '[data-health-migration-import]', function () {
                const $button = $(this);
                importHealthCertificateBatch($button, String($button.data('healthMigrationImport') || ''), '', 0, 0, null, null);
            });

            let healthMigrationSearchTimer = null;

            function refreshHealthMigrationSection($form) {
                if (!$form || $form.length === 0 || typeof App.admin.activateSection !== 'function') return;
                const params = {};
                const activeField = window.document.activeElement;

                if (activeField && $form.has(activeField).length > 0 && activeField.name) {
                    App.state.healthMigrationFocus = {
                        name: String(activeField.name),
                        position: typeof activeField.selectionStart === 'number'
                            ? activeField.selectionStart
                            : String($(activeField).val() || '').length
                    };
                }
                $.each($form.serializeArray(), function (_, field) {
                    params[field.name] = field.value;
                });
                App.admin.activateSection('migracao-atestados', params, { suppressGlobalLoading: true });
            }

            $(document).on('submit', '[data-health-migration-filter="1"]', function (event) {
                event.preventDefault();
                refreshHealthMigrationSection($(this));
            });

            $(document).on('input', '[data-health-migration-search="1"]', function () {
                const $form = $(this).closest('form');
                window.clearTimeout(healthMigrationSearchTimer);
                healthMigrationSearchTimer = window.setTimeout(function () {
                    refreshHealthMigrationSection($form);
                }, 350);
            });

            $(document).on('change', '[data-health-migration-limit="1"]', function () {
                refreshHealthMigrationSection($(this).closest('form'));
            });

            $(document).on('click', '[data-external-migration-delete="1"]', function () {
                const $button = $(this);
                const name = String($button.data('migrationName') || 'este registro');
                if (!window.confirm('Deseja excluir da tabela de migração o registro de ' + name + '?')) {
                    return;
                }
                $.ajax({
                    url: App.core.buildUrl('/admin/migracao-cadastros/remover'),
                    method: 'POST',
                    dataType: 'json',
                    data: { id: String($button.data('migrationId') || '0') },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível remover o registro.'));
                        return;
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Registro removido.'));
                    refreshPanel($('[data-external-migration-filter="1"]').first(), false);
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            });

        },

        iniciarGerenciamentoTemporadasTurmas: function () {
            function modalFor(type) {
                return $(type === 'season' ? '#course-season-modal' : '#course-class-modal');
            }

            function closeModals() {
                $('#course-season-modal, #course-class-modal, #course-professor-modal').addClass('hidden').attr('aria-hidden', 'true');
            }

            function normalizeDateTime(value) {
                const text = String(value == null ? '' : value);
                return text.length >= 16 ? text.slice(0, 16).replace(' ', 'T') : text;
            }

            function ensureClassAgeCriterionField($form) {
                const $ageGrid = $form.find('[name="idade_maxima"]').closest('.grid-two');
                const $anchor = $ageGrid.length ? $ageGrid : $form.find('[name="idade_maxima"]').closest('label');
                if ($form.find('[name="dias_semana[]"]').length === 0) {
                    const dayNames = { 1: 'Segunda', 2: 'Terça', 3: 'Quarta', 4: 'Quinta', 5: 'Sexta', 6: 'Sábado', 7: 'Domingo' };
                    const $days = $('<fieldset>', { class: 'course-weekdays-field' }).append($('<legend>', { text: 'Dias da semana' }));
                    const $options = $('<div>', { class: 'course-weekdays-options' });
                    Object.keys(dayNames).forEach(function (value) {
                        $options.append($('<label>', { class: 'checkbox-chip' })
                            .append($('<input>', { type: 'checkbox', name: 'dias_semana[]', value: value }))
                            .append($('<span>', { text: dayNames[value] })));
                    });
                    $days.append($options).append($('<small>', { class: 'muted', text: 'Selecione qualquer combinação, inclusive sábado e domingo.' }));
                    const $times = $('<div>', { class: 'grid-two' })
                        .append($('<label>').append($('<span>', { text: 'Horário inicial' })).append($('<input>', { type: 'time', name: 'hora_inicio' })))
                        .append($('<label>').append($('<span>', { text: 'Horário final' })).append($('<input>', { type: 'time', name: 'hora_fim' })));
                    const $name = $form.find('[name="nome"]').closest('label');
                    $name.after($days, $times);
                }
                if ($form.find('[name="criterio_faixa_etaria"]').length === 0) {
                    const $field = $('<label>', { class: 'course-age-criterion-field' })
                        .append($('<span>', { text: 'Critério da faixa etária' }))
                        .append($('<select>', { name: 'criterio_faixa_etaria', required: true })
                            .append($('<option>', { value: 'idade_exata', text: 'Usar idade exata pela data de nascimento' }))
                            .append($('<option>', { value: 'ano_nascimento', text: 'Usar apenas o ano de nascimento' })))
                        .append($('<small>', { class: 'muted', text: 'No modo por ano, o sistema ignora o dia e o mês de nascimento.' }));
                    $anchor.after($field);
                }
                if ($form.find('[name="sexo"]').length === 0) {
                    const $sexField = $('<label>', { class: 'course-sex-field' })
                        .append($('<span>', { text: 'Sexo permitido' }))
                        .append($('<select>', { name: 'sexo' })
                            .append($('<option>', { value: '', text: 'Todos' }))
                            .append($('<option>', { value: 'masculino', text: 'Masculino' }))
                            .append($('<option>', { value: 'feminino', text: 'Feminino' })))
                        .append($('<small>', { class: 'muted', text: 'Escolha Todos quando a turma não tiver restrição por sexo.' }));
                    const $criterionField = $form.find('[name="criterio_faixa_etaria"]').closest('label');
                    ($criterionField.length ? $criterionField : $anchor).after($sexField);
                }
            }

            function ensureClassScheduleField($form) {
                if ($form.find('[name="cronograma_modalidade_id"]').length) return;
                let schedules = [];
                try { schedules = JSON.parse(String($form.closest('[data-course-modality-schedules]').attr('data-course-modality-schedules') || '[]')); } catch (error) { schedules = []; }
                const $select = $('<select>', { name: 'cronograma_modalidade_id', required: true }).append($('<option>', { value: '', text: 'Selecione a temporada e a modalidade' }));
                schedules.forEach(function (schedule) {
                    $select.append($('<option>', { value: String(schedule.id), text: String(schedule.nome || '') })
                        .attr('data-season-id', String(schedule.temporada_id || ''))
                        .attr('data-modality-id', String(schedule.modalidade_id || '')));
                });
                $form.find('[name="modalidade_id"]').closest('label').after($('<label>').append($('<span>', { text: 'Cronograma da modalidade' })).append($select));
            }

            function filterClassSchedules($form, selectedId) {
                const seasonId = String($form.find('[name="temporada_id"]').val() || '');
                const modalityId = String($form.find('[name="modalidade_id"]').val() || '');
                const $select = $form.find('[name="cronograma_modalidade_id"]');
                $select.find('option[data-season-id]').each(function () {
                    const visible = String($(this).attr('data-season-id')) === seasonId && String($(this).attr('data-modality-id')) === modalityId;
                    $(this).prop('disabled', !visible).prop('hidden', !visible);
                });
                if (selectedId) $select.val(String(selectedId));
                if (!$select.val() || $select.find('option:selected').prop('disabled')) $select.val('');
            }

            function fillForm($form, record) {
                $form[0].reset();
                Object.keys(record || {}).forEach(function (name) {
                    const $field = $form.find('[name="' + name + '"]');
                    if ($field.length === 0) return;
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', Number(record[name] || 0) === 1);
                    } else if ($field.attr('type') === 'datetime-local') {
                        $field.val(normalizeDateTime(record[name]));
                    } else {
                        $field.val(record[name] == null ? '' : String(record[name]));
                    }
                });
                const selectedDays = String((record || {}).dias_semana || '').split(',');
                $form.find('[name="dias_semana[]"]').each(function () {
                    $(this).prop('checked', selectedDays.indexOf(String($(this).val())) !== -1);
                });
            }

            function ensureSeasonNoticeFields($form) {
                if ($form.find('[name="origem_temporada_id"]').length === 0) {
                    const $name = $form.find('[name="nome"]').closest('label');
                    let origins = [];
                    try {
                        origins = JSON.parse(String($form.closest('[data-course-season-origins]').attr('data-course-season-origins') || '[]'));
                    } catch (error) {
                        origins = [];
                    }
                    const $originSelect = $('<select>', { name: 'origem_temporada_id', required: true })
                        .append($('<option>', { value: '', text: 'Selecione' }));
                    origins.forEach(function (origin) {
                        const inactiveSuffix = Number(origin.ativo || 0) === 1 ? '' : ' (inativa)';
                        $originSelect.append($('<option>', {
                            value: String(origin.id || ''),
                            text: String(origin.nome || '') + inactiveSuffix
                        }));
                    });
                    const $origin = $('<label>')
                        .append($('<span>', { text: 'Instituição gestora (origem)' }))
                        .append($originSelect);
                    const $toggle = $('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'checkbox', name: 'possui_edital', value: '1', 'data-season-notice-toggle': '1' }))
                        .append($('<span>', { text: 'Esta temporada possui edital' }));
                    const $fields = $('<div>', { class: 'grid-two', 'data-season-notice-fields': '1' })
                        .append($('<label>').append($('<span>', { text: 'Número do edital' })).append($('<input>', { name: 'numero_edital', maxlength: 100 })))
                        .append($('<label>').append($('<span>', { text: 'Link do edital' })).append($('<input>', { type: 'url', name: 'link_edital', maxlength: 2048, placeholder: 'https://...' })));
                    $name.after($origin, $toggle, $fields);
                }
                if ($form.find('[name="data_liberacao_segunda_inscricao"]').length === 0) {
                    const $initialLimit = $form.find('[name="limite_inscricoes_periodo"]').closest('label');
                    $initialLimit.find('span').first().text('Limite inicial por CPF');
                    const $releaseFields = $('<div>', { class: 'grid-two' })
                        .append($('<label>')
                            .append($('<span>', { text: 'Liberar segunda inscrição em' }))
                            .append($('<input>', { type: 'datetime-local', name: 'data_liberacao_segunda_inscricao' })))
                        .append($('<label>')
                            .append($('<span>', { text: 'Liberar terceira ou mais inscrições em' }))
                            .append($('<input>', { type: 'datetime-local', name: 'data_liberacao_inscricoes_adicionais' })));
                    const $additionalLimit = $('<label>')
                        .append($('<span>', { text: 'Limite de inscrições após a última liberação' }))
                        .append($('<input>', { type: 'number', name: 'limite_inscricoes_adicionais', min: 3, value: 3 }));
                    $initialLimit.after($releaseFields, $additionalLimit);
                }
            }

            const seasonFieldHelp = {
                nome: 'Identifica a temporada nas telas administrativas e públicas, por exemplo: Temporada de Verão 2027.',
                origem_temporada_id: 'Indica a instituição responsável pela gestão da temporada.',
                possui_edital: 'Marque quando a temporada for regulamentada por um edital próprio.',
                numero_edital: 'Número oficial que identifica o edital da temporada.',
                link_edital: 'Endereço eletrônico onde o usuário poderá consultar o edital completo.',
                tipo_periodicidade: 'Informa a duração planejada da temporada: anual, semestral, quadrimestral, bimestral ou mensal.',
                data_inicio: 'Indica o primeiro dia da publicação das turmas e das modalidades. Serve como referência inicial para os cronogramas das modalidades da temporada.',
                data_fim: 'Indica o último dia da publicação das turmas e das modalidades. Serve como referência inicial para os cronogramas das modalidades da temporada.',
                inscricoes_inicio: 'Data e horário em que começa o período inicial de inscrições da temporada.',
                inscricoes_fim: 'Data e horário em que termina o período inicial de inscrições da temporada.',
                matriculas_inicio: 'Data e horário a partir dos quais as matrículas poderão ser realizadas ou confirmadas.',
                matriculas_fim: 'Data e horário limite para realizar ou confirmar as matrículas.',
                inscricoes_abertas_inicio: 'Início do período posterior de inscrições abertas, quando ainda houver disponibilidade.',
                inscricoes_abertas_fim: 'Encerramento do período posterior de inscrições abertas.',
                aulas_inicio: 'Primeiro dia previsto para as aulas da temporada.',
                aulas_fim: 'Último dia previsto para as aulas da temporada.',
                status: 'Define a situação administrativa da temporada. Somente temporadas ativas podem disponibilizar inscrições.',
                limite_inscricoes_periodo: 'Quantidade inicial de inscrições permitida para cada CPF, independentemente da modalidade.',
                data_liberacao_segunda_inscricao: 'Data e horário em que cada CPF passa a poder realizar uma segunda inscrição.',
                data_liberacao_inscricoes_adicionais: 'Data e horário em que cada CPF passa a poder realizar três ou mais inscrições.',
                limite_inscricoes_adicionais: 'Quantidade máxima de inscrições por CPF depois da última data de liberação.',
                permitir_inscricao_logada: 'Permite que usuários autenticados inscrevam pessoas vinculadas à conta.',
                permitir_inscricao_por_cpf: 'Permite inscrições pelo fluxo específico que utiliza somente o CPF.'
            };

            function ensureSeasonFieldHelp($form) {
                const fieldLabels = { data_inicio: 'Início da publicação', data_fim: 'Fim da publicação' };
                Object.keys(seasonFieldHelp).forEach(function (name) {
                    const $field = $form.find('[name="' + name + '"]').first();
                    const $label = $field.closest('label');
                    if (!$field.length || !$label.length || $label.find('[data-season-field-help="' + name + '"]').length) return;
                    const $help = $('<button>', { type: 'button', class: 'season-field-help', text: '?', title: 'Explicação deste campo', 'aria-label': 'Explicação do campo' })
                        .attr('data-season-field-help', name);
                    const $caption = $label.children('span').first();
                    if ($caption.length && fieldLabels[name]) $caption.text(fieldLabels[name]);
                    if ($caption.length) $caption.append($help); else $label.prepend($help);
                });
            }

            function updateSeasonNoticeFields($form) {
                const enabled = $form.find('[name="possui_edital"]').is(':checked');
                $form.find('[data-season-notice-fields="1"]').toggleClass('hidden', !enabled);
                $form.find('[name="numero_edital"], [name="link_edital"]').prop('required', enabled);
            }

            function openModal(type, record) {
                const $modal = modalFor(type);
                const $form = $modal.find('[data-course-form="' + type + '"]');
                if ($form.find('[name="operacao"]').length === 0) {
                    $form.append($('<input>', { type: 'hidden', name: 'operacao' }));
                }
                if (type === 'class') { ensureClassAgeCriterionField($form); ensureClassScheduleField($form); }
                if (type === 'season') { ensureSeasonNoticeFields($form); ensureSeasonFieldHelp($form); }
                fillForm($form, record || {});
                if (type === 'class') filterClassSchedules($form, record && record.cronograma_modalidade_id);
                $form.find('[name="operacao"]').val(record ? 'editar' : 'criar');
                if (!record && type === 'season') {
                    $form.find('[name="permitir_inscricao_logada"]').prop('checked', true);
                    $form.find('[name="limite_inscricoes_periodo"]').val('1');
                    $form.find('[name="limite_inscricoes_adicionais"]').val('3');
                }
                if (type === 'season') updateSeasonNoticeFields($form);
                $('#course-' + type + '-modal-title').text((record ? 'Editar ' : 'Criar ') + (type === 'season' ? 'temporada' : 'turma'));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function replacePanel(response) {
                if (!response || !response.html) return false;
                const $updatedPanel = $(String(response.html)).first();
                const sectionName = String($updatedPanel.attr('data-admin-section') || '');
                if (!sectionName) return false;
                $('[data-admin-section="' + sectionName + '"]').replaceWith($updatedPanel);
                return true;
            }

            $(document).on('click', '[data-course-create]', function () {
                openModal(String($(this).attr('data-course-create') || ''), null);
            });

            $(document).on('click', '[data-course-edit]', function () {
                let record = {};
                try { record = JSON.parse(String($(this).attr('data-course-record') || '{}')); } catch (error) { record = {}; }
                openModal(String($(this).attr('data-course-edit') || ''), record);
            });
            $(document).on('change', '[data-course-form="class"] [name="temporada_id"], [data-course-form="class"] [name="modalidade_id"]', function () {
                filterClassSchedules($(this).closest('form'), '');
            });

            $(document).on('click', '[data-course-modal-close="1"]', closeModals);
            $(document).on('click', '[data-course-professor-close="1"]', closeModals);
            $(document).on('click', '[data-course-assign-professor]', function () {
                const $modal = $('#course-professor-modal');
                $modal.find('[name="turma_id"]').val(String($(this).attr('data-course-assign-professor') || ''));
                $modal.find('[name="professor_conta_id"]').val(String($(this).attr('data-course-current-professor') || ''));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('submit', '[data-course-professor-form="1"]', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', data: $form.serialize() })
                    .done(function (response) {
                        if (!response || !response.success) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atribuir o professor.')); return; }
                        closeModals();
                        replacePanel(response);
                        App.core.abrirPopup('sucesso', String(response.message || 'Professor atribuído com sucesso.'));
                    })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });
            $(document).on('change', '[data-season-notice-toggle="1"]', function () { updateSeasonNoticeFields($(this).closest('form')); });
            $(document).on('click', '[data-season-field-help]', function (event) {
                event.preventDefault(); event.stopPropagation();
                const name = String($(this).attr('data-season-field-help') || '');
                App.core.abrirPopup('sucesso', seasonFieldHelp[name] || 'Informação não disponível para este campo.');
                $('#popup-titulo').text('Ajuda sobre o campo');
            });
            $(document).on('click', '#course-season-modal, #course-class-modal', function (event) { if (event.target === this) closeModals(); });

            $(document).on('submit', '[data-course-form="season"], [data-course-form="class"]', function (event) {
                event.preventDefault();
                const $form = $(this);
                if (String($form.find('[name="operacao"]').val() || '') === 'editar' && Number($form.find('[name="id"]').val() || 0) <= 0) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o registro que será editado. Feche o modal e tente novamente.');
                    return;
                }
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'), method: 'POST', dataType: 'json', data: new FormData($form[0]),
                    processData: false, contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o registro.')); return; }
                    replacePanel(response);
                    App.core.abrirPopup('sucesso', String(response.message || 'Registro salvo com sucesso.'));
                }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });

            $(document).on('submit', 'form[data-course-deactivate="1"]', function (event) {
                event.preventDefault();
                if (!window.confirm('Deseja realmente inativar este registro?')) return;
                const $form = $(this);
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'), method: 'POST', dataType: 'json', data: $form.serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível inativar o registro.')); return; }
                    replacePanel(response);
                    App.core.abrirPopup('sucesso', String(response.message || 'Registro inativado com sucesso.'));
                }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });
        },

        iniciarGerenciamentoOrigensTemporada: function () {
            const closeModal = function () {
                $('#admin-season-origin-modal').addClass('hidden').attr('aria-hidden', 'true');
            };
            const openModal = function (origin) {
                const editing = origin && Number(origin.id || 0) > 0;
                const $form = $('#admin-season-origin-form');
                if ($form.length === 0) return;
                $form[0].reset();
                $form.find('[name="origem_temporada_id"]').val(editing ? String(origin.id) : '');
                $form.attr('action', editing ? String($form.attr('data-update-action') || '') : String($form.attr('data-create-action') || ''));
                $form.find('[name="nome"]').val(editing ? String(origin.name || '') : '');
                $form.find('[name="ativo"]').val(editing ? String(origin.active || '0') : '1');
                $('#admin-season-origin-form-error').addClass('hidden').text('');
                $('#admin-season-origin-modal-title').text(editing ? 'Editar origem da temporada' : 'Criar origem da temporada');
                $('#admin-season-origin-modal').removeClass('hidden').attr('aria-hidden', 'false');
                $form.find('[name="nome"]').trigger('focus');
            };

            $(document).on('click', '#admin-season-origin-create', function () { openModal(null); });
            $(document).on('click', '.admin-season-origin-edit', function () {
                openModal({
                    id: $(this).attr('data-origin-id'),
                    name: $(this).attr('data-origin-name'),
                    active: $(this).attr('data-origin-active')
                });
            });
            $(document).on('click', '[data-admin-season-origin-close="1"], #admin-season-origin-modal', function (event) {
                if ($(event.target).is('#admin-season-origin-modal') || $(event.target).is('[data-admin-season-origin-close="1"]')) closeModal();
            });
            $(document).on('submit', '#admin-season-origin-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $submit = $form.find('[type="submit"]');
                const editing = Number($form.find('[name="origem_temporada_id"]').val() || 0) > 0;
                if (editing && String($form.attr('action') || '').indexOf('/atualizar') === -1) {
                    $('#admin-season-origin-form-error').removeClass('hidden').text('Não foi possível preparar esta origem para edição. Feche o modal e tente novamente.');
                    return;
                }
                $submit.prop('disabled', true);
                $('#admin-season-origin-form-error').addClass('hidden').text('');
                $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), dataType: 'json' })
                    .done(function (response) {
                        if (!response || response.success === false) {
                            $('#admin-season-origin-form-error').removeClass('hidden').text(String((response && response.message) || 'Não foi possível salvar a origem da temporada.'));
                            return;
                        }
                        $('#admin-season-origin-list-body').html(String(response.html || ''));
                        closeModal();
                        App.core.abrirPopup('sucesso', String(response.message || 'Origem da temporada salva com sucesso.'));
                    })
                    .fail(function (xhr) {
                        $('#admin-season-origin-form-error').removeClass('hidden').text(App.core.extrairMensagemErroAjax(xhr).mensagem);
                    })
                    .always(function () { $submit.prop('disabled', false); });
            });
            $(document).on('click', '.admin-season-origin-delete', function () {
                const $button = $(this);
                const originId = Number($button.attr('data-origin-id') || 0);
                const originName = String($button.attr('data-origin-name') || '').trim();
                if (originId <= 0) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar a origem da temporada que será excluída.');
                    return;
                }
                if (!window.confirm('Deseja realmente excluir a origem "' + originName + '"?')) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/origens-temporada/excluir'),
                    method: 'POST',
                    dataType: 'json',
                    data: { origem_temporada_id: originId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir a origem da temporada.'));
                        return;
                    }
                    $('#admin-season-origin-list-body').html(String(response.html || ''));
                    App.core.abrirPopup('sucesso', String(response.message || 'Origem da temporada excluída com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });
        },

        init: function () {
            App.admin.iniciarSecoesAdmin();
            App.admin.iniciarEditorPessoaAdmin();
            App.admin.iniciarConsultaUsuariosAdmin();
            App.admin.iniciarGerenciamentoPapeisAdmin();
            App.admin.iniciarFiltroPessoasAdmin();
            App.admin.iniciarEditorHorariosSemanais();
            App.admin.iniciarEditorEventosEspeciais();
            App.admin.iniciarValidacaoCondicoesAdmin();
            App.admin.iniciarValidacaoAtestadosSaudeAdmin();
            App.admin.iniciarEditorPostagensBlog();
            App.admin.iniciarEditorComunicacaoOficialAdmin();
            App.admin.iniciarBuscaEnderecoCep();
            App.admin.iniciarFiltroLocaisTreino();
            App.admin.iniciarFiltroEspacosTreino();
            App.admin.iniciarGerenciamentoModalidades();
            App.admin.iniciarEditorEspacosTreino();
            App.admin.iniciarModalSuspensoesLocal();
            App.admin.iniciarEditorConteudoHome();
            App.admin.iniciarMigracaoCadastrosExternos();
            App.admin.iniciarGerenciamentoTemporadasTurmas();
            App.admin.iniciarGerenciamentoOrigensTemporada();
        }
    });

    window.App = App;
}(window, window.jQuery));
