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

        solicitarGeolocalizacao: function () {
            if (!navigator.geolocation || !document.body.classList.contains('pagina-home')) {
                return;
            }

            navigator.geolocation.getCurrentPosition(function () {
                $('.location-status').text('Localizacao autorizada. Futuras sugestoes por proximidade poderão ser aplicadas aqui.');
            }, function () {
                $('.location-status').text('Localização não autorizada. O sistema continua funcionando normalmente.');
            });
        },

        init: function () {
            App.home.initScrollAnimations();
            App.home.solicitarGeolocalizacao();
        }
    });

    window.App = App;
}(window, window.jQuery));
