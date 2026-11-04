<?php
if (PHP_SAPI !== 'cli') { exit; }
define('ROOT_PATH', dirname(__DIR__, 2));
require __DIR__ . '/../../app/Helpers/functions.php';
$_SESSION['class_age_criterion_csrf'] = 'token-teste';
$professorView = true;
$base = ['temporada_id'=>1,'temporada_nome'=>'2026 Validação do Novo Sistema','local_treino_id'=>1,'local_nome'=>'Baetinha','modalidade_id'=>1,'modalidade_nome'=>'Alongamento','espaco_nome'=>'Sala','dias_semana_descricao'=>'Segunda e quarta','hora_inicio'=>'19:00','hora_fim'=>'19:50','professor_principal_nome'=>'Professor de exemplo','status'=>'planejada','status_label'=>'Planejada','faixa_etaria_descricao'=>'Nascidos entre 1927 e 2008'];
$ageCriterionClasses = [array_merge($base,['id'=>21,'nome'=>'Alongamento','criterio_faixa_etaria'=>'ano_nascimento']),array_merge($base,['id'=>505,'nome'=>'Aula de Alongamento','criterio_faixa_etaria'=>'idade_exata']),array_merge($base,['id'=>506,'nome'=>'Outra temporada','temporada_id'=>2,'temporada_nome'=>'2027','local_treino_id'=>2,'local_nome'=>'Orquídeas','criterio_faixa_etaria'=>'idade_exata'])];
require __DIR__ . '/../../app/Views/admin/partials/class_age_criterion_panel.php';
