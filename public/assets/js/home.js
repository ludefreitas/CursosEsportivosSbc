(function (window, $) {
    const App = window.App || {};

    App.home = Object.assign(App.home || {}, {
        solicitarGeolocalizacao: function () {
            if (!navigator.geolocation || !document.body.classList.contains('pagina-home')) {
                return;
            }

            navigator.geolocation.getCurrentPosition(function () {
                $('.location-status').text('Localizacao autorizada. Futuras sugestoes por proximidade poderao ser aplicadas aqui.');
            }, function () {
                $('.location-status').text('Localizacao nao autorizada. O sistema continua funcionando normalmente.');
            });
        },

        init: function () {
            App.home.solicitarGeolocalizacao();
        }
    });

    window.App = App;
}(window, window.jQuery));
