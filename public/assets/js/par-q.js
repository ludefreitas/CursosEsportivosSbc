(function (window, $) {
    const App = window.App;
    const $modal = $('#par-q-modal');
    const $body = $modal.find('[data-par-q-content]');
    let request;
    let trigger;
    let saving = false;

    function close() {
        if (saving) return;
        if (request) request.abort();
        $modal.addClass('hidden').attr('aria-hidden', 'true');
        $body.empty();
        if (trigger && document.contains(trigger)) trigger.focus();
    }

    function open(personId, staff, source) {
        if (!personId || saving) return;
        if (request) request.abort();
        trigger = source;
        $body.text('Carregando questionário…');
        $modal.removeClass('hidden').attr('aria-hidden', 'false');
        $modal.find('.popup-close-icon').trigger('focus');
        request = $.getJSON(App.core.buildUrl('/par-q'), { pessoa_id: personId, consulta: staff ? '1' : '0' })
            .done(function (response) {
                $body.html(response.html).scrollTop(0);
            }).fail(function (xhr, status) {
                if (status !== 'abort') {
                    $body.text(App.core.extrairMensagemErroAjax(xhr).mensagem);
                }
            });
    }

    function showError($target, message) {
        $target.prepend($('<p>', { class: 'alert-inline', role: 'alert', 'data-par-q-inline-error': '', text: message }));
        const body = $body[0];
        const offset = $target[0].getBoundingClientRect().top - body.getBoundingClientRect().top;
        body.scrollTo({ top: body.scrollTop + offset - 12 });
        const input = $target.find('input').first()[0];
        if (input) input.focus({ preventScroll: true });
    }

    App.parQ = { open: open };

    $(document).on('click', '[data-par-q-person]', function (event) {
        event.preventDefault();
        open($(this).attr('data-par-q-person'), $(this).attr('data-par-q-staff') === '1', this);
    });

    $modal.on('click', '[data-par-q-close]', close).on('keydown', function (event) {
        if (event.key === 'Escape') close();
    }).on('change', 'input', function () {
        $(this).closest('[data-par-q-question], [data-par-q-term]').find('[data-par-q-inline-error]').remove();
    });

    $modal.on('submit', '[data-par-q-form]', function (event) {
        event.preventDefault();
        if (saving) return;

        const $form = $(this);
        const $error = $form.find('[data-par-q-error]').addClass('hidden').empty();
        $form.find('[data-par-q-inline-error]').remove();
        const $missing = $form.find('[data-par-q-question]').filter(function () {
            return !$(this).find('input:checked').length;
        }).first();
        if ($missing.length) {
            showError($missing, 'Responda a esta pergunta para continuar.');
            return;
        }
        if ($form.find('[data-par-q-question] input[value="sim"]:checked').length && !$form.find('[name="termo"]').is(':checked')) {
            showError($form.find('[data-par-q-term]'), 'Leia e aceite o Termo de Responsabilidade.');
            return;
        }

        const id = $form.find('[name="pessoa_id"]').val();
        const $submit = $form.find('[type="submit"]').prop('disabled', true);
        saving = true;
        $.post($form.attr('action'), $form.serialize(), null, 'json').done(function (response) {
            const $links = $('[data-par-q-person]').filter(function () {
                return $(this).hasClass('par-q-link') && $(this).attr('data-par-q-person') === id;
            });
            $links.each(function () {
                const $link = $(this);
                const html = response.links[$link.attr('data-par-q-staff') === '1' ? 'staff' : 'user'];
                if (html) {
                    const $newLink = $(html);
                    if (trigger === this) trigger = $newLink.filter('a')[0];
                    $link.replaceWith($newLink);
                }
            });
            $('[data-par-q-alert-person]').filter(function () {
                return $(this).attr('data-par-q-alert-person') === id;
            }).remove();
            $('.site-header-alert-card').filter(function () {
                return !$(this).find('.site-header-alert-item').length;
            }).closest('.site-header-alerts').remove();

            saving = false;
            close();
            if (App.agenda && typeof App.agenda.atualizarPessoasAgendamento === 'function') {
                App.agenda.atualizarPessoasAgendamento(true);
            }
            App.core.abrirPopup('sucesso', response.message);
        }).fail(function (xhr) {
            $error.text(App.core.extrairMensagemErroAjax(xhr).mensagem).removeClass('hidden');
        }).always(function () {
            saving = false;
            $submit.prop('disabled', false);
        });
    });

    $(function () {
        const current = new URL(window.location.href);
        const personId = current.searchParams.get('abrir_par_q');
        if (personId) {
            current.searchParams.delete('abrir_par_q');
            window.history.replaceState(window.history.state, '', current);
            open(personId, false);
        }
    });
})(window, window.jQuery);