<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AccountAccessService;
use App\Services\AdminService;
use App\Services\AgendaService;
use App\Services\BlogService;
use App\Services\CepService;
use App\Services\ProfileService;
use App\Services\SitePopupService;
use App\Services\UserService;
use App\Services\HomeInfoService;
use App\Services\OfficialCommunicationService;
use App\Services\ExternalPersonService;
use App\Services\ExternalLocationService;
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
    private ExternalPersonService $externalPersonService;
    private ExternalLocationService $externalLocationService;

    /**
     * Inicializa servicos da área administrativa.
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
        $this->externalPersonService = new ExternalPersonService();
        $this->externalLocationService = new ExternalLocationService();
    }

    /**
     * Exibe a área administrativa inicial.
     */
    public function index(): void
    {
        $user = $this->assertAdminAccess();

        $this->view('admin/index', [
            'title' => 'Área Administrativa',
            'user' => $user,
        ]);
    }

    /**
     * Retorna o HTML de uma seção especifica da área administrativa.
     */
    public function section(): void
    {
        $user = $this->assertAdminAccess();
        $sectionName = (string) ($_GET['nome'] ?? 'inicio');

        try {
            $allowedSections = [
                'inicio',
                'usuarios-pessoas',
                'migracao-cadastros',
                'agenda',
                'pagina-home',
                'pop-ups',
                'blog',
                'locais-espacos',
                'configuracoes',
                'outras-areas',
            ];

            if (!in_array($sectionName, $allowedSections, true)) {
                throw new \RuntimeException('A seção administrativa solicitada não existe.');
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
     * Salva o quadro "o que você precisa saber" da home.
     */
    public function saveHomeInfoBox(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->homeInfoService->saveHomeInfoBox((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Rascunho do quadro salvo com sucesso.',
                    'content' => $this->homeInfoService->getHomeInfoBoxForAdmin(),
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
                    'message' => 'Comunicação oficial salva com sucesso.',
                    'communication' => $officialCommunication,
                    'card_html' => $this->renderOfficialCommunicationCardHtml($officialCommunication),
                ]);
            }

            flash('success', 'Comunicação oficial salva com sucesso.');
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
     * Retorna os dados completos de uma pessoa para edição inline.
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
     * Retorna os dependentes do usuário selecionado para consulta em modal.
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
     * Atualiza os papéis ativos do usuário selecionado.
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
                    'message' => 'Papéis do usuário atualizados com sucesso.',
                    'user' => $updatedUser,
                ]);
            }

            flash('success', 'Papéis do usuário atualizados com sucesso.');
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
     * Retorna os dados completos de um horário semanal para edição em modal.
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
     * Retorna os dados completos de um horário especial para edição em modal.
     */
    public function specialScheduleDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $event = $this->adminService->getSpecialScheduleDetails((int) ($_GET['id'] ?? 0));
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
     * Retorna os eventos do calendário administrativo da agenda.
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
     * Retorna a lista de chamada de uma ocorrência especifica da agenda administrativa.
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
                throw new \RuntimeException('Data e horário da ocorrência são inválidos.');
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
     * Retorna apenas o quadro de pessoas da área administrativa.
     */
    public function peoplePanel(): void
    {
        $this->assertAdminAccess();

        try {
            (new AccountAccessService())->revokeExpiredRoles();
            $peopleLimit = (int) ($_GET['people_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT);
            $peopleLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, $peopleLimit));
            $usersLimit = (int) ($_GET['users_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT);
            $usersLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, $usersLimit));
            $peopleSearch = (string) ($_GET['people_search'] ?? '');
            $usersSearch = (string) ($_GET['users_search'] ?? '');
            $people = $this->adminService->listUsersAndDependents($peopleLimit, $peopleSearch);
            $usersOnly = $this->adminService->listUsersOnly($usersLimit, $usersSearch);
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

    /** Retorna somente a lista administrativa da migração externa. */
    public function externalMigrationPanel(): void
    {
        $this->assertAdminAccess();

        try {
            $migrationLimit = max(1, min(
                ExternalPersonService::MAX_LIST_LIMIT,
                (int) ($_GET['migration_limit'] ?? ExternalPersonService::DEFAULT_LIST_LIMIT)
            ));
            $migrationSearch = (string) ($_GET['migration_search'] ?? '');
            $migrationLimitMax = ExternalPersonService::MAX_LIST_LIMIT;
            $migrationRows = $this->externalPersonService->listForAdmin($migrationLimit, $migrationSearch);
            if ((string) ($_GET['skip_summary'] ?? '') === '1') {
                $migrationSummary = [
                    'total' => max(0, (int) ($_GET['summary_total'] ?? 0)),
                    'cpfs' => max(0, (int) ($_GET['summary_cpfs'] ?? 0)),
                    'pendentes' => max(0, (int) ($_GET['summary_pendentes'] ?? 0)),
                    'migrados' => max(0, (int) ($_GET['summary_migrados'] ?? 0)),
                ];
            } else {
                $migrationSummary = $this->externalPersonService->adminSummary();
            }

            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/external_migration_panel.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Retorna todos os dados de uma linha importada. */
    public function externalMigrationDetails(): void
    {
        $this->assertAdminAccess();

        try {
            $migrationRecord = $this->externalPersonService->getAdminDetails((int) ($_GET['id'] ?? 0));
            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/external_migration_details.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Importa ou atualiza a tabela local a partir do banco de origem. */
    public function importExternalMigration(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $result = $this->externalPersonService->importBatch(
                (int) ($user['conta_id'] ?? 0),
                max(0, (int) ($_POST['cursor'] ?? 0)),
                isset($_POST['base_max_id_externo']) && $_POST['base_max_id_externo'] !== ''
                    ? max(0, (int) $_POST['base_max_id_externo'])
                    : null,
                isset($_POST['alterado_desde']) && trim((string) $_POST['alterado_desde']) !== ''
                    ? trim((string) $_POST['alterado_desde'])
                    : null
            );
            $this->jsonResponse([
                'success' => true,
                'processados' => (int) ($result['processados'] ?? 0),
                'proximo_cursor' => (int) ($result['proximo_cursor'] ?? 0),
                'tem_mais' => !empty($result['tem_mais']),
                'base_max_id_externo' => (int) ($result['base_max_id_externo'] ?? 0),
                'alterado_desde' => (string) ($result['alterado_desde'] ?? ''),
                'message' => (int) ($result['processados'] ?? 0) . ' registros externos foram processados neste lote.',
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Exclui uma linha da tabela local de migração. */
    public function deleteExternalMigration(): void
    {
        $this->assertAdminAccess();

        try {
            $this->externalPersonService->deleteForAdmin((int) ($_POST['id'] ?? 0));
            $this->jsonResponse(['success' => true, 'message' => 'Registro de migração removido com sucesso.']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Entrega um PDF de certificado para consulta na área administrativa.
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
                echo 'Arquivo não encontrado.';
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
            echo 'Arquivo não encontrado.';
            exit;
        }
    }

    /**
     * Retorna o modal de validação administrativa de um certificado de condicao.
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
     * Salva a validação administrativa de um certificado de condicao.
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
                'message' => 'Validação do certificado atualizada com sucesso.',
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
     * Retorna o modal de validação administrativa de um atestado de saude.
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
     * Salva a validação administrativa de um atestado de saude.
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
                'message' => 'Validação do atestado atualizada com sucesso.',
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
     * Atualiza pessoa e usuário diretamente pela área administrativa.
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
     * Retorna os dados de uma postagem para edição em modal.
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
     * Cadastra um novo local de treino.
     */
    public function storeTrainingLocation(): void
    {
        $this->assertAdminAccess();

        try {
            $locationId = $this->adminService->createTrainingLocation($_POST);
            $this->externalLocationService->consumeLocation(
                (int) ($_POST['local_externo_migracao_id'] ?? 0),
                $locationId
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Local de treino cadastrado com sucesso.',
                    'locations_html' => $this->renderTrainingLocationRows(),
                    'location' => [
                        'id' => $locationId,
                        'nome_local' => trim((string) ($_POST['nome_local'] ?? '')),
                        'apelido_local' => trim((string) ($_POST['apelido_local'] ?? '')),
                    ],
                ]);
            }

            flash('success', 'Local de treino cadastrado com sucesso.');
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

    public function saveHomeHighlightCards(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $this->homeInfoService->saveHighlightCards((int) $user['conta_id'], $_POST);
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => true, 'message' => 'Rascunho dos destaques salvo com sucesso.', 'content' => $this->homeInfoService->getHighlightCardsForAdmin()]);
            }
            flash('success', 'Quadros destacados salvos com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }
        redirect('/admin');
    }

    public function saveHomeHeroContent(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $this->homeInfoService->saveHeroContent((int) $user['conta_id'], $_POST);
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => true, 'message' => 'Rascunho do quadro principal salvo com sucesso.', 'content' => $this->homeInfoService->getHeroContentForAdmin()]);
            }
            flash('success', 'Quadro principal salvo com sucesso.');
        } catch (\Throwable $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }
        redirect('/admin');
    }

    public function saveHomeHeaderContent(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $this->homeInfoService->saveHeaderContent((int) $user['conta_id'], $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Rascunho do logotipo e contato salvo.', 'content' => $this->homeInfoService->getHeaderContentForAdmin()]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveHomeLogoContent(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $this->homeInfoService->saveLogoContent((int) $user['conta_id'], $_POST, $_FILES);
            $this->jsonResponse(['success' => true, 'message' => 'Rascunho do logotipo salvo.', 'content' => $this->homeInfoService->getLogoContent(true)]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function saveHomeContactContent(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $this->homeInfoService->saveContactContent((int) $user['conta_id'], $_POST);
            $this->jsonResponse(['success' => true, 'message' => 'Rascunho do contato salvo.', 'content' => $this->homeInfoService->getContactContent(true)]);
        } catch (\Throwable $e) { $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function publishHomeContent(): void
    {
        $user = $this->assertAdminAccess();
        try {
            $content = $this->homeInfoService->publishContent((string) ($_POST['chave'] ?? ''), (int) $user['conta_id']);
            $this->jsonResponse(['success' => true, 'message' => 'Conteúdo publicado na página Home.', 'content' => $content]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Lista os locais copiados do sistema anterior para selecao no cadastro. */
    public function externalTrainingLocations(): void
    {
        $this->assertAdminAccess();

        try {
            $summary = $this->externalLocationService->importOnce();
            $this->jsonResponse([
                'success' => true,
                'summary' => $summary,
                'locations' => $this->externalLocationService->listLocations((string) ($_GET['search'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Lista os espacos do sistema anterior apenas para consulta. */
    public function externalTrainingSpaces(): void
    {
        $this->assertAdminAccess();

        try {
            $summary = $this->externalLocationService->importOnce();
            $this->jsonResponse([
                'success' => true,
                'summary' => $summary,
                'spaces' => $this->externalLocationService->listSpaces((string) ($_GET['search'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Atualiza um local de treino existente.
     */
    public function updateTrainingLocation(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->updateTrainingLocation($_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Local de treino atualizado com sucesso.',
                    'locations_html' => $this->renderTrainingLocationRows(),
                ]);
            }

            flash('success', 'Local de treino atualizado com sucesso.');
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
     * Retorna somente as linhas filtradas da lista de locais de treino.
     */
    public function trainingLocationList(): void
    {
        $this->assertAdminAccess();

        try {
            $locationLimit = (int) ($_GET['location_limit'] ?? AdminService::DEFAULT_TRAINING_LOCATION_LIMIT);
            $locationLimit = max(1, min(AdminService::MAX_TRAINING_LOCATION_LIMIT, $locationLimit));
            $trainingLocations = $this->adminService->listTrainingLocationsForManagement(
                (string) ($_GET['location_search'] ?? ''),
                $locationLimit
            );

            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/training_location_rows.php';
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
     * Retorna somente as linhas filtradas da lista de espaços de treino.
     */
    public function trainingSpaceList(): void
    {
        $this->assertAdminAccess();

        try {
            $spaceLimit = (int) ($_GET['space_limit'] ?? AdminService::DEFAULT_TRAINING_SPACE_LIMIT);
            $spaceLimit = max(1, min(AdminService::MAX_TRAINING_SPACE_LIMIT, $spaceLimit));
            $trainingSpaces = $this->adminService->listTrainingSpacesForManagement(
                (string) ($_GET['space_search'] ?? ''),
                $spaceLimit
            );

            ob_start();
            require ROOT_PATH . '/app/Views/admin/partials/training_space_rows.php';
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

    public function storeTrainingSpace(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->createTrainingSpace($_POST);
            $this->externalLocationService->consumeSpace((int) ($_POST['espaco_externo_migracao_id'] ?? 0));
            $this->jsonResponse([
                'success' => true,
                'message' => 'Espaço de treino cadastrado com sucesso.',
                'spaces_html' => $this->renderSpaceManagementFragments()['spaces_html'],
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateTrainingSpace(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->updateTrainingSpace($_POST);
            $this->jsonResponse([
                'success' => true,
                'message' => 'Espaço de treino atualizado com sucesso.',
                'spaces_html' => $this->renderSpaceManagementFragments()['spaces_html'],
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Salva uma nova suspensão temporaria de espaco.
     */
    public function storeSpaceSuspension(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createSpaceSuspension((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $fragments = $this->renderSpaceManagementFragments();
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Suspensão de espaço salva com sucesso.',
                    'spaces_html' => $fragments['spaces_html'],
                    'suspensions_html' => $fragments['suspensions_html'],
                ]);
            }

            flash('success', 'Suspensão de espaço salva com sucesso.');
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
     * Inativa uma suspensão temporaria de espaco.
     */
    public function deactivateSpaceSuspension(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateSpaceSuspension((int) ($_POST['suspensao_espaco_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $fragments = $this->renderSpaceManagementFragments();
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Suspensão de espaço inativada com sucesso.',
                    'spaces_html' => $fragments['spaces_html'],
                    'suspensions_html' => $fragments['suspensions_html'],
                ]);
            }

            flash('success', 'Suspensão de espaço inativada com sucesso.');
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
     * Exclui uma suspensão futura antes do início da vigência.
     */
    public function deleteFutureSpaceSuspension(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deleteFutureSpaceSuspension((int) ($_POST['suspensao_espaco_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $fragments = $this->renderSpaceManagementFragments();
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Suspensão futura excluída com sucesso.',
                    'spaces_html' => $fragments['spaces_html'],
                    'suspensions_html' => $fragments['suspensions_html'],
                ]);
            }

            flash('success', 'Suspensão futura excluída com sucesso.');
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
     * Salva um novo horário semanal.
     */
    public function storeWeeklySchedule(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createWeeklySchedule((int) $user['conta_id'], $_POST);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário semanal criado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário semanal criado com sucesso.');
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
     * Atualiza um horário semanal existente.
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
                    'message' => 'Horário semanal atualizado com sucesso.',
                    'schedule' => $schedule,
                ]);
            }

            flash('success', 'Horário semanal atualizado com sucesso.');
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
     * Inativa um horário semanal existente.
     */
    public function deactivateWeeklySchedule(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário semanal inativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário semanal inativado com sucesso.');
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
     * Ativa novamente um horário semanal existente.
     */
    public function activateWeeklySchedule(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->activateWeeklySchedule((int) ($_POST['horario_semanal_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário semanal ativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário semanal ativado com sucesso.');
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
     * Salva um novo horário especial para a agenda pública.
     */
    public function storeSpecialSchedule(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->createSpecialSchedule((int) $user['conta_id'], $_POST, $_FILES);

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário especial salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário especial salvo com sucesso.');
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
     * Inativa um horário especial da agenda pública.
     */
    public function deactivateSpecialSchedule(): void
    {
        $this->assertAdminAccess();

        try {
            $this->adminService->deactivateSpecialSchedule((int) ($_POST['agenda_horario_especial_id'] ?? 0));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário especial inativado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário especial inativado com sucesso.');
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
     * Atualiza um horário especial existente.
     */
    public function updateSpecialSchedule(): void
    {
        $user = $this->assertAdminAccess();

        try {
            $this->adminService->updateSpecialSchedule(
                (int) ($_POST['agenda_horario_especial_id'] ?? 0),
                (int) $user['conta_id'],
                $_POST,
                $_FILES
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Horário especial atualizado com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'Horário especial atualizado com sucesso.');
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
                    'message' => 'CEP de exceção salvo com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'CEP de exceção salvo com sucesso.');
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
                    'message' => 'CEP de exceção removido com sucesso.',
                    'redirect' => url('/admin'),
                ]);
            }

            flash('success', 'CEP de exceção removido com sucesso.');
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
                    'message' => 'Faca login para acessar a área administrativa.',
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
                    'message' => 'Complete primeiro seu cadastro para acessar a área administrativa.',
                    'redirect' => profile_completion_modal_url('/admin'),
                ], 403);
            }

            flash('error', 'Complete primeiro seu cadastro para acessar a área administrativa.');
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
                'message' => 'Seu nível de acesso não permite abrir a área administrativa.',
                'redirect' => url('/dashboard'),
            ], 403);
        }

        flash('error', 'Seu nível de acesso não permite abrir a área administrativa.');
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
                'message' => 'Somente Administrador Master e Administrador podem gerenciar papéis de usuário.',
                'redirect' => url('/admin'),
            ], 403);
        }

        flash('error', 'Somente Administrador Master e Administrador podem gerenciar papéis de usuário.');
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
     * Monta apenas os dados necessarios para a seção solicitada.
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
            $usersLimit = (int) ($_GET['users_limit'] ?? AdminService::DEFAULT_PEOPLE_LIMIT);
            $usersLimit = max(1, min(AdminService::MAX_PEOPLE_LIMIT, $usersLimit));
            $data['peopleSearch'] = (string) ($_GET['people_search'] ?? '');
            $data['usersSearch'] = (string) ($_GET['users_search'] ?? '');

            $data['people'] = $this->adminService->listUsersAndDependents($peopleLimit, (string) $data['peopleSearch']);
            $data['usersOnly'] = $this->adminService->listUsersOnly($usersLimit, (string) $data['usersSearch']);
            $data['conditionValidationRows'] = $this->adminService->listPeopleRequiringConditionValidation();
            $data['healthCertificateValidationRows'] = $this->adminService->listPeopleRequiringHealthCertificateValidation();
            $data['availableRoles'] = $this->adminService->listRolesForManagement();
            $data['canManageRoles'] = $this->canManageUserRoles($user);
            $data['peopleLimit'] = $peopleLimit;
            $data['usersLimit'] = $usersLimit;
            $data['peopleLimitMax'] = AdminService::MAX_PEOPLE_LIMIT;
        }

        if ($sectionName === 'migracao-cadastros') {
            $migrationLimit = (int) ($_GET['migration_limit'] ?? ExternalPersonService::DEFAULT_LIST_LIMIT);
            $migrationLimit = max(1, min(ExternalPersonService::MAX_LIST_LIMIT, $migrationLimit));
            $data['migrationSearch'] = (string) ($_GET['migration_search'] ?? '');
            $data['migrationLimit'] = $migrationLimit;
            $data['migrationLimitMax'] = ExternalPersonService::MAX_LIST_LIMIT;
            $data['migrationRows'] = $this->externalPersonService->listForAdmin($migrationLimit, (string) $data['migrationSearch']);
            $data['migrationSummary'] = $this->externalPersonService->adminSummary();
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

            if ($dailySpaceId > 0) {
                $selectedDailySpace = null;

                foreach ($data['trainingSpaces'] as $trainingSpace) {
                    if ((int) ($trainingSpace['id'] ?? 0) === $dailySpaceId) {
                        $selectedDailySpace = $trainingSpace;
                        break;
                    }
                }

                if (
                    $selectedDailySpace === null
                    || (
                        $dailyLocationId > 0
                        && (int) ($selectedDailySpace['local_treino_id'] ?? 0) !== $dailyLocationId
                    )
                ) {
                    $dailySpaceId = 0;
                }
            }

            $data['modalities'] = $this->adminService->listModalitiesForManagement();
            $data['selectedLocationId'] = $locationId > 0 ? $locationId : 0;
            $data['selectedModalityId'] = $modalityId > 0 ? $modalityId : 0;
            $data['selectedDailyDate'] = $dailyDate;
            $data['selectedDailyLocationId'] = $dailyLocationId > 0 ? $dailyLocationId : 0;
            $data['selectedDailySpaceId'] = $dailySpaceId > 0 ? $dailySpaceId : 0;
            $data['weeklySchedules'] = $this->adminService->listWeeklySchedulesForManagement($locationId, $modalityId);
            $data['specialSchedules'] = $this->adminService->listSpecialSchedulesForManagement($locationId, $modalityId);
            $data['dailyBookings'] = $this->adminService->listDailyBookingsForManagement($dailyDate, $dailyLocationId, $dailySpaceId);
            $data['currentAdminName'] = (string) ($user['nome_completo'] ?? '');
        }

        if ($sectionName === 'pop-ups') {
            $data['canManageSitePopups'] = $this->canManageSitePopups($user);
            $data['sitePopups'] = $this->sitePopupService->listAll();
            $data['popupPages'] = $this->sitePopupService->availablePages();
        }

        if ($sectionName === 'pagina-home') {
            $data['homeInfoBox'] = $this->homeInfoService->getHomeInfoBoxForAdmin();
            $data['homeHighlightCards'] = $this->homeInfoService->getHighlightCardsForAdmin();
            $data['homeHeroContent'] = $this->homeInfoService->getHeroContentForAdmin();
            $data['homeHeaderContent'] = array_merge($this->homeInfoService->getLogoContent(true), $this->homeInfoService->getContactContent(true));
            $data['locations'] = (new AgendaService())->listLocations();
            $data['suggestedLocations'] = array_slice($data['locations'], 0, 3);
            $data['posts'] = $this->blogService->listPublishedPosts(['limit' => 3]);
            $data['homeSpecialEvents'] = $this->adminService->listPublishedSpecialSchedules('home', 3);
            $data['blogSpecialEvents'] = $this->adminService->listPublishedSpecialSchedules('blog', 6);
            $data['homeInfoMaxParagraphs'] = HomeInfoService::MAX_PARAGRAPHS;
            $data['homeInfoMaxTitleLength'] = HomeInfoService::MAX_TITLE_LENGTH;
            $data['homeInfoMaxParagraphLength'] = HomeInfoService::MAX_PARAGRAPH_LENGTH;
        }

        if ($sectionName === 'blog') {
            $data['officialCommunication'] = $this->officialCommunicationService->getBlogBlock();
            $data['posts'] = $this->blogService->listPostsForAdmin();
            $data['blogSummary'] = $this->blogService->adminSummary();
            $data['blogCategories'] = $this->blogService->listPublicCategories();
            $data['blogSpecialEvents'] = $this->adminService->listPublishedSpecialSchedules('blog', 20);
        }

        if ($sectionName === 'locais-espacos') {
            $data['locationSearch'] = (string) ($_GET['location_search'] ?? '');
            $data['locationLimit'] = (int) ($_GET['location_limit'] ?? AdminService::DEFAULT_TRAINING_LOCATION_LIMIT);
            $data['locationLimit'] = max(1, min(AdminService::MAX_TRAINING_LOCATION_LIMIT, (int) $data['locationLimit']));
            $data['locationLimitMax'] = AdminService::MAX_TRAINING_LOCATION_LIMIT;
            $data['trainingLocations'] = $this->adminService->listTrainingLocationsForManagement(
                (string) $data['locationSearch'],
                (int) $data['locationLimit']
            );
            $data['eligibleLocationManagers'] = $this->adminService->listEligibleLocationManagers();
            $data['spaceSearch'] = (string) ($_GET['space_search'] ?? '');
            $data['spaceLimit'] = (int) ($_GET['space_limit'] ?? AdminService::DEFAULT_TRAINING_SPACE_LIMIT);
            $data['spaceLimit'] = max(1, min(AdminService::MAX_TRAINING_SPACE_LIMIT, (int) $data['spaceLimit']));
            $data['spaceLimitMax'] = AdminService::MAX_TRAINING_SPACE_LIMIT;
            $data['spaceFormLocations'] = $this->adminService->listTrainingLocationsForSpaceForm();
            $data['eligibleSpaceSupervisors'] = $this->adminService->listEligibleSpaceSupervisors();
            $data['trainingSpaces'] = $this->adminService->listTrainingSpacesForManagement(
                (string) $data['spaceSearch'],
                (int) $data['spaceLimit']
            );
            $data['spaceSuspensions'] = $this->adminService->listSpaceSuspensionsForManagement();
        }

        if ($sectionName === 'configuracoes') {
            $data['acceptedRanges'] = $this->cepService->listAcceptedRanges();
            $data['cepExceptions'] = $this->cepService->listCepExceptions();
        }

        return $data;
    }

    /**
     * Renderiza o modal de validação administrativa de condicao.
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
     * Renderiza o modal de validação administrativa de atestado de saude.
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

    /**
     * Renderiza os fragmentos atualizados da gestão de espaços e suspensões.
     */
    private function renderSpaceManagementFragments(): array
    {
        $spaceSearch = trim((string) ($_POST['space_search'] ?? ''));
        $spaceLimit = (int) ($_POST['space_limit'] ?? AdminService::DEFAULT_TRAINING_SPACE_LIMIT);
        $spaceLimit = max(1, min(AdminService::MAX_TRAINING_SPACE_LIMIT, $spaceLimit));
        $trainingSpaces = $this->adminService->listTrainingSpacesForManagement($spaceSearch, $spaceLimit);
        $spaceSuspensions = $this->adminService->listSpaceSuspensionsForManagement();

        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/training_space_rows.php';
        $spacesHtml = (string) ob_get_clean();

        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/location_suspension_rows.php';
        $suspensionsHtml = (string) ob_get_clean();

        return [
            'spaces_html' => $spacesHtml,
            'suspensions_html' => $suspensionsHtml,
        ];
    }

    /** Renderiza novamente as linhas da lista de locais apos salvar por AJAX. */
    private function renderTrainingLocationRows(): string
    {
        $locationSearch = trim((string) ($_POST['location_search'] ?? ''));
        $locationLimit = (int) ($_POST['location_limit'] ?? AdminService::DEFAULT_TRAINING_LOCATION_LIMIT);
        $locationLimit = max(1, min(AdminService::MAX_TRAINING_LOCATION_LIMIT, $locationLimit));
        $trainingLocations = $this->adminService->listTrainingLocationsForManagement($locationSearch, $locationLimit);

        ob_start();
        require ROOT_PATH . '/app/Views/admin/partials/training_location_rows.php';
        return (string) ob_get_clean();
    }
}
