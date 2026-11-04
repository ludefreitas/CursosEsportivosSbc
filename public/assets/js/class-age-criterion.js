(function (window, $) {
    'use strict';
    function cards($panel) { return $panel.find('[data-age-class]'); }
    function refill($group, $cards, key, placeholder) {
        const values = new Map();
        $cards.each(function () { values.set(this.dataset[key], this.dataset[key + 'Name']); });
        $group.empty();
        if (!values.size) $group.append($('<span>', { class: 'muted', text: placeholder }));
        Array.from(values).sort((a, b) => a[1].localeCompare(b[1], 'pt-BR')).forEach(function (item) {
            $group.append($('<button>', { type: 'button', class: 'btn btn-secondary admin-class-filter-button', 'data-age-filter-value': item[0], 'aria-pressed': 'false', text: item[1] }));
        });

    }
    $(document).on('click', '[data-age-filter-value]', function () {
        const $group = $(this).closest('[data-age-filter]');
        $group.find('[data-age-filter-value]').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).addClass('is-active').attr('aria-pressed', 'true');
        const $panel = $(this).closest('[data-age-criterion-panel]');
        const selected = name => String($panel.find('[data-age-filter="' + name + '"] .is-active').attr('data-age-filter-value') || '');
        const season = selected('temporada');
        const $seasonCards = cards($panel).filter(function () { return this.dataset.season === season; });
        if ($group.attr('data-age-filter') === 'temporada') {
            refill($panel.find('[data-age-filter="local"]'), $seasonCards, 'location', 'Nenhum local com turmas nesta temporada.');
            refill($panel.find('[data-age-filter="modalidade"]'), $(), 'modality', 'Selecione um local.');
        }
        const location = selected('local');
        if ($group.attr('data-age-filter') === 'local') {
            refill($panel.find('[data-age-filter="modalidade"]'), $seasonCards.filter(function () { return this.dataset.location === location; }), 'modality', 'Selecione a modalidade');
        }
        const modality = selected('modalidade'), criterion = selected('criterio');
        let count = 0;
        cards($panel).each(function () {
            const visible = !!(season && location && modality && this.dataset.season === season && this.dataset.location === location && this.dataset.modality === modality && (!criterion || this.dataset.criterion === criterion));
            this.hidden = !visible;
            if (visible) count++;
        });
        $panel.find('[data-age-list-status]').text(season && location && modality ? count + (count === 1 ? ' turma encontrada.' : ' turmas encontradas.') : 'Selecione a temporada, o local e a modalidade para consultar as turmas.');
    });
    $(document).on('change', '[data-age-change]', function () {
        const $input = $(this), $card = $input.closest('[data-age-class]'), $panel = $card.closest('[data-age-criterion-panel]');
        const previous = $card.attr('data-criterion'), criterion = $input.val();
        const $radios = $card.find('[data-age-change]');
        $radios.prop('disabled', true);
        $card.find('[data-age-save-status]').text('Salvando…');
        $.ajax({ url: $panel.attr('data-save-url'), method: 'POST', dataType: 'json', suppressGlobalLoading: true,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            data: { turma_id: $card.attr('data-age-class'), criterio: criterion, criterio_anterior: previous, csrf: $panel.attr('data-csrf') }
        }).done(function (response) {
            if (!response.success) { failed(response.message); return; }
            // Atualiza somente o trecho etário, sem recarregar ou remover o cartão.
            const rule = response.age_rule;
            if (rule) {
                $card.find('[data-age-summary]').text((rule.criterio_faixa_etaria === 'ano_nascimento' ? 'Ano de nascimento: ' : '') + rule.faixa_etaria_descricao);
                const $details = $card.find('[data-course-class-details]');
                let record = {};
                try { record = JSON.parse($details.attr('data-course-class-details') || '{}'); } catch (error) {}
                Object.assign(record, rule);
                $details.attr('data-course-class-details', JSON.stringify(record));
            }
            $card.attr('data-criterion', criterion);
            $card.find('[data-age-save-status]').text('');
        }).fail(function (xhr) { failed((xhr.responseJSON || {}).message); })
            .always(function () { $radios.prop('disabled', false); });
        function failed(message) {
            $radios.each(function () { this.checked = this.value === previous; });
            $card.find('[data-age-save-status]').text(message || 'Não foi possível salvar. Tente novamente.');
        }
    });
}(window, window.jQuery));
