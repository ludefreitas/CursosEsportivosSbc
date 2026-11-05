<?php
// Renderização CLI das telas reais. Não instancia serviços nem acessa o banco.
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
spl_autoload_register(function ($class) { if (str_starts_with($class, 'App\\')) require_once ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php'; });
$_SERVER['REQUEST_URI'] = '/admin'; $_SESSION = ['account_id' => 999999];
$user = ['conta_id' => 999999, 'nome_completo' => 'Administrador de demonstração', 'roles' => ['master_admin'], 'cadastro_completo' => 1];
$canAccessMasterSections = true; $canEditProfessorPage = true;
$courseSeasons = [['id'=>9001,'nome'=>'Temporada demonstrativa 2027','tipo_periodicidade'=>'anual','data_inicio'=>'2027-01-01','data_fim'=>'2027-12-31','inscricoes_inicio'=>'2027-02-01 08:00:00','inscricoes_fim'=>'2027-02-05 23:59:00','matriculas_inicio'=>'2027-02-08 08:00:00','matriculas_fim'=>'2027-02-12 23:59:00','inscricoes_abertas_inicio'=>'2027-02-15 08:00:00','inscricoes_abertas_fim'=>'2027-11-30 23:59:00','aulas_inicio'=>'2027-02-15','aulas_fim'=>'2027-12-17','abrangencia_semanal'=>'segunda_sexta','status'=>'planejada','limite_inscricoes_periodo'=>1,'permitir_inscricao_logada'=>1,'permitir_inscricao_por_cpf'=>1,'observacao'=>'Dados de demonstração. Datas ilustrativas.']];
$courseModalitiesManagement = $modalities = [['id'=>9002,'nome'=>'Alongamento demonstrativo','tipo_ambiente'=>'terrestre','ativo'=>1,'criador_nome'=>'Administrador de demonstração']];
$modalitySchedules = [$courseSeasons[0] + ['id'=>9003,'temporada_id'=>9001,'modalidade_id'=>9002,'nome_cronograma'=>'Alongamento — cronograma demonstrativo','modalidade_nome'=>'Alongamento demonstrativo','temporada_nome'=>'Temporada demonstrativa 2027','total_turmas'=>0]];
$modalitySchedules[0]['id']=9003; $modalitySchedules[0]['nome']='Alongamento — cronograma demonstrativo';
$modalitySchedules[0]['limite_inscricoes_modalidade']=2;
$courseLocationsManagement = [['id'=>9004,'apelido_local'=>'Centro Esportivo de Demonstração']];
$courseSpacesManagement = [['id'=>9005,'nome'=>'Sala de atividades','local_treino_id'=>9004]];
$courseProfessors = [['id'=>999999,'conta_id'=>999999,'nome_completo'=>'Professor de demonstração']];
$courseSeasonOrigins=[['id'=>9006,'nome'=>'Secretaria de Esportes — demonstração','ativo'=>1]];
$courseClassesManagement=[]; $footerContent=[]; $title='Área administrativa'; $pageClass='admin-page'; $profileCompletionBlockMessage='';
$dir=ROOT_PATH.'/tmp/tutorial-gestao'; if(!is_dir($dir)) mkdir($dir,0755,true);
ob_start(); require ROOT_PATH.'/app/Views/partials/header.php'; require ROOT_PATH.'/app/Views/admin/index.php'; require ROOT_PATH.'/app/Views/partials/footer.php'; file_put_contents($dir.'/admin.html',ob_get_clean());
foreach(['temporadas','turmas','modalidades'] as $sectionName){$courseManagementView=$sectionName;ob_start();require ROOT_PATH.'/app/Views/admin/partials/section_content.php';file_put_contents($dir.'/'.$sectionName.'.html',ob_get_clean());}
echo "Telas de gestão renderizadas sem acesso ao banco.\n";
