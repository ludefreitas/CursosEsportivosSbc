<?php
require __DIR__.'/../app/Services/CourseClassBrowserFilter.php';
use App\Services\CourseClassBrowserFilter;
function browserCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$classes = [
 ['id'=>1,'temporada_id'=>1,'local_treino_id'=>10,'local_nome'=>'Alves Dias','modalidade_id'=>20,'modalidade_nome'=>'Alongamento'],
 ['id'=>2,'temporada_id'=>1,'local_treino_id'=>10,'local_nome'=>'Alves Dias','modalidade_id'=>21,'modalidade_nome'=>'Ginástica'],
 ['id'=>3,'temporada_id'=>1,'local_treino_id'=>11,'local_nome'=>'Orquídeas','modalidade_id'=>20,'modalidade_nome'=>'Alongamento'],
 ['id'=>4,'temporada_id'=>2,'local_treino_id'=>12,'local_nome'=>'Outro','modalidade_id'=>22,'modalidade_nome'=>'Outra'],
];
$r=CourseClassBrowserFilter::filter($classes,'turmas-locais',1,10,0);
browserCheck(array_keys($r['secondary_groups'])===[20,21] && $r['classes']===[], 'Local deve listar apenas suas modalidades e aguardar seleção.');
$r=CourseClassBrowserFilter::filter($classes,'turmas-locais',1,10,20);
browserCheck(array_column($r['classes'],'id')===[1],'Filtro local e modalidade incorreto.');
$r=CourseClassBrowserFilter::filter($classes,'turmas',1,20,0);
browserCheck(array_keys($r['secondary_groups'])===[10,11] && $r['classes']===[], 'Modalidade deve listar seus locais e aguardar seleção.');
$r=CourseClassBrowserFilter::filter($classes,'turmas',1,20,11);
browserCheck(array_column($r['classes'],'id')===[3], 'Filtro modalidade e local incorreto.');
$r=CourseClassBrowserFilter::filter($classes,'turmas-locais',1,12,22);
browserCheck($r['secondary_groups']===[] && $r['classes']===[], 'Não deve misturar temporadas.');
$r=CourseClassBrowserFilter::filter($classes,'turmas',1,21,11);
browserCheck($r['classes']===[], 'Não deve aceitar local sem turmas da modalidade.');
echo "Turmas: filtros encadeados por local/modalidade e isolamento por temporada OK.\n";
