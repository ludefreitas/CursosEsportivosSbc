<?php
if(PHP_SAPI!=='cli')exit;
define('ROOT_PATH',dirname(__DIR__,2));require ROOT_PATH.'/app/Helpers/functions.php';
$professorView=false;$peopleUsersTotals=['people'=>6];$peopleLimit=10;$peopleLimitMax=40;$peopleSearch='';$peopleConditions=[];
$source=file_get_contents(ROOT_PATH.'/app/Views/admin/partials/people_panel.php');
eval('?>'.substr($source,0,strpos($source,'    <div class="table-wrap">')));
echo '<table class="data-table"><tbody><tr><td>Pessoa demonstrativa</td></tr></tbody></table></article></div>';
