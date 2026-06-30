<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AccountAccessService;
use App\Services\AdminService;
use App\Services\BlogService;
use App\Services\CepService;
use App\Services\ProfileService;
use App\Services\SitePopupService;
use App\Services\UserService;
use App\Services\HomeInfoService;
use App\Services\OfficialCommunicationService;
use DateTimeImmutable;

class AdminController extends Controller
{
    private AdminService $adminService;
    private CepService $cepService;
    private SitePopupService $sitePopupService;
    private UserService $userService;
    private HomeInfoService $homeInfoService;
    private BlogService $blogService;
    private OfficialCommunicationService $officialCommunicationService;

    /**
     * Inicializa servicos da area administrativa.
     */
    public function __construct()
    {
        $this->adminService = new AdminService();
        $this->cepService = new CepService();
        $this->sitePopupService = new SitePopupService();
        $this->userService = new UserService();
        $this->homeInfoService = new HomeInfoService();
        $this->blogService = new BlogService();
        $this->officialCommunicationService = new OfficialCommunicationService();
    }

    /**
     * Exibe a area administrativa inicial.
     */
    public function index(): void
    {
        $user = $this->assertAdminAccess();

        $this->view('admin/index', [
            'title' => 'Area Administrativa',
            'user' => $user,
        ]);
    }

