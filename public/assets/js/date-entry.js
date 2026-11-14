(function () {
    'use strict';
    const selector = 'input[type="date"],input[type="datetime-local"]';
    const editors = new WeakMap();
    function pad(v) { return String(v).padStart(2, '0'); }
    function display(v) { const m = String(v || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/); return !m ? '' : m[4] ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : m[3] + '/' + m[2] + '/' + m[1]; }
    function parse(v, time) {
        const m = String(v || '').trim().match(time ? /^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/ : /^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!m) return null;
        const d = new Date(+m[3], +m[2] - 1, +m[1], time ? +m[4] : 12, time ? +m[5] : 0);
        if (d.getFullYear() !== +m[3] || d.getMonth() !== +m[2] - 1 || d.getDate() !== +m[1] || (time && (d.getHours() !== +m[4] || d.getMinutes() !== +m[5]))) return null;
        return m[3] + '-' + m[2] + '-' + m[1] + (time ? 'T' + m[4] + ':' + m[5] : '');
    }
    function mask(v, time) {
        v = String(v || '').trim().replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2})(?:T|\s)([0-9]{2}):([0-9]{2}).*$/, '$3/$2/$1 $4:$5').replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/, '$3/$2/$1');
        const n = v.replace(/\D/g, '').slice(0, time ? 12 : 8); let out = n.slice(0, 2);
        if (n.length > 2) out += '/' + n.slice(2, 4); if (n.length > 4) out += '/' + n.slice(4, 8); if (time && n.length > 8) out += ' ' + n.slice(8, 10); if (time && n.length > 10) out += ':' + n.slice(10, 12);
        return out;
    }
    function notify(field) { field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true })); }
    function enhance(field) {
        if (editors.has(field)) return editors.get(field);
        const isTime = () => field.type === 'datetime-local';
        const tools = document.createElement('span'); tools.className = 'date-entry-tools';
        const text = document.createElement('input'); text.type = 'text'; text.className = 'date-entry-text'; text.inputMode = 'numeric'; text.autocomplete = 'off';
        const calendar = document.createElement('button'); calendar.type = 'button'; calendar.className = 'date-entry-calendar-toggle'; calendar.innerHTML = '&#128197;'; calendar.setAttribute('aria-label', 'Abrir calendário');
        const error = document.createElement('small'); error.className = 'date-entry-error'; error.hidden = true;
        tools.append(text, calendar, error); field.after(tools); field.classList.add('date-entry-native-hidden');
        const caption = field.labels && field.labels[0] && field.labels[0].querySelector('span'); text.setAttribute('aria-label', caption ? caption.textContent.trim() : 'Data');
        function errorFor(message) { error.textContent = message; error.hidden = !message; text.setCustomValidity(message); field.setCustomValidity(message); }
        function sync() { if (document.activeElement !== text) text.value = display(field.value); text.placeholder = isTime() ? 'DD/MM/AAAA HH:MM' : 'DD/MM/AAAA'; }
        function check() { const raw = text.value.trim(); const value = raw ? parse(raw, isTime()) : ''; if (value === null) { errorFor(isTime() ? 'Informe data e hora no formato DD/MM/AAAA HH:MM.' : 'Informe a data no formato DD/MM/AAAA.'); return false; } field.value = value; if (!value && field.required) { errorFor('Informe a data.'); return false; } if (field.validity.rangeUnderflow || field.validity.rangeOverflow) { errorFor('Informe uma data dentro do período permitido.'); return false; } errorFor(''); return true; }
        text.addEventListener('input', function () { const pos = text.selectionStart; text.value = mask(text.value, isTime()); text.setSelectionRange(Math.min(pos, text.value.length), Math.min(pos, text.value.length)); errorFor(''); const value = parse(text.value, isTime()); if (value) field.value = value; });
        text.addEventListener('blur', function () { const old = field.value; if (check() && old !== field.value) notify(field); });
        function hideNativeCalendarField() { field.classList.add('date-entry-native-hidden'); }
        calendar.addEventListener('click', function () {
            if (field.disabled || field.readOnly) return;
            field.classList.remove('date-entry-native-hidden'); field.focus();
            try { if (typeof field.showPicker === 'function') field.showPicker(); } catch (e) { /* O calendário nativo permanece focado como alternativa. */ }
            window.setTimeout(function () { if (document.activeElement !== field) hideNativeCalendarField(); }, 150);
        });
        field.addEventListener('change', function () { sync(); hideNativeCalendarField(); });
        field.addEventListener('blur', function () { window.setTimeout(hideNativeCalendarField, 150); });
        sync();
        const editor = { check: check }; editors.set(field, editor); return editor;
    }
    function scan(root) { if (!root || (root.nodeType !== 1 && root.nodeType !== 9)) return; if (root.matches && root.matches(selector)) enhance(root); root.querySelectorAll(selector).forEach(enhance); }
    document.addEventListener('submit', function (event) { for (const field of Array.from(event.target.elements || [])) { const editor = editors.get(field); if (editor && !field.disabled && !editor.check()) { event.preventDefault(); field.focus(); return; } } }, true);
    scan(document); new MutationObserver(changes => changes.forEach(change => change.addedNodes.forEach(scan))).observe(document.body, { childList: true, subtree: true });
})();
