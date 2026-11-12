(function () {
    'use strict';

    const selector = 'input[type="date"], input[type="datetime-local"]';
    const editors = new WeakMap();
    let serial = 0;

    function parse(value, withTime) {
        if (/^\d{8}(?:\s|$)/.test(value)) {
            value = value.slice(0, 2) + '/' + value.slice(2, 4) + '/' + value.slice(4);
        }
        const format = withTime
            ? /^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?$/
            : /^(\d{2})\/(\d{2})\/(\d{4})$/;
        const parts = value.match(format);
        if (!parts) return null;

        const [, day, month, year, hour, minute, second] = parts;
        if (+year < 1 || +month < 1 || +month > 12 || +day < 1) return null;
        const leap = +year % 4 === 0 && (+year % 100 !== 0 || +year % 400 === 0);
        const monthLengths = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if (+day > monthLengths[+month - 1] || (withTime && (+hour > 23 || +minute > 59 || (second && +second > 59)))) return null;

        return year + '-' + month + '-' + day + (withTime ? 'T' + hour + ':' + minute + (second ? ':' + second : '') : '');
    }

    function display(value) {
        const [date, time] = value.split('T');
        return date.split('-').reverse().join('/') + (time ? ' ' + time : '');
    }

    function signal(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function enhance(field) {
        if (editors.has(field)) return editors.get(field);

        const tools = document.createElement('span');
        tools.className = 'date-entry-tools';
        const text = document.createElement('input');
        text.type = 'text';
        text.autocomplete = 'off';
        text.hidden = true;
        text.className = 'date-entry-text';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'link-button date-entry-toggle';
        button.textContent = 'Digitar data';
        const error = document.createElement('small');
        error.className = 'date-entry-error';
        error.id = 'date-entry-error-' + (++serial);
        error.setAttribute('role', 'alert');
        error.hidden = true;
        text.setAttribute('aria-describedby', error.id);
        const label = field.labels && field.labels[0];
        const title = label && label.querySelector('span');
        text.setAttribute('aria-label', title ? title.textContent.trim() : (field.getAttribute('aria-label') || 'Data'));
        tools.append(text, button, error);
        field.after(tools);

        let editing = false;
        let lastValue = '';
        let hasError = false;
        const withTime = () => field.type === 'datetime-local';
        const birthDate = () => ['birth_date', 'data_nascimento'].includes(field.name)
            || (field.name === 'valor' && field.closest('#identity-correction-modal'));

        function show(message) {
            hasError = Boolean(message);
            error.textContent = message;
            error.hidden = !hasError;
            text.setAttribute('aria-invalid', String(hasError));
            field.setCustomValidity(message);
        }

        function validate() {
            const raw = text.value.trim();
            const value = raw ? parse(raw, withTime()) : '';
            if (value === null) {
                show(withTime()
                    ? 'Informe uma data e hora válidas no formato DD/MM/AAAA HH:MM.'
                    : 'Informe uma data válida no formato DD/MM/AAAA.');
                return false;
            }
            if (!value && field.required) {
                show('Informe a data.');
                return false;
            }
            field.value = value;
            show('');
            if (value && birthDate() && value > new Date().toLocaleDateString('sv-SE')) {
                show('A data de nascimento não pode estar no futuro.');
                return false;
            }
            if (field.validity.rangeUnderflow || field.validity.rangeOverflow) {
                const limit = field.validity.rangeUnderflow ? field.min : field.max;
                show('Informe uma data ' + (field.validity.rangeUnderflow ? 'a partir de ' : 'até ') + display(limit) + '.');
                return false;
            }
            if (field.validity.stepMismatch) {
                show('Informe uma data e hora dentro do intervalo permitido.');
                return false;
            }
            return true;
        }

        function leave() {
            editing = false;
            text.hidden = true;
            field.classList.remove('date-entry-native-hidden');
            button.textContent = 'Digitar data';
            show('');
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            if (field.disabled || field.readOnly) return;

            if (!editing) {
                editing = true;
                lastValue = field.value;
                text.value = display(lastValue);
                text.placeholder = withTime() ? 'DD/MM/AAAA HH:MM' : 'DD/MM/AAAA';
                text.inputMode = withTime() ? 'text' : 'numeric';
                text.hidden = false;
                field.classList.add('date-entry-native-hidden');
                button.textContent = 'Usar calendário';
                text.focus();
                return;
            }

            const accepted = validate();
            if (!accepted) field.value = lastValue;
            leave();
            field.focus();
            if (typeof field.showPicker === 'function') {
                try { field.showPicker(); } catch (error) { /* O controle nativo permanece disponível. */ }
            }
            if (accepted && field.value !== lastValue) signal(field);
        });

        text.addEventListener('input', function () {
            show('');
            const value = parse(text.value.trim(), withTime());
            if (value !== null) field.value = value;
            else if (text.value.trim() === '') field.value = '';
        });
        text.addEventListener('blur', function (event) {
            if (!editing || event.relatedTarget === button) return;
            if (validate() && field.value !== lastValue) {
                lastValue = field.value;
                signal(field);
            }
        });
        field.addEventListener('invalid', function (event) {
            if (editing) {
                event.preventDefault();
                if (!validate()) text.focus();
            }
        });

        const editor = {
            syncType: function () {
                tools.hidden = !field.matches(selector);
                if (tools.hidden && editing) leave();
            },
            reset: leave,
            check: function () {
                if (field.disabled || field.readOnly || !field.matches(selector)) return true;
                if (!editing) {
                    if (field.validity.badInput || field.validity.rangeUnderflow || field.validity.rangeOverflow || field.validity.stepMismatch) {
                        field.reportValidity();
                        return false;
                    }
                    if (field.value && birthDate() && field.value > new Date().toLocaleDateString('sv-SE')) {
                        show('A data de nascimento não pode estar no futuro.');
                        field.focus();
                        return false;
                    }
                    return true;
                }
                if (validate()) return true;
                text.focus();
                return false;
            }
        };
        field.addEventListener('input', function () {
            if (!editing && hasError) show('');
        });
        editors.set(field, editor);
        return editor;
    }

    function scan(root) {
        if (root.nodeType !== 1 && root.nodeType !== 9) return;
        if (root.matches && root.matches(selector)) enhance(root).syncType();
        root.querySelectorAll(selector).forEach(field => enhance(field).syncType());
    }

    document.addEventListener('submit', function (event) {
        const fields = Array.from(event.target.elements || []);
        for (const field of fields) {
            const editor = editors.get(field);
            if (editor && !editor.check()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
        }
    }, true);

    document.addEventListener('reset', function (event) {
        Array.from(event.target.elements || []).forEach(field => {
            const editor = editors.get(field);
            if (editor) editor.reset();
        });
    }, true);

    scan(document);
    new MutationObserver(function (changes) {
        changes.forEach(change => {
            if (change.type === 'attributes') {
                const editor = editors.get(change.target);
                if (editor) editor.syncType();
                else if (change.target.matches(selector)) enhance(change.target);
                return;
            }
            change.addedNodes.forEach(scan);
        });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['type'] });
})();
