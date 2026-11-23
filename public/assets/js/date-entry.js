(function () {
    'use strict';
    const selector = 'input[type="date"],input[type="datetime-local"]';
    const editors = new WeakMap();
    const proxies = new WeakSet();
    const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    function display(value, time) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
        if (!match) return '';
        return match[3] + '/' + match[2] + '/' + match[1] + (time && match[4] ? ' ' + match[4] + ':' + match[5] : '');
    }
    function parse(value, time) {
        const match = String(value).trim().match(time ? /^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/ : /^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match || +match[3] < 1) return null;
        const date = new Date(0);
        date.setUTCFullYear(+match[3], +match[2] - 1, +match[1]);
        date.setUTCHours(time ? +match[4] : 12, time ? +match[5] : 0, 0, 0);
        if (date.getUTCFullYear() !== +match[3] || date.getUTCMonth() !== +match[2] - 1 || date.getUTCDate() !== +match[1]
            || (time && (date.getUTCHours() !== +match[4] || date.getUTCMinutes() !== +match[5]))) return null;
        return match[3] + '-' + match[2] + '-' + match[1] + (time ? 'T' + match[4] + ':' + match[5] : '');
    }
    function mask(value, time) {
        value = String(value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}(?:$|[T ])/.test(value)) value = display(value, time);
        const digits = value.replace(/\D/g, '').slice(0, time ? 12 : 8);
        let result = digits.slice(0, 2);
        if (digits.length > 2) result += '/' + digits.slice(2, 4);
        if (digits.length > 4) result += '/' + digits.slice(4, 8);
        if (time && digits.length > 8) result += ' ' + digits.slice(8, 10);
        if (time && digits.length > 10) result += ':' + digits.slice(10, 12);
        return result;
    }
    function caretFor(value, digits) {
        if (!digits) return 0;
        for (let index = 0, seen = 0; index < value.length; index++) {
            if (/\d/.test(value[index]) && ++seen === digits) return index + 1;
        }
        return value.length;
    }
    function enhance(field) {
        if (editors.has(field)) return editors.get(field);
        // Uma cópia de formulário pode trazer o HTML da máscara, sem seus eventos.
        if (field.nextElementSibling?.classList.contains('date-entry-tools')) field.nextElementSibling.remove();
        const isTime = () => field.type === 'datetime-local';
        const tools = document.createElement('span'); tools.className = 'date-entry-tools';
        const text = document.createElement('input'); text.type = 'text'; text.className = 'date-entry-text'; text.inputMode = 'numeric'; text.autocomplete = 'off';
        const calendar = document.createElement('button'); calendar.type = 'button'; calendar.className = 'date-entry-calendar-toggle'; calendar.innerHTML = '&#128197;'; calendar.setAttribute('aria-label', 'Abrir calendário');
        const error = document.createElement('small'); error.className = 'date-entry-error'; error.hidden = true;
        let localError = '';
        let writingText = false;
        let committed = field.value;
        let maskedType = field.type;
        function clearError() {
            if (localError && field.validity.customError && field.validationMessage === localError) field.setCustomValidity('');
            localError = '';
            text.setCustomValidity('');
            error.textContent = ''; error.hidden = true;
        }
        function errorFor(message, external = false) {
            text.setCustomValidity(message); error.textContent = message; error.hidden = !message;
            if (!external) { localError = message; field.setCustomValidity(message); }
        }
        function sync(force = false) {
            if (!writingText && (force || document.activeElement !== text)) text.value = display(field.value, isTime());
            text.placeholder = isTime() ? 'DD/MM/AAAA HH:MM' : 'DD/MM/AAAA';
            text.disabled = field.disabled;
            text.readOnly = field.readOnly;
            text.required = field.required;
            calendar.disabled = field.disabled || field.readOnly;
            tools.hidden = field.hidden || field.classList.contains('hidden') || !field.matches(selector);
            if (maskedType !== field.type) {
                maskedType = field.type;
                if (field.matches(selector)) hideNative();
                else if (field.classList.contains('date-entry-native-hidden')) field.classList.remove('date-entry-native-hidden');
            }
            const label = field.labels?.[0];
            const caption = label?.querySelector(':scope > span:not(.date-entry-tools)');
            const labelText = label ? Array.from(label.childNodes).filter(node => node.nodeType === 3).map(node => node.textContent.trim()).join(' ').trim() : '';
            text.setAttribute('aria-label', field.getAttribute('aria-label') || caption?.textContent.trim() || labelText || 'Data');
        }
        function setNative(value) { nativeValue.set.call(field, value); }
        function dispatch(type) {
            writingText = true;
            try { field.dispatchEvent(new Event(type, { bubbles: true })); } finally { writingText = false; }
        }
        function check() {
            if (field.disabled || field.readOnly || field.hidden || field.closest('.hidden,[hidden]') || !field.matches(selector)) return true;
            clearError();
            const raw = text.value.trim();
            const value = raw ? parse(raw, isTime()) : '';
            if (value === null) {
                setNative('');
                errorFor(isTime() ? 'Informe data e hora no formato DD/MM/AAAA HH:MM.' : 'Informe a data no formato DD/MM/AAAA.');
                return false;
            }
            setNative(value);
            if (!value && field.required) { errorFor('Informe a data.'); return false; }
            if (field.validity.rangeUnderflow || field.validity.rangeOverflow) { errorFor('Informe uma data dentro do período permitido.'); return false; }
            if (!field.validity.valid) { errorFor(field.validationMessage, true); return false; }
            return true;
        }
        function edited() {
            const raw = text.value;
            const end = text.selectionStart ?? raw.length;
            const atEnd = end === raw.length;
            const before = raw.slice(0, end).replace(/\D/g, '').length;
            text.value = mask(raw, isTime());
            const caret = atEnd ? text.value.length : caretFor(text.value, before);
            text.setSelectionRange(caret, caret);
            clearError();
            const old = field.value;
            setNative(parse(text.value, isTime()) || '');
            if (old !== field.value) dispatch('input');
        }
        text.addEventListener('input', edited);
        text.addEventListener('beforeinput', function (event) {
            if (!['deleteContentBackward', 'deleteContentForward'].includes(event.inputType) || text.selectionStart !== text.selectionEnd) return;
            const backward = event.inputType === 'deleteContentBackward';
            const start = text.selectionStart;
            let index = backward ? start - 1 : start;
            if (index < 0 || index >= text.value.length || /\d/.test(text.value[index])) return;
            while (index >= 0 && index < text.value.length && !/\d/.test(text.value[index])) index += backward ? -1 : 1;
            if (index < 0 || index >= text.value.length) return;
            event.preventDefault();
            text.value = text.value.slice(0, index) + text.value.slice(index + 1);
            const position = backward ? index : start;
            text.setSelectionRange(position, position);
            text.dispatchEvent(new Event('input', { bubbles: true }));
        });
        text.addEventListener('blur', function () {
            if (check() && committed !== field.value) { committed = field.value; dispatch('change'); }
        });
        function hideNative() { if (!field.classList.contains('date-entry-native-hidden')) field.classList.add('date-entry-native-hidden'); }
        calendar.addEventListener('click', function (event) {
            event.preventDefault();
            if (field.disabled || field.readOnly) return;
            field.classList.remove('date-entry-native-hidden'); field.focus();
            try { if (typeof field.showPicker === 'function') field.showPicker(); } catch (ignored) { /* Mantém o campo nativo acessível como alternativa. */ }
            window.setTimeout(function () { if (document.activeElement !== field) hideNative(); }, 150);
        });
        field.addEventListener('input', function () { if (!writingText) { clearError(); sync(true); } });
        field.addEventListener('change', function () {
            if (!writingText) { clearError(); committed = field.value; sync(true); }
            hideNative();
        });
        field.addEventListener('blur', function () { window.setTimeout(hideNative, 150); });
        field.addEventListener('invalid', function (event) { event.preventDefault(); check(); text.focus(); });
        // Atualiza a máscara também em preenchimentos por .value e jQuery.val(), sem
        // emitir mudanças artificiais que acionariam filtros ou cópias de formulários.
        Object.defineProperty(field, 'value', {
            configurable: true,
            get() { return nativeValue.get.call(this); },
            set(value) { nativeValue.set.call(this, value); committed = this.value; clearError(); sync(true); }
        });
        const editor = { text, tools, check, focus: () => text.focus(), sync, reset: () => { clearError(); committed = field.value; sync(true); } };
        editors.set(field, editor);
        proxies.add(text);
        tools.append(text, calendar, error); field.after(tools); hideNative(); sync();
        return editor;
    }
    function scan(root) {
        if (!root || (root.nodeType !== 1 && root.nodeType !== 9)) return;
        if (root.matches?.(selector)) enhance(root);
        root.querySelectorAll(selector).forEach(enhance);
    }
    const App = window.App = window.App || {};
    App.dateEntry = {
        isProxy: field => proxies.has(field),
        inputFor: field => field?.matches?.(selector) ? editors.get(field)?.text || field : field,
        toolsFor: field => field?.matches?.(selector) ? editors.get(field)?.tools || null : null,
        validationMessageFor(field) {
            const raw = field?.matches?.(selector) ? editors.get(field)?.text.value.trim() : '';
            return raw && parse(raw, field.type === 'datetime-local') === null
                ? (field.type === 'datetime-local' ? 'Informe data e hora no formato DD/MM/AAAA HH:MM.' : 'Informe a data no formato DD/MM/AAAA.') : '';
        }
    };
    document.addEventListener('submit', function (event) {
        for (const field of Array.from(event.target.elements || [])) {
            const editor = editors.get(field);
            if (editor && !editor.check()) {
                event.preventDefault(); event.stopImmediatePropagation(); editor.focus(); return;
            }
        }
    }, true);
    document.addEventListener('reset', function (event) {
        window.setTimeout(function () {
            for (const field of Array.from(event.target.elements || [])) editors.get(field)?.reset();
        }, 0);
    }, true);
    scan(document);
    new MutationObserver(function (changes) {
        changes.forEach(function (change) {
            if (change.type === 'attributes') {
                const editor = editors.get(change.target);
                if (editor) editor.sync(['value','type'].includes(change.attributeName)); else if (change.target.matches?.(selector)) enhance(change.target);
            } else change.addedNodes.forEach(scan);
        });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['value','type','disabled','readonly','required','min','max','step','hidden','class','aria-label'] });
})();
