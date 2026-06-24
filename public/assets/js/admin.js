(function (window, $) {
    const App = window.App || {};

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
                initAdminAgendaCalendar();
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
                    App.core.abrirPopup('erro', 'O modal da chamada administrativa nao esta disponivel nesta tela.');
                    return;
                }

                $content.html('<p class="muted">Carregando chamada da ocorrencia...</p>');
                openOccurrenceModal();

                $.ajax({
                    url: App.core.buildUrl('/admin/agendamentos/ocorrencia'),
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        horario_id: String(scheduleId || '0'),
                        data_hora_inicio: String(startDateTime || '')
                    },
                    suppressGlobalLoading: true
                }).done(function (response) {
                    if (!response || response.success === false) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar a chamada desta ocorrencia.'));
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
                        url: App.core.buildUrl('/api/admin/agenda/eventos'),
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
                        loadOccurrenceAttendance(
                            info.event.id,
                            String((info.event.extendedProps && info.event.extendedProps.occurrence_start) || info.event.startStr || '')
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
                $row.find('[data-booking-justification-cell="1"]').text(normalizedReason !== '' ? normalizedReason : '-');
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
                $modal.addClass('hidden').attr('aria-hidden', 'true');
            }

            function openJustificationModal(bookingId, reason) {
                const $modal = getJustificationModal();
                const $form = getJustificationForm();

                if ($modal.length === 0 || $form.length === 0) {
                    App.core.abrirPopup('erro', 'O modal de justificativa nao esta disponivel nesta tela.');
                    return;
                }

                $form.find('input[name="agendamento_id"]').val(String(bookingId || ''));
                $form.find('input[name="justificativa_motivo"]').val(String(reason || ''));
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
                    url: App.core.buildUrl('/admin/agendamentos/presenca'),
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar a chamada.'));
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
                $host.attr('data-admin-loading', '1').html('<section class="admin-section-panel"><article class="content-card"><p class="muted">Carregando conteudo...</p></article></section>');

                $.ajax({
                    url: sectionsUrl,
                    method: 'GET',
                    dataType: 'json',
                    data: requestData,
                    suppressGlobalLoading: requestOptions.suppressGlobalLoading === true
                })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar esta secao agora.'));
                            return;
                        }

                        $host.html(String(response.html || ''));
                        hydrateDynamicSection();
                        updateHash(normalizedTarget);
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
                activateSection('agenda', currentAgendaFilters());
            });

            $(document).on('change', '#admin-daily-bookings-filter-form input[name="data_agendamento"], #admin-daily-bookings-filter-form select[name="agendamento_local_treino_id"], #admin-daily-bookings-filter-form select[name="agendamento_espaco_treino_id"]', function () {
                activateSection('agenda', currentAgendaFilters());
            });

            $(document).on('click', '[data-admin-agenda-filter-mode]', function () {
                const mode = String($(this).data('adminAgendaFilterMode') || '').trim().toLowerCase();

                if (mode !== 'todos' && mode !== 'local' && mode !== 'modalidade') {
                    return;
                }

                if ($(this).hasClass('is-active')) {
                    return;
                }

                $('#admin-agenda-calendar-filter-mode').val(mode);
                $('[data-admin-agenda-filter-mode]').removeClass('is-active');
                $(this).addClass('is-active');
                $('[data-admin-agenda-filter-panel]').addClass('hidden');

                if (mode === 'todos') {
                    $('#admin-agenda-calendar-local-filter').val('0');
                    $('#admin-agenda-calendar-modality-filter').val('0');
                    $('[data-admin-agenda-filter-kind="local"]').removeClass('is-active');
                    $('[data-admin-agenda-filter-kind="local"][data-admin-agenda-filter-value="0"]').addClass('is-active');
                    $('[data-admin-agenda-filter-kind="modalidade"]').removeClass('is-active');
                    $('[data-admin-agenda-filter-kind="modalidade"][data-admin-agenda-filter-value="0"]').addClass('is-active');
                } else if (mode === 'local') {
                    $('[data-admin-agenda-filter-panel="local"]').removeClass('hidden');
                    $('#admin-agenda-calendar-modality-filter').val('0');
                    $('[data-admin-agenda-filter-kind="modalidade"]').removeClass('is-active');
                    $('[data-admin-agenda-filter-kind="modalidade"][data-admin-agenda-filter-value="0"]').addClass('is-active');
                } else {
                    $('[data-admin-agenda-filter-panel="modalidade"]').removeClass('hidden');
                    $('#admin-agenda-calendar-local-filter').val('0');
                    $('[data-admin-agenda-filter-kind="local"]').removeClass('is-active');
                    $('[data-admin-agenda-filter-kind="local"][data-admin-agenda-filter-value="0"]').addClass('is-active');
                }

                refetchAdminAgendaCalendar();
            });

            $(document).on('click', '[data-admin-agenda-filter-kind]', function () {
                const kind = String($(this).data('adminAgendaFilterKind') || '').trim().toLowerCase();
                const value = String($(this).data('adminAgendaFilterValue') || '0');

                if (kind !== 'local' && kind !== 'modalidade') {
                    return;
                }

                if ($(this).hasClass('is-active')) {
                    return;
                }

                $('[data-admin-agenda-filter-kind="' + kind + '"]').removeClass('is-active');
                $(this).addClass('is-active');

                if (kind === 'local') {
                    $('[data-admin-agenda-filter-mode]').removeClass('is-active');
                    $('[data-admin-agenda-filter-mode="local"]').addClass('is-active');
                    $('[data-admin-agenda-filter-panel]').addClass('hidden');
                    $('[data-admin-agenda-filter-panel="local"]').removeClass('hidden');
                    $('#admin-agenda-calendar-filter-mode').val('local');
                    $('#admin-agenda-calendar-local-filter').val(value);
                    $('#admin-agenda-calendar-modality-filter').val('0');
                } else {
                    $('[data-admin-agenda-filter-mode]').removeClass('is-active');
                    $('[data-admin-agenda-filter-mode="modalidade"]').addClass('is-active');
                    $('[data-admin-agenda-filter-panel]').addClass('hidden');
                    $('[data-admin-agenda-filter-panel="modalidade"]').removeClass('hidden');
                    $('#admin-agenda-calendar-filter-mode').val('modalidade');
                    $('#admin-agenda-calendar-modality-filter').val(value);
                    $('#admin-agenda-calendar-local-filter').val('0');
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
                    openJustificationModal(bookingId, String($checkbox.data('currentJustification') || ''));
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
                    App.core.abrirPopup('erro', 'O modal de consulta de pessoa nao esta disponivel nesta tela.');
                    return;
                }

                currentPerson = person;
                $('#admin-person-details-subtitle').text('Consultando ' + String(person.nome_completo || '') + ' sem sair desta pagina.');
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
                    App.core.abrirPopup('erro', 'O formulario de edicao de pessoa nao esta disponivel nesta tela.');
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
                    $accountHint.text('Conta vinculada encontrada. Voce pode ativar ou inativar este usuario aqui.');
                } else {
                    setValue('#admin-person-conta-ativa', '0');
                    $contaAtiva.prop('disabled', true);
                    $accountHint.text('Esta pessoa ainda nao possui conta de usuario vinculada.');
                }

                $('#admin-person-editor-subtitle').text('Editando ' + String(person.nome_completo || '') + ' sem sair desta pagina.');
                $panel.removeClass('hidden').attr('aria-hidden', 'false');
                $('#admin-person-sexo').trigger('change');
            }

            $(document).on('click', '[data-person-edit="1"]', function () {
                const personId = Number($(this).data('personId') || 0);

                if (!personId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar a pessoa selecionada.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/pessoas/detalhe'), { id: personId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.person) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar os dados desta pessoa.'));
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
                    App.core.abrirPopup('erro', 'Nao foi possivel localizar os dados desta pessoa para edicao.');
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel salvar as alteracoes.'));
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
                    String($(this).data('alertMessage') || 'Nao foi possivel carregar o aviso deste certificado.')
                );
            });
        },

        iniciarFiltroPessoasAdmin: function () {
            let peopleFilterTimer = null;

            function refreshPeoplePanel($form, options) {
                const settings = Object.assign({
                    preserveSearchFocus: false
                }, options || {});
                const limit = String($form.find('input[name="people_limit"]').val() || '').trim();
                const search = String($form.find('input[name="people_search"]').val() || '').trim();
                const selectionStart = settings.preserveSearchFocus ? Number($form.find('input[name="people_search"]')[0] && $form.find('input[name="people_search"]')[0].selectionStart) : null;
                const selectionEnd = settings.preserveSearchFocus ? Number($form.find('input[name="people_search"]')[0] && $form.find('input[name="people_search"]')[0].selectionEnd) : null;

                $.getJSON(App.core.buildUrl('/admin/pessoas/lista'), {
                    people_limit: limit,
                    people_search: search
                })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar a lista agora.'));
                            return;
                        }

                        $('#admin-people-panel-shell').replaceWith(String(response.html));

                        if (settings.preserveSearchFocus) {
                            window.requestAnimationFrame(function () {
                                const $searchInput = $('#admin-people-search');

                                if ($searchInput.length === 0) {
                                    return;
                                }

                                $searchInput.trigger('focus');

                                if ($searchInput[0] && typeof $searchInput[0].setSelectionRange === 'function') {
                                    const start = Number.isFinite(selectionStart) ? selectionStart : search.length;
                                    const end = Number.isFinite(selectionEnd) ? selectionEnd : search.length;
                                    $searchInput[0].setSelectionRange(start, end);
                                }
                            });
                        }
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            }

            $(document).on('submit', '#admin-people-filter-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                refreshPeoplePanel($form);
            });

            $(document).on('input', '#admin-people-search', function () {
                const $form = $('#admin-people-filter-form');

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

            $(document).on('change', '#admin-people-filter-form input[name="people_limit"]', function () {
                const $form = $('#admin-people-filter-form');

                if ($form.length === 0) {
                    return;
                }

                refreshPeoplePanel($form);
            });
        },

        iniciarEditorHorariosSemanais: function () {
            function getModal() {
                return $('#admin-weekly-schedule-editor');
            }

            function getForm() {
                return $('#admin-weekly-schedule-form');
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

                $('#admin-weekly-schedule-editor-subtitle').text(
                    'Editando ' + String(schedule.modalidade_nome || '') + ' em ' + String(schedule.local_nome || '') + ' sem sair da agenda administrativa.'
                );
            }

            $(document).on('click', '[data-weekly-schedule-edit="1"]', function () {
                const scheduleId = Number($(this).data('weeklyScheduleId') || 0);

                if (!scheduleId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar o horario selecionado.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/horarios-semanais/detalhe'), { id: scheduleId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.schedule) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar este horario.'));
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

            $(document).on('click', '#admin-weekly-schedule-editor', function (event) {
                if (event.target === this) {
                    closeEditor();
                }
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeEditor();
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel criar o horario semanal.'));
                        return;
                    }

                    $createForm[0].reset();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horario semanal criado com sucesso.'));
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar o horario semanal.'));
                        return;
                    }

                    closeEditor();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horario semanal atualizado com sucesso.'));
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel inativar o horario semanal.'));
                        return;
                    }

                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horario semanal inativado com sucesso.'));
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel ativar o horario semanal.'));
                        return;
                    }

                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Horario semanal ativado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        iniciarEditorEventosEspeciais: function () {
            function getModal() {
                return $('#admin-special-agenda-event-editor');
            }

            function getForm() {
                return $('#admin-special-agenda-event-form');
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

            function setValue(selector, value) {
                $(selector).val(value == null ? '' : String(value));
            }

            function formatDateTimeLocal(value) {
                return String(value || '').replace(' ', 'T').slice(0, 16);
            }

            function fillForm(eventData) {
                setValue('#admin-special-agenda-event-id', eventData.id);
                setValue('#admin-special-agenda-event-title', eventData.titulo || '');
                setValue('#admin-special-agenda-event-description', eventData.descricao || '');
                setValue('#admin-special-agenda-event-start', formatDateTimeLocal(eventData.data_inicio));
                setValue('#admin-special-agenda-event-end', formatDateTimeLocal(eventData.data_fim));
                setValue('#admin-special-agenda-event-publish-start', formatDateTimeLocal(eventData.data_publicacao_inicio));
                setValue('#admin-special-agenda-event-publish-end', formatDateTimeLocal(eventData.data_publicacao_fim));
                setValue('#admin-special-agenda-event-age-min', eventData.idade_minima);
                setValue('#admin-special-agenda-event-age-max', eventData.idade_maxima);
                setValue('#admin-special-agenda-event-space', eventData.espaco_treino_id || '');
                setValue('#admin-special-agenda-event-modality', eventData.modalidade_id || '');
                setValue('#admin-special-agenda-event-image-url', eventData.imagem_url || '');
                setValue('#admin-special-agenda-event-url', eventData.url_destino || '');
                setValue('#admin-special-agenda-event-label', eventData.rotulo_acao || '');
                setValue('#admin-special-agenda-event-active', Number(eventData.ativo || 0) === 1 ? '1' : '0');
                $('#admin-special-agenda-event-home').prop('checked', Number(eventData.publicar_pagina_inicial || 0) === 1);
                $('#admin-special-agenda-event-blog').prop('checked', Number(eventData.publicar_blog || 0) === 1);

                $('#admin-special-agenda-event-editor-subtitle').text(
                    'Editando ' + String(eventData.titulo || 'evento especial') + ' sem sair da agenda administrativa.'
                );
            }

            $(document).on('click', '[data-special-agenda-event-edit="1"]', function () {
                const eventId = Number($(this).data('specialAgendaEventId') || 0);

                if (!eventId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar o evento especial selecionado.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/agenda-eventos-especiais/detalhe'), { id: eventId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.event) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar este evento especial.'));
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

            $(document).on('click', '#admin-special-agenda-event-editor-close, #admin-special-agenda-event-cancel', function () {
                closeEditor();
            });

            $(document).on('click', '#admin-special-agenda-event-editor', function (event) {
                if (event.target === this) {
                    closeEditor();
                }
            });

            $(document).on('submit', '#admin-special-agenda-event-form', function (event) {
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar o evento especial.'));
                        return;
                    }

                    closeEditor();
                    App.admin.activateSection('agenda', currentAgendaFilters());
                    App.core.abrirPopup('sucesso', String(response.message || 'Evento especial atualizado com sucesso.'));
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
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar a condicao selecionada para validacao.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/admin/certificados/validacao/modal'), {
                    person_id: personId,
                    condition_slug: conditionSlug
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel abrir a validacao deste certificado.'));
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
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel salvar a validacao do certificado.'));
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
                    App.core.abrirPopup('sucesso', String(response.message || 'Validacao atualizada com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        init: function () {
            App.admin.iniciarSecoesAdmin();
            App.admin.iniciarEditorPessoaAdmin();
            App.admin.iniciarFiltroPessoasAdmin();
            App.admin.iniciarEditorHorariosSemanais();
            App.admin.iniciarEditorEventosEspeciais();
            App.admin.iniciarValidacaoCondicoesAdmin();
        }
    });

    window.App = App;
}(window, window.jQuery));
