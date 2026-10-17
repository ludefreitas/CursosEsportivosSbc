<?php
require __DIR__ . '/../app/Services/CourseClassTimelineService.php';
use App\Services\CourseClassTimelineService;
function timelineCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$classes = [
    ['id'=>1,'temporada_id'=>10,'temporada_nome'=>'2026','modalidade_id'=>7,'cronograma_modalidade_id'=>100],
    ['id'=>2,'temporada_id'=>10,'temporada_nome'=>'2026','modalidade_id'=>8,'cronograma_modalidade_id'=>101],
    ['id'=>3,'temporada_id'=>20,'temporada_nome'=>'2027','modalidade_id'=>7,'cronograma_modalidade_id'=>102],
    ['id'=>4,'temporada_id'=>10,'temporada_nome'=>'2026','modalidade_id'=>7,'cronograma_modalidade_id'=>0],
];
$schedules = [
    ['id'=>100,'temporada_id'=>10,'modalidade_id'=>7,'inscricoes_inicio'=>'2026-10-01 08:00:00','inscricoes_fim'=>'2026-10-10 18:00:00','matriculas_inicio'=>'2026-10-11','matriculas_fim'=>'2026-10-15'],
    ['id'=>101,'temporada_id'=>10,'modalidade_id'=>8,'inscricoes_inicio'=>'2026-10-01 08:00:00','inscricoes_fim'=>'2026-10-10 18:00:00','matriculas_inicio'=>'2026-10-12','matriculas_fim'=>'2026-10-15'],
    ['id'=>102,'temporada_id'=>20,'modalidade_id'=>7,'inscricoes_inicio'=>'2027-01-01','inscricoes_fim'=>'2027-01-31'],
];
$key='inscricao-inicial|2026-10-01 08:00:00|2026-10-10 18:00:00';
$result=CourseClassTimelineService::build($classes,$schedules,10,$key);
timelineCheck(array_keys($result['groups'][$key]['classes']) === [1,2], 'Datas iguais devem reunir modalidades distintas sem incluir outra temporada ou turma sem cronograma.');
timelineCheck(count($result['groups']) === 3, 'Matrículas com datas diferentes devem gerar opções distintas.');
$result=CourseClassTimelineService::build([$classes[0]],$schedules,10,$key,7);
timelineCheck(array_keys($result['seasons']) === [10] && count($result['groups']) === 2, 'Filtros do professor devem usar apenas suas turmas, mesmo recebendo outros cronogramas.');
timelineCheck(array_column($result['classes'],'id') === [1], 'A lista do professor deve preservar o escopo recebido.');
$result=CourseClassTimelineService::build($classes,$schedules,20,$key);
timelineCheck($result['classes'] === [] && $result['period_key'] === '', 'A troca de temporada deve limpar o período anterior.');
$result=CourseClassTimelineService::build([],$schedules,10,$key);
timelineCheck($result['seasons'] === [] && $result['classes'] === [], 'Lista vazia não deve revelar filtros de outras turmas.');
$result=CourseClassTimelineService::build($classes,$schedules,10,$key);
timelineCheck($result['classes'] === [] && array_keys($result['modalities']) === [7,8], 'O período deve apresentar modalidades e aguardar seleção.');
$result=CourseClassTimelineService::build($classes,$schedules,10,$key,8);
timelineCheck(array_column($result['classes'],'id') === [2], 'A modalidade deve restringir as turmas do período.');
$result=CourseClassTimelineService::build($classes,$schedules,20,$key,8);
timelineCheck($result['modality_id'] === 0 && $result['classes'] === [], 'Seleção inválida deve limpar a modalidade.');
echo "Cronograma turmas: agrupamento por datas, troca de temporada, ausência de cronograma e escopo do professor OK.\n";
