<?php
if (PHP_SAPI !== 'cli') exit;
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
$professorView = ($argv[1] ?? '') === 'professor';
$professorAccountId = 7;
$canAccessMasterSections = !$professorView;
$season = [
    'id'=>7,'nome'=>'Temporada de teste 2027','origem_temporada_id'=>1,'observacao'=>'Orientação de teste','tipo_periodicidade'=>'anual','status'=>'planejada',
    'data_inicio'=>'2027-01-02','data_fim'=>'2027-12-01',
    'inscricoes_inicio'=>'2027-01-03 08:00:00','inscricoes_fim'=>'2027-01-10 23:59:00',
    'matriculas_inicio'=>'2027-01-11 08:00:00','matriculas_fim'=>'2027-01-15 23:59:00',
    'inscricoes_abertas_inicio'=>'2027-01-16 08:00:00','inscricoes_abertas_fim'=>'2027-10-01 23:59:00',
    'aulas_inicio'=>'2027-02-01','aulas_fim'=>'2027-11-30','abrangencia_semanal'=>'segunda_sexta',
    'possui_edital'=>1,'numero_edital'=>'12/2027','link_edital'=>'https://example.test/edital',
    'permitir_inscricao_periodo_matricula'=>0,'permitir_multiplas_inscricoes_modalidade'=>1,'limite_inscricoes_modalidade'=>2,
    'data_liberacao_multiplas_inscricoes_modalidade'=>'2027-02-15 08:00:00',
    'limite_inscricoes_periodo'=>1,'limite_inscricoes_adicionais'=>3,'data_liberacao_segunda_inscricao'=>'2027-03-01 08:00:00',
    'data_liberacao_inscricoes_adicionais'=>'2027-04-01 08:00:00','permitir_inscricao_logada'=>1,'permitir_inscricao_por_cpf'=>0,
];
$courseSeasons = [$season];
$courseSeasonOrigins = [['id'=>1,'nome'=>'SESP','ativo'=>1]];
$courseModalitiesManagement = [['id'=>2,'nome'=>'Futsal','tipo_ambiente'=>'terrestre','ativo'=>1,'criador_conta_id'=>7]];
$modalitiesManagement = $courseModalitiesManagement;
$modalitySchedules = [array_merge($season, ['id'=>100,'temporada_id'=>7,'modalidade_id'=>2,'modalidade_nome'=>'Futsal','temporada_nome'=>$season['nome'],'nome'=>'Cronograma de teste','criador_conta_id'=>7,'data_inicio'=>'2027-01-01'])];
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><body><div id="admin-section-host" data-professor-mode="<?php echo $professorView ? '1' : '0'; ?>">
<?php
$sectionName = 'modalidades';
require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
if (!$professorView) {
    $courseManagementView = 'temporadas';
    require ROOT_PATH . '/app/Views/admin/partials/course_management_panel.php';
}
?>
</div></body></html>
