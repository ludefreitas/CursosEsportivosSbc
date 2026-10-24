<?php
$root=dirname(__DIR__,2);define('ROOT_PATH',$root);$_SESSION=[];
require $root.'/app/Helpers/functions.php';
spl_autoload_register(function($class){if(str_starts_with($class,'App\\')){$file=ROOT_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file;}});
$person=['id'=>999999,'nome_completo'=>'Mariana Oliveira Santos','cpf'=>'52998224725','data_nascimento'=>'1990-05-15'];
$returnTo='/dashboard';$canEditBirthDate=false;$registrationBlock=null;
if(!is_dir($root.'/tmp/tutorial-realista'))mkdir($root.'/tmp/tutorial-realista',0755,true);
ob_start();require $root.'/app/Views/profile/complete.php';file_put_contents($root.'/tmp/tutorial-realista/completar.html',ob_get_clean());
