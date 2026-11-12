<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\CourseEnrollmentService;
use App\Services\DashboardBookingService;
use App\Services\ProfileService;
use App\Services\UserService;

class DashboardController extends Controller
{
    public function requestIdentityCorrection(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Faça login para enviar a solicitação.'], 401);
            }
            redirect_to_login_modal('/dashboard');
        }

        $service = new \App\Services\IdentityCorrectionService();
        try {
            \App\Services\IdentityCorrectionService::assertToken($_POST);
            $service->submit((int) Auth::id(), $_POST, $_FILES['documento'] ?? []);
            if ($this->isAjaxRequest()) {
                $identityCorrections = $service->list((int) Auth::id());
                $dashboardCorrectionHistory = true;
                ob_start();
                require ROOT_PATH . '/app/Views/dashboard/partials/identity_correction_status.php';
                $this->jsonResponse(['success' => true, 'message' => 'Solicitação recebida. Acompanhe o andamento no seu painel.', 'status_html' => (string) ob_get_clean()]);
            }
            flash('success', 'Solicitação recebida. Acompanhe o andamento no seu painel.');
        } catch (\PDOException $e) {
            error_log($e->getMessage());
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Não foi possível registrar a solicitação. Tente novamente mais tarde.'], 500);
            }
            flash('error', 'Não foi possível registrar a solicitação. Tente novamente mais tarde.');
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
        }

        redirect('/dashboard#solicitacoes-correcao');
    }

    /**
     * Exibe o painel principal autenticado.
     */
    public function index(): void
    {
        $bookingRequest = $this->isAjaxRequest() && ($_GET['painel'] ?? '') === 'agendamentos';
        if (!Auth::check()) {
            if ($bookingRequest) {
                $this->jsonResponse(['success' => false, 'message' => 'Faça login para consultar seus agendamentos.'], 401);
            }
            redirect_to_login_modal('/dashboard');
        }

        $profileService = new ProfileService();
        $person = $profileService->getAuthenticatedPerson();
        $registrationBlock = $profileService->getRegistrationBlockForAuthenticatedPerson();

        if ($registrationBlock !== null || !$person || (int) $person['cadastro_completo'] !== 1) {
            $message = $registrationBlock['mensagem'] ?? 'Complete primeiro seu cadastro para acessar as páginas autenticadas.';
            if ($bookingRequest) {
                $this->jsonResponse(['success' => false, 'message' => $message], 403);
            }
            flash('error', $message);
            redirect_to_profile_completion_modal('/dashboard');
        }

        $bookingHistory = (new DashboardBookingService())->listForAccount(
            (int) Auth::id(), (int) ($_GET['ano_agendamentos'] ?? 0), (int) ($_GET['mes_agendamentos'] ?? 0)
        );
        if ($bookingRequest) {
            ob_start();
            require ROOT_PATH . '/app/Views/dashboard/partials/booking_history.php';
            $this->jsonResponse(['success' => true, 'html' => (string) ob_get_clean()]);
        }

        $userService = new UserService();
        $courseEnrollmentService = new CourseEnrollmentService();
        $user = $userService->currentAccountWithRoles();
        $professorEnrollmentDeletionEnabled = $user && has_role($user['roles'] ?? [], 'teacher');
        $personId = (int) $person['id'];
        $dependents = array_column($profileService->listDependents(), null, 'id');
        $person = $dependents[$personId] ?? $profileService->getManagedDependent($personId);
        unset($dependents[$personId]);
        $metrics = $userService->dashboardMetrics($personId);
        $metrics['dependentes'] = count($dependents);

        $this->view('dashboard/index', [
            'title' => 'Painel do Usuário',
            'pageClass' => 'pagina-dashboard',
            'user' => $user,
            'person' => $person,
            'identityCorrections' => (new \App\Services\IdentityCorrectionService())->list((int) Auth::id()),
            'dependents' => array_values($dependents),
            'bookingHistory' => $bookingHistory,
            'metrics' => $metrics,
            'courseEnrollmentHistory' => $courseEnrollmentService->dashboardEnrollments(
                (int) ($_GET['temporada_inscricoes'] ?? 0), (int) ($_GET['inscricao_destaque'] ?? 0)
            ),
            'professorEnrollmentDeletionEnabled' => $professorEnrollmentDeletionEnabled,
        ]);
    }
}