    /**
     * Retorna o HTML de uma secao especifica da area administrativa.
     */
    public function section(): void
    {
        $user = $this->assertAdminAccess();
        $sectionName = (string) ($_GET['nome'] ?? 'inicio');

        try {
            $allowedSections = [
                'inicio',
                'usuarios-pessoas',
                'agenda',
                'pagina-home',
                'blog',
                'locais-espacos',
                'configuracoes',
                'outras-areas',
            ];

            if (!in_array($sectionName, $allowedSections, true)) {
                throw new \RuntimeException('A secao administrativa solicitada nao existe.');
            }

            $sectionData = $this->buildSectionData($sectionName, $user);

            ob_start();
            extract($sectionData, EXTR_SKIP);
            require ROOT_PATH . '/app/Views/admin/partials/section_content.php';
            $html = (string) ob_get_clean();

            $this->jsonResponse([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Salva o quadro "o que voce precisa saber" da home.
     */
    public function saveHomeInfoBox(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->homeInfoService->saveHomeInfoBox((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Quadro da home salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Quadro da home salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva o quadro de comunicacao oficial da home.
     */
    public function saveOfficialCommunication(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $officialCommunication = $this->officialCommunicationService->saveBlogBlock((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Comunicacao oficial salva com sucesso.',
                    'communication' => $officialCommunication,
                    'card_html' => $this->renderOfficialCommunicationCardHtml($officialCommunication),
                ]);
            }

            flash('success', 'Comunicacao oficial salva com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Retorna os dados completos de uma pessoa para edicao inline.
     */
    public function personDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $person = $this->adminService->getPersonDetails((int) ($_GET['id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'person' => $person,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retorna os dados completos de um usuario para consulta em modal.
     */
    public function userDetails(): void
    {
        $this->assertAdminAccess();

        try {
            (new AccountAccessService())->revokeExpiredRolesForAccount((int) ($_GET['id'] ?? 0));
            $user = $this->adminService->getUserDetails((int) ($_GET['id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retorna os dependentes do usuario selecionado para consulta em modal.
     */
    public function userDependents(): void
    {
        $this->assertAdminAccess();

        try {
            (new AccountAccessService())->revokeExpiredRolesForAccount((int) ($_GET['conta_id'] ?? 0));
            $payload = $this->adminService->listUserDependents((int) ($_GET['conta_id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'user' => $payload['user'],
                'dependents' => $payload['dependents'],
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Atualiza os papeis ativos do usuario selecionado.
     */
    public function updateUserRoles(): void
    {
        $user = $this->assertRoleManagementAccess();

        try {
            $updatedUser = $this->adminService->updateUserRoles(
                (int) ($_POST['conta_id'] ?? 0),
                (int) ($user['conta_id'] ?? 0),
                $_POST
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Papeis do usuario atualizados com sucesso.',
                    'user' => $updatedUser,
                ]);
            }

            flash('success', 'Papeis do usuario atualizados com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Retorna os dados completos de um horario semanal para edicao em modal.
     */
    public function weeklyScheduleDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $schedule = $this->adminService->getWeeklyScheduleDetails((int) ($_GET['id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'schedule' => $schedule,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retorna os dados completos de um evento especial para edicao em modal.
     */
    public function specialAgendaEventDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $event = $this->adminService->getSpecialAgendaEventDetails((int) ($_GET['id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'event' => $event,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retorna os eventos do calendario administrativo da agenda.
     */
    public function calendarEvents(): void
    {
        $this->assertAdminAccess();

        try {
            $events = $this->adminService->listCalendarEventsForManagement(
                (int) ($_GET['local_treino_id'] ?? 0),
                (int) ($_GET['modalidade_id'] ?? 0),
                trim((string) ($_GET['start'] ?? '')),
                trim((string) ($_GET['end'] ?? ''))
            );

            $this->jsonResponse($events);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retorna a lista de chamada de uma ocorrencia especifica da agenda administrativa.
     */
    public function bookingOccurrence(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $scheduleId = (int) ($_GET['horario_id'] ?? 0);
            $startDateTime = trim((string) ($_GET['data_hora_inicio'] ?? ''));
            $bookings = $this->adminService->listOccurrenceBookingsForManagement($scheduleId, $startDateTime);

            try {
                $occurrenceDate = new DateTimeImmutable($startDateTime);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Data e horario da ocorrencia sao invalidos.');
            }

            $occurrence = [
                'horario_id' => $scheduleId,
                'data_hora_inicio' => $occurrenceDate->format('Y-m-d H:i:s'),
                'data_label' => $occurrenceDate->format('d/m/Y'),
                'hora_label' => $occurrenceDate->format('H:i'),
                'title' => '',
                'subtitle' => '',
            ];

            if ($bookings !== []) {
                $firstBooking = $bookings[0];
                $occurrence['title'] = (string) ($firstBooking['modalidade_nome'] ?? '') . ' - ' . ucfirst((string) ($firstBooking['tipo_horario'] ?? ''));
                $occurrence['subtitle'] = (string) ($firstBooking['local_nome'] ?? '') . ' - ' . (string) ($firstBooking['espaco_nome'] ?? '');
            } else {
                $schedule = $this->adminService->getWeeklyScheduleDetails($scheduleId);
                $occurrence['title'] = (string) ($schedule['modalidade_nome'] ?? '') . ' - ' . ucfirst((string) ($schedule['tipo_horario'] ?? ''));
                $occurrence['subtitle'] = (string) ($schedule['local_nome'] ?? '') . ' - ' . (string) ($schedule['espaco_nome'] ?? '');
            }

            $currentAdminName = (string) ($user['nome_completo'] ?? '');
            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/booking_occurrence_modal_content.php';
            $html = (string) ob_get_clean();

            $this->jsonResponse([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retorna apenas o quadro de pessoas da area administrativa.
     */
    public function peoplePanel(): void
    {
        $this->assertAdminAccess();

        try {
            (new AccountAccessService())->revokeExpiredRoles();
            $peopleLimit = (int) ($_GET['people_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT);
            $peopleLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, $peopleLimit));
            $peopleSearch = trim((string) ($_GET['people_search'] ?? ''));
            $people = $this->adminService->listUsersAndDependents($peopleLimit, $peopleSearch);
            $usersOnly = $this->adminService->listUsersOnly($peopleLimit, $peopleSearch);
            $conditionValidationRows = $this->adminService->listPeopleRequiringConditionValidation();
            $availableRoles = $this->adminService->listRolesForManagement();
            $peopleLimitMax = AdminService::MAX_PEOPLE_LIMIT;
            $currentAdmin = $this->userService->currentAccountWithRoles();
            $canManageRoles = $currentAdmin ? $this->canManageUserRoles($currentAdmin) : false;

            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/people_panel.php';
            $html = (string) ob_get_clean();

            $this->jsonResponse([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Entrega um PDF de certificado para consulta na area administrativa.
     */
    public function certificateDocument(): void
    {
        $this->assertAdminAccess();

        try {
            $document = $this->adminService->getCertificateDocumentForAdmin((int) ($_GET['document_id'] ?? 0));
            $relativePath = (string) ($document['caminho_armazenado'] ?? '');
            $absolutePath = ROOT_PATH . '/public' . $relativePath;

            if ($relativePath === '' || !is_file($absolutePath)) {
                http_response_code(404);
                echo 'Arquivo nao encontrado.';
                exit;
            }

            $fileName = basename((string) ($document['nome_original'] ?? 'documento.pdf'));
            $mimeType = (string) ($document['mime_type'] ?? 'application/pdf');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string) filesize($absolutePath));
            header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($absolutePath);
            exit;
        } catch (\Throwable $e) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            exit;
        }
    }

    /**
     * Retorna o modal de validacao administrativa de um certificado de condicao.
     */
    public function conditionValidationModal(): void
    {
        $this->assertAdminAccess();

        try {
            $modalData = $this->adminService->getConditionValidationDetails(
                (int) ($_GET['person_id'] ?? 0),
                (string) ($_GET['condition_slug'] ?? '')
            );

            $this->jsonResponse([
                'success' => true,
                'html' => $this->renderConditionValidationModalHtml($modalData),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Salva a validacao administrativa de um certificado de condicao.
     */
    public function saveConditionValidation(): void
    {
        $this->assertAdminAccess();

        try {
            $modalData = $this->adminService->updateConditionValidation(
                (int) ($_POST['person_id'] ?? 0),
                (string) ($_POST['condition_slug'] ?? ''),
                (int) Auth::id(),
                $_POST
            );

            $conditionValidationRows = $this->adminService->listPeopleRequiringConditionValidation();
            $html = $this->renderConditionValidationPanelHtml($conditionValidationRows);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Validacao do certificado atualizada com sucesso.',
                'html' => $this->renderConditionValidationModalHtml($modalData),
                'panel_html' => $html,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retorna o modal de validacao administrativa de um atestado de saude.
     */
    public function healthCertificateValidationModal(): void
    {
        $this->assertAdminAccess();

        try {
            $modalData = $this->adminService->getHealthCertificateValidationDetails(
                (int) ($_GET['person_id'] ?? 0),
                (string) ($_GET['certificate_type'] ?? '')
            );

            $this->jsonResponse([
                'success' => true,
                'html' => $this->renderHealthCertificateValidationModalHtml($modalData),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Salva a validacao administrativa de um atestado de saude.
     */
    public function saveHealthCertificateValidation(): void
    {
        $this->assertAdminAccess();

        try {
            $modalData = $this->adminService->updateHealthCertificateValidation(
                (int) ($_POST['person_id'] ?? 0),
                (string) ($_POST['certificate_type'] ?? ''),
                (int) Auth::id(),
                $_POST
            );

            $panelHtml = $this->renderHealthCertificateValidationPanelHtml(
                $this->adminService->listPeopleRequiringHealthCertificateValidation()
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Validacao do atestado atualizada com sucesso.',
                'html' => $this->renderHealthCertificateValidationModalHtml($modalData),
                'panel_html' => $panelHtml,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Atualiza pessoa e usuario diretamente pela area administrativa.
     */
    public function updatePerson(): void
    {
        $this->assertAdminAccess();

        try {
            $person = $this->adminService->updatePersonAndUser((int) ($_POST['person_id'] ?? 0), $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Dados atualizados com sucesso.',
                    'person' => $person,
                ]);
            }

            flash('success', 'Dados atualizados com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Atualiza a chamada de um agendamento do dia na agenda administrativa.
     */
    public function markBookingAttendance(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $status = trim((string) ($_POST['status'] ?? 'presente'));
            $justificationReason = trim((string) ($_POST['justificativa_motivo'] ?? ''));

            $this->adminService->updateBookingAttendanceStatus(
                (int) ($_POST['agendamento_id'] ?? 0),
                $status,
                (int) $user['conta_id'],
                $justificationReason
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Chamada atualizada com sucesso.',
                ]);
            }

            flash('success', 'Chamada atualizada com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva um novo pop-up do site.
     */
    public function storeSitePopup(): void
    {
        $user = $this->assertPopupManagementAccess();

        try {
            $this->sitePopupService->create((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Pop-up salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Pop-up salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Arquiva ou reativa um pop-up do site.
     */
    public function updateSitePopupStatus(): void
    {
        $this->assertPopupManagementAccess();

        try {
            $this->sitePopupService->updateStatus(
                (int) ($_POST['site_popup_id'] ?? 0),
                (string) ($_POST['status'] ?? '')
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Status do pop-up atualizado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Status do pop-up atualizado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Exclui logicamente um pop-up do site.
     */
    public function deleteSitePopup(): void
    {
        $this->assertPopupManagementAccess();

        try {
            $this->sitePopupService->delete((int) ($_POST['site_popup_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Pop-up excluido com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Pop-up excluido com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva uma nova postagem do blog.
     */
    public function storePost(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $isEdit = (int) ($_POST['post_id'] ?? 0) > 0;
            $post = $this->blogService->savePost((int) $user['conta_id'], $_POST, $_FILES);
            $message = $isEdit ? 'Postagem atualizada com sucesso.' : 'Postagem salva com sucesso.';

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => $message,
                    'post' => $post,
                ]);
            }

            flash('success', $message);
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Remove uma postagem existente do blog.
     */
    public function deletePost(): void
    {
        $this->assertAdminAccess();

        try {
            $this->blogService->deletePost((int) ($_POST['post_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Postagem removida com sucesso.',
                ]);
            }

            flash('success', 'Postagem removida com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Retorna os dados de uma postagem para edicao em modal.
     */
    public function postDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $post = $this->blogService->getPostForAdmin((int) ($_GET['id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'post' => $post,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Salva uma nova suspensao temporaria de espaco.
     */
    public function storeSpaceSuspension(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createSpaceSuspension((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Suspensao de espaco salva com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Suspensao de espaco salva com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Inativa uma suspensao temporaria de espaco.
     */
    public function deactivateSpaceSuspension(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateSpaceSuspension((int) ($_POST['suspensao_espaco_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Suspensao de espaco inativada com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Suspensao de espaco inativada com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva um novo horario semanal.
     */
    public function storeWeeklySchedule(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createWeeklySchedule((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horario semanal criado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horario semanal criado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Atualiza um horario semanal existente.
     */
    public function updateWeeklySchedule(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $schedule = $this->adminService->updateWeeklySchedule(
                (int) ($_POST['horario_semanal_id'] ?? 0),
                (int) $user['conta_id'],
                $_POST
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horario semanal atualizado com sucesso.',
                    'schedule' => $schedule,
                ]);
            }

            flash('success', 'Horario semanal atualizado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Inativa um horario semanal existente.
     */
    public function deactivateWeeklySchedule(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horario semanal inativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horario semanal inativado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Ativa novamente um horario semanal existente.
     */
    public function activateWeeklySchedule(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->activateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horario semanal ativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horario semanal ativado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva um novo evento sazonal/informativo para a agenda publica.
     */
    public function storeSpecialAgendaEvent(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createSpecialAgendaEvent((int) $user['conta_id'], $_POST, $_FILES);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Evento especial salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Evento especial salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Inativa um evento sazonal/informativo da agenda publica.
     */
    public function deactivateSpecialAgendaEvent(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateSpecialAgendaEvent((int) ($_POST['agenda_evento_especial_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Evento especial inativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Evento especial inativado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Atualiza um evento especial existente.
     */
    public function updateSpecialAgendaEvent(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->updateSpecialAgendaEvent(
                (int) ($_POST['agenda_evento_especial_id'] ?? 0),
                (int) $user['conta_id'],
                $_POST,
                $_FILES
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Evento especial atualizado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Evento especial atualizado com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva uma excecao de CEP fora do intervalo padrao.
     */
    public function storeCepException(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->cepService->createCepException((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'CEP de excecao salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'CEP de excecao salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Remove um CEP da lista de excecoes.
     */
    public function deleteCepException(): void
    {
        $this->assertAdminAccess();

        try {
            $this->cepService->deleteCepException((int) ($_POST['cep_excecao_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'CEP de excecao removido com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'CEP de excecao removido com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Salva um novo intervalo aceito de CEP.
     */
    public function storeAcceptedRange(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->cepService->createAcceptedRange((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Intervalo de CEP aceito salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Intervalo de CEP aceito salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Remove um intervalo aceito de CEP.
     */
    public function deleteAcceptedRange(): void
    {
        $this->assertAdminAccess();

        try {
            $this->cepService->deleteAcceptedRange((int) ($_POST['cep_intervalo_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Intervalo de CEP removido com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Intervalo de CEP removido com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            flash('error', $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Garante que a conta atual tenha papel de gestao.
     */
    private function assertAdminAccess(): array
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Faca login para acessar a area administrativa.',
                    'redirect' => login_modal_url('/admin'),
                ], 401);
            }

            redirect_to_login_modal('/admin');
        }

        $profileService = new ProfileService();
        $person = $profileService->getAuthenticatedPerson();
        $registrationBlock = $profileService->getRegistrationBlockForAuthenticatedPerson();

        if ($registrationBlock !== null) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $registrationBlock['mensagem'],
                    'redirect' => profile_completion_modal_url('/admin'),
                ], 403);
            }

            flash('error', $registrationBlock['mensagem']);
            redirect_to_profile_completion_modal('/admin');
        }

        if (!$person || (int) ($person['cadastro_completo'] ?? 0) !== 1) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Complete primeiro seu cadastro para acessar a area administrativa.',
                    'redirect' => profile_completion_modal_url('/admin'),
                ], 403);
            }

            flash('error', 'Complete primeiro seu cadastro para acessar a area administrativa.');
            redirect_to_profile_completion_modal('/admin');
        }

        $user = $this->userService->currentAccountWithRoles();
        $allowed = ['master_admin', 'admin', 'supervisor', 'coordinator'];

        foreach ($allowed as $slug) {
            if (has_role($user['roles'] ?? [], $slug)) {
                return $user;
            }
        }

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Seu nivel de acesso nao permite abrir a area administrativa.',
                'redirect' => url('/dashboard'),
            ], 403);
        }

        flash('error', 'Seu nivel de acesso nao permite abrir a area administrativa.');
        redirect('/dashboard');
    }

    /**
     * Restringe a gestao de pop-ups a administradores master e administradores.
     */
    private function assertPopupManagementAccess(): array
    {
        $user = $this->assertAdminAccess();

        if ($this->canManageSitePopups($user)) {
            return $user;
        }

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Somente Administrador Master e Administrador podem gerenciar os pop-ups do site.',
                'redirect' => url('/admin'),
            ], 403);
        }

        flash('error', 'Somente Administrador Master e Administrador podem gerenciar os pop-ups do site.');
        redirect('/admin');
    }

    /**
     * Restringe a gestao de papeis a administradores master e administradores.
     */
    private function assertRoleManagementAccess(): array
    {
        $user = $this->assertAdminAccess();

        if ($this->canManageUserRoles($user)) {
            return $user;
        }

        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Somente Administrador Master e Administrador podem gerenciar papeis de usuario.',
                'redirect' => url('/admin'),
            ], 403);
        }

        flash('error', 'Somente Administrador Master e Administrador podem gerenciar papeis de usuario.');
        redirect('/admin');
    }

    /**
     * Informa se a conta atual pode gerenciar pop-ups do site.
     */
    private function canManageSitePopups(array $user): bool
    {
        return has_role($user['roles'] ?? [], 'master_admin') || has_role($user['roles'] ?? [], 'admin');
    }

    /**
     * Informa se a conta atual pode gerenciar papeis de usuario.
     */
    private function canManageUserRoles(array $user): bool
    {
        return has_role($user['roles'] ?? [], 'master_admin') || has_role($user['roles'] ?? [], 'admin');
    }

    /**
     * Monta apenas os dados necessarios para a secao solicitada.
     */
    private function buildSectionData(string $sectionName, array $user): array
    {
        $data = [
            'sectionName' => $sectionName,
        ];

        if ($sectionName === 'usuarios-pessoas') {
            (new AccountAccessService())->revokeExpiredRoles();
            $peopleLimit = (int) ($_GET['people_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT);
            $peopleLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, $peopleLimit));
            $data['peopleSearch'] = trim((string) ($_GET['people_search'] ?? ''));

            $data['people'] = $this->adminService->listUsersAndDependents($peopleLimit, (string) $data['peopleSearch']);
            $data['usersOnly'] = $this->adminService->listUsersOnly($peopleLimit, (string) $data['peopleSearch']);
            $data['conditionValidationRows'] = $this->adminService->listPeopleRequiringConditionValidation();
            $data['healthCertificateValidationRows'] = $this->adminService->listPeopleRequiringHealthCertificateValidation();
            $data['availableRoles'] = $this->adminService->listRolesForManagement();
            $data['canManageRoles'] = $this->canManageUserRoles($user);
            $data['peopleLimit'] = $peopleLimit;
            $data['peopleLimitMax'] = AdminService::MAX_PEOPLE_LIMIT;
        }

        if ($sectionName === 'agenda') {
            $locationId = (int) ($_GET['local_treino_id'] ?? 0);
            $modalityId = (int) ($_GET['modalidade_id'] ?? 0);
            $dailyDate = trim((string) ($_GET['data_agendamento'] ?? date('Y-m-d')));
            $dailyLocationId = (int) ($_GET['agendamento_local_treino_id'] ?? 0);
            $dailySpaceId = (int) ($_GET['agendamento_espaco_treino_id'] ?? 0);

            try {
                $normalizedDailyDate = DateTimeImmutable::createFromFormat('Y-m-d', $dailyDate);
                $dailyDate = $normalizedDailyDate instanceof DateTimeImmutable && $normalizedDailyDate->format('Y-m-d') === $dailyDate
                    ? $normalizedDailyDate->format('Y-m-d')
                    : date('Y-m-d');
            } catch (\Throwable $e) {
                $dailyDate = date('Y-m-d');
            }

            $data['trainingSpaces'] = $this->adminService->listTrainingSpacesForManagement();
            $data['modalities'] = $this->adminService->listModalitiesForManagement();
            $data['selectedLocationId'] = $locationId > 0 ? $locationId : 0;
            $data['selectedModalityId'] = $modalityId > 0 ? $modalityId : 0;
            $data['selectedDailyDate'] = $dailyDate;
            $data['selectedDailyLocationId'] = $dailyLocationId > 0 ? $dailyLocationId : 0;
            $data['selectedDailySpaceId'] = $dailySpaceId > 0 ? $dailySpaceId : 0;
            $data['weeklySchedules'] = $this->adminService->listWeeklySchedulesForManagement($locationId, $modalityId);
            $data['specialAgendaEvents'] = $this->adminService->listSpecialAgendaEventsForManagement($locationId, $modalityId);
            $data['dailyBookings'] = $this->adminService->listDailyBookingsForManagement($dailyDate, $dailyLocationId, $dailySpaceId);
            $data['currentAdminName'] = (string) ($user['nome_completo'] ?? '');
        }

        if ($sectionName === 'pagina-home') {
            $data['canManageSitePopups'] = $this->canManageSitePopups($user);
            $data['sitePopups'] = $this->sitePopupService->listAll();
            $data['popupPages'] = $this->sitePopupService->availablePages();
            $data['homeInfoBox'] = $this->homeInfoService->getHomeInfoBox();
            $data['homeInfoMaxParagraphs'] = HomeInfoService::MAX_PARAGRAPHS;
            $data['homeInfoMaxTitleLength'] = HomeInfoService::MAX_TITLE_LENGTH;
            $data['homeInfoMaxParagraphLength'] = HomeInfoService::MAX_PARAGRAPH_LENGTH;
        }

        if ($sectionName === 'blog') {
            $data['officialCommunication'] = $this->officialCommunicationService->getBlogBlock();
            $data['posts'] = $this->blogService->listPostsForAdmin();
            $data['blogSummary'] = $this->blogService->adminSummary();
            $data['blogCategories'] = $this->blogService->listPublicCategories();
            $data['blogSpecialEvents'] = $this->adminService->listPublishedSpecialAgendaEvents('blog', 20);
        }

        if ($sectionName === 'locais-espacos') {
            $data['trainingSpaces'] = $this->adminService->listTrainingSpacesForManagement();
            $data['spaceSuspensions'] = $this->adminService->listSpaceSuspensionsForManagement();
        }

        if ($sectionName === 'configuracoes') {
            $data['acceptedRanges'] = $this->cepService->listAcceptedRanges();
            $data['cepExceptions'] = $this->cepService->listCepExceptions();
        }

        return $data;
    }

    /**
     * Renderiza o modal de validacao administrativa de condicao.
     */
    private function renderConditionValidationModalHtml(array $modalData): string
    {
        ob_start();
        extract($modalData, EXTR_SKIP);
        require ROOT_PATH . '/app/Views/admin/partials/condition_validation_modal.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza somente o quadro da fila administrativa de condicoes.
     */
    private function renderConditionValidationPanelHtml(array $conditionValidationRows): string
    {
        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/condition_validation_panel.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza o modal de validacao administrativa de atestado de saude.
     */
    private function renderHealthCertificateValidationModalHtml(array $modalData): string
    {
        ob_start();
        extract($modalData, EXTR_SKIP);
        require ROOT_PATH . '/app/Views/admin/partials/health_certificate_validation_modal.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza o card de comunicacao oficial no admin.
     */
    private function renderOfficialCommunicationCardHtml(array $officialCommunication): string
    {
        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/official_communication_card.php';
        return (string) ob_get_clean();
    }

    /**
     * Renderiza somente o quadro da fila administrativa de atestados.
     */
    private function renderHealthCertificateValidationPanelHtml(array $healthCertificateValidationRows): string
    {
        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/health_certificate_validation_panel.php';
        return (string) ob_get_clean();
    }
}
