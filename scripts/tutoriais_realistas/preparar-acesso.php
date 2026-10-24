<?php
require __DIR__.'/preparar.php';
$person+=['telefone_whatsapp'=>'11987654321'];$user=$person;$user['roles']=[];
$metrics=['dependentes'=>0,'agendamentos_futuros'=>0,'documentos_pendentes'=>0,'postagens_blog'=>0];
$bookingHistory=['years'=>[2026],'year'=>2026,'bookings'=>[]];$dependents=[];$courseEnrollments=[];
ob_start();require ROOT_PATH.'/app/Views/dashboard/index.php';file_put_contents(ROOT_PATH.'/tmp/tutorial-realista/dashboard.html',ob_get_clean());
