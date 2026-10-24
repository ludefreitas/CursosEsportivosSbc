<?php
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
require ROOT_PATH . '/app/Services/TutorialPageService.php';
$tutorialPage = ['titulo' => 'Ajuda ao usuário', 'texto_introdutorio' => 'Precisa de ajuda? Assista aos vídeos explicativos abaixo.', 'videos' => [
    ['titulo' => 'Como criar uma conta', 'url' => 'https://youtu.be/abcdefghijk', 'embed_url' => 'https://www.youtube-nocookie.com/embed/abcdefghijk'],
    ['titulo' => 'Como enviar atestado clínico', 'tipo' => 'local', 'arquivo' => '/uploads/tutorial-videos/' . str_repeat('a', 32) . '.mp4', 'nome_arquivo' => 'atestado-clinico.mp4'],
]];
if (($argv[1] ?? '') === 'public') { require ROOT_PATH . '/app/Views/home/tutorial.php'; exit; }
require ROOT_PATH . '/app/Views/admin/partials/tutorial_page_editor.php';
