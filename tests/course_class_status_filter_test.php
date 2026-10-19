<?php
require __DIR__.'/../app/Services/CourseClassStatusFilter.php';
use App\Services\CourseClassStatusFilter;
function statusCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$classes=[
 ['id'=>1,'temporada_id'=>10,'temporada_nome'=>'2026','status'=>'planejada','status_label'=>'Planejada','modalidade_id'=>2,'modalidade_nome'=>'Alongamento','local_treino_id'=>3,'local_nome'=>'Alves Dias'],
 ['id'=>2,'temporada_id'=>10,'temporada_nome'=>'2026','status'=>'planejada','status_label'=>'Planejada','modalidade_id'=>2,'modalidade_nome'=>'Alongamento','local_treino_id'=>4,'local_nome'=>'Orquídeas'],
 ['id'=>3,'temporada_id'=>10,'temporada_nome'=>'2026','status'=>'inscricoes_abertas','status_label'=>'Inscrições abertas','modalidade_id'=>5,'modalidade_nome'=>'Ginástica','local_treino_id'=>3,'local_nome'=>'Alves Dias'],
 ['id'=>4,'temporada_id'=>11,'temporada_nome'=>'2027','status'=>'cancelada','status_label'=>'Cancelada','modalidade_id'=>6,'modalidade_nome'=>'Outra','local_treino_id'=>7,'local_nome'=>'Outro'],
];
$r=CourseClassStatusFilter::filter($classes,10,'',0,0);
statusCheck(count($r['statuses'])===2 && !isset($r['statuses']['cancelada']) && $r['classes']===[], 'A temporada deve restringir os status e aguardar seleção.');
$r=CourseClassStatusFilter::filter($classes,10,'planejada',0,0);
statusCheck(array_keys($r['modalities'])===[2] && $r['classes']===[], 'Status deve restringir modalidades.');
$r=CourseClassStatusFilter::filter($classes,10,'planejada',2,0);
statusCheck(array_keys($r['locations'])===[3,4] && $r['classes']===[], 'Modalidade deve restringir locais e aguardar escolha.');
$r=CourseClassStatusFilter::filter($classes,10,'planejada',2,4);
statusCheck(array_column($r['classes'],'id')===[2], 'Os quatro filtros devem ser aplicados juntos.');
$r=CourseClassStatusFilter::filter([$classes[0]],10,'planejada',2,4);
statusCheck($r['classes']===[] && $r['location_id']===0 && array_keys($r['seasons'])===[10], 'Professor não pode acessar turmas fora do conjunto atribuído.');
$r=CourseClassStatusFilter::filter($classes,11,'planejada',2,4);
statusCheck($r['status']==='' && $r['modality_id']===0 && $r['location_id']===0 && $r['classes']===[], 'Trocar temporada deve descartar filtros incompatíveis.');
$r=CourseClassStatusFilter::filter([],0,'',0,0);statusCheck($r['seasons']===[] && $r['classes']===[], 'Tratar professor sem turmas.');
echo "Status turmas: quatro filtros, opções encadeadas, troca de temporada e escopo do professor OK.\n";
