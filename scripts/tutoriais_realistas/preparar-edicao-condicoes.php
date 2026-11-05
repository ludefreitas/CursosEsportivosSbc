<?php
// Somente CLI: renderiza views reais com pessoas fictícias, sem acessar banco.
if (PHP_SAPI !== 'cli') { exit; }
define('ROOT_PATH', dirname(__DIR__,2));
require ROOT_PATH.'/app/Helpers/functions.php';
spl_autoload_register(function($class){if(str_starts_with($class,'App\\')){$file=ROOT_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file;}});
$_SERVER['REQUEST_URI']='/dashboard';$_SESSION=['account_id'=>999999];
$base=['telefone_whatsapp'=>'11987654321','email'=>'familia.exemplo@example.com','numero_cartao_sus'=>'','cep'=>'09710000','logradouro'=>'Rua Marechal Deodoro','numero_endereco'=>'100','complemento'=>'Apto 12','bairro'=>'Centro','cidade'=>'São Bernardo do Campo','uf'=>'SP','contato_emergencia_nome'=>'Ana Oliveira Santos','contato_emergencia_telefone'=>'11976543210','responsavel1_nome'=>'Mariana Oliveira Santos','responsavel1_cpf'=>'52998224725','responsavel2_nome'=>'','responsavel2_cpf'=>'','sexo'=>'masculino','cadastro_completo'=>1,'eh_pcd'=>0,'eh_plm'=>0,'eh_pvs'=>0];
$adult=$base+['id'=>999999,'nome_completo'=>'Mariana Oliveira Santos','cpf'=>'52998224725','data_nascimento'=>'1990-05-15'];$adult['sexo']='feminino';
$child=$base+['id'=>999998,'nome_completo'=>'Lucas Oliveira Santos','cpf'=>'11144477735','data_nascimento'=>'2015-06-15'];
$disabilityTypes=['auditiva'=>'Auditiva','visual'=>'Visual','intelectual'=>'Intelectual','fisica'=>'Física','autismo'=>'Autismo','tea'=>'TEA (Transtorno do Espectro Autista)'];
foreach(['editar-pessoa','condicao-pcd','condicao-plm','condicao-pvs'] as $kind){
 $dir=ROOT_PATH.'/tmp/tutorial-'.$kind;if(!is_dir($dir))mkdir($dir,0755,true);
 $slug=str_starts_with($kind,'condicao-')?substr($kind,9):'';
 $person=$adult;$dependent=$child;if($slug!=='')$dependent['eh_'.$slug]=1;
 $original=$dependent;$dependents=[$adult,$dependent];$user=$adult+['roles'=>[]];
 $metrics=['dependentes'=>2,'agendamentos_futuros'=>0,'documentos_pendentes'=>$slug!==''?1:0,'postagens_blog'=>0];
 $bookingHistory=['years'=>[2026],'year'=>2026,'bookings'=>[]];$courseEnrollments=[];$title='Meu painel';$pageClass='pagina-dashboard';$profileCompletionBlockMessage='';$footerContent=[];
 ob_start();require ROOT_PATH.'/app/Views/partials/header.php';require ROOT_PATH.'/app/Views/dashboard/index.php';require ROOT_PATH.'/app/Views/partials/footer.php';file_put_contents($dir.'/dashboard.html',ob_get_clean());
 foreach(['dependente','proprio'] as $who)foreach(['inicial','salvo'] as $state){
  $dependent=$who==='dependente'?$original:$adult;
  if($state==='salvo'){if($who==='dependente'){$dependent['nome_completo']='Lucas Oliveira dos Santos';$dependent['email']='lucas.exemplo@example.com';$dependent['numero_endereco']='120';$dependent['complemento']='Casa 2';$dependent['contato_emergencia_telefone']='11965432109';}else $dependent['telefone_whatsapp']='11912345678';}
  $canEditBirthDate=false;$isResponsiblePerson=$who==='proprio';$responsibleWhatsapp=$adult['telefone_whatsapp'];$responsiblePersonId=$adult['id'];
  ob_start();require ROOT_PATH.'/app/Views/dashboard/partials/dependent_modal.php';file_put_contents($dir.'/dados-'.$who.'-'.$state.'.html',ob_get_clean());
  ob_start();require ROOT_PATH.'/app/Views/dashboard/partials/dependent_row.php';file_put_contents($dir.'/linha-'.$who.'-'.$state.'.html',ob_get_clean());
 }
 if($slug!=='')foreach(['inicial','enviado'] as $state){
  $person=$original;$certificate=$state==='enviado'?['descricao_resumida'=>'Documentação comprobatória da condição — demonstração','codigo_cid_declarado'=>$slug==='pcd'?'H90.3':'M54.5','doenca_declarada'=>$slug==='pcd'?'Deficiência auditiva — exemplo fictício':'Condição descrita no laudo — exemplo fictício','numero_nis'=>'12345678901','tipos_deficiencia_pcd_lista'=>['auditiva'],'data_emissao'=>'2026-11-01','observacoes'=>'Documentos demonstrativos, sem valor comprobatório.','status'=>'pendente']:null;
  $conditions=[['slug'=>$slug,'label'=>condition_public_label($slug),'declared'=>true,'certificate'=>$certificate,'status_label'=>$state==='enviado'?'Documentação pendente de validação':'Documentação não enviada','documents'=>$state==='enviado'?[['id'=>900001,'nome_original'=>'documento-'.$slug.'-exemplo.pdf'],['id'=>900002,'nome_original'=>'complemento-'.$slug.'-exemplo.pdf']]:[],'disability_type_options'=>$disabilityTypes]];
  ob_start();require ROOT_PATH.'/app/Views/profile/partials/certificates_modal.php';file_put_contents($dir.'/condicao-'.$state.'.html',ob_get_clean());
 }
 echo $kind." preparado.\n";
}
