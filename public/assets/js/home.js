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
                $('.location-status').text('Três locais foram selecionados aleatoriamente.');
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
                $('#home-locations-title').text('Locais próximos a você');
                $('.location-status').text('Localização compartilhada. Os locais com coordenadas cadastradas foram ordenados por proximidade.');
                renderLocations(nearby);
            }, function () {
                $('.location-status').text('Localização não compartilhada. Exibimos três locais selecionados aleatoriamente.');
            }, {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000
            });
        },

        init: function () {
            App.home.initScrollAnimations();
            App.home.iniciarLocaisSugeridos();
        }
    });

    window.App = App;
}(window, window.jQuery));
