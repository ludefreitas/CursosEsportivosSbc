<?php
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH.'/app/Helpers/functions.php';
spl_autoload_register(function($class){if(str_starts_with($class,'App\\')){$file=ROOT_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file;}});
$_SESSION=[];
$person=$dependent=['id'=>987654,'nome_completo'=>'Pessoa de Exemplo','cpf'=>'52998224725','data_nascimento'=>'1990-01-01','cadastro_completo'=>1];
$responsiblePersonId=987654;$responsibleWhatsapp='11912345678';$canEditBirthDate=false;$isResponsiblePerson=false;
$user=['roles'=>[]];$locations=[];$spaces=[];$sectionName='locais-espacos';
$variant=$argv[1]??'profile';
$files=['profile'=>'profile/complete.php','create'=>'dashboard/partials/dependent_create_modal.php','edit'=>'dashboard/partials/dependent_modal.php','admin'=>'admin/partials/person_editor_modals.php','location'=>'admin/partials/section_content.php'];
if(!isset($files[$variant]))throw new RuntimeException('Formulário inválido.');
require ROOT_PATH.'/app/Views/'.$files[$variant];
