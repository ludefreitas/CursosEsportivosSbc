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
                if ($host.is('[data-professor-mode="1"]') && App.professor && typeof App.professor.init === 'function') {
                    App.professor.init();
                }
                if (typeof App.admin.initializeAdminClassBrowsers === 'function') {
                    App.admin.initializeAdminClassBrowsers($host);
                }
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
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    height: Math.max(220, window.innerHeight - 220),
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

                if (!$('#admin-agenda-calendar-modal').hasClass('hidden')) {
                    App.state.adminAgendaCalendar.render();
                }

                $(window).off('resize.adminAgendaCalendarFit').on('resize.adminAgendaCalendarFit', function () {
                    fitAdminAgendaCalendar();
                });
            }

            function currentOnlineUsersFilters() {
                const $form = $('[data-online-users-filter="1"]');
                return {
                    online_limit: String($form.find('[name="online_limit"]').val() || '25'),
                    online_type: String($form.find('[name="online_type"]').val() || 'todos'),
                    online_device: String($form.find('[name="online_device"]').val() || 'todos'),
                    online_sort: String($form.find('[name="online_sort"]').val() || 'atividade')
                };
            }

            function adminAgendaCalendarAvailableHeight() {
                const modal = document.getElementById('admin-agenda-calendar-modal');
                const card = modal ? modal.querySelector('.admin-agenda-calendar-modal-card') : null;
                const head = card ? card.querySelector(':scope > .popup-head') : null;
                const body = card ? card.querySelector(':scope > .admin-popup-body') : null;
                const actions = card ? card.querySelector(':scope > .popup-actions') : null;
                const viewportGap = window.innerWidth <= 700 ? 12 : 40;
                let reserved = 180;

                if (modal && card && !modal.classList.contains('hidden')) {
                    const bodyStyle = body ? window.getComputedStyle(body) : null;
                    const bodyPadding = bodyStyle
                        ? (parseFloat(bodyStyle.paddingTop) || 0) + (parseFloat(bodyStyle.paddingBottom) || 0)
                        : 0;
                    reserved = (head ? head.offsetHeight : 0)
                        + (actions ? actions.offsetHeight : 0)
                        + bodyPadding;
                }

                return Math.max(220, window.innerHeight - viewportGap - reserved);
            }

            function fitAdminAgendaCalendar() {
                if (!App.state.adminAgendaCalendar || $('#admin-agenda-calendar-modal').hasClass('hidden')) {
                    return;
                }
                App.state.adminAgendaCalendar.setOption('height', adminAgendaCalendarAvailableHeight());
                App.state.adminAgendaCalendar.updateSize();
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

            const adultAbsenceReasons = ['Acompanhamento de familiar', 'Afastamento temporário', 'Atividade ou compromisso oficial', 'Compromisso de trabalho', 'Compromisso escolar ou acadêmico', 'Condições climáticas', 'Consulta médica ou odontológica', 'Exame médico', 'Falecimento ou emergência familiar', 'Outro motivo', 'Problema de saúde', 'Problema de transporte', 'Tratamento ou fisioterapia', 'Viagem'];
            const minorAbsenceReasons = ['Afastamento temporário', 'Compromisso escolar', 'Condições climáticas', 'Consulta médica ou odontológica', 'Emergência familiar', 'Exame, tratamento ou terapia', 'Falta de acompanhante responsável', 'Falecimento na família', 'Guarda ou convivência familiar', 'Orientação dos pais ou responsáveis', 'Outro motivo', 'Passeio ou evento escolar', 'Problema de saúde do menor', 'Problema de transporte', 'Prova ou atividade extracurricular', 'Responsável impossibilitado de levar ou buscar', 'Viagem familiar'];

            function isMinorOnDate(birthDate, referenceDate) {
                const birth = new Date(String(birthDate || '') + 'T12:00:00');
                const reference = referenceDate ? new Date(String(referenceDate).slice(0, 10) + 'T12:00:00') : new Date();
                if (Number.isNaN(birth.getTime())) return false;
                let age = reference.getFullYear() - birth.getFullYear();
                if (reference.getMonth() < birth.getMonth() || (reference.getMonth() === birth.getMonth() && reference.getDate() < birth.getDate())) age--;
                return age < 18;
            }

            function prepareJustificationFields($form, birthDate, referenceDate, currentReason) {
                const reasons = isMinorOnDate(birthDate, referenceDate) ? minorAbsenceReasons : adultAbsenceReasons;
                const reason = String(currentReason || '').trim();
                const isKnown = reasons.indexOf(reason) !== -1;
                const $select = $form.find('[data-justification-reason-select]');
                $select.html('<option value="">Selecione o motivo</option>' + reasons.map(function (item) { return $('<option>').val(item).text(item)[0].outerHTML; }).join(''));
                $select.val(reason === '' ? '' : (isKnown ? reason : 'Outro motivo'));
                $form.find('[data-justification-other-wrap]').toggleClass('hidden', $select.val() !== 'Outro motivo');
                $form.find('[data-justification-other]').val(isKnown ? '' : reason).prop('required', $select.val() === 'Outro motivo');
            }

            function selectedJustificationReason($form) {
                const selected = String($form.find('[data-justification-reason-select]').val() || '').trim();
                return selected === 'Outro motivo' ? String($form.find('[data-justification-other]').val() || '').trim() : selected;
            }

            $(document).on('change', '[data-justification-reason-select]', function () {
                const $form = $(this).closest('form');
                const isOther = String($(this).val() || '') === 'Outro motivo';
                $form.find('[data-justification-other-wrap]').toggleClass('hidden', !isOther);
                $form.find('[data-justification-other]').prop('required', isOther);
                if (isOther) $form.find('[data-justification-other]').trigger('focus');
            });

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

            function openJustificationModal(bookingId, reason, personName, bookingDate, birthDate, referenceDate) {
                const $modal = getJustificationModal();
                const $form = getJustificationForm();

                if ($modal.length === 0 || $form.length === 0) {
                    App.core.abrirPopup('erro', 'O modal de justificativa não está disponível nesta tela.');
                    return;
                }

                $form.find('input[name="agendamento_id"]').val(String(bookingId || ''));
                prepareJustificationFields($form, birthDate, referenceDate, reason);
                $('#admin-booking-justification-person').text(String(personName || '-'));
                $('#admin-booking-justification-date').text(String(bookingDate || '-'));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                $form.find('[data-justification-reason-select]').trigger('focus');
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
                if (payload.evaluate) {
                    formData.append('avaliar_modalidade', '1');
                    formData.append('nivel_slug', String(payload.levelSlug || ''));
                    formData.append('observacoes_avaliacao', String(payload.evaluationNotes || ''));
                    if (payload.confirmDemotion) formData.append('confirmar_rebaixamento', '1');
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
                const requestOptions = Object.assign({ suppressGlobalLoading: normalizedTarget === 'minhas-turmas' }, options || {});
                const previousContent = $host.html();

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
                        if (Number(xhr.status || 0) === 401) {
                            $host.html(previousContent);
                            App.auth.solicitarAutenticacaoNaPaginaAtual(
                                String($host.data('adminBasePath') || '/admin'),
                                function () { activateSection(normalizedTarget, extraParams, options); }
                            );
                            return;
                        }
                        if (Number(xhr.status || 0) === 403) {
                            window.location.href = App.core.buildUrl('/');
                            return;
                        }
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

            $(document).on('submit', '[data-online-users-filter="1"]', function (event) {
                event.preventDefault();
                activateSection('usuarios-online', currentOnlineUsersFilters(), { suppressGlobalLoading: true });
            });

            $(document).on('change', '[data-online-users-filter="1"] select', function () {
                activateSection('usuarios-online', currentOnlineUsersFilters(), { suppressGlobalLoading: true });
            });

            window.setInterval(function () {
                if ($('[data-admin-section="usuarios-online"]').length) {
                    activateSection('usuarios-online', currentOnlineUsersFilters(), { suppressGlobalLoading: true });
                }
            }, 20000);

            function currentCourseEnrollmentFilters($panel) {
                return {
                    ordenar_por: String($panel.find('[data-course-enrollment-sort="criterion"]').val() || 'ordem_inscricao'),
                    direcao: String($panel.find('[data-course-enrollment-sort="direction"]').val() || 'asc'),
                    status: String($panel.find('[data-course-enrollment-filter="status"]').val() || 'todos'),
                    condicao: String($panel.find('[data-course-enrollment-filter="condition"]').val() || 'todas'),
                    turma_id: String($panel.find('[data-course-enrollment-filter="class"]').val() || '0'),
                    turma_nome: String($panel.find('[data-course-enrollment-filter="class-name"]').val() || ''),
                    agrupar_por: String($panel.find('[data-course-enrollment-filter="group-by"]').val() || ''),
                    temporada_id: String($panel.find('[data-course-enrollment-filter="season"]').val() || '0'),
                    grupo_id: String($panel.find('[data-course-enrollment-filter="group"]').val() || '0'),
                    grupo_secundario_id: String($panel.find('[data-course-enrollment-filter="secondary-group"]').val() || '0'),
                    pagina: String($panel.find('[data-course-enrollment-filter="page"]').val() || '1')
                };
            }

            $(document).on('click', '[data-course-enrollment-group-by]', function () {
                activateSection('inscricoes', {
                    agrupar_por: String($(this).attr('data-course-enrollment-group-by') || ''),
                    temporada_id: '0', grupo_id: '0', grupo_secundario_id: '0', pagina: '1'
                }, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-season]', function () {
                const $panel = $(this).closest('[data-admin-section="inscricoes"]');
                activateSection('inscricoes', {
                    agrupar_por: String($panel.find('[data-course-enrollment-filter="group-by"]').val() || $panel.find('[data-course-enrollment-group-by].is-active').attr('data-course-enrollment-group-by') || ''),
                    temporada_id: String($(this).attr('data-course-enrollment-season') || '0'),
                    grupo_id: '0', grupo_secundario_id: '0', pagina: '1'
                }, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-group]', function () {
                const $panel = $(this).closest('[data-admin-section="inscricoes"]');
                activateSection('inscricoes', {
                    agrupar_por: String($panel.find('[data-course-enrollment-group-by].is-active').attr('data-course-enrollment-group-by') || ''),
                    temporada_id: String($panel.find('[data-course-enrollment-season].is-active').attr('data-course-enrollment-season') || '0'),
                    grupo_id: String($(this).attr('data-course-enrollment-group') || '0'),
                    grupo_secundario_id: '0',
                    pagina: '1'
                }, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-secondary-group]', function () {
                const $panel = $(this).closest('[data-admin-section="inscricoes"]');
                activateSection('inscricoes', {
                    agrupar_por: String($panel.find('[data-course-enrollment-group-by].is-active').attr('data-course-enrollment-group-by') || ''),
                    temporada_id: String($panel.find('[data-course-enrollment-season].is-active').attr('data-course-enrollment-season') || '0'),
                    grupo_id: String($panel.find('[data-course-enrollment-group].is-active').attr('data-course-enrollment-group') || '0'),
                    grupo_secundario_id: String($(this).attr('data-course-enrollment-secondary-group') || '0'),
                    pagina: '1'
                }, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-class-enrollments]', function () {
                App.state.courseEnrollmentReturn = {
                    html: $host.html(),
                    section: String($(this).closest('[data-admin-section]').attr('data-admin-section') || ''),
                    scrollTop: window.scrollY || document.documentElement.scrollTop || 0
                };
                activateSection('inscricoes', {
                    turma_id: String($(this).attr('data-course-class-enrollments') || '0'),
                    turma_nome: String($(this).attr('data-course-class-enrollments-name') || ''),
                    ordenar_por: 'ordem_inscricao',
                    direcao: 'asc',
                    status: 'todos',
                    condicao: 'todas'
                }, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-back]', function () {
                const returnState = App.state.courseEnrollmentReturn;
                if (!returnState || !returnState.html) return;
                $host.html(returnState.html);
                $host.find('[data-admin-class-browser]').each(function () {
                    layoutClassFilterLine($(this).find('[data-class-filter-line="season"]'));
                    layoutClassFilterLine($(this).find('[data-class-group-line]:not(.hidden)'));
                });
                syncActiveButton(returnState.section);
                updateHash(returnState.section);
                window.scrollTo(0, Number(returnState.scrollTop || 0));
                App.state.courseEnrollmentReturn = null;
            });

            $(document).on('change', '[data-course-enrollment-sort]', function () {
                const $panel = $(this).closest('[data-admin-section="inscricoes"]');
                const filters = currentCourseEnrollmentFilters($panel);
                filters.pagina = '1';
                activateSection('inscricoes', filters, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-filter-all], [data-course-enrollment-filter-status], [data-course-enrollment-filter-condition]', function () {
                const $button = $(this);
                const $panel = $button.closest('[data-admin-section="inscricoes"]');
                const filters = currentCourseEnrollmentFilters($panel);
                if ($button.is('[data-course-enrollment-filter-all]')) {
                    filters.status = 'todos';
                    filters.condicao = 'todas';
                } else if ($button.is('[data-course-enrollment-filter-status]')) {
                    const selected = String($button.attr('data-course-enrollment-filter-status') || 'todos');
                    filters.status = filters.status === selected ? 'todos' : selected;
                } else {
                    const selected = String($button.attr('data-course-enrollment-filter-condition') || 'todas');
                    filters.condicao = filters.condicao === selected ? 'todas' : selected;
                }
                filters.pagina = '1';
                activateSection('inscricoes', filters, { suppressGlobalLoading: true });
            });

            $(document).on('click', '[data-course-enrollment-show-more]', function () {
                const $button = $(this);
                const $panel = $button.closest('[data-admin-section="inscricoes"]');
                const filters = currentCourseEnrollmentFilters($panel);
                filters.pagina = String($button.attr('data-course-enrollment-show-more') || '2');
                $button.prop('disabled', true).text('Carregando...');

                $.ajax({
                    url: sectionsUrl,
                    method: 'GET',
                    dataType: 'json',
                    data: Object.assign({ nome: 'inscricoes' }, filters),
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar mais inscrições.'));
                        return;
                    }
                    const $response = $('<div>').html(String(response.html));
                    $panel.find('.course-enrollment-list').append($response.find('.course-enrollment-list').children());
                    $panel.find('[data-course-enrollment-filter="page"]').val(filters.pagina);
                    const $next = $response.find('[data-course-enrollment-show-more]').first();
                    if ($next.length) {
                        $button.attr('data-course-enrollment-show-more', String($next.attr('data-course-enrollment-show-more') || '')).prop('disabled', false).text('Mostrar mais...');
                    } else {
                        $button.remove();
                    }
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    if ($button.closest('html').length && !$button.prop('disabled')) return;
                    $button.prop('disabled', false).text('Mostrar mais...');
                });
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

            function closeAdminAgendaFlow() {
                $('#admin-agenda-locations-modal, #admin-agenda-modalities-modal, #admin-agenda-calendar-modal')
                    .addClass('hidden').attr('aria-hidden', 'true');
            }

            function adminAgendaCombinations() {
                try {
                    return JSON.parse($('#admin-agenda-schedule-filter-combinations').text() || '[]');
                } catch (error) {
                    return [];
                }
            }

            $(document).on('click', '#admin-agenda-calendar-open', function () {
                $('#admin-agenda-calendar-local-filter, #admin-agenda-calendar-modality-filter').val('0');
                $('[data-admin-agenda-modality]').prop('hidden', false);
                closeAdminAgendaFlow();
                $('#admin-agenda-locations-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-admin-agenda-location]', function () {
                const locationId = String($(this).data('adminAgendaLocation') || '0');
                const locationLabel = String($(this).data('adminAgendaLabel') || '').trim();
                const compatible = adminAgendaCombinations()
                    .filter(function (item) { return String(item.location_id) === locationId; })
                    .map(function (item) { return String(item.modality_id); });

                $('#admin-agenda-calendar-local-filter').val(locationId).data('label', locationLabel);
                $('#admin-agenda-calendar-modality-filter').val('0').data('label', '');
                $('[data-admin-agenda-modality]').each(function () {
                    $(this).prop('hidden', compatible.indexOf(String($(this).data('adminAgendaModality'))) === -1);
                });
                $('#admin-agenda-modalities-subtitle').text('Escolha uma modalidade oferecida em ' + locationLabel + '.');
                closeAdminAgendaFlow();
                $('#admin-agenda-modalities-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-admin-agenda-modality]', function () {
                const modalityId = String($(this).data('adminAgendaModality') || '0');
                const modalityLabel = String($(this).data('adminAgendaLabel') || '').trim();
                const locationLabel = String($('#admin-agenda-calendar-local-filter').data('label') || '').trim();

                $('#admin-agenda-calendar-modality-filter').val(modalityId).data('label', modalityLabel);
                $('#admin-agenda-calendar-subtitle').text(modalityLabel + ' em ' + locationLabel + '. Clique em um horário para acessar a lista de chamada.');
                closeAdminAgendaFlow();
                $('#admin-agenda-calendar-modal').removeClass('hidden').attr('aria-hidden', 'false');

                if (!App.state.adminAgendaCalendar) {
                    initAdminAgendaCalendar();
                }
                if (App.state.adminAgendaCalendar) {
                    App.state.adminAgendaCalendar.render();
                    fitAdminAgendaCalendar();
                    refetchAdminAgendaCalendar();
                }
            });

            $(document).on('click', '[data-admin-agenda-back="locations"]', function () {
                closeAdminAgendaFlow();
                $('#admin-agenda-locations-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-admin-agenda-back="modalities"]', function () {
                closeAdminAgendaFlow();
                $('#admin-agenda-modalities-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('click', '[data-admin-agenda-flow-close="1"]', closeAdminAgendaFlow);
            $(document).on('click', '#admin-agenda-locations-modal, #admin-agenda-modalities-modal, #admin-agenda-calendar-modal', function (event) {
                if (event.target === this) closeAdminAgendaFlow();
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
                        String($checkbox.attr('data-booking-date') || ''),
                        String($checkbox.attr('data-booking-birth-date') || ''),
                        String($checkbox.attr('data-booking-reference-date') || '')
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
                const reason = selectedJustificationReason($form);

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

            function formatPersonCpf(value) {
                const original = String(value || '').trim();
                const digits = original.replace(/\D+/g, '');

                if (/^\*{3}\.\d{3}\.\d{3}-\*{2}$/.test(original)) {
                    return original;
                }

                if (status === 'presente' && String($checkbox.attr('data-booking-type') || '') === 'avaliacao') {
                    syncBookingStatusGroup(bookingId, previousStatus);
                    const $modal = $('#admin-booking-evaluation-modal');
                    const $form = $('#admin-booking-evaluation-form');
                    $form[0].reset();
                    $form.find('[name="agendamento_id"]').val(bookingId);
                    $form.attr('data-previous-status', previousStatus);
                    $form.find('[data-evaluation-person="1"]').text(String($checkbox.attr('data-booking-person') || '-'));
                    $form.find('[data-evaluation-current-level="1"]').text(String($checkbox.attr('data-current-level') || 'Sem certificado de nível'));
                    $form.find('[data-evaluation-level-field="1"], [data-evaluation-demotion-field="1"]').addClass('hidden');
                    $modal.removeClass('hidden').attr('aria-hidden', 'false');
                    return;
                }
                if (digits.length === 11) {
                    return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }

                return original || '-';
            }

            function formatResponsible(name, cpf) {
                const parts = [
                    String(name || '').trim(),
                    cpf ? formatPersonCpf(cpf) : ''
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
                $('#admin-person-details-cpf').text(formatPersonCpf(person.cpf));
                $('#admin-person-details-sex').text(formatSex(person.sexo));
                $('#admin-person-details-birth-date').text(App.core.formatBirthDateWithAge(person.data_nascimento));
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
                setValue('#admin-person-cpf', person.cpf ? formatPersonCpf(person.cpf) : '');
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
                setValue('#admin-person-responsavel1-cpf', person.responsavel1_cpf ? formatPersonCpf(person.responsavel1_cpf) : '');
                setValue('#admin-person-responsavel2-nome', person.responsavel2_nome || '');
                setValue('#admin-person-responsavel2-cpf', person.responsavel2_cpf ? formatPersonCpf(person.responsavel2_cpf) : '');
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
                        $row.find('td').eq(1).text(person.cpf ? formatPersonCpf(person.cpf) : '');
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
                const original = String(value || '').trim();
                const digits = original.replace(/\D+/g, '');

                if (/^\*{3}\.\d{3}\.\d{3}-\*{2}$/.test(original)) {
                    return original;
                }

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

            function formatDate(value) {
                const raw = String(value || '').trim();
                const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);

                return match ? match[3] + '/' + match[2] + '/' + match[1] : (raw || '-');
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
                $('#admin-user-details-birth-date').text(App.core.formatBirthDateWithAge(user.data_nascimento));
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
                    const since = formatDate(dependent.data_inicio);
                    const note = String(dependent.observacoes || '').trim() || '-';

                    return '' +
                        '<tr>' +
                            '<td>' + App.core.escapeHtml(String(dependent.nome_completo || '-')) + '</td>' +
                            '<td>' + App.core.escapeHtml(formatCpf(dependent.cpf)) + '</td>' +
                            '<td>' + App.core.escapeHtml(App.core.formatBirthDateWithAge(dependent.data_nascimento)) + '</td>' +
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
            let peopleSearchCompositionActive = false;

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
                const requestSequence = ++peopleFilterSequence;

                if (peopleFilterRequest) {
                    peopleFilterRequest.abort();
                }

                peopleFilterRequest = $.ajax({
                    url: String($form.attr('action') || $peopleForm.attr('action') || App.core.buildUrl('/admin/pessoas/lista')),
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

                        if (settings.preserveSearchFocus) {
                            const $response = $('<div>').html(String(response.html));
                            const $currentPeopleBody = $('#admin-people-panel .data-table tbody');
                            const $currentUsersBody = $('#admin-users-panel .data-table tbody');
                            const $newPeopleBody = $response.find('#admin-people-panel .data-table tbody');
                            const $newUsersBody = $response.find('#admin-users-panel .data-table tbody');

                            if ($currentPeopleBody.length && $newPeopleBody.length) {
                                $currentPeopleBody.replaceWith($newPeopleBody);
                            }
                            if ($currentUsersBody.length && $newUsersBody.length) {
                                $currentUsersBody.replaceWith($newUsersBody);
                            }
                        } else {
                            $('#admin-people-panel-shell').replaceWith(String(response.html));
                        }

                        // Durante a digitação os formulários não são substituídos.
                        // Assim, o teclado, o foco e o cursor permanecem estáveis no celular.
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

                if ($form.length === 0 || peopleSearchCompositionActive) {
                    return;
                }

                if (peopleFilterTimer) {
                    window.clearTimeout(peopleFilterTimer);
                }

                peopleFilterTimer = window.setTimeout(function () {
                    refreshPeoplePanel($form, {
                        preserveSearchFocus: true
                    });
                }, 600);
            });

            $(document).on('compositionstart', '.admin-people-search-input', function () {
                peopleSearchCompositionActive = true;
                if (peopleFilterTimer) window.clearTimeout(peopleFilterTimer);
            });

            $(document).on('compositionend', '.admin-people-search-input', function () {
                peopleSearchCompositionActive = false;
                $(this).trigger('input');
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

            const weeklyScheduleFieldHelp = {
                espaco_treino_id: 'Define o espaço físico em que o horário acontecerá. O local de treino é identificado automaticamente a partir do espaço selecionado.',
                modalidade_id: 'Define a modalidade esportiva oferecida neste horário e utilizada nos filtros da agenda.',
                tipo_horario: 'Indica a finalidade do horário semanal: avaliação, treino ou aula.',
                niveis_aceitos: 'Sem nenhum nível marcado, o horário não possui limitação de nível. Ao marcar um ou mais níveis, somente pessoas com certificado de nível ativo e compatível poderão agendar. Em horários de avaliação, quem já possui certificado somente poderá agendar para um nível superior.',
                dispensar_avaliacao_previa: 'Para treino ou aula, define se a pessoa precisa possuir uma avaliação física apta para a mesma modalidade. Em horários de avaliação, essa exigência não se aplica.',
                dia_semana: 'Define o dia da semana em que este horário se repetirá.',
                sexo: 'Restringe o horário por sexo. Selecione Livre para permitir o agendamento de qualquer pessoa que atenda aos demais critérios.',
                hora_inicio: 'Informa o horário em que a atividade começa.',
                hora_fim: 'Informa o horário em que a atividade termina. Também é usado para definir a duração e verificar conflitos.',
                criterio_faixa_etaria: 'Define se a faixa etária será conferida pela idade exata na data da atividade ou somente pelo ano de nascimento.',
                idade_minima: 'Define a menor idade permitida para agendar este horário, conforme o critério etário escolhido.',
                idade_maxima: 'Define a maior idade permitida para agendar este horário, conforme o critério etário escolhido.',
                regra_atestado_clinico: 'A regra global é o comportamento padrão do sistema quando este horário não possui uma regra própria. Para o atestado clínico, a regra global exige um atestado válido em todos os horários. Selecione Exigir ou Dispensar para substituir esse padrão somente neste horário.',
                regra_atestado_dermatologico: 'A regra global é o comportamento padrão do sistema quando este horário não possui uma regra própria. Para o atestado dermatológico, a regra global exige um atestado válido nas modalidades aquáticas e o dispensa nas modalidades terrestres. Selecione Exigir ou Dispensar para substituir esse padrão somente neste horário.',
                vagas_geral: 'Define a quantidade de vagas destinadas ao público geral em cada ocorrência deste horário.',
                vagas_pcd: 'Define a quantidade de vagas destinadas a pessoas com deficiência (PCD) em cada ocorrência.',
                vagas_plm: 'Define a quantidade de vagas destinadas a pessoas com limitação de mobilidade (PLM) em cada ocorrência.',
                vagas_pvs: 'Define a quantidade de vagas destinadas a pessoas em vulnerabilidade social (PVS) em cada ocorrência.',
                janela_agendamento_tipo: 'Define quando cada ocorrência ficará disponível para agendamento. Semana atual e próxima: exibe somente horários da semana atual e da próxima, do início da semana atual até o domingo da próxima, e encerra cada agendamento 2 horas antes da atividade. Dias fixos da semana: abre e fecha a agenda nos dias e horários semanais informados para a semana da ocorrência. Antecedência da ocorrência: abre a agenda a quantidade de dias informada antes da atividade e fecha a quantidade de horas informada antes do seu início.',
                janela_horas_antes_fechamento: 'Define quantas horas antes do início da atividade o agendamento será encerrado.',
                janela_abertura_dia_semana: 'Define o dia fixo da semana em que a agenda será aberta quando for usada a janela semanal fixa.',
                janela_abertura_hora: 'Define o horário de abertura da agenda no dia semanal escolhido.',
                janela_fechamento_dia_semana: 'Define o dia fixo da semana em que a agenda será fechada quando for usada a janela semanal fixa.',
                janela_fechamento_hora: 'Define o horário de fechamento da agenda no dia semanal escolhido.',
                janela_dias_antecedencia: 'Define quantos dias antes de cada atividade a ocorrência ficará disponível para agendamento.',
                ativo: 'Define se o horário já será disponibilizado como ativo após o cadastro. Horários inativos não ficam disponíveis para novos agendamentos.'
            };

            function ensureWeeklyScheduleFieldHelp($form) {
                if (!$form || $form.length === 0) {
                    return;
                }

                Object.keys(weeklyScheduleFieldHelp).forEach(function (fieldName) {
                    const $field = $form.find('[name="' + fieldName + '"]').first();
                    const $labelText = $field.closest('label').children('span').first();

                    if ($field.length === 0 || $labelText.length === 0 || $labelText.find('[data-weekly-schedule-field-help="' + fieldName + '"]').length > 0) {
                        return;
                    }

                    $('<button>', {
                        type: 'button',
                        class: 'season-field-help weekly-schedule-field-help',
                        text: '?',
                        title: 'Explicação deste campo',
                        'aria-label': 'Explicação do campo ' + $labelText.text().trim()
                    })
                        .attr('data-weekly-schedule-field-help', fieldName)
                        .appendTo($labelText);
                });
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

            function syncWeeklyScheduleEvaluationRequirement($form) {
                if (!$form || $form.length === 0) return;
                const isEvaluation = String($form.find('select[name="tipo_horario"]').val() || '') === 'avaliacao';
                const $field = $form.find('select[name="dispensar_avaliacao_previa"]');
                const $help = $form.find('[data-evaluation-requirement-help="1"]');

                if (isEvaluation) {
                    $field.val('1').prop('disabled', true);
                    $help.text('Não se aplica: este horário é destinado à própria avaliação.');
                } else {
                    $field.prop('disabled', false);
                    $help.text($field.val() === '1'
                        ? 'A pessoa poderá agendar este treino ou aula sem avaliação física apta na modalidade.'
                        : 'A pessoa deverá possuir avaliação física apta na modalidade para agendar.');
                }
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
                    syncWeeklyScheduleEvaluationRequirement($form);
                }
            }

            function openCreateModal() {
                const $modal = getCreateModal();

                if ($modal.length === 0) {
                    return;
                }

                const $createForm = $('#admin-weekly-schedule-create-form');

                ensureWeeklyScheduleFieldHelp($createForm);
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                syncWeeklyScheduleWindowFields($createForm);
                syncWeeklyScheduleEvaluationRequirement($createForm);
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
                setValue('#admin-weekly-schedule-evaluation-requirement', Number(schedule.dispensar_avaliacao_previa || 0) === 1 ? '1' : '0');
                let acceptedLevels = [];
                try { acceptedLevels = JSON.parse(String(schedule.niveis_aceitos_json || '[]')); } catch (error) { acceptedLevels = []; }
                $('#admin-weekly-schedule-form [name="niveis_aceitos[]"]').each(function () {
                    $(this).prop('checked', acceptedLevels.indexOf(String($(this).val())) !== -1);
                });
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
                syncWeeklyScheduleEvaluationRequirement(getForm());

                $('#admin-weekly-schedule-editor-subtitle').text(
                    'Editando ' + String(schedule.modalidade_nome || '') + ' em ' + String(schedule.local_nome || '') + ' sem sair da agenda administrativa.'
                );
            }

            $(document).on('click', '[data-weekly-schedule-edit="1"]', function () {
                const $button = $(this);
                const scheduleId = Number($button.data('weeklyScheduleId') || 0);
                const detailUrl = String($button.attr('data-weekly-schedule-detail-url') || adminUrl('/admin/horarios-semanais/detalhe'));

                if (!scheduleId) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o horário selecionado.');
                    return;
                }

                $.getJSON(detailUrl, { id: scheduleId })
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

            $(document).on('change', '#admin-booking-evaluation-form [name="evaluation_action"]', function () {
                const generate = String($(this).val() || '') === 'certificado';
                const $form = $('#admin-booking-evaluation-form');
                $form.find('[data-evaluation-level-field="1"], [data-evaluation-demotion-field="1"]').toggleClass('hidden', !generate);
                $form.find('[name="nivel_slug"]').prop('required', generate);
            });

            $(document).on('click', '[data-evaluation-close="1"]', function () {
                $('#admin-booking-evaluation-modal').addClass('hidden').attr('aria-hidden', 'true');
            });

            $(document).on('submit', '#admin-booking-evaluation-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const action = String($form.find('[name="evaluation_action"]').val() || 'presenca');
                const bookingId = String($form.find('[name="agendamento_id"]').val() || '0');
                if (action === 'certificado' && !$form.find('[name="nivel_slug"]').val()) {
                    App.core.abrirPopup('erro', 'Selecione o nível que será certificado.'); return;
                }
                $('#admin-booking-evaluation-modal').addClass('hidden').attr('aria-hidden', 'true');
                submitBookingAttendanceStatus({
                    bookingId: bookingId, status: 'presente', evaluate: action !== 'presenca',
                    levelSlug: action === 'certificado' ? $form.find('[name="nivel_slug"]').val() : '',
                    evaluationNotes: $form.find('[name="observacoes_avaliacao"]').val(),
                    confirmDemotion: $form.find('[name="confirmar_rebaixamento"]').is(':checked')
                });
            });

            $(document).on('click', '[data-weekly-schedule-field-help]', function (event) {
                const fieldName = String($(this).attr('data-weekly-schedule-field-help') || '');
                const message = weeklyScheduleFieldHelp[fieldName];

                event.preventDefault();
                event.stopPropagation();

                if (!message) {
                    return;
                }

                App.core.abrirPopup('sucesso', message);
                $('#popup-titulo').text('Ajuda sobre o campo');
            });

            $(document).on('input change', '#admin-weekly-schedule-create-form input[name="idade_minima"], #admin-weekly-schedule-create-form input[name="idade_maxima"], #admin-weekly-schedule-create-form select[name="criterio_faixa_etaria"], #admin-weekly-schedule-form input[name="idade_minima"], #admin-weekly-schedule-form input[name="idade_maxima"], #admin-weekly-schedule-form select[name="criterio_faixa_etaria"]', function () {
                syncWeeklyScheduleAgePreview($(this).closest('form'));
            });

            $(document).on('change', '#admin-weekly-schedule-create-form select[name="janela_agendamento_tipo"], #admin-weekly-schedule-form select[name="janela_agendamento_tipo"]', function () {
                syncWeeklyScheduleWindowFields($(this).closest('form'));
            });

            $(document).on('change', '#admin-weekly-schedule-create-form select[name="tipo_horario"], #admin-weekly-schedule-create-form select[name="dispensar_avaliacao_previa"], #admin-weekly-schedule-form select[name="tipo_horario"], #admin-weekly-schedule-form select[name="dispensar_avaliacao_previa"]', function () {
                syncWeeklyScheduleEvaluationRequirement($(this).closest('form'));
            });

            $(document).on('click', '[data-weekly-schedule-team="1"]', function () {
                const $button = $(this), $modal = $('#weekly-schedule-team-modal'), mainId = String($button.attr('data-weekly-schedule-main-professor') || '');
                let professorIds = [], internIds = [];
                try { professorIds = JSON.parse(String($button.attr('data-weekly-schedule-professors') || '[]')).map(String); } catch (error) {}
                try { internIds = JSON.parse(String($button.attr('data-weekly-schedule-interns') || '[]')).map(String); } catch (error) {}
                $modal.find('form')[0].reset();
                $modal.find('[data-course-team-search]').val('');
                $modal.find('[data-course-team-options] label').removeClass('hidden');
                $modal.find('[name="horario_semanal_id"]').val(String($button.attr('data-weekly-schedule-id') || ''));
                $modal.find('[name="professor_principal_conta_id"]').filter('[value="' + mainId + '"]').prop('checked', true);
                $modal.find('[name="professor_auxiliar_conta_ids[]"]').each(function () { const id = String($(this).val()); $(this).prop('checked', id !== mainId && professorIds.indexOf(id) !== -1).prop('disabled', id === mainId); });
                $modal.find('[name="estagiario_conta_ids[]"]').each(function () { $(this).prop('checked', internIds.indexOf(String($(this).val())) !== -1); });
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-weekly-schedule-team-close="1"]', function () { $('#weekly-schedule-team-modal').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('submit', '[data-weekly-schedule-team-form="1"]', function (event) {
                event.preventDefault();
                const $form = $(this), $button = $form.find('button[type="submit"]').prop('disabled', true);
                if (!$form.find('[name="professor_principal_conta_id"]:checked').length) { App.core.abrirPopup('erro', 'Eleja o professor principal do horário.'); $button.prop('disabled', false); return; }
                const mainName = $.trim($form.find('[name="professor_principal_conta_id"]:checked').closest('label').find('span').text());
                const assistantNames = $form.find('[name="professor_auxiliar_conta_ids[]"]:checked').map(function () { return $.trim($(this).closest('label').find('span').text()); }).get();
                const internNames = $form.find('[name="estagiario_conta_ids[]"]:checked').map(function () { return $.trim($(this).closest('label').find('span').text()); }).get();
                $.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', data: $form.serialize() })
                    .done(function (response) {
                        if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atualizar a equipe.')); return; }
                        const schedule = response.schedule || {}, id = String(schedule.id || $form.find('[name="horario_semanal_id"]').val() || '');
                        $('[data-weekly-schedule-team="1"][data-weekly-schedule-id="' + id + '"]').attr('data-weekly-schedule-main-professor', String(schedule.professor_conta_id || '')).attr('data-weekly-schedule-professors', JSON.stringify(schedule.professores_ids || [])).attr('data-weekly-schedule-interns', JSON.stringify(schedule.estagiarios_ids || []));
                        const $row = $('[data-weekly-schedule-row="1"][data-weekly-schedule-id="' + id + '"]');
                        $row.find('[data-weekly-main-name]').text(mainName || 'Não atribuído');
                        $row.find('[data-weekly-assistants-names]').text(assistantNames.join(', '));
                        $row.find('[data-weekly-assistants-line]').toggleClass('hidden', assistantNames.length === 0);
                        $row.find('[data-weekly-interns-names]').text(internNames.join(', '));
                        $row.find('[data-weekly-interns-line]').toggleClass('hidden', internNames.length === 0);
                        $('#weekly-schedule-team-modal').addClass('hidden').attr('aria-hidden', 'true');
                        App.core.abrirPopup('sucesso', String(response.message || 'Equipe do horário atualizada com sucesso.'));
                    }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); }).always(function () { $button.prop('disabled', false); });
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
            ensureWeeklyScheduleFieldHelp($('#admin-weekly-schedule-create-form'));
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

                $note.prop('required', status === 'reprovado' || status === 'validado_parcial');
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
                const personId = String(formData.get('person_id') || '').trim();
                const conditionSlug = String(formData.get('condition_slug') || '').trim();
                const selectedStatus = String(formData.get('status') || '').trim();

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

                    const statusLabels = {
                        pendente: 'Validação pendente',
                        reprovado: 'Reprovado',
                        validado: 'Validado',
                        validado_parcial: 'Validado parcial'
                    };
                    $('[data-condition-status-person="' + personId + '"][data-condition-status-slug="' + conditionSlug + '"]')
                        .text(String(statusLabels[selectedStatus] || selectedStatus));

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
                let $modal = $('#admin-health-certificate-validation-modal');

                if ($modal.length === 0) {
                    $modal = $('<div>', {
                        id: 'admin-health-certificate-validation-modal',
                        class: 'popup-overlay hidden',
                        'aria-hidden': 'true'
                    }).append(
                        $('<div>', {
                            class: 'popup-card popup-admin-card admin-condition-validation-card',
                            role: 'dialog',
                            'aria-modal': 'true',
                            'aria-labelledby': 'admin-health-certificate-validation-title'
                        }).append($('<div>', { id: 'admin-health-certificate-validation-modal-content' }))
                    );
                }

                return $modal.appendTo(document.body).css('z-index', '2147483000');
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

                if ($modal.attr('data-return-to-attendance-roster') === '1') {
                    $('#course-class-attendance-roster-modal').removeClass('hidden').attr('aria-hidden', 'false');
                    $modal.removeAttr('data-return-to-attendance-roster');
                }
            }

            function openModal() {
                const $modal = getModal();

                if ($modal.length === 0) {
                    return;
                }

                const $attendanceRoster = $('#course-class-attendance-roster-modal');
                if ($attendanceRoster.length && !$attendanceRoster.hasClass('hidden')) {
                    $attendanceRoster.addClass('hidden').attr('aria-hidden', 'true');
                    $modal.attr('data-return-to-attendance-roster', '1');
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false').scrollTop(0);
                $modal.find('.popup-card').scrollTop(0);
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

            function closeLocationPopupModal() { $('#admin-location-popup-modal').addClass('hidden').attr('aria-hidden', 'true'); }
            function fillLocationPopupForm(record) {
                const $form = $('#admin-location-popup-form');
                $form.get(0).reset();
                Object.keys(record || {}).forEach(function (key) {
                    const $field = $form.find('[name="' + key + '"]');
                    if (!$field.length) return;
                    $field.val($field.attr('type') === 'datetime-local' ? String(record[key] || '').replace(' ', 'T').slice(0, 16) : String(record[key] == null ? '' : record[key]));
                });
                const editing = Number(record && record.id || 0) > 0;
                const area = String(record && record.area || 'cursos');
                $form.find('[name="local_popup_id"]').val(editing ? String(record.id) : '');
                $form.find('[name="area"]').val(area);
                $('#admin-location-popup-area-label').text(area === 'agenda' ? 'Agenda pública' : 'Inscrições dos cursos esportivos');
                $('#admin-location-popup-title').text(editing ? 'Editar pop-up do local' : 'Criar pop-up do local');
                $('#admin-location-popup-subtitle').text(String(record.apelido_local || record.nome_local || record.local_nome || ''));
                $('#admin-location-popup-delete').toggleClass('hidden', !editing).attr('data-popup-id', editing ? String(record.id) : '');
            }
            $(document).on('click', '.admin-location-popup-create', function () {
                const $modal = $('#admin-location-popup-modal');
                if ($modal.length === 0) {
                    App.core.abrirPopup('erro', 'Não foi possível carregar o formulário do pop-up do local. Atualize a seção e tente novamente.');
                    return;
                }
                fillLocationPopupForm({local_treino_id:$(this).attr('data-location-id'),local_nome:$(this).attr('data-location-name'),area:$(this).attr('data-popup-area'),status:'ativo'});
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '.admin-location-popup-manage', function () {
                let record={}; try{record=JSON.parse(String($(this).attr('data-popup')||'{}'));}catch(error){record={};}
                fillLocationPopupForm(record); $('#admin-location-popup-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-location-popup-close="1"], #admin-location-popup-modal', function (event) {
                if ($(event.target).is('#admin-location-popup-modal') || $(event.target).is('[data-location-popup-close="1"]')) closeLocationPopupModal();
            });
            $(document).on('submit', '#admin-location-popup-form', function (event) {
                event.preventDefault(); const $form=$(this), $button=$form.find('[type="submit"]').prop('disabled',true);
                $.post(App.core.buildUrl('/admin/locais/popups'),$form.serialize(),function(response){
                    if(!response||response.success===false){App.core.abrirPopup('erro',String(response&&response.message||'Não foi possível salvar o pop-up.'));return;}
                    closeLocationPopupModal(); App.admin.activateSection('locais-espacos'); App.core.abrirPopup('sucesso',String(response.message));
                },'json').fail(function(xhr){App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);}).always(function(){$button.prop('disabled',false);});
            });
            $(document).on('click', '#admin-location-popup-delete', function () {
                const id=Number($(this).attr('data-popup-id')||0); if(!id||!window.confirm('Deseja excluir este pop-up do local?')) return;
                $.post(App.core.buildUrl('/admin/locais/popups/excluir'),{local_popup_id:id},function(response){
                    if(!response||response.success===false){App.core.abrirPopup('erro',String(response&&response.message||'Não foi possível excluir.'));return;}
                    closeLocationPopupModal(); App.admin.activateSection('locais-espacos'); App.core.abrirPopup('sucesso',String(response.message));
                },'json').fail(function(xhr){App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);});
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
                syncSpaceAccessibilityBarriers($form);
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

            function syncSpaceAccessibilityBarriers($form) {
                const hasPool = inferPoolSpace($form);
                $form.find('[data-pool-only-barrier="1"]').toggleClass('hidden', !hasPool);
                if (!hasPool) {
                    $form.find('[data-pool-only-barrier="1"] input[type="checkbox"]').prop('checked', false);
                }
                $form.find('[data-space-accessibility-toggle]').each(function () {
                    const slug = String($(this).attr('data-space-accessibility-toggle') || '');
                    const selected = $(this).is(':checked');
                    const $barriers = $form.find('[data-space-accessibility-barriers="' + slug + '"]');
                    $barriers.toggleClass('hidden', !selected);
                    $barriers.find('input[type="checkbox"]').prop('required', false);
                    if (!selected) {
                        $barriers.find('input[type="checkbox"]').prop('checked', false);
                    }
                });
            }

            function normalizeSpaceDescription(value) {
                return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
            }

            function editDistance(left, right) {
                const a = String(left || '');
                const b = String(right || '');
                const row = Array.from({ length: b.length + 1 }, function (_, index) { return index; });
                for (let i = 1; i <= a.length; i += 1) {
                    let previous = row[0];
                    row[0] = i;
                    for (let j = 1; j <= b.length; j += 1) {
                        const saved = row[j];
                        row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
                        previous = saved;
                    }
                }
                return row[b.length];
            }

            function inferPoolSpace($form) {
                const description = normalizeSpaceDescription(
                    String($form.find('[name="nome"]').val() || '') + ' ' + String($form.find('[name="tipo_espaco"]').val() || '')
                );
                if (description === '') return false;
                const words = description.split(/\s+/);
                const poolMatch = description.indexOf('piscin') !== -1
                    || /tanque.*natacao/.test(description)
                    || /(complexo|centro|area).*aquatic/.test(description)
                    || description.indexOf('natatorio') !== -1
                    || words.some(function (word) { return word.length >= 5 && editDistance(word, 'piscina') <= 2; });
                return poolMatch;
            }

            function prepareCreate() {
                const $form = $('#admin-training-space-form');

                if ($form[0]) {
                    $form[0].reset();
                }
                $form.attr('action', String($form.data('createAction') || ''));
                $form.find('input[name="espaco_treino_id"]').val('');
                $form.find('input[name="espaco_externo_migracao_id"]').val('');
                syncSpaceAccessibilityBarriers($form);
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
                const accessibilityBarriers = space.acessibilidade_barreiras && typeof space.acessibilidade_barreiras === 'object'
                    ? space.acessibilidade_barreiras
                    : {};
                $form.find('input[name^="acessibilidade_barreiras["]').each(function () {
                    const match = String($(this).attr('name') || '').match(/^acessibilidade_barreiras\[([^\]]+)\]/);
                    const disability = match ? match[1] : '';
                    const selectedBarriers = Array.isArray(accessibilityBarriers[disability]) ? accessibilityBarriers[disability].map(String) : [];
                    $(this).prop('checked', selectedBarriers.indexOf(String($(this).val())) !== -1);
                });
                syncSpaceAccessibilityBarriers($form);
                $form.find('select[name="ativo"]').val(String(Number(space.ativo || 0)));
                $('#admin-training-space-modal-title').text('Editar espaço de treino');
                $('#admin-training-space-submit').text('Salvar alterações');
                getModal().removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('submit', '#admin-training-space-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                let missingBarrierLabel = '';
                $form.find('[data-space-accessibility-toggle]:checked').each(function () {
                    const slug = String($(this).attr('data-space-accessibility-toggle') || '');
                    const hasBarrier = $form.find('[data-space-accessibility-barriers="' + slug + '"] input[type="checkbox"]:checked').length > 0;
                    if (!hasBarrier && missingBarrierLabel === '') {
                        missingBarrierLabel = String($(this).siblings('span').text() || slug);
                    }
                });
                if (missingBarrierLabel !== '') {
                    App.core.abrirPopup('erro', 'Informe pelo menos uma barreira de acessibilidade para a deficiência ' + missingBarrierLabel + '.');
                    return;
                }
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

            $(document).on('change', '#admin-training-space-form [data-space-accessibility-toggle]', function () {
                syncSpaceAccessibilityBarriers($(this).closest('form'));
            });

            $(document).on('input change', '#admin-training-space-form [name="nome"], #admin-training-space-form [name="tipo_espaco"]', function () {
                syncSpaceAccessibilityBarriers($(this).closest('form'));
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
            function modalityPopupModal() { return $('#admin-modality-popup-modal'); }
            function closeModalityPopupModal() { modalityPopupModal().addClass('hidden').attr('aria-hidden', 'true'); }
            function fillModalityPopupForm(record) {
                const $form = $('#admin-modality-popup-form');
                $form.get(0).reset();
                Object.keys(record || {}).forEach(function (key) {
                    const $field = $form.find('[name="' + key + '"]');
                    if (!$field.length) return;
                    $field.val($field.attr('type') === 'datetime-local' ? String(record[key] || '').replace(' ', 'T').slice(0, 16) : String(record[key] == null ? '' : record[key]));
                });
                const editing = Number(record && record.id || 0) > 0;
                const area = String(record && record.area || 'cursos');
                $form.find('[name="area"]').val(area);
                $('#admin-modality-popup-area-label').text(area === 'agenda' ? 'Agenda pública' : 'Inscrições dos cursos esportivos');
                $form.find('[name="modalidade_popup_id"]').val(editing ? String(record.id) : '');
                $('#admin-modality-popup-delete').toggleClass('hidden', !editing).attr('data-popup-id', editing ? String(record.id) : '');
                $('#admin-modality-popup-title').text(editing ? 'Editar pop-up da modalidade' : 'Criar pop-up da modalidade');
                $('#admin-modality-popup-subtitle').text(String(record.modalidade_nome || ''));
            }
            $(document).on('click', '.admin-modality-popup-create', function () {
                fillModalityPopupForm({ modalidade_id: $(this).attr('data-modality-id'), modalidade_nome: $(this).attr('data-modality-name'), area: String($(this).attr('data-popup-area') || 'cursos'), status: 'ativo' });
                modalityPopupModal().removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '.admin-modality-popup-manage', function () {
                let record = {}; try { record = JSON.parse(String($(this).attr('data-popup') || '{}')); } catch (error) { record = {}; }
                fillModalityPopupForm(record); modalityPopupModal().removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-modality-popup-close="1"], #admin-modality-popup-modal', function (event) {
                if ($(event.target).is('#admin-modality-popup-modal') || $(event.target).is('[data-modality-popup-close="1"]')) closeModalityPopupModal();
            });
            $(document).on('submit', '#admin-modality-popup-form', function (event) {
                event.preventDefault(); const $form=$(this); const $button=$form.find('[type="submit"]').prop('disabled',true);
                $.ajax({url:App.core.buildUrl('/admin/modalidades/popups'),method:'POST',dataType:'json',data:$form.serialize()})
                    .done(function(response){if(!response||response.success===false){App.core.abrirPopup('erro',String(response&&response.message||'Não foi possível salvar o pop-up.'));return;} closeModalityPopupModal(); App.admin.activateSection('modalidades'); App.core.abrirPopup('sucesso',String(response.message));})
                    .fail(function(xhr){App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);}).always(function(){$button.prop('disabled',false);});
            });
            $(document).on('click', '#admin-modality-popup-delete', function () {
                const id=Number($(this).attr('data-popup-id')||0); if(!id||!window.confirm('Deseja excluir este pop-up da modalidade?')) return;
                $.post(App.core.buildUrl('/admin/modalidades/popups/excluir'),{modalidade_popup_id:id},function(response){if(!response||response.success===false){App.core.abrirPopup('erro',String(response&&response.message||'Não foi possível excluir.'));return;} closeModalityPopupModal(); App.admin.activateSection('modalidades'); App.core.abrirPopup('sucesso',String(response.message));},'json').fail(function(xhr){App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);});
            });
            function scheduleModal() { return $('#admin-modality-schedule-modal'); }
            function closeScheduleModal() { scheduleModal().addClass('hidden').attr('aria-hidden', 'true'); }
            function scheduleData(attribute) {
                try { return JSON.parse(String($('[data-admin-section="modalidades"]').attr(attribute) || '[]')); } catch (error) { return []; }
            }
            function toLocalDateTime(value) { return String(value || '').replace(' ', 'T').slice(0, 16); }
            function formatBrazilianDate(value) {
                const raw = String(value || '');
                const parts = raw.slice(0, 10).split('-');
                if (parts.length !== 3) return '';
                const time = raw.length >= 16 ? raw.slice(11, 16) : '';
                return parts[2] + '/' + parts[1] + '/' + parts[0] + (time ? ' às ' + time : '');
            }
            function validateCoursePeriodChronology($form) {
                const fieldNames = ['data_inicio', 'data_fim', 'inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'aulas_inicio', 'aulas_fim'];
                const fields = {};
                const values = {};
                fieldNames.forEach(function (name) {
                    fields[name] = $form.find('[name="' + name + '"]').get(0) || null;
                    values[name] = fields[name] && fields[name].value ? new Date(fields[name].value.length === 10 ? fields[name].value + 'T00:00:00' : fields[name].value) : null;
                    if (fields[name]) {
                        fields[name].setCustomValidity('');
                        $(fields[name]).removeAttr('data-period-validation-error data-remote-validation-error');
                    }
                });
                function setPeriodError(field, message) {
                    if (!field) return;
                    $(field).attr('data-period-validation-error', message).attr('data-remote-validation-error', message);
                    field.setCustomValidity(message);
                }
                function before(earlier, later, message) {
                    if (!values[earlier] || !values[later] || values[earlier].getTime() < values[later].getTime()) return true;
                    setPeriodError(fields[later], message);
                    return false;
                }
                let valid = true;
                valid = before('data_inicio', 'data_fim', 'O fim da publicação deve ser posterior ao seu início.') && valid;
                valid = before('data_inicio', 'inscricoes_inicio', 'O início da inscrição inicial deve ser posterior ao início da publicação.') && valid;
                valid = before('inscricoes_inicio', 'inscricoes_fim', 'O fim da inscrição inicial deve ser posterior ao seu início.') && valid;
                valid = before('inscricoes_fim', 'matriculas_inicio', 'As matrículas devem começar somente depois do encerramento da inscrição inicial.') && valid;
                valid = before('matriculas_inicio', 'matriculas_fim', 'O fim das matrículas deve ser posterior ao seu início.') && valid;
                const weeklyCoverage = String($form.find('[name="abrangencia_semanal"]').val() || 'segunda_sexta');
                if (values.matriculas_inicio && values.matriculas_fim) {
                    if (values.matriculas_inicio.getDay() !== 1) {
                        setPeriodError(fields.matriculas_inicio, 'O período de matrícula deve começar em uma segunda-feira.');
                        valid = false;
                    }
                    const minimumDays = weeklyCoverage === 'segunda_domingo' ? 7 : 5;
                    const minimumEnd = new Date(values.matriculas_inicio.getFullYear(), values.matriculas_inicio.getMonth(), values.matriculas_inicio.getDate() + minimumDays - 1);
                    if (values.matriculas_fim.getTime() < minimumEnd.getTime()) {
                        setPeriodError(fields.matriculas_fim, 'O período deve abranger pelo menos ' + minimumDays + ' dias, começando em uma segunda-feira.');
                        valid = false;
                    }
                }
                const enrollmentDuringRegistration = $form.find('[name="permitir_inscricao_periodo_matricula"]').is(':checked');
                if (enrollmentDuringRegistration && values.inscricoes_abertas_inicio && values.matriculas_inicio && values.matriculas_fim) {
                    if (values.inscricoes_abertas_inicio < values.matriculas_inicio || values.inscricoes_abertas_inicio > values.matriculas_fim) {
                        setPeriodError(fields.inscricoes_abertas_inicio, 'Inscrições abertas: início deve estar entre ' + formatBrazilianDate(fields.matriculas_inicio.value) + ' e ' + formatBrazilianDate(fields.matriculas_fim.value) + '.');
                        valid = false;
                    }
                }
                valid = before('inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'O fim das inscrições abertas deve ser posterior ao seu início.') && valid;
                valid = before('data_inicio', 'aulas_inicio', 'O início das aulas deve ser posterior ao início da publicação.') && valid;
                valid = before('matriculas_inicio', 'aulas_inicio', 'O início das aulas deve ser posterior ao início das matrículas.') && valid;
                valid = before('aulas_inicio', 'aulas_fim', 'O fim das aulas deve ser posterior ao seu início.') && valid;
                fieldNames.forEach(function (name) {
                    if (fields[name] && $(fields[name]).attr('data-validation-touched') === '1') App.core.validarCampoInline(fields[name], true);
                });
                return valid;
            }
            function updateModalityRegistrationEnrollmentField() {
                const $form = $('#admin-modality-schedule-form');
                const enabled = $form.find('[name="permitir_inscricao_periodo_matricula"]').is(':checked');
                const $field = $form.find('[name="inscricoes_abertas_inicio"]');
                const registrationStart = String($form.find('[name="matriculas_inicio"]').val() || '');
                const registrationEnd = String($form.find('[name="matriculas_fim"]').val() || '');
                const $range = $form.find('[data-modality-registration-enrollment-range="1"]');
                $field.prop('required', true);
                if (enabled) {
                    $field.removeAttr('min max');
                    const formattedStart = formatBrazilianDate(registrationStart);
                    const formattedEnd = formatBrazilianDate(registrationEnd);
                    $range.toggleClass('hidden', !formattedStart || !formattedEnd)
                        .text(formattedStart && formattedEnd ? 'Inscrições abertas: início deve estar entre ' + formattedStart + ' e ' + formattedEnd + '.' : '');
                } else {
                    $field.removeAttr('min max');
                    $range.addClass('hidden').text('');
                }
                validateCoursePeriodChronology($form);
            }
            function modalityRuleFromForm($form) {
                return {
                    permitir: $form.find('[name="permitir_multiplas_inscricoes_modalidade"]').is(':checked') ? 1 : 0,
                    limite: String($form.find('[name="limite_inscricoes_modalidade"]').val() || '1'),
                    liberacao: String($form.find('[name="data_liberacao_multiplas_inscricoes_modalidade"]').val() || '')
                };
            }
            function describeModalityRule(rule) {
                if (Number(rule && rule.permitir || 0) !== 1) {
                    return 'Regra que será aplicada: somente 1 inscrição por CPF nesta modalidade.';
                }
                const release = formatBrazilianDate(String(rule && rule.liberacao || '')) || 'data e horário ainda não informados';
                return 'Regra que será aplicada: até ' + String(rule && rule.limite || '2') + ' inscrições por CPF nesta modalidade, liberadas a partir de ' + release + '.';
            }
            function modalityScheduleSiblings($form) {
                const currentId = String($form.find('[name="cronograma_modalidade_id"]').val() || '');
                const seasonId = String($form.find('[name="temporada_id"]').val() || '');
                const modalityId = String($form.find('[name="modalidade_id"]').val() || '');
                return scheduleData('data-modality-schedules').filter(function (item) {
                    return String(item.id || '') !== currentId && String(item.temporada_id || '') === seasonId && String(item.modalidade_id || '') === modalityId;
                });
            }
            function updateModalityMultipleScheduleWarning($form) {
                const schedules = modalityScheduleSiblings($form);
                const enabled = $form.find('[name="permitir_multiplas_inscricoes_modalidade"]').is(':checked');
                const $warning = $form.find('[data-modality-multiple-siblings-warning="1"]');
                $warning.toggleClass('hidden', !enabled || schedules.length === 0).text(enabled && schedules.length > 0
                    ? 'Existem ' + schedules.length + ' outro(s) cronograma(s) desta modalidade nesta temporada. ' + describeModalityRule(modalityRuleFromForm($form)) + ' Ao confirmar, ela será aplicada a todos esses cronogramas.'
                    : '');
                $form.data('modality-schedule-siblings', schedules);
            }
            function fillScheduleForm(record) {
                const $form = $('#admin-modality-schedule-form');
                $form[0].reset();
                $form.find('input, select, textarea').each(function () {
                    this.setCustomValidity('');
                    $(this).removeAttr('data-validation-touched data-period-validation-error data-remote-validation-error').removeClass('field-invalid');
                });
                $form.find('.field-invalid-container').removeClass('field-invalid-container');
                $form.find('.field-error-inline').remove();
                $form.find('[name="cronograma_modalidade_id"]').val(record && record.id ? String(record.id) : '');
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
                const allowMultiple = $form.find('[name="permitir_multiplas_inscricoes_modalidade"]').is(':checked');
                $form.find('[data-modality-multiple-fields="1"]').toggleClass('hidden', !allowMultiple).find('input').prop('required', allowMultiple);
                $form.data('original-modality-rule', JSON.stringify({ permitir: allowMultiple ? 1 : 0, limite: String($form.find('[name="limite_inscricoes_modalidade"]').val() || '1'), liberacao: String($form.find('[name="data_liberacao_multiplas_inscricoes_modalidade"]').val() || '') }));
                $form.data('modality-schedule-sibling-count', Math.max(0, Number(record && record.total_cronogramas_modalidade || 1) - 1));
                updateModalityMultipleScheduleWarning($form);
                updateModalityRegistrationEnrollmentField();
                validateCoursePeriodChronology($form);
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
            function copySeasonToModalitySchedule() {
                const $form = $('#admin-modality-schedule-form');
                const seasonId = String($form.find('[name="temporada_id"]').val() || '');
                const season = scheduleData('data-modality-seasons').find(function (item) { return String(item.id) === seasonId; });
                if (!season) {
                    App.core.abrirPopup('erro', 'Selecione primeiro a temporada cujo cronograma será utilizado.');
                    return;
                }
                ['inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim'].forEach(function (field) { $form.find('[name="' + field + '"]').val(toLocalDateTime(season[field])); });
                $form.find('[name="abrangencia_semanal"]').val(String(season.abrangencia_semanal || 'segunda_sexta'));
                ['data_inicio', 'data_fim', 'aulas_inicio', 'aulas_fim'].forEach(function (field) { $form.find('[name="' + field + '"]').val(String(season[field] || '')); });
                $form.find('[name="permitir_inscricao_periodo_matricula"]').prop('checked', Number(season.permitir_inscricao_periodo_matricula || 0) === 1);
                const hasNotice = Number(season.possui_edital || 0) === 1;
                $form.find('[name="possui_edital"]').prop('checked', hasNotice);
                $form.find('[name="numero_edital"]').val(hasNotice ? String(season.numero_edital || '') : '');
                $form.find('[name="link_edital"]').val(hasNotice ? String(season.link_edital || '') : '');
                $form.find('[data-modality-schedule-notice-fields="1"]').toggleClass('hidden', !hasNotice).find('input').prop('required', hasNotice);
                const allowMultiple = Number(season.permitir_multiplas_inscricoes_modalidade || 0) === 1;
                $form.find('[name="permitir_multiplas_inscricoes_modalidade"]').prop('checked', allowMultiple);
                $form.find('[name="limite_inscricoes_modalidade"]').val(String(season.limite_inscricoes_modalidade || 2));
                $form.find('[name="data_liberacao_multiplas_inscricoes_modalidade"]').val(toLocalDateTime(season.data_liberacao_multiplas_inscricoes_modalidade));
                $form.find('[data-modality-multiple-fields="1"]').toggleClass('hidden', !allowMultiple).find('input').prop('required', allowMultiple);
                updateModalityRegistrationEnrollmentField();
                validateCoursePeriodChronology($form);
                const modalityText = $form.find('[name="modalidade_id"] option:selected').text();
                if (!$form.find('[name="nome"]').val()) $form.find('[name="nome"]').val((modalityText && modalityText !== 'Selecione' ? modalityText + ' - ' : '') + String(season.nome || ''));
                App.core.abrirPopup('sucesso', 'O cronograma da temporada foi copiado. Revise os dados antes de salvar.');
            }
            $(document).on('click', '#admin-modality-schedule-use-season', copySeasonToModalitySchedule);
            $(document).on('change', '[data-modality-schedule-notice-toggle="1"]', function () {
                const enabled = $(this).is(':checked');
                $('#admin-modality-schedule-form [data-modality-schedule-notice-fields="1"]').toggleClass('hidden', !enabled).find('input').prop('required', enabled);
            });
            $(document).on('change', '[data-modality-multiple-toggle="1"]', function () {
                const enabled = $(this).is(':checked');
                const $form = $('#admin-modality-schedule-form');
                $form.find('[data-modality-multiple-fields="1"]').toggleClass('hidden', !enabled).find('input').prop('required', enabled);
                updateModalityMultipleScheduleWarning($form);
            });
            $(document).on('change', '#admin-modality-schedule-form [name="temporada_id"], #admin-modality-schedule-form [name="modalidade_id"]', function () { updateModalityMultipleScheduleWarning($(this).closest('form')); });
            $(document).on('input change', '#admin-modality-schedule-form [name="limite_inscricoes_modalidade"], #admin-modality-schedule-form [name="data_liberacao_multiplas_inscricoes_modalidade"]', function () { updateModalityMultipleScheduleWarning($(this).closest('form')); });
            $(document).on('change', '[data-modality-registration-enrollment-toggle="1"], #admin-modality-schedule-form [name="matriculas_inicio"], #admin-modality-schedule-form [name="matriculas_fim"]', updateModalityRegistrationEnrollmentField);
            $(document).on('input change', '#admin-modality-schedule-form [name="data_inicio"], #admin-modality-schedule-form [name="data_fim"], #admin-modality-schedule-form [name="inscricoes_inicio"], #admin-modality-schedule-form [name="inscricoes_fim"], #admin-modality-schedule-form [name="matriculas_inicio"], #admin-modality-schedule-form [name="matriculas_fim"], #admin-modality-schedule-form [name="abrangencia_semanal"], #admin-modality-schedule-form [name="inscricoes_abertas_inicio"], #admin-modality-schedule-form [name="inscricoes_abertas_fim"], #admin-modality-schedule-form [name="aulas_inicio"], #admin-modality-schedule-form [name="aulas_fim"]', function () { validateCoursePeriodChronology($(this).closest('form')); });
            $(document).on('submit', '#admin-modality-schedule-form', function (event) {
                event.preventDefault(); const $form = $(this);
                const chronologyValid = validateCoursePeriodChronology($form);
                let firstInvalid = null;
                $form.find('input, select, textarea').each(function () {
                    if (firstInvalid || this.disabled || String(this.type || '').toLowerCase() === 'hidden' || $(this).closest('.hidden').length > 0) return;
                    if (!this.checkValidity()) {
                        $(this).attr('data-validation-touched', '1');
                        App.core.validarCampoInline(this, true);
                        $(this).closest('label').addClass('field-invalid-container');
                        firstInvalid = this;
                    }
                });
                if (!chronologyValid || firstInvalid) {
                    firstInvalid = firstInvalid || $form.find('[data-period-validation-error]').get(0) || null;
                    const $invalid = $(firstInvalid);
                    const fieldLabel = String($invalid.closest('label').children('span').first().text() || $invalid.attr('name') || 'campo').trim();
                    App.core.abrirPopup('erro', 'Preencha ou corrija o campo destacado antes de salvar. Campo: ' + fieldLabel + '.', function () {
                        if (!firstInvalid) return;
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        window.setTimeout(function () { firstInvalid.focus(); App.core.validarCampoInline(firstInvalid, true); }, 250);
                    });
                    return;
                }
                const currentId = String($form.find('[name="cronograma_modalidade_id"]').val() || '');
                const seasonId = String($form.find('[name="temporada_id"]').val() || '');
                const scheduleName = String($form.find('[name="nome"]').val() || '').trim().toLocaleLowerCase('pt-BR');
                const duplicatedName = scheduleData('data-modality-schedules').some(function (schedule) {
                    return String(schedule.id || '') !== currentId
                        && String(schedule.temporada_id || '') === seasonId
                        && String(schedule.nome || '').trim().toLocaleLowerCase('pt-BR') === scheduleName;
                });
                if (duplicatedName) {
                    App.core.abrirPopup('erro', 'Já existe um cronograma com este nome na temporada selecionada. Informe um nome diferente.');
                    $form.find('[name="nome"]').trigger('focus');
                    return;
                }
                $form.find('[name="aplicar_regra_modalidade_todos_cronogramas"]').remove();
                const editing = Number($form.find('[name="cronograma_modalidade_id"]').val() || 0) > 0;
                const currentRule = JSON.stringify(modalityRuleFromForm($form));
                const siblings = modalityScheduleSiblings($form);
                let mustConfirm = editing && currentRule !== String($form.data('original-modality-rule') || '') && siblings.length > 0;
                if (!editing && siblings.length > 0) {
                    const reference = siblings[0];
                    const referenceRule = JSON.stringify({ permitir: Number(reference.permitir_multiplas_inscricoes_modalidade || 0) === 1 ? 1 : 0, limite: String(reference.limite_inscricoes_modalidade || '1'), liberacao: toLocalDateTime(reference.data_liberacao_multiplas_inscricoes_modalidade) });
                    mustConfirm = currentRule !== referenceRule;
                }
                if (mustConfirm) {
                    const confirmationMessage = 'Já existem ' + siblings.length + ' outro(s) cronograma(s) desta modalidade nesta temporada.\n\n' + describeModalityRule(modalityRuleFromForm($form)) + '\n\nA regra será aplicada a todos eles. Deseja prosseguir?';
                    if (!window.confirm(confirmationMessage)) return;
                    $form.append($('<input>', { type: 'hidden', name: 'aplicar_regra_modalidade_todos_cronogramas', value: '1' }));
                }
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/modalidades/cronogramas'),
                    method: 'POST',
                    dataType: 'json',
                    data: $form.serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
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
            function formatBrazilianDate(value) {
                const raw = String(value || '');
                const parts = raw.slice(0, 10).split('-');
                if (parts.length !== 3) return '';
                const time = raw.length >= 16 ? raw.slice(11, 16) : '';
                return parts[2] + '/' + parts[1] + '/' + parts[0] + (time ? ' às ' + time : '');
            }

            function modalFor(type) {
                return $(type === 'season' ? '#course-season-modal' : '#course-class-modal');
            }

            function closeModals() {
                $('#course-season-modal, #course-class-modal, #course-professor-modal, #course-class-status-modal').addClass('hidden').attr('aria-hidden', 'true');
            }

            function validateCoursePeriodChronology($form) {
                const fieldNames = ['data_inicio', 'data_fim', 'inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'aulas_inicio', 'aulas_fim'];
                const fields = {};
                const values = {};
                fieldNames.forEach(function (name) {
                    fields[name] = $form.find('[name="' + name + '"]').get(0) || null;
                    values[name] = fields[name] && fields[name].value ? new Date(fields[name].value.length === 10 ? fields[name].value + 'T00:00:00' : fields[name].value) : null;
                    if (fields[name]) {
                        fields[name].setCustomValidity('');
                        $(fields[name]).removeAttr('data-period-validation-error data-remote-validation-error');
                    }
                });
                function setPeriodError(field, message) {
                    if (!field) return;
                    $(field).attr('data-period-validation-error', message).attr('data-remote-validation-error', message);
                    field.setCustomValidity(message);
                }
                function before(earlier, later, message) {
                    if (!values[earlier] || !values[later] || values[earlier].getTime() < values[later].getTime()) return true;
                    setPeriodError(fields[later], message);
                    return false;
                }
                let valid = true;
                valid = before('data_inicio', 'data_fim', 'O fim da publicação deve ser posterior ao seu início.') && valid;
                valid = before('data_inicio', 'inscricoes_inicio', 'O início da inscrição inicial deve ser posterior ao início da publicação.') && valid;
                valid = before('inscricoes_inicio', 'inscricoes_fim', 'O fim da inscrição inicial deve ser posterior ao seu início.') && valid;
                valid = before('inscricoes_fim', 'matriculas_inicio', 'As matrículas devem começar somente depois do encerramento da inscrição inicial.') && valid;
                valid = before('matriculas_inicio', 'matriculas_fim', 'O fim das matrículas deve ser posterior ao seu início.') && valid;
                const weeklyCoverage = String($form.find('[name="abrangencia_semanal"]').val() || 'segunda_sexta');
                if (values.matriculas_inicio && values.matriculas_fim) {
                    if (values.matriculas_inicio.getDay() !== 1) {
                        setPeriodError(fields.matriculas_inicio, 'O período de matrícula deve começar em uma segunda-feira.');
                        valid = false;
                    }
                    const minimumDays = weeklyCoverage === 'segunda_domingo' ? 7 : 5;
                    const minimumEnd = new Date(values.matriculas_inicio.getFullYear(), values.matriculas_inicio.getMonth(), values.matriculas_inicio.getDate() + minimumDays - 1);
                    if (values.matriculas_fim.getTime() < minimumEnd.getTime()) {
                        setPeriodError(fields.matriculas_fim, 'O período deve abranger pelo menos ' + minimumDays + ' dias, começando em uma segunda-feira.');
                        valid = false;
                    }
                }
                if ($form.find('[name="permitir_inscricao_periodo_matricula"]').is(':checked') && values.inscricoes_abertas_inicio && values.matriculas_inicio && values.matriculas_fim) {
                    if (values.inscricoes_abertas_inicio < values.matriculas_inicio || values.inscricoes_abertas_inicio > values.matriculas_fim) {
                        setPeriodError(fields.inscricoes_abertas_inicio, 'Inscrições abertas: início deve estar entre ' + formatBrazilianDate(fields.matriculas_inicio.value) + ' e ' + formatBrazilianDate(fields.matriculas_fim.value) + '.');
                        valid = false;
                    }
                }
                valid = before('inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'O fim das inscrições abertas deve ser posterior ao seu início.') && valid;
                valid = before('data_inicio', 'aulas_inicio', 'O início das aulas deve ser posterior ao início da publicação.') && valid;
                valid = before('matriculas_inicio', 'aulas_inicio', 'O início das aulas deve ser posterior ao início das matrículas.') && valid;
                valid = before('aulas_inicio', 'aulas_fim', 'O fim das aulas deve ser posterior ao seu início.') && valid;
                fieldNames.forEach(function (name) {
                    if (fields[name] && $(fields[name]).attr('data-validation-touched') === '1') App.core.validarCampoInline(fields[name], true);
                });
                return valid;
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

            function ensureClassProgramField($form) {
                if ($form.find('[name="programa"]').length) return;
                const $nameField = $form.find('[name="nome"]').closest('label');
                if (!$nameField.length) return;
                const $select = $('<select>', { name: 'programa' })
                    .append($('<option>', { value: '', text: 'Sem programa definido' }))
                    .append($('<option>', { value: 'Corpo em Ação', text: 'Corpo em Ação' }))
                    .append($('<option>', { value: 'Hora do Treino', text: 'Hora do Treino' }))
                    .append($('<option>', { value: 'Campeões da Vida', text: 'Campeões da Vida' }))
                    .append($('<option>', { value: 'GR São Bernardo', text: 'GR São Bernardo' }));
                $nameField.after($('<label>').append($('<span>', { text: 'Programa' })).append($select));
            }

            function ensureClassAgeExceptionFields($form) {
                if ($form.find('[data-class-age-exceptions="1"]').length) return;
                const labels = {
                    pcd: 'PCD (Pessoa Com Deficiência)',
                    plm: 'PLM (Pessoa com Laudo Médico de Doença)',
                    pvs: 'PVS (Pessoa em situação de Vulnerabilidade Social)'
                };
                const $field = $('<fieldset>', { 'data-class-age-exceptions': '1', class: 'course-age-exceptions-field' });
                $field.append($('<legend>', { text: 'Exceções de faixa etária por público-alvo ' }).append($('<button>', {
                    type: 'button', class: 'field-help-button', text: '?',
                    'aria-label': 'Ajuda sobre exceções de faixa etária',
                    'data-field-help-message': 'Permite a inscrição fora da faixa etária geral exclusivamente para a condição selecionada. A pessoa deverá estar dentro da faixa excepcional e possuir documentação correspondente previamente enviada, validada e vigente. Os demais requisitos e a disponibilidade de vagas continuam sendo exigidos.'
                })));
                Object.keys(labels).forEach(function (condition) {
                    const prefix = 'excecoes_idade[' + condition + ']';
                    const $toggle = $('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'checkbox', name: prefix + '[enabled]', value: '1', 'data-class-age-exception-toggle': condition }))
                        .append($('<span>', { text: 'Permitir exceção para ' + labels[condition] }));
                    const $range = $('<div>', { class: 'grid-two hidden', 'data-class-age-exception-range': condition })
                        .append($('<label>').append($('<span>', { text: 'Idade mínima excepcional' })).append($('<input>', { type: 'number', name: prefix + '[min]', min: 0, value: 0 })))
                        .append($('<label>').append($('<span>', { text: 'Idade máxima excepcional' })).append($('<input>', { type: 'number', name: prefix + '[max]', min: 0, value: 120 })));
                    $field.append($toggle, $range);
                });
                $field.append($('<small>', { class: 'muted', text: 'As exceções ficam desativadas por padrão.' }));
                $form.find('[name="criterio_faixa_etaria"]').closest('label').after($field);
            }

            function updateClassAgeExceptionFields($form) {
                $form.find('[data-class-age-exception-toggle]').each(function () {
                    const condition = String($(this).attr('data-class-age-exception-toggle') || '');
                    const enabled = $(this).is(':checked');
                    $form.find('[data-class-age-exception-range="' + condition + '"]')
                        .toggleClass('hidden', !enabled)
                        .find('input').prop('required', enabled);
                });
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
                const $label = $('<label>').append($('<span>', { text: 'Cronograma da modalidade' })).append($select)
                    .append($('<small>', { class: 'field-error hidden', 'data-class-schedule-warning': '1' }));
                const $catalog = $('<div>', { class: 'class-schedule-catalog hidden', 'data-class-schedule-catalog': '1' });
                $form.find('[name="modalidade_id"]').closest('label').after($label, $catalog);
            }

            function ensureClassCopyFields($form) {
                if ($form.find('[data-class-copy]').length || String($form.attr('action') || '').indexOf('/professor/') === -1) return;
                const $copy = $('<section>', { class: 'class-copy-panel', 'data-class-copy': '1' });
                const $steps = $('<div>', { class: 'class-copy-steps hidden', 'data-class-copy-steps': '1' })
                    .append($('<p>', { class: 'muted', text: 'Selecione a temporada de origem.' }))
                    .append($('<div>', { class: 'class-copy-options', 'data-class-copy-options': 'seasons' }))
                    .append($('<div>', { class: 'class-copy-step hidden', 'data-class-copy-step': 'modalities' }).append($('<strong>', { text: 'Modalidade' }), $('<div>', { class: 'class-copy-options' })))
                    .append($('<div>', { class: 'class-copy-step hidden', 'data-class-copy-step': 'classes' }).append($('<strong>', { text: 'Turma' }), $('<div>', { class: 'class-copy-options' })))
                    .append($('<div>', { class: 'form-notice hidden', 'data-class-copy-notice': '1' }));
                $copy.append(
                    $('<label>', { class: 'checkbox-chip class-copy-toggle' }).append($('<input>', { type: 'checkbox', 'data-class-copy-toggle': '1' }), $('<span>', { text: 'Deseja copiar uma turma de outra temporada?' })),
                    $steps,
                    $('<input>', { type: 'hidden', name: 'copia_origem_tipo' }),
                    $('<input>', { type: 'hidden', name: 'copia_origem_temporada_id' }),
                    $('<input>', { type: 'hidden', name: 'copia_origem_turma_id' })
                );
                $form.find('[name="temporada_id"]').closest('label').after($copy);
            }

            function classCopyRequest($form, data, done) {
                $.ajax({
                    url: App.core.buildUrl('/professor/minhas-turmas/copiar'), method: 'GET', dataType: 'json', data: data,
                    suppressGlobalLoading: true, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível consultar as turmas para cópia.')); return; }
                    done(response);
                }).fail(function (xhr) {
                    if (App.auth && App.auth.tratarFalhaDeAcesso(xhr, function () { classCopyRequest($form, data, done); }, '/professor')) return;
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                });
            }

            function renderClassCopyOptions($container, items, attribute, formatter) {
                $container.empty();
                (items || []).forEach(function (item) {
                    const text = formatter ? formatter(item) : String(item.nome || '');
                    $container.append($('<button>', { type: 'button', class: 'btn btn-secondary', text: text }).attr(attribute, String(item.id || '')));
                });
                if (!$container.children().length) $container.append($('<span>', { class: 'muted', text: 'Nenhuma opção encontrada.' }));
            }

            function resetClassCopy($form, editing) {
                const $copy = $form.find('[data-class-copy]');
                $copy.toggleClass('hidden', !!editing).removeAttr('data-source-season');
                $copy.find('[data-class-copy-toggle]').prop('checked', false);
                $copy.find('[data-class-copy-steps], [data-class-copy-step], [data-class-copy-notice]').addClass('hidden');
                $copy.find('[data-class-copy-options], [data-class-copy-step] .class-copy-options').empty();
                $form.find('[name^="copia_origem_"]').val('');
                $form.find('.is-copy-required-missing').removeClass('is-copy-required-missing');
            }

            $(document).on('change', '[data-class-copy-toggle]', function () {
                const $form = $(this).closest('form'); const $copy = $(this).closest('[data-class-copy]');
                if (!$(this).is(':checked')) { resetClassCopy($form, false); return; }
                if (!String($form.find('[name="temporada_id"]').val() || '')) {
                    $(this).prop('checked', false);
                    App.core.abrirPopup('informacao', 'Selecione primeiro a temporada de destino da nova turma.');
                    return;
                }
                $copy.find('[data-class-copy-steps]').removeClass('hidden');
                classCopyRequest($form, { etapa: 'temporadas' }, function (response) {
                    renderClassCopyOptions($copy.find('[data-class-copy-options="seasons"]'), response.items, 'data-class-copy-season');
                });
            });

            $(document).on('click', '[data-class-copy-season]', function () {
                const $button = $(this); const $form = $button.closest('form'); const $copy = $button.closest('[data-class-copy]');
                $button.addClass('is-active').siblings().removeClass('is-active');
                $copy.attr('data-source-season', String($button.attr('data-class-copy-season') || ''));
                $copy.find('[data-class-copy-step="classes"], [data-class-copy-notice]').addClass('hidden');
                const $step = $copy.find('[data-class-copy-step="modalities"]').removeClass('hidden');
                classCopyRequest($form, { etapa: 'modalidades', temporada_origem: $copy.attr('data-source-season') }, function (response) {
                    renderClassCopyOptions($step.find('.class-copy-options'), response.items, 'data-class-copy-modality');
                });
            });

            $(document).on('click', '[data-class-copy-modality]', function () {
                const $button = $(this); const $form = $button.closest('form'); const $copy = $button.closest('[data-class-copy]');
                $button.addClass('is-active').siblings().removeClass('is-active');
                $copy.find('[data-class-copy-notice]').addClass('hidden');
                const $step = $copy.find('[data-class-copy-step="classes"]').removeClass('hidden');
                classCopyRequest($form, { etapa: 'turmas', temporada_origem: $copy.attr('data-source-season'), modalidade_origem_id: $button.attr('data-class-copy-modality') }, function (response) {
                    renderClassCopyOptions($step.find('.class-copy-options'), response.items, 'data-class-copy-class', function (item) { return '[' + String(item.id || '') + '] ' + String(item.nome || ''); });
                });
            });

            $(document).on('click', '[data-class-copy-class]', function () {
                const $button = $(this); const $form = $button.closest('form'); const $copy = $button.closest('[data-class-copy]');
                $button.addClass('is-active').siblings().removeClass('is-active');
                classCopyRequest($form, {
                    etapa: 'detalhe', temporada_origem: $copy.attr('data-source-season'), turma_origem_id: $button.attr('data-class-copy-class'),
                    temporada_destino_id: $form.find('[name="temporada_id"]').val()
                }, function (response) {
                    const record = response.record || {};
                    fillForm($form, record);
                    $form.find('[name="id"]').val('');
                    $form.find('[name="operacao"]').val('criar');
                    $form.find('[data-class-copy-toggle]').prop('checked', true);
                    $copy.removeClass('hidden').find('[data-class-copy-steps]').removeClass('hidden');
                    filterClassSchedules($form, record.cronograma_modalidade_id || '');
                    $form.find('[name="niveis_aceitos[]"]').each(function () { $(this).prop('checked', (record.niveis_aceitos || []).map(String).indexOf(String($(this).val())) !== -1); });
                    const ageExceptions = record.excecoes_idade && typeof record.excecoes_idade === 'object' ? record.excecoes_idade : {};
                    ['pcd', 'plm', 'pvs'].forEach(function (condition) {
                        const range = ageExceptions[condition] || null;
                        $form.find('[name="excecoes_idade[' + condition + '][enabled]"]').prop('checked', !!range);
                        if (range) {
                            $form.find('[name="excecoes_idade[' + condition + '][min]"]').val(String(range.min));
                            $form.find('[name="excecoes_idade[' + condition + '][max]"]').val(String(range.max));
                        }
                    });
                    updateClassAgeExceptionFields($form);
                    $form.find('.is-copy-required-missing').removeClass('is-copy-required-missing');
                    const missing = record.campos_obrigatorios_pendentes || [];
                    missing.forEach(function (item) { $form.find('[name="' + String(item.campo || '') + '"]').closest('label').addClass('is-copy-required-missing'); });
                    const notices = [];
                    if ((response.previous_copies || []).length) notices.push('Atenção: esta turma já foi copiada para ' + response.previous_copies.map(function (item) { return 'a turma [' + String(item.turma_id) + '] da temporada ' + String(item.temporada_nome || ''); }).join('; ') + '. Você ainda pode criar outra cópia.');
                    if (missing.length) notices.push('Preencha os campos obrigatórios destacados: ' + missing.map(function (item) { return String(item.rotulo || ''); }).join(', ') + '.');
                    $copy.find('[data-class-copy-notice]').toggleClass('hidden', notices.length === 0).text(notices.join(' '));
                });
            });

            function ensureClassLevelFields($form) {
                if ($form.find('[name="niveis_aceitos[]"]').length) return;
                const levels = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado', treinamento: 'Treinamento' };
                const $field = $('<fieldset>', { class: 'course-levels-field' });
                const $legend = $('<legend>', { text: 'Níveis aceitos ' }).append($('<button>', {
                    type: 'button', class: 'field-help-button', text: '?',
                    'aria-label': 'Ajuda sobre níveis aceitos',
                    'data-field-help-message': 'Sem nível marcado, a turma aceita pessoas com ou sem certificado. Ao marcar um ou mais níveis, somente pessoas com certificado de nível ativo e correspondente nesta modalidade poderão se inscrever.'
                }));
                const $options = $('<div>', { class: 'course-weekdays-options' });
                Object.keys(levels).forEach(function (slug) {
                    $options.append($('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'checkbox', name: 'niveis_aceitos[]', value: slug }))
                        .append($('<span>', { text: levels[slug] })));
                });
                $field.append($legend, $options, $('<small>', { class: 'muted', text: 'Padrão: sem limitação de nível.' }));
                $form.find('[name="nome"]').closest('label').after($field);
            }

            function classScheduleData($form) {
                try { return JSON.parse(String($form.closest('[data-course-modality-schedules]').attr('data-course-modality-schedules') || '[]')); } catch (error) { return []; }
            }

            function compactScheduleDate(value) {
                if (!value) return 'Não informado';
                return formatBrazilianDate(String(value));
            }

            function renderClassScheduleCatalog($form) {
                const seasonId = String($form.find('[name="temporada_id"]').val() || '');
                const modalityId = String($form.find('[name="modalidade_id"]').val() || '');
                const selectedId = String($form.find('[name="cronograma_modalidade_id"]').val() || '');
                const schedules = classScheduleData($form).filter(function (schedule) {
                    return String(schedule.temporada_id || '') === seasonId && String(schedule.modalidade_id || '') === modalityId;
                });
                const $catalog = $form.find('[data-class-schedule-catalog="1"]').empty().toggleClass('hidden', schedules.length === 0);
                schedules.forEach(function (schedule) {
                    const multiple = Number(schedule.permitir_multiplas_inscricoes_modalidade || 0) === 1
                        ? 'Até ' + String(schedule.limite_inscricoes_modalidade || 2) + ' por CPF/modalidade após ' + compactScheduleDate(schedule.data_liberacao_multiplas_inscricoes_modalidade)
                        : 'Uma inscrição por CPF/modalidade';
                    const notice = Number(schedule.possui_edital || 0) === 1 ? 'Edital ' + String(schedule.numero_edital || 'informado') : 'Sem edital específico';
                    const $card = $('<button>', { type: 'button', class: 'class-schedule-option' + (String(schedule.id) === selectedId ? ' is-selected' : ''), 'data-class-schedule-option': String(schedule.id) })
                        .append($('<strong>', { text: String(schedule.nome || 'Cronograma') }))
                        .append($('<span>', { text: 'Publicação: ' + compactScheduleDate(schedule.data_inicio) + ' a ' + compactScheduleDate(schedule.data_fim) }))
                        .append($('<span>', { text: 'Inscrição inicial: ' + compactScheduleDate(schedule.inscricoes_inicio) + ' a ' + compactScheduleDate(schedule.inscricoes_fim) }))
                        .append($('<span>', { text: 'Matrículas: ' + compactScheduleDate(schedule.matriculas_inicio) + ' a ' + compactScheduleDate(schedule.matriculas_fim) }))
                        .append($('<span>', { text: 'Inscrição durante matrículas: ' + (Number(schedule.permitir_inscricao_periodo_matricula || 0) === 1 ? 'Sim' : 'Não') }))
                        .append($('<span>', { text: 'Inscrições abertas: ' + compactScheduleDate(schedule.inscricoes_abertas_inicio) + ' a ' + compactScheduleDate(schedule.inscricoes_abertas_fim) }))
                        .append($('<span>', { text: 'Aulas: ' + compactScheduleDate(schedule.aulas_inicio) + ' a ' + compactScheduleDate(schedule.aulas_fim) }))
                        .append($('<span>', { text: notice + ' · ' + multiple }));
                    $catalog.append($card);
                });
            }

            function ensureClassOpenEnrollmentField($form) {
                if ($form.find('[name="inscricoes_abertas"]').length) return;
                const $field = $('<label>', { class: 'checkbox-chip' })
                    .append($('<input>', { type: 'checkbox', name: 'inscricoes_abertas', value: '1' }))
                    .append($('<span>', { text: 'Inscrições abertas nesta turma' }));
                $form.find('button[type="submit"]').before($field);
            }

            function ensureClassFieldHelp($form) {
                const help = {
                    temporada_id: 'Selecione a temporada à qual a turma pertencerá. A temporada serve de referência para publicação, inscrições e matrículas.',
                    modalidade_id: 'Selecione a modalidade esportiva oferecida pela turma. A escolha define quais cronogramas estarão disponíveis.',
                    cronograma_modalidade_id: 'Selecione o cronograma que regerá os períodos de publicação, inscrição, matrícula e aulas desta turma.',
                    local_treino_id: 'Selecione o local onde as aulas desta turma serão realizadas.',
                    espaco_treino_id: 'Selecione o espaço específico do local onde as aulas acontecerão, como quadra, sala ou piscina.',
                    nome: 'Informe um nome que identifique claramente a turma para administradores, professores e público.',
                    hora_inicio: 'Informe a hora em que a aula começa nos dias da semana selecionados.',
                    hora_fim: 'Informe a hora em que a aula termina. Ela deve ser posterior à hora inicial.',
                    criterio_faixa_etaria: 'Escolha se a faixa etária será conferida pela idade exata na data de referência ou apenas pelo ano de nascimento.',
                    idade_minima: 'Informe a menor idade aceita na turma, conforme o critério etário selecionado.',
                    idade_maxima: 'Informe a maior idade aceita na turma, conforme o critério etário selecionado.',
                    sexo: 'Defina se a turma aceita todos os sexos ou se possui uma restrição específica.',
                    vagas_geral: 'Informe a quantidade de vagas reservadas ao público geral.',
                    vagas_pcd: 'Informe a quantidade de vagas reservadas a Pessoas com Deficiência (PCD).',
                    vagas_plm: 'Informe a quantidade de vagas reservadas a Pessoas com Laudo Médico de Doença.',
                    vagas_pvs: 'Informe a quantidade de vagas reservadas a Pessoas em Vulnerabilidade Social.',
                    vagas_espera_geral: 'Informe o limite da lista de espera destinado ao público geral.',
                    vagas_espera_pcd: 'Informe o limite da lista de espera destinado a Pessoas com Deficiência (PCD).',
                    vagas_espera_plm: 'Informe o limite da lista de espera destinado a Pessoas com Laudo Médico de Doença.',
                    vagas_espera_pvs: 'Informe o limite da lista de espera destinado a Pessoas em Vulnerabilidade Social.',
                    vagas_totais: 'Confira o total de vagas da turma. O valor deve corresponder à soma das vagas distribuídas entre os públicos.',
                    inscricoes_abertas: 'Indica se a turma está habilitada para receber inscrições, sempre respeitando o status da turma e o cronograma selecionado.'
                };
                Object.keys(help).forEach(function (name) {
                    const $field = $form.find('[name="' + name + '"]').first();
                    if (!$field.length) return;
                    const $label = $field.closest('label');
                    const $caption = $label.children('span').first();
                    if (!$caption.length || $caption.find('[data-field-help-message]').length) return;
                    $caption.append($('<button>', {
                        type: 'button',
                        class: 'field-help-button',
                        text: '?',
                        'aria-label': 'Ajuda sobre ' + $caption.clone().children().remove().end().text().trim(),
                        'data-field-help-message': help[name]
                    }));
                });
                const $daysLegend = $form.find('.course-weekdays-field legend').first();
                if ($daysLegend.length && !$daysLegend.find('[data-field-help-message]').length) {
                    $daysLegend.append($('<button>', {
                        type: 'button', class: 'field-help-button', text: '?',
                        'aria-label': 'Ajuda sobre dias da semana',
                        'data-field-help-message': 'Marque todos os dias em que a turma terá aula. É possível escolher qualquer combinação, inclusive dias consecutivos, sábado e domingo.'
                    }));
                }
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
                const availableCount = $select.find('option[data-season-id]').filter(function () { return !$(this).prop('disabled'); }).length;
                const missingSchedule = Boolean(seasonId && modalityId && availableCount === 0);
                const $warning = $form.find('[data-class-schedule-warning="1"]');
                $warning.toggleClass('hidden', !missingSchedule).text(missingSchedule ? 'Esta modalidade não possui cronograma na temporada selecionada. Crie primeiro o cronograma da modalidade.' : '');
                $select.prop('disabled', missingSchedule);
                $form.find('button[type="submit"]').prop('disabled', missingSchedule);
                renderClassScheduleCatalog($form);
                return !missingSchedule;
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
                if ($form.find('[name="permitir_inscricao_periodo_matricula"]').length === 0) {
                    const $enrollmentPeriod = $form.find('[name="matriculas_fim"]').closest('.grid-two');
                    const $allowEnrollmentDuringRegistration = $('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'checkbox', name: 'permitir_inscricao_periodo_matricula', value: '1', 'data-season-registration-enrollment-toggle': '1' }))
                        .append($('<span>', { text: 'Aceitar inscrições durante o período de matrícula' }));
                    const $registrationRange = $('<small>', { class: 'hidden', 'data-season-registration-enrollment-range': '1' });
                    $enrollmentPeriod.after($allowEnrollmentDuringRegistration, $registrationRange);
                }
                if ($form.find('[name="permitir_multiplas_inscricoes_modalidade"]').length === 0) {
                    const $loggedEnrollment = $form.find('[name="permitir_inscricao_logada"]').closest('label');
                    const $toggle = $('<label>', { class: 'checkbox-chip' })
                        .append($('<input>', { type: 'checkbox', name: 'permitir_multiplas_inscricoes_modalidade', value: '1', 'data-season-multiple-toggle': '1' }))
                        .append($('<span>', { text: 'Aceitar mais de uma inscrição por CPF na mesma modalidade' }));
                    const $fields = $('<div>', { class: 'grid-two hidden', 'data-season-multiple-fields': '1' })
                        .append($('<label>').append($('<span>', { text: 'Máximo de inscrições por CPF/modalidade' })).append($('<input>', { type: 'number', name: 'limite_inscricoes_modalidade', min: 2, value: 2 })))
                        .append($('<label>').append($('<span>', { text: 'Liberar inscrições adicionais em' })).append($('<input>', { type: 'datetime-local', name: 'data_liberacao_multiplas_inscricoes_modalidade' })));
                    $loggedEnrollment.before($toggle, $fields);
                }
                $form.find('[name="nome"], [name="tipo_periodicidade"], [name="data_inicio"], [name="data_fim"], [name="inscricoes_inicio"], [name="inscricoes_fim"], [name="matriculas_inicio"], [name="matriculas_fim"], [name="inscricoes_abertas_inicio"], [name="inscricoes_abertas_fim"], [name="aulas_inicio"], [name="aulas_fim"], [name="status"], [name="limite_inscricoes_periodo"], [name="data_liberacao_segunda_inscricao"], [name="data_liberacao_inscricoes_adicionais"], [name="limite_inscricoes_adicionais"]').prop('required', true);
            }

            function ensureSeasonWeeklyCoverageField($form) {
                if ($form.find('[name="abrangencia_semanal"]').length) return;
                const $field = $('<label>')
                    .append($('<span>', { text: 'Abrangência semanal das aulas' }))
                    .append($('<select>', { name: 'abrangencia_semanal', required: true })
                        .append($('<option>', { value: 'segunda_sexta', text: 'Segunda a sexta-feira' }))
                        .append($('<option>', { value: 'segunda_domingo', text: 'Segunda-feira a domingo' })))
                    .append($('<small>', { class: 'muted', text: 'O período de matrícula deve começar em uma segunda-feira e abranger integralmente os dias selecionados.' }));
                $form.find('[name="matriculas_inicio"]').closest('.grid-two').before($field);
                $form.find('[name="matriculas_inicio"], [name="matriculas_fim"]').prop('required', true);
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
                permitir_inscricao_periodo_matricula: 'Define se novas inscrições também poderão ser realizadas entre o início e o fim do período de matrícula. Ao criar um cronograma de modalidade, esta escolha será copiada da temporada e poderá ser alterada de forma independente.',
                inscricoes_abertas_inicio: 'Início do período de inscrições abertas. Quando a temporada aceitar inscrições durante as matrículas, esta data deverá estar entre o início e o fim do período de matrículas.',
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

            function updateSeasonRegistrationEnrollmentField($form) {
                const enabled = $form.find('[name="permitir_inscricao_periodo_matricula"]').is(':checked');
                const $field = $form.find('[name="inscricoes_abertas_inicio"]');
                const registrationStart = String($form.find('[name="matriculas_inicio"]').val() || '');
                const registrationEnd = String($form.find('[name="matriculas_fim"]').val() || '');
                const $range = $form.find('[data-season-registration-enrollment-range="1"]');
                $field.prop('required', true);
                if (enabled) {
                    $field.removeAttr('min max');
                    const formattedStart = formatBrazilianDate(registrationStart);
                    const formattedEnd = formatBrazilianDate(registrationEnd);
                    $range.toggleClass('hidden', !formattedStart || !formattedEnd)
                        .text(formattedStart && formattedEnd ? 'Inscrições abertas: início deve estar entre ' + formattedStart + ' e ' + formattedEnd + '.' : '');
                } else {
                    $field.removeAttr('min max');
                    $range.addClass('hidden').text('');
                }
                validateCoursePeriodChronology($form);
            }

            function openModal(type, record) {
                const $modal = modalFor(type);
                const $form = $modal.find('[data-course-form="' + type + '"]');
                if ($modal.length === 0 || $form.length === 0) {
                    App.core.abrirPopup('erro', 'Não foi possível carregar o formulário solicitado. Atualize a seção e tente novamente.');
                    return;
                }
                // Permite que o evento AJAX trate também formulários incompletos e
                // apresente claramente o primeiro campo inválido dentro do modal.
                $form.attr({
                    novalidate: 'novalidate',
                    'data-own-submit-validation': '1',
                    'data-manual-submit': '1'
                });
                if ($form.find('[name="operacao"]').length === 0) {
                    $form.append($('<input>', { type: 'hidden', name: 'operacao' }));
                }
                if (type === 'class') { ensureClassCopyFields($form); ensureClassProgramField($form); ensureClassAgeCriterionField($form); ensureClassAgeExceptionFields($form); ensureClassScheduleField($form); ensureClassLevelFields($form); ensureClassOpenEnrollmentField($form); ensureClassFieldHelp($form); }
                if (type === 'season') { ensureSeasonNoticeFields($form); ensureSeasonWeeklyCoverageField($form); ensureSeasonFieldHelp($form); }
                fillForm($form, record || {});
                if (type === 'class') {
                    const acceptedLevels = Array.isArray((record || {}).niveis_aceitos) ? record.niveis_aceitos : [];
                    $form.find('[name="niveis_aceitos[]"]').each(function () { $(this).prop('checked', acceptedLevels.indexOf(String($(this).val())) !== -1); });
                    const ageExceptions = record && record.excecoes_idade && typeof record.excecoes_idade === 'object' ? record.excecoes_idade : {};
                    ['pcd', 'plm', 'pvs'].forEach(function (condition) {
                        const range = ageExceptions[condition] || null;
                        $form.find('[name="excecoes_idade[' + condition + '][enabled]"]').prop('checked', !!range);
                        if (range) {
                            $form.find('[name="excecoes_idade[' + condition + '][min]"]').val(String(range.min));
                            $form.find('[name="excecoes_idade[' + condition + '][max]"]').val(String(range.max));
                        }
                    });
                    updateClassAgeExceptionFields($form);
                }
                if (type === 'class') filterClassSchedules($form, record && record.cronograma_modalidade_id);
                $form.find('[name="operacao"]').val(record ? 'editar' : 'criar');
                if (type === 'class') resetClassCopy($form, !!record);
                if (!record && type === 'season') {
                    $form.find('[name="permitir_inscricao_logada"]').prop('checked', true);
                    $form.find('[name="limite_inscricoes_periodo"]').val('1');
                    $form.find('[name="limite_inscricoes_adicionais"]').val('3');
                }
                if (type === 'season') {
                    const allowMultiple = $form.find('[name="permitir_multiplas_inscricoes_modalidade"]').is(':checked');
                    $form.find('[data-season-multiple-fields="1"]').toggleClass('hidden', !allowMultiple).find('input').prop('required', allowMultiple);
                    updateSeasonNoticeFields($form);
                    updateSeasonRegistrationEnrollmentField($form);
                    validateCoursePeriodChronology($form);
                }
                $('#course-' + type + '-modal-title').text((record ? 'Editar ' : 'Criar ') + (type === 'season' ? 'temporada' : 'turma'));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function replacePanel(response, classFilterState) {
                if (response && response.professor_class_refresh) {
                    $(document).trigger('professor:classes-refresh', [response]);
                    return true;
                }
                if (!response || !response.html) return false;
                const $updatedPanel = $(String(response.html)).first();
                const sectionName = String($updatedPanel.attr('data-admin-section') || '');
                if (!sectionName) return false;
                $('[data-admin-section="' + sectionName + '"]').replaceWith($updatedPanel);
                const $browser = $updatedPanel.find('[data-admin-class-browser]').first();
                if (classFilterState && $browser.length) {
                    $browser.find('[data-class-season]').removeClass('is-active');
                    const $season = $browser.find('[data-class-season="' + String(classFilterState.seasonId || '') + '"]').first();
                    ($season.length ? $season : $browser.find('[data-class-season]').first()).addClass('is-active');
                    layoutClassFilterLine($browser.find('[data-class-filter-line="season"]'));
                    loadAdminClassBrowser($browser, '').done(function (filterResponse) {
                        if (!filterResponse || filterResponse.success === false) return;
                        const groupId = String(classFilterState.groupId || '');
                        const $group = $browser.find('[data-class-group="' + groupId + '"]').first();
                        if (!$group.length) return;
                        $browser.find('[data-class-group]').removeClass('is-active');
                        $group.addClass('is-active');
                        loadAdminClassBrowser($browser, groupId);
                    });
                } else {
                    initializeAdminClassBrowsers($updatedPanel);
                }
                return true;
            }

            function layoutClassFilterLine($line) {
                if (!$line || !$line.length || $line.hasClass('hidden')) return;
                const $options = $line.find('[data-class-filter-options]').first();
                const $more = $line.find('[data-class-filter-more]').first();
                const firstButton = $options.find('.admin-class-filter-button:visible').get(0);
                if (!firstButton) { $more.addClass('hidden'); return; }
                const expanded = $line.hasClass('is-expanded');
                $more.addClass('hidden');
                const optionsElement = $options.get(0);
                const needsMore = optionsElement.scrollWidth > optionsElement.clientWidth + 2;
                $more.toggleClass('hidden', !needsMore);
                $options.toggleClass('is-expanded', expanded && needsMore);
                $more.text(expanded ? 'Menos...' : 'Mais...').attr('aria-expanded', expanded ? 'true' : 'false');
            }

            function loadAdminClassBrowser($browser, groupId) {
                const seasonId = String($browser.find('[data-class-season].is-active').attr('data-class-season') || '');
                const url = String($browser.attr('data-class-filter-url') || '');
                const view = String($browser.attr('data-class-management-view') || 'turmas');
                const selectedGroupId = String(groupId || '');
                if (!seasonId || !url) return;
                $browser.addClass('is-loading');
                return $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json',
                    data: { tipo: view, temporada_id: seasonId, grupo_id: selectedGroupId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar as turmas.'));
                        return;
                    }
                    if (!selectedGroupId) {
                        $browser.find('[data-class-filter-options="group"]').html(String(response.groups_html || ''));
                        $browser.find('[data-class-group-line]').removeClass('is-expanded');
                    }
                    $browser.find('[data-class-results]').html(String(response.classes_html || ''));
                    const count = Number(response.count || 0);
                    $browser.find('[data-class-result-summary]').text(selectedGroupId ? (count === 1 ? '1 turma encontrada.' : count + ' turmas encontradas.') : '');
                    window.requestAnimationFrame(function () {
                        layoutClassFilterLine($browser.find('[data-class-filter-line="season"]'));
                        layoutClassFilterLine($browser.find('[data-class-group-line]'));
                    });
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $browser.removeClass('is-loading');
                });
            }

            function initializeAdminClassBrowsers($context) {
                const $browsers = $context && $context.is && $context.is('[data-admin-class-browser]') ? $context : ($context || $(document)).find('[data-admin-class-browser]');
                $browsers.not('[data-professor-course-controls-source] [data-admin-class-browser]').each(function () {
                    const $browser = $(this);
                    layoutClassFilterLine($browser.find('[data-class-filter-line="season"]'));
                    const $activeSeason = $browser.find('[data-class-season].is-active').first();
                    if ($activeSeason.length) loadAdminClassBrowser($browser, '');
                });
            }
            App.admin.initializeAdminClassBrowsers = initializeAdminClassBrowsers;

            $(document).on('click', '[data-admin-class-browser] [data-class-season]', function () {
                const $browser = $(this).closest('[data-admin-class-browser]');
                $browser.find('[data-class-season]').removeClass('is-active');
                $(this).addClass('is-active');
                $browser.find('[data-class-filter-options="group"]').html('<span class="muted">Carregando...</span>');
                $browser.find('[data-class-results]').html('<p class="muted">Selecione uma opção para consultar as turmas.</p>');
                $browser.find('[data-class-result-summary]').text('');
                loadAdminClassBrowser($browser, '');
            });
            $(document).on('click', '[data-admin-class-browser] [data-class-group]', function () {
                const $browser = $(this).closest('[data-admin-class-browser]');
                $(this).closest('[data-class-group-line]').find('[data-class-group]').removeClass('is-active');
                $(this).addClass('is-active');
                loadAdminClassBrowser($browser, String($(this).attr('data-class-group') || ''));
            });
            $(document).on('click', '[data-admin-class-browser] [data-class-filter-more]', function () {
                const $line = $(this).closest('.admin-class-filter-line').toggleClass('is-expanded');
                layoutClassFilterLine($line);
            });
            $(window).off('resize.adminClassBrowser').on('resize.adminClassBrowser', function () {
                window.clearTimeout(App.state.adminClassBrowserResizeTimer);
                App.state.adminClassBrowserResizeTimer = window.setTimeout(function () { $('[data-admin-class-browser]').each(function () { layoutClassFilterLine($(this).find('[data-class-filter-line="season"]')); layoutClassFilterLine($(this).find('[data-class-group-line]:not(.hidden)')); }); }, 120);
            });
            $(document).on('click', '[data-course-create]', function () {
                openModal(String($(this).attr('data-course-create') || ''), null);
            });

            $(document).on('click', '[data-course-edit]', function () {
                let record = {};
                try { record = JSON.parse(String($(this).attr('data-course-record') || '{}')); } catch (error) { record = {}; }
                openModal(String($(this).attr('data-course-edit') || ''), record);
            });
            function seasonSummaryValue(value) {
                const text = String(value == null || value === '' ? '' : value);
                return text === '' ? 'Não informado' : text;
            }
            function seasonSummaryDate(value) {
                return formatBrazilianDate(String(value || '')) || 'Não informado';
            }
            function seasonSummaryYesNo(value) {
                return Number(value || 0) === 1 ? 'Sim' : 'Não';
            }
            function renderSeasonSummary(record) {
                const hasNotice = Number(record.possui_edital || 0) === 1;
                const allowMultiple = Number(record.permitir_multiplas_inscricoes_modalidade || 0) === 1;
                const rows = [
                    ['Instituição gestora', record.origem_temporada],
                    ['Periodicidade', record.tipo_periodicidade],
                    ['Status', record.status],
                    ['Início da publicação', seasonSummaryDate(record.data_inicio)],
                    ['Fim da publicação', seasonSummaryDate(record.data_fim)],
                    ['Inscrição inicial', seasonSummaryDate(record.inscricoes_inicio) + ' até ' + seasonSummaryDate(record.inscricoes_fim)],
                    ['Matrículas', seasonSummaryDate(record.matriculas_inicio) + ' até ' + seasonSummaryDate(record.matriculas_fim)],
                    ['Aceita inscrições durante as matrículas', seasonSummaryYesNo(record.permitir_inscricao_periodo_matricula)],
                    ['Inscrições abertas', seasonSummaryDate(record.inscricoes_abertas_inicio) + ' até ' + seasonSummaryDate(record.inscricoes_abertas_fim)],
                    ['Aulas', seasonSummaryDate(record.aulas_inicio) + ' até ' + seasonSummaryDate(record.aulas_fim)],
                    ['Possui edital', hasNotice ? 'Sim — nº ' + seasonSummaryValue(record.numero_edital) : 'Não'],
                    ['Link do edital', hasNotice ? seasonSummaryValue(record.link_edital) : 'Não se aplica'],
                    ['Inscrição com usuário autenticado', seasonSummaryYesNo(record.permitir_inscricao_logada)],
                    ['Inscrição somente por CPF', seasonSummaryYesNo(record.permitir_inscricao_por_cpf)],
                    ['Limite inicial por CPF', seasonSummaryValue(record.limite_inscricoes_periodo)],
                    ['Liberação da segunda inscrição', seasonSummaryDate(record.data_liberacao_segunda_inscricao)],
                    ['Liberação da terceira ou demais inscrições', seasonSummaryDate(record.data_liberacao_inscricoes_adicionais)],
                    ['Limite após a última liberação', seasonSummaryValue(record.limite_inscricoes_adicionais)],
                    ['Mais de uma inscrição na mesma modalidade', allowMultiple ? 'Sim — máximo de ' + seasonSummaryValue(record.limite_inscricoes_modalidade) : 'Não'],
                    ['Liberação adicional na mesma modalidade', allowMultiple ? seasonSummaryDate(record.data_liberacao_multiplas_inscricoes_modalidade) : 'Não se aplica']
                ];
                $('#course-season-summary-title').text(String(record.nome || 'Resumo da temporada'));
                $('#course-season-summary-subtitle').text('Características e regras cadastradas para esta temporada.');
                $('#course-season-summary-body').html(rows.map(function (row) {
                    return '<div class="course-season-summary-item"><strong>' + App.core.escapeHtml(String(row[0])) + '</strong><span>' + App.core.escapeHtml(seasonSummaryValue(row[1])) + '</span></div>';
                }).join(''));
            }
            $(document).on('click', '[data-course-season-summary]', function () {
                let record = {};
                try { record = JSON.parse(String($(this).attr('data-course-season-summary') || '{}')); } catch (error) { record = {}; }
                renderSeasonSummary(record);
                $('#course-season-summary-modal').removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-course-season-summary-close="1"], #course-season-summary-modal', function (event) {
                if ($(event.target).is('#course-season-summary-modal') || $(event.target).is('[data-course-season-summary-close="1"]')) {
                    $('#course-season-summary-modal').addClass('hidden').attr('aria-hidden', 'true');
                }
            });
            $(document).on('click', '[data-course-season-status]', function () {
                const $button = $(this);
                const action = String($button.attr('data-course-season-status') || '');
                const id = Number($button.attr('data-course-season-id') || 0);
                const name = String($button.attr('data-course-season-name') || 'esta temporada');
                const confirmation = action === 'suspender'
                    ? 'Deseja suspender a temporada "' + name + '"? Enquanto estiver suspensa, suas turmas não serão publicadas e não aceitarão inscrições.'
                    : 'Deseja reativar a temporada "' + name + '"? O status voltará a ser definido automaticamente pelos cronogramas.';
                if (id <= 0 || !window.confirm(confirmation)) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/temporadas/status'),
                    method: 'POST', dataType: 'json', data: { temporada_id: id, acao: action },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível alterar o status da temporada.'));
                        return;
                    }
                    replacePanel(response);
                    App.core.abrirPopup('sucesso', String(response.message || 'Status da temporada alterado com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });
            $(document).on('click', '[data-course-season-delete]', function () {
                const $button = $(this);
                const id = Number($button.attr('data-course-season-delete') || 0);
                const name = String($button.attr('data-course-season-name') || 'esta temporada');
                if (id <= 0 || !window.confirm('Deseja realmente excluir a temporada "' + name + '"? Esta ação não poderá ser desfeita.')) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: App.core.buildUrl('/admin/temporadas/excluir'),
                    method: 'POST', dataType: 'json', data: { temporada_id: id },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir a temporada.')); return; }
                    replacePanel(response);
                    App.core.abrirPopup('sucesso', String(response.message || 'Temporada excluída com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $button.prop('disabled', false); });
            });
            $(document).on('change', '[data-course-form="class"] [name="temporada_id"], [data-course-form="class"] [name="modalidade_id"]', function () {
                filterClassSchedules($(this).closest('form'), '');
            });
            $(document).on('change', '[data-course-form="class"] [name="cronograma_modalidade_id"]', function () {
                renderClassScheduleCatalog($(this).closest('form'));
            });
            $(document).on('click', '[data-class-schedule-option]', function () {
                const $form = $(this).closest('form');
                $form.find('[name="cronograma_modalidade_id"]').val(String($(this).attr('data-class-schedule-option') || '')).trigger('change');
            });
            $(document).on('change', '[data-season-multiple-toggle="1"]', function () {
                const enabled = $(this).is(':checked');
                $(this).closest('form').find('[data-season-multiple-fields="1"]').toggleClass('hidden', !enabled).find('input').prop('required', enabled);
            });

            $(document).on('click', '[data-course-modal-close="1"]', closeModals);
            $(document).on('click', '[data-course-professor-close="1"]', closeModals);
            $(document).on('click', '[data-course-assign-professor]', function () {
                const $modal = $('#course-professor-modal');
                $modal.find('[data-course-team-search]').val('');
                $modal.find('[data-course-team-options] label').removeClass('hidden');
                $modal.find('[name="turma_id"]').val(String($(this).attr('data-course-assign-professor') || ''));
                let professorIds = [];
                let internIds = [];
                const mainProfessorId = String($(this).attr('data-course-main-professor') || '');
                try { professorIds = JSON.parse(String($(this).attr('data-course-current-professors') || '[]')).map(String); } catch (error) { professorIds = []; }
                try { internIds = JSON.parse(String($(this).attr('data-course-current-interns') || '[]')).map(String); } catch (error) { internIds = []; }
                $modal.find('[name="professor_principal_conta_id"]').prop('checked', false).filter('[value="' + mainProfessorId + '"]').prop('checked', true);
                $modal.find('[name="professor_auxiliar_conta_ids[]"]').each(function () {
                    const id = String($(this).val());
                    $(this).prop('checked', id !== mainProfessorId && professorIds.indexOf(id) !== -1).prop('disabled', id === mainProfessorId);
                });
                $modal.find('[name="estagiario_conta_ids[]"]').each(function () { $(this).prop('checked', internIds.indexOf(String($(this).val())) !== -1); });
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('input', '[data-course-team-search]', function () {
                const query = String($(this).val() || '').trim().toLocaleLowerCase('pt-BR');
                $(this).siblings('[data-course-team-options]').find('label').each(function () {
                    const name = String($(this).find('span').text() || '').toLocaleLowerCase('pt-BR');
                    $(this).toggleClass('hidden', query !== '' && name.indexOf(query) === -1);
                });
            });
            $(document).on('submit', '[data-course-professor-form="1"]', function (event) {
                event.preventDefault();
                const $form = $(this);
                if ($form.find('[name="professor_principal_conta_id"]:checked').length === 0) {
                    App.core.abrirPopup('erro', 'Eleja o professor principal da turma.');
                    return;
                }
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                const classId = String($form.find('[name="turma_id"]').val() || '');
                const $mainProfessor = $form.find('[name="professor_principal_conta_id"]:checked');
                const mainProfessorId = String($mainProfessor.val() || '');
                const mainProfessorName = $.trim($mainProfessor.closest('label').find('span').text());
                const auxiliaryProfessorIds = [];
                const auxiliaryProfessorNames = [];
                const internIds = [];
                const internNames = [];
                $form.find('[name="professor_auxiliar_conta_ids[]"]:checked').each(function () {
                    auxiliaryProfessorIds.push(String($(this).val() || ''));
                    auxiliaryProfessorNames.push($.trim($(this).closest('label').find('span').text()));
                });
                $form.find('[name="estagiario_conta_ids[]"]:checked').each(function () {
                    internIds.push(String($(this).val() || ''));
                    internNames.push($.trim($(this).closest('label').find('span').text()));
                });
                $.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', data: $form.serialize() })
                    .done(function (response) {
                        if (!response || !response.success) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível atribuir o professor.')); return; }
                        const professorIds = [mainProfessorId].concat(auxiliaryProfessorIds).filter(Boolean);
                        const $card = $('[data-course-class-card="' + classId + '"]').first();
                        $card.find('[data-course-class-main-professor-name]').text(mainProfessorName || 'Sem professor principal');
                        const $teamButton = $card.find('[data-course-assign-professor]').first()
                            .attr('data-course-main-professor', mainProfessorId)
                            .attr('data-course-current-professors', JSON.stringify(professorIds))
                            .attr('data-course-current-interns', JSON.stringify(internIds));
                        $card.find('[data-course-class-details], [data-course-edit="class"]').each(function () {
                            const attribute = $(this).is('[data-course-class-details]') ? 'data-course-class-details' : 'data-course-record';
                            let record = {};
                            try { record = JSON.parse(String($(this).attr(attribute) || '{}')); } catch (error) { record = {}; }
                            record.professor_conta_id = mainProfessorId;
                            record.professor_principal_nome = mainProfessorName;
                            record.professores_ids = professorIds;
                            record.professores_auxiliares_nomes = auxiliaryProfessorNames.join(', ');
                            record.estagiarios_ids = internIds;
                            record.estagiarios_nomes = internNames.join(', ');
                            $(this).attr(attribute, JSON.stringify(record));
                        });
                        closeModals();
                        $teamButton.trigger('blur');
                    })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });
            $(document).on('change', '[name="professor_principal_conta_id"]', function () {
                const mainId = String($(this).val() || '');
                const $form = $(this).closest('form');
                $form.find('[name="professor_auxiliar_conta_ids[]"]').each(function () {
                    const isMain = String($(this).val()) === mainId;
                    if (isMain) $(this).prop('checked', false);
                    $(this).prop('disabled', isMain);
                });
            });
            $(document).on('change', '[data-season-notice-toggle="1"]', function () { updateSeasonNoticeFields($(this).closest('form')); });
            $(document).on('change', '[data-class-age-exception-toggle]', function () { updateClassAgeExceptionFields($(this).closest('form')); });
            $(document).on('change', '[data-season-registration-enrollment-toggle="1"], [data-course-form="season"] [name="matriculas_inicio"], [data-course-form="season"] [name="matriculas_fim"]', function () { updateSeasonRegistrationEnrollmentField($(this).closest('form')); });
            $(document).on('input change', '[data-course-form="season"] [name="data_inicio"], [data-course-form="season"] [name="data_fim"], [data-course-form="season"] [name="inscricoes_inicio"], [data-course-form="season"] [name="inscricoes_fim"], [data-course-form="season"] [name="matriculas_inicio"], [data-course-form="season"] [name="matriculas_fim"], [data-course-form="season"] [name="abrangencia_semanal"], [data-course-form="season"] [name="inscricoes_abertas_inicio"], [data-course-form="season"] [name="inscricoes_abertas_fim"], [data-course-form="season"] [name="aulas_inicio"], [data-course-form="season"] [name="aulas_fim"]', function () { validateCoursePeriodChronology($(this).closest('form')); });
            $(document).on('click', '[data-season-field-help]', function (event) {
                event.preventDefault(); event.stopPropagation();
                const name = String($(this).attr('data-season-field-help') || '');
                App.core.abrirPopup('sucesso', seasonFieldHelp[name] || 'Informação não disponível para este campo.');
                $('#popup-titulo').text('Ajuda sobre o campo');
            });
            $(document).on('click', '#course-season-modal, #course-class-modal, #course-class-status-modal', function (event) { if (event.target === this) closeModals(); });

            $(document).on('click', '[data-course-class-status-open="1"]', function () {
                const $button = $(this);
                const $modal = $('#course-class-status-modal');
                const $form = $modal.find('[data-course-class-status-form="1"]');
                const currentStatus = String($button.attr('data-course-class-status') || 'planejada');
                const scheduleStatus = String($button.attr('data-course-class-schedule-status') || 'planejada');
                const suspended = currentStatus === 'inscricoes_suspensas';
                const $notice = $form.find('[data-course-class-status-notice="1"]');
                $form.find('[name="turma_id"]').val(String($button.attr('data-course-class-id') || ''));
                $form.find('[name="course_management_view"]').val(String($button.attr('data-course-management-view') || 'turmas'));
                $form.find('[name="status"]').prop({ checked: false, disabled: true });
                $form.find('[name="status"][value="' + currentStatus + '"]').prop('checked', true);
                if (suspended) {
                    $form.find('[name="status"][value="' + scheduleStatus + '"]').prop('disabled', false);
                } else {
                    $form.find('[name="status"][value="inscricoes_suspensas"]').prop('disabled', false);
                }
                $form.find('[data-course-status-option]').each(function () {
                    $(this).toggleClass('is-disabled', $(this).find('input').prop('disabled'));
                });
                if (scheduleStatus === 'periodo_matricula') {
                    $notice.removeClass('hidden').text(suspended
                        ? 'A turma está suspensa durante o período de matrícula. Neste momento, somente é possível retomá-la no status “Em período de matrícula”.'
                        : 'A turma está no período de matrícula. O status definido pelo cronograma não pode ser alterado; somente a suspensão das inscrições está disponível.');
                } else {
                    $notice.removeClass('hidden').text(suspended
                        ? 'A turma está suspensa. Para retomá-la, somente o status atualmente definido pelo cronograma pode ser selecionado.'
                        : 'Os status da turma são definidos automaticamente pelo cronograma. A única alteração manual disponível é suspender as inscrições.');
                }
                $form.find('button[type="submit"]').prop('disabled', true);
                $('#course-class-status-subtitle').text(String($button.attr('data-course-class-name') || 'Turma selecionada'));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });

            $(document).on('change', '[data-course-class-status-form="1"] [name="status"]', function () {
                const $form = $(this).closest('form');
                $form.find('button[type="submit"]').prop('disabled', $(this).prop('disabled'));
            });

            $(document).on('click', '[data-course-class-status-close="1"]', closeModals);

            $(document).on('click', '[data-course-class-delete]', function () {
                const $button = $(this);
                const classId = String($button.attr('data-course-class-delete') || '');
                const className = String($button.attr('data-course-class-name') || 'esta turma');
                if (!window.confirm('Deseja realmente excluir a turma “' + className + '”?')) return;
                const base = String($('[data-admin-section-host]').data('adminBasePath') || '/admin');
                const endpoint = base === '/professor' ? '/professor/minhas-turmas/excluir' : '/admin/turmas/excluir';
                $button.prop('disabled', true);
                $.ajax({ url: App.core.buildUrl(endpoint), method: 'POST', dataType: 'json', data: { turma_id: classId } })
                    .done(function (response) {
                        if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível excluir a turma.')); return; }
                        const $card = $button.closest('[data-course-class-card]');
                        $card.fadeOut(160, function () { $(this).remove(); });
                        App.core.abrirPopup('sucesso', String(response.message || 'Turma excluída com sucesso.'));
                    })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $button.prop('disabled', false); });
            });

            function classDetailDate(value) {
                const raw = String(value || '').trim();
                if (!raw) return 'Não informado';
                const parts = raw.slice(0, 10).split('-');
                const date = parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : raw;
                return raw.length >= 16 ? date + ' às ' + raw.slice(11, 16) : date;
            }

            $(document).on('click', '[data-course-class-details]', function () {
                let record = {};
                try { record = JSON.parse(String($(this).attr('data-course-class-details') || '{}')); } catch (error) { record = {}; }
                const escape = function (value) { return $('<div>').text(String(value == null || value === '' ? 'Não informado' : value)).html(); };
                const range = function (start, end) { return classDetailDate(start) + ' — ' + classDetailDate(end); };
                const $modal = $('#course-class-details-modal');
                const classInformation = String(record.observacao || '').trim();
                const informationSection = classInformation !== ''
                    ? '<section class="course-class-information-highlight"><strong>Informação da turma</strong><p>' + escape(classInformation) + '</p></section>'
                    : '';
                $modal.find('[data-course-details-subtitle]').text('[' + String(record.id || '') + '] ' + String(record.nome || 'Turma'));
                $modal.find('[data-course-details-content]').html(
                    informationSection +
                    '<section><strong>Nível e equipe</strong>' +
                    '<p><b>Programa:</b> ' + escape(record.programa || 'Sem programa definido') + '</p>' +
                    '<p><b>Níveis aceitos:</b> ' + escape(record.niveis_aceitos_descricao || 'Sem limitação de nível') + '</p>' +
                    '<p><b>Professor principal:</b> ' + escape(record.professor_principal_nome || 'Sem professor principal') + '</p>' +
                    '<p><b>Professores auxiliares:</b> ' + escape(record.professores_auxiliares_nomes || 'Sem professor auxiliar') + '</p>' +
                    '<p><b>Estagiários:</b> ' + escape(record.estagiarios_nomes || 'Sem estagiário') + '</p></section>' +
                    '<section><strong>Cronograma resumido</strong>' +
                    '<p><b>Inscrição inicial:</b> ' + escape(range(record.cronograma_inscricoes_inicio, record.cronograma_inscricoes_fim)) + '</p>' +
                    '<p><b>Matrícula:</b> ' + escape(range(record.cronograma_matriculas_inicio, record.cronograma_matriculas_fim)) + '</p>' +
                    '<p><b>Inscrições abertas:</b> ' + escape(range(record.cronograma_inscricoes_abertas_inicio, record.cronograma_inscricoes_abertas_fim)) + '</p>' +
                    '<p><b>Aulas:</b> ' + escape(range(record.aulas_inicio || record.cronograma_data_inicio, record.aulas_fim || record.cronograma_data_fim)) + '</p></section>' +
                    '<section><strong>Quantidades de vagas</strong>' +
                    '<p><b>Total:</b> ' + escape(record.vagas_totais || 0) + ' · <b>Geral:</b> ' + escape(record.vagas_geral || 0) + ' · <b>PCD:</b> ' + escape(record.vagas_pcd || 0) + ' · <b>PLM:</b> ' + escape(record.vagas_plm || 0) + ' · <b>PVS:</b> ' + escape(record.vagas_pvs || 0) + '</p>' +
                    '<p><b>Lista de espera:</b> Geral ' + escape(record.vagas_espera_geral || 0) + ' · PCD ' + escape(record.vagas_espera_pcd || 0) + ' · PLM ' + escape(record.vagas_espera_plm || 0) + ' · PVS ' + escape(record.vagas_espera_pvs || 0) + '</p></section>'
                );
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-course-details-close="1"]', function () { $('#course-class-details-modal').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#course-class-details-modal', function (event) { if (event.target === this) $(this).addClass('hidden').attr('aria-hidden', 'true'); });

            function classAttendanceEndpoint() {
                const base = String($('[data-admin-section-host]').data('adminBasePath') || '/admin');
                return App.core.buildUrl(base === '/professor' ? '/professor/minhas-turmas/chamada' : '/admin/turmas/chamada');
            }

            function confirmClassAttendanceExit(message) {
                return window.confirm(message);
            }

            function closeClassAttendance() {
                if (!confirmClassAttendanceExit('Deseja sair do calendário de chamada?')) return;
                if (App.state.courseClassAttendanceCalendar && typeof App.state.courseClassAttendanceCalendar.destroy === 'function') App.state.courseClassAttendanceCalendar.destroy();
                App.state.courseClassAttendanceCalendar = null;
                $('#course-class-attendance-modal').addClass('hidden').attr('aria-hidden', 'true');
                $('#course-class-attendance-roster-modal').addClass('hidden').attr('aria-hidden', 'true').find('[data-class-attendance-roster]').empty();
            }

            function closeClassAttendanceRoster() {
                if (!confirmClassAttendanceExit('Deseja sair da lista para fazer chamada?')) return;
                $('#course-class-attendance-roster-modal').addClass('hidden').attr('aria-hidden', 'true').find('[data-class-attendance-roster]').empty();
            }

            function loadClassAttendanceRoster(classId, date) {
                const $rosterModal = $('#course-class-attendance-roster-modal').attr('data-class-id', classId).removeClass('hidden').attr('aria-hidden', 'false').scrollTop(0);
                $rosterModal.find('.popup-card').scrollTop(0);
                const $roster = $rosterModal.find('[data-class-attendance-roster]').html('<p class="muted">Carregando lista de chamada...</p>');
                $.ajax({ url: classAttendanceEndpoint(), method: 'GET', dataType: 'json', data: { turma_id: classId, data: date }, suppressGlobalLoading: true })
                    .done(function (response) { if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível carregar a chamada.')); return; } $roster.html(String(response.html || '')); })
                    .fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); });
            }

            function placeClassAttendanceModal(selector, $context) {
                let $candidate = $context && $context.length ? $context.find(selector).last() : $();
                if (!$candidate.length) $candidate = $('body > ' + selector).last();
                if (!$candidate.length) $candidate = $(selector).last();
                if (!$candidate.length) return $();

                $candidate.detach();
                $(selector).remove();
                return $candidate.appendTo(document.body);
            }

            function prepareClassAttendanceModals($trigger) {
                const $context = $trigger.closest('[data-class-results], [data-professor-class-results]');
                const selectors = [
                    '#course-class-attendance-modal',
                    '#course-class-attendance-roster-modal',
                    '#course-class-justification-modal',
                    '#class-enrollment-action-modal'
                ];
                const modals = {};
                selectors.forEach(function (selector) {
                    modals[selector] = placeClassAttendanceModal(selector, $context);
                });
                return modals;
            }

            const classAttendanceAdultAbsenceReasons = ['Acompanhamento de familiar', 'Afastamento temporário', 'Atividade ou compromisso oficial', 'Compromisso de trabalho', 'Compromisso escolar ou acadêmico', 'Condições climáticas', 'Consulta médica ou odontológica', 'Exame médico', 'Falecimento ou emergência familiar', 'Outro motivo', 'Problema de saúde', 'Problema de transporte', 'Tratamento ou fisioterapia', 'Viagem'];
            const classAttendanceMinorAbsenceReasons = ['Afastamento temporário', 'Compromisso escolar', 'Condições climáticas', 'Consulta médica ou odontológica', 'Emergência familiar', 'Exame, tratamento ou terapia', 'Falta de acompanhante responsável', 'Falecimento na família', 'Guarda ou convivência familiar', 'Orientação dos pais ou responsáveis', 'Outro motivo', 'Passeio ou evento escolar', 'Problema de saúde do menor', 'Problema de transporte', 'Prova ou atividade extracurricular', 'Responsável impossibilitado de levar ou buscar', 'Viagem familiar'];

            function isClassAttendanceMinor(birthDate, referenceDate) {
                const birth = new Date(String(birthDate || '') + 'T12:00:00');
                const reference = referenceDate ? new Date(String(referenceDate).slice(0, 10) + 'T12:00:00') : new Date();
                if (Number.isNaN(birth.getTime())) return false;
                let age = reference.getFullYear() - birth.getFullYear();
                if (reference.getMonth() < birth.getMonth() || (reference.getMonth() === birth.getMonth() && reference.getDate() < birth.getDate())) age--;
                return age < 18;
            }

            function prepareClassAttendanceJustificationFields($form, birthDate, referenceDate, currentReason) {
                const reasons = isClassAttendanceMinor(birthDate, referenceDate) ? classAttendanceMinorAbsenceReasons : classAttendanceAdultAbsenceReasons;
                const reason = String(currentReason || '').trim();
                const isKnown = reasons.indexOf(reason) !== -1;
                const $select = $form.find('[data-justification-reason-select]');
                $select.html('<option value="">Selecione o motivo</option>' + reasons.map(function (item) { return $('<option>').val(item).text(item)[0].outerHTML; }).join(''));
                $select.val(reason === '' ? '' : (isKnown ? reason : 'Outro motivo'));
                $form.find('[data-justification-other-wrap]').toggleClass('hidden', $select.val() !== 'Outro motivo');
                $form.find('[data-justification-other]').val(isKnown ? '' : reason).prop('required', $select.val() === 'Outro motivo');
            }

            function selectedClassAttendanceJustificationReason($form) {
                const selected = String($form.find('[data-justification-reason-select]').val() || '').trim();
                return selected === 'Outro motivo' ? String($form.find('[data-justification-other]').val() || '').trim() : selected;
            }

            $(document).on('click', '[data-course-class-attendance]', function () {
                let record = {};
                try { record = JSON.parse(String($(this).attr('data-course-class-attendance') || '{}')); } catch (error) { record = {}; }
                const weekdays = String(record.dias_semana || '').split(',').map(Number).filter(Boolean);
                const modals = prepareClassAttendanceModals($(this));
                const $modal = modals['#course-class-attendance-modal'];
                if (!$modal.length) {
                    App.core.abrirPopup('erro', 'O modal da chamada não está disponível nesta tela.');
                    return;
                }
                $modal.attr('data-class-id', String(record.id || '')).attr('data-class-weekdays', weekdays.join(','));
                const schedule = String(record.dias_semana_descricao || 'dias não informados') + (record.hora_inicio && record.hora_fim ? ', ' + String(record.hora_inicio).slice(0, 5) + ' às ' + String(record.hora_fim).slice(0, 5) : '');
                $modal.find('[data-class-attendance-subtitle]').text('[' + String(record.id || '') + '] ' + String(record.nome || 'Turma') + ' · ' + schedule);
                $modal.removeClass('hidden').attr('aria-hidden', 'false').scrollTop(0);
                $modal.find('.popup-card').scrollTop(0);
                const element = document.getElementById('course-class-attendance-calendar');
                if (!element || typeof FullCalendar === 'undefined') { App.core.abrirPopup('erro', 'O calendário não pôde ser carregado.'); return; }
                if (App.state.courseClassAttendanceCalendar) App.state.courseClassAttendanceCalendar.destroy();
                const classStart = String(record.aulas_inicio || record.cronograma_data_inicio || record.temporada_inicio || '').slice(0, 10);
                const classEnd = String(record.aulas_fim || record.cronograma_data_fim || record.temporada_fim || '').slice(0, 10);
                App.state.courseClassAttendanceCalendar = new FullCalendar.Calendar(element, {
                    locale: 'pt-br', initialView: 'dayGridMonth', height: 'auto',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                    // Temporariamente, datas futuras permanecem liberadas para testes da chamada.
                    dayCellClassNames: function (info) { const iso = info.date.getDay() === 0 ? 7 : info.date.getDay(), date = info.date.getFullYear() + '-' + String(info.date.getMonth() + 1).padStart(2, '0') + '-' + String(info.date.getDate()).padStart(2, '0'), outsidePeriod = (classStart && date < classStart) || (classEnd && date > classEnd); return (weekdays.indexOf(iso) === -1 || outsidePeriod) ? ['class-attendance-day-disabled'] : []; },
                    dateClick: function (info) {
                        const iso = info.date.getDay() === 0 ? 7 : info.date.getDay();
                        if ((classStart && info.dateStr < classStart) || (classEnd && info.dateStr > classEnd)) { window.alert('Esta data está fora do período de aulas da turma.'); return; }
                        if (weekdays.indexOf(iso) === -1) { window.alert('Esta turma não possui aula neste dia da semana. Selecione um dos dias de aula informados no card.'); return; }
                        loadClassAttendanceRoster(String(record.id || ''), String(info.dateStr || '').slice(0, 10));
                    }
                });
                App.state.courseClassAttendanceCalendar.render();
            });
            $(document).on('click', '[data-class-attendance-close="1"]', closeClassAttendance);
            $(document).on('click', '#course-class-attendance-modal', function (event) { if (event.target === this) closeClassAttendance(); });
            $(document).on('click', '[data-class-attendance-roster-close="1"]', closeClassAttendanceRoster);
            $(document).on('click', '#course-class-attendance-roster-modal', function (event) { if (event.target === this) closeClassAttendanceRoster(); });
            $(document).on('keydown', function (event) {
                const $validationModal = $('#admin-health-certificate-validation-modal');
                if (event.key !== 'Escape' || ($validationModal.length && !$validationModal.hasClass('hidden'))) return;
                if (!$('#course-class-attendance-roster-modal').hasClass('hidden')) { closeClassAttendanceRoster(); return; }
                if (!$('#course-class-attendance-modal').hasClass('hidden')) closeClassAttendance();
            });

            function saveClassAttendanceStatus($input, status, justification) {
                const $row = $input.closest('[data-class-attendance-row]');
                $row.find('[data-class-attendance-status]').not($input).prop('checked', false);
                $input.prop('checked', true).attr('data-current-justification', justification || '');
                $row.find('[data-class-attendance-status]').prop('disabled', true);
                $.ajax({ url: classAttendanceEndpoint(), method: 'POST', dataType: 'json', data: { turma_id: $('#course-class-attendance-roster-modal').attr('data-class-id'), inscricao_id: $input.attr('data-enrollment-id'), data: $row.closest('[data-class-attendance-roster]').find('[data-class-attendance-date]').attr('data-class-attendance-date'), status: status, justificativa: justification || '' }, suppressGlobalLoading: true })
                    .done(function () { const marks={presente:'✓',ausente:'×',justificado:'J'}; $row.find('[data-class-attendance-mark]').attr('class','class-attendance-mark is-'+status).text(marks[status] || '−'); })
                    .fail(function (xhr) { $input.prop('checked', false); App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $row.find('[data-class-attendance-status]').prop('disabled', false); });
            }

            function openClassAttendanceJustification($input) {
                const $row = $input.closest('[data-class-attendance-row]');
                $input.prop('checked', false);
                let $modal = $('body > #course-class-justification-modal').last();
                if (!$modal.length) {
                    const $context = $input.closest('[data-class-results], [data-professor-class-results]');
                    $modal = placeClassAttendanceModal('#course-class-justification-modal', $context);
                }
                if (!$modal.length) {
                    App.core.abrirPopup('erro', 'O modal de justificativa não está disponível nesta tela.');
                    return;
                }
                $modal = $modal.appendTo(document.body).data('attendanceInput', $input);
                const referenceDate = String($row.closest('[data-class-attendance-roster]').find('[data-class-attendance-date]').attr('data-class-attendance-date') || '');
                prepareClassAttendanceJustificationFields($modal.find('form'), String($input.attr('data-birth-date') || ''), referenceDate, String($input.attr('data-current-justification') || ''));
                $modal.find('[data-class-justification-person]').text(String($input.attr('data-person-name') || ''));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
                window.setTimeout(function () { $modal.find('[data-justification-reason-select]').trigger('focus'); }, 0);
            }

            $(document).on('click', '[data-class-attendance-status="justificado"]', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openClassAttendanceJustification($(this));
            });

            $(document).on('change', '#course-class-justification-modal [data-justification-reason-select]', function () {
                const $form = $(this).closest('form');
                const isOther = String($(this).val() || '') === 'Outro motivo';
                $form.find('[data-justification-other-wrap]').toggleClass('hidden', !isOther);
                $form.find('[data-justification-other]').prop('required', isOther);
                if (isOther) $form.find('[data-justification-other]').trigger('focus');
            });

            $(document).on('change', '[data-class-attendance-status]', function () {
                const $input = $(this);
                const status = String($input.attr('data-class-attendance-status') || '');
                if (status === 'justificado') return;
                if (!$input.is(':checked')) { $input.prop('checked', true); return; }
                saveClassAttendanceStatus($input, status, '');
            });
            $(document).on('click', '[data-class-justification-close="1"]', function () { $('#course-class-justification-modal').removeData('attendanceInput').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('click', '#course-class-justification-modal', function (event) { if (event.target === this) $(this).removeData('attendanceInput').addClass('hidden').attr('aria-hidden', 'true'); });
            $(document).on('submit', '[data-class-justification-form="1"]', function (event) {
                event.preventDefault();
                const $form = $(this); const reason = selectedClassAttendanceJustificationReason($form);
                if (!reason) { App.core.abrirPopup('erro', 'Selecione ou informe o motivo da justificativa.'); return; }
                const $modal = $('#course-class-justification-modal'); const $input = $modal.data('attendanceInput');
                $modal.removeData('attendanceInput').addClass('hidden').attr('aria-hidden', 'true');
                if ($input && $input.length) saveClassAttendanceStatus($input, 'justificado', reason);
            });
            $(document).on('click', '[data-class-enrollment-action]', function () {
                const $button=$(this), action=String($button.attr('data-class-enrollment-action')||''), name=String($button.attr('data-person-name')||'aluno');
                const labels={suspensa:'suspender a matrícula de ',matriculada:'rematricular ',desistente:'marcar como desistente '};
                $('#class-enrollment-action-modal').data('sourceButton',$button).removeClass('hidden').attr('aria-hidden','false').find('[data-class-enrollment-action-question]').text('Deseja realmente '+(labels[action]||'alterar a matrícula de ')+name+'?');
            });
            $(document).on('click', '[data-missing-health-certificate="1"]', function () {
                const label = String($(this).attr('data-certificate-label') || 'selecionado');
                App.core.abrirPopup('erro', 'Não existe atestado ' + label + ' enviado para validar.');
                $('#popup-mensagem').appendTo(document.body).css('z-index', '2147483000');
            });
            $(document).on('click','[data-class-enrollment-action-close="1"]',function(){ $('#class-enrollment-action-modal').removeData('sourceButton').addClass('hidden').attr('aria-hidden','true'); });
            $(document).on('click','[data-class-enrollment-action-confirm="1"]',function(){
                const $modal=$('#class-enrollment-action-modal'), $button=$modal.data('sourceButton'); if (!$button||!$button.length) return;
                const $roster=$('#course-class-attendance-roster-modal [data-class-attendance-roster]'), date=String($roster.find('[data-class-attendance-date]').attr('data-class-attendance-date')||''), classId=String($('#course-class-attendance-roster-modal').attr('data-class-id')||'');
                const enrollmentId=String($button.attr('data-enrollment-id')||'');
                $(this).prop('disabled',true); $.ajax({url:classAttendanceEndpoint(),method:'POST',dataType:'json',data:{turma_id:classId,inscricao_id:enrollmentId,acao_matricula:$button.attr('data-class-enrollment-action'),data:date},suppressGlobalLoading:true}).done(function(response){
                    $modal.addClass('hidden').attr('aria-hidden','true');
                    const $current=$roster.find('[data-enrollment-id="'+enrollmentId+'"]').first().closest('.class-attendance-student, .class-attendance-suspended > div'), $parsed=$('<div>').html(String((response&&response.html)||'')), $replacement=$parsed.find('[data-enrollment-id="'+enrollmentId+'"]').first().closest('.class-attendance-student, .class-attendance-suspended > div');
                    const destinationClass=$replacement.closest('.class-attendance-list').length?'class-attendance-list':($replacement.closest('.class-attendance-absence-excluded').length?'class-attendance-absence-excluded':'class-attendance-suspended');
                    $current.remove();
                    if($replacement.length){let $destination=$roster.find('.'+destinationClass).first();if(!$destination.length){$roster.append($parsed.find('.'+destinationClass).first());}else{$destination.append($replacement);}}
                    $roster.find('.class-attendance-suspended').each(function(){if($(this).children('div').length===0)$(this).remove();});
                }).fail(function(xhr){App.core.abrirPopup('erro',App.core.extrairMensagemErroAjax(xhr).mensagem);}).always(function(){$modal.find('[data-class-enrollment-action-confirm="1"]').prop('disabled',false);});
            });

            $(document).on('submit', '[data-course-class-status-form="1"]', function (event) {
                event.preventDefault();
                const $form = $(this);
                const classId = String($form.find('[name="turma_id"]').val() || '');
                const $submit = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'), method: 'POST', dataType: 'json', data: $form.serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível alterar o status da turma.'));
                        return;
                    }
                    const $card = $('[data-course-class-card="' + classId + '"]').first();
                    $card.find('[data-course-class-status-label="1"]')
                        .attr('class', 'class-status-badge class-status-' + String(response.status || 'planejada'))
                        .text(String(response.status_label || 'Status atualizado'));
                    $card.find('[data-course-class-status-open="1"]')
                        .attr('data-course-class-status', String(response.status || ''))
                        .attr('data-course-class-schedule-status', String(response.status_cronograma || response.status || ''));
                    closeModals();
                    App.core.abrirPopup('sucesso', String(response.message || 'Status da turma alterado com sucesso.'));
                }).fail(function (xhr) {
                    if (App.auth && App.auth.tratarFalhaDeAcesso(xhr, function () { $form.trigger('submit'); }, '/admin')) return;
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $submit.prop('disabled', false); });
            });

            function saveCourseForm($form) {
                if (!$form.length || $form.attr('data-course-saving') === '1') return;
                const $currentClassBrowser = $form.closest('[data-admin-section]').find('[data-admin-class-browser]').first();
                const classFilterState = $form.is('[data-course-form="class"]') && $currentClassBrowser.length ? {
                    seasonId: String($currentClassBrowser.find('[data-class-season].is-active').attr('data-class-season') || ''),
                    groupId: String($currentClassBrowser.find('[data-class-group].is-active').attr('data-class-group') || '')
                } : null;
                function showFirstInvalid(message) {
                    const formElement = $form.get(0);
                    let firstInvalid = formElement.__appFirstInvalidField
                        || $form.find('.field-invalid').get(0)
                        || null;
                    // O pseudo-seletor :invalid não é suportado de forma segura
                    // por todas as versões do jQuery. Uma exceção aqui interrompia
                    // o clique antes da abertura do aviso, fazendo o botão parecer
                    // sem ação.
                    if (!firstInvalid) {
                        $form.find('input, select, textarea').each(function () {
                            if (firstInvalid || this.disabled || String(this.type || '').toLowerCase() === 'hidden' || $(this).closest('.hidden').length > 0) return;
                            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                                App.core.validarCampoInline(this, true);
                                firstInvalid = this;
                            }
                        });
                    }
                    if (firstInvalid) {
                        const $invalid = $(firstInvalid);
                        const $label = $invalid.closest('label');
                        const fieldName = String(
                            $label.children('span').first().clone().children().remove().end().text()
                                || $invalid.attr('aria-label')
                                || $invalid.attr('name')
                                || 'Campo'
                        ).trim();
                        $form.find('.field-invalid-container').removeClass('field-invalid-container');
                        $label.addClass('field-invalid-container');
                        message += ' Campo: ' + fieldName + '.';

                        // O aviso geral cobre o formulário enquanto está aberto.
                        // Ao fechá-lo, posiciona o campo dentro da rolagem do modal
                        // para que o destaque e a explicação inline fiquem visíveis.
                        App.core.abrirPopup('erro', message, function () {
                            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            window.setTimeout(function () {
                                try { firstInvalid.focus({ preventScroll: true }); } catch (error) { firstInvalid.focus(); }
                            }, 180);
                        });
                        return;
                    }
                    App.core.abrirPopup('erro', 'Não foi possível identificar o campo inválido. Feche o formulário, abra-o novamente e tente salvar.');
                }
                if ($form.is('[data-course-form="season"]') && !validateCoursePeriodChronology($form)) {
                    App.core.validarFormularioInline($form.get(0));
                    showFirstInvalid('Revise as datas da temporada destacadas no formulário antes de salvar.');
                    return;
                }
                // validarFormularioInline já reúne as regras required, formato,
                // limites nativos e mensagens remotas. Não chame checkValidity()
                // novamente aqui: ele dispara o listener global de `invalid`, que
                // pode limpar uma validade antiga durante a própria consulta e
                // devolver false quando nenhum campo continua inválido.
                if (!App.core.validarFormularioInline($form.get(0))) {
                    showFirstInvalid('Preencha ou corrija o campo destacado antes de salvar.');
                    return;
                }
                if (String($form.find('[name="operacao"]').val() || '') === 'editar' && Number($form.find('[name="id"]').val() || 0) <= 0) {
                    App.core.abrirPopup('erro', 'Não foi possível identificar o registro que será editado. Feche o modal e tente novamente.');
                    return;
                }
                $form.attr('data-course-saving', '1');
                const $button = $form.find('button[type="submit"]').prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'), method: 'POST', dataType: 'json', data: new FormData($form[0]),
                    processData: false, contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) { App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível salvar o registro.')); return; }
                    closeModals();
                    replacePanel(response, classFilterState);
                    App.core.abrirPopup('sucesso', String(response.message || 'Registro salvo com sucesso.'));
                }).fail(function (xhr) { App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem); })
                    .always(function () { $form.removeAttr('data-course-saving'); $button.prop('disabled', false); });
            }

            // O clique chama diretamente o salvamento. Assim, a atualização não
            // depende do submit nativo, que o navegador pode bloquear antes de o
            // manipulador AJAX receber o evento.
            $(document).on('click', '[data-course-form="season"] button[type="submit"], [data-course-form="class"] button[type="submit"]', function (event) {
                event.preventDefault();
                event.stopPropagation();
                try {
                    saveCourseForm($(this).closest('form'));
                } catch (error) {
                    window.console.error('Falha ao salvar temporada ou turma.', error);
                    App.core.abrirPopup('erro', 'Não foi possível validar o formulário. Feche esta mensagem e tente novamente.');
                }
            });

            $(document).on('submit', '[data-course-form="season"], [data-course-form="class"]', function (event) {
                event.preventDefault();
                try {
                    saveCourseForm($(this));
                } catch (error) {
                    window.console.error('Falha ao salvar temporada ou turma.', error);
                    App.core.abrirPopup('erro', 'Não foi possível validar o formulário. Feche esta mensagem e tente novamente.');
                }
            });


            // A montagem inicial dos filtros pode consultar conteúdo carregado por
            // AJAX. Ela fica por último para nunca impedir o registro dos eventos
            // de criar, editar e salvar temporada/turma.
            initializeAdminClassBrowsers($(document));
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

        iniciarDetalhesInscricoesCursos: function () {
            if (App.admin._courseEnrollmentDetailsBound) return;
            App.admin._courseEnrollmentDetailsBound = true;

            const parseData = function ($element, attribute) {
                try { return JSON.parse(String($element.attr(attribute) || '{}')); }
                catch (error) { return {}; }
            };
            const openModal = function (title, $content) {
                const $modal = $('#course-enrollment-info-modal').last();
                $modal.find('#course-enrollment-info-title').text(title);
                $modal.find('#course-enrollment-info-body').empty().append($content);
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            };
            const detailLine = function (label, value) {
                return $('<p>').append($('<strong>', { text: label + ': ' })).append(document.createTextNode(String(value || '-')));
            };
            const formatHistoryDate = function (value) {
                const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
                return match ? match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '') : String(value || '-');
            };

            $(document).on('click', '.course-address-open', function () {
                const address = parseData($(this), 'data-address');
                const lines = [
                    [address.logradouro, address.numero].filter(Boolean).join(', '),
                    address.complemento,
                    [address.bairro, address.cidade, address.uf].filter(Boolean).join(' — '),
                    address.cep ? 'CEP: ' + address.cep : '',
                    address.telefone ? 'Telefone/WhatsApp: ' + address.telefone : '',
                    address.emergencia_nome || address.emergencia_telefone ? 'Contato de emergência: ' + [address.emergencia_nome, address.emergencia_telefone].filter(Boolean).join(' — ') : ''
                ].filter(Boolean);
                openModal('Endereço da pessoa', $('<div>').append($('<p>', { text: lines.length ? lines.join('\n') : 'Endereço não informado.', class: 'preserve-lines' })));
            });
            $(document).on('click', '.course-status-history-open', function () {
                const $button = $(this);
                const history = parseData($button, 'data-history');
                const $list = $('<div>', { class: 'course-status-history-list' });
                if (!Array.isArray(history) || history.length === 0) {
                    $list.append($('<p>', { text: 'Ainda não existem alterações de status registradas.' }));
                } else {
                    history.forEach(function (item) {
                        const previous = String(item.status_anterior_label || '').trim();
                        const transition = previous ? previous + ' → ' + String(item.status_novo_label || '') : String(item.status_novo_label || '');
                        const $entry = $('<article>').append($('<strong>', { text: transition }));
                        $entry.append($('<small>', { text: formatHistoryDate(item.criado_em) + ' · ' + String(item.alterado_por || 'Sistema') }));
                        if (String(item.vaga_informada_em || '').trim()) $entry.append($('<p>', { text: 'Vaga comunicada em: ' + formatHistoryDate(item.vaga_informada_em) }));
                        if (String(item.motivo || '').trim()) $entry.append($('<p>', { text: 'Motivo: ' + String(item.motivo) }));
                        $list.append($entry);
                    });
                }
                openModal('Alterações da inscrição Nº ' + String($button.attr('data-enrollment-number') || ''), $list);
            });
            $(document).on('click', '.course-enrollment-details-open', function () {
                const details = parseData($(this), 'data-details');
                const $content = $('<div>', { class: 'course-enrollment-details' });
                $content.append($('<h4>', { text: 'Detalhes da inscrição Nº ' + String(details.id || '') }));
                $content.append($('<p>', { class: 'course-enrollment-detail-person', text: String(details.nome || '') + (details.idade !== null && details.idade !== undefined ? ' — ' + String(details.idade) + ' anos' : '') }));
                $content.append(detailLine('Turma / Temporada', String(details.turma || '-') + ' / ' + String(details.temporada || '-')));
                $content.append(detailLine('Horário', details.horario));
                $content.append(detailLine('Local da aula', details.local));
                $content.append(detailLine('Data da inscrição', details.data_inscricao));
                $content.append(detailLine('Público da inscrição', details.publico_alvo));
                if (String(details.excecao_condicao || '').trim()) $content.append(detailLine('Exceção etária autorizada por', details.excecao_condicao));
                $content.append(detailLine('Com laudo?', details.com_laudo));
                $content.append(detailLine('Pessoa PCD?', details.pcd));
                $content.append(detailLine('Responsável pela inscrição', [details.responsavel, details.responsavel_email].filter(Boolean).join(' — ')));
                $content.append(detailLine('Status da inscrição', details.status));
                $content.append(detailLine('Início previsto das aulas', details.aulas_inicio));
                const $links = $('<div>', { class: 'course-enrollment-detail-links' });
                $links.append($('<button>', { type: 'button', class: 'link-button course-future-link', 'data-future-label': 'Declaração Aluno', text: 'Declaração Aluno' }));
                $links.append($('<button>', { type: 'button', class: 'link-button course-more-enrollments', text: 'Mais inscrições de ' + String(details.nome || 'esta pessoa') }).data('items', details.outras_inscricoes || []));
                $content.append($links);
                openModal('Detalhes da inscrição', $content);
            });
            $(document).on('click', '.course-more-enrollments', function () {
                const items = $(this).data('items') || [];
                const $list = $('<div>', { class: 'course-status-history-list' });
                if (!items.length) $list.append($('<p>', { text: 'Nenhuma outra inscrição encontrada nesta relação.' }));
                items.forEach(function (item) {
                    $list.append($('<article>').append($('<strong>', { text: '[' + String(item.id) + '] ' + String(item.turma || '') })).append($('<small>', { text: String(item.temporada || '') + ' · ' + String(item.status || '') + ' · ' + String(item.data || '') })));
                });
                openModal('Inscrições da pessoa', $list);
            });
            $(document).on('click', '.course-status-change-open', function () {
                const $button = $(this);
                const enrollmentId = String($button.attr('data-enrollment-id') || '');
                const enrollmentNumber = String($button.attr('data-enrollment-number') || enrollmentId);
                const nextStatus = String($button.attr('data-next-status') || '');
                const nextLabel = String($button.attr('data-next-label') || '');
                const currentStatus = String($button.attr('data-current-status') || '');
                const $modal = $('#course-status-change-modal').last();
                const $form = $modal.find('#course-status-change-form');
                $form[0].reset();
                $form.find('[name="inscricao_id"]').val(enrollmentId);
                $form.find('[name="status"]').val(nextStatus);
                if (!$form.find('[name="turma_id"]').length) $form.append($('<input>', { type: 'hidden', name: 'turma_id' }));
                $form.find('[name="turma_id"]').val(String($('.course-enrollment-management [data-course-enrollment-filter="class"]').val() || '0'));
                if (!$form.find('[name="turma_nome"]').length) $form.append($('<input>', { type: 'hidden', name: 'turma_nome' }));
                $form.find('[name="turma_nome"]').val(String($('.course-enrollment-management [data-course-enrollment-filter="class-name"]').val() || ''));
                $modal.find('#course-status-change-question').text('Deseja realmente mudar o status da inscrição \'' + enrollmentNumber + '\' para \'' + nextLabel + '\'?');
                $modal.find('#course-status-change-date').text(new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date()));
                const requiresNotice = currentStatus === 'lista_espera' && nextStatus === 'aguardando_matricula';
                const $noticeFields = $modal.find('#course-vacancy-notice-fields').toggleClass('hidden', !requiresNotice);
                $noticeFields.find('[name="vaga_informada"], [name="vaga_informada_em"]').prop('required', requiresNotice);
                if (requiresNotice) {
                    const now = new Date();
                    const localValue = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                    $noticeFields.find('[name="vaga_informada_em"]').val(localValue).attr('max', localValue);
                }
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            });
            $(document).on('click', '[data-course-status-change-close="1"], #course-status-change-modal', function (event) {
                if ($(event.target).is('#course-status-change-modal') || $(event.target).is('[data-course-status-change-close="1"]')) {
                    $('#course-status-change-modal').addClass('hidden').attr('aria-hidden', 'true');
                }
            });
            $(document).on('submit', '#course-status-change-form', function (event) {
                event.preventDefault();
                const $form = $(this);
                const $submit = $form.find('[type="submit"]');
                $submit.prop('disabled', true);
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    dataType: 'json',
                    data: $form.serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Não foi possível alterar o status da inscrição.'));
                        return;
                    }
                    const $panel = $('.course-enrollment-management').first();
                    if ($panel.length && response.panel_html) {
                        $('#course-enrollment-info-modal, #course-status-change-modal').remove();
                        $panel.replaceWith(String(response.panel_html));
                    }
                    App.core.abrirPopup('sucesso', String(response.message || 'Status da inscrição atualizado com sucesso.'));
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () { $submit.prop('disabled', false); });
            });
            $(document).on('click', '.course-future-link', function (event) {
                event.preventDefault();
                App.core.abrirPopup('informacao', String($(this).attr('data-future-label') || 'Este recurso') + ' será disponibilizado em uma atualização futura.');
            });
            $(document).on('click', '[data-course-enrollment-modal-close="1"], #course-enrollment-info-modal', function (event) {
                if ($(event.target).is('#course-enrollment-info-modal') || $(event.target).is('[data-course-enrollment-modal-close="1"]')) {
                    $('#course-enrollment-info-modal').addClass('hidden').attr('aria-hidden', 'true');
                }
            });
        },

        iniciarEditorPaginaProfessor: function () {
            function actionRow() {
                return $('<div>', { class: 'professor-page-action-row', 'data-professor-action-row': '1' })
                    .append($('<label>').append($('<span>').text('Texto do botão ou link'), $('<input>', { type: 'text', name: 'acao_rotulo[]', maxlength: 90, placeholder: 'Ex.: Consultar orientação' })))
                    .append($('<label>').append($('<span>').text('URL de destino'), $('<input>', { type: 'text', name: 'acao_url[]', maxlength: 2048, placeholder: '/agenda ou https://...' })))
                    .append($('<label>').append($('<span>').text('Apresentação'), $('<select>', { name: 'acao_tipo[]' }).append($('<option>', { value: 'botao', text: 'Botão' }), $('<option>', { value: 'link', text: 'Link' }))))
                    .append($('<button>', { type: 'button', class: 'btn btn-secondary', 'data-professor-action-remove': '1', 'aria-label': 'Remover esta ação', text: 'Remover' }));
            }

            $(document).on('click', '[data-professor-action-add]', function () {
                const $list = $(this).siblings('[data-professor-actions-list]');
                if ($list.find('[data-professor-action-row]').length >= 8) {
                    App.core.abrirPopup('informacao', 'É possível cadastrar no máximo oito botões ou links.');
                    return;
                }
                $list.append(actionRow());
                $list.find('[data-professor-action-row]').last().find('input').first().trigger('focus');
            });

            $(document).on('click', '[data-professor-action-remove]', function () {
                const $list = $(this).closest('[data-professor-actions-list]');
                $(this).closest('[data-professor-action-row]').remove();
                if (!$list.find('[data-professor-action-row]').length) $list.append(actionRow());
            });
        },

        iniciarEditorPaginaAjuda: function () {
            function videoRow() {
                return $('<div>', { class: 'tutorial-admin-video-row', 'data-tutorial-video-row': '1' })
                    .append($('<label>').append($('<span>').text('Título do vídeo'), $('<input>', { type: 'text', name: 'video_titulo[]', maxlength: 180, placeholder: 'Ex.: Como realizar meu cadastro' })))
                    .append($('<label>').append($('<span>').text('URL do YouTube'), $('<input>', { type: 'url', name: 'video_url[]', maxlength: 2048, placeholder: 'https://youtu.be/...' })))
                    .append($('<div>', { class: 'tutorial-admin-video-actions' })
                        .append($('<button>', { type: 'button', class: 'btn btn-secondary', 'data-tutorial-video-up': '1', 'aria-label': 'Mover vídeo para cima', text: 'Subir' }))
                        .append($('<button>', { type: 'button', class: 'btn btn-secondary', 'data-tutorial-video-down': '1', 'aria-label': 'Mover vídeo para baixo', text: 'Descer' }))
                        .append($('<button>', { type: 'button', class: 'btn btn-danger', 'data-tutorial-video-remove': '1', text: 'Remover' })));
            }

            $(document).on('click', '[data-tutorial-video-add]', function () {
                const $list = $(this).siblings('[data-tutorial-videos-list]');
                if ($list.find('[data-tutorial-video-row]').length >= 30) {
                    App.core.abrirPopup('informacao', 'É possível cadastrar no máximo 30 vídeos de ajuda.');
                    return;
                }
                $list.append(videoRow());
                $list.find('[data-tutorial-video-row]').last().find('input').first().trigger('focus');
            });
            $(document).on('click', '[data-tutorial-video-remove]', function () {
                const $list = $(this).closest('[data-tutorial-videos-list]');
                $(this).closest('[data-tutorial-video-row]').remove();
                if (!$list.find('[data-tutorial-video-row]').length) $list.append(videoRow());
            });
            $(document).on('click', '[data-tutorial-video-up], [data-tutorial-video-down]', function () {
                const $row = $(this).closest('[data-tutorial-video-row]');
                if ($(this).is('[data-tutorial-video-up]')) {
                    const $previous = $row.prev('[data-tutorial-video-row]');
                    if ($previous.length) $row.insertBefore($previous);
                } else {
                    const $next = $row.next('[data-tutorial-video-row]');
                    if ($next.length) $row.insertAfter($next);
                }
            });
        },

        init: function () {
            const initializers = [
                'iniciarSecoesAdmin',
                // Estes módulos controlam ações essenciais carregadas por AJAX e
                // devem ser preparados antes dos componentes administrativos mais
                // complexos.
                'iniciarFiltroLocaisTreino',
                'iniciarGerenciamentoTemporadasTurmas',
                'iniciarEditorPessoaAdmin',
                'iniciarConsultaUsuariosAdmin',
                'iniciarGerenciamentoPapeisAdmin',
                'iniciarFiltroPessoasAdmin',
                'iniciarEditorHorariosSemanais',
                'iniciarEditorEventosEspeciais',
                'iniciarValidacaoCondicoesAdmin',
                'iniciarValidacaoAtestadosSaudeAdmin',
                'iniciarEditorPostagensBlog',
                'iniciarEditorComunicacaoOficialAdmin',
                'iniciarBuscaEnderecoCep',
                'iniciarFiltroEspacosTreino',
                'iniciarGerenciamentoModalidades',
                'iniciarEditorEspacosTreino',
                'iniciarModalSuspensoesLocal',
                'iniciarEditorConteudoHome',
                'iniciarMigracaoCadastrosExternos',
                'iniciarGerenciamentoOrigensTemporada',
                'iniciarDetalhesInscricoesCursos',
                'iniciarEditorPaginaProfessor',
                'iniciarEditorPaginaAjuda'
            ];

            initializers.forEach(function (initializer) {
                try {
                    App.admin[initializer]();
                } catch (error) {
                    // Um componente com falha não deve desativar os botões e
                    // formulários das demais áreas administrativas.
                    window.console.error('Falha ao iniciar o módulo administrativo ' + initializer + '.', error);
                }
            });
        }
    });

    window.App = App;
}(window, window.jQuery));
