<?php
require __DIR__ . '/../app/Services/CourseEnrollmentService.php';
$service = new App\Services\CourseEnrollmentService();
$method = new ReflectionMethod($service, 'validateClassAttendanceDate');
$method->setAccessible(true);
$class = ['dias_semana' => '1,2,3,4,5,6,7', 'aulas_inicio' => '2026-10-05', 'aulas_fim' => '2026-10-20', 'cronograma_data_inicio' => '2026-01-01', 'cronograma_data_fim' => '2026-12-31'];
foreach (['2026-10-05', '2026-10-10', '2026-10-20'] as $date) {
    $method->invoke($service, $class, $date);
}
foreach (['2026-10-04', '2026-10-21', '2026-02-30'] as $date) {
    try { $method->invoke($service, $class, $date); }
    catch (RuntimeException $e) { continue; }
    throw new RuntimeException('Data indevida aceita: ' . $date);
}
foreach (['aulas_inicio', 'aulas_fim'] as $field) {
    $missing = $class;
    $missing[$field] = null;
    try { $method->invoke($service, $missing, '2026-10-10'); }
    catch (RuntimeException $e) { continue; }
    throw new RuntimeException('Cronograma incompleto aceito: ' . $field);
}
$class['dias_semana'] = '1';
try { $method->invoke($service, $class, '2026-10-06'); }
catch (RuntimeException $e) { echo "Período de chamada: testes concluídos.\n"; exit(0); }
throw new RuntimeException('Dia sem aula aceito.');
