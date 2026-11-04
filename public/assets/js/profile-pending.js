(function (window, $) {
    'use strict';
    let request = null, source = null, sequence = 0;
    function modal() {
        let $modal = $('#profile-pending-modal');
        if (!$modal.length) {
            $modal = $('<div>', { id: 'profile-pending-modal', class: 'popup-overlay hidden', 'aria-hidden': 'true' }).html('<div class="popup-card popup-admin-card" role="dialog" aria-modal="true" aria-labelledby="profile-pending-title"><div class="popup-head"><div><h3 id="profile-pending-title">Pendências do cadastro</h3><p class="muted" data-profile-pending-name></p></div><button type="button" class="popup-close-icon" data-profile-pending-close aria-label="Fechar">&times;</button></div><div class="popup-body"><div data-profile-pending-content role="status" aria-live="polite"></div><div class="popup-actions"><button type="button" class="btn btn-secondary" data-profile-pending-close>Fechar</button></div></div></div>');
            $('body').append($modal);
        }
        return $modal;
    }
    function close() {
        sequence++;
        if (request) request.abort();
        request = null;
        $('#profile-pending-modal').addClass('hidden').attr('aria-hidden', 'true');
        if (source && source.isConnected) source.focus();
    }
    $(document).on('click', '[data-profile-pending]', function () {
        if (request) request.abort();
        const current = ++sequence;
        source = this;
        const $modal = modal(), $content = $modal.find('[data-profile-pending-content]');
        $modal.find('[data-profile-pending-name]').text('');
        $content.text('Consultando pendências…');
        $modal.removeClass('hidden').attr('aria-hidden', 'false');
        $modal.find('[data-profile-pending-close]').first().trigger('focus');
        const base = $('[data-admin-section-host]').attr('data-admin-base-path') || '/admin';
        request = $.ajax({url: window.App.core.buildUrl(base + '/pessoas/pendencias'), method: 'GET', dataType: 'json', suppressGlobalLoading: true,
            headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
            data: {id: $(this).attr('data-profile-pending'), tipo: $(this).attr('data-profile-pending-type') || 'pessoa'}
        }).done(function (response) {
            if (current !== sequence) return;
            if (!response.success || !response.report) { $content.text(response.message || 'Não foi possível consultar as pendências.'); return; }
            const report = response.report;
            $modal.find('[data-profile-pending-name]').text(report.name || '');
            $content.empty();
            if (report.pending.length) {
                $content.append($('<p>', {text: report.pending.length + (report.pending.length === 1 ? ' pendência identificada:' : ' pendências identificadas:')}));
                const $list = $('<ul>');
                report.pending.forEach(function (item) { $list.append($('<li>', {text: item})); });
                $content.append($list);
            } else $content.append($('<p>', {text: 'Nenhuma pendência encontrada nos dados obrigatórios.'}));
            $content.append($('<p>', {class: 'muted', text: report.guidance}));
        }).fail(function (xhr, reason) {
            if (current === sequence && reason !== 'abort') $content.text((xhr.responseJSON || {}).message || 'Não foi possível consultar as pendências. Tente novamente.');
        }).always(function () { if (current === sequence) request = null; });
    });
    $(document).on('click', '[data-profile-pending-close]', close);
    $(document).on('click', '#profile-pending-modal', function (event) { if (event.target === this) close(); });
    $(document).on('keydown', function (event) {
        const $modal = $('#profile-pending-modal');
        if (!$modal.length || $modal.hasClass('hidden')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const $focusable = $modal.find('button:visible'), first = $focusable[0], last = $focusable[$focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
}(window, window.jQuery));
