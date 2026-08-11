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
                    byDate[key] = byDate[key] || { available: false, statuses: [] };
                    byDate[key].available = true;
                    if (status && byDate[key].statuses.indexOf(status) < 0) {
                        byDate[key].statuses.push(status);
                    }
                });

                $(calendarElement).find('.fc-daygrid-day')
                    .removeClass('home-training-day-state is-available is-agendado is-presente is-falta is-justificado is-cancelado is-misto');
                Object.keys(byDate).forEach(function (key) {
                    const $cell = $(calendarElement).find('.fc-daygrid-day[data-date="' + key + '"]');
                    if ($cell.length === 0) return;
                    let state = byDate[key].available ? 'available' : '';
                    if (byDate[key].statuses.length === 1) state = byDate[key].statuses[0];
                    if (byDate[key].statuses.length > 1) state = 'misto';
                    if (state) $cell.addClass('home-training-day-state is-' + state);
                });
            }

            function renderDayList(events) {
                const modalityId = Number($('#home-training-day-modality').val() || 0);
                const isPastDate = selectedDate !== '' && selectedDate < dateKey(new Date());
                const records = (events || []).filter(function (event) {
                    const props = event.extendedProps || {};
                    const matchesModality = modalityId <= 0 || Number(props.modalidade_id || 0) === modalityId;
                    const hasPersonalBooking = statusFromEvent(event) !== ''
                        || (Array.isArray(props.meus_agendamentos) && props.meus_agendamentos.length > 0);
                    return matchesModality && (!isPastDate || hasPersonalBooking);
                }).sort(function (left, right) { return left.start - right.start; });
                const $list = $('#home-training-day-list').empty();

                if (records.length === 0) {
                    $list.append($('<p>', { class: 'muted', text: 'Nenhum horário encontrado para este dia e modalidade.' }));
                    return;
                }

                records.forEach(function (event) {
                    const props = event.extendedProps || {};
                    const status = statusFromEvent(event);
                    const $card = $('<article>', { class: 'home-training-day-item' + (status ? ' is-' + status : '') });
                    $card.append($('<div>', { class: 'home-training-day-time', text: App.agenda.formatarHoraAgenda(event.start) + ' às ' + App.agenda.formatarHoraAgenda(event.end) }));
                    $card.append($('<strong>', { text: String(props.modalidade || event.title || 'Horário') }));
                    $card.append($('<span>', { text: String(props.espaco || 'Espaço a definir') + ' — ' + String(props.tipo_horario || 'treino') }));
                    const personalBookings = Array.isArray(props.meus_agendamentos) ? props.meus_agendamentos : [];
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
                    $list.append($card);
                });
            }

            function loadDay() {
                if (!selectedLocationId || !selectedDate) return;
                const end = new Date(selectedDate + 'T12:00:00');
                end.setDate(end.getDate() + 1);
                $.getJSON(App.core.buildUrl('/api/agenda/eventos'), {
                    local_treino_id: selectedLocationId,
                    modalidade_id: Number($('#home-training-day-modality').val() || 0),
                    start: selectedDate + 'T00:00:00',
                    end: dateKey(end) + 'T00:00:00'
                }).done(function (records) {
                    const eventObjects = (Array.isArray(records) ? records : []).map(function (record) {
                        return {
                            title: record.title,
                            start: new Date(record.start),
                            end: new Date(record.end),
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
                    $('#home-training-day-modality').val('0');
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
                $('#home-training-modality').val('0');
                $calendarModal.removeClass('hidden').attr('aria-hidden', 'false');
                calendar.refetchEvents();
                window.setTimeout(function () {
                    calendar.updateSize();
                }, 50);
            });

            $(document).on('change', '#home-training-modality', function () { calendar.refetchEvents(); });
            $(document).on('change', '#home-training-day-modality', loadDay);
            $(document).on('click', '[data-home-training-day-close="1"]', function () { $dayModal.addClass('hidden').attr('aria-hidden', 'true'); });
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
            App.home.iniciarAgendaDeTreinos();
        }
    });

    window.App = App;
}(window, window.jQuery));
