<?php
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
require ROOT_PATH . '/app/Services/TutorialPageService.php';
$_SESSION['staff_help_csrf']='token-demonstracao';
$tutorialHelp=['titulo'=>'Ajuda e tutoriais','texto_introdutorio'=>'Vídeos de gestão','videos'=>[['titulo'=>'Como criar uma turma','tipo'=>'youtube','url'=>'https://youtu.be/abcdefghijk','embed_url'=>'https://www.youtube-nocookie.com/embed/abcdefghijk']]];
$canManageTutorialHelp=($argv[1]??'')==='admin';
$tutorialHelpLibraries=['admin'=>$tutorialHelp,'professor'=>$tutorialHelp];
require ROOT_PATH.'/app/Views/admin/partials/staff_help_panel.php';
