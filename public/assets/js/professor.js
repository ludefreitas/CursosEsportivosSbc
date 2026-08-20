(function (window, $) {
    const App = window.App || {};

    App.professor = Object.assign(App.professor || {}, {
        init: function () {
            const $host = $('[data-professor-mode="1"]');

            if ($host.length === 0) {
                return;
            }

            $('body').addClass('professor-page');
            $host.find('.admin-section-panel').addClass('professor-view');
            $host.attr('data-professor-ready', '1');
        }
    });
})(window, window.jQuery);
