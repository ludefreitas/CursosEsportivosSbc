(function (window, $) {
    const App = window.App || {};
    const labels = {apto: 'Apto', nao_apto: 'Não apto', pendente: 'Pendente'};
    let current = null;
    function url(path) { return App.core.buildUrl(path); }
    function date(value) { return value ? App.core.formatDateBr ? App.core.formatDateBr(value) : String(value).slice(0, 10).split('-').reverse().join('/') : 'Não informada'; }
    function certificateLink(record) {
        return $('<a>', {class: 'link-button', text: record.modalidade_nome + ' — ' + record.nivel_nome + ' (' + (record.status === 'ativo' ? 'Ativo' : 'Histórico') + ') · Certificado PDF', href: url('/avaliacoes/certificado.pdf?id=' + encodeURIComponent(record.id)), target: '_blank', rel: 'noopener'});
    }
    function records(data, target) {
        target.empty();
        const seen = new Set();
        (data.evaluations || []).forEach(function (record) {
            if (seen.has(String(record.modalidade_id))) return;
            seen.add(String(record.modalidade_id));
            target.append($('<p>').append($('<strong>', {text: record.modalidade_nome + ': '}), document.createTextNode((labels[record.situacao] || record.situacao) + ' · Avaliação em ' + date(record.data_avaliacao))));
        });
        (data.certificates || []).forEach(function (record) { target.append($('<p>').append(certificateLink(record))); });
        if (!seen.size && !(data.certificates || []).length) target.append($('<p>', {text: 'Nenhuma avaliação ou certificado de nível registrado.'}));
    }
    function request(person, modality) { return $.getJSON(url('/avaliacoes/modalidade'), {pessoa_id: person, modalidade_id: modality || 0}); }
    function modal() {
        let element = $('#modality-evaluation-modal');
        if (element.length) return element;
        element = $('<div>', {id: 'modality-evaluation-modal', class: 'popup-overlay hidden', 'aria-hidden': 'true'}).append(
            $('<div>', {class: 'popup-card popup-admin-card', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'modality-evaluation-title'}).append(
                $('<div>', {class: 'popup-head'}).append($('<h3>', {id: 'modality-evaluation-title', text: 'Aptidão e nível'}), $('<button>', {type: 'button', class: 'popup-close-icon', 'data-evaluation-close': '1', 'aria-label': 'Fechar avaliação', text: '×'})),
                $('<div>', {class: 'popup-body'}).append($('<div>', {'data-evaluation-content': '1', 'aria-live': 'polite'}))));
        $('body').append(element);
        return element;
    }
    function open(person, modality, edit) {
        current = {person: person, modality: modality, edit: edit};
        const element = modal().removeClass('hidden').attr('aria-hidden', 'false');
        const target = element.find('[data-evaluation-content]').text('Carregando...');
        request(person, modality).done(function (data) {
            if (!data || !data.success) { target.text((data && data.message) || 'Não foi possível carregar a avaliação.'); return; }
            // Não exibe resposta de um pedido anterior depois de abrir outra pessoa.
            if (!current || current.person !== person || current.modality !== modality || current.edit !== edit) return;
            $('#modality-evaluation-title').text('Aptidão e nível — ' + data.person.nome_completo);
            records(data, target);
            if (data.can_manage && Number(modality) > 0 && !edit) target.append($('<button>', {type: 'button', class: 'btn btn-primary', text: 'Atualizar aptidão/nível'}).on('click', function () { open(person, modality, true); }));
            if (data.can_manage && Number(modality) > 0 && edit) {
                const form = $('<form>', {class: 'stack-form', 'data-evaluation-form': '1', 'data-manual-submit': '1'});
                form.append($('<input>', {type: 'hidden', name: 'csrf', value: data.csrf}), $('<input>', {type: 'hidden', name: 'pessoa_id', value: person}), $('<input>', {type: 'hidden', name: 'modalidade_id', value: modality}));
                const status = $('<select>', {name: 'situacao', required: true}).append($('<option>', {value: '', text: 'Selecione'}));
                Object.keys(labels).forEach(function (key) { status.append($('<option>', {value: key, text: labels[key]})); });
                const level = $('<select>', {name: 'nivel_modalidade_id'}).append($('<option>', {value: '', text: 'Sem concessão de novo nível'}));
                (data.levels || []).forEach(function (item) { level.append($('<option>', {value: item.id, text: item.nome})); });
                if ((data.evaluations || []).length) status.val(data.evaluations[0].situacao);
                const active = (data.certificates || []).find(function (item) { return item.status === 'ativo'; });
                if (active && status.val() === 'apto') level.val(String(active.nivel_modalidade_id));
                form.append($('<label>').append($('<span>', {text: 'Situação da aptidão'}), status), $('<label>').append($('<span>', {text: 'Nível'}), level), $('<label>').append($('<span>', {text: 'Observações técnicas / justificativa'}), $('<textarea>', {name: 'observacoes', rows: 4, maxlength: 4000})), $('<label>', {class: 'checkbox-chip'}).append($('<input>', {type: 'checkbox', name: 'confirmar_rebaixamento', value: '1'}), $('<span>', {text: 'Confirmo o rebaixamento de nível, quando aplicável.'})), $('<p>', {class: 'muted', text: 'Não apto ou Pendente inativam o certificado de nível atual. O histórico permanece disponível.'}), $('<div>', {class: 'alert-inline hidden', 'data-evaluation-error': '1', role: 'alert'}), $('<button>', {type: 'submit', class: 'btn btn-primary', text: 'Salvar avaliação'}));
                status.on('change', function () { const apt = status.val() === 'apto'; level.prop('disabled', !apt); if (!apt) level.val(''); }).trigger('change');
                target.append(form);
            }
            if (data.can_configure) {
                const settings = $('<details>').append($('<summary>', {text: 'Configurar logo e instituição do certificado'}));
                const form = $('<form>', {class: 'stack-form', 'data-certificate-settings': '1', 'data-manual-submit': '1'}).append(
                    $('<input>', {type: 'hidden', name: 'csrf', value: data.csrf}),
                    $('<label>').append($('<span>', {text: 'Nome da instituição (abaixo do logo)'}), $('<input>', {name: 'instituicao', value: data.settings.institution, maxlength: 150, required: true})),
                    $('<label>').append($('<span>', {text: 'Logo em JPG — até 2 MB'}), $('<input>', {type: 'file', name: 'logo', accept: 'image/jpeg'})),
                    $('<label>', {class: 'checkbox-chip'}).append($('<input>', {type: 'checkbox', name: 'remover_logo', value: '1'}), $('<span>', {text: 'Remover logo e manter espaço reservado'})),
                    $('<button>', {type: 'submit', class: 'btn btn-secondary', text: 'Salvar modelo do certificado'}), $('<p>', {'data-settings-message': '1', role: 'status'}));
                if (data.settings.logo) form.prepend($('<img>', {src: url(data.settings.logo), alt: 'Logo atual da instituição', class: 'modality-certificate-logo'}));
                target.append(settings.append(form));
            }
        }).fail(function (xhr) { target.text(App.core.extrairMensagemErroAjax(xhr).mensagem); });
    }
    $(document).on('click', '[data-evaluation-open]', function () { open(String($(this).attr('data-evaluation-person')), String($(this).attr('data-evaluation-modality')), $(this).attr('data-evaluation-open') === 'edit'); });
    $(document).on('click', '[data-evaluation-close]', function () { current = null; $('#modality-evaluation-modal').addClass('hidden').attr('aria-hidden', 'true'); });
    $(document).on('click', '#modality-evaluation-modal', function (event) { if (event.target === this) $(this).find('[data-evaluation-close]').trigger('click'); });
    $(document).on('keydown.modalityEvaluation', function (event) { if (event.key === 'Escape') $('#modality-evaluation-modal [data-evaluation-close]').trigger('click'); });
    $(document).on('submit', '[data-evaluation-form]', function (event) {
        event.preventDefault(); const form = $(this); const button = form.find('[type=submit]').prop('disabled', true); const context = Object.assign({}, current);
        $.ajax({url: url('/avaliacoes/modalidade'), method: 'POST', dataType: 'json', data: form.serialize()}).done(function (data) {
            if (data.success) { $('[data-evaluation-person-list="' + context.person + '"]').removeAttr('data-evaluation-loaded'); loadLists(); open(context.person, context.modality, false); }
            else form.find('[data-evaluation-error]').removeClass('hidden').text(data.message);
        }).fail(function (xhr) { form.find('[data-evaluation-error]').removeClass('hidden').text(App.core.extrairMensagemErroAjax(xhr).mensagem); }).always(function () { button.prop('disabled', false); });
    });
    $(document).on('submit', '[data-certificate-settings]', function (event) {
        event.preventDefault(); const form = $(this); const button = form.find('[type=submit]').prop('disabled', true);
        $.ajax({url: url('/avaliacoes/certificado/modelo'), method: 'POST', dataType: 'json', data: new FormData(this), processData: false, contentType: false}).done(function (data) { form.find('[data-settings-message]').text(data.message); }).fail(function (xhr) { form.find('[data-settings-message]').text(App.core.extrairMensagemErroAjax(xhr).mensagem); }).always(function () { button.prop('disabled', false); });
    });
    function loadLists() {
        $('[data-evaluation-person-list]:not([data-evaluation-loaded])').each(function () {
            const element = $(this).attr('data-evaluation-loaded', '1').text('Carregando níveis...');
            request(element.attr('data-evaluation-person-list'), 0).done(function (data) { if (data.success) records(data, element); else element.text(data.message); }).fail(function (xhr) { element.text(App.core.extrairMensagemErroAjax(xhr).mensagem); });
        });
    }
    $(function () {
        loadLists();
        let scheduled = false;
        new MutationObserver(function () { if (!scheduled) { scheduled = true; window.setTimeout(function () { scheduled = false; loadLists(); }, 50); } }).observe(document.body, {childList: true, subtree: true});
    });
})(window, window.jQuery);
