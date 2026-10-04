<?php
require __DIR__ . '/../app/Services/ScheduleGridService.php';
$service = new App\Services\ScheduleGridService();
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function lesson(int $id, string $start, string $end, string $days = '1'): array {
    return ['id' => $id, 'nome' => 'Turma ' . $id, 'hora_inicio' => $start, 'hora_fim' => $end, 'dias_semana' => $days];
}
$empty = $service->buildGrid([]);
foreach ($empty['periodos'] as $period) check(count($period['linhas']) === 3, 'Período vazio precisa de três blocos.');
$late = $service->buildGrid([lesson(1, '11:00:00', '11:50:00')]);
check($late['periodos'][0]['linhas'][2][1]['id'] === 1, 'Aula perto do almoço deve ficar no último bloco.');
$middle = $service->buildGrid([lesson(1, '09:00', '10:00', '3,7')]);
check($middle['periodos'][0]['linhas'][1][3]['id'] === 1, 'Aula no meio da manhã deve ficar no meio.');
check($middle['periodos'][0]['linhas'][1][7]['id'] === 1, 'Domingo precisa ser incluído.');
foreach ([
    [1, '12:30', '13:30', 0],
    [1, '14:30', '15:30', 1],
    [1, '17:00', '18:00', 2],
    [2, '18:00', '19:00', 0],
    [2, '19:30', '20:30', 1],
    [2, '21:00', '22:00', 2],
] as [$periodIndex, $start, $end, $expectedRow]) {
    $isolated = $service->buildGrid([lesson(1, $start, $end)]);
    check($isolated['periodos'][$periodIndex]['linhas'][$expectedRow][1]['id'] === 1, 'Aula isolada de ' . $start . ' deve ocupar o bloco correspondente ao horário.');
    foreach ($isolated['periodos'][$periodIndex]['linhas'] as $rowIndex => $row) {
        if ($rowIndex !== $expectedRow) check($row[1] === null, 'Os demais blocos da aula isolada devem ficar vazios.');
    }
}
foreach ([4, 5] as $number) {
    $items = [];
    for ($i = 0; $i < $number; $i++) $items[] = lesson($i + 1, sprintf('%02d:00', $i + 7), sprintf('%02d:50', $i + 7), '2,4');
    $grid = $service->buildGrid($items);
    check(count($grid['periodos'][0]['linhas']) === $number, 'A quantidade de linhas deve acompanhar o dia mais cheio.');
    foreach ($grid['periodos'][0]['linhas'] as $index => $row) {
        check($row[2]['id'] === $index + 1 && $row[4]['id'] === $index + 1, 'Todas as aulas devem aparecer em ordem, sem sobreposição.');
        check($row[1] === null, 'Os dias vazios devem permanecer vazios.');
    }
}
$sameBand = $service->buildGrid([lesson(1, '10:00', '10:20'), lesson(2, '10:20', '10:40'), lesson(3, '10:40', '11:00'), lesson(4, '11:00', '11:20'), lesson(5, '11:20', '11:40')]);
check(count($sameBand['periodos'][0]['linhas']) === 5, 'Cinco aulas próximas devem usar cinco linhas, não sete.');
$invalid = $service->buildGrid([lesson(1, '', ''), lesson(2, '25:00', '26:00'), lesson(3, '10:00', '09:00')]);
check($invalid['sem_horario'] === 3, 'Horários inválidos devem ser sinalizados.');
$crossing = $service->buildGrid([lesson(1, '11:30', '12:30')]);
check($crossing['periodos'][0]['linhas'][2][1]['id'] === 1 && $crossing['periodos'][1]['linhas'][0][1]['id'] === 1, 'Aula que atravessa períodos deve aparecer nos dois.');
echo "Grade validada: posição por horário, 3/4/5 blocos, dias, ordenação e horários inválidos.\n";
