(function () {
    'use strict';
    const location = document.querySelector('[data-grid-location]');
    const space = document.querySelector('[data-grid-space]');
    if (location && space) {
        location.addEventListener('change', function () {
            // Descarta o espaço anterior antes de carregar os espaços do novo local.
            space.value = '0';
            space.disabled = true;
            location.form.requestSubmit();
        });
    }
    if (space) space.addEventListener('change', function () { space.form.requestSubmit(); });
    document.querySelectorAll('[data-grid-class]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const inputs = checkbox.form.querySelector('[data-grid-excluded-inputs]');
            const id = checkbox.dataset.gridClass;
            const existing = Array.from(inputs.querySelectorAll('input')).find(function (input) { return input.value === id; });
            if (checkbox.checked && existing) existing.remove();
            if (!checkbox.checked && !existing) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'turmas_excluidas[]'; input.value = id;
                inputs.appendChild(input);
            }
            checkbox.form.requestSubmit();
        });
    });
    const printButton = document.querySelector('[data-grid-print]');
    document.querySelectorAll('[data-grid-status]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (location && location.value) checkbox.form.requestSubmit();
        });
    });
    document.querySelectorAll('[data-grid-field]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            document.querySelectorAll('[data-grid-content="' + checkbox.dataset.gridField + '"]').forEach(function (element) {
                element.hidden = !checkbox.checked;
            });
            fitSheets();
        });
    });
    if (printButton) printButton.addEventListener('click', function () { fitSheets(); window.print(); });
    function fitSheets() {
        document.querySelectorAll('.schedule-grid-table').forEach(function (table) {
            table.style.fontSize = '';
            const events = Array.from(table.querySelectorAll('.schedule-grid-event'));
            const fieldCount = Math.max(0, ...events.map(function (event) {
                return Array.from(event.children).filter(function (child) { return !child.hidden; }).length;
            }));
            table.style.setProperty('--grid-event-gap', fieldCount > 3 ? '.2mm' : table.tBodies[0].rows.length > 3 ? '.3mm' : '.7mm');
            let size = parseFloat(window.getComputedStyle(table).fontSize);
            function overflows() {
                return Array.from(table.querySelectorAll('td')).some(function (cell) {
                    const event = cell.querySelector('.schedule-grid-event');
                    if (!event) return false;
                    const style = window.getComputedStyle(cell);
                    const available = cell.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
                    return event.getBoundingClientRect().height > available + 0.5 || event.scrollWidth > cell.clientWidth;
                });
            }
            while (overflows() && size > 3) {
                size -= 0.25;
                table.style.fontSize = size + 'px';
            }
        });
    }
    window.addEventListener('beforeprint', fitSheets);
    window.addEventListener('load', fitSheets);
    fitSheets();
}());
