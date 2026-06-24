$(function () {
    const App = window.App || {};

    if (App.core && typeof App.core.init === 'function') {
        App.core.init();
    }

    if (App.auth && typeof App.auth.init === 'function') {
        App.auth.init();
    }

    if (App.admin && typeof App.admin.init === 'function') {
        App.admin.init();
    }

    if (App.agenda && typeof App.agenda.init === 'function') {
        App.agenda.init();
    }

    if (App.home && typeof App.home.init === 'function') {
        App.home.init();
    }

    if (App.dashboard && typeof App.dashboard.init === 'function') {
        App.dashboard.init();
    }
});
