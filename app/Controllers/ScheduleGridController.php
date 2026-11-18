<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ScheduleGridService;
use InvalidArgumentException;

class ScheduleGridController extends Controller
{
    public function index(): void
    {
        $professorId = 0;
        $professorOrigin = ($_GET['origem'] ?? null) === 'professor';
        if (\App\Core\Auth::check()) {
            $account = (new \App\Services\UserService())->currentAccountWithRoles();
            $roles = $account['roles'] ?? [];
            if (\App\Services\InternPermissionService::isRestricted($roles)) {
                $this->jsonResponse(['success' => false, 'message' => 'A consulta de grades de horário não está disponível para estagiários.'], 403);
                exit;
            }
            $hasAdminRole = array_intersect(array_column($roles, 'slug'), ['master_admin', 'admin', 'supervisor', 'coordinator']) !== [];
            if (has_role($roles, 'teacher') && ($professorOrigin || !$hasAdminRole)) {
                $professorId = (int) $account['conta_id'];
            }
        }
        $onlyMyClasses = $professorId > 0 && ($_GET['turmas'] ?? null) !== 'todas';
        $service = new ScheduleGridService();
        $error = null;
        $fieldOptions = ['turma' => 'Nome da turma', 'turma_id' => 'ID da turma', 'status' => 'Status da turma', 'modalidade' => 'Modalidade', 'professor' => 'Professor', 'professores_auxiliares' => 'Professores auxiliares', 'estagiarios' => 'Estagiários', 'horario' => 'Horário', 'idade' => 'Faixa etária', 'programa' => 'Programa', 'temporada' => 'Temporada no rodapé'];
        $fields = isset($_GET['campos_configurados']) ? array_values(array_intersect(array_keys($fieldOptions), (array) ($_GET['campos'] ?? []))) : ['turma', 'professor', 'horario', 'temporada'];
        $statuses = isset($_GET['status_configurados']) ? (array) ($_GET['status_turmas'] ?? []) : null;
        $excludedClasses = (array) ($_GET['turmas_excluidas'] ?? []);
        try {
            $data = $service->search(max(0, (int) ($_GET['temporada_id'] ?? 0)), max(0, (int) ($_GET['local_id'] ?? 0)), max(0, (int) ($_GET['espaco_id'] ?? 0)), $statuses, $excludedClasses, $professorId, $onlyMyClasses);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
            $data = $service->search(0, 0, 0, null, [], $professorId, $onlyMyClasses);
        }
        $disabledFields = $professorId > 0 && !$data['hasOwnClasses']
            ? ScheduleGridService::TEAM_FIELDS
            : [];
        $this->view('schedule_grid/index', $data + [
            'title' => 'Grades de horário', 'pageClass' => 'pagina-grades-horario', 'error' => $error,
            'fieldOptions' => $fieldOptions, 'fields' => array_diff($fields, $disabledFields),
            'disabledFields' => $disabledFields, 'professorView' => $professorId > 0, 'onlyMyClasses' => $onlyMyClasses,
            'sitePopupAtivo' => null,
        ]);
    }
}
