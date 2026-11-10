<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\CourseEnrollmentService;
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
        if (!Auth::check()) {
            redirect_to_login_modal('/dashboard');
        }

        $profileService = new ProfileService();
        $person = $profileService->getAuthenticatedPerson();
        $registrationBlock = $profileService->getRegistrationBlockForAuthenticatedPerson();

        if ($registrationBlock !== null) {
            flash('error', $registrationBlock['mensagem']);
            redirect_to_profile_completion_modal('/dashboard');
        }

        if (!$person || (int) $person['cadastro_completo'] !== 1) {
            flash('error', 'Complete primeiro seu cadastro para acessar as páginas autenticadas.');
            redirect_to_profile_completion_modal('/dashboard');
        }

        $userService = new UserService();
        $courseEnrollmentService = new CourseEnrollmentService();
        $user = $userService->currentAccountWithRoles();
        $professorEnrollmentDeletionEnabled = $user && has_role($user['roles'] ?? [], 'teacher');

        $this->view('dashboard/index', [
            'title' => 'Painel do Usuário',
            'pageClass' => 'pagina-dashboard',
            'user' => $user,
            'person' => $person,
            'identityCorrections' => (new \App\Services\IdentityCorrectionService())->list((int) Auth::id()),
            'dependents' => $profileService->listDependents(),
            'bookingHistory' => (new \App\Services\DashboardBookingService())->listForAccount((int) \App\Core\Auth::id(), (int) ($_GET['ano_agendamentos'] ?? 0)),
            'metrics' => $userService->dashboardMetrics((int) $person['id']),
            'courseEnrollments' => $courseEnrollmentService->listForAuthenticatedAccount(),
            'professorEnrollmentDeletionEnabled' => $professorEnrollmentDeletionEnabled,
        ]);
    }
}
