<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AgendaService;
use App\Services\ProfileService;
use App\Services\UserService;

class DashboardController extends Controller
{
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
            flash('error', 'Complete primeiro seu cadastro para acessar as paginas autenticadas.');
            redirect_to_profile_completion_modal('/dashboard');
        }

        $userService = new UserService();
        $agendaService = new AgendaService();
        $user = $userService->currentAccountWithRoles();

        $this->view('dashboard/index', [
            'title' => 'Painel do Usuario',
            'user' => $user,
            'person' => $person,
            'dependents' => $profileService->listDependents(),
            'metrics' => $userService->dashboardMetrics((int) $person['id']),
            'locations' => $agendaService->listLocations(),
        ]);
    }
}
