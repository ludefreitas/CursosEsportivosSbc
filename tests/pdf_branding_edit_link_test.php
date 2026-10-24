<?php
namespace App\Core { class Auth { public static function check(): bool { return ($GLOBALS['testRole']??'')!=='guest'; } } }
namespace App\Services { class UserService { public function currentAccountWithRoles(): array { return ['roles'=>[['slug'=>$GLOBALS['testRole']??'']]]; } } }
namespace {
require __DIR__.'/../app/Services/PdfBrandingSettings.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function url($v){return $v;}
if(($argv[1]??'')==='--render'){
    $GLOBALS['testRole']=$argv[2];$_SERVER['REQUEST_URI']=$argv[3]??'/grades-de-horario';$pdfModel='grade';require __DIR__.'/../app/Views/partials/pdf_branding_edit_link.php';exit;
}
foreach([
    ['master_admin','/grades-de-horario',true],
    ['master_admin','/admin',true],
    ['master_admin','/admin/cartazes',true],
    ['admin','/grades-de-horario',false],
    ['guest','/grades-de-horario',false],
    ['master_admin','/dashboard',false],
    ['master_admin','/cursos/declaracao?codigo=teste',false],
    ['master_admin','/cursos/frequencia',false],
    ['master_admin','/avaliacoes/certificado.pdf?id=1',false],
    ['master_admin','/',false]
] as [$role,$path,$allowed]){
    $process=proc_open([PHP_BINARY,__FILE__,'--render',$role,$path],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $html=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0||$error!=='')throw new RuntimeException($error);
    if(str_contains($html,'pdf_model=grade#admin-outras-areas')!==$allowed)throw new RuntimeException('Visibilidade incorreta: '.$role.' em '.$path);
    if($allowed&&!str_contains($html,'display:none!important'))throw new RuntimeException('Link sem ocultação na impressão.');
}
echo "Atalhos: administração e grade permitidas; painel, declaração, certificados e área pública sem links OK.\n";
}
