<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AdminService;
use App\Services\AgendaService;
use App\Services\ProfileService;
use App\Services\UserService;
use DateTimeImmutable;

class ProfessorController extends Controller
{
    private AdminService $adminService;
    private UserService $userService;

    public function __construct()
    {
        $this->adminService = new AdminService();
        $this->userService = new UserService();
    }

    public function index(): void
    {
        $user = $this->assertProfessorAccess();
        $this->view('professor/index', ['title' => 'Área do professor', 'user' => $user, 'pageClass' => 'professor-page', 'professorPageConfig' => (new \App\Services\ProfessorPageService())->get()]);
    }

    public function section(): void
    {
        $user = $this->assertProfessorAccess();
        $sectionName = (string) ($_GET['nome'] ?? 'inicio');
        if (!in_array($sectionName, ['inicio', 'usuarios-pessoas', 'inscricoes', 'minhas-turmas', 'agenda'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'A seção não está disponível para professores.'], 403);
            return;
        }

        try {
            $data = $this->buildSectionData($sectionName, $user);
            ob_start();
            extract($data, EXTR_SKIP);
            require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function peoplePanel(): void
    {
        $this->assertProfessorAccess();
        try {
            $data = $this->buildPeopleData();
            extract($data, EXTR_SKIP);
            $professorView = true;
            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/people_panel.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function personDetails(): void
    {
        $this->assertProfessorAccess();
        try {
            $person = $this->maskCpfData($this->adminService->getPersonDetails((int) ($_GET['id'] ?? 0)));
            $this->jsonResponse(['success' => true, 'person' => $person]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function userDetails(): void
    {
        $this->assertProfessorAccess();
        try {
            $user = $this->maskCpfData($this->adminService->getUserDetails((int) ($_GET['id'] ?? 0)));
            $this->jsonResponse(['success' => true, 'user' => $user]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function userDependents(): void
    {
        $this->assertProfessorAccess();
        try {
            $payload = $this->adminService->listUserDependents((int) ($_GET['conta_id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'user' => $this->maskCpfData($payload['user'] ?? []),
                'dependents' => array_map(fn (array $dependent): array => $this->maskCpfData($dependent), $payload['dependents'] ?? []),
            ]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function calendarEvents(): void
    {
        $this->assertProfessorAccess();
        try {
            $events = $this->adminService->listCalendarEventsForManagement(
                (int) ($_GET['local_treino_id'] ?? 0),
                (int) ($_GET['modalidade_id'] ?? 0),
                trim((string) ($_GET['start'] ?? '')),
                trim((string) ($_GET['end'] ?? ''))
            );
            $this->jsonResponse($events);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function classBrowser(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $result = (new \App\Services\CourseEnrollmentService())->professorClassBrowser(
                (int) ($user['conta_id'] ?? 0),
                (int) ($_GET['temporada_id'] ?? 0),
                (int) ($_GET['local_treino_id'] ?? 0),
                (int) ($_GET['modalidade_id'] ?? 0)
            );
            if (($result['stage'] ?? '') === 'classes') {
                $courseClasses = (array) ($result['classes'] ?? []);
                $courseManagementView = 'professor-turmas';
                ob_start();
                require ROOT_PATH . '/app/Views/admin/partials/course_class_card_list.php';
                $result['html'] = (string) ob_get_clean();
                unset($result['classes']);
            }
            $this->jsonResponse(array_merge(['success' => true], $result));
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveAssignedClass(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $service = new \App\Services\CourseEnrollmentService();
            $classId = (int) ($_POST['id'] ?? 0);
            if (($_POST['operacao'] ?? '') !== 'editar' || !$service->professorIsAssignedToClass((int) $user['conta_id'], $classId)) {
                throw new \RuntimeException('Você não possui acesso a esta turma ou não está mais atribuído a ela.');
            }
            $service->createClass((int) $user['conta_id'], $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Turma atualizada com sucesso.', 'professor_class_refresh' => true]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function saveAssignedClassTeam(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $service = new \App\Services\CourseEnrollmentService();
            $classId = (int) ($_POST['turma_id'] ?? 0);
            if (!$service->professorIsAssignedToClass((int) $user['conta_id'], $classId)) {
                throw new \RuntimeException('Você não possui acesso a esta turma ou não está mais atribuído a ela.');
            }
            $service->assignClassTeam($classId, (int) ($_POST['professor_principal_conta_id'] ?? 0), (array) ($_POST['professor_auxiliar_conta_ids'] ?? []), (array) ($_POST['estagiario_conta_ids'] ?? []), (int) $user['conta_id']);
            $stillAssigned = $service->professorIsAssignedToClass((int) $user['conta_id'], $classId);
            $this->jsonResponse(['success' => true, 'message' => 'Equipe da turma atualizada com sucesso.', 'professor_class_refresh' => true, 'remove_class_id' => $stillAssigned ? 0 : $classId]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function changeAssignedClassStatus(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $service = new \App\Services\CourseEnrollmentService();
            $classId = (int) ($_POST['turma_id'] ?? 0);
            if (!$service->professorIsAssignedToClass((int) $user['conta_id'], $classId)) {
                throw new \RuntimeException('Você não possui acesso a esta turma ou não está mais atribuído a ela.');
            }
            $result = $service->setClassOperationalStatus($classId, trim((string) ($_POST['status'] ?? '')), (int) $user['conta_id']);
            $this->jsonResponse(array_merge(['success' => true, 'message' => 'Status da turma alterado para “' . $result['status_label'] . '”.'], $result));
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function courseClassAttendance(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $service = new \App\Services\CourseEnrollmentService();
            $classId = (int) ($_GET['turma_id'] ?? 0);
            if (!$service->professorIsAssignedToClass((int) $user['conta_id'], $classId)) { throw new \RuntimeException('Você não está atribuído a esta turma.'); }
            $attendance = $service->classAttendanceRoster($classId, trim((string) ($_GET['data'] ?? '')));
            ob_start(); require ROOT_PATH . '/app/Views/admin/partials/course_class_attendance.php'; $html = (string) ob_get_clean();
            $this->jsonResponse(['success' => true, 'html' => $html]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function saveCourseClassAttendance(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $service = new \App\Services\CourseEnrollmentService();
            $classId = (int) ($_POST['turma_id'] ?? 0);
            if (!$service->professorIsAssignedToClass((int) $user['conta_id'], $classId)) { throw new \RuntimeException('Você não está atribuído a esta turma.'); }
            $service->saveClassAttendance($classId, (int) ($_POST['inscricao_id'] ?? 0), trim((string) ($_POST['data'] ?? '')), trim((string) ($_POST['status'] ?? '')), trim((string) ($_POST['justificativa'] ?? '')), (int) $user['conta_id']);
            $this->jsonResponse(['success' => true, 'message' => 'Chamada atualizada.']);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    /**
     * Cria um horário semanal pela área do professor.
     */
    public function storeWeeklySchedule(): void
    {
        $user = $this->assertProfessorAccess();

        try {
            $this->adminService->createWeeklySchedule((int) ($user['conta_id'] ?? 0), $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário semanal criado com sucesso.',
                ]);
            }

            flash('success', 'Horário semanal criado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/professor');
    }

    public function weeklyScheduleDetails(): void
    {
        $user = $this->assertProfessorAccess();

        try {
            $this->jsonResponse([
                'success' => true,
                'schedule' => $this->adminService->getWeeklyScheduleDetails((int) ($_GET['id'] ?? 0), (int) ($user['conta_id'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateWeeklySchedule(): void
    {
        $user = $this->assertProfessorAccess();

        try {
            $schedule = $this->adminService->updateWeeklySchedule(
                (int) ($_POST['horario_semanal_id'] ?? 0),
                (int) ($user['conta_id'] ?? 0),
                $_POST,
                true
            );
            $this->jsonResponse([
                'success' => true,
                'message' => 'Horário semanal atualizado com sucesso.',
                'schedule' => $schedule,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deactivateWeeklySchedule(): void
    {
        $user = $this->assertProfessorAccess();

        try {
            $this->adminService->deactivateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0), (int) ($user['conta_id'] ?? 0));
            $this->jsonResponse(['success' => true, 'message' => 'Horário semanal inativado com sucesso.']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function activateWeeklySchedule(): void
    {
        $user = $this->assertProfessorAccess();

        try {
            $this->adminService->activateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0), (int) ($user['conta_id'] ?? 0));
            $this->jsonResponse(['success' => true, 'message' => 'Horário semanal ativado com sucesso.']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function bookingOccurrence(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $scheduleId = (int) ($_GET['horario_id'] ?? 0);
            $startDateTime = trim((string) ($_GET['data_hora_inicio'] ?? ''));
            $bookings = array_map(fn (array $booking): array => $this->maskCpfData($booking), $this->adminService->listOccurrenceBookingsForManagement($scheduleId, $startDateTime));
            $occurrenceDate = new DateTimeImmutable($startDateTime);
            $occurrence = [
                'horario_id' => $scheduleId,
                'data_hora_inicio' => $occurrenceDate->format('Y-m-d H:i:s'),
                'data_label' => $occurrenceDate->format('d/m/Y'),
                'hora_label' => $occurrenceDate->format('H:i'),
                'title' => '',
                'subtitle' => '',
            ];
            if ($bookings !== []) {
                $first = $bookings[0];
                $occurrence['title'] = (string) ($first['modalidade_nome'] ?? '') . ' - ' . ucfirst((string) ($first['tipo_horario'] ?? ''));
                $occurrence['subtitle'] = (string) ($first['local_nome'] ?? '') . ' - ' . (string) ($first['espaco_nome'] ?? '');
            }
            $currentAdminName = (string) ($user['nome_completo'] ?? '');
            $professorView = true;
            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/booking_occurrence_modal_content.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function markBookingAttendance(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $this->adminService->updateBookingAttendanceStatus(
                (int) ($_POST['agendamento_id'] ?? 0),
                trim((string) ($_POST['status'] ?? 'presente')),
                (int) $user['conta_id'],
                trim((string) ($_POST['justificativa_motivo'] ?? '')),
                [
                    'avaliar_modalidade' => !empty($_POST['avaliar_modalidade']),
                    'nivel_slug' => trim((string) ($_POST['nivel_slug'] ?? '')),
                    'observacoes_avaliacao' => trim((string) ($_POST['observacoes_avaliacao'] ?? '')),
                    'confirmar_rebaixamento' => !empty($_POST['confirmar_rebaixamento']),
                ]
            );
            $this->jsonResponse(['success' => true, 'message' => 'Chamada atualizada com sucesso.']);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function updateEnrollmentStatus(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            (new \App\Services\CourseEnrollmentService())->changeStatus(
                (int) ($_POST['inscricao_id'] ?? 0),
                trim((string) ($_POST['status'] ?? '')),
                (int) $user['conta_id'],
                trim((string) ($_POST['motivo'] ?? '')),
                trim((string) ($_POST['suspensa_fim'] ?? '')) ?: null,
                trim((string) ($_POST['token'] ?? '')),
                trim((string) ($_POST['vaga_informada_em'] ?? '')) ?: null,
                !empty($_POST['vaga_informada'])
            );
            $courseEnrollmentService = new \App\Services\CourseEnrollmentService();
            $courseEnrollmentSortBy = trim((string) ($_POST['ordenar_por'] ?? 'ordem_inscricao'));
            $courseEnrollmentSortDirection = trim((string) ($_POST['direcao'] ?? 'asc'));
            $courseEnrollmentStatusFilter = trim((string) ($_POST['status_filtro'] ?? 'todos'));
            $courseEnrollmentConditionFilter = trim((string) ($_POST['condicao_filtro'] ?? 'todas'));
            $courseEnrollmentsManagement = $courseEnrollmentService->listForManagement($courseEnrollmentSortBy, $courseEnrollmentSortDirection, $courseEnrollmentStatusFilter, $courseEnrollmentConditionFilter);
            $courseEnrollmentStatusSummary = $courseEnrollmentService->enrollmentStatusSummaryForManagement();
            $professorView = true;
            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/course_enrollment_panel.php';
            $this->jsonResponse([
                'success' => true,
                'message' => 'Status da inscrição atualizado com sucesso.',
                'panel_html' => (string) ob_get_clean(),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function createEnrollmentToken(): void
    {
        $user = $this->assertProfessorAccess();
        try {
            $token = (new \App\Services\CourseEnrollmentService())->createExceptionToken((int) $user['conta_id'], $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Token criado com sucesso.', 'token' => $token]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function conditionValidationModal(): void
    {
        $this->assertProfessorAccess();
        try { $this->renderValidationModal('condition'); }
        catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function saveConditionValidation(): void
    {
        $this->assertProfessorAccess();
        try {
            $data = $this->adminService->updateConditionValidation((int) ($_POST['person_id'] ?? 0), (string) ($_POST['condition_slug'] ?? ''), (int) Auth::id(), $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Validação do certificado atualizada com sucesso.', 'html' => $this->renderValidationHtml('condition', $data), 'panel_html' => $this->renderPanel('condition')]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function healthCertificateValidationModal(): void
    {
        $this->assertProfessorAccess();
        try { $this->renderValidationModal('health'); }
        catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function saveHealthCertificateValidation(): void
    {
        $this->assertProfessorAccess();
        try {
            $data = $this->adminService->updateHealthCertificateValidation((int) ($_POST['person_id'] ?? 0), (string) ($_POST['certificate_type'] ?? ''), (int) Auth::id(), $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Validação do atestado atualizada com sucesso.', 'html' => $this->renderValidationHtml('health', $data), 'panel_html' => $this->renderPanel('health')]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function certificateDocument(): void
    {
        $this->assertProfessorAccess();
        try {
            $document = $this->adminService->getCertificateDocumentForAdmin((int) ($_GET['document_id'] ?? 0));
            $relativePath = (string) ($document['caminho_armazenado'] ?? '');
            $absolutePath = ROOT_PATH . '/public' . $relativePath;
            if ($relativePath === '' || !is_file($absolutePath)) { throw new \RuntimeException('Arquivo não encontrado.'); }
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: ' . (string) ($document['mime_type'] ?? 'application/pdf'));
            header('Content-Length: ' . (string) filesize($absolutePath));
            header('Content-Disposition: inline; filename="' . rawurlencode(basename((string) ($document['nome_original'] ?? 'documento.pdf'))) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (\Throwable $e) { http_response_code(404); echo 'Arquivo não encontrado.'; exit; }
    }

    public function healthCertificateDocument(): void
    {
        $this->assertProfessorAccess();
        try {
            $document = $this->adminService->getHealthCertificateDocumentForAdmin((int) ($_GET['certificate_id'] ?? 0));
            $relativePath = (string) ($document['caminho_arquivo'] ?? '');
            $absolutePath = ROOT_PATH . '/public' . $relativePath;
            if ($relativePath === '' || !is_file($absolutePath)) { throw new \RuntimeException('Arquivo não encontrado.'); }
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/pdf');
            header('Content-Length: ' . (string) filesize($absolutePath));
            header('Content-Disposition: inline; filename="' . rawurlencode(basename((string) ($document['nome_arquivo'] ?? 'atestado.pdf'))) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (\Throwable $e) { http_response_code(404); echo 'Arquivo não encontrado.'; exit; }
    }

    private function buildPeopleData(): array
    {
        $peopleLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, (int) ($_GET['people_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT)));
        $usersLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, (int) ($_GET['users_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT)));
        return [
            'people' => $this->adminService->listUsersAndDependents($peopleLimit, (string) ($_GET['people_search'] ?? '')),
            'usersOnly' => $this->adminService->listUsersOnly($usersLimit, (string) ($_GET['users_search'] ?? '')),
            'peopleUsersTotals' => $this->adminService->peopleAndUsersTotals(),
            'conditionValidationRows' => $this->adminService->listPeopleRequiringConditionValidation(),
            'healthCertificateValidationRows' => $this->adminService->listPeopleRequiringHealthCertificateValidation(),
            'availableRoles' => [], 'canManageRoles' => false,
            'peopleLimit' => $peopleLimit, 'usersLimit' => $usersLimit,
            'peopleLimitMax' => AdminService::MAX_PEOPLE_LIMIT,
            'peopleSearch' => (string) ($_GET['people_search'] ?? ''),
            'usersSearch' => (string) ($_GET['users_search'] ?? ''),
        ];
    }

    private function buildSectionData(string $sectionName, array $user): array
    {
        if ($sectionName === 'inicio') { return ['sectionName' => $sectionName, 'professorView' => true]; }
        if ($sectionName === 'usuarios-pessoas') { return array_merge(['sectionName' => $sectionName, 'professorView' => true], $this->buildPeopleData()); }
        if ($sectionName === 'inscricoes') {
            $courseEnrollmentService = new \App\Services\CourseEnrollmentService();
            $courseEnrollmentSortBy = trim((string) ($_GET['ordenar_por'] ?? 'ordem_inscricao'));
            $courseEnrollmentSortDirection = trim((string) ($_GET['direcao'] ?? 'asc'));
            $courseEnrollmentStatusFilter = trim((string) ($_GET['status'] ?? 'todos'));
            $courseEnrollmentConditionFilter = trim((string) ($_GET['condicao'] ?? 'todas'));
            return [
                'sectionName' => $sectionName,
                'professorView' => true,
                'courseEnrollmentSortBy' => $courseEnrollmentSortBy,
                'courseEnrollmentSortDirection' => $courseEnrollmentSortDirection,
                'courseEnrollmentStatusFilter' => $courseEnrollmentStatusFilter,
                'courseEnrollmentConditionFilter' => $courseEnrollmentConditionFilter,
                'courseEnrollmentsManagement' => $courseEnrollmentService->listForManagement($courseEnrollmentSortBy, $courseEnrollmentSortDirection, $courseEnrollmentStatusFilter, $courseEnrollmentConditionFilter),
                'courseEnrollmentStatusSummary' => $courseEnrollmentService->enrollmentStatusSummaryForManagement(),
            ];
        }
        if ($sectionName === 'minhas-turmas') {
            $courseService = new \App\Services\CourseEnrollmentService();
            $browser = $courseService->professorClassBrowser((int) ($user['conta_id'] ?? 0));
            return ['sectionName' => $sectionName, 'professorView' => true, 'professorClassSeasons' => (array) ($browser['items'] ?? []),
                'courseSeasons' => $courseService->listSeasonsForManagement(), 'courseSeasonOrigins' => [],
                'modalitySchedules' => $courseService->listModalitySchedulesForManagement(), 'courseProfessors' => $courseService->listProfessors(),
                'courseInterns' => $courseService->listInterns(), 'courseModalitiesManagement' => $this->adminService->listModalitiesForManagement(),
                'courseLocationsManagement' => $this->adminService->listTrainingLocationsForSpaceForm(), 'courseSpacesManagement' => $this->adminService->listTrainingSpacesForManagement()];
        }

        $locationId = (int) ($_GET['local_treino_id'] ?? 0);
        $modalityId = (int) ($_GET['modalidade_id'] ?? 0);
        $dailyDate = trim((string) ($_GET['data_agendamento'] ?? date('Y-m-d')));
        $dailyLocationId = (int) ($_GET['agendamento_local_treino_id'] ?? 0);
        $dailySpaceId = (int) ($_GET['agendamento_espaco_treino_id'] ?? 0);
        return [
            'sectionName' => 'agenda', 'professorView' => true,
            'trainingSpaces' => $this->adminService->listTrainingSpacesForManagement(),
            'modalities' => $this->adminService->listModalitiesForManagement(),
            'scheduleFilterOptions' => (new AgendaService())->activeWeeklyScheduleFilterOptions(true),
            'selectedDailyDate' => $dailyDate, 'selectedDailyLocationId' => $dailyLocationId, 'selectedDailySpaceId' => $dailySpaceId,
            'weeklySchedules' => $this->adminService->listWeeklySchedulesForManagement($locationId, $modalityId, (int) ($user['conta_id'] ?? 0)),
            'specialSchedules' => $this->adminService->listSpecialSchedulesForManagement($locationId, $modalityId),
            'dailyBookings' => array_map(fn (array $booking): array => $this->maskCpfData($booking), $this->adminService->listDailyBookingsForManagement($dailyDate, $dailyLocationId, $dailySpaceId)),
            'currentAdminName' => (string) ($user['nome_completo'] ?? ''),
        ];
    }

    private function renderValidationModal(string $type): void
    {
        $data = $type === 'condition'
            ? $this->adminService->getConditionValidationDetails((int) ($_GET['person_id'] ?? 0), (string) ($_GET['condition_slug'] ?? ''))
            : $this->adminService->getHealthCertificateValidationDetails((int) ($_GET['person_id'] ?? 0), (string) ($_GET['certificate_type'] ?? ''));
        $this->jsonResponse(['success' => true, 'html' => $this->renderValidationHtml($type, $data)]);
    }

    private function renderValidationHtml(string $type, array $data): string
    {
        ob_start();
        $professorView = true;
        extract($data, EXTR_SKIP);
        require ROOT_PATH . '/app/Views/admin/partials/' . ($type === 'condition' ? 'condition_validation_modal.php' : 'health_certificate_validation_modal.php');
        return (string) ob_get_clean();
    }

    private function renderPanel(string $type): string
    {
        $rows = $type === 'condition' ? $this->adminService->listPeopleRequiringConditionValidation() : $this->adminService->listPeopleRequiringHealthCertificateValidation();
        ob_start();
        $professorView = true;
        if ($type === 'condition') { $conditionValidationRows = $rows; require ROOT_PATH . '/app/Views/admin/partials/condition_validation_panel.php'; }
        else { $healthCertificateValidationRows = $rows; require ROOT_PATH . '/app/Views/admin/partials/health_certificate_validation_panel.php'; }
        return (string) ob_get_clean();
    }

    private function maskCpfData(array $data): array
    {
        foreach (['cpf', 'responsavel1_cpf', 'responsavel2_cpf'] as $key) {
            if (isset($data[$key])) { $data[$key] = format_cpf_professor((string) $data[$key]); }
        }
        return $data;
    }

    private function assertProfessorAccess(): array
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) { $this->jsonResponse(['success' => false, 'message' => 'Faça login para acessar a área do professor.', 'redirect' => login_modal_url('/professor')], 401); }
            redirect_to_login_modal('/professor');
        }
        $person = (new ProfileService())->getAuthenticatedPerson();
        if (!$person || (int) ($person['cadastro_completo'] ?? 0) !== 1) {
            if ($this->isAjaxRequest()) { $this->jsonResponse(['success' => false, 'message' => 'Complete primeiro seu cadastro para acessar a área do professor.'], 403); }
            redirect_to_profile_completion_modal('/professor');
        }
        $user = $this->userService->currentAccountWithRoles();
        if ($user && has_role($user['roles'] ?? [], 'teacher')) { return $user; }
        if ($this->isAjaxRequest()) { $this->jsonResponse(['success' => false, 'message' => 'Seu nível de acesso não permite abrir a área do professor.', 'redirect' => url('/')], 403); }
        redirect('/');
    }
}
