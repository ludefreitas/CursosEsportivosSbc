(function (window, $) {
    'use strict';
    const App = window.App;
    const selector = 'input[data-person-cep-address="1"], input[data-cep-address-search="1"]';
    const states = new WeakMap();
    const cache = new Map();
    const limitMs = 5000;
    let initialized = false;

    function digits(input) { return String(input.value || '').replace(/\D/g, ''); }

    function status($input, text, retry) {
        let $message = $input.siblings('.cep-address-status, .cep-helper').first();
        if (!$message.length) $message = $('<small class="cep-helper muted"></small>').insertAfter($input);
        $message.attr({ role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' }).text(text);
        let $retry = $input.siblings('[data-cep-address-retry]');
        if (!$retry.length) {
            $retry = $('<button type="button" class="link-button hidden" data-cep-address-retry>Tentar consultar o CEP novamente</button>').insertAfter($message);
        }
        $retry.toggleClass('hidden', !retry);
    }

    function fields($input) {
        const names = $input.is('[data-person-cep-address="1"]')
            ? { logradouro: 'street', bairro: 'neighborhood', cidade: 'city', uf: 'state' }
            : { logradouro: 'logradouro', bairro: 'bairro', cidade: 'cidade', uf: 'uf' };
        return Object.entries(names).map(function ([key, name]) {
            return { key: key, $field: $input.closest('form').find('[name="' + name + '"]') };
        });
    }

    function release(state) {
        window.clearTimeout(state.timer);
        state.saved.forEach(function (saved) { saved.field.disabled = saved.disabled; });
        state.$input.removeAttr('aria-busy');
    }

    function cancel(input) {
        const state = states.get(input);
        if (!state) return;
        states.delete(input); // Invalidates callbacks before abort() invokes them.
        release(state);
        state.requests.forEach(function (request) { request.abort(); });
        if (state.$input.attr('data-remote-validation-error') === 'Aguarde a consulta do CEP.') {
            state.$input.removeAttr('data-remote-validation-error');
        }
        status(state.$input, '', false);
    }

    function current(state) {
        if (states.get(state.$input[0]) !== state) return false;
        if (!state.$input[0].isConnected || digits(state.$input[0]) !== state.cep) {
            cancel(state.$input[0]);
            return false;
        }
        return true;
    }

    function complete(state, message, error, retry, address) {
        if (!current(state)) return;
        states.delete(state.$input[0]);
        release(state);
        state.requests.forEach(function (request) { if (request.readyState !== 4) request.abort(); });
        if (address) {
            state.fields.forEach(function (item) {
                item.$field.val(String(address[item.key] || '')).trigger('change');
                // CEPs genéricos podem não informar logradouro ou bairro.
                if (!address[item.key]) item.$field.prop('readOnly', false);
            });
        } else if (!error) {
            // Falha temporária: permite completar o endereço sem prender o usuário.
            state.fields.forEach(function (item) { item.$field.prop('readOnly', false); });
        }
        state.$input.attr('data-remote-validation-error', error || '');
        state.$input.data('personCepAddressSelected', !!address);
        status(state.$input, message, retry);
        App.core.validarCampoInline(state.$input[0], !!error);
    }

    function resolve(state) {
        if (!current(state)) return;
        if (state.validationDone && state.validation && state.validation.aceito === false) {
            const message = String(state.validation.mensagem || 'CEP fora da área atendida.');
            complete(state, message, message, false);
            return;
        }
        if (!state.addressDone || !state.validationDone) return;
        if (!state.validation || state.validation.aceito !== true) {
            const message = 'Não foi possível validar o CEP agora. Tente consultar novamente.';
            complete(state, message, message, true);
            return;
        }
        const result = state.address;
        if (result && result.success === true && result.address &&
            String(result.address.cep || '').replace(/\D/g, '') === state.cep) {
            const address = result.address;
            cache.set(state.cep, { address: address, until: Date.now() + 300000 });
            if (cache.size > 50) cache.delete(cache.keys().next().value);
            const missing = ['logradouro', 'bairro', 'cidade', 'uf'].some(function (key) { return !String(address[key] || '').trim(); });
            complete(state, missing ? 'Endereço preenchido. Complete os campos que o CEP não informou.' : 'Endereço preenchido automaticamente. Confira o número e o complemento.', '', false, address);
        } else if (result && result.success === false && result.message === 'CEP não encontrado.') {
            complete(state, 'CEP não encontrado. Confira os 8 números informados.', 'CEP não encontrado.', true);
        } else {
            complete(state, 'A consulta não respondeu a tempo. Preencha o endereço manualmente ou tente novamente.', '', true);
        }
    }

    function search(input) {
        const $input = $(input);
        cancel(input);
        $input.siblings('.cep-address-results').addClass('hidden').empty();
        $input.removeAttr('aria-autocomplete aria-expanded aria-controls');
        $input.data('personCepAddressSelected', false).attr('data-remote-validation-error', '');
        const cep = digits(input).slice(0, 8);
        $input.val(cep.length > 5 ? cep.slice(0, 5) + '-' + cep.slice(5) : cep);
        const addressFields = fields($input);
        addressFields.forEach(function (item) { item.$field.val(''); });
        if (cep.length !== 8) {
            status($input, 'Digite os 8 números do CEP para preencher o endereço.', false);
            App.core.validarCampoInline(input, false);
            return;
        }
        const state = { $input: $input, cep: cep, fields: addressFields, requests: [], saved: [], addressDone: false,
            validationDone: !$input.is('[data-person-cep-address="1"]'), validation: { aceito: true } };
        states.set(input, state);
        addressFields.forEach(function (item) {
            item.$field.each(function () { state.saved.push({ field: this, disabled: this.disabled }); this.disabled = true; });
        });
        $input.attr({ 'aria-busy': 'true', 'data-remote-validation-error': 'Aguarde a consulta do CEP.' });
        status($input, 'Buscando endereço… Aguarde até 5 segundos.', false);
        // Um limite único cobre consulta e validação, sem somar esperas.
        state.timer = window.setTimeout(function () {
            state.addressDone = true;
            state.validationDone = true;
            if (!state.validationReceived && $input.is('[data-person-cep-address="1"]')) state.validation = null;
            resolve(state);
        }, limitMs);
        function request(path, done) {
            const xhr = $.ajax({ url: App.core.buildUrl(path), method: 'GET', dataType: 'json', data: { cep: cep },
                timeout: limitMs - 100, suppressGlobalLoading: true });
            state.requests.push(xhr);
            xhr.done(function (response) { if (current(state)) done(response); })
                .fail(function (xhr, reason) { if (reason !== 'abort' && current(state)) done(null); });
        }
        if (!state.validationDone) {
            request('/api/ceps/validar', function (response) {
                state.validation = response; state.validationReceived = true; state.validationDone = true; resolve(state);
            });
        }
        const cached = cache.get(cep);
        if (cached && cached.until > Date.now()) {
            state.address = { success: true, address: cached.address }; state.addressDone = true; resolve(state);
        } else {
            request('/api/ceps/endereco', function (response) { state.address = response; state.addressDone = true; resolve(state); });
        }
    }

    App.cepAddress = {
        cancel: function ($inputs) { $inputs.each(function () { cancel(this); }); },
        init: function () {
            if (initialized) return;
            initialized = true;
            $(document).on('input.cepAddress', selector, function () { search(this); });
            $(document).on('click.cepAddress', '[data-cep-address-retry]', function (event) {
                event.preventDefault();
                const input = $(this).siblings(selector)[0];
                if (input) search(input);
            });
            $(document).on('reset.cepAddress', 'form', function () {
                $(this).find(selector).each(function () {
                    cancel(this); $(this).removeAttr('data-remote-validation-error'); status($(this), '', false);
                });
            });
            document.addEventListener('submit', function (event) {
                const input = $(event.target).find(selector).toArray().find(function (input) { return states.has(input); });
                if (!input) return;
                event.preventDefault(); event.stopImmediatePropagation(); input.focus();
            }, true);
        }
    };
})(window, window.jQuery);
