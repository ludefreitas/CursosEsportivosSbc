<?php
require __DIR__ . '/../app/Services/CourseEnrollmentService.php';
use App\Services\CourseEnrollmentService;
$service = new CourseEnrollmentService();
$tz = new DateTimeZone('America/Sao_Paulo');
$base = ['inscricoes_inicio'=>'2026-11-10 10:00:00','inscricoes_fim'=>'2026-11-10 23:59:00','matriculas_inicio'=>'2026-11-11 08:00:00','matriculas_fim'=>'2026-11-15 17:00:00','inscricoes_abertas_inicio'=>'2026-11-16 10:00:00','inscricoes_abertas_fim'=>'2026-11-30 23:59:00'];
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$window = $service->cpfEnrollmentWindow($base, new DateTimeImmutable('2026-11-03 12:00:00', $tz));
check(!$window['cpf_inscricao_aberta'] && $window['cpf_periodo_inicial'], 'A consulta futura deve mostrar o período inicial.');
check($window['cpf_inscricao_inicio_descricao']==='10/11/2026, às 10:00', 'Data em português.');
foreach (['2026-11-10 10:00:00','2026-11-10 23:59:00'] as $time) check($service->cpfEnrollmentWindow($base,new DateTimeImmutable($time,$tz))['cpf_inscricao_aberta'], 'Limites inclusivos.');
$window=$service->cpfEnrollmentWindow($base,new DateTimeImmutable('2026-11-12 12:00:00',$tz));
check(!$window['cpf_inscricao_aberta'] && !$window['cpf_periodo_inicial'] && $window['cpf_inscricao_inicio']==='2026-11-16 10:00:00','Respeitar reabertura após matrículas.');
$base['permitir_inscricao_periodo_matricula']=1;
check($service->cpfEnrollmentWindow($base,new DateTimeImmutable('2026-11-12 12:00:00',$tz))['cpf_inscricao_aberta'],'Matrículas podem admitir inscrições conforme configuração.');
check($service->cpfEnrollmentWindow($base,new DateTimeImmutable('2026-12-01',$tz))===null,'Não exibir períodos expirados.');
check($service->cpfEnrollmentWindow([])===null,'Não inventar datas não cadastradas.');
echo "OK: consulta futura, datas em português, abertura e fechamento, reabertura, matrículas e períodos expirados.\n";
