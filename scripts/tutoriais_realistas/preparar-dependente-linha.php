<?php
require __DIR__.'/preparar-acesso.php';
$dependent=['id'=>999998,'nome_completo'=>'Lucas Oliveira Santos','cpf'=>'11144477735','data_nascimento'=>'2015-06-15'];
ob_start();require ROOT_PATH.'/app/Views/dashboard/partials/dependent_row.php';file_put_contents(ROOT_PATH.'/tmp/tutorial-realista/dependente-linha.html',ob_get_clean());
