const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('public/assets/js/admin.js', 'utf8');
const begin = source.indexOf('            function agendaEventsForDay(');
const end = source.indexOf('            function openAgendaDayModal(', begin);
const events = [
 { id: 'late', startStr: '2026-10-20T10:00:00-03:00' },
 { id: 'other', startStr: '2026-10-21T08:00:00-03:00' },
 { id: 'early', startStr: '2026-10-20T08:00:00-03:00' }
];
const context = { App: { state: { adminAgendaCalendar: { getEvents: () => events } } } };
vm.createContext(context);
vm.runInContext(source.slice(begin, end), context);
assert.deepStrictEqual(Array.from(context.agendaEventsForDay('2026-10-20'), e => e.id), ['early', 'late']);
assert.strictEqual(context.agendaEventsForDay('2026-10-22').length, 0);
assert.strictEqual(context.agendaEventsForDay(new Date(2026, 9, 21)).length, 1);
console.log('Calendário: agrupa por dia, ordena horários e preserva datas locais OK.');
